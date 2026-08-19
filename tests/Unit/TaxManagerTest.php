<?php

namespace Robokassa\Tests\Unit;

use Robokassa\Payment\TaxManager;
use Robokassa\Tests\Support\TestCase;

class TaxManagerTest extends TestCase {
	/** @dataProvider taxNormalizationProvider */
	public function testNormalizesKnownLegacyAndUnknownTaxCodes($input, $expected): void {
		$manager = new TaxManager();

		$this->assertSame($expected, $manager->normalizeTaxCode($input));
	}

	public function taxNormalizationProvider(): array {
		return array(
			'known' => array('vat10', 'vat10'),
			'legacy 18 percent' => array('vat118', 'vat120'),
			'unknown' => array('vat999', 'none'),
		);
	}

	public function testRestrictsTaxesByStoreCountry(): void {
		$manager = new TaxManager();

		$this->assertSame('vat12', $manager->normalizeTaxCodeForCountry('vat12', 'KZ'));
		$this->assertSame('none', $manager->normalizeTaxCodeForCountry('vat20', 'KZ'));
		$this->assertSame('vat20', $manager->normalizeTaxCodeForCountry('vat20', 'RU'));
		$this->assertSame('none', $manager->normalizeTaxCodeForCountry('vat12', 'RU'));
	}

	/** @dataProvider taxCalculationProvider */
	public function testCalculatesTaxAmount($tax, $amount, $expected): void {
		$manager = new TaxManager();

		$this->assertEqualsWithDelta($expected, $manager->calculateTaxSum($tax, $amount), 0.00001);
	}

	public function taxCalculationProvider(): array {
		return array(
			'no vat' => array('none', 120, 0.0),
			'20 percent' => array('vat20', 120, 24.0),
			'20/120 calculated rate' => array('vat120', 120, 0.2),
			'unknown is zero' => array('bad', 100, 0.0),
		);
	}

	public function testUsesGlobalTaxUnlessProductModeIsEnabled(): void {
		$this->setOptions(array(
			'robokassa_payment_tax' => 'vat20',
			'robokassa_payment_tax_source' => 'global',
		));
		$product = new \WC_Product('Item', array('_robokassa_tax_rate' => 'vat10'));
		$item = new \WC_Order_Item_Product($product, 1, 100);
		$manager = new TaxManager();

		$this->assertSame('vat20', $manager->getItemTax($item));

		$this->setOptions(array('robokassa_payment_tax_source' => 'product'));
		$this->assertSame('vat10', $manager->getItemTax($item));
	}

	public function testFallsBackForMissingProductAndInvalidCountryTax(): void {
		$this->setOptions(array(
			'robokassa_payment_tax' => 'vat12',
			'robokassa_country_code' => 'KZ',
			'robokassa_payment_tax_source' => 'product',
		));
		$manager = new TaxManager();

		$this->assertSame('vat12', $manager->getItemTax(new \WC_Order_Item_Product(null)));

		$product = new \WC_Product('Item', array('_robokassa_tax_rate' => 'vat20'));
		$this->assertSame('none', $manager->getItemTax(new \WC_Order_Item_Product($product)));
	}

	public function testSavesAndDeletesProductTaxMeta(): void {
		$this->setOptions(array('robokassa_country_code' => 'RU'));
		$product = new \WC_Product();
		$manager = new TaxManager();

		$_POST['_robokassa_tax_rate'] = 'vat10';
		$manager->saveProductTaxField($product);
		$this->assertSame('vat10', $product->get_meta('_robokassa_tax_rate'));

		$_POST['_robokassa_tax_rate'] = 'invalid';
		$manager->saveProductTaxField($product);
		$this->assertSame('', $product->get_meta('_robokassa_tax_rate'));

		$_POST['_robokassa_tax_rate'] = 'none';
		$manager->saveProductTaxField($product);
		$this->assertSame('none', $product->get_meta('_robokassa_tax_rate'));
	}
}
