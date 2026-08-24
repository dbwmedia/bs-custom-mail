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
 *  - Failure case: retries with exponential backoff (1/5/15/30 minutes).
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

	const STATE_PENDING = 'pending';
	const STATE_DONE    = 'done';
	const STATE_FAILED  = 'failed';

	/**
	 * Seconds after which a held lock is considered abandoned.
	 */
	const LOCK_TTL = 300;

	/**
	 * Number of worker attempts before the order is declared failed.
	 */
	const MAX_ATTEMPTS = 4;

	/**
	 * Retry delays in seconds, indexed by the number of failed attempts.
	 */
	const BACKOFF = array( 60, 300, 900, 1800 );

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

		if ( ! $this->acquire_lock( $order_id ) ) {
			// Another process owns this order right now. Come back shortly
			// rather than risk a parallel run.
			$this->enqueue_order( $order_id, 60 );
			return false;
		}

		$vouchers_ok = false;
		$emails_ok   = false;

		try {
			$vouchers_ok = $this->voucher->process_order_vouchers( $order_id, $context );
			$emails_ok   = $this->email_sender->maybe_send_order_emails( $order_id );
		} catch ( \Throwable $e ) {
			Bs_Custom_Mail_Health::log(
				sprintf( 'Verarbeitung von Bestellung #%d abgebrochen: %s', $order_id, $e->getMessage() ),
				'error'
			);
		} finally {
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

		$attempts = (int) $order->get_meta( self::META_ATTEMPTS );

		$order->update_meta_data( self::META_STATE, self::STATE_DONE );
		$order->save();

		Bs_Custom_Mail_Health::resolve_incidents( $order_id );

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

		$attempts = (int) $order->get_meta( self::META_ATTEMPTS ) + 1;
		$order->update_meta_data( self::META_ATTEMPTS, $attempts );

		$what = array();
		if ( ! $vouchers_ok ) {
			$what[] = 'Gutschein/Gutschein-Mail';
		}
		if ( ! $emails_ok ) {
			$what[] = 'Bestätigungs-Mail';
		}
		$what = implode( ' + ', $what );

		if ( $attempts >= self::MAX_ATTEMPTS ) {
			$order->update_meta_data( self::META_STATE, self::STATE_FAILED );
			$order->save();

			Bs_Custom_Mail_Health::record_incident(
				$order_id,
				'delivery_failed',
				sprintf(
					/* translators: 1: failing step, 2: attempt count */
					__( '%1$s konnte nach %2$d Versuchen nicht zugestellt werden. Bitte manuell prüfen und erneut auslösen.', 'bs-custom-mail' ),
					$what,
					$attempts
				)
			);

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
				'date_created' => '>' . ( time() - DAY_IN_SECONDS ),
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

		if ( false === as_next_scheduled_action( self::HOOK_SWEEP, array(), self::GROUP ) ) {
			/**
			 * Filter the interval (in seconds) of the recurring safety-net sweep.
			 *
			 * @since 2.1.0
			 * @param int $interval Interval in seconds. Default 10 minutes.
			 */
			$interval = (int) apply_filters( 'bs_custom_mail_safety_net_sweep_interval', 10 * MINUTE_IN_SECONDS );
			as_schedule_recurring_action( time() + 60, $interval, self::HOOK_SWEEP, array(), self::GROUP );
		}

		if ( false === as_next_scheduled_action( self::HOOK_CLEANUP, array(), self::GROUP ) ) {
			as_schedule_recurring_action( time() + HOUR_IN_SECONDS, DAY_IN_SECONDS, self::HOOK_CLEANUP, array(), self::GROUP );
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
	 * Clears the failure state so the pipeline gets a genuinely fresh start.
	 *
	 * @param int $order_id Order ID.
	 * @return bool
	 */
	public function retry_now( $order_id ) {
		$order = wc_get_order( (int) $order_id );

		if ( ! $order ) {
			return false;
		}

		$order->update_meta_data( self::META_ATTEMPTS, 0 );
		$order->update_meta_data( self::META_STATE, self::STATE_PENDING );
		$order->save();

		return $this->process_order( (int) $order_id, 'manual' );
	}
}
