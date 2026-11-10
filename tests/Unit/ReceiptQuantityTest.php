<?php

namespace Robokassa\Tests\Unit;

use Robokassa\Tests\Support\TestCase;

class ReceiptQuantityTest extends TestCase
{
	private function createOrder($quantity, $total = 100): \WC_Order
	{
		$GLOBALS['robokassa_test_orders'][42] = array(
			'id' => 42,
			'status' => 'pending',
			'payment_method' => 'robokassa',
			'items' => array(123 => new \WC_Order_Item_Product(new \WC_Product('товар'), $quantity, $total)),
			'total' => $total,
		);
		return new \WC_Order(42);
	}

	/** @dataProvider invalidQuantities */
	public function testInvalidQuantityDoesNotProduceReceipt($quantity, $total): void
	{
		$this->createOrder($quantity, $total);
		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('количество у позиции заказа №123 должно быть больше нуля');
		\createRobokassaReceipt(42);
	}

	public static function invalidQuantities(): array
	{
		return array(
			'zero quantity and zero sum' => array(0, 0),
			'zero quantity and positive sum' => array(0, 100),
			'negative quantity' => array(-1, 100),
			'string zero' => array('0.00', 100),
			'invalid quantity' => array('invalid', 100),
			'infinite quantity' => array(INF, 100),
			'nan quantity' => array(NAN, 100),
		);
	}

	/** @dataProvider validQuantities */
	public function testValidQuantityPreservesReceiptAmounts($quantity, $total, $cost): void
	{
		$this->createOrder($quantity, $total);
		$receipt = \createRobokassaReceipt(42);
		$this->assertCount(1, $receipt['items']);
		$this->assertSame($quantity, $receipt['items'][0]['quantity']);
		$this->assertEquals($total, $receipt['items'][0]['sum']);
		$this->assertEquals($cost, $receipt['items'][0]['cost']);
	}

	public static function validQuantities(): array
	{
		return array(
			'normal quantity' => array(2, 100, 50),
			'free item' => array(1, 0, 0),
			'fractional quantity' => array(0.5, 100, 200),
		);
	}

	public function testInvalidFeeQuantityDoesNotProduceReceipt(): void
	{
		$this->createOrder(1);
		$GLOBALS['robokassa_test_orders'][42]['fees'] = array(456 => new \Robokassa_Test_Fee_Item('сбор', 0, 10));
		$this->expectException(\InvalidArgumentException::class);
		$this->expectExceptionMessage('позиции заказа №456');
		\createRobokassaReceipt(42);
	}

	public function testCheckoutRejectsInvalidQuantityWithoutChangingOrder(): void
	{
		$this->createOrder(0);
		$before = $GLOBALS['robokassa_test_orders'][42];
		$result = (new \payment_robokassa_pay_method_request_main())->process_payment(42);
		$this->assertSame(array('result' => 'failure'), $result);
		$this->assertSame($before, $GLOBALS['robokassa_test_orders'][42]);
		$this->assertSame('error', $GLOBALS['robokassa_test_notices'][0]['type']);
		$this->assertSame(array(), $GLOBALS['robokassa_test_http_requests']);
	}

	public function testPaymentPageShowsErrorInsteadOfPaymentForm(): void
	{
		$this->createOrder(0);
		ob_start();
		try {
			\processRobokassaPayment(42, 'robokassa');
			$output = ob_get_contents();
		} finally {
			ob_end_clean();
		}
		$this->assertStringContainsString('woocommerce-error', $output);
		$this->assertStringContainsString('количество у позиции заказа №123', $output);
		$this->assertStringNotContainsString('<form', $output);
		$this->assertStringNotContainsString('<script', $output);
		$this->assertSame(array(), $GLOBALS['robokassa_test_http_requests']);
	}

	public function testHoldConfirmationIsNotSentForInvalidQuantity(): void
	{
		$order = $this->createOrder(0);
		$this->setOptions(array('robokassa_payment_hold_onoff' => 1));
		\robokassa_hold_confirm(42, 'on-hold', 'processing', $order);
		$this->assertBlockedWithNote('Подтверждение холда не отправлено');
	}

	public function testSubscriptionPaymentIsNotSentForInvalidQuantity(): void
	{
		$order = $this->createOrder(0);
		(new \payment_robokassa_pay_method_request_main())->process_subscription_payment(100, $order);
		$this->assertBlockedWithNote('Платёж по подписке не отправлен');
	}

	public function testValidHoldStillSendsReceiptWithOriginalQuantityAndAmount(): void
	{
		$order = $this->createOrder(2);
		$this->setOptions(array('robokassa_payment_hold_onoff' => 1));
		\robokassa_hold_confirm(42, 'on-hold', 'processing', $order);
		$this->assertCount(1, $GLOBALS['robokassa_test_http_requests']);
		$request = $GLOBALS['robokassa_test_http_requests'][0];
		$this->assertSame('https://auth.robokassa.ru/Merchant/Payment/Confirm', $request['url']);
		$receipt = json_decode($request['args']['body']['Receipt'], true);
		$this->assertCount(1, $receipt['items']);
		$this->assertSame(2, $receipt['items'][0]['quantity']);
		$this->assertEquals(100, $receipt['items'][0]['sum']);
		$this->assertEquals(50, $receipt['items'][0]['cost']);
	}

	public function testSecondReceiptIsNotPreparedForInvalidQuantity(): void
	{
		$this->createOrder(0);
		$this->setOptions(array(
			'robokassa_payment_paymentMethod' => 'prepayment',
			'robokassa_payment_sno' => 'osn',
			'robokassa_payment_order_status_for_second_check' => 'wc-completed',
		));
		\robokassa_2check_send(42, 'processing', 'completed');
		$this->assertBlockedWithNote('Второй чек не отправлен');
		$this->assertSame('', (new \WC_Order(42))->get_meta('_robokassa_second_receipt_operation_id'));
	}

	private function assertBlockedWithNote(string $message): void
	{
		$this->assertSame(array(), $GLOBALS['robokassa_test_http_requests']);
		$this->assertSame('pending', $GLOBALS['robokassa_test_orders'][42]['status']);
		$this->assertCount(1, $GLOBALS['robokassa_test_orders'][42]['notes']);
		$this->assertStringContainsString($message, $GLOBALS['robokassa_test_orders'][42]['notes'][0]);
	}
}
