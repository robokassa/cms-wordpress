<?php

namespace Robokassa\Payment;

/**
 * Проверяет и декодирует подписанные уведомления Robokassa ResultUrl2.
 */
class ResultUrl2Notification
{
	/** @var string */
	private $merchantLogin;

	/** @var string|null */
	private $publicKey;

	public function __construct($merchantLogin, $publicKey = null)
	{
		$this->merchantLogin = (string)$merchantLogin;
		$this->publicKey = $publicKey;
	}

	/**
	 * @param string $jws
	 * @return array|\WP_Error Декодированное поле data.
	 */
	public function verify($jws)
	{
		if (!function_exists('openssl_verify')) {
			return new \WP_Error('robokassa_result2_openssl_missing', 'Для проверки ResultUrl2 требуется расширение OpenSSL.');
		}

		$segments = explode('.', trim((string)$jws));
		if (count($segments) !== 3) {
			return new \WP_Error('robokassa_result2_invalid_format', 'Некорректный формат ResultUrl2 JWS.');
		}

		$headerJson = $this->base64UrlDecode($segments[0]);
		$payloadJson = $this->base64UrlDecode($segments[1]);
		$signature = $this->base64UrlDecode($segments[2]);
		if ($headerJson === false || $payloadJson === false || $signature === false) {
			return new \WP_Error('robokassa_result2_invalid_base64', 'Некорректное кодирование ResultUrl2 JWS.');
		}

		$header = json_decode($headerJson, true);
		$payload = json_decode($payloadJson, true);
		if (!is_array($header)
			|| !is_array($payload)
			|| ($header['alg'] ?? '') !== 'RS256'
			|| ($header['typ'] ?? '') !== 'JWT'
		) {
			return new \WP_Error('robokassa_result2_invalid_algorithm', 'ResultUrl2 должен быть подписан алгоритмом RS256.');
		}

		$keyMaterial = $this->getPublicKey();
		if (strpos($keyMaterial, 'BEGIN CERTIFICATE') !== false) {
			$certificate = openssl_x509_read($keyMaterial);
			$certificateData = $certificate !== false ? openssl_x509_parse($certificate) : false;
			$now = time();
			if (!is_array($certificateData)
				|| (int)($certificateData['validFrom_time_t'] ?? 0) > $now
				|| (int)($certificateData['validTo_time_t'] ?? 0) < $now
			) {
				return new \WP_Error('robokassa_result2_certificate_expired', 'Сертификат ResultUrl2 Robokassa недействителен или истёк.');
			}
		}

		$publicKey = openssl_pkey_get_public($keyMaterial);
		if ($publicKey === false) {
			return new \WP_Error('robokassa_result2_certificate_invalid', 'Не удалось прочитать сертификат ResultUrl2 Robokassa.');
		}

		$verified = openssl_verify(
			$segments[0] . '.' . $segments[1],
			$signature,
			$publicKey,
			OPENSSL_ALGO_SHA256
		);
		if ($verified !== 1) {
			return new \WP_Error('robokassa_result2_bad_signature', 'Подпись ResultUrl2 Robokassa не прошла проверку.');
		}

		$data = isset($payload['data']) && is_array($payload['data']) ? $payload['data'] : array();
		$notificationHeader = isset($payload['header']) && is_array($payload['header']) ? $payload['header'] : array();
		if (($notificationHeader['type'] ?? '') !== 'PaymentStateNotification') {
			return new \WP_Error('robokassa_result2_invalid_type', 'Неизвестный тип уведомления ResultUrl2.');
		}
		$timestamp = isset($notificationHeader['timestamp']) ? (string)$notificationHeader['timestamp'] : '';
		$maxAge = (int)apply_filters('robokassa_result2_max_age', 7 * 24 * 60 * 60);
		if (!ctype_digit($timestamp)
			|| (int)$timestamp > time() + 5 * 60
			|| (int)$timestamp < time() - $maxAge
		) {
			return new \WP_Error('robokassa_result2_invalid_timestamp', 'Недопустимое время уведомления ResultUrl2.');
		}
		if (($data['shop'] ?? '') !== $this->merchantLogin) {
			return new \WP_Error('robokassa_result2_wrong_shop', 'ResultUrl2 предназначен для другого магазина.');
		}
		if (empty($data['invId']) || empty($data['opKey']) || !in_array(($data['state'] ?? ''), array('OK', 'HOLD'), true)) {
			return new \WP_Error('robokassa_result2_invalid_data', 'ResultUrl2 не содержит обязательных данных операции.');
		}

		$data['_notificationTimestamp'] = (int)$timestamp;

		return $data;
	}

	private function getPublicKey()
	{
		if ($this->publicKey !== null) {
			return $this->publicKey;
		}
		$cached = get_option('robokassa_result_url2_certificate', '');
		if (is_string($cached) && strpos($cached, '-----BEGIN') !== false) {
			return $cached;
		}

		$path = dirname(__DIR__, 3) . '/certificates/robokassa-result-url-2.cer';
		$path = apply_filters('robokassa_result_url2_certificate_path', $path);
		$certificate = is_readable($path) ? file_get_contents($path) : '';
		if ($certificate === '') {
			return '';
		}

		return self::normalizeCertificate($certificate);
	}

	/** @return string */
	public static function normalizeCertificate($certificate)
	{
		if (strpos((string)$certificate, '-----BEGIN') !== false) {
			return (string)$certificate;
		}

		return "-----BEGIN CERTIFICATE-----\n"
			. chunk_split(base64_encode((string)$certificate), 64, "\n")
			. "-----END CERTIFICATE-----\n";
	}

	/** @return string|false */
	private function base64UrlDecode($value)
	{
		$value = strtr((string)$value, '-_', '+/');
		$padding = strlen($value) % 4;
		if ($padding > 0) {
			$value .= str_repeat('=', 4 - $padding);
		}

		return base64_decode($value, true);
	}
}
