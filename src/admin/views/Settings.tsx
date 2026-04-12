/**
 * Settings view with Test Email functionality
 */
import { useState, useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { Card, CardHeader, CardBody, Button, TextControl, SelectControl, Notice, Spinner } from '@wordpress/components';
import { useSettings, useTemplates } from '../hooks';

export function Settings() {
	const { settings, isLoading, updateSettings } = useSettings();
	const { templates, isLoading: isTemplatesLoading, sendTestEmail } = useTemplates();
	const [ formData, setFormData ] = useState( {
		trigger_status: 'processing',
		from_name: '',
		from_email: '',
	} );
	const [ isSaving, setIsSaving ] = useState( false );
	const [ showSuccess, setShowSuccess ] = useState( false );

	// Test email state
	const [ testEmail, setTestEmail ] = useState( '' );
	const [ selectedTemplate, setSelectedTemplate ] = useState( '' );
	const [ isSendingTest, setIsSendingTest ] = useState( false );
	const [ testMessage, setTestMessage ] = useState<{ type: 'success' | 'error'; text: string } | null>( null );

	// Update form data when settings load
	useEffect( () => {
		if ( settings ) {
			setFormData( {
				trigger_status: settings.trigger_status || 'processing',
				from_name: settings.from_name || '',
				from_email: settings.from_email || '',
			} );
		}
	}, [ settings ] );

	// Set default template when templates load
	useEffect( () => {
		if ( templates.length > 0 && ! selectedTemplate ) {
			setSelectedTemplate( templates[0].template_key );
		}
	}, [ templates, selectedTemplate ] );

	const handleSave = async () => {
		setIsSaving( true );
		try {
			await updateSettings( formData );
			setShowSuccess( true );
			setTimeout( () => setShowSuccess( false ), 3000 );
		} finally {
			setIsSaving( false );
		}
	};

	const handleSendTest = async () => {
		if ( ! testEmail || ! testEmail.includes( '@' ) ) {
			setTestMessage( { type: 'error', text: __( 'Bitte geben Sie eine gültige E-Mail Adresse ein.', 'bs-custom-mail' ) } );
			return;
		}

		if ( ! selectedTemplate ) {
			setTestMessage( { type: 'error', text: __( 'Bitte wählen Sie ein Template aus.', 'bs-custom-mail' ) } );
			return;
		}

		setIsSendingTest( true );
		setTestMessage( null );
		try {
			const result = await sendTestEmail( selectedTemplate, testEmail );
			setTestMessage( { type: 'success', text: result.message } );
		} catch ( err ) {
			setTestMessage( { 
				type: 'error', 
				text: err instanceof Error ? err.message : __( 'Fehler beim Senden der Test-E-Mail.', 'bs-custom-mail' ) 
			} );
		} finally {
			setIsSendingTest( false );
		}
	};

	if ( isLoading ) {
		return (
			<div className="bs-settings" style={ { padding: '24px' } }>
				<h2>{ __( 'Einstellungen', 'bs-custom-mail' ) }</h2>
				<p><Spinner /> { __( 'Lade Einstellungen...', 'bs-custom-mail' ) }</p>
			</div>
		);
	}

	const templateOptions = templates.map( ( t ) => ( { 
		label: t.template_name, 
		value: t.template_key 
	} ) );

	return (
		<div className="bs-settings" style={ { padding: '24px', maxWidth: '1200px' } }>
			<h2 style={ { marginBottom: '24px' } }>{ __( 'Einstellungen', 'bs-custom-mail' ) }</h2>

			{ showSuccess && (
				<Notice status="success" isDismissible onDismiss={ () => setShowSuccess( false ) }>
					{ __( 'Einstellungen gespeichert.', 'bs-custom-mail' ) }
				</Notice>
			) }

			<div style={ { display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '24px' } }>
				<div>
					<Card>
						<CardHeader>
							<h3>{ __( 'E-Mail Einstellungen', 'bs-custom-mail' ) }</h3>
						</CardHeader>
						<CardBody>
							<SelectControl
								label={ __( 'Trigger Status', 'bs-custom-mail' ) }
								value={ formData.trigger_status }
								options={ [
									{ label: __( 'Processing', 'bs-custom-mail' ), value: 'processing' },
									{ label: __( 'Completed', 'bs-custom-mail' ), value: 'completed' },
									{ label: __( 'On Hold', 'bs-custom-mail' ), value: 'on-hold' },
								] }
								onChange={ ( value ) => setFormData( { ...formData, trigger_status: value } ) }
								help={ __( 'Bestellstatus, bei dem E-Mails automatisch versendet werden.', 'bs-custom-mail' ) }
							/>

							<TextControl
								label={ __( 'Absender Name', 'bs-custom-mail' ) }
								value={ formData.from_name }
								onChange={ ( value ) => setFormData( { ...formData, from_name: value } ) }
								placeholder="Bootsschule Berlin Köpenick"
							/>

							<TextControl
								label={ __( 'Absender E-Mail', 'bs-custom-mail' ) }
								value={ formData.from_email }
								onChange={ ( value ) => setFormData( { ...formData, from_email: value } ) }
								type="email"
								placeholder="info@bootsschule.de"
								help={ __( 'Stelle sicher, dass diese E-Mail-Domain für den Versand autorisiert ist (SPF/DKIM).', 'bs-custom-mail' ) }
							/>

							<Button
								variant="primary"
								onClick={ handleSave }
								isBusy={ isSaving }
								disabled={ isSaving }
								style={ { marginTop: '16px' } }
							>
								{ __( 'Einstellungen speichern', 'bs-custom-mail' ) }
							</Button>
						</CardBody>
					</Card>
				</div>

				<div>
					<Card>
						<CardHeader>
							<h3>{ __( 'Test-E-Mail senden', 'bs-custom-mail' ) }</h3>
						</CardHeader>
						<CardBody>
							<p style={ { marginBottom: '16px', color: '#6b7280' } }>
								{ __( 'Teste jedes Template vor dem Live-Betrieb.', 'bs-custom-mail' ) }
							</p>

							{ testMessage && (
								<div style={ { marginBottom: '16px' } }>
									<Notice 
										status={ testMessage.type } 
										isDismissible 
										onDismiss={ () => setTestMessage( null ) }
									>
										{ testMessage.text }
									</Notice>
								</div>
							) }

							{ isTemplatesLoading ? (
								<p><Spinner /> { __( 'Lade Templates...', 'bs-custom-mail' ) }</p>
							) : templates.length === 0 ? (
								<Notice status="warning" isDismissible={ false }>
									{ __( 'Keine Templates verfügbar. Bitte erstellen Sie zuerst ein Template.', 'bs-custom-mail' ) }
								</Notice>
							) : (
								<>
									<SelectControl
										label={ __( 'Template auswählen', 'bs-custom-mail' ) }
										value={ selectedTemplate }
										options={ templateOptions }
										onChange={ setSelectedTemplate }
										style={ { marginBottom: '16px' } }
									/>

									<TextControl
										label={ __( 'E-Mail Adresse', 'bs-custom-mail' ) }
										value={ testEmail }
										onChange={ setTestEmail }
										type="email"
										placeholder="test@example.com"
										style={ { marginBottom: '16px' } }
									/>

									<Button
										variant="secondary"
										onClick={ handleSendTest }
										isBusy={ isSendingTest }
										disabled={ isSendingTest || ! selectedTemplate }
									>
										{ __( 'Test-E-Mail senden', 'bs-custom-mail' ) }
									</Button>
								</>
							) }
						</CardBody>
					</Card>
				</div>
			</div>
		</div>
	);
}
