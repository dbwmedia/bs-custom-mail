/**
 * Template editor view
 */
import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import {
	Button,
	Card,
	CardHeader,
	CardBody,
	TextControl,
	TextareaControl,
} from '@wordpress/components';
import { ViewType } from '../types';

interface TemplateEditorProps {
	mode: 'create' | 'edit';
	templateKey?: string;
	onNavigate: ( view: ViewType, templateKey?: string ) => void;
}

export function TemplateEditor( { mode, templateKey, onNavigate }: TemplateEditorProps ) {
	const [ subject, setSubject ] = useState( '' );
	const [ content, setContent ] = useState( '' );

	return (
		<div className="bs-template-editor">
			<h2>
				{ mode === 'create'
					? __( 'Neues Template erstellen', 'bs-custom-mail' )
					: __( 'Template bearbeiten', 'bs-custom-mail' ) }
			</h2>
			{ templateKey && <p>Key: { templateKey }</p> }

			<Card>
				<CardHeader>
					<h3>{ __( 'E-Mail Details', 'bs-custom-mail' ) }</h3>
				</CardHeader>
				<CardBody>
					<TextControl
						label={ __( 'Betreff', 'bs-custom-mail' ) }
						value={ subject }
						onChange={ setSubject }
					/>
					<TextareaControl
						label={ __( 'Inhalt', 'bs-custom-mail' ) }
						value={ content }
						onChange={ setContent }
						rows={ 10 }
					/>
				</CardBody>
			</Card>

			<Button
				variant="primary"
				onClick={ () => onNavigate( 'list' ) }
				style={ { marginTop: '20px' } }
			>
				{ __( 'Zurück', 'bs-custom-mail' ) }
			</Button>
		</div>
	);
}
