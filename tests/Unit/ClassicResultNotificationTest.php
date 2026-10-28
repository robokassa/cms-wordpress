<?php

namespace Robokassa\Tests\Unit;

use Robokassa\Payment\ClassicResultNotification;
use Robokassa\Tests\Support\TestCase;

class ClassicResultNotificationTest extends TestCase
{
	private $database;
	private $cart;

	protected function setUp(): void
	{
		parent::setUp();
		$this->setOptions(array(
			'robokassa_payment_MerchantLogin' => 'merchant',
			'robokassa_payment_shoppass2' => 'password2',
		));
		$this->database = new ResultLockDatabase();
		$this->cart = new ResultCart();
		$GLOBALS['robokassa_test_wc']->cart = $this->cart;
		$GLOBALS['robokassa_test_orders'][42] = array('id' => 42, 'status' => 'pending');
	}

	private function request(string $invoiceId = '42'): array
	{
		$request = array('OutSum' => '100.00', 'InvId' => $invoiceId);
		$request['SignatureValue'] = \robokassa_payment_build_callback_signature($request, 'password2');
		return $request;
	}

	public function testFirstNotificationCompletesPaymentAndDuplicateHasNoSideEffects(): void
	{
		$handler = new ClassicResultNotification($this->database);
		$this->assertSame('OK42', $handler->process($this->request(), 'wc-custom-paid'));
		$order = \wc_get_order(42);
		$this->assertSame('custom-paid', $order->get_status());
		$this->assertNotNull($order->get_date_paid());
		$this->assertSame('yes', $order->get_meta(ClassicResultNotification::PROCESSED_META_KEY));
		$this->assertSame(1, $this->cart->calls);
		$before = $GLOBALS['robokassa_test_orders'][42];

		$this->assertSame('OK42', $handler->process($this->request(), 'wc-completed'));
		$this->assertSame($before, $GLOBALS['robokassa_test_orders'][42]);
		$this->assertSame(1, $this->cart->calls);
		$this->assertSame(1, $before['payment_complete_calls']);
		$this->assertSame(array('Заказ успешно оплачен!'), $before['notes']);
		$this->assertSame(2, $this->database->releases);
	}

	/** @dataProvider previousPaymentStates */
	public function testPreservesPreviouslyPaidOrdersRegardlessOfCurrentStatus(array $state): void
	{
		$GLOBALS['robokassa_test_orders'][42] = array_merge(array('id' => 42), $state);
		$before = $GLOBALS['robokassa_test_orders'][42];

		$this->assertSame('OK42', (new ClassicResultNotification($this->database))->process($this->request(), 'wc-processing'));
		$this->assertSame($before, $GLOBALS['robokassa_test_orders'][42]);
		$this->assertSame(0, $this->cart->calls);
	}

	public static function previousPaymentStates(): array
	{
		return array(
			'processing' => array(array('status' => 'processing')),
			'completed' => array(array('status' => 'completed')),
			'custom paid' => array(array('status' => 'custom-paid', 'date_paid' => 100)),
			'cancelled later' => array(array('status' => 'cancelled', 'date_paid' => 100)),
			'refunded later' => array(array('status' => 'refunded', 'date_paid' => 100)),
			'marker without date' => array(array('status' => 'custom', 'meta' => array(ClassicResultNotification::PROCESSED_META_KEY => 'yes'))),
		);
	}

	public function testHoldTransactionDoesNotPreventActualPayment(): void
	{
		$GLOBALS['robokassa_test_orders'][42]['status'] = 'on-hold';
		$GLOBALS['robokassa_test_orders'][42]['transaction_id'] = 'hold-operation';
		$GLOBALS['robokassa_test_orders'][42]['meta'] = array('_robokassa_result2_state' => 'HOLD', '_robokassa_operation_key' => 'hold-operation');

		$this->assertSame('OK42', (new ClassicResultNotification($this->database))->process($this->request(), 'wc-processing'));
		$this->assertSame(1, $GLOBALS['robokassa_test_orders'][42]['payment_complete_calls']);
	}

	public function testRejectsBadSignatureBeforeLockEvenForProcessedOrder(): void
	{
		$GLOBALS['robokassa_test_orders'][42]['meta'][ClassicResultNotification::PROCESSED_META_KEY] = 'yes';
		$before = $GLOBALS['robokassa_test_orders'][42];
		$request = $this->request();
		$request['SignatureValue'] = 'bad';
		$result = (new ClassicResultNotification($this->database))->process($request, 'wc-processing');

		$this->assertSame('BAD SIGN', $result->get_error_message());
		$this->assertSame(400, $result->get_error_data());
		$this->assertSame(array(), $this->database->queries);
		$this->assertSame($before, $GLOBALS['robokassa_test_orders'][42]);
	}

	/** @dataProvider lockFailures */
	public function testLockFailureDoesNotAcknowledgeOrModifyOrder($lockResult): void
	{
		$this->database->lockResult = $lockResult;
		$before = $GLOBALS['robokassa_test_orders'][42];
		$result = (new ClassicResultNotification($this->database))->process($this->request(), 'wc-processing');

		$this->assertSame(503, $result->get_error_data());
		$this->assertSame($before, $GLOBALS['robokassa_test_orders'][42]);
		$this->assertSame(0, $this->cart->calls);
		$this->assertSame(0, $this->database->releases);
	}

	public static function lockFailures(): array
	{
		return array('busy' => array('0'), 'database error' => array(null));
	}

	public function testReadsProcessedStateAfterAcquiringLock(): void
	{
		$this->database->onLock = function () {
			$GLOBALS['robokassa_test_orders'][42]['meta'][ClassicResultNotification::PROCESSED_META_KEY] = 'yes';
		};

		$this->assertSame('OK42', (new ClassicResultNotification($this->database))->process($this->request(), 'wc-processing'));
		$this->assertArrayNotHasKey('payment_complete_calls', $GLOBALS['robokassa_test_orders'][42]);
		$this->assertSame(0, $this->cart->calls);
	}

	public function testInvalidatesOrderAndMetaCachesWarmedBeforeConcurrentPayment(): void
	{
		$GLOBALS['robokassa_test_order_cache'] = $GLOBALS['robokassa_test_orders'];
		$this->database->onLock = function () {
			$GLOBALS['robokassa_test_orders'][42]['status'] = 'custom-paid';
			$GLOBALS['robokassa_test_orders'][42]['date_paid'] = 100;
			$GLOBALS['robokassa_test_orders'][42]['meta'][ClassicResultNotification::PROCESSED_META_KEY] = 'yes';
		};

		$this->assertSame('OK42', (new ClassicResultNotification($this->database))->process($this->request(), 'wc-completed'));
		$this->assertSame('custom-paid', $GLOBALS['robokassa_test_orders'][42]['status']);
		$this->assertArrayNotHasKey('payment_complete_calls', $GLOBALS['robokassa_test_orders'][42]);
		$this->assertSame(0, $this->cart->calls);
	}

	public function testFailureBeforePaymentReleasesLockAndAllowsRetry(): void
	{
		$GLOBALS['robokassa_test_payment_complete_callback'] = function () {
			throw new \RuntimeException('failure before payment');
		};
		$handler = new ClassicResultNotification($this->database);
		$result = $handler->process($this->request(), 'wc-custom-paid');

		$this->assertSame(503, $result->get_error_data());
		$this->assertSame('pending', $GLOBALS['robokassa_test_orders'][42]['status']);
		$this->assertSame(1, $this->database->releases);
		unset($GLOBALS['robokassa_test_payment_complete_callback']);
		$this->assertSame('OK42', $handler->process($this->request(), 'wc-custom-paid'));
		$this->assertSame(1, $GLOBALS['robokassa_test_orders'][42]['payment_complete_calls']);
	}

	public function testFalsePaymentResultDoesNotApplyCustomStatusOrMarkProcessed(): void
	{
		$GLOBALS['robokassa_test_orders'][42]['payment_failure'] = true;
		$result = (new ClassicResultNotification($this->database))->process($this->request(), 'wc-custom-paid');

		$this->assertSame(503, $result->get_error_data());
		$this->assertSame('pending', $GLOBALS['robokassa_test_orders'][42]['status']);
		$this->assertEmpty(\wc_get_order(42)->get_meta(ClassicResultNotification::PROCESSED_META_KEY));
		$this->assertSame(1, $this->database->releases);
	}

	public function testMissingOrderReturns404AndReleasesLock(): void
	{
		unset($GLOBALS['robokassa_test_orders'][42]);
		$result = (new ClassicResultNotification($this->database))->process($this->request(), 'wc-processing');

		$this->assertSame(404, $result->get_error_data());
		$this->assertSame(1, $this->database->releases);
	}

	public function testEquivalentInvoiceFormsUseSameLockAndPreserveResponse(): void
	{
		$handler = new ClassicResultNotification($this->database);
		$this->assertSame('OK42', $handler->process($this->request(), 'wc-processing'));
		$this->assertSame('OK00042', $handler->process($this->request('00042'), 'wc-processing'));
		$this->assertSame($this->database->queries[0], $this->database->queries[2]);
	}

	/** @dataProvider invalidInvoices */
	public function testRejectsInvalidSignedInvoiceBeforeLock(string $invoiceId): void
	{
		$result = (new ClassicResultNotification($this->database))->process($this->request($invoiceId), 'wc-processing');

		$this->assertSame(400, $result->get_error_data());
		$this->assertSame(array(), $this->database->queries);
		$this->assertSame(0, $this->cart->calls);
	}

	public static function invalidInvoices(): array
	{
		return array('zero' => array('0'), 'negative' => array('-42'), 'suffix' => array('42abc'), 'overflow' => array((string)PHP_INT_MAX . '0'));
	}

	public function testNullCartDoesNotPreventServerToServerNotification(): void
	{
		$GLOBALS['robokassa_test_wc']->cart = null;
		$this->assertSame('OK42', (new ClassicResultNotification($this->database))->process($this->request(), 'wc-processing'));
	}

	public function testDoesNotRepeatSubscriptionOrSmsEffects(): void
	{
		$GLOBALS['wpdb'] = $this->database;
		$this->setOptions(array('robokassa_payment_sms1_enabled' => 'on', 'robokassa_payment_sms1_text' => 'order {order_number}'));
		$GLOBALS['robokassa_test_orders'][42]['billing_phone'] = '+79001234567';
		$subscription = new ResultSubscription();
		$GLOBALS['robokassa_test_subscriptions'] = array($subscription);
		$smsRequests = array();
		$GLOBALS['robokassa_test_stream_callback'] = function ($url) use (&$smsRequests) {
			$smsRequests[] = $url;
			return '{"result":true}';
		};
		$handler = new ClassicResultNotification($this->database);

		$this->assertSame('OK42', $handler->process($this->request(), 'wc-custom-paid'));
		$this->assertSame('OK42', $handler->process($this->request(), 'wc-custom-paid'));
		$this->assertSame(array('active'), $subscription->statuses);
		$this->assertCount(1, $smsRequests);
		$this->assertStringContainsString('services.robokassa.ru/SMS/', $smsRequests[0]);
		$this->assertSame(1, $this->database->smsChecks);
		$this->assertSame(1, $this->database->smsInserts);
		$this->assertSame(1, $this->database->smsUpdates);
		$this->assertSame(1, $this->cart->calls);
	}
	public function testResumesAfterCartFailureWithoutRepeatingPaymentStatusOrNote(): void
	{
		$this->cart->fail = true;
		$subscription = new ResultSubscription();
		$GLOBALS['robokassa_test_subscriptions'] = array($subscription);
		$handler = new ClassicResultNotification($this->database);
		$this->assertSame(503, $handler->process($this->request(), 'wc-custom-paid')->get_error_data());
		$this->assertSame('', \wc_get_order(42)->get_meta(ClassicResultNotification::PROCESSED_META_KEY));
		$this->assertSame(array(), $subscription->statuses);
		$this->cart->fail = false;

		$this->assertSame('OK42', $handler->process($this->request(), 'wc-completed'));
		$state = $GLOBALS['robokassa_test_orders'][42];
		$this->assertSame(1, $state['payment_complete_calls']);
		$this->assertSame('custom-paid', $state['status']);
		$this->assertSame(array('Заказ успешно оплачен!'), $state['notes']);
		$this->assertSame(array('active'), $subscription->statuses);
		$this->assertSame(2, $this->cart->calls);
		$this->assertSame('OK42', $handler->process($this->request(), 'wc-completed'));
		$this->assertSame($state, $GLOBALS['robokassa_test_orders'][42]);
	}

	/** @dataProvider interruptedPaymentResults */
	public function testResumesAfterPaymentPersistedWithoutReplayingWooCommerceHooks(bool $throw): void
	{
		$GLOBALS['robokassa_test_after_payment_callback'] = function () use ($throw) {
			if ($throw) { throw new \RuntimeException('hook interrupted after payment'); }
			return false;
		};
		$handler = new ClassicResultNotification($this->database);
		$this->assertSame(503, $handler->process($this->request(), 'wc-custom-paid')->get_error_data());
		unset($GLOBALS['robokassa_test_after_payment_callback']);
		$this->assertSame('OK42', $handler->process($this->request(), 'wc-completed'));
		$order = \wc_get_order(42);
		$this->assertSame('custom-paid', $order->get_status());
		$this->assertSame(1, $GLOBALS['robokassa_test_orders'][42]['payment_complete_calls']);
		$this->assertTrue($order->get_meta(ClassicResultNotification::PROGRESS_META_KEY)['payment_recovered']);
		$this->assertCount(2, $GLOBALS['robokassa_test_orders'][42]['notes']);
		$this->assertSame('OK42', $handler->process($this->request(), 'wc-completed'));
		$this->assertCount(2, $GLOBALS['robokassa_test_orders'][42]['notes']);
	}

	public static function interruptedPaymentResults(): array
	{
		return array('false result' => array(false), 'exception' => array(true));
	}

	/** @dataProvider manualStatuses */
	public function testPreservesManualStatusWhileResumingAuxiliaryActions(string $status): void
	{
		$GLOBALS['robokassa_test_status_callback'] = function () { throw new \RuntimeException('status update unavailable'); };
		$handler = new ClassicResultNotification($this->database);
		$this->assertSame(503, $handler->process($this->request(), 'wc-custom-paid')->get_error_data());
		unset($GLOBALS['robokassa_test_status_callback']);
		$GLOBALS['robokassa_test_orders'][42]['status'] = $status;
		$GLOBALS['robokassa_test_orders'][42]['date_paid'] = null;
		$this->assertSame('OK42', $handler->process($this->request(), 'wc-completed'));
		$this->assertSame($status, \wc_get_order(42)->get_status());
		$this->assertSame(1, $GLOBALS['robokassa_test_orders'][42]['payment_complete_calls']);
	}

	public static function manualStatuses(): array
	{
		return array('cancelled' => array('cancelled'), 'refunded' => array('refunded'), 'custom' => array('manual-status'));
	}

	public function testRetriesFailedCustomStatusBeforeItsSave(): void
	{
		$GLOBALS['robokassa_test_status_callback'] = function () { throw new \RuntimeException('status failure'); };
		$handler = new ClassicResultNotification($this->database);
		$this->assertSame(503, $handler->process($this->request(), 'wc-custom-paid')->get_error_data());
		unset($GLOBALS['robokassa_test_status_callback']);
		$this->assertSame('OK42', $handler->process($this->request(), 'wc-completed'));
		$this->assertSame('custom-paid', \wc_get_order(42)->get_status());
		$this->assertSame(1, $GLOBALS['robokassa_test_orders'][42]['payment_complete_calls']);
	}

	public function testDoesNotDuplicateNoteAfterItsHookThrows(): void
	{
		$GLOBALS['robokassa_test_note_callback'] = function () { throw new \RuntimeException('note hook failed'); };
		$handler = new ClassicResultNotification($this->database);
		$this->assertSame(503, $handler->process($this->request(), 'wc-custom-paid')->get_error_data());
		unset($GLOBALS['robokassa_test_note_callback']);
		$this->assertSame('OK42', $handler->process($this->request(), 'wc-custom-paid'));
		$this->assertSame(array('Заказ успешно оплачен!'), $GLOBALS['robokassa_test_orders'][42]['notes']);
	}

	public function testFindsInsertedNoteEvenWhenItsMetadataWasNotSaved(): void
	{
		$GLOBALS['robokassa_test_note_callback'] = function () { throw new \RuntimeException('note hook failed'); };
		$handler = new ClassicResultNotification($this->database);
		$this->assertSame(503, $handler->process($this->request(), 'wc-processing')->get_error_data());
		unset($GLOBALS['robokassa_test_note_callback'], $GLOBALS['robokassa_test_orders'][42]['note_meta']);
		$this->assertSame('OK42', $handler->process($this->request(), 'wc-processing'));
		$this->assertCount(1, $GLOBALS['robokassa_test_orders'][42]['notes']);
	}

	public function testResumesSubscriptionListWithoutReactivatingSuccessfulSubscriptions(): void
	{
		$first = new ResultSubscription();
		$second = new ResultSubscription(); $second->id = 11; $second->fail = true;
		$GLOBALS['robokassa_test_subscriptions'] = array($first, $second);
		$handler = new ClassicResultNotification($this->database);
		$this->assertSame(503, $handler->process($this->request(), 'wc-custom-paid')->get_error_data());
		$this->assertSame(array('active'), $first->statuses);
		$this->assertSame(array(), $second->statuses);
		$second->fail = false;
		$this->assertSame('OK42', $handler->process($this->request(), 'wc-custom-paid'));
		$this->assertSame(array('active'), $first->statuses);
		$this->assertSame(array('active'), $second->statuses);
		$this->assertSame(1, $this->cart->calls);
	}

	public function testDoesNotReactivateSubscriptionWhenItsHookThrowsAfterSavingActiveStatus(): void
	{
		$subscription = new ResultSubscription(); $subscription->failAfterSave = true;
		$GLOBALS['robokassa_test_subscriptions'] = array($subscription);
		$handler = new ClassicResultNotification($this->database);
		$this->assertSame(503, $handler->process($this->request(), 'wc-processing')->get_error_data());
		$this->assertSame('OK42', $handler->process($this->request(), 'wc-processing'));
		$this->assertSame(array('active'), $subscription->statuses);
	}

	public function testDoesNotActivateSubscriptionAfterParentWasCancelledDuringRecovery(): void
	{
		$subscription = new ResultSubscription(); $subscription->fail = true;
		$GLOBALS['robokassa_test_subscriptions'] = array($subscription);
		$handler = new ClassicResultNotification($this->database);
		$this->assertSame(503, $handler->process($this->request(), 'wc-processing')->get_error_data());
		$GLOBALS['robokassa_test_orders'][42]['status'] = 'cancelled';
		$this->assertSame('OK42', $handler->process($this->request(), 'wc-processing'));
		$this->assertSame(array(), $subscription->statuses);
		$this->assertSame('cancelled', \wc_get_order(42)->get_status());
	}

	public function testMissingPlannedSubscriptionPreventsAcknowledgementUntilItIsAvailable(): void
	{
		$subscription = new ResultSubscription();
		$GLOBALS['robokassa_test_subscriptions'] = array($subscription);
		$this->cart->fail = true;
		$handler = new ClassicResultNotification($this->database);
		$this->assertSame(503, $handler->process($this->request(), 'wc-processing')->get_error_data());
		$this->cart->fail = false;
		$GLOBALS['robokassa_test_subscriptions'] = array();
		$this->assertSame(503, $handler->process($this->request(), 'wc-processing')->get_error_data());
		$this->assertSame('', \wc_get_order(42)->get_meta(ClassicResultNotification::PROCESSED_META_KEY));
		$GLOBALS['robokassa_test_subscriptions'] = array($subscription);
		$this->assertSame('OK42', $handler->process($this->request(), 'wc-processing'));
		$this->assertSame(array('active'), $subscription->statuses);
	}

	public function testSupportsRenewalOrderAndPreservesCancelledSubscription(): void
	{
		$subscription = new ResultSubscription(); $subscription->status = 'cancelled';
		$GLOBALS['robokassa_test_renewal_subscriptions'] = array($subscription);
		$this->assertSame('OK42', (new ClassicResultNotification($this->database))->process($this->request(), 'wc-processing'));
		$this->assertSame(array(), $subscription->statuses);
		$this->assertTrue(\wc_get_order(42)->get_meta(ClassicResultNotification::PROGRESS_META_KEY)['subscriptions'][10]);
	}

	/** @dataProvider smsFailures */
	public function testUnconfirmedSmsProducesOneWarningWithoutAutomaticResend(bool $throw): void
	{
		$GLOBALS['wpdb'] = $this->database;
		$this->setOptions(array('robokassa_payment_sms1_enabled' => 'on'));
		$calls = 0;
		$GLOBALS['robokassa_test_stream_callback'] = function () use (&$calls, $throw) {
			$calls++;
			if ($throw) { throw new \RuntimeException('unknown SMS outcome'); }
			return '{"result":0}';
		};
		$handler = new ClassicResultNotification($this->database);
		$this->assertSame('OK42', $handler->process($this->request(), 'wc-processing'));
		$this->assertSame('OK42', $handler->process($this->request(), 'wc-processing'));
		$this->assertSame(1, $calls);
		$this->assertCount(2, $GLOBALS['robokassa_test_orders'][42]['notes']);
		$this->assertSame($throw ? 'uncertain' : 'failed', \wc_get_order(42)->get_meta(ClassicResultNotification::PROGRESS_META_KEY)['sms']);
	}

	public static function smsFailures(): array { return array('rejected' => array(false), 'unknown' => array(true)); }

	public function testDoesNotRepeatSmsWhenProgressSaveFailsAfterSending(): void
	{
		$GLOBALS['wpdb'] = $this->database;
		$this->setOptions(array('robokassa_payment_sms1_enabled' => 'on'));
		$calls = 0;
		$GLOBALS['robokassa_test_stream_callback'] = function () use (&$calls) { $calls++; return '{"result":1}'; };
		$GLOBALS['robokassa_test_meta_save_callback'] = function ($order) {
			if (($order->get_meta(ClassicResultNotification::PROGRESS_META_KEY)['sms'] ?? '') === 'sent') {
				throw new \RuntimeException('save failed after SMS');
			}
		};
		$handler = new ClassicResultNotification($this->database);
		$this->assertSame(503, $handler->process($this->request(), 'wc-processing')->get_error_data());
		unset($GLOBALS['robokassa_test_meta_save_callback']);
		$this->assertSame('OK42', $handler->process($this->request(), 'wc-processing'));
		$this->assertSame(1, $calls);
		$this->assertCount(2, $GLOBALS['robokassa_test_orders'][42]['notes']);
	}

	/** @dataProvider progressFailures */
	public function testResumesWhenSavingStageOrCompletionFails(string $stage): void
	{
		$subscription = new ResultSubscription();
		$GLOBALS['robokassa_test_subscriptions'] = array($subscription);
		$GLOBALS['robokassa_test_meta_save_callback'] = function ($order) use ($stage) {
			$progress = $order->get_meta(ClassicResultNotification::PROGRESS_META_KEY);
			$fail = $stage === 'processed' ? $order->get_meta(ClassicResultNotification::PROCESSED_META_KEY) === 'yes'
				: ($stage === 'subscription' ? !empty($progress['subscriptions'][10]) : !empty($progress['done'][$stage]));
			if ($fail) { throw new \RuntimeException('stage save failed'); }
		};
		$handler = new ClassicResultNotification($this->database);
		$this->assertSame(503, $handler->process($this->request(), 'wc-custom-paid')->get_error_data());
		unset($GLOBALS['robokassa_test_meta_save_callback']);
		$this->assertSame('OK42', $handler->process($this->request(), 'wc-completed'));
		$this->assertSame(1, $GLOBALS['robokassa_test_orders'][42]['payment_complete_calls']);
		$this->assertSame('custom-paid', \wc_get_order(42)->get_status());
		$this->assertSame(1, $this->cart->calls);
		$this->assertSame(array('active'), $subscription->statuses);
		$this->assertSame(array('Заказ успешно оплачен!'), array_values(array_filter($GLOBALS['robokassa_test_orders'][42]['notes'], function ($note) { return $note === 'Заказ успешно оплачен!'; })));
	}

	public static function progressFailures(): array
	{
		return array('payment' => array('payment'), 'status' => array('status'), 'note' => array('note'), 'cart' => array('cart'), 'subscription' => array('subscription'), 'completion' => array('processed'));
	}

}

class ResultSubscription
{
	public $statuses = array();
	public $status = 'pending';
	public $id = 10;
	public $fail = false;
	public $failAfterSave = false;

	public function get_id() { return $this->id; }
	public function get_status() { return $this->status; }

	public function update_status($status)
	{
		if ($this->fail) { throw new \RuntimeException('subscription before save'); }
		$this->statuses[] = $status;
		$this->status = $status;
		if ($this->failAfterSave) { throw new \RuntimeException('subscription after save'); }
	}
}

class ResultCart
{
	public $calls = 0;
	public $empty = false;
	public $fail = false;

	public function is_empty() { return $this->empty; }

	public function empty_cart()
	{
		$this->calls++;
		if ($this->fail) { throw new \RuntimeException('cart failure'); }
		$this->empty = true;
	}
}

class ResultLockDatabase extends \wpdb
{
	public $queries = array();
	public $lockResult = '1';
	public $releases = 0;
	public $onLock;
	public $smsChecks = 0;
	public $smsInserts = 0;
	public $smsUpdates = 0;

	public function prepare($sql, ...$args)
	{
		if (count($args) === 1 && is_array($args[0])) {
			$args = $args[0];
		}
		return preg_replace_callback('/%[sd]/', function ($match) use (&$args) {
			$value = array_shift($args);
			return $match[0] === '%d' ? (string)(int)$value : "'" . str_replace("'", "''", $value) . "'";
		}, $sql);
	}

	public function get_var($sql)
	{
		$this->queries[] = $sql;
		if (strpos($sql, 'SELECT COUNT') !== false) {
			$this->smsChecks++;
			return '0';
		}
		if (strpos($sql, 'GET_LOCK') !== false) {
			if ($this->onLock) {
				call_user_func($this->onLock);
			}
			return $this->lockResult;
		}
		$this->releases++;
		return '1';
	}

	public function insert($table, $data, $format = null)
	{
		$this->smsInserts++;
		return 1;
	}

	public function update($table, $data, $where, $format = null, $whereFormat = null)
	{
		$this->smsUpdates++;
		return 1;
	}
}
