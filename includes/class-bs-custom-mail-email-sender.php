<?php

/**
 * Email sender functionality
 *
 * @link       https://jltzbrg.com
 * @since      1.0.0
 *
 * @package    Bs_Custom_Mail
 * @subpackage Bs_Custom_Mail/includes
 */

/**
 * Email sender class.
 *
 * Handles WooCommerce order hooks, product matching, and email sending.
 *
 * @since      1.0.0
 * @package    Bs_Custom_Mail
 * @subpackage Bs_Custom_Mail/includes
 * @author     Julio Litzenberg <jltbrg@gmail.com>
 */
class Bs_Custom_Mail_Email_Sender {

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
	 * @param    string    $plugin_name    The name of this plugin.
	 * @param    string    $version        The version of this plugin.
	 */
	public function __construct( $plugin_name, $version ) {
		$this->plugin_name = $plugin_name;
		$this->version = $version;
	}

	/**
	 * Handle WooCommerce order status change.
	 *
	 * @since    1.0.0
	 * @param    int       $order_id    Order ID.
	 * @param    string    $old_status  Old order status.
	 * @param    string    $new_status  New order status.
	 */
	public function handle_order_status_change( $order_id, $old_status, $new_status ) {
		$trigger_status = get_option( 'bs_custom_mail_trigger_status', 'processing' );

		// Check if the new status matches our trigger
		if ( $new_status !== $trigger_status ) {
			return;
		}

		// Get order
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}

		// Check if emails were already sent for this order
		$emails_sent = get_post_meta( $order_id, '_bs_custom_mail_sent', true );
		if ( $emails_sent ) {
			return;
		}

		// Loop through order items
		$items = $order->get_items();
		$sent_templates = array();

		foreach ( $items as $item ) {
			$product = $item->get_product();
			if ( ! $product ) {
				continue;
			}

			// Check if custom email is enabled for this product
			$send_custom = get_post_meta( $product->get_id(), '_bs_custom_mail_send_custom', true );
			if ( 'yes' !== $send_custom ) {
				continue;
			}

			// Get manually assigned template
			$template_key = get_post_meta( $product->get_id(), '_bs_custom_mail_template', true );

			// Fallback: try automatic matching if no template is assigned
			if ( empty( $template_key ) ) {
				$template_key = $this->match_product_to_template( $product->get_name() );
			}

			if ( $template_key && ! in_array( $template_key, $sent_templates, true ) ) {
				$success = $this->send_product_email( $order, $product, $template_key );
				if ( $success ) {
					$sent_templates[] = $template_key;
				}
			}
		}

		// Mark emails as sent
		if ( ! empty( $sent_templates ) ) {
			update_post_meta( $order_id, '_bs_custom_mail_sent', true );
			update_post_meta( $order_id, '_bs_custom_mail_sent_at', current_time( 'mysql' ) );
			update_post_meta( $order_id, '_bs_custom_mail_templates', $sent_templates );
		}
	}

	/**
	 * Match product name to template key (fallback method).
	 *
	 * Uses stripos for case-insensitive partial matching.
	 *
	 * @since    1.0.0
	 * @param    string    $product_name    Product name.
	 * @return   string|false              Template key or false if no match.
	 */
	private function match_product_to_template( $product_name ) {
		$product_name_lower = strtolower( $product_name );

		// Product to template mapping (fallback)
		$mapping = array(
			'ubi src kombi' => 'ubi_src_kombi',
			'src funkzeugnis' => 'src_funkzeugnis',
			'sbf binnen see kombi' => 'sbf_kombi',
			'sbf binnen' => 'sbf_binnen',
			'sbf see' => 'sbf_see',
			'gutschein' => 'gutschein',
		);

		// Check longer patterns first to avoid false matches
		$patterns = array(
			'ubi src kombi',
			'src funkzeugnis',
			'sbf binnen see kombi',
			'sbf binnen',
			'sbf see',
			'gutschein',
		);

		foreach ( $patterns as $pattern ) {
			if ( stripos( $product_name_lower, $pattern ) !== false ) {
				return $mapping[ $pattern ];
			}
		}

		return false;
	}

	/**
	 * Send product-specific email.
	 *
	 * @since    1.0.0
	 * @param    WC_Order    $order          Order object.
	 * @param    WC_Product  $product        Product object.
	 * @param    string      $template_key   Template key.
	 * @return   bool                        Success or failure.
	 */
	private function send_product_email( $order, $product, $template_key ) {
		global $wpdb;

		// Get template from database
		$table_name = $wpdb->prefix . 'bs_custom_mail_templates';
		$template = $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM $table_name WHERE template_key = %s AND is_active = 1",
			$template_key
		) );

		if ( ! $template ) {
			$this->log_statistic( $order->get_id(), $order->get_billing_email(), $product->get_name(), $template_key, 'template_not_found' );
			return false;
		}

		// Prepare email content
		$to = $order->get_billing_email();
		$subject = $this->parse_placeholders( $template->subject, $order, $product );
		
		// Get template attachments
		$template_attachments = $this->get_template_attachments( $template_key );
		$template_attachment_data = $this->get_template_attachment_data( $template_key );
		
		// Get product attachments
		$product_attachments = $this->get_product_attachments( $product->get_id() );
		$product_attachment_data = $this->get_attachment_data_for_display( $product->get_id() );
		
		// Combine attachments
		$attachments = array_merge( $template_attachments, $product_attachments );
		$attachment_data = array_merge( $template_attachment_data, $product_attachment_data );
		
		// Build email body with attachments section
		$message = $this->build_email_body( $template, $order, $product, $attachment_data );

		// Set headers
		$from_name  = get_option( 'bs_custom_mail_from_name', get_bloginfo( 'name' ) );
		$from_email = get_option( 'bs_custom_mail_from_email', '' );
		if ( empty( $from_email ) || ! is_email( $from_email ) ) {
			$from_email = get_option( 'admin_email' );
		}

		$headers = array(
			'Content-Type: text/html; charset=UTF-8',
			'From: ' . $from_name . ' <' . $from_email . '>',
		);

		// Send email to customer
		$sent = wp_mail( $to, $subject, $message, $headers, $attachments );

		// Send to additional recipients if configured
		$custom_recipients = get_post_meta( $product->get_id(), '_bs_custom_mail_custom_recipients', true );
		if ( ! empty( $custom_recipients ) ) {
			$additional_emails = array_map( 'trim', explode( ',', $custom_recipients ) );
			foreach ( $additional_emails as $email ) {
				if ( is_email( $email ) ) {
					$cc_subject = '[CC] ' . $subject;
					wp_mail( $email, $cc_subject, $message, $headers, $attachments );
				}
			}
		}

		// Log statistic
		$status = $sent ? 'sent' : 'failed';
		$this->log_statistic( $order->get_id(), $to, $product->get_name(), $template_key, $status );

		return $sent;
	}

	/**
	 * Build complete email body.
	 *
	 * @since    1.0.0
	 * @param    object      $template         Template object from database.
	 * @param    WC_Order    $order            Order object.
	 * @param    WC_Product  $product          Product object.
	 * @param    array       $attachments      Array of attachment data for display.
	 * @return   string                        Complete HTML email.
	 */
	private function build_email_body( $template, $order, $product, $attachments = array() ) {
		$header = $this->parse_placeholders( $template->header_text, $order, $product );
		$content = $this->parse_placeholders( $template->content, $order, $product );
		$footer = $this->parse_placeholders( $template->footer_text, $order, $product );

		// Build attachments HTML section if attachments exist
		$attachments_html = '';
		if ( ! empty( $attachments ) ) {
			$attachments_html = $this->build_attachments_section( $attachments );
		}

		$body = '<!DOCTYPE html>
<html>
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>' . esc_html( $template->subject ) . '</title>
</head>
<body style="margin: 0; padding: 0; font-family: Arial, sans-serif; line-height: 1.6; color: #333;">
	<table role="presentation" style="width: 100%; border-collapse: collapse;">
		<tr>
			<td style="padding: 0;">
				<table role="presentation" style="width: 600px; margin: 0 auto; border-collapse: collapse; border: 1px solid #ddd;">
					<tr>
						<td>
							' . $header . '
						</td>
					</tr>
					<tr>
						<td style="padding: 30px;">
							' . $content . '
							' . $attachments_html . '
						</td>
					</tr>
					<tr>
						<td>
							' . $footer . '
						</td>
					</tr>
				</table>
			</td>
		</tr>
	</table>
</body>
</html>';

		return $body;
	}

	/**
	 * Build attachments section for email body.
	 *
	 * @since    1.0.0
	 * @param    array    $attachments    Array of attachment data.
	 * @return   string                   HTML for attachments section.
	 */
	private function build_attachments_section( $attachments ) {
		$html = '
		<div class="bs-attachments-section" style="margin-top: 30px; padding-top: 25px; border-top: 2px dashed #e0e0e0;">
			<h3 style="margin: 0 0 15px; font-size: 16px; color: #333; display: flex; align-items: center;">
				<span style="display: inline-block; width: 24px; height: 24px; margin-right: 10px; background: #2271b1; border-radius: 50%; text-align: center; line-height: 24px; color: white; font-size: 14px;">📎</span>
				' . esc_html__( 'Angehängte Dokumente', 'bs-custom-mail' ) . '
			</h3>
			<p style="margin: 0 0 15px; font-size: 13px; color: #666;">
				' . esc_html__( 'Die folgenden Dateien sind dieser E-Mail beigefügt. Sie können sie direkt herunterladen:', 'bs-custom-mail' ) . '
			</p>
			<div class="bs-attachments-list" style="display: flex; flex-direction: column; gap: 10px;">';

		foreach ( $attachments as $attachment ) {
			$file_size = size_format( filesize( $attachment['path'] ) );
			$file_icon = $this->get_file_icon( $attachment['type'] );
			
			$html .= '
			<div class="bs-attachment-item" style="display: flex; align-items: center; padding: 15px; background: #f9f9f9; border: 1px solid #e0e0e0; border-radius: 8px; transition: all 0.2s;">
				<div class="bs-attachment-icon" style="width: 40px; height: 40px; margin-right: 15px; background: white; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
					' . $file_icon . '
				</div>
				<div class="bs-attachment-info" style="flex: 1;">
					<div class="bs-attachment-name" style="font-weight: 600; font-size: 14px; color: #333; margin-bottom: 3px;">
						' . esc_html( $attachment['name'] ) . '
					</div>
					<div class="bs-attachment-meta" style="font-size: 12px; color: #666;">
						' . esc_html( strtoupper( $attachment['extension'] ) ) . ' • ' . esc_html( $file_size ) . '
					</div>
				</div>
			</div>';
		}

		$html .= '
			</div>
			<p style="margin: 15px 0 0; font-size: 12px; color: #999; font-style: italic;">
				' . esc_html__( 'Tipp: Die Dateien befinden sich auch als Anhang in dieser E-Mail.', 'bs-custom-mail' ) . '
			</p>
		</div>';

		return $html;
	}

	/**
	 * Get file icon based on file type.
	 *
	 * @since    1.0.0
	 * @param    string    $mime_type    MIME type of the file.
	 * @return   string                  Emoji icon for the file type.
	 */
	private function get_file_icon( $mime_type ) {
		$icons = array(
			'application/pdf'                  => '📄',
			'application/msword'               => '📝',
			'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => '📝',
			'application/vnd.ms-excel'         => '📊',
			'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => '📊',
			'application/vnd.ms-powerpoint'    => '📽️',
			'application/zip'                  => '📦',
			'image/'                           => '🖼️',
			'video/'                           => '🎬',
			'audio/'                           => '🎵',
		);

		foreach ( $icons as $pattern => $icon ) {
			if ( strpos( $mime_type, $pattern ) === 0 ) {
				return $icon;
			}
		}

		return '📎';
	}

	/**
	 * Get attachment data for display in email.
	 *
	 * @since    1.0.0
	 * @param    int    $product_id    Product ID.
	 * @return   array                 Array of attachment data.
	 */
	private function get_attachment_data_for_display( $product_id ) {
		$attachment_ids = get_post_meta( $product_id, '_bs_custom_mail_attachments', true );
		
		if ( empty( $attachment_ids ) || ! is_array( $attachment_ids ) ) {
			return array();
		}

		return $this->build_attachment_data( $attachment_ids );
	}

	/**
	 * Get template attachments file paths.
	 *
	 * @since    1.0.0
	 * @param    string    $template_key    Template key.
	 * @return   array                     Array of file paths for attachments.
	 */
	private function get_template_attachments( $template_key ) {
		global $wpdb;
		
		$table_name = $wpdb->prefix . 'bs_custom_mail_templates';
		$attachments_string = $wpdb->get_var( $wpdb->prepare(
			"SELECT attachments FROM $table_name WHERE template_key = %s",
			$template_key
		) );
		
		if ( empty( $attachments_string ) ) {
			return array();
		}
		
		$attachment_ids = array_map( 'intval', explode( ',', $attachments_string ) );
		$attachment_ids = array_filter( $attachment_ids );
		
		$attachments = array();
		foreach ( $attachment_ids as $attachment_id ) {
			$file_path = get_attached_file( $attachment_id );
			if ( $file_path && file_exists( $file_path ) ) {
				$attachments[] = $file_path;
			}
		}
		
		return $attachments;
	}

	/**
	 * Get template attachment data for display.
	 *
	 * @since    1.0.0
	 * @param    string    $template_key    Template key.
	 * @return   array                     Array of attachment data.
	 */
	private function get_template_attachment_data( $template_key ) {
		global $wpdb;
		
		$table_name = $wpdb->prefix . 'bs_custom_mail_templates';
		$attachments_string = $wpdb->get_var( $wpdb->prepare(
			"SELECT attachments FROM $table_name WHERE template_key = %s",
			$template_key
		) );
		
		if ( empty( $attachments_string ) ) {
			return array();
		}
		
		$attachment_ids = array_map( 'intval', explode( ',', $attachments_string ) );
		$attachment_ids = array_filter( $attachment_ids );
		
		return $this->build_attachment_data( $attachment_ids );
	}

	/**
	 * Build attachment data from attachment IDs.
	 *
	 * @since    1.0.0
	 * @param    array    $attachment_ids    Array of attachment IDs.
	 * @return   array                       Array of attachment data.
	 */
	private function build_attachment_data( $attachment_ids ) {
		$attachments = array();
		
		foreach ( $attachment_ids as $attachment_id ) {
			$file_path = get_attached_file( $attachment_id );
			if ( $file_path && file_exists( $file_path ) ) {
				$attachments[] = array(
					'id'        => $attachment_id,
					'path'      => $file_path,
					'name'      => basename( $file_path ),
					'extension' => pathinfo( $file_path, PATHINFO_EXTENSION ),
					'type'      => get_post_mime_type( $attachment_id ),
				);
			}
		}

		return $attachments;
	}

	/**
	 * Parse placeholders in content.
	 *
	 * @since    1.0.0
	 * @param    string      $content    Content with placeholders.
	 * @param    WC_Order    $order      Order object.
	 * @param    WC_Product  $product    Product object.
	 * @return   string                  Content with replaced placeholders.
	 */
	private function parse_placeholders( $content, $order, $product ) {
		$placeholders = array(
			'{{customer_name}}' => $order->get_billing_first_name(),
			'{{customer_full_name}}' => $order->get_formatted_billing_full_name(),
			'{{order_number}}' => $order->get_order_number(),
			'{{order_date}}' => wc_format_datetime( $order->get_date_created() ),
			'{{product_name}}' => $product->get_name(),
			'{{site_name}}' => get_bloginfo( 'name' ),
			'{{site_url}}' => home_url(),
		);

		return str_replace(
			array_keys( $placeholders ),
			array_values( $placeholders ),
			$content
		);
	}

	/**
	 * Log email statistic.
	 *
	 * @since    1.0.0
	 * @param    int       $order_id       Order ID.
	 * @param    string    $email          Customer email.
	 * @param    string    $product_name   Product name.
	 * @param    string    $template_key   Template key.
	 * @param    string    $status         Status (sent, failed, template_not_found).
	 */
	private function log_statistic( $order_id, $email, $product_name, $template_key, $status ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'bs_custom_mail_stats';

		$wpdb->insert(
			$table_name,
			array(
				'order_id' => $order_id,
				'customer_email' => $email,
				'product_name' => $product_name,
				'template_key' => $template_key,
				'status' => $status,
				'sent_at' => current_time( 'mysql' ),
			),
			array( '%d', '%s', '%s', '%s', '%s', '%s' )
		);
	}

	/**
	 * Send test email.
	 *
	 * @since    1.0.0
	 * @param    string    $to             Recipient email.
	 * @param    string    $template_key   Template key.
	 * @param    int       $product_id     Optional product ID for testing with real attachments.
	 * @return   array                     Result with success status and message.
	 */
	public function send_test_email( $to, $template_key, $product_id = 0 ) {
		global $wpdb;

		// Get template
		$table_name = $wpdb->prefix . 'bs_custom_mail_templates';
		$template = $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM $table_name WHERE template_key = %s",
			$template_key
		) );

		if ( ! $template ) {
			return array(
				'success' => false,
				'message' => __( 'Template not found.', 'bs-custom-mail' ),
			);
		}

		// Prepare test data
		$subject = '[TEST] ' . $template->subject;

		$header = str_replace(
			array( '{{customer_name}}', '{{customer_full_name}}' ),
			__( 'Max Mustermann', 'bs-custom-mail' ),
			$template->header_text
		);

		$content = str_replace(
			array(
				'{{customer_name}}',
				'{{customer_full_name}}',
				'{{order_number}}',
				'{{order_date}}',
				'{{product_name}}',
			),
			array(
				__( 'Max', 'bs-custom-mail' ),
				__( 'Max Mustermann', 'bs-custom-mail' ),
				'12345',
				current_time( 'd.m.Y' ),
				$template->template_name,
			),
			$template->content
		);

		$footer = $template->footer_text;

		// Get attachments - either from real product or use demo attachments
		$attachments = array();
		$attachment_files = array();
		
		if ( $product_id > 0 ) {
			// Use real product attachments
			$attachments = $this->get_attachment_data_for_display( $product_id );
			$attachment_files = $this->get_product_attachments( $product_id );
		}
		
		// Build attachments HTML section
		$attachments_html = '';
		if ( ! empty( $attachments ) ) {
			$attachments_html = $this->build_attachments_section( $attachments );
		} else {
			// Show demo attachments section for preview
			$attachments_html = $this->build_demo_attachments_section();
		}

		$body = '<!DOCTYPE html>
<html>
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>' . esc_html( $subject ) . '</title>
</head>
<body style="margin: 0; padding: 0; font-family: Arial, sans-serif; line-height: 1.6; color: #333;">
	<table role="presentation" style="width: 100%; border-collapse: collapse;">
		<tr>
			<td style="padding: 0;">
				<table role="presentation" style="width: 600px; margin: 0 auto; border-collapse: collapse; border: 1px solid #ddd;">
					<tr>
						<td>
							<div style="background: #ff9800; color: #fff; text-align: center; padding: 10px; font-weight: bold;">
								' . __( 'DIES IST EINE TEST-E-MAIL', 'bs-custom-mail' ) . '
							</div>
							' . $header . '
						</td>
					</tr>
					<tr>
						<td style="padding: 30px;">
							' . $content . '
							' . $attachments_html . '
						</td>
					</tr>
					<tr>
						<td>
							' . $footer . '
						</td>
					</tr>
				</table>
			</td>
		</tr>
	</table>
</body>
</html>';

		// Set headers
		$from_name  = get_option( 'bs_custom_mail_from_name', get_bloginfo( 'name' ) );
		$from_email = get_option( 'bs_custom_mail_from_email', '' );
		if ( empty( $from_email ) || ! is_email( $from_email ) ) {
			$from_email = get_option( 'admin_email' );
		}

		$headers = array(
			'Content-Type: text/html; charset=UTF-8',
			'From: ' . $from_name . ' <' . $from_email . '>',
		);

		// Capture PHPMailer error details
		$mail_error = null;
		$error_handler = function( $wp_error ) use ( &$mail_error ) {
			$mail_error = $wp_error;
		};
		add_action( 'wp_mail_failed', $error_handler );

		$sent = wp_mail( $to, $subject, $body, $headers, $attachment_files );

		remove_action( 'wp_mail_failed', $error_handler );

		if ( $sent ) {
			return array(
				'success' => true,
				'message' => __( 'Test email sent successfully.', 'bs-custom-mail' ),
			);
		}

		$error_message = __( 'Failed to send test email. Please check your WordPress email configuration.', 'bs-custom-mail' );
		if ( $mail_error instanceof WP_Error ) {
			$error_message .= ' ' . $mail_error->get_error_message();
		}

		return array(
			'success' => false,
			'message' => $error_message,
		);
	}

	/**
	 * Build demo attachments section for test emails.
	 *
	 * @since    1.0.0
	 * @return   string   HTML for demo attachments section.
	 */
	private function build_demo_attachments_section() {
		$html = '
		<div class="bs-attachments-section" style="margin-top: 30px; padding-top: 25px; border-top: 2px dashed #e0e0e0;">
			<h3 style="margin: 0 0 15px; font-size: 16px; color: #333; display: flex; align-items: center;">
				<span style="display: inline-block; width: 24px; height: 24px; margin-right: 10px; background: #2271b1; border-radius: 50%; text-align: center; line-height: 24px; color: white; font-size: 14px;">📎</span>
				' . esc_html__( 'Angehängte Dokumente', 'bs-custom-mail' ) . '
			</h3>
			<p style="margin: 0 0 15px; font-size: 13px; color: #666;">
				' . esc_html__( 'Die folgenden Dateien sind dieser E-Mail beigefügt:', 'bs-custom-mail' ) . '
			</p>
			<div class="bs-attachments-list" style="display: flex; flex-direction: column; gap: 10px;">
				<div class="bs-attachment-item" style="display: flex; align-items: center; padding: 15px; background: #f9f9f9; border: 1px solid #e0e0e0; border-radius: 8px;">
					<div class="bs-attachment-icon" style="width: 40px; height: 40px; margin-right: 15px; background: white; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
						📄
					</div>
					<div class="bs-attachment-info" style="flex: 1;">
						<div class="bs-attachment-name" style="font-weight: 600; font-size: 14px; color: #333; margin-bottom: 3px;">
							' . esc_html__( 'Information-SBF-See.pdf', 'bs-custom-mail' ) . '
						</div>
						<div class="bs-attachment-meta" style="font-size: 12px; color: #666;">
							PDF • 245 KB
						</div>
					</div>
				</div>
				<div class="bs-attachment-item" style="display: flex; align-items: center; padding: 15px; background: #f9f9f9; border: 1px solid #e0e0e0; border-radius: 8px;">
					<div class="bs-attachment-icon" style="width: 40px; height: 40px; margin-right: 15px; background: white; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
						📝
					</div>
					<div class="bs-attachment-info" style="flex: 1;">
						<div class="bs-attachment-name" style="font-weight: 600; font-size: 14px; color: #333; margin-bottom: 3px;">
							' . esc_html__( 'Checkliste-Kurs.docx', 'bs-custom-mail' ) . '
						</div>
						<div class="bs-attachment-meta" style="font-size: 12px; color: #666;">
							WORD • 128 KB
						</div>
					</div>
				</div>
			</div>
			<p style="margin: 15px 0 0; font-size: 12px; color: #ff9800; font-style: italic;">
				⚠️ ' . esc_html__( 'Dies ist eine Demo-Ansicht. Bei echten Bestellungen werden die tatsächlich zugewiesenen Dateien angezeigt.', 'bs-custom-mail' ) . '
			</p>
		</div>';

		return $html;
	}

	/**
	 * Get product attachments file paths.
	 *
	 * @since    1.0.0
	 * @param    int    $product_id    Product ID.
	 * @return   array                 Array of file paths for attachments.
	 */
	private function get_product_attachments( $product_id ) {
		$attachment_ids = get_post_meta( $product_id, '_bs_custom_mail_attachments', true );
		
		if ( empty( $attachment_ids ) || ! is_array( $attachment_ids ) ) {
			return array();
		}
		
		$attachments = array();
		foreach ( $attachment_ids as $attachment_id ) {
			$file_path = get_attached_file( $attachment_id );
			if ( $file_path && file_exists( $file_path ) ) {
				$attachments[] = $file_path;
			}
		}
		
		return $attachments;
	}

	/**
	 * Send voucher email.
	 *
	 * @since    2.0.0
	 * @param    string      $to             Recipient email.
	 * @param    array       $voucher_data   Voucher data.
	 * @param    WC_Order    $order          Order object.
	 * @return   bool
	 */
	public function send_voucher_email( $to, $voucher_data, $order ) {
		$subject = __( 'Ihr Wertgutschein - Bootsschule Berlin Köpenick', 'bs-custom-mail' );
		
		$gutschein_code = isset( $voucher_data['gutschein_code'] ) ? $voucher_data['gutschein_code'] : '';
		$gutschein_wert = isset( $voucher_data['gutschein_wert'] ) ? $voucher_data['gutschein_wert'] : '';
		$gutschein_ablauf = isset( $voucher_data['gutschein_ablauf'] ) ? $voucher_data['gutschein_ablauf'] : '';
		$empfaenger_name = isset( $voucher_data['empfaenger_name'] ) ? $voucher_data['empfaenger_name'] : '';
		$persoenliche_nachricht = isset( $voucher_data['persoenliche_nachricht'] ) ? $voucher_data['persoenliche_nachricht'] : '';
		$pdf_url = isset( $voucher_data['pdf_url'] ) ? $voucher_data['pdf_url'] : '';

		// Build email body
		$body = '<!DOCTYPE html>
<html>
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>' . esc_html( $subject ) . '</title>
</head>
<body style="margin: 0; padding: 0; font-family: Arial, sans-serif; line-height: 1.6; color: #333;">
	<table role="presentation" style="width: 100%; border-collapse: collapse;">
		<tr>
			<td style="padding: 0;">
				<table role="presentation" style="width: 600px; margin: 0 auto; border-collapse: collapse; border: 1px solid #ddd;">
					<tr>
						<td style="background: #000; color: #fff; padding: 30px; text-align: center;">
							<h1 style="margin: 0; font-size: 24px;">🎁 ' . esc_html__( 'Ihr Wertgutschein', 'bs-custom-mail' ) . '</h1>
						</td>
					</tr>
					<tr>
						<td style="padding: 30px;">
							<h2 style="margin-top: 0;">' . sprintf( esc_html__( 'Hallo %s,', 'bs-custom-mail' ), esc_html( $empfaenger_name ) ) . '</h2>
							<p>' . esc_html__( 'Sie haben einen Wertgutschein für die Bootsschule Berlin Köpenick erhalten!', 'bs-custom-mail' ) . '</p>
							
							<div style="background: #f9fafb; border: 2px solid #000; border-radius: 12px; padding: 24px; text-align: center; margin: 24px 0;">
								<div style="font-size: 14px; color: #6b7280; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 8px;">' . esc_html__( 'Gutscheinwert', 'bs-custom-mail' ) . '</div>
								<div style="font-size: 36px; font-weight: 800; color: #000; line-height: 1;">' . esc_html( $gutschein_wert ) . '</div>
								<div style="margin-top: 16px; padding-top: 16px; border-top: 1px dashed #e5e7eb;">
									<div style="font-size: 13px; color: #6b7280; margin-bottom: 4px;">' . esc_html__( 'Gutscheincode', 'bs-custom-mail' ) . '</div>
									<code style="font-size: 20px; font-weight: 700; color: #000; background: #fff; padding: 8px 16px; border-radius: 6px; display: inline-block;">' . esc_html( strtoupper( $gutschein_code ) ) . '</code>
								</div>
							</div>';

		// Add personal message if present
		if ( ! empty( $persoenliche_nachricht ) ) {
			$body .= '
							<div style="background: #f3f4f6; padding: 20px; border-radius: 8px; margin: 24px 0;">
								<div style="font-size: 13px; color: #6b7280; margin-bottom: 8px;">' . esc_html__( 'Persönliche Nachricht', 'bs-custom-mail' ) . '</div>
								<div style="font-style: italic; color: #374151;">' . nl2br( esc_html( $persoenliche_nachricht ) ) . '</div>
							</div>';
		}

		$body .= '
							<p style="font-size: 13px; color: #6b7280; margin-top: 24px;">
								<strong>' . esc_html__( 'Gültig bis:', 'bs-custom-mail' ) . '</strong> ' . esc_html( $gutschein_ablauf ) . '<br>
								' . esc_html__( 'Der Gutschein kann für alle Kurse und Produkte in unserem Shop eingelöst werden.', 'bs-custom-mail' ) . '
							</p>
						</td>
					</tr>
					<tr>
						<td style="background: #f9fafb; padding: 20px; text-align: center; border-top: 1px solid #e5e7eb;">
							<p style="margin: 0; font-size: 13px; color: #6b7280;">
								<strong>Bootsschule Berlin Köpenick</strong><br>
								Grünauer Str. 3, 12557 Berlin<br>
								Tel: 0163/6298589
							</p>
						</td>
					</tr>
				</table>
			</td>
		</tr>
	</table>
</body>
</html>';

		// Set headers
		$from_name = get_option( 'bs_custom_mail_from_name', get_bloginfo( 'name' ) );
		$from_email = get_option( 'bs_custom_mail_from_email', get_option( 'admin_email' ) );

		$headers = array(
			'Content-Type: text/html; charset=UTF-8',
			'From: ' . $from_name . ' <' . $from_email . '>',
		);

		// Prepare attachments - get PDF path from URL
		$attachments = array();
		if ( ! empty( $pdf_url ) ) {
			$wp_upload = wp_upload_dir();
			$upload_dir = $wp_upload['basedir'] . '/bs-vouchers/';
			$upload_url = $wp_upload['baseurl'] . '/bs-vouchers/';
			$pdf_path = str_replace( $upload_url, $upload_dir, $pdf_url );
			if ( file_exists( $pdf_path ) ) {
				$attachments[] = $pdf_path;
			}
		}

		// Send email
		$sent = wp_mail( $to, $subject, $body, $headers, $attachments );

		return $sent;
	}

}
