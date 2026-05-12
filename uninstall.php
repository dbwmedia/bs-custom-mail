<?php

/**
 * Fired when the plugin is uninstalled.
 *
 * @link       https://jltzbrg.com
 * @since      1.0.0
 *
 * @package    Bs_Custom_Mail
 */

// If uninstall not called from WordPress, then exit.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Daten standardmaessig erhalten. Loeschung nur wenn explizit opt-in.
if ( ! get_option( 'bs_custom_mail_delete_data_on_uninstall', false ) ) {
	return;
}

// Delete database tables
global $wpdb;

$tables = array(
	$wpdb->prefix . 'bs_custom_mail_templates',
	$wpdb->prefix . 'bs_custom_mail_stats',
);

foreach ( $tables as $table ) {
	$wpdb->query( "DROP TABLE IF EXISTS {$table}" );
}

// Delete options
delete_option( 'bs_custom_mail_trigger_status' );
delete_option( 'bs_custom_mail_from_name' );
delete_option( 'bs_custom_mail_from_email' );

// Delete post meta
$wpdb->query( "DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE '_bs_custom_mail_%'" );
