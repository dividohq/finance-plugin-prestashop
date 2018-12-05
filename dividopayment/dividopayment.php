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

require_once dirname(__FILE__) . '/lib/divido/Divido.php';
require_once dirname(__FILE__) . '/classes/divido.class.php';

class DividoPayment extends PaymentModule
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
            'code' => 'CANCELED',
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

    public function __construct()
    {
        $this->name = 'dividopayment';
        $this->tab = 'payments_gateways';
        $this->version = '1.0.0';
        $this->author = 'Divido Financial Services';
        $this->need_instance = 0;

        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('Divido');
        $this->description = $this->l(
            'The Divido Retail Finance extension allows you to accept finance payments in your Prestashop store.'
        );

        $this->confirmUninstall = $this->l('Are you sure you want to uninstall this module?');
        /*------Version Check-------------*/
        $this->ps_below_7 = Tools::version_compare(_PS_VERSION_, '1.7', '<');

        $this->ps_versions_compliancy = array('min' => '1.6', 'max' => _PS_VERSION_);
    }

    public function install()
    {
        Configuration::updateValue('DIVIDO_API_KEY', null);
        Configuration::updateValue('DIVIDO_PAYMENT_TITLE', $this->displayName);
        Configuration::updateValue('DIVIDO_ACTIVATION_STATUS', Configuration::get('PS_OS_DELIVERED'));
        Configuration::updateValue('DIVIDO_PRODUCT_WIDGET', null);
        Configuration::updateValue('DIVIDO_PRODUCT_CALCULATOR', null);
        Configuration::updateValue('DIVIDO_PRODUCT_WIDGET_PREFIX', 'Finance From');
        Configuration::updateValue('DIVIDO_PRODUCT_WIDGET_SUFFIX', 'with');
        Configuration::updateValue('DIVIDO_ALL_PLAN_SELECTION', true);
        Configuration::updateValue('DIVIDO_PLAN_SELECTION', null);
        Configuration::updateValue('DIVIDO_WHOLE_CART', false);
        Configuration::updateValue('DIVIDO_CART_MINIMUM', '0');
        Configuration::updateValue('DIVIDO_PRODUCTS_OPTIONS', 'All');
        Configuration::updateValue('DIVIDO_PRODUCTS_MINIMUM', '0');

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

                case 'CANCELED':
                case 'DECLINED':
                    $status = Configuration::get('PS_OS_CANCELED');
                    break;

                case 'FULFILLED':
                    $status = Configuration::get('PS_OS_DELIVERED');
                    break;
                default:
                    $status = Configuration::get('PS_OS_PREPARATION');
                    break;
            }
            Configuration::updateValue('DIVIDO_STATUS_'.$ApiStatus['code'], $status);
        }

        require_once(dirname(__FILE__).'/sql/install.php');
        
        if (!parent::install() ||
            !$this->registerHook('payment') ||
            !$this->registerHook('header') ||
            !$this->registerHook('actionAdminControllerSetMedia') ||
            !$this->registerHook('displayFooterProduct') ||
            !$this->registerHook('displayProductPriceBlock') ||
            !$this->registerHook('displayAdminProductsExtra') ||
            !$this->registerHook('actionProductUpdate') ||
            !$this->registerHook('actionOrderStatusUpdate') ||
            !$this->registerHook('paymentReturn')) {
            return false;
        }
        $status = array();
        $status['module_name'] = $this->name;
        $status['send_email'] = false;
        $status['invoice'] = false;
        $status['unremovable'] = true;
        $status['paid'] = false;
        $state = $this->addState($this->l('Awaiting divido response'), '#0404B4', $status);
        Configuration::updateValue('DIVIDO_AWAITING_STATUS', $state);

        /*------------------Handle hooks according to version-------------------*/
        if ($this->ps_below_7 && !$this->registerHook('payment')) {
            Configuration::updateValue('DIVIDO_PAYMENT_DESCRIPTION', $this->displayName);
            return false;
        } elseif (!$this->ps_below_7 && !$this->registerHook('paymentOptions')) {
            return false;
        }

        return true;
    }

    private function addState($name, $color, $status)
    {
        $order_state = new OrderState();
        $order_state->name = array();
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
                copy(dirname(__FILE__).'/logo.gif', dirname(__FILE__).'/../../img/os/'.(int)$order_state->id.'.gif');
            }
        }
        return $order_state->id;
    }

    public function uninstall()
    {
        Configuration::deleteByName('DIVIDO_API_KEY');
        Configuration::deleteByName('DIVIDO_PAYMENT_TITLE');
        Configuration::deleteByName('DIVIDO_ACTIVATION_STATUS');
        Configuration::deleteByName('DIVIDO_PRODUCT_WIDGET');
        Configuration::deleteByName('DIVIDO_PRODUCT_CALCULATOR');
        Configuration::deleteByName('DIVIDO_PRODUCT_WIDGET_PREFIX');
        Configuration::deleteByName('DIVIDO_PRODUCT_WIDGET_SUFFIX');
        Configuration::deleteByName('DIVIDO_ALL_PLAN_SELECTION');
        Configuration::deleteByName('DIVIDO_PLAN_SELECTION');
        Configuration::deleteByName('DIVIDO_WHOLE_CART');
        Configuration::deleteByName('DIVIDO_CART_MINIMUM');
        Configuration::deleteByName('DIVIDO_PRODUCTS_OPTIONS');
        Configuration::deleteByName('DIVIDO_PRODUCTS_MINIMUM');

        /*------------------Handle hooks according to version-------------------*/
        if (!$this->ps_below_7) {
            Configuration::deleteByName('DIVIDO_PAYMENT_DESCRIPTION');
        }

        foreach ($this->ApiOrderStatus as $ApiStatus) {
            Configuration::deleteByName('DIVIDO_STATUS_'.$ApiStatus['code']);
        }
        $id_state = Configuration::get('DIVIDO_AWAITING_STATUS');
        $order_state = new OrderState($id_state);
        if (Validate::isLoadedObject($order_state)) {
            $order_state->delete();
            Configuration::deleteByName('DIVIDO_AWAITING_STATUS');
            if (file_exists(dirname(__FILE__).'/../../img/os/'.(int)$order_state->id.'.gif')) {
                unlink(dirname(__FILE__).'/../../img/os/'.(int)$order_state->id.'.gif');
            }
        }
        require_once(dirname(__FILE__).'/sql/uninstall.php');

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
        if (((bool)Tools::isSubmit('submitDividoModule')) == true) {
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
        $helper->submit_action = 'submitDividoModule';
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
                'title' => $this->l('Settings'),
                'icon' => 'icon-cogs',
                ),
                'input' => array(
                    array(
                        'type' => 'text',
                        'name' => 'DIVIDO_API_KEY',
                        'label' => $this->l('API key'),
                    )
                ),
                'submit' => array(
                    'title' => $this->l('Save'),
                ),
            ),
        );

        /*----------------------Display form only after key is inserted-----------------------------*/
        if (Configuration::get('DIVIDO_API_KEY')) {
            $api = new DividoApi();
            $dividoPlans = $api->getAllPlans();
            $orderStatus = OrderState::getOrderStates($this->context->language->id);
            $product_options = array(
                array(
                    'type' => 'All',
                    'name' => $this->l('All Products'),
                ),
                array(
                    'type' => 'product_selected',
                    'name' => $this->l('Selected Products'),
                ),
                array(
                    'type' => 'min_price',
                    'name' => $this->l('Products above minimum value'),
                ),
            );
            $form['form']['input'][] = array(
                'type' => 'text',
                'name' => 'DIVIDO_PAYMENT_TITLE',
                'label' => $this->l('Title'),
            );
            if (!$this->ps_below_7) {
                $form['form']['input'][] = array(
                    'type' => 'text',
                    'name' => 'DIVIDO_PAYMENT_DESCRIPTION',
                    'label' => $this->l('Payment page description'),
                );
            }
            $form['form']['input'][] = array(
                'type' => 'select',
                'name' => 'DIVIDO_ACTIVATION_STATUS',
                'label' => $this->l('Activation status'),
                'hint' => $this->l('Prestashop status to make divido activation call'),
                'options' => array(
                    'query' => $orderStatus,
                    'id' => 'id_order_state',
                    'name' => 'name',
                ),
            );
            $form['form']['input'][] = array(
                'type' => 'switch',
                'name' => 'DIVIDO_ALL_PLAN_SELECTION',
                'label' => $this->l('Select All Plans'),
                'is_bool' => true,
                'values' => array(
                    array(
                        'id' => 'active_on',
                        'value' => true,
                        'label' => $this->l('Yes')
                    ),
                    array(
                        'id' => 'active_off',
                        'value' => false,
                        'label' => $this->l('No')
                    )
                ),
            );
            $form['form']['input'][] = array(
                'type' => 'swap',
                'name' => 'DIVIDO_PLAN_SELECTION',
                'label' => $this->l('Plans Selected'),
                'options' => array(
                    'query' => $dividoPlans,
                    'name' => 'text',
                    'id' => 'id',
                ),
            );
            $form['form']['input'][] = array(
                'type' => 'switch',
                'name' => 'DIVIDO_PRODUCT_WIDGET',
                'label' => $this->l('Widget on product page'),
                'is_bool' => true,
                'values' => array(
                    array(
                        'id' => 'active_on',
                        'value' => true,
                        'label' => $this->l('Enabled')
                    ),
                    array(
                        'id' => 'active_off',
                        'value' => false,
                        'label' => $this->l('Disabled')
                    )
                ),
            );
            $form['form']['input'][] = array(
                'type' => 'switch',
                'name' => 'DIVIDO_PRODUCT_CALCULATOR',
                'label' => $this->l('Calculator on product page'),
                'is_bool' => true,
                'values' => array(
                    array(
                        'id' => 'active_on',
                        'value' => true,
                        'label' => $this->l('Enabled')
                    ),
                    array(
                        'id' => 'active_off',
                        'value' => false,
                        'label' => $this->l('Disabled')
                    )
                ),
            );
            $form['form']['input'][] = array(
                'type' => 'text',
                'name' => 'DIVIDO_PRODUCT_WIDGET_PREFIX',
                'label' => $this->l('Prefix'),
            );
            $form['form']['input'][] = array(
                'type' => 'text',
                'name' => 'DIVIDO_PRODUCT_WIDGET_SUFFIX',
                'label' => $this->l('Suffix'),
            );
            $form['form']['input'][] = array(
                'type' => 'switch',
                'name' => 'DIVIDO_WHOLE_CART',
                'label' => $this->l('Require whole cart to be available on finance'),
                'is_bool' => true,
                'values' => array(
                    array(
                        'id' => 'active_on',
                        'value' => true,
                        'label' => $this->l('Enabled')
                    ),
                    array(
                        'id' => 'active_off',
                        'value' => false,
                        'label' => $this->l('Disabled')
                    )
                ),
            );
            $form['form']['input'][] = array(
                'type' => 'text',
                'name' => 'DIVIDO_CART_MINIMUM',
                'label' => $this->l('Cart amount minimum'),
                'help' => $this->l('Minimum required amount in cart, for Divido to be available')
            );
            $form['form']['input'][] = array(
                'type' => 'select',
                'name' => 'DIVIDO_PRODUCTS_OPTIONS',
                'label' => $this->l('Product Selection'),
                'options' => array(
                    'query' => $product_options,
                    'id' => 'type',
                    'name' => 'name',
                ),
            );
            $form['form']['input'][] = array(
                'type' => 'text',
                'name' => 'DIVIDO_PRODUCTS_MINIMUM',
                'label' => $this->l('Product price minimum'),
            );
            $form['form']['input'][] = array(
                'type' => 'html',
                'name' => '<div class="alert alert-warning">'.$this->l('DIVIDO Response status mapping').'</div>',
            );
            foreach ($this->ApiOrderStatus as $ApiStatus) {
                $form['form']['input'][] = array(
                    'type' => 'select',
                    'name' => 'DIVIDO_STATUS_'.$ApiStatus['code'],
                    'label' => $ApiStatus['code'],
                    'options' => array(
                        'query' => $orderStatus,
                        'id' => 'id_order_state',
                        'name' => 'name',
                    ),
                );
            }
        }
        return $form;
    }

    /**
     * Set values for the inputs.
     */
    protected function getConfigFormValues()
    {
        $form_values = array(
            'DIVIDO_API_KEY' => Configuration::get('DIVIDO_API_KEY'),
            'DIVIDO_PAYMENT_TITLE' => Configuration::get('DIVIDO_PAYMENT_TITLE'),
            'DIVIDO_ACTIVATION_STATUS' => Configuration::get('DIVIDO_ACTIVATION_STATUS'),
            'DIVIDO_ALL_PLAN_SELECTION' => Configuration::get('DIVIDO_ALL_PLAN_SELECTION'),
            'DIVIDO_PLAN_SELECTION' => explode(',', Configuration::get('DIVIDO_PLAN_SELECTION')),
            'DIVIDO_PRODUCT_WIDGET' => Configuration::get('DIVIDO_PRODUCT_WIDGET'),
            'DIVIDO_PRODUCT_CALCULATOR' => Configuration::get('DIVIDO_PRODUCT_CALCULATOR'),
            'DIVIDO_PRODUCT_WIDGET_SUFFIX' => Configuration::get('DIVIDO_PRODUCT_WIDGET_SUFFIX'),
            'DIVIDO_PRODUCT_WIDGET_PREFIX' => Configuration::get('DIVIDO_PRODUCT_WIDGET_PREFIX'),
            'DIVIDO_CART_MINIMUM' => Configuration::get('DIVIDO_CART_MINIMUM'),
            'DIVIDO_PRODUCTS_OPTIONS' => Configuration::get('DIVIDO_PRODUCTS_OPTIONS'),
            'DIVIDO_PRODUCTS_MINIMUM' => Configuration::get('DIVIDO_PRODUCTS_MINIMUM'),
            'DIVIDO_WHOLE_CART' => Configuration::get('DIVIDO_WHOLE_CART'),
        );

        if (!$this->ps_below_7) {
            $form_values['DIVIDO_PAYMENT_DESCRIPTION'] = Configuration::get('DIVIDO_PAYMENT_DESCRIPTION');
        }
        foreach ($this->ApiOrderStatus as $ApiStatus) {
            $form_values['DIVIDO_STATUS_'.$ApiStatus['code']] = Configuration::get('DIVIDO_STATUS_'.$ApiStatus['code']);
        }
        return $form_values;
    }

    /**
     * Save form data.
     */
    protected function postProcess()
    {
        if (!Tools::getValue('DIVIDO_API_KEY')) {
            return '<div class="alert alert-danger">'.Tools::displayError('Api key Cannot be empty').'</div>';
        }
        $form_values = $this->getConfigFormValues();

        foreach (array_keys($form_values) as $key) {
            if ($key == 'DIVIDO_PLAN_SELECTION') {
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

    /*-----------------check if allowed currency---------------*/
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

    public function hookHeader()
    {
        $js_key = $this->getJsKey();
        Media::addJsDef(array(
            'dividoKey' => $js_key,
        ));
        $this->context->controller->addJS(_PS_MODULE_DIR_.$this->name.'/views/js/divido.js');
    }

    public function getJsKey()
    {
        $api_key   = Configuration::get('DIVIDO_API_KEY');
        $key_parts = explode('.', $api_key);
        $js_key    = Tools::strtolower(array_shift($key_parts));
        return $js_key;
    }

    /*------------------Button on payment page in 1.6-------------------*/
    public function hookPayment($params)
    {
        if (!$this->active) {
            return;
        }
        if (!$this->checkCurrency($params['cart'])) {
            return;
        }
        $cart = $params['cart'];
        if ($cart->getOrderTotal() < Configuration::get('DIVIDO_CART_MINIMUM') ||
            $cart->id_address_delivery !== $cart->id_address_invoice
        ) {
            return;
        }

        $api = new DividoApi();
        $plans = $api->getCartPlans($this->context->cart);

        if (!$plans) {
            return;
        }

        $this->smarty->assign(array(
            'payment_title' => Configuration::get('DIVIDO_PAYMENT_TITLE'),
        ));
        return $this->display(__FILE__, 'payment.tpl');
    }

    /*------------------Button on payment page in 1.7-------------------*/
    public function hookPaymentOptions($params)
    {
        if (!$this->active) {
            return;
        }
        if (!$this->checkCurrency($params['cart'])) {
            return;
        }
        $cart = $this->context->cart;
        if ((Configuration::get('DIVIDO_CART_MINIMUM') &&
            $cart->getOrderTotal() < Configuration::get('DIVIDO_CART_MINIMUM')) ||
            $cart->id_address_delivery !== $cart->id_address_invoice
        ) {
            return;
        }

        $api = new DividoApi();
        $plans = $api->getCartPlans($cart);

        if (!$plans) {
            return;
        }

        $action = Configuration::get('DIVIDO_PAYMENT_TITLE');
        $info = Configuration::get('DIVIDO_PAYMENT_DESCRIPTION');
        $newOption = new PrestaShop\PrestaShop\Core\Payment\PaymentOption;
        $newOption->setCallToActionText($action);
        $newOption->setAction($this->context->link->getModuleLink($this->name, 'payment', array(), true));
        $newOption->setAdditionalInformation($info);
        $payment_options = array($newOption);

        return $payment_options;
    }

    /*------------------OrderConfirmation-------------------*/
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
                $this->l('%1$s (tax incl.)'),
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
        $this->context->controller->addJS($this->_path.'views/js/dividoAdmin.js');
    }

    public function hookDisplayProductPriceBlock($params)
    {
        if (!Configuration::get('DIVIDO_PRODUCT_WIDGET') ||
            $params['type'] != 'after_price'
        ) {
            return;
        }

        return $this->getWidgetData($params, 'widget.tpl');
    }

    public function hookDisplayAdminProductsExtra($params)
    {
        $settings = DividoApi::getProductSettings($params['id_product']);

        $DividoApi = new DividoApi();
        $plans = $DividoApi->getPlans();

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

        $this->context->smarty->assign(array(
            'product_settings' => $settings,
            'plans' => $plans,
        ));

        return $this->display(__FILE__, 'productfields.tpl');
    }

    public function hookActionProductUpdate($params)
    {
        $id_product = (int)$params['id_product'];
        $display = Tools::getValue('DIVIDO_display');
        $plans = '';
        if (Tools::getValue('DIVIDO_plans')) {
            $plans = implode(',', Tools::getValue('DIVIDO_plans'));
        }
        $data = array(
            'display' => pSQL($display),
            'plans' => pSQL($plans),
            'id_product' => (int)$id_product
        );
        Db::getInstance()->delete('divido_product', '`id_product` = "'.(int)$id_product.'"');
        Db::getInstance()->insert('divido_product', $data);
    }

    public function hookDisplayFooterProduct($params)
    {
        if (!Configuration::get('DIVIDO_PRODUCT_CALCULATOR')) {
            return;
        }

        return $this->getWidgetData($params, 'calculator.tpl');
    }

    public function hookActionOrderStatusUpdate($params)
    {
        $orderStatus = $params['newOrderStatus'];
        $id_order = $params['id_order'];
        $order = new Order((int)$id_order);
        if ($order->module != $this->name) {
            return;
        }
        $carrier = new Carrier($order->id_carrier);
        $orderPaymanet = Db::getInstance()->getRow(
            'SELECT * FROM `'._DB_PREFIX_.'order_payment`
            WHERE `order_reference` = "'.pSQL($order->reference).'"
            AND transaction_id != "" ORDER BY `date_add` ASC'
        );
        if ($orderStatus->id == Configuration::get('DIVIDO_ACTIVATION_STATUS') && $orderPaymanet) {
            $api_key   = Configuration::get('DIVIDO_API_KEY');

            $request_data = array(
                'merchant' => $api_key,
                'application' => $orderPaymanet['transaction_id'],
                'deliveryMethod' => $order->shipping_number ? $order->shipping_number : 'not entered',
                'trackingNumber' => $carrier->name,
            );
            Divido::setMerchant($api_key);

            $response = Divido_Activation::activate($request_data);

            if (isset($response->status) && $response->status == 'ok') {
                return true;
            }
            if (isset($response->error)) {
                $error = $response->error;
            } else {
                $error = $this->l('There was some error during activation api call');
            }
            PrestaShopLogger::addLog('Divido Activation Error: '.$error, 1, null, 'Order', (int)$id_order, true);
        }
    }

    public function getWidgetData($params, $template)
    {
        $product = $params['product'];
        $divido = new DividoApi();

        if ($this->ps_below_7 && is_object($product)) {
            $product_price = $product->price;
            $id_product = $product->id;
        } elseif (!$this->ps_below_7) {
            $product_price = $product['price_amount'];
            $id_product = $product['id_product'];
        } else {
            return;
        }
        $plans = $divido->getProductPlans($product_price, $id_product);

        if (!$plans) {
            return;
        }

        $this->context->smarty->assign(array(
            'plans' => implode(',', array_keys($plans)),
            'raw_total' => $product_price,
            'divido_prefix' => Configuration::get('DIVIDO_PRODUCT_WIDGET_PREFIX'),
            'divido_suffix' => Configuration::get('DIVIDO_PRODUCT_WIDGET_SUFFIX'),
        ));

        return $this->display(__FILE__, $template);
    }
}
