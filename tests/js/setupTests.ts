/**
 * Jest setup file
 */
import '@testing-library/jest-dom';

declare global {
	interface Window {
		bsCustomMailData: {
			restUrl: string;
			restNonce: string;
			ajaxUrl: string;
			ajaxNonce: string;
		};
	}
}

window.bsCustomMailData = {
	restUrl: 'http://example.com/wp-json/bs-custom-mail/v1',
	restNonce: 'test-nonce',
	ajaxUrl: 'http://example.com/wp-admin/admin-ajax.php',
	ajaxNonce: 'test-ajax-nonce',
};

// Mock console methods for cleaner test output
// eslint-disable-next-line no-console
console.error = jest.fn();
// eslint-disable-next-line no-console
console.warn = jest.fn();
// eslint-disable-next-line no-console
console.log = jest.fn();

// Cleanup after each test
afterEach(() => {
	jest.clearAllMocks();
});
