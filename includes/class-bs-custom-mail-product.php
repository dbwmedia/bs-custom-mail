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
			<div class="options_group">
				<h3><?php esc_html_e( 'Automatische E-Mail Konfiguration', 'bs-custom-mail' ); ?></h3>
				<p class="form-field">
					<?php
					woocommerce_wp_checkbox( array(
						'id'          => '_bs_custom_mail_send_custom',
						'label'       => __( 'Automatische E-Mail senden', 'bs-custom-mail' ),
						'description' => __( 'Aktivieren Sie diese Option, um beim Kauf dieses Produkts automatisch eine E-Mail zu versenden.', 'bs-custom-mail' ),
						'value'       => $send_custom_email,
					) );
					?>
				</p>
			</div>

			<div class="options_group">
				<h3><?php esc_html_e( 'Template Auswahl', 'bs-custom-mail' ); ?></h3>
				<p class="form-field">
					<label for="_bs_custom_mail_template"><?php esc_html_e( 'E-Mail Template', 'bs-custom-mail' ); ?></label>
					<select name="_bs_custom_mail_template" id="_bs_custom_mail_template" style="width: 100%; max-width: 400px;">
						<option value=""><?php esc_html_e( '-- Kein Template --', 'bs-custom-mail' ); ?></option>
						<?php foreach ( $templates as $key => $name ) : ?>
							<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $saved_template, $key ); ?>>
								<?php echo esc_html( $name ); ?>
							</option>
						<?php endforeach; ?>
					</select>
					<span class="description">
						<?php esc_html_e( 'Wählen Sie das E-Mail-Template, das für dieses Produkt verwendet werden soll.', 'bs-custom-mail' ); ?>
					</span>
				</p>
			</div>

			<div class="options_group">
				<h3><?php esc_html_e( 'Zusätzliche Empfänger', 'bs-custom-mail' ); ?></h3>
				<p class="form-field">
					<label for="_bs_custom_mail_custom_recipients"><?php esc_html_e( 'Zusätzliche E-Mail-Adressen', 'bs-custom-mail' ); ?></label>
					<input type="text" 
						name="_bs_custom_mail_custom_recipients" 
						id="_bs_custom_mail_custom_recipients" 
						value="<?php echo esc_attr( $custom_recipients ); ?>" 
						style="width: 100%; max-width: 400px;"
						placeholder="z.B. info@bootsschule.de, admin@bootsschule.de">
					<span class="description">
						<?php esc_html_e( 'Optional: Weitere E-Mail-Adressen, die eine Kopie erhalten sollen (durch Komma getrennt).', 'bs-custom-mail' ); ?>
					</span>
				</p>
			</div>

			<div class="options_group">
				<h3><?php esc_html_e( 'E-Mail Anhänge', 'bs-custom-mail' ); ?></h3>
				<p class="form-field">
					<label><?php esc_html_e( 'Dokumente anhängen', 'bs-custom-mail' ); ?></label>
					<button type="button" class="button" id="bs_add_attachments">
						<?php esc_html_e( 'Dateien auswählen', 'bs-custom-mail' ); ?>
					</button>
					<span class="description">
						<?php esc_html_e( 'Wählen Sie PDFs oder andere Dokumente aus der Mediathek, die an die E-Mail angehängt werden.', 'bs-custom-mail' ); ?>
					</span>
				</p>
				<div id="bs_attachments_list" style="margin: 10px 12px;">
					<?php foreach ( $attachment_ids as $attachment_id ) : 
						$attachment = get_post( $attachment_id );
						if ( $attachment ) :
							$icon = wp_mime_type_icon( $attachment_id );
					?>
						<div class="bs-attachment-item" data-id="<?php echo esc_attr( $attachment_id ); ?>" style="display: flex; align-items: center; padding: 8px; background: #f9f9f9; border: 1px solid #ddd; margin-bottom: 5px; border-radius: 3px;">
							<img src="<?php echo esc_url( $icon ); ?>" alt="" style="width: 32px; height: 32px; margin-right: 10px;">
							<span style="flex: 1;"><?php echo esc_html( basename( get_attached_file( $attachment_id ) ) ); ?></span>
							<button type="button" class="button button-small bs-remove-attachment" style="color: #a00;">
								<?php esc_html_e( 'Entfernen', 'bs-custom-mail' ); ?>
							</button>
							<input type="hidden" name="_bs_custom_mail_attachments[]" value="<?php echo esc_attr( $attachment_id ); ?>">
						</div>
						<?php endif; endforeach; ?>
				</div>
			</div>

			<div class="options_group">
				<h3><?php esc_html_e( 'Vorschau & Test', 'bs-custom-mail' ); ?></h3>
				<p class="form-field">
					<button type="button" class="button" id="bs_preview_template" data-product-id="<?php echo esc_attr( $post->ID ); ?>">
						<?php esc_html_e( 'Template Vorschau', 'bs-custom-mail' ); ?>
					</button>
					<span class="description" style="margin-left: 10px;">
						<?php esc_html_e( 'Vorschau des ausgewählten Templates anzeigen.', 'bs-custom-mail' ); ?>
					</span>
				</p>
			</div>

			<style>
				#bs_custom_mail_product_data h3 {
					padding: 10px 12px;
					margin: 0;
					background: #f1f1f1;
					border-bottom: 1px solid #ddd;
					font-size: 14px;
				}
				#bs_custom_mail_product_data .options_group {
					border-bottom: 1px solid #eee;
					padding-bottom: 10px;
				}
			</style>
		</div>
		<?php
	}

	/**
	 * Save product meta data.
	 *
	 * @since    1.0.0
	 * @param    int    $post_id    Product ID.
	 */
	public function save_product_meta( $post_id ) {
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
