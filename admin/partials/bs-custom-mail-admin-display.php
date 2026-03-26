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

// Get all templates
$templates = $wpdb->get_results( "SELECT * FROM $table_name ORDER BY template_name ASC" );
?>

<div class="wrap bs-custom-mail-admin">
	<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

	<?php settings_errors( 'bs_custom_mail' ); ?>

	<?php if ( 'edit' === $action && $template_key ) : ?>
		<?php
		$template = $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM $table_name WHERE template_key = %s",
			$template_key
		) );
		
		if ( $template ) :
		?>
			<div class="bs-custom-mail-editor">
				<div class="bs-editor-header">
					<div>
						<h2><?php echo esc_html( $template->template_name ); ?></h2>
						<span class="bs-template-key"><?php echo esc_html( $template->template_key ); ?></span>
					</div>
					<div class="bs-status-toggle">
						<label class="bs-switch">
							<input type="checkbox" name="is_active" form="template-form" value="1" <?php checked( $template->is_active, 1 ); ?>>
							<span class="bs-slider"></span>
						</label>
						<span class="bs-status-label"><?php $template->is_active ? esc_html_e( 'Aktiv', 'bs-custom-mail' ) : esc_html_e( 'Inaktiv', 'bs-custom-mail' ); ?></span>
					</div>
				</div>
				
				<form id="template-form" method="post" action="" class="bs-editor-form">
					<?php wp_nonce_field( 'bs_custom_mail_save_template' ); ?>
					<input type="hidden" name="template_key" value="<?php echo esc_attr( $template->template_key ); ?>">
					
					<div class="bs-editor-grid">
						<div class="bs-editor-main">
							<div class="bs-card">
								<div class="bs-card-header">
									<span class="dashicons dashicons-email"></span>
									<h3><?php esc_html_e( 'E-Mail Details', 'bs-custom-mail' ); ?></h3>
								</div>
								<div class="bs-card-body">
									<div class="bs-form-group">
										<label for="subject"><?php esc_html_e( 'Betreff', 'bs-custom-mail' ); ?></label>
										<input type="text" name="subject" id="subject" 
											value="<?php echo esc_attr( $template->subject ); ?>" 
											class="bs-input" required>
										<p class="bs-help-text">
											<span class="dashicons dashicons-info"></span>
											<?php esc_html_e( 'Verwende Platzhalter wie ', 'bs-custom-mail' ); ?>
											<code>{{order_number}}</code>
										</p>
									</div>
								</div>
							</div>

							<!-- Template Attachments -->
							<div class="bs-card">
								<div class="bs-card-header">
									<span class="dashicons dashicons-paperclip"></span>
									<h3><?php esc_html_e( 'Template-Anhänge', 'bs-custom-mail' ); ?></h3>
								</div>
								<div class="bs-card-body">
									<p class="bs-card-description">
										<?php esc_html_e( 'Diese Dateien werden an alle E-Mails dieses Templates angehängt (zusätzlich zu produktspezifischen Anhängen).', 'bs-custom-mail' ); ?>
									</p>
									
									<?php
									// Get template attachments
									$template_attachments = isset( $template->attachments ) ? $template->attachments : '';
									$attachment_ids = $template_attachments ? explode( ',', $template_attachments ) : array();
									$attachment_ids = array_map( 'intval', array_filter( $attachment_ids ) );
									?>
									
									<div class="bs-attachments-section">
										<button type="button" class="button bs-add-template-attachments">
											<span class="dashicons dashicons-plus" style="font-size: 16px; line-height: 1.4; margin-right: 4px;"></span>
											<?php esc_html_e( 'Dateien hinzufügen', 'bs-custom-mail' ); ?>
										</button>
										
										<div class="bs-attachments-list" id="bs-template-attachments-list">
											<?php foreach ( $attachment_ids as $attachment_id ) : 
												$attachment = get_post( $attachment_id );
												if ( $attachment ) :
													$file_path = get_attached_file( $attachment_id );
													$file_size = $file_path && file_exists( $file_path ) ? size_format( filesize( $file_path ) ) : '';
													$icon = wp_mime_type_icon( $attachment_id );
													$extension = pathinfo( $file_path, PATHINFO_EXTENSION );
												?>
													<div class="bs-attachment-item" data-id="<?php echo esc_attr( $attachment_id ); ?>">
														<img src="<?php echo esc_url( $icon ); ?>" alt="" class="bs-attachment-icon-file">
														<div class="bs-attachment-info">
															<span class="bs-attachment-name"><?php echo esc_html( basename( $file_path ) ); ?></span>
															<span class="bs-attachment-meta"><?php echo esc_html( strtoupper( $extension ) ); ?> • <?php echo esc_html( $file_size ); ?></span>
														</div>
														<button type="button" class="button-link bs-remove-attachment" title="<?php esc_attr_e( 'Entfernen', 'bs-custom-mail' ); ?>">
															<span class="dashicons dashicons-no-alt"></span>
														</button>
														<input type="hidden" name="template_attachments[]" value="<?php echo esc_attr( $attachment_id ); ?>">
													</div>
												<?php endif; endforeach; ?>
										</div>
										
										<?php if ( empty( $attachment_ids ) ) : ?>
											<p class="bs-no-attachments"><?php esc_html_e( 'Noch keine Anhänge vorhanden.', 'bs-custom-mail' ); ?></p>
										<?php endif; ?>
									</div>
								</div>
							</div>

							<div class="bs-card">
								<div class="bs-card-header">
									<span class="dashicons dashicons-format-quote"></span>
									<h3><?php esc_html_e( 'Header', 'bs-custom-mail' ); ?></h3>
								</div>
								<div class="bs-card-body">
									<?php
									wp_editor( $template->header_text, 'header_text', array(
										'textarea_name' => 'header_text',
										'textarea_rows' => 4,
										'teeny'         => true,
									) );
									?>
								</div>
							</div>

							<div class="bs-card">
								<div class="bs-card-header">
									<span class="dashicons dashicons-text-page"></span>
									<h3><?php esc_html_e( 'Inhalt', 'bs-custom-mail' ); ?></h3>
								</div>
								<div class="bs-card-body">
									<?php
									wp_editor( $template->content, 'content', array(
										'textarea_name' => 'content',
										'textarea_rows' => 15,
										'teeny'         => false,
									) );
									?>
								</div>
							</div>

							<div class="bs-card">
								<div class="bs-card-header">
									<span class="dashicons dashicons-editor-insertmore"></span>
									<h3><?php esc_html_e( 'Footer', 'bs-custom-mail' ); ?></h3>
								</div>
								<div class="bs-card-body">
									<?php
									wp_editor( $template->footer_text, 'footer_text', array(
										'textarea_name' => 'footer_text',
										'textarea_rows' => 4,
										'teeny'         => true,
									) );
									?>
								</div>
							</div>
						</div>

						<div class="bs-editor-sidebar">
							<div class="bs-card bs-sticky-card">
								<div class="bs-card-header">
									<span class="dashicons dashicons-lightbulb"></span>
									<h3><?php esc_html_e( 'Platzhalter', 'bs-custom-mail' ); ?></h3>
								</div>
								<div class="bs-card-body">
									<p class="bs-card-description">
										<?php esc_html_e( 'Klicke zum Kopieren:', 'bs-custom-mail' ); ?>
									</p>
									<ul class="bs-placeholders-list">
										<li><code class="bs-copy" data-clipboard="{{customer_name}}" title="<?php esc_attr_e( 'Klicken zum Kopieren', 'bs-custom-mail' ); ?>">{{customer_name}}</code> <small><?php esc_html_e( 'Vorname', 'bs-custom-mail' ); ?></small></li>
										<li><code class="bs-copy" data-clipboard="{{customer_full_name}}" title="<?php esc_attr_e( 'Klicken zum Kopieren', 'bs-custom-mail' ); ?>">{{customer_full_name}}</code> <small><?php esc_html_e( 'Vollständiger Name', 'bs-custom-mail' ); ?></small></li>
										<li><code class="bs-copy" data-clipboard="{{order_number}}" title="<?php esc_attr_e( 'Klicken zum Kopieren', 'bs-custom-mail' ); ?>">{{order_number}}</code> <small><?php esc_html_e( 'Bestellnummer', 'bs-custom-mail' ); ?></small></li>
										<li><code class="bs-copy" data-clipboard="{{order_date}}" title="<?php esc_attr_e( 'Klicken zum Kopieren', 'bs-custom-mail' ); ?>">{{order_date}}</code> <small><?php esc_html_e( 'Bestelldatum', 'bs-custom-mail' ); ?></small></li>
										<li><code class="bs-copy" data-clipboard="{{product_name}}" title="<?php esc_attr_e( 'Klicken zum Kopieren', 'bs-custom-mail' ); ?>">{{product_name}}</code> <small><?php esc_html_e( 'Produktname', 'bs-custom-mail' ); ?></small></li>
										<li><code class="bs-copy" data-clipboard="{{site_name}}" title="<?php esc_attr_e( 'Klicken zum Kopieren', 'bs-custom-mail' ); ?>">{{site_name}}</code> <small><?php esc_html_e( 'Website-Name', 'bs-custom-mail' ); ?></small></li>
										<li><code class="bs-copy" data-clipboard="{{site_url}}" title="<?php esc_attr_e( 'Klicken zum Kopieren', 'bs-custom-mail' ); ?>">{{site_url}}</code> <small><?php esc_html_e( 'Website-URL', 'bs-custom-mail' ); ?></small></li>
									</ul>
								</div>
							</div>

							<div class="bs-card">
								<div class="bs-card-header">
									<span class="dashicons dashicons-email-alt"></span>
									<h3><?php esc_html_e( 'Test-E-Mail', 'bs-custom-mail' ); ?></h3>
								</div>
								<div class="bs-card-body">
									<div class="bs-form-group">
										<input type="email" id="test_email_ajax" 
											value="<?php echo esc_attr( wp_get_current_user()->user_email ); ?>" 
											class="bs-input" placeholder="<?php esc_attr_e( 'E-Mail Adresse', 'bs-custom-mail' ); ?>">
									</div>
									<button type="button" class="button button-secondary" id="bs-send-test-ajax" data-template="<?php echo esc_attr( $template->template_key ); ?>">
										<span class="dashicons dashicons-send" style="font-size: 16px; line-height: 1.4; margin-right: 4px;"></span>
										<?php esc_html_e( 'Test-E-Mail senden', 'bs-custom-mail' ); ?>
									</button>
								</div>
							</div>

							<div class="bs-card bs-actions-card">
								<div class="bs-card-body">
									<input type="submit" name="bs_custom_mail_save_template" 
										class="button button-primary button-hero" 
										value="<?php esc_attr_e( 'Template speichern', 'bs-custom-mail' ); ?>">
									<a href="<?php echo esc_url( admin_url( 'admin.php?page=bs-custom-mail' ) ); ?>" 
										class="button button-link" style="margin-top: 10px; text-align: center; display: block;">
										← <?php esc_html_e( 'Zurück zur Übersicht', 'bs-custom-mail' ); ?>
									</a>
								</div>
							</div>
						</div>
					</div>
				</form>
			</div>
		<?php else : ?>
			<div class="bs-notice bs-notice-error">
				<span class="dashicons dashicons-warning"></span>
				<?php esc_html_e( 'Template nicht gefunden.', 'bs-custom-mail' ); ?>
			</div>
		<?php endif; ?>
	
	<?php elseif ( 'create' === $action ) : ?>
		
		<div class="bs-custom-mail-editor">
			<div class="bs-editor-header">
				<div>
					<h2><?php esc_html_e( 'Neues Template erstellen', 'bs-custom-mail' ); ?></h2>
					<span class="bs-template-key"><?php esc_html_e( 'Neues E-Mail-Template anlegen', 'bs-custom-mail' ); ?></span>
				</div>
			</div>
			
			<form id="template-form" method="post" action="" class="bs-editor-form">
				<?php wp_nonce_field( 'bs_custom_mail_create_template' ); ?>
				
				<div class="bs-editor-grid">
					<div class="bs-editor-main">
						<div class="bs-card bs-card-warning">
							<div class="bs-card-header">
								<span class="dashicons dashicons-warning"></span>
								<h3><?php esc_html_e( 'Wichtig: Template Key', 'bs-custom-mail' ); ?></h3>
							</div>
							<div class="bs-card-body">
								<p><?php esc_html_e( 'Der Template Key ist ein eindeutiger Identifikator (nur Kleinbuchstaben, Zahlen und Unterstriche). Er kann später nicht mehr geändert werden.', 'bs-custom-mail' ); ?></p>
							</div>
						</div>

						<div class="bs-card">
							<div class="bs-card-header">
								<span class="dashicons dashicons-info"></span>
								<h3><?php esc_html_e( 'Template Details', 'bs-custom-mail' ); ?></h3>
							</div>
							<div class="bs-card-body">
								<div class="bs-form-group">
									<label for="template_key"><?php esc_html_e( 'Template Key', 'bs-custom-mail' ); ?> <span class="required">*</span></label>
									<input type="text" name="template_key" id="template_key" 
										class="bs-input" required 
										pattern="[a-z0-9_]+"
										title="<?php esc_attr_e( 'Nur Kleinbuchstaben, Zahlen und Unterstriche', 'bs-custom-mail' ); ?>"
										placeholder="z.B. sbf_see_kombi">
									<p class="bs-help-text">
										<span class="dashicons dashicons-info"></span>
										<?php esc_html_e( 'Eindeutiger Key, z.B.: kurs_leipzig_2024', 'bs-custom-mail' ); ?>
									</p>
								</div>
								
								<div class="bs-form-group">
									<label for="template_name"><?php esc_html_e( 'Template Name', 'bs-custom-mail' ); ?> <span class="required">*</span></label>
									<input type="text" name="template_name" id="template_name" 
										class="bs-input" required
										placeholder="z.B. SBF See Kurs Leipzig">
									<p class="bs-help-text">
										<span class="dashicons dashicons-info"></span>
										<?php esc_html_e( 'Anzeigename im Admin', 'bs-custom-mail' ); ?>
									</p>
								</div>
								
								<div class="bs-form-group">
									<label for="subject"><?php esc_html_e( 'E-Mail Betreff', 'bs-custom-mail' ); ?> <span class="required">*</span></label>
									<input type="text" name="subject" id="subject" 
										class="bs-input" required
										placeholder="z.B. Ihre Kursbuchung - Wichtige Informationen">
								</div>
							</div>
						</div>

						<!-- Template Attachments -->
						<div class="bs-card">
							<div class="bs-card-header">
								<span class="dashicons dashicons-paperclip"></span>
								<h3><?php esc_html_e( 'Template-Anhänge', 'bs-custom-mail' ); ?></h3>
							</div>
							<div class="bs-card-body">
								<p class="bs-card-description">
									<?php esc_html_e( 'Diese Dateien werden an alle E-Mails dieses Templates angehängt.', 'bs-custom-mail' ); ?>
								</p>
								
								<div class="bs-attachments-section">
									<button type="button" class="button bs-add-template-attachments">
										<span class="dashicons dashicons-plus" style="font-size: 16px; line-height: 1.4; margin-right: 4px;"></span>
										<?php esc_html_e( 'Dateien hinzufügen', 'bs-custom-mail' ); ?>
									</button>
									
									<div class="bs-attachments-list" id="bs-template-attachments-list">
										<!-- Attachments will be added here dynamically -->
									</div>
									
									<p class="bs-no-attachments"><?php esc_html_e( 'Noch keine Anhänge vorhanden.', 'bs-custom-mail' ); ?></p>
								</div>
							</div>
						</div>

						<div class="bs-card">
							<div class="bs-card-header">
								<span class="dashicons dashicons-format-quote"></span>
								<h3><?php esc_html_e( 'Header', 'bs-custom-mail' ); ?></h3>
							</div>
							<div class="bs-card-body">
								<?php
								wp_editor( '', 'header_text', array(
									'textarea_name' => 'header_text',
									'textarea_rows' => 4,
									'teeny'         => true,
								) );
								?>
							</div>
						</div>

						<div class="bs-card">
							<div class="bs-card-header">
								<span class="dashicons dashicons-text-page"></span>
								<h3><?php esc_html_e( 'Inhalt', 'bs-custom-mail' ); ?></h3>
							</div>
							<div class="bs-card-body">
								<?php
								wp_editor( '', 'content', array(
									'textarea_name' => 'content',
									'textarea_rows' => 15,
									'teeny'         => false,
								) );
								?>
							</div>
						</div>

						<div class="bs-card">
							<div class="bs-card-header">
								<span class="dashicons dashicons-editor-insertmore"></span>
								<h3><?php esc_html_e( 'Footer', 'bs-custom-mail' ); ?></h3>
							</div>
							<div class="bs-card-body">
								<?php
								wp_editor( '', 'footer_text', array(
									'textarea_name' => 'footer_text',
									'textarea_rows' => 4,
									'teeny'         => true,
								) );
								?>
							</div>
						</div>
					</div>

					<div class="bs-editor-sidebar">
						<div class="bs-card bs-sticky-card">
							<div class="bs-card-header">
								<span class="dashicons dashicons-lightbulb"></span>
								<h3><?php esc_html_e( 'Platzhalter', 'bs-custom-mail' ); ?></h3>
							</div>
							<div class="bs-card-body">
								<p class="bs-card-description">
									<?php esc_html_e( 'Klicke zum Kopieren:', 'bs-custom-mail' ); ?>
								</p>
								<ul class="bs-placeholders-list">
									<li><code class="bs-copy" data-clipboard="{{customer_name}}" title="<?php esc_attr_e( 'Klicken zum Kopieren', 'bs-custom-mail' ); ?>">{{customer_name}}</code> <small><?php esc_html_e( 'Vorname', 'bs-custom-mail' ); ?></small></li>
									<li><code class="bs-copy" data-clipboard="{{customer_full_name}}" title="<?php esc_attr_e( 'Klicken zum Kopieren', 'bs-custom-mail' ); ?>">{{customer_full_name}}</code> <small><?php esc_html_e( 'Vollständiger Name', 'bs-custom-mail' ); ?></small></li>
									<li><code class="bs-copy" data-clipboard="{{order_number}}" title="<?php esc_attr_e( 'Klicken zum Kopieren', 'bs-custom-mail' ); ?>">{{order_number}}</code> <small><?php esc_html_e( 'Bestellnummer', 'bs-custom-mail' ); ?></small></li>
									<li><code class="bs-copy" data-clipboard="{{order_date}}" title="<?php esc_attr_e( 'Klicken zum Kopieren', 'bs-custom-mail' ); ?>">{{order_date}}</code> <small><?php esc_html_e( 'Bestelldatum', 'bs-custom-mail' ); ?></small></li>
									<li><code class="bs-copy" data-clipboard="{{product_name}}" title="<?php esc_attr_e( 'Klicken zum Kopieren', 'bs-custom-mail' ); ?>">{{product_name}}</code> <small><?php esc_html_e( 'Produktname', 'bs-custom-mail' ); ?></small></li>
									<li><code class="bs-copy" data-clipboard="{{site_name}}" title="<?php esc_attr_e( 'Klicken zum Kopieren', 'bs-custom-mail' ); ?>">{{site_name}}</code> <small><?php esc_html_e( 'Website-Name', 'bs-custom-mail' ); ?></small></li>
									<li><code class="bs-copy" data-clipboard="{{site_url}}" title="<?php esc_attr_e( 'Klicken zum Kopieren', 'bs-custom-mail' ); ?>">{{site_url}}</code> <small><?php esc_html_e( 'Website-URL', 'bs-custom-mail' ); ?></small></li>
								</ul>
							</div>
						</div>

						<div class="bs-card bs-actions-card">
							<div class="bs-card-body">
								<input type="submit" name="bs_custom_mail_create_template" 
									class="button button-primary button-hero" 
									value="<?php esc_attr_e( 'Template erstellen', 'bs-custom-mail' ); ?>">
								<a href="<?php echo esc_url( admin_url( 'admin.php?page=bs-custom-mail' ) ); ?>" 
									class="button button-link" style="margin-top: 10px; text-align: center; display: block;">
									← <?php esc_html_e( 'Zurück zur Übersicht', 'bs-custom-mail' ); ?>
								</a>
							</div>
						</div>
					</div>
				</div>
			</form>
		</div>
	
	<?php else : ?>
		
		<div class="bs-page-header">
			<div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px;">
				<div>
					<h2><?php esc_html_e( 'E-Mail Templates', 'bs-custom-mail' ); ?></h2>
					<p class="bs-description">
						<?php esc_html_e( 'Verwalten Sie die automatischen E-Mails für Bootsschule-Produkte.', 'bs-custom-mail' ); ?>
					</p>
				</div>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=bs-custom-mail&action=create' ) ); ?>" 
					class="button button-primary button-hero">
					<span class="dashicons dashicons-plus" style="font-size: 18px; line-height: 1.3; margin-right: 6px;"></span>
					<?php esc_html_e( 'Neues Template', 'bs-custom-mail' ); ?>
				</a>
			</div>
		</div>

		<div class="bs-templates-grid">
			<?php foreach ( $templates as $template ) : ?>
				<div class="bs-template-card <?php echo $template->is_active ? 'bs-active' : 'bs-inactive'; ?>">
					<div class="bs-template-header">
						<div class="bs-template-icon">
							<span class="dashicons dashicons-email"></span>
						</div>
						<div class="bs-template-status">
							<?php if ( $template->is_active ) : ?>
								<span class="bs-badge bs-badge-success"><?php esc_html_e( 'Aktiv', 'bs-custom-mail' ); ?></span>
							<?php else : ?>
								<span class="bs-badge bs-badge-inactive"><?php esc_html_e( 'Inaktiv', 'bs-custom-mail' ); ?></span>
							<?php endif; ?>
						</div>
					</div>
					
					<h3 class="bs-template-title"><?php echo esc_html( $template->template_name ); ?></h3>
					<p class="bs-template-subject"><?php echo esc_html( $template->subject ); ?></p>
					
					<div class="bs-template-meta">
						<span class="bs-last-edited">
							<span class="dashicons dashicons-clock"></span>
							<?php 
							$modified = strtotime( $template->updated_at );
							$time_diff = human_time_diff( $modified, current_time( 'timestamp' ) );
							echo esc_html( sprintf( __( 'Vor %s bearbeitet', 'bs-custom-mail' ), $time_diff ) );
							?>
						</span>
						<?php
						$attachment_count = ! empty( $template->attachments ) ? count( explode( ',', $template->attachments ) ) : 0;
						if ( $attachment_count > 0 ) : ?>
							<span class="bs-attachment-count" title="<?php echo esc_attr( sprintf( __( '%d Anhänge', 'bs-custom-mail' ), $attachment_count ) ); ?>">
								<span class="dashicons dashicons-paperclip"></span>
								<?php echo esc_html( $attachment_count ); ?>
							</span>
						<?php endif; ?>
					</div>
					
					<div class="bs-template-actions" style="display: flex; gap: 8px;">
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=bs-custom-mail&action=edit&template=' . $template->template_key ) ); ?>" 
							class="button button-primary" style="flex: 1;">
							<span class="dashicons dashicons-edit" style="font-size: 16px; line-height: 1.4; margin-right: 4px;"></span>
							<?php esc_html_e( 'Bearbeiten', 'bs-custom-mail' ); ?>
						</a>
						<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=bs-custom-mail&action=delete&template=' . $template->template_key ), 'bs_delete_template_' . $template->template_key ) ); ?>" 
							class="button button-link-delete bs-confirm-delete"
							data-confirm="<?php esc_attr_e( 'Sind Sie sicher? Dieses Template wird unwiderruflich gelöscht.', 'bs-custom-mail' ); ?>"
							title="<?php esc_attr_e( 'Template löschen', 'bs-custom-mail' ); ?>">
							<span class="dashicons dashicons-trash" style="font-size: 16px; line-height: 1.4;"></span>
						</a>
					</div>
				</div>
			<?php endforeach; ?>
		</div>
		
		<div class="bs-info-section">
			<h3><span class="dashicons dashicons-info"></span> <?php esc_html_e( 'So funktioniert es', 'bs-custom-mail' ); ?></h3>
			
			<div class="bs-steps">
				<div class="bs-step">
					<div class="bs-step-number">1</div>
					<div class="bs-step-content">
						<h4><?php esc_html_e( 'Template bearbeiten', 'bs-custom-mail' ); ?></h4>
						<p><?php esc_html_e( 'Passe Betreff, Header, Inhalt und Footer an. Verwende Platzhalter für dynamische Inhalte.', 'bs-custom-mail' ); ?></p>
					</div>
				</div>
				<div class="bs-step">
					<div class="bs-step-number">2</div>
					<div class="bs-step-content">
						<h4><?php esc_html_e( 'Produkt zuordnen', 'bs-custom-mail' ); ?></h4>
						<p><?php esc_html_e( 'Gehe zu Produkte → Produkt bearbeiten → Tab "E-Mail Template". Wähle das Template und aktiviere es.', 'bs-custom-mail' ); ?></p>
					</div>
				</div>
				<div class="bs-step">
					<div class="bs-step-number">3</div>
					<div class="bs-step-content">
						<h4><?php esc_html_e( 'Automatischer Versand', 'bs-custom-mail' ); ?></h4>
						<p><?php esc_html_e( 'Bei Bestellungen mit dem Status "In Bearbeitung" wird die E-Mail automatisch mit Anhängen versendet.', 'bs-custom-mail' ); ?></p>
					</div>
				</div>
			</div>
		</div>
		
	<?php endif; ?>
	
</div>
