<?php

namespace Robokassa\Payment;

/**
 * HTTP-клиент API возвратов Robokassa.
 */
class RefundApi
{
	const CREATE_URL = 'https://services.robokassa.ru/RefundService/Refund/Create';
	const STATE_URL = 'https://services.robokassa.ru/RefundService/Refund/GetState';
	const OP_STATE_URL = 'https://auth.robokassa.ru/Merchant/WebService/Service.asmx/OpStateExt';

	/** @var string */
	private $merchantLogin;

	/** @var string */
	private $password2;

	/** @var string */
	private $password3;

	public function __construct($merchantLogin, $password2, $password3)
	{
		$this->merchantLogin = (string)$merchantLogin;
		$this->password2 = (string)$password2;
		$this->password3 = (string)$password3;
	}

	/**
	 * Создаёт заявку на возврат.
	 *
	 * @param string     $operationKey
	 * @param float|null $amount
	 * @param array      $invoiceItems
	 *
	 * @return array|\WP_Error
	 */
	public function create($operationKey, $amount = null, array $invoiceItems = array())
	{
		if ($this->password3 === '') {
			return new \WP_Error('robokassa_refund_password3_missing', 'Не указан пароль магазина #3 для API возвратов Robokassa.');
		}

		$payload = array('OpKey' => (string)$operationKey);

		if ($amount !== null) {
			$payload['RefundSum'] = (float)$amount;
		}

		if (!empty($invoiceItems)) {
			$payload['InvoiceItems'] = array_values($invoiceItems);
		}

		$token = $this->buildToken($payload);
		$response = wp_remote_post(self::CREATE_URL, array(
			'timeout' => 30,
			'headers' => array(
				'Accept' => 'application/json',
				'Content-Type' => 'application/json',
			),
			// По OpenAPI тело является JSON-строкой, содержащей compact JWT.
			'body' => wp_json_encode($token),
		));

		return $this->decodeJsonResponse($response, 'robokassa_refund_create_failed');
	}

	/**
	 * Возвращает состояние ранее созданной заявки.
	 *
	 * @param string $requestId
	 * @return array|\WP_Error
	 */
	public function getState($requestId)
	{
		$url = add_query_arg('id', (string)$requestId, self::STATE_URL);
		$response = wp_remote_get($url, array(
			'timeout' => 15,
			'headers' => array('Accept' => 'application/json'),
		));

		return $this->decodeJsonResponse($response, 'robokassa_refund_state_failed');
	}

	/**
	 * Получает OpKey оплаченной операции по номеру заказа.
	 *
	 * @param int $invoiceId
	 * @return string|\WP_Error
	 */
	public function getOperationKey($invoiceId)
	{
		if ($this->merchantLogin === '' || $this->password2 === '') {
			return new \WP_Error('robokassa_refund_credentials_missing', 'Не заданы логин магазина или пароль #2 Robokassa.');
		}

		$signature = strtoupper(md5($this->merchantLogin . ':' . (int)$invoiceId . ':' . $this->password2));
		$url = add_query_arg(array(
			'MerchantLogin' => $this->merchantLogin,
			'InvoiceID' => (int)$invoiceId,
			'Signature' => $signature,
		), self::OP_STATE_URL);
		$response = wp_remote_get($url, array('timeout' => 15));

		if (is_wp_error($response)) {
			return $response;
		}

		$code = (int)wp_remote_retrieve_response_code($response);
		$body = (string)wp_remote_retrieve_body($response);

		if ($code < 200 || $code >= 300 || trim($body) === '') {
			return new \WP_Error('robokassa_operation_state_failed', 'Robokassa не вернула данные оплаченной операции.');
		}

		$previous = libxml_use_internal_errors(true);
		$xml = simplexml_load_string($body);
		libxml_clear_errors();
		libxml_use_internal_errors($previous);

		if ($xml === false) {
			return new \WP_Error('robokassa_operation_state_invalid_xml', 'Robokassa вернула некорректный ответ OpStateExt.');
		}

		$resultCodes = $xml->xpath('//*[local-name()="Result"]/*[local-name()="Code"]');
		if (!empty($resultCodes) && (string)$resultCodes[0] !== '0') {
			$descriptions = $xml->xpath('//*[local-name()="Result"]/*[local-name()="Description"]');
			$message = !empty($descriptions) ? (string)$descriptions[0] : 'Операция Robokassa не найдена.';
			return new \WP_Error('robokassa_operation_not_found', $message);
		}

		$operationKeys = $xml->xpath('//*[local-name()="OpKey"]');
		$operationKey = !empty($operationKeys) ? trim((string)$operationKeys[0]) : '';

		if ($operationKey === '') {
			return new \WP_Error('robokassa_operation_key_missing', 'Robokassa не вернула OpKey оплаченной операции.');
		}

		return $operationKey;
	}

	/**
	 * Формирует compact JWT с HMAC-SHA256.
	 *
	 * @param array $payload
	 * @return string
	 */
	public function buildToken(array $payload)
	{
		$header = array('alg' => 'HS256', 'typ' => 'JWT');
		$segments = array(
			$this->base64UrlEncode(wp_json_encode($header, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)),
			$this->base64UrlEncode(wp_json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)),
		);
		$signingInput = implode('.', $segments);
		$segments[] = $this->base64UrlEncode(hash_hmac('sha256', $signingInput, $this->password3, true));

		return implode('.', $segments);
	}

	private function base64UrlEncode($value)
	{
		return rtrim(strtr(base64_encode((string)$value), '+/', '-_'), '=');
	}

	/**
	 * @param array|\WP_Error $response
	 * @param string          $errorCode
	 * @return array|\WP_Error
	 */
	private function decodeJsonResponse($response, $errorCode)
	{
		if (is_wp_error($response)) {
			return $response;
		}

		$status = (int)wp_remote_retrieve_response_code($response);
		$body = (string)wp_remote_retrieve_body($response);
		$data = json_decode($body, true);

		if (!is_array($data)) {
			return new \WP_Error($errorCode, 'Robokassa вернула некорректный JSON-ответ.', array('status' => $status));
		}

		if ($status < 200 || $status >= 300) {
			$message = isset($data['message']) && $data['message'] !== ''
				? (string)$data['message']
				: 'HTTP ' . $status;
			return new \WP_Error($errorCode, 'Ошибка API возвратов Robokassa: ' . $message, array('status' => $status));
		}

		return $data;
	}
}
