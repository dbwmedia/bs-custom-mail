/**
 * Placeholder help component
 */
import { useState } from '@wordpress/element';
import { Card, CardHeader, CardBody } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

const PLACEHOLDERS = [
	{ code: '{{customer_name}}', label: __( 'Vorname', 'bs-custom-mail' ) },
	{ code: '{{customer_full_name}}', label: __( 'Vollständiger Name', 'bs-custom-mail' ) },
	{ code: '{{order_number}}', label: __( 'Bestellnummer', 'bs-custom-mail' ) },
	{ code: '{{order_date}}', label: __( 'Bestelldatum', 'bs-custom-mail' ) },
	{ code: '{{product_name}}', label: __( 'Produktname', 'bs-custom-mail' ) },
	{ code: '{{site_name}}', label: __( 'Website-Name', 'bs-custom-mail' ) },
	{ code: '{{site_url}}', label: __( 'Website-URL', 'bs-custom-mail' ) },
];

interface PlaceholderHelpProps {
	onCopy?: ( code: string ) => void;
}

export function PlaceholderHelp( { onCopy }: PlaceholderHelpProps ) {
	const [ copiedCode, setCopiedCode ] = useState<string | null>( null );

	const handleCopy = ( code: string ) => {
		navigator.clipboard.writeText( code );
		setCopiedCode( code );
		if ( onCopy ) {
			onCopy( code );
		}
		setTimeout( () => setCopiedCode( null ), 1500 );
	};

	return (
		<Card>
			<CardHeader>
				<h3>{ __( 'Platzhalter', 'bs-custom-mail' ) }</h3>
			</CardHeader>
			<CardBody>
				<p className="bs-card-description">
					{ __( 'Klicke zum Kopieren:', 'bs-custom-mail' ) }
				</p>
				<ul className="bs-placeholders-list">
					{ PLACEHOLDERS.map( ( { code, label } ) => (
						<li key={ code }>
							<code
								className={ `bs-copy ${ copiedCode === code ? 'copied' : '' }` }
								onClick={ () => handleCopy( code ) }
								title={ __( 'Klicken zum Kopieren', 'bs-custom-mail' ) }
							>
								{ copiedCode === code ? __( 'Kopiert!', 'bs-custom-mail' ) : code }
							</code>
							<small>{ label }</small>
						</li>
					) ) }
				</ul>
			</CardBody>
		</Card>
	);
}
