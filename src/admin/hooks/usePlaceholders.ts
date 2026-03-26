/**
 * Custom hook for placeholders
 */
import { useState, useEffect, useCallback } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';

export interface Placeholder {
	code: string;
	label: string;
	description: string;
}

interface UsePlaceholdersReturn {
	placeholders: Placeholder[];
	isLoading: boolean;
}

export function usePlaceholders(): UsePlaceholdersReturn {
	const [ placeholders, setPlaceholders ] = useState<Placeholder[]>( [] );
	const [ isLoading, setIsLoading ] = useState( false );

	const fetchPlaceholders = useCallback( async () => {
		setIsLoading( true );
		try {
			const data = await apiFetch<Placeholder[]>( {
				path: '/bs-custom-mail/v1/placeholders',
			} );
			setPlaceholders( data );
		} finally {
			setIsLoading( false );
		}
	}, [] );

	useEffect( () => {
		fetchPlaceholders();
	}, [ fetchPlaceholders ] );

	return {
		placeholders,
		isLoading,
	};
}
