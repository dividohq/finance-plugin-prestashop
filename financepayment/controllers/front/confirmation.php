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

class FinancePaymentConfirmationModuleFrontController extends ModuleFrontController
{
    public function init()
    {
        $cart_id = (int)Tools::getValue('cart_id');

        PrestaShopLogger::addLog(
            'Arrived at confirmation',
            1,
            null,
            'Cart',
            $cart_id,
            true
        );

        $cart = new Cart($cart_id);
        if (!Validate::isLoadedObject($cart)) {
            PrestaShopLogger::addLog(
                'Could not load cart',
                1,
                null,
                'Cart',
                $cart_id,
                true
            );
            $url = $this->context->link->getPageLink('index');
            Tools::redirect($url);
        }
        $context = Context::getContext();
        if (!$cart->OrderExists()) {
            PrestaShopLogger::addLog(
                'Order could not be found',
                1,
                null,
                'Cart',
                (int)$cart_id,
                true
            );
            $url = $context->link->getModuleLink($this->module->name, 'payment', array('error' => true, "responsetext"=>$this->module->l("The order could not be found")));
            Tools::redirect($url);
        }
        $order = new Order(Order::getOrderByCartId($cart_id));
        if (!Validate::isLoadedObject($order)){
            PrestaShopLogger::addLog(
                'Waiting for order to load',
                1,
                null,
                'Order',
                $order->id,
                true
            );
            sleep(2);
        }
        
        $customer = new Customer($cart->id_customer);

        if($this->context->customer->id !== $customer->id){
            $token = Tools::getValue('token');
            if($this->validateToken($token, $cart_id)){
                $customer->logged = 1;
                $this->context->customer = $customer;
                $this->context->cookie->id_customer = $customer->id;  
            } else {
                PrestaShopLogger::addLog(
                    'Could not validate confirmation token',
                    1,
                    null,
                    'Order',
                    $order->id,
                    true
                );
            }
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

    private function getOrder($cart_id) {
        $request = Db::getInstance()->getRow(
            "
            SELECT *
            FROM `"._DB_PREFIX_."divido_requests`
            WHERE `cart_id` = '{$cart_id}'
            "
        );

        return $request;
    }

    private function completeCheck($cart_id) {
        $complete = false;
        for ($x=1; $x<6; $x++){
            $request = $this->getOrder($cart_id);
            if($request['complete']) {
                $complete = true;
                break;
            }else{
                sleep(1);
            }
        }

        if(!$complete) {
            $url = $context->link->getModuleLink(
                $this->module->name, 
                'payment', 
                array('error' => true, "responsetext"=>$this->module->l("Order awaiting application completion"))
            );
            Tools::redirect($url);
        }
    }

    private function validateToken($token, $cart_id){
        $divido_request = $this->getOrder($cart_id);
        if($divido_request['token'] !== $token){
            return false;
        }
        return true;
    }
}
