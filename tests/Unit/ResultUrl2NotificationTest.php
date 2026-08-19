<?php

namespace Robokassa\Tests\Unit;

use Robokassa\Payment\ResultUrl2Notification;
use Robokassa\Tests\Support\TestCase;

class ResultUrl2NotificationTest extends TestCase
{
	public function testVerifiesRs256NotificationAndReturnsOperationData(): void
	{
		list($jws, $publicKey) = $this->createNotification(array(
			'shop' => 'merchant',
			'opKey' => 'operation-key',
			'invId' => '42',
			'paymentMethod' => 'BankCard',
			'incSum' => '100.00',
			'state' => 'OK',
		));

		$data = (new ResultUrl2Notification('merchant', $publicKey))->verify($jws);

		$this->assertFalse(is_wp_error($data));
		$this->assertSame('operation-key', $data['opKey']);
		$this->assertSame('42', $data['invId']);
	}

	public function testRejectsTamperedNotification(): void
	{
		list($jws, $publicKey) = $this->createNotification(array(
			'shop' => 'merchant',
			'opKey' => 'operation-key',
			'invId' => '42',
			'paymentMethod' => 'BankCard',
			'incSum' => '100.00',
			'state' => 'OK',
		));
		$segments = explode('.', $jws);
		$segments[1] = $this->base64Url(json_encode(array(
			'header' => array('type' => 'PaymentStateNotification', 'version' => '1.0.0', 'timestamp' => '1'),
			'data' => array('shop' => 'merchant', 'opKey' => 'attacker-key', 'invId' => '42', 'state' => 'OK'),
		)));

		$result = (new ResultUrl2Notification('merchant', $publicKey))->verify(implode('.', $segments));

		$this->assertInstanceOf(\WP_Error::class, $result);
		$this->assertSame('robokassa_result2_bad_signature', $result->get_error_code());
	}

	public function testRejectsNotificationForAnotherMerchant(): void
	{
		list($jws, $publicKey) = $this->createNotification(array(
			'shop' => 'another-merchant',
			'opKey' => 'operation-key',
			'invId' => '42',
			'paymentMethod' => 'BankCard',
			'incSum' => '100.00',
			'state' => 'OK',
		));

		$result = (new ResultUrl2Notification('merchant', $publicKey))->verify($jws);

		$this->assertInstanceOf(\WP_Error::class, $result);
		$this->assertSame('robokassa_result2_wrong_shop', $result->get_error_code());
	}

	public function testBundledCertificateCanBeLoaded(): void
	{
		list($jws) = $this->createNotification(array(
			'shop' => 'merchant',
			'opKey' => 'operation-key',
			'invId' => '42',
			'paymentMethod' => 'BankCard',
			'incSum' => '100.00',
			'state' => 'OK',
		));

		$result = (new ResultUrl2Notification('merchant'))->verify($jws);

		$this->assertInstanceOf(\WP_Error::class, $result);
		$this->assertSame('robokassa_result2_bad_signature', $result->get_error_code());
	}

	private function createNotification(array $data): array
	{
		$privateKey = openssl_pkey_new(array(
			'private_key_bits' => 2048,
			'private_key_type' => OPENSSL_KEYTYPE_RSA,
		));
		$details = openssl_pkey_get_details($privateKey);
		$header = $this->base64Url(json_encode(array('typ' => 'JWT', 'alg' => 'RS256')));
		$payload = $this->base64Url(json_encode(array(
			'header' => array(
				'type' => 'PaymentStateNotification',
				'version' => '1.0.0',
				'timestamp' => (string)time(),
			),
			'data' => $data,
		)));
		openssl_sign($header . '.' . $payload, $signature, $privateKey, OPENSSL_ALGO_SHA256);

		return array($header . '.' . $payload . '.' . $this->base64Url($signature), $details['key']);
	}

	private function base64Url($value): string
	{
		return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
	}
}
