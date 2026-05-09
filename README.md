# Bootsschule Mail & Vouchers (`bs-custom-mail`)

WooCommerce-Plugin für die **Bootsschule Berlin Köpenick**: automatische, produktbezogene **HTML-Bestellbestätigungen** mit Anhängen, **PDF-Wertgutscheine** und eine **React-Admin-Oberfläche** zur Pflege von Templates, Gutscheinen und Einstellungen.

**Version:** 2.0.0 · **Lizenz:** GPL-2.0+

## Funktionen

- Versand personalisierter E-Mails bei WooCommerce-Bestellungen (Trigger-Status konfigurierbar, Standard: `processing`)
- Sechs vordefinierte Template-Typen (u. a. SBF See/Binnen/Kombi, UBi/SRC, Gutschein) — beliebig erweiterbar
- **Zwei Ebenen für Anhänge:** pro Template (Medien-IDs in der DB) und pro Produkt (Post-Meta)
- Platzhalter im Text: u. a. `{{customer_name}}`, `{{order_number}}`, `{{product_name}}`, …
- PDF-Gutscheine inkl. Verwaltung über die Admin-UI
- REST-API unter Namespace `bs-custom-mail/v1` für das React-Frontend
- Logging in `wp_bs_custom_mail_stats`, Template-Definitionen in `wp_bs_custom_mail_templates`

## Voraussetzungen

| Komponente   | Minimum (empfohlen) |
|-------------|---------------------|
| PHP         | 7.4+ (8.x)          |
| WordPress   | 6.4+                |
| WooCommerce | 8.0+                |

Zusätzlich: **Composer** (PHP-Abhängigkeiten, u. a. FPDF) und für die Admin-UI **Node.js + npm**. Für Release-ZIPs: **`zip`** im PATH (siehe `Justfile`).

## Installation (Produktion)

1. Ordner nach `wp-content/plugins/bs-custom-mail/` kopieren **oder** fertiges Archiv aus `dist/` entpacken.
2. Im Plugin-Verzeichnis: `composer install --no-dev --optimize-autoloader`
3. Admin-UI bauen: `npm ci && npm run build` (liefert `build/`)
4. Plugin in WordPress aktivieren (legt Tabellen an / führt Schema-Updates aus).
5. WooCommerce-Produkte mit Template verknüpfen; Einstellungen unter dem Plugin-Menü setzen.

**Hinweis:** Zuverlässiger Versand oft über **SMTP** (z. B. WP Mail SMTP) — reines `wp_mail()` ist hostabhängig.

## Entwicklung

### Einrichtung

```bash
composer install
npm install
```

### Übliche Befehle

| Aufgabe | Befehl |
|--------|--------|
| React-Dev-Server (HMR) | `npm start` |
| Produktions-Build | `npm run build` |
| PHPUnit | `./vendor/bin/phpunit` · nur Unit: `./vendor/bin/phpunit --testsuite=unit` |
| Jest (Admin) | `npm run test:unit` |
| PHP-CS (WPCS) | `./vendor/bin/phpcs --standard=WordPress includes/ admin/ public/` |
| ESLint / Stylelint | `npm run lint:js` · `npm run lint:css` |

### Just (optional)

Mit [just](https://github.com/casey/just): `just` ohne Argument zeigt Hilfe. Wichtig:

- `just dist` — Tests, Build, ZIP unter `dist/` (ohne `src/`, `node_modules`, …)
- `just dist-quick` — wie oben, **ohne** Tests
- `just ci` — Check, install, lint, test, build

## Architektur (Kurz)

- **PHP:** Boilerplate-Struktur unter `includes/`, `admin/`, `public/` — Hooks über `Bs_Custom_Mail_Loader`, Einstieg `Bs_Custom_Mail::run()`.
- **React:** Quellcode in `src/admin/`, Ausgabe in `build/`; Kommunikation nur über REST.
- Wichtige Klassen u. a.: `Bs_Custom_Mail_Email_Sender`, `Bs_Custom_Mail_REST_API`, `Bs_Custom_Mail_Voucher`, `Bs_Custom_Mail_PDF_Generator`.

Ausführlichere technische Notizen: `CLAUDE.md`, `AGENTS.md`.

## REST-API (Auszug)

Basis: `/wp-json/bs-custom-mail/v1/`

- Templates: `GET/POST /templates`, `GET/PUT/DELETE /templates/{key}`, `POST /templates/{key}/test`
- Statistik: `GET /stats`, `GET /stats/recent`
- Gutscheine: `GET/POST /vouchers`, `GET/PUT/DELETE /vouchers/{id}`, `GET /vouchers/stats`

## Tests & Qualität

- PHPUnit: `tests/phpunit/` (Suites `unit` / `integration`), Bootstrap `tests/phpunit/bootstrap.php`
- Coverage PHP: z. B. `composer test-coverage` oder `./vendor/bin/phpunit --coverage-html tests/coverage-report`

## Projektstruktur (relevant)

```
bs-custom-mail.php          # Bootstrap, Konstanten, Version
includes/                   # Kern (E-Mail, REST, Voucher, PDF, …)
admin/ public/              # WP-Admin / öffentliche Hooks
src/admin/                  # React SPA (TypeScript)
build/                      # Kompilierte Admin-Assets (nicht von Hand editieren)
languages/                  # Textdomain bs-custom-mail
```

---

**Autor:** Julio Litzenberg · [jltzbrg.com](https://jltzbrg.com)
