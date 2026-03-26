/**
 * Template editor view
 */
import { useState, useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import {
	Button,
	Card,
	CardHeader,
	CardBody,
	TextControl,
	ToggleControl,
	Spinner,
	TextareaControl,
} from '@wordpress/components';
import { arrowLeft } from '@wordpress/icons';
import { Template, ViewType, Attachment } from '../types';
import { useTemplates, useNotices } from '../hooks';
import { Notices } from '../components/Notices';
import { AttachmentUploader } from '../components/AttachmentUploader';
import { PlaceholderHelp } from '../components/PlaceholderHelp';

interface TemplateEditorProps {
	mode: 'create' | 'edit';
	templateKey?: string;
	onNavigate: ( view: ViewType, templateKey?: string ) => void;
}

const EMPTY_TEMPLATE: Template = {
	template_key: '',
	template_name: '',
	subject: '',
	header_text: '',
	content: '',
	footer_text: '',
	attachments: [],
	is_active: true,
};

export function TemplateEditor( { mode, templateKey, onNavigate }: TemplateEditorProps ) {
	const { templates, createTemplate, updateTemplate, sendTestEmail } =
		useTemplates();
	const { notices, success, error, removeNotice } = useNotices();
	const [ template, setTemplate ] = useState<Template>( EMPTY_TEMPLATE );
	const [ isLoading, setIsLoading ] = useState( mode === 'edit' );
	const [ isSaving, setIsSaving ] = useState( false );
	const [ testEmail, setTestEmail ] = useState( '' );
	const [ isSendingTest, setIsSendingTest ] = useState( false );

	useEffect( () => {
		if ( mode === 'edit' && templateKey ) {
			const existingTemplate = templates.find( ( t ) => t.template_key === templateKey );
			if ( existingTemplate ) {
				setTemplate( existingTemplate );
				setIsLoading( false );
			}
		}
	}, [ mode, templateKey, templates ] );

	const handleSave = async () => {
		if ( ! template.template_name || ! template.subject ) {
			error( __( 'Template Name und Betreff sind Pflichtfelder.', 'bs-custom-mail' ) );
			return;
		}

		if ( mode === 'create' && ! template.template_key ) {
			error( __( 'Template Key ist erforderlich.', 'bs-custom-mail' ) );
			return;
		}

		setIsSaving( true );
		try {
			if ( mode === 'create' ) {
				await createTemplate( template );
				success( __( 'Template erfolgreich erstellt.', 'bs-custom-mail' ) );
				onNavigate( 'list' );
			} else if ( templateKey ) {
				await updateTemplate( templateKey, {
					subject: template.subject,
					header_text: template.header_text,
					content: template.content,
					footer_text: template.footer_text,
					attachments: template.attachments as Attachment[],
					is_active: template.is_active,
				} );
				success( __( 'Template erfolgreich gespeichert.', 'bs-custom-mail' ) );
			}
		} catch ( err ) {
			error(
				err instanceof Error
					? err.message
					: __( 'Fehler beim Speichern.', 'bs-custom-mail' )
			);
		} finally {
			setIsSaving( false );
		}
	};

	const handleSendTest = async () => {
		if ( ! testEmail || ! testEmail.includes( '@' ) ) {
			error( __( 'Bitte geben Sie eine gültige E-Mail Adresse ein.', 'bs-custom-mail' ) );
			return;
		}

		setIsSendingTest( true );
		try {
			const result = await sendTestEmail(
				mode === 'edit' ? templateKey! : template.template_key,
				testEmail
			);
			success( result.message );
		} catch ( err ) {
			error( __( 'Fehler beim Senden der Test-E-Mail.', 'bs-custom-mail' ) );
		} finally {
			setIsSendingTest( false );
		}
	};

	const handleAttachmentChange = ( attachments: Attachment[] ) => {
		setTemplate( { ...template, attachments } );
	};

	if ( isLoading ) {
		return (
			<div className="bs-loading">
				<Spinner />
				<p>{ __( 'Lade Template...', 'bs-custom-mail' ) }</p>
			</div>
		);
	}

	return (
		<div className="bs-template-editor">
			<Notices notices={ notices } onRemove={ removeNotice } />

			<div className="bs-editor-header">
				<div>
					<h2>
						{ mode === 'create'
							? __( 'Neues Template erstellen', 'bs-custom-mail' )
							: template.template_name }
					</h2>
					{ mode === 'edit' && (
						<span className="bs-template-key">{ template.template_key }</span>
					) }
				</div>
				{ mode === 'edit' && (
					<div className="bs-status-toggle">
						<ToggleControl
							label={ template.is_active ? __( 'Aktiv', 'bs-custom-mail' ) : __( 'Inaktiv', 'bs-custom-mail' ) }
							checked={ template.is_active }
							onChange={ ( is_active ) =>
								setTemplate( { ...template, is_active } )
							}
						/>
					</div>
				) }
			</div>

			<div className="bs-editor-grid">
				<div className="bs-editor-main">
					{ mode === 'create' && (
						<Card className="bs-card-warning">
							<CardHeader>
								<span className="dashicons dashicons-warning"></span>
								<h3>{ __( 'Wichtig: Template Key', 'bs-custom-mail' ) }</h3>
							</CardHeader>
							<CardBody>
								<TextControl
									label={ __( 'Template Key', 'bs-custom-mail' ) }
									value={ template.template_key }
									onChange={ ( template_key ) =>
										setTemplate( { ...template, template_key } )
									}
									help={ __(
										'Nur Kleinbuchstaben, Zahlen und Unterstriche. Kann später nicht geändert werden.',
										'bs-custom-mail'
									) }
									required
								/>
								<TextControl
									label={ __( 'Template Name', 'bs-custom-mail' ) }
									value={ template.template_name }
									onChange={ ( template_name ) =>
										setTemplate( { ...template, template_name } )
									}
									required
								/>
							</CardBody>
						</Card>
					) }

					<Card>
						<CardHeader>
							<span className="dashicons dashicons-email"></span>
							<h3>{ __( 'E-Mail Details', 'bs-custom-mail' ) }</h3>
						</CardHeader>
						<CardBody>
							<TextControl
								label={ __( 'Betreff', 'bs-custom-mail' ) }
								value={ template.subject }
								onChange={ ( subject ) => setTemplate( { ...template, subject } ) }
								required
								help={ __(
									'Verwende Platzhalter wie {{order_number}}',
									'bs-custom-mail'
								) }
							/>
						</CardBody>
					</Card>

					<Card>
						<CardHeader>
							<span className="dashicons dashicons-paperclip"></span>
							<h3>{ __( 'Template-Anhänge', 'bs-custom-mail' ) }</h3>
						</CardHeader>
						<CardBody>
							<p className="bs-card-description">
								{ __(
									'Diese Dateien werden an alle E-Mails dieses Templates angehängt.',
									'bs-custom-mail'
								) }
							</p>
							<AttachmentUploader
								attachments={ template.attachments as Attachment[] }
								onChange={ handleAttachmentChange }
							/>
						</CardBody>
					</Card>

					<Card>
						<CardHeader>
							<span className="dashicons dashicons-format-quote"></span>
							<h3>{ __( 'Header', 'bs-custom-mail' ) }</h3>
						</CardHeader>
						<CardBody>
							<TextareaControl
								value={ template.header_text }
								onChange={ ( header_text: string ) =>
									setTemplate( { ...template, header_text } )
								}
								rows={ 4 }
							/>
						</CardBody>
					</Card>

					<Card>
						<CardHeader>
							<span className="dashicons dashicons-text-page"></span>
							<h3>{ __( 'Inhalt', 'bs-custom-mail' ) }</h3>
						</CardHeader>
						<CardBody>
							<TextareaControl
								value={ template.content }
								onChange={ ( content: string ) =>
									setTemplate( { ...template, content } )
								}
								rows={ 15 }
							/>
						</CardBody>
					</Card>

					<Card>
						<CardHeader>
							<span className="dashicons dashicons-editor-insertmore"></span>
							<h3>{ __( 'Footer', 'bs-custom-mail' ) }</h3>
						</CardHeader>
						<CardBody>
							<TextareaControl
								value={ template.footer_text }
								onChange={ ( footer_text: string ) =>
									setTemplate( { ...template, footer_text } )
								}
								rows={ 4 }
							/>
						</CardBody>
					</Card>
				</div>

				<div className="bs-editor-sidebar">
					<PlaceholderHelp
						onCopy={ ( code ) =>
							success( `${ code } ${ __( 'kopiert!', 'bs-custom-mail' ) }` )
						}
					/>

					<Card style={ { marginTop: '20px' } }>
						<CardHeader>
							<span className="dashicons dashicons-email-alt"></span>
							<h3>{ __( 'Test-E-Mail', 'bs-custom-mail' ) }</h3>
						</CardHeader>
						<CardBody>
							<TextControl
								type="email"
								value={ testEmail }
								onChange={ setTestEmail }
								placeholder={ __( 'E-Mail Adresse', 'bs-custom-mail' ) }
							/>
							<Button
								variant="secondary"
								onClick={ handleSendTest }
								isBusy={ isSendingTest }
								disabled={ isSendingTest }
								// Note: No icon available for send action
							>
								{ __( 'Test-E-Mail senden', 'bs-custom-mail' ) }
							</Button>
						</CardBody>
					</Card>

					<Card className="bs-actions-card" style={ { marginTop: '20px' } }>
						<CardBody>
							<Button
								variant="primary"
								onClick={ handleSave }
								isBusy={ isSaving }
								disabled={ isSaving }
								isPressed
								style={ { width: '100%', justifyContent: 'center' } }
							>
								{ mode === 'create'
									? __( 'Template erstellen', 'bs-custom-mail' )
									: __( 'Template speichern', 'bs-custom-mail' ) }
							</Button>
							<Button
								variant="tertiary"
								onClick={ () => onNavigate( 'list' ) }
								style={ {
									width: '100%',
									marginTop: '10px',
									justifyContent: 'center',
								} }
								icon={ arrowLeft }
							>
								{ __( 'Zurück zur Übersicht', 'bs-custom-mail' ) }
							</Button>
						</CardBody>
					</Card>
				</div>
			</div>
		</div>
	);
}
