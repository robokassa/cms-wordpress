<?php

namespace Robokassa\Tests\Unit;

use Robokassa\Payment\MarkingManager;
use Robokassa\Tests\Support\TestCase;

class MarkingManagerTest extends TestCase {
	private function enableMarking(): void {
		$this->setOptions(array(
			MarkingManager::OPTION_ENABLED => 'yes',
			'robokassa_country_code' => 'RU',
		));
	}

	public function testDecodesRawCodeAndPreservesGroupSeparator(): void {
		$this->enableMarking();
		$manager = new MarkingManager();
		$code = "010460043993125621ABC\x1D910001";

		$this->assertSame(array($code), $manager->decodeCodes(array(base64_encode($code))));
		$this->assertStringContainsString('\\u001d', wp_json_encode(array('nomenclature_code' => $code)));
	}

	public function testRejectsNonAsciiMarkingCode(): void {
		$this->enableMarking();
		$manager = new MarkingManager();

		$this->expectException(\InvalidArgumentException::class);
		$manager->decodeCodes(array(base64_encode('код')));
	}

	public function testSplitsMarkedItemAndKeepsExactTotal(): void {
		$this->enableMarking();
		$item = new \WC_Order_Item_Product(
			new \WC_Product('Marked product'),
			3,
			100,
			array(
				MarkingManager::ORDER_ITEM_REQUIRED_META_KEY => 'yes',
				MarkingManager::ORDER_ITEM_CODES_META_KEY => array('code-1', 'code-2', 'code-3'),
			),
			7
		);
		$order = new \WC_Order(array('id' => 15, 'items' => array($item)));
		$manager = new MarkingManager();

		$result = $manager->buildSecondReceiptItems($item, array(
			'name' => 'Marked product',
			'quantity' => 3,
			'sum' => '100.00',
			'tax' => 'vat20',
		));

		$this->assertCount(3, $result);
		$this->assertSame(array('33.34', '33.33', '33.33'), array_column($result, 'sum'));
		$this->assertSame(array('code-1', 'code-2', 'code-3'), array_column($result, 'nomenclature_code'));
		$this->assertSame(3, array_sum(array_column($result, 'quantity')));
	}

	public function testValidationBlocksIncompleteAndDuplicateCodes(): void {
		$this->enableMarking();
		$first = new \WC_Order_Item_Product(
			new \WC_Product('First'),
			2,
			20,
			array(
				MarkingManager::ORDER_ITEM_REQUIRED_META_KEY => 'yes',
				MarkingManager::ORDER_ITEM_CODES_META_KEY => array('same-code', ''),
			),
			1
		);
		$second = new \WC_Order_Item_Product(
			new \WC_Product('Second'),
			1,
			10,
			array(
				MarkingManager::ORDER_ITEM_REQUIRED_META_KEY => 'yes',
				MarkingManager::ORDER_ITEM_CODES_META_KEY => array('same-code'),
			),
			2
		);
		$order = new \WC_Order(array('id' => 16, 'items' => array($first, $second)));
		$errors = (new MarkingManager())->validateOrder($order);

		$this->assertCount(2, $errors);
		$this->assertStringContainsString('1 из 2', $errors[0]);
		$this->assertStringContainsString('повторяющийся', $errors[1]);
	}
}
