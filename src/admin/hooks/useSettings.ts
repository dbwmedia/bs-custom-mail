/**
 * Custom hook for settings operations
 */
import { useState, useEffect, useCallback } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import { Settings } from '../types';

interface UseSettingsReturn {
	settings: Settings | null;
	isLoading: boolean;
	error: string | null;
	updateSettings: ( data: Partial<Settings> ) => Promise<Settings>;
}

export function useSettings(): UseSettingsReturn {
	const [settings, setSettings] = useState<Settings | null>( null );
	const [isLoading, setIsLoading] = useState( false );
	const [error, setError] = useState<string | null>( null );

	const fetchSettings = useCallback( async () => {
		setIsLoading( true );
		setError( null );
		try {
			const data = await apiFetch<Settings>( {
				path: '/bs-custom-mail/v1/settings',
			} );
			setSettings( data );
		} catch ( err ) {
			setError( err instanceof Error ? err.message : 'Failed to fetch settings' );
		} finally {
			setIsLoading( false );
		}
	}, [] );

	useEffect( () => {
		fetchSettings();
	}, [fetchSettings] );

	const updateSettings = async ( data: Partial<Settings> ): Promise<Settings> => {
		const response = await apiFetch<Settings>( {
			path: '/bs-custom-mail/v1/settings',
			method: 'PUT',
			data,
		} );
		setSettings( response );
		return response;
	};

	return {
		settings,
		isLoading,
		error,
		updateSettings,
	};
}
