/**
 * Custom hook for managing notices
 */
import { useState, useCallback } from '@wordpress/element';
import { Notice } from '../types';

interface UseNoticesReturn {
	notices: Notice[];
	addNotice: ( status: Notice['status'], message: string ) => void;
	removeNotice: ( id: string ) => void;
	success: ( message: string ) => void;
	error: ( message: string ) => void;
	warning: ( message: string ) => void;
	info: ( message: string ) => void;
}

export function useNotices(): UseNoticesReturn {
	const [notices, setNotices] = useState<Notice[]>( [] );

	const addNotice = useCallback( ( status: Notice['status'], message: string ) => {
		const id = Date.now().toString() + Math.random().toString( 36 ).substr( 2, 9 );
		setNotices( ( prev ) => [ ...prev, { id, status, message } ] );

		// Auto-remove success notices after 5 seconds
		if ( status === 'success' ) {
			setTimeout( () => {
				setNotices( ( prev ) => prev.filter( ( n ) => n.id !== id ) );
			}, 5000 );
		}
	}, [] );

	const removeNotice = useCallback( ( id: string ) => {
		setNotices( ( prev ) => prev.filter( ( n ) => n.id !== id ) );
	}, [] );

	return {
		notices,
		addNotice,
		removeNotice,
		success: ( message: string ) => addNotice( 'success', message ),
		error: ( message: string ) => addNotice( 'error', message ),
		warning: ( message: string ) => addNotice( 'warning', message ),
		info: ( message: string ) => addNotice( 'info', message ),
	};
}
