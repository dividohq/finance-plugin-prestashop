<?php
// modules/financepayment/src/Controller/StatusController.php

namespace Prestashop\Module\Financepayment\Controller;

require_once dirname(__FILE__) . '/../../classes/divido.class.php';

use PrestaShopBundle\Controller\Admin\FrameworkBundleAdminController;
use Divido\Proxy\FinanceApi;
use Configuration;
use Tools;
use Divido\Exceptions\DividoOrderPaymentException;

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
            'notify' => false,
            'action' => false
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

                $return['application_id'] = $application['id'];
                $return['lender'] = $application['lender']['app_name'];
                $return['reasons'] = self::REASONS[$application['lender']['app_name']] ?? null;
            
                switch($newOrderStatus){
                    case Configuration::get('FINANCE_CANCELLATION_STATUS'):
                        $return['amount'] = $application['amounts']['cancelable_amount']/100;
                        $return['message'] = sprintf(
                            "<p>Are you sure you want to cancel this order?<p>
                            <p>The de-facto cancelable amount for this application is %s%s.</p>
                        ", $currency, $return['amount']);
                        $return['action'] = self::ACTIONS['cancel'];
                        break;
                    case Configuration::get('FINANCE_REFUND_STATUS'):
                        $return['amount'] = $application['amounts']['refundable_amount']/100;
                        $return['message'] = sprintf("
                            <p>Are you sure you want to refund this order?</p>
                            <p>The de-facto amount refundable for this application is %s%s. Any refund attempt exceeding this will be processed as a full refund for %s%s</p>
                        ", $currency, $return['amount'], $currency, $return['amount']);
                        $return['action'] = self::ACTIONS['refund']; 
                        break;
                }

                $return['notify'] = true;
            }
        } catch (DividoOrderPaymentException $e){
            $return['message'] = '<p>The customer has not proceeded through the application yet, so you are unable to notify the lender.</p>';
        } catch (\Divido\MerchantSDK\Exceptions\MerchantApiBadResponseException $e){
            $return['message'] = '<p>It appears you may be using a different API Key to the one used to create this application. Please revert to that API key if you wish to notify the lender of this status change</p>';
            \PrestaShopLogger::addLog(sprintf("Bad response from Divido: %s", $e->getMessage()));
        } catch(\JsonException $e){
            $return['message'] = '<p>There was an error reading the related application.</p>';
        } catch(\Exception $e){
            $return['message'] = sprintf("<p>An unexpected error has occured: %s</p>", $e->getMessage());
            $return['action'] = false;
        }
        return $this->json($return);
    }

    public function updateAction(){
        $action = Tools::getValue('action');
        $orderId = Tools::getValue('orderId');
        $newPrestaStatus = Tools::getValue('status');
        $applicationId = Tools::getValue('applicationId');
        $amount = Tools::getValue('amount');
        $reason = (Tools::getValue('reason') === false) ? null : Tools::getValue('reason');

        $return = [
            'success' => false,
            'message' => 'Nothing Happened',
            'action' => $action,
            'reason' => $reason,
            'amount' => $amount,
            'order_id' => $orderId,
            'application_id' => $applicationId
        ];
        
        try{
            $order = $this->getOrderFromId($orderId);

            switch($action){
                case self::ACTIONS['cancel']:
                    //cancel order
                    $response = FinanceApi::cancelApplication($applicationId, $orderId, $amount, $reason);
                    $return = array_merge($return, [
                        'success' => true,
                        'message' => sprintf(
                            'The lender has been notified of the cancellation request (Cancellation ID. %s)',
                            $response['id']
                        ),
                        'cancellation_id' => $response['id']
                    ]);
                    break;
                case self::ACTIONS['refund']:
                    //refund order
                    $response = FinanceApi::refundApplication($applicationId, $orderId, $amount, $reason);
                    $return = array_merge($return, [
                        'success' => true,
                        'message' => sprintf(
                            'The lender has been notified of the refund request (Refund ID. %s',
                            $response['id']
                        ),
                        'refund_id' => $response['id']
                    ]);
                    break;
                default:
                    $return = array_merge($return, [
                        'success' => false,
                        'message' => sprintf(
                            'There is nothing to perform for this action (%s)',
                            $action
                        )
                    ]);
                    break;
            }

            $order->setCurrentState($newPrestaStatus);

        } catch (\Divido\MerchantSDK\Exceptions\MerchantApiBadResponseException $e){
            \PrestaShopLogger::addLog(sprintf("Bad response from Divido: %s", $e->getMessage()));
            $return = array_merge($return, [
                'success' => false,
                'message' => sprintf("Can not notify lender: %s.", $e->getMessage())
            ]);
        } catch (\Exception $e) {
            $return = array_merge($return, [
                'success' => false,
                'message' => sprintf(
                    "An error occured whilst attempting to notify the lender: %s", 
                    $e->getMessage()
                )
            ]);
        }

        return $this->json($return);
        
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
        
        $payment = $this->getFirstDividoOrderPayment($order);

        if($payment === null){
            throw new DividoOrderPaymentException("Can not find relevent payment related to this order");
        }

        $applicationId = $payment->transaction_id;

        $application = FinanceApi::getApplication($applicationId);

        return $application;
    }

    /**
     * Fetches the first Order Payment related to Divido
     * Would throw an error if more than one is in the db,
     * but there's a bug in the order creation currently which creates two Payments
     *
     * @param \Order $order
     * @return \OrderPayment|null
     */
    public function getFirstDividoOrderPayment(\Order $order):?\OrderPayment{
        $payments = $order->getOrderPayments();

        foreach($payments as $payment){
            if($payment->payment_method === 'Powered By Divido' && $payment->transaction_id != ''){
                //TODO: retrieve payment method name from a const
                return $payment;
            }
        }
        return null;
    }
}