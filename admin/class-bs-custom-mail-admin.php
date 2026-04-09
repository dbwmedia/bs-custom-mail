<?php

/**
 * The admin-specific functionality of the plugin.
 *
 * @link       https://jltzbrg.com
 * @since      1.0.0
 *
 * @package    Bs_Custom_Mail
 * @subpackage Bs_Custom_Mail/admin
 */

/**
 * The admin-specific functionality of the plugin.
 *
 * Defines the plugin name, version, and hooks for the admin area.
 *
 * @package    Bs_Custom_Mail
 * @subpackage Bs_Custom_Mail/admin
 * @author     Julio Litzenberg <jltbrg@gmail.com>
 */
class Bs_Custom_Mail_Admin {

	/**
	 * The ID of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $plugin_name    The ID of this plugin.
	 */
	private $plugin_name;

	/**
	 * The version of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $version    The current version of this plugin.
	 */
	private $version;

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since    1.0.0
	 * @param    string    $plugin_name       The name of this plugin.
	 * @param    string    $version    The version of this plugin.
	 */
	public function __construct( $plugin_name, $version ) {
		$this->plugin_name = $plugin_name;
		$this->version = $version;
	}

	/**
	 * Register the stylesheets for the admin area.
	 *
	 * @since    1.0.0
	 * @param    string    $hook    Current admin page hook.
	 */
	public function enqueue_styles( $hook ) {
		// Only load on plugin admin pages
		if ( strpos( $hook, 'bs-custom-mail' ) === false ) {
			return;
		}

		wp_enqueue_style( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'css/bs-custom-mail-admin.css', array(), $this->version, 'all' );
	}

	/**
	 * Register the JavaScript for the admin area.
	 *
	 * @since    1.0.0
	 * @param    string    $hook    Current admin page hook.
	 */
	public function enqueue_scripts( $hook ) {
		$load_script = false;

		// Load on WooCommerce product edit page
		if ( in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
			$screen = get_current_screen();
			if ( $screen && 'product' === $screen->post_type ) {
				$load_script = true;
				// Load WordPress Media Uploader scripts
				wp_enqueue_media();
			}
		}

		// Load on WooCommerce products list
		if ( 'edit.php' === $hook ) {
			$screen = get_current_screen();
			if ( $screen && 'product' === $screen->post_type ) {
				$load_script = true;
			}
		}

		if ( ! $load_script ) {
			return;
		}

		wp_enqueue_script( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'js/bs-custom-mail-admin.js', array( 'jquery' ), $this->version, false );

		// Localize script for AJAX
		wp_localize_script( $this->plugin_name, 'bs_custom_mail_ajax', array(
			'ajax_url' => admin_url( 'admin-ajax.php' ),
			'nonce'    => wp_create_nonce( 'bs_custom_mail_nonce' ),
		) );
	}

	/**
	 * Enqueue React app for plugin admin pages
	 *
	 * @since    2.0.0
	 * @param    string    $hook    Current admin page hook.
	 */
	public function enqueue_react_app( $hook ) {
		// Only load on plugin admin pages
		// Check if we're on any of our plugin pages
		$is_plugin_page = false;
		
		// Check for main plugin pages
		if ( strpos( $hook, 'bs-custom-mail' ) !== false ) {
			$is_plugin_page = true;
		}
		
		// Check for menu page or submenu pages
		if ( strpos( $hook, 'page_bs-custom-mail' ) !== false ) {
			$is_plugin_page = true;
		}
		
		if ( ! $is_plugin_page ) {
			return;
		}

		// Load WordPress Media Uploader
		wp_enqueue_media();

		// Enqueue the built React app
		$asset_file = plugin_dir_path( dirname( __FILE__ ) ) . 'build/admin.asset.php';
		
		if ( file_exists( $asset_file ) ) {
			$asset = require $asset_file;
			
			wp_enqueue_script(
				'bs-custom-mail-admin-app',
				plugin_dir_url( dirname( __FILE__ ) ) . 'build/admin.js',
				$asset['dependencies'],
				$asset['version'],
				true
			);

			wp_enqueue_style(
				'bs-custom-mail-admin-app-style',
				plugin_dir_url( dirname( __FILE__ ) ) . 'build/style-admin.css',
				array(),
				$asset['version']
			);
		}

		// Localize data for the React app
		wp_localize_script(
			'bs-custom-mail-admin-app',
			'bsCustomMailData',
			array(
				'restUrl'   => rest_url( 'bs-custom-mail/v1' ),
				'restNonce' => wp_create_nonce( 'wp_rest' ),
				'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
				'ajaxNonce' => wp_create_nonce( 'bs_custom_mail_nonce' ),
			)
		);
	}

	/**
	 * Add admin menu pages.
	 *
	 * @since    1.0.0
	 */
	public function add_admin_menu() {
		add_menu_page(
			__( 'Bootsschule Mail', 'bs-custom-mail' ),
			__( 'Bootsschule Mail', 'bs-custom-mail' ),
			'manage_options',
			'bs-custom-mail',
			array( $this, 'display_templates_page' ),
			'dashicons-email',
			30
		);

		add_submenu_page(
			'bs-custom-mail',
			__( 'E-Mail Templates', 'bs-custom-mail' ),
			__( '📧 E-Mail Templates', 'bs-custom-mail' ),
			'manage_options',
			'bs-custom-mail',
			array( $this, 'display_templates_page' )
		);

		add_submenu_page(
			'bs-custom-mail',
			__( 'Gutscheine', 'bs-custom-mail' ),
			__( '🎁 Gutscheine', 'bs-custom-mail' ),
			'manage_options',
			'bs-custom-mail-vouchers',
			array( $this, 'display_react_app_page' )
		);

		add_submenu_page(
			'bs-custom-mail',
			__( 'Statistik', 'bs-custom-mail' ),
			__( '📊 Statistik', 'bs-custom-mail' ),
			'manage_options',
			'bs-custom-mail-stats',
			array( $this, 'display_stats_page' )
		);

		add_submenu_page(
			'bs-custom-mail',
			__( 'Einstellungen', 'bs-custom-mail' ),
			__( '⚙️ Einstellungen', 'bs-custom-mail' ),
			'manage_options',
			'bs-custom-mail-settings',
			array( $this, 'display_settings_page' )
		);

		add_submenu_page(
			'bs-custom-mail',
			__( 'Hilfe & Anleitung', 'bs-custom-mail' ),
			__( '❓ Hilfe', 'bs-custom-mail' ),
			'manage_options',
			'bs-custom-mail-help',
			array( $this, 'display_help_page' )
		);
	}

	/**
	 * Display templates page.
	 *
	 * @since    1.0.0
	 */
	public function display_templates_page() {
		global $wpdb;

		$table_name = $wpdb->prefix . 'bs_custom_mail_templates';
		$action = isset( $_GET['action'] ) ? sanitize_text_field( wp_unslash( $_GET['action'] ) ) : 'list';
		$template_key = isset( $_GET['template'] ) ? sanitize_text_field( wp_unslash( $_GET['template'] ) ) : '';

		// Handle form submissions
		if ( isset( $_POST['bs_custom_mail_save_template'] ) ) {
			check_admin_referer( 'bs_custom_mail_save_template' );
			$this->save_template();
		}

		if ( isset( $_POST['bs_custom_mail_create_template'] ) ) {
			check_admin_referer( 'bs_custom_mail_create_template' );
			$this->create_template();
		}

		// Handle delete action
		if ( 'delete' === $action && ! empty( $template_key ) ) {
			$this->delete_template( $template_key );
		}

		// Load the view
		require_once plugin_dir_path( __FILE__ ) . 'partials/bs-custom-mail-admin-display.php';
	}

	/**
	 * Display settings page.
	 *
	 * @since    1.0.0
	 */
	public function display_settings_page() {
		// Handle form submission
		if ( isset( $_POST['bs_custom_mail_save_settings'] ) ) {
			check_admin_referer( 'bs_custom_mail_save_settings' );
			$this->save_settings();
		}

		// Handle test email
		if ( isset( $_POST['bs_custom_mail_send_test'] ) ) {
			check_admin_referer( 'bs_custom_mail_send_test' );
			$this->handle_test_email();
		}

		$trigger_status = get_option( 'bs_custom_mail_trigger_status', 'processing' );
		$from_name = get_option( 'bs_custom_mail_from_name', get_bloginfo( 'name' ) );
		$from_email = get_option( 'bs_custom_mail_from_email', get_option( 'admin_email' ) );

		require_once plugin_dir_path( __FILE__ ) . 'partials/bs-custom-mail-admin-settings.php';
	}

	/**
	 * Display statistics page.
	 *
	 * @since    1.0.0
	 */
	public function display_stats_page() {
		global $wpdb;

		$table_stats = $wpdb->prefix . 'bs_custom_mail_stats';
		$table_templates = $wpdb->prefix . 'bs_custom_mail_templates';

		// Get overall statistics
		$total_sent = $wpdb->get_var( "SELECT COUNT(*) FROM $table_stats WHERE status = 'sent'" );
		$total_failed = $wpdb->get_var( "SELECT COUNT(*) FROM $table_stats WHERE status = 'failed'" );

		// Get per-template statistics
		$template_stats = $wpdb->get_results(
			"SELECT t.template_name, t.template_key, 
				COUNT(CASE WHEN s.status = 'sent' THEN 1 END) as sent_count,
				COUNT(CASE WHEN s.status = 'failed' THEN 1 END) as failed_count
			FROM $table_templates t
			LEFT JOIN $table_stats s ON t.template_key = s.template_key
			GROUP BY t.template_key, t.template_name
			ORDER BY sent_count DESC"
		);

		// Get recent activity
		$recent_activity = $wpdb->get_results(
			"SELECT * FROM $table_stats ORDER BY sent_at DESC LIMIT 50"
		);

		require_once plugin_dir_path( __FILE__ ) . 'partials/bs-custom-mail-admin-stats.php';
	}

	/**
	 * Display React app page for vouchers and PDF templates.
	 *
	 * @since    2.0.0
	 */
	public function display_react_app_page() {
		// The React app will handle routing based on the page parameter
		echo '<div id="bs-custom-mail-admin-app"></div>';
	}

	/**
	 * Display help page.
	 *
	 * @since    2.0.0
	 */
	public function display_help_page() {
		require_once plugin_dir_path( __FILE__ ) . 'partials/bs-custom-mail-admin-help.php';
	}

	/**
	 * Save template.
	 *
	 * @since    1.0.0
	 */
	private function save_template() {
		global $wpdb;

		$template_key = isset( $_POST['template_key'] ) ? sanitize_text_field( wp_unslash( $_POST['template_key'] ) ) : '';
		$subject = isset( $_POST['subject'] ) ? sanitize_text_field( wp_unslash( $_POST['subject'] ) ) : '';
		$header_text = isset( $_POST['header_text'] ) ? wp_kses_post( wp_unslash( $_POST['header_text'] ) ) : '';
		$content = isset( $_POST['content'] ) ? wp_kses_post( wp_unslash( $_POST['content'] ) ) : '';
		$footer_text = isset( $_POST['footer_text'] ) ? wp_kses_post( wp_unslash( $_POST['footer_text'] ) ) : '';
		$is_active = isset( $_POST['is_active'] ) ? 1 : 0;

		// Handle template attachments
		$attachments = array();
		if ( isset( $_POST['template_attachments'] ) && is_array( $_POST['template_attachments'] ) ) {
			$attachments = array_map( 'intval', $_POST['template_attachments'] );
			$attachments = array_filter( $attachments );
		}
		$attachments_string = implode( ',', $attachments );

		if ( empty( $template_key ) ) {
			add_settings_error(
				'bs_custom_mail',
				'template_error',
				__( 'Template key is required.', 'bs-custom-mail' ),
				'error'
			);
			return;
		}

		$table_name = $wpdb->prefix . 'bs_custom_mail_templates';

		$result = $wpdb->update(
			$table_name,
			array(
				'subject'     => $subject,
				'header_text' => $header_text,
				'content'     => $content,
				'footer_text' => $footer_text,
				'attachments' => $attachments_string,
				'is_active'   => $is_active,
			),
			array( 'template_key' => $template_key ),
			array( '%s', '%s', '%s', '%s', '%s', '%d' ),
			array( '%s' )
		);

		if ( $result !== false ) {
			add_settings_error(
				'bs_custom_mail',
				'template_saved',
				__( 'Template saved successfully.', 'bs-custom-mail' ),
				'success'
			);
		} else {
			add_settings_error(
				'bs_custom_mail',
				'template_error',
				__( 'Failed to save template.', 'bs-custom-mail' ),
				'error'
			);
		}
	}

	/**
	 * Create new template.
	 *
	 * @since    1.0.0
	 */
	private function create_template() {
		global $wpdb;

		$template_key = isset( $_POST['template_key'] ) ? sanitize_text_field( wp_unslash( $_POST['template_key'] ) ) : '';
		$template_name = isset( $_POST['template_name'] ) ? sanitize_text_field( wp_unslash( $_POST['template_name'] ) ) : '';
		$subject = isset( $_POST['subject'] ) ? sanitize_text_field( wp_unslash( $_POST['subject'] ) ) : '';
		$header_text = isset( $_POST['header_text'] ) ? wp_kses_post( wp_unslash( $_POST['header_text'] ) ) : '';
		$content = isset( $_POST['content'] ) ? wp_kses_post( wp_unslash( $_POST['content'] ) ) : '';
		$footer_text = isset( $_POST['footer_text'] ) ? wp_kses_post( wp_unslash( $_POST['footer_text'] ) ) : '';

		// Validate template key
		if ( empty( $template_key ) || ! preg_match( '/^[a-z0-9_]+$/', $template_key ) ) {
			add_settings_error(
				'bs_custom_mail',
				'template_error',
				__( 'Template Key ist ungültig. Nur Kleinbuchstaben, Zahlen und Unterstriche sind erlaubt.', 'bs-custom-mail' ),
				'error'
			);
			return;
		}

		if ( empty( $template_name ) || empty( $subject ) ) {
			add_settings_error(
				'bs_custom_mail',
				'template_error',
				__( 'Template Name und Betreff sind Pflichtfelder.', 'bs-custom-mail' ),
				'error'
			);
			return;
		}

		$table_name = $wpdb->prefix . 'bs_custom_mail_templates';

		// Check if template key already exists
		$existing = $wpdb->get_var( $wpdb->prepare(
			"SELECT id FROM $table_name WHERE template_key = %s",
			$template_key
		) );

		if ( $existing ) {
			add_settings_error(
				'bs_custom_mail',
				'template_error',
				__( 'Dieser Template Key existiert bereits. Bitte wählen Sie einen anderen.', 'bs-custom-mail' ),
				'error'
			);
			return;
		}

		// Handle template attachments
		$attachments = array();
		if ( isset( $_POST['template_attachments'] ) && is_array( $_POST['template_attachments'] ) ) {
			$attachments = array_map( 'intval', $_POST['template_attachments'] );
			$attachments = array_filter( $attachments );
		}
		$attachments_string = implode( ',', $attachments );

		$result = $wpdb->insert(
			$table_name,
			array(
				'template_key'  => $template_key,
				'template_name' => $template_name,
				'subject'       => $subject,
				'header_text'   => $header_text,
				'content'       => $content,
				'footer_text'   => $footer_text,
				'attachments'   => $attachments_string,
				'is_active'     => 1,
			),
			array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d' )
		);

		if ( $result !== false ) {
			add_settings_error(
				'bs_custom_mail',
				'template_created',
				__( 'Template erfolgreich erstellt.', 'bs-custom-mail' ),
				'success'
			);
			// Redirect to edit page
			wp_redirect( admin_url( 'admin.php?page=bs-custom-mail&action=edit&template=' . $template_key ) );
			exit;
		} else {
			add_settings_error(
				'bs_custom_mail',
				'template_error',
				__( 'Fehler beim Erstellen des Templates.', 'bs-custom-mail' ),
				'error'
			);
		}
	}

	/**
	 * Delete template.
	 *
	 * @since    1.0.0
	 * @param    string    $template_key    Template key to delete.
	 */
	private function delete_template( $template_key ) {
		global $wpdb;

		// Verify nonce
		if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'bs_delete_template_' . $template_key ) ) {
			add_settings_error(
				'bs_custom_mail',
				'template_error',
				__( 'Sicherheitsüberprüfung fehlgeschlagen.', 'bs-custom-mail' ),
				'error'
			);
			return;
		}

		// Check user capabilities
		if ( ! current_user_can( 'manage_options' ) ) {
			add_settings_error(
				'bs_custom_mail',
				'template_error',
				__( 'Sie haben keine Berechtigung, Templates zu löschen.', 'bs-custom-mail' ),
				'error'
			);
			return;
		}

		$table_name = $wpdb->prefix . 'bs_custom_mail_templates';

		$result = $wpdb->delete(
			$table_name,
			array( 'template_key' => $template_key ),
			array( '%s' )
		);

		if ( $result !== false ) {
			add_settings_error(
				'bs_custom_mail',
				'template_deleted',
				__( 'Template erfolgreich gelöscht.', 'bs-custom-mail' ),
				'success'
			);
		} else {
			add_settings_error(
				'bs_custom_mail',
				'template_error',
				__( 'Fehler beim Löschen des Templates.', 'bs-custom-mail' ),
				'error'
			);
		}

		// Redirect back to list
		wp_redirect( admin_url( 'admin.php?page=bs-custom-mail' ) );
		exit;
	}

	/**
	 * Save settings.
	 *
	 * @since    1.0.0
	 */
	private function save_settings() {
		$trigger_status = isset( $_POST['trigger_status'] ) ? sanitize_text_field( wp_unslash( $_POST['trigger_status'] ) ) : 'processing';
		$from_name = isset( $_POST['from_name'] ) ? sanitize_text_field( wp_unslash( $_POST['from_name'] ) ) : '';
		$from_email = isset( $_POST['from_email'] ) ? sanitize_email( wp_unslash( $_POST['from_email'] ) ) : '';

		update_option( 'bs_custom_mail_trigger_status', $trigger_status );
		update_option( 'bs_custom_mail_from_name', $from_name );
		update_option( 'bs_custom_mail_from_email', $from_email );

		add_settings_error(
			'bs_custom_mail',
			'settings_saved',
			__( 'Settings saved successfully.', 'bs-custom-mail' ),
			'success'
		);
	}

	/**
	 * Handle test email.
	 *
	 * @since    1.0.0
	 */
	private function handle_test_email() {
		$test_email = isset( $_POST['test_email'] ) ? sanitize_email( wp_unslash( $_POST['test_email'] ) ) : '';
		$test_template = isset( $_POST['test_template'] ) ? sanitize_text_field( wp_unslash( $_POST['test_template'] ) ) : '';

		if ( empty( $test_email ) || ! is_email( $test_email ) ) {
			add_settings_error(
				'bs_custom_mail',
				'test_email_error',
				__( 'Please enter a valid email address.', 'bs-custom-mail' ),
				'error'
			);
			return;
		}

		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-bs-custom-mail-email-sender.php';
		$sender = new Bs_Custom_Mail_Email_Sender( $this->plugin_name, $this->version );
		$result = $sender->send_test_email( $test_email, $test_template );

		if ( $result['success'] ) {
			add_settings_error(
				'bs_custom_mail',
				'test_email_sent',
				$result['message'],
				'success'
			);
		} else {
			add_settings_error(
				'bs_custom_mail',
				'test_email_error',
				$result['message'],
				'error'
			);
		}
	}

	/**
	 * AJAX handler for sending test email.
	 *
	 * @since    1.0.0
	 */
	public function ajax_send_test_email() {
		check_ajax_referer( 'bs_custom_mail_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'bs-custom-mail' ) ) );
		}

		$email = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
		$template = isset( $_POST['template'] ) ? sanitize_text_field( wp_unslash( $_POST['template'] ) ) : '';

		if ( empty( $email ) || ! is_email( $email ) ) {
			wp_send_json_error( array( 'message' => __( 'Please enter a valid email address.', 'bs-custom-mail' ) ) );
		}

		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-bs-custom-mail-email-sender.php';
		$sender = new Bs_Custom_Mail_Email_Sender( $this->plugin_name, $this->version );
		$result = $sender->send_test_email( $email, $template );

		if ( $result['success'] ) {
			wp_send_json_success( $result );
		} else {
			wp_send_json_error( $result );
		}
	}

}
