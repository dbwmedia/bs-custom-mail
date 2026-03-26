/**
 * Mock for @wordpress/icons
 */
const React = require('react');

const createIcon = (name) => {
	const Icon = ({ size = 24, ...props }) =>
		React.createElement('svg', {
			width: size,
			height: size,
			viewBox: '0 0 24 24',
			className: `dashicon dashicons-${name}`,
			...props
		}, React.createElement('title', null, name));
	Icon.displayName = name;
	return Icon;
};

exports.edit = createIcon('edit');
exports.trash = createIcon('trash');
exports.plus = createIcon('plus');
exports.arrowLeft = createIcon('arrow-left');
exports.send = createIcon('send');
exports.check = createIcon('check');
exports.close = createIcon('close');
exports.email = createIcon('email');
exports.info = createIcon('info');
exports.warning = createIcon('warning');
exports.help = createIcon('help');
exports.more = createIcon('more');
exports.settings = createIcon('settings');
exports.chartLine = createIcon('chart-line');
exports.media = createIcon('media');
exports.upload = createIcon('upload');
exports.download = createIcon('download');
exports.external = createIcon('external');
exports.link = createIcon('link');
exports.linkOff = createIcon('link-off');
exports.plusCircle = createIcon('plus-circle');
exports.trashRemove = createIcon('trash-remove');
