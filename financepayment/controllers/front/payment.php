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

class FinancePaymentPaymentModuleFrontController extends ModuleFrontController
{
    public $ssl = true;
    public $display_column_left = false;

    /**
     * @see FrontController::initContent()
     */
    public function initContent()
    {
        parent::initContent();
        $cart = $this->context->cart;
        if (!$this->module->checkCurrency($cart)) {
            Tools::redirect('index.php?controller=order');
        }
        if ($cart->getOrderTotal() < Configuration::get('FINANCE_CART_MINIMUM')) {
            Tools::redirect('index.php?controller=order');
        }
        if ($cart->getOrderTotal() > Configuration::get('FINANCE_CART_MAXIMUM') &&  Configuration::get('FINANCE_USE_CART_MAXIMUM') === "1") {
            Tools::redirect('index.php?controller=order');
        }

        $payment_error = false;
        $responsetext = '';
        $responsedes = '';

        /*-------Error Handling--------*/
        if (Tools::getValue('error')) {
            $payment_error = true;
            $responsetext = Tools::getValue('responsetext');
            $responsedes = Tools::getValue('responsedes');
        }

        $currency = new Currency($cart->id_currency);

        $js_key    = $this->module->getJsKey();

        $api = new FinanceApi();
        $plans = $api->getCartPlans($cart);

        if (!$plans) {
            Tools::redirect('index.php?controller=order');
        }

        // get lender name to set widget styling
        $lender = $api->getLender();
        if(empty($lender)){
            $lender =  $api->setLender();
        }

        $this->context->smarty->assign(
            array(
                'payment_error' => $payment_error,
                'responsetext' => $responsetext,
                'finance_environment' => Configuration::get('FINANCE_ENVIRONMENT'),
                'nbProducts' => $cart->nbProducts(),
                'responsedes' => $responsedes,
                'cust_currency' => $cart->id_currency,
                'raw_total' => $cart->getOrderTotal(true, Cart::BOTH),
                'total' => Tools::displayPrice($cart->getOrderTotal(true, Cart::BOTH), $currency),
                'merchant_script' => "//cdn.divido.com/calculator/".$js_key.".js",
                'plans' => implode(',', array_keys($plans)),
                'validationLink' => $this->context->link->getModuleLink('financepayment', 'validation'),
                'api_key' => Tools::substr(Configuration::get('FINANCE_API_KEY'),  0,  strpos(Configuration::get('FINANCE_API_KEY'), ".")),
                'lender' => $lender
            )
        );
        Media::addJsDef(
            array(
            'merchant_script' => "//cdn.divido.com/calculator/".$js_key.".js",
            'validationLink' => $this->context->link->getModuleLink('financepayment', 'validation'),
            )
        );

        if (!$this->module->ps_below_7) {
            $this->context->smarty->assign(
                array(
                'currencies' => Currency::getCurrencies(),
                'this_path_bw' => $this->module->getPathUri(),
                )
            );
        }

        if ($this->module->ps_below_7) {
            $this->setTemplate('payment_execution_1_6.tpl');
        } else {
            $this->setTemplate('module:'.$this->module->name.'/views/templates/front/payment_execution.tpl');
        }
    }

    public function setMedia()
    {
        parent::setMedia();
        $this->addJS(_PS_MODULE_DIR_.$this->module->name.'/views/js/finance.js');
    }
}
