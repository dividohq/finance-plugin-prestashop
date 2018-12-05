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

class DividoPaymentValidationModuleFrontController extends ModuleFrontController
{
    const DEBUG_MODE = false;
    /**
     * This class should be use by your Instant Payment
     * Notification system to validate the order remotely
     */
    public function postProcess()
    {
        if (!(Tools::getIsset('total') && Tools::getIsset('deposit') && Tools::getIsset('finance'))) {
            Tools::redirect($this->context->link->getPageLink('index'));
        }

        $response = array(
            'status' => false,
            'message' => $this->module->l('Credit request could not be initiated.'),
        );

        /**
         * If the module is not active anymore, no need to process anything.
         */
        if ($this->module->active == false) {
            echo Tools::jsonEncode($response);
            die;
        }
        
        $cart = $this->context->cart;

        if ($cart->getOrderTotal(true, Cart::BOTH) != Tools::getValue('total')) {
            $response = array(
                'status' => true,
                'url' => $this->context->link->getPageLink('order'),
            );
            echo Tools::jsonEncode($response);
            die;
        }

        if (!Validate::isLoadedObject($cart)) {
            echo Tools::jsonEncode($response);
            die;
        }

        $id_cus = $cart->id_customer;
        $id_add = $cart->id_address_delivery;
        if ($id_cus == 0 || $id_add == 0 || $cart->id_address_invoice == 0) {
            echo Tools::jsonEncode($response);
            die;
        }

        $authorized = false;
        foreach (Module::getPaymentModules() as $module) {
            if ($module['name'] == $this->module->name) {
                $authorized = true;
                break;
            }
        }

        if (!$authorized) {
            echo json_encode($response);
            die;
        }

        $response = $this->getConfirmation();
        echo Tools::jsonEncode($response);
        die;
    }

    public function getConfirmation()
    {
        $api_key   = Configuration::get('DIVIDO_API_KEY');
        Divido::setApiKey($api_key);
        $deposit = Tools::getValue('deposit');
        $finance = Tools::getValue('finance');

        $cart = $this->context->cart;
        $customer = new Customer($cart->id_customer);
        $address = new Address($cart->id_address_invoice);
        $country = Country::getIsoById($address->id_country);

        $language = Tools::strtoupper(Language::getIsoById($this->context->language->id));

        $currencyObj = new Currency($cart->id_currency);
        $currency = $currencyObj->iso_code;

        $cart_id = $cart->id;

        $firstname = $customer->firstname;
        $lastname = $customer->lastname;
        $email = $customer->email;
        $telephone = '';
        if ($address->phone) {
            $telephone = $address->phone;
        } elseif ($address->phone_mobile) {
            $telephone = $address->phone_mobile;
        }

        $postcode  = $address->postcode;

        $products  = array();
        foreach ($cart->getProducts() as $product) {
            $products[] = array(
                'type' => 'product',
                'text' => $product['name'],
                'quantity' => $product['quantity'],
                'value' => $product['price_wt'],
            );
        }

        $sub_total = $cart->getOrderTotal(true, Cart::BOTH);

        $shiphandle = $cart->getOrderTotal(true, Cart::ONLY_SHIPPING);
        $disounts = $cart->getOrderTotal(true, Cart::ONLY_DISCOUNTS);

        $products[] = array(
            'type'     => 'product',
            'text'     => 'Shipping & Handling',
            'quantity' => 1,
            'value'    => $shiphandle,
        );

        $products[] = array(
            'type'     => 'product',
            'text'     => 'Discount',
            'quantity' => 1,
            'value'    => "-".$disounts,
        );

        $deposit_amount = Tools::ps_round(($deposit / 100) * $sub_total, 2);

        $response_url = $this->context->link->getModuleLink($this->module->name, 'response');
        $redirect_url = $this->context->link->getModuleLink(
            $this->module->name,
            'confirmation',
            array('cart_id' => $cart_id)
        );
        $checkout_url = $this->context->link->getPageLink('order');

        $salt = uniqid('', true);
        $hash = hash('sha256', $cart_id.$salt);

        $this->saveHash($cart_id, $salt, $sub_total);

        $request_data = array(
            'merchant' => $api_key,
            'deposit'  => $deposit_amount,
            'finance'  => $finance,
            'country'  => $country,
            'language' => $language,
            'currency' => $currency,
            'metadata' => array(
                'cart_id' => $cart_id,
                'cart_hash' => $hash,
            ),
            'customer' => array(
                'title'         => '',
                'first_name'    => $firstname,
                'middle_name'   => '',
                'last_name'     => $lastname,
                'country'       => $country,
                'postcode'      => $postcode,
                'email'         => $email,
                'mobile_number' => '',
                'phone_number'  => $telephone,
                'address' => array(
                    'text' => $address->address1." ".$address->address2.
                        " ".$address->city." ".$address->postcode,
                ),
            ),
            'products' => $products,
            'response_url' => $response_url,
            'redirect_url' => $redirect_url,
            'checkout_url' => $checkout_url,
        );

        $response = Divido_CreditRequest::create($request_data);

        if ($response->status == 'ok') {
            $data = array(
                'status' => true,
                'url'    => $response->url,
            );
            $customer = new Customer($cart->id_customer);
            $this->validatOrder(
                $cart_id,
                Configuration::get('DIVIDO_AWAITING_STATUS'),
                $sub_total,
                $this->module->displayName,
                null,
                array('transaction_id' => $response->id),
                (int)$cart->id_currency,
                false,
                $customer->secure_key
            );
        } else {
            $data = array(
                'status'  => false,
                'message' => Tools::displayError($response->error),
            );
        }

        return $data;
    }

    public function saveHash($cart_id, $salt, $total)
    {
        $data = array(
            'cart_id' => (int)$cart_id,
            'hash' => pSQL($salt),
            'total' => pSQL($total),
        );
        $result = Db::getInstance()->getRow(
            'SELECT * FROM `'._DB_PREFIX_.'divido_requests` WHERE `cart_id` = "'.(int)$cart_id.'"'
        );

        if ($result) {
            Db::getInstance()->update('divido_requests', $data, '`cart_id` = "'.(int)$cart_id.'"');
        } else {
            Db::getInstance()->insert('divido_requests', $data);
        }
    }

    public function validatOrder(
        $id_cart,
        $id_order_state,
        $amount_paid,
        $payment_method = 'Unknown',
        $message = null,
        $extra_vars = array(),
        $currency_special = null,
        $dont_touch_amount = false,
        $secure_key = false,
        Shop $shop = null
    ) {
        if (self::DEBUG_MODE) {
            PrestaShopLogger::addLog(
                'PaymentModule::validateOrder - Function called',
                1,
                null,
                'Cart',
                (int)$id_cart,
                true
            );
        }

        if (!isset($this->context)) {
            $this->context = Context::getContext();
        }
        $this->context->cart = new Cart((int)$id_cart);
        $this->context->customer = new Customer((int)$this->context->cart->id_customer);
        // The tax cart is loaded before the customer so re-cache the tax calculation method
        $this->context->cart->setTaxCalculationMethod();

        $this->context->language = new Language((int)$this->context->cart->id_lang);
        $this->context->shop = ($shop ? $shop : new Shop((int)$this->context->cart->id_shop));
        ShopUrl::resetMainDomainCache();
        $id_currency = $currency_special ? (int)$currency_special : (int)$this->context->cart->id_currency;
        $this->context->currency = new Currency((int)$id_currency, null, (int)$this->context->shop->id);
        if (Configuration::get('PS_TAX_ADDRESS_TYPE') == 'id_address_delivery') {
            $context_country = $this->context->country;
        }

        $order_status = new OrderState((int)$id_order_state, (int)$this->context->language->id);
        if (!Validate::isLoadedObject($order_status)) {
            PrestaShopLogger::addLog(
                'PaymentModule::validateOrder - Order Status cannot be loaded',
                3,
                null,
                'Cart',
                (int)$id_cart,
                true
            );
            throw new PrestaShopException('Can\'t load Order status');
        }

        if (!$this->module->active) {
            PrestaShopLogger::addLog(
                'PaymentModule::validateOrder - Module is not active',
                3,
                null,
                'Cart',
                (int)$id_cart,
                true
            );
            die(Tools::displayError());
        }

        // Does order already exists ?
        if (Validate::isLoadedObject($this->context->cart) && $this->context->cart->OrderExists() == false) {
            if ($secure_key !== false && $secure_key != $this->context->cart->secure_key) {
                PrestaShopLogger::addLog(
                    'PaymentModule::validateOrder - Secure key does not match',
                    3,
                    null,
                    'Cart',
                    (int)$id_cart,
                    true
                );
                die(Tools::displayError('Secure key does not match'));
            }

            // For each package, generate an order
            $delivery_option_list = $this->context->cart->getDeliveryOptionList();
            $package_list = $this->context->cart->getPackageList();
            $cart_delivery_option = $this->context->cart->getDeliveryOption();

            // If some delivery options are not defined, or not valid, use the first valid option
            foreach ($delivery_option_list as $id_address => $package) {
                if (!isset($cart_delivery_option[$id_address]) ||
                    !array_key_exists($cart_delivery_option[$id_address], $package)
                ) {
                    foreach (array_keys($package) as $key) {
                        $cart_delivery_option[$id_address] = $key;
                        break;
                    }
                }
            }

            $order_list = array();
            $order_detail_list = array();

            do {
                $reference = Order::generateReference();
            } while (Order::getByReference($reference)->count());

            $this->module->currentOrderReference = $reference;

            $order_creation_failed = false;
            $cart_total_paid = (float)Tools::ps_round(
                (float)$this->context->cart->getOrderTotal(true, Cart::BOTH),
                2
            );

            foreach ($cart_delivery_option as $id_address => $key_carriers) {
                $car_list = $delivery_option_list[$id_address][$key_carriers]['carrier_list'];
                foreach ($car_list as $id_carrier => $data) {
                    foreach ($data['package_list'] as $id_package) {
                        // Rewrite the id_warehouse
                        $package_list[$id_address][$id_package]['id_warehouse'] =
                        (int)$this->context->cart->getPackageIdWarehouse(
                            $package_list[$id_address][$id_package],
                            (int)$id_carrier
                        );
                        $package_list[$id_address][$id_package]['id_carrier'] = $id_carrier;
                    }
                }
            }
            // Make sure CartRule caches are empty
            CartRule::cleanCache();
            $cart_rules = $this->context->cart->getCartRules();
            foreach ($cart_rules as $cart_rule) {
                if (($rule = new CartRule((int)$cart_rule['obj']->id)) && Validate::isLoadedObject($rule)) {
                    if ($error = $rule->checkValidity($this->context, true, true)) {
                        $this->context->cart->removeCartRule((int)$rule->id);
                        if (isset($this->context->cookie) &&
                            isset($this->context->cookie->id_customer) &&
                            $this->context->cookie->id_customer &&
                            !empty($rule->code)
                        ) {
                            if ($this->module->ps_below_7 &&
                                Configuration::get('PS_ORDER_PROCESS_TYPE') == 1
                            ) {
                                Tools::redirect(
                                    'index.php?controller=order-opc&submitAddDiscount=1&discount_name='.
                                    urlencode($rule->code)
                                );
                            }
                            Tools::redirect(
                                'index.php?controller=order&submitAddDiscount=1&discount_name='.
                                urlencode($rule->code)
                            );
                        } else {
                            $rule_name = isset(
                                $rule->name[(int)$this->context->cart->id_lang]
                            ) ? $rule->name[(int)$this->context->cart->id_lang] : $rule->code;
                            $error = sprintf(
                                Tools::displayError(
                                    'CartRule ID %1s (%2s) used in this cart is not valid
                                     and has been withdrawn from cart'
                                ),
                                (int)$rule->id,
                                $rule_name
                            );
                            PrestaShopLogger::addLog(
                                $error,
                                3,
                                '0000002',
                                'Cart',
                                (int)$this->context->cart->id
                            );
                        }
                    }
                }
            }

            foreach ($package_list as $id_address => $packageByAddress) {
                foreach ($packageByAddress as $id_package => $package) {
                    /** @var Order $order */
                    $order = new Order();
                    $order->product_list = $package['product_list'];

                    if (Configuration::get('PS_TAX_ADDRESS_TYPE') == 'id_address_delivery') {
                        $address = new Address((int)$id_address);
                        $this->context->country = new Country(
                            (int)$address->id_country,
                            (int)$this->context->cart->id_lang
                        );
                        if (!$this->context->country->active) {
                            throw new PrestaShopException('The delivery address country is not active.');
                        }
                    }

                    $carrier = null;
                    if (!$this->context->cart->isVirtualCart() && isset($package['id_carrier'])) {
                        $carrier = new Carrier(
                            (int)$package['id_carrier'],
                            (int)$this->context->cart->id_lang
                        );
                        $order->id_carrier = (int)$carrier->id;
                        $id_carrier = (int)$carrier->id;
                    } else {
                        $order->id_carrier = 0;
                        $id_carrier = 0;
                    }

                    $order->id_customer = (int)$this->context->cart->id_customer;
                    $order->id_address_invoice = (int)$this->context->cart->id_address_invoice;
                    $order->id_address_delivery = (int)$id_address;
                    $order->id_currency = $this->context->currency->id;
                    $order->id_lang = (int)$this->context->cart->id_lang;
                    $order->id_cart = (int)$this->context->cart->id;
                    $order->reference = $reference;
                    $order->id_shop = (int)$this->context->shop->id;
                    $order->id_shop_group = (int)$this->context->shop->id_shop_group;

                    $order->secure_key = (
                        $secure_key ? pSQL($secure_key) : pSQL($this->context->customer->secure_key)
                    );
                    $order->payment = $payment_method;
                    if (isset($this->module->name)) {
                        $order->module = $this->module->name;
                    }
                    $order->recyclable = $this->context->cart->recyclable;
                    $order->gift = (int)$this->context->cart->gift;
                    $order->gift_message = $this->context->cart->gift_message;
                    $order->mobile_theme = $this->context->cart->mobile_theme;
                    $order->conversion_rate = $this->context->currency->conversion_rate;
                    $amount_paid = !$dont_touch_amount ? Tools::ps_round(
                        (float)$amount_paid,
                        2
                    ) : $amount_paid;
                    $order->total_paid_real = 0;

                    $order->total_products = (float)$this->context->cart->getOrderTotal(
                        false,
                        Cart::ONLY_PRODUCTS,
                        $order->product_list,
                        $id_carrier
                    );
                    $order->total_products_wt = (float)$this->context->cart->getOrderTotal(
                        true,
                        Cart::ONLY_PRODUCTS,
                        $order->product_list,
                        $id_carrier
                    );
                    $order->total_discounts_tax_excl = (float)abs($this->context->cart->getOrderTotal(
                        false,
                        Cart::ONLY_DISCOUNTS,
                        $order->product_list,
                        $id_carrier
                    ));
                    $order->total_discounts_tax_incl = (float)abs($this->context->cart->getOrderTotal(
                        true,
                        Cart::ONLY_DISCOUNTS,
                        $order->product_list,
                        $id_carrier
                    ));
                    $order->total_discounts = $order->total_discounts_tax_incl;

                    $order->total_shipping_tax_excl = (float)$this->context->cart->getPackageShippingCost(
                        (int)$id_carrier,
                        false,
                        null,
                        $order->product_list
                    );
                    $order->total_shipping_tax_incl = (float)$this->context->cart->getPackageShippingCost(
                        (int)$id_carrier,
                        true,
                        null,
                        $order->product_list
                    );
                    $order->total_shipping = $order->total_shipping_tax_incl;

                    if (!is_null($carrier) && Validate::isLoadedObject($carrier)) {
                        $order->carrier_tax_rate = $carrier->getTaxesRate(
                            new Address(
                                (int)$this->context->cart->{Configuration::get('PS_TAX_ADDRESS_TYPE')}
                            )
                        );
                    }

                    $order->total_wrapping_tax_excl = (float)abs($this->context->cart->getOrderTotal(
                        false,
                        Cart::ONLY_WRAPPING,
                        $order->product_list,
                        $id_carrier
                    ));
                    $order->total_wrapping_tax_incl = (float)abs($this->context->cart->getOrderTotal(
                        true,
                        Cart::ONLY_WRAPPING,
                        $order->product_list,
                        $id_carrier
                    ));
                    $order->total_wrapping = $order->total_wrapping_tax_incl;

                    $order->total_paid_tax_excl = (float)Tools::ps_round(
                        (float)$this->context->cart->getOrderTotal(
                            false,
                            Cart::BOTH,
                            $order->product_list,
                            $id_carrier
                        ),
                        _PS_PRICE_COMPUTE_PRECISION_
                    );
                    $order->total_paid_tax_incl = (float)Tools::ps_round(
                        (float)$this->context->cart->getOrderTotal(
                            true,
                            Cart::BOTH,
                            $order->product_list,
                            $id_carrier
                        ),
                        _PS_PRICE_COMPUTE_PRECISION_
                    );
                    $order->total_paid = $order->total_paid_tax_incl;
                    $order->round_mode = Configuration::get('PS_PRICE_ROUND_MODE');
                    $order->round_type = Configuration::get('PS_ROUND_TYPE');

                    $order->invoice_date = '0000-00-00 00:00:00';
                    $order->delivery_date = '0000-00-00 00:00:00';

                    if (self::DEBUG_MODE) {
                        PrestaShopLogger::addLog(
                            'PaymentModule::validateOrder - Order is about to be added',
                            1,
                            null,
                            'Cart',
                            (int)$id_cart,
                            true
                        );
                    }

                    // Creating order
                    $result = $order->add();

                    if (!$result) {
                        PrestaShopLogger::addLog(
                            'PaymentModule::validateOrder - Order cannot be created',
                            3,
                            null,
                            'Cart',
                            (int)$id_cart,
                            true
                        );
                        throw new PrestaShopException('Can\'t save Order');
                    }

                    // Amount paid by customer is not the right one -> Status = payment error
                    // We don't use the following condition to avoid the float precision issues :
                    //http://www.php.net/manual/en/language.types.float.php
                    // if ($order->total_paid != $order->total_paid_real)
                    // We use number_format in order to compare two string
                    if ($order_status->logable &&
                        number_format(
                            $cart_total_paid,
                            _PS_PRICE_COMPUTE_PRECISION_
                        ) != number_format($amount_paid, _PS_PRICE_COMPUTE_PRECISION_)
                    ) {
                        $id_order_state = Configuration::get('PS_OS_ERROR');
                    }

                    $order_list[] = $order;

                    if (self::DEBUG_MODE) {
                        PrestaShopLogger::addLog(
                            'PaymentModule::validateOrder - OrderDetail is about to be added',
                            1,
                            null,
                            'Cart',
                            (int)$id_cart,
                            true
                        );
                    }

                    // Insert new Order detail list using cart for the current order
                    $order_detail = new OrderDetail(null, null, $this->context);
                    $order_detail->createList(
                        $order,
                        $this->context->cart,
                        $id_order_state,
                        $order->product_list,
                        0,
                        true,
                        $package_list[$id_address][$id_package]['id_warehouse']
                    );
                    $order_detail_list[] = $order_detail;

                    if (self::DEBUG_MODE) {
                        PrestaShopLogger::addLog(
                            'PaymentModule::validateOrder - OrderCarrier is about to be added',
                            1,
                            null,
                            'Cart',
                            (int)$id_cart,
                            true
                        );
                    }

                    // Adding an entry in order_carrier table
                    if (!is_null($carrier)) {
                        $order_carrier = new OrderCarrier();
                        $order_carrier->id_order = (int)$order->id;
                        $order_carrier->id_carrier = (int)$id_carrier;
                        $order_carrier->weight = (float)$order->getTotalWeight();
                        $order_carrier->shipping_cost_tax_excl = (float)$order->total_shipping_tax_excl;
                        $order_carrier->shipping_cost_tax_incl = (float)$order->total_shipping_tax_incl;
                        $order_carrier->add();
                    }
                }
            }

            // The country can only change if the address used for the calculation is the delivery address,
            // and if multi-shipping is activated
            if (Configuration::get('PS_TAX_ADDRESS_TYPE') == 'id_address_delivery') {
                $this->context->country = $context_country;
            }

            if (!$this->context->country->active) {
                PrestaShopLogger::addLog(
                    'PaymentModule::validateOrder - Country is not active',
                    3,
                    null,
                    'Cart',
                    (int)$id_cart,
                    true
                );
                throw new PrestaShopException('The order address country is not active.');
            }

            if (self::DEBUG_MODE) {
                PrestaShopLogger::addLog(
                    'PaymentModule::validateOrder - Payment is about to be added',
                    1,
                    null,
                    'Cart',
                    (int)$id_cart,
                    true
                );
            }

            // Register Payment only if the order status validate the order
            if ($order_status->logable) {
                // $order is the last order loop in the foreach
                // The method addOrderPayment of the class Order make a create a paymentOrder
                // linked to the order reference and not to the order id
                if (isset($extra_vars['transaction_id'])) {
                    $transaction_id = $extra_vars['transaction_id'];
                } else {
                    $transaction_id = null;
                }

                if (!isset($order) ||
                    !Validate::isLoadedObject($order) ||
                    !$order->addOrderPayment($amount_paid, null, $transaction_id)
                ) {
                    PrestaShopLogger::addLog(
                        'PaymentModule::validateOrder - Cannot save Order Payment',
                        3,
                        null,
                        'Cart',
                        (int)$id_cart,
                        true
                    );
                    throw new PrestaShopException('Can\'t save Order Payment');
                }
            }

            // Make sure CartRule caches are empty
            CartRule::cleanCache();
            foreach ($order_detail_list as $key => $order_detail) {
                /** @var OrderDetail $order_detail */

                $order = $order_list[$key];
                if (!$order_creation_failed && isset($order->id)) {
                    if (!$secure_key) {
                        $message .= '<br />'.Tools::displayError(
                            'Warning: the secure key is empty, check your payment account before validation'
                        );
                    }
                    // Optional message to attach to this order
                    if (isset($message) & !empty($message)) {
                        $msg = new Message();
                        $message = strip_tags($message, '<br>');
                        if (Validate::isCleanHtml($message)) {
                            if (self::DEBUG_MODE) {
                                PrestaShopLogger::addLog(
                                    'PaymentModule::validateOrder - Message is about to be added',
                                    1,
                                    null,
                                    'Cart',
                                    (int)$id_cart,
                                    true
                                );
                            }
                            $msg->message = $message;
                            $msg->id_cart = (int)$id_cart;
                            $msg->id_customer = (int)($order->id_customer);
                            $msg->id_order = (int)$order->id;
                            $msg->private = 1;
                            $msg->add();
                        }
                    }

                    // Insert new Order detail list using cart for the current order
                    //$orderDetail = new OrderDetail(null, null, $this->context);
                    //$orderDetail->createList($order, $this->context->cart, $id_order_state);

                    

                    // Specify order id for message
                    $old_message = Message::getMessageByCartId((int)$this->context->cart->id);
                    if ($old_message && !$old_message['private']) {
                        $update_message = new Message((int)$old_message['id_message']);
                        $update_message->id_order = (int)$order->id;
                        $update_message->update();

                        // Add this message in the customer thread
                        $customer_thread = new CustomerThread();
                        $customer_thread->id_contact = 0;
                        $customer_thread->id_customer = (int)$order->id_customer;
                        $customer_thread->id_shop = (int)$this->context->shop->id;
                        $customer_thread->id_order = (int)$order->id;
                        $customer_thread->id_lang = (int)$this->context->language->id;
                        $customer_thread->email = $this->context->customer->email;
                        $customer_thread->status = 'open';
                        $customer_thread->token = Tools::passwdGen(12);
                        $customer_thread->add();

                        $customer_message = new CustomerMessage();
                        $customer_message->id_customer_thread = $customer_thread->id;
                        $customer_message->id_employee = 0;
                        $customer_message->message = $update_message->message;
                        $customer_message->private = 1;

                        if (!$customer_message->add()) {
                            $this->errors[] = Tools::displayError('An error occurred while saving message');
                        }
                    }

                    if (self::DEBUG_MODE) {
                        PrestaShopLogger::addLog(
                            'PaymentModule::validateOrder - Hook validateOrder is about to be called',
                            1,
                            null,
                            'Cart',
                            (int)$id_cart,
                            true
                        );
                    }

                    // Hook validate order
                    Hook::exec('actionValidateOrder', array(
                        'cart' => $this->context->cart,
                        'order' => $order,
                        'customer' => $this->context->customer,
                        'currency' => $this->context->currency,
                        'orderStatus' => $order_status
                    ));

                    foreach ($this->context->cart->getProducts() as $product) {
                        if ($order_status->logable) {
                            ProductSale::addProductSale(
                                (int)$product['id_product'],
                                (int)$product['cart_quantity']
                            );
                        }
                    }

                    if (self::DEBUG_MODE) {
                        PrestaShopLogger::addLog(
                            'PaymentModule::validateOrder - Order Status is about to be added',
                            1,
                            null,
                            'Cart',
                            (int)$id_cart,
                            true
                        );
                    }

                    // Set the order status
                    $new_history = new OrderHistory();
                    $new_history->id_order = (int)$order->id;
                    $new_history->changeIdOrderState((int)$id_order_state, $order, true);
                    $new_history->addWithemail(true, $extra_vars);

                    // Switch to back order if needed
                    if (Configuration::get('PS_STOCK_MANAGEMENT') &&
                        ($order_detail->getStockState() || $order_detail->product_quantity_in_stock <= 0)
                    ) {
                        $history = new OrderHistory();
                        $history->id_order = (int)$order->id;
                        $history->changeIdOrderState(
                            Configuration::get(
                                $order->valid ? 'PS_OS_OUTOFSTOCK_PAID' : 'PS_OS_OUTOFSTOCK_UNPAID'
                            ),
                            $order,
                            true
                        );
                        $history->addWithemail();
                    }

                    unset($order_detail);

                    // Order is reloaded because the status just changed
                    $order = new Order((int)$order->id);

                    // updates stock in shops
                    if (Configuration::get('PS_ADVANCED_STOCK_MANAGEMENT')) {
                        $product_list = $order->getProducts();
                        foreach ($product_list as $product) {
                            // if the available quantities depends on the physical stock
                            if (StockAvailable::dependsOnStock($product['product_id'])) {
                                // synchronizes
                                StockAvailable::synchronize($product['product_id'], $order->id_shop);
                            }
                        }
                    }

                    $order->updateOrderDetailTax();
                    if (!$this->module->ps_below_7) {
                        // sync all stock
                        (new PrestaShop\PrestaShop\Adapter\StockManager())->updatePhysicalProductQuantity(
                            (int)$order->id_shop,
                            (int)Configuration::get('PS_OS_ERROR'),
                            (int)Configuration::get('PS_OS_CANCELED'),
                            null,
                            (int)$order->id
                        );
                    }
                } else {
                    $error = Tools::displayError('Order creation failed');
                    PrestaShopLogger::addLog($error, 4, '0000002', 'Cart', (int)($order->id_cart));
                    die($error);
                }
            } // End foreach $order_detail_list

            // Use the last order as currentOrder
            if (isset($order) && $order->id) {
                $this->module->currentOrder = (int)$order->id;
            }

            if (self::DEBUG_MODE) {
                PrestaShopLogger::addLog(
                    'PaymentModule::validateOrder - End of validateOrder',
                    1,
                    null,
                    'Cart',
                    (int)$id_cart,
                    true
                );
            }
            return true;
        } else {
            $error = Tools::displayError(
                'Cart cannot be loaded or an order has already been placed using this cart'
            );
            PrestaShopLogger::addLog($error, 4, '0000001', 'Cart', (int)($this->context->cart->id));
            die($error);
        }
    }
}
