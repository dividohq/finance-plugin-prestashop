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

class DividoPaymentConfirmationModuleFrontController extends ModuleFrontController
{
    public function init()
    {
        $cart_id = Tools::getValue('cart_id');
        $cart = new Cart($cart_id);
        if (!Validate::isLoadedObject($cart)) {
            $url = $this->context->link->getPageLink('index');
            Tools::redirect($url);
        }
        $context = Context::getContext();
        if (!$cart->OrderExists()) {
            $url = $context->link->getModuleLink($this->module->name, 'payment', array('error' => true));
            Tools::redirect($url);
        }
        $order = new Order(Order::getOrderByCartId($cart_id));
        $customer = new Customer($cart->id_customer);
        if ($context->cookie->id_cart == $cart_id) {
            unset($context->cookie->id_cart);
        }

        if ($order->current_state == Configuration::get('DIVIDO_AWAITING_STATUS')) {
            $this->context->cart = $cart;
            $response = $cart->duplicate();
            if ($response['success']) {
                $this->context->cookie->id_cart = $response['cart']->id;
                $this->context->cart = $response['cart'];
                $this->context->updateCustomer($customer);
            }
            $url = $context->link->getModuleLink($this->module->name, 'payment', array('error' => true));
            Tools::redirect($url);
        }


        $data = array(
            'id_cart' => $cart_id,
            'id_module' => $this->module->id,
            'id_order' => Order::getOrderByCartId($cart_id),
            'key' => $customer->secure_key
        );
        $url = $context->link->getPageLink('order-confirmation', null, null, $data);
        Tools::redirect($url);
    }
}
