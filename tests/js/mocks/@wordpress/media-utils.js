/**
 * Mock for @wordpress/media-utils
 */
const React = require('react');

exports.MediaUpload = ({ onSelect, render }) => {
	const open = () => {
		onSelect?.([
			{
				id: 1,
				url: 'http://example.com/test.pdf',
				filename: 'test.pdf',
				title: 'Test PDF',
				filesizeHumanReadable: '245 KB',
				icon: 'http://example.com/pdf-icon.png',
			},
		]);
	};

	return render?.({ open }) || null;
};

exports.MediaUploadCheck = ({ children }) => React.createElement(React.Fragment, null, children);
