<?php

namespace Robokassa\Tests\Unit;

use Robokassa\Payment\RefundApi;
use Robokassa\Tests\Support\TestCase;

class RefundApiTest extends TestCase
{
	public function testBuildsHs256CompactJwt(): void
	{
		$api = new RefundApi('merchant', 'password2', 'secret');
		$token = $api->buildToken(array('OpKey' => 'operation-key', 'RefundSum' => 12.5));
		$segments = explode('.', $token);

		$this->assertCount(3, $segments);
		$this->assertSame(array('alg' => 'HS256', 'typ' => 'JWT'), $this->decodeSegment($segments[0]));
		$this->assertSame(array('OpKey' => 'operation-key', 'RefundSum' => 12.5), $this->decodeSegment($segments[1]));
		$this->assertSame(
			$this->base64Url(hash_hmac('sha256', $segments[0] . '.' . $segments[1], 'secret', true)),
			$segments[2]
		);
	}

	public function testSendsJwtAsJsonStringAndParsesCreateResponse(): void
	{
		$GLOBALS['robokassa_test_http_callback'] = function ($method, $url, $args) {
			$this->assertSame('POST', $method);
			$this->assertSame(RefundApi::CREATE_URL, $url);
			$this->assertSame('application/json', $args['headers']['Content-Type']);
			$this->assertIsString(json_decode($args['body'], true));

			return array(
				'response' => array('code' => 200),
				'body' => '{"success":true,"message":null,"requestId":"68cd7fa6-1338-4745-ba5c-28d16cbcdb3d"}',
			);
		};

		$result = (new RefundApi('merchant', 'password2', 'password3'))->create('operation-key', 10.0);

		$this->assertTrue($result['success']);
		$this->assertSame('68cd7fa6-1338-4745-ba5c-28d16cbcdb3d', $result['requestId']);
	}

	public function testGetsOperationKeyUsingSignedOpStateRequest(): void
	{
		$GLOBALS['robokassa_test_http_callback'] = function ($method, $url) {
			$this->assertSame('GET', $method);
			$this->assertStringContainsString('MerchantLogin=merchant', $url);
			$this->assertStringContainsString('InvoiceID=42', $url);
			$this->assertStringContainsString(
				'Signature=' . strtoupper(md5('merchant:42:password2')),
				$url
			);
			return array(
				'response' => array('code' => 200),
				'body' => '<OperationStateResponse xmlns="http://merchant.roboxchange.com/WebService/"><Result><Code>0</Code></Result><Info><OpKey>operation-key</OpKey></Info></OperationStateResponse>',
			);
		};

		$this->assertSame(
			'operation-key',
			(new RefundApi('merchant', 'password2', 'password3'))->getOperationKey(42)
		);
	}

	public function testReturnsErrorForRejectedCreateRequest(): void
	{
		$GLOBALS['robokassa_test_http_callback'] = function () {
			return array(
				'response' => array('code' => 401),
				'body' => '{"success":false,"message":"Signature key is not exists","requestId":null}',
			);
		};

		$result = (new RefundApi('merchant', 'password2', 'wrong'))->create('operation-key', 10);

		$this->assertInstanceOf(\WP_Error::class, $result);
		$this->assertStringContainsString('Signature key is not exists', $result->get_error_message());
	}

	private function decodeSegment($segment): array
	{
		$padding = strlen($segment) % 4;
		if ($padding > 0) {
			$segment .= str_repeat('=', 4 - $padding);
		}
		return json_decode(base64_decode(strtr($segment, '-_', '+/')), true);
	}

	private function base64Url($value): string
	{
		return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
	}
}
