<?php

/**
 * Health monitoring, incident escalation and the external queue runner.
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
 * Makes the delivery pipeline observable and self-diagnosing.
 *
 * Three responsibilities:
 *  1. Logging — one shared source for the whole pipeline.
 *  2. Incidents — nothing fails silently. A failed delivery raises a
 *     persistent admin notice (which survives a failing mail server) *and*
 *     attempts an alert email.
 *  3. Diagnostics — measures whether the scheduler actually runs on this host
 *     and exposes a key-protected URL that any external cron service can call
 *     to drive the queue. That removes the dependency on shell access or a
 *     hosting control panel with a cron manager.
 *
 * @since      3.0.0
 * @package    Bs_Custom_Mail
 * @subpackage Bs_Custom_Mail/includes
 */
class Bs_Custom_Mail_Health {

	/**
	 * Option holding the list of open incidents.
	 */
	const OPTION_INCIDENTS = 'bs_custom_mail_incidents';

	/**
	 * Option holding the secret key for the external queue runner.
	 */
	const OPTION_RUNNER_KEY = 'bs_custom_mail_runner_key';

	/**
	 * Transient caching the last scheduler diagnosis.
	 */
	const TRANSIENT_DIAGNOSIS = 'bs_custom_mail_scheduler_diagnosis';

	/**
	 * Logger source shown under WooCommerce -> Status -> Logs.
	 */
	const LOG_SOURCE = 'bs-custom-mail';

	/**
	 * Maximum number of incidents kept.
	 */
	const MAX_INCIDENTS = 50;

	/**
	 * Register hooks.
	 */
	public function register_hooks() {
		add_action( 'admin_notices', array( $this, 'render_admin_notices' ) );
		add_action( 'admin_post_bs_custom_mail_dismiss_incident', array( $this, 'handle_dismiss_incident' ) );
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
		add_action( 'admin_menu', array( $this, 'register_admin_page' ), 20 );
	}

	/**
	 * Add the "Zustellung & System" page under the plugin menu.
	 */
	public function register_admin_page() {
		add_submenu_page(
			'bs-custom-mail',
			__( 'Zustellung & System', 'bs-custom-mail' ),
			__( 'Zustellung & System', 'bs-custom-mail' ),
			'manage_options',
			'bs-custom-mail-health',
			array( $this, 'render_admin_page' )
		);
	}

	/**
	 * Render the delivery/system status page.
	 *
	 * Deliberately plain PHP rather than part of the React admin app: this page
	 * has to stay readable even when the JavaScript bundle fails to load, which
	 * is exactly the kind of moment somebody comes looking for it.
	 */
	public function render_admin_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Keine Berechtigung.', 'bs-custom-mail' ) );
		}

		$diagnosis = self::diagnose_scheduler( true );
		$incidents = get_option( self::OPTION_INCIDENTS, array() );
		$incidents = is_array( $incidents ) ? $incidents : array();
		$details   = $diagnosis['details'];

		echo '<div class="wrap">';
		echo '<h1>' . esc_html__( 'Zustellung & System', 'bs-custom-mail' ) . '</h1>';

		// --- Status ---------------------------------------------------------
		$badge = $diagnosis['healthy']
			? '<span style="background:#d1e7dd;color:#0f5132;padding:3px 10px;border-radius:10px;">' . esc_html__( 'OK', 'bs-custom-mail' ) . '</span>'
			: '<span style="background:#f8d7da;color:#842029;padding:3px 10px;border-radius:10px;">' . esc_html__( 'Achtung', 'bs-custom-mail' ) . '</span>';

		echo '<h2>' . esc_html__( 'Zeitsteuerung', 'bs-custom-mail' ) . ' ' . wp_kses_post( $badge ) . '</h2>';
		echo '<p>' . esc_html( $diagnosis['message'] ) . '</p>';

		echo '<table class="widefat striped" style="max-width:820px;"><tbody>';
		$this->row( __( 'Action Scheduler verfügbar', 'bs-custom-mail' ), $details['action_scheduler'] ? __( 'ja', 'bs-custom-mail' ) : __( 'nein', 'bs-custom-mail' ) );
		$this->row( __( 'Überfällige Aufgaben (> 5 Min.)', 'bs-custom-mail' ), (string) $details['overdue_actions'] );
		if ( null === $details['loopback'] ) {
			$loopback_label = __( 'nicht prüfbar (Zugriffsschutz davor)', 'bs-custom-mail' );
		} elseif ( false === $details['loopback'] ) {
			$loopback_label = __( 'blockiert', 'bs-custom-mail' );
		} else {
			$loopback_label = __( 'ok', 'bs-custom-mail' );
		}
		$this->row( __( 'Loopback (Seite erreicht sich selbst)', 'bs-custom-mail' ), $loopback_label );
		$this->row( 'DISABLE_WP_CRON', $details['wp_cron_disabled'] ? 'true' : 'false' );
		$this->row(
			__( 'Letzter externer Cron-Aufruf', 'bs-custom-mail' ),
			$details['last_sweep']
				? sprintf( /* translators: %s: human readable time difference */ __( 'vor %s', 'bs-custom-mail' ), human_time_diff( (int) $details['last_sweep'], time() ) )
				: __( 'noch nie', 'bs-custom-mail' )
		);
		echo '</tbody></table>';

		// --- Cron URL -------------------------------------------------------
		echo '<h2>' . esc_html__( 'Externer Cron-Aufruf', 'bs-custom-mail' ) . '</h2>';
		echo '<p>' . esc_html__( 'Diese URL einmal pro Minute aufrufen lassen — über den Cronjob-Bereich des Hosters oder einen externen Dienst. Damit werden Gutscheine unabhängig vom Besucheraufkommen zugestellt. Kein SSH nötig.', 'bs-custom-mail' ) . '</p>';
		printf(
			'<p><input type="text" readonly onclick="this.select()" value="%s" style="width:100%%;max-width:820px;font-family:monospace;padding:8px;"></p>',
			esc_attr( self::get_runner_url() )
		);
		echo '<p class="description">' . esc_html__( 'Der Schlüssel in der URL ist ein Geheimnis — nicht öffentlich teilen.', 'bs-custom-mail' ) . '</p>';

		// --- Incidents ------------------------------------------------------
		echo '<h2>' . esc_html__( 'Offene Vorfälle', 'bs-custom-mail' ) . '</h2>';

		if ( empty( $incidents ) ) {
			echo '<p>' . esc_html__( 'Keine offenen Vorfälle. Alle bezahlten Bestellungen wurden vollständig verarbeitet.', 'bs-custom-mail' ) . '</p>';
		} else {
			echo '<table class="widefat striped" style="max-width:820px;"><thead><tr>';
			echo '<th>' . esc_html__( 'Bestellung', 'bs-custom-mail' ) . '</th>';
			echo '<th>' . esc_html__( 'Problem', 'bs-custom-mail' ) . '</th>';
			echo '<th>' . esc_html__( 'Zeitpunkt', 'bs-custom-mail' ) . '</th>';
			echo '<th></th></tr></thead><tbody>';

			foreach ( array_reverse( $incidents, true ) as $key => $incident ) {
				$order = wc_get_order( $incident['order_id'] );
				$url   = wp_nonce_url(
					admin_url( 'admin-post.php?action=bs_custom_mail_dismiss_incident&incident=' . rawurlencode( $key ) ),
					'bs_custom_mail_dismiss_incident'
				);

				echo '<tr><td>';
				if ( $order ) {
					printf( '<a href="%s">#%s</a>', esc_url( $order->get_edit_order_url() ), esc_html( $order->get_order_number() ) );
				} else {
					echo esc_html( $incident['order_id'] );
				}
				echo '</td><td>' . esc_html( $incident['message'] ) . '</td>';
				echo '<td>' . esc_html( date_i18n( 'd.m.Y H:i', (int) $incident['time'] ) ) . '</td>';
				printf( '<td><a class="button" href="%s">%s</a></td>', esc_url( $url ), esc_html__( 'Erneut versuchen', 'bs-custom-mail' ) );
				echo '</tr>';
			}

			echo '</tbody></table>';
		}

		echo '</div>';
	}

	/**
	 * Render one key/value row of the status table.
	 *
	 * @param string $label Label.
	 * @param string $value Value.
	 */
	private function row( $label, $value ) {
		printf(
			'<tr><td style="width:340px;"><strong>%s</strong></td><td>%s</td></tr>',
			esc_html( $label ),
			esc_html( $value )
		);
	}

	/* ---------------------------------------------------------------------
	 * Logging
	 * ------------------------------------------------------------------ */

	/**
	 * Write a pipeline log entry.
	 *
	 * @param string $message Log message.
	 * @param string $level   PSR-3 level.
	 */
	public static function log( $message, $level = 'info' ) {
		if ( function_exists( 'wc_get_logger' ) ) {
			wc_get_logger()->log( $level, $message, array( 'source' => self::LOG_SOURCE ) );
			return;
		}

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( '[' . self::LOG_SOURCE . '][' . $level . '] ' . $message );
		}
	}

	/* ---------------------------------------------------------------------
	 * Incidents
	 * ------------------------------------------------------------------ */

	/**
	 * Record an incident, alert the shop owner and log it.
	 *
	 * The persistent admin notice is the primary channel on purpose: incidents
	 * are frequently caused by a broken mail path, in which case an alert email
	 * would fail for exactly the same reason.
	 *
	 * @param int    $order_id Affected order.
	 * @param string $type     Machine readable type.
	 * @param string $message  Human readable description.
	 */
	public static function record_incident( $order_id, $type, $message ) {
		$order_id  = (int) $order_id;
		$incidents = get_option( self::OPTION_INCIDENTS, array() );

		if ( ! is_array( $incidents ) ) {
			$incidents = array();
		}

		$key = $order_id . ':' . $type;

		// Already reported and still open — do not alert twice.
		if ( isset( $incidents[ $key ] ) ) {
			return;
		}

		$incidents[ $key ] = array(
			'order_id' => $order_id,
			'type'     => $type,
			'message'  => $message,
			'time'     => time(),
		);

		if ( count( $incidents ) > self::MAX_INCIDENTS ) {
			$incidents = array_slice( $incidents, -self::MAX_INCIDENTS, null, true );
		}

		update_option( self::OPTION_INCIDENTS, $incidents, false );

		self::log( sprintf( 'INCIDENT (%s) Bestellung #%d: %s', $type, $order_id, $message ), 'error' );

		$order = wc_get_order( $order_id );
		if ( $order ) {
			$order->add_order_note( '⚠️ ' . $message );
		}

		self::send_alert( $order_id, $type, $message );
	}

	/**
	 * Clear all open incidents for an order once it is settled.
	 *
	 * @param int $order_id Order ID.
	 */
	public static function resolve_incidents( $order_id ) {
		$incidents = get_option( self::OPTION_INCIDENTS, array() );

		if ( ! is_array( $incidents ) || empty( $incidents ) ) {
			return;
		}

		$order_id = (int) $order_id;
		$changed  = false;

		foreach ( $incidents as $key => $incident ) {
			if ( (int) $incident['order_id'] === $order_id ) {
				unset( $incidents[ $key ] );
				$changed = true;
			}
		}

		if ( $changed ) {
			update_option( self::OPTION_INCIDENTS, $incidents, false );
		}
	}

	/**
	 * Send the alert email to the shop owner.
	 *
	 * Deliberately plain text and without attachments to maximise the chance
	 * of getting through when the regular (attachment heavy) mails do not.
	 *
	 * @param int    $order_id Order ID.
	 * @param string $type     Incident type.
	 * @param string $message  Description.
	 */
	private static function send_alert( $order_id, $type, $message ) {
		/**
		 * Filter the recipient of pipeline alert emails.
		 *
		 * @since 3.0.0
		 * @param string $recipient Email address.
		 */
		$recipient = apply_filters( 'bs_custom_mail_alert_recipient', get_option( 'admin_email' ) );

		if ( ! is_email( $recipient ) ) {
			return;
		}

		$order     = wc_get_order( $order_id );
		$edit_link = $order ? $order->get_edit_order_url() : admin_url( 'edit.php?post_type=shop_order' );

		$subject = sprintf(
			/* translators: %s: order number */
			__( '[Aktion nötig] Gutschein-Zustellung fehlgeschlagen — Bestellung %s', 'bs-custom-mail' ),
			$order ? $order->get_order_number() : $order_id
		);

		$lines = array(
			__( 'Die automatische Verarbeitung einer bezahlten Bestellung ist fehlgeschlagen.', 'bs-custom-mail' ),
			'',
			sprintf( __( 'Bestellung: %s', 'bs-custom-mail' ), $order ? $order->get_order_number() : $order_id ),
			sprintf( __( 'Kunde: %s', 'bs-custom-mail' ), $order ? $order->get_billing_email() : '-' ),
			sprintf( __( 'Problem: %s', 'bs-custom-mail' ), $message ),
			sprintf( __( 'Typ: %s', 'bs-custom-mail' ), $type ),
			'',
			sprintf( __( 'Bestellung öffnen: %s', 'bs-custom-mail' ), $edit_link ),
			'',
			__( 'Diese Meldung erscheint auch als Hinweis im WordPress-Backend.', 'bs-custom-mail' ),
		);

		wp_mail( $recipient, $subject, implode( "\n", $lines ), array( 'Content-Type: text/plain; charset=UTF-8' ) );
	}

	/**
	 * Render admin notices for open incidents and a broken scheduler.
	 */
	public function render_admin_notices() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$incidents = get_option( self::OPTION_INCIDENTS, array() );

		if ( is_array( $incidents ) && ! empty( $incidents ) ) {
			echo '<div class="notice notice-error"><p><strong>' .
				esc_html__( 'Bootsschule Mail: Bestellungen brauchen deine Aufmerksamkeit', 'bs-custom-mail' ) .
				'</strong></p><ul style="margin-left:1.5em;list-style:disc;">';

			foreach ( array_reverse( $incidents, true ) as $key => $incident ) {
				$order = wc_get_order( $incident['order_id'] );
				$label = $order ? $order->get_order_number() : $incident['order_id'];
				$url   = wp_nonce_url(
					admin_url( 'admin-post.php?action=bs_custom_mail_dismiss_incident&incident=' . rawurlencode( $key ) ),
					'bs_custom_mail_dismiss_incident'
				);

				printf(
					'<li><strong>#%1$s</strong> — %2$s %3$s &middot; <a href="%4$s">%5$s</a></li>',
					esc_html( $label ),
					esc_html( $incident['message'] ),
					$order ? '<a href="' . esc_url( $order->get_edit_order_url() ) . '">' . esc_html__( 'Bestellung öffnen', 'bs-custom-mail' ) . '</a>' : '',
					esc_url( $url ),
					esc_html__( 'erledigt & erneut versuchen', 'bs-custom-mail' )
				);
			}

			echo '</ul></div>';
		}

		$diagnosis = self::diagnose_scheduler();

		if ( ! $diagnosis['healthy'] ) {
			echo '<div class="notice notice-warning"><p><strong>' .
				esc_html__( 'Bootsschule Mail: Zeitgesteuerte Aufgaben laufen nicht zuverlässig.', 'bs-custom-mail' ) .
				'</strong><br>' . esc_html( $diagnosis['message'] ) . '<br>' .
				esc_html__( 'Gutscheine werden dadurch möglicherweise verzögert zugestellt. Einrichtungsanleitung: docs/BETRIEB.md im Plugin-Ordner.', 'bs-custom-mail' ) .
				'</p></div>';
		}
	}

	/**
	 * Dismiss an incident and immediately retry the order.
	 */
	public function handle_dismiss_incident() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Keine Berechtigung.', 'bs-custom-mail' ) );
		}

		check_admin_referer( 'bs_custom_mail_dismiss_incident' );

		$key       = isset( $_GET['incident'] ) ? sanitize_text_field( wp_unslash( $_GET['incident'] ) ) : '';
		$incidents = get_option( self::OPTION_INCIDENTS, array() );

		if ( is_array( $incidents ) && isset( $incidents[ $key ] ) ) {
			$order_id = (int) $incidents[ $key ]['order_id'];
			unset( $incidents[ $key ] );
			update_option( self::OPTION_INCIDENTS, $incidents, false );

			if ( $order_id ) {
				do_action( 'bs_custom_mail_manual_retry', $order_id );
			}
		}

		wp_safe_redirect( wp_get_referer() ? wp_get_referer() : admin_url() );
		exit;
	}

	/* ---------------------------------------------------------------------
	 * Scheduler diagnostics
	 * ------------------------------------------------------------------ */

	/**
	 * Determine whether scheduled work actually runs on this host.
	 *
	 * Two signals, cheapest first:
	 *  1. Backlog — are there actions that were due minutes ago and still have
	 *     not run? This is empirical evidence and beats any theory.
	 *  2. Loopback probe — can this site reach its own cron endpoint? Many
	 *     shared hosts block loopback requests, which is precisely what makes
	 *     Action Scheduler's "run as soon as possible" silently degrade into
	 *     "run whenever someone visits the site".
	 *
	 * @param bool $force Skip the cache.
	 * @return array {
	 *     @type bool   $healthy Whether scheduling looks reliable.
	 *     @type string $message Human readable summary.
	 *     @type array  $details Raw measurements.
	 * }
	 */
	public static function diagnose_scheduler( $force = false ) {
		if ( ! $force ) {
			$cached = get_transient( self::TRANSIENT_DIAGNOSIS );
			if ( is_array( $cached ) ) {
				return $cached;
			}
		}

		$details = array(
			'action_scheduler'  => function_exists( 'as_enqueue_async_action' ),
			'wp_cron_disabled'  => defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON,
			'alternate_wp_cron' => defined( 'ALTERNATE_WP_CRON' ) && ALTERNATE_WP_CRON,
			'overdue_actions'   => self::count_overdue_actions(),
			// The loopback probe is a blocking HTTP request, so it only runs on
			// the status page. Routine admin page loads rely on the backlog
			// count, which is a cheap query and better evidence anyway.
			'loopback'          => $force ? self::probe_loopback() : null,
			'last_sweep'        => get_option( 'bs_custom_mail_last_sweep', 0 ),
		);

		$healthy = true;
		$message = __( 'Zeitsteuerung sieht gesund aus.', 'bs-custom-mail' );

		if ( ! $details['action_scheduler'] ) {
			$healthy = false;
			$message = __( 'Action Scheduler ist nicht verfügbar (WooCommerce inaktiv?). Es läuft nur der WP-Cron-Notpfad.', 'bs-custom-mail' );
		} elseif ( $details['overdue_actions'] > 0 ) {
			$healthy = false;
			$message = sprintf(
				/* translators: %d: number of overdue actions */
				__( '%d geplante Aufgabe(n) sind seit über 5 Minuten überfällig. Es fehlt ein zuverlässiger Auslöser (System-Cron oder externer Cron-Aufruf).', 'bs-custom-mail' ),
				$details['overdue_actions']
			);
		} elseif ( false === $details['loopback'] && ! $details['wp_cron_disabled'] ) {
			$healthy = false;
			$message = __( 'Die Seite kann sich selbst nicht aufrufen (Loopback blockiert). Ohne externen Cron-Aufruf laufen Hintergrundaufgaben nur bei Besucherzugriffen.', 'bs-custom-mail' );
		} elseif ( $details['wp_cron_disabled'] ) {
			$message = __( 'WP-Cron ist deaktiviert — es muss ein externer Cron-Aufruf eingerichtet sein.', 'bs-custom-mail' );
		}

		$result = array(
			'healthy' => $healthy,
			'message' => $message,
			'details' => $details,
		);

		set_transient( self::TRANSIENT_DIAGNOSIS, $result, 5 * MINUTE_IN_SECONDS );

		return $result;
	}

	/**
	 * Count Action Scheduler actions that are noticeably overdue.
	 *
	 * @return int
	 */
	private static function count_overdue_actions() {
		global $wpdb;

		$table = $wpdb->prefix . 'actionscheduler_actions';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );

		if ( ! $exists ) {
			return 0;
		}

		$cutoff = gmdate( 'Y-m-d H:i:s', time() - 5 * MINUTE_IN_SECONDS );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE status = 'pending' AND scheduled_date_gmt < %s",
				$cutoff
			)
		);
	}

	/**
	 * Check whether the site can issue a HTTP request to itself.
	 *
	 * @return bool|null True/false, or null when the probe could not run.
	 */
	private static function probe_loopback() {
		$response = wp_remote_post(
			site_url( 'wp-cron.php?doing_wp_cron=' . sprintf( '%.22F', microtime( true ) ) ),
			array(
				'timeout'   => 5,
				'blocking'  => true,
				'sslverify' => apply_filters( 'https_local_ssl_verify', false ),
				'body'      => array( 'bs_custom_mail_probe' => 1 ),
			)
		);

		if ( is_wp_error( $response ) ) {
			return false;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );

		// A staging site behind HTTP Basic Auth answers 401 to its own probe.
		// That says nothing about whether loopback requests work — reporting it
		// as "blocked" would be a false alarm, so it is inconclusive instead.
		if ( in_array( $code, array( 401, 403 ), true ) ) {
			return null;
		}

		// wp-cron.php answers 200 (or 503 while another run is in progress).
		return in_array( $code, array( 200, 204, 503 ), true );
	}

	/* ---------------------------------------------------------------------
	 * External queue runner
	 * ------------------------------------------------------------------ */

	/**
	 * The secret key for the external runner, generated on first use.
	 *
	 * @return string
	 */
	public static function get_runner_key() {
		$key = get_option( self::OPTION_RUNNER_KEY );

		if ( ! $key ) {
			$key = wp_generate_password( 40, false );
			update_option( self::OPTION_RUNNER_KEY, $key, false );
		}

		return $key;
	}

	/**
	 * The URL an external cron service should call.
	 *
	 * @return string
	 */
	public static function get_runner_url() {
		return add_query_arg( 'key', self::get_runner_key(), rest_url( 'bs-custom-mail/v1/run-queue' ) );
	}

	/**
	 * Register the runner and status routes.
	 */
	public function register_routes() {
		register_rest_route(
			'bs-custom-mail/v1',
			'/run-queue',
			array(
				'methods'             => array( 'GET', 'POST' ),
				'callback'            => array( $this, 'handle_run_queue' ),
				// Authenticated by the shared secret in the "key" parameter —
				// this endpoint is called by an external cron service that has
				// no WordPress session.
				'permission_callback' => '__return_true',
				'args'                => array(
					'key' => array(
						'required' => true,
						'type'     => 'string',
					),
				),
			)
		);

		register_rest_route(
			'bs-custom-mail/v1',
			'/health',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'handle_health' ),
				'permission_callback' => function () {
					return current_user_can( 'manage_options' );
				},
			)
		);
	}

	/**
	 * Run due scheduled actions on demand.
	 *
	 * This is what makes the delivery SLA achievable on hosting without shell
	 * access: any external cron service (or the hoster's URL cron) can call
	 * this every minute and the queue drains regardless of site traffic.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function handle_run_queue( $request ) {
		$provided = (string) $request->get_param( 'key' );
		$expected = self::get_runner_key();

		if ( ! hash_equals( $expected, $provided ) ) {
			return new WP_Error( 'bs_forbidden', __( 'Ungültiger Schlüssel.', 'bs-custom-mail' ), array( 'status' => 403 ) );
		}

		// Cheap guard against an over-eager or duplicated cron entry.
		if ( get_transient( 'bs_custom_mail_runner_lock' ) ) {
			return rest_ensure_response(
				array(
					'ran'     => false,
					'reason'  => 'throttled',
					'message' => __( 'Läuft bereits oder wurde gerade ausgeführt.', 'bs-custom-mail' ),
				)
			);
		}

		set_transient( 'bs_custom_mail_runner_lock', 1, 20 );

		$before = self::count_overdue_actions();

		if ( class_exists( 'ActionScheduler_QueueRunner' ) ) {
			try {
				ActionScheduler_QueueRunner::instance()->run( 'Bs_Custom_Mail_URL_Runner' );
			} catch ( \Throwable $e ) {
				self::log( 'URL-Runner: ' . $e->getMessage(), 'error' );
			}
		}

		// Also drive plain WP-Cron so the fallback path drains too.
		if ( function_exists( 'wp_cron' ) ) {
			wp_cron();
		}

		delete_transient( 'bs_custom_mail_runner_lock' );
		delete_transient( self::TRANSIENT_DIAGNOSIS );

		update_option( 'bs_custom_mail_last_sweep', time(), false );

		return rest_ensure_response(
			array(
				'ran'             => true,
				'overdue_before'  => $before,
				'overdue_after'   => self::count_overdue_actions(),
				'timestamp'       => time(),
			)
		);
	}

	/**
	 * Machine readable health payload for the admin UI.
	 *
	 * @return WP_REST_Response
	 */
	public function handle_health() {
		$diagnosis = self::diagnose_scheduler( true );
		$incidents = get_option( self::OPTION_INCIDENTS, array() );

		return rest_ensure_response(
			array(
				'scheduler'  => $diagnosis,
				'incidents'  => is_array( $incidents ) ? array_values( $incidents ) : array(),
				'runner_url' => self::get_runner_url(),
			)
		);
	}
}
