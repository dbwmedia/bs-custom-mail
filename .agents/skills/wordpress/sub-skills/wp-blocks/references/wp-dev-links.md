# WordPress Developer References - Block Development

## Official Documentation

### Block Editor
- [Block Editor Handbook](https://developer.wordpress.org/block-editor/) - Complete guide
- [Block API Reference](https://developer.wordpress.org/block-editor/reference-guides/block-api/) - Block registration
- [Components Reference](https://developer.wordpress.org/block-editor/reference-guides/components/) - UI components
- [Data Module](https://developer.wordpress.org/block-editor/reference-guides/data/) - State management
- [Block Editor Tutorial](https://developer.wordpress.org/block-editor/tutorials/) - Step-by-step guides

### Gutenberg Packages
- [@wordpress/blocks](https://www.npmjs.com/package/@wordpress/blocks) - Block registration
- [@wordpress/block-editor](https://www.npmjs.com/package/@wordpress/block-editor) - Editor components
- [@wordpress/components](https://www.npmjs.com/package/@wordpress/components) - UI components library
- [@wordpress/element](https://www.npmjs.com/package/@wordpress/element) - React abstraction
- [@wordpress/i18n](https://www.npmjs.com/package/@wordpress/i18n) - Internationalization
- [@wordpress/icons](https://www.npmjs.com/package/@wordpress/icons) - Icon library

### Tutorials & Examples
- [Gutenberg Examples](https://github.com/WordPress/gutenberg-examples) - Official examples
- [Block Development Tutorial](https://developer.wordpress.org/block-editor/tutorials/create-block/) - Create first block
- [Dynamic Blocks Tutorial](https://developer.wordpress.org/block-editor/tutorials/block-tutorial/creating-dynamic-blocks/) - Server-side rendering

## Key Concepts

### Block.json Structure
```json
{
  "$schema": "https://schemas.wp.org/trunk/block.json",
  "apiVersion": 3,
  "name": "namespace/block-name",
  "version": "1.0.0",
  "title": "Block Title",
  "category": "text|design|media|widgets|theme|embed",
  "icon": "dashicon-name",
  "description": "Description",
  "keywords": ["keyword1", "keyword2"],
  "supports": {
    "align": true,
    "alignWide": true,
    "anchor": true,
    "customClassName": true,
    "html": false,
    "multiple": true,
    "reusable": true
  },
  "attributes": {},
  "editorScript": "file:./build/index.js",
  "editorStyle": "file:./build/index.css",
  "style": "file:./build/style-index.css"
}
```

### Block Attributes Types
| Type | Description |
|------|-------------|
| string | Text value |
| integer | Number |
| number | Float |
| boolean | True/false |
| object | Nested values |
| array | List of values |

### Block Supports
```json
{
  "align": ["wide", "full"],
  "alignWide": true,
  "anchor": true,
  "customClassName": true,
  "html": false,
  "multiple": false,
  "reusable": true,
  "color": {
    "background": true,
    "text": true,
    "gradients": true,
    "link": true
  },
  "spacing": {
    "margin": ["top", "bottom"],
    "padding": true
  },
  "typography": {
    "fontSize": true,
    "lineHeight": true,
    "fontWeight": true,
    "textTransform": true,
    "letterSpacing": true
  }
}
```

## Common Patterns

### UseBlockProps
```typescript
// Edit component
const blockProps = useBlockProps( { className: 'custom-class' } );
<div { ...blockProps }>...</div>

// Save component
const blockProps = useBlockProps.save( { className: 'custom-class' } );
<div { ...blockProps }>...</div>

// InnerBlocks
const innerBlocksProps = useInnerBlocksProps.save( {}, { allowedBlocks: [...] } );
<div { ...innerBlocksProps }>...</div>
```

### Inspector Controls
```typescript
<InspectorControls>
    <PanelBody title={ __( 'Settings', 'text-domain' ) }>
        <TextControl />
        <ToggleControl />
        <SelectControl />
    </PanelBody>
</InspectorControls>
```

### Block Transforms
```typescript
transforms: {
    from: [
        {
            type: 'block',
            blocks: [ 'core/paragraph' ],
            transform: ( attributes ) => createBlock( 'my-plugin/block', { content: attributes.content } )
        }
    ]
}
```

## Build Configuration

### @wordpress/scripts
```json
{
  "scripts": {
    "start": "wp-scripts start",
    "build": "wp-scripts build",
    "lint:js": "wp-scripts lint-js",
    "format": "wp-scripts format"
  }
}
```

### Vite Configuration
```typescript
import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';

export default defineConfig( {
    plugins: [ react() ],
    build: {
        outDir: 'build',
        minify: true
    },
    resolve: {
        alias: {
            '@': '/src'
        }
    }
} );
```

## Testing

### Jest Configuration
```json
{
  "testEnvironment": "jsdom",
  "moduleNameMapper": {
    "\\.css$": "identity-obj-proxy"
  }
}
```

### PHPUnit for Dynamic Blocks
```php
class Block_Render_Test extends WP_UnitTestCase {
    public function test_render_callback() {
        $attributes = [ 'content' => 'Test' ];
        $output = render_my_block( $attributes, '' );
        $this->assertContains( 'Test', $output );
    }
}
```

## Security

### Block Security Checklist
- [ ] Validate all attributes on server
- [ ] Sanitize content in save component
- [ ] Escape output in PHP render callback
- [ ] Use wp_add_inline_script for data
- [ ] Implement proper nonces for dynamic content

### Escaping Functions
```php
esc_html()        // HTML content
esc_attr()        // HTML attributes
esc_url()         // URLs
esc_js()          // JavaScript strings
wp_kses_post()    // Allow HTML in post content
wp_kses()         // Custom allowed tags
```

---

## Version

**Version:** 1.0.0  
**Last Updated:** 2026-03-24