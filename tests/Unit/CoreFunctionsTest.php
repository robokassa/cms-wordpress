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
