<?php

namespace Robokassa\Tests\Unit;

use Robokassa\Payment\PaymentObjectManager;
use Robokassa\Tests\Support\TestCase;

class PaymentObjectManagerTest extends TestCase {
	public function testNormalizesGlobalPaymentObject(): void {
		$manager = new PaymentObjectManager();

		$this->setOptions(array('robokassa_payment_paymentObject' => 'service'));
		$this->assertSame('service', $manager->getDefaultPaymentObject());

		$this->setOptions(array('robokassa_payment_paymentObject' => 'unsupported'));
		$this->assertSame('commodity', $manager->getDefaultPaymentObject());
	}

	public function testResolvesProductOverrideAndFallback(): void {
		$this->setOptions(array(
			'robokassa_payment_paymentObject' => 'service',
			'robokassa_payment_payment_object_source' => 'product',
		));
		$manager = new PaymentObjectManager();

		$product = new \WC_Product('Item', array('_robokassa_payment_object' => 'excise'));
		$this->assertSame(
			'excise',
			$manager->getItemPaymentObject(new \WC_Order_Item_Product($product))
		);

		$productWithoutOverride = new \WC_Product();
		$this->assertSame(
			'service',
			$manager->getItemPaymentObject(new \WC_Order_Item_Product($productWithoutOverride))
		);

		$productWithInvalidOverride = new \WC_Product('Item', array('_robokassa_payment_object' => 'bad'));
		$this->assertSame(
			'commodity',
			$manager->getItemPaymentObject(new \WC_Order_Item_Product($productWithInvalidOverride))
		);
	}

	public function testGlobalModeIgnoresProductOverride(): void {
		$this->setOptions(array(
			'robokassa_payment_paymentObject' => 'service',
			'robokassa_payment_payment_object_source' => 'global',
		));
		$product = new \WC_Product('Item', array('_robokassa_payment_object' => 'excise'));

		$this->assertSame(
			'service',
			(new PaymentObjectManager())->getItemPaymentObject(new \WC_Order_Item_Product($product))
		);
	}

	public function testSavesOnlyKnownPaymentObjects(): void {
		$product = new \WC_Product();
		$manager = new PaymentObjectManager();

		$_POST['_robokassa_payment_object'] = 'service';
		$manager->saveProductPaymentObjectField($product);
		$this->assertSame('service', $product->get_meta('_robokassa_payment_object'));

		$_POST['_robokassa_payment_object'] = 'not-supported';
		$manager->saveProductPaymentObjectField($product);
		$this->assertSame('', $product->get_meta('_robokassa_payment_object'));
	}
}
