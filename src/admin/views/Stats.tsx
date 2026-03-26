/**
 * Statistics view
 */
import { __ } from '@wordpress/i18n';
import {
	Card,
	CardHeader,
	CardBody,
	Spinner,
} from '@wordpress/components';
import { useStats } from '../hooks';

/**
 * Progress bar component
 */
function ProgressBar( { value }: { value: number } ) {
	return (
		<div className="bs-progress-bar">
			<div
				className="bs-progress-bar__fill"
				style={ { width: `${ value }%` } }
			/>
		</div>
	);
}

export function Stats() {
	const { stats, recentActivity, isLoading } = useStats();

	if ( isLoading || ! stats ) {
		return (
			<div className="bs-loading">
				<Spinner />
				<p>{ __( 'Lade Statistiken...', 'bs-custom-mail' ) }</p>
			</div>
		);
	}

	const formatDate = ( dateString: string ) => {
		const date = new Date( dateString );
		return date.toLocaleString();
	};

	const getStatusLabel = ( status: string ) => {
		switch ( status ) {
			case 'sent':
				return <span className="bs-status-success">{ __( 'Gesendet', 'bs-custom-mail' ) }</span>;
			case 'failed':
				return <span className="bs-status-error">{ __( 'Fehlgeschlagen', 'bs-custom-mail' ) }</span>;
			default:
				return <span className="bs-status-warning">{ __( 'Nicht gefunden', 'bs-custom-mail' ) }</span>;
		}
	};

	return (
		<div className="bs-stats">
			<h2>{ __( 'Statistik', 'bs-custom-mail' ) }</h2>

			<div className="bs-stats-overview">
				<Card className="bs-stat-card">
					<CardBody>
						<div className="bs-stat-value">{ stats.total_sent }</div>
						<div className="bs-stat-label">{ __( 'Gesendet', 'bs-custom-mail' ) }</div>
					</CardBody>
				</Card>
				<Card className="bs-stat-card">
					<CardBody>
						<div className="bs-stat-value">{ stats.total_failed }</div>
						<div className="bs-stat-label">{ __( 'Fehlgeschlagen', 'bs-custom-mail' ) }</div>
					</CardBody>
				</Card>
				<Card className="bs-stat-card">
					<CardBody>
						<div className="bs-stat-value">{ stats.total_emails }</div>
						<div className="bs-stat-label">{ __( 'Gesamt', 'bs-custom-mail' ) }</div>
					</CardBody>
				</Card>
				<Card className="bs-stat-card">
					<CardBody>
						<div className="bs-stat-value">{ stats.success_rate }%</div>
						<div className="bs-stat-label">{ __( 'Erfolgsrate', 'bs-custom-mail' ) }</div>
					</CardBody>
				</Card>
			</div>

			<div className="bs-stats-grid">
				<Card className="bs-template-stats">
					<CardHeader>
						<h3>{ __( 'Template-Statistik', 'bs-custom-mail' ) }</h3>
					</CardHeader>
					<CardBody>
						{ stats.template_stats.length === 0 ? (
							<p>{ __( 'Noch keine Daten verfügbar.', 'bs-custom-mail' ) }</p>
						) : (
							<div className="bs-template-stats-list">
								{ stats.template_stats.map( ( stat ) => {
									const total = stat.sent_count + stat.failed_count;
									const rate = total > 0 ? Math.round( ( stat.sent_count / total ) * 100 ) : 0;

									return (
										<div key={ stat.template_key } className="bs-template-stat-item">
											<div className="bs-template-stat-header">
												<span className="bs-template-stat-name">
													{ stat.template_name }
												</span>
												<span className="bs-template-stat-rate">
													{ rate }% ({ stat.sent_count }/{ total })
												</span>
											</div>
											<ProgressBar value={ rate } />
										</div>
									);
								} ) }
							</div>
						) }
					</CardBody>
				</Card>

				<Card className="bs-recent-activity">
					<CardHeader>
						<h3>{ __( 'Letzte Aktivität', 'bs-custom-mail' ) }</h3>
					</CardHeader>
					<CardBody>
						{ recentActivity.length === 0 ? (
							<p>{ __( 'Noch keine Aktivität vorhanden.', 'bs-custom-mail' ) }</p>
						) : (
							<div className="bs-activity-list">
								{ recentActivity.slice( 0, 10 ).map( ( activity ) => (
									<div key={ activity.id } className="bs-activity-item">
										<div className="bs-activity-header">
											<span className="bs-activity-product">
												{ activity.product_name }
											</span>
											{ getStatusLabel( activity.status ) }
										</div>
										<div className="bs-activity-meta">
											<span>{ activity.customer_email }</span>
											<span>{ formatDate( activity.sent_at ) }</span>
										</div>
									</div>
								) ) }
							</div>
						) }
					</CardBody>
				</Card>
			</div>
		</div>
	);
}
