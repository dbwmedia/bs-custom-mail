/**
 * Jest setup file
 */
import '@testing-library/jest-dom';

// Mock WordPress globals
(global as any).window.bsCustomMailData = {
	restUrl: 'http://example.com/wp-json/bs-custom-mail/v1',
	restNonce: 'test-nonce',
	ajaxUrl: 'http://example.com/wp-admin/admin-ajax.php',
	ajaxNonce: 'test-ajax-nonce',
};

// Mock console methods for cleaner test output
global.console = {
	...console,
	// Suppress console.error and console.warn in tests unless explicitly needed
	error: jest.fn(),
	warn: jest.fn(),
	log: jest.fn(),
};

// Cleanup after each test
afterEach(() => {
	jest.clearAllMocks();
});
