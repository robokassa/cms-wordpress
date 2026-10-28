<?php

namespace Robokassa\Tests\Unit;

use Robokassa\Payment\MarkingManager;
use Robokassa\Tests\Support\TestCase;

class SecondReceiptTest extends TestCase
{
	protected function setUp(): void
	{
		parent::setUp();
		$this->setOptions(array(
			'robokassa_payment_paymentMethod' => 'prepayment',
			'robokassa_payment_sno' => 'osn',
			'robokassa_payment_order_status_for_second_check' => 'wc-completed',
			MarkingManager::OPTION_ENABLED => 'yes',
		));
	}

	private function createOrder(string $gateway): void
	{
		$GLOBALS['robokassa_test_orders'][42] = array(
			'id' => 42,
			'payment_method' => $gateway,
			'items' => array(new \WC_Order_Item_Product(
				new \WC_Product('Marked item'),
				1,
				100,
				array(MarkingManager::ORDER_ITEM_REQUIRED_META_KEY => 'yes')
			)),
		);
	}

	/** @dataProvider unrelatedGateways */
	public function testOtherPaymentMethodsAreIgnoredBeforeReceiptPreparation(string $gateway): void
	{
		$this->createOrder($gateway);
		$before = $GLOBALS['robokassa_test_orders'][42];
		$manager = new SecondReceiptMarkingSpy();
		$GLOBALS['robokassa_payment_marking_manager'] = $manager;
		try {
			\robokassa_2check_send(42, 'processing', 'completed');

			$this->assertSame($before, $GLOBALS['robokassa_test_orders'][42]);
			$this->assertSame(0, $manager->validations);
			$this->assertSame(array(), $GLOBALS['robokassa_test_log_entries']);
		} finally {
			unset($GLOBALS['robokassa_payment_marking_manager']);
		}
	}

	public static function unrelatedGateways(): array
	{
		return array_map(function ($gateway) { return array($gateway); }, array('cod', 'bacs', 'cheque', 'stripe', 'paypal', '', 'robokassa_fake', 'Robokassa'));
	}

	/** @dataProvider robokassaGateways */
	public function testAllRobokassaGatewaysRetainReceiptPreparationWhenDisabledForCheckout(string $gateway): void
	{
		$this->createOrder($gateway);
		$manager = new SecondReceiptMarkingSpy();
		$GLOBALS['robokassa_payment_marking_manager'] = $manager;
		try {
			\robokassa_2check_send(42, 'processing', 'completed');

			$this->assertSame(1, $manager->validations);
			$order = \wc_get_order(42);
			$this->assertGreaterThan(0, $order->get_meta('_robokassa_second_receipt_operation_id'));
			$this->assertCount(1, $order->export_data()['notes']);
			$this->assertStringContainsString('Заполните маркировку', $order->export_data()['notes'][0]);
		} finally {
			unset($GLOBALS['robokassa_payment_marking_manager']);
		}
	}

	public static function robokassaGateways(): array
	{
		return array_map(function ($gateway) { return array($gateway); }, array('robokassa', 'robokassa_sbp', 'robokassa_credit', 'robokassa_podeli', 'robokassa_mokka', 'robokassa_split'));
	}

	/** @dataProvider earlyExitOptions */
	public function testUnrelatedOrderIsNotLoggedEvenWithDebugEnabled(array $options): void
	{
		$script = 'define("ROBOKASSA_PAYMENT_DEBUG_STATUS", true);'
			. 'require ' . var_export(dirname(__DIR__, 2) . '/vendor/autoload.php', true) . ';'
			. 'require ' . var_export(dirname(__DIR__) . '/bootstrap.php', true) . ';'
			. '$GLOBALS["robokassa_test_options"] = ' . var_export($options, true) . ';'
			. '$GLOBALS["robokassa_test_orders"][42] = array("id" => 42, "payment_method" => "cod");'
			. 'robokassa_2check_send(42, "pending", "processing");'
			. 'echo json_encode($GLOBALS["robokassa_test_log_entries"] ?? array());';
		$output = array();
		exec(escapeshellarg(PHP_BINARY) . ' -r ' . escapeshellarg($script) . ' 2>&1', $output, $status);

		$this->assertSame(0, $status, implode("\n", $output));
		$this->assertSame('[]', implode("\n", $output));
	}

	public static function earlyExitOptions(): array
	{
		return array(
			'sno disabled' => array(array('robokassa_payment_paymentMethod' => 'prepayment', 'robokassa_payment_sno' => 'fckoff')),
			'wrong trigger status' => array(array('robokassa_payment_paymentMethod' => 'prepayment', 'robokassa_payment_sno' => 'osn')),
			'full payment' => array(array('robokassa_payment_paymentMethod' => 'full_payment')),
		);
	}

	public function testMissingOrderIsIgnored(): void
	{
		\robokassa_2check_send(999, 'processing', 'completed');
		$this->assertSame(array(), $GLOBALS['robokassa_test_orders']);
		$this->assertSame(array(), $GLOBALS['robokassa_test_log_entries']);
	}

	public function testPreviouslySentRobokassaReceiptIsStillSkipped(): void
	{
		$this->createOrder('robokassa');
		$GLOBALS['robokassa_test_orders'][42]['meta']['_robokassa_second_receipt_sent'] = 'yes';
		$before = $GLOBALS['robokassa_test_orders'][42];
		\robokassa_2check_send(42, 'processing', 'completed');
		$this->assertSame($before, $GLOBALS['robokassa_test_orders'][42]);
	}
}

class SecondReceiptMarkingSpy extends MarkingManager
{
	public $validations = 0;

	public function validateOrder($order)
	{
		$this->validations++;
		return parent::validateOrder($order);
	}
}
