/**
 * Main Admin App Component
 */
import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { TemplateList, TemplateEditor } from './views';
import { ViewType } from './types';

/**
 * Get initial view from URL parameters
 */
function getInitialViewFromUrl(): { view: ViewType; templateKey?: string } {
	const urlParams = new URLSearchParams( window.location.search );
	const action = urlParams.get( 'action' );
	const templateKey = urlParams.get( 'template' ) || undefined;

	if ( action === 'edit' && templateKey ) {
		return { view: 'edit', templateKey };
	}
	if ( action === 'create' ) {
		return { view: 'create' };
	}
	return { view: 'list' };
}

/**
 * Update URL without reloading the page
 */
function updateUrl( view: ViewType, templateKey?: string ) {
	const url = new URL( window.location.href );

	url.searchParams.delete( 'action' );
	url.searchParams.delete( 'template' );

	if ( view === 'edit' && templateKey ) {
		url.searchParams.set( 'action', 'edit' );
		url.searchParams.set( 'template', templateKey );
	} else if ( view === 'create' ) {
		url.searchParams.set( 'action', 'create' );
	}

	window.history.replaceState( {}, '', url.toString() );
}

export function App() {
	const initialState = getInitialViewFromUrl();
	const [ currentView, setCurrentView ] = useState<ViewType>( initialState.view );
	const [ selectedTemplateKey, setSelectedTemplateKey ] = useState<string | undefined>(
		initialState.templateKey
	);

	const handleNavigate = ( view: ViewType, templateKey?: string ) => {
		setCurrentView( view );
		setSelectedTemplateKey( templateKey );
		updateUrl( view, templateKey );
	};

	const renderContent = () => {
		switch ( currentView ) {
			case 'create':
				return <TemplateEditor mode="create" onNavigate={ handleNavigate } />;
			case 'edit':
				return (
					<TemplateEditor
						mode="edit"
						templateKey={ selectedTemplateKey }
						onNavigate={ handleNavigate }
					/>
				);
			case 'list':
			default:
				return <TemplateList onNavigate={ handleNavigate } />;
		}
	};

	return (
		<div className="wrap bs-custom-mail-admin">
			<h1>{ __( 'Bootsschule Emails', 'bs-custom-mail' ) }</h1>
			{ renderContent() }
		</div>
	);
}
