---
name: wp-wpcli
description: Ultimate WP-CLI Development - Complete guide for creating custom WP-CLI commands for WordPress plugins.
trigger: /wp-wpcli or "WP-CLI" or "Command" or "CLI"
category: wordpress
sub-category: cli-development
version: "1.0.0"
author: zenclaw
tags:
  - wordpress
  - wp-cli
  - cli
  - command
  - bulk-operation
agent-support:
  - opencode
  - claude-code
  - kimi-code
  - pi-dev
references:
  - https://developer.wordpress.org/plugins/wordpress-org/
spec-url: https://agentskills.io/specification
---

# wp-wpcli: Ultimate WP-CLI Skill

## Purpose

This skill provides comprehensive guidance for creating custom WP-CLI commands for WordPress plugins.

## Trigger

- **Manual:** `/wp-wpcli` or "WP-CLI" or "CLI Command"
- **From wordpress super-skill:** When user requests CLI functionality

---

## Core Responsibilities

| Responsibility | Description |
|----------------|-------------|
| **Command Structure** | Create WP-CLI commands |
| **Subcommands** | Create nested commands |
| **Arguments** | Define command arguments |
| **Flags** | Add command flags |
| **Bulk Operations** | Process multiple items |
| **Progress Bars** | Show progress indicators |

---

## Step 1: Basic Command Structure

### A) Command Class

```php
<?php
namespace MyVendor\MyPlugin\CLI;

use WP_CLI;
use WP_CLI_Command;

if ( ! class_exists( 'WP_CLI' ) ) {
    return;
}

class Example_Command extends WP_CLI_Command {
    
    /**
     * Subcommand: my-plugin example
     *
     * ## DESCRIPTION
     * This is an example WP-CLI command.
     *
     * ## OPTIONS
     *
     * [--format=<format>]
     * : Output format (table, json, csv).
     * ---
     * default: table
     * options:
     *   - table
     *   - json
     *   - csv
     *
     * ## EXAMPLES
     *
     *     wp my-plugin example
     *     wp my-plugin example --format=json
     *
     * @param array $args       Positional arguments.
     * @param array $assoc_args Associative arguments.
     */
    public function __invoke( $args, $assoc_args ) {
        $format = \WP_CLI\Utils\get_flag_value( $assoc_args, 'format', 'table' );
        
        $data = array(
            array( 'id' => 1, 'name' => 'Item 1' ),
            array( 'id' => 2, 'name' => 'Item 2' ),
            array( 'id' => 3, 'name' => 'Item 3' )
        );
        
        \WP_CLI\Utils\format_items( $format, $data, array( 'id', 'name' ) );
    }
}

WP_CLI::add_command( 'my-plugin example', __NAMESPACE__ . '\Example_Command' );
```

### B) Registration

```php
<?php
// In main plugin file
function register_cli_commands() {
    if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
        return;
    }
    
    require_once plugin_dir_path( __FILE__ ) . '/cli/class-example-command.php';
}
add_action( 'init', 'register_cli_commands' );
```

---

## Step 2: CRUD Commands

### A) List Command

```php
<?php
class List_Command extends WP_CLI_Command {
    
    public function __invoke( $args, $assoc_args ) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'my_plugin_items';
        
        $items = $wpdb->get_results( "SELECT * FROM {$table}", ARRAY_A );
        
        if ( empty( $items ) ) {
            WP_CLI::success( 'No items found.' );
            return;
        }
        
        $formatter = $this->formatter( $assoc_args );
        $formatter->display_items( $items );
    }
    
    protected function formatter( $assoc_args ) {
        return new \WP_CLI\Formatter( $assoc_args, $this->obj_fields );
    }
    
    protected $obj_fields = array( 'id', 'title', 'status', 'created' );
}
```

### B) Create Command

```php
<?php
class Create_Command extends WP_CLI_Command {
    
    /**
     * Create a new item.
     *
     * ## OPTIONS
     *
     * <title>
     * : The title of the item.
     *
     * [--status=<status>]
     * : The status of the item.
     * ---
     * default: draft
     * options:
     *   - draft
     *   - publish
     *
     * [--content=<content>]
     * : The content description.
     *
     * ## EXAMPLES
     *
     *     wp my-plugin create "My Title" --status=publish
     *     wp my-plugin create "Title" --content="Description"
     *
     * @param array $args       Positional arguments.
     * @param array $assoc_args Associative arguments.
     */
    public function __invoke( $args, $assoc_args ) {
        $title = $args[0];
        $status = isset( $assoc_args['status'] ) ? $assoc_args['status'] : 'draft';
        $content = isset( $assoc_args['content'] ) ? $assoc_args['content'] : '';
        
        global $wpdb;
        
        $result = $wpdb->insert(
            $wpdb->prefix . 'my_plugin_items',
            array(
                'title'     => sanitize_text_field( $title ),
                'content'   => sanitize_textarea_field( $content ),
                'status'    => sanitize_key( $status ),
                'created'   => current_time( 'mysql' ),
                'updated'   => current_time( 'mysql' )
            ),
            array( '%s', '%s', '%s', '%s', '%s' )
        );
        
        if ( $result ) {
            $id = $wpdb->insert_id;
            WP_CLI::success( "Created item {$id}." );
        } else {
            WP_CLI::error( 'Failed to create item.' );
        }
    }
}
```

### C) Update Command

```php
<?php
class Update_Command extends WP_CLI_Command {
    
    /**
     * Update an existing item.
     *
     * ## OPTIONS
     *
     * <id>
     * : The item ID.
     *
     * [--title=<title>]
     * : New title.
     *
     * [--status=<status>]
     * : New status.
     *
     * ## EXAMPLES
     *
     *     wp my-plugin update 1 --title="New Title"
     *     wp my-plugin update 1 --status=publish
     *
     * @param array $args       Positional arguments.
     * @param array $assoc_args Associative arguments.
     */
    public function __invoke( $args, $assoc_args ) {
        $id = absint( $args[0] );
        
        global $wpdb;
        
        // Check if exists
        $item = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}my_plugin_items WHERE id = %d",
            $id
        ) );
        
        if ( ! $item ) {
            WP_CLI::error( "Item {$id} not found." );
            return;
        }
        
        $update_data = array();
        $format = array();
        
        if ( isset( $assoc_args['title'] ) ) {
            $update_data['title'] = sanitize_text_field( $assoc_args['title'] );
            $format[] = '%s';
        }
        
        if ( isset( $assoc_args['status'] ) ) {
            $update_data['status'] = sanitize_key( $assoc_args['status'] );
            $format[] = '%s';
        }
        
        if ( empty( $update_data ) ) {
            WP_CLI::warning( 'No fields to update.' );
            return;
        }
        
        $update_data['updated'] = current_time( 'mysql' );
        $format[] = '%s';
        
        $result = $wpdb->update(
            $wpdb->prefix . 'my_plugin_items',
            $update_data,
            array( 'id' => $id ),
            $format,
            array( '%d' )
        );
        
        if ( $result !== false ) {
            WP_CLI::success( "Updated item {$id}." );
        } else {
            WP_CLI::error( 'Failed to update item.' );
        }
    }
}
```

### D) Delete Command

```php
<?php
class Delete_Command extends WP_CLI_Command {
    
    /**
     * Delete an item.
     *
     * ## OPTIONS
     *
     * <id>
     * : The item ID.
     *
     * [--force]
     * : Force deletion (skip trash).
     *
     * ## EXAMPLES
     *
     *     wp my-plugin delete 1
     *     wp my-plugin delete 1 --force
     *
     * @param array $args       Positional arguments.
     * @param array $assoc_args Associative arguments.
     */
    public function __invoke( $args, $assoc_args ) {
        $id = absint( $args[0] );
        
        global $wpdb;
        
        $result = $wpdb->delete(
            $wpdb->prefix . 'my_plugin_items',
            array( 'id' => $id ),
            array( '%d' )
        );
        
        if ( $result ) {
            WP_CLI::success( "Deleted item {$id}." );
        } else {
            WP_CLI::error( "Failed to delete item {$id}." );
        }
    }
}
```

---

## Step 3: Bulk Operations

### A) Bulk Import

```php
<?php
class Import_Command extends WP_CLI_Command {
    
    /**
     * Import items from a CSV file.
     *
     * ## OPTIONS
     *
     * <file>
     * : Path to CSV file.
     *
     * ## EXAMPLES
     *
     *     wp my-plugin import /path/to/items.csv
     *
     * @param array $args Positional arguments.
     */
    public function __invoke( $args, $assoc_args ) {
        $file = $args[0];
        
        if ( ! file_exists( $file ) ) {
            WP_CLI::error( "File not found: {$file}" );
            return;
        }
        
        $rows = array_map( 'str_getcsv', file( $file ) );
        $header = array_shift( $rows );
        
        $count = 0;
        $total = count( $rows );
        
        $progress = \WP_CLI\Utils\make_progress_bar( 'Importing items', $total );
        
        global $wpdb;
        
        foreach ( $rows as $row ) {
            $data = array_combine( $header, $row );
            
            $result = $wpdb->insert(
                $wpdb->prefix . 'my_plugin_items',
                array(
                    'title'   => sanitize_text_field( $data['title'] ),
                    'content' => sanitize_textarea_field( $data['content'] ),
                    'status'  => isset( $data['status'] ) ? sanitize_key( $data['status'] ) : 'draft',
                    'created' => current_time( 'mysql' ),
                    'updated' => current_time( 'mysql' )
                ),
                array( '%s', '%s', '%s', '%s', '%s' )
            );
            
            if ( $result ) {
                $count++;
            }
            
            $progress->tick();
        }
        
        $progress->finish();
        
        WP_CLI::success( "Imported {$count} of {$total} items." );
    }
}
```

### B) Bulk Delete

```php
<?php
class Clean_Command extends WP_CLI_Command {
    
    /**
     * Delete items by status.
     *
     * ## OPTIONS
     *
     * <status>
     * : Status to delete (draft, trash, all).
     *
     * ## EXAMPLES
     *
     *     wp my-plugin clean draft
     *     wp my-plugin clean all
     *
     * @param array $args Positional arguments.
     */
    public function __invoke( $args, $assoc_args ) {
        $status = $args[0];
        
        global $wpdb;
        
        if ( $status === 'all' ) {
            $count = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}my_plugin_items" );
            $wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}my_plugin_items" );
        } else {
            $count = $wpdb->get_var( $wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}my_plugin_items WHERE status = %s",
                $status
            ) );
            $wpdb->delete(
                $wpdb->prefix . 'my_plugin_items',
                array( 'status' => $status ),
                array( '%s' )
            );
        }
        
        WP_CLI::success( "Deleted {$count} items." );
    }
}
```

---

## Step 4: Progress and Feedback

### A) Progress Bar

```php
// Long-running operation with progress
$total = 100;
$progress = \WP_CLI\Utils\make_progress_bar( 'Processing items', $total );

for ( $i = 0; $i < $total; $i++ ) {
    // Do work
    $progress->tick();
}

$progress->finish();
```

### B) Messages

```php
WP_CLI::success( 'Operation completed successfully.' );
WP_CLI::error( 'Operation failed.' );
WP_CLI::warning( 'Warning message.' );
WP_CLI::line( 'Information message.' );
WP_CLI::debug( 'Debug message.', 'group' );
WP_CLI::log( 'Log message.' );

// With data
WP_CLI::success( "Processed {$count} items." );
WP_CLI::error( "Failed to process item {$id}." );
```

### C) Tables

```php
$items = array(
    array( 'id' => 1, 'name' => 'Item 1', 'status' => 'active' ),
    array( 'id' => 2, 'name' => 'Item 2', 'status' => 'inactive' )
);

$formatter = new \WP_CLI\Formatter( $assoc_args, array( 'id', 'name', 'status' ) );
$formatter->display_items( $items );
```

---

## Step 5: Command Registration

### A) Main Plugin File

```php
<?php
function register_my_plugin_commands() {
    if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
        return;
    }
    
    // Single command
    require_once plugin_dir_path( __FILE__ ) . 'cli/class-example-command.php';
    
    // Command with subcommands
    require_once plugin_dir_path( __FILE__ ) . 'cli/class-list-command.php';
    require_once plugin_dir_path( __FILE__ ) . 'cli/class-create-command.php';
    require_once plugin_dir_path( __FILE__ ) . 'cli/class-update-command.php';
    require_once plugin_dir_path( __FILE__ ) . 'cli/class-delete-command.php';
    require_once plugin_dir_path( __FILE__ ) . 'cli/class-import-command.php';
}
add_action( 'init', 'register_my_plugin_commands' );
```

---

## Step 6: WP-CLI in bin Directory

### A) Standalone Command

```php
#!/usr/bin/env php
<?php
/**
 * Standalone WP-CLI command script.
 */

// Define ABSPATH if not defined
if ( ! defined( 'ABSPATH' ) ) {
    define( 'ABSPATH', dirname( __DIR__ ) . '/wp/' );
}

// Load WordPress
require_once ABSPATH . 'wp-load.php';

// Run command
WP_CLI::add_command( 'standalone-command', function( $args, $assoc_args ) {
    WP_CLI::success( 'This is a standalone command.' );
} );
```

---

## Commands

| Command | Description |
|---------|-------------|
| `/wp-wpcli` | Start WP-CLI workflow |
| `/wp-wpcli create` | Create new command |
| `/wp-wpcli bulk` | Create bulk operation |
| `/wp-wpcli import` | Import data |
| `/wp-wpcli export` | Export data |

---

## References

### Official Documentation

- [WP-CLI Handbook](https://developer.wordpress.org/plugins/wordpress-org/)
- [WP-CLI Commands](https://developer.wordpress.org/plugins/wordpress-org/)

### Best Practices

1. **Use namespacing** to avoid conflicts
2. **Document all options** in docblocks
3. **Provide examples** for every command
4. **Use progress bars** for long operations
5. **Handle errors gracefully** with WP_CLI::error()

---

## Version

**Version:** 1.0.0  
**Last Updated:** 2026-03-24