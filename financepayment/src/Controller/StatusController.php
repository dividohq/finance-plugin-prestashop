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
                            $this->_t('cancel_confirmation_prompt'),
                            sprintf(
                                $this->_t('cancel_amount_warning_msg'),
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
                            $this->_t('refund_confirmation_prompt'),
                            sprintf(
                                $this->_t('refund_amount_warning_msg'),
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
            $return['message'] = $this->_t('journey_incomplete_warning_msg');
        } catch (\Divido\MerchantSDK\Exceptions\MerchantApiBadResponseException $e){
            $return['message'] = $this->_t('update_api_key_error_msg');
            \PrestaShopLogger::addLog(sprintf("Merchant API Exception: %s", $e->getMessage()));
        } catch(\JsonException $e){
            $return['message'] = $this->_t('unexpected_error_msg');
            \PrestaShopLogger::addLog(
                sprintf(
                    "Received a JsonException when trying to process the API respone: %s - %s", 
                    $e->getMessage(), 
                    json_last_error_msg()
                )
            );
        } catch(\Exception $e){
            $return['message'] = sprintf("%s: %s", $this->_t('unexpected_error_msg'), $e->getMessage());
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
                            $this->_t('cancel_success_msg'),
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
                            $this->_t('refund_success_msg'),
                            $response['id']
                        ),
                        'refund_id' => $response['id']
                    ]);
                    break;
                default:
                    $return = array_merge($return, [
                        'success' => false,
                        'message' => sprintf(
                            $this->_t('unrecognised_action_error_msg'),
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
                    $this->_t('update_generic_error_msg'), 
                    $e->getMessage()
                )
            ]);
        } catch (\Exception $e) {
            $return = array_merge($return, [
                'success' => false,
                'message' => sprintf(
                    "%s: %s",
                    $this->_t('update_generic_error_msg'),
                    $e->getMessage()
                )
            ]);
        }

        return $this->json($return);
        
    }

    public function getOrderFromId($orderId){
        $order = new \Order((int) $orderId);
        if(!($order)){
            throw new \Exception(sprintf("%s %s", $this->_t('order_id_error_msg'), $orderId));
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
                $this->_t('invoice_unfound_error_msg')
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

    private function _t(string $key):string{
        return $this->trans($key, 'Modules.Financepayment', []);
    }
}