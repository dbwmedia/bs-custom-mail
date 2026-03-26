# AGENTS.md - Bootsschule Custom Mail Plugin

This document provides essential information for AI coding agents working on the **Bootsschule Custom Mail** WordPress plugin.

## Project Overview

**Bootsschule Custom Mail** (`bs-custom-mail`) is a WordPress plugin designed to send automated, product-specific order confirmation emails for Bootsschule Berlin Köpenick (a boat school). The plugin integrates with WooCommerce to detect purchased courses and send customized HTML emails with relevant documents, learning materials, and contact information.

### Purpose
- Automatically send personalized confirmation emails when customers purchase boat school courses
- Support 6 product types: SBF See, SBF Binnen, SBF Kombi, UBi SRC Kombi, SRC Funkzeugnis, and Gutschein
- Reduce support tickets by providing all necessary information upfront
- Provide a modern admin interface for editing email templates and managing attachments
- Support both template-level and product-level attachments

### Key Business Requirements (from PRD)
- **Trigger**: WooCommerce order status change to "processing" (configurable)
- **Performance**: <100ms per email
- **Target**: 100% automated email delivery, 70% reduction in support tickets

## Technology Stack

- **Language**: PHP (WordPress Coding Standards)
- **Platform**: WordPress 6.4+, WooCommerce 8.0+
- **Frontend**: jQuery, vanilla CSS (modern card-based UI)
- **License**: GPL-2.0+
- **Text Domain**: `bs-custom-mail`
- **Version**: 1.0.0 (Semantic Versioning)

## Project Structure

This plugin follows the **WordPress Plugin Boilerplate** pattern with object-oriented architecture:

```
bs-custom-mail/
├── bs-custom-mail.php          # Main plugin file (bootstrap)
├── index.php                   # Silence is golden
├── uninstall.php               # Uninstall hook handler
├── README.txt                  # WordPress.org readme template
├── LICENSE.txt                 # GPL-2.0 license
├── PRD.md                      # Product Requirements Document (German)
├── AGENTS.md                   # This file
│
├── includes/                   # Core plugin classes
│   ├── class-bs-custom-mail.php              # Main plugin class
│   ├── class-bs-custom-mail-activator.php    # Activation hook + DB setup
│   ├── class-bs-custom-mail-deactivator.php  # Deactivation hook
│   ├── class-bs-custom-mail-loader.php       # Hook loader/orchestrator
│   ├── class-bs-custom-mail-i18n.php         # Internationalization
│   ├── class-bs-custom-mail-email-sender.php # Email sending logic
│   ├── class-bs-custom-mail-product.php      # Product template assignment
│   └── index.php
│
├── admin/                      # Admin-facing functionality
│   ├── class-bs-custom-mail-admin.php        # Admin class
│   ├── partials/
│   │   ├── bs-custom-mail-admin-display.php  # Templates list/edit view
│   │   ├── bs-custom-mail-admin-settings.php # Settings page
│   │   └── bs-custom-mail-admin-stats.php    # Statistics page
│   ├── css/
│   │   └── bs-custom-mail-admin.css          # Modern card-based styles
│   ├── js/
│   │   └── bs-custom-mail-admin.js           # Media uploader, AJAX
│   └── index.php
│
├── public/                     # Public-facing functionality
│   ├── class-bs-custom-mail-public.php       # Public class
│   ├── css/
│   │   └── bs-custom-mail-public.css
│   ├── js/
│   │   └── bs-custom-mail-public.js
│   └── index.php
│
└── languages/                  # Translation files
    └── bs-custom-mail.pot      # Template file
```

## Database Schema

### Tables

#### `wp_bs_custom_mail_templates`
Stores email template definitions:
- `id` (bigint, PK, AUTO_INCREMENT)
- `template_key` (varchar 50, UNIQUE) - e.g., 'sbf_see', 'gutschein'
- `template_name` (varchar 100) - Display name
- `subject` (varchar 255) - Email subject line
- `header_text` (text) - HTML header content
- `content` (text) - Main email body content
- `footer_text` (text) - HTML footer content
- `attachments` (text) - Comma-separated attachment IDs (NEW)
- `is_active` (tinyint 1) - Enable/disable template
- `created_at` (datetime)
- `updated_at` (datetime)

#### `wp_bs_custom_mail_stats`
Stores email sending statistics:
- `id` (bigint, PK, AUTO_INCREMENT)
- `order_id` (bigint) - WooCommerce order ID
- `customer_email` (varchar 255)
- `product_name` (varchar 255)
- `template_key` (varchar 50)
- `status` (varchar 20) - 'sent', 'failed', 'template_not_found'
- `sent_at` (datetime)

### Post Meta (Product Level)
- `_bs_custom_mail_send_custom` (yes/no) - Enable custom emails for product
- `_bs_custom_mail_template` (string) - Assigned template key
- `_bs_custom_mail_custom_recipients` (string) - CC emails (comma-separated)
- `_bs_custom_mail_attachments` (array) - Product-specific attachment IDs

## Architecture Pattern

### Hook-Based Architecture
The plugin uses WordPress's hook system exclusively. No direct execution occurs during plugin load - everything is registered via hooks.

### Core Classes

1. **`Bs_Custom_Mail`** (main plugin class)
   - Located in `includes/class-bs-custom-mail.php`
   - Orchestrates the plugin by loading dependencies and registering hooks
   - Manages plugin name and version

2. **`Bs_Custom_Mail_Loader`** (hook orchestrator)
   - Located in `includes/class-bs-custom-mail-loader.php`
   - Collects actions and filters
   - Registers them with WordPress via `run()` method

3. **`Bs_Custom_Mail_Email_Sender`** (email functionality)
   - Located in `includes/class-bs-custom-mail-email-sender.php`
   - Handles WooCommerce order status changes
   - Manages template matching and email sending
   - Combines template and product attachments
   - Builds HTML email body with attachment sections

4. **`Bs_Custom_Mail_Product`** (product integration)
   - Located in `includes/class-bs-custom-mail-product.php`
   - Adds template selection to WooCommerce product editor
   - Handles product-level attachment management
   - Adds custom columns to product list

5. **`Bs_Custom_Mail_i18n`** (internationalization)
   - Handles text domain loading for translations

6. **`Bs_Custom_Mail_Activator`** / **`Bs_Custom_Mail_Deactivator`**
   - Static classes for activation/deactivation hooks
   - Creates database tables on activation
   - Handles database schema upgrades

7. **`Bs_Custom_Mail_Admin`** / **`Bs_Custom_Mail_Public`**
   - Admin and public-facing functionality
   - Handle CSS/JS enqueuing
   - Admin page registration and form handling

## Attachment System

### Two-Level Attachment Architecture

The plugin supports attachments at TWO levels that are combined when sending emails:

#### 1. Template-Level Attachments
- Managed in: **Bootsschule Emails → Templates → Edit Template**
- Stored in: `wp_bs_custom_mail_templates.attachments` (comma-separated IDs)
- Use case: General documents for all products using this template (e.g., SBF See course information PDF)
- Visible to customers: Yes, in email body with download box

#### 2. Product-Level Attachments
- Managed in: **Products → Edit Product → E-Mail Template tab**
- Stored in: Post meta `_bs_custom_mail_attachments` (serialized array)
- Use case: Product-specific documents (e.g., specific schedule for a particular course date)
- Visible to customers: Yes, combined with template attachments in email body

### How Attachments Work

1. When an order reaches the trigger status (e.g., "processing")
2. Plugin identifies the product and assigned template
3. Plugin collects attachments from BOTH sources:
   - Template attachments (via `get_template_attachments()`)
   - Product attachments (via `get_product_attachments()`)
4. Attachments are:
   - Added as email attachments via `wp_mail()`
   - Displayed in email body with styled download boxes
   - Shown with file icons (PDF, Word, Excel, etc.)
   - Include file size information

### Attachment Display in Emails

```html
📎 Angehängte Dokumente

┌─────────────────────────────┐
│ 📄  Information-SBF-See.pdf │
│     PDF • 245 KB            │
└─────────────────────────────┘

┌─────────────────────────────┐
│ 📝  Checkliste.docx         │
│     WORD • 128 KB           │
└─────────────────────────────┘
```

## Admin Interface

### Modern Card-Based UI
The admin interface uses a modern, responsive design with:
- Card-based layouts instead of traditional tables
- Visual status indicators (badges)
- Sticky sidebars for quick actions
- Copy-to-clipboard for placeholders
- Drag-and-drop media uploaders

### Pages

1. **Templates List** (`admin.php?page=bs-custom-mail`)
   - Grid of all templates with status badges
   - Shows attachment count per template
   - Last edited timestamps

2. **Template Editor** (`action=edit`)
   - Two-column layout: Editor left, Sidebar right
   - Sections: Template Details, Attachments, Header, Content, Footer
   - Real-time placeholder copying
   - Test email sending
   - Toggle switch for active/inactive

3. **Settings** (`page=bs-custom-mail-settings`)
   - Trigger status selection
   - Sender name/email configuration
   - System status checks
   - Test email with template selection

4. **Statistics** (`page=bs-custom-mail-stats`)
   - Dashboard with success rate
   - Per-template statistics with progress bars
   - Recent activity feed

## Coding Conventions

### File Headers
All PHP files must include a standardized DocBlock header:
```php
/**
 * Short description
 *
 * Long description
 *
 * @link       https://jltzbrg.com
 * @since      1.0.0
 *
 * @package    Bs_Custom_Mail
 * @subpackage Bs_Custom_Mail/includes
 * @author     Julio Litzenberg <jltbrg@gmail.com>
 */
```

### Naming Conventions
- **Classes**: `Bs_Custom_Mail_*` (PascalCase with underscores)
- **Functions**: `snake_case()` with plugin prefix
- **Constants**: `BS_CUSTOM_MAIL_*` (uppercase with underscores)
- **Files**: `class-bs-custom-mail-*.php` (kebab-case)

### Security Standards
- All files must check for `WPINC` constant to prevent direct access:
  ```php
  if ( ! defined( 'WPINC' ) ) {
      die;
  }
  ```
- Uninstall checks for `WP_UNINSTALL_PLUGIN` constant
- Follow WordPress Coding Standards for SQL queries (use `$wpdb->prepare()`)
- Use nonces for form submissions (`wp_nonce_field()`, `check_admin_referer()`)
- Sanitize all inputs (`sanitize_text_field()`, `wp_kses_post()`)
- Escape all outputs (`esc_html()`, `esc_attr()`, `esc_url()`)

### Hooks Registration
Always use the loader pattern:
```php
$this->loader->add_action( 'hook_name', $component, 'callback', $priority, $accepted_args );
$this->loader->add_filter( 'hook_name', $component, 'callback', $priority, $accepted_args );
```

## Development Guidelines

### Managing Email Templates (CRUD)

The plugin supports full CRUD operations for templates:

#### Creating Templates
- Navigate to **Bootsschule Emails → Templates**
- Click **"Neues Template"** button
- Fill in required fields:
  - **Template Key**: Unique identifier (lowercase, numbers, underscores only)
  - **Template Name**: Display name in admin
  - **Subject**: Email subject line
- Optional: Add template-level attachments
- Save creates template and redirects to edit page

#### Editing Templates
- Click **"Bearbeiten"** on any template card
- Modify all fields including content, header, footer
- Add/remove attachments
- Toggle active/inactive status
- Changes apply immediately to future emails

#### Deleting Templates
- Click **trash icon** on template card
- Confirm deletion in browser dialog
- Template is permanently removed
- ⚠️ Products using this template will no longer send custom emails

#### Default Templates
Six default templates are created on activation:
- `sbf_see` - SBF See
- `sbf_binnen` - SBF Binnen  
- `sbf_kombi` - SBF Binnen See Kombi
- `ubi_src_kombi` - UBi SRC Kombi
- `src_funkzeugnis` - SRC Funkzeugnis
- `gutschein` - Gutschein

These can be edited or deleted like any custom template.

### Adding Attachments Support

For template-level attachments:
- Uses WordPress Media Uploader (`wp_enqueue_media()`)
- Stored as comma-separated IDs in database
- Processed via `get_template_attachments()` and `get_template_attachment_data()`

For product-level attachments:
- Uses same Media Uploader
- Stored as serialized array in post meta
- Processed via `get_product_attachments()` and `get_attachment_data_for_display()`

### Customizing Email Content

Available placeholders (automatically replaced):
- `{{customer_name}}` - First name
- `{{customer_full_name}}` - Full name
- `{{order_number}}` - Order number
- `{{order_date}}` - Order date (formatted)
- `{{product_name}}` - Product name
- `{{site_name}}` - Website name
- `{{site_url}}` - Website URL

## Testing Strategy

### Manual Testing Checklist
1. Install and activate plugin
2. Verify no PHP errors/warnings
3. Test database table creation
4. Test template editing and saving
5. Test attachment upload (template and product level)
6. Test WooCommerce order completion flow
7. Verify email delivery with correct template and attachments
8. Verify attachment display in email body
9. Test admin interface functionality
10. Test deactivation/uninstallation

### Compatibility Requirements
- WordPress 6.4+
- WooCommerce 8.0+
- PHP 7.4+ (recommended: 8.0+)

## Deployment Process

### Installation
1. Upload plugin folder to `/wp-content/plugins/`
2. Activate through WordPress admin
3. Database tables auto-created on activation
4. Configure templates via admin interface
5. Assign templates to products

### Database Upgrades
The plugin handles database schema upgrades automatically:
- `maybe_add_attachments_column()` adds new columns if missing
- Runs on every activation
- Safe to run multiple times (checks if column exists)

### No Build Process
This is a pure PHP plugin with no build tools (no npm, composer, webpack, etc.). Files are served directly as-is.

## Internationalization

- Text domain: `bs-custom-mail`
- Translation files located in `/languages/`
- Template file: `bs-custom-mail.pot`
- Domain path: `/languages`

Use WordPress translation functions:
```php
__( 'Text to translate', 'bs-custom-mail' );
esc_html__( 'Text to translate', 'bs-custom-mail' );
```

User-facing content should be in **German** (business requirement), code comments in **English**.

## Security Considerations

1. **Nonce Verification**: Use `wp_nonce_field()` and `check_admin_referer()` for form submissions
2. **Capability Checks**: Use `current_user_can()` before admin actions
3. **Data Sanitization**: Use `sanitize_text_field()`, `wp_kses_post()`, etc.
4. **Output Escaping**: Use `esc_html()`, `esc_attr()`, `esc_url()` when outputting data
5. **SQL Queries**: Use `$wpdb->prepare()` for dynamic queries
6. **File Uploads**: Uses WordPress Media Library (already secured)
7. **Attachment Access**: Only uses `get_attached_file()` - respects WordPress permissions

## Common Tasks

### Adding a New Placeholder
1. Add to `parse_placeholders()` in email-sender class
2. Add to copyable list in admin template editor
3. Document in AGENTS.md

### Debugging Email Issues
1. Enable `WP_DEBUG` and `WP_DEBUG_LOG`
2. Check `wp_bs_custom_mail_stats` table for status
3. Use WP Mail Logging plugin to see sent emails
4. Check server mail logs
5. Verify attachment files exist via `get_attached_file()`

### Adding New File Type Icons
1. Update `get_file_icon()` in email-sender class
2. Map MIME type to emoji icon
3. Test with actual file attachment

## Notes for Agents

- **Current State**: This is a fully functional plugin, NOT a boilerplate. All core features are implemented.
- **Language**: Code comments are in English, user-facing content is in German.
- **Boilerplate Origin**: Based on WordPress Plugin Boilerplate (https://wppb.io/)
- **WooCommerce Dependency**: This plugin requires WooCommerce to be installed and active
- **Attachment Storage**: Uses WordPress Media Library - files must be uploaded there
- **Email Deliverability**: Consider using SMTP plugin (WP Mail SMTP, Post SMTP) for production

## References

- WordPress Plugin Handbook: https://developer.wordpress.org/plugins/
- WordPress Coding Standards: https://developer.wordpress.org/coding-standards/
- WooCommerce Hook Reference: https://woocommerce.github.io/code-reference/hooks/hooks.html
- Plugin Boilerplate: https://github.com/DevinVinson/WordPress-Plugin-Boilerplate
- WordPress Media Uploader: https://developer.wordpress.org/reference/functions/wp_enqueue_media/
