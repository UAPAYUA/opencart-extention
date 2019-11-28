<?php

class ControllerExtensionPaymentUapay extends Controller
{
    public function index()
    {
        $this->load->language('extension/payment/uapay');
        $data = $this->language->all();

        $this->load->model('checkout/order');
        $order_info = $this->model_checkout_order->getOrder($this->session->data['order_id']);

        $this->load->library('UaPayApi');

        $order_id = $order_info['order_id'];

        $amount = $order_info['total'] * $order_info['currency_value'];

        $config = $this->config;
        $uapay = new UapayApi($config);
        $uapay->testMode();
        $uapay->setDataCallbackUrl($this->url->link('extension/payment/uapay/callback&order_id=' . $order_id, '', true));
        $uapay->setDataRedirectUrl($this->url->link('account/order/info&order_id=' . $order_id, '', true));
        $uapay->setDataOrderId(strval($order_id));
        $uapay->setDataAmount($amount);
        $uapay->setDataDescription("Order #{$order_id}");
//        $uapay->setDataEmail(!empty($order_info['email']) ? $order_info['email'] : '');
        $uapay->setDataReusability(0);

        $result = $uapay->createInvoice();
        $this->UaPayApi->writeLog(array($result), '$result', '');
        if (!empty($result['paymentPageUrl'])) {

            $data['redirect_url'] = $result['paymentPageUrl'];
            $data['action'] = $this->url->link('checkout/checkout', '', true);

            $payment_data = [
                'method' => $uapay->getTypeOperation(),
                'amount' => $amount,
                'invoiceId' => $result['id']
            ];
            $this->load->model('extension/payment/uapay');
            $this->model_extension_payment_uapay->addPaymentData($order_id, $payment_data);

            //$this->cart->clear();
        } else {
            $data['error'] = !empty($uapay->messageError) ? $uapay->messageError : '';
        }
        return $this->load->view('extension/payment/uapay', $data);
    }

    public function callback()
    {
        $this->load->library('UaPayApi');
        $this->UaPayApi->writeLog('callback', '');

        //serialize_precision for json_encode
        if (version_compare(phpversion(), '7.1', '>=')) {
            ini_set('serialize_precision', -1);
        }

        $request = $this->request->get;
        $order_id = $request['order_id'];
        if (empty($order_id)) exit;

        $this->load->model('extension/payment/uapay');
        $uapay_payment_info = (array)json_decode($this->model_extension_payment_uapay->getPaymentData($order_id));

        $invoiceId = $uapay_payment_info['invoiceId'];

        $config = $this->config;
        $uapay = new UapayApi($config);
        $uapay->testMode();
        $invoice = $uapay->getDataInvoice($invoiceId);
        $payment = $invoice['payments'][0];

        $this->UaPayApi->writeLog($uapay_payment_info, '$uapay_payment_info', '');
        $this->UaPayApi->writeLog($payment, 'payment', '');
        $this->UaPayApi->writeLog($payment['paymentStatus'], 'paymentStatus', '');
        $this->UaPayApi->writeLog($payment['status'], 'status', '');

        $this->load->language('extension/payment/uapay');
        $this->load->model('checkout/order');
        $amount = $uapay_payment_info['amount'];

        switch ($payment['paymentStatus']) {
            case UaPayApi::STATUS_FINISHED:
                $this->UaPayApi->writeLog('STATUS_FINISHED', '', '');
                if (empty($uapay_payment_info['paymentId']) && !empty($payment['paymentId'])) {
                    $payment_data = [
                        'method' => UaPayApi::STATUS_FINISHED,
                        'amount' => $amount,
                        'invoiceId' => $invoiceId,
                        'paymentId' => $payment['paymentId'],
                    ];
                    $this->UaPayApi->writeLog(array($payment_data), 'new $payment_data', '');

                    $this->model_extension_payment_uapay->addPaymentData($order_id, $payment_data);

                    $this->UaPayApi->writeLog('change status on complete 1', '', '');
                    $this->model_checkout_order->addOrderHistory(
                        $order_id,
                        $this->config->get('payment_uapay_order_status_complete_id'),
                        sprintf($this->language->get('text_pay_success'), $amount),
                        true
                    );
                    $this->UaPayApi->writeLog('change status on complete 2', '', '');
                }
                break;
            case UaPayApi::STATUS_HOLDED:
                if ($payment['status'] == 'PAID') {
                    $this->UaPayApi->writeLog('STATUS_HOLDED status=PAID', '', '');

                    $order_info = $this->model_checkout_order->getOrder($order_id);
                    $this->UaPayApi->writeLog($order_info['order_status_id'], 'order_status_id ', '');
                    $this->UaPayApi->writeLog($this->config->get('payment_uapay_order_status_auth_id'), 'status_auth ', '');

                    if ($order_info['order_status_id'] !== $this->config->get('payment_uapay_order_status_auth_id')) {
                        $payment_data = [
                            'method' => UaPayApi::OPERATION_HOLD,
                            'amount' => $amount,
                            'invoiceId' => $invoiceId,
                            'paymentId' => $payment['paymentId'],
                        ];
                        $this->UaPayApi->writeLog(array($payment_data), '$payment_data new', '');

                        $this->model_extension_payment_uapay->addPaymentData($order_id, $payment_data);
                        $status_name = $this->model_extension_payment_uapay->getStatusName($this->config->get('payment_uapay_order_status_complete_id'), $this->config->get('config_language_id'));

                        $this->UaPayApi->writeLog('STATUS_HOLDED_1 order_status_id !=status_auth', '', '');
                        $this->model_checkout_order->addOrderHistory(
                            $order_id,
                            $this->config->get('payment_uapay_order_status_auth_id'),
                            $this->language->get('text_pay_auth') . $status_name->row['name'],
                            true
                        );
                        $this->UaPayApi->writeLog('STATUS_HOLDED_2 order_status_id !=status_auth', '', '');
                    }
                }
                break;
            case UaPayApi::STATUS_CANCELED:
            case UaPayApi::STATUS_REVERSED:
                break;
            case UaPayApi::STATUS_REJECTED:
                $this->UaPayApi->writeLog('STATUS_REJECTED 1', '', '');

                $order_info = $this->model_checkout_order->getOrder($order_id);
                if ($order_info['order_status_id'] !== $this->config->get('payment_uapay_order_status_failure_id')) {
                    $payment_data = [
                        'method' => UaPayApi::STATUS_REJECTED,
                        'amount' => $amount,
                        'invoiceId' => $invoiceId,
                        'paymentId' => $payment['paymentId'],
                    ];
                    $this->UaPayApi->writeLog(array($payment_data), 'new $payment_data', '');

                    $this->model_extension_payment_uapay->addPaymentData($order_id, $payment_data);

                    $this->model_checkout_order->addOrderHistory(
                        $order_id,
                        $this->config->get('payment_uapay_order_status_failure_id'),
                        $this->language->get('text_pay_failure'),
                        true
                    );
                }
                $this->UaPayApi->writeLog('STATUS_REJECTED 2', '', '');
                break;
        }
        if ($invoice === false) {
            $this->model_checkout_order->addOrderHistory(
                $order_id,
                $this->config->get('payment_uapay_order_status_failure_id'),
                $uapay->messageError,
                true
            );
        }
        exit;
    }

    public function uapayRefund($route, &$args)
    {
        //serialize_precision for json_encode
        if (version_compare(phpversion(), '7.1', '>=')) {
            ini_set('serialize_precision', -1);
        }

        $this->load->library('UaPayApi');
        $this->load->language('extension/payment/uapay');
        $this->UaPayApi->writeLog(array('$args', $args), 'uapayRefund args', '');

        $order_id = (int)$args[0];
        $order_status = $args[1];
        $this->UaPayApi->writeLog($order_status, '$order_status change on', '');

        $this->load->model('checkout/order');
        $order_info = $this->model_checkout_order->getOrder($order_id);
        $this->UaPayApi->writeLog($order_info['order_status_id'], '$order_status this time', '');

        $order_status_listen_refund = $this->config->get('payment_uapay_order_status_listen');
        $this->load->model('extension/payment/uapay');

        if (!empty($order_info) && $order_info['payment_code']) {

            $payment_data = (array)json_decode($this->model_extension_payment_uapay->getPaymentData($order_id));

            if (!empty($payment_data)) {
                $this->UaPayApi->writeLog(array('$payment_data', $payment_data), '', '');
                $status_name = $this->model_extension_payment_uapay->getStatusName($order_status, $this->config->get('config_language_id'));
                $order_status_name = $status_name->row['name'];

                $amount_payment = $payment_data['amount'];

                $config = $this->config;
                $uapay = new UaPayApi($config);
                $uapay->testMode();

                if ($order_status == $this->config->get('payment_uapay_order_status_complete_id')) {
                    if ($payment_data['method'] == UaPayApi::OPERATION_PAY) {// capture
                        $this->UaPayApi->writeLog('status is Complete', '', '');
                        if (!empty($payment_data['paymentId'])) {
                            $uapay->setInvoiceId($payment_data['invoiceId']);
                            $uapay->setPaymentId($payment_data['paymentId']);
                            $result = $uapay->completeInvoice();
                            $this->UaPayApi->writeLog(array($result), '$result completeInvoice', '');

                            if (!empty($result['status'])) {
                                $payment_data = [
                                    'method' => UaPayApi::OPERATION_PAY,
                                    'invoiceId' => $payment_data['invoiceId'],
                                    'paymentId' => $payment_data['paymentId'],
                                    'amount' => $amount_payment
                                ];

                                $this->UaPayApi->writeLog(array('FINISHED_data', $payment_data), '', '');

                                $this->model_extension_payment_uapay->addPaymentData($order_id, $payment_data);

                                $args[2] .= sprintf($this->language->get('text_pay_success'), $amount_payment);
                                $args[3] = true;
                            } else {
                                $this->UaPayApi->writeLog($uapay->messageError, 'complete !empty', '');
                                $refund_message = sprintf('Error! ' . $uapay->messageError);
                                $args[2] .= $refund_message;
                                $args[1] = $order_info['order_status_id'];
                            }
                        } else {
                            $refund_message = sprintf(' Complete UaPay impossible, not enough data.');
                            $args[2] .= $refund_message;
                        }
                    } elseif ($payment_data['method'] == UaPayApi::STATUS_FINISHED) {
                        $payment_data = [
                            'method' => UaPayApi::OPERATION_PAY,
                            'invoiceId' => $payment_data['invoiceId'],
                            'paymentId' => $payment_data['paymentId'],
                            'amount' => $amount_payment
                        ];
                        $this->UaPayApi->writeLog(array('FINISHED_data update', $payment_data), '', '');

                        $this->model_extension_payment_uapay->addPaymentData($order_id, $payment_data);

                    } else {
                        $uapay->setInvoiceId($payment_data['invoiceId']);
                        $uapay->setPaymentId($payment_data['paymentId']);
                        $result = $uapay->completeInvoice();
                        if (empty($result['status'])) {
                            $refund_message = sprintf('Error! ' . $uapay->messageError);
                            $args[2] .= $refund_message;
                            $args[1] = $order_info['order_status_id'];
                        }
                    }
                }// capture
                elseif (is_array($order_status_listen_refund) && in_array($order_status, $order_status_listen_refund)) {// refund
                    $this->UaPayApi->writeLog('status is Refund', '', '');
                    if (!empty($payment_data['paymentId'])) {
                        $invoice = $uapay->getDataInvoice($payment_data['invoiceId']);
                        $payment = $invoice['payments'][0];

                        $this->UaPayApi->writeLog('paymentId+ method-' . $payment_data['method'], '', '');
                        $amount_order = $order_info['total'];
                        $this->UaPayApi->writeLog(array('$amount_payment', $amount_payment), '', '');
                        $this->UaPayApi->writeLog(array('$amount_order', $amount_order), '', '');
                        $this->UaPayApi->writeLog('paymentStatus ', $payment['paymentStatus'], '');

                        $uapay->setInvoiceId($payment_data['invoiceId']);
                        $uapay->setPaymentId($payment_data['paymentId']);

                        switch ($payment['paymentStatus']) {
                            case UaPayApi::STATUS_HOLDED:
                                $result = $uapay->cancelInvoice();
                                $message = $this->language->get('text_pay_void');
                                $method = UaPayApi::STATUS_CANCELED;
                                break;
                            case UaPayApi::STATUS_FINISHED:
                                $result = $uapay->reverseInvoice();
                                $message = $this->language->get('text_pay_refund');
                                $method = UaPayApi::STATUS_REVERSED;
                                break;
                            case UaPayApi::STATUS_CANCELED:
                                $result = $uapay->cancelInvoice();
                                break;
                            case UaPayApi::STATUS_REVERSED:
                                $result = $uapay->reverseInvoice();
                                break;
                        }
                        if (!empty($result['status'])) {
                            $this->UaPayApi->writeLog('method Refund', '', '');

                            $payment_data = [
                                'method' => $method,
                                'invoiceId' => $payment_data['invoiceId'],
                                'paymentId' => $payment_data['paymentId'],
                                'amount' => $amount_payment
                            ];

                            $this->UaPayApi->writeLog(array('$Refund_data', $payment_data), '', '');

                            $this->model_extension_payment_uapay->addPaymentData($order_id, $payment_data);

                            $args[2] .= $message;
                            $args[3] = true;
                        } else {
                            $this->UaPayApi->writeLog($uapay->messageError, 'Refund !empty', '');
                            $refund_message = sprintf('Error! ' . $uapay->messageError);
                            $args[2] .= $refund_message;
                            $args[1] = $order_info['order_status_id'];
                        }
                    } else {
                        $refund_message = sprintf(' Refunds UaPay impossible, not enough data.');
                        $args[2] .= $refund_message;
                        $args[1] = $order_info['order_status_id'];
                    }
                } elseif ($order_status == $this->config->get('payment_uapay_order_status_auth_id')) {
                    $this->UaPayApi->writeLog('change in auth', '', '');

                    $this->UaPayApi->writeLog('now complete or refund', '', '');

                    $message = $this->language->get('text_error') . $order_status_name;
                    $args[2] .= $message;
                    $args[1] = $order_info['order_status_id'];
                } elseif ($order_status == $this->config->get('payment_uapay_order_status_failure_id')) {

                } else {
                    if ($order_info['order_status_id'] == $this->config->get('payment_uapay_order_status_auth_id')
                        && ($order_status != $this->config->get('payment_uapay_order_status_complete_id')
                            || !in_array($order_status, $order_status_listen_refund))) {
                        $message = $this->language->get('text_error') . $order_status_name;
                        $args[2] .= $message;
                        $args[1] = $order_info['order_status_id'];
                    } elseif ($order_info['order_status_id'] == $this->config->get('payment_uapay_order_status_complete_id')
                        && $order_status == $this->config->get('payment_uapay_order_status_auth_id')) {
                        $message = $this->language->get('text_error') . $order_status_name;
                        $args[2] .= $message;
                        $args[1] = $order_info['order_status_id'];
                    } else {
                        
                    }
                }
            }
        }
    }
}