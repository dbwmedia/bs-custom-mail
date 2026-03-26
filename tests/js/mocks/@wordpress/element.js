/**
 * Mock for @wordpress/element
 */
const React = require('react');

module.exports = {
	React,
	useState: React.useState,
	useEffect: React.useEffect,
	useCallback: React.useCallback,
	useMemo: React.useMemo,
	useRef: React.useRef,
	createRef: React.createRef,
	forwardRef: React.forwardRef,
	createContext: React.createContext,
	useContext: React.useContext,
	createRoot: (container) => ({
		render: (element) => {
			// Mock render
		},
	}),
	render: () => {},
	hydrate: () => {},
	unmountComponentAtNode: () => {},
	findDOMNode: () => {},
	Children: React.Children,
	Component: React.Component,
	Fragment: React.Fragment,
	Suspense: React.Suspense,
	lazy: React.lazy,
	StrictMode: React.StrictMode,
};
