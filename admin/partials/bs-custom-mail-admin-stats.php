<?php
/**
 * Provide a admin area statistics view for the plugin
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

$total = $total_sent + $total_failed;
$success_rate = $total > 0 ? round( ( $total_sent / $total ) * 100, 1 ) : 0;
?>

<div class="wrap bs-custom-mail-admin">
	<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

	<div class="bs-stats-dashboard">
		<div class="bs-stat-card bs-stat-total">
			<div class="bs-stat-icon">
				<span class="dashicons dashicons-email"></span>
			</div>
			<div class="bs-stat-content">
				<h3><?php echo esc_html( number_format_i18n( $total ) ); ?></h3>
				<p><?php esc_html_e( 'Gesamt', 'bs-custom-mail' ); ?></p>
			</div>
		</div>
		
		<div class="bs-stat-card bs-stat-success">
			<div class="bs-stat-icon">
				<span class="dashicons dashicons-yes-alt"></span>
			</div>
			<div class="bs-stat-content">
				<h3><?php echo esc_html( number_format_i18n( $total_sent ) ); ?></h3>
				<p><?php esc_html_e( 'Erfolgreich', 'bs-custom-mail' ); ?></p>
			</div>
		</div>
		
		<div class="bs-stat-card bs-stat-error">
			<div class="bs-stat-icon">
				<span class="dashicons dashicons-warning"></span>
			</div>
			<div class="bs-stat-content">
				<h3><?php echo esc_html( number_format_i18n( $total_failed ) ); ?></h3>
				<p><?php esc_html_e( 'Fehlgeschlagen', 'bs-custom-mail' ); ?></p>
			</div>
		</div>
		
		<div class="bs-stat-card bs-stat-rate <?php echo $success_rate >= 95 ? 'bs-excellent' : ( $success_rate >= 80 ? 'bs-good' : 'bs-warning' ); ?>">
			<div class="bs-stat-icon">
				<span class="dashicons dashicons-chart-pie"></span>
			</div>
			<div class="bs-stat-content">
				<h3><?php echo esc_html( $success_rate ); ?>%</h3>
				<p><?php esc_html_e( 'Erfolgsquote', 'bs-custom-mail' ); ?></p>
			</div>
			<?php if ( $total > 0 ) : ?>
				<div class="bs-stat-ring" style="--progress: <?php echo esc_attr( $success_rate ); ?>"></div>
			<?php endif; ?>
		</div>
	</div>

	<div class="bs-stats-grid">
		<div class="bs-stats-main">
			<div class="bs-card">
				<div class="bs-card-header">
					<span class="dashicons dashicons-chart-bar"></span>
					<h3><?php esc_html_e( 'Statistik nach Template', 'bs-custom-mail' ); ?></h3>
				</div>
				<div class="bs-card-body">
					<?php if ( ! empty( $template_stats ) ) : ?>
						<div class="bs-template-stats">
							<?php foreach ( $template_stats as $stat ) : 
								$stat_total = (int) $stat->sent_count + (int) $stat->failed_count;
								$stat_rate = $stat_total > 0 ? round( ( (int) $stat->sent_count / $stat_total ) * 100, 1 ) : 0;
							?>
								<div class="bs-template-stat-row">
									<div class="bs-template-info">
										<h4><?php echo esc_html( $stat->template_name ); ?></h4>
										<span class="bs-template-key"><?php echo esc_html( $stat->template_key ); ?></span>
									</div>
									<div class="bs-template-numbers">
										<div class="bs-number bs-number-success" title="<?php esc_attr_e( 'Erfolgreich', 'bs-custom-mail' ); ?>">
											<span class="dashicons dashicons-yes"></span>
											<?php echo esc_html( number_format_i18n( $stat->sent_count ) ); ?>
										</div>
										<div class="bs-number bs-number-error" title="<?php esc_attr_e( 'Fehlgeschlagen', 'bs-custom-mail' ); ?>">
											<span class="dashicons dashicons-no"></span>
											<?php echo esc_html( number_format_i18n( $stat->failed_count ) ); ?>
										</div>
										<div class="bs-number bs-number-total" title="<?php esc_attr_e( 'Gesamt', 'bs-custom-mail' ); ?>">
											<?php echo esc_html( number_format_i18n( $stat_total ) ); ?>
										</div>
									</div>
									<div class="bs-template-bar">
										<div class="bs-progress-container">
											<div class="bs-progress-bar <?php echo $stat_rate >= 95 ? 'bs-excellent' : ( $stat_rate >= 80 ? 'bs-good' : 'bs-warning' ); ?>" 
												style="width: <?php echo esc_attr( $stat_rate ); ?>%;">
											</div>
										</div>
										<span class="bs-progress-text"><?php echo esc_html( $stat_rate ); ?>%</span>
									</div>
								</div>
							<?php endforeach; ?>
						</div>
					<?php else : ?>
						<div class="bs-empty-state">
							<span class="dashicons dashicons-chart-area"></span>
							<p><?php esc_html_e( 'Noch keine Daten verfügbar.', 'bs-custom-mail' ); ?></p>
							<p class="bs-empty-hint"><?php esc_html_e( 'Statistiken werden automatisch gesammelt, sobald E-Mails versendet werden.', 'bs-custom-mail' ); ?></p>
						</div>
					<?php endif; ?>
				</div>
			</div>
		</div>

		<div class="bs-stats-sidebar">
			<div class="bs-card">
				<div class="bs-card-header">
					<span class="dashicons dashicons-backup"></span>
					<h3><?php esc_html_e( 'Letzte Aktivität', 'bs-custom-mail' ); ?></h3>
				</div>
				<div class="bs-card-body bs-activity-list">
					<?php if ( ! empty( $recent_activity ) ) : ?>
						<?php foreach ( array_slice( $recent_activity, 0, 10 ) as $activity ) : 
							$status_class = $activity->status === 'sent' ? 'bs-status-sent' : 'bs-status-failed';
							$status_icon = $activity->status === 'sent' ? 'dashicons-yes' : 'dashicons-no';
							$time_ago = human_time_diff( strtotime( $activity->sent_at ), current_time( 'timestamp' ) );
						?>
							<div class="bs-activity-item">
								<div class="bs-activity-status <?php echo esc_attr( $status_class ); ?>">
									<span class="dashicons <?php echo esc_attr( $status_icon ); ?>"></span>
								</div>
								<div class="bs-activity-content">
									<h4><?php echo esc_html( $activity->customer_email ); ?></h4>
									<p><?php echo esc_html( $activity->product_name ); ?> <span class="bs-activity-template">(<?php echo esc_html( $activity->template_key ); ?>)</span></p>
									<span class="bs-activity-time"><?php echo esc_html( sprintf( __( 'Vor %s', 'bs-custom-mail' ), $time_ago ) ); ?></span>
								</div>
								<div class="bs-activity-order">
									<a href="<?php echo esc_url( get_edit_post_link( $activity->order_id ) ); ?>" title="<?php esc_attr_e( 'Bestellung anzeigen', 'bs-custom-mail' ); ?>">
										#<?php echo esc_html( $activity->order_id ); ?>
									</a>
								</div>
							</div>
						<?php endforeach; ?>
						
						<?php if ( count( $recent_activity ) > 10 ) : ?>
							<p class="bs-more-activity">
								<em><?php echo esc_html( sprintf( __( 'und %d weitere...', 'bs-custom-mail' ), count( $recent_activity ) - 10 ) ); ?></em>
							</p>
						<?php endif; ?>
					<?php else : ?>
						<div class="bs-empty-state">
							<span class="dashicons dashicons-clock"></span>
							<p><?php esc_html_e( 'Noch keine Aktivität vorhanden.', 'bs-custom-mail' ); ?></p>
						</div>
					<?php endif; ?>
				</div>
			</div>
		</div>
	</div>
</div>
