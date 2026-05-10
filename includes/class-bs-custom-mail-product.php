<?php

/**
 * Product template assignment functionality
 *
 * @link       https://jltzbrg.com
 * @since      1.0.0
 *
 * @package    Bs_Custom_Mail
 * @subpackage Bs_Custom_Mail/includes
 */

/**
 * Product template assignment class.
 *
 * Adds template selection to WooCommerce products.
 *
 * @since      1.0.0
 * @package    Bs_Custom_Mail
 * @subpackage Bs_Custom_Mail/includes
 * @author     Julio Litzenberg <jltbrg@gmail.com>
 */
class Bs_Custom_Mail_Product {

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
	 * Add template selection tab to WooCommerce product data tabs.
	 *
	 * @since    1.0.0
	 * @param    array    $tabs    Product data tabs.
	 * @return   array             Modified product data tabs.
	 */
	public function add_product_data_tab( $tabs ) {
		$tabs['bs_custom_mail'] = array(
			'label'    => __( 'E-Mail Template', 'bs-custom-mail' ),
			'target'   => 'bs_custom_mail_product_data',
			'class'    => array(),
			'priority' => 70,
		);

		return $tabs;
	}

	/**
	 * Output the template selection panel content.
	 *
	 * @since    1.0.0
	 */
	public function add_product_data_panel() {
		global $post;

		// Get all available templates
		$templates = $this->get_available_templates();

		// Get saved template for this product
		$saved_template = get_post_meta( $post->ID, '_bs_custom_mail_template', true );
		$send_custom_email = get_post_meta( $post->ID, '_bs_custom_mail_send_custom', true );
		$custom_recipients = get_post_meta( $post->ID, '_bs_custom_mail_custom_recipients', true );
		$attachment_ids = get_post_meta( $post->ID, '_bs_custom_mail_attachments', true );
		if ( ! is_array( $attachment_ids ) ) {
			$attachment_ids = array();
		}

		?>
		<div id="bs_custom_mail_product_data" class="panel woocommerce_options_panel">
			<!-- Header -->
			<div class="bs-mail-panel-header">
				<div class="bs-mail-panel-icon">✉️</div>
				<div class="bs-mail-panel-title">
					<h3><?php esc_html_e( 'Automatische E-Mail Konfiguration', 'bs-custom-mail' ); ?></h3>
					<p><?php esc_html_e( 'Konfigurieren Sie die automatische E-Mail für dieses Produkt.', 'bs-custom-mail' ); ?></p>
				</div>
			</div>

			<!-- Activation Card -->
			<div class="bs-mail-card">
				<div class="bs-mail-card-header">
					<span class="bs-mail-card-icon">⚡</span>
					<h4><?php esc_html_e( 'E-Mail Aktivierung', 'bs-custom-mail' ); ?></h4>
				</div>
				<div class="bs-mail-card-body">
					<label class="bs-mail-toggle">
						<input type="checkbox" name="_bs_custom_mail_send_custom" id="_bs_custom_mail_send_custom" value="yes" <?php checked( $send_custom_email, 'yes' ); ?>>
						<span class="bs-mail-toggle-slider"></span>
						<span class="bs-mail-toggle-label"><?php esc_html_e( 'Automatische E-Mail beim Kauf senden', 'bs-custom-mail' ); ?></span>
					</label>
					<p class="bs-mail-hint"><?php esc_html_e( 'Wenn aktiviert, wird beim Kauf dieses Produkts automatisch eine personalisierte E-Mail versendet.', 'bs-custom-mail' ); ?></p>
				</div>
			</div>

			<!-- Template Card -->
			<div class="bs-mail-card">
				<div class="bs-mail-card-header">
					<span class="bs-mail-card-icon">📝</span>
					<h4><?php esc_html_e( 'Template Auswahl', 'bs-custom-mail' ); ?></h4>
				</div>
				<div class="bs-mail-card-body">
					<div class="bs-mail-field">
						<label for="_bs_custom_mail_template"><?php esc_html_e( 'E-Mail Template', 'bs-custom-mail' ); ?></label>
						<div class="bs-mail-select-wrap">
							<select name="_bs_custom_mail_template" id="_bs_custom_mail_template">
								<option value=""><?php esc_html_e( 'Kein Template ausgewählt', 'bs-custom-mail' ); ?></option>
								<?php foreach ( $templates as $key => $name ) : ?>
									<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $saved_template, $key ); ?>>
										<?php echo esc_html( $name ); ?>
									</option>
								<?php endforeach; ?>
							</select>
						</div>
						<p class="bs-mail-hint"><?php esc_html_e( 'Wählen Sie das E-Mail-Template, das für dieses Produkt verwendet werden soll.', 'bs-custom-mail' ); ?></p>
					</div>
				</div>
			</div>

			<!-- Recipients Card -->
			<div class="bs-mail-card">
				<div class="bs-mail-card-header">
					<span class="bs-mail-card-icon">👥</span>
					<h4><?php esc_html_e( 'Zusätzliche Empfänger (CC)', 'bs-custom-mail' ); ?></h4>
				</div>
				<div class="bs-mail-card-body">
					<div class="bs-mail-field">
						<label for="_bs_custom_mail_custom_recipients"><?php esc_html_e( 'E-Mail-Adressen für CC', 'bs-custom-mail' ); ?></label>
						<input type="text" 
							class="bs-mail-input"
							name="_bs_custom_mail_custom_recipients" 
							id="_bs_custom_mail_custom_recipients" 
							value="<?php echo esc_attr( $custom_recipients ); ?>" 
							placeholder="info@bootsschule.de, admin@bootsschule.de">
						<p class="bs-mail-hint"><?php esc_html_e( 'Mehrere Adressen mit Komma trennen. Diese erhalten eine Kopie der E-Mail.', 'bs-custom-mail' ); ?></p>
					</div>
				</div>
			</div>

			<!-- Attachments Card -->
			<div class="bs-mail-card">
				<div class="bs-mail-card-header">
					<span class="bs-mail-card-icon">📎</span>
					<h4><?php esc_html_e( 'E-Mail Anhänge', 'bs-custom-mail' ); ?></h4>
				</div>
				<div class="bs-mail-card-body">
					<div class="bs-mail-attachments-section">
						<button type="button" class="bs-mail-btn bs-mail-btn-secondary" id="bs_add_attachments">
							<span class="bs-mail-btn-icon">+</span>
							<?php esc_html_e( 'Dateien auswählen', 'bs-custom-mail' ); ?>
						</button>
						<p class="bs-mail-hint"><?php esc_html_e( 'PDFs oder Dokumente aus der Mediathek an die E-Mail anhängen.', 'bs-custom-mail' ); ?></p>
					</div>
					
					<div id="bs_attachments_list" class="bs-mail-attachments-list">
						<?php foreach ( $attachment_ids as $attachment_id ) : 
							$attachment = get_post( $attachment_id );
							if ( $attachment ) :
								$icon = wp_mime_type_icon( $attachment_id );
								$file_url = get_attached_file( $attachment_id );
								$file_name = $file_url ? basename( $file_url ) : 'Datei';
						?>
							<div class="bs-mail-attachment-item" data-id="<?php echo esc_attr( $attachment_id ); ?>">
								<div class="bs-mail-attachment-icon">
									<img src="<?php echo esc_url( $icon ); ?>" alt="">
								</div>
								<div class="bs-mail-attachment-info">
									<span class="bs-mail-attachment-name"><?php echo esc_html( $file_name ); ?></span>
								</div>
								<button type="button" class="bs-mail-attachment-remove bs-remove-attachment" title="<?php esc_attr_e( 'Entfernen', 'bs-custom-mail' ); ?>">
									×
								</button>
								<input type="hidden" name="_bs_custom_mail_attachments[]" value="<?php echo esc_attr( $attachment_id ); ?>">
							</div>
							<?php endif; endforeach; ?>
					</div>
				</div>
			</div>

			<!-- Preview Card -->
			<div class="bs-mail-card bs-mail-card-preview">
				<div class="bs-mail-card-header">
					<span class="bs-mail-card-icon">👁️</span>
					<h4><?php esc_html_e( 'Vorschau & Test', 'bs-custom-mail' ); ?></h4>
				</div>
				<div class="bs-mail-card-body">
					<button type="button" class="bs-mail-btn bs-mail-btn-primary" id="bs_preview_template" data-product-id="<?php echo esc_attr( $post->ID ); ?>">
						<span class="bs-mail-btn-icon">👁️</span>
						<?php esc_html_e( 'Template Vorschau', 'bs-custom-mail' ); ?>
					</button>
					<p class="bs-mail-hint"><?php esc_html_e( 'Vorschau des E-Mail-Templates mit Beispieldaten anzeigen.', 'bs-custom-mail' ); ?></p>
				</div>
			</div>

			<style>
				/* Panel Container - Clean gray background */
				#bs_custom_mail_product_data {
					background: #f0f0f1;
					padding: 16px;
				}

				/* COMPLETELY hide all WooCommerce default labels and form field structures */
				#bs_custom_mail_product_data .form-field,
				#bs_custom_mail_product_data p.form-field {
					padding: 0 !important;
					margin: 0 !important;
					float: none !important;
					clear: none !important;
					width: auto !important;
				}
				#bs_custom_mail_product_data .form-field > label,
				#bs_custom_mail_product_data p.form-field > label {
					display: none !important;
					visibility: hidden !important;
					opacity: 0 !important;
					position: absolute !important;
					left: -9999px !important;
					float: none !important;
					width: 0 !important;
					margin: 0 !important;
					padding: 0 !important;
					height: 0 !important;
					overflow: hidden !important;
				}
				#bs_custom_mail_product_data .form-field input,
				#bs_custom_mail_product_data .form-field select,
				#bs_custom_mail_product_data .form-field textarea {
					float: none !important;
					width: auto !important;
					margin: 0 !important;
				}

				/* Header - Simple white card */
				.bs-mail-panel-header {
					display: flex;
					align-items: center;
					gap: 12px;
					margin-bottom: 16px;
					padding: 16px 20px;
					background: #fff;
					border: 1px solid #c3c4c7;
					border-radius: 4px;
				}
				.bs-mail-panel-icon {
					font-size: 24px;
					line-height: 1;
				}
				.bs-mail-panel-title h3 {
					margin: 0 0 2px 0;
					font-size: 14px;
					font-weight: 600;
					color: #1d2327;
				}
				.bs-mail-panel-title p {
					margin: 0;
					font-size: 12px;
					color: #646970;
				}

				/* Cards - Simple white with subtle border */
				.bs-mail-card {
					background: #fff;
					border: 1px solid #c3c4c7;
					border-radius: 4px;
					margin-bottom: 12px;
				}
				.bs-mail-card:last-child {
					margin-bottom: 0;
				}
				.bs-mail-card-header {
					display: flex;
					align-items: center;
					gap: 8px;
					padding: 12px 16px;
					background: #f6f7f7;
					border-bottom: 1px solid #c3c4c7;
				}
				.bs-mail-card-icon {
					font-size: 16px;
					line-height: 1;
				}
				.bs-mail-card-header h4 {
					margin: 0;
					font-size: 13px;
					font-weight: 600;
					color: #1d2327;
				}
				.bs-mail-card-body {
					padding: 16px;
				}

				/* Toggle Switch - Gray scale only */
				.bs-mail-toggle {
					display: flex !important;
					align-items: center;
					gap: 12px;
					cursor: pointer;
					margin: 0 !important;
					padding: 0 !important;
					width: auto !important;
					float: none !important;
				}
				.bs-mail-toggle input[type="checkbox"] {
					display: none !important;
				}
				.bs-mail-toggle-slider {
					position: relative;
					width: 40px;
					height: 22px;
					background: #c3c4c7;
					border-radius: 22px;
					transition: background 0.2s ease;
					flex-shrink: 0;
					display: block !important;
				}
				.bs-mail-toggle-slider::after {
					content: '';
					position: absolute;
					top: 2px;
					left: 2px;
					width: 18px;
					height: 18px;
					background: #fff;
					border-radius: 50%;
					box-shadow: 0 1px 3px rgba(0,0,0,0.2);
					transition: transform 0.2s ease;
				}
				.bs-mail-toggle input:checked + .bs-mail-toggle-slider {
					background: #646970;
				}
				.bs-mail-toggle input:checked + .bs-mail-toggle-slider::after {
					transform: translateX(18px);
				}
				.bs-mail-toggle-label {
					font-size: 13px;
					font-weight: 500;
					color: #1d2327;
					display: inline !important;
					width: auto !important;
					float: none !important;
					margin: 0 !important;
					padding: 0 !important;
				}

				/* Hint Text */
				.bs-mail-hint {
					margin: 8px 0 0 0;
					font-size: 12px;
					color: #646970;
					line-height: 1.5;
				}

				/* Form Fields */
				.bs-mail-field {
					margin-bottom: 4px;
				}
				.bs-mail-field label {
					display: block !important;
					margin-bottom: 6px;
					font-size: 12px;
					font-weight: 500;
					color: #1d2327;
					float: none !important;
					width: auto !important;
				}
				.bs-mail-input {
					width: 100%;
					max-width: 400px;
					padding: 6px 8px;
					font-size: 13px;
					color: #1d2327;
					background: #fff;
					border: 1px solid #8c8f94;
					border-radius: 4px;
					box-shadow: inset 0 1px 2px rgba(0,0,0,0.05);
					transition: border-color 0.1s ease;
					float: none !important;
					margin: 0 !important;
				}
				.bs-mail-input:focus {
					outline: none;
					border-color: #646970;
				}

				/* Select */
				.bs-mail-select-wrap {
					position: relative;
					max-width: 400px;
				}
				.bs-mail-select-wrap select {
					width: 100%;
					padding: 6px 32px 6px 8px;
					font-size: 13px;
					color: #1d2327;
					background: #fff;
					border: 1px solid #8c8f94;
					border-radius: 4px;
					appearance: none;
					cursor: pointer;
					box-shadow: inset 0 1px 2px rgba(0,0,0,0.05);
					float: none !important;
					margin: 0 !important;
				}
				.bs-mail-select-wrap::after {
					content: '';
					position: absolute;
					right: 10px;
					top: 50%;
					transform: translateY(-50%);
					width: 0;
					height: 0;
					border-left: 4px solid transparent;
					border-right: 4px solid transparent;
					border-top: 5px solid #646970;
					pointer-events: none;
				}
				.bs-mail-select-wrap select:focus {
					outline: none;
					border-color: #646970;
				}

				/* Buttons - WP Admin style */
				.bs-mail-btn {
					display: inline-flex;
					align-items: center;
					gap: 6px;
					padding: 6px 12px;
					font-size: 13px;
					line-height: 1.4;
					font-weight: 400;
					border: 1px solid #2271b1;
					border-radius: 3px;
					cursor: pointer;
					transition: all 0.1s ease;
				}
				.bs-mail-btn-primary {
					background: #2271b1;
					color: #fff;
				}
				.bs-mail-btn-primary:hover {
					background: #135e96;
					border-color: #135e96;
				}
				.bs-mail-btn-secondary {
					background: #f6f7f7;
					color: #2271b1;
				}
				.bs-mail-btn-secondary:hover {
					background: #f0f0f1;
					border-color: #0a4b78;
					color: #0a4b78;
				}
				.bs-mail-btn-icon {
					font-size: 14px;
					line-height: 1;
				}

				/* Attachments */
				.bs-mail-attachments-section {
					margin-bottom: 12px;
				}
				.bs-mail-attachments-list {
					display: flex;
					flex-direction: column;
					gap: 6px;
				}
				.bs-mail-attachment-item {
					display: flex;
					align-items: center;
					gap: 10px;
					padding: 10px 12px;
					background: #f6f7f7;
					border: 1px solid #c3c4c7;
					border-radius: 4px;
				}
				.bs-mail-attachment-icon img {
					width: 20px;
					height: 20px;
					opacity: 0.6;
				}
				.bs-mail-attachment-info {
					flex: 1;
					min-width: 0;
				}
				.bs-mail-attachment-name {
					font-size: 13px;
					color: #1d2327;
					word-break: break-word;
				}
				.bs-mail-attachment-remove {
					display: flex;
					align-items: center;
					justify-content: center;
					width: 24px;
					height: 24px;
					font-size: 18px;
					line-height: 1;
					color: #646970;
					background: transparent;
					border: none;
					border-radius: 3px;
					cursor: pointer;
				}
				.bs-mail-attachment-remove:hover {
					color: #d63638;
					background: #fcf0f1;
				}

				/* Preview Card */
				.bs-mail-card-preview .bs-mail-card-body {
					display: flex;
					align-items: center;
					gap: 12px;
					flex-wrap: wrap;
				}
				.bs-mail-card-preview .bs-mail-hint {
					margin: 0;
				}

				/* WooCommerce Overrides - ensure clean layout */
				#bs_custom_mail_product_data .options_group {
					border: none !important;
					padding: 0 !important;
					margin: 0 !important;
				}
			</style>
		</div>
		<?php
	}

	/**
	 * Save product meta data.
	 *
	 * Accepts both a plain post ID (save_post) and a WC_Product object
	 * (woocommerce_admin_process_product_object).
	 *
	 * @since    1.0.0
	 * @param    int|WC_Product    $post_id    Product ID or WC_Product object.
	 */
	public function save_product_meta( $post_id ) {
		if ( is_object( $post_id ) && method_exists( $post_id, 'get_id' ) ) {
			$post_id = $post_id->get_id();
		}

		$post_id = (int) $post_id;

		// Check if this is an autosave
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		// Check user permissions
		if ( ! current_user_can( 'edit_product', $post_id ) ) {
			return;
		}

		// Save template selection
		if ( isset( $_POST['_bs_custom_mail_template'] ) ) {
			$template = sanitize_text_field( wp_unslash( $_POST['_bs_custom_mail_template'] ) );
			update_post_meta( $post_id, '_bs_custom_mail_template', $template );
		}

		// Save send custom email checkbox
		$send_custom = isset( $_POST['_bs_custom_mail_send_custom'] ) ? 'yes' : 'no';
		update_post_meta( $post_id, '_bs_custom_mail_send_custom', $send_custom );

		// Save custom recipients
		if ( isset( $_POST['_bs_custom_mail_custom_recipients'] ) ) {
			$recipients = sanitize_text_field( wp_unslash( $_POST['_bs_custom_mail_custom_recipients'] ) );
			update_post_meta( $post_id, '_bs_custom_mail_custom_recipients', $recipients );
		}

		// Save attachments
		if ( isset( $_POST['_bs_custom_mail_attachments'] ) && is_array( $_POST['_bs_custom_mail_attachments'] ) ) {
			$attachment_ids = array_map( 'intval', $_POST['_bs_custom_mail_attachments'] );
			$attachment_ids = array_filter( $attachment_ids ); // Remove empty values
			update_post_meta( $post_id, '_bs_custom_mail_attachments', $attachment_ids );
		} else {
			delete_post_meta( $post_id, '_bs_custom_mail_attachments' );
		}
	}

	/**
	 * Add quick edit field for template selection.
	 *
	 * @since    1.0.0
	 * @param    string    $column_name    Column name.
	 * @param    string    $post_type      Post type.
	 */
	public function add_quick_edit_field( $column_name, $post_type ) {
		if ( 'product' !== $post_type || 'name' !== $column_name ) {
			return;
		}

		$templates = $this->get_available_templates();
		?>
		<fieldset class="inline-edit-col-right bs-custom-mail-quick-edit" style="margin-top: 10px;">
			<div class="inline-edit-col">
				<span class="title"><?php esc_html_e( 'E-Mail Template', 'bs-custom-mail' ); ?></span>
				<select name="_bs_custom_mail_template">
					<option value=""><?php esc_html_e( '-- Kein Template --', 'bs-custom-mail' ); ?></option>
					<?php foreach ( $templates as $key => $name ) : ?>
						<option value="<?php echo esc_attr( $key ); ?>">
							<?php echo esc_html( $name ); ?>
						</option>
					<?php endforeach; ?>
				</select>
			</div>
		</fieldset>
		<?php
	}

	/**
	 * Add custom column to product list.
	 *
	 * @since    1.0.0
	 * @param    array    $columns    Product list columns.
	 * @return   array                Modified columns.
	 */
	public function add_product_column( $columns ) {
		$new_columns = array();

		foreach ( $columns as $key => $value ) {
			$new_columns[ $key ] = $value;
			if ( 'product_tag' === $key ) {
				$new_columns['bs_mail_template'] = __( 'E-Mail Template', 'bs-custom-mail' );
			}
		}

		return $new_columns;
	}

	/**
	 * Render custom column content.
	 *
	 * @since    1.0.0
	 * @param    string    $column     Column name.
	 * @param    int       $post_id    Product ID.
	 */
	public function render_product_column( $column, $post_id ) {
		if ( 'bs_mail_template' !== $column ) {
			return;
		}

		$template_key = get_post_meta( $post_id, '_bs_custom_mail_template', true );
		$send_custom = get_post_meta( $post_id, '_bs_custom_mail_send_custom', true );

		if ( 'yes' === $send_custom && $template_key ) {
			$templates = $this->get_available_templates();
			if ( isset( $templates[ $template_key ] ) ) {
				echo '<span class="bs-template-badge" style="background: #46b450; color: #fff; padding: 3px 8px; border-radius: 3px; font-size: 12px;">';
				echo esc_html( $templates[ $template_key ] );
				echo '</span>';
			}
		} else {
			echo '<span style="color: #999;">—</span>';
		}
	}

	/**
	 * Get available templates from database.
	 *
	 * @since    1.0.0
	 * @return   array    Array of template_key => template_name.
	 */
	private function get_available_templates() {
		global $wpdb;

		$table_name = $wpdb->prefix . 'bs_custom_mail_templates';
		$templates = $wpdb->get_results( "SELECT template_key, template_name FROM $table_name WHERE is_active = 1 ORDER BY template_name ASC" );

		$options = array();
		foreach ( $templates as $template ) {
			$options[ $template->template_key ] = $template->template_name;
		}

		return $options;
	}

	/**
	 * AJAX handler for template preview.
	 *
	 * @since    1.0.0
	 */
	public function ajax_preview_template() {
		check_ajax_referer( 'bs_custom_mail_nonce', 'nonce' );

		if ( ! current_user_can( 'edit_products' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'bs-custom-mail' ) ) );
		}

		$template_key = isset( $_POST['template'] ) ? sanitize_text_field( wp_unslash( $_POST['template'] ) ) : '';

		if ( empty( $template_key ) ) {
			wp_send_json_error( array( 'message' => __( 'Please select a template.', 'bs-custom-mail' ) ) );
		}

		global $wpdb;
		$table_name = $wpdb->prefix . 'bs_custom_mail_templates';
		$template = $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM $table_name WHERE template_key = %s",
			$template_key
		) );

		if ( ! $template ) {
			wp_send_json_error( array( 'message' => __( 'Template not found.', 'bs-custom-mail' ) ) );
		}

		// Generate preview HTML with sample data
		$header = str_replace( '{{customer_name}}', 'Max', $template->header_text );
		$content = str_replace(
			array( '{{customer_name}}', '{{order_number}}', '{{product_name}}' ),
			array( 'Max', '12345', 'SBF See Kurs' ),
			$template->content
		);
		$footer = $template->footer_text;

		$preview = '<div style="max-width: 600px; margin: 0 auto; border: 1px solid #ddd;">';
		$preview .= $header;
		$preview .= '<div style="padding: 30px;">' . $content . '</div>';
		$preview .= $footer;
		$preview .= '</div>';

		wp_send_json_success( array( 'html' => $preview ) );
	}

}
