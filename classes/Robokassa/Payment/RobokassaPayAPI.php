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

		$this->apiUrl = substr($_SERVER['SERVER_PROTOCOL'], 0, -4) . '://auth.robokassa.ru/Merchant/WebService/Service.asmx/';
	}

	/**
	 * @param string $mthd
	 * @param array $data
	 *
	 * @return array
	 */
	private function sendRequest($mthd, $data) {
		return json_decode($this->parseXmlAndConvertToJson($this->apiUrl . $mthd . '?' . http_build_query($data)), true);
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
	private function getSignatureString($sum, $invId, $receiptJson, $recurring = false) {
		$outCurrency = get_option('robokassa_out_currency');
		$holdPaymentParam = (get_option('robokassa_payment_hold_onoff') == '1') ? 'true' : '';

		return \implode(
			':',
			\array_diff(
				array(
					$this->mrh_login,
					$sum,
					$invId,
					$outCurrency,
					$receiptJson,
					$holdPaymentParam,
					urlencode((Util::siteUrl('/?robokassa=result'))),
					$this->mrh_pass1,
					'shp_label=official_wordpress',
					'Shp_merchant_id=' . $this->mrh_login,
					'Shp_order_id=' . $invId,
					'Shp_result_url=' . (Util::siteUrl('/?robokassa=result')),
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
	 *
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
		$email = null,
		$recurring = false
	) {

		$kzUrl = 'https://auth.robokassa.kz/Merchant/Index.aspx';
		$ruUrl = 'https://auth.robokassa.ru/Merchant/Index.aspx';

		if (get_option('robokassa_country_code') == "RU")
			$paymentUrl = $ruUrl;
		elseif (get_option('robokassa_country_code') == "KZ")
			$paymentUrl = $kzUrl;


		$receiptJson = (!empty($receipt) && \is_array($receipt))
			? \urlencode(\json_encode($receipt, 256))
			: null;

		$formData = array(
			//'Encoding' => 'utf-8',
			'MrchLogin' => $this->mrh_login,
			'OutSum' => $sum,
			'InvId' => $invId,
			'ResultUrl2' => urlencode(Util::siteUrl('/?robokassa=result')),
			'Desc' => $invDesc,
			'shp_label' => 'official_wordpress',
			'Shp_merchant_id' => $this->mrh_login,
			'Shp_order_id' => $invId,
			'Shp_result_url' => Util::siteUrl('/?robokassa=result'),
			'recurring' => $recurring ? 'true' : '',
			'SignatureValue' => $this->getSignature($this->getSignatureString($sum, $invId, $receiptJson)),
		);

		if (get_option('robokassa_payment_hold_onoff') == 1) {
			$formData['StepByStep'] = 'true';
		}

		//$formData['OutSumCurrency'] = get_option('robokassa_out_currency');

		if ($email !== null)
			$formData['Email'] = $email;


		$culture = get_option('robokassa_culture');
		if ($culture !== Helper::CULTURE_AUTO)
			$formData['Culture'] = $culture;

		if (!empty($receipt)) {
			$formData['Receipt'] = $receiptJson;
		}

		if ($test == 'true') {
			$formData['IsTest'] = 1;
		}

		/*		if ($incCurrLabel !== 'all') {
					$formData['IncCurrLabel'] = "Podeli";
				}*/

		$robokassaEnabled = get_option('robokassa_payment_wc_robokassa_enabled');

		switch ($robokassaEnabled) {
			case 'yes':
				$formUrl = $paymentUrl;
				break;
			default:
				throw new \Exception('Не ожиданное значение опции "wc_robokassa_enabled"');
		}

		return $this->renderForm($formUrl, $formData);
	}


	/**
	 * @param string $formUrl
	 * @param array $formData
	 *
	 * @return string
	 */
	private function renderForm($formUrl, array $formData) {
		$chosenMethod = (string)WC()->session->get('chosen_payment_method');

		if (get_option('robokassa_iframe')) {
			return $this->renderIframePayment($formData);
		}

		if ($this->isDirectPaymentMethod($chosenMethod)) {
			return $this->renderDirectPayment($chosenMethod, $formData);
		}

		return $this->renderAutoSubmitForm($formUrl, $formData);
	}

	/**
	 * Формирует скрипт запуска iframe-оплаты без промежуточных переходов.
	 *
	 * @param array $formData
	 *
	 * @return string
	 */
	private function renderIframePayment(array $formData) {
		$scriptUrl = $this->getIframeScriptUrl();
		$params = $this->buildPaymentPayload($formData);

		$script = '<script type="text/javascript" src="' . esc_url($scriptUrl) . '"></script>';
		$script .= '<script type="text/javascript">';
		$script .= 'document.addEventListener("DOMContentLoaded", function(){';
		$script .= 'if (window.Robokassa && typeof window.Robokassa.StartPayment === "function") {';
		$script .= 'window.Robokassa.StartPayment(' . $params . ');';
		$script .= '}';
		$script .= '});';
		$script .= '</script>';

		return $this->buildRedirectNotice() . $script;
	}

	/**
	 * Формирует скрипт прямого запуска партнёрских оплат.
	 *
	 * @param string $chosenMethod
	 * @param array $formData
	 *
	 * @return string
	 */
	private function renderDirectPayment($chosenMethod, array $formData) {
		$label = $this->getDirectPaymentLabel($chosenMethod);

		if ($label !== '') {
			$formData['IncCurrLabel'] = $label;
		}

		$params = $this->buildPaymentPayload($formData);
		$script = '<script type="text/javascript" src="https://auth.robokassa.ru/Merchant/PaymentForm/DirectPayment.js"></script>';
		$script .= '<script type="text/javascript">';
		$script .= 'document.addEventListener("DOMContentLoaded", function(){';
		$script .= 'if (window.Robo && window.Robo.directPayment && typeof window.Robo.directPayment.startOp === "function") {';
		$script .= 'window.Robo.directPayment.startOp(' . $params . ');';
		$script .= '}';
		$script .= '});';
		$script .= '</script>';

		return $this->buildRedirectNotice() . $script;
	}

	/**
	 * Строит стандартную HTML-форму и автоматически её отправляет.
	 *
	 * @param string $formUrl
	 * @param array $formData
	 *
	 * @return string
	 */
	private function renderAutoSubmitForm($formUrl, array $formData) {
		$formId = $this->generateHtmlId('robokassa-payment-form-');
		$manualId = $this->generateHtmlId('robokassa-redirect-manual-');
		$wrapper = $this->formatHtmlAttributes([
			'class' => 'robokassa-redirect-wrapper',
			'data-form-id' => $formId,
			'data-manual-id' => $manualId,
			'data-manual-delay' => '6000',
			'data-submit-delay' => '200',
		]);

		$form = '<div ' . $wrapper . '>';
		$form .= $this->buildRedirectNotice($manualId, $formUrl);
		$form .= $this->buildAutoSubmitFormHtml($formUrl, $formData, $formId);
		$form .= '</div>';

		return $form;
	}

	/**
	 * Формирует блок уведомления с информацией о перенаправлении.
	 *
	 * @param string $manualId
	 * @param string $formUrl
	 *
	 * @return string
	 */
	private function buildRedirectNotice($manualId = '', $formUrl = '') {
		$messages = $this->getRedirectNoticeMessages();

		$notice = '<div class="robokassa-redirect-notice" role="status" aria-live="polite">';
		$notice .= '<p class="robokassa-redirect-title">' . esc_html($messages['title']) . '</p>';
		$notice .= '<div class="robokassa-redirect-status">';
		$notice .= '<span class="robokassa-redirect-loader" aria-hidden="true"></span>';
		$notice .= '<p class="robokassa-redirect-message">' . esc_html($messages['message']) . '</p>';
		$notice .= '</div>';

		$notice .= '</div>';

		return $notice;
	}

	/**
	 * Возвращает локализованные сообщения для блока перенаправления.
	 *
	 * @return array
	 */
	private function getRedirectNoticeMessages() {
		$locale = function_exists('determine_locale') ? determine_locale() : get_locale();

		if (strpos((string)$locale, 'ru') === 0) {
			return [
				'title' => __('Спасибо за ваш заказ!', 'robokassa'),
				'message' => __('Пожалуйста, подождите, выполняется перенаправление на страницу оплаты.', 'robokassa'),
			];
		}

		return [
			'title' => __('Thank you for your order!', 'robokassa'),
			'message' => __('Please wait while we redirect you to the payment page.', 'robokassa'),
		];
	}

	/**
	 * Собирает HTML формы для автоперехода на оплату.
	 *
	 * @param string $formUrl
	 * @param array $formData
	 * @param string $formId
	 *
	 * @return string
	 */
	private function buildAutoSubmitFormHtml($formUrl, array $formData, $formId) {
		$attributes = $this->formatHtmlAttributes([
			'action' => $formUrl,
			'method' => 'POST',
			'id' => $formId,
		]);
		$form = '<form ' . $attributes . '>';
		$form .= $this->buildFormInputs($formData);
		$form .= '</form>';

		return $form;
	}

	/**
	 * Формирует набор скрытых полей для отправки в Robokassa.
	 *
	 * @param array $formData
	 *
	 * @return string
	 */
	private function buildFormInputs(array $formData) {
		$inputs = '';

		foreach ($formData as $inputName => $inputValue) {
			$value = htmlspecialchars($inputValue, ENT_COMPAT, 'UTF-8');
			$inputs .= '<input type="hidden" name="' . esc_attr($inputName) . '" value="' . $value . '">';
		}

		return $inputs;
	}

	/**
	 * Генерирует безопасный уникальный идентификатор для HTML-элементов.
	 *
	 * @param string $prefix
	 *
	 * @return string
	 */
	private function generateHtmlId($prefix) {
		if (function_exists('wp_unique_id')) {
			return wp_unique_id($prefix);
		}

		return $prefix . uniqid();
	}

	/**
	 * Подготавливает строку с HTML-атрибутами.
	 *
	 * @param array $attributes
	 *
	 * @return string
	 */
	private function formatHtmlAttributes(array $attributes) {
		$result = [];

		foreach ($attributes as $name => $value) {
			$result[] = $name . '="' . esc_attr($value) . '"';
		}

		return implode(' ', $result);
	}

	/**
	 * Определяет, является ли способ прямым подключением партнёра.
	 *
	 * @param string $chosenMethod
	 *
	 * @return bool
	 */
	private function isDirectPaymentMethod($chosenMethod) {
		return in_array($chosenMethod, array(
			'robokassa_podeli',
			'robokassa_credit',
			'robokassa_mokka',
			'robokassa_split',
		), true);
	}

	/**
	 * Возвращает значение параметра IncCurrLabel для партнёрских оплат.
	 *
	 * @param string $chosenMethod
	 *
	 * @return string
	 */
	private function getDirectPaymentLabel($chosenMethod) {
		$labels = array(
			'robokassa_podeli' => 'Podeli',
			'robokassa_credit' => 'OTP',
			'robokassa_mokka' => 'Mokka',
			'robokassa_split' => 'YandexPaySplit',
		);

		if (isset($labels[$chosenMethod])) {
			return $labels[$chosenMethod];
		}

		return '';
	}

	/**
	 * Подготавливает данные формы к встраиванию в JavaScript.
	 *
	 * @param array $formData
	 *
	 * @return string
	 */
	private function buildPaymentPayload(array $formData) {
		$payload = array();

		foreach ($formData as $inputName => $inputValue) {
			$payload[$inputName] = $inputValue;
		}

		$json = wp_json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

		if (!is_string($json)) {
			return '{}';
		}

		return $json;
	}

	/**
	 * Возвращает URL для подключения iframe-скрипта Robokassa.
	 *
	 * @return string
	 */
	private function getIframeScriptUrl() {
		if (get_option('robokassa_country_code') === 'KZ') {
			return 'https://auth.robokassa.kz/Merchant/bundle/robokassa_iframe.js';
		}

		return 'https://auth.robokassa.ru/Merchant/bundle/robokassa_iframe.js';
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

		$url = substr($_SERVER['SERVER_PROTOCOL'], 0, -4) . '://services.robokassa.ru/SMS/?' . http_build_query($data);

		$response = file_get_contents($url);
		$parsed = json_decode($response, true);

		$this->request = $url;
		$this->reply = $response;

		return ($parsed['result'] == 1);
	}

	/**
	 * Запрашивает и парсит в массив все возможные способы оплаты для данного магазина
	 *
	 * @return array
	 */
	public function getCurrLabels() {
		return $this->sendRequest('GetCurrencies', array(
			'MerchantLogin' => $this->mrh_login,
			'Language' => 'ru',
		));
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


	public function getRecurringPaymentData($invoiceId, $parentInvoiceId, $amount, $receipt, $description = '') {
		// $receipt = (get_option('robokassa_payment_type_commission') == 'false' && get_option('robokassa_country_code') != 'KZ') ? $receipt : [];
		$receiptJson = (!empty($receipt) && \is_array($receipt)) ? \urlencode(\json_encode($receipt, 256)) : null;

		$data = array_filter([
			'MerchantLogin' => $this->mrh_login,
			'InvoiceID' => $invoiceId,
			'PreviousInvoiceID' => $parentInvoiceId,
			'Description' => '',
			'SignatureValue' => md5("{$this->mrh_login}:{$amount}:{$invoiceId}:{$receiptJson}:{$this->mrh_pass1}:shp_label=official_wordpress:Shp_merchant_id=" . get_option('robokassa_payment_MerchantLogin') . ":Shp_order_id={$invoiceId}:Shp_result_url=" . Util::siteUrl('/?robokassa=result')),
			'OutSum' => $amount,
			'shp_label' => 'official_wordpress',
			'Shp_merchant_id' => get_option('robokassa_payment_MerchantLogin'),
			'Shp_order_id' => $invoiceId,
			'Shp_result_url' => Util::siteUrl('/?robokassa=result'),
			'Receipt' => $receiptJson
		], function($val) {
			return $val !== null;
		});

		return $data;
	}
}