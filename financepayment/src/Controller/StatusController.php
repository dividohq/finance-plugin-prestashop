<?php
// modules/financepayment/src/Controller/StatusController.php

namespace Prestashop\Module\Financepayment\Controller;

require_once dirname(__FILE__) . '/../../financepayment.php';
require_once dirname(__FILE__) . '/../../classes/divido.class.php';

use PrestaShopBundle\Controller\Admin\FrameworkBundleAdminController;
use Divido\Proxy\FinanceApi;
use FinancePayment;
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

    const FEEDBACK = [
        'cancel_confirmation' => 'Are you sure you want to cancel this order?',
        'cancellable_amount' => 'The de-facto amount that can be cancelled by the lender for this application is %s',
        'refund_confirmation' => 'Are you sure you want to refund this order?',
        'refundable_amount' => 'The de-facto amount refundable for this application is %s. Any refund attempt exceeding this will be processed as a full refund for %s',
        'order_payment_exception' => 'The customer has not proceeded through the application yet, so you are unable to notify the lender',
        'merchant_api_bad_response_exception' => 'It appears you may be using a different API Key to the one used to create this application. Please revert to that API key if you wish to notify the lender of this status change',
        'unexpected_error' => 'An unexpected error has occurred',
        'cancel_success' => 'The lender has been notified of the cancellation request (Cancellation ID. %s)',
        'refund_success' => 'The lender has been notified of the refund request (Refund ID. %s)',
        'unrecognised_action' => 'There is nothing to perform for this action (%s)',
        'update_unexpected_error' => 'An error occurred whilst attempting to notify the lender',
        'order_id_exception' => 'Could not find order with ID',
        'divido_order_payment_exception' => 'The invoice related to this order could not be found'
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
                $order->payment === FinancePayment::DISPLAY_NAME &&
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
                        $return['amount'] = $application['amounts']['cancelable_amount'];
                        $return['message'] = sprintf(
                            "<p>%s<p>
                            <p>%s</p>",
                            $this->_t(self::FEEDBACK['cancel_confirmation']),
                            sprintf(
                                $this->_t(self::FEEDBACK['cancellable_amount']),
                                sprintf("%s%s", $currency, number_format($return['amount']/100,2))
                            )
                        );
                        $return['action'] = self::ACTIONS['cancel'];
                        break;
                    case Configuration::get('FINANCE_REFUND_STATUS'):
                        $return['amount'] = $application['amounts']['refundable_amount'];
                        $return['message'] = sprintf("
                            <p>%s</p>
                            <p>%s</p>", 
                            $this->_t(self::FEEDBACK['refund_confirmation']),
                            sprintf(
                                $this->_t(self::FEEDBACK['refundable_amount']),
                                sprintf("%s%s", $currency, number_format($return['amount']/100,2)), 
                                sprintf("%s%s", $currency, number_format($return['amount']/100,2))
                            )
                        );
                        $return['action'] = self::ACTIONS['refund']; 
                        break;
                }

                $return['notify'] = true;
            }
        } catch (DividoOrderPaymentException $e){
            $return['message'] = $this->_t(self::FEEDBACK['order_payment_exception']);
        } catch (\Divido\MerchantSDK\Exceptions\MerchantApiBadResponseException $e){
            $return['message'] = $this->_t(self::FEEDBACK['merchant_api_bad_response_exception']);
            \PrestaShopLogger::addLog(sprintf("%s: %s", self::FEEDBACK['merchant_api_exception'], $e->getMessage()));
        } catch(\JsonException $e){
            $return['message'] = $this->_t(self::FEEDBACK['merchant_api_bad_response_exception']);
            \PrestaShopLogger::addLog(
                sprintf(
                    "Received a JsonException when trying to process the API respone: %s - %s", 
                    $e->getMessage(), 
                    json_last_error_msg()
                )
            );
        } catch(\Exception $e){
            $return['message'] = sprintf("%s: %s", $this->_t(self::FEEDBACK['unexpected_error']), $e->getMessage());
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
                            $this->_t(self::FEEDBACK['cancel_success']),
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
                            $this->_t(self::FEEDBACK['refund_success']),
                            $response['id']
                        ),
                        'refund_id' => $response['id']
                    ]);
                    break;
                default:
                    $return = array_merge($return, [
                        'success' => false,
                        'message' => sprintf(
                            $this->_t(self::FEEDBACK['unrecognised_action']),
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
                'message' => sprintf(
                    "%s: %s", 
                    $this->_t(self::FEEDBACK['update_unexpected_error']), 
                    $e->getMessage()
                )
            ]);
        } catch (\Exception $e) {
            $return = array_merge($return, [
                'success' => false,
                'message' => sprintf(
                    "%s: %s",
                    $this->_t(self::FEEDBACK['update_unexpected_error']),
                    $e->getMessage()
                )
            ]);
        }

        return $this->json($return);
        
    }

    public function getOrderFromId($orderId){
        $order = new \Order((int) $orderId);
        if(!($order)){
            throw new \Exception(sprintf("%s %s", $this->_t(self::FEEDBACK['order_id_exception']), $orderId));
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
            throw new DividoOrderPaymentException(
                $this->_t(self::FEEDBACK['divido_order_payment_exception'])
            );
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
            if($payment->payment_method === FinancePayment::DISPLAY_NAME && $payment->transaction_id != ''){
                return $payment;
            }
        }
        return null;
    }

    private function _t(string $default):string{
        return $this->trans($default, 'Modules.Financepayment.Admin', []);
    }
}