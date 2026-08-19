<?php

namespace Robokassa\Tests\Unit;

use Robokassa\Payment\RoboDataBase;
use Robokassa\Tests\Support\TestCase;

class RoboDataBaseTest extends TestCase {
	public function testPreparesParameterizedQueries(): void {
		$db = new RecordingWpdb();
		$database = new RoboDataBase($db);

		$this->assertSame(2, $database->query('DELETE FROM table WHERE id = %d', array(5)));
		$this->assertSame('DELETE FROM table WHERE id = 5', $db->queries[0]);

		$this->assertSame(2, $database->query('DELETE FROM table'));
		$this->assertSame('DELETE FROM table', $db->queries[1]);
	}

	public function testReadsSingleValue(): void {
		$db = new RecordingWpdb();
		$db->varResult = '7';

		$this->assertSame(
			'7',
			(new RoboDataBase($db))->getVar('SELECT COUNT(*) FROM table WHERE id = %d', array(10))
		);
		$this->assertSame('SELECT COUNT(*) FROM table WHERE id = 10', $db->lastPrepared);
	}

	public function testDelegatesInsertAndUpdateWithFormats(): void {
		$db = new RecordingWpdb();
		$database = new RoboDataBase($db);

		$this->assertSame(1, $database->insert('wp_sms', array('status' => '-1'), array('%s')));
		$this->assertSame(
			array('wp_sms', array('status' => '-1'), array('%s')),
			$db->lastInsert
		);

		$this->assertSame(
			1,
			$database->update('wp_sms', array('status' => '1'), array('order_id' => 5), array('%s'), array('%d'))
		);
		$this->assertSame(
			array('wp_sms', array('status' => '1'), array('order_id' => 5), array('%s'), array('%d')),
			$db->lastUpdate
		);
	}
}

class RecordingWpdb extends \wpdb {
	public $queries = array();
	public $lastPrepared = '';
	public $varResult = null;
	public $lastInsert;
	public $lastUpdate;

	public function prepare($sql, $args) {
		$this->lastPrepared = vsprintf(str_replace('%d', '%d', $sql), $args);
		return $this->lastPrepared;
	}

	public function query($sql) {
		$this->queries[] = $sql;
		return 2;
	}

	public function get_var($sql) {
		$this->lastPrepared = $sql;
		return $this->varResult;
	}

	public function insert($table, $data, $format = null) {
		$this->lastInsert = array($table, $data, $format);
		return 1;
	}

	public function update($table, $data, $where, $dataFormat = null, $whereFormat = null) {
		$this->lastUpdate = array($table, $data, $where, $dataFormat, $whereFormat);
		return 1;
	}
}
