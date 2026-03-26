/**
 * Custom hook for settings operations
 */
import { useState } from '@wordpress/element';

interface UseSettingsReturn {
	isLoading: boolean;
}

export function useSettings(): UseSettingsReturn {
	const [ isLoading ] = useState( false );

	return {
		isLoading,
	};
}
