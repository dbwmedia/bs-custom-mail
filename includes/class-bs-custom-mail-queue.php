<?php

/**
 * Asynchronous order processing queue.
 *
 * @since      3.0.0
 *
 * @package    Bs_Custom_Mail
 * @subpackage Bs_Custom_Mail/includes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Central queue for all post-checkout work.
 *
 * The checkout request must never do heavy work. Coupon creation, PDF
 * rendering and email delivery all happen in a separate, asynchronous request
 * so that a failure in any of them can no longer block the visible order
 * confirmation or prevent WooCommerce from emptying the cart.
 *
 * Delivery guarantees:
 *  - Normal case: the async job runs within seconds of the status change.
 *  - Failure case: retries with exponential backoff (1/5/15 minutes).
 *  - Hard crashes (fatal error, memory exhausted) count as an attempt too:
 *    the counter is persisted BEFORE the work starts, so a deterministic
 *    crash stops after MAX_ATTEMPTS instead of looping forever.
 *  - Last resort: a recurring sweep picks up orders whose job was never even
 *    queued (e.g. the original request died before the status hook ran).
 *  - Nothing fails silently: after the final attempt an incident is recorded
 *    and the shop owner is alerted.
 *
 * @since      3.0.0
 * @package    Bs_Custom_Mail
 * @subpackage Bs_Custom_Mail/includes
 */
class Bs_Custom_Mail_Queue {

	/**
	 * Action hook for the per-order worker job.
	 */
	const HOOK_ORDER = 'bs_custom_mail_process_order';

	/**
	 * Action hook for a manual "resend the booking mail" job (mail step only).
	 */
	const HOOK_RESEND = 'bs_custom_mail_resend_order_mail';

	/**
	 * WooCommerce order action key for the resend button.
	 */
	const ORDER_ACTION_RESEND = 'bs_custom_mail_resend_mail';

	/**
	 * Action hook for the recurring backstop sweep.
	 */
	const HOOK_SWEEP = 'bs_custom_mail_safety_net_sweep';

	/**
	 * Legacy per-order hook from version 2.1.0.
	 *
	 * Actions scheduled by the previous release may still sit in the queue when
	 * this version is deployed. They are routed into the new worker so no
	 * pending order is dropped during the upgrade.
	 */
	const HOOK_LEGACY = 'bs_custom_mail_safety_net_check';

	/**
	 * Action hook for the voucher PDF housekeeping job.
	 */
	const HOOK_CLEANUP = 'bs_custom_mail_cleanup_pdfs';

	/**
	 * Action Scheduler group.
	 */
	const GROUP = 'bs-custom-mail';

	/**
	 * Order meta: processing state ("pending", "done", "failed").
	 */
	const META_STATE = '_bs_cm_state';

	/**
	 * Order meta: number of completed worker attempts.
	 */
	const META_ATTEMPTS = '_bs_cm_attempts';

	/**
	 * Order meta: unix timestamp of the first enqueue.
	 */
	const META_QUEUED_AT = '_bs_cm_queued_at';

	/**
	 * Order meta: unix timestamp of the most recent attempt.
	 */
	const META_LAST_ATTEMPT = '_bs_cm_last_attempt';

	/**
	 * Order meta: set when an admin closed the order by hand ("Erledigt").
	 */
	const META_RESOLVED_BY_HAND = '_bs_cm_manually_resolved';

	/**
	 * Option prefix for the last fatal error of an order's job.
	 *
	 * Stored as a plain option rather than order meta: it is written from a
	 * shutdown handler after a fatal, where loading and saving a WC_Order is
	 * the last thing we want to depend on.
	 */
	const FATAL_OPTION_PREFIX = '_bs_cm_fatal_';

	const STATE_PENDING = 'pending';
	const STATE_DONE    = 'done';
	const STATE_FAILED  = 'failed';

	/**
	 * Seconds after which a held lock is considered abandoned.
	 */
	const LOCK_TTL = 300;

	/**
	 * Number of worker attempts before the order is declared failed.
	 *
	 * Crashed attempts count. Incident #4673 (09/2026) showed a deterministic
	 * memory fatal being retried ~110 times a day because a crash never
	 * reached the attempt counter.
	 */
	const MAX_ATTEMPTS = 3;

	/**
	 * Retry delays in seconds, indexed by the number of failed attempts.
	 */
	const BACKOFF = array( 60, 300, 900 );

	/**
	 * How far back the sweep looks for unsettled orders.
	 */
	const SWEEP_WINDOW = 3 * DAY_IN_SECONDS;

	/**
	 * Order currently being processed in this request (for the shutdown handler).
	 *
	 * @var int
	 */
	private static $current_order = 0;

	/**
	 * Pipeline step currently running in this request (for the shutdown handler).
	 *
	 * @var string
	 */
	private static $current_step = '';

	/**
	 * Memory held back so the shutdown handler can still work after a
	 * "memory exhausted" fatal.
	 *
	 * @var string|null
	 */
	private static $memory_reserve = null;

	/**
	 * Whether the shutdown handler is registered in this request.
	 *
	 * @var bool
	 */
	private static $shutdown_registered = false;

	/**
	 * Order statuses that must never be processed.
	 *
	 * Everything else is processed: an order may well have moved on from the
	 * trigger status (processing -> completed) before the worker gets to run,
	 * and that must not cost the customer their voucher.
	 */
	const BLOCKED_STATUSES = array( 'cancelled', 'refunded', 'failed', 'pending', 'checkout-draft', 'trash', 'draft' );

	/**
	 * Voucher component.
	 *
	 * @var Bs_Custom_Mail_Voucher
	 */
	private $voucher;

	/**
	 * Email component.
	 *
	 * @var Bs_Custom_Mail_Email_Sender
	 */
	private $email_sender;

	/**
	 * Constructor.
	 *
	 * @param Bs_Custom_Mail_Voucher      $voucher      Voucher component.
	 * @param Bs_Custom_Mail_Email_Sender $email_sender Email component.
	 */
	public function __construct( $voucher, $email_sender ) {
		$this->voucher      = $voucher;
		$this->email_sender = $email_sender;
	}

	/**
	 * Register all queue related hooks.
	 */
	public function register_hooks() {
		// Phase A: the only thing the checkout request still does.
		add_action( 'woocommerce_order_status_changed', array( $this, 'maybe_enqueue_order' ), 10, 3 );

		// Phase B: the worker.
		add_action( self::HOOK_ORDER, array( $this, 'run_order_job' ), 10, 1 );
		add_action( self::HOOK_LEGACY, array( $this, 'run_order_job' ), 10, 1 );

		// Manual resend of the booking mail from the order screen.
		add_filter( 'woocommerce_order_actions', array( $this, 'add_order_actions' ), 10, 1 );
		add_action( 'woocommerce_order_action_' . self::ORDER_ACTION_RESEND, array( $this, 'handle_resend_order_action' ), 10, 1 );
		add_action( self::HOOK_RESEND, array( $this, 'run_resend_job' ), 10, 1 );

		// Phase C: backstop.
		add_action( self::HOOK_SWEEP, array( $this, 'run_sweep' ), 10, 0 );
		add_action( self::HOOK_CLEANUP, array( $this, 'run_cleanup' ), 10, 0 );
		add_action( 'init', array( $this, 'ensure_recurring_actions' ) );
	}

	/**
	 * The configured trigger status, validated against the real status list.
	 *
	 * A typo in the setting used to silence the entire mail pipeline without
	 * any visible error. An unknown status now falls back to "processing".
	 *
	 * @return string Status slug without the "wc-" prefix.
	 */
	public static function get_trigger_status() {
		$status = (string) get_option( 'bs_custom_mail_trigger_status', 'processing' );
		$status = ltrim( trim( $status ), '#' );
		$status = preg_replace( '/^wc-/', '', $status );

		if ( function_exists( 'wc_get_order_statuses' ) ) {
			$valid = array_map(
				function ( $key ) {
					return preg_replace( '/^wc-/', '', $key );
				},
				array_keys( wc_get_order_statuses() )
			);

			if ( ! in_array( $status, $valid, true ) ) {
				return 'processing';
			}
		}

		return $status ? $status : 'processing';
	}

	/**
	 * Statuses that cause an order to be queued.
	 *
	 * Besides the configured trigger status, both "processing" and "completed"
	 * queue the order. Virtual products (a gift voucher is virtual) can jump
	 * straight from "pending" to "completed" without ever passing through
	 * "processing" — under the old logic those orders never got a confirmation
	 * mail at all. Double processing is impossible because the worker is
	 * idempotent.
	 *
	 * @return array
	 */
	public static function get_queue_statuses() {
		$statuses = array( self::get_trigger_status(), 'processing', 'completed' );

		/**
		 * Filter the order statuses that enqueue post-checkout processing.
		 *
		 * @since 3.0.0
		 * @param array $statuses Status slugs.
		 */
		return array_values( array_unique( (array) apply_filters( 'bs_custom_mail_queue_statuses', $statuses ) ) );
	}

	/**
	 * Order status listener — enqueues the worker and returns immediately.
	 *
	 * This runs inside the checkout (or webhook) request and is deliberately
	 * the only plugin code that does. It is wrapped in a catch-all so that not
	 * even a fatal error in the scheduler can take down the payment request.
	 *
	 * @param int    $order_id   Order ID.
	 * @param string $old_status Previous status.
	 * @param string $new_status New status.
	 */
	public function maybe_enqueue_order( $order_id, $old_status, $new_status ) {
		try {
			if ( ! in_array( $new_status, self::get_queue_statuses(), true ) ) {
				return;
			}

			$this->enqueue_order( (int) $order_id );
		} catch ( \Throwable $e ) {
			// Never let queueing break the checkout. The sweep will pick the
			// order up within minutes.
			Bs_Custom_Mail_Health::log(
				sprintf( 'Einplanen für Bestellung #%d fehlgeschlagen: %s', $order_id, $e->getMessage() ),
				'error'
			);
		}
	}

	/**
	 * Queue the worker job for an order.
	 *
	 * @param int $order_id Order ID.
	 * @param int $delay    Delay in seconds. 0 runs as soon as possible.
	 * @return bool Whether a job is now queued.
	 */
	public function enqueue_order( $order_id, $delay = 0 ) {
		$order_id = (int) $order_id;

		if ( ! $order_id ) {
			return false;
		}

		$args = array( $order_id );

		// Already queued — nothing to do.
		if ( function_exists( 'as_next_scheduled_action' ) ) {
			if ( false !== as_next_scheduled_action( self::HOOK_ORDER, $args, self::GROUP ) ) {
				return true;
			}
		}

		if ( $delay <= 0 && function_exists( 'as_enqueue_async_action' ) ) {
			as_enqueue_async_action( self::HOOK_ORDER, $args, self::GROUP );
			$this->stamp_queued( $order_id );
			return true;
		}

		if ( function_exists( 'as_schedule_single_action' ) ) {
			as_schedule_single_action( time() + max( 0, (int) $delay ), self::HOOK_ORDER, $args, self::GROUP );
			$this->stamp_queued( $order_id );
			return true;
		}

		// Action Scheduler missing (WooCommerce inactive or broken) — WP-Cron.
		if ( ! wp_next_scheduled( self::HOOK_ORDER, $args ) ) {
			wp_schedule_single_event( time() + max( 1, (int) $delay ), self::HOOK_ORDER, $args );
			$this->stamp_queued( $order_id );
		}

		return true;
	}

	/**
	 * Record when an order first entered the queue (used by the SLA watchdog).
	 *
	 * @param int $order_id Order ID.
	 */
	private function stamp_queued( $order_id ) {
		$order = wc_get_order( $order_id );

		if ( ! $order || $order->get_meta( self::META_QUEUED_AT ) ) {
			return;
		}

		$order->update_meta_data( self::META_QUEUED_AT, time() );
		$order->update_meta_data( self::META_STATE, self::STATE_PENDING );

		// save_meta_data() rather than save(): this runs inside the checkout
		// request, and writing only the meta avoids a full order save (and the
		// hook cascade that comes with it) at the most sensitive moment.
		$order->save_meta_data();
	}

	/**
	 * Action Scheduler / WP-Cron callback: process one order.
	 *
	 * @param mixed $order_id Order ID (int, or the legacy associative payload).
	 */
	public function run_order_job( $order_id = 0 ) {
		// The 2.1.0 hook passed array( 'order_id' => X ).
		if ( is_array( $order_id ) ) {
			$order_id = isset( $order_id['order_id'] ) ? $order_id['order_id'] : reset( $order_id );
		}

		$this->process_order( (int) $order_id, 'job' );
	}

	/**
	 * Run the full post-checkout pipeline for one order.
	 *
	 * Idempotent and safe to call repeatedly. Guarded by a database level lock
	 * so that the worker, a retry and the sweep can never run concurrently for
	 * the same order — which is what could otherwise produce two live coupons
	 * for a single purchase.
	 *
	 * @param int    $order_id Order ID.
	 * @param string $context  Where the call came from, for logging.
	 * @return bool Whether the order is fully settled.
	 */
	public function process_order( $order_id, $context = 'job' ) {
		$order_id = (int) $order_id;

		if ( ! $order_id ) {
			return false;
		}

		$order = wc_get_order( $order_id );

		if ( ! $order ) {
			return false;
		}

		if ( self::STATE_DONE === $order->get_meta( self::META_STATE ) ) {
			return true;
		}

		if ( in_array( $order->get_status(), self::BLOCKED_STATUSES, true ) ) {
			// Cancelled/refunded orders are settled by definition.
			$order->update_meta_data( self::META_STATE, self::STATE_DONE );
			$order->save();
			return true;
		}

		if ( self::STATE_FAILED === $order->get_meta( self::META_STATE ) && 'manual' !== $context ) {
			// Escalated already; only an explicit retry may run it again.
			return false;
		}

		if ( ! $this->acquire_lock( $order_id ) ) {
			// Another process owns this order right now. Come back shortly
			// rather than risk a parallel run.
			$this->enqueue_order( $order_id, 60 );
			return false;
		}

		// Count the attempt BEFORE doing any work. A fatal error below never
		// returns to schedule_retry(), so this is the only place where a
		// crashed attempt can be counted at all.
		$attempts = (int) $order->get_meta( self::META_ATTEMPTS );

		if ( $attempts >= self::MAX_ATTEMPTS ) {
			$this->release_lock( $order_id );
			$this->give_up( $order_id, __( 'Verarbeitung', 'bs-custom-mail' ) );
			return false;
		}

		$order->update_meta_data( self::META_ATTEMPTS, $attempts + 1 );
		$order->update_meta_data( self::META_LAST_ATTEMPT, time() );
		$order->save_meta_data();

		$this->begin_crash_guard( $order_id );

		$vouchers_ok = false;
		$emails_ok   = false;

		try {
			self::set_step( 'voucher' );
			$vouchers_ok = $this->voucher->process_order_vouchers( $order_id, $context );
			self::set_step( 'customer_mail' );
			$emails_ok = $this->email_sender->maybe_send_order_emails( $order_id );
		} catch ( \Throwable $e ) {
			self::store_error(
				$order_id,
				array(
					'step' => self::$current_step,
					'msg'  => $e->getMessage(),
					'file' => basename( $e->getFile() ) . ':' . $e->getLine(),
				)
			);
			Bs_Custom_Mail_Health::log(
				sprintf( 'Verarbeitung von Bestellung #%d abgebrochen: %s', $order_id, $e->getMessage() ),
				'error'
			);
		} finally {
			$this->end_crash_guard();
			$this->release_lock( $order_id );
		}

		if ( $vouchers_ok && $emails_ok ) {
			$this->mark_done( $order_id, $context );
			return true;
		}

		$this->schedule_retry( $order_id, $vouchers_ok, $emails_ok );

		return false;
	}

	/**
	 * Mark an order as fully processed.
	 *
	 * @param int    $order_id Order ID.
	 * @param string $context  Calling context.
	 */
	private function mark_done( $order_id, $context ) {
		$order = wc_get_order( $order_id );

		if ( ! $order ) {
			return;
		}

		// The attempt of this very run is already counted.
		$attempts = max( 0, (int) $order->get_meta( self::META_ATTEMPTS ) - 1 );

		$order->update_meta_data( self::META_STATE, self::STATE_DONE );
		$order->save();

		Bs_Custom_Mail_Health::resolve_incidents( $order_id );
		self::clear_error( $order_id );

		if ( $attempts > 0 || 'job' !== $context ) {
			$queued_at = (int) $order->get_meta( self::META_QUEUED_AT );
			$latency   = $queued_at ? human_time_diff( $queued_at, time() ) : '-';

			$order->add_order_note(
				sprintf(
					/* translators: 1: context, 2: attempt count, 3: latency */
					__( 'Gutschein/Bestätigung nachträglich zugestellt (Quelle: %1$s, Versuche: %2$d, Verzögerung: %3$s).', 'bs-custom-mail' ),
					$context,
					$attempts + 1,
					$latency
				)
			);

			Bs_Custom_Mail_Health::log(
				sprintf(
					'RECOVERED: Bestellung #%d wurde über "%s" nach %d Versuch(en) vollständig abgeschlossen (Verzögerung: %s).',
					$order_id,
					$context,
					$attempts + 1,
					$latency
				),
				'warning'
			);
		}
	}

	/**
	 * Count a failed attempt and either retry or escalate.
	 *
	 * @param int  $order_id    Order ID.
	 * @param bool $vouchers_ok Whether voucher generation succeeded.
	 * @param bool $emails_ok   Whether email delivery succeeded.
	 */
	private function schedule_retry( $order_id, $vouchers_ok, $emails_ok ) {
		$order = wc_get_order( $order_id );

		if ( ! $order ) {
			return;
		}

		// Already incremented at the start of the attempt.
		$attempts = (int) $order->get_meta( self::META_ATTEMPTS );

		$what = array();
		if ( ! $vouchers_ok ) {
			$what[] = 'Gutschein/Gutschein-Mail';
		}
		if ( ! $emails_ok ) {
			$what[] = 'Bestätigungs-Mail';
		}
		$what = implode( ' + ', $what );

		if ( $attempts >= self::MAX_ATTEMPTS ) {
			$this->give_up( $order_id, $what );
			return;
		}

		$index = min( $attempts - 1, count( self::BACKOFF ) - 1 );
		$delay = self::BACKOFF[ $index ];

		$order->update_meta_data( self::META_STATE, self::STATE_PENDING );
		$order->save();

		Bs_Custom_Mail_Health::log(
			sprintf(
				'Bestellung #%d: %s fehlgeschlagen (Versuch %d/%d). Neuer Versuch in %d s.',
				$order_id,
				$what,
				$attempts,
				self::MAX_ATTEMPTS,
				$delay
			),
			'warning'
		);

		$this->enqueue_order( $order_id, $delay );
	}

	/**
	 * Stop retrying an order: mark it failed, raise ONE incident, then stay quiet.
	 *
	 * @param int    $order_id Order ID.
	 * @param string $what     Human readable description of the failing part.
	 */
	private function give_up( $order_id, $what ) {
		$order = wc_get_order( $order_id );

		if ( ! $order ) {
			return;
		}

		$attempts = (int) $order->get_meta( self::META_ATTEMPTS );

		$order->update_meta_data( self::META_STATE, self::STATE_FAILED );
		$order->save();

		$message = sprintf(
			/* translators: 1: failing step, 2: attempt count */
			__( '%1$s nach %2$d Versuchen aufgegeben. Es wird nicht mehr automatisch wiederholt.', 'bs-custom-mail' ),
			$what,
			$attempts
		);

		$cause = self::describe_error( self::get_error( $order_id ) );
		if ( $cause ) {
			$message .= ' ' . sprintf(
				/* translators: %s: error description */
				__( 'Letzter Fehler: %s.', 'bs-custom-mail' ),
				$cause
			);
		}

		// The SLA incident is superseded by this one.
		Bs_Custom_Mail_Health::resolve_incidents( $order_id );
		Bs_Custom_Mail_Health::record_incident( $order_id, 'delivery_failed', $message );
	}

	/**
	 * Name the step currently running, so a fatal can be attributed to it.
	 *
	 * @param string $step Step key (voucher, customer_mail, invoice_pdf, ...).
	 */
	public static function set_step( $step ) {
		self::$current_step = (string) $step;
	}

	/**
	 * Arm the shutdown handler that records a fatal error for this order.
	 *
	 * @param int $order_id Order ID.
	 */
	private function begin_crash_guard( $order_id ) {
		self::$current_order  = (int) $order_id;
		self::$current_step   = '';
		self::$memory_reserve = str_repeat( ' ', 1024 * 1024 );

		if ( ! self::$shutdown_registered ) {
			register_shutdown_function( array( __CLASS__, 'handle_shutdown' ) );
			self::$shutdown_registered = true;
		}
	}

	/**
	 * Disarm the shutdown handler after the job returned normally.
	 */
	private function end_crash_guard() {
		self::$current_order  = 0;
		self::$current_step   = '';
		self::$memory_reserve = null;
	}

	/**
	 * Shutdown handler: persist the cause of a fatal that killed the job.
	 */
	public static function handle_shutdown() {
		self::$memory_reserve = null;

		if ( ! self::$current_order ) {
			return;
		}

		$error = error_get_last();

		if ( ! $error || ! in_array( $error['type'], array( E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR ), true ) ) {
			return;
		}

		self::store_error(
			self::$current_order,
			array(
				'step' => self::$current_step,
				'msg'  => $error['message'],
				'file' => basename( $error['file'] ) . ':' . $error['line'],
				'peak' => memory_get_peak_usage( true ),
			)
		);
	}

	/**
	 * Persist the last error of an order's job.
	 *
	 * @param int   $order_id Order ID.
	 * @param array $error    step, msg, file, peak.
	 */
	private static function store_error( $order_id, $error ) {
		global $wpdb;

		$error['msg'] = function_exists( 'mb_substr' ) ? mb_substr( (string) $error['msg'], 0, 300 ) : substr( (string) $error['msg'], 0, 300 );
		$error['at']  = time();

		// Direct query: cheap, no object cache, safe inside a shutdown handler.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query(
			$wpdb->prepare(
				"REPLACE INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, %s, 'no')",
				self::FATAL_OPTION_PREFIX . (int) $order_id,
				wp_json_encode( $error )
			)
		);
	}

	/**
	 * Read the last recorded error of an order's job.
	 *
	 * @param int $order_id Order ID.
	 * @return array|null
	 */
	public static function get_error( $order_id ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$raw = $wpdb->get_var(
			$wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", self::FATAL_OPTION_PREFIX . (int) $order_id )
		);

		$error = $raw ? json_decode( $raw, true ) : null;

		return is_array( $error ) ? $error : null;
	}

	/**
	 * Forget the recorded error of an order.
	 *
	 * @param int $order_id Order ID.
	 */
	private static function clear_error( $order_id ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->delete( $wpdb->options, array( 'option_name' => self::FATAL_OPTION_PREFIX . (int) $order_id ), array( '%s' ) );
	}

	/**
	 * Human readable one-liner for a stored error.
	 *
	 * @param array|null $error Stored error.
	 * @return string Empty when there is nothing to say.
	 */
	public static function describe_error( $error ) {
		if ( empty( $error['msg'] ) ) {
			return '';
		}

		$steps = array(
			'voucher'       => __( 'Gutschein', 'bs-custom-mail' ),
			'customer_mail' => __( 'Kundenmail', 'bs-custom-mail' ),
			'invoice_pdf'   => __( 'Rechnungs-PDF', 'bs-custom-mail' ),
		);

		$msg = false !== stripos( $error['msg'], 'Allowed memory size' )
			? __( 'Speicher voll', 'bs-custom-mail' )
			: $error['msg'];

		$parts = array( $msg );

		if ( ! empty( $error['file'] ) ) {
			$parts[] = sprintf( /* translators: %s: file:line */ __( 'in %s', 'bs-custom-mail' ), $error['file'] );
		}

		if ( ! empty( $error['step'] ) ) {
			$parts[] = '(' . ( isset( $steps[ $error['step'] ] ) ? $steps[ $error['step'] ] : $error['step'] ) . ')';
		}

		return implode( ' ', $parts );
	}

	/**
	 * Oldest order creation time the sweep may look at.
	 *
	 * The window is SWEEP_WINDOW, but never older than 24 h before this
	 * version first ran. Up to 3.0.0 the window was 24 h; without this floor
	 * the first sweep after the update would suddenly pick up orders from the
	 * two days before, including ones that were already settled by hand
	 * (#4673 got its mail manually on 30.09.2026 and must not get it twice).
	 *
	 * @return int Unix timestamp.
	 */
	private static function sweep_since() {
		$floor = (int) get_option( 'bs_custom_mail_sweep_floor', 0 );

		if ( ! $floor ) {
			$floor = time() - DAY_IN_SECONDS;
			add_option( 'bs_custom_mail_sweep_floor', $floor, '', false );
		}

		return max( time() - self::SWEEP_WINDOW, $floor );
	}

	/**
	 * Recurring backstop: catch orders whose job was never queued or never ran.
	 *
	 * Deliberately queries without a meta_query so the same code path works
	 * identically on legacy post storage and on HPOS.
	 */
	public function run_sweep() {
		if ( ! function_exists( 'wc_get_orders' ) ) {
			return;
		}

		$orders = wc_get_orders(
			array(
				'status'       => self::get_queue_statuses(),
				'limit'        => 50,
				'orderby'      => 'date',
				'order'        => 'DESC',
				'date_created' => '>' . self::sweep_since(),
			)
		);

		if ( empty( $orders ) ) {
			return;
		}

		foreach ( $orders as $order ) {
			if ( ! $order instanceof WC_Order ) {
				continue;
			}

			$state = $order->get_meta( self::META_STATE );

			if ( self::STATE_DONE === $state ) {
				continue;
			}

			$order_id = $order->get_id();

			// SLA watchdog: an order that has been sitting unprocessed for
			// longer than the alert threshold is reported even if the sweep
			// manages to heal it a moment later — a near miss is a signal.
			//
			// The age is measured from the moment the order entered THIS
			// pipeline, not from when it was created. Orders that predate the
			// plugin update carry no queue stamp; they are picked up and
			// settled silently instead of raising a burst of false alarms on
			// the first sweep after a deployment.
			$queued_at = (int) $order->get_meta( self::META_QUEUED_AT );

			/**
			 * Filter the age (in seconds) after which an unprocessed order raises an alert.
			 *
			 * @since 3.0.0
			 * @param int $threshold Seconds. Default 30 minutes.
			 */
			$threshold = (int) apply_filters( 'bs_custom_mail_sla_threshold', 30 * MINUTE_IN_SECONDS );

			if ( $queued_at && ( time() - $queued_at ) > $threshold && self::STATE_FAILED !== $state ) {
				Bs_Custom_Mail_Health::record_incident(
					$order_id,
					'sla_breach',
					sprintf(
						/* translators: 1: how long the order has been waiting */
						__( 'Bestellung wartet seit %s auf die vollständige Verarbeitung. Sicherheitsnetz greift.', 'bs-custom-mail' ),
						human_time_diff( $queued_at, time() )
					)
				);
			}

			if ( self::STATE_FAILED === $state ) {
				// Escalated already; a human has to look at it.
				continue;
			}

			$this->process_order( $order_id, 'sweep' );
		}
	}

	/**
	 * Housekeeping: delete voucher PDFs that are no longer needed.
	 */
	public function run_cleanup() {
		if ( ! class_exists( 'Bs_Custom_Mail_PDF_Generator' ) ) {
			require_once plugin_dir_path( __FILE__ ) . 'class-bs-custom-mail-pdf-generator.php';
		}

		/**
		 * Filter how many days generated voucher PDFs are kept on disk.
		 *
		 * @since 3.0.0
		 * @param int $days Retention in days. Default 90.
		 */
		$days = (int) apply_filters( 'bs_custom_mail_pdf_retention_days', 90 );

		$generator = new Bs_Custom_Mail_PDF_Generator();
		$generator->cleanup_old_pdfs( $days );
	}

	/**
	 * Make sure the recurring sweep and cleanup jobs exist. Idempotent.
	 */
	public function ensure_recurring_actions() {
		if ( ! function_exists( 'as_schedule_recurring_action' ) || ! function_exists( 'as_next_scheduled_action' ) ) {
			return;
		}

		$this->dedupe_recurring_actions();

		if ( false === as_next_scheduled_action( self::HOOK_SWEEP, array(), self::GROUP ) ) {
			/**
			 * Filter the interval (in seconds) of the recurring safety-net sweep.
			 *
			 * @since 2.1.0
			 * @param int $interval Interval in seconds. Default 10 minutes.
			 */
			$interval = (int) apply_filters( 'bs_custom_mail_safety_net_sweep_interval', 10 * MINUTE_IN_SECONDS );
			// $unique = true: two concurrent requests on "init" used to both
			// see "nothing scheduled" and each add a sweep.
			as_schedule_recurring_action( time() + 60, $interval, self::HOOK_SWEEP, array(), self::GROUP, true );
		}

		if ( false === as_next_scheduled_action( self::HOOK_CLEANUP, array(), self::GROUP ) ) {
			as_schedule_recurring_action( time() + HOUR_IN_SECONDS, DAY_IN_SECONDS, self::HOOK_CLEANUP, array(), self::GROUP, true );
		}
	}

	/**
	 * Remove duplicate recurring actions left behind by earlier versions.
	 *
	 * Checked at most once an hour; cheap when there is nothing to do. Runs
	 * before scheduling, so whatever it removes is re-created right after.
	 */
	private function dedupe_recurring_actions() {
		if ( ! function_exists( 'as_get_scheduled_actions' ) || ! function_exists( 'as_unschedule_all_actions' ) ) {
			return;
		}

		if ( get_transient( 'bs_custom_mail_dedupe_checked' ) ) {
			return;
		}

		set_transient( 'bs_custom_mail_dedupe_checked', 1, HOUR_IN_SECONDS );

		foreach ( array( self::HOOK_SWEEP, self::HOOK_CLEANUP ) as $hook ) {
			$pending = as_get_scheduled_actions(
				array(
					'hook'     => $hook,
					'group'    => self::GROUP,
					'status'   => 'pending',
					'per_page' => 5,
				),
				'ids'
			);

			if ( count( $pending ) <= 1 ) {
				continue;
			}

			as_unschedule_all_actions( $hook, array(), self::GROUP );

			Bs_Custom_Mail_Health::log(
				sprintf( '%d doppelte geplante Aufgaben "%s" entfernt, wird neu eingeplant.', count( $pending ), $hook ),
				'warning'
			);
		}
	}

	/**
	 * Acquire an exclusive processing lock for an order.
	 *
	 * Implemented as an atomic INSERT IGNORE against the options table: the
	 * unique index on option_name makes exactly one concurrent caller win.
	 * Locks older than LOCK_TTL are considered abandoned (the holder crashed)
	 * and can be stolen, so a dead request can never block an order forever.
	 *
	 * The option is written with autoload "no" and is never read through the
	 * options API, so the object cache cannot serve a stale value.
	 *
	 * @param int $order_id Order ID.
	 * @return bool Whether the lock was acquired.
	 */
	private function acquire_lock( $order_id ) {
		global $wpdb;

		$name = $this->lock_name( $order_id );
		$now  = time();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$inserted = $wpdb->query(
			$wpdb->prepare(
				"INSERT IGNORE INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, %s, 'no')",
				$name,
				(string) $now
			)
		);

		if ( $inserted ) {
			return true;
		}

		// Steal an abandoned lock.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$stolen = $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$wpdb->options} SET option_value = %s WHERE option_name = %s AND option_value < %s",
				(string) $now,
				$name,
				(string) ( $now - self::LOCK_TTL )
			)
		);

		if ( $stolen ) {
			Bs_Custom_Mail_Health::log(
				sprintf( 'Verwaiste Sperre für Bestellung #%d übernommen (vorheriger Lauf abgebrochen).', $order_id ),
				'warning'
			);
			return true;
		}

		return false;
	}

	/**
	 * Release the processing lock for an order.
	 *
	 * @param int $order_id Order ID.
	 */
	private function release_lock( $order_id ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->delete( $wpdb->options, array( 'option_name' => $this->lock_name( $order_id ) ), array( '%s' ) );
	}

	/**
	 * Option name used for an order's processing lock.
	 *
	 * @param int $order_id Order ID.
	 * @return string
	 */
	private function lock_name( $order_id ) {
		return '_bs_cm_lock_' . (int) $order_id;
	}

	/**
	 * Re-run processing for an order on demand (admin action).
	 *
	 * Resets the attempt counter and queues an async job. Never processes in
	 * the admin request itself: a PDF or mail fatal would otherwise end in the
	 * WordPress error screen (seen with #4673).
	 *
	 * @param int $order_id Order ID.
	 * @return bool Whether a job is queued.
	 */
	public function retry_now( $order_id ) {
		$order = wc_get_order( (int) $order_id );

		if ( ! $order ) {
			return false;
		}

		$order->update_meta_data( self::META_ATTEMPTS, 0 );
		$order->update_meta_data( self::META_STATE, self::STATE_PENDING );
		// A manual retry usually follows a fix (e.g. a smaller invoice logo):
		// give the invoice another chance. If it still crashes, the next
		// attempt falls back to sending without it again.
		$order->delete_meta_data( Bs_Custom_Mail_Email_Sender::META_INVOICE_STARTED );
		$order->delete_meta_data( Bs_Custom_Mail_Email_Sender::META_INVOICE_SKIPPED );
		$order->save();

		$order->add_order_note( __( 'Verarbeitung manuell neu angestoßen (läuft im Hintergrund).', 'bs-custom-mail' ) );

		return $this->enqueue_order( (int) $order_id );
	}

	/**
	 * Add "Buchungsmail erneut senden" to the order actions dropdown.
	 *
	 * @param array $actions Order actions.
	 * @return array
	 */
	public function add_order_actions( $actions ) {
		$actions[ self::ORDER_ACTION_RESEND ] = __( 'Buchungs-/Gutscheinmail erneut senden', 'bs-custom-mail' );
		return $actions;
	}

	/**
	 * Order action handler: queue the resend job, never send inline.
	 *
	 * @param WC_Order $order Order.
	 */
	public function handle_resend_order_action( $order ) {
		if ( ! $order instanceof WC_Order || ! current_user_can( 'edit_shop_orders' ) ) {
			return;
		}

		$args = array( $order->get_id() );

		if ( function_exists( 'as_next_scheduled_action' ) && false !== as_next_scheduled_action( self::HOOK_RESEND, $args, self::GROUP ) ) {
			return; // Already queued (double click).
		}

		if ( function_exists( 'as_enqueue_async_action' ) ) {
			as_enqueue_async_action( self::HOOK_RESEND, $args, self::GROUP );
		} else {
			wp_schedule_single_event( time() + 1, self::HOOK_RESEND, $args );
		}

		$order->add_order_note(
			sprintf(
				/* translators: %s: user name */
				__( 'Buchungsmail zum erneuten Versand eingeplant (von %s). Kein neuer Gutschein, keine neue Buchung.', 'bs-custom-mail' ),
				wp_get_current_user()->display_name
			)
		);
	}

	/**
	 * Worker for the resend job: mail step only, guarded like the main job.
	 *
	 * @param int $order_id Order ID.
	 */
	public function run_resend_job( $order_id ) {
		$order_id = (int) $order_id;
		$order    = wc_get_order( $order_id );

		if ( ! $order ) {
			return;
		}

		if ( ! $this->acquire_lock( $order_id ) ) {
			as_schedule_single_action( time() + 60, self::HOOK_RESEND, array( $order_id ), self::GROUP );
			return;
		}

		$this->begin_crash_guard( $order_id );
		self::set_step( 'customer_mail' );

		$result = array(
			'sent'   => array(),
			'failed' => array(),
		);

		try {
			$result = $this->email_sender->resend_order_emails( $order_id );
		} catch ( \Throwable $e ) {
			$result['failed'][] = $e->getMessage();
		} finally {
			$this->end_crash_guard();
			$this->release_lock( $order_id );
		}

		$order = wc_get_order( $order_id );

		if ( $result['sent'] && ! $result['failed'] ) {
			$note = sprintf(
				/* translators: 1: recipient, 2: template keys */
				__( 'Buchungsmail erneut an %1$s gesendet (Vorlage: %2$s).', 'bs-custom-mail' ),
				$order->get_billing_email(),
				implode( ', ', $result['sent'] )
			);
		} elseif ( ! $result['sent'] && ! $result['failed'] ) {
			$note = __( 'Buchungsmail nicht erneut gesendet: Für die Produkte dieser Bestellung ist keine eigene Mail eingerichtet.', 'bs-custom-mail' );
		} else {
			$note = sprintf(
				/* translators: 1: sent templates, 2: failed templates */
				__( '⚠️ Erneuter Versand der Buchungsmail teilweise fehlgeschlagen. Gesendet: %1$s. Fehlgeschlagen: %2$s. Details im Log (WooCommerce → Status → Logs → bs-custom-mail).', 'bs-custom-mail' ),
				$result['sent'] ? implode( ', ', $result['sent'] ) : '-',
				implode( ', ', $result['failed'] )
			);
		}

		$order->add_order_note( $note );
	}

	/**
	 * Close an order by hand without processing it ("Erledigt").
	 *
	 * Marks it settled so neither the sweep nor the SLA watchdog touches it
	 * again. Used when the shop owner has taken care of the customer manually.
	 *
	 * @param int $order_id Order ID.
	 */
	public function resolve_by_hand( $order_id ) {
		$order = wc_get_order( (int) $order_id );

		if ( ! $order ) {
			return;
		}

		$order->update_meta_data( self::META_STATE, self::STATE_DONE );
		$order->update_meta_data( self::META_RESOLVED_BY_HAND, time() );
		$order->save();

		$order->add_order_note( __( 'Zustellung manuell als erledigt markiert. Es findet keine automatische Verarbeitung mehr statt.', 'bs-custom-mail' ) );

		Bs_Custom_Mail_Health::resolve_incidents( $order_id );
		self::clear_error( $order_id );
	}
}
