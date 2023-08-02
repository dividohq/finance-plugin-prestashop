<?php
// modules/financepayment/src/Controller/StatusController.php

namespace Prestashop\Module\Financepayment\Controller;

require_once dirname(__FILE__) . '/../../classes/divido.class.php';

use PrestaShopBundle\Controller\Admin\FrameworkBundleAdminController;
use Divido\Proxy\FinanceApi;
use Configuration;
use Tools;

class StatusController extends FrameworkBundleAdminController
{
    const ACTIONS = [
        'cancel' => 'cancel',
        'refund' => 'refund'
    ];

    const REASONS = [
        "novuna" => [
            "ALTERNATIVE_PAYMENT_METHOD_USED" => "Alternative Payment Method Used",
            "GOODS_FAULTY" => "Goods Faulty",
            "GOODS_NOT_RECEIVED" => "Goods Not Received",
            "GOODS_RETURNED" => "Goods Returned",
            "LOAN_AMENDED" => "Loan Amended",
            "NOT_GOING_AHEAD" => "Not Going Ahead",
            "NO_CUSTOMER_INFORMATION" => "No Customer Information"
        ]
    ];

    public function reasonAction()
    {
        $newOrderStatus = Tools::getValue('newOrderStatus');
        $orderId = Tools::getValue('orderId');

        $return = [
            'message' => 'Unactionable event',
            'reasons' => null,
            'notify' => true,
            'action' => true
        ];

        try{
            $order = $this->getOrderFromId($orderId);
            
            if(
                $order->payment === $this->trans('Powered By Divido', 'Module.financepayment.Admin', []) &&
                ($newOrderStatus == Configuration::get('FINANCE_CANCELLATION_STATUS') || 
                $newOrderStatus == Configuration::get('FINANCE_REFUND_STATUS'))
            ){
                $return['action'] = 'continue';
                $currency = $this->getOrderCurrencySymbol($order);
                $application = $this->getApplicationFromOrder($order);
                //TODO: admend: won't necessarily find application if cancelling 
                $return['application_id'] = $application['id'];
                $return['reasons'] = self::REASONS[$application['lender']['app_name']] ?? null;
            
                switch($newOrderStatus){
                    case Configuration::get('FINANCE_CANCELLATION_STATUS'):
                        $cancelable = $application['amounts']['cancelable_amount']/100;
                        $return['message'] = sprintf(
                            "<p>Are you sure you want to cancel this order?<p>
                            <p>The de-facto cancelable amount for this application is %s%s.</p>
                        ", $currency, $cancelable);
                        $return['action'] = self::ACTIONS['cancel'];
                        break;
                    case Configuration::get('FINANCE_REFUND_STATUS'):
                        $refundable = $application['amounts']['refundable_amount']/100;
                        $return['message'] = sprintf("
                            <p>Are you sure you want to refund this order?</p>
                            <p>The de-facto amount refundable for this application is %s%s. Any refund attempt exceeding this will be processed as a full refund for %s%s</p>
                        ", $currency, $refundable, $currency, $refundable);
                        $return['action'] = self::ACTIONS['refund']; 
                        break;
                }

                $return['notify'] = true;
            }

        } catch (\Divido\MerchantSDK\Exceptions\MerchantApiBadResponseException $e){
            $return['message'] = '<p>It appears you may be using a different API Key to the one used to create this application. Please revert to that API key if you wish to notify the lender of this status change</p>';
        } catch(\JsonException $e){
            $return['message'] = '<p>There was an error reading this application.</p>';
        } catch(\Exception $e){
            $return['message'] = sprintf("<p>%s</p>", $e->getMessage());
        }
        return $this->json($return);
    }

    public function updateAction(){
        $action = Tools::getValue('action');
        $orderId = Tools::getValue('orderId');
        $applicationId = Tools::getValue('applicationId');

        return $this->json([
            'success' => false,
            'message' => 'This is just a test',
            'application_id' => $applicationId
        ]);
        /*
        switch($action){
            case self::ACTIONS['cancel']:
                //cancel order
                $response = FinanceApi::cancelApplication($applicationId);
                break;
            case self::ACTIONS['refund']:
                //refund order
                $response = FinanceApi::refundApplication($applicationId);
                break;
            default:
                // send error that action unrecognised
        }

        // handle response

        return $this->json($return);
        */
    }

    public function getOrderFromId($orderId){
        $order = new \Order((int) $orderId);
        if(!($order)){
            throw new \Exception(sprintf("Could not find order with ID %s", $orderId));
        }
        
        return $order;
    }

    public function getOrderCurrencySymbol(\Order $order){
        $currencyId = $order->id_currency;
        $currency = new \Currency($currencyId);
        return $currency->symbol;
    }

    public function getApplicationFromOrder(\Order $order){
        
        $payment = FinanceApi::getDividoOrderPayment($order);

        $applicationId = $payment->transaction_id;

        $application = FinanceApi::getApplication($applicationId);

        return $application;
    }
}