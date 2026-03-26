---
name: wp-testing
description: Ultimate WordPress Testing - Complete guide for PHPUnit, Jest, Integration Tests, and test automation for WordPress plugins.
trigger: /wp-testing or "Test" or "PHPUnit" or "Jest" or "Testing" or "Run tests"
category: wordpress
sub-category: testing
version: "1.0.0"
author: zenclaw
tags:
  - wordpress
  - phpunit
  - jest
  - testing
  - unit-test
  - integration-test
  - coverage
agent-support:
  - opencode
  - claude-code
  - kimi-code
  - pi-dev
references:
  - https://developer.wordpress.org/plugins/wordpress-org/
spec-url: https://agentskills.io/specification
---

# wp-testing: Ultimate WordPress Testing Skill

## Purpose

This skill provides comprehensive guidance for testing WordPress plugins with PHPUnit, Jest, and integration tests.

## Trigger

- **Manual:** `/wp-testing` or "Test" or "PHPUnit" or "Jest"
- **From wordpress super-skill:** After implementation for quality checks

---

## Core Responsibilities

| Responsibility | Description |
|----------------|-------------|
| **PHPUnit Setup** | Configure PHPUnit for WordPress |
| **Unit Tests** | Test PHP classes and functions |
| **Integration Tests** | Test WordPress integration |
| **Jest Setup** | Configure Jest for JavaScript |
| **Component Tests** | React component testing |
| **Code Coverage** | Generate coverage reports |

---

## Step 1: PHPUnit Setup

### A) composer.json

```json
{
    "name": "myvendor/my-plugin",
    "description": "My WordPress Plugin",
    "require-dev": {
        "phpunit/phpunit": "^9.5",
        "yoast/phpunit-polyfills": "^1.0"
    },
    "scripts": {
        "test": "phpunit",
        "test:coverage": "phpunit --coverage-html coverage"
    },
    "autoload": {
        "classmap": ["src/"]
    }
}
```

### B) phpunit.xml

```xml
<?xml version="1.0" encoding="UTF-8"?>
<phpunit xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
         xsi:noNamespaceSchemaLocation="vendor/phpunit/phpunit/phpunit.xsd"
         bootstrap="tests/bootstrap.php"
         colors="true"
         cacheDirectory=".phpunit.cache"
         executionOrder="depends,defects"
         requireCoverageMetadata="false"
         beStrictAboutOutputDuringTests="true"
         failOnRisky="true"
         failOnWarning="true">
    <testsuites>
        <testsuite name="Unit">
            <directory>tests/Unit</directory>
        </testsuite>
        <testsuite name="Integration">
            <directory>tests/Integration</directory>
        </testsuite>
    </testsuites>
    <source>
        <include>
            <directory>src</directory>
        </include>
    </source>
    <extensions>
        <extension class="Yoast\PHPUnitPolyfills\TestListeners\TestListener"/>
    </extensions>
</phpunit>
```

### C) bootstrap.php

```php
<?php
$_tests_dir = getenv( 'WP_TESTS_DIR' );

if ( ! $_tests_dir ) {
    $_tests_dir = '/tmp/wordpress-tests-lib';
}

if ( ! file_exists( $_tests_dir . '/includes/functions.php' ) ) {
    echo "Could not find $_tests_dir\n";
    exit( 1 );
}

require_once $_tests_dir . '/includes/functions.php';

function _manually_load_plugin() {
    define( 'WP_PLUGIN_DIR', dirname( __DIR__ ) . '/wp-content/plugins' );
    $plugin = dirname( __DIR__ ) . '/my-plugin/my-plugin.php';
    
    if ( ! file_exists( $plugin ) ) {
        echo "Plugin file not found: $plugin\n";
        exit( 1 );
    }
    
    activate_plugin( 'my-plugin/my-plugin.php' );
}
tests_add_filter( 'muplugins_loaded', '_manually_load_plugin' );

require $_tests_dir . '/includes/bootstrap.php';
```

---

## Step 2: Unit Tests

### A) Basic Test Case

```php
<?php
namespace MyVendor\MyPlugin\Tests\Unit;

use Yoast\PHPUnitPolyfills\TestCases\TestCase;
use MyVendor\MyPlugin\Utils\Helper;

class Helper_Test extends TestCase {
    
    public function test_sanitize_input() {
        $input = '<script>alert("xss")</script>Hello World';
        $result = Helper::sanitize_input( $input );
        
        $this->assertStringNotContainsString( '<script>', $result );
        $this->assertStringContainsString( 'Hello World', $result );
    }
    
    public function test_format_date() {
        $result = Helper::format_date( '2024-01-15' );
        
        $this->assertIsString( $result );
        $this->assertNotEmpty( $result );
    }
    
    public function test_format_date_with_invalid_input() {
        $result = Helper::format_date( 'invalid-date' );
        
        $this->assertFalse( $result );
    }
    
    public function test_get_default_options() {
        $defaults = Helper::get_default_options();
        
        $this->assertIsArray( $defaults );
        $this->assertArrayHasKey( 'version', $defaults );
        $this->assertArrayHasKey( 'enabled', $defaults );
    }
    
    public function test_is_valid_email() {
        $this->assertTrue( Helper::is_valid_email( 'test@example.com' ) );
        $this->assertFalse( Helper::is_valid_email( 'invalid-email' ) );
    }
}
```

### B) Testing Plugin Classes

```php
<?php
namespace MyVendor\MyPlugin\Tests\Unit;

use Yoast\PHPUnitPolyfills\TestCases\TestCase;
use MyVendor\MyPlugin\Blocks\Block_Registry;

class Block_Registry_Test extends TestCase {
    
    protected Block_Registry $registry;
    
    protected function set_up() {
        parent::set_up();
        $this->registry = new Block_Registry();
    }
    
    public function test_register_block() {
        $result = $this->registry->register( 'test-block', array(
            'name' => 'my-plugin/test-block',
            'title' => 'Test Block'
        ) );
        
        $this->assertTrue( $result );
    }
    
    public function test_get_registered_blocks() {
        $this->registry->register( 'test-1', array( 'title' => 'Test 1' ) );
        $this->registry->register( 'test-2', array( 'title' => 'Test 2' ) );
        
        $blocks = $this->registry->get_all();
        
        $this->assertCount( 2, $blocks );
    }
    
    public function test_unregister_block() {
        $this->registry->register( 'test-block', array( 'title' => 'Test' ) );
        $result = $this->registry->unregister( 'test-block' );
        
        $this->assertTrue( $result );
        $this->assertFalse( $this->registry->get( 'test-block' ) );
    }
    
    public function test_get_nonexistent_block() {
        $block = $this->registry->get( 'nonexistent' );
        
        $this->assertNull( $block );
    }
}
```

---

## Step 3: Integration Tests

### A) CPT Integration Test

```php
<?php
namespace MyVendor\MyPlugin\Tests\Integration;

use WP_UnitTest_Factory;
use Yoast\PHPUnitPolyfills\TestCases\WP_TestCase;

class CPT_Test extends WP_TestCase {
    
    protected static $factory;
    protected static $post_ids;
    
    public static function set_up_before_class() {
        parent::set_up_before_class();
        
        self::$factory = new WP_UnitTest_Factory();
    }
    
    public static function wpSetUpBeforeClass( $factory ) {
        self::$factory = $factory;
        
        // Create test CPT
        register_post_type( 'book', array( 'public' => true ) );
        
        // Create test posts
        self::$post_ids = $factory->post->create_many( 3, array(
            'post_type' => 'book',
            'post_status' => 'publish'
        ) );
    }
    
    public static function wpTearDownAfterClass() {
        foreach ( self::$post_ids as $post_id ) {
            wp_delete_post( $post_id, true );
        }
        
        parent::wpTearDownAfterClass();
    }
    
    public function test_cpt_registered() {
        $this->assertTrue( post_type_exists( 'book' ) );
    }
    
    public function test_create_book() {
        $book_id = self::$factory->post->create( array(
            'post_type' => 'book',
            'post_title' => 'Test Book'
        ) );
        
        $this->assertIsInt( $book_id );
        $this->assertEquals( 'publish', get_post_status( $book_id ) );
    }
    
    public function test_book_meta() {
        $book_id = self::$post_ids[0];
        
        update_post_meta( $book_id, '_book_price', '19.99' );
        
        $price = get_post_meta( $book_id, '_book_price', true );
        
        $this->assertEquals( '19.99', $price );
    }
    
    public function test_book_has_correct_type() {
        $post = get_post( self::$post_ids[0] );
        
        $this->assertEquals( 'book', $post->post_type );
    }
}
```

### B) REST API Integration Test

```php
<?php
namespace MyVendor\MyPlugin\Tests\Integration;

use Yoast\PHPUnitPolyfills\TestCases\WP_TestCase;
use WP_REST_Request;

class REST_API_Test extends WP_TestCase {
    
    protected $server;
    protected $endpoint = '/wp/v2/posts';
    
    public static function set_up_before_class() {
        parent::set_up_before_class();
        global $wp_rest_server;
        $wp_rest_server = new \WP_REST_Server();
        do_action( 'rest_api_init' );
    }
    
    public function test_get_posts() {
        $request = new WP_REST_Request( 'GET', $this->endpoint );
        $response = rest_get_server()->dispatch( $request );
        
        $this->assertEquals( 200, $response->get_status() );
    }
    
    public function test_create_post() {
        wp_set_current_user( 1 );
        
        $request = new WP_REST_Request( 'POST', $this->endpoint );
        $request->set_body_params( array(
            'title'   => 'Test Post',
            'content' => 'Test content',
            'status'  => 'draft'
        ) );
        
        $response = rest_get_server()->dispatch( $request );
        
        $this->assertEquals( 201, $response->get_status() );
        
        $data = $response->get_data();
        $this->assertEquals( 'Test Post', $data['title']['raw'] );
    }
    
    public function test_unauthorized_cannot_create() {
        wp_set_current_user( 0 );
        
        $request = new WP_REST_Request( 'POST', $this->endpoint );
        $request->set_body_params( array(
            'title' => 'Unauthorized Post'
        ) );
        
        $response = rest_get_server()->dispatch( $request );
        
        $this->assertEquals( 401, $response->get_status() );
    }
}
```

---

## Step 4: Jest Setup

### A) package.json

```json
{
  "name": "my-plugin-blocks",
  "scripts": {
    "test": "wp-scripts test",
    "test:watch": "wp-scripts test --watch",
    "test:coverage": "wp-scripts test --coverage",
    "test:debug": "wp-scripts test --inspect-brk"
  },
  "devDependencies": {
    "@wordpress/scripts": "^27.0.0",
    "@testing-library/react": "^14.0.0",
    "@testing-library/jest-dom": "^6.1.0",
    "jest": "^29.7.0"
  },
  "jest": {
    "setupFilesAfterEnv": [ "<rootDir>/tests/jest-setup.js" ],
    "testMatch": [
      "**/tests/**/*.test.{js,jsx,ts,tsx}"
    ],
    "transform": {
      "^.+\\.(ts|tsx)$": "babel-jest"
    },
    "moduleNameMapper": {
      "\\.(css|scss|sass)$": "identity-obj-proxy"
    }
  }
}
```

### B) jest-setup.js

```javascript
import '@testing-library/jest-dom';

jest.mock( '@wordpress/data', () => ( {
    select: jest.fn(),
    dispatch: jest.fn(),
    useSelect: jest.fn( ( callback ) => callback( jest.fn() ) ),
    useDispatch: jest.fn( () => ( { dispatch: jest.fn() } ) )
} ) );

jest.mock( '@wordpress/blocks', () => ( {
    registerBlockType: jest.fn(),
    serialize: jest.fn(),
    parse: jest.fn()
} ) );

global.wp = {
    apiFetch: jest.fn(),
    data: {
        select: jest.fn(),
        dispatch: jest.fn()
    },
    i18n: {
        __: jest.fn( ( text ) => text ),
        _n: jest.fn( ( single, plural, n ) => n === 1 ? single : plural )
    }
};
```

---

## Step 5: Jest Tests

### A) Component Test

```tsx
import { render, screen, fireEvent } from '@testing-library/react';
import MyBlockEdit from '../edit';

jest.mock( '@wordpress/block-editor', () => ( {
    ...jest.requireActual( '@wordpress/block-editor' ),
    useBlockProps: jest.fn( () => ( { className: 'test-block' } ) ),
    InspectorControls: ({ children }) => <div>{ children }</div>,
    BlockControls: ({ children }) => <div>{ children }</div>
} ) );

jest.mock( '@wordpress/components', () => ( {
    ...jest.requireActual( '@wordpress/components' ),
    TextControl: ({ label, value, onChange }) => (
        <input 
            type="text" 
            aria-label={ label } 
            value={ value }
            onChange={ ( e ) => onChange( e.target.value ) }
        />
    ),
    PanelBody: ({ title, children }) => <div><h3>{ title }</h3>{ children }</div>
} ) );

describe( 'MyBlockEdit', () => {
    const defaultProps = {
        attributes: {
            content: 'Test content',
            alignment: 'left'
        },
        setAttributes: jest.fn(),
        isSelected: false
    };
    
    beforeEach( () => {
        jest.clearAllMocks();
    } );
    
    test( 'renders with content', () => {
        render( <MyBlockEdit { ...defaultProps } /> );
        
        expect( screen.getByDisplayValue( 'Test content' ) ).toBeInTheDocument();
    } );
    
    test( 'calls setAttributes on content change', () => {
        render( <MyBlockEdit { ...defaultProps } /> );
        
        const input = screen.getByDisplayValue( 'Test content' );
        fireEvent.change( input, { target: { value: 'New content' } } );
        
        expect( defaultProps.setAttributes ).toHaveBeenCalledWith( {
            content: 'New content'
        } );
    } );
    
    test( 'shows placeholder when content is empty', () => {
        const props = {
            ...defaultProps,
            attributes: { ...defaultProps.attributes, content: '' }
        };
        
        render( <MyBlockEdit { ...props } /> );
        
        expect( screen.getByPlaceholderText( /enter your content/i ) ).toBeInTheDocument();
    } );
} );
```

### B) Utility Function Test

```typescript
import { formatBlockAttributes, validateBlockData } from '../utils';

describe( 'formatBlockAttributes', () => {
    test( 'formats attributes correctly', () => {
        const input = { title: '  Test  ', count: '5' };
        const result = formatBlockAttributes( input );
        
        expect( result.title ).toBe( 'Test' );
        expect( result.count ).toBe( 5 );
    } );
    
    test( 'handles empty input', () => {
        const result = formatBlockAttributes( {} );
        
        expect( result ).toEqual( {} );
    } );
} );

describe( 'validateBlockData', () => {
    test( 'returns true for valid data', () => {
        const data = {
            title: 'Valid Title',
            content: 'Some content here'
        };
        
        expect( validateBlockData( data ) ).toBe( true );
    } );
    
    test( 'returns false for missing title', () => {
        const data = {
            content: 'Some content'
        };
        
        expect( validateBlockData( data ) ).toBe( false );
    } );
    
    test( 'returns false for invalid title', () => {
        const data = {
            title: '', // Empty title
            content: 'Content'
        };
        
        expect( validateBlockData( data ) ).toBe( false );
    } );
} );
```

---

## Step 6: Code Coverage

### PHPUnit Coverage

```bash
# Generate HTML coverage report
./vendor/bin/phpunit --coverage-html coverage

# Generate Clover XML for CI
./vendor/bin/phpunit --coverage-clover coverage/clover.xml

# Minimum coverage thresholds
./vendor/bin/phpunit --coverage-minimum=80
```

### Jest Coverage

```json
// package.json
{
  "jest": {
    "collectCoverageFrom": [
      "src/**/*.{js,jsx,ts,tsx}",
      "!src/**/*.test.{js,jsx,ts,tsx}",
      "!src/index.{js,jsx}"
    ],
    "coverageThreshold": {
      "global": {
        "branches": 80,
        "functions": 80,
        "lines": 80,
        "statements": 80
      }
    }
  }
}
```

---

## Commands

| Command | Description |
|---------|-------------|
| `/wp-testing` | Start testing workflow |
| `/wp-testing phpunit` | Run PHPUnit tests |
| `/wp-testing jest` | Run Jest tests |
| `/wp-testing coverage` | Generate coverage report |
| `/wp-testing integration` | Run integration tests |
| `/wp-testing unit` | Run unit tests |

---

## References

### Official Documentation

- [PHPUnit Documentation](https://phpunit.de/documentation.html)
- [Jest Documentation](https://jestjs.io/docs/getting-started)
- [WordPress Testing Handbook](https://developer.wordpress.org/plugins/wordpress-org/)

### Best Practices

1. **Test both success and failure cases**
2. **Use descriptive test names**
3. **Mock external dependencies**
4. **Aim for high coverage on critical paths**
5. **Run tests in CI/CD pipeline**
6. **Use test databases for integration tests**

---

## Version

**Version:** 1.0.0  
**Last Updated:** 2026-03-24