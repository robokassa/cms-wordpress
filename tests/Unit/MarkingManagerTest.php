<?php

namespace Robokassa\Tests\Unit;

use Robokassa\Payment\MarkingManager;
use Robokassa\Tests\Support\TestCase;

class MarkingManagerTest extends TestCase {
	public function testProductFieldHasOwnVisibleOptionsGroup(): void {
		$this->enableMarking();
		ob_start();
		(new MarkingManager())->renderProductField();
		$html = ob_get_clean();
		$document = new \DOMDocument();
		$document->loadHTML('<div id="general_product_data">' . $html . '</div>');
		$xpath = new \DOMXPath($document);
		$groups = $xpath->query('//*[@id="general_product_data"]/div[contains(concat(" ", normalize-space(@class), " "), " options_group ")]');

		$this->assertSame(1, $groups->length);
		$this->assertSame(1, $xpath->query('.//input[@id="_robokassa_marking_required"]', $groups->item(0))->length);
		$classes = explode(' ', $groups->item(0)->getAttribute('class'));
		foreach (array('simple', 'external', 'variable') as $type) {
			$this->assertContains('show_if_' . $type, $classes);
			$this->assertNotContains('hide_if_' . $type, $classes);
		}
	}

	/** @dataProvider disabledMarkingOptions */
	public function testProductFieldIsHiddenWhenMarkingIsUnavailable(array $options): void {
		$this->setOptions($options);
		ob_start();
		(new MarkingManager())->renderProductField();
		$html = ob_get_clean();

		$this->assertSame('', $html);
		$this->assertSame(array(), $GLOBALS['robokassa_test_checkbox_fields']);
	}

	public static function disabledMarkingOptions(): array {
		return array(
			'disabled' => array(array(MarkingManager::OPTION_ENABLED => 'no')),
			'other country' => array(array(MarkingManager::OPTION_ENABLED => 'yes', 'robokassa_country_code' => 'KZ')),
		);
	}

	/** @dataProvider productTypes */
	public function testProductMarkingCanBeSavedAndRemoved(string $type): void {
		$this->enableMarking();
		$manager = new MarkingManager();
		$product = new \WC_Product('Product', array(), $type);
		$_POST[MarkingManager::PRODUCT_META_KEY] = 'yes';

		$manager->saveProductField($product);
		$this->assertSame('yes', $product->get_meta(MarkingManager::PRODUCT_META_KEY));
		$this->assertTrue($manager->isProductMarked($product));

		$_POST = array();
		$manager->saveProductField($product);
		$this->assertSame('', $product->get_meta(MarkingManager::PRODUCT_META_KEY));
		$this->assertFalse($manager->isProductMarked($product));
	}

	public static function productTypes(): array {
		return array('simple' => array('simple'), 'external' => array('external'), 'variable' => array('variable'));
	}

	public function testVariationInheritsMarkingAndSnapshotsItInOrder(): void {
		$this->enableMarking();
		$manager = new MarkingManager();
		$parent = new \WC_Product('Parent', array(), 'variable');
		$GLOBALS['robokassa_test_products'][100] = $parent;
		$_POST[MarkingManager::PRODUCT_META_KEY] = 'yes';
		$manager->saveProductField($parent);
		$variation = new \WC_Product('Variation', array(), 'variation', 100);
		$item = new \WC_Order_Item_Product($variation);

		$this->assertTrue($manager->isProductMarked($variation));
		$manager->snapshotOrderItem($item, 'cart-key', array('data' => $variation));
		$this->assertSame('yes', $item->get_meta(MarkingManager::ORDER_ITEM_REQUIRED_META_KEY));

		$parent->delete_meta_data(MarkingManager::PRODUCT_META_KEY);
		$this->assertFalse($manager->isProductMarked($variation));
		$this->assertTrue($manager->isOrderItemMarked($item));
	}

	public function testVariationWithoutMarkedParentDoesNotRequireCodes(): void {
		$this->enableMarking();
		$manager = new MarkingManager();
		$GLOBALS['robokassa_test_products'][100] = new \WC_Product('Parent', array(), 'variable');
		$variation = new \WC_Product('Variation', array(), 'variation', 100);
		$item = new \WC_Order_Item_Product($variation);

		$manager->snapshotOrderItem($item, 'cart-key', array('data' => $variation));
		$this->assertFalse($manager->isProductMarked($variation));
		$this->assertSame('', $item->get_meta(MarkingManager::ORDER_ITEM_REQUIRED_META_KEY));
		unset($GLOBALS['robokassa_test_products'][100]);
		$this->assertFalse($manager->isProductMarked($variation));
	}

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
