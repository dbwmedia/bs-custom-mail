/**
 * Placeholder help component
 */
import { useState } from '@wordpress/element';
import { Card, CardHeader, CardBody } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { usePlaceholders } from '../hooks';

interface PlaceholderHelpProps {
	onCopy?: ( code: string ) => void;
}

export function PlaceholderHelp( { onCopy }: PlaceholderHelpProps ) {
	const { placeholders, isLoading } = usePlaceholders();
	const [ copiedCode, setCopiedCode ] = useState<string | null>( null );

	const handleCopy = ( code: string ) => {
		navigator.clipboard.writeText( code );
		setCopiedCode( code );
		if ( onCopy ) {
			onCopy( code );
		}
		setTimeout( () => setCopiedCode( null ), 1500 );
	};

	if ( isLoading ) {
		return (
			<Card>
				<CardHeader>
					<h3>{ __( 'Platzhalter', 'bs-custom-mail' ) }</h3>
				</CardHeader>
				<CardBody>
					<p>{ __( 'Lade Platzhalter...', 'bs-custom-mail' ) }</p>
				</CardBody>
			</Card>
		);
	}

	return (
		<Card>
			<CardHeader>
				<h3>{ __( 'Platzhalter', 'bs-custom-mail' ) }</h3>
			</CardHeader>
			<CardBody>
				<p className="bs-card-description">
					{ __( 'Klicke zum Einfügen:', 'bs-custom-mail' ) }
				</p>
				<ul className="bs-placeholders-list">
					{ placeholders.map( ( { code, label, description } ) => (
						<li key={ code } title={ description }>
							<code
								className={ `bs-copy ${ copiedCode === code ? 'copied' : '' }` }
								onClick={ () => handleCopy( code ) }
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
