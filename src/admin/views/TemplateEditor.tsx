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
	SelectControl,
} from '@wordpress/components';
import { arrowLeft } from '@wordpress/icons';
import { Template, ViewType } from '../types';
import { useTemplates, useNotices, useDefaultTemplates } from '../hooks';
import { Notices } from '../components/Notices';
// import { AttachmentUploader } from '../components/AttachmentUploader';
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
	const { templates, isLoading: isTemplatesLoading, createTemplate, updateTemplate, sendTestEmail } =
		useTemplates();
	const { templates: defaultTemplates, isLoading: isDefaultTemplatesLoading } = useDefaultTemplates();
	const { notices, success, error, removeNotice } = useNotices();
	const [ template, setTemplate ] = useState<Template>( EMPTY_TEMPLATE );
	const [ isSaving, setIsSaving ] = useState( false );
	const [ testEmail, setTestEmail ] = useState( '' );
	const [ isSendingTest, setIsSendingTest ] = useState( false );
	const [ selectedDefaultTemplate, setSelectedDefaultTemplate ] = useState( '' );

	// Load existing template data when editing
	useEffect( () => {
		if ( mode === 'edit' && templateKey ) {
			const existingTemplate = templates.find( ( t ) => t.template_key === templateKey );
			if ( existingTemplate ) {
				setTemplate( existingTemplate );
			}
		}
	}, [ mode, templateKey, templates ] );

	// Load default template when selected
	useEffect( () => {
		if ( selectedDefaultTemplate ) {
			const defaultTemplate = defaultTemplates.find( 
				( t ) => t.filename === selectedDefaultTemplate 
			);
			if ( defaultTemplate ) {
				setTemplate( ( prev ) => ( {
					...prev,
					subject: defaultTemplate.subject || prev.subject,
					content: defaultTemplate.content || prev.content,
				} ) );
			}
		}
	}, [ selectedDefaultTemplate, defaultTemplates ] );

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
					content: template.content,
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

	// Show loading state while templates are loading in edit mode
	if ( mode === 'edit' && isTemplatesLoading ) {
		return (
			<div className="bs-loading">
				<Spinner />
				<p>{ __( 'Lade Template...', 'bs-custom-mail' ) }</p>
			</div>
		);
	}

	const defaultTemplateOptions = [
		{ value: '', label: __( '-- Vorlage auswählen --', 'bs-custom-mail' ) },
		...defaultTemplates.map( ( t ) => ( { 
			value: t.filename, 
			label: t.name 
		} ) ),
	];

	return (
		<div className="bs-template-editor">
			<Notices notices={ notices } onRemove={ removeNotice } />

			<div className="bs-editor-header">
				<div>
					<h2>
						{ mode === 'create'
							? __( 'Neues Template erstellen', 'bs-custom-mail' )
							: __( 'Template bearbeiten', 'bs-custom-mail' ) }
					</h2>
					{ mode === 'edit' && template.template_key && (
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
								<h3>{ __( 'Vorlage laden (optional)', 'bs-custom-mail' ) }</h3>
							</CardHeader>
							<CardBody>
								<SelectControl
									label={ __( 'Bestehende Vorlage laden', 'bs-custom-mail' ) }
									value={ selectedDefaultTemplate }
									options={ defaultTemplateOptions }
									onChange={ setSelectedDefaultTemplate }
									help={ __( 'Wähle eine Vorlage als Ausgangspunkt', 'bs-custom-mail' ) }
									disabled={ isDefaultTemplatesLoading }
								/>
							</CardBody>
						</Card>
					) }

					{ mode === 'create' && (
						<Card className="bs-card-warning">
							<CardHeader>
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
							<h3>{ __( 'E-Mail Details', 'bs-custom-mail' ) }</h3>
						</CardHeader>
						<CardBody>
							<TextControl
								label={ __( 'Betreff', 'bs-custom-mail' ) }
								value={ template.subject }
								onChange={ ( subject ) => setTemplate( { ...template, subject } ) }
								required
							/>
						</CardBody>
					</Card>

					<Card>
						<CardHeader>
							<h3>{ __( 'Inhalt', 'bs-custom-mail' ) }</h3>
						</CardHeader>
						<CardBody>
							<TextareaControl
								label={ __( 'E-Mail Text', 'bs-custom-mail' ) }
								value={ template.content }
								onChange={ ( content: string ) =>
									setTemplate( { ...template, content } )
								}
								rows={ 20 }
								help={ __( 'Verwende Platzhalter wie {{Kundenname}}, {{Produktname}}, etc.', 'bs-custom-mail' ) }
							/>
						</CardBody>
					</Card>
				</div>

				<div className="bs-editor-sidebar">
					<PlaceholderHelp
						onCopy={ ( code ) => {
							// Insert placeholder at cursor position in content
							const textarea = document.querySelector( 'textarea' ) as HTMLTextAreaElement;
							if ( textarea ) {
								const start = textarea.selectionStart;
								const end = textarea.selectionEnd;
								const newContent = template.content.substring( 0, start ) + code + template.content.substring( end );
								setTemplate( { ...template, content: newContent } );
							}
							success( `${ code } ${ __( 'eingefügt!', 'bs-custom-mail' ) }` );
						} }
					/>

					<Card style={ { marginTop: '20px' } }>
						<CardHeader>
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
								style={ { marginTop: '10px' } }
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
								icon={ arrowLeft }
								style={ {
									width: '100%',
									marginTop: '10px',
									justifyContent: 'center',
								} }
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
