<?php

/**
 * Fired during plugin activation
 *
 * @link       https://jltzbrg.com
 * @since      1.0.0
 *
 * @package    Bs_Custom_Mail
 * @subpackage Bs_Custom_Mail/includes
 */

/**
 * Fired during plugin activation.
 *
 * This class defines all code necessary to run during the plugin's activation.
 *
 * @since      1.0.0
 * @package    Bs_Custom_Mail
 * @subpackage Bs_Custom_Mail/includes
 * @author     Julio Litzenberg <jltbrg@gmail.com>
 */
class Bs_Custom_Mail_Activator {

	/**
	 * Activate the plugin.
	 *
	 * Creates database tables for email templates and statistics.
	 * Sets up default email templates for all 6 product types.
	 *
	 * @since    1.0.0
	 */
	public static function activate() {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();

		// Table for email templates
		$table_templates = $wpdb->prefix . 'bs_custom_mail_templates';
		$sql_templates = "CREATE TABLE IF NOT EXISTS $table_templates (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			template_key varchar(50) NOT NULL,
			template_name varchar(100) NOT NULL,
			subject varchar(255) NOT NULL,
			header_text text,
			content text NOT NULL,
			footer_text text,
			is_active tinyint(1) DEFAULT 1,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			UNIQUE KEY template_key (template_key)
		) $charset_collate;";

		// Table for email statistics
		$table_stats = $wpdb->prefix . 'bs_custom_mail_stats';
		$sql_stats = "CREATE TABLE IF NOT EXISTS $table_stats (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			order_id bigint(20) NOT NULL,
			customer_email varchar(255) NOT NULL,
			product_name varchar(255) NOT NULL,
			template_key varchar(50) NOT NULL,
			status varchar(20) NOT NULL,
			sent_at datetime DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY order_id (order_id),
			KEY template_key (template_key),
			KEY sent_at (sent_at)
		) $charset_collate;";

		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
		dbDelta( $sql_templates );
		dbDelta( $sql_stats );

		// Add attachments column if not exists (upgrade from older versions)
		self::maybe_add_attachments_column();

		// Insert default templates
		self::insert_default_templates();

		// Set default options
		add_option( 'bs_custom_mail_trigger_status', 'processing' );
		add_option( 'bs_custom_mail_from_name', get_bloginfo( 'name' ) );
		add_option( 'bs_custom_mail_from_email', get_option( 'admin_email' ) );
	}

	/**
	 * Add attachments column to templates table if not exists.
	 *
	 * @since    1.0.0
	 */
	private static function maybe_add_attachments_column() {
		global $wpdb;
		$table_name = $wpdb->prefix . 'bs_custom_mail_templates';
		
		// Check if attachments column exists
		$column_exists = $wpdb->get_results(
			"SHOW COLUMNS FROM $table_name LIKE 'attachments'"
		);
		
		if ( empty( $column_exists ) ) {
			$wpdb->query( "ALTER TABLE $table_name ADD COLUMN attachments text AFTER footer_text" );
		}
	}

	/**
	 * Insert default email templates for all product types.
	 *
	 * @since    1.0.0
	 */
	private static function insert_default_templates() {
		global $wpdb;
		$table_name = $wpdb->prefix . 'bs_custom_mail_templates';

		$templates = array(
			'sbf_see' => array(
				'name' => 'SBF See',
				'subject' => 'Ihre SBF See Ausbildung - Wichtige Informationen & Unterlagen',
			),
			'sbf_binnen' => array(
				'name' => 'SBF Binnen',
				'subject' => 'Ihre SBF Binnen Ausbildung - Wichtige Informationen & Unterlagen',
			),
			'sbf_kombi' => array(
				'name' => 'SBF Binnen See Kombi',
				'subject' => 'Ihre SBF Kombi Ausbildung - Wichtige Informationen & Unterlagen',
			),
			'ubi_src_kombi' => array(
				'name' => 'UBi SRC Kombi',
				'subject' => 'Ihre UBi SRC Kombi Ausbildung - Wichtige Informationen & Unterlagen',
			),
			'src_funkzeugnis' => array(
				'name' => 'SRC Funkzeugnis',
				'subject' => 'Ihr SRC Funkzeugnis Kurs - Wichtige Informationen & Unterlagen',
			),
			'gutschein' => array(
				'name' => 'Gutschein',
				'subject' => 'Ihr Bootsschule Gutschein - Vielen Dank für Ihren Kauf!',
			),
		);

		foreach ( $templates as $key => $template ) {
			$existing = $wpdb->get_var( $wpdb->prepare(
				"SELECT id FROM $table_name WHERE template_key = %s",
				$key
			) );

			if ( ! $existing ) {
				$wpdb->insert(
					$table_name,
					array(
						'template_key' => $key,
						'template_name' => $template['name'],
						'subject' => $template['subject'],
						'header_text' => self::get_default_header(),
						'content' => self::get_default_content( $key ),
						'footer_text' => self::get_default_footer(),
						'is_active' => 1,
					),
					array( '%s', '%s', '%s', '%s', '%s', '%s', '%d' )
				);
			}
		}
	}

	/**
	 * Get default email header HTML.
	 *
	 * @since    1.0.0
	 * @return   string
	 */
	private static function get_default_header() {
		return '<div style="text-align: center; padding: 20px; background: #003366;">
			<h1 style="color: #ffffff; margin: 0;">Bootsschule Berlin Köpenick</h1>
			<p style="color: #ffffff; margin: 10px 0 0 0;">Willkommen bei Ihrer Bootsschulausbildung!</p>
		</div>';
	}

	/**
	 * Get default email footer HTML.
	 *
	 * @since    1.0.0
	 * @return   string
	 */
	private static function get_default_footer() {
		return '<div style="text-align: center; padding: 20px; background: #f5f5f5; font-size: 12px; color: #666;">
			<p><strong>Bootsschule Berlin Köpenick</strong><br>
			Grünauer Str. 3, 12557 Berlin<br>
			Tel: 0163/6298589 | E-Mail: info@bootsschule-koepenick.de</p>
			<p><a href="https://bootsschule-koepenick.de/impressum">Impressum</a> | <a href="https://bootsschule-koepenick.de/datenschutz">Datenschutz</a></p>
		</div>';
	}

	/**
	 * Get default content based on template key.
	 *
	 * @since    1.0.0
	 * @param    string    $key    Template key.
	 * @return   string
	 */
	private static function get_default_content( $key ) {
		$contents = array(
			'sbf_see' => '<h2>Hallo {{customer_name}},</h2>
			<p>vielen Dank für Ihre Buchung der <strong>SBF See</strong> Ausbildung bei der Bootsschule Berlin Köpenick!</p>
			
			<h3>📍 Standort</h3>
			<p><strong>Grünauer Str. 3, 12557 Berlin</strong><br>
			Die theoretische Ausbildung findet in unseren modernen Schulungsräumen statt.</p>
			
			<h3>📋 Wichtige Dokumente</h3>
			<p>Bitte laden Sie sich die folgenden Dokumente herunter:</p>
			<ul>
				<li><a href="https://bootsschule-koepenick.de/downloads/pruefungsantrag-see">Prüfungsantrag SBF See</a></li>
				<li><a href="https://bootsschule-koepenick.de/downloads/attest-see">Ärztliches Attest</a></li>
				<li><a href="https://bootsschule-koepenick.de/downloads/gebuehren">Gebührenübersicht</a></li>
			</ul>
			
			<h3>📚 Lernmaterialien</h3>
			<ul>
				<li><a href="https://bootsschule-koepenick.de/online-kurse">Online-Lernkurse</a></li>
				<li><a href="https://bootsschule-koepenick.de/buecher">Empfohlene Fachbücher</a></li>
				<li><a href="https://bootsschule-koepenick.de/app">Bootsschule-App für unterwegs</a></li>
			</ul>
			
			<h3>🎥 Video-Materialien</h3>
			<p>Zusätzliche Lernvideos finden Sie in unserer <a href="https://dropbox.com/bootsschule-videos">Dropbox-Sammlung</a>.</p>
			
			<h3>📞 Praxis & Termine</h3>
			<p>Für Praxistermine und Rückfragen erreichen Sie uns unter:<br>
			<strong>Tel: 0163/6298589</strong><br>
			Mo-Fr: 09:00 - 18:00 Uhr</p>
			
			<p>Wir freuen uns auf Sie!<br>
			Ihr Team der Bootsschule Berlin Köpenick</p>',

			'sbf_binnen' => '<h2>Hallo {{customer_name}},</h2>
			<p>vielen Dank für Ihre Buchung der <strong>SBF Binnen</strong> Ausbildung bei der Bootsschule Berlin Köpenick!</p>
			
			<h3>📍 Standort</h3>
			<p><strong>Grünauer Str. 3, 12557 Berlin</strong><br>
			Die theoretische Ausbildung findet in unseren modernen Schulungsräumen statt.</p>
			
			<h3>📋 Wichtige Dokumente</h3>
			<p>Bitte laden Sie sich die folgenden Dokumente herunter:</p>
			<ul>
				<li><a href="https://bootsschule-koepenick.de/downloads/pruefungsantrag-binnen">Prüfungsantrag SBF Binnen</a></li>
				<li><a href="https://bootsschule-koepenick.de/downloads/attest-binnen">Ärztliches Attest</a></li>
				<li><a href="https://bootsschule-koepenick.de/downloads/gebuehren">Gebührenübersicht</a></li>
			</ul>
			
			<h3>📚 Lernmaterialien</h3>
			<ul>
				<li><a href="https://bootsschule-koepenick.de/online-kurse">Online-Lernkurse</a></li>
				<li><a href="https://bootsschule-koepenick.de/buecher">Empfohlene Fachbücher</a></li>
				<li><a href="https://bootsschule-koepenick.de/app">Bootsschule-App für unterwegs</a></li>
			</ul>
			
			<h3>🎥 Video-Materialien</h3>
			<p>Zusätzliche Lernvideos finden Sie in unserer <a href="https://dropbox.com/bootsschule-videos">Dropbox-Sammlung</a>.</p>
			
			<h3>📞 Praxis & Termine</h3>
			<p>Für Praxistermine und Rückfragen erreichen Sie uns unter:<br>
			<strong>Tel: 0163/6298589</strong><br>
			Mo-Fr: 09:00 - 18:00 Uhr</p>
			
			<p>Wir freuen uns auf Sie!<br>
			Ihr Team der Bootsschule Berlin Köpenick</p>',

			'sbf_kombi' => '<h2>Hallo {{customer_name}},</h2>
			<p>vielen Dank für Ihre Buchung der <strong>SBF Binnen & See Kombi</strong> Ausbildung bei der Bootsschule Berlin Köpenick!</p>
			
			<h3>📍 Standort</h3>
			<p><strong>Grünauer Str. 3, 12557 Berlin</strong><br>
			Die theoretische Ausbildung findet in unseren modernen Schulungsräumen statt.</p>
			
			<h3>📋 Wichtige Dokumente</h3>
			<p>Bitte laden Sie sich die folgenden Dokumente herunter:</p>
			<ul>
				<li><a href="https://bootsschule-koepenick.de/downloads/pruefungsantrag-binnen">Prüfungsantrag SBF Binnen</a></li>
				<li><a href="https://bootsschule-koepenick.de/downloads/pruefungsantrag-see">Prüfungsantrag SBF See</a></li>
				<li><a href="https://bootsschule-koepenick.de/downloads/attest">Ärztliches Attest</a></li>
				<li><a href="https://bootsschule-koepenick.de/downloads/gebuehren">Gebührenübersicht</a></li>
			</ul>
			
			<h3>📚 Lernmaterialien</h3>
			<ul>
				<li><a href="https://bootsschule-koepenick.de/online-kurse">Online-Lernkurse</a></li>
				<li><a href="https://bootsschule-koepenick.de/buecher">Empfohlene Fachbücher</a></li>
				<li><a href="https://bootsschule-koepenick.de/app">Bootsschule-App für unterwegs</a></li>
			</ul>
			
			<h3>🎥 Video-Materialien</h3>
			<p>Zusätzliche Lernvideos finden Sie in unserer <a href="https://dropbox.com/bootsschule-videos">Dropbox-Sammlung</a>.</p>
			
			<h3>📞 Praxis & Termine</h3>
			<p>Für Praxistermine und Rückfragen erreichen Sie uns unter:<br>
			<strong>Tel: 0163/6298589</strong><br>
			Mo-Fr: 09:00 - 18:00 Uhr</p>
			
			<p>Wir freuen uns auf Sie!<br>
			Ihr Team der Bootsschule Berlin Köpenick</p>',

			'ubi_src_kombi' => '<h2>Hallo {{customer_name}},</h2>
			<p>vielen Dank für Ihre Buchung der <strong>UBi SRC Kombi</strong> Ausbildung bei der Bootsschule Berlin Köpenick!</p>
			
			<h3>📍 Standort</h3>
			<p><strong>Grünauer Str. 3, 12557 Berlin</strong><br>
			Die theoretische Ausbildung findet in unseren modernen Schulungsräumen statt.</p>
			
			<h3>📋 Wichtige Dokumente</h3>
			<p>Bitte laden Sie sich die folgenden Dokumente herunter:</p>
			<ul>
				<li><a href="https://bootsschule-koepenick.de/downloads/pruefungsantrag-ubi">Prüfungsantrag UBi</a></li>
				<li><a href="https://bootsschule-koepenick.de/downloads/src-antrag">SRC Antrag</a></li>
				<li><a href="https://bootsschule-koepenick.de/downloads/gebuehren">Gebührenübersicht</a></li>
			</ul>
			
			<h3>📚 Lernmaterialien</h3>
			<ul>
				<li><a href="https://bootsschule-koepenick.de/online-kurse">Online-Lernkurse</a></li>
				<li><a href="https://bootsschule-koepenick.de/buecher">Empfohlene Fachbücher</a></li>
				<li><a href="https://bootsschule-koepenick.de/app">Bootsschule-App für unterwegs</a></li>
			</ul>
			
			<h3>📞 Praxis & Termine</h3>
			<p>Für Praxistermine und Rückfragen erreichen Sie uns unter:<br>
			<strong>Tel: 0163/6298589</strong><br>
			Mo-Fr: 09:00 - 18:00 Uhr</p>
			
			<p>Wir freuen uns auf Sie!<br>
			Ihr Team der Bootsschule Berlin Köpenick</p>',

			'src_funkzeugnis' => '<h2>Hallo {{customer_name}},</h2>
			<p>vielen Dank für Ihre Buchung des <strong>SRC Funkzeugnis</strong> Kurses bei der Bootsschule Berlin Köpenick!</p>
			
			<h3>📍 Standort</h3>
			<p><strong>Grünauer Str. 3, 12557 Berlin</strong><br>
			Die Ausbildung findet in unseren modernen Schulungsräumen statt.</p>
			
			<h3>📋 Wichtige Dokumente</h3>
			<p>Bitte laden Sie sich die folgenden Dokumente herunter:</p>
			<ul>
				<li><a href="https://bootsschule-koepenick.de/downloads/src-antrag">SRC Antragsformular</a></li>
				<li><a href="https://bootsschule-koepenick.de/downloads/src-gebuehren">Gebührenübersicht</a></li>
			</ul>
			
			<h3>📚 Lernmaterialien</h3>
			<ul>
				<li><a href="https://bootsschule-koepenick.de/src-online-kurs">SRC Online-Lernkurs</a></li>
				<li><a href="https://bootsschule-koepenick.de/buecher">Fachbücher</a></li>
			</ul>
			
			<h3>📞 Termine & Kontakt</h3>
			<p>Für Termine und Rückfragen erreichen Sie uns unter:<br>
			<strong>Tel: 0163/6298589</strong><br>
			Mo-Fr: 09:00 - 18:00 Uhr</p>
			
			<p>Wir freuen uns auf Sie!<br>
			Ihr Team der Bootsschule Berlin Köpenick</p>',

			'gutschein' => '<h2>Hallo {{customer_name}},</h2>
			<p>vielen Dank für Ihren Gutscheinkauf bei der Bootsschule Berlin Köpenick!</p>
			
			<h3>🎁 Ihr Gutschein</h3>
			<p>Ihr Gutschein wurde erfolgreich erworben und ist ab sofort einlösbar.</p>
			
			<p><strong>Bestelldetails:</strong><br>
			Bestellnummer: {{order_number}}<br>
			Bestelldatum: {{order_date}}</p>
			
			<h3>📧 Gutschein-Versand</h3>
			<p>Der Gutschein wurde an die von Ihnen angegebene E-Mail-Adresse versandt. Bitte prüfen Sie auch Ihren Spam-Ordner.</p>
			
			<h3>💡 Einlösung</h3>
			<p>Der Gutschein kann für alle Kurse und Produkte in unserem Shop eingelöst werden. Bei der Buchung einfach den Gutscheincode im Warenkorb eingeben.</p>
			
			<h3>📞 Fragen?</h3>
			<p>Bei Fragen zur Einlösung erreichen Sie uns unter:<br>
			<strong>Tel: 0163/6298589</strong><br>
			E-Mail: info@bootsschule-koepenick.de</p>
			
			<p>Wir wünschen viel Freude mit dem Gutschein!<br>
			Ihr Team der Bootsschule Berlin Köpenick</p>',
		);

		return isset( $contents[ $key ] ) ? $contents[ $key ] : $contents['sbf_see'];
	}

}
