<?php

/**
 * Voucher functionality for the plugin.
 *
 * @link       https://jltzbrg.com
 * @since      2.0.0
 *
 * @package    Bs_Custom_Mail
 * @subpackage Bs_Custom_Mail/includes
 */

/**
 * Voucher functionality class.
 *
 * Handles voucher generation, PDF creation, and WooCommerce integration.
 *
 * @since      2.0.0
 * @package    Bs_Custom_Mail
 * @subpackage Bs_Custom_Mail/includes
 * @author     Julio Litzenberg <jltbrg@gmail.com>
 */
class Bs_Custom_Mail_Voucher {

	/**
	 * The plugin version.
	 *
	 * @since    2.0.0
	 * @access   private
	 * @var      string    $version
	 */
	private $version;

	/**
	 * Upload directory for vouchers.
	 *
	 * @since    2.0.0
	 * @access   private
	 * @var      string    $upload_dir
	 */
	private $upload_dir;

	/**
	 * Upload URL for vouchers.
	 *
	 * @since    2.0.0
	 * @access   private
	 * @var      string    $upload_url
	 */
	private $upload_url;

	/**
	 * Initialize the class.
	 *
	 * @since    2.0.0
	 * @param    string    $version    The plugin version.
	 */
	public function __construct( $version ) {
		$this->version = $version;
		$this->init_upload_dir();
	}

	/**
	 * Initialize upload directory.
	 *
	 * @since    2.0.0
	 */
	private function init_upload_dir() {
		$wp_upload = wp_upload_dir();
		$this->upload_dir = $wp_upload['basedir'] . '/bs-vouchers/';
		$this->upload_url = $wp_upload['baseurl'] . '/bs-vouchers/';

		if ( ! file_exists( $this->upload_dir ) ) {
			wp_mkdir_p( $this->upload_dir );

			// Protect directory
			$htaccess = $this->upload_dir . '.htaccess';
			if ( ! file_exists( $htaccess ) ) {
				file_put_contents( $htaccess, "Options -Indexes\ndeny from all" );
			}

			$index = $this->upload_dir . 'index.php';
			if ( ! file_exists( $index ) ) {
				file_put_contents( $index, '<?php // Silence is golden' );
			}
		}
	}

	/**
	 * Register hooks.
	 *
	 * @since    2.0.0
	 */
	public function register_hooks() {
		// WooCommerce product fields
		add_action( 'woocommerce_product_options_general_product_data', array( $this, 'add_product_fields' ) );
		add_action( 'woocommerce_process_product_meta', array( $this, 'save_product_fields' ) );

		// Frontend fields
		add_action( 'woocommerce_before_add_to_cart_button', array( $this, 'add_frontend_fields' ) );

		// Cart functionality
		add_filter( 'woocommerce_add_cart_item_data', array( $this, 'add_cart_item_data' ), 10, 3 );
		add_filter( 'woocommerce_get_item_data', array( $this, 'display_cart_item_data' ), 10, 2 );
		add_action( 'woocommerce_before_calculate_totals', array( $this, 'set_cart_item_price' ) );
		add_action( 'woocommerce_checkout_create_order_line_item', array( $this, 'save_order_item_meta' ), 10, 4 );

		// Voucher generation
		add_action( 'woocommerce_order_status_processing', array( $this, 'generate_voucher' ) );
		add_action( 'woocommerce_order_status_completed', array( $this, 'generate_voucher' ) );

		// Voucher cancellation
		add_action( 'woocommerce_order_status_cancelled', array( $this, 'cancel_voucher' ) );
		add_action( 'woocommerce_order_status_refunded', array( $this, 'cancel_voucher' ) );

		// Admin scripts
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_scripts' ) );
	}

	/**
	 * Add product fields in admin.
	 *
	 * @since    2.0.0
	 */
	public function add_product_fields() {
		global $post;

		$is_voucher = get_post_meta( $post->ID, '_bs_custom_mail_voucher', true );
		$pdf_template_id = get_post_meta( $post->ID, '_bs_custom_mail_voucher_pdf_template', true );
		$min_price = get_post_meta( $post->ID, '_bs_custom_mail_voucher_min_price', true ) ?: 10;
		$max_price = get_post_meta( $post->ID, '_bs_custom_mail_voucher_max_price', true ) ?: 1000;

		echo '<div class="options_group bs-voucher-fields" style="background: #f0f9ff; padding: 15px; margin: 15px; border-radius: 8px; border: 1px solid #bae6fd;">';
		echo '<h3 style="margin-top: 0; color: #0369a1;">🎁 ' . esc_html__( 'Wertgutschein Einstellungen', 'bs-custom-mail' ) . '</h3>';

		// Enable voucher
		woocommerce_wp_checkbox( array(
			'id' => '_bs_custom_mail_voucher',
			'label' => __( 'Wertgutschein aktivieren', 'bs-custom-mail' ),
			'description' => __( 'Aktivieren Sie diese Option, um dieses Produkt als Wertgutschein zu kennzeichnen.', 'bs-custom-mail' ),
			'value' => $is_voucher
		) );

		echo '<div class="bs-voucher-settings" style="margin-top: 20px; padding-top: 20px; border-top: 1px dashed #bae6fd; ' . ( $is_voucher !== 'yes' ? 'display:none;' : '' ) . '">';

		// PDF Template selection
		$this->render_pdf_template_selector( $pdf_template_id );

		// Price range
		echo '<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-top: 15px;">';
		woocommerce_wp_text_input( array(
			'id' => '_bs_custom_mail_voucher_min_price',
			'label' => __( 'Mindestbetrag (€)', 'bs-custom-mail' ),
			'type' => 'number',
			'value' => $min_price,
			'custom_attributes' => array( 'min' => '1', 'step' => '0.01' )
		) );
		woocommerce_wp_text_input( array(
			'id' => '_bs_custom_mail_voucher_max_price',
			'label' => __( 'Höchstbetrag (€)', 'bs-custom-mail' ),
			'type' => 'number',
			'value' => $max_price,
			'custom_attributes' => array( 'min' => '1', 'step' => '0.01' )
		) );
		echo '</div>';

		echo '</div>';
		echo '</div>';

		// JavaScript for toggle
		?>
		<script type="text/javascript">
		jQuery(document).ready(function($) {
			$('#_bs_custom_mail_voucher').on('change', function() {
				if ($(this).is(':checked')) {
					$('.bs-voucher-settings').slideDown();
				} else {
					$('.bs-voucher-settings').slideUp();
				}
			});
		});
		</script>
		<?php
	}

	/**
	 * Render PDF template selector.
	 *
	 * @since    2.0.0
	 * @param    int    $selected_id    Currently selected template ID.
	 */
	private function render_pdf_template_selector( $selected_id ) {
		global $wpdb;
		$table_name = $wpdb->prefix . 'bs_custom_mail_pdf_templates';
		$templates = $wpdb->get_results( "SELECT id, template_name FROM $table_name WHERE is_active = 1 ORDER BY template_name ASC" );

		echo '<p class="form-field"><label for="_bs_custom_mail_voucher_pdf_template">' . esc_html__( 'Gutschein-Vorlage', 'bs-custom-mail' ) . '</label>';
		echo '<select name="_bs_custom_mail_voucher_pdf_template" id="_bs_custom_mail_voucher_pdf_template" class="select short">';
		echo '<option value="">' . esc_html__( '-- Keine Vorlage --', 'bs-custom-mail' ) . '</option>';

		foreach ( $templates as $template ) {
			$selected = selected( $selected_id, $template->id, false );
			echo '<option value="' . esc_attr( $template->id ) . '" ' . $selected . '>' . esc_html( $template->template_name ) . '</option>';
		}

		echo '</select>';
		echo '<span class="description">' . esc_html__( 'Wählen Sie eine Vorlage (PDF, JPG oder PNG). Beim Kauf wird automatisch eine PDF generiert.', 'bs-custom-mail' ) . '</span>';
		echo '</p>';
	}

	/**
	 * Save product fields.
	 *
	 * @since    2.0.0
	 * @param    int    $post_id    Product ID.
	 */
	public function save_product_fields( $post_id ) {
		$is_voucher = isset( $_POST['_bs_custom_mail_voucher'] ) ? 'yes' : 'no';
		update_post_meta( $post_id, '_bs_custom_mail_voucher', $is_voucher );

		if ( isset( $_POST['_bs_custom_mail_voucher_pdf_template'] ) ) {
			update_post_meta( $post_id, '_bs_custom_mail_voucher_pdf_template', intval( $_POST['_bs_custom_mail_voucher_pdf_template'] ) );
		}

		if ( isset( $_POST['_bs_custom_mail_voucher_min_price'] ) ) {
			update_post_meta( $post_id, '_bs_custom_mail_voucher_min_price', floatval( $_POST['_bs_custom_mail_voucher_min_price'] ) );
		}

		if ( isset( $_POST['_bs_custom_mail_voucher_max_price'] ) ) {
			update_post_meta( $post_id, '_bs_custom_mail_voucher_max_price', floatval( $_POST['_bs_custom_mail_voucher_max_price'] ) );
		}
	}

	/**
	 * Add frontend fields on product page.
	 *
	 * @since    2.0.0
	 */
	public function add_frontend_fields() {
		global $product;

		if ( ! is_object( $product ) ) {
			return;
		}

		$product_id = $product->get_id();
		$is_voucher = get_post_meta( $product_id, '_bs_custom_mail_voucher', true );

		if ( $is_voucher !== 'yes' ) {
			return;
		}

		$min_price = get_post_meta( $product_id, '_bs_custom_mail_voucher_min_price', true ) ?: 10;
		$max_price = get_post_meta( $product_id, '_bs_custom_mail_voucher_max_price', true ) ?: 1000;

		?>
		<div class="bs-voucher-frontend" style="margin: 25px 0; padding: 25px; background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%); border-radius: 16px; box-shadow: 0 4px 20px rgba(0,0,0,0.08); border: 2px solid #2271b1;">
			<h4 style="margin: 0 0 20px 0; color: #1e293b; font-size: 18px;">🎁 <?php _e( 'Gutschein personalisieren', 'bs-custom-mail' ); ?></h4>

			<div style="display: grid; gap: 20px;">
				<!-- Voucher Value -->
				<div>
					<label for="bs_voucher_value" style="font-weight: 600; display: block; margin-bottom: 8px; color: #374151;">
						<?php _e( 'Gutscheinwert (€)', 'bs-custom-mail' ); ?> *
					</label>
					<div style="position: relative;">
						<input type="number"
							   id="bs_voucher_value"
							   name="bs_voucher_value"
							   step="0.01"
							   min="<?php echo esc_attr( $min_price ); ?>"
							   max="<?php echo esc_attr( $max_price ); ?>"
							   placeholder="z.B. 150"
							   required
							   style="width: 100%; padding: 16px 16px 16px 40px; font-size: 20px; border: 2px solid #e2e8f0; border-radius: 12px; text-align: left; font-weight: bold;">
						<span style="position: absolute; left: 16px; top: 50%; transform: translateY(-50%); font-size: 20px; color: #6b7280;">€</span>
					</div>
					<small style="color: #6b7280; font-size: 12px; display: block; margin-top: 8px;">
						<?php printf( __( 'Mindestbetrag: %s€ | Höchstbetrag: %s€', 'bs-custom-mail' ), $min_price, $max_price ); ?>
					</small>
				</div>

				<!-- Recipient Email -->
				<div>
					<label for="bs_voucher_recipient" style="font-weight: 600; display: block; margin-bottom: 8px; color: #374151;">
						<?php _e( 'Empfänger E-Mail (optional)', 'bs-custom-mail' ); ?>
					</label>
					<input type="email"
						   id="bs_voucher_recipient"
						   name="bs_voucher_recipient"
						   placeholder="geschenk@freund.de"
						   style="width: 100%; padding: 16px; font-size: 16px; border: 2px solid #e2e8f0; border-radius: 12px;">
					<small style="color: #6b7280; font-size: 12px; display: block; margin-top: 8px;">
						<?php _e( 'Wenn leer, wird der Gutschein an Ihre E-Mail-Adresse gesendet.', 'bs-custom-mail' ); ?>
					</small>
				</div>

				<!-- Recipient Name -->
				<div>
					<label for="bs_voucher_recipient_name" style="font-weight: 600; display: block; margin-bottom: 8px; color: #374151;">
						<?php _e( 'Empfänger Name (optional)', 'bs-custom-mail' ); ?>
					</label>
					<input type="text"
						   id="bs_voucher_recipient_name"
						   name="bs_voucher_recipient_name"
						   placeholder="Max Mustermann"
						   style="width: 100%; padding: 16px; font-size: 16px; border: 2px solid #e2e8f0; border-radius: 12px;">
				</div>

				<!-- Personal Message -->
				<div>
					<label for="bs_voucher_message" style="font-weight: 600; display: block; margin-bottom: 8px; color: #374151;">
						<?php _e( 'Persönliche Nachricht (optional)', 'bs-custom-mail' ); ?>
					</label>
					<textarea id="bs_voucher_message"
							  name="bs_voucher_message"
							  rows="3"
							  placeholder="Alles Gute zum Geburtstag!"
							  style="width: 100%; padding: 16px; font-size: 16px; border: 2px solid #e2e8f0; border-radius: 12px; resize: vertical;"></textarea>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Add cart item data.
	 *
	 * @since    2.0.0
	 * @param    array    $cart_item_data    Cart item data.
	 * @param    int      $product_id        Product ID.
	 * @param    int      $variation_id      Variation ID.
	 * @return   array
	 */
	public function add_cart_item_data( $cart_item_data, $product_id, $variation_id ) {
		if ( get_post_meta( $product_id, '_bs_custom_mail_voucher', true ) !== 'yes' ) {
			return $cart_item_data;
		}

		if ( isset( $_POST['bs_voucher_value'] ) ) {
			$cart_item_data['bs_voucher_value'] = floatval( sanitize_text_field( $_POST['bs_voucher_value'] ) );
		}

		if ( isset( $_POST['bs_voucher_recipient'] ) ) {
			$cart_item_data['bs_voucher_recipient'] = sanitize_email( $_POST['bs_voucher_recipient'] );
		}

		if ( isset( $_POST['bs_voucher_recipient_name'] ) ) {
			$cart_item_data['bs_voucher_recipient_name'] = sanitize_text_field( $_POST['bs_voucher_recipient_name'] );
		}

		if ( isset( $_POST['bs_voucher_message'] ) ) {
			$cart_item_data['bs_voucher_message'] = sanitize_textarea_field( $_POST['bs_voucher_message'] );
		}

		$cart_item_data['bs_voucher_key'] = wp_generate_password( 12, false );

		return $cart_item_data;
	}

	/**
	 * Display cart item data.
	 *
	 * @since    2.0.0
	 * @param    array    $item_data    Item data.
	 * @param    array    $cart_item    Cart item.
	 * @return   array
	 */
	public function display_cart_item_data( $item_data, $cart_item ) {
		if ( isset( $cart_item['bs_voucher_value'] ) ) {
			$item_data[] = array(
				'key' => __( 'Gutscheinwert', 'bs-custom-mail' ),
				'value' => wc_price( $cart_item['bs_voucher_value'] )
			);
		}

		if ( ! empty( $cart_item['bs_voucher_recipient'] ) ) {
			$item_data[] = array(
				'key' => __( 'Empfänger', 'bs-custom-mail' ),
				'value' => esc_html( $cart_item['bs_voucher_recipient'] )
			);
		}

		return $item_data;
	}

	/**
	 * Set cart item price.
	 *
	 * @since    2.0.0
	 * @param    WC_Cart    $cart    Cart object.
	 */
	public function set_cart_item_price( $cart ) {
		if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
			return;
		}

		foreach ( $cart->get_cart() as $cart_item ) {
			if ( isset( $cart_item['bs_voucher_value'] ) ) {
				$cart_item['data']->set_price( $cart_item['bs_voucher_value'] );
			}
		}
	}

	/**
	 * Save order item meta.
	 *
	 * @since    2.0.0
	 * @param    WC_Order_Item_Product    $item           Order item.
	 * @param    string                   $cart_item_key  Cart item key.
	 * @param    array                    $values         Cart item values.
	 * @param    WC_Order                 $order          Order object.
	 */
	public function save_order_item_meta( $item, $cart_item_key, $values, $order ) {
		if ( isset( $values['bs_voucher_value'] ) ) {
			$item->add_meta_data( '_bs_voucher_value', $values['bs_voucher_value'], true );
		}
		if ( isset( $values['bs_voucher_recipient'] ) ) {
			$item->add_meta_data( '_bs_voucher_recipient', $values['bs_voucher_recipient'], true );
		}
		if ( isset( $values['bs_voucher_recipient_name'] ) ) {
			$item->add_meta_data( '_bs_voucher_recipient_name', $values['bs_voucher_recipient_name'], true );
		}
		if ( isset( $values['bs_voucher_message'] ) ) {
			$item->add_meta_data( '_bs_voucher_message', $values['bs_voucher_message'], true );
		}
		if ( isset( $values['bs_voucher_key'] ) ) {
			$item->add_meta_data( '_bs_voucher_key', $values['bs_voucher_key'], true );
		}
	}

	/**
	 * Generate voucher after order.
	 *
	 * @since    2.0.0
	 * @param    int    $order_id    Order ID.
	 */
	public function generate_voucher( $order_id ) {
		$order = wc_get_order( $order_id );

		if ( ! $order ) {
			return;
		}

		// Check if already generated
		$vouchers_generated = $order->get_meta( '_bs_vouchers_generated' );
		if ( $vouchers_generated === 'yes' ) {
			return;
		}

		$generated_vouchers = array();

		foreach ( $order->get_items() as $item_id => $item ) {
			$product_id = $item->get_product_id();

			if ( get_post_meta( $product_id, '_bs_custom_mail_voucher', true ) !== 'yes' ) {
				continue;
			}

			$voucher_value = $item->get_meta( '_bs_voucher_value' ) ?: $item->get_total();
			$recipient = $item->get_meta( '_bs_voucher_recipient' );
			$recipient_name = $item->get_meta( '_bs_voucher_recipient_name' );
			$message = $item->get_meta( '_bs_voucher_message' );

			// Generate unique code
			$coupon_code = $this->generate_unique_code();

			// Create WooCommerce coupon
			$coupon = new WC_Coupon();
			$coupon->set_code( $coupon_code );
			$coupon->set_discount_type( 'fixed_cart' );
			$coupon->set_amount( $voucher_value );
			$coupon->set_usage_limit( 1 );
			$coupon->set_individual_use( true );
			$coupon->set_description( sprintf( __( 'Wertgutschein für Bestellung #%s', 'bs-custom-mail' ), $order->get_order_number() ) );

			$expiry_date = date( 'Y-m-d', strtotime( '+3 years' ) );
			$coupon->set_date_expires( $expiry_date );
			$coupon->save();

			// Generate PDF
			$pdf_result = $this->generate_voucher_pdf( $product_id, $coupon_code, $voucher_value, $recipient_name, $expiry_date );

			// Save to database
			$voucher_id = $this->save_voucher( array(
				'order_id' => $order_id,
				'order_item_id' => $item_id,
				'product_id' => $product_id,
				'voucher_code' => $coupon_code,
				'voucher_value' => $voucher_value,
				'recipient_email' => $recipient ?: $order->get_billing_email(),
				'recipient_name' => $recipient_name,
				'personal_message' => $message,
				'pdf_path' => $pdf_result ? $pdf_result['path'] : null,
				'expiry_date' => $expiry_date,
				'status' => 'active'
			) );

			$voucher_info = array(
				'id' => $voucher_id,
				'code' => $coupon_code,
				'value' => $voucher_value,
				'recipient' => $recipient,
				'recipient_name' => $recipient_name,
				'message' => $message,
				'pdf_path' => $pdf_result ? $pdf_result['path'] : null,
				'expiry_date' => $expiry_date
			);

			$generated_vouchers[] = $voucher_info;

			// Send email
			$this->send_voucher_email( $voucher_info, $order );

			// Add order note
			$note = sprintf(
				__( 'Wertgutschein erstellt: %s (%s)', 'bs-custom-mail' ),
				$coupon_code,
				wc_price( $voucher_value )
			);
			if ( $recipient ) {
				$note .= sprintf( __( ' → Gesendet an: %s', 'bs-custom-mail' ), $recipient );
			}
			$order->add_order_note( $note );
		}

		if ( ! empty( $generated_vouchers ) ) {
			$order->update_meta_data( '_bs_vouchers_generated', 'yes' );
			$order->update_meta_data( '_bs_vouchers_list', $generated_vouchers );
			$order->save();
		}
	}

	/**
	 * Generate unique voucher code.
	 *
	 * @since    2.0.0
	 * @return   string
	 */
	private function generate_unique_code() {
		$prefix = get_option( 'bs_custom_mail_voucher_prefix', 'WERT' );
		$max_attempts = 10;
		$attempt = 0;

		do {
			$code = $prefix . '-' . strtoupper( wp_generate_password( 8, false ) );
			$attempt++;

			global $wpdb;
			$table_name = $wpdb->prefix . 'bs_custom_mail_vouchers';
			$existing = $wpdb->get_var( $wpdb->prepare(
				"SELECT id FROM $table_name WHERE voucher_code = %s",
				$code
			) );

			if ( ! $existing ) {
				return $code;
			}
		} while ( $attempt < $max_attempts );

		return $prefix . '-' . strtoupper( wp_generate_password( 6, false ) ) . '-' . time();
	}

	/**
	 * Generate voucher PDF.
	 *
	 * @since    2.0.0
	 * @param    int       $product_id       Product ID.
	 * @param    string    $code             Voucher code.
	 * @param    float     $value            Voucher value.
	 * @param    string    $recipient_name   Recipient name.
	 * @param    string    $expiry_date      Expiry date.
	 * @return   array|false
	 */
	private function generate_voucher_pdf( $product_id, $code, $value, $recipient_name, $expiry_date ) {
		$pdf_template_id = get_post_meta( $product_id, '_bs_custom_mail_voucher_pdf_template', true );

		if ( ! $pdf_template_id ) {
			return false;
		}

		global $wpdb;
		$table_name = $wpdb->prefix . 'bs_custom_mail_pdf_templates';
		$template = $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM $table_name WHERE id = %d",
			$pdf_template_id
		) );

		if ( ! $template ) {
			return false;
		}

		$pdf_path = get_attached_file( $template->attachment_id );
		if ( ! $pdf_path || ! file_exists( $pdf_path ) ) {
			return false;
		}

		// Load PDF generator
		if ( ! class_exists( 'Bs_Custom_Mail_PDF_Generator' ) ) {
			require_once plugin_dir_path( __FILE__ ) . 'class-bs-custom-mail-pdf-generator.php';
		}

		$generator = new Bs_Custom_Mail_PDF_Generator();
		return $generator->generate( $pdf_path, $template->template_config, array(
			'wert' => $value,
			'code' => $code,
			'name' => $recipient_name,
			'expiry' => date_i18n( get_option( 'date_format' ), strtotime( $expiry_date ) ),
			'font_size' => $template->font_size
		) );
	}

	/**
	 * Save voucher to database.
	 *
	 * @since    2.0.0
	 * @param    array    $data    Voucher data.
	 * @return   int
	 */
	private function save_voucher( $data ) {
		global $wpdb;
		$table_name = $wpdb->prefix . 'bs_custom_mail_vouchers';

		$wpdb->insert( $table_name, $data );
		return $wpdb->insert_id;
	}

	/**
	 * Send voucher email.
	 *
	 * @since    2.0.0
	 * @param    array       $voucher_info    Voucher info.
	 * @param    WC_Order    $order           Order object.
	 * @return   bool
	 */
	private function send_voucher_email( $voucher_info, $order ) {
		$to = $voucher_info['recipient'] ?: $order->get_billing_email();

		if ( ! is_email( $to ) ) {
			return false;
		}

		// Use email sender class
		if ( ! class_exists( 'Bs_Custom_Mail_Email_Sender' ) ) {
			require_once plugin_dir_path( __FILE__ ) . 'class-bs-custom-mail-email-sender.php';
		}

		$sender = new Bs_Custom_Mail_Email_Sender( $this->version );

		// Prepare voucher data for email
		$voucher_data = array(
			'gutschein_code' => $voucher_info['code'],
			'gutschein_wert' => wc_price( $voucher_info['value'] ),
			'gutschein_ablauf' => date_i18n( get_option( 'date_format' ), strtotime( $voucher_info['expiry_date'] ) ),
			'empfaenger_name' => $voucher_info['recipient_name'] ?: $order->get_billing_first_name(),
			'persoenliche_nachricht' => $voucher_info['message'],
			'pdf_url' => $voucher_info['pdf_path'] ? $this->upload_url . basename( $voucher_info['pdf_path'] ) : ''
		);

		return $sender->send_voucher_email( $to, $voucher_data, $order );
	}

	/**
	 * Cancel voucher.
	 *
	 * @since    2.0.0
	 * @param    int    $order_id    Order ID.
	 */
	public function cancel_voucher( $order_id ) {
		$order = wc_get_order( $order_id );

		if ( ! $order ) {
			return;
		}

		global $wpdb;
		$table_name = $wpdb->prefix . 'bs_custom_mail_vouchers';

		$vouchers = $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM $table_name WHERE order_id = %d",
			$order_id
		) );

		foreach ( $vouchers as $voucher ) {
			// Disable WooCommerce coupon
			$coupon_id = wc_get_coupon_id_by_code( $voucher->voucher_code );
			if ( $coupon_id ) {
				$coupon = new WC_Coupon( $coupon_id );
				$coupon->set_usage_count( $coupon->get_usage_limit() );
				$coupon->save();
			}

			// Update voucher status
			$wpdb->update(
				$table_name,
				array( 'status' => 'cancelled' ),
				array( 'id' => $voucher->id )
			);

			$order->add_order_note( sprintf(
				__( 'Wertgutschein %s wurde aufgrund von Stornierung ungültig gemacht.', 'bs-custom-mail' ),
				$voucher->voucher_code
			) );
		}
	}

	/**
	 * Admin scripts.
	 *
	 * @since    2.0.0
	 * @param    string    $hook    Current admin page.
	 */
	public function admin_scripts( $hook ) {
		if ( $hook === 'post.php' || $hook === 'post-new.php' ) {
			global $post;
			if ( isset( $post ) && $post->post_type === 'product' ) {
				wp_enqueue_media();
			}
		}
	}
}
