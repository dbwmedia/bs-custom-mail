/**
 * Settings view
 */
import { __ } from '@wordpress/i18n';
import {
	Button,
	Card,
	CardHeader,
	CardBody,
	TextControl,
	SelectControl,
	Spinner,
} from '@wordpress/components';
import { useSettings, useNotices } from '../hooks';
import { Notices } from '../components/Notices';

const ORDER_STATUSES = [
	{ value: 'processing', label: __( 'In Bearbeitung (processing)', 'bs-custom-mail' ) },
	{ value: 'completed', label: __( 'Abgeschlossen (completed)', 'bs-custom-mail' ) },
	{ value: 'on-hold', label: __( 'Wartend (on-hold)', 'bs-custom-mail' ) },
];

export function Settings() {
	const { settings, isLoading, updateSettings } = useSettings();
	const { notices, success, error, removeNotice } = useNotices();

	const handleSave = async () => {
		if ( ! settings ) return;

		try {
			await updateSettings( settings );
			success( __( 'Einstellungen gespeichert.', 'bs-custom-mail' ) );
		} catch ( err ) {
			error( __( 'Fehler beim Speichern.', 'bs-custom-mail' ) );
		}
	};

	if ( isLoading || ! settings ) {
		return (
			<div className="bs-loading">
				<Spinner />
				<p>{ __( 'Lade Einstellungen...', 'bs-custom-mail' ) }</p>
			</div>
		);
	}

	return (
		<div className="bs-settings">
			<Notices notices={ notices } onRemove={ removeNotice } />

			<h2>{ __( 'Einstellungen', 'bs-custom-mail' ) }</h2>

			<Card>
				<CardHeader>
					<h3>{ __( 'Trigger-Einstellungen', 'bs-custom-mail' ) }</h3>
				</CardHeader>
				<CardBody>
					<SelectControl
						label={ __( 'E-Mail Trigger Status', 'bs-custom-mail' ) }
						value={ settings.trigger_status }
						options={ ORDER_STATUSES }
						onChange={ ( trigger_status ) =>
							updateSettings( { ...settings, trigger_status } )
						}
						help={ __(
							'Der Bestellstatus, bei dem die E-Mail versendet wird.',
							'bs-custom-mail'
						) }
					/>
				</CardBody>
			</Card>

			<Card style={ { marginTop: '20px' } }>
				<CardHeader>
					<h3>{ __( 'Absender-Einstellungen', 'bs-custom-mail' ) }</h3>
				</CardHeader>
				<CardBody>
					<TextControl
						label={ __( 'Absender Name', 'bs-custom-mail' ) }
						value={ settings.from_name }
						onChange={ ( from_name ) =>
							updateSettings( { ...settings, from_name } )
						}
					/>
					<TextControl
						label={ __( 'Absender E-Mail', 'bs-custom-mail' ) }
						type="email"
						value={ settings.from_email }
						onChange={ ( from_email ) =>
							updateSettings( { ...settings, from_email } )
						}
					/>
				</CardBody>
			</Card>

			<div style={ { marginTop: '20px' } }>
				<Button variant="primary" onClick={ handleSave }>
					{ __( 'Einstellungen speichern', 'bs-custom-mail' ) }
				</Button>
			</div>

			<Card style={ { marginTop: '40px' } } className="bs-system-status">
				<CardHeader>
					<h3>{ __( 'System Status', 'bs-custom-mail' ) }</h3>
				</CardHeader>
				<CardBody>
					<div className="bs-status-grid">
						<div className="bs-status-item">
							<span
								className={ `bs-status-dot ${
									typeof (window as any).wc !== 'undefined' ? 'active' : 'inactive'
								}` }
							></span>
							<span>WooCommerce</span>
						</div>
						<div className="bs-status-item">
							<span className="bs-status-dot active"></span>
							<span>WordPress REST API</span>
						</div>
					</div>
				</CardBody>
			</Card>
		</div>
	);
}
