/**
 * Settings view
 */
import { __ } from '@wordpress/i18n';

export function Settings() {
	return (
		<div className="bs-settings">
			<h2>{ __( 'Einstellungen', 'bs-custom-mail' ) }</h2>
		</div>
	);
}
