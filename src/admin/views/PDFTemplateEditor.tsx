/**
 * PDF Template Editor with Drag & Drop
 */
import { useState, useRef, useCallback } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { PDFTemplate, PDFTemplateConfig } from '../types';
import { useNotices } from '../hooks';

interface PDFTemplateEditorProps {
	template: PDFTemplate | null;
	onSave: ( template: PDFTemplate ) => void;
	onCancel: () => void;
}

const DEFAULT_CONFIG: PDFTemplateConfig = {
	wert: { x: 105, y: 100 },
	code: { x: 105, y: 130 },
	name: { x: 105, y: 160 },
	expiry: { x: 105, y: 190 },
};

export function PDFTemplateEditor( { template, onSave, onCancel }: PDFTemplateEditorProps ) {
	const [ templateName, setTemplateName ] = useState( template?.template_name || '' );
	const [ templateKey, setTemplateKey ] = useState( template?.template_key || '' );
	const [ attachmentId, setAttachmentId ] = useState< number >( template?.attachment_id || 0 );
	const [ attachmentUrl, setAttachmentUrl ] = useState( template?.attachment_url || '' );
	const [ fontSize, setFontSize ] = useState( template?.font_size || 16 );
	const [ config, setConfig ] = useState< PDFTemplateConfig >(
		template?.template_config
			? ( typeof template.template_config === 'string'
				? JSON.parse( template.template_config )
				: template.template_config )
			: DEFAULT_CONFIG
	);
	const [ isDragging, setIsDragging ] = useState< string | null >( null );
	const [ dragOffset, setDragOffset ] = useState( { x: 0, y: 0 } );
	const canvasRef = useRef< HTMLDivElement >( null );
	const { notices, addNotice, removeNotice } = useNotices();

	const fields = [
		{ key: 'wert', label: __( 'Wert', 'bs-custom-mail' ), color: '#22c55e', ...config.wert! },
		{ key: 'code', label: __( 'Code', 'bs-custom-mail' ), color: '#3b82f6', ...config.code! },
		{ key: 'name', label: __( 'Name', 'bs-custom-mail' ), color: '#6b7280', ...config.name! },
		{ key: 'expiry', label: __( 'Ablauf', 'bs-custom-mail' ), color: '#8b5cf6', ...config.expiry! },
	];

	const handleSelectPDF = () => {
		// @ts-ignore - wp.media is global
		const frame = wp.media( {
			title: __( 'PDF Vorlage wählen', 'bs-custom-mail' ),
			button: { text: __( 'Auswählen', 'bs-custom-mail' ) },
			multiple: false,
			library: { type: 'application/pdf' },
		} );

		frame.on( 'select', () => {
			const attachment = frame.state().get( 'selection' ).first().toJSON();
			setAttachmentId( attachment.id );
			setAttachmentUrl( attachment.url );
		} );

		frame.open();
	};

	const handleMouseDown = useCallback(
		( e: React.MouseEvent, fieldKey: string ) => {
			e.preventDefault();
			const rect = canvasRef.current?.getBoundingClientRect();
			if ( ! rect ) return;

			const field = fields.find( ( f ) => f.key === fieldKey );
			if ( ! field ) return;

			setIsDragging( fieldKey );
			setDragOffset( {
				x: e.clientX - rect.left - ( config[ fieldKey as keyof PDFTemplateConfig ]?.x || 0 ) * 2,
				y: e.clientY - rect.top - ( config[ fieldKey as keyof PDFTemplateConfig ]?.y || 0 ) * 2,
			} );
		},
		[ config, fields ]
	);

	const handleMouseMove = useCallback(
		( e: React.MouseEvent ) => {
			if ( ! isDragging || ! canvasRef.current ) return;

			const rect = canvasRef.current.getBoundingClientRect();
			const newX = e.clientX - rect.left - dragOffset.x;
			const newY = e.clientY - rect.top - dragOffset.y;

			const constrainedX = Math.max( 0, Math.min( 420, newX ) );
			const constrainedY = Math.max( 0, Math.min( 594, newY ) );

			setConfig( ( prev ) => ( {
				...prev,
				[ isDragging ]: { x: constrainedX / 2, y: constrainedY / 2 },
			} ) );
		},
		[ isDragging, dragOffset ]
	);

	const handleMouseUp = useCallback( () => {
		setIsDragging( null );
	}, [] );

	const handleSaveClick = () => {
		if ( ! templateName || ! templateKey || ! attachmentId ) {
			addNotice( 'error', __( 'Bitte füllen Sie alle Pflichtfelder aus.', 'bs-custom-mail' ) );
			return;
		}

		onSave( {
			id: template?.id,
			template_name: templateName,
			template_key: templateKey,
			attachment_id: attachmentId,
			template_config: JSON.stringify( config ),
			font_size: fontSize,
			is_active: true,
		} as PDFTemplate );
	};

	return (
		<div className="bs-pdf-template-editor">
			<div className="bs-page-header">
				<h2>
					{ template
						? __( 'PDF-Vorlage bearbeiten', 'bs-custom-mail' )
						: __( 'Neue PDF-Vorlage', 'bs-custom-mail' ) }
				</h2>
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

			<div
				style={ {
					display: 'grid',
					gridTemplateColumns: '1fr 320px',
					gap: '24px',
				} }
			>
				<div>
					{ attachmentUrl ? (
						<div
							ref={ canvasRef }
							style={ {
								width: '420px',
								height: '594px',
								background: '#f3f4f6',
								border: '2px solid #e5e7eb',
								borderRadius: '8px',
								position: 'relative',
								cursor: isDragging ? 'grabbing' : 'default',
								margin: '0 auto',
							} }
							onMouseMove={ handleMouseMove }
							onMouseUp={ handleMouseUp }
							onMouseLeave={ handleMouseUp }
						>
							<div
								style={ {
									position: 'absolute',
									inset: '0',
									display: 'flex',
									alignItems: 'center',
									justifyContent: 'center',
									color: '#9ca3af',
									fontSize: '14px',
								} }
							>
								{ __( 'PDF Hintergrund', 'bs-custom-mail' ) }
							</div>

							{ fields.map( ( field ) => {
								const pos = config[ field.key as keyof PDFTemplateConfig ] || { x: 0, y: 0 };
								return (
									<div
										key={ field.key }
										style={ {
											position: 'absolute',
											left: pos.x * 2,
											top: pos.y * 2,
											padding: '8px 16px',
											background: field.color,
											color: 'white',
											borderRadius: '6px',
											cursor: isDragging === field.key ? 'grabbing' : 'grab',
											fontSize: '14px',
											fontWeight: 500,
											boxShadow: '0 2px 4px rgba(0,0,0,0.2)',
											userSelect: 'none',
											zIndex: isDragging === field.key ? 10 : 1,
										} }
										onMouseDown={ ( e ) => handleMouseDown( e, field.key ) }
									>
										{ field.label }
										<div
											style={ {
												position: 'absolute',
												bottom: '-20px',
												left: '50%',
												transform: 'translateX(-50%)',
												fontSize: '10px',
												color: '#666',
												whiteSpace: 'nowrap',
											} }
										>
											{ pos.x.toFixed( 0 ) }mm, { pos.y.toFixed( 0 ) }mm
										</div>
									</div>
								);
							} ) }
						</div>
					) : (
						<div
							style={ {
								width: '420px',
								height: '594px',
								background: '#f9fafb',
								border: '2px dashed #d1d5db',
								borderRadius: '8px',
								display: 'flex',
								flexDirection: 'column',
								alignItems: 'center',
								justifyContent: 'center',
								margin: '0 auto',
							} }
						>
							<div style={ { fontSize: '48px', marginBottom: '16px' } }>📄</div>
							<p style={ { color: '#6b7280', marginBottom: '16px' } }>
								{ __( 'Bitte wählen Sie ein PDF aus', 'bs-custom-mail' ) }
							</p>
							<button className="button button-primary" onClick={ handleSelectPDF }>
								{ __( 'PDF auswählen', 'bs-custom-mail' ) }
							</button>
						</div>
					) }

					<div
						style={ {
							marginTop: '24px',
							display: 'flex',
							justifyContent: 'center',
							gap: '16px',
						} }
					>
						{ fields.map( ( field ) => (
							<div
								key={ field.key }
								style={ { display: 'flex', alignItems: 'center', gap: '6px' } }
							>
								<div
									style={ {
										width: '12px',
										height: '12px',
										background: field.color,
										borderRadius: '3px',
									} }
								/>
								<span style={ { fontSize: '12px', color: '#4b5563' } }>
									{ field.label }
								</span>
							</div>
						) ) }
					</div>
				</div>

				<div
					style={ {
						background: '#fff',
						borderRadius: '12px',
						padding: '20px',
						boxShadow: '0 1px 3px rgba(0,0,0,0.1)',
						height: 'fit-content',
					} }
				>
					<h3 style={ { margin: '0 0 20px 0', fontSize: '16px' } }>
						{ __( 'Einstellungen', 'bs-custom-mail' ) }
					</h3>

					<div style={ { marginBottom: '16px' } }>
						<label style={ { display: 'block', marginBottom: '6px', fontWeight: 500 } }>
							{ __( 'Vorlagenname', 'bs-custom-mail' ) } *
						</label>
						<input
							type="text"
							value={ templateName }
							onChange={ ( e ) => setTemplateName( e.target.value ) }
							className="regular-text"
							placeholder={ __( 'z.B. Standard Gutschein', 'bs-custom-mail' ) }
						/>
					</div>

					<div style={ { marginBottom: '16px' } }>
						<label style={ { display: 'block', marginBottom: '6px', fontWeight: 500 } }>
							{ __( 'Template Key', 'bs-custom-mail' ) } *
						</label>
						<input
							type="text"
							value={ templateKey }
							onChange={ ( e ) => setTemplateKey( e.target.value ) }
							className="regular-text"
							placeholder={ __( 'z.B. standard_gutschein', 'bs-custom-mail' ) }
							disabled={ !! template?.id }
						/>
						<p className="description">
							{ __( 'Eindeutiger technischer Name (nur Kleinbuchstaben, Zahlen, Unterstriche)', 'bs-custom-mail' ) }
						</p>
					</div>

					<div style={ { marginBottom: '16px' } }>
						<label style={ { display: 'block', marginBottom: '6px', fontWeight: 500 } }>
							{ __( 'PDF Datei', 'bs-custom-mail' ) } *
						</label>
						{ attachmentUrl ? (
							<div
								style={ {
									background: '#f0f9ff',
									padding: '12px',
									borderRadius: '6px',
									marginBottom: '8px',
								} }
							>
								<div style={ { display: 'flex', alignItems: 'center', gap: '8px' } }>
									<span>📄</span>
									<a
										href={ attachmentUrl }
										target="_blank"
										rel="noopener noreferrer"
										style={ { flex: 1 } }
									>
										{ attachmentUrl.split( '/' ).pop() }
									</a>
									<button
										className="button-link"
										onClick={ () => {
											setAttachmentId( 0 );
											setAttachmentUrl( '' );
										} }
										style={ { color: '#dc2626' } }
									>
										{ __( 'Entfernen', 'bs-custom-mail' ) }
									</button>
								</div>
							</div>
						) : null }
						<button className="button" onClick={ handleSelectPDF }>
							{ attachmentUrl
								? __( 'PDF ändern', 'bs-custom-mail' )
								: __( 'PDF auswählen', 'bs-custom-mail' ) }
						</button>
					</div>

					<div style={ { marginBottom: '16px' } }>
						<label style={ { display: 'block', marginBottom: '6px', fontWeight: 500 } }>
							{ __( 'Schriftgröße', 'bs-custom-mail' ) }
						</label>
						<input
							type="number"
							value={ fontSize }
							onChange={ ( e ) => setFontSize( parseInt( e.target.value ) || 16 ) }
							min="8"
							max="72"
							style={ { width: '80px' } }
						/>
						<span style={ { marginLeft: '8px' } }>pt</span>
					</div>

					<div
						style={ {
							marginTop: '24px',
							paddingTop: '16px',
							borderTop: '1px solid #e5e7eb',
							display: 'flex',
							gap: '8px',
						} }
					>
						<button className="button button-primary" onClick={ handleSaveClick }>
							{ __( 'Speichern', 'bs-custom-mail' ) }
						</button>
						<button className="button" onClick={ onCancel }>
							{ __( 'Abbrechen', 'bs-custom-mail' ) }
						</button>
					</div>
				</div>
			</div>
		</div>
	);
}
