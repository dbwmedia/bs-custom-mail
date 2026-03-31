/**
 * Settings view
 */
import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { Card, CardHeader, CardBody, Button, TextControl, SelectControl, Notice } from '@wordpress/components';
import { useSettings } from '../hooks';

export function Settings() {
	const { settings, isLoading, updateSettings } = useSettings();
	const [ formData, setFormData ] = useState( {
		trigger_status: 'processing',
		from_name: '',
		from_email: '',
	} );
	const [ isSaving, setIsSaving ] = useState( false );
	const [ showSuccess, setShowSuccess ] = useState( false );

	// Update form data when settings load
	useState( () => {
		if ( settings ) {
			setFormData( {
				trigger_status: settings.trigger_status || 'processing',
				from_name: settings.from_name || '',
				from_email: settings.from_email || '',
			} );
		}
	} );

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

	if ( isLoading ) {
		return (
			<div className="bs-settings">
				<h2>{ __( 'Einstellungen', 'bs-custom-mail' ) }</h2>
				<p>{ __( 'Lade Einstellungen...', 'bs-custom-mail' ) }</p>
			</div>
		);
	}

	return (
		<div className="bs-settings">
			<h2>{ __( 'Einstellungen', 'bs-custom-mail' ) }</h2>

			{ showSuccess && (
				<Notice status="success" isDismissible onDismiss={ () => setShowSuccess( false ) }>
					{ __( 'Einstellungen gespeichert.', 'bs-custom-mail' ) }
				</Notice>
			) }

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
						] }
						onChange={ ( value ) => setFormData( { ...formData, trigger_status: value } ) }
					/>

					<TextControl
						label={ __( 'Absender Name', 'bs-custom-mail' ) }
						value={ formData.from_name }
						onChange={ ( value ) => setFormData( { ...formData, from_name: value } ) }
					/>

					<TextControl
						label={ __( 'Absender E-Mail', 'bs-custom-mail' ) }
						value={ formData.from_email }
						onChange={ ( value ) => setFormData( { ...formData, from_email: value } ) }
						type="email"
					/>

					<Button
						variant="primary"
						onClick={ handleSave }
						isBusy={ isSaving }
						disabled={ isSaving }
					>
						{ __( 'Einstellungen speichern', 'bs-custom-mail' ) }
					</Button>
				</CardBody>
			</Card>
		</div>
	);
}
