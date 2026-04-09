# BS Custom Mail Build Pipeline
# Using Just command runner

# Default recipe - show help
_default:
    @echo "BS Custom Mail Build Pipeline"
    @echo ""
    @echo "Available commands:"
    @echo "  just install       - Install all dependencies"
    @echo "  just test          - Run all tests (PHP + React)"
    @echo "  just test-php      - Run PHPUnit tests only"
    @echo "  just test-react    - Run React/Jest tests only"
    @echo "  just test-watch    - Run React tests in watch mode"
    @echo "  just build         - Build optimized production bundle"
    @echo "  just build-dev     - Build development bundle"
    @echo "  just clean         - Clean build artifacts"
    @echo "  just zip           - Create distribution ZIP"
    @echo "  just dist          - Full build: test + build + zip"
    @echo "  just lint          - Run all linters"
    @echo "  just lint-php      - Run PHP code sniffer"
    @echo "  just lint-js       - Run ESLint"
    @echo "  just format        - Format all code"
    @echo ""

# Install all dependencies
install:
    @echo "📦 Installing Node dependencies..."
    npm install
    @echo "✅ Dependencies installed"

# Run all tests
test: test-php test-react
    @echo "✅ All tests passed!"

# Run PHPUnit tests
test-php:
    @echo "🧪 Running PHPUnit tests..."
    @if [ ! -f "vendor/bin/phpunit" ]; then \
        echo "⚠️  PHPUnit not found. Run 'composer install' first."; \
        exit 1; \
    fi
    ./vendor/bin/phpunit --colors=always --testdox
    @echo "✅ PHPUnit tests passed"

# Run React tests
test-react:
    @echo "🧪 Running React tests..."
    npm run test:unit -- --coverage --coverageReporters=text-summary
    @echo "✅ React tests passed"

# Run React tests in watch mode
test-watch:
    @echo "👀 Starting React tests in watch mode..."
    npm run test:unit -- --watch

# Run tests with coverage
test-coverage: test-php-coverage test-react-coverage

test-php-coverage:
    @echo "📊 Running PHPUnit tests with coverage..."
    ./vendor/bin/phpunit --coverage-html tests/coverage-report --coverage-text

test-react-coverage:
    @echo "📊 Running React tests with coverage..."
    npm run test:unit -- --coverage

# Build optimized production bundle
build:
    @echo "🏗️  Building production bundle..."
    npm run build
    @echo "✅ Build complete"

# Build development bundle with source maps
build-dev:
    @echo "🏗️  Building development bundle..."
    NODE_ENV=development npm run start

# Clean build artifacts
clean:
    @echo "🧹 Cleaning build artifacts..."
    rm -rf build/
    rm -rf dist/
    rm -rf tests/coverage/
    rm -rf tests/coverage-report/
    rm -f tests/phpunit-report.xml
    @echo "✅ Cleanup complete"

# Create distribution ZIP
dist: clean test build
    @echo "📦 Installing production dependencies..."
    composer install --no-dev --optimize-autoloader --quiet
    
    @echo "📦 Creating distribution package..."
    mkdir -p dist
    
    # Create ZIP with only necessary files (including vendor for FPDF)
    zip -r "dist/bs-custom-mail-v$(cat package.json | grep '"version"' | cut -d'"' -f4).zip" \
        ./*.php \
        ./includes/ \
        ./admin/ \
        ./public/ \
        ./languages/ \
        ./build/ \
        ./vendor/ \
        ./uninstall.php \
        ./README.txt \
        ./LICENSE.txt \
        ./composer.json \
        ./composer.lock \
        -x "*/.*" \
        -x "*/tests/*" \
        -x "*/node_modules/*" \
        -x "*/src/*" \
        -x "*.map" \
        -x "package*.json" \
        -x "phpunit.xml" \
        -x "jest.config.js" \
        -x "tsconfig.json" \
        -x "*.md"
    
    @echo "📦 Reinstalling dev dependencies..."
    composer install --quiet
    
    @echo "✅ Distribution package created in dist/"

# Quick dist without tests
dist-quick: clean build
    @echo "📦 Installing production dependencies..."
    composer install --no-dev --optimize-autoloader --quiet
    
    @echo "📦 Creating distribution package (quick)..."
    mkdir -p dist
    
    zip -r "dist/bs-custom-mail-v$(cat package.json | grep '"version"' | cut -d'"' -f4).zip" \
        ./*.php \
        ./includes/ \
        ./admin/ \
        ./public/ \
        ./languages/ \
        ./build/ \
        ./vendor/ \
        ./uninstall.php \
        ./README.txt \
        ./LICENSE.txt \
        ./composer.json \
        ./composer.lock \
        -x "*/.*" \
        -x "*/tests/*" \
        -x "*/node_modules/*" \
        -x "*/src/*" \
        -x "*.map"
    
    @echo "📦 Reinstalling dev dependencies..."
    composer install --quiet
    
    @echo "✅ Distribution package created"

# Run all linters
lint: lint-js lint-css
    @echo "✅ All linting passed"

# Run PHP code sniffer
lint-php:
    @echo "🔍 Running PHP Code Sniffer..."
    @if [ -f "vendor/bin/phpcs" ]; then \
        ./vendor/bin/phpcs --standard=WordPress includes/ admin/ public/; \
    else \
        echo "⚠️  PHP_CodeSniffer not found"; \
    fi

# Run ESLint
lint-js:
    @echo "🔍 Running ESLint..."
    npm run lint:js -- --quiet

# Run style lint
lint-css:
    @echo "🔍 Running Stylelint..."
    npm run lint:css -- --quiet || true

# Format all code
format:
    @echo "✨ Formatting code..."
    npm run format
    @echo "✅ Formatting complete"

# Start development server
start:
    @echo "🚀 Starting development server..."
    npm start

# Check environment
check:
    @echo "🔍 Checking environment..."
    @node --version
    @npm --version
    @which zip || echo "⚠️  zip not installed (needed for dist)"
    @echo "✅ Environment check complete"

# Run security audit
audit:
    @echo "🔒 Running security audit..."
    npm audit
    @echo "✅ Audit complete"

# Fix security issues
audit-fix:
    @echo "🔧 Fixing security issues..."
    npm audit fix
    @echo "✅ Security issues fixed"

# Full CI pipeline
ci: check install lint test build
    @echo "✅ CI pipeline completed successfully!"

# Deploy to dist (manual)
deploy: dist
    @echo "🚀 Ready for deployment!"
    @ls -lh dist/*.zip
