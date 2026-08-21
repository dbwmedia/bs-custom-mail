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

		// Make voucher products purchasable even without a fixed price set.
		add_filter( 'woocommerce_is_purchasable', array( $this, 'make_voucher_purchasable' ), 10, 2 );

		// Frontend fields
		add_action( 'woocommerce_before_add_to_cart_button', array( $this, 'add_frontend_fields' ) );

		// Cart functionality
		add_filter( 'woocommerce_add_to_cart_validation', array( $this, 'validate_add_to_cart' ), 10, 3 );
		add_filter( 'woocommerce_add_cart_item_data', array( $this, 'add_cart_item_data' ), 10, 3 );
		add_filter( 'woocommerce_get_item_data', array( $this, 'display_cart_item_data' ), 10, 2 );
		add_action( 'woocommerce_before_calculate_totals', array( $this, 'set_cart_item_price' ) );
		add_action( 'woocommerce_checkout_create_order_line_item', array( $this, 'save_order_item_meta' ), 10, 4 );

		// Express checkout guard. Express buttons (Apple Pay / Google Pay) add
		// the product straight to the cart with their own AJAX request, which
		// does not necessarily carry the voucher fields. Until that is verified
		// per gateway, express buttons are hidden on voucher products so a
		// voucher can never be bought without its value and gift data.
		add_filter( 'wcpay_payment_request_is_product_supported', array( $this, 'filter_express_product_support' ), 10, 2 );
		add_filter( 'wcpay_express_checkout_button_is_supported_product', array( $this, 'filter_express_product_support' ), 10, 2 );
		add_filter( 'wcpay_payment_request_should_show_express_checkout_button', array( $this, 'filter_express_button_visibility' ), 10, 1 );
		add_action( 'wp_head', array( $this, 'print_express_guard_styles' ), 99 );

		// Voucher generation now runs exclusively through the async queue
		// (Bs_Custom_Mail_Queue). No coupon, PDF or mail work happens in the
		// checkout request any more.

		// Voucher cancellation
		add_action( 'woocommerce_order_status_cancelled', array( $this, 'cancel_voucher' ) );
		add_action( 'woocommerce_order_status_refunded', array( $this, 'cancel_voucher' ) );
		add_action( 'woocommerce_order_partially_refunded', array( $this, 'flag_partial_refund' ), 10, 2 );

		// Sync voucher status when a coupon from this plugin is redeemed.
		add_action( 'woocommerce_order_status_processing', array( $this, 'sync_voucher_status_on_order' ) );
		add_action( 'woocommerce_order_status_completed', array( $this, 'sync_voucher_status_on_order' ) );

		// Admin scripts
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_scripts' ) );
	}

	/**
	 * Whether a product is one of our voucher products.
	 *
	 * @since    3.0.0
	 * @param    int    $product_id    Product ID.
	 * @return   bool
	 */
	public static function is_voucher_product( $product_id ) {
		return 'yes' === get_post_meta( (int) $product_id, '_bs_custom_mail_voucher', true );
	}

	/**
	 * Disable express checkout support for voucher products.
	 *
	 * @since    3.0.0
	 * @param    bool       $supported  Current support state.
	 * @param    WC_Product $product    Product object.
	 * @return   bool
	 */
	public function filter_express_product_support( $supported, $product ) {
		if ( ! $this->express_guard_enabled() ) {
			return $supported;
		}

		if ( is_object( $product ) && method_exists( $product, 'get_id' ) && self::is_voucher_product( $product->get_id() ) ) {
			return false;
		}

		return $supported;
	}

	/**
	 * Hide the express checkout button on voucher product pages.
	 *
	 * @since    3.0.0
	 * @param    bool    $show    Current visibility.
	 * @return   bool
	 */
	public function filter_express_button_visibility( $show ) {
		if ( ! $this->express_guard_enabled() ) {
			return $show;
		}

		if ( function_exists( 'is_product' ) && is_product() ) {
			global $product;
			if ( is_object( $product ) && self::is_voucher_product( $product->get_id() ) ) {
				return false;
			}
		}

		return $show;
	}

	/**
	 * Whether the express checkout guard is active.
	 *
	 * Can be switched off in the settings once express checkout has been
	 * verified to carry the voucher fields on this shop.
	 *
	 * @since    3.0.0
	 * @return   bool
	 */
	private function express_guard_enabled() {
		return 'no' !== get_option( 'bs_custom_mail_express_guard', 'yes' );
	}

	/**
	 * Hide express checkout buttons on voucher product pages.
	 *
	 * The PHP filters above only fire on the filter names a given WooPayments
	 * release happens to use, and those were renamed when the plugin moved from
	 * the Payment Request Button to the Express Checkout Element. The button is
	 * injected by JavaScript after Stripe confirms the browser supports Apple
	 * Pay, so it is not in the server rendered HTML at all. This rule is the
	 * version independent backstop.
	 *
	 * @since    3.0.0
	 */
	public function print_express_guard_styles() {
		if ( ! $this->express_guard_enabled() || ! function_exists( 'is_product' ) || ! is_product() ) {
			return;
		}

		global $product;

		if ( ! is_object( $product ) || ! self::is_voucher_product( $product->get_id() ) ) {
			return;
		}

		echo '<style id="bs-voucher-express-guard">'
			. '#wcpay-payment-request-wrapper,'
			. '#wcpay-express-checkout-wrapper,'
			. '#wcpay-payment-request-button-separator,'
			. '#wcpay-express-checkout-button-separator,'
			. '.wcpay-payment-request-wrapper,'
			. '.wc-block-components-express-payment,'
			. '.wc-block-components-express-payment-continue-rule'
			. '{display:none !important;}'
			. '</style>';
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
		$fixed_price = get_post_meta( $post->ID, '_bs_custom_mail_voucher_fixed_price', true );
		$fixed_price_value = get_post_meta( $post->ID, '_bs_custom_mail_voucher_fixed_price_value', true ) ?: '';
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

		// Fixed price option
		woocommerce_wp_checkbox( array(
			'id' => '_bs_custom_mail_voucher_fixed_price',
			'label' => __( 'Fester Gutscheinwert', 'bs-custom-mail' ),
			'description' => __( 'Aktivieren, um einen festen Wert für diesen Gutschein festzulegen (statt variabel vom Kunden eingegeben).', 'bs-custom-mail' ),
			'value' => $fixed_price
		) );

		// Fixed price value
		echo '<div class="bs-voucher-fixed-price" style="margin: 15px 0; padding: 15px; background: #fff; border-radius: 6px; ' . ( $fixed_price !== 'yes' ? 'display:none;' : '' ) . '">';
		woocommerce_wp_text_input( array(
			'id' => '_bs_custom_mail_voucher_fixed_price_value',
			'label' => __( 'Fester Wert (€)', 'bs-custom-mail' ),
			'type' => 'number',
			'value' => $fixed_price_value,
			'custom_attributes' => array( 'min' => '1', 'step' => '0.01' ),
			'desc_tip' => true,
			'description' => __( 'Der Gutschein hat immer diesen Wert, unabhängig vom Produktpreis.', 'bs-custom-mail' )
		) );
		echo '</div>';

		// Price range (variable)
		echo '<div class="bs-voucher-price-range" style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-top: 15px; ' . ( $fixed_price === 'yes' ? 'display:none;' : '' ) . '">';
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

			$('#_bs_custom_mail_voucher_fixed_price').on('change', function() {
				if ($(this).is(':checked')) {
					$('.bs-voucher-fixed-price').slideDown();
					$('.bs-voucher-price-range').slideUp();
				} else {
					$('.bs-voucher-fixed-price').slideUp();
					$('.bs-voucher-price-range').slideDown();
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

		// Fixed price option
		$fixed_price = isset( $_POST['_bs_custom_mail_voucher_fixed_price'] ) ? 'yes' : 'no';
		update_post_meta( $post_id, '_bs_custom_mail_voucher_fixed_price', $fixed_price );

		if ( isset( $_POST['_bs_custom_mail_voucher_fixed_price_value'] ) ) {
			update_post_meta( $post_id, '_bs_custom_mail_voucher_fixed_price_value', floatval( $_POST['_bs_custom_mail_voucher_fixed_price_value'] ) );
		}

		if ( isset( $_POST['_bs_custom_mail_voucher_min_price'], $_POST['_bs_custom_mail_voucher_max_price'] ) ) {
			$min = max( 0.01, floatval( $_POST['_bs_custom_mail_voucher_min_price'] ) );
			$max = max( 0.01, floatval( $_POST['_bs_custom_mail_voucher_max_price'] ) );
			if ( $min > $max ) {
				$min = $max;
			}
			update_post_meta( $post_id, '_bs_custom_mail_voucher_min_price', $min );
			update_post_meta( $post_id, '_bs_custom_mail_voucher_max_price', $max );
		}
	}

	/**
	 * Make voucher products purchasable regardless of their WooCommerce price.
	 *
	 * Without this filter, WooCommerce hides the add-to-cart form for products
	 * with no price set. The actual price is set at cart-level via set_cart_item_price().
	 *
	 * @since    2.2.0
	 * @param    bool       $purchasable Current purchasable state.
	 * @param    WC_Product $product     Product object.
	 * @return   bool
	 */
	public function make_voucher_purchasable( $purchasable, $product ) {
		if ( get_post_meta( $product->get_id(), '_bs_custom_mail_voucher', true ) === 'yes' ) {
			return true;
		}

		return $purchasable;
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

		// Check for fixed price
		$fixed_price = get_post_meta( $product_id, '_bs_custom_mail_voucher_fixed_price', true );
		$fixed_price_value = get_post_meta( $product_id, '_bs_custom_mail_voucher_fixed_price_value', true );
		$has_fixed_price = ( $fixed_price === 'yes' && ! empty( $fixed_price_value ) );

		$min_price = get_post_meta( $product_id, '_bs_custom_mail_voucher_min_price', true ) ?: 10;
		$max_price = get_post_meta( $product_id, '_bs_custom_mail_voucher_max_price', true ) ?: 1000;

		// Generate unique ID for this instance
		$instance_id = 'bs-voucher-' . $product_id;
		?>
		<div class="bs-voucher-container" style="margin: 30px 0; border: 1px solid #e5e7eb; border-radius: 12px; background: #fff;">
			<!-- Header -->
			<div style="padding: 20px 24px; border-bottom: 1px solid #f3f4f6; display: flex; align-items: center; gap: 12px;">
				<div style="width: 40px; height: 40px; background: #000; border-radius: 10px; display: flex; align-items: center; justify-content: center; color: #fff; font-size: 20px;">🎁</div>
				<div>
					<h4 style="margin: 0; font-size: 16px; font-weight: 600; color: #111827;"><?php _e( 'Gutschein', 'bs-custom-mail' ); ?></h4>
					<p style="margin: 2px 0 0; font-size: 13px; color: #6b7280;"><?php _e( 'Personalisiere deinen Geschenkgutschein', 'bs-custom-mail' ); ?></p>
				</div>
			</div>

			<div style="padding: 24px;">
				<!-- Voucher Value Section -->
				<div style="margin-bottom: 24px;">
					<?php if ( $has_fixed_price ) : ?>
						<!-- Fixed Price Display -->
						<div style="background: #f9fafb; border: 2px solid #000; border-radius: 12px; padding: 24px; text-align: center;">
							<span style="display: block; font-size: 13px; color: #6b7280; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 8px;"><?php _e( 'Gutscheinwert', 'bs-custom-mail' ); ?></span>
							<div style="font-size: 42px; font-weight: 800; color: #000; line-height: 1;">
								<?php echo wc_price( $fixed_price_value ); ?>
							</div>
							<?php if ( $product->get_price() != $fixed_price_value ) : ?>
								<div style="margin-top: 8px; font-size: 13px; color: #6b7280;">
									<?php printf( __( 'Produktpreis: %s', 'bs-custom-mail' ), $product->get_price_html() ); ?>
								</div>
							<?php endif; ?>
							<input type="hidden" name="bs_voucher_value" value="<?php echo esc_attr( $fixed_price_value ); ?>">
						</div>
					<?php else : ?>
						<!-- Variable Price Input -->
						<label style="display: block; font-size: 13px; font-weight: 600; color: #374151; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 10px;">
							<?php _e( 'Gutscheinwert', 'bs-custom-mail' ); ?> *
						</label>
						<div style="position: relative;">
							<input type="number"
								   id="bs_voucher_value_<?php echo $instance_id; ?>"
								   name="bs_voucher_value"
								   step="0.01"
								   min="<?php echo esc_attr( $min_price ); ?>"
								   max="<?php echo esc_attr( $max_price ); ?>"
								   placeholder="0.00"
								   required
								   style="width: 100%; padding: 16px 16px 16px 48px; font-size: 24px; font-weight: 600; border: 1px solid #e5e7eb; border-radius: 10px; transition: border-color 0.2s;"
								   onfocus="this.style.borderColor='#000'"
								   onblur="this.style.borderColor='#e5e7eb'">
							<span style="position: absolute; left: 20px; top: 50%; transform: translateY(-50%); font-size: 20px; color: #6b7280; font-weight: 500;">€</span>
						</div>
						<div style="display: flex; gap: 16px; margin-top: 10px;">
							<span style="font-size: 12px; color: #6b7280;"><?php printf( __( 'Min: %s€', 'bs-custom-mail' ), $min_price ); ?></span>
							<span style="font-size: 12px; color: #6b7280;"><?php printf( __( 'Max: %s€', 'bs-custom-mail' ), $max_price ); ?></span>
						</div>
					<?php endif; ?>
				</div>

				<!-- Gift Toggle -->
				<div style="border-top: 1px solid #f3f4f6; padding-top: 20px;">
					<label style="display: flex; align-items: center; gap: 12px; cursor: pointer;">
						<input type="checkbox" 
							   id="bs_voucher_gift_toggle_<?php echo $instance_id; ?>"
							   style="width: 20px; height: 20px; accent-color: #000; cursor: pointer;"
							   onchange="document.getElementById('bs_voucher_gift_fields_<?php echo $instance_id; ?>').style.display = this.checked ? 'block' : 'none'">
						<span style="font-size: 15px; font-weight: 500; color: #111827;"><?php _e( 'Als Geschenk versenden', 'bs-custom-mail' ); ?></span>
					</label>
				</div>

				<!-- Gift Fields (Hidden by default) -->
				<div id="bs_voucher_gift_fields_<?php echo $instance_id; ?>" style="display: none; margin-top: 20px; padding-top: 20px; border-top: 1px solid #f3f4f6;">
					<div style="display: grid; gap: 16px;">
						<!-- Recipient Email -->
						<div>
							<label for="bs_voucher_recipient_<?php echo $instance_id; ?>" style="display: block; font-size: 13px; font-weight: 500; color: #374151; margin-bottom: 6px;">
								<?php _e( 'E-Mail des Empfängers', 'bs-custom-mail' ); ?>
							</label>
							<input type="email"
								   id="bs_voucher_recipient_<?php echo $instance_id; ?>"
								   name="bs_voucher_recipient"
								   placeholder="max.mustermann@beispiel.de"
								   style="width: 100%; padding: 12px 16px; font-size: 15px; border: 1px solid #e5e7eb; border-radius: 8px; transition: border-color 0.2s;"
								   onfocus="this.style.borderColor='#000'"
								   onblur="this.style.borderColor='#e5e7eb'">
							<span style="font-size: 12px; color: #9ca3af; margin-top: 4px; display: block;"><?php _e( 'Wenn leer, erhalten Sie den Gutschein', 'bs-custom-mail' ); ?></span>
						</div>

						<!-- Recipient Name -->
						<div>
							<label for="bs_voucher_recipient_name_<?php echo $instance_id; ?>" style="display: block; font-size: 13px; font-weight: 500; color: #374151; margin-bottom: 6px;">
								<?php _e( 'Name des Empfängers', 'bs-custom-mail' ); ?>
							</label>
							<input type="text"
								   id="bs_voucher_recipient_name_<?php echo $instance_id; ?>"
								   name="bs_voucher_recipient_name"
								   placeholder="Max Mustermann"
								   style="width: 100%; padding: 12px 16px; font-size: 15px; border: 1px solid #e5e7eb; border-radius: 8px; transition: border-color 0.2s;"
								   onfocus="this.style.borderColor='#000'"
								   onblur="this.style.borderColor='#e5e7eb'">
						</div>

						<!-- Personal Message -->
						<div>
							<label for="bs_voucher_message_<?php echo $instance_id; ?>" style="display: block; font-size: 13px; font-weight: 500; color: #374151; margin-bottom: 6px;">
								<?php _e( 'Persönliche Nachricht', 'bs-custom-mail' ); ?>
							</label>
							<textarea id="bs_voucher_message_<?php echo $instance_id; ?>"
									  name="bs_voucher_message"
									  rows="3"
									  placeholder="Herzlichen Glückwunsch zum Geburtstag! 🎉"
									  style="width: 100%; padding: 12px 16px; font-size: 15px; border: 1px solid #e5e7eb; border-radius: 8px; resize: vertical; transition: border-color 0.2s;"
									  onfocus="this.style.borderColor='#000'"
									  onblur="this.style.borderColor='#e5e7eb'"></textarea>
						</div>
					</div>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Block adding a voucher to the cart when its data is invalid.
	 *
	 * Previously an invalid amount only produced a notice while the product
	 * still landed in the cart — with a price of 0 for products that have no
	 * WooCommerce price of their own.
	 *
	 * @since    3.0.0
	 * @param    bool    $passed        Current validation state.
	 * @param    int     $product_id    Product ID.
	 * @param    int     $quantity      Quantity.
	 * @return   bool
	 */
	public function validate_add_to_cart( $passed, $product_id, $quantity ) {
		if ( ! self::is_voucher_product( $product_id ) ) {
			return $passed;
		}

		// A fixed price voucher needs no input at all.
		if ( $this->get_fixed_price( $product_id ) ) {
			return $passed;
		}

		$min = floatval( get_post_meta( $product_id, '_bs_custom_mail_voucher_min_price', true ) ?: 10 );
		$max = floatval( get_post_meta( $product_id, '_bs_custom_mail_voucher_max_price', true ) ?: 1000 );

		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$raw = isset( $_POST['bs_voucher_value'] ) ? sanitize_text_field( wp_unslash( $_POST['bs_voucher_value'] ) ) : '';

		if ( '' === $raw ) {
			wc_add_notice( __( 'Bitte gib einen Gutscheinwert an.', 'bs-custom-mail' ), 'error' );
			return false;
		}

		$value = floatval( str_replace( ',', '.', $raw ) );

		if ( $value < $min || $value > $max ) {
			wc_add_notice(
				sprintf(
					/* translators: 1: minimum amount, 2: maximum amount */
					__( 'Bitte gib einen Gutscheinwert zwischen %1$s und %2$s ein.', 'bs-custom-mail' ),
					wp_strip_all_tags( wc_price( $min ) ),
					wp_strip_all_tags( wc_price( $max ) )
				),
				'error'
			);
			return false;
		}

		// A voucher must never be paid for with a different voucher.
		if ( ! empty( $_POST['bs_voucher_recipient'] ) && ! is_email( wp_unslash( $_POST['bs_voucher_recipient'] ) ) ) {
			wc_add_notice( __( 'Die E-Mail-Adresse des Empfängers ist ungültig.', 'bs-custom-mail' ), 'error' );
			return false;
		}

		return $passed;
	}

	/**
	 * Resolve the configured fixed price of a voucher product.
	 *
	 * @since    3.0.0
	 * @param    int    $product_id    Product ID.
	 * @return   float                 The fixed price, or 0.0 when variable.
	 */
	private function get_fixed_price( $product_id ) {
		if ( 'yes' !== get_post_meta( $product_id, '_bs_custom_mail_voucher_fixed_price', true ) ) {
			return 0.0;
		}

		return (float) get_post_meta( $product_id, '_bs_custom_mail_voucher_fixed_price_value', true );
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
		if ( ! self::is_voucher_product( $product_id ) ) {
			return $cart_item_data;
		}

		$fixed_price_value = $this->get_fixed_price( $product_id );

		if ( $fixed_price_value > 0 ) {
			$cart_item_data['bs_voucher_value'] = $fixed_price_value;
			// phpcs:ignore WordPress.Security.NonceVerification.Missing
		} elseif ( isset( $_POST['bs_voucher_value'] ) ) {
			// Already validated in validate_add_to_cart().
			// phpcs:ignore WordPress.Security.NonceVerification.Missing
			$raw   = sanitize_text_field( wp_unslash( $_POST['bs_voucher_value'] ) );
			$value = floatval( str_replace( ',', '.', $raw ) );
			$min   = floatval( get_post_meta( $product_id, '_bs_custom_mail_voucher_min_price', true ) ?: 10 );
			$max   = floatval( get_post_meta( $product_id, '_bs_custom_mail_voucher_max_price', true ) ?: 1000 );

			if ( $value < $min || $value > $max ) {
				return $cart_item_data;
			}

			$cart_item_data['bs_voucher_value'] = $value;
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
	 * Kept for backwards compatibility with any third party code that called
	 * this directly. Production flow goes through Bs_Custom_Mail_Queue.
	 *
	 * @since    2.0.0
	 * @param    int    $order_id    Order ID.
	 */
	public function generate_voucher( $order_id ) {
		$this->process_order_vouchers( $order_id, 'legacy' );
	}

	/**
	 * Generate (or resume generating) all vouchers for an order.
	 *
	 * Fully idempotent and resumable: it can be called any number of times for
	 * the same order without ever creating a duplicate WooCommerce coupon. Each
	 * step (coupon, PDF, DB record, email) is guarded individually, so a run
	 * that was aborted mid-way (e.g. the PPCP race condition that kills the
	 * checkout request during PDF generation) is completed cleanly on the next
	 * call instead of starting over.
	 *
	 * @since    2.1.0
	 * @since    3.0.0 Returns a success flag; the "generated" marker is only
	 *                 written once every step has actually succeeded.
	 * @param    int       $order_id    Order ID.
	 * @param    string    $context     Calling context, for logging.
	 * @return   bool                   True when the order's vouchers are fully settled.
	 */
	public function process_order_vouchers( $order_id, $context = 'job' ) {
		$order = wc_get_order( $order_id );

		if ( ! $order ) {
			return false;
		}

		// Already fully processed — nothing to do.
		if ( $order->get_meta( '_bs_vouchers_generated' ) === 'yes' ) {
			return true;
		}

		// Preserve vouchers already recorded on an earlier (partial) run.
		$existing_list      = $order->get_meta( '_bs_vouchers_list' );
		$generated_vouchers = is_array( $existing_list ) ? $existing_list : array();
		$work_done          = false;
		$all_delivered      = true;

		foreach ( $order->get_items( 'line_item' ) as $item_id => $item ) {
			$product_id = $item->get_product_id();

			if ( get_post_meta( $product_id, '_bs_custom_mail_voucher', true ) !== 'yes' ) {
				continue;
			}

			$voucher_value  = $item->get_meta( '_bs_voucher_value' ) ?: $item->get_total();
			$recipient      = $item->get_meta( '_bs_voucher_recipient' );
			$recipient_name = $item->get_meta( '_bs_voucher_recipient_name' );
			$message        = $item->get_meta( '_bs_voucher_message' );

			// --- Step 1: determine the coupon code (idempotent) --------------
			// The code is persisted to the order item BEFORE the WooCommerce
			// coupon is created, and the coupon's real existence is verified via
			// wc_get_coupon_id_by_code(). This makes EVERY crash ordering safe:
			// neither a duplicate coupon (double money) nor a dangling code can
			// result from a resume.
			$coupon_code = $item->get_meta( '_bs_voucher_coupon_code' );
			$expiry_date = $item->get_meta( '_bs_voucher_expiry' );

			if ( empty( $coupon_code ) ) {
				$coupon_code = $this->generate_unique_code();
				$expiry_date = $this->calculate_expiry_date();
				$item->update_meta_data( '_bs_voucher_coupon_code', $coupon_code );
				$item->update_meta_data( '_bs_voucher_expiry', $expiry_date );
				$item->save();
				$work_done = true;
			}

			if ( empty( $expiry_date ) ) {
				$expiry_date = $this->calculate_expiry_date();
			}

			// Create the WooCommerce coupon only if it does not already exist.
			if ( ! wc_get_coupon_id_by_code( $coupon_code ) ) {
				$coupon = new WC_Coupon();
				$coupon->set_code( $coupon_code );
				$coupon->set_discount_type( 'fixed_cart' );
				$coupon->set_amount( $voucher_value );
				$coupon->set_usage_limit( 1 );
				$coupon->set_individual_use( true );
				$coupon->set_description( sprintf( __( 'Wertgutschein für Bestellung #%s', 'bs-custom-mail' ), $order->get_order_number() ) );
				$coupon->set_date_expires( $expiry_date );
				$coupon->save();
				$work_done = true;

				$this->log( sprintf( 'Voucher-Coupon %s für Bestellung #%d (Position %d) erstellt.', $coupon_code, $order_id, $item_id ), 'debug' );
			}

			// --- Step 2: plugin DB record + PDF (idempotent) -----------------
			$voucher_id = $this->get_voucher_id_for_item( $item_id );
			$pdf_path   = '';

			if ( ! $voucher_id ) {
				$pdf_result = $this->generate_voucher_pdf( $product_id, $coupon_code, $voucher_value, $recipient_name, $expiry_date );
				$pdf_path   = $pdf_result ? $pdf_result['path'] : null;

				$voucher_id = $this->save_voucher( array(
					'order_id'         => $order_id,
					'order_item_id'    => $item_id,
					'product_id'       => $product_id,
					'voucher_code'     => $coupon_code,
					'voucher_value'    => $voucher_value,
					'recipient_email'  => $recipient ?: $order->get_billing_email(),
					'recipient_name'   => $recipient_name,
					'personal_message' => $message,
					'pdf_path'         => $pdf_path,
					'expiry_date'      => $expiry_date,
					'status'           => 'active',
				) );
				$work_done = true;
			} else {
				$pdf_path = $this->get_voucher_pdf_path( $voucher_id );
			}

			$voucher_info = array(
				'id'             => $voucher_id,
				'code'           => $coupon_code,
				'value'          => $voucher_value,
				'recipient'      => $recipient,
				'recipient_name' => $recipient_name,
				'message'        => $message,
				'pdf_path'       => $pdf_path,
				'expiry_date'    => $expiry_date,
			);

			// --- Step 3: voucher email (idempotent) --------------------------
			// The "sent" flag is only written when wp_mail() actually reported
			// success. A failed send therefore stays visible to the retry logic
			// instead of being silently marked as delivered.
			if ( $item->get_meta( '_bs_voucher_email_sent' ) !== 'yes' ) {
				$sent = $this->send_voucher_email( $voucher_info, $order );

				if ( $sent ) {
					$item->update_meta_data( '_bs_voucher_email_sent', 'yes' );
					$item->save();
					$work_done = true;

					$note = sprintf(
						__( 'Wertgutschein erstellt: %s (%s)', 'bs-custom-mail' ),
						$coupon_code,
						wc_price( $voucher_value )
					);
					if ( $recipient ) {
						$note .= sprintf( __( ' → Gesendet an: %s', 'bs-custom-mail' ), $recipient );
					}
					$order->add_order_note( $note );
				} else {
					$all_delivered = false;

					Bs_Custom_Mail_Health::log(
						sprintf(
							'Gutschein-Mail für Bestellung #%d (Position %d, Code %s) konnte nicht versendet werden.',
							$order_id,
							$item_id,
							$coupon_code
						),
						'error'
					);
				}
			}

			// Keep this voucher in the order's list (dedup by code).
			$already_listed = false;
			foreach ( $generated_vouchers as $gv ) {
				if ( isset( $gv['code'] ) && $gv['code'] === $coupon_code ) {
					$already_listed = true;
					break;
				}
			}
			if ( ! $already_listed ) {
				$generated_vouchers[] = $voucher_info;
			}
		}

		// The voucher list is persisted even after a partial run: the order
		// confirmation email reads it for the {{Gutscheincode}} placeholder,
		// and a code that exists should be usable even if its mail failed.
		if ( ! empty( $generated_vouchers ) ) {
			$order->update_meta_data( '_bs_vouchers_list', $generated_vouchers );
		}

		// The "generated" marker is the promise that nothing is outstanding.
		// It is only written when every voucher really did get delivered.
		if ( $all_delivered ) {
			$order->update_meta_data( '_bs_vouchers_generated', 'yes' );
		}

		$order->save();

		if ( 'job' !== $context && $work_done ) {
			Bs_Custom_Mail_Health::log(
				sprintf(
					'RECOVERED (vouchers): Bestellung #%d hatte eine unvollständige Gutschein-Generierung; fehlende Schritte (Coupon/PDF/Mail) wurden über "%s" nachgeholt — ohne Coupon-Duplikat.',
					$order_id,
					$context
				),
				'warning'
			);
		}

		return $all_delivered;
	}

	/**
	 * Expiry date for a newly issued voucher.
	 *
	 * German statutory limitation runs to the END of the third calendar year
	 * following the purchase, so a plain "+3 years" was shorter than the legal
	 * minimum.
	 *
	 * @since    3.0.0
	 * @return   string    Date in Y-m-d.
	 */
	private function calculate_expiry_date() {
		/**
		 * Filter the number of full calendar years a voucher stays valid.
		 *
		 * @since 3.0.0
		 * @param int $years Number of years. Default 3.
		 */
		$years = (int) apply_filters( 'bs_custom_mail_voucher_validity_years', 3 );

		return sprintf( '%d-12-31', (int) date( 'Y' ) + max( 1, $years ) );
	}

	/**
	 * Get the plugin voucher record id for an order item, if any.
	 *
	 * @since    2.1.0
	 * @param    int    $item_id    Order item ID.
	 * @return   int                Voucher id, or 0.
	 */
	private function get_voucher_id_for_item( $item_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'bs_custom_mail_vouchers';

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE order_item_id = %d LIMIT 1", (int) $item_id ) );
	}

	/**
	 * Get the stored PDF path for a voucher record.
	 *
	 * @since    2.1.0
	 * @param    int    $voucher_id    Voucher record id.
	 * @return   string
	 */
	private function get_voucher_pdf_path( $voucher_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'bs_custom_mail_vouchers';

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return (string) $wpdb->get_var( $wpdb->prepare( "SELECT pdf_path FROM {$table} WHERE id = %d", (int) $voucher_id ) );
	}

	/**
	 * Write a safety-net log entry (shared source with the email safety net).
	 *
	 * @since    2.1.0
	 * @param    string    $message    Log message.
	 * @param    string    $level      PSR-3 level. Default "info".
	 */
	private function log( $message, $level = 'info' ) {
		Bs_Custom_Mail_Health::log( $message, $level );
	}

	/**
	 * Generate unique voucher code.
	 *
	 * Checks both the plugin's own table AND the WooCommerce coupon list. A
	 * collision with an existing coupon used to be invisible: the generation
	 * step would simply adopt the foreign coupon, handing the customer someone
	 * else's discount instead of their voucher.
	 *
	 * @since    2.0.0
	 * @return   string
	 */
	private function generate_unique_code() {
		$prefix       = get_option( 'bs_custom_mail_voucher_prefix', 'WERT' );
		$max_attempts = 10;
		$attempt      = 0;

		global $wpdb;
		$table_name = $wpdb->prefix . 'bs_custom_mail_vouchers';

		do {
			$code = $prefix . '-' . strtoupper( wp_generate_password( 8, false ) );
			$attempt++;

			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$existing = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table_name} WHERE voucher_code = %s", $code ) );

			if ( $existing ) {
				continue;
			}

			if ( function_exists( 'wc_get_coupon_id_by_code' ) && wc_get_coupon_id_by_code( $code ) ) {
				continue;
			}

			return $code;
		} while ( $attempt < $max_attempts );

		// Practically unreachable; the timestamp suffix guarantees uniqueness.
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

		// Get template path if using image background
		$pdf_path = '';
		if ( isset( $template->background_type ) && $template->background_type === 'image' && $template->attachment_id ) {
			$pdf_path = get_attached_file( $template->attachment_id );
		}

		// Load PDF generator
		if ( ! class_exists( 'Bs_Custom_Mail_PDF_Generator' ) ) {
			require_once plugin_dir_path( __FILE__ ) . 'class-bs-custom-mail-pdf-generator.php';
		}

		// Prepare options with all template settings
		$options = array(
			'paper_size'       => isset( $template->paper_size ) ? $template->paper_size : 'A4',
			'orientation'      => isset( $template->orientation ) ? $template->orientation : 'portrait',
			'background_color' => isset( $template->background_color ) ? $template->background_color : '#ffffff',
			'background_type'  => isset( $template->background_type ) ? $template->background_type : 'color',
			'active_fields'    => isset( $template->active_fields ) ? $template->active_fields : array( 'wert', 'code', 'name', 'expiry' ),
		);

		$generator = new Bs_Custom_Mail_PDF_Generator();
		return $generator->generate( $pdf_path, $template->template_config, array(
			'wert' => $value,
			'code' => $code,
			'name' => $recipient_name,
			'expiry' => date_i18n( get_option( 'date_format' ), strtotime( $expiry_date ) ),
			'font_size' => $template->font_size
		), $options );
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

		$sender = new Bs_Custom_Mail_Email_Sender( 'bs-custom-mail', $this->version );

		// Prepare voucher data for email
		// Patched 2026-05-27 (Robin Herbeck, DBW Media) — siehe Projekt/julius.md.
		// `pdf_path` als direkter Filesystem-Pfad zusaetzlich zu `pdf_url` mitgegeben,
		// damit der Mail-Sender den Roundtrip URL->Pfad via str_replace nicht braucht.
		$voucher_data = array(
			'gutschein_code' => $voucher_info['code'],
			'gutschein_wert' => wp_strip_all_tags( wc_price( $voucher_info['value'] ) ),
			'gutschein_ablauf' => date_i18n( get_option( 'date_format' ), strtotime( $voucher_info['expiry_date'] ) ),
			'empfaenger_name' => $voucher_info['recipient_name'] ?: $order->get_billing_first_name(),
			'persoenliche_nachricht' => $voucher_info['message'],
			'pdf_path' => $voucher_info['pdf_path'] ?: '',
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
			self::devalue_coupon( $voucher->voucher_code );

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
	 * Make a coupon permanently unusable.
	 *
	 * Belt and braces: the expiry date is moved into the past AND the usage
	 * count is pushed to the limit. Relying on the usage count alone was
	 * fragile — raising the usage limit later would silently bring a cancelled
	 * voucher back to life.
	 *
	 * @since    3.0.0
	 * @param    string    $code    Coupon code.
	 * @return   bool
	 */
	public static function devalue_coupon( $code ) {
		if ( ! function_exists( 'wc_get_coupon_id_by_code' ) ) {
			return false;
		}

		$coupon_id = wc_get_coupon_id_by_code( $code );

		if ( ! $coupon_id ) {
			return false;
		}

		$coupon = new WC_Coupon( $coupon_id );
		$coupon->set_date_expires( gmdate( 'Y-m-d', time() - DAY_IN_SECONDS ) );
		$coupon->set_usage_limit( 1 );
		$coupon->set_usage_count( max( 1, (int) $coupon->get_usage_count() ) );
		$coupon->save();

		Bs_Custom_Mail_Health::log( sprintf( 'Coupon %s entwertet.', $code ), 'info' );

		return true;
	}

	/**
	 * Restore a previously cancelled coupon.
	 *
	 * @since    3.0.0
	 * @param    string    $code           Coupon code.
	 * @param    string    $expiry_date    Expiry date in Y-m-d.
	 * @return   bool
	 */
	public static function revalue_coupon( $code, $expiry_date = '' ) {
		if ( ! function_exists( 'wc_get_coupon_id_by_code' ) ) {
			return false;
		}

		$coupon_id = wc_get_coupon_id_by_code( $code );

		if ( ! $coupon_id ) {
			return false;
		}

		$coupon = new WC_Coupon( $coupon_id );
		$coupon->set_usage_count( 0 );
		$coupon->set_usage_limit( 1 );

		if ( $expiry_date && strtotime( $expiry_date ) > time() ) {
			$coupon->set_date_expires( $expiry_date );
		} else {
			$coupon->set_date_expires( null );
		}

		$coupon->save();

		Bs_Custom_Mail_Health::log( sprintf( 'Coupon %s reaktiviert.', $code ), 'info' );

		return true;
	}

	/**
	 * Flag a partial refund for manual review.
	 *
	 * Whether a partially refunded order should invalidate its voucher, reduce
	 * it, or leave it untouched is a business decision, not a technical one —
	 * so this deliberately only raises a visible note instead of guessing.
	 *
	 * @since    3.0.0
	 * @param    int    $order_id     Order ID.
	 * @param    int    $refund_id    Refund ID.
	 */
	public function flag_partial_refund( $order_id, $refund_id ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'bs_custom_mail_vouchers';

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$codes = $wpdb->get_col( $wpdb->prepare( "SELECT voucher_code FROM {$table_name} WHERE order_id = %d AND status = 'active'", (int) $order_id ) );

		if ( empty( $codes ) ) {
			return;
		}

		$order = wc_get_order( $order_id );

		if ( ! $order ) {
			return;
		}

		$order->add_order_note(
			sprintf(
				/* translators: %s: comma separated voucher codes */
				__( 'Teilerstattung: Die Wertgutscheine %s sind weiterhin gültig. Bitte manuell prüfen, ob sie entwertet oder angepasst werden sollen.', 'bs-custom-mail' ),
				implode( ', ', $codes )
			)
		);
	}

	/**
	 * Sync voucher status to 'used' when a matching coupon is redeemed in an order.
	 *
	 * @since    2.0.0
	 * @param    int    $order_id    Order ID.
	 */
	public function sync_voucher_status_on_order( $order_id ) {
		$order = wc_get_order( $order_id );

		if ( ! $order ) {
			return;
		}

		$coupon_codes = $order->get_coupon_codes();

		if ( empty( $coupon_codes ) ) {
			return;
		}

		global $wpdb;
		$table_name = $wpdb->prefix . 'bs_custom_mail_vouchers';

		foreach ( $coupon_codes as $coupon_code ) {
			$voucher = $wpdb->get_row( $wpdb->prepare(
				"SELECT * FROM {$table_name} WHERE voucher_code = %s AND status = 'active'",
				strtoupper( $coupon_code )
			) );

			if ( ! $voucher ) {
				continue;
			}

			// Skip if this voucher belongs to the same order that created it.
			if ( (int) $voucher->order_id === (int) $order_id ) {
				continue;
			}

			$wpdb->update(
				$table_name,
				array(
					'status'  => 'used',
					'used_at' => current_time( 'mysql' ),
				),
				array( 'id' => $voucher->id )
			);

			$order->add_order_note( sprintf(
				/* translators: %s: voucher code */
				__( 'Wertgutschein %s wurde als eingelöst markiert.', 'bs-custom-mail' ),
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
