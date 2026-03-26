---
name: wp-database
description: Ultimate WordPress Database Development - Complete guide for Custom Post Types, Taxonomies, Meta fields, Custom Tables, and database operations.
trigger: /wp-database or "CPT" or "Taxonomy" or "Custom Post Type" or "Meta Field" or "Custom Table"
category: wordpress
sub-category: database-development
version: "1.0.0"
author: zenclaw
tags:
  - wordpress
  - cpt
  - custom-post-type
  - taxonomy
  - meta
  - custom-table
  - wp-query
  - database
agent-support:
  - opencode
  - claude-code
  - kimi-code
  - pi-dev
references:
  - https://developer.wordpress.org/rest-api/reference/
  - https://developer.wordpress.org/rest-api/reference/
spec-url: https://agentskills.io/specification
---

# wp-database: Ultimate WordPress Database Skill

## Purpose

This skill provides comprehensive guidance for WordPress database development including Custom Post Types (CPT), Taxonomies, Meta fields, Custom Tables, and WP_Query.

## Trigger

- **Manual:** `/wp-database` or "CPT" or "Taxonomy" or "Custom Table"
- **From wordpress super-skill:** When user requests database development

---

## Core Responsibilities

| Responsibility | Description |
|----------------|-------------|
| **Custom Post Types** | Create and manage CPTs |
| **Custom Taxonomies** | Create and manage taxonomies |
| **Post Meta** | Add/update/delete post meta |
| **User Meta** | Add/update/delete user meta |
| **Term Meta** | Add/update/delete taxonomy meta |
| **Custom Tables** | Create and manage custom DB tables |
| **WP_Query** | Custom queries and loops |

---

## Step 1: Custom Post Types

### A) Basic CPT Registration

```php
<?php
function register_my_cpt() {
    $labels = array(
        'name'               => __( 'Books', 'my-plugin' ),
        'singular_name'      => __( 'Book', 'my-plugin' ),
        'menu_name'          => __( 'Books', 'my-plugin' ),
        'name_admin_bar'     => __( 'Book', 'my-plugin' ),
        'add_new'            => __( 'Add New', 'my-plugin' ),
        'add_new_item'       => __( 'Add New Book', 'my-plugin' ),
        'edit_item'          => __( 'Edit Book', 'my-plugin' ),
        'new_item'           => __( 'New Book', 'my-plugin' ),
        'view_item'          => __( 'View Book', 'my-plugin' ),
        'search_items'       => __( 'Search Books', 'my-plugin' ),
        'not_found'          => __( 'No books found', 'my-plugin' ),
        'not_found_in_trash' => __( 'No books found in trash', 'my-plugin' )
    );
    
    $args = array(
        'label'               => __( 'Books', 'my-plugin' ),
        'description'         => __( 'Book custom post type', 'my-plugin' ),
        'labels'              => $labels,
        'public'              => true,
        'publicly_queryable'  => true,
        'show_ui'             => true,
        'show_in_menu'        => true,
        'query_var'           => true,
        'rewrite'             => array( 'slug' => 'books', 'with_front' => false ),
        'capability_type'     => 'post',
        'has_archive'         => true,
        'hierarchical'        => false,
        'menu_position'       => null,
        'menu_icon'           => 'dashicons-book',
        'show_in_rest'        => true, // REST API support
        'rest_base'           => 'books',
        'supports'            => array( 'title', 'editor', 'thumbnail', 'excerpt', 'custom-fields' ),
        'taxonomies'          => array( 'genre', 'author' )
    );
    
    register_post_type( 'book', $args );
}
add_action( 'init', 'register_my_cpt' );
```

### B) CPT with Extended Options

```php
function register_extended_cpt() {
    $args = array(
        'label'               => __( 'Products', 'my-plugin' ),
        'labels'              => $product_labels,
        'public'              => true,
        'publicly_queryable'  => true,
        'show_ui'             => true,
        'show_in_nav_menus'   => true,
        'show_in_admin_bar'   => true,
        'show_in_rest'        => true,
        'rest_base'           => 'products',
        'menu_position'       => 5,
        'menu_icon'           => 'dashicons-store',
        'hierarchical'        => false,
        'supports'            => array( 'title', 'editor', 'thumbnail', 'excerpt', 'revisions', 'author' ),
        'taxonomies'          => array( 'product_cat', 'product_tag' ),
        'has_archive'         => true,
        'archive_slug'        => 'products',
        'rewrite'             => array(
            'slug'       => 'products',
            'with_front' => false,
            'feeds'      => true,
            'pages'      => true
        ),
        'query_var'           => 'product',
        'can_export'          => true,
        'delete_with_user'    => false,
        'map_meta_cap'        => true,
        'capabilities' => array(
            'edit_post'          => 'edit_product',
            'read_post'          => 'read_product',
            'delete_post'        => 'delete_product',
            'edit_posts'         => 'edit_products',
            'edit_others_posts'  => 'edit_others_products',
            'publish_posts'      => 'publish_products',
            'read_private_posts' => 'read_private_products'
        )
    );
    
    register_post_type( 'product', $args );
}
```

---

## Step 2: Custom Taxonomies

### A) Hierarchical Taxonomy (Like Categories)

```php
function register_genre_taxonomy() {
    $labels = array(
        'name'              => __( 'Genres', 'my-plugin' ),
        'singular_name'     => __( 'Genre', 'my-plugin' ),
        'search_items'      => __( 'Search Genres', 'my-plugin' ),
        'all_items'         => __( 'All Genres', 'my-plugin' ),
        'parent_item'       => __( 'Parent Genre', 'my-plugin' ),
        'parent_item_colon' => __( 'Parent Genre:', 'my-plugin' ),
        'edit_item'         => __( 'Edit Genre', 'my-plugin' ),
        'update_item'       => __( 'Update Genre', 'my-plugin' ),
        'add_new_item'      => __( 'Add New Genre', 'my-plugin' ),
        'new_item_name'     => __( 'New Genre Name', 'my-plugin' ),
        'menu_name'         => __( 'Genres', 'my-plugin' )
    );
    
    $args = array(
        'labels'            => $labels,
        'description'      => __( 'Book genres', 'my-plugin' ),
        'public'           => true,
        'show_in_nav_menus' => true,
        'show_in_rest'     => true,
        'rest_base'        => 'genres',
        'show_admin_column' => true,
        'hierarchical'     => true,
        'rewrite'          => array( 'slug' => 'genre', 'with_front' => false ),
        'capabilities'     => array(
            'manage_terms' => 'manage_genres',
            'edit_terms'   => 'edit_genres',
            'delete_terms' => 'delete_genres',
            'assign_terms' => 'assign_genres'
        )
    );
    
    register_taxonomy( 'genre', array( 'book' ), $args );
}
add_action( 'init', 'register_genre_taxonomy' );
```

### B) Non-Hierarchical Taxonomy (Like Tags)

```php
function register_tag_taxonomy() {
    $labels = array(
        'name'                       => __( 'Tags', 'my-plugin' ),
        'singular_name'              => __( 'Tag', 'my-plugin' ),
        'search_items'               => __( 'Search Tags', 'my-plugin' ),
        'popular_items'             => __( 'Popular Tags', 'my-plugin' ),
        'all_items'                 => __( 'All Tags', 'my-plugin' ),
        'edit_item'                 => __( 'Edit Tag', 'my-plugin' ),
        'update_item'               => __( 'Update Tag', 'my-plugin' ),
        'add_new_item'              => __( 'Add New Tag', 'my-plugin' ),
        'new_item_name'             => __( 'New Tag Name', 'my-plugin' ),
        'separate_items_with_commas' => __( 'Separate tags with commas', 'my-plugin' ),
        'add_or_remove_items'       => __( 'Add or remove tags', 'my-plugin' ),
        'choose_from_most_used'     => __( 'Choose from most used tags', 'my-plugin' ),
        'menu_name'                 => __( 'Tags', 'my-plugin' )
    );
    
    $args = array(
        'labels'            => $labels,
        'description'       => __( 'Book tags', 'my-plugin' ),
        'public'           => true,
        'show_in_rest'     => true,
        'rest_base'        => 'book_tags',
        'show_admin_column' => false,
        'hierarchical'     => false,
        'rewrite'          => array( 'slug' => 'book-tag' ),
        'update_count_callback' => '_update_post_term_count'
    );
    
    register_taxonomy( 'book_tag', array( 'book' ), $args );
}
```

---

## Step 3: Meta Fields

### A) Post Meta (Add/Update/Delete)

```php
<?php
// Add post meta
function add_book_metadata( $post_id, $post ) {
    // Auto-save check
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
        return $post_id;
    }
    
    // Verify nonce
    if ( ! isset( $_POST['book_meta_nonce'] ) || ! wp_verify_nonce( $_POST['book_meta_nonce'], 'book_meta_save' ) ) {
        return $post_id;
    }
    
    // Check permissions
    if ( ! current_user_can( 'edit_post', $post_id ) ) {
        return $post_id;
    }
    
    // ISBN
    if ( isset( $_POST['book_isbn'] ) ) {
        update_post_meta( $post_id, '_book_isbn', sanitize_text_field( $_POST['book_isbn'] ) );
    }
    
    // Price
    if ( isset( $_POST['book_price'] ) ) {
        update_post_meta( $post_id, '_book_price', floatval( $_POST['book_price'] ) );
    }
    
    // Rating (select)
    if ( isset( $_POST['book_rating'] ) ) {
        update_post_meta( $post_id, '_book_rating', intval( $_POST['book_rating'] ) );
    }
    
    // Featured (checkbox)
    if ( isset( $_POST['book_featured'] ) ) {
        update_post_meta( $post_id, '_book_featured', 'yes' );
    } else {
        delete_post_meta( $post_id, '_book_featured' );
    }
}
add_action( 'save_post', 'add_book_metadata', 10, 2 );

// Get post meta
function get_book_data( $post_id ) {
    $isbn    = get_post_meta( $post_id, '_book_isbn', true );
    $price   = get_post_meta( $post_id, '_book_price', true );
    $rating  = get_post_meta( $post_id, '_book_rating', true );
    $featured = get_post_meta( $post_id, '_book_featured', true );
    
    return array(
        'isbn'      => $isbn,
        'price'     => $price ? floatval( $price ) : 0,
        'rating'    => $rating ? intval( $rating ) : 0,
        'featured'  => $featured === 'yes'
    );
}
```

### B) User Meta

```php
<?php
// Get user meta
function get_user_profile_data( $user_id ) {
    $first_name = get_user_meta( $user_id, 'first_name', true );
    $last_name  = get_user_meta( $user_id, 'last_name', true );
    $bio        = get_user_meta( $user_id, 'user_bio', true );
    $avatar     = get_user_meta( $user_id, 'user_avatar', true );
    
    return array(
        'first_name' => $first_name,
        'last_name'  => $last_name,
        'bio'       => $bio,
        'avatar'    => $avatar
    );
}

// Update user meta
function update_user_profile( $user_id, $data ) {
    if ( isset( $data['first_name'] ) ) {
        update_user_meta( $user_id, 'first_name', sanitize_text_field( $data['first_name'] ) );
    }
    
    if ( isset( $data['last_name'] ) ) {
        update_user_meta( $user_id, 'last_name', sanitize_text_field( $data['last_name'] ) );
    }
    
    if ( isset( $data['bio'] ) ) {
        update_user_meta( $user_id, 'user_bio', sanitize_textarea_field( $data['bio'] ) );
    }
}

// Delete user meta
function clear_user_data( $user_id ) {
    delete_user_meta( $user_id, 'user_avatar' );
    delete_user_meta( $user_id, 'user_bio' );
}
```

### C) Term Meta

```php
<?php
// Add term meta support
function add_term_meta_support() {
    register_meta( 'term', 'term_color', array(
        'type'         => 'string',
        'single'       => true,
        'show_in_rest' => true
    ) );
    
    register_meta( 'term', 'term_order', array(
        'type'         => 'integer',
        'single'       => true,
        'show_in_rest' => true
    ) );
}
add_action( 'init', 'add_term_meta_support' );

// Get term meta
function get_term_color( $term_id ) {
    return get_term_meta( $term_id, 'term_color', true );
}

// Update term meta
function set_term_color( $term_id, $color ) {
    update_term_meta( $term_id, 'term_color', sanitize_hex_color( $color ) );
}
```

---

## Step 4: Custom Tables

### A) Create Custom Table

```php
<?php
function create_custom_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'my_plugin_data';
    $charset_collate = $wpdb->get_charset_collate();
    
    $sql = "CREATE TABLE {$table_name} (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        title varchar(255) NOT NULL DEFAULT '',
        content longtext NOT NULL,
        status varchar(20) NOT NULL DEFAULT 'draft',
        user_id bigint(20) unsigned NOT NULL DEFAULT 0,
        created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        meta json NULL,
        PRIMARY KEY  (id),
        KEY user_id (user_id),
        KEY status (status),
        KEY created_at (created_at)
    ) {$charset_collate};";
    
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta( $sql );
}
register_activation_hook( __FILE__, 'create_custom_table' );
```

### B) CRUD Operations on Custom Table

```php
<?php
class Custom_Table_Controller {
    
    protected $table_name;
    
    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'my_plugin_data';
    }
    
    public function insert( $data ) {
        global $wpdb;
        
        $defaults = array(
            'title'   => '',
            'content' => '',
            'status'  => 'draft',
            'user_id' => get_current_user_id(),
            'meta'    => array()
        );
        
        $data = wp_parse_args( $data, $defaults );
        
        $result = $wpdb->insert(
            $this->table_name,
            array(
                'title'     => sanitize_text_field( $data['title'] ),
                'content'   => wp_kses_post( $data['content'] ),
                'status'    => sanitize_key( $data['status'] ),
                'user_id'   => absint( $data['user_id'] ),
                'meta'      => json_encode( $data['meta'] )
            ),
            array( '%s', '%s', '%s', '%d', '%s' )
        );
        
        return $result ? $wpdb->insert_id : false;
    }
    
    public function get( $id ) {
        global $wpdb;
        
        $row = $wpdb->get_row(
            $wpdb->prepare( "SELECT * FROM {$this->table_name} WHERE id = %d", $id )
        );
        
        if ( $row ) {
            $row->meta = json_decode( $row->meta, true );
        }
        
        return $row;
    }
    
    public function get_all( $args = array() ) {
        global $wpdb;
        
        $defaults = array(
            'status'   => 'publish',
            'per_page' => 10,
            'page'    => 1,
            'orderby' => 'created_at',
            'order'   => 'DESC'
        );
        
        $args = wp_parse_args( $args, $defaults );
        
        $where = '';
        if ( $args['status'] ) {
            $where = $wpdb->prepare( " WHERE status = %s", $args['status'] );
        }
        
        $offset = ( $args['page'] - 1 ) * $args['per_page'];
        
        $sql = "SELECT * FROM {$this->table_name}{$where} 
                ORDER BY {$args['orderby']} {$args['order']} 
                LIMIT %d OFFSET %d";
        
        $rows = $wpdb->get_results( $wpdb->prepare( $sql, $args['per_page'], $offset ) );
        
        foreach ( $rows as $row ) {
            $row->meta = json_decode( $row->meta, true );
        }
        
        return $rows;
    }
    
    public function update( $id, $data ) {
        global $wpdb;
        
        $update_data = array();
        $format = array();
        
        if ( isset( $data['title'] ) ) {
            $update_data['title'] = sanitize_text_field( $data['title'] );
            $format[] = '%s';
        }
        
        if ( isset( $data['content'] ) ) {
            $update_data['content'] = wp_kses_post( $data['content'] );
            $format[] = '%s';
        }
        
        if ( isset( $data['status'] ) ) {
            $update_data['status'] = sanitize_key( $data['status'] );
            $format[] = '%s';
        }
        
        if ( isset( $data['meta'] ) ) {
            $update_data['meta'] = json_encode( $data['meta'] );
            $format[] = '%s';
        }
        
        if ( empty( $update_data ) ) {
            return false;
        }
        
        return $wpdb->update(
            $this->table_name,
            $update_data,
            array( 'id' => $id ),
            $format,
            array( '%d' )
        );
    }
    
    public function delete( $id ) {
        global $wpdb;
        
        return $wpdb->delete(
            $this->table_name,
            array( 'id' => $id ),
            array( '%d' )
        );
    }
}
```

---

## Step 5: WP_Query

### Custom Queries

```php
<?php
function custom_book_query( $args = array() ) {
    $defaults = array(
        'post_type'      => 'book',
        'post_status'    => 'publish',
        'posts_per_page' => 10,
        'paged'         => 1,
        'orderby'       => 'date',
        'order'         => 'DESC'
    );
    
    $args = wp_parse_args( $args, $defaults );
    
    // Meta query for featured books
    if ( isset( $args['featured'] ) && $args['featured'] ) {
        $args['meta_query'] = array(
            array(
                'key'     => '_book_featured',
                'value'   => 'yes',
                'compare' => '='
            )
        );
    }
    
    // Taxonomy query for genre
    if ( isset( $args['genre'] ) ) {
        $args['tax_query'] = array(
            array(
                'taxonomy' => 'genre',
                'field'    => 'slug',
                'terms'    => $args['genre']
            )
        );
    }
    
    // Search
    if ( isset( $args['search'] ) ) {
        $args['s'] = $args['search'];
    }
    
    $query = new WP_Query( $args );
    
    return $query;
}

// Usage
$books = custom_book_query( array(
    'genre'     => 'fiction',
    'featured'  => true,
    'per_page'  => 5,
    'orderby'   => 'title',
    'order'     => 'ASC'
) );

if ( $books->have_posts() ) {
    while ( $books->have_posts() ) {
        $books->the_post();
        the_title();
        the_content();
    }
    wp_reset_postdata();
}
```

### Multiple Meta Queries

```php
$args = array(
    'post_type' => 'product',
    'meta_query' => array(
        'relation' => 'AND',
        array(
            'key'     => 'price',
            'value'   => array( 10, 100 ),
            'type'    => 'NUMERIC',
            'compare' => 'BETWEEN'
        ),
        array(
            'key'     => 'in_stock',
            'value'   => 'yes',
            'compare' => '='
        )
    )
);
```

---

## Step 6: Options API

```php
<?php
// Single option
$value = get_option( 'my_plugin_setting', 'default_value' );
update_option( 'my_plugin_setting', $new_value );
delete_option( 'my_plugin_setting' );

// Array option
$options = get_option( 'my_plugin_options', array() );
$options['key1'] = 'value1';
$options['key2'] = 'value2';
update_option( 'my_plugin_options', $options );

// Transients (caching)
set_transient( 'my_plugin_cache', $data, HOUR_IN_SECONDS );
$cached = get_transient( 'my_plugin_cache' );
delete_transient( 'my_plugin_cache' );
```

---

## Step 7: Database Helper Functions

```php
<?php
// Safe database operations
function safe_database_operations() {
    global $wpdb;
    
    // Prepare statement (prevents SQL injection)
    $result = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT * FROM {$wpdb->posts} WHERE post_type = %s AND post_status = %s",
            'book',
            'publish'
        )
    );
    
    // Insert with placeholders
    $wpdb->insert(
        $wpdb->prefix . 'my_table',
        array(
            'column1' => 'value1',
            'column2' => 'value2'
        ),
        array( '%s', '%s' )
    );
    
    // Update with where clause
    $wpdb->update(
        $wpdb->prefix . 'my_table',
        array( 'column1' => 'new_value' ),
        array( 'id' => 1 ),
        array( '%s' ),
        array( '%d' )
    );
    
    // Delete
    $wpdb->delete(
        $wpdb->prefix . 'my_table',
        array( 'id' => 1 ),
        array( '%d' )
    );
    
    // Get var (single value)
    $count = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts}" );
    
    // Get row (single row)
    $row = $wpdb->get_row( "SELECT * FROM {$wpdb->posts} WHERE ID = 1" );
    
    // Get col (single column)
    $titles = $wpdb->get_col( "SELECT post_title FROM {$wpdb->posts}" );
}
```

---

## Commands

| Command | Description |
|---------|-------------|
| `/wp-database` | Start database workflow |
| `/wp-database cpt [name]` | Create CPT |
| `/wp-database taxonomy` | Create taxonomy |
| `/wp-database meta` | Add meta fields |
| `/wp-database table` | Create custom table |
| `/wp-database query` | Custom WP_Query |

---

## References

### Official Documentation

- [Post Types](https://developer.wordpress.org/rest-api/reference/) - CPT reference
- [Taxonomies](https://developer.wordpress.org/rest-api/reference/) - Taxonomy reference
- [Metadata](https://developer.wordpress.org/plugins/metadata/) - Meta API

### Best Practices

1. **Flush rewrite rules** after CPT/taxonomy registration
2. **Use meta functions** instead of raw queries for post meta
3. **Use $wpdb->prepare** for all dynamic SQL
4. **dbDelta for table creation** - handles upgrades properly
5. **Index custom tables** for frequently queried columns
6. **Use transients** for expensive data caching

---

## Version

**Version:** 1.0.0  
**Last Updated:** 2026-03-24