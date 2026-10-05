<?php

namespace Robokassa\Tests\Unit;

use Robokassa\Tests\Support\TestCase;

class CoreFunctionsTest extends TestCase {
	/** @dataProvider amountProvider */
	public function testNormalizesFormattedAmounts($input, $expected): void {
		$this->assertSame($expected, \robokassa_normalize_amount_value($input));
	}

	public function amountProvider(): array {
		return array(
			'decimal comma' => array('1 234,56 ₽', 1234.56),
			'decimal point' => array('99.90', 99.9),
			'integer' => array(42, 42.0),
			'invalid type' => array(array('42'), 0.0),
		);
	}

	public function testAliasNormalizationAndDetails(): void {
		$this->assertSame('SBP', \robokassa_normalize_alias_key('ignored', array('Alias' => ' sbp ')));
		$this->assertSame('OTP', \robokassa_normalize_alias_key(' otp ', array()));
		$this->assertSame(
			array('Alias' => 'SBP', 'MinValue' => '10', 'MaxValue' => '1000'),
			\robokassa_prepare_alias_details('SBP', array('MinValue' => 10, 'MaxValue' => 1000))
		);
	}

	public function testHandlesCurrencyAliasCatalogState(): void {
		$aliases = \robokassa_get_available_currency_aliases();
		$this->assertIsArray($aliases);
		$this->assertFalse(\robokassa_is_currency_alias_available('missing-alias'));
		$this->assertFalse(\robokassa_is_amount_allowed_for_alias('missing-alias', 100));

		foreach ($aliases as $alias => $details) {
			$this->assertTrue(\robokassa_is_currency_alias_available(strtolower($alias)));
			$this->assertSame($details, \robokassa_get_currency_alias_details($alias));
		}
	}

	public function testSelectsLiveAndTestPasswords(): void {
		$this->setOptions(array(
			'robokassa_payment_test_onoff' => 'false',
			'robokassa_payment_shoppass1' => 'live-1',
			'robokassa_payment_shoppass2' => 'live-2',
			'robokassa_payment_testshoppass1' => 'test-1',
			'robokassa_payment_testshoppass2' => 'test-2',
		));

		$this->assertSame(array('pass1' => 'live-1', 'pass2' => 'live-2'), \getRobokassaPasses());

		$this->setOptions(array('robokassa_payment_test_onoff' => 'true'));
		$this->assertSame(array('pass1' => 'test-1', 'pass2' => 'test-2'), \getRobokassaPasses());
	}

	public function testRejectsInvalidCallbackSignatureWithoutChangingOrder(): void {
		$this->setOptions(array(
			'robokassa_payment_MerchantLogin' => 'merchant',
			'robokassa_payment_shoppass2' => 'pass2',
		));
		$GLOBALS['robokassa_test_orders'][5000] = array(
			'id' => 5000,
			'status' => 'completed',
			'notes' => array(),
		);
		$request = array(
			'OutSum' => '5990',
			'InvId' => '5000',
			'SignatureValue' => 'x',
		);

		$this->assertFalse(\robokassa_payment_is_valid_callback_signature(
			$request,
			\robokassa_payment_get_callback_password(2)
		));

		$order = \wc_get_order(5000);
		$this->assertSame('completed', $order->get_status());
		$this->assertSame(array(), $order->export_data()['notes']);
	}

	public function testAcceptsValidLiveAndTestCallbackSignatures(): void {
		$this->setOptions(array(
			'robokassa_payment_MerchantLogin' => 'merchant',
			'robokassa_payment_test_onoff' => 'false',
			'robokassa_payment_shoppass2' => 'live-pass2',
			'robokassa_payment_testshoppass2' => 'test-pass2',
		));
		$request = array('OutSum' => '100.00', 'InvId' => '42');
		$request['SignatureValue'] = \robokassa_payment_build_callback_signature($request, 'live-pass2');

		$this->assertTrue(\robokassa_payment_is_valid_callback_signature(
			$request,
			\robokassa_payment_get_callback_password(2)
		));

		$this->setOptions(array('robokassa_payment_test_onoff' => 'true'));
		$request['SignatureValue'] = strtolower(\robokassa_payment_build_callback_signature($request, 'test-pass2'));

		$this->assertTrue(\robokassa_payment_is_valid_callback_signature(
			$request,
			\robokassa_payment_get_callback_password(2)
		));
	}

	/** @dataProvider invalidCallbackProvider */
	public function testRejectsMalformedCallbackData(array $request, string $password): void {
		$this->assertFalse(\robokassa_payment_is_valid_callback_signature($request, $password));
	}

	public function invalidCallbackProvider(): array {
		return array(
			'missing signature' => array(array('OutSum' => '1', 'InvId' => '1'), 'pass'),
			'missing amount' => array(array('InvId' => '1', 'SignatureValue' => str_repeat('a', 32)), 'pass'),
			'array invoice id' => array(array('OutSum' => '1', 'InvId' => array('1'), 'SignatureValue' => str_repeat('a', 32)), 'pass'),
			'empty password' => array(array('OutSum' => '1', 'InvId' => '1', 'SignatureValue' => str_repeat('a', 32)), ''),
		);
	}

	public function testUnsignedCallbackRedirectDoesNotExposeOrderUrl(): void {
		$this->setOptions(array(
			'robokassa_payment_SuccessURL' => 'wc_success',
			'robokassa_payment_shoppass1' => 'pass1',
		));
		$GLOBALS['robokassa_test_orders'][5000] = array(
			'id' => 5000,
			'order_key' => 'wc_order_secret',
		);

		$url = \robokassa_payment_get_callback_redirect_url('success', array(
			'OutSum' => '5990',
			'InvId' => '5000',
			'SignatureValue' => 'x',
		));

		$this->assertSame('https://shop.example.test/checkout/', $url);
		$this->assertStringNotContainsString('wc_order_secret', $url);
	}

	public function testSignedCallbackRedirectPreservesConfiguredOrderDestination(): void {
		$this->setOptions(array(
			'robokassa_payment_MerchantLogin' => 'merchant',
			'robokassa_payment_SuccessURL' => 'wc_success',
			'robokassa_payment_shoppass1' => 'pass1',
		));
		$GLOBALS['robokassa_test_orders'][42] = array(
			'id' => 42,
			'order_key' => 'wc_order_valid',
		);
		$request = array('OutSum' => '100.00', 'InvId' => '42');
		$request['SignatureValue'] = \robokassa_payment_build_callback_signature($request, 'pass1');

		$this->assertSame(
			'https://shop.example.test/checkout/order-received/42/?key=wc_order_valid',
			\robokassa_payment_get_callback_redirect_url('success', $request)
		);
	}

	public function testCustomCallbackPageDoesNotRequireOrderData(): void {
		$this->setOptions(array('robokassa_payment_SuccessURL' => '123'));

		$this->assertSame(
			'https://shop.example.test/?page_id=123',
			\robokassa_payment_get_callback_redirect_url('success', array('InvId' => '5000'))
		);
	}

	public function testCountryAndTaxReceiptRules(): void {
		$this->assertTrue(\robokassa_payment_should_send_sno('RU', 'osn'));
		$this->assertFalse(\robokassa_payment_should_send_sno('KZ', 'osn'));
		$this->assertFalse(\robokassa_payment_should_send_sno('RU', 'fckoff'));

		$this->assertTrue(\robokassa_payment_should_send_tax('RU', 'osn'));
		$this->assertTrue(\robokassa_payment_should_send_tax('KZ', 'osn'));
		$this->assertTrue(\robokassa_payment_should_send_tax('RU', 'fckoff'));
		$this->assertFalse(\robokassa_payment_should_send_tax('BY', 'fckoff'));
	}

	public function testBuildsReceiptForProductsFeesShippingAndAgentData(): void {
		$this->setOptions(array(
			'robokassa_payment_sno' => 'osn',
			'robokassa_payment_tax' => 'vat20',
			'robokassa_payment_tax_source' => 'product',
			'robokassa_payment_paymentObject' => 'commodity',
			'robokassa_payment_payment_object_source' => 'product',
			'robokassa_payment_paymentObject_shipping' => 'service',
			'robokassa_payment_paymentMethod' => 'full_payment',
			'robokassa_payment_agent_fields_enabled' => 'yes',
		));
		$product = new \WC_Product('Agent item', array(
			'_robokassa_tax_rate' => 'vat10',
			'_robokassa_payment_object' => 'service',
			'_robokassa_agent_type' => 'commission_agent',
			'_robokassa_agent_supplier_name' => 'Supplier',
			'_robokassa_agent_supplier_inn' => '7700000000',
			'_robokassa_agent_supplier_phones' => '+7 900',
		));
		$GLOBALS['robokassa_test_orders'][101] = array(
			'items' => array(new \WC_Order_Item_Product($product, 2, 200)),
			'fees' => array(new \Robokassa_Test_Fee_Item('Fee', 1, 10)),
			'shipping' => 20,
			'total' => 230,
		);

		$receipt = \createRobokassaReceipt(101);

		$this->assertSame('osn', $receipt['sno']);
		$this->assertCount(3, $receipt['items']);
		$this->assertSame('Agent item', $receipt['items'][0]['name']);
		$this->assertSame('vat10', $receipt['items'][0]['tax']);
		$this->assertSame('service', $receipt['items'][0]['payment_object']);
		$this->assertSame('commission_agent', $receipt['items'][0]['agent_info']['type']);
		$this->assertArrayNotHasKey('nomenclature_code', $receipt['items'][0]);
		$this->assertSame('Fee', $receipt['items'][1]['name']);
		$this->assertSame('Доставка', $receipt['items'][2]['name']);
		$this->assertSame('service', $receipt['items'][2]['payment_object']);
	}

	public function testKzReceiptOmitsRussianPaymentFieldsAndKeepsTax(): void {
		$this->setOptions(array(
			'robokassa_country_code' => 'KZ',
			'robokassa_payment_sno' => 'osn',
			'robokassa_payment_tax' => 'vat12',
			'robokassa_payment_paymentMethod' => 'full_payment',
		));
		$product = new \WC_Product('Item');
		$GLOBALS['robokassa_test_orders'][102] = array(
			'items' => array(new \WC_Order_Item_Product($product, 1, 100)),
			'total' => 100,
		);

		$receipt = \createRobokassaReceipt(102);

		$this->assertArrayNotHasKey('sno', $receipt);
		$this->assertArrayNotHasKey('payment_object', $receipt['items'][0]);
		$this->assertArrayNotHasKey('payment_method', $receipt['items'][0]);
		$this->assertSame('vat12', $receipt['items'][0]['tax']);
	}

	public function testCompletesPaymentAndAppliesCustomStatus(): void {
		$order = new \WC_Order(array('status' => 'pending'));

		\robokassa_mark_order_payment_complete($order, 'wc-completed');

		$this->assertSame('completed', $order->get_status());
		$this->assertSame(1, $order->export_data()['payment_complete_calls']);
	}

	public function testTracksIframeAndSbpRedirects(): void {
		$order = new \WC_Order(array('payment_method' => 'robokassa'));
		$this->assertFalse(\robokassa_should_track_payment_redirect($order));

		$this->setOptions(array('robokassa_iframe' => 1));
		$this->assertTrue(\robokassa_should_track_payment_redirect($order));

		$this->setOptions(array('robokassa_iframe' => 0));
		$sbpOrder = new \WC_Order(array('payment_method' => 'robokassa_sbp'));
		$this->assertTrue(\robokassa_should_track_payment_redirect($sbpOrder));

		$GLOBALS['robokassa_test_wc']->session->set('chosen_payment_method', 'robokassa_sbp');
		$this->assertTrue(\robokassa_should_track_payment_redirect($order));
	}
}
