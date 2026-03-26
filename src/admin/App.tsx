/**
 * Main Admin App Component
 */
import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { TabPanel } from '@wordpress/components';
import { TemplateList, TemplateEditor, Settings, Stats } from './views';
import { ViewType } from './types';

export function App() {
	const [ currentView, setCurrentView ] = useState<ViewType>( 'list' );
	const [ selectedTemplateKey, setSelectedTemplateKey ] = useState<string | undefined>(
		undefined
	);

	const handleNavigate = ( view: ViewType, templateKey?: string ) => {
		setCurrentView( view );
		setSelectedTemplateKey( templateKey );
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

	const tabs = [
		{
			name: 'templates',
			title: __( 'Templates', 'bs-custom-mail' ),
		},
		{
			name: 'settings',
			title: __( 'Einstellungen', 'bs-custom-mail' ),
		},
		{
			name: 'stats',
			title: __( 'Statistik', 'bs-custom-mail' ),
		},
	];

	// Custom navigation for list/create/edit views
	if ( currentView !== 'settings' && currentView !== 'stats' ) {
		return (
			<div className="wrap bs-custom-mail-admin">
				<h1>{ __( 'Bootsschule Emails', 'bs-custom-mail' ) }</h1>
				{ renderContent() }
			</div>
		);
	}

	return (
		<div className="wrap bs-custom-mail-admin">
			<h1>{ __( 'Bootsschule Emails', 'bs-custom-mail' ) }</h1>

			<TabPanel
				className="bs-admin-tabs"
				activeClass="active-tab"
				tabs={ tabs }
				initialTabName={
					currentView === 'settings'
						? 'settings'
						: currentView === 'stats'
							? 'stats'
							: 'templates'
				}
				onSelect={ ( tabName ) => {
					if ( tabName === 'templates' ) {
						setCurrentView( 'list' );
					} else if ( tabName === 'settings' ) {
						setCurrentView( 'settings' );
					} else if ( tabName === 'stats' ) {
						setCurrentView( 'stats' );
					}
				} }
			>
				{ ( tab ) => {
					if ( tab.name === 'templates' ) {
						return renderContent();
					}
					if ( tab.name === 'settings' ) {
						return <Settings />;
					}
					if ( tab.name === 'stats' ) {
						return <Stats />;
					}
					return null;
				} }
			</TabPanel>
		</div>
	);
}
