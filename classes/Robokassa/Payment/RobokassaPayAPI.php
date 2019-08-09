<?php

namespace Robokassa\Payment;

class RobokassaPayAPI {

    /**
     * @var string
     */
    private $mrh_login;

    /**
     * @var string
     */
    private $mrh_pass1;

    /**
     * @var string
     */
    private $mrh_pass2;

    /**
     * @var string
     */
    private $method;

    /**
     * @var string
     */
    private $apiUrl;

    /**
     * @var string
     */
    private $reply = '';

    /**
     * @var string
     */
    private $request = '';

    /**
     * @return string
     */
    public function getReply() {
        return $this->reply;
    }

    /**
     * @return string
     */
    public function getRequest() {
        return $this->request;
    }

    /**
     * @return string
     */
    public function getSendResult() {
        return json_encode(array(
            'request' => $this->request,
            'reply' => $this->reply,
        ));
    }

    /**
     * @param string $login
     * @param string $pass1
     * @param string $pass2
     * @param string $method
     */
    public function __construct($login, $pass1, $pass2, $method = 'md5') {
        $this->mrh_login = $login;
        $this->mrh_pass1 = $pass1;
        $this->mrh_pass2 = $pass2;
        $this->method = $method;

        $this->apiUrl = substr($_SERVER['SERVER_PROTOCOL'], 0, -4).'://auth.robokassa.ru/Merchant/WebService/Service.asmx/';
    }

    /**
     * @param string $mthd
     * @param array  $data
     *
     * @return array
     */
    private function sendRequest($mthd, $data) {
        return json_decode($this->parseXmlAndConvertToJson($this->apiUrl.$mthd.'?'.http_build_query($data)), true);
    }

    /**
     * Если $receiptJson пустой (то есть имеет значение "[]") - то в формировании сигнатуры
     * он не использоваться, а если не пустой - используем его json-представление
     *
     * @param string $sum
     * @param string $invId
     * @param string $receiptJson
     *
     * @return string
     */
    private function getSignatureString($sum, $invId, $receiptJson)
    {

        return \implode(
        	':',
            \array_diff(
	            array(
	                $this->mrh_login,
	                $sum,
	                $invId,
	                $receiptJson,
	                $this->mrh_pass1,
	            ),
	            array(
	            	false,
		            '',
		            null
	            )
            )
        );
    }

    /**
     * Генерирует хеш для строки $string с помощью метода $method
     *
     * @param string $string
     * @param string $method
     *
     * @return string
     *
     * @throws \Exception
     */
    public function getSignature($string, $method = 'md5') {
        if (in_array($method, array('md5', 'ripemd160', 'sha1', 'sha256', 'sha384', 'sha512'))) {
            return strtoupper(hash($method, $string));
        }

        throw new \Exception('Wrong Signature Method');
    }

    /**
     * Генерирует форму, в Opencart модуле НЕ ИСПОЛЬЗУЕТСЯ!
     *
     * @param float $sum
     * @param int $invId
     * @param string $invDesc
     * @param string $test
     * @param string $incCurrLabel
     * @param array $receipt
     *
     * @param null $email
     * @return string
     *
     * @throws \Exception
     */
    public function createForm(
        $sum,
        $invId,
        $invDesc,
        $test = 'false',
        $incCurrLabel = 'all',
        $receipt = null,
        $email = null
    ) {

	    $receiptJson = (!empty($receipt) && \is_array($receipt))
	        ? \urlencode(\json_encode($receipt, 256))
		    : null;

        $formData = array(
            'Encoding' => 'utf-8',
            'MrchLogin' => $this->mrh_login,
            'OutSum' => $sum,
            'InvId' => $invId,
            'Desc' => $invDesc,
            'SignatureValue' => $this->getSignature($this->getSignatureString($sum, $invId, $receiptJson)),
        );

        if($email !== null)
            $formData['Email'] = $email;

        $culture = get_option('robokassa_culture');
        if($culture !== Helper::CULTURE_AUTO)
            $formData['Culture'] = $culture;

        if (!empty($receipt)) {
            $formData['Receipt'] = $receiptJson;
        }

        if ($test == 'true') {
            $formData['IsTest'] = 1;
        }

        if ($incCurrLabel !== 'all') {
            $formData['IncCurrLabel'] = $incCurrLabel;
        }

        $robokassaEnabled = get_option('robokassa_payment_wc_robokassa_enabled');

        switch ($robokassaEnabled) {
            case 'torobomarket':
                $formUrl = 'http://robo.market/cart/insert';
                break;
            case 'yes':
                $formUrl = 'https://auth.robokassa.ru/Merchant/Index.aspx';
                break;
            default:
                throw new \Exception('Не ожиданное значение опции "wc_robokassa_enabled"');
        }

        return $this->renderForm($formUrl, $formData);
    }

    /**
     * @param string $formUrl
     * @param array  $formData
     *
     * @return string
     */
    private function renderForm($formUrl, array $formData) {
        $form = "<form action=\"$formUrl\" method=\"POST\">";

        foreach ($formData as $inputName => $inputValue) {
            $value = htmlspecialchars($inputValue, ENT_COMPAT, 'UTF-8');

            $form .= "<input type=\"hidden\" name=\"$inputName\" value=\"$value\">";
        }

        $form .= "<input type=\"submit\" value=\"Оплатить\"></form>";

        return $form;
    }

	/**
	 * Отправляет СМС с помощью GET-запроса на робокассу
	 *
	 * @param string $phone
	 * @param string $message
	 *
	 * @return bool
	 * @throws \Exception
	 */
    public function sendSms($phone, $message) {
        $data = array(
            'login' => $this->mrh_login,
            'phone' => $phone,
            'message' => $message,
            'signature' => $this->getSignature("$this->mrh_login:$phone:$message:$this->mrh_pass1"),
        );

        $url = substr($_SERVER['SERVER_PROTOCOL'], 0, -4).'://services.robokassa.ru/SMS/?'.http_build_query($data);

        $response = file_get_contents($url);
        $parsed = json_decode($response, true);

        $this->request = $url;
        $this->reply = $response;

        return ($parsed['result'] == 1);
    }

    /**
     * Запрашиват размер комиссии в процентах для конкретного способа оплаты
     *
     * @param string $incCurrLabel Кодовое имя метода оплаты
     * @param int    $sum          Стоимость товара
     *
     * @return float Комиссия метода в %
     */
    public function getCommission($incCurrLabel, $sum = 10000) {
        if ($incCurrLabel == 'all') {
            $incCurrLabel = '';
        }

        $parsed = $this->sendRequest('CalcOutSumm', array(
            'MerchantLogin' => $this->mrh_login,
            'IncCurrLabel' => $incCurrLabel,
            'IncSum' => (int) $sum,
        ));

        return abs(round(($sum - $parsed['OutSum']) / $parsed['OutSum'] * 100));
    }

    /**
     * Возвращает сумму к оплате с учетом комиссий.
     *
     * @param string $incCurrLabel Кодовое имя метода оплаты
     * @param int    $sum          Стоимость товара
     *
     * @return float Стоимость, которую необходимо передавать в Робокассу.
     */
    public function getCommissionSum($incCurrLabel, $sum) {
        $parsed = $this->sendRequest('CalcOutSumm', array(
            'MerchantLogin' => $this->mrh_login,
            'IncCurrLabel' => $incCurrLabel,
            'IncSum' => $sum,
        ));

        return $parsed['OutSum'];
    }

    /**
     * Запрашивает и парсит в массив все возможные способы оплаты для данного магазина
     *
     * @return array
     */
    public function getCurrLabels() {
        $parsed = $this->sendRequest('GetCurrencies', array(
            'MerchantLogin' => $this->mrh_login,
            'Language' => 'ru',
        ));

        $outArr = array();
		if(isset($parsed['Groups']))
		{
			foreach ($parsed['Groups']['Group'] as $value) {
				foreach ($value['Items']['Currency'] as $value2) {
					if (isset($value2['@attributes'])) {
						$attr = $value2['@attributes'];

						if ($attr['Name']) {
							$valLabel = $attr['Label'];

							$outArr[$valLabel] = array(
								'Name' => $attr['Name'],
								'Label' => $valLabel,
								'Alias' => $attr['Alias'],
								'Commission' => $this->GetCommission($valLabel),
								'MinValue' => isset($attr['MinValue']) ? $attr['MinValue'] : 0,
								'MaxValue' => isset($attr['MaxValue']) ? $attr['MaxValue'] : 9999999,
							);
						}
					}
				}
			}
		}

        return $outArr;
    }

    /**
     * Парсит XML в JSON
     *
     * @param string $url
     *
     * @return string
     */
    public function parseXmlAndConvertToJson($url) {
        return json_encode(simplexml_load_string(trim(str_replace('"', "'", str_replace(array(
            "\n",
            "\r",
            "\t",
        ), '', file_get_contents($url))))));
    }

    /**
     * Запрашивает у робокассы подтверждение платежа
     *
     * @param int $invId
     *
     * @return bool
     */
    public function reCheck($invId) {
        $result = $this->sendRequest('OpState', array(
            'MerchantLogin' => $this->mrh_login,
            'InvoiceID' => $invId,
            'Signature' => strtoupper(md5("$this->mrh_login:$invId:$this->mrh_pass2")),
        ));

        return ($result['Result']['Code'] == '0');
    }

}
