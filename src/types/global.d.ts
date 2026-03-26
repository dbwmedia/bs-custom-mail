/**
 * Global type declarations
 */

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

export {};
