<?php

namespace Robokassa\Tests\Unit;

use Robokassa\Payment\AgentManager;
use Robokassa\Tests\Support\TestCase;

class AgentManagerTest extends TestCase {
	public function testReturnsNoPayloadWhenFeatureIsDisabled(): void {
		$product = $this->completeAgentProduct();

		$this->assertSame(array(), (new AgentManager())->buildAgentPayload($product));
	}

	public function testBuildsCompletePayloadAndNormalizesPhones(): void {
		$this->setOptions(array('robokassa_payment_agent_fields_enabled' => 'yes'));
		$product = $this->completeAgentProduct(array(
			'_robokassa_agent_supplier_phones' => '+7 900 1, +7 900 2; +7 900 1' . "\n" . '+7 900 3',
		));

		$this->assertSame(
			array(
				'agent_info' => array('type' => 'commission_agent'),
				'supplier_info' => array(
					'name' => 'Supplier',
					'inn' => '7700000000',
					'phones' => array('+7 900 1', '+7 900 2', '+7 900 3'),
				),
			),
			(new AgentManager())->buildAgentPayload($product)
		);
	}

	/** @dataProvider incompleteAgentProvider */
	public function testRejectsIncompleteOrInvalidPayload($field, $value): void {
		$this->setOptions(array('robokassa_payment_agent_fields_enabled' => 'yes'));
		$product = $this->completeAgentProduct(array($field => $value));

		$this->assertSame(array(), (new AgentManager())->buildAgentPayload($product));
	}

	public function incompleteAgentProvider(): array {
		return array(
			'invalid type' => array('_robokassa_agent_type', 'invalid'),
			'missing name' => array('_robokassa_agent_supplier_name', ''),
			'missing inn' => array('_robokassa_agent_supplier_inn', ''),
			'missing phone' => array('_robokassa_agent_supplier_phones', ''),
		);
	}

	public function testGetsPayloadOnlyFromProductOrderItem(): void {
		$this->setOptions(array('robokassa_payment_agent_fields_enabled' => 'yes'));
		$manager = new AgentManager();

		$this->assertSame(array(), $manager->getItemAgentData(new \WC_Order_Item()));
		$this->assertSame(array(), $manager->getItemAgentData(new \WC_Order_Item_Product(null)));
		$this->assertNotEmpty(
			$manager->getItemAgentData(new \WC_Order_Item_Product($this->completeAgentProduct()))
		);
	}

	public function testSavesValidFieldsAndDeletesEmptyOnes(): void {
		$this->setOptions(array('robokassa_payment_agent_fields_enabled' => 'yes'));
		$product = new \WC_Product();
		$manager = new AgentManager();

		$_POST = array(
			'_robokassa_agent_type' => 'attorney',
			'_robokassa_agent_supplier_name' => ' Supplier ',
			'_robokassa_agent_supplier_inn' => ' 123 ',
			'_robokassa_agent_supplier_phones' => '+7 900',
		);
		$manager->saveProductAgentFields($product);

		$this->assertSame('attorney', $product->get_meta('_robokassa_agent_type'));
		$this->assertSame('Supplier', $product->get_meta('_robokassa_agent_supplier_name'));

		$_POST['_robokassa_agent_type'] = 'bad';
		$_POST['_robokassa_agent_supplier_name'] = '';
		$manager->saveProductAgentFields($product);

		$this->assertSame('', $product->get_meta('_robokassa_agent_type'));
		$this->assertSame('', $product->get_meta('_robokassa_agent_supplier_name'));
	}

	private function completeAgentProduct(array $overrides = array()): \WC_Product {
		return new \WC_Product('Agent product', array_merge(
			array(
				'_robokassa_agent_type' => 'commission_agent',
				'_robokassa_agent_supplier_name' => 'Supplier',
				'_robokassa_agent_supplier_inn' => '7700000000',
				'_robokassa_agent_supplier_phones' => '+7 900 1',
			),
			$overrides
		));
	}
}
