---
name: wp-deployment
description: Ultimate WordPress Deployment - Complete guide for WordPress.org deployment, GitHub Actions CI/CD, versioning, and release management.
trigger: /wp-deployment or "Deploy" or "Release" or "WordPress.org" or "Version" or "SVN"
category: wordpress
sub-category: deployment
version: "1.0.0"
author: zenclaw
tags:
  - wordpress
  - deployment
  - svn
  - github-actions
  - release
  - versioning
  - ci-cd
agent-support:
  - opencode
  - claude-code
  - kimi-code
  - pi-dev
references:
  - https://developer.wordpress.org/plugins/wordpress-org/
spec-url: https://agentskills.io/specification
---

# wp-deployment: Ultimate WordPress Deployment Skill

## Purpose

This skill provides comprehensive guidance for deploying WordPress plugins to WordPress.org, GitHub Actions CI/CD, and release management.

## Trigger

- **Manual:** `/wp-deployment` or "Deploy" or "Release" or "WordPress.org"
- **From wordpress super-skill:** After security audit for deployment

---

## Core Responsibilities

| Responsibility | Description |
|----------------|-------------|
| **WordPress.org Deployment** | SVN deployment to wp.org |
| **GitHub Actions** | CI/CD pipeline setup |
| **Version Management** | Semantic versioning |
| **Changelog** | Keep a Changelog format |
| **Plugin Check** | WP.org validation |
| **Asset Management** | Banners and icons |

---

## Step 1: WordPress.org Preparation

### A) readme.txt Format

```txt
=== Plugin Name ===
Contributors: username
Donate link: https://example.com/donate
Tags: tag1, tag2
Requires at least: 6.0
Tested up to: 6.7
Stable tag: 1.0.0
License: GPL v2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Short description of the plugin (no longer than 150 characters).

== Description ==

Detailed description of what the plugin does.

= Features =

* Feature 1
* Feature 2
* Feature 3

== Installation ==

1. Upload the plugin folder to your `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Configure settings as needed

== Frequently Asked Questions ==

= Does this plugin work with WordPress Multisite? =

Yes, it does!

= How do I contribute? =

Visit our GitHub repository.

== Screenshots ==

1. Description of screenshot 1
2. Description of screenshot 2

== Changelog ==

= 1.0.0 =
* Initial release

== Upgrade Notice ==

= 1.0.0 =
Initial release of the plugin.
```

### B) Plugin Header

```php
<?php
/**
 * Plugin Name:       My Plugin Name
 * Plugin URI:        https://example.com/my-plugin
 * Description:       A brief description of what the plugin does.
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Your Name
 * Author URI:        https://example.com
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       my-plugin
 * Domain Path:       /languages
 */
```

---

## Step 2: GitHub Actions CI/CD

### A) Main Workflow

```yaml
name: WordPress Plugin CI/CD

on:
  push:
    branches: [main, develop]
  pull_request:
    branches: [main]
  release:
    types: [published]

jobs:
  lint:
    name: Lint
    runs-on: ubuntu-latest
    
    steps:
      - uses: actions/checkout@v4
      
      - name: Set up PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.0'
          tools: composer, cs2pr
          
      - name: Install dependencies
        run: composer install --no-dev
        
      - name: PHP Code Sniffer
        run: |
          ./vendor/bin/phpcs -p . --standard=WordPress --extensions=php
          
      - name: PHP Syntax Check
        run: find . -name "*.php" -exec php -l {} \;

  test:
    name: Test
    runs-on: ubuntu-latest
    
    services:
      mysql:
        image: mysql:8.0
        env:
          MYSQL_ROOT_PASSWORD: root
          MYSQL_DATABASE: wordpress_test
        ports:
          - 3306:3306
        options: >-
          --health-cmd="mysqladmin ping"
          --health-interval=10s
          --health-timeout=5s
          --health-retries=5
          
    steps:
      - uses: actions/checkout@v4
      
      - name: Set up PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.0'
          
      - name: Install WordPress test env
        run: |
          mkdir -p /tmp/wordpress-tests-lib
          git clone --depth=1 --branch=6.7 https://github.com/WordPress/wordpress-tests-lib.git /tmp/wordpress-tests-lib
          
      - name: Install dependencies
        run: composer install --no-dev
          
      - name: Run PHPUnit
        run: ./vendor/bin/phpunit

  build:
    name: Build
    runs-on: ubuntu-latest
    
    steps:
      - uses: actions/checkout@v4
      
      - name: Set up Node
        uses: actions/setup-node@v4
        with:
          node-version: '18'
          
      - name: Install dependencies
        run: npm ci
        
      - name: Build
        run: npm run build
        
      - name: Upload artifact
        uses: actions/upload-artifact@v4
        with:
          name: build
          path: build/
          retention-days: 7

  deploy:
    name: Deploy to WordPress.org
    needs: [lint, test]
    runs-on: ubuntu-latest
    if: github.event_name == 'release' && github.event.action == 'published'
    
    steps:
      - uses: actions/checkout@v4
        with:
          repository: wordpress-plugin/slug
          path: svn
          sparse-checkout: |
            trunk
            assets
          sparse-checkout-cone-mode: false
          
      - name: Set up Node
        uses: actions/setup-node@v4
        with:
          node-version: '18'
          
      - name: Build
        run: |
          npm ci
          npm run build
          
      - name: Copy build to trunk
        run: |
          rm -rf svn/trunk/*
          cp -r build/* svn/trunk/
          
      - name: Commit and push
        run: |
          cd svn
          git config user.name "GitHub Actions"
          git config user.email "actions@github.com"
          git add .
          git commit -m "Release ${{ github.ref_name }}"
          git push origin trunk
```

---

## Step 3: SVN Deployment

### A) Manual SVN Deployment

```bash
# 1. Check out SVN repository
svn co https://plugins.svn.wordpress.org/my-plugin svn-repo

# 2. Navigate to plugin directory
cd svn-repo

# 3. Create trunk if not exists
mkdir -p trunk
mkdir -p assets

# 4. Copy plugin files to trunk
cp -r /path/to/my-plugin/* trunk/

# 5. Add new files
svn add trunk/* assets/*

# 6. Commit
svn ci -m "Version 1.0.0"

# 7. Tag the release
svn copy trunk tags/1.0.0
svn ci -m "Tag version 1.0.0"
```

### B) Using 10up Action

```yaml
name: Deploy to WordPress.org
on:
  release:
    types: [published]

jobs:
  deploy:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      
      - name: Build
        run: npm ci && npm run build
        
      - name: WordPress Plugin Deploy
        uses: 10up/action-wordpress-plugin-deploy@stable
        env:
          SVN_USERNAME: ${{ secrets.SVN_USERNAME }}
          SVN_PASSWORD: ${{ secrets.SVN_PASSWORD }}
```

---

## Step 4: Version Management

### A) Version Bump Script

```bash
#!/bin/bash
# bump-version.sh

VERSION=$1

if [ -z "$VERSION" ]; then
    echo "Usage: ./bump-version.sh 1.0.1"
    exit 1
fi

# Update composer.json
sed -i "s/\"version\": \".*\"/\"version\": \"$VERSION\"/" composer.json

# Update main plugin file
sed -i "s/Version:           .*/Version:           $VERSION/" my-plugin.php

# Update readme.txt
sed -i "s/^Stable tag:.*/Stable tag: $VERSION/" readme.txt

# Commit
git add -A
git commit -m "Bump version to $VERSION"

# Tag
git tag -a "v$VERSION" -m "Version $VERSION"

echo "Version bumped to $VERSION"
```

### B) Changelog Format

```markdown
# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.1.0] - YYYY-MM-DD

### Added
- New feature description
- Another new feature

### Changed
- Description of change
- Improvement details

### Deprecated
- List of deprecated features

### Removed
- List of removed features

### Fixed
- Bug fix description
- Security fix details

### Security
- Security-related changes

## [1.0.0] - YYYY-MM-DD

### Added
- Initial release
```

---

## Step 5: Plugin Assets

### A) Banner Image

```
assets/banner-772x250.png  - Main banner (772x250)
assets/banner-154x125.png  - Small banner (154x125) - optional
assets/icon-128x128.png    - Icon (128x128)
assets/icon-256x256.png    - Icon (256x256)
```

### B) Screenshot Configuration

```txt
== Screenshots ==

1. This is the first screenshot caption.
2. This is the second screenshot caption.
3. This is the third screenshot caption.
```

---

## Step 6: Plugin Check

### A) Local Validation

```bash
# Install plugin check CLI
npm install -g @wordpress/plugin-check

# Run checks
wp-plugin-check --path=/path/to/plugin

# Specific checks
wp-plugin-check --path=/path/to/plugin --checks=security
wp-plugin-check --path=/path/to/plugin --checks=performance
wp-plugin-check --path=/path/to/plugin --checks=static-analysis
```

### B) Pre-deployment Checklist

| Item | Status |
|------|--------|
| Version updated in PHP file | ☐ |
| Version updated in readme.txt | ☐ |
| Stable tag updated | ☐ |
| Changelog updated | ☐ |
| Assets uploaded (banner, icon) | ☐ |
| PHP syntax check passed | ☐ |
| PHP CodeSniffer passed | ☐ |
| PHPUnit tests passed | ☐ |
| JavaScript build succeeded | ☐ |
| No console errors | ☐ |

---

## Commands

| Command | Description |
|---------|-------------|
| `/wp-deployment` | Start deployment workflow |
| `/wp-deployment svn` | Deploy to WordPress.org |
| `/wp-deployment github` | Setup GitHub Actions |
| `/wp-deployment version` | Bump version |
| `/wp-deployment check` | Run plugin check |
| `/wp-deployment assets` | Upload assets |

---

## References

### Official Documentation

- [Plugin Handbook](https://developer.wordpress.org/plugins/)
- [Deploying to WordPress.org](https://developer.wordpress.org/plugins/wordpress-org/deploy-from-github/)
- [10up Action](https://github.com/10up/action-wordpress-plugin-deploy)

### Best Practices

1. **Tag releases** in Git for every version
2. **Test locally** before deploying
3. **Keep changelog updated** with every change
4. **Use semantic versioning** (MAJOR.MINOR.PATCH)
5. **Submit to WP.org** only after thorough testing

---

## Version

**Version:** 1.0.0  
**Last Updated:** 2026-03-24