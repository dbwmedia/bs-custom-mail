<?php
/**
 * Provide a admin area settings view for the plugin
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

global $wpdb;
$table_templates = $wpdb->prefix . 'bs_custom_mail_templates';
$templates = $wpdb->get_results( "SELECT * FROM $table_templates ORDER BY template_name ASC" );

$wc_statuses = wc_get_order_statuses();

// System checks
$system_status = array(
	'wordpress' => array(
		'name' => __( 'WordPress', 'bs-custom-mail' ),
		'version' => get_bloginfo( 'version' ),
		'ok' => version_compare( get_bloginfo( 'version' ), '6.0', '>=' ),
	),
	'woocommerce' => array(
		'name' => __( 'WooCommerce', 'bs-custom-mail' ),
		'version' => class_exists( 'WooCommerce' ) ? WC()->version : __( 'Nicht installiert', 'bs-custom-mail' ),
		'ok' => class_exists( 'WooCommerce' ) && version_compare( WC()->version, '7.0', '>=' ),
	),
	'php' => array(
		'name' => __( 'PHP', 'bs-custom-mail' ),
		'version' => phpversion(),
		'ok' => version_compare( phpversion(), '7.4', '>=' ),
	),
);

$all_ok = ! in_array( false, array_column( $system_status, 'ok' ), true );
?>

<div class="wrap bs-custom-mail-admin">
	<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

	<?php settings_errors( 'bs_custom_mail' ); ?>

	<div class="bs-settings-grid">
		<div class="bs-settings-main">
			<form method="post" action="" class="bs-form">
				<?php wp_nonce_field( 'bs_custom_mail_save_settings' ); ?>
				
				<div class="bs-card">
					<div class="bs-card-header">
						<span class="dashicons dashicons-admin-generic"></span>
						<h3><?php esc_html_e( 'Allgemeine Einstellungen', 'bs-custom-mail' ); ?></h3>
					</div>
					<div class="bs-card-body">
						<div class="bs-form-group">
							<label for="trigger_status">
								<?php esc_html_e( 'Trigger Status', 'bs-custom-mail' ); ?>
								<span class="bs-tooltip" title="<?php esc_attr_e( 'Der Bestellstatus, bei dem die E-Mails automatisch versendet werden.', 'bs-custom-mail' ); ?>">?</span>
							</label>
							<select name="trigger_status" id="trigger_status" class="bs-select">
								<?php foreach ( $wc_statuses as $status_key => $status_label ) : ?>
									<?php $status_key = str_replace( 'wc-', '', $status_key ); ?>
									<option value="<?php echo esc_attr( $status_key ); ?>" 
										<?php selected( $trigger_status, $status_key ); ?>>
										<?php echo esc_html( $status_label ); ?>
									</option>
								<?php endforeach; ?>
							</select>
							<p class="bs-help-text">
								<span class="dashicons dashicons-info"></span>
								<?php esc_html_e( 'Empfohlen: "In Bearbeitung" (processing)', 'bs-custom-mail' ); ?>
							</p>
						</div>
					</div>
				</div>

				<div class="bs-card">
					<div class="bs-card-header">
						<span class="dashicons dashicons-email"></span>
						<h3><?php esc_html_e( 'Absender-Einstellungen', 'bs-custom-mail' ); ?></h3>
					</div>
					<div class="bs-card-body">
						<div class="bs-form-group">
							<label for="from_name"><?php esc_html_e( 'Absender Name', 'bs-custom-mail' ); ?></label>
							<input type="text" name="from_name" id="from_name" 
								value="<?php echo esc_attr( $from_name ); ?>" 
								class="bs-input" placeholder="Bootsschule Berlin Köpenick">
						</div>
						
						<div class="bs-form-group">
							<label for="from_email"><?php esc_html_e( 'Absender E-Mail', 'bs-custom-mail' ); ?></label>
							<input type="email" name="from_email" id="from_email" 
								value="<?php echo esc_attr( $from_email ); ?>" 
								class="bs-input" placeholder="info@bootsschule.de">
							<p class="bs-help-text">
								<span class="dashicons dashicons-warning"></span>
								<?php esc_html_e( 'Stelle sicher, dass diese E-Mail-Domain für den Versand autorisiert ist (SPF/DKIM).', 'bs-custom-mail' ); ?>
							</p>
						</div>
					</div>
				</div>
				
				<?php submit_button( __( 'Einstellungen speichern', 'bs-custom-mail' ), 'primary', 'bs_custom_mail_save_settings' ); ?>
			</form>
			
			<div class="bs-card bs-test-email-card">
				<div class="bs-card-header">
					<span class="dashicons dashicons-email-alt"></span>
					<h3><?php esc_html_e( 'Test-E-Mail senden', 'bs-custom-mail' ); ?></h3>
				</div>
				<div class="bs-card-body">
					<p class="bs-card-description">
						<?php esc_html_e( 'Teste jedes Template vor dem Live-Betrieb.', 'bs-custom-mail' ); ?>
					</p>
					
					<div class="bs-form-row">
						<div class="bs-form-group" style="flex: 2;">
							<select id="test_template_ajax" class="bs-select">
								<?php foreach ( $templates as $template ) : ?>
									<option value="<?php echo esc_attr( $template->template_key ); ?>">
										<?php echo esc_html( $template->template_name ); ?>
									</option>
								<?php endforeach; ?>
							</select>
						</div>
						<div class="bs-form-group" style="flex: 2;">
							<input type="email" id="test_email_ajax" 
								value="<?php echo esc_attr( wp_get_current_user()->user_email ); ?>" 
								class="bs-input" placeholder="<?php esc_attr_e( 'E-Mail Adresse', 'bs-custom-mail' ); ?>">
						</div>
						<div class="bs-form-group" style="flex: 1;">
							<button type="button" class="button button-secondary" id="bs-send-test-ajax">
								<span class="dashicons dashicons-send" style="font-size: 16px; line-height: 1.4; margin-right: 4px;"></span>
								<?php esc_html_e( 'Senden', 'bs-custom-mail' ); ?>
							</button>
						</div>
					</div>
				</div>
			</div>
		</div>

		<div class="bs-settings-sidebar">
			<div class="bs-card bs-status-card">
				<div class="bs-card-header">
					<span class="dashicons dashicons-desktop"></span>
					<h3><?php esc_html_e( 'System-Status', 'bs-custom-mail' ); ?></h3>
				</div>
				<div class="bs-card-body">
					<div class="bs-status-indicator <?php echo $all_ok ? 'bs-status-ok' : 'bs-status-warning'; ?>">
						<span class="dashicons <?php echo $all_ok ? 'dashicons-yes-alt' : 'dashicons-warning'; ?>"></span>
						<span><?php echo $all_ok ? esc_html__( 'System bereit', 'bs-custom-mail' ) : esc_html__( 'Aktion erforderlich', 'bs-custom-mail' ); ?></span>
					</div>
					
					<ul class="bs-system-list">
						<?php foreach ( $system_status as $key => $status ) : ?>
							<li class="<?php echo $status['ok'] ? 'bs-ok' : 'bs-error'; ?>">
								<span class="dashicons <?php echo $status['ok'] ? 'dashicons-yes' : 'dashicons-no'; ?>"></span>
								<strong><?php echo esc_html( $status['name'] ); ?></strong>
								<span class="bs-version"><?php echo esc_html( $status['version'] ); ?></span>
							</li>
						<?php endforeach; ?>
					</ul>
				</div>
			</div>

			<div class="bs-card">
				<div class="bs-card-header">
					<span class="dashicons dashicons-lightbulb"></span>
					<h3><?php esc_html_e( 'Tipps', 'bs-custom-mail' ); ?></h3>
				</div>
				<div class="bs-card-body">
					<ul class="bs-tips-list">
						<li>
							<span class="dashicons dashicons-email"></span>
							<p><?php esc_html_e( 'Verwende ein SMTP-Plugin (z.B. WP Mail SMTP) für zuverlässigen E-Mail-Versand.', 'bs-custom-mail' ); ?></p>
						</li>
						<li>
							<span class="dashicons dashicons-media-document"></span>
							<p><?php esc_html_e( 'Halte PDF-Anhänge unter 5MB für bessere Zustellraten.', 'bs-custom-mail' ); ?></p>
						</li>
						<li>
							<span class="dashicons dashicons-spam"></span>
							<p><?php esc_html_e( 'Teste mit mail-tester.com, damit E-Mails nicht im Spam landen.', 'bs-custom-mail' ); ?></p>
						</li>
					</ul>
				</div>
			</div>
		</div>
	</div>
</div>
