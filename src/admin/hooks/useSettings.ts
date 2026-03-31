/**
 * Custom hook for settings operations
 */
import { useState, useEffect, useCallback } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import { Settings } from '../types';

export interface UseSettingsReturn {
	settings: Settings | null;
	isLoading: boolean;
	updateSettings: ( data: Partial<Settings> ) => Promise<void>;
	error: string | null;
}

export function useSettings(): UseSettingsReturn {
	const [ settings, setSettings ] = useState<Settings | null>( null );
	const [ isLoading, setIsLoading ] = useState( false );
	const [ error, setError ] = useState<string | null>( null );

	const fetchSettings = useCallback( async () => {
		setIsLoading( true );
		setError( null );
		try {
			const data = await apiFetch<Settings>( {
				path: '/bs-custom-mail/v1/settings',
			} );
			setSettings( data );
		} catch ( err ) {
			const errorMessage = err instanceof Error ? err.message : 'Failed to load';
			setError( errorMessage );
		} finally {
			setIsLoading( false );
		}
	}, [] );

	useEffect( () => {
		fetchSettings();
	}, [ fetchSettings ] );

	const updateSettings = async ( data: Partial<Settings> ): Promise<void> => {
		setIsLoading( true );
		setError( null );
		try {
			const response = await apiFetch<Settings>( {
				path: '/bs-custom-mail/v1/settings',
				method: 'PUT',
				data,
			} );
			setSettings( response );
		} catch ( err ) {
			const errorMessage = err instanceof Error ? err.message : 'Failed to update';
			setError( errorMessage );
			throw err;
		} finally {
			setIsLoading( false );
		}
	};

	return {
		settings,
		isLoading,
		updateSettings,
		error,
	};
}
