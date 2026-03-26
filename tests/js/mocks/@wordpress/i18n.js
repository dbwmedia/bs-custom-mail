/**
 * Mock for @wordpress/i18n
 */

function __(text, domain) {
	return text;
}

function _x(text, context, domain) {
	return text;
}

function _n(single, plural, number, domain) {
	return number === 1 ? single : plural;
}

function _nx(single, plural, number, context, domain) {
	return number === 1 ? single : plural;
}

function sprintf(format, ...args) {
	return format.replace(/%s/g, () => args.shift() ?? '');
}

function setLocaleData(data, domain) {
	// Mock implementation
}

function getLocaleData(domain) {
	return {};
}

module.exports = {
	__,
	_x,
	_n,
	_nx,
	sprintf,
	setLocaleData,
	getLocaleData,
};
