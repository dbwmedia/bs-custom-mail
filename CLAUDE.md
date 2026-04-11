# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What This Plugin Does

**Bootsschule Mail & Vouchers** (`bs-custom-mail`) is a WooCommerce plugin for Bootsschule Berlin Köpenick that:
- Sends automated, product-specific HTML order confirmation emails with file attachments
- Generates and manages PDF gift vouchers
- Provides a React-based admin interface for managing email templates, vouchers, and settings

Trigger: WooCommerce order status change to "processing" (configurable). Six default product templates: `sbf_see`, `sbf_binnen`, `sbf_kombi`, `ubi_src_kombi`, `src_funkzeugnis`, `gutschein`.

## Commands

### PHP
```bash
composer install                        # Install PHP dependencies (includes FPDF + PHPUnit)
./vendor/bin/phpunit                    # Run all PHP tests
./vendor/bin/phpunit --testsuite=unit   # Run only unit tests
./vendor/bin/phpunit --testsuite=integration  # Run only integration tests
./vendor/bin/phpcs --standard=WordPress includes/ admin/ public/   # PHP lint
./vendor/bin/phpcbf --standard=WordPress includes/ admin/ public/  # PHP autofix
```

### JavaScript / React
```bash
npm install         # Install Node dependencies
npm start           # Dev server with hot reload (wp-scripts start)
npm run build       # Production build to build/
npm run lint:js     # ESLint
npm run lint:css    # Stylelint
npm run format      # wp-scripts format
npm run test:unit   # Jest tests
```

### Distribution
```bash
just dist           # Full pipeline: clean → test → build → zip
just dist-quick     # Skip tests: clean → build → zip
just lint           # JS + CSS linting
```

## Architecture

### Dual-Layer: PHP Backend + React Frontend

The plugin uses a **WordPress Plugin Boilerplate** OOP structure for the PHP backend and a **React SPA** (via `@wordpress/scripts`) for the admin UI.

**PHP classes** (in `includes/`, `admin/`, `public/`) are autoloaded via Composer classmap. All hooks are registered through `Bs_Custom_Mail_Loader` and executed via `Bs_Custom_Mail::run()` in `bs-custom-mail.php`.

**React SPA** lives in `src/admin/` and compiles to `build/`. It communicates with the PHP backend exclusively through the REST API (`/wp-json/bs-custom-mail/v1/`).

### Key PHP Classes

| Class | File | Responsibility |
|---|---|---|
| `Bs_Custom_Mail` | `includes/class-bs-custom-mail.php` | Bootstraps plugin, wires dependencies |
| `Bs_Custom_Mail_Loader` | `includes/class-bs-custom-mail-loader.php` | Collects and registers all WP hooks |
| `Bs_Custom_Mail_Email_Sender` | `includes/class-bs-custom-mail-email-sender.php` | WooCommerce order hook → template match → `wp_mail()` |
| `Bs_Custom_Mail_REST_API` | `includes/class-bs-custom-mail-rest-api.php` | All REST endpoints (`bs-custom-mail/v1`) |
| `Bs_Custom_Mail_Voucher` | `includes/class-bs-custom-mail-voucher.php` | Voucher generation, PDF creation, WooCommerce integration |
| `Bs_Custom_Mail_PDF_Generator` | `includes/class-bs-custom-mail-pdf-generator.php` | PDF creation via FPDF (setasign/fpdf in vendor/) |
| `Bs_Custom_Mail_Product` | `includes/class-bs-custom-mail-product.php` | Product editor tab, product-level attachment meta |
| `Bs_Custom_Mail_Activator` | `includes/class-bs-custom-mail-activator.php` | DB table creation, schema upgrades on activation |

### REST API Endpoints (`/wp-json/bs-custom-mail/v1/`)

- `GET/POST /templates` — list / create templates
- `GET/PUT/DELETE /templates/{key}` — read / update / delete a template
- `POST /templates/{key}/test` — send test email
- `GET /stats`, `GET /stats/recent` — statistics
- `GET/POST /vouchers` — list / create vouchers
- `GET/PUT/DELETE /vouchers/{id}` — read / update / cancel vouchers
- `GET /vouchers/stats` — voucher statistics

### Database Tables

- `wp_bs_custom_mail_templates` — email template definitions (key, subject, header_text, content, footer_text, attachments CSV, is_active)
- `wp_bs_custom_mail_stats` — email sending log (order_id, template_key, status)

**Product-level settings** are stored as post meta: `_bs_custom_mail_send_custom`, `_bs_custom_mail_template`, `_bs_custom_mail_custom_recipients`, `_bs_custom_mail_attachments`.

### Attachment System

Attachments exist at two levels that are merged when sending:
1. **Template-level**: stored as comma-separated media IDs in `wp_bs_custom_mail_templates.attachments`
2. **Product-level**: stored as serialized array in `_bs_custom_mail_attachments` post meta

Both resolve to WordPress Media Library files via `get_attached_file()`.

### React Admin SPA (`src/admin/`)

Entry point: `src/admin/index.tsx` → `App.tsx` → views in `src/admin/views/`

Views: `TemplateList`, `TemplateEditor`, `PDFTemplateList`, `PDFTemplateEditor`, `VoucherList`, `Stats`, `Settings`

Data fetching via custom hooks in `src/admin/hooks/` using `@wordpress/api-fetch`.

## Coding Conventions

**Hooks**: Always use the loader pattern — never call `add_action()`/`add_filter()` directly in plugin classes:
```php
$this->loader->add_action( 'hook_name', $component, 'callback' );
```

**Naming**:
- Classes: `Bs_Custom_Mail_*`
- Files: `class-bs-custom-mail-*.php`
- Constants: `BS_CUSTOM_MAIL_*`

**Security**: Every PHP file must guard against direct access. Use `$wpdb->prepare()` for SQL, nonces for forms, `current_user_can()` before admin operations, `sanitize_*()` on input, `esc_*()` on output.

**Language**: Code comments in English; all user-facing strings in German (business requirement). Use the `bs-custom-mail` text domain.

## Email Placeholders

`{{customer_name}}`, `{{customer_full_name}}`, `{{order_number}}`, `{{order_date}}`, `{{product_name}}`, `{{site_name}}`, `{{site_url}}`

Add new placeholders in `parse_placeholders()` in `Bs_Custom_Mail_Email_Sender`.

## Test Structure

PHPUnit tests in `tests/phpunit/` with suites `unit` (in `Unit/`) and `integration` (in `Integration/`). Bootstrap at `tests/phpunit/bootstrap.php`. Coverage reports output to `tests/coverage-report/`.

## Dependencies

- **Runtime**: PHP ≥7.4, WordPress ≥6.4, WooCommerce ≥8.0, `setasign/fpdf ^1.8` (PDF generation)
- **Dev PHP**: `phpunit/phpunit ^9.6`, `wp-coding-standards/wpcs ^3.0`
- **Dev JS**: `@wordpress/scripts ^27`, Jest, TypeScript, React 18
