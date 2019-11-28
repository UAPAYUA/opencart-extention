<?php
require_once __DIR__ . '/Jweety/JWTEncoderInterface.php';
require_once __DIR__ . '/Jweety/JWTEncoder.php';

class UaPayApi
{
    const wrLog = false;

    const METHOD_POST = 'POST';
    const METHOD_GET = 'GET';
    const METHOD_PUT = 'PUT';

    const SYSTYPE_P2P = 'P2P';
    const SYSTYPE_ECOM = 'ECOM';

    const OPERATION_PAY = 'PAY';
    const OPERATION_HOLD = 'HOLD';
    const OPERATION_SUBSCRIBE = 'SUBSCRIBE';

    const STATUS_FINISHED = 'FINISHED'; //  Платіж завершено успішно, гроші відправлено одержувачу
    const STATUS_HOLDED = 'HOLDED';    //   Необхідно підтвердження. Для завершення списання коштів потрібно виконати підтвердження.
    const STATUS_CANCELED = 'CANCELED'; //  Процес оплати не завершений та платіж був відхилений (обірвалося з'єднання, платіж зупинений на проміжному етапі з вини платника).
    const STATUS_REVERSED = 'REVERSED'; //  Платіж повернуто, кошти повернулися відправнику.
    const STATUS_REJECTED = 'REJECTED'; //  Платіж не відбувся з технічних причин.
    const STATUS_NEED_CONFIRM = 'NEEDS_CONFIRMATION'; //    Платіж очікує підтвердження (лукап або 3ds)
    const STATUS_PENDING = 'PENDING';    //     Платіж знаходиться в стані оплати (проміжний статус)

    private $headers = [];
    private $payload = [];

    private $apiURL = 'https://api.uapay.ua/';
    private $apiTestURL = 'https://api.demo.uapay.ua/';

    private $clientId;
    private $secretKey;
    private $testMode;
    private $type_payment;
    private $user_redirect_url;

    private $JWTEncoder;

    public $messageError = '';

    function __construct($registry = null)
    {
        $this->config = $registry;

        $this->clientId = trim($this->config->get('payment_uapay_client_id'));
        $this->secretKey = trim($this->config->get('payment_uapay_secret_key'));
        $this->testMode = ($this->config->get('payment_uapay_test_mode') == '1') ? 1 : 0;
        $this->type_payment = mb_strtoupper(trim($this->config->get('payment_uapay_type_payment')));
        $this->user_redirect_url = !empty($this->config->get('payment_uapay_redirect_url')) ? trim($this->config->get('payment_uapay_redirect_url')) : '';

        $this->JWTEncoder = new Jweety\JWTEncoder($this->secretKey);
    }

    public function testMode()
    {
        if (boolval($this->testMode)) {
            $this->apiURL = $this->apiTestURL;
        }
    }

    public function setPaymentId($paymentId = '')
    {
        $this->payload['params']['paymentId'] = strval(trim($paymentId));
    }

    public function setInvoiceId($invoiceId = '')
    {
        $this->payload['params']['invoiceId'] = strval(trim($invoiceId));
    }

//  public function setAmount($amount = 0)
//  {
//      $this->payload['params']['amount'] = self::formattedAmount($amount);
//  }

    public function setParamSystemType($type = '')
    {
        if (in_array($type, [self::SYSTYPE_ECOM, self::SYSTYPE_P2P])) {
            $this->payload['params']['systemType'] = $type;
        } else {
            $this->payload['params']['systemType'] = self::SYSTYPE_P2P;
        }
    }

    public function setDataTypeOperation($type = '')
    {
        self::writeLog($type, '$type1');
        if (in_array($type, [self::OPERATION_PAY, self::OPERATION_HOLD, self::OPERATION_SUBSCRIBE])) {
            $this->payload['data']['type'] = strval($type);
        } else {
            $this->payload['data']['type'] = strval(self::OPERATION_PAY);
        }
        self::writeLog($this->payload['data']['type'], '$type2');
    }

    public function setDataOrderId($id = 0)
    {
        $this->payload['data']['externalId'] = strval(trim($id));
    }

    public function setDataAmount($amount = 0)
    {
        $this->payload['data']['amount'] = self::formattedAmount($amount);
    }

    public function setDataDescription($description)
    {
        $this->payload['data']['description'] = strval(trim($description));
    }

    public function setDataEmail($email)
    {
        $this->payload['data']['email'] = strval(trim($email));
    }

    public function setDataRedirectUrl($redirectUrl)
    {
        if (!empty($this->user_redirect_url))
            $this->payload['data']['redirectUrl'] = htmlspecialchars_decode($this->user_redirect_url);
        else
            $this->payload['data']['redirectUrl'] = strval(trim($redirectUrl));
    }

    public function setDataCallbackUrl($callbackUrl)
    {
        $this->payload['data']['callbackUrl'] = strval(trim($callbackUrl));
    }

    public function setDataReusability($isReusability = 1)
    {
        $this->payload['data']['reusability'] = boolval($isReusability);
    }

//  public function setDataRecurringInterval($recurringInterval = 1)
//  {
//      $this->payload['data']['callbackrecurringInterval'] = intval($recurringInterval);
//  }

//  public function setDataExpiresAt($expiresAt = 1)
//  {
//      $this->payload['data']['expiresAt'] = intval($expiresAt);
//  }

//  public function setDataCardToId($cardToId)
//  {
//      $this->payload['data']['cardTo']['id'] = strval(trim($cardToId));
//  }

    /**
     * @param string $operation
     * @return array|bool|mixed|object
     */
    public function createInvoice()
    {
        self::writeLog('createInvoice', '', '');
        $session = $this->createSession();
        if ($session === true) {
            $this->setParamSystemType(self::SYSTYPE_ECOM);
            $this->setDataTypeOperation($this->type_payment);
            $result = $this->request('api/invoicer/invoices/create');

            return $result;
        }

        return $session;
    }

    public function getTypeOperation()
    {
        return $this->type_payment;
    }

    public function getDataInvoice($id)
    {
        self::writeLog('getDataInvoice', '', '');
        $session = $this->createSession();
        if ($session === true) {
            $this->setParamId($id);
            $result = $this->request('api/invoicer/invoices/show');

            return $result;
        }

        return $session;
    }

    /**
     * method called after operation type HOLD
     * @return array|bool|mixed|object|string
     */
    public function completeInvoice()
    {
        self::writeLog('completeInvoice', '', '');
        $session = $this->createSession();
        if ($session === true) {
            $result = $this->request('api/invoicer/payments/complete');

            return $result;
        }

        return $session;
    }

    /**
     * method called after HOLD
     * @return array|bool|mixed|object
     */
    public function cancelInvoice()
    {
        self::writeLog('cancelInvoice', '', '');
        $session = $this->createSession();
        if ($session === true) {
            $result = $this->request('api/invoicer/payments/cancel');

            return $result;
        }

        return $session;
    }

    /**
     * method called after payment
     * @return array|bool|mixed|object
     */
    public function reverseInvoice()
    {
        self::writeLog('reverseInvoice', '', '');
        $session = $this->createSession();
        if ($session === true) {
            $result = $this->request('api/invoicer/payments/reverse');

            return $result;
        }

        return $session;
    }

//  public function createAuthCard($cardNumber = '', $expireAt = '')
//  {
//      $payload['params']['sessionId'] = $this->getParamSessionId();
//      $payload['data']['pan'] = $cardNumber;
//      $payload['data']['expiresAt'] = $expireAt;
//
//      $result = $this->request('api/cards/create', $payload);
//      if(!empty($result['status']) && !empty($result['data']['id'])) {
//          return $result['data']['id'];
//      }
//
//      return false;
//  }

    private function setParamSessionId($id = '')
    {
        $this->payload['params']['sessionId'] = strval(trim($id));
    }

    private function setParamId($id = '')
    {
        $this->payload['params']['id'] = strval(trim($id));
    }

    public function getParamSessionId($id = '')
    {
        return $this->payload['params']['sessionId'] = strval(trim($id));
    }

    private function createSession()
    {
        if (!empty($this->getParamSessionId())) {
            return true;
        }
        $payload['params']['clientId'] = $this->clientId;
        $result = $this->request('api/sessions/create', $payload);
        if (!empty($result['status']) && !empty($result['data']['token'])) {
            $session = $this->parseSign($result['data']['token']);
            $this->setParamSessionId($session['id']);

            return true;
        }

        return $result;
    }

    public static function formattedAmount($number = 0)
    {
        $amount = is_string($number) ? str_replace(',', '.', $number) : $number;

        $amount = (int)(floatval($amount) * 100);

        return $amount;
    }

    private function formalizeErrorMessage($data = [])
    {
        $result = '';
        if (!empty($data['code']) || !empty($data['message'])) {
            $msgType = !empty($data['code']) ? ucfirst($data['code']) . ': ' : '';
            $msg = !empty($data['message']) ? [$msgType . $data['message']] : [];
            if (!empty($data['fields']['params'])) {
                $fields_params = $data['fields']['params'];
                foreach ($fields_params as $field => $value) {
                    $msg[] = 'params.' . $field . ': ' . $value;
                }
            }
            if (!empty($data['fields']['data'])) {
                $fields_params = $data['fields']['data'];
                foreach ($fields_params as $field => $value) {
                    $msg[] = 'data.' . $field . ': ' . $value;
                }
            }
            $result = implode(', ', $msg);
        }

        return $result;
    }

//  public function getPaymentFromStatus($data = [], $status)
//  {
//      $payment = [];
//      if(!empty($data)){
//          foreach($data as $item){
//              if($item['paymentStatus'] == $status){
//                  $payment = $item;
//                  break;
//              }
//          }
//      }
//
//      return $payment;
//  }

    private function generateSign($params)
    {
        return $this->JWTEncoder->stringify($params);
    }

    public function parseSign($str)
    {
        return $this->JWTEncoder->parse($str);
    }

//  public function resetParams()
//  {
//      $this->payload = [];
//  }

    private function setHeader($header)
    {
        if (!in_array($header, $this->headers)) {
            $this->headers[] = $header;
        }
    }

    public function getHeaders()
    {
        return $this->headers;
    }

    /**
     * @param $uri
     * @param array $payload
     * @param string $method
     * @return array|bool|mixed|object
     */
    private function request($uri, $payload = [], $method = self::METHOD_POST)
    {
        $url = $this->apiURL . $uri;

        $params = !empty($payload) ? $payload : (!empty($this->payload) ? $this->payload : []);

        $this->setHeader('Content-Type: application/json');

        $params['iat'] = time();
        $params['token'] = $this->generateSign($params);

        $ch = curl_init();
        if ($method === self::METHOD_POST) {
            $data = json_encode($params);

            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        } elseif ($method === self::METHOD_GET) {
            $data = !empty($params) ? '?' . http_build_query($params) : '';

            curl_setopt($ch, CURLOPT_URL, $url . $data);
        } else {
            curl_setopt($ch, CURLOPT_URL, $url);
        }

        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $this->getHeaders());

        $server_response = curl_exec($ch);
        $http_info = curl_getinfo($ch);
        $errNum = curl_errno($ch);
        $errMsg = curl_error($ch);

        curl_close($ch);
        // for check request
        self::writeLog($url, '', '', 1);

        if ($errMsg) {
            self::writeLog($errMsg, 'Error Message');
        }

        if ($errNum && empty($server_response)) {
            $this->messageError = $errMsg;
            return false;
        } else {

            $result = json_decode($server_response, true) ? json_decode($server_response, true) : $server_response;

            if (!empty($result['error'])) {
                self::writeLog(array($result['error']), '$result error');
//                $this->messageError = $this->formalizeErrorMessage($result['error']);
                $this->messageError = $result['error']['message'];
            }

            if (!empty($result['data']['token'])) {
                $result = array_merge($result, $this->parseSign($result['data']['token']));
            }

            return !empty($this->messageError) ? false : $result;
        }
    }

    /**
     * @param $data
     * @param string $flag
     * @param string $filename
     * @param bool|true $append
     */
    static function writeLog($data, $flag = '', $filename = '', $append = true)
    {
        if (self::wrLog) {
            $filename = !empty($filename) ? strval($filename) : 'resultRequest';

            if (is_string($data)) {
                $data = json_decode($data) ? json_decode($data, 1) : $data;
            }

            file_put_contents(__DIR__ . "/{$filename}.log", "\n\n" . date('Y-m-d H:i:s') . " - $flag \n" .
                (is_array($data) ? json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) : $data)
                , ($append ? FILE_APPEND : 0)
            );
        }
    }

}