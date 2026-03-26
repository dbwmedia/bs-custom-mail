/**
 * PDF Template List View Component
 */
import { useState, useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import { PDFTemplate } from '../types';
import { useNotices } from '../hooks';
import { PDFTemplateEditor } from './PDFTemplateEditor';

interface PDFTemplateListProps {
	onNavigate: ( view: 'vouchers' | 'pdf-templates' ) => void;
}

export function PDFTemplateList( { onNavigate }: PDFTemplateListProps ) {
	const [ templates, setTemplates ] = useState< PDFTemplate[] >( [] );
	const [ loading, setLoading ] = useState( true );
	const [ editingTemplate, setEditingTemplate ] = useState< PDFTemplate | null >( null );
	const [ isCreating, setIsCreating ] = useState( false );
	const { notices, addNotice, removeNotice } = useNotices();

	useEffect( () => {
		loadTemplates();
	}, [] );

	const loadTemplates = async () => {
		try {
			setLoading( true );
			const response = await apiFetch< PDFTemplate[] >( {
				path: '/bs-custom-mail/v1/pdf-templates',
			} );
			setTemplates( response );
		} catch ( error ) {
			addNotice( 'error', __( 'Fehler beim Laden der PDF-Vorlagen.', 'bs-custom-mail' ) );
		} finally {
			setLoading( false );
		}
	};

	const handleDelete = async ( id: number ) => {
		if ( ! window.confirm( __( 'Möchten Sie diese PDF-Vorlage wirklich löschen?', 'bs-custom-mail' ) ) ) {
			return;
		}

		try {
			await apiFetch( {
				path: `/bs-custom-mail/v1/pdf-templates/${ id }`,
				method: 'DELETE',
			} );
			addNotice( 'success', __( 'PDF-Vorlage gelöscht.', 'bs-custom-mail' ) );
			loadTemplates();
		} catch ( error ) {
			addNotice( 'error', __( 'Fehler beim Löschen.', 'bs-custom-mail' ) );
		}
	};

	const handleSave = async ( template: PDFTemplate ) => {
		try {
			if ( template.id ) {
				await apiFetch( {
					path: `/bs-custom-mail/v1/pdf-templates/${ template.id }`,
					method: 'POST',
					data: template,
				} );
				addNotice( 'success', __( 'PDF-Vorlage aktualisiert.', 'bs-custom-mail' ) );
			} else {
				await apiFetch( {
					path: '/bs-custom-mail/v1/pdf-templates',
					method: 'POST',
					data: template,
				} );
				addNotice( 'success', __( 'PDF-Vorlage erstellt.', 'bs-custom-mail' ) );
			}
			setEditingTemplate( null );
			setIsCreating( false );
			loadTemplates();
		} catch ( error ) {
			addNotice( 'error', __( 'Fehler beim Speichern.', 'bs-custom-mail' ) );
		}
	};

	if ( editingTemplate || isCreating ) {
		return (
			<PDFTemplateEditor
				template={ editingTemplate }
				onSave={ handleSave }
				onCancel={ () => {
					setEditingTemplate( null );
					setIsCreating( false );
				} }
			/>
		);
	}

	return (
		<div className="bs-pdf-template-list">
			<div className="bs-page-header">
				<h2>{ __( 'PDF Vorlagen', 'bs-custom-mail' ) }</h2>
				<div className="bs-page-actions">
					<button
						className="button button-secondary"
						onClick={ () => onNavigate( 'vouchers' ) }
					>
						{ __( '← Zurück zu Gutscheinen', 'bs-custom-mail' ) }
					</button>
					<button
						className="button button-primary"
						onClick={ () => setIsCreating( true ) }
					>
						{ __( '+ Neue PDF-Vorlage', 'bs-custom-mail' ) }
					</button>
				</div>
			</div>

			<div
				style={ {
					background: '#f0f9ff',
					padding: '16px',
					borderRadius: '8px',
					marginBottom: '24px',
					borderLeft: '4px solid #2563eb',
				} }
			>
				<p style={ { margin: 0 } }>
					{ __(
						'PDF-Vorlagen werden für die Generierung von Gutscheinen verwendet. Laden Sie ein PDF hoch und positionieren Sie die Textfelder (Wert, Code, Name, Ablaufdatum) per Drag & Drop.',
						'bs-custom-mail'
					) }
				</p>
			</div>

			{ notices.map( ( notice ) => (
				<div
					key={ notice.id }
					className={ `notice notice-${ notice.status } is-dismissible` }
				>
					<p>{ notice.message }</p>
					<button
						className="notice-dismiss"
						onClick={ () => removeNotice( notice.id ) }
					>
						<span className="screen-reader-text">
							{ __( 'Dismiss this notice.', 'bs-custom-mail' ) }
						</span>
					</button>
				</div>
			) ) }

			{ loading ? (
				<div className="bs-loading">{ __( 'Lade PDF-Vorlagen...', 'bs-custom-mail' ) }</div>
			) : templates.length === 0 ? (
				<div
					style={ {
						textAlign: 'center',
						padding: '60px 20px',
						background: '#f9fafb',
						borderRadius: '12px',
					} }
				>
					<div style={ { fontSize: '48px', marginBottom: '16px' } }>📄</div>
					<h3 style={ { margin: '0 0 8px 0' } }>
						{ __( 'Keine PDF-Vorlagen vorhanden', 'bs-custom-mail' ) }
					</h3>
					<p style={ { color: '#666', marginBottom: '24px' } }>
						{ __( 'Erstellen Sie Ihre erste PDF-Vorlage für Gutscheine.', 'bs-custom-mail' ) }
					</p>
					<button
						className="button button-primary button-hero"
						onClick={ () => setIsCreating( true ) }
					>
						{ __( 'PDF-Vorlage erstellen', 'bs-custom-mail' ) }
					</button>
				</div>
			) : (
				<div
					style={ {
						display: 'grid',
						gridTemplateColumns: 'repeat(auto-fill, minmax(320px, 1fr))',
						gap: '20px',
					} }
				>
					{ templates.map( ( template ) => (
						<div
							key={ template.id }
							style={ {
								background: '#fff',
								borderRadius: '12px',
								boxShadow: '0 1px 3px rgba(0,0,0,0.1)',
								padding: '20px',
								border: '1px solid #e5e7eb',
							} }
						>
							<div
								style={ {
									display: 'flex',
									justifyContent: 'space-between',
									alignItems: 'flex-start',
									marginBottom: '12px',
								} }
							>
								<div
									style={ {
										width: '48px',
										height: '48px',
										background: '#fee2e2',
										borderRadius: '8px',
										display: 'flex',
										alignItems: 'center',
										justifyContent: 'center',
										fontSize: '24px',
									} }
								>
									📄
								</div>
								<span
									className="status-badge"
									style={ {
										background: template.is_active ? '#dcfce7' : '#f3f4f6',
										color: template.is_active ? '#166534' : '#6b7280',
									} }
								>
									{ template.is_active
										? __( 'Aktiv', 'bs-custom-mail' )
										: __( 'Inaktiv', 'bs-custom-mail' ) }
								</span>
							</div>

							<h3 style={ { margin: '0 0 8px 0', fontSize: '18px' } }>
								{ template.template_name }
							</h3>
							<p
								style={ {
									margin: '0 0 16px 0',
									color: '#6b7280',
									fontSize: '14px',
								} }
							>
								{ template.template_key }
							</p>

							{ template.attachment_url && (
								<div
									style={ {
										marginBottom: '16px',
										padding: '8px',
										background: '#f9fafb',
										borderRadius: '6px',
										fontSize: '12px',
									} }
								>
									<a
										href={ template.attachment_url }
										target="_blank"
										rel="noopener noreferrer"
									>
										{ __( 'PDF anzeigen', 'bs-custom-mail' ) }
									</a>
								</div>
							) }

							<div style={ { display: 'flex', gap: '8px' } }>
								<button
									className="button button-small"
									onClick={ () => setEditingTemplate( template ) }
								>
									{ __( 'Bearbeiten', 'bs-custom-mail' ) }
								</button>
								<button
									className="button button-small"
									style={ { color: '#dc2626' } }
									onClick={ () => handleDelete( template.id! ) }
								>
									{ __( 'Löschen', 'bs-custom-mail' ) }
								</button>
							</div>
						</div>
					) ) }
				</div>
			) }
		</div>
	);
}
