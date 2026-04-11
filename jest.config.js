/**
 * Jest configuration
 */
module.exports = {
	// Test environment
	testEnvironment: 'jsdom',

	// Setup files
	setupFilesAfterEnv: ['<rootDir>/tests/js/setupTests.ts'],

	// Module file extensions
	moduleFileExtensions: ['ts', 'tsx', 'js', 'jsx', 'json'],

	// Transform
	transform: {
		'^.+\\.(ts|tsx)$': ['ts-jest', {
			tsconfig: {
				jsx: 'react-jsx',
				esModuleInterop: true,
				types: ['jest', '@testing-library/jest-dom'],
			},
		}],
	},

	// Module name mapper for WordPress packages
	moduleNameMapper: {
		'^@wordpress/(.*)$': '<rootDir>/tests/js/mocks/@wordpress/$1.js',
		'\\.(scss|css|less)$': '<rootDir>/tests/js/mocks/styleMock.js',
	},

	// Test match patterns
	testMatch: [
		'<rootDir>/tests/js/**/*.test.ts',
		'<rootDir>/tests/js/**/*.test.tsx',
		'<rootDir>/src/**/*.test.ts',
		'<rootDir>/src/**/*.test.tsx',
	],

	// Coverage
	collectCoverageFrom: [
		'src/**/*.{ts,tsx}',
		'!src/**/*.d.ts',
		'!src/**/index.ts',
	],

	coverageDirectory: 'tests/coverage',
	coverageReporters: ['text', 'lcov', 'html'],

	// Ignore patterns
	testPathIgnorePatterns: [
		'/node_modules/',
		'/build/',
	],

	// Watch plugins
	watchPlugins: [
		'jest-watch-typeahead/filename',
		'jest-watch-typeahead/testname',
	],
};
