<?php
/**
 * Provide a admin area view for the plugin
 *
 * @link       https://jltzbrg.com
 * @since      1.0.0
 *
 * @package    Bs_Custom_Mail
 * @subpackage Bs_Custom_Mail/admin/partials
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

// React App Container - The React app handles all rendering
?>
<div id="bs-custom-mail-admin-app" class="wrap bs-custom-mail-admin">
	<div class="bs-loading">
		<span class="spinner is-active"></span>
		<p><?php esc_html_e( 'Lade Anwendung...', 'bs-custom-mail' ); ?></p>
	</div>
</div>
<?php
// Legacy PHP code - kept for fallback but React app will take over
/*
Legacy code preserved for reference. The React app now handles all UI rendering.
*/
