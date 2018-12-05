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

class DividoApi
{
    public function getGlobalSelectedPlans()
    {
        $all_plans     = $this->getAllPlans();
        $selected_plans = explode(',', Configuration::get('DIVIDO_PLAN_SELECTION'));

        if (Configuration::get('DIVIDO_ALL_PLAN_SELECTION')) {
            return $all_plans;
        }

        if (!$selected_plans) {
            return array();
        }

        $plans = array();
        foreach ($all_plans as $plan) {
            if (in_array($plan->id, $selected_plans)) {
                $plans[$plan->id] = $plan;
            }
        }

        return $plans;
    }

    public function getAllPlans()
    {
        $api_key = Configuration::get('DIVIDO_API_KEY');
        if (!$api_key) {
            return array();
        }

        Divido::setMerchant($api_key);

        $response = Divido_Finances::all();
        if ($response->status != 'ok') {
            return array();
        }

        $plans = $response->finances;

        $plans_plain = array();
        foreach ($plans as $plan) {
            $plan_copy = new stdClass();
            $plan_copy->id                 = $plan->id;
            $plan_copy->text               = $plan->text;
            $plan_copy->country            = $plan->country;
            $plan_copy->min_amount         = $plan->min_amount;
            $plan_copy->min_deposit        = $plan->min_deposit;
            $plan_copy->max_deposit        = $plan->max_deposit;
            $plan_copy->interest_rate      = $plan->interest_rate;
            $plan_copy->deferral_period    = $plan->deferral_period;
            $plan_copy->agreement_duration = $plan->agreement_duration;

            $plans_plain[$plan->id] = $plan_copy;
        }

        return $plans_plain;
    }

    public function getCartPlans($cart)
    {
        $exclusive = Configuration::get('DIVIDO_WHOLE_CART');
        $plans     = array();
        $products  = $cart->getProducts();
        foreach ($products as $product) {
            $product_plans = $this->getProductPlans($product['total_wt'], $product['id_product']);
            if ($product_plans) {
                $plans = array_merge($plans, $product_plans);
            } elseif (!$product_plans && $exclusive) {
                return array();
            }
        }
        return $plans;
    }

    public function getPlans($default_plans = false)
    {
        if ($default_plans) {
            $plans = $this->getGlobalSelectedPlans();
        } else {
            $plans = $this->getAllPlans();
        }

        return $plans;
    }

    public function getProductPlans($product_price, $id_product)
    {
        $settings = $this->getProductSettings($id_product);
        $product_selection = Configuration::get('DIVIDO_PRODUCTS_OPTIONS');
        $price_threshold   = Configuration::get('DIVIDO_PRODUCTS_MINIMUM');

        $plans = $this->getPlans(true);

        if (empty($settings)) {
            $settings = array(
                'display' => 'default',
                'plans'   => '',
            );
        }
        if ($product_selection == 'All') {
            return $plans;
        }

        if ($product_selection == 'min_price' && $price_threshold > $product_price) {
            return null;
        } elseif ($product_selection == 'min_price') {
            return $plans;
        }

        if ($product_selection == 'product_selected' && $settings['display'] == 'default') {
            return null;
        }

        if ($product_selection == 'product_selected' && $settings['display'] == 'custom' && empty($settings['plans'])) {
            return null;
        }

        $available_plans = $this->getPlans(false);
        $selected_plans  = explode(',', $settings['plans']);

        $plans = array();
        foreach ($available_plans as $plan) {
            if (in_array($plan->id, $selected_plans)) {
                $plans[$plan->id] = $plan;
            }
        }

        if (empty($plans)) {
            return null;
        }

        return $plans;
    }

    public static function getProductSettings($id_product)
    {
        $query = "select * from `"._DB_PREFIX_."divido_product` where id_product = '".(int)$id_product."'";

        return Db::getInstance()->getRow($query);
    }
}
