/**
 * Custom hook for default email templates
 */
import { useState, useEffect, useCallback } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';

export interface DefaultTemplate {
	filename: string;
	name: string;
	subject: string;
	content: string;
}

interface UseDefaultTemplatesReturn {
	templates: DefaultTemplate[];
	isLoading: boolean;
	getTemplateByName: ( name: string ) => DefaultTemplate | undefined;
}

export function useDefaultTemplates(): UseDefaultTemplatesReturn {
	const [ templates, setTemplates ] = useState<DefaultTemplate[]>( [] );
	const [ isLoading, setIsLoading ] = useState( false );

	const fetchTemplates = useCallback( async () => {
		setIsLoading( true );
		try {
			const data = await apiFetch<DefaultTemplate[]>( {
				path: '/bs-custom-mail/v1/default-templates',
			} );
			setTemplates( data );
		} finally {
			setIsLoading( false );
		}
	}, [] );

	useEffect( () => {
		fetchTemplates();
	}, [ fetchTemplates ] );

	const getTemplateByName = useCallback( ( name: string ) => {
		return templates.find( 
			( t ) => t.name.toLowerCase().includes( name.toLowerCase() ) || 
					 t.filename.toLowerCase().includes( name.toLowerCase() )
		);
	}, [ templates ] );

	return {
		templates,
		isLoading,
		getTemplateByName,
	};
}
