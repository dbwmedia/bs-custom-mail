<?php

/**
 * The file that defines the core plugin class
 *
 * A class definition that includes attributes and functions used across both the
 * public-facing side of the site and the admin area.
 *
 * @link       https://jltzbrg.com
 * @since      1.0.0
 *
 * @package    Bs_Custom_Mail
 * @subpackage Bs_Custom_Mail/includes
 */

/**
 * The core plugin class.
 *
 * This is used to define internationalization, admin-specific hooks, and
 * public-facing site hooks.
 *
 * Also maintains the unique identifier of this plugin as well as the current
 * version of the plugin.
 *
 * @since      1.0.0
 * @package    Bs_Custom_Mail
 * @subpackage Bs_Custom_Mail/includes
 * @author     Julio Litzenberg <jltbrg@gmail.com>
 */
class Bs_Custom_Mail {

	/**
	 * The loader that's responsible for maintaining and registering all hooks that power
	 * the plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      Bs_Custom_Mail_Loader    $loader    Maintains and registers all hooks for the plugin.
	 */
	protected $loader;

	/**
	 * The unique identifier of this plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      string    $plugin_name    The string used to uniquely identify this plugin.
	 */
	protected $plugin_name;

	/**
	 * The current version of the plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      string    $version    The current version of the plugin.
	 */
	protected $version;

	/**
	 * Define the core functionality of the plugin.
	 *
	 * Set the plugin name and the plugin version that can be used throughout the plugin.
	 * Load the dependencies, define the locale, and set the hooks for the admin area and
	 * the public-facing side of the site.
	 *
	 * @since    1.0.0
	 */
	public function __construct() {
		if ( defined( 'BS_CUSTOM_MAIL_VERSION' ) ) {
			$this->version = BS_CUSTOM_MAIL_VERSION;
		} else {
			$this->version = '1.0.0';
		}
		$this->plugin_name = 'bs-custom-mail';

		$this->load_dependencies();
		$this->set_locale();
		$this->define_admin_hooks();
		$this->define_public_hooks();
		$this->define_woocommerce_hooks();
		$this->define_product_hooks();
		$this->define_voucher_hooks();
		$this->define_rest_api_hooks();

	}

	/**
	 * Load the required dependencies for this plugin.
	 *
	 * Include the following files that make up the plugin:
	 *
	 * - Bs_Custom_Mail_Loader. Orchestrates the hooks of the plugin.
	 * - Bs_Custom_Mail_i18n. Defines internationalization functionality.
	 * - Bs_Custom_Mail_Admin. Defines all hooks for the admin area.
	 * - Bs_Custom_Mail_Public. Defines all hooks for the public side of the site.
	 * - Bs_Custom_Mail_Email_Sender. Handles email sending functionality.
	 * - Bs_Custom_Mail_Product. Handles product template assignment.
	 * - Bs_Custom_Mail_REST_API. Handles REST API endpoints.
	 *
	 * Create an instance of the loader which will be used to register the hooks
	 * with WordPress.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function load_dependencies() {

		/**
		 * The class responsible for orchestrating the actions and filters of the
		 * core plugin.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-bs-custom-mail-loader.php';

		/**
		 * The class responsible for defining internationalization functionality
		 * of the plugin.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-bs-custom-mail-i18n.php';

		/**
		 * The class responsible for defining all actions that occur in the admin area.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'admin/class-bs-custom-mail-admin.php';

		/**
		 * The class responsible for defining all actions that occur in the public-facing
		 * side of the site.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'public/class-bs-custom-mail-public.php';

		/**
		 * The class responsible for handling email sending functionality.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-bs-custom-mail-email-sender.php';

		/**
		 * The class responsible for handling product template assignment.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-bs-custom-mail-product.php';

		/**
		 * The class responsible for handling REST API endpoints.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-bs-custom-mail-rest-api.php';

		/**
		 * The class responsible for handling voucher functionality.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-bs-custom-mail-voucher.php';

		/**
		 * The class responsible for generating PDF vouchers.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-bs-custom-mail-pdf-generator.php';

		$this->loader = new Bs_Custom_Mail_Loader();

	}

	/**
	 * Define the locale for this plugin for internationalization.
	 *
	 * Uses the Bs_Custom_Mail_i18n class in order to set the domain and to register the hook
	 * with WordPress.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function set_locale() {

		$plugin_i18n = new Bs_Custom_Mail_i18n();

		$this->loader->add_action( 'plugins_loaded', $plugin_i18n, 'load_plugin_textdomain' );

	}

	/**
	 * Register all of the hooks related to the admin area functionality
	 * of the plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function define_admin_hooks() {

		$plugin_admin = new Bs_Custom_Mail_Admin( $this->get_plugin_name(), $this->get_version() );

		$this->loader->add_action( 'admin_enqueue_scripts', $plugin_admin, 'enqueue_styles' );
		$this->loader->add_action( 'admin_enqueue_scripts', $plugin_admin, 'enqueue_scripts' );
		$this->loader->add_action( 'admin_enqueue_scripts', $plugin_admin, 'enqueue_react_app' );
		$this->loader->add_action( 'admin_menu', $plugin_admin, 'add_admin_menu' );
		$this->loader->add_action( 'wp_ajax_bs_custom_mail_send_test', $plugin_admin, 'ajax_send_test_email' );

	}

	/**
	 * Register all of the hooks related to the public-facing functionality
	 * of the plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function define_public_hooks() {

		$plugin_public = new Bs_Custom_Mail_Public( $this->get_plugin_name(), $this->get_version() );

		$this->loader->add_action( 'wp_enqueue_scripts', $plugin_public, 'enqueue_styles' );
		$this->loader->add_action( 'wp_enqueue_scripts', $plugin_public, 'enqueue_scripts' );

	}

	/**
	 * Register all of the hooks related to WooCommerce functionality.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function define_woocommerce_hooks() {

		$email_sender = new Bs_Custom_Mail_Email_Sender( $this->get_plugin_name(), $this->get_version() );

		// Hook into WooCommerce order status changes
		$this->loader->add_action( 'woocommerce_order_status_changed', $email_sender, 'handle_order_status_change', 10, 3 );

	}

	/**
	 * Register all of the hooks related to voucher functionality.
	 *
	 * @since    2.0.0
	 * @access   private
	 */
	private function define_voucher_hooks() {

		$voucher = new Bs_Custom_Mail_Voucher( $this->get_version() );
		$voucher->register_hooks();

	}

	/**
	 * Register all of the hooks related to product template assignment.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function define_product_hooks() {

		$product = new Bs_Custom_Mail_Product( $this->get_plugin_name(), $this->get_version() );

		// Add product data tab and panel
		$this->loader->add_filter( 'woocommerce_product_data_tabs', $product, 'add_product_data_tab' );
		$this->loader->add_action( 'woocommerce_product_data_panels', $product, 'add_product_data_panel' );

		// Save product meta
		$this->loader->add_action( 'woocommerce_admin_process_product_object', $product, 'save_product_meta', 10, 1 );

		// Add product list column
		$this->loader->add_filter( 'manage_product_posts_columns', $product, 'add_product_column' );
		$this->loader->add_action( 'manage_product_posts_custom_column', $product, 'render_product_column', 10, 2 );

		// AJAX handlers
		$this->loader->add_action( 'wp_ajax_bs_custom_mail_preview_template', $product, 'ajax_preview_template' );

	}

	/**
	 * Register REST API hooks
	 *
	 * @since    2.0.0
	 * @access   private
	 */
	private function define_rest_api_hooks() {
		new Bs_Custom_Mail_REST_API();
	}

	/**
	 * Run the loader to execute all of the hooks with WordPress.
	 *
	 * @since    1.0.0
	 */
	public function run() {
		$this->loader->run();
	}

	/**
	 * The name of the plugin used to uniquely identify it within the context of
	 * WordPress and to define internationalization functionality.
	 *
	 * @since     1.0.0
	 * @return    string    The name of the plugin.
	 */
	public function get_plugin_name() {
		return $this->plugin_name;
	}

	/**
	 * The reference to the class that orchestrates the hooks with the plugin.
	 *
	 * @since     1.0.0
	 * @return    Bs_Custom_Mail_Loader    Orchestrates the hooks of the plugin.
	 */
	public function get_loader() {
		return $this->loader;
	}

	/**
	 * Retrieve the version number of the plugin.
	 *
	 * @since     1.0.0
	 * @return    string    The version number of the plugin.
	 */
	public function get_version() {
		return $this->version;
	}

}
