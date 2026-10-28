<?php

namespace Robokassa\Tests\Integration;

use Robokassa\Tests\Support\TestCase;

class ClassicResultConcurrencyTest extends TestCase
{
	/** @requires extension pdo_mysql */
	public function testTwoProcessesHandleTheSamePaymentOnlyOnce(): void
	{
		$socket = getenv('ROBOKASSA_TEST_MYSQL_SOCKET');
		if (!$socket) {
			$this->markTestSkipped('задайте ROBOKASSA_TEST_MYSQL_SOCKET для отдельного тестового MySQL');
		}
		$stateFile = tempnam(sys_get_temp_dir(), 'robokassa-concurrent-');
		$barrierFile = $stateFile . '-barrier';
		file_put_contents($stateFile, json_encode(array('order' => array('id' => 42, 'status' => 'pending'), 'cart_calls' => 0)));
		$processes = array();
		try {
			$processes[] = $this->startWorker($socket, $stateFile, $barrierFile, 'wc-custom-paid');
			$deadline = microtime(true) + 5;
			while (!file_exists($barrierFile) && microtime(true) < $deadline) {
				usleep(10000);
			}
			$this->assertFileExists($barrierFile);
			$processes[] = $this->startWorker($socket, $stateFile, $barrierFile, 'wc-completed');
			$responses = array();
			foreach ($processes as $process) {
				$output = stream_get_contents($process[1][1]);
				$error = stream_get_contents($process[1][2]);
				fclose($process[1][1]);
				fclose($process[1][2]);
				$this->assertSame(0, proc_close($process[0]), $error . $output);
				$responses[] = json_decode($output, true);
			}
			$processes = array();
			$this->assertSame(array('OK42', 'OK42'), array_column($responses, 'result'));
			$this->assertGreaterThan(0.2, $responses[1]['elapsed']);
			$state = json_decode(file_get_contents($stateFile), true);
			$this->assertSame(1, $state['order']['payment_complete_calls']);
			$this->assertSame('custom-paid', $state['order']['status']);
			$this->assertSame(array('Заказ успешно оплачен!'), $state['order']['notes']);
			$this->assertSame(1, $state['cart_calls']);
		} finally {
			foreach ($processes as $process) {
				if (is_resource($process[0])) {
					proc_terminate($process[0]);
					proc_close($process[0]);
				}
			}
			unlink($stateFile);
			if (file_exists($barrierFile)) {
				unlink($barrierFile);
			}
		}
	}

	private function startWorker($socket, $stateFile, $barrierFile, $status): array
	{
		$process = proc_open(
			array(PHP_BINARY, dirname(__DIR__) . '/Support/ResultConcurrencyWorker.php', $socket, $stateFile, $barrierFile, $status),
			array(0 => array('pipe', 'r'), 1 => array('pipe', 'w'), 2 => array('pipe', 'w')),
			$pipes
		);
		$this->assertIsResource($process);
		fclose($pipes[0]);
		return array($process, $pipes);
	}
}
