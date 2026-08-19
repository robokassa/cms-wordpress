<?php

namespace Robokassa\Tests\Unit;

use Robokassa\Tests\Support\TestCase;

class PaymentMethodsTest extends TestCase {
	public function testMainGatewayIsAlwaysRegistered(): void {
		$this->setOptions($this->allOptionalMethods('no'));

		$this->assertSame(array('robokassa'), \robokassa_get_gateway_ids());
	}

	public function testDoesNotRegisterUnavailableOptionalMethods(): void {
		$this->setOptions($this->allOptionalMethods('yes'));

		$ids = \robokassa_get_gateway_ids();

		$this->assertSame(array('robokassa'), $ids);
		$this->assertFalse(\robokassa_is_optional_method_available(array('alias' => 'missing-alias')));
	}

	public function testKzDisablesAllOptionalMethods(): void {
		$this->setOptions(array_merge(
			$this->allOptionalMethods('yes'),
			array('robokassa_country_code' => 'KZ')
		));

		$this->assertSame(array('robokassa'), \robokassa_get_gateway_ids());
	}

	public function testAddsActiveGatewayClassesToExistingList(): void {
		$this->setOptions($this->allOptionalMethods('no'));

		$this->assertSame(
			array('existing_gateway', 'payment_robokassa_pay_method_request_main'),
			\robokassa_payment_add_WC_WP_robokassa_class(array('existing_gateway'))
		);
	}

	public function testFindsOptionalConfigurationByGateway(): void {
		$config = \robokassa_get_optional_method_config_by_gateway('robokassa_sbp');

		$this->assertSame('SBP', $config['alias']);
		$this->assertSame(array(), \robokassa_get_optional_method_config_by_gateway('unknown'));
	}

	private function allOptionalMethods($value): array {
		return array(
			'robokassa_payment_method_credit_enabled' => $value,
			'robokassa_payment_method_podeli_enabled' => $value,
			'robokassa_payment_method_mokka_enabled' => $value,
			'robokassa_payment_method_split_enabled' => $value,
			'robokassa_payment_method_sbp_enabled' => $value,
		);
	}
}
