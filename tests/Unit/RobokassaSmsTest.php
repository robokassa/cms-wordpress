<?php

namespace Robokassa\Tests\Unit;

use Robokassa\Payment\RoboDataBase;
use Robokassa\Payment\RobokassaPayAPI;
use Robokassa\Payment\RobokassaSms;
use Robokassa\Tests\Support\TestCase;

class RobokassaSmsTest extends TestCase {
	private $db;
	private $api;

	protected function setUp(): void {
		parent::setUp();
		$GLOBALS['wpdb'] = new \wpdb();
		$GLOBALS['robokassa_test_orders'][77] = array('id' => 77,
			'billing_address_1' => 'Москва', 'billing_address_2' => 'Ленина 1',
			'billing_first_name' => 'Иван', 'billing_last_name' => 'Иванов');
		$this->db = new SmsDatabaseStub();
		$this->api = new SmsApiStub();
	}

	private function sender($type = 1): RobokassaSms {
		return new RobokassaSms($this->db, $this->api, '+7 (900) 123-45-67', 'Заказ {order_number}, {fio}, {address}', true, 77, $type);
	}

	private function state($type = 1): array {
		return \wc_get_order(77)->get_meta(RobokassaSms::META_PREFIX . $type);
	}

	/** @dataProvider types */
	public function testStoresOnlyStateAndDoesNotNeedLegacyTable(int $type): void {
		$this->assertTrue($this->sender($type)->send());
		$this->assertSame('79001234567', $this->api->sentPhone);
		$this->assertSame('Zakaz 77, Ivan Ivanov, Moskva Lenina 1', $this->api->sentMessage);
		$this->assertSame(array('version', 'status', 'attempted_at', 'finished_at'), array_keys($this->state($type)));
		$this->assertSame('sent', $this->state($type)['status']);
		$this->assertGreaterThan(0, $this->state($type)['attempted_at']);
		$this->assertTrue($this->sender($type)->send());
		$this->assertSame(1, $this->api->calls);
		$this->assertSame(2, $this->db->releases);
		$this->assertStringNotContainsString('79001234567', json_encode($GLOBALS['robokassa_test_orders'][77]));
		foreach ($this->db->queries as $query) {
			$this->assertDoesNotMatchRegularExpression('/INSERT|UPDATE|DELETE|CREATE|DROP/i', $query[0]);
		}
	}

	public static function types(): array { return array('payment' => array(1), 'completion' => array(2)); }

	public function testTypesHaveIndependentAttempts(): void {
		$this->assertTrue($this->sender(1)->send());
		$this->assertTrue($this->sender(2)->send());
		$this->assertTrue($this->sender(1)->send());
		$this->assertTrue($this->sender(2)->send());
		$this->assertSame(2, $this->api->calls);
		$locks = array_values(array_filter($this->db->queries, function ($query) { return strpos($query[0], 'GET_LOCK') !== false; }));
		$this->assertNotSame($locks[0][1], $locks[1][1]);
	}

	/** @dataProvider types */
	public function testMigratesExistingLegacyAttemptWithoutReadingPersonalData(int $type): void {
		$this->db->tableExists = true;
		$this->db->existingCount = 1;
		$this->assertNull($this->sender($type)->send());
		$this->assertSame('legacy', $this->state($type)['source']);
		$this->assertSame('uncertain', $this->state($type)['status']);
		$this->assertSame(0, $this->api->calls);
		$this->assertNull($this->sender($type)->send());
		$this->assertCount(1, $GLOBALS['robokassa_test_orders'][77]['notes']);
		$this->assertCount(1, array_filter($this->db->queries, function ($query) { return strpos($query[0], 'SELECT COUNT') === 0; }));
	}

	public function testExistingTableWithoutThisAttemptDoesNotPreventSending(): void {
		$this->db->tableExists = true;
		$this->assertTrue($this->sender()->send());
		$this->assertSame(1, $this->api->calls);
	}

	/** @dataProvider oldProgress */
	public function testHonorsExistingResultProgress(string $old, string $status): void {
		$GLOBALS['robokassa_test_orders'][77]['meta']['_robokassa_result_progress'] = array('sms' => $old);
		$this->sender()->send();
		$this->assertSame($status, $this->state()['status']);
		$this->assertSame('result_progress', $this->state()['source']);
		$this->assertSame(0, $this->api->calls);
		$this->assertCount(2, $this->db->queries);
	}

	public static function oldProgress(): array { return array(array('sent', 'sent'), array('failed', 'uncertain'), array('uncertain', 'uncertain'), array('attempted', 'uncertain')); }

	/** @dataProvider historyFailures */
	public function testCannotSendWhenLegacyHistoryCannotBeChecked(string $failure): void {
		$this->db->tableExists = true;
		$this->db->failure = $failure;
		$this->assertInstanceOf(\WP_Error::class, $this->sender()->send());
		$this->assertSame(0, $this->api->calls);
		$this->assertSame('', \wc_get_order(77)->get_meta(RobokassaSms::META_PREFIX . '1'));
		$this->db->failure = '';
		$this->assertTrue($this->sender()->send());
		$this->assertSame(1, $this->api->calls);
	}

	public static function historyFailures(): array { return array(array('table'), array('count')); }

	public function testLockTimeoutDoesNotSendOrWriteOrder(): void {
		$this->db->lockResult = '0';
		$before = $GLOBALS['robokassa_test_orders'][77];
		$this->assertSame(503, $this->sender()->send()->get_error_data());
		$this->assertSame($before, $GLOBALS['robokassa_test_orders'][77]);
		$this->assertSame(0, $this->db->releases);
		$this->assertSame(0, $this->api->calls);
	}

	public function testLostSqlLockPreventsRequestAfterStateWasReserved(): void {
		$this->db->ownsLock = false;
		$this->assertSame(503, $this->sender()->send()->get_error_data());
		$this->assertSame(0, $this->api->calls);
		$this->assertSame('attempted', $this->state()['status']);
		$this->db->ownsLock = true;
		$this->assertNull($this->sender()->send());
		$this->assertSame(0, $this->api->calls);
		$this->assertSame('uncertain', $this->state()['status']);
	}

	public function testReadsFreshStateAfterAcquiringLock(): void {
		$GLOBALS['robokassa_test_order_cache'] = $GLOBALS['robokassa_test_orders'];
		$this->db->onLock = function () {
			$GLOBALS['robokassa_test_orders'][77]['meta'][RobokassaSms::META_PREFIX . '1'] = array('version' => 1, 'status' => 'sent', 'attempted_at' => 100);
		};
		$this->assertTrue($this->sender()->send());
		$this->assertSame(0, $this->api->calls);
	}

	public function testReentrantInvocationCannotSendAgain(): void {
		$this->api->callback = function () {
			$this->assertSame('robokassa_sms_busy', $this->sender()->send()->get_error_code());
			return true;
		};
		$this->assertTrue($this->sender()->send());
		$this->assertSame(1, $this->api->calls);
	}

	/** @dataProvider outcomes */
	public function testFailedAndUnknownOutcomesAreNotSentAgain($result, bool $throw, string $status): void {
		$this->api->callback = function () use ($result, $throw) {
			if ($throw) { throw new \RuntimeException('uncertain transport'); }
			return $result;
		};
		$this->sender()->send();
		$this->assertSame($status, $this->state()['status']);
		$this->sender()->send();
		$this->assertSame(1, $this->api->calls);
		$this->assertCount(1, $GLOBALS['robokassa_test_orders'][77]['notes']);
	}

	public static function outcomes(): array { return array(array(false, false, 'failed'), array(null, false, 'uncertain'), array(null, true, 'uncertain')); }

	public function testInterruptedAttemptIsNotRepeatedAndGetsWarning(): void {
		$GLOBALS['robokassa_test_orders'][77]['meta'][RobokassaSms::META_PREFIX . '1'] = array('version' => 1, 'status' => 'attempted', 'attempted_at' => 100);
		$this->assertNull($this->sender()->send());
		$this->assertSame('uncertain', $this->state()['status']);
		$this->assertSame(100, $this->state()['attempted_at']);
		$this->assertSame(0, $this->api->calls);
		$this->assertCount(1, $GLOBALS['robokassa_test_orders'][77]['notes']);
	}

	public function testSaveFailureBeforeRequestAllowsSafeRetry(): void {
		$GLOBALS['robokassa_test_meta_save_callback'] = function () { throw new \RuntimeException('storage unavailable'); };
		$this->assertInstanceOf(\WP_Error::class, $this->sender()->send());
		$this->assertSame(0, $this->api->calls);
		unset($GLOBALS['robokassa_test_meta_save_callback']);
		$this->assertTrue($this->sender()->send());
		$this->assertSame(1, $this->api->calls);
	}

	public function testSaveFailureAfterRequestDoesNotRepeatRequest(): void {
		$GLOBALS['robokassa_test_meta_save_callback'] = function ($order) {
			if (($order->get_meta(RobokassaSms::META_PREFIX . '1')['status'] ?? '') === 'sent') { throw new \RuntimeException('storage unavailable'); }
		};
		$this->assertInstanceOf(\WP_Error::class, $this->sender()->send());
		unset($GLOBALS['robokassa_test_meta_save_callback']);
		$this->assertNull($this->sender()->send());
		$this->assertSame(1, $this->api->calls);
	}

	public function testLostStateWriteIsDetectedBeforeSending(): void {
		$GLOBALS['robokassa_test_order_save_callback'] = function ($data) {
			unset($GLOBALS['robokassa_test_orders'][77]['meta'][RobokassaSms::META_PREFIX . '1']);
		};
		$this->assertInstanceOf(\WP_Error::class, $this->sender()->send());
		$this->assertSame(0, $this->api->calls);
	}

	public function testCorruptStateIsNotOverwrittenOrSent(): void {
		$GLOBALS['robokassa_test_orders'][77]['meta'][RobokassaSms::META_PREFIX . '1'] = 'corrupt';
		$this->assertInstanceOf(\WP_Error::class, $this->sender()->send());
		$this->assertSame('corrupt', \wc_get_order(77)->get_meta(RobokassaSms::META_PREFIX . '1'));
		$this->assertSame(0, $this->api->calls);
	}
	public function testCompletedHookUsesTestCredentialsAndSendsOnlyOnce(): void {
		$GLOBALS['wpdb'] = new SmsGatewayDatabase();
		$GLOBALS['robokassa_test_orders'][77]['billing_phone'] = '79001234567';
		$this->setOptions(array('robokassa_payment_sms2_enabled' => 'on', 'robokassa_payment_sms2_text' => 'order {order_number}',
			'robokassa_payment_test_onoff' => 'true', 'robokassa_payment_testshoppass1' => 'test-pass', 'robokassa_payment_shoppass1' => 'live-pass', 'robokassa_payment_MerchantLogin' => 'merchant'));
		$calls = 0;
		$GLOBALS['robokassa_test_http_callback'] = function ($method, $url) use (&$calls) {
			$calls++; parse_str(parse_url($url, PHP_URL_QUERY), $query);
			$this->assertSame(strtoupper(md5('merchant:79001234567:order 77:test-pass')), $query['signature']);
			return array('response' => array('code' => 200), 'body' => '{"result":1}');
		};
		\do_action('woocommerce_order_status_completed', 77);
		\do_action('woocommerce_order_status_completed', 77);
		$this->assertSame(1, $calls);
		$this->assertSame('sent', $this->state(2)['status']);
	}

	public function testDisabledCompletedSmsDoesNotReadDatabaseOrSend(): void {
		$GLOBALS['wpdb'] = new SmsGatewayDatabase();
		\do_action('woocommerce_order_status_completed', 77);
		$this->assertSame(0, $GLOBALS['wpdb']->calls);
		$this->assertSame(array(), $GLOBALS['robokassa_test_http_requests']);
	}

	public function testInvalidMessageCanBeCorrectedWithoutReservingAnAttempt(): void {
		$invalid = new RobokassaSms($this->db, $this->api, '', '', false, 77, 1);
		$this->assertSame(400, $invalid->send()->get_error_data());
		$this->assertSame('', \wc_get_order(77)->get_meta(RobokassaSms::META_PREFIX . '1'));
		$this->assertTrue($this->sender()->send());
		$this->assertSame(1, $this->api->calls);
	}

}

class SmsDatabaseStub extends RoboDataBase {
	public $tableExists = false;
	public $existingCount = 0;
	public $lockResult = '1';
	public $ownsLock = true;
	public $releases = 0;
	public $queries = array();
	public $failure = '';
	public $onLock;
	private $error = false;

	public function __construct() {}

	public function getVar($sql, array $args = array()) {
		$this->queries[] = array($sql, $args);
		$this->error = false;
		if (strpos($sql, 'IS_USED_LOCK') !== false) { return $this->ownsLock ? '1' : '0'; }
		if (strpos($sql, 'GET_LOCK') !== false) {
			if ($this->onLock) { call_user_func($this->onLock); }
			return $this->lockResult;
		}
		if (strpos($sql, 'RELEASE_LOCK') !== false) { $this->releases++; return '1'; }
		if (strpos($sql, 'SHOW') === 0) {
			if ($this->failure === 'table') { $this->error = true; return null; }
			return $this->tableExists ? 'wp_sms_stats' : null;
		}
		if ($this->failure === 'count') { $this->error = true; return null; }
		return (string)$this->existingCount;
	}

	public function hasError() { return $this->error; }
}

class SmsApiStub extends RobokassaPayAPI {
	public $sentPhone;
	public $sentMessage;
	public $calls = 0;
	public $callback;

	public function __construct() { parent::__construct('merchant', 'pass1', 'pass2'); }

	public function sendSms($phone, $message) {
		$this->calls++;
		$this->sentPhone = $phone;
		$this->sentMessage = $message;
		return $this->callback ? call_user_func($this->callback) : true;
	}
}

class SmsGatewayDatabase extends \wpdb {
	public $calls = 0;
	public function prepare($sql, ...$args) { return $sql; }
	public function get_var($sql) { $this->calls++; return strpos($sql, 'SHOW TABLES') === 0 ? null : '1'; }
}
