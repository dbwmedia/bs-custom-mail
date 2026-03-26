/**
 * Mock for @wordpress/components
 */
const React = require('react');

// Button
exports.Button = ({ children, onClick, variant, ...props }) =>
	React.createElement('button', { onClick, 'data-variant': variant, ...props }, children);

// Card
exports.Card = ({ children, className, ...props }) =>
	React.createElement('div', { className: `components-card ${className || ''}`, ...props }, children);

exports.CardHeader = ({ children, ...props }) =>
	React.createElement('div', { className: 'components-card__header', ...props }, children);

exports.CardBody = ({ children, ...props }) =>
	React.createElement('div', { className: 'components-card__body', ...props }, children);

exports.CardFooter = ({ children, ...props }) =>
	React.createElement('div', { className: 'components-card__footer', ...props }, children);

// Form controls
exports.TextControl = ({ label, value, onChange, ...props }) =>
	React.createElement('div', { className: 'components-text-control' },
		label && React.createElement('label', null, label),
		React.createElement('input', {
			type: 'text',
			value: value || '',
			onChange: (e) => onChange?.(e.target.value),
			...props
		})
	);

exports.TextareaControl = ({ label, value, onChange, rows = 4, ...props }) =>
	React.createElement('div', { className: 'components-textarea-control' },
		label && React.createElement('label', null, label),
		React.createElement('textarea', {
			value: value || '',
			onChange: (e) => onChange?.(e.target.value),
			rows,
			...props
		})
	);

exports.SelectControl = ({ label, value, options = [], onChange, ...props }) =>
	React.createElement('div', { className: 'components-select-control' },
		label && React.createElement('label', null, label),
		React.createElement('select', {
			value,
			onChange: (e) => onChange?.(e.target.value),
			...props
		}, options.map((opt) =>
			React.createElement('option', { key: opt.value, value: opt.value }, opt.label)
		))
	);

exports.ToggleControl = ({ label, checked, onChange }) =>
	React.createElement('div', { className: 'components-toggle-control' },
		React.createElement('label', null,
			React.createElement('input', {
				type: 'checkbox',
				checked,
				onChange: (e) => onChange?.(e.target.checked)
			}),
			label
		)
	);

// Notice
exports.Notice = ({ children, status, onDismiss, ...props }) =>
	React.createElement('div', { className: `components-notice is-${status}`, ...props },
		children,
		onDismiss && React.createElement('button', { onClick: onDismiss }, 'Dismiss')
	);

// Spinner
exports.Spinner = () => React.createElement('div', { className: 'components-spinner' }, 'Loading...');

// ProgressBar
exports.ProgressBar = ({ value, ...props }) =>
	React.createElement('div', { className: 'components-progress-bar', ...props },
		React.createElement('div', { className: 'components-progress-bar__progress', style: { width: `${value}%` } })
	);

// TabPanel
exports.TabPanel = ({ tabs, children, initialTabName, onSelect }) => {
	const [activeTab, setActiveTab] = React.useState(initialTabName || tabs?.[0]?.name);
	
	return React.createElement('div', { className: 'components-tab-panel' },
		React.createElement('div', { className: 'components-tab-panel__tabs' },
			tabs?.map((tab) =>
				React.createElement('button', {
					key: tab.name,
					className: activeTab === tab.name ? 'active-tab' : '',
					onClick: () => {
						setActiveTab(tab.name);
						onSelect?.(tab.name);
					}
				}, tab.title)
			)
		),
		React.createElement('div', { className: 'components-tab-panel__tab-content' },
			typeof children === 'function' ? children(tabs?.find((t) => t.name === activeTab)) : children
		)
	);
};

// Modal
exports.Modal = ({ title, onRequestClose, children, ...props }) =>
	React.createElement('div', { className: 'components-modal', ...props },
		React.createElement('div', { className: 'components-modal__content' },
			title && React.createElement('div', { className: 'components-modal__header' }, title),
			children
		),
		React.createElement('button', { onClick: onRequestClose }, 'Close')
	);

// Tooltip
exports.Tooltip = ({ text, children }) =>
	React.createElement('span', { className: 'components-tooltip', title: text }, children);

// Experimental components
exports.__experimentalTextareaControl = exports.TextareaControl;
exports.__experimentalText = ({ children }) => React.createElement('span', null, children);
