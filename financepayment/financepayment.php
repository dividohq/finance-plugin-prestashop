<?php
/**
* 2007-2018 PrestaShop
*
* NOTICE OF LICENSE
*
* This source file is subject to the Academic Free License (AFL 3.0)
* that is bundled with this package in the file LICENSE.txt.
* It is also available through the world-wide-web at this URL:
* http://opensource.org/licenses/afl-3.0.php
* If you did not receive a copy of the license and are unable to
* obtain it through the world-wide-web, please send an email
* to license@prestashop.com so we can send you a copy immediately.
*
* DISCLAIMER
*
* Do not edit or add to this file if you wish to upgrade PrestaShop to newer
* versions in the future. If you wish to customize PrestaShop for your
* needs please refer to http://www.prestashop.com for more information.
*
*  @author    PrestaShop SA <contact@prestashop.com>
*  @copyright 2007-2018 PrestaShop SA
*  @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
*  International Registered Trademark & Property of PrestaShop SA
*/

if (!defined('_PS_VERSION_')) {
    exit;
}

require_once dirname(__FILE__) . '/vendor/autoload.php';
require_once dirname(__FILE__) . '/classes/divido.class.php';
require_once dirname(__FILE__) . '/classes/DividoHelper.php';

use Divido\MerchantSDK\Environment;
use Divido\MerchantSDK\Exceptions\InvalidApiKeyFormatException;
use Divido\MerchantSDK\Exceptions\InvalidEnvironmentException;
use Divido\Helper\DividoHelper;
use Divido\Proxy\FinanceApi;
use Divido\Proxy\EnvironmentUnhealthyException;
use Divido\Proxy\EnvironmentUrlException;
use Divido\Proxy\Merchant_SDK;

class NoFinancePlansException extends Exception
{
}

class BadApiKeyException extends Exception
{
}

class FinancePayment extends PaymentModule
{
    public $ps_below_7;

    public $ApiOrderStatus = array(
        array(
            'code' => 'ACCEPTED',
        ),
        array(
            'code' => 'DEPOSIT-PAID',
        ),
        array(
            'code' => 'SIGNED',
        ),
        array(
            'code' => 'READY',
        ),
        array(
            'code' => 'ACTION-LENDER',
        ),
        array(
            'code' => 'CANCELLED',
        ),
        array(
            'code' => 'COMPLETED',
        ),
        array(
            'code' => 'DECLINED',
        ),
        array(
            'code' => 'DEFERRED',
        ),
        array(
            'code' => 'REFERRED',
        ),
        array(
            'code' => 'FULFILLED',
        ),
    );

    const SUPPORTED_LANGUAGES = ['gb', 'no', 'fi', 'da', 'fr', 'es', 'pe', 'en', 'de'];

    public function __construct()
    {
        $this->name = 'financepayment';
        $this->tab = 'payments_gateways';
        $this->version = '2.4.1';
        $this->author = 'Divido Financial Services Ltd';
        $this->need_instance = 0;
        $this->module_key = "71b50f7f5d75c244cd0a5635f664cd56";

        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('Powered By Divido');
        $this->checkoutTitleDefault = $this->l('Pay in instalments');
        $this->checkoutDescriptionDefault = $this->l('Break your purchase down into smaller payments');
        $this->confirmUninstall = $this->l('uninstall_alert');

        /*------Version Check-------------*/
        $this->ps_below_7 = Tools::version_compare(_PS_VERSION_, '1.7', '<');

        $this->ps_versions_compliancy = array('min' => '1.6', 'max' => _PS_VERSION_);

    }

    /**
     * @return bool
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    public function install()
    {
        Configuration::updateValue('FINANCE_ENVIRONMENT_URL', null);
        Configuration::updateValue('FINANCE_API_KEY', null);
        Configuration::updateValue('FINANCE_ENVIRONMENT', null);
        Configuration::updateValue('FINANCE_HMAC', null);
        Configuration::updateValue('FINANCE_PAYMENT_TITLE', $this->checkoutTitleDefault);
        Configuration::updateValue('FINANCE_PAYMENT_DESCRIPTION', $this->checkoutDescriptionDefault);
        Configuration::updateValue('FINANCE_ACTIVATION_STATUS', Configuration::get('PS_OS_DELIVERED'));
        Configuration::updateValue('FINANCE_CANCELLATION_STATUS', Configuration::get('PS_OS_CANCELED'));
        Configuration::updateValue('FINANCE_REFUND_STATUS', '7');
        Configuration::updateValue('FINANCE_PRODUCT_WIDGET', true);
        Configuration::updateValue('FINANCE_PRODUCT_CALCULATOR', null);
        Configuration::updateValue('FINANCE_PRODUCT_WIDGET_BUTTON_TEXT', null);
        Configuration::updateValue('FINANCE_PRODUCT_WIDGET_FOOTNOTE', '');
        Configuration::updateValue('FINANCE_ALL_PLAN_SELECTION', true);
        Configuration::updateValue('FINANCE_PLAN_SELECTION', null);
        Configuration::updateValue('FINANCE_CART_MINIMUM', '0');
        Configuration::updateValue('FINANCE_CART_MAXIMUM', '10000');
        Configuration::updateValue('FINANCE_PRODUCTS_OPTIONS', 'All');
        Configuration::updateValue('FINANCE_PRODUCTS_MINIMUM', '0');

        foreach ($this->ApiOrderStatus as $ApiStatus) {
            switch ($ApiStatus['code']) {
                case 'ACCEPTED':
                case 'DEPOSIT-PAID':
                case 'ACTION-LENDER':
                case 'DEFERRED':
                case 'REFERRED':
                    $status = Configuration::get('PS_OS_PREPARATION');

                    break;

                case 'SIGNED':
                case 'READY':
                case 'COMPLETED':
                    $status = Configuration::get('PS_OS_PAYMENT');

                    break;

                case 'CANCELLED':
                case 'DECLINED':
                    $status = Configuration::get('PS_OS_CANCELED');

                    break;

                case 'FULFILLED':
                    $status = Configuration::get('PS_OS_DELIVERED');

                    break;
                default:
                    $status = Configuration::get('PS_OS_PREPARATION');

                    break;
                case 'REFUNDED':
                    $status = Configuration::get('PS_OS_REFUNDED');

                    break;
            }
            Configuration::updateValue('FINANCE_STATUS_'.$ApiStatus['code'], $status);
        }

        include_once dirname(__FILE__).'/sql/install.php';

        if (!parent::install()
            || !$this->registerHook('payment')
            || !$this->registerHook('header')
            || !$this->registerHook('actionAdminControllerSetMedia')
            || !$this->registerHook('displayFooterProduct')
            || !$this->registerHook('displayProductPriceBlock')
            || !$this->registerHook('displayAdminProductsExtra')
            || !$this->registerHook('actionProductUpdate')
            || !$this->registerHook('actionOrderStatusUpdate')
            || !$this->registerHook('paymentReturn')
        ) {
            return false;
        }
        $status = array();
        $status['module_name'] = $this->name;
        $status['send_email'] = false;
        $status['invoice'] = false;
        $status['unremovable'] = true;
        $status['paid'] = false;
        $state = $this->addState($this->l('awaiting_finance_response'), '#0404B4', $status);
        Configuration::updateValue('FINANCE_AWAITING_STATUS', $state);

        /*------------------Handle hooks according to version-------------------*/
        if ($this->ps_below_7 && !$this->registerHook('payment')) {
            Configuration::updateValue('FINANCE_PAYMENT_DESCRIPTION', $this->displayName);

            return false;
        } elseif (!$this->ps_below_7 && !$this->registerHook('paymentOptions')) {
            return false;
        }

        return true;
    }

    /**
     * @param $name
     * @param $color
     * @param $status
     * @return int
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    private function addState($name, $color, $status)
    {
        $order_state = new OrderState();
        $order_state->name = array_fill(0, 10, $name);
        $order_state->name[$this->context->language->id] = $name;
        $order_state->module_name = $status['module_name'];
        $order_state->send_email = $status['send_email'];
        $order_state->color = $color;
        $order_state->hidden = false;
        $order_state->unremovable = $status['unremovable'];
        $order_state->delivery = false;
        $order_state->logable = false;
        $order_state->invoice = $status['invoice'];
        $order_state->paid = $status['paid'];
        if ($order_state->add()) {
            if (file_exists(dirname(__FILE__).'/logo.gif')) {
                copy(dirname(__FILE__).'/logo.gif', dirname(__FILE__).'/../../img/os/'.(int) $order_state->id.'.gif');
            }
        }

        return $order_state->id;
    }

    /**
     * @return bool
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    public function uninstall()
    {
        Configuration::deleteByName('FINANCE_ENVIRONMENT_URL');
        Configuration::deleteByName('FINANCE_API_KEY');
        Configuration::deleteByName('FINANCE_HMAC');
        Configuration::deleteByName('FINANCE_ENVIRONMENT');
        Configuration::deleteByName('FINANCE_PAYMENT_TITLE');
        Configuration::deleteByName('FINANCE_ACTIVATION_STATUS');
        Configuration::deleteByName('FINANCE_CANCELLATION_STATUS');
        Configuration::deleteByName('FINANCE_REFUND_STATUS');
        Configuration::deleteByName('FINANCE_REFUNDED_STATUS');
        Configuration::deleteByName('FINANCE_PRODUCT_WIDGET');
        Configuration::deleteByName('FINANCE_PRODUCT_CALCULATOR');
        Configuration::deleteByName('FINANCE_PRODUCT_WIDGET_BUTTON_TEXT');
        Configuration::deleteByName('FINANCE_PRODUCT_WIDGET_FOOTNOTE');
        Configuration::deleteByName('FINANCE_LANGUAGE_OVERRIDE');
        Configuration::deleteByName('FINANCE_ALL_PLAN_SELECTION');
        Configuration::deleteByName('FINANCE_PLAN_SELECTION');
        Configuration::deleteByName('FINANCE_CART_MINIMUM');
        Configuration::deleteByName('FINANCE_CART_MAXIMUM');
        Configuration::deleteByName('FINANCE_PRODUCTS_OPTIONS');
        Configuration::deleteByName('FINANCE_PRODUCTS_MINIMUM');

        /*------------------Handle hooks according to version-------------------*/
        if (!$this->ps_below_7) {
            Configuration::deleteByName('FINANCE_PAYMENT_DESCRIPTION');
        }

        foreach ($this->ApiOrderStatus as $ApiStatus) {
            Configuration::deleteByName('FINANCE_STATUS_'.$ApiStatus['code']);
        }
        $id_state = Configuration::get('FINANCE_AWAITING_STATUS');
        $order_state = new OrderState($id_state);
        if (Validate::isLoadedObject($order_state)) {
            $order_state->delete();
            Configuration::deleteByName('FINANCE_AWAITING_STATUS');
            if (file_exists(dirname(__FILE__).'/../../img/os/'.(int) $order_state->id.'.gif')) {
                unlink(dirname(__FILE__).'/../../img/os/'.(int) $order_state->id.'.gif');
            }
        }
        include_once dirname(__FILE__).'/sql/uninstall.php';

        return parent::uninstall();
    }

    /**
     * Load the configuration form
     */
    public function getContent()
    {
        /**
         * If values have been submitted in the form, process.
         */
        $error = '';
        if (((bool) Tools::isSubmit('submitFinanceModule')) == true) {
            $error = $this->postProcess();
        }

        return $error.$this->renderForm();
    }

    /**
     * Create the form that will be displayed in the configuration of the module.
     */
    protected function renderForm()
    {
        $helper = new HelperForm();

        $helper->show_toolbar = false;
        $helper->table = $this->table;
        $helper->module = $this;
        $helper->default_form_language = $this->context->language->id;
        $helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG', 0);

        $helper->identifier = $this->identifier;
        $helper->submit_action = 'submitFinanceModule';
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false)
            .'&configure='.$this->name.'&tab_module='.$this->tab.'&module_name='.$this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');

        $helper->tpl_vars = array(
            'fields_value' => $this->getConfigFormValues(), /* Add values for your inputs */
            'languages' => $this->context->controller->getLanguages(),
            'id_language' => $this->context->language->id,
        );

        return $helper->generateForm(array($this->getConfigForm()));
    }

    /**
     * Create the structure of the form.
     */
    protected function getConfigForm()
    {
        $form = array(
            'form' => array(
                'legend' => array(
                    'title' => $this->l('settings_label'),
                    'icon' => 'icon-cogs',
                ),

                'error' => '',
                'warning' => '',
                'description' => '',

                'input' => array(
                    array(
                        'type'  => 'text',
                        'name'  => 'FINANCE_ENVIRONMENT_URL',
                        'label' => $this->l('environment_url_label'),
                        'hint'  => $this->l('environment_url_description'),
                    ),
                    array(
                        'type'  => 'text',
                        'name'  => 'FINANCE_API_KEY',
                        'label' => $this->l('api_key_label'),
                        'hint'  => $this->l('api_key_description'),
                        'required' => true,
                    )
                ),
                'submit' => array(
                    'title' => $this->l('save_label'),
                ),
            ),
        );

        /*----------------------Display form only after key is inserted----------------------------*/
        if (Configuration::get('FINANCE_API_KEY')) {
            $api = new FinanceApi();
            $api_key = Configuration::get('FINANCE_API_KEY');

            /*-------If no Environment URL, apply appropriate internal multitenant URL-----------*/
            if (!Configuration::get('FINANCE_ENVIRONMENT_URL')) {
                $env = Environment::getEnvironmentFromAPIKey($api_key);
                $multitenant_environment_url = Environment::CONFIGURATION[$env]['base_uri'];

                Configuration::updateValue('FINANCE_ENVIRONMENT_URL', $multitenant_environment_url);

                $form['form']['description'] = $this->l('environment_url_label') . ': ' . $multitenant_environment_url;
            };

            try {
                $api->checkEnviromentHealth();

                $finance_environment = $api->getFinanceEnv($api_key);

                if ($finance_environment === NULL) {
                    throw new BadApiKeyException();
                }

                Configuration::updateValue('FINANCE_ENVIRONMENT', $finance_environment);

                $financePlans = $this->getPlans();

                if ($financePlans === NULL) {
                    throw new NoFinancePlansException();
                };

                $orderStatus = OrderState::getOrderStates($this->context->language->id);
                $product_options = array(
                    array(
                        'type' => 'All',
                        'name' => $this->l('finance_all_products_option'),
                    ),
                    array(
                        'type' => 'product_selected',
                        'name' => $this->l('finance_specific_products_option'),
                    ),
                    array(
                        'type' => 'min_price',
                        'name' => $this->l('finance_threshold_products_option'),
                    )
                );
                $form['form']['input'][] = array(
                    'type'  => 'text',
                    'name'  => 'FINANCE_HMAC',
                    'label' => $this->l('shared_secret_label'),
                    'hint'  => $this->l('shared_secret_description'),
                );
                $form['form']['input'][] = array(
                    'type'  => 'text',
                    'name'  => 'FINANCE_PAYMENT_TITLE',
                    'label' => $this->l('checkout_title_label'),
                    'hint'  => $this->l('checkout_title_description'),
                );
                if (!$this->ps_below_7) {
                    $form['form']['input'][] = array(
                        'type'  => 'text',
                        'name'  => 'FINANCE_PAYMENT_DESCRIPTION',
                        'label' => $this->l('checkout_description_label'),
                        'hint'  => $this->l('checkout_description_description'),
                    );
                }
                $form['form']['input'][] = array(
                    'type'    => 'select',
                    'name'    => 'FINANCE_ACTIVATION_STATUS',
                    'label'   => $this->l('activate_on_status_label'),
                    'hint'    => $this->l('activate_on_status_description'),
                    'options' => array(
                        'query' => $orderStatus,
                        'id'    => 'id_order_state',
                        'name'  => 'name',
                    ),
                );
                $form['form']['input'][] = array(
                    'type'    => 'select',
                    'name'    => 'FINANCE_CANCELLATION_STATUS',
                    'label'   => $this->l('cancel_on_status_label'),
                    'hint'    => $this->l('cancel_on_status_description'),
                    'options' => array(
                        'query' => $orderStatus,
                        'id'    => 'id_order_state',
                        'name'  => 'name',
                    ),
                );
                $form['form']['input'][] = array(
                    'type'    => 'select',
                    'name'    => 'FINANCE_REFUND_STATUS',
                    'label'   => $this->l('refund_on_status_label'),
                    'hint'    => $this->l('refund_on_status_description'),
                    'options' => array(
                        'query' => $orderStatus,
                        'id'    => 'id_order_state',
                        'name'  => 'name',
                    ),
                );
                $form['form']['input'][] = array(
                    'type'    => 'switch',
                    'name'    => 'FINANCE_ALL_PLAN_SELECTION',
                    'label'   => $this->l('show_all_plans_option'),
                    'is_bool' => true,
                    'values'  => array(
                        array(
                            'id'    => 'active_on',
                            'value' => true,
                            'label' => $this->l('Yes')
                        ),
                        array(
                            'id'    => 'active_off',
                            'value' => false,
                            'label' => $this->l('No')
                        )
                    ),
                );
                $form['form']['input'][] = array(
                    'type'    => 'swap',
                    'name'    => 'FINANCE_PLAN_SELECTION',
                    'label'   => $this->l('select_specific_plans_option'),
                    'options' => array(
                        'query' => $financePlans,
                        'name'  => 'text',
                        'id'    => 'id',
                    ),
                );
                $form['form']['input'][] = array(
                    'type'    => 'switch',
                    'name'    => 'FINANCE_PRODUCT_WIDGET',
                    'label'   => $this->l('show_widget_label'),
                    'hint'    => $this->l('show_widget_description'),
                    'is_bool' => true,
                    'values'  => array(
                        array(
                            'id'    => 'active_on',
                            'value' => true,
                            'label' => $this->l('Yes')
                        ),
                        array(
                            'id'    => 'active_off',
                            'value' => false,
                            'label' => $this->l('No')
                        )
                    ),
                );
                $form['form']['input'][] = array(
                    'type'    => 'switch',
                    'name'    => 'FINANCE_PRODUCT_CALCULATOR',
                    'label'   => $this->l('product_calculator_title'),
                    'is_bool' => true,
                    'values'  => array(
                        array(
                            'id'    => 'active_on',
                            'value' => true,
                            'label' => $this->l('Yes')
                        ),
                        array(
                            'id'    => 'active_off',
                            'value' => false,
                            'label' => $this->l('No')
                        )
                    ),
                );
                $form['form']['input'][] = array(
                    'type'    => 'switch',
                    'name'    => 'FINANCE_LANGUAGE_OVERRIDE',
                    'hint'    => $this->l('use_store_language_description'),
                    'label'   => $this->l('use_store_language_label'),
                    'is_bool' => true,
                    'values'  => array(
                        array(
                            'id'    => 'active_on',
                            'value' => true,
                            'label' => $this->l('Yes')
                        ),
                        array(
                            'id'    => 'active_off',
                            'value' => false,
                            'label' => $this->l('No')
                        )
                    ),
                );
                $form['form']['input'][] = array(
                    'type'  => 'text',
                    'name'  => 'FINANCE_PRODUCT_WIDGET_BUTTON_TEXT',
                    'label' => $this->l('widget_button_text_label'),
                    'hint'  => $this->l('widget_button_text_description'),
                );
                $form['form']['input'][] = array(
                    'type'  => 'text',
                    'name'  => 'FINANCE_PRODUCT_WIDGET_FOOTNOTE',
                    'label' => $this->l('widget_footnote_label'),
                    'hint'  => $this->l('widget_footnote_description')
                );
                $form['form']['input'][] = array(
                    'type'  => 'text',
                    'name'  => 'FINANCE_CART_MINIMUM',
                    'label' => $this->l('cart_threshold_label'),
                    'hint'  => $this->l('cart_threshold_description')
                );

                $form['form']['input'][] = array(
                    'type'  => 'text',
                    'name'  => 'FINANCE_CART_MAXIMUM',
                    'label' => $this->l('cart_maximum_label'),
                    'hint'  => $this->l('cart_maximum_description')
                );
                $form['form']['input'][] = array(
                    'type'    => 'select',
                    'name'    => 'FINANCE_PRODUCTS_OPTIONS',
                    'label'   => $this->l('product_selection_label'),
                    'hint'    => $this->l('product_selection_description'),
                    'options' => array(
                        'query' => $product_options,
                        'id'    => 'type',
                        'name'  => 'name',
                    ),
                );
                $form['form']['input'][] = array(
                    'type'  => 'text',
                    'name'  => 'FINANCE_PRODUCTS_MINIMUM',
                    'label' => $this->l('product_price_threshold_label'),
                    'hint'  => $this->l('product_price_threshold_description')
                );
                $form['form']['input'][] = array(
                    'type' => 'html',
                    'name' => $this->l('map_statuses_description').":",
                );
                foreach ($this->ApiOrderStatus as $ApiStatus) {
                    $form['form']['input'][] = array(
                        'type'    => 'select',
                        'name'    => 'FINANCE_STATUS_'.$ApiStatus['code'],
                        'label'   => $ApiStatus['code'],
                        'options' => array(
                            'query' => $orderStatus,
                            'id'    => 'id_order_state',
                            'name'  => 'name',
                        ),
                    );
                }
            } catch (EnvironmentUrlException $e) {
                $form['form']['error'] = $this->l('environment_url_error') . '? ';
            } catch (EnvironmentUnhealthyException $e) {
                $form['form']['error'] = $this->l('environment_url_error') . '? ' . '<br>'
                                            . $this->l('environment_unhealthy_error_msg') . ' ' 
                                            . $e->getMessage();
            } catch (InvalidEnvironmentException | BadApiKeyException $e) {
                $form['form']['error'] = $this->l('invalid_api_key_error');
            } catch (InvalidApiKeyFormatException $e) {
                $form['form']['error'] = $this->l('invalid_api_key_error') . '<br>'
                                            . $e->getMessage();
            } catch (NoFinancePlansException $e) {
                $form['form']['warning'] = $this->l('finance_no_plans');
            }
        }; 

        return $form;
    }

    /**
     * Set values for the inputs.
     */
    protected function getConfigFormValues()
    {
        $form_values = array(
            'FINANCE_ENVIRONMENT_URL'=> Configuration::get('FINANCE_ENVIRONMENT_URL'),
            'FINANCE_API_KEY' => Configuration::get('FINANCE_API_KEY'),
            'FINANCE_HMAC' => Configuration::get('FINANCE_HMAC'),
            'FINANCE_ENVIRONMENT' => Configuration::get('FINANCE_ENVIRONMENT'),
            'FINANCE_PAYMENT_TITLE' => Configuration::get('FINANCE_PAYMENT_TITLE'),
            'FINANCE_ACTIVATION_STATUS' => Configuration::get('FINANCE_ACTIVATION_STATUS'),
            'FINANCE_CANCELLATION_STATUS' => Configuration::get('FINANCE_CANCELLATION_STATUS'),
            'FINANCE_REFUND_STATUS' => Configuration::get('FINANCE_REFUND_STATUS'),
            'FINANCE_ALL_PLAN_SELECTION' => Configuration::get('FINANCE_ALL_PLAN_SELECTION'),
            'FINANCE_PLAN_SELECTION' => explode(',', Configuration::get('FINANCE_PLAN_SELECTION')),
            'FINANCE_PRODUCT_WIDGET' => Configuration::get('FINANCE_PRODUCT_WIDGET'),
            'FINANCE_PRODUCT_CALCULATOR' => Configuration::get('FINANCE_PRODUCT_CALCULATOR'),
            'FINANCE_LANGUAGE_OVERRIDE' => Configuration::get('FINANCE_LANGUAGE_OVERRIDE'),
            'FINANCE_PRODUCT_WIDGET_SUFFIX' => Configuration::get('FINANCE_PRODUCT_WIDGET_SUFFIX'),
            'FINANCE_PRODUCT_WIDGET_PREFIX' => Configuration::get('FINANCE_PRODUCT_WIDGET_PREFIX'),
            'FINANCE_PRODUCT_WIDGET_BUTTON_TEXT' => Configuration::get('FINANCE_PRODUCT_WIDGET_BUTTON_TEXT'),
            'FINANCE_PRODUCT_WIDGET_FOOTNOTE' => Configuration::get('FINANCE_PRODUCT_WIDGET_FOOTNOTE'),
            'FINANCE_CART_MINIMUM' => Configuration::get('FINANCE_CART_MINIMUM'),
            'FINANCE_CART_MAXIMUM' => Configuration::get('FINANCE_CART_MAXIMUM'),
            'FINANCE_PRODUCTS_OPTIONS' => Configuration::get('FINANCE_PRODUCTS_OPTIONS'),
            'FINANCE_PRODUCTS_MINIMUM' => Configuration::get('FINANCE_PRODUCTS_MINIMUM')
        );

        if (!$this->ps_below_7) {
            $form_values['FINANCE_PAYMENT_DESCRIPTION'] = Configuration::get('FINANCE_PAYMENT_DESCRIPTION');
        }
        foreach ($this->ApiOrderStatus as $ApiStatus) {
            $form_values['FINANCE_STATUS_'.$ApiStatus['code']] =
            Configuration::get('FINANCE_STATUS_'.$ApiStatus['code']);
        }

        return $form_values;
    }

    /**
     * Save form data.
     */
    protected function postProcess()
    {
        $displayedError = array();
        // if (!Tools::getValue('FINANCE_ENVIRONMENT_URL')) {
        //     $displayedError[] = $this->l('environment_url_description');
        // }

        if (!Tools::getValue('FINANCE_API_KEY')) {
            $displayedError[] = $this->l('api_key_empty_error');
        }

        if(!empty($displayedError)) {
            return $this->displayError(implode('<br>', $displayedError));
        }
        $form_values = $this->getConfigFormValues();

        foreach (array_keys($form_values) as $key) {
            if ($key == 'FINANCE_PLAN_SELECTION') {
                if (Tools::getIsset($key.'_selected')) {
                    $value =  Tools::getValue($key.'_selected');
                } else {
                    $value =  explode(',', Configuration::get($key));
                }
                Configuration::updateValue($key, implode(',', $value));
            } else {
                $value = Tools::getIsset($key) ? Tools::getValue($key) : Configuration::get($key);
                Configuration::updateValue($key, $value);
            }
        }

        Tools::redirectAdmin(
            $this->context->link->getAdminLink('AdminModules')
            .'&configure='.$this->name.'&conf=4&tab_module='.$this->tab.'&module_name='.$this->name
        );
    }

    /**
     * check if allowed currency
     *
     * @param  $cart
     * @return bool
     */
    public function checkCurrency($cart)
    {
        $currency_order = new Currency($cart->id_currency);
        $currencies_module = $this->getCurrency($cart->id_currency);

        if (is_array($currencies_module)) {
            foreach ($currencies_module as $currency_module) {
                if ($currency_order->id == $currency_module['id_currency']) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     *  hook header
     */
    public function hookHeader()
    {
        $js_key = $this->getJsKey();
        Media::addJsDef(
            array(
            Configuration::get('FINANCE_ENVIRONMENT') . 'Key' => $js_key,
            )
        );
        $this->context->controller->addJS(_PS_MODULE_DIR_.$this->name.'/views/js/finance.js');
    }

    /**
     * @return bool|false|mixed|string|string[]|null
     */
    public function getJsKey()
    {
        $api_key   = Configuration::get('FINANCE_API_KEY');
        $key_parts = explode('.', $api_key);
        $js_key    = Tools::strtolower(array_shift($key_parts));

        return $js_key;
    }

    /**
     * Button on payment page in 1.6
     *
     * @param  $params
     * @return string|void
     */
    public function hookPayment($params)
    {
        PrestaShopLogger::addLog('hook payment');
        if (!$this->active) {
            return;
        }
        if (!$this->checkCurrency($params['cart'])) {
            return;
        }
        $cart = $params['cart'];
        if ($cart->getOrderTotal() < Configuration::get('FINANCE_CART_MINIMUM')
            || $cart->id_address_delivery !== $cart->id_address_invoice
        ) {
            return;
        }

        $cart = $params['cart'];
        if ($cart->getOrderTotal() > Configuration::get('FINANCE_CART_MINIMUM')
        ) {
            return;
        }

        // checks cache first to see if they are stored there
        $plans = $this->getPlansFromCart($this->context->cart);

        if (!$plans) {
            return;
        }

        $this->smarty->assign(
            array(
            'payment_title' => Configuration::get('FINANCE_PAYMENT_TITLE'),
            )
        );

        return $this->display(__FILE__, 'payment.tpl');
    }

    /**
     * -Button on payment page in 1.
     *
     * @param  $params
     * @return array|void
     * @throws Exception
     */
    public function hookPaymentOptions($params)
    {
        PrestaShopLogger::addLog('hook payment options');
        if (!$this->active) {
            return;
        }
        if (!$this->checkCurrency($params['cart'])) {
            return;
        }
        $cart = $this->context->cart;
        if ((Configuration::get('FINANCE_CART_MINIMUM')
            && $cart->getOrderTotal() < Configuration::get('FINANCE_CART_MINIMUM'))
            || $cart->id_address_delivery !== $cart->id_address_invoice
        ) {
            return;
        }

        if ((Configuration::get('FINANCE_CART_MAXIMUM')
                && $cart->getOrderTotal() > Configuration::get('FINANCE_CART_MAXIMUM'))
        ) {
            return;
        }

        // checks cache first to see if they are stored there
        $plans = $this->getPlansFromCart($cart);

        if (!$plans || $plans === null) {
            return;
        }

        $action = Configuration::get('FINANCE_PAYMENT_TITLE');
        $info = Configuration::get('FINANCE_PAYMENT_DESCRIPTION');
        $newOption = new PrestaShop\PrestaShop\Core\Payment\PaymentOption;
        $newOption->setCallToActionText($action);
        $newOption->setAction($this->context->link->getModuleLink($this->name, 'payment', array(), true));
        $newOption->setAdditionalInformation($info);
        $payment_options = array($newOption);

        return $payment_options;
    }

    /**
     * OrderConfirmation-
     *
     * @param  $params
     * @return string|void
     */
    public function hookPaymentReturn($params)
    {
        if ($this->active == false) {
            return;
        }
        if ($this->ps_below_7) {
            $total = Tools::displayPrice($params['total_to_pay'], $params['currencyObj'], false);
            $order = $params['objOrder'];
        } else {
            $order = $params['order'];
            $total = sprintf(
                '%s ('.$this->l('price_tax_inclusive').')',
                Tools::displayPrice($order->getTotalPaid())
            );
        }
        $id_state = $order->current_state;

        if ($id_state == Configuration::get('PS_OS_ERROR')) {
            $this->smarty->assign('status', 'not');
        } elseif ($id_state == Configuration::get('PS_OS_CANCELED')) {
            $this->smarty->assign('status', 'not');
        } else {
            $this->smarty->assign('status', 'ok');
        }
        $this->smarty->assign(
            array(
                'id_order' => $order->id,
                'reference' => $order->reference,
                'params' => $params,
                'total' => $total,
                'shop_name' => Configuration::get('PS_SHOP_NAME'),
            )
        );

        return $this->display(__FILE__, 'confirmation.tpl');
    }

    public function hookActionAdminControllerSetMedia()
    {
        $this->context->controller->addJS($this->_path.'views/js/financeAdmin.js');
    }

    /**
     * @param $params
     * @return string|void
     */
    public function hookDisplayProductPriceBlock($params)
    {
        if (!Configuration::get('FINANCE_PRODUCT_WIDGET')
            || $params['type'] != 'after_price'
        ) {
            return;
        }

        return $this->getWidgetData($params, 'widget.tpl');
    }

    /**
     * @param $params
     * @return string|void
     */
    public function hookDisplayAdminProductsExtra($params)
    {
        PrestaShopLogger::addLog('hook display admin');
        $settings = FinanceApi::getProductSettings($params['id_product']);

        // checks cached finance plans first
        $plans = $this->getPlans();

        if (!$plans) {
            return;
        }

        if (!$settings) {
            $settings = array(
                'display' => 'default',
                'plans'   => array(),
            );
        } else {
            $settings['plans'] = explode(',', $settings['plans']);
        }

        $this->context->smarty->assign(
            array(
            'product_settings' => $settings,
            'plans' => $plans,
            )
        );

        return $this->display(__FILE__, 'productfields.tpl');
    }

    /**
     * @param $params
     * @throws PrestaShopDatabaseException
     */
    public function hookActionProductUpdate($params)
    {
        $id_product = (int) $params['id_product'];
        $display = Tools::getValue('FINANCE_display');
        $plans = '';
        if (Tools::getValue('FINANCE_plans')) {
            $plans = implode(',', Tools::getValue('FINANCE_plans'));
        }
        $data = array(
            'display' => pSQL($display),
            'plans' => pSQL($plans),
            'id_product' => (int) $id_product
        );
        Db::getInstance()->delete('finance_product', '`id_product` = "'.(int) $id_product.'"');
        Db::getInstance()->insert('finance_product', $data);
    }

    public function hookDisplayFooterProduct($params)
    {

   //     return $this->getWidgetData($params, 'calculator.tpl');
    }

    /**
     * @param $params
     * @return bool|void
     * @throws PrestaShopDatabaseException
     * @throws PrestaShopException
     */
    public function hookActionOrderStatusUpdate($params)
    {

        $orderStatus = $params['newOrderStatus'];
        $id_order = $params['id_order'];

        $order = new Order((int) $id_order);
        $total_price = $order->total_paid;

        if ($order->module != $this->name) {
            return;
        }

        $orderPaymanet = Db::getInstance()->getRow(
            'SELECT * FROM `'._DB_PREFIX_.'order_payment`
            WHERE `order_reference` = "'.pSQL($order->reference).'"
            AND transaction_id != "" ORDER BY `date_add` ASC'
        );

        if ($orderStatus->id == Configuration::get('FINANCE_ACTIVATION_STATUS') && $orderPaymanet) {
            try {
                $this->setFulfilled($orderPaymanet['transaction_id'], $total_price, $id_order);

                return true;
            } catch (Exception $e) {
                return $e->message;
            }
            PrestaShopLogger::addLog('Finance Activation Error: '.$e->message, 1, null, 'Order', (int) $id_order, true);
        } elseif ($orderStatus->id == Configuration::get('FINANCE_CANCELLATION_STATUS') && $orderPaymanet) {
            try {
                $this->setCancelled($orderPaymanet['transaction_id'], $total_price, $id_order);

                return true;
            } catch (Exception $e) {
                return $e->message;
            }
            PrestaShopLogger::addLog('Finance Activation Error: '.$e->message, 1, null, 'Order', (int) $id_order, true);
        } elseif ($orderStatus->id == Configuration::get('FINANCE_REFUND_STATUS') && $orderPaymanet) {
            try {
                $this->setRefunded($orderPaymanet['transaction_id'], $total_price, $id_order);

                return true;
            } catch (Exception $e) {
                return $e->message;
            }
            PrestaShopLogger::addLog('Finance Activation Error: '.$e->message, 1, null, 'Order', (int) $id_order, true);
        }
    }

    /**
     * @param $params
     * @param $template
     * @return string|void
     */
    public function getWidgetData($params, $template)
    {

        $product = $params['product'];

        if ($this->ps_below_7 && is_object($product)) {
            $product_price = $product->price;
            $id_product = $product->id;
        } elseif (!$this->ps_below_7) {
            $product_price = $product['price_amount'];
            $id_product = $product['id_product'];
        } else {
            return;
        }

        $finance = new FinanceApi();
        $plans = $finance->getProductPlans($product_price, $id_product);

        if (!$plans) {
            return;
        }

        // get lender name to set widget styling
        $lender =  $finance->getLender();
        if (empty($lender)) {
            $lender = $finance->setLender();
        }

        $data_mode = (
            !empty(Configuration::get('FINANCE_PRODUCT_CALCULATOR')) 
            && 1 == Configuration::get('FINANCE_PRODUCT_CALCULATOR'))
                ? 'calculator'
                : 'lightbox';

        $data_footnote = (empty(Configuration::get('FINANCE_PRODUCT_WIDGET_FOOTNOTE')))
            ? false
            : Configuration::get('FINANCE_PRODUCT_WIDGET_FOOTNOTE');

        $data_button_text = (empty(Configuration::get('FINANCE_PRODUCT_WIDGET_BUTTON_TEXT')))
            ? false
            : Configuration::get('FINANCE_PRODUCT_WIDGET_BUTTON_TEXT');

        $data_language = false;
        if(
            !empty(Configuration::get('FINANCE_PRODUCT_WIDGET_BUTTON_TEXT')) 
            && 1 == Configuration::get('FINANCE_PRODUCT_CALCULATOR')
        ){
            $language = $this->context->language->iso_code;
            if(in_array($language, self::SUPPORTED_LANGUAGES)){
                $data_language = $language;
            }
        }

        $this->context->smarty->assign(array(
            'plans' => implode(',', array_keys($plans)),
            'raw_total' => $product_price,
            'finance_environment'  => Configuration::get('FINANCE_ENVIRONMENT'),
            'api_key' => explode(".", Configuration::get('FINANCE_API_KEY'), 2)[0],
            'lender' => $lender,
            'data_button_text' => $data_button_text,
            'data_mode' => $data_mode,
            'data_footnote' => $data_footnote,
            'data_language' => $data_language,
            'calculator_url' => DividoHelper::generateCalcUrl(
                configuration::get('FINANCE_ENVIRONMENT'),
                Environment::getEnvironmentFromAPIKey(Configuration::get('FINANCE_API_KEY'))
            )
        ));

        return $this->display(__FILE__, $template);
    }

    /**
     * @param $application_id
     * @param $order_total
     * @param $order_id
     * @param null           $shipping_method
     * @param null           $tracking_numbers
     * @return string
     */
    public function setFulfilled(
        $application_id,
        $order_total,
        $order_id,
        $shipping_method = null,
        $tracking_numbers = null
    ) {
        // First get the application you wish to create an activation for.
        $api_key   = Configuration::get('FINANCE_API_KEY');
        $application = (new \Divido\MerchantSDK\Models\Application())
        ->withId($application_id);
        $items       = array(
            array(
                'name'     => "Order id: $order_id",
                'quantity' => 1,
                'price'    => $order_total * 100,
            ),
        );
        // Create a new application activation model.
        $application_activation = (new \Divido\MerchantSDK\Models\ApplicationActivation())
            ->withOrderItems($items)
            ->withDeliveryMethod($shipping_method)
            ->withTrackingNumber($tracking_numbers);
        // Create a new activation for the application.
        $sdk = Merchant_SDK::getSDK(Configuration::get('FINANCE_ENVIRONMENT_URL'), $api_key);
        $response = $sdk->applicationActivations()->createApplicationActivation(
            $application,
            $application_activation
        );
        $activation_response_body = $response->getBody()->getContents();

        return $activation_response_body;
    }

    /**
     * @param $application_id
     * @param $order_total
     * @param $order_id
     * @return string
     */
    public function setCancelled(
        $application_id,
        $order_total,
        $order_id
    ) {

        // First get the application you wish to create an activation for.
        $api_key   = Configuration::get('FINANCE_API_KEY');
        $application = ( new \Divido\MerchantSDK\Models\Application() )
        ->withId($application_id);
        $items       = [
            [
                'name'     => "Order id: $order_id",
                'quantity' => 1,
                'price'    => $order_total * 100,
            ],
        ];
        // Create a new application activation model.
        $applicationCancel = ( new \Divido\MerchantSDK\Models\ApplicationCancellation() )
            ->withOrderItems($items);
        // Create a new activation for the application.
        $sdk = Merchant_SDK::getSDK(Configuration::get('FINANCE_ENVIRONMENT_URL'), $api_key);
        $response = $sdk->applicationCancellations()->createApplicationCancellation($application, $applicationCancel);
        $cancellation_response_body = $response->getBody()->getContents();

        return $cancellation_response_body;
    }

    /**
     * @param $application_id
     * @param $order_total
     * @param $order_id
     * @return string
     */
    public function setRefunded(
        $application_id,
        $order_total,
        $order_id
    ) {
        // First get the application you wish to create an activation for.
        $api_key   = Configuration::get('FINANCE_API_KEY');
        $application = ( new \Divido\MerchantSDK\Models\Application() )
        ->withId($application_id);
        $items       = [
            [
                'name'     => "Order id: $order_id",
                'quantity' => 1,
                'price'    => $order_total * 100,
            ],
        ];
        // Create a new application activation model.
        $applicationRefund = ( new \Divido\MerchantSDK\Models\ApplicationRefund() )
            ->withOrderItems($items);
        // Create a new activation for the application.

        $sdk = Merchant_SDK::getSDK(Configuration::get('FINANCE_ENVIRONMENT_URL'), $api_key);
        $response = $sdk->applicationRefunds()->createApplicationRefund($application, $applicationRefund);
        $cancellation_response_body = $response->getBody()->getContents();

        return $cancellation_response_body;
    }

    /**
     * Retrieve all plans applicable to all/some of the items
     * in the cart, according to the merchant config settings
     *
     * @param  $cart The shopping cart
     * @return array|null Array of plans or null if no plans
     */
    public function getPlansFromCart($cart)
    {
        $api = new FinanceApi();
        $plans = $api->getCartPlans($cart);

        return (count($plans) > 0) ? $plans : null;
    }

    /**
     * Returns an array of all the finance plans
     * available to the merchant via an API call
     * or null if no plans are available
     *
     * @return array Array of plans
     */
    public function getPlans()
    {
        $FinanceApi = new FinanceApi();
        $plans  = $FinanceApi->getPlans();

        return $plans;
    }
}
