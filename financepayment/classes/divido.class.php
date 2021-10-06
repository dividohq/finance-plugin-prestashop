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

use Divido\MerchantSDKGuzzle5\GuzzleAdapter;
use Divido\MerchantSDK\Client;
use Divido\MerchantSDK\Environment;
use Divido\MerchantSDK\HttpClient\HttpClientWrapper;
use Divido\MerchantSDK\Exceptions\MerchantApiBadResponseException;
use GuzzleHttp\Client as Guzzle;

class FinanceApi
{
    public function getGlobalSelectedPlans()
    {
        $all_plans     = $this->getAllPlans();
        $selected_plans = explode(',', Configuration::get('FINANCE_PLAN_SELECTION'));

        if (Configuration::get('FINANCE_ALL_PLAN_SELECTION')) {
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


    public function getFinanceEnv($api_key)
    {
        $environment_url = Configuration::get('FINANCE_ENVIRONMENT_URL');
        $api_key = Configuration::get('FINANCE_API_KEY');

        if (!$environment_url || !$api_key) {
            return array();
        }

        $client = new Guzzle();
        $env = Environment::getEnvironmentFromAPIKey($api_key);
        $httpClientWrapper = new HttpClientWrapper(
            new GuzzleAdapter($client),
            $environment_url,
            $api_key
        );

        $sdk = new Client($httpClientWrapper, $env);

        $response = $sdk->platformEnvironments()->getPlatformEnvironment();
        $finance_env = $response->getBody()->getContents();
        $decoded =json_decode($finance_env);

        return @$decoded->data->environment;
    }

    public function getAllPlans()
    {
        $environment_url = Configuration::get('FINANCE_ENVIRONMENT_URL');

        // Decide the env set by admin somehow...

        $api_key = Configuration::get('FINANCE_API_KEY');
        if (!$environment_url || !$api_key) {
            return array();
        }

        $client = new Guzzle();
        $env = Environment::getEnvironmentFromAPIKey($api_key);

        $httpClientWrapper = new HttpClientWrapper(
            new GuzzleAdapter($client),
            $environment_url,
            $api_key
        );

        $sdk = new Client($httpClientWrapper, $env);

        $requestOptions = (new \Divido\MerchantSDK\Handlers\ApiRequestOptions());

        try {
            $plans = $sdk->finances()->yieldAllPlans($requestOptions);

            $plans_plain = array();
            foreach ($plans as $plan) {
                $plan_copy = new stdClass();
                $plan_copy->id = $plan->id;
                $plan_copy->text = $plan->description;
                $plan_copy->country = $plan->country;
                $plan_copy->min_amount = $plan->credit_amount->minimum_amount;
                $plan_copy->min_deposit = $plan->deposit->minimum_percentage;
                $plan_copy->max_deposit = $plan->deposit->maximum_percentage;
                $plan_copy->interest_rate = $plan->interest_rate_percentage;
                $plan_copy->deferral_period = $plan->deferral_period_months;
                $plan_copy->agreement_duration = $plan->agreement_duration_months;
                if($plan->active){
                    $plans_plain[$plan->id] = $plan_copy;
                }
            }
            return $plans_plain;
        } catch (MerchantApiBadResponseException $e) {
            // Handle exception how you like...
            // $e->getCode() | eg 400401
            // $e->getMessage() | eg resource not found
            // $e->getContext()
        }
    }

    public function getCartPlans($cart)
    {
        $plans     = array();
        $products  = $cart->getProducts();
        foreach ($products as $product) {
            $product_plans = $this->getProductPlans($product['total_wt'], $product['id_product']);
            if ($product_plans) {
                $plans = array_merge($plans, $product_plans);
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

        $product_selection = Configuration::get('FINANCE_PRODUCTS_OPTIONS');
        $price_threshold   = Configuration::get('FINANCE_PRODUCTS_MINIMUM');


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
        $query = "select * from `"._DB_PREFIX_."finance_product` where id_product = '".(int)$id_product."'";

        return Db::getInstance()->getRow($query);
    }


    public function getEnvironment($key)
    {
        $array       = explode('_', $key);
        $environment = Tools::strtoupper($array[0]);
        return ('LIVE' == $environment)
            ? constant("Divido\MerchantSDK\Environment::PRODUCTION")
            : constant("Divido\MerchantSDK\Environment::$environment");
    }


    public function setLender()
    {
        $environment_url = Configuration::get('FINANCE_ENVIRONMENT_URL');
        $api_key = Configuration::get('FINANCE_API_KEY');
        if (!$environment_url || !$api_key) {
            return array();
        }

        $client = new Guzzle();
        $env = Environment::getEnvironmentFromAPIKey($api_key);
        $httpClientWrapper = new HttpClientWrapper(
            new GuzzleAdapter($client),
            $environment_url,
            $api_key
        );

        $sdk = new Client($httpClientWrapper, $env);

        // Set any request options.
        $requestOptions = (new \Divido\MerchantSDK\Handlers\ApiRequestOptions());

        // Retrieve all finance plans for the merchant.
        $plans = $sdk->getAllPlans($requestOptions);

        $data = json_decode(json_encode($plans->getResources()), true);

        Configuration::updateValue('FINANCE_LENDER', $data[0]['lender']['name']);

        return $data[0]['lender']['name'];
    }

    /**
     * @return string
     */
    public function getLender()
    {

        return  Configuration::get('FINANCE_LENDER');
    }
}
