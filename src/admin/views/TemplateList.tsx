/**
 * Template list view
 */
import { __ } from '@wordpress/i18n';
import {
	Button,
	Card,
	CardHeader,
	CardBody,
	Spinner,
} from '@wordpress/components';
import { edit, trash, plus } from '@wordpress/icons';
import { Template, ViewType } from '../types';
import { useTemplates, useNotices } from '../hooks';
import { Notices } from '../components/Notices';

interface TemplateListProps {
	onNavigate: ( view: ViewType, templateKey?: string ) => void;
}

export function TemplateList( { onNavigate }: TemplateListProps ) {
	const { templates, isLoading, deleteTemplate } = useTemplates();
	const { notices, success, error, removeNotice } = useNotices();

	const handleDelete = async ( template: Template ) => {
		if (
			! window.confirm(
				__(
					'Sind Sie sicher? Dieses Template wird unwiderruflich gelöscht.',
					'bs-custom-mail'
				)
			)
		) {
			return;
		}

		try {
			await deleteTemplate( template.template_key );
			success( __( 'Template erfolgreich gelöscht.', 'bs-custom-mail' ) );
		} catch ( err ) {
			const apiMessage = ( err as { message?: string } )?.message;
			error( apiMessage || __( 'Fehler beim Löschen des Templates.', 'bs-custom-mail' ) );
		}
	};

	const formatTimeAgo = ( dateString: string ) => {
		const date = new Date( dateString );
		const now = new Date();
		const diffMs = now.getTime() - date.getTime();
		const diffMins = Math.floor( diffMs / 60000 );
		const diffHours = Math.floor( diffMs / 3600000 );
		const diffDays = Math.floor( diffMs / 86400000 );

		if ( diffMins < 1 ) return __( 'Gerade eben', 'bs-custom-mail' );
		if ( diffMins < 60 ) return `${ diffMins }m`;
		if ( diffHours < 24 ) return `${ diffHours }h`;
		if ( diffDays < 30 ) return `${ diffDays }d`;
		return date.toLocaleDateString();
	};

	if ( isLoading ) {
		return (
			<div className="bs-loading">
				<Spinner />
				<p>{ __( 'Lade Templates...', 'bs-custom-mail' ) }</p>
			</div>
		);
	}

	return (
		<div className="bs-template-list">
			<Notices notices={ notices } onRemove={ removeNotice } />

			<div className="bs-page-header">
				<div className="bs-header-content">
					<div>
						<h2>{ __( 'E-Mail Templates', 'bs-custom-mail' ) }</h2>
						<p className="bs-description">
							{ __(
								'Verwalten Sie die automatischen E-Mails für Bootsschule-Produkte.',
								'bs-custom-mail'
							) }
						</p>
					</div>
					<Button
						variant="primary"
						onClick={ () => onNavigate( 'create' ) }
						icon={ plus }
					>
						{ __( 'Neues Template', 'bs-custom-mail' ) }
					</Button>
				</div>
			</div>

			{ templates.length === 0 ? (
				<Card className="bs-empty-state">
					<CardBody>
						<div className="bs-empty-content">
							<span className="dashicons dashicons-email-alt"></span>
							<h3>{ __( 'Keine Templates vorhanden', 'bs-custom-mail' ) }</h3>
							<p>
								{ __(
									'Erstellen Sie Ihr erstes E-Mail-Template, um zu beginnen.',
									'bs-custom-mail'
								) }
							</p>
							<Button
								variant="primary"
								onClick={ () => onNavigate( 'create' ) }
							>
								{ __( 'Template erstellen', 'bs-custom-mail' ) }
							</Button>
						</div>
					</CardBody>
				</Card>
			) : (
				<div className="bs-templates-grid">
					{ templates.map( ( template ) => (
						<Card
							key={ template.template_key }
							className={ `bs-template-card ${ template.is_active ? 'bs-active' : 'bs-inactive' }` }
						>
							<CardHeader>
								<div className="bs-template-header">
									<div className="bs-template-icon">
										<span className="dashicons dashicons-email"></span>
									</div>
									<div className="bs-template-status">
										{ template.is_active ? (
											<span className="bs-badge bs-badge-success">
												{ __( 'Aktiv', 'bs-custom-mail' ) }
											</span>
										) : (
											<span className="bs-badge bs-badge-inactive">
												{ __( 'Inaktiv', 'bs-custom-mail' ) }
											</span>
										) }
									</div>
								</div>
							</CardHeader>
							<CardBody>
								<h3 className="bs-template-title">{ template.template_name }</h3>
								<p className="bs-template-subject">{ template.subject }</p>

								<div className="bs-template-meta">
									<span className="bs-last-edited">
										<span className="dashicons dashicons-clock"></span>
										{ template.updated_at
											? formatTimeAgo( template.updated_at )
											: '-' }
									</span>
									{ Array.isArray( template.attachments ) &&
										template.attachments.length > 0 && (
										<span
											className="bs-attachment-count"
											title={ `${ template.attachments.length } Anhänge` }
										>
											<span className="dashicons dashicons-paperclip"></span>
											{ template.attachments.length }
										</span>
									) }
								</div>

								<div className="bs-template-actions">
									<Button
										variant="primary"
										onClick={ () =>
											onNavigate( 'edit', template.template_key )
										}
										icon={ edit }
									>
										{ __( 'Bearbeiten', 'bs-custom-mail' ) }
									</Button>
									<Button
										variant="tertiary"
										isDestructive
										onClick={ () => handleDelete( template ) }
										icon={ trash }
										label={ __( 'Löschen', 'bs-custom-mail' ) }
									/>
								</div>
							</CardBody>
						</Card>
					) ) }
				</div>
			) }

			<div className="bs-info-section">
				<h3>
					<span className="dashicons dashicons-info"></span>{ ' ' }
					{ __( 'So funktioniert es', 'bs-custom-mail' ) }
				</h3>
				<div className="bs-steps">
					<div className="bs-step">
						<div className="bs-step-number">1</div>
						<div className="bs-step-content">
							<h4>{ __( 'Template bearbeiten', 'bs-custom-mail' ) }</h4>
							<p>
								{ __(
									'Passe Betreff, Header, Inhalt und Footer an. Verwende Platzhalter für dynamische Inhalte.',
									'bs-custom-mail'
								) }
							</p>
						</div>
					</div>
					<div className="bs-step">
						<div className="bs-step-number">2</div>
						<div className="bs-step-content">
							<h4>{ __( 'Produkt zuordnen', 'bs-custom-mail' ) }</h4>
							<p>
								{ __(
									'Gehe zu Produkte → Produkt bearbeiten → Tab "E-Mail Template". Wähle das Template und aktiviere es.',
									'bs-custom-mail'
								) }
							</p>
						</div>
					</div>
					<div className="bs-step">
						<div className="bs-step-number">3</div>
						<div className="bs-step-content">
							<h4>{ __( 'Automatischer Versand', 'bs-custom-mail' ) }</h4>
							<p>
								{ __(
									'Bei Bestellungen mit dem Status "In Bearbeitung" wird die E-Mail automatisch mit Anhängen versendet.',
									'bs-custom-mail'
								) }
							</p>
						</div>
					</div>
				</div>
			</div>
		</div>
	);
}
