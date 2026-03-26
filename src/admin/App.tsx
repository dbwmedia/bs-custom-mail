/**
 * Main Admin App Component
 */
import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { TemplateList, TemplateEditor, VoucherList, PDFTemplateList } from './views';
import { ViewType } from './types';

/**
 * Get initial view from URL parameters
 */
function getInitialViewFromUrl(): { view: ViewType; templateKey?: string } {
	const urlParams = new URLSearchParams( window.location.search );
	const page = urlParams.get( 'page' );
	const action = urlParams.get( 'action' );
	const templateKey = urlParams.get( 'template' ) || undefined;

	if ( page === 'bs-custom-mail-vouchers' ) {
		return { view: 'vouchers' };
	}

	if ( page === 'bs-custom-mail-pdf-templates' ) {
		return { view: 'pdf-templates' };
	}

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
		if ( templateKey ) {
			setSelectedTemplateKey( templateKey );
		}
		updateUrl( view, templateKey );
	};

	const handleTabNavigate = ( view: ViewType ) => {
		setCurrentView( view );
		updateUrl( view );
	};

	const isTemplateView = currentView === 'list' || currentView === 'create' || currentView === 'edit';
	const isVoucherView = currentView === 'vouchers' || currentView === 'pdf-templates';

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
			case 'vouchers':
				return <VoucherList onNavigate={ handleNavigate } />;
			case 'pdf-templates':
				return <PDFTemplateList onNavigate={ handleNavigate } />;
			case 'list':
			default:
				return <TemplateList onNavigate={ handleNavigate } />;
		}
	};

	return (
		<div className="wrap bs-custom-mail-admin">
			<div
				style={ {
					display: 'flex',
					justifyContent: 'space-between',
					alignItems: 'center',
					marginBottom: '8px',
				} }
			>
				<h1 style={ { margin: 0 } }>{ __( 'Bootsschule Mail & Gutscheine', 'bs-custom-mail' ) }</h1>
			</div>

			{ /* Navigation Tabs */ }
			<div
				style={ {
					display: 'flex',
					gap: '8px',
					marginBottom: '24px',
					borderBottom: '1px solid #e5e7eb',
					paddingBottom: '0',
				} }
			>
				<button
					style={ {
						padding: '12px 20px',
						background: 'transparent',
						border: 'none',
						borderBottom: isTemplateView ? '2px solid #000' : '2px solid transparent',
						fontWeight: isTemplateView ? 600 : 400,
						color: isTemplateView ? '#000' : '#6b7280',
						cursor: 'pointer',
						display: 'flex',
						alignItems: 'center',
						gap: '6px',
						fontSize: '14px',
					} }
					onClick={ () => handleTabNavigate( 'list' ) }
				>
					<span>📧</span>
					{ __( 'Templates', 'bs-custom-mail' ) }
				</button>
				<button
					style={ {
						padding: '12px 20px',
						background: 'transparent',
						border: 'none',
						borderBottom: isVoucherView ? '2px solid #000' : '2px solid transparent',
						fontWeight: isVoucherView ? 600 : 400,
						color: isVoucherView ? '#000' : '#6b7280',
						cursor: 'pointer',
						display: 'flex',
						alignItems: 'center',
						gap: '6px',
						fontSize: '14px',
					} }
					onClick={ () => handleTabNavigate( 'vouchers' ) }
				>
					<span>🎁</span>
					{ __( 'Gutscheine', 'bs-custom-mail' ) }
				</button>
			</div>

			{ renderContent() }
		</div>
	);
}
