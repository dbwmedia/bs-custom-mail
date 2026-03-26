/**
 * Custom hook for statistics operations
 */
import { useState } from '@wordpress/element';

interface UseStatsReturn {
	isLoading: boolean;
}

export function useStats(): UseStatsReturn {
	const [ isLoading ] = useState( false );

	return {
		isLoading,
	};
}
