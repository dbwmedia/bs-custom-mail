/**
 * Custom hook for template operations
 */
import { useState, useEffect, useCallback } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import { Template } from '../types';

interface UseTemplatesReturn {
	templates: Template[];
	isLoading: boolean;
	createTemplate: ( template: Omit<Template, 'id' | 'created_at' | 'updated_at'> ) => Promise<Template>;
	updateTemplate: ( key: string, data: Partial<Template> ) => Promise<Template>;
	deleteTemplate: ( key: string ) => Promise<void>;
	sendTestEmail: ( key: string, email: string ) => Promise<{ success: boolean; message: string }>;
	error: string | null;
}

export function useTemplates(): UseTemplatesReturn {
	const [ templates, setTemplates ] = useState<Template[]>( [] );
	const [ isLoading, setIsLoading ] = useState( false );
	const [ error, setError ] = useState<string | null>( null );

	const fetchTemplates = useCallback( async () => {
		setIsLoading( true );
		setError( null );
		try {
			const data = await apiFetch<Template[]>( {
				path: '/bs-custom-mail/v1/templates',
			} );
			setTemplates( data );
		} catch ( err ) {
			const errorMessage = err instanceof Error ? err.message : 'Network error';
			setError( errorMessage );
		} finally {
			setIsLoading( false );
		}
	}, [] );

	useEffect( () => {
		fetchTemplates();
	}, [ fetchTemplates ] );

	const createTemplate = async (
		template: Omit<Template, 'id' | 'created_at' | 'updated_at'>
	): Promise<Template> => {
		const response = await apiFetch<Template>( {
			path: '/bs-custom-mail/v1/templates',
			method: 'POST',
			data: template,
		} );
		await fetchTemplates();
		return response;
	};

	const updateTemplate = async ( key: string, data: Partial<Template> ): Promise<Template> => {
		const response = await apiFetch<Template>( {
			path: `/bs-custom-mail/v1/templates/${ encodeURIComponent( key ) }`,
			method: 'PUT',
			data,
		} );
		await fetchTemplates();
		return response;
	};

	const deleteTemplate = async ( key: string ): Promise<void> => {
		await apiFetch( {
			path: `/bs-custom-mail/v1/templates/${ encodeURIComponent( key ) }`,
			method: 'DELETE',
		} );
		await fetchTemplates();
	};

	const sendTestEmail = async (
		key: string,
		email: string
	): Promise<{ success: boolean; message: string }> => {
		return apiFetch<{ success: boolean; message: string }>( {
			path: `/bs-custom-mail/v1/templates/${ encodeURIComponent( key ) }/test`,
			method: 'POST',
			data: { email },
		} );
	};

	return {
		templates,
		isLoading,
		createTemplate,
		updateTemplate,
		deleteTemplate,
		sendTestEmail,
		error,
	};
}
