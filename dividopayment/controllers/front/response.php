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

class DividoPaymentResponseModuleFrontController extends ModuleFrontController
{
    const DEBUG_MODE = true;

    public function postProcess()
    {
        $input = Tools::file_get_contents('php://input');
        $data  = Tools::jsonDecode($input);

        if (!isset($data->status) || !isset($data->metadata->cart_id)) {
            die;
        }

        $cart_id   = $data->metadata->cart_id;

        $result = Db::getInstance()->getRow(
            'SELECT * FROM `'._DB_PREFIX_.'divido_requests` WHERE `cart_id` = "'.(int)$cart_id.'"'
        );

        if (!$result) {
            die;
        }

        $hash = hash('sha256', $result['cart_id'].$result['hash']);

        if ($hash !== $data->metadata->cart_hash) {
            die;
        }

        $cart = new Cart($cart_id);
        if (!Validate::isLoadedObject($cart)) {
            die;
        }
        $status = Configuration::get('DIVIDO_STATUS_'.$data->status);

        if (!$status) {
            die;
        }

        $total = $cart->getOrderTotal();

        if ($total != $result['total']) {
            $status = Configuration::get('PS_OS_ERROR');
        }
        if (!$cart->OrderExists()) {
            die;
        }
        $order = new Order(Order::getOrderByCartId($cart_id));
        if ($order->current_state != Configuration::get('DIVIDO_AWAITING_STATUS')) {
            if ($status != $order->current_state) {
                $this->setCurrentState($order, $status);
            }
        } elseif ($status != $order->current_state) {
            $extra_vars = array('transaction_id' => $data->application);
            $order->addOrderPayment($result['total'], null, $data->application);
            $this->setCurrentState($order, $status);

            $this->updateOrder(
                $cart_id,
                $status,
                $extra_vars
            );
        }
        die;
    }

    public function setCurrentState($order, $id_order_state, $id_employee = 0)
    {
        if (empty($id_order_state)) {
            return false;
        }
        $history = new OrderHistory();
        $history->id_order = (int)$order->id;
        $history->id_employee = (int)$id_employee;
        $history->changeIdOrderState((int)$id_order_state, $order, true);
        $res = Db::getInstance()->getRow('
            SELECT `invoice_number`, `invoice_date`, `delivery_number`, `delivery_date`
            FROM `'._DB_PREFIX_.'orders`
            WHERE `id_order` = '.(int)$order->id);
        $order->invoice_date = $res['invoice_date'];
        $order->invoice_number = $res['invoice_number'];
        $order->delivery_date = $res['delivery_date'];
        $order->delivery_number = $res['delivery_number'];
        $order->update();

        $history->addWithemail();
    }

    public function updateOrder($cart_id, $id_order_state, $extra_vars)
    {
        $cart = new Cart($cart_id);
        $id_cart = $cart_id;
        $order = new Order(Order::getOrderByCartId($cart_id));
        $customer = new Customer($cart->id_customer);
        $carrier = new Carrier($order->getIdOrderCarrier());
        $currency = new Currency($cart->id_currency);
        $order_status = new OrderState((int)$id_order_state, (int)$order->id_lang);
        $cart_rules = $cart->getCartRules();

        // Construct order detail table for the email
        $virtual_product = true;
        $product_list = $cart->getProducts();
        $product_var_tpl_list = array();
        $cart_rule_used = array();
        foreach ($product_list as $product) {
            $price = Product::getPriceStatic(
                (int)$product['id_product'],
                false,
                ($product['id_product_attribute'] ? (int)$product['id_product_attribute'] : null),
                6,
                null,
                false,
                true,
                $product['cart_quantity'],
                false,
                (int)$order->id_customer,
                (int)$order->id_cart,
                (int)$order->{Configuration::get('PS_TAX_ADDRESS_TYPE')}
            );
            $price_wt = Product::getPriceStatic(
                (int)$product['id_product'],
                true,
                ($product['id_product_attribute'] ? (int)$product['id_product_attribute'] : null),
                2,
                null,
                false,
                true,
                $product['cart_quantity'],
                false,
                (int)$order->id_customer,
                (int)$order->id_cart,
                (int)$order->{Configuration::get('PS_TAX_ADDRESS_TYPE')}
            );

            $product_price = Product::getTaxCalculationMethod() == PS_TAX_EXC ? Tools::ps_round(
                $price,
                2
            ) : $price_wt;

            $product_var_tpl = array(
                'reference' => $product['reference'],
                'name' => $product['name'].(
                    isset($product['attributes']) ? ' - '.$product['attributes'] : ''
                ),
                'unit_price' => Tools::displayPrice($product_price, $currency, false),
                'price' => Tools::displayPrice(
                    $product_price * $product['quantity'],
                    $currency,
                    false
                ),
                'quantity' => $product['quantity'],
                'customization' => array()
            );

            if (isset($product['price']) && $product['price']) {
                $product_var_tpl['unit_price'] = Tools::displayPrice($product_price, $currency, false);
                $product_var_tpl['unit_price_full'] = Tools::displayPrice(
                    $product_price,
                    $currency,
                    false
                ).' '.$product['unity'];
            } else {
                $product_var_tpl['unit_price'] = $product_var_tpl['unit_price_full'] = '';
            }

            $customized_datas = Product::getAllCustomizedDatas((int)$order->id_cart);
            if (isset($customized_datas[$product['id_product']][$product['id_product_attribute']])) {
                $product_var_tpl['customization'] = array();
                $p_customized_datas = $customized_datas[$product['id_product']];
                $p_a_customized_datas = $p_customized_datas[$product['id_product_attribute']];
                foreach ($p_a_customized_datas[$order->id_address_delivery] as $customization) {
                    $customization_text = '';
                    if (isset($customization['datas'][Product::CUSTOMIZE_TEXTFIELD])) {
                        foreach ($customization['datas'][Product::CUSTOMIZE_TEXTFIELD] as $text) {
                            $customization_text .= $text['name'].': '.$text['value'].'<br />';
                        }
                    }

                    if (isset($customization['datas'][Product::CUSTOMIZE_FILE])) {
                        $customization_text .= sprintf(
                            Tools::displayError('%d image(s)'),
                            count($customization['datas'][Product::CUSTOMIZE_FILE])
                        ).'<br />';
                    }

                    $customization_quantity = (int)$product['customization_quantity'];

                    $product_var_tpl['customization'][] = array(
                        'customization_text' => $customization_text,
                        'customization_quantity' => $customization_quantity,
                        'quantity' => Tools::displayPrice(
                            $customization_quantity * $product_price,
                            $currency,
                            false
                        )
                    );
                }
            }

            $product_var_tpl_list[] = $product_var_tpl;
            // Check if is not a virutal product for the displaying of shipping
            if (!$product['is_virtual']) {
                $virtual_product &= false;
            }
        } // end foreach ($products)

        $product_list_txt = '';
        $product_list_html = '';
        if (count($product_var_tpl_list) > 0) {
            $product_list_txt = $this->getEmailTemplateContent(
                'order_conf_product_list.txt',
                Mail::TYPE_TEXT,
                $product_var_tpl_list
            );
            $product_list_html = $this->getEmailTemplateContent(
                'order_conf_product_list.tpl',
                Mail::TYPE_HTML,
                $product_var_tpl_list
            );
        }


        $cart_rules_list = array();
        $total_reduction_value_ti = 0;
        $total_reduction_value_tex = 0;
        foreach ($cart_rules as $cart_rule) {
            $package = array(
                'id_carrier' => $order->id_carrier,
                'id_address' => $order->id_address_delivery,
                'products' => $order->product_list
            );
            $values = array(
                'tax_incl' => $cart_rule['obj']->getContextualValue(
                    true,
                    $this->context,
                    CartRule::FILTER_ACTION_ALL_NOCAP,
                    $package
                ),
                'tax_excl' => $cart_rule['obj']->getContextualValue(
                    false,
                    $this->context,
                    CartRule::FILTER_ACTION_ALL_NOCAP,
                    $package
                )
            );

            // If the reduction is not applicable to this order, then continue with the next one
            if (!$values['tax_excl']) {
                continue;
            }

            // IF
            //  This is not multi-shipping
            //  The value of the voucher is greater than the total of the order
            //  Partial use is allowed
            //  This is an "amount" reduction, not a reduction in % or a gift
            // THEN
            //  The voucher is cloned with a new value corresponding to the remainder
            $order_list = Db::getInstance()->executeS(
                'SELECT * FROM `'._DB_PREFIX_.'orders` WHERE `id_cart` = "'.(int)$order->id_cart.'"'
            );
            if (count($order_list) == 1 &&
                $values['tax_incl'] > ($order->total_products_wt - $total_reduction_value_ti) &&
                $cart_rule['obj']->partial_use == 1 &&
                $cart_rule['obj']->reduction_amount > 0
            ) {
                // Create a new voucher from the original
                // We need to instantiate the CartRule without lang parameter to allow saving it
                $voucher = new CartRule((int)$cart_rule['obj']->id);
                unset($voucher->id);

                // Set a new voucher code
                $voucher->code = empty($voucher->code) ? Tools::substr(
                    md5($order->id.'-'.$order->id_customer.'-'.$cart_rule['obj']->id),
                    0,
                    16
                ) : $voucher->code.'-2';
                if (preg_match(
                    '/\-([0-9]{1,2})\-([0-9]{1,2})$/',
                    $voucher->code,
                    $matches
                ) && $matches[1] == $matches[2]) {
                    $voucher->code = preg_replace(
                        '/'.$matches[0].'$/',
                        '-'.((int)($matches[1]) + 1),
                        $voucher->code
                    );
                }

                // Set the new voucher value
                if ($voucher->reduction_tax) {
                    $voucher->reduction_amount = (
                        $total_reduction_value_ti + $values['tax_incl']
                    ) - $order->total_products_wt;

                    // Add total shipping amout only if reduction amount > total shipping
                    if ($voucher->free_shipping == 1 &&
                        $voucher->reduction_amount >= $order->total_shipping_tax_incl
                    ) {
                        $voucher->reduction_amount -= $order->total_shipping_tax_incl;
                    }
                } else {
                    $voucher->reduction_amount = (
                        $total_reduction_value_tex + $values['tax_excl']
                    ) - $order->total_products;

                    // Add total shipping amout only if reduction amount > total shipping
                    if ($voucher->free_shipping == 1 &&
                        $voucher->reduction_amount >= $order->total_shipping_tax_excl
                    ) {
                        $voucher->reduction_amount -= $order->total_shipping_tax_excl;
                    }
                }
                if ($voucher->reduction_amount <= 0) {
                    continue;
                }

                if ($customer->isGuest()) {
                    $voucher->id_customer = 0;
                } else {
                    $voucher->id_customer = $order->id_customer;
                }

                $voucher->quantity = 1;
                $voucher->reduction_currency = $order->id_currency;
                $voucher->quantity_per_user = 1;
                if ($voucher->add()) {
                    // If the voucher has conditions, they are now copied to the new voucher
                    CartRule::copyConditions($cart_rule['obj']->id, $voucher->id);
                    $params = array(
                        '{voucher_amount}' => Tools::displayPrice($voucher->reduction_amount, $currency, false),
                        '{voucher_num}' => $voucher->code,
                        '{firstname}' => $customer->firstname,
                        '{lastname}' => $customer->lastname,
                        '{id_order}' => $order->reference,
                        '{order_name}' => $order->getUniqReference()
                    );
                    Mail::Send(
                        (int)$order->id_lang,
                        'voucher',
                        sprintf(
                            Mail::l('New voucher for your order %s', (int)$order->id_lang),
                            $order->reference
                        ),
                        $params,
                        $customer->email,
                        $customer->firstname.' '.$customer->lastname,
                        null,
                        null,
                        null,
                        null,
                        _PS_MAIL_DIR_,
                        false,
                        (int)$order->id_shop
                    );
                }

                $values['tax_incl'] = $order->total_products_wt - $total_reduction_value_ti;
                $values['tax_excl'] = $order->total_products - $total_reduction_value_tex;
                if (1 == $voucher->free_shipping) {
                    $values['tax_incl'] += $order->total_shipping_tax_incl;
                    $values['tax_excl'] += $order->total_shipping_tax_excl;
                }
            }
            $total_reduction_value_ti += $values['tax_incl'];
            $total_reduction_value_tex += $values['tax_excl'];

            $order->addCartRule(
                $cart_rule['obj']->id,
                $cart_rule['obj']->name,
                $values,
                0,
                $cart_rule['obj']->free_shipping
            );

            if ($id_order_state != Configuration::get('PS_OS_ERROR') &&
                $id_order_state != Configuration::get('PS_OS_CANCELED') &&
                !in_array($cart_rule['obj']->id, $cart_rule_used)
            ) {
                $cart_rule_used[] = $cart_rule['obj']->id;

                // Create a new instance of Cart Rule without id_lang, in order to update its quantity
                $cart_rule_to_update = new CartRule((int)$cart_rule['obj']->id);
                $cart_rule_to_update->quantity = max(0, $cart_rule_to_update->quantity - 1);
                $cart_rule_to_update->update();
            }

            $cart_rules_list[] = array(
                'voucher_name' => $cart_rule['obj']->name,
                'voucher_reduction' => (
                    $values['tax_incl'] != 0.00 ? '-' : ''
                ).Tools::displayPrice($values['tax_incl'], $currency, false)
            );
        }

        $cart_rules_list_txt = '';
        $cart_rules_list_html = '';
        if (count($cart_rules_list) > 0) {
            $cart_rules_list_txt = $this->getEmailTemplateContent(
                'order_conf_cart_rules.txt',
                Mail::TYPE_TEXT,
                $cart_rules_list
            );
            $cart_rules_list_html = $this->getEmailTemplateContent(
                'order_conf_cart_rules.tpl',
                Mail::TYPE_HTML,
                $cart_rules_list
            );
        }


        // Send an e-mail to customer (one order = one email)
        if ($id_order_state != Configuration::get('PS_OS_ERROR') &&
            $id_order_state != Configuration::get('PS_OS_CANCELED') &&
            $customer->id
        ) {
            $invoice = new Address((int)$order->id_address_invoice);
            $delivery = new Address((int)$order->id_address_delivery);
            $delivery_state = $delivery->id_state ? new State((int)$delivery->id_state) : false;
            $invoice_state = $invoice->id_state ? new State((int)$invoice->id_state) : false;
            $tax_method = Product::getTaxCalculationMethod();
            $data = array(
                '{firstname}' => $customer->firstname,
                '{lastname}' => $customer->lastname,
                '{email}' => $customer->email,
                '{delivery_block_txt}' => $this->getFormatedAddress($delivery, "\n"),
                '{invoice_block_txt}' => $this->getFormatedAddress($invoice, "\n"),
                '{delivery_block_html}' => $this->getFormatedAddress($delivery, '<br />', array(
                    'firstname'    => '<span style="font-weight:bold;">%s</span>',
                    'lastname'    => '<span style="font-weight:bold;">%s</span>'
                )),
                '{invoice_block_html}' => $this->getFormatedAddress($invoice, '<br />', array(
                        'firstname'    => '<span style="font-weight:bold;">%s</span>',
                        'lastname'    => '<span style="font-weight:bold;">%s</span>'
                )),
                '{delivery_company}' => $delivery->company,
                '{delivery_firstname}' => $delivery->firstname,
                '{delivery_lastname}' => $delivery->lastname,
                '{delivery_address1}' => $delivery->address1,
                '{delivery_address2}' => $delivery->address2,
                '{delivery_city}' => $delivery->city,
                '{delivery_postal_code}' => $delivery->postcode,
                '{delivery_country}' => $delivery->country,
                '{delivery_state}' => $delivery->id_state ? $delivery_state->name : '',
                '{delivery_phone}' => ($delivery->phone) ? $delivery->phone : $delivery->phone_mobile,
                '{delivery_other}' => $delivery->other,
                '{invoice_company}' => $invoice->company,
                '{invoice_vat_number}' => $invoice->vat_number,
                '{invoice_firstname}' => $invoice->firstname,
                '{invoice_lastname}' => $invoice->lastname,
                '{invoice_address2}' => $invoice->address2,
                '{invoice_address1}' => $invoice->address1,
                '{invoice_city}' => $invoice->city,
                '{invoice_postal_code}' => $invoice->postcode,
                '{invoice_country}' => $invoice->country,
                '{invoice_state}' => $invoice->id_state ? $invoice_state->name : '',
                '{invoice_phone}' => ($invoice->phone) ? $invoice->phone : $invoice->phone_mobile,
                '{invoice_other}' => $invoice->other,
                '{order_name}' => $order->getUniqReference(),
                '{date}' => Tools::displayDate($order->date_add, null, 1),
                '{carrier}' => (
                    $virtual_product || !isset($carrier->name)
                ) ? $this->module->l('No carrier') : $carrier->name,
                '{payment}' => Tools::substr($order->payment, 0, 255),
                '{products}' => $product_list_html,
                '{products_txt}' => $product_list_txt,
                '{discounts}' => $cart_rules_list_html,
                '{discounts_txt}' => $cart_rules_list_txt,
                '{total_paid}' => Tools::displayPrice($order->total_paid, $currency, false),
                '{total_products}' => Tools::displayPrice(
                    $tax_method == PS_TAX_EXC ? $order->total_products : $order->total_products_wt,
                    $currency,
                    false
                ),
                '{total_discounts}' => Tools::displayPrice($order->total_discounts, $currency, false),
                '{total_shipping}' => Tools::displayPrice($order->total_shipping, $currency, false),
                '{total_wrapping}' => Tools::displayPrice($order->total_wrapping, $currency, false),
                '{total_tax_paid}' => Tools::displayPrice(
                    (
                        $order->total_products_wt - $order->total_products
                    ) + (
                        $order->total_shipping_tax_incl - $order->total_shipping_tax_excl
                    ),
                    $currency,
                    false
                )
            );

            if (is_array($extra_vars)) {
                $data = array_merge($data, $extra_vars);
            }

            // Join PDF invoice
            if ((int)Configuration::get('PS_INVOICE') &&
                $order_status->invoice &&
                $order->invoice_number
            ) {
                $file_attachement = array();
                $order_invoice_list = $order->getInvoicesCollection();
                Hook::exec('actionPDFInvoiceRender', array('order_invoice_list' => $order_invoice_list));
                $pdf = new PDF($order_invoice_list, PDF::TEMPLATE_INVOICE, $this->context->smarty);
                $file_attachement['content'] = $pdf->render(false);
                $file_attachement['name'] = Configuration::get(
                    'PS_INVOICE_PREFIX',
                    (int)$order->id_lang,
                    null,
                    $order->id_shop
                ).sprintf('%06d', $order->invoice_number).'.pdf';
                $file_attachement['mime'] = 'application/pdf';
            } else {
                $file_attachement = null;
            }

            if (self::DEBUG_MODE) {
                PrestaShopLogger::addLog(
                    'PaymentModule::validateOrder - Mail is about to be sent',
                    1,
                    null,
                    'Cart',
                    (int)$id_cart,
                    true
                );
            }

            if (Validate::isEmail($customer->email)) {
                Mail::Send(
                    (int)$order->id_lang,
                    'order_conf',
                    Mail::l('Order confirmation', (int)$order->id_lang),
                    $data,
                    $customer->email,
                    $customer->firstname.' '.$customer->lastname,
                    null,
                    null,
                    $file_attachement,
                    null,
                    _PS_MAIL_DIR_,
                    false,
                    (int)$order->id_shop
                );
            }
        }
    }

    protected function getEmailTemplateContent($template_name, $mail_type, $var)
    {
        $email_configuration = Configuration::get('PS_MAIL_TYPE');
        if ($email_configuration != $mail_type && $email_configuration != Mail::TYPE_BOTH) {
            return '';
        }

        $pathToFindEmail = array(
            _PS_THEME_DIR_.'mails'.DIRECTORY_SEPARATOR.
            $this->context->language->iso_code.DIRECTORY_SEPARATOR.$template_name,
            _PS_THEME_DIR_.'mails'.DIRECTORY_SEPARATOR.'en'.DIRECTORY_SEPARATOR.$template_name,
            _PS_MAIL_DIR_.$this->context->language->iso_code.DIRECTORY_SEPARATOR.$template_name,
            _PS_MAIL_DIR_.'en'.DIRECTORY_SEPARATOR.$template_name,
        );

        foreach ($pathToFindEmail as $path) {
            if (Tools::file_exists_cache($path)) {
                $this->context->smarty->assign('list', $var);
                return $this->context->smarty->fetch($path);
            }
        }

        return '';
    }

    protected function getFormatedAddress(Address $the_address, $line_sep, $fields_style = array())
    {
        return AddressFormat::generateAddress(
            $the_address,
            array('avoid' => array()),
            $line_sep,
            ' ',
            $fields_style
        );
    }
}
