<?php

namespace Robokassa\Tests\Unit;

use InvalidArgumentException;
use Robokassa\Payment\RobokassaPayAPI;
use Robokassa\Tests\Support\TestCase;

class RobokassaPayAPITest extends TestCase {
	/** @dataProvider serverProtocols */
	public function testServiceApiAlwaysUsesHttps(?string $protocol): void {
		$previousProtocol = $_SERVER['SERVER_PROTOCOL'] ?? null;
		if ($protocol === null) {
			unset($_SERVER['SERVER_PROTOCOL']);
		} else {
			$_SERVER['SERVER_PROTOCOL'] = $protocol;
		}
		$requests = array();
		$GLOBALS['robokassa_test_stream_callback'] = function ($url) use (&$requests) {
			$requests[] = $url;
			return '<Currencies><Result><Code>0</Code></Result></Currencies>';
		};
		try {
			$result = (new RobokassaPayAPI('merchant', 'pass1', 'pass2'))->getCurrLabels();

			$this->assertSame(array('https://auth.robokassa.ru/Merchant/WebService/Service.asmx/GetCurrencies?MerchantLogin=merchant&Language=ru'), $requests);
			$this->assertSame('0', $result['Result']['Code']);
		} finally {
			$this->restoreServerProtocol($previousProtocol);
		}
	}

	/** @dataProvider serverProtocols */
	public function testSmsAlwaysUsesHttpsAndPreservesSignedParameters(?string $protocol): void {
		$previousProtocol = $_SERVER['SERVER_PROTOCOL'] ?? null;
		if ($protocol === null) {
			unset($_SERVER['SERVER_PROTOCOL']);
		} else {
			$_SERVER['SERVER_PROTOCOL'] = $protocol;
		}
		$requests = array();
		$GLOBALS['robokassa_test_http_callback'] = function ($method, $url, $args) use (&$requests) {
			$requests[] = $url;
			return array('response' => array('code' => 200), 'body' => '{"result":1}');
		};
		try {
			$api = new RobokassaPayAPI('merchant', 'pass1', 'pass2');
			$this->assertTrue($api->sendSms('79001234567', 'Заказ 42 оплачен'));
			$this->assertCount(1, $requests);
			$this->assertSame('https://services.robokassa.ru/SMS/', strtok($requests[0], '?'));
			parse_str(parse_url($requests[0], PHP_URL_QUERY), $parameters);
			$this->assertSame(array(
				'login' => 'merchant',
				'phone' => '79001234567',
				'message' => 'Заказ 42 оплачен',
				'signature' => strtoupper(md5('merchant:79001234567:Заказ 42 оплачен:pass1')),
			), $parameters);
			$this->assertSame($requests[0], $api->getRequest());
			$this->assertSame('{"result":1}', $api->getReply());
		} finally {
			$this->restoreServerProtocol($previousProtocol);
		}
	}

	/** @dataProvider smsReplies */
	public function testSmsRejectsUnconfirmedResponses($response, ?bool $expected): void {
		$GLOBALS['robokassa_test_http_callback'] = function ($method, $url, $args) use ($response) {
			$this->assertSame('GET', $method);
			$this->assertSame(0, $args['redirection']);
			return $response;
		};
		$api = new RobokassaPayAPI('merchant', 'pass1', 'pass2');
		if ($expected === null) {
			$this->expectException(\RuntimeException::class);
			$this->expectExceptionMessage('SMS response is unconfirmed');
			$api->sendSms('79001234567', 'message');
		} else {
			$this->assertSame($expected, $api->sendSms('79001234567', 'message'));
		}
	}

	public static function smsReplies(): array {
		$cases = array();
		foreach (array(true, 1, '1', false, 0, '0') as $index => $result) {
			$cases['explicit result ' . $index] = array(array('response' => array('code' => 200), 'body' => json_encode(array('result' => $result))), in_array($result, array(true, 1, '1'), true));
		}
		foreach (array('invalid JSON' => '{', 'no result' => '{}', 'ambiguous' => '{"result":2}', 'scalar' => 'false') as $name => $body) {
			$cases[$name] = array(array('response' => array('code' => 200), 'body' => $body), null);
		}
		$cases['string HTTP code'] = array(array('response' => array('code' => '200'), 'body' => '{"result":1}'), true);
		$cases['timeout'] = array(new \WP_Error('timeout', 'private HTTP detail'), null);
		$cases['server error'] = array(array('response' => array('code' => 500), 'body' => '{"result":1}'), null);
		$cases['redirect'] = array(array('response' => array('code' => 302), 'body' => '{"result":1}'), null);
		return $cases;
	}

	public static function serverProtocols(): array {
		return array(
			'http 1.0' => array('HTTP/1.0'),
			'http 1.1' => array('HTTP/1.1'),
			'http 2' => array('HTTP/2'),
			'http 2.0' => array('HTTP/2.0'),
			'http 3' => array('HTTP/3'),
			'missing cli' => array(null),
		);
	}

	private function restoreServerProtocol(?string $protocol): void {
		if ($protocol === null) {
			unset($_SERVER['SERVER_PROTOCOL']);
		} else {
			$_SERVER['SERVER_PROTOCOL'] = $protocol;
		}
		unset($GLOBALS['robokassa_test_stream_callback']);
	}

	public function testGeneratesSupportedSignaturesAndRejectsUnknownAlgorithm(): void {
		$api = new RobokassaPayAPI('merchant', 'pass1', 'pass2');

		$this->assertSame(strtoupper(md5('payload')), $api->getSignature('payload'));
		$this->assertSame(strtoupper(hash('sha256', 'payload')), $api->getSignature('payload', 'sha256'));

		$this->expectException(\Exception::class);
		$this->expectExceptionMessage('Wrong Signature Method');
		$api->getSignature('payload', 'crc32');
	}

	public function testCreatesAutoSubmitFormWithSignedPaymentFields(): void {
		$this->setBasePaymentOptions();
		$GLOBALS['robokassa_test_wc']->session->set('chosen_payment_method', 'robokassa');
		$api = new RobokassaPayAPI('merchant', 'pass1', 'pass2');

		$html = $api->createForm(
			125.50,
			42,
			'Order 42',
			'true',
			'all',
			array('items' => array(array('name' => 'Product'))),
			'buyer@example.test'
		);

		$this->assertStringContainsString('action="https://auth.robokassa.ru/Merchant/Index.aspx"', $html);
		$this->assertStringContainsString('name="MrchLogin" value="merchant"', $html);
		$this->assertStringContainsString('name="InvId" value="42"', $html);
		$this->assertStringContainsString('name="IsTest" value="1"', $html);
		$this->assertStringContainsString('name="Email" value="buyer@example.test"', $html);
		$this->assertStringContainsString('name="SignatureValue"', $html);
		$this->assertStringContainsString('robokassa-redirect-wrapper', $html);
	}

	/** @dataProvider directPaymentProvider */
	public function testCreatesDirectPaymentForPartnerMethods($gateway, $alias): void {
		$this->setBasePaymentOptions();
		$GLOBALS['robokassa_test_wc']->session->set('chosen_payment_method', $gateway);
		$api = new RobokassaPayAPI('merchant', 'pass1', 'pass2');

		$html = $api->createForm(100, 7, 'Order', 'false', 'all', null, 'buyer@example.test');

		$this->assertStringContainsString('DirectPayment.js', $html);
		$this->assertStringContainsString('"IncCurrLabel":"' . $alias . '"', $html);
	}

	public function directPaymentProvider(): array {
		return array(
			'podeli' => array('robokassa_podeli', 'Podeli'),
			'credit' => array('robokassa_credit', 'OTP'),
			'mokka' => array('robokassa_mokka', 'Mokka'),
			'split' => array('robokassa_split', 'YandexPaySplit'),
		);
	}

	public function testCreatesIframePaymentUsingCountryScript(): void {
		$this->setBasePaymentOptions(array(
			'robokassa_country_code' => 'KZ',
			'robokassa_iframe' => 1,
		));
		$GLOBALS['robokassa_test_wc']->session->set('chosen_payment_method', 'robokassa');

		$html = (new RobokassaPayAPI('merchant', 'pass1', 'pass2'))
			->createForm(100, 8, 'Order');

		$this->assertStringContainsString('https://auth.robokassa.kz/Merchant/bundle/robokassa_iframe.js', $html);
		$this->assertStringContainsString('Robokassa.StartPayment', $html);
	}

	public function testCreatesSbpOptionsAndSignature(): void {
		$this->setBasePaymentOptions();
		$GLOBALS['robokassa_test_wc']->session->set('chosen_payment_method', 'robokassa_sbp');

		$html = (new RobokassaPayAPI('merchant', 'pass1', 'pass2'))
			->createForm(100, 9, 'Order', 'false', 'all', null, 'buyer@example.test');

		$this->assertStringContainsString('"paymentMethod":"SBP"', $html);
		$this->assertStringContainsString('"merchantLogin":"merchant"', $html);
		$this->assertStringContainsString('"invId":9', $html);
		$this->assertStringContainsString('function(url){ window.robokassaSbpLink = url; return url; }', $html);
	}

	public function testSbpRequiresEmail(): void {
		$this->setBasePaymentOptions();
		$GLOBALS['robokassa_test_wc']->session->set('chosen_payment_method', 'robokassa_sbp');

		$this->expectException(InvalidArgumentException::class);
		(new RobokassaPayAPI('merchant', 'pass1', 'pass2'))->createForm(100, 9, 'Order');
	}

	public function testBuildsRecurringPaymentDataWithReceipt(): void {
		$this->setBasePaymentOptions();
		$receipt = array('items' => array(array('name' => 'Item')));
		$api = new RobokassaPayAPI('merchant', 'pass1', 'pass2');

		$data = $api->getRecurringPaymentData(22, 11, '49.90', $receipt, 'Ignored');
		$encodedReceipt = urlencode(json_encode($receipt, 256));
		$expectedSign = md5(
			'merchant:49.90:22:' . $encodedReceipt
			. ':pass1:shp_label=official_wordpress:Shp_merchant_id=merchant'
			. ':Shp_order_id=22:Shp_result_url=https://shop.example.test/?robokassa=result'
		);

		$this->assertSame(11, $data['PreviousInvoiceID']);
		$this->assertSame($encodedReceipt, $data['Receipt']);
		$this->assertSame($expectedSign, $data['SignatureValue']);
	}

	public function testRejectsDisabledGateway(): void {
		$this->setBasePaymentOptions(array('robokassa_payment_wc_robokassa_enabled' => 'no'));
		$GLOBALS['robokassa_test_wc']->session->set('chosen_payment_method', 'robokassa');

		$this->expectException(\Exception::class);
		$this->expectExceptionMessage('Не ожиданное значение опции');
		(new RobokassaPayAPI('merchant', 'pass1', 'pass2'))->createForm(100, 1, 'Order');
	}

	private function setBasePaymentOptions(array $overrides = array()): void {
		$this->setOptions(array_merge(
			array(
				'robokassa_country_code' => 'RU',
				'robokassa_payment_wc_robokassa_enabled' => 'yes',
				'robokassa_payment_MerchantLogin' => 'merchant',
				'robokassa_payment_hold_onoff' => 0,
				'robokassa_iframe' => 0,
				'robokassa_culture' => 'auto',
				'robokassa_out_currency' => '',
			),
			$overrides
		));
	}
}
