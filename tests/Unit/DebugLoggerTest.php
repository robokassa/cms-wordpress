<?php

namespace Robokassa\Tests\Unit;

use Robokassa\Payment\DebugLogger;
use Robokassa\Tests\Support\TestCase;

class DebugLoggerTest extends TestCase
{
	public function testDebugIsDisabledByDefault(): void
	{
		\robokassa_payment_DEBUG('second_receipt_prepared', array('order_id' => 42, 'item_count' => 1));

		$this->assertFalse(ROBOKASSA_PAYMENT_DEBUG_STATUS);
		$this->assertSame(array(), $GLOBALS['robokassa_test_log_entries']);
		$this->assertFileDoesNotExist(dirname(__DIR__, 2) . '/data/robokassa_DEBUG.txt');
	}

	/** @dataProvider debugSettings */
	public function testWrapperHonorsConfiguredDebugSetting($setting, bool $expected): void
	{
		$script = 'define("ROBOKASSA_PAYMENT_DEBUG_STATUS", ' . var_export($setting, true) . ');'
			. 'require ' . var_export(dirname(__DIR__, 2) . '/vendor/autoload.php', true) . ';'
			. 'require ' . var_export(dirname(__DIR__) . '/bootstrap.php', true) . ';'
			. 'robokassa_payment_DEBUG("second_receipt_prepared", array("order_id" => 42, "item_count" => 2));'
			. 'echo count($GLOBALS["robokassa_test_log_entries"] ?? array());';
		$output = array();
		exec(escapeshellarg(PHP_BINARY) . ' -r ' . escapeshellarg($script) . ' 2>&1', $output, $status);

		$this->assertSame(0, $status, implode("\n", $output));
		$this->assertSame($expected ? '1' : '0', implode("\n", $output));
	}

	public static function debugSettings(): array
	{
		return array(
			'enabled' => array(true, true),
			'disabled' => array(false, false),
			'string false' => array('false', false),
		);
	}

	public function testStoresOnlyAllowlistedDiagnosticsUsingDatabaseHandler(): void
	{
		(new DebugLogger())->log('second_receipt_prepared', array(
			'order_id' => 42,
			'item_count' => 2,
			'email' => 'private@example.test',
			'phone' => '+79990000000',
			'name' => 'private customer',
			'items' => array(array('name' => 'private product', 'nomenclature_code' => 'private-code')),
			'source' => 'public-file',
			'backtrace' => true,
		));

		$this->assertCount(1, $GLOBALS['robokassa_test_log_entries']);
		$entry = $GLOBALS['robokassa_test_log_entries'][0];
		$this->assertSame('second_receipt_prepared', $entry['message']);
		$this->assertSame(array('source' => 'robokassa', 'order_id' => 42, 'item_count' => 2), $entry['context']);
		$this->assertCount(1, $entry['handlers']);
		$this->assertInstanceOf(\WC_Log_Handler_DB::class, $entry['handlers'][0]);
	}

	public function testRejectsFreeTextAndEncodedPayloads(): void
	{
		$logger = new DebugLogger();
		foreach (array(
			'private@example.test',
			"second_receipt_prepared\r\nprivate customer",
			'{"email":"private@example.test"}',
			base64_encode('private@example.test'),
			array('second_receipt_prepared'),
		) as $message) {
			$logger->log($message);
		}

		$this->assertSame(array(), $GLOBALS['robokassa_test_log_entries']);
	}

	public function testRejectsInjectedValuesEvenInAllowedContextKeys(): void
	{
		(new DebugLogger())->log('second_receipt_response', array(
			'order_id' => "42\nprivate@example.test",
			'result_code' => array('phone' => '+79990000000'),
			'response' => '{"items":[{"name":"private product"}]}',
		));

		$this->assertSame(array('source' => 'robokassa'), $GLOBALS['robokassa_test_log_entries'][0]['context']);
	}

	public function testLoggingFailureDoesNotInterruptPayment(): void
	{
		$GLOBALS['robokassa_test_logger_throw'] = true;
		(new DebugLogger())->log('second_receipt_prepared', array('order_id' => 42));

		$this->assertSame(array(), $GLOBALS['robokassa_test_log_entries']);
	}

	public function testMissingWooCommerceDoesNotCauseAnError(): void
	{
		$script = 'require ' . var_export(dirname(__DIR__, 2) . '/classes/Robokassa/Payment/DebugLogger.php', true) . ';'
			. '(new Robokassa\\Payment\\DebugLogger())->log("second_receipt_prepared"); echo "ok";';
		$output = array();
		exec(escapeshellarg(PHP_BINARY) . ' -r ' . escapeshellarg($script) . ' 2>&1', $output, $status);

		$this->assertSame(0, $status, implode("\n", $output));
		$this->assertSame('ok', implode("\n", $output));
	}

	public function testPluginLoadRemovesLegacyLogEvenWithDebugDisabled(): void
	{
		$root = dirname(__DIR__, 2);
		$directory = sys_get_temp_dir() . '/robokassa-plugin-' . uniqid();
		mkdir($directory);
		mkdir($directory . '/data');
		$path = $directory . '/data/robokassa_DEBUG.txt';
		try {
			copy($root . '/wp_robokassa.php', $directory . '/wp_robokassa.php');
			foreach (array('payment-widget.php', 'StatusReporter.php', 'classes') as $entry) {
				symlink($root . '/' . $entry, $directory . '/' . $entry);
			}
			file_put_contents($path, 'private@example.test +79990000000 private product');
			$script = 'define("ROBOKASSA_PAYMENT_DEBUG_STATUS", false);'
				. 'require ' . var_export(dirname(__DIR__) . '/Support/WordPressWooCommerceStubs.php', true) . ';'
				. 'chdir(' . var_export($directory, true) . '); require "wp_robokassa.php"; echo "loaded";';
			$output = array();
			exec(escapeshellarg(PHP_BINARY) . ' -r ' . escapeshellarg($script) . ' 2>&1', $output, $status);

			$this->assertSame(0, $status, implode("\n", $output));
			$this->assertSame('loaded', implode("\n", $output));
			$this->assertFileDoesNotExist($path);
		} finally {
			foreach (array('payment-widget.php', 'StatusReporter.php', 'classes', 'wp_robokassa.php', 'data/robokassa_DEBUG.txt') as $entry) {
				if (file_exists($directory . '/' . $entry) || is_link($directory . '/' . $entry)) {
					unlink($directory . '/' . $entry);
				}
			}
			rmdir($directory . '/data');
			rmdir($directory);
		}
	}

	public function testClearsLegacyContentsWhenDirectoryForbidsDeletion(): void
	{
		$directory = sys_get_temp_dir() . '/robokassa-readonly-' . uniqid();
		mkdir($directory);
		$path = $directory . '/robokassa_DEBUG.txt';
		file_put_contents($path, 'private@example.test private product');
		chmod($directory, 0555);
		try {
			$this->assertTrue(DebugLogger::removeLegacyLog($path));
			if (file_exists($path)) {
				$this->assertSame('', file_get_contents($path));
			} else {
				$this->assertFileDoesNotExist($path);
			}
		} finally {
			chmod($directory, 0700);
			if (file_exists($path)) {
				unlink($path);
			}
			rmdir($directory);
		}
	}

	public function testDeletesExistingLegacyLogInsteadOfMigratingSensitiveContents(): void
	{
		$path = tempnam(sys_get_temp_dir(), 'robokassa-legacy-');
		try {
			file_put_contents($path, 'private@example.test +79990000000 private product private-code');
			$this->assertTrue(DebugLogger::removeLegacyLog($path));
			$this->assertFileDoesNotExist($path);
			$this->assertTrue(DebugLogger::removeLegacyLog($path));
			$this->assertSame(array(), $GLOBALS['robokassa_test_log_entries']);
		} finally {
			if (is_file($path)) {
				unlink($path);
			}
		}
	}

	public function testDeletesLegacySymlinkWithoutChangingItsTarget(): void
	{
		$target = tempnam(sys_get_temp_dir(), 'robokassa-target-');
		$link = $target . '-link';
		try {
			file_put_contents($target, 'keep target');
			symlink($target, $link);
			$this->assertTrue(DebugLogger::removeLegacyLog($link));
			$this->assertFalse(is_link($link));
			$this->assertSame('keep target', file_get_contents($target));
		} finally {
			if (is_link($link)) {
				unlink($link);
			}
			unlink($target);
		}
	}

	public function testReportsCleanupFailureInsteadOfDeletingDirectories(): void
	{
		$path = sys_get_temp_dir() . '/robokassa-legacy-' . uniqid();
		mkdir($path);
		try {
			$this->assertFalse(DebugLogger::removeLegacyLog($path));
			$this->assertDirectoryExists($path);
		} finally {
			rmdir($path);
		}
	}

	public function testReportsFailureWhenLegacyFileCannotBeDeletedOrCleared(): void
	{
		$directory = sys_get_temp_dir() . '/robokassa-denied-' . uniqid();
		mkdir($directory);
		$path = $directory . '/robokassa_DEBUG.txt';
		file_put_contents($path, 'private@example.test');
		chmod($path, 0444);
		chmod($directory, 0555);
		try {
			if (is_writable($directory) || is_writable($path)) {
				$this->markTestSkipped('для проверки отказа прав требуется непривилегированный пользователь');
			}
			$this->assertFalse(DebugLogger::removeLegacyLog($path));
			$this->assertSame('private@example.test', file_get_contents($path));
		} finally {
			chmod($directory, 0700);
			chmod($path, 0600);
			unlink($path);
			rmdir($directory);
		}
	}

	/** @requires extension pdo_sqlite */
	public function testScheduledCleanupRemovesOnlyExpiredRobokassaEntriesEvenWhenDebugIsDisabled(): void
	{
		$previousDb = $GLOBALS['wpdb'] ?? null;
		$db = new DebugLogDatabase();
		$GLOBALS['wpdb'] = $db;
		$db->connection->exec('CREATE TABLE wp_woocommerce_log (source TEXT, timestamp TEXT)');
		$insert = $db->connection->prepare('INSERT INTO wp_woocommerce_log VALUES (?, ?)');
		$insert->execute(array('robokassa', date('Y-m-d H:i:s', strtotime('-40 days'))));
		$insert->execute(array('robokassa', date('Y-m-d H:i:s', strtotime('-1 day'))));
		$insert->execute(array('other-plugin', date('Y-m-d H:i:s', strtotime('-40 days'))));
		try {
			\do_action('woocommerce_cleanup_logs');
			$rows = $db->connection->query('SELECT source, timestamp FROM wp_woocommerce_log')->fetchAll(\PDO::FETCH_ASSOC);

			$this->assertFalse(ROBOKASSA_PAYMENT_DEBUG_STATUS);
			$this->assertCount(2, $rows);
			$this->assertSame(array('robokassa', 'other-plugin'), array_column($rows, 'source'));
			$this->assertGreaterThan(strtotime('-30 days'), strtotime($rows[0]['timestamp']));
		} finally {
			$GLOBALS['wpdb'] = $previousDb;
		}
	}
}

class DebugLogDatabase extends \wpdb
{
	public $connection;

	public function __construct()
	{
		$this->connection = new \PDO('sqlite::memory:');
		$this->connection->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
	}

	public function prepare($sql, ...$args)
	{
		return vsprintf($sql, array_map(array($this->connection, 'quote'), $args));
	}

	public function query($sql)
	{
		return $this->connection->exec($sql);
	}
}
