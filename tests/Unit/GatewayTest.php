<?php

namespace Robokassa\Tests\Unit;

use Robokassa\Tests\Support\TestCase;

class GatewayTest extends TestCase {
	public function testMainGatewaySupportsProductsAndSubscriptions(): void {
		$gateway = new \payment_robokassa_pay_method_request_main();

		$this->assertSame('robokassa', $gateway->id);
		$this->assertContains('products', $gateway->supports);
		$this->assertContains('subscriptions', $gateway->supports);
		$this->assertContains('subscription_reactivation', $gateway->supports);
	}

	public function testOptionalGatewaySupportsProductsOnly(): void {
		$gateway = new \payment_robokassa_pay_method_request_sbp();

		$this->assertSame('robokassa_sbp', $gateway->id);
		$this->assertSame(array('products'), $gateway->supports);
	}

	public function testProcessPaymentReturnsWooCommerceRedirect(): void {
		$GLOBALS['robokassa_test_orders'][55] = array(
			'checkout_payment_url' => 'https://shop.example.test/pay/55',
		);
		$gateway = new \payment_robokassa_pay_method_request_main();

		$this->assertSame(
			array('result' => 'success', 'redirect' => 'https://shop.example.test/pay/55'),
			$gateway->process_payment(55)
		);
	}

	public function testDisabledGatewayIsUnavailable(): void {
		$gateway = new \payment_robokassa_pay_method_request_main();
		$gateway->enabled = 'no';

		$this->assertFalse($gateway->is_available());
	}

	public function testUnavailableAliasDisablesOptionalGateway(): void {
		$this->setOptions(array('robokassa_payment_method_sbp_enabled' => 'yes'));
		$gateway = new \payment_robokassa_pay_method_request_sbp();

		$this->assertFalse($gateway->is_available());
	}
}
