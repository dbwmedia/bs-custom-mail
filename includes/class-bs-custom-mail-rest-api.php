<?php

/**
 * REST API Handler for BS Custom Mail
 *
 * @link       https://jltzbrg.com
 * @since      2.0.0
 *
 * @package    Bs_Custom_Mail
 * @subpackage Bs_Custom_Mail/includes
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * REST API Handler Class
 *
 * Registers and handles all REST API endpoints for the plugin.
 *
 * @since      2.0.0
 * @package    Bs_Custom_Mail
 * @subpackage Bs_Custom_Mail/includes
 * @author     Julio Litzenberg <jltbrg@gmail.com>
 */
class Bs_Custom_Mail_REST_API {

	/**
	 * The namespace for the REST API routes.
	 *
	 * @since    2.0.0
	 * @access   private
	 * @var      string    $namespace    The API namespace.
	 */
	private $namespace = 'bs-custom-mail/v1';

	/**
	 * Initialize the class
	 *
	 * @since    2.0.0
	 */
	public function __construct() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Register REST API routes
	 *
	 * @since    2.0.0
	 */
	public function register_routes() {
		// Templates endpoints
		register_rest_route(
			$this->namespace,
			'/templates',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_templates' ),
					'permission_callback' => array( $this, 'check_admin_permissions' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_template' ),
					'permission_callback' => array( $this, 'check_admin_permissions' ),
					'args'                => $this->get_template_creation_args(),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/templates/(?P<key>[a-z0-9_]+)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_template' ),
					'permission_callback' => array( $this, 'check_admin_permissions' ),
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_template' ),
					'permission_callback' => array( $this, 'check_admin_permissions' ),
					'args'                => $this->get_template_update_args(),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'delete_template' ),
					'permission_callback' => array( $this, 'check_admin_permissions' ),
				),
			)
		);

		// Settings endpoints
		register_rest_route(
			$this->namespace,
			'/settings',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_settings' ),
					'permission_callback' => array( $this, 'check_admin_permissions' ),
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_settings' ),
					'permission_callback' => array( $this, 'check_admin_permissions' ),
					'args'                => $this->get_settings_args(),
				),
			)
		);

		// Statistics endpoints
		register_rest_route(
			$this->namespace,
			'/stats',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_stats' ),
					'permission_callback' => array( $this, 'check_admin_permissions' ),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/stats/recent',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_recent_activity' ),
					'permission_callback' => array( $this, 'check_admin_permissions' ),
				),
			)
		);

		// Test email endpoint
		register_rest_route(
			$this->namespace,
			'/templates/(?P<key>[a-z0-9_]+)/test',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'send_test_email' ),
					'permission_callback' => array( $this, 'check_admin_permissions' ),
					'args'                => array(
						'email' => array(
							'required'          => true,
							'type'              => 'string',
							'validate_callback' => function( $param ) {
								return is_email( $param );
							},
						),
					),
				),
			)
		);

		// Placeholders endpoint
		register_rest_route(
			$this->namespace,
			'/placeholders',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_placeholders' ),
					'permission_callback' => array( $this, 'check_admin_permissions' ),
				),
			)
		);

		// Default templates endpoint
		register_rest_route(
			$this->namespace,
			'/default-templates',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_default_templates' ),
					'permission_callback' => array( $this, 'check_admin_permissions' ),
				),
			)
		);

		// Voucher endpoints
		$this->register_voucher_routes();
	}

	/**
	 * Register voucher-related REST API routes
	 *
	 * @since    2.0.0
	 */
	private function register_voucher_routes() {
		// Vouchers list
		register_rest_route(
			$this->namespace,
			'/vouchers',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_vouchers' ),
					'permission_callback' => array( $this, 'check_admin_permissions' ),
					'args'                => array(
						'page' => array(
							'default' => 1,
							'type'    => 'integer',
						),
						'per_page' => array(
							'default' => 20,
							'type'    => 'integer',
						),
						'status' => array(
							'default' => '',
							'type'    => 'string',
						),
						'search' => array(
							'default' => '',
							'type'    => 'string',
						),
					),
				),
			)
		);

		// Single voucher
		register_rest_route(
			$this->namespace,
			'/vouchers/(?P<id>\d+)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_voucher' ),
					'permission_callback' => array( $this, 'check_admin_permissions' ),
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_voucher' ),
					'permission_callback' => array( $this, 'check_admin_permissions' ),
					'args'                => array(
						'status' => array(
							'type' => 'string',
							'enum' => array( 'active', 'used', 'cancelled' ),
						),
					),
				),
			)
		);

		// Voucher stats
		register_rest_route(
			$this->namespace,
			'/vouchers/stats',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_voucher_stats' ),
					'permission_callback' => array( $this, 'check_admin_permissions' ),
				),
			)
		);

		// PDF Templates
		register_rest_route(
			$this->namespace,
			'/pdf-templates',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_pdf_templates' ),
					'permission_callback' => array( $this, 'check_admin_permissions' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_pdf_template' ),
					'permission_callback' => array( $this, 'check_admin_permissions' ),
					'args'                => $this->get_pdf_template_creation_args(),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/pdf-templates/(?P<id>\d+)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_pdf_template' ),
					'permission_callback' => array( $this, 'check_admin_permissions' ),
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_pdf_template' ),
					'permission_callback' => array( $this, 'check_admin_permissions' ),
					'args'                => $this->get_pdf_template_update_args(),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'delete_pdf_template' ),
					'permission_callback' => array( $this, 'check_admin_permissions' ),
				),
			)
		);
	}

	/**
	 * Get PDF template creation arguments
	 *
	 * @since    2.0.0
	 * @return   array
	 */
	private function get_pdf_template_creation_args() {
		return array(
			'template_name' => array(
				'required'          => true,
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			),
			'template_key' => array(
				'required'          => true,
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			),
			'attachment_id' => array(
				'required' => true,
				'type'     => 'integer',
			),
			'template_config' => array(
				'type'    => 'string',
				'default' => '',
			),
			'font_size' => array(
				'type'    => 'integer',
				'default' => 16,
			),
		);
	}

	/**
	 * Get PDF template update arguments
	 *
	 * @since    2.0.0
	 * @return   array
	 */
	private function get_pdf_template_update_args() {
		return array(
			'template_name' => array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			),
			'attachment_id' => array(
				'type' => 'integer',
			),
			'template_config' => array(
				'type' => 'string',
			),
			'font_size' => array(
				'type' => 'integer',
			),
			'is_active' => array(
				'type' => 'boolean',
			),
		);
	}

	/**
	 * Check if user has admin permissions
	 *
	 * @since    2.0.0
	 * @return   bool
	 */
	public function check_admin_permissions() {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Get template creation arguments
	 *
	 * @since    2.0.0
	 * @return   array
	 */
	private function get_template_creation_args() {
		return array(
			'template_key'  => array(
				'required'          => true,
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'validate_callback' => function( $param ) {
					return preg_match( '/^[a-z0-9_]+$/', $param );
				},
			),
			'template_name' => array(
				'required'          => true,
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			),
			'subject'       => array(
				'required'          => true,
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			),
			'header_text'   => array(
				'type'              => 'string',
				'sanitize_callback' => 'wp_kses_post',
				'default'           => '',
			),
			'content'       => array(
				'type'              => 'string',
				'sanitize_callback' => 'wp_kses_post',
				'default'           => '',
			),
			'footer_text'   => array(
				'type'              => 'string',
				'sanitize_callback' => 'wp_kses_post',
				'default'           => '',
			),
			'attachments'   => array(
				'type'    => 'array',
				'default' => array(),
			),
		);
	}

	/**
	 * Get template update arguments
	 *
	 * @since    2.0.0
	 * @return   array
	 */
	private function get_template_update_args() {
		return array(
			'subject'     => array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			),
			'header_text' => array(
				'type'              => 'string',
				'sanitize_callback' => 'wp_kses_post',
			),
			'content'     => array(
				'type'              => 'string',
				'sanitize_callback' => 'wp_kses_post',
			),
			'footer_text' => array(
				'type'              => 'string',
				'sanitize_callback' => 'wp_kses_post',
			),
			'attachments' => array(
				'type' => 'array',
			),
			'is_active'   => array(
				'type'    => 'boolean',
				'default' => true,
			),
		);
	}

	/**
	 * Get settings arguments
	 *
	 * @since    2.0.0
	 * @return   array
	 */
	private function get_settings_args() {
		return array(
			'trigger_status' => array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			),
			'from_name'      => array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			),
			'from_email'     => array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_email',
			),
		);
	}

	/**
	 * Get all templates
	 *
	 * @since    2.0.0
	 * @param    WP_REST_Request $request The request.
	 * @return   WP_REST_Response
	 */
	public function get_templates( $request ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'bs_custom_mail_templates';
		$templates  = $wpdb->get_results( "SELECT * FROM {$table_name} ORDER BY template_name ASC", ARRAY_A );

		if ( false === $templates ) {
			return new WP_Error(
				'rest_database_error',
				__( 'Database error occurred.', 'bs-custom-mail' ),
				array( 'status' => 500 )
			);
		}

		// Format attachments for each template
		foreach ( $templates as &$template ) {
			$template['attachments'] = $this->format_attachments( $template['attachments'] );
		}

		return rest_ensure_response( $templates );
	}

	/**
	 * Get a single template
	 *
	 * @since    2.0.0
	 * @param    WP_REST_Request $request The request.
	 * @return   WP_REST_Response|WP_Error
	 */
	public function get_template( $request ) {
		global $wpdb;

		$key        = $request->get_param( 'key' );
		$table_name = $wpdb->prefix . 'bs_custom_mail_templates';

		$template = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$table_name} WHERE template_key = %s", $key ),
			ARRAY_A
		);

		if ( ! $template ) {
			return new WP_Error(
				'rest_template_not_found',
				__( 'Template not found.', 'bs-custom-mail' ),
				array( 'status' => 404 )
			);
		}

		$template['attachments'] = $this->format_attachments( $template['attachments'] );

		return rest_ensure_response( $template );
	}

	/**
	 * Create a new template
	 *
	 * @since    2.0.0
	 * @param    WP_REST_Request $request The request.
	 * @return   WP_REST_Response|WP_Error
	 */
	public function create_template( $request ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'bs_custom_mail_templates';

		// Check if template key already exists
		$existing = $wpdb->get_var(
			$wpdb->prepare( "SELECT id FROM {$table_name} WHERE template_key = %s", $request->get_param( 'template_key' ) )
		);

		if ( $existing ) {
			return new WP_Error(
				'rest_template_exists',
				__( 'A template with this key already exists.', 'bs-custom-mail' ),
				array( 'status' => 409 )
			);
		}

		$attachments = $request->get_param( 'attachments' );
		$attachments = is_array( $attachments ) ? implode( ',', array_map( 'intval', $attachments ) ) : '';

		$result = $wpdb->insert(
			$table_name,
			array(
				'template_key'  => $request->get_param( 'template_key' ),
				'template_name' => $request->get_param( 'template_name' ),
				'subject'       => $request->get_param( 'subject' ),
				'header_text'   => $request->get_param( 'header_text' ),
				'content'       => $request->get_param( 'content' ),
				'footer_text'   => $request->get_param( 'footer_text' ),
				'attachments'   => $attachments,
				'is_active'     => 1,
				'created_at'    => current_time( 'mysql' ),
				'updated_at'    => current_time( 'mysql' ),
			),
			array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s' )
		);

		if ( false === $result ) {
			return new WP_Error(
				'rest_insert_failed',
				__( 'Failed to create template.', 'bs-custom-mail' ),
				array( 'status' => 500 )
			);
		}

		$template = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$table_name} WHERE id = %d", $wpdb->insert_id ),
			ARRAY_A
		);

		$template['attachments'] = $this->format_attachments( $template['attachments'] );

		$response = rest_ensure_response( $template );
		$response->set_status( 201 );

		return $response;
	}

	/**
	 * Update a template
	 *
	 * @since    2.0.0
	 * @param    WP_REST_Request $request The request.
	 * @return   WP_REST_Response|WP_Error
	 */
	public function update_template( $request ) {
		global $wpdb;

		$key        = $request->get_param( 'key' );
		$table_name = $wpdb->prefix . 'bs_custom_mail_templates';

		// Check if template exists
		$existing = $wpdb->get_var(
			$wpdb->prepare( "SELECT id FROM {$table_name} WHERE template_key = %s", $key )
		);

		if ( ! $existing ) {
			return new WP_Error(
				'rest_template_not_found',
				__( 'Template not found.', 'bs-custom-mail' ),
				array( 'status' => 404 )
			);
		}

		$update_data   = array();
		$update_format = array();

		if ( $request->has_param( 'subject' ) ) {
			$update_data['subject'] = $request->get_param( 'subject' );
			$update_format[]        = '%s';
		}

		if ( $request->has_param( 'header_text' ) ) {
			$update_data['header_text'] = $request->get_param( 'header_text' );
			$update_format[]            = '%s';
		}

		if ( $request->has_param( 'content' ) ) {
			$update_data['content'] = $request->get_param( 'content' );
			$update_format[]        = '%s';
		}

		if ( $request->has_param( 'footer_text' ) ) {
			$update_data['footer_text'] = $request->get_param( 'footer_text' );
			$update_format[]            = '%s';
		}

		if ( $request->has_param( 'attachments' ) ) {
			$attachments                = $request->get_param( 'attachments' );
			$update_data['attachments'] = is_array( $attachments ) ? implode( ',', array_map( 'intval', $attachments ) ) : '';
			$update_format[]            = '%s';
		}

		if ( $request->has_param( 'is_active' ) ) {
			$update_data['is_active'] = $request->get_param( 'is_active' ) ? 1 : 0;
			$update_format[]          = '%d';
		}

		$update_data['updated_at'] = current_time( 'mysql' );
		$update_format[]           = '%s';

		$result = $wpdb->update(
			$table_name,
			$update_data,
			array( 'template_key' => $key ),
			$update_format,
			array( '%s' )
		);

		if ( false === $result ) {
			return new WP_Error(
				'rest_update_failed',
				__( 'Failed to update template.', 'bs-custom-mail' ),
				array( 'status' => 500 )
			);
		}

		$template = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$table_name} WHERE template_key = %s", $key ),
			ARRAY_A
		);

		$template['attachments'] = $this->format_attachments( $template['attachments'] );

		return rest_ensure_response( $template );
	}

	/**
	 * Delete a template
	 *
	 * @since    2.0.0
	 * @param    WP_REST_Request $request The request.
	 * @return   WP_REST_Response|WP_Error
	 */
	public function delete_template( $request ) {
		global $wpdb;

		$key        = $request->get_param( 'key' );
		$table_name = $wpdb->prefix . 'bs_custom_mail_templates';

		// Check if template exists
		$existing = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$table_name} WHERE template_key = %s", $key ),
			ARRAY_A
		);

		if ( ! $existing ) {
			return new WP_Error(
				'rest_template_not_found',
				__( 'Template not found.', 'bs-custom-mail' ),
				array( 'status' => 404 )
			);
		}

		$result = $wpdb->delete(
			$table_name,
			array( 'template_key' => $key ),
			array( '%s' )
		);

		if ( false === $result ) {
			return new WP_Error(
				'rest_delete_failed',
				__( 'Failed to delete template.', 'bs-custom-mail' ),
				array( 'status' => 500 )
			);
		}

		return rest_ensure_response(
			array(
				'deleted'  => true,
				'template' => $existing,
			)
		);
	}

	/**
	 * Get settings
	 *
	 * @since    2.0.0
	 * @return   WP_REST_Response
	 */
	public function get_settings() {
		$settings = array(
			'trigger_status' => get_option( 'bs_custom_mail_trigger_status', 'processing' ),
			'from_name'      => get_option( 'bs_custom_mail_from_name', get_bloginfo( 'name' ) ),
			'from_email'     => get_option( 'bs_custom_mail_from_email', get_option( 'admin_email' ) ),
		);

		return rest_ensure_response( $settings );
	}

	/**
	 * Update settings
	 *
	 * @since    2.0.0
	 * @param    WP_REST_Request $request The request.
	 * @return   WP_REST_Response
	 */
	public function update_settings( $request ) {
		if ( $request->has_param( 'trigger_status' ) ) {
			update_option( 'bs_custom_mail_trigger_status', $request->get_param( 'trigger_status' ) );
		}

		if ( $request->has_param( 'from_name' ) ) {
			update_option( 'bs_custom_mail_from_name', $request->get_param( 'from_name' ) );
		}

		if ( $request->has_param( 'from_email' ) ) {
			update_option( 'bs_custom_mail_from_email', $request->get_param( 'from_email' ) );
		}

		return $this->get_settings();
	}

	/**
	 * Get statistics
	 *
	 * @since    2.0.0
	 * @return   WP_REST_Response
	 */
	public function get_stats() {
		global $wpdb;

		$table_stats     = $wpdb->prefix . 'bs_custom_mail_stats';
		$table_templates = $wpdb->prefix . 'bs_custom_mail_templates';

		$total_sent    = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table_stats} WHERE status = 'sent'" );
		$total_failed  = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table_stats} WHERE status = 'failed'" );
		$total_emails  = $total_sent + $total_failed;
		$success_rate  = $total_emails > 0 ? round( ( $total_sent / $total_emails ) * 100, 1 ) : 0;

		// Per-template statistics
		$template_stats = $wpdb->get_results(
			"SELECT t.template_name, t.template_key, 
				COUNT(CASE WHEN s.status = 'sent' THEN 1 END) as sent_count,
				COUNT(CASE WHEN s.status = 'failed' THEN 1 END) as failed_count
			FROM {$table_templates} t
			LEFT JOIN {$table_stats} s ON t.template_key = s.template_key
			GROUP BY t.template_key, t.template_name
			ORDER BY sent_count DESC",
			ARRAY_A
		);

		return rest_ensure_response(
			array(
				'total_sent'     => $total_sent,
				'total_failed'   => $total_failed,
				'total_emails'   => $total_emails,
				'success_rate'   => $success_rate,
				'template_stats' => $template_stats,
			)
		);
	}

	/**
	 * Get recent activity
	 *
	 * @since    2.0.0
	 * @return   WP_REST_Response
	 */
	public function get_recent_activity() {
		global $wpdb;

		$table_name = $wpdb->prefix . 'bs_custom_mail_stats';

		$recent = $wpdb->get_results(
			"SELECT * FROM {$table_name} ORDER BY sent_at DESC LIMIT 50",
			ARRAY_A
		);

		return rest_ensure_response( $recent );
	}

	/**
	 * Send test email
	 *
	 * @since    2.0.0
	 * @param    WP_REST_Request $request The request.
	 * @return   WP_REST_Response|WP_Error
	 */
	public function send_test_email( $request ) {
		$key   = $request->get_param( 'key' );
		$email = $request->get_param( 'email' );

		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-bs-custom-mail-email-sender.php';
		$sender = new Bs_Custom_Mail_Email_Sender( 'bs-custom-mail', '2.0.0' );
		$result = $sender->send_test_email( $email, $key );

		if ( $result['success'] ) {
			return rest_ensure_response( $result );
		} else {
			return new WP_Error(
				'rest_test_email_failed',
				$result['message'],
				array( 'status' => 500 )
			);
		}
	}

	/**
	 * Get available placeholders
	 *
	 * @since    2.0.0
	 * @return   WP_REST_Response
	 */
	public function get_placeholders() {
		$placeholders = array(
			array( 'code' => '{{Kundenname}}', 'label' => __( 'Kundenname', 'bs-custom-mail' ), 'description' => __( 'Vorname des Kunden', 'bs-custom-mail' ) ),
			array( 'code' => '{{Produktname}}', 'label' => __( 'Produktname', 'bs-custom-mail' ), 'description' => __( 'Name des gebuchten Produkts', 'bs-custom-mail' ) ),
			array( 'code' => '{{Kursdatum}}', 'label' => __( 'Kursdatum', 'bs-custom-mail' ), 'description' => __( 'Datum des Kurses', 'bs-custom-mail' ) ),
			array( 'code' => '{{Rechnungsnummer}}', 'label' => __( 'Rechnungsnummer', 'bs-custom-mail' ), 'description' => __( 'Rechnungsnummer der Bestellung', 'bs-custom-mail' ) ),
			array( 'code' => '{{Gutscheincode}}', 'label' => __( 'Gutscheincode', 'bs-custom-mail' ), 'description' => __( 'Gutscheincode (bei Gutscheinen)', 'bs-custom-mail' ) ),
			array( 'code' => '{{Gutscheinwert}}', 'label' => __( 'Gutscheinwert', 'bs-custom-mail' ), 'description' => __( 'Wert des Gutscheins', 'bs-custom-mail' ) ),
			array( 'code' => '{{order_number}}', 'label' => __( 'Bestellnummer', 'bs-custom-mail' ), 'description' => __( 'WooCommerce Bestellnummer', 'bs-custom-mail' ) ),
			array( 'code' => '{{order_date}}', 'label' => __( 'Bestelldatum', 'bs-custom-mail' ), 'description' => __( 'Datum der Bestellung', 'bs-custom-mail' ) ),
			array( 'code' => '{{customer_name}}', 'label' => __( 'Kundenname (EN)', 'bs-custom-mail' ), 'description' => __( 'Vorname des Kunden (EN Variante)', 'bs-custom-mail' ) ),
			array( 'code' => '{{customer_full_name}}', 'label' => __( 'Vollständiger Name', 'bs-custom-mail' ), 'description' => __( 'Vor- und Nachname des Kunden', 'bs-custom-mail' ) ),
			array( 'code' => '{{site_name}}', 'label' => __( 'Website-Name', 'bs-custom-mail' ), 'description' => __( 'Name der Website', 'bs-custom-mail' ) ),
			array( 'code' => '{{site_url}}', 'label' => __( 'Website-URL', 'bs-custom-mail' ), 'description' => __( 'URL der Website', 'bs-custom-mail' ) ),
		);

		return rest_ensure_response( $placeholders );
	}

	/**
	 * Get default email templates from Mail-Templates folder
	 *
	 * @since    2.0.0
	 * @return   WP_REST_Response
	 */
	public function get_default_templates() {
		$plugin_dir = plugin_dir_path( dirname( __FILE__ ) );
		$templates_dir = $plugin_dir . 'Mail-Templates/';
		
		$templates = array();
		
		if ( is_dir( $templates_dir ) ) {
			$files = glob( $templates_dir . '*.txt' );
			
			if ( $files === false ) {
				$files = array();
			}
			
			foreach ( $files as $file ) {
				$filename = basename( $file, '.txt' );
				$content = file_get_contents( $file );
				
				// Remove BOM if present
				$content = preg_replace( '/^\xEF\xBB\xBF/', '', $content );
				
				// Parse subject from first lines
				$lines = explode( "\n", $content );
				$subject = '';
				
				foreach ( $lines as $line ) {
					if ( strpos( $line, 'Betreff:' ) === 0 ) {
						$subject = trim( substr( $line, 8 ) );
						break;
					}
				}
				
				// Create a nice display name
				$display_name = $filename;
				$display_name = str_replace( array( 'Email Text ', 'Email ' ), '', $display_name );
				$display_name = str_replace( array( 'SBF SEE & Binnen', 'SBF See' ), 'SBF See', $display_name );
				
				$templates[] = array(
					'filename' => $filename . '.txt',
					'name' => $display_name,
					'subject' => $subject,
					'content' => $content,
				);
			}
		}
		
		// Sort templates alphabetically
		usort( $templates, function( $a, $b ) {
			return strcmp( $a['name'], $b['name'] );
		});
		
		return rest_ensure_response( $templates );
	}

	/**
	 * Format attachments string to array
	 *
	 * @since    2.0.0
	 * @param    string $attachments Comma-separated attachment IDs.
	 * @return   array
	 */
	private function format_attachments( $attachments ) {
		if ( empty( $attachments ) ) {
			return array();
		}

		$ids = array_map( 'intval', explode( ',', $attachments ) );
		$ids = array_filter( $ids );

		$result = array();
		foreach ( $ids as $id ) {
			$file_path = get_attached_file( $id );
			if ( $file_path && file_exists( $file_path ) ) {
				$result[] = array(
					'id'        => $id,
					'name'      => basename( $file_path ),
					'extension' => pathinfo( $file_path, PATHINFO_EXTENSION ),
					'size'      => size_format( filesize( $file_path ) ),
					'icon'      => wp_mime_type_icon( $id ),
				);
			}
		}

		return $result;
	}

	/**
	 * Get all vouchers
	 *
	 * @since    2.0.0
	 * @param    WP_REST_Request $request The request.
	 * @return   WP_REST_Response
	 */
	public function get_vouchers( $request ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'bs_custom_mail_vouchers';
		$page       = $request->get_param( 'page' );
		$per_page   = $request->get_param( 'per_page' );
		$status     = $request->get_param( 'status' );
		$search     = $request->get_param( 'search' );

		$where = 'WHERE 1=1';
		if ( $status ) {
			$where .= $wpdb->prepare( ' AND status = %s', $status );
		}
		if ( $search ) {
			$where .= $wpdb->prepare( ' AND (voucher_code LIKE %s OR recipient_email LIKE %s OR recipient_name LIKE %s)',
				'%' . $wpdb->esc_like( $search ) . '%',
				'%' . $wpdb->esc_like( $search ) . '%',
				'%' . $wpdb->esc_like( $search ) . '%'
			);
		}

		$total = $wpdb->get_var( "SELECT COUNT(*) FROM {$table_name} {$where}" );

		$offset = ( $page - 1 ) * $per_page;
		$vouchers = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table_name} {$where} ORDER BY created_at DESC LIMIT %d OFFSET %d",
				$per_page,
				$offset
			),
			ARRAY_A
		);

		return rest_ensure_response( array(
			'vouchers'    => $vouchers,
			'total'       => (int) $total,
			'total_pages' => ceil( $total / $per_page ),
			'page'        => $page,
			'per_page'    => $per_page,
		) );
	}

	/**
	 * Get single voucher
	 *
	 * @since    2.0.0
	 * @param    WP_REST_Request $request The request.
	 * @return   WP_REST_Response|WP_Error
	 */
	public function get_voucher( $request ) {
		global $wpdb;

		$id         = $request->get_param( 'id' );
		$table_name = $wpdb->prefix . 'bs_custom_mail_vouchers';

		$voucher = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$table_name} WHERE id = %d", $id ),
			ARRAY_A
		);

		if ( ! $voucher ) {
			return new WP_Error(
				'rest_voucher_not_found',
				__( 'Voucher not found.', 'bs-custom-mail' ),
				array( 'status' => 404 )
			);
		}

		return rest_ensure_response( $voucher );
	}

	/**
	 * Update voucher
	 *
	 * @since    2.0.0
	 * @param    WP_REST_Request $request The request.
	 * @return   WP_REST_Response|WP_Error
	 */
	public function update_voucher( $request ) {
		global $wpdb;

		$id         = $request->get_param( 'id' );
		$table_name = $wpdb->prefix . 'bs_custom_mail_vouchers';

		$existing = $wpdb->get_row(
			$wpdb->prepare( "SELECT id FROM {$table_name} WHERE id = %d", $id )
		);

		if ( ! $existing ) {
			return new WP_Error(
				'rest_voucher_not_found',
				__( 'Voucher not found.', 'bs-custom-mail' ),
				array( 'status' => 404 )
			);
		}

		$update_data = array();
		if ( $request->has_param( 'status' ) ) {
			$update_data['status'] = $request->get_param( 'status' );
		}

		if ( empty( $update_data ) ) {
			return rest_ensure_response( $this->get_voucher( $request ) );
		}

		$wpdb->update(
			$table_name,
			$update_data,
			array( 'id' => $id )
		);

		return $this->get_voucher( $request );
	}

	/**
	 * Get voucher statistics
	 *
	 * @since    2.0.0
	 * @return   WP_REST_Response
	 */
	public function get_voucher_stats() {
		global $wpdb;

		$table_name = $wpdb->prefix . 'bs_custom_mail_vouchers';

		$total     = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table_name}" );
		$active    = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table_name} WHERE status = 'active'" );
		$used      = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table_name} WHERE status = 'used'" );
		$cancelled = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table_name} WHERE status = 'cancelled'" );
		$total_value = (float) $wpdb->get_var( "SELECT SUM(voucher_value) FROM {$table_name}" ) ?: 0;

		return rest_ensure_response( array(
			'total'       => $total,
			'active'      => $active,
			'used'        => $used,
			'cancelled'   => $cancelled,
			'total_value' => $total_value,
		) );
	}

	/**
	 * Get all PDF templates
	 *
	 * @since    2.0.0
	 * @return   WP_REST_Response
	 */
	public function get_pdf_templates() {
		global $wpdb;

		$table_name = $wpdb->prefix . 'bs_custom_mail_pdf_templates';
		$templates  = $wpdb->get_results( "SELECT * FROM {$table_name} ORDER BY template_name ASC", ARRAY_A );

		// Add attachment info
		foreach ( $templates as &$template ) {
			$template['attachment_url'] = $template['attachment_id'] ? wp_get_attachment_url( $template['attachment_id'] ) : '';
			$template['template_config'] = json_decode( $template['template_config'], true );
		}

		return rest_ensure_response( $templates );
	}

	/**
	 * Get single PDF template
	 *
	 * @since    2.0.0
	 * @param    WP_REST_Request $request The request.
	 * @return   WP_REST_Response|WP_Error
	 */
	public function get_pdf_template( $request ) {
		global $wpdb;

		$id         = $request->get_param( 'id' );
		$table_name = $wpdb->prefix . 'bs_custom_mail_pdf_templates';

		$template = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$table_name} WHERE id = %d", $id ),
			ARRAY_A
		);

		if ( ! $template ) {
			return new WP_Error(
				'rest_template_not_found',
				__( 'PDF Template not found.', 'bs-custom-mail' ),
				array( 'status' => 404 )
			);
		}

		$template['attachment_url'] = $template['attachment_id'] ? wp_get_attachment_url( $template['attachment_id'] ) : '';
		$template['template_config'] = json_decode( $template['template_config'], true );

		return rest_ensure_response( $template );
	}

	/**
	 * Create PDF template
	 *
	 * @since    2.0.0
	 * @param    WP_REST_Request $request The request.
	 * @return   WP_REST_Response|WP_Error
	 */
	public function create_pdf_template( $request ) {
		global $wpdb;

		// Debug logging
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( 'BS Custom Mail: create_pdf_template called' );
			error_log( 'Request params: ' . print_r( $request->get_params(), true ) );
		}

		$table_name = $wpdb->prefix . 'bs_custom_mail_pdf_templates';

		// Check if table exists
		$table_exists = $wpdb->get_var( "SHOW TABLES LIKE '{$table_name}'" );
		if ( ! $table_exists ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'BS Custom Mail: Table does not exist: ' . $table_name );
			}
			return new WP_Error(
				'rest_table_not_found',
				__( 'Database table does not exist. Please deactivate and reactivate the plugin.', 'bs-custom-mail' ),
				array( 'status' => 500 )
			);
		}

		$template_key = $request->get_param( 'template_key' );
		$template_name = $request->get_param( 'template_name' );
		$attachment_id = $request->get_param( 'attachment_id' );

		if ( empty( $template_key ) ) {
			return new WP_Error(
				'rest_missing_template_key',
				__( 'Template key is required.', 'bs-custom-mail' ),
				array( 'status' => 400 )
			);
		}

		if ( empty( $template_name ) ) {
			return new WP_Error(
				'rest_missing_template_name',
				__( 'Template name is required.', 'bs-custom-mail' ),
				array( 'status' => 400 )
			);
		}

		// Check if template key already exists
		$existing = $wpdb->get_var(
			$wpdb->prepare( "SELECT id FROM {$table_name} WHERE template_key = %s", $template_key )
		);

		if ( $existing ) {
			return new WP_Error(
				'rest_template_exists',
				__( 'A PDF template with this key already exists.', 'bs-custom-mail' ),
				array( 'status' => 409 )
			);
		}

		if ( ! is_numeric( $attachment_id ) ) {
			$attachment_id = 0;
		}

		$insert_data = array(
			'template_name'   => sanitize_text_field( $template_name ),
			'template_key'    => sanitize_text_field( $template_key ),
			'attachment_id'   => intval( $attachment_id ),
			'template_config' => $request->get_param( 'template_config' ) ?: '',
			'font_size'       => intval( $request->get_param( 'font_size' ) ) ?: 16,
			'is_active'       => 1,
		);

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( 'BS Custom Mail: Insert data: ' . print_r( $insert_data, true ) );
		}

		$result = $wpdb->insert(
			$table_name,
			$insert_data,
			array( '%s', '%s', '%d', '%s', '%d', '%d' )
		);

		if ( false === $result ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'BS Custom Mail: Database insert failed: ' . $wpdb->last_error );
			}
			return new WP_Error(
				'rest_insert_failed',
				__( 'Failed to create PDF template: ', 'bs-custom-mail' ) . $wpdb->last_error,
				array( 'status' => 500 )
			);
		}

		$new_id = $wpdb->insert_id;
		
		$template = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$table_name} WHERE id = %d", $new_id ),
			ARRAY_A
		);

		if ( ! $template ) {
			return new WP_Error(
				'rest_template_not_found',
				__( 'PDF Template not found after creation.', 'bs-custom-mail' ),
				array( 'status' => 404 )
			);
		}

		$template['attachment_url'] = $template['attachment_id'] ? wp_get_attachment_url( $template['attachment_id'] ) : '';
		$template['template_config'] = json_decode( $template['template_config'], true );

		return rest_ensure_response( $template );
	}

	/**
	 * Update PDF template
	 *
	 * @since    2.0.0
	 * @param    WP_REST_Request $request The request.
	 * @return   WP_REST_Response|WP_Error
	 */
	public function update_pdf_template( $request ) {
		global $wpdb;

		$id         = $request->get_param( 'id' );
		$table_name = $wpdb->prefix . 'bs_custom_mail_pdf_templates';

		$existing = $wpdb->get_row(
			$wpdb->prepare( "SELECT id FROM {$table_name} WHERE id = %d", $id )
		);

		if ( ! $existing ) {
			return new WP_Error(
				'rest_template_not_found',
				__( 'PDF Template not found.', 'bs-custom-mail' ),
				array( 'status' => 404 )
			);
		}

		$update_data = array();
		if ( $request->has_param( 'template_name' ) ) {
			$update_data['template_name'] = sanitize_text_field( $request->get_param( 'template_name' ) );
		}
		if ( $request->has_param( 'attachment_id' ) ) {
			$update_data['attachment_id'] = intval( $request->get_param( 'attachment_id' ) );
		}
		if ( $request->has_param( 'template_config' ) ) {
			$update_data['template_config'] = $request->get_param( 'template_config' );
		}
		if ( $request->has_param( 'font_size' ) ) {
			$update_data['font_size'] = intval( $request->get_param( 'font_size' ) );
		}
		if ( $request->has_param( 'is_active' ) ) {
			$update_data['is_active'] = $request->get_param( 'is_active' ) ? 1 : 0;
		}

		if ( empty( $update_data ) ) {
			return $this->get_pdf_template( $request );
		}

		$result = $wpdb->update(
			$table_name,
			$update_data,
			array( 'id' => $id )
		);

		if ( false === $result ) {
			return new WP_Error(
				'rest_update_failed',
				__( 'Failed to update PDF template: ', 'bs-custom-mail' ) . $wpdb->last_error,
				array( 'status' => 500 )
			);
		}

		return $this->get_pdf_template( $request );
	}

	/**
	 * Delete PDF template
	 *
	 * @since    2.0.0
	 * @param    WP_REST_Request $request The request.
	 * @return   WP_REST_Response|WP_Error
	 */
	public function delete_pdf_template( $request ) {
		global $wpdb;

		$id         = $request->get_param( 'id' );
		$table_name = $wpdb->prefix . 'bs_custom_mail_pdf_templates';

		$existing = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$table_name} WHERE id = %d", $id ),
			ARRAY_A
		);

		if ( ! $existing ) {
			return new WP_Error(
				'rest_template_not_found',
				__( 'PDF Template not found.', 'bs-custom-mail' ),
				array( 'status' => 404 )
			);
		}

		$wpdb->delete(
			$table_name,
			array( 'id' => $id )
		);

		return rest_ensure_response( array(
			'deleted'  => true,
			'template' => $existing,
		) );
	}
}
