<?php
/**
 * Provide a admin area help view for the plugin
 *
 * @link       https://jltzbrg.com
 * @since      2.0.0
 *
 * @package    Bs_Custom_Mail
 * @subpackage Bs_Custom_Mail/admin/partials
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}
?>

<div class="wrap bs-custom-mail-help">
	<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

	<style>
		.bs-help-container {
			max-width: 1200px;
		}
		.bs-help-section {
			background: #fff;
			border: 1px solid #e5e7eb;
			border-radius: 8px;
			padding: 24px;
			margin-bottom: 24px;
		}
		.bs-help-section h2 {
			margin-top: 0;
			padding-bottom: 12px;
			border-bottom: 2px solid #f3f4f6;
		}
		.bs-help-section h3 {
			color: #1f2937;
			margin-top: 24px;
		}
		.bs-help-grid {
			display: grid;
			grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
			gap: 24px;
			margin-top: 20px;
		}
		.bs-help-card {
			background: #f9fafb;
			border-radius: 8px;
			padding: 20px;
			border-left: 4px solid #000;
		}
		.bs-help-card h4 {
			margin-top: 0;
			color: #000;
		}
		.bs-help-steps {
			counter-reset: step;
			list-style: none;
			padding: 0;
		}
		.bs-help-steps li {
			position: relative;
			padding-left: 50px;
			margin-bottom: 20px;
			min-height: 40px;
		}
		.bs-help-steps li::before {
			counter-increment: step;
			content: counter(step);
			position: absolute;
			left: 0;
			top: 0;
			width: 36px;
			height: 36px;
			background: #000;
			color: #fff;
			border-radius: 50%;
			display: flex;
			align-items: center;
			justify-content: center;
			font-weight: bold;
		}
		.bs-help-steps li strong {
			display: block;
			color: #1f2937;
			margin-bottom: 4px;
		}
		.bs-help-steps li p {
			margin: 0;
			color: #6b7280;
			font-size: 14px;
		}
		.bs-help-notice {
			background: #fef3c7;
			border-left: 4px solid #f59e0b;
			padding: 16px;
			margin: 16px 0;
			border-radius: 0 4px 4px 0;
		}
		.bs-help-notice.info {
			background: #dbeafe;
			border-left-color: #3b82f6;
		}
		.bs-help-notice.success {
			background: #d1fae5;
			border-left-color: #10b981;
		}
		.bs-code {
			background: #1f2937;
			color: #fff;
			padding: 2px 6px;
			border-radius: 4px;
			font-family: monospace;
			font-size: 13px;
		}
		.bs-placeholder-list {
			display: grid;
			grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
			gap: 12px;
			margin-top: 16px;
		}
		.bs-placeholder-item {
			background: #f3f4f6;
			padding: 12px;
			border-radius: 6px;
		}
		.bs-placeholder-item code {
			background: #000;
			color: #fff;
			padding: 4px 8px;
			border-radius: 4px;
			font-size: 12px;
		}
		.bs-placeholder-item span {
			display: block;
			font-size: 13px;
			color: #6b7280;
			margin-top: 4px;
		}
	</style>

	<div class="bs-help-container">
		
		<!-- Übersicht -->
		<div class="bs-help-section">
			<h2>📧 Was macht dieses Plugin?</h2>
			<p>Das <strong>Bootsschule Mail</strong> Plugin automatisiert E-Mails für deine Bootsschule. Es bietet zwei Hauptfunktionen:</p>
			
			<div class="bs-help-grid">
				<div class="bs-help-card">
					<h4>📧 Automatische E-Mails</h4>
					<p>Sendet automatisch personalisierte Bestellbestätigungen mit Kursinformationen, wenn Kunden Produkte kaufen.</p>
				</div>
				<div class="bs-help-card">
					<h4>🎁 Wertgutscheine</h4>
					<p>Ermöglicht den Verkauf von Wertgutscheinen mit automatischer PDF-Generierung und E-Mail-Versand.</p>
				</div>
			</div>
		</div>

		<!-- E-Mail Templates Anleitung -->
		<div class="bs-help-section">
			<h2>📧 E-Mail Templates einrichten</h2>
			
			<div class="bs-help-notice info">
				<strong>💡 Tipp:</strong> Das Plugin enthält bereits vordefinierte Templates für alle Kurstypen (SBF See, SBF Binnen, etc.). Du kannst diese nach Belieben anpassen.
			</div>

			<h3>Schritt-für-Schritt Anleitung:</h3>
			<ol class="bs-help-steps">
				<li>
					<strong>Template auswählen oder erstellen</strong>
					<p>Gehe zu <strong>Bootsschule Mail → 📧 E-Mail Templates</strong>. Wähle ein bestehendes Template oder erstelle ein neues mit "Neues Template".</p>
				</li>
				<li>
					<strong>Template bearbeiten</strong>
					<p>Passe Betreff, Inhalt und Platzhalter an. Verwende Platzhalter wie <span class="bs-code">{{Kundenname}}</span> für automatische Personalisierung.</p>
				</li>
				<li>
					<strong>Template einem Produkt zuweisen</strong>
					<p>Gehe zu <strong>Produkte → [Dein Produkt] bearbeiten</strong>. Scrolle zum Abschnitt "E-Mail Template" und wähle das passende Template aus.</p>
				</li>
				<li>
					<strong>Test-E-Mail senden</strong>
					<p>Im Template-Editor kannst du eine Test-E-Mail an dich selbst senden, um das Ergebnis zu prüfen.</p>
				</li>
			</ol>

			<h3>Verfügbare Platzhalter:</h3>
			<div class="bs-placeholder-list">
				<div class="bs-placeholder-item">
					<code>{{Kundenname}}</code>
					<span>Vorname des Kunden</span>
				</div>
				<div class="bs-placeholder-item">
					<code>{{customer_name}}</code>
					<span>Vorname (Englisch)</span>
				</div>
				<div class="bs-placeholder-item">
					<code>{{customer_full_name}}</code>
					<span>Vor- und Nachname</span>
				</div>
				<div class="bs-placeholder-item">
					<code>{{order_number}}</code>
					<span>Bestellnummer</span>
				</div>
				<div class="bs-placeholder-item">
					<code>{{order_date}}</code>
					<span>Bestelldatum</span>
				</div>
				<div class="bs-placeholder-item">
					<code>{{product_name}}</code>
					<span>Produktname</span>
				</div>
				<div class="bs-placeholder-item">
					<code>{{site_name}}</code>
					<span>Name der Website</span>
				</div>
				<div class="bs-placeholder-item">
					<code>{{site_url}}</code>
					<span>Website-URL</span>
				</div>
			</div>
		</div>

		<!-- Gutscheine Anleitung -->
		<div class="bs-help-section">
			<h2>🎁 Wertgutscheine einrichten</h2>
			
			<div class="bs-help-notice success">
				<strong>✅ Wichtig:</strong> Für die PDF-Generierung wird automatisch die FPDF-Bibliothek verwendet. Diese ist bereits im Plugin enthalten.
			</div>

			<h3>Schritt 1: Gutschein-Produkt erstellen</h3>
			<ol class="bs-help-steps">
				<li>
					<strong>Neues Produkt anlegen</strong>
					<p>Gehe zu <strong>Produkte → Neu hinzufügen</strong>. Gib dem Produkt einen Namen wie "Wertgutschein".</p>
				</li>
				<li>
					<strong>Als Gutschein aktivieren</strong>
					<p>Scrolle zum Abschnitt <strong>"🎁 Wertgutschein Einstellungen"</strong> und aktiviere "Wertgutschein aktivieren".</p>
				</li>
				<li>
					<strong>Gutscheinwert festlegen</strong>
					<p>Wähle zwischen:
						<br>• <strong>Fester Wert:</strong> Der Gutschein hat immer denselben Betrag
						<br>• <strong>Variabler Wert:</strong> Kunden können den Betrag selbst wählen (Min/Max festlegen)</p>
				</li>
				<li>
					<strong>PDF-Vorlage zuweisen (optional)</strong>
					<p>Wähle eine PDF-Vorlage aus oder erstelle eine neue unter <strong>Bootsschule Mail → 🎁 Gutscheine → PDF Templates</strong>.</p>
				</li>
			</ol>

			<h3>Schritt 2: PDF-Vorlage erstellen (optional)</h3>
			<ol class="bs-help-steps">
				<li>
					<strong>Neue Vorlage erstellen</strong>
					<p>Gehe zu <strong>Bootsschule Mail → 🎁 Gutscheine → PDF Templates → Neues Template</strong>.</p>
				</li>
				<li>
					<strong>Hintergrund wählen</strong>
					<p>Entweder eine Farbe oder ein Bild (JPG/PNG) als Hintergrund hochladen.</p>
				</li>
				<li>
					<strong>Textfelder positionieren</strong>
					<p>Ziehe die Felder (Gutscheinwert, Code, Name, Ablaufdatum) an die gewünschte Position.</p>
				</li>
				<li>
					<strong>Speichern</strong>
					<p>Das Template steht dann allen Gutschein-Produkten zur Verfügung.</p>
				</li>
			</ol>

			<h3>Was passiert beim Kauf?</h3>
			<p>Wenn ein Kunde einen Gutschein kauft:</p>
			<ul style="list-style: disc; margin-left: 20px;">
				<li>Ein eindeutiger Gutscheincode wird generiert (z.B. <span class="bs-code">WERT-AB12CD34</span>)</li>
				<li>Ein WooCommerce-Coupon wird automatisch erstellt</li>
				<li>Eine PDF-Gutschein wird generiert (falls Vorlage zugewiesen)</li>
				<li>Die E-Mail mit Gutschein-Details wird automatisch versendet</li>
				<li>Der Gutschein erscheint in der Übersicht unter <strong>🎁 Gutscheine</strong></li>
			</ul>
		</div>

		<!-- Troubleshooting -->
		<div class="bs-help-section">
			<h2>🔧 Häufige Fragen & Probleme</h2>
			
			<div class="bs-help-grid">
				<div class="bs-help-card">
					<h4>E-Mails kommen nicht an?</h4>
					<p>Prüfe im Spam-Ordner. Stelle sicher, dass WooCommerce-Bestell-E-Mails funktionieren. Installiere ggf. ein SMTP-Plugin wie "WP Mail SMTP".</p>
				</div>
				<div class="bs-help-card">
					<h4>PDF wird nicht generiert?</h4>
					<p>Stelle sicher, dass im Produkt eine PDF-Vorlage ausgewählt ist. Prüfe, ob das Upload-Verzeichnis beschreibbar ist.</p>
				</div>
				<div class="bs-help-card">
					<h4>Platzhalter werden nicht ersetzt?</h4>
					<p>Verwende exakt die Schreibweise mit geschweiften Klammern. Achte auf Groß-/Kleinschreibung.</p>
				</div>
				<div class="bs-help-card">
					<h4>Gutschein wurde storniert</h4>
					<p>Wenn eine Bestellung storniert wird, wird der Gutschein automatisch ungültig. Der Status ändert sich zu "Storniert".</p>
				</div>
			</div>
		</div>

		<!-- Support -->
		<div class="bs-help-section">
			<h2>📞 Support</h2>
			<p>Bei weiteren Fragen oder Problemen:</p>
			<ul style="list-style: disc; margin-left: 20px;">
				<li>E-Mail: <a href="mailto:jltbrg@gmail.com">jltbrg@gmail.com</a></li>
				<li>Website: <a href="https://jltzbrg.com" target="_blank">jltzbrg.com</a></li>
			</ul>
		</div>

	</div>
</div>
