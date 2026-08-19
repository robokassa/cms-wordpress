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
		$this->assertContains('refunds', $gateway->supports);
	}

	public function testOptionalGatewaySupportsProductsOnly(): void {
		$gateway = new \payment_robokassa_pay_method_request_sbp();

		$this->assertSame('robokassa_sbp', $gateway->id);
		$this->assertSame(array('products', 'refunds'), $gateway->supports);
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

	public function testProcessesRefundAndStoresAsynchronousRequest(): void {
		$this->setOptions(array(
			'robokassa_payment_MerchantLogin' => 'merchant',
			'robokassa_payment_shoppass2' => 'password2',
			'robokassa_payment_shoppass3' => 'password3',
			'robokassa_payment_test_onoff' => 'false',
		));
		$GLOBALS['robokassa_test_orders'][77] = array(
			'id' => 77,
			'total' => 150,
			'meta' => array('_robokassa_operation_key' => 'operation-key'),
		);
		$GLOBALS['robokassa_test_http_callback'] = function ($method, $url, $args) {
			$this->assertSame('POST', $method);
			$this->assertSame(\Robokassa\Payment\RefundApi::CREATE_URL, $url);
			return array(
				'response' => array('code' => 200),
				'body' => json_encode(array(
					'success' => true,
					'message' => null,
					'requestId' => '68cd7fa6-1338-4745-ba5c-28d16cbcdb3d',
				)),
			);
		};

		$gateway = new \payment_robokassa_pay_method_request_main();
		$this->assertTrue($gateway->process_refund(77, 50, 'Возврат покупателю'));

		$order = new \WC_Order(77);
		$requests = $order->get_meta('_robokassa_refund_requests', true);
		$this->assertSame(50.0, $requests['68cd7fa6-1338-4745-ba5c-28d16cbcdb3d']['amount']);
		$this->assertSame('processing', $requests['68cd7fa6-1338-4745-ba5c-28d16cbcdb3d']['status']);
		$this->assertSame('robokassa_refund_check_event', $GLOBALS['robokassa_test_scheduled_events'][0]['hook']);
		$this->assertFalse($GLOBALS['robokassa_test_scheduled_events'][0]['unique']);
	}

	public function testRejectsRefundsForTestPayments(): void {
		$this->setOptions(array('robokassa_payment_test_onoff' => 'true'));
		$GLOBALS['robokassa_test_orders'][78] = array('id' => 78, 'total' => 100);

		$result = (new \payment_robokassa_pay_method_request_main())->process_refund(78, 25);

		$this->assertInstanceOf(\WP_Error::class, $result);
		$this->assertSame('robokassa_refund_test_unsupported', $result->get_error_code());
	}

	public function testAutomaticRefundButtonRequiresPassword3(): void {
		$GLOBALS['robokassa_test_orders'][83] = array('id' => 83, 'total' => 100);
		$order = new \WC_Order(83);
		$gateway = new \payment_robokassa_pay_method_request_main();

		$this->assertFalse($gateway->can_refund_order($order));
		$this->setOptions(array('robokassa_payment_shoppass3' => 'password3'));
		$this->assertTrue($gateway->can_refund_order($order));
	}

	public function testOmitsRefundSumForAFullRefund(): void {
		$this->setOptions(array(
			'robokassa_payment_MerchantLogin' => 'merchant',
			'robokassa_payment_shoppass2' => 'password2',
			'robokassa_payment_shoppass3' => 'password3',
			'robokassa_payment_test_onoff' => 'false',
		));
		$GLOBALS['robokassa_test_orders'][79] = array(
			'id' => 79,
			'total' => 100,
			// На момент process_refund WooCommerce уже сохранил локальный refund.
			'total_refunded' => 100,
			'meta' => array('_robokassa_operation_key' => 'operation-key'),
		);
		$GLOBALS['robokassa_test_http_callback'] = function ($method, $url, $args) {
			$jwt = json_decode($args['body'], true);
			$segments = explode('.', $jwt);
			$payload = $segments[1];
			$payload .= str_repeat('=', (4 - strlen($payload) % 4) % 4);
			$data = json_decode(base64_decode(strtr($payload, '-_', '+/')), true);
			$this->assertArrayNotHasKey('RefundSum', $data);
			return array(
				'response' => array('code' => 200),
				'body' => '{"success":true,"requestId":"68cd7fa6-1338-4745-ba5c-28d16cbcdb3e"}',
			);
		};

		$this->assertTrue((new \payment_robokassa_pay_method_request_main())->process_refund(79, 100));
	}

	public function testMarksAsynchronousRefundAsFinished(): void {
		$requestId = '68cd7fa6-1338-4745-ba5c-28d16cbcdb3f';
		$GLOBALS['robokassa_test_orders'][80] = array(
			'id' => 80,
			'total' => 100,
			'status' => 'processing',
			'payment_method' => 'robokassa',
			'meta' => array(
				'_robokassa_refund_requests' => array(
					$requestId => array('amount' => 100, 'is_full' => true, 'status' => 'processing', 'attempts' => 0),
				),
			),
		);
		$GLOBALS['robokassa_test_http_callback'] = function ($method, $url) use ($requestId) {
			$this->assertSame('GET', $method);
			$this->assertStringContainsString(rawurlencode($requestId), $url);
			return array(
				'response' => array('code' => 200),
				'body' => json_encode(array('requestId' => $requestId, 'amount' => 25, 'label' => 'finished')),
			);
		};

		\robokassa_payment_check_refund_status(80, $requestId);

		$order = new \WC_Order(80);
		$requests = $order->get_meta('_robokassa_refund_requests', true);
		$this->assertSame('finished', $requests[$requestId]['status']);
		$this->assertSame(1, $requests[$requestId]['attempts']);
		$this->assertSame('refunded', $order->get_status());
		$this->assertStringContainsString('возврат успешно завершён', $order->export_data()['notes'][0]);
	}

	public function testDefersFullyRefundedStatusOnlyForPendingRobokassaRefund(): void {
		$requestId = '68cd7fa6-1338-4745-ba5c-28d16cbcdb40';
		$GLOBALS['robokassa_test_orders'][84] = array(
			'id' => 84,
			'payment_method' => 'robokassa_sbp',
			'meta' => array(
				'_robokassa_refund_requests' => array(
					$requestId => array('is_full' => true, 'status' => 'processing'),
				),
			),
		);
		$GLOBALS['robokassa_test_orders'][85] = array(
			'id' => 85,
			'payment_method' => 'cod',
			'meta' => $GLOBALS['robokassa_test_orders'][84]['meta'],
		);

		$this->assertFalse(\robokassa_payment_defer_fully_refunded_status('refunded', 84, 900));
		$this->assertSame('refunded', \robokassa_payment_defer_fully_refunded_status('refunded', 85, 901));

		$order = new \WC_Order(84);
		$requests = $order->get_meta('_robokassa_refund_requests', true);
		$requests[$requestId]['status'] = 'finished';
		$order->update_meta_data('_robokassa_refund_requests', $requests);
		$order->save();
		$this->assertSame('refunded', \robokassa_payment_defer_fully_refunded_status('refunded', 84, 900));
	}

	public function testBindsRequestToWooRefundSendsReceiptAndPreventsDuplicate(): void {
		$this->setOptions(array(
			'robokassa_payment_MerchantLogin' => 'merchant',
			'robokassa_payment_shoppass2' => 'password2',
			'robokassa_payment_shoppass3' => 'password3',
			'robokassa_payment_test_onoff' => 'false',
			'robokassa_payment_tax' => 'none',
			'robokassa_payment_paymentObject' => 'commodity',
		));
		$originalItem = new \WC_Order_Item_Product(new \WC_Product('Товар'), 2, 100, array(), 501, 'Товар');
		$GLOBALS['robokassa_test_orders'][81] = array(
			'id' => 81,
			'total' => 100,
			'items' => array($originalItem),
			'meta' => array('_robokassa_operation_key' => 'operation-key'),
		);
		$refundItem = new \WC_Order_Item_Product(
			new \WC_Product('Товар'),
			-1,
			-50,
			array('_refunded_item_id' => 501),
			601,
			'Товар'
		);
		$refund = new \WC_Order_Refund(array(
			'id' => 901,
			'amount' => 50,
			'reason' => 'Один товар',
			'items' => array($refundItem),
		));
		$GLOBALS['robokassa_test_refunds'][81] = array($refund);
		$GLOBALS['robokassa_test_refund_orders'][901] = $refund;
		$GLOBALS['robokassa_test_http_callback'] = function ($method, $url, $args) {
			$jwt = json_decode($args['body'], true);
			$segments = explode('.', $jwt);
			$payload = $segments[1] . str_repeat('=', (4 - strlen($segments[1]) % 4) % 4);
			$data = json_decode(base64_decode(strtr($payload, '-_', '+/')), true);
			$this->assertSame('Товар', $data['InvoiceItems'][0]['Name']);
			$this->assertEquals(1.0, $data['InvoiceItems'][0]['Quantity']);
			$this->assertEquals(50.0, $data['InvoiceItems'][0]['Cost']);
			return array(
				'response' => array('code' => 200),
				'body' => '{"success":true,"requestId":"68cd7fa6-1338-4745-ba5c-28d16cbcdb40"}',
			);
		};

		$gateway = new \payment_robokassa_pay_method_request_main();
		$this->assertTrue($gateway->process_refund(81, 50, 'Один товар'));
		$this->assertSame(
			'68cd7fa6-1338-4745-ba5c-28d16cbcdb40',
			$refund->get_meta('_robokassa_refund_request_id', true)
		);
		$this->assertTrue($gateway->process_refund(81, 50, 'Один товар'));
		$this->assertCount(1, $GLOBALS['robokassa_test_http_requests']);
	}

	public function testBlocksImmediateRetryAfterAmbiguousNetworkFailure(): void {
		$this->setOptions(array(
			'robokassa_payment_MerchantLogin' => 'merchant',
			'robokassa_payment_shoppass2' => 'password2',
			'robokassa_payment_shoppass3' => 'password3',
			'robokassa_payment_test_onoff' => 'false',
		));
		$GLOBALS['robokassa_test_orders'][82] = array(
			'id' => 82,
			'total' => 100,
			'meta' => array('_robokassa_operation_key' => 'operation-key'),
		);
		$GLOBALS['robokassa_test_http_callback'] = function () {
			return new \WP_Error('http_request_failed', 'Connection timed out');
		};
		$gateway = new \payment_robokassa_pay_method_request_main();

		$first = $gateway->process_refund(82, 25, 'Сетевая ошибка');
		$second = $gateway->process_refund(82, 25, 'Сетевая ошибка');

		$this->assertSame('http_request_failed', $first->get_error_code());
		$this->assertSame('robokassa_refund_submission_uncertain', $second->get_error_code());
		$this->assertCount(1, $GLOBALS['robokassa_test_http_requests']);
	}
}
