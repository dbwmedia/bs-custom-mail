<?php

/**
 * Fired during plugin deactivation
 *
 * @link       https://jltzbrg.com
 * @since      1.0.0
 *
 * @package    Bs_Custom_Mail
 * @subpackage Bs_Custom_Mail/includes
 */

/**
 * Fired during plugin deactivation.
 *
 * This class defines all code necessary to run during the plugin's deactivation.
 *
 * @since      1.0.0
 * @package    Bs_Custom_Mail
 * @subpackage Bs_Custom_Mail/includes
 * @author     Julio Litzenberg <jltbrg@gmail.com>
 */
class Bs_Custom_Mail_Deactivator {

	/**
	 * Clean up recurring background work.
	 *
	 * Pending per-order jobs are deliberately left in place: if the plugin is
	 * reactivated shortly afterwards, orders that were mid-flight still get
	 * their voucher instead of being silently dropped.
	 *
	 * @since    1.0.0
	 */
	public static function deactivate() {
		if ( ! function_exists( 'as_unschedule_all_actions' ) ) {
			return;
		}

		as_unschedule_all_actions( 'bs_custom_mail_safety_net_sweep', array(), 'bs-custom-mail' );
		as_unschedule_all_actions( 'bs_custom_mail_cleanup_pdfs', array(), 'bs-custom-mail' );
	}

}
