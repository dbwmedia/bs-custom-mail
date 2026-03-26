/**
 * Custom hook for template operations
 */
import { useState, useEffect, useCallback } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import { Template } from '../types';

interface UseTemplatesReturn {
	templates: Template[];
	isLoading: boolean;
	error: string | null;
	refetch: () => void;
	createTemplate: ( template: Omit<Template, 'id' | 'created_at' | 'updated_at'> ) => Promise<Template>;
	updateTemplate: ( key: string, data: Partial<Template> ) => Promise<Template>;
	deleteTemplate: ( key: string ) => Promise<void>;
	getTemplate: ( key: string ) => Promise<Template>;
	sendTestEmail: ( key: string, email: string ) => Promise<{ success: boolean; message: string }>;
}

export function useTemplates(): UseTemplatesReturn {
	const [templates, setTemplates] = useState<Template[]>( [] );
	const [isLoading, setIsLoading] = useState( false );
	const [error, setError] = useState<string | null>( null );

	const fetchTemplates = useCallback( async () => {
		setIsLoading( true );
		setError( null );
		try {
			const data = await apiFetch<Template[]>( {
				path: '/bs-custom-mail/v1/templates',
			} );
			setTemplates( data );
		} catch ( err ) {
			setError( err instanceof Error ? err.message : 'Failed to fetch templates' );
		} finally {
			setIsLoading( false );
		}
	}, [] );

	useEffect( () => {
		fetchTemplates();
	}, [fetchTemplates] );

	const createTemplate = async (
		template: Omit<Template, 'id' | 'created_at' | 'updated_at'>
	): Promise<Template> => {
		const response = await apiFetch<Template>( {
			path: '/bs-custom-mail/v1/templates',
			method: 'POST',
			data: {
				...template,
				attachments: Array.isArray( template.attachments )
					? template.attachments.map( ( a ) => a.id )
					: [],
			},
		} );
		await fetchTemplates();
		return response;
	};

	const updateTemplate = async (
		key: string,
		data: Partial<Template>
	): Promise<Template> => {
		const updateData = { ...data };
		if ( Array.isArray( data.attachments ) ) {
			(updateData as any).attachments = data.attachments.map( ( a: any ) =>
				typeof a === 'object' ? a.id : a
			);
		}

		const response = await apiFetch<Template>( {
			path: `/bs-custom-mail/v1/templates/${ key }`,
			method: 'PUT',
			data: updateData,
		} );
		await fetchTemplates();
		return response;
	};

	const deleteTemplate = async ( key: string ): Promise<void> => {
		await apiFetch( {
			path: `/bs-custom-mail/v1/templates/${ key }`,
			method: 'DELETE',
		} );
		await fetchTemplates();
	};

	const getTemplate = async ( key: string ): Promise<Template> => {
		return apiFetch<Template>( {
			path: `/bs-custom-mail/v1/templates/${ key }`,
		} );
	};

	const sendTestEmail = async (
		key: string,
		email: string
	): Promise<{ success: boolean; message: string }> => {
		return apiFetch<{ success: boolean; message: string }>( {
			path: `/bs-custom-mail/v1/templates/${ key }/test`,
			method: 'POST',
			data: { email },
		} );
	};

	return {
		templates,
		isLoading,
		error,
		refetch: fetchTemplates,
		createTemplate,
		updateTemplate,
		deleteTemplate,
		getTemplate,
		sendTestEmail,
	};
}
