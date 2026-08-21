<?php

/**
 * The plugin bootstrap file
 *
 * This file is read by WordPress to generate the plugin information in the plugin
 * admin area. This file also includes all of the dependencies used by the plugin,
 * registers the activation and deactivation functions, and defines a function
 * that starts the plugin.
 *
 * @link              https://jltzbrg.com
 * @since             1.0.0
 * @package           Bs_Custom_Mail
 *
 * @wordpress-plugin
 * Plugin Name:       Bootsschule Mail & Vouchers
 * Plugin URI:        https://jltzbrg.com
 * Description:       Automatisierte E-Mails und Wertgutscheine für Bootsschule Berlin Köpenick. Sendet personalisierte Bestellbestätigungen und erstellt PDF-Gutscheine.
 * Version:           3.0.0
 * Author:            Julio Litzenberg
 * Author URI:        https://jltzbrg.com/
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       bs-custom-mail
 * Domain Path:       /languages
 * Requires PHP:      7.4
 * WC requires at least: 8.0
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Currently plugin version.
 * Start at version 1.0.0 and use SemVer - https://semver.org
 * Rename this for your plugin and update it as you release new versions.
 */
define( 'BS_CUSTOM_MAIL_VERSION', '3.0.0' );

/**
 * Declare compatibility with WooCommerce High-Performance Order Storage.
 *
 * All order meta access goes through the order CRUD API and no order query
 * relies on the post meta tables, so HPOS can be switched on safely.
 */
add_action(
	'before_woocommerce_init',
	function () {
		// Note: compatibility with the block based cart/checkout is NOT
		// declared — the voucher fields are rendered with classic hooks and
		// that combination has not been verified.
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
		}
	}
);

/**
 * The code that runs during plugin activation.
 * This action is documented in includes/class-bs-custom-mail-activator.php
 */
function activate_bs_custom_mail() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-bs-custom-mail-activator.php';
	Bs_Custom_Mail_Activator::activate();
}

/**
 * The code that runs during plugin deactivation.
 * This action is documented in includes/class-bs-custom-mail-deactivator.php
 */
function deactivate_bs_custom_mail() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-bs-custom-mail-deactivator.php';
	Bs_Custom_Mail_Deactivator::deactivate();
}

register_activation_hook( __FILE__, 'activate_bs_custom_mail' );
register_deactivation_hook( __FILE__, 'deactivate_bs_custom_mail' );

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
require plugin_dir_path( __FILE__ ) . 'includes/class-bs-custom-mail.php';

/**
 * Begins execution of the plugin.
 *
 * Since everything within the plugin is registered via hooks,
 * then kicking off the plugin from this point in the file does
 * not affect the page life cycle.
 *
 * @since    1.0.0
 */
function run_bs_custom_mail() {

	$plugin = new Bs_Custom_Mail();
	$plugin->run();

}
run_bs_custom_mail();
