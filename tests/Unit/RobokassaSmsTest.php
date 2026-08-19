<?php

namespace Robokassa\Tests\Unit;

use Robokassa\Payment\RoboDataBase;
use Robokassa\Payment\RobokassaPayAPI;
use Robokassa\Payment\RobokassaSms;
use Robokassa\Tests\Support\TestCase;

class RobokassaSmsTest extends TestCase {
	public function testFiltersTransliteratesSendsAndRecordsSms(): void {
		$GLOBALS['wpdb'] = new \wpdb();
		$GLOBALS['robokassa_test_orders'][77] = array(
			'billing_address_1' => 'Москва',
			'billing_address_2' => 'Ленина 1',
			'billing_first_name' => 'Иван',
			'billing_last_name' => 'Иванов',
		);
		$db = new SmsDatabaseStub();
		$api = new SmsApiStub();
		$sms = new RobokassaSms(
			$db,
			$api,
			'+7 (900) 123-45-67',
			'Заказ {order_number}, {fio}, {address}',
			true,
			77,
			1
		);

		$sms->send();

		$this->assertSame('79001234567', $api->sentPhone);
		$this->assertSame('Zakaz 77, Ivan Ivanov, Moskva Lenina 1', $api->sentMessage);
		$this->assertSame('wp_sms_stats', $db->insertedTable);
		$this->assertSame('79001234567', $db->insertedData['number']);
		$this->assertSame('1', $db->updatedData['status']);
	}

	public function testExistingSmsIsNotSentAgain(): void {
		$GLOBALS['wpdb'] = new \wpdb();
		$GLOBALS['robokassa_test_orders'][78] = array();
		$db = new SmsDatabaseStub();
		$db->existingCount = 1;
		$api = new SmsApiStub();
		$sms = new RobokassaSms($db, $api, '7900', 'Message', false, 78, 1);

		$sms->send();

		$this->assertNull($api->sentPhone);
		$this->assertSame('-1', $db->updatedData['status']);
	}
}

class SmsDatabaseStub extends RoboDataBase {
	public $existingCount = 0;
	public $insertedTable;
	public $insertedData = array();
	public $updatedData = array();

	public function __construct() {
	}

	public function getVar($sql, array $args = array()) {
		return $this->existingCount;
	}

	public function insert($table, array $data, array $format = array()) {
		$this->insertedTable = $table;
		$this->insertedData = $data;
		return 1;
	}

	public function update($table, array $data, array $where, array $data_format = array(), array $where_format = array()) {
		$this->updatedData = $data;
		return 1;
	}
}

class SmsApiStub extends RobokassaPayAPI {
	public $sentPhone;
	public $sentMessage;

	public function __construct() {
		parent::__construct('merchant', 'pass1', 'pass2');
	}

	public function sendSms($phone, $message) {
		$this->sentPhone = $phone;
		$this->sentMessage = $message;
		return true;
	}

	public function getSendResult() {
		return json_encode(array('reply' => json_encode(array('result' => true))));
	}

	public function getRequest() {
		return 'request-url';
	}

	public function getReply() {
		return '{"result":true}';
	}
}
