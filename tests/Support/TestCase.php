<?php

namespace Robokassa\Tests\Support;

use PHPUnit\Framework\TestCase as PhpUnitTestCase;

abstract class TestCase extends PhpUnitTestCase {
	protected function setUp(): void {
		parent::setUp();

		$GLOBALS['robokassa_test_options'] = array(
			'woocommerce_price_num_decimals' => 2,
			'robokassa_country_code' => 'RU',
		);
		$GLOBALS['robokassa_test_orders'] = array();
		$GLOBALS['robokassa_test_products'] = array();
		$GLOBALS['robokassa_test_checkbox_fields'] = array();
		$GLOBALS['robokassa_test_log_entries'] = array();
		$GLOBALS['robokassa_test_logger_throw'] = false;
		$GLOBALS['robokassa_test_refunds'] = array();
		$GLOBALS['robokassa_test_refund_orders'] = array();
		$GLOBALS['robokassa_test_wc'] = new \Robokassa_Test_WC_Container();
		$GLOBALS['robokassa_test_http_requests'] = array();
		$GLOBALS['robokassa_test_scheduled_events'] = array();
		unset($GLOBALS['robokassa_test_http_callback']);
		unset($GLOBALS['robokassa_test_order_save_callback'], $GLOBALS['robokassa_test_payment_complete_callback']);
		unset($GLOBALS['robokassa_test_stream_callback']);
		unset($GLOBALS['robokassa_test_after_payment_callback'], $GLOBALS['robokassa_test_note_callback'], $GLOBALS['robokassa_test_meta_save_callback'], $GLOBALS['robokassa_test_status_callback']);
		$GLOBALS['robokassa_test_filters'] = array();
		unset($GLOBALS['robokassa_test_order_cache']);
		$GLOBALS['robokassa_test_subscriptions'] = array();
		$GLOBALS['robokassa_test_renewal_subscriptions'] = array();
		$_POST = array();
		$_GET = array();
		$_REQUEST = array();
	}

	protected function setOptions(array $options): void {
		$GLOBALS['robokassa_test_options'] = array_merge(
			$GLOBALS['robokassa_test_options'],
			$options
		);
	}
}
