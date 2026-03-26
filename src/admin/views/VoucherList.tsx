/**
 * Voucher List View Component
 */
import { useState, useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import { Voucher, VoucherStats } from '../types';
import { useNotices } from '../hooks';

interface VoucherListProps {
	onNavigate: ( view: 'vouchers' | 'pdf-templates' ) => void;
}

export function VoucherList( { onNavigate }: VoucherListProps ) {
	const [ vouchers, setVouchers ] = useState< Voucher[] >( [] );
	const [ stats, setStats ] = useState< VoucherStats | null >( null );
	const [ loading, setLoading ] = useState( true );
	const [ searchTerm, setSearchTerm ] = useState( '' );
	const [ statusFilter, setStatusFilter ] = useState( '' );
	const [ page, setPage ] = useState( 1 );
	const [ totalPages, setTotalPages ] = useState( 1 );
	const { notices, addNotice, removeNotice } = useNotices();

	const perPage = 20;

	useEffect( () => {
		loadVouchers();
		loadStats();
	}, [ page, statusFilter, searchTerm ] );

	const loadVouchers = async () => {
		try {
			setLoading( true );
			const response = await apiFetch< {
				vouchers: Voucher[];
				total: number;
				total_pages: number;
			} >( {
				path: `/bs-custom-mail/v1/vouchers?page=${ page }&per_page=${ perPage }&status=${ statusFilter }&search=${ searchTerm }`,
			} );
			setVouchers( response.vouchers );
			setTotalPages( response.total_pages );
		} catch ( error ) {
			addNotice( 'error', __( 'Fehler beim Laden der Gutscheine.', 'bs-custom-mail' ) );
		} finally {
			setLoading( false );
		}
	};

	const loadStats = async () => {
		try {
			const response = await apiFetch< VoucherStats >( {
				path: '/bs-custom-mail/v1/vouchers/stats',
			} );
			setStats( response );
		} catch ( error ) {
			console.error( 'Failed to load voucher stats:', error );
		}
	};

	const handleStatusChange = async ( voucherId: number, newStatus: string ) => {
		try {
			await apiFetch( {
				path: `/bs-custom-mail/v1/vouchers/${ voucherId }`,
				method: 'POST',
				data: { status: newStatus },
			} );
			addNotice( 'success', __( 'Gutschein-Status aktualisiert.', 'bs-custom-mail' ) );
			loadVouchers();
			loadStats();
		} catch ( error ) {
			addNotice( 'error', __( 'Fehler beim Aktualisieren.', 'bs-custom-mail' ) );
		}
	};

	const getStatusBadge = ( status: string ) => {
		const statusConfig: Record< string, { class: string; label: string } > = {
			active: { class: 'status-badge status-active', label: __( 'Aktiv', 'bs-custom-mail' ) },
			used: { class: 'status-badge status-used', label: __( 'Eingelöst', 'bs-custom-mail' ) },
			cancelled: { class: 'status-badge status-cancelled', label: __( 'Storniert', 'bs-custom-mail' ) },
		};
		const config = statusConfig[ status ] || { class: 'status-badge', label: status };
		return <span className={ config.class }>{ config.label }</span>;
	};

	const formatPrice = ( value: number ) => {
		return new Intl.NumberFormat( 'de-DE', {
			style: 'currency',
			currency: 'EUR',
		} ).format( value );
	};

	const formatDate = ( dateString: string ) => {
		if ( ! dateString ) return '-';
		return new Date( dateString ).toLocaleDateString( 'de-DE' );
	};

	return (
		<div className="bs-voucher-list">
			{ /* Header */ }
			<div className="bs-page-header">
				<h2>{ __( 'Gutscheine', 'bs-custom-mail' ) }</h2>
				<div className="bs-page-actions">
					<button
						className="button"
						onClick={ () => onNavigate( 'pdf-templates' ) }
					>
						{ __( '📄 PDF Vorlagen', 'bs-custom-mail' ) }
					</button>
				</div>
			</div>

			{ /* Stats Cards */ }
			{ stats && (
				<div className="bs-stats-grid" style={ { marginBottom: '24px' } }>
					<div className="bs-stat-card">
						<div className="stat-value">{ stats.total }</div>
						<div className="stat-label">{ __( 'Gesamt', 'bs-custom-mail' ) }</div>
					</div>
					<div className="bs-stat-card" style={ { borderLeft: '4px solid #22c55e' } }>
						<div className="stat-value" style={ { color: '#22c55e' } }>
							{ stats.active }
						</div>
						<div className="stat-label">{ __( 'Aktiv', 'bs-custom-mail' ) }</div>
					</div>
					<div className="bs-stat-card" style={ { borderLeft: '4px solid #3b82f6' } }>
						<div className="stat-value" style={ { color: '#3b82f6' } }>
							{ stats.used }
						</div>
						<div className="stat-label">{ __( 'Eingelöst', 'bs-custom-mail' ) }</div>
					</div>
					<div className="bs-stat-card" style={ { borderLeft: '4px solid #ef4444' } }>
						<div className="stat-value" style={ { color: '#ef4444' } }>
							{ stats.cancelled }
						</div>
						<div className="stat-label">{ __( 'Storniert', 'bs-custom-mail' ) }</div>
					</div>
					<div className="bs-stat-card" style={ { borderLeft: '4px solid #f59e0b' } }>
						<div className="stat-value" style={ { color: '#f59e0b' } }>
							{ formatPrice( stats.total_value ) }
						</div>
						<div className="stat-label">{ __( 'Gesamtwert', 'bs-custom-mail' ) }</div>
					</div>
				</div>
			) }

			{ /* Notices */ }
			{ notices.map( ( notice ) => (
				<div
					key={ notice.id }
					className={ `notice notice-${ notice.status } is-dismissible` }
				>
					<p>{ notice.message }</p>
					<button
						className="notice-dismiss"
						onClick={ () => removeNotice( notice.id ) }
					>
						<span className="screen-reader-text">
							{ __( 'Dismiss this notice.', 'bs-custom-mail' ) }
						</span>
					</button>
				</div>
			) ) }

			{ /* Filters */ }
			<div className="bs-filters" style={ { marginBottom: '20px', display: 'flex', gap: '12px' } }>
				<input
					type="text"
					placeholder={ __( 'Suche nach Code, E-Mail oder Name...', 'bs-custom-mail' ) }
					value={ searchTerm }
					onChange={ ( e ) => setSearchTerm( e.target.value ) }
					className="regular-text"
					style={ { maxWidth: '300px' } }
				/>
				<select
					value={ statusFilter }
					onChange={ ( e ) => setStatusFilter( e.target.value ) }
				>
					<option value="">{ __( 'Alle Status', 'bs-custom-mail' ) }</option>
					<option value="active">{ __( 'Aktiv', 'bs-custom-mail' ) }</option>
					<option value="used">{ __( 'Eingelöst', 'bs-custom-mail' ) }</option>
					<option value="cancelled">{ __( 'Storniert', 'bs-custom-mail' ) }</option>
				</select>
			</div>

			{ /* Vouchers Table */ }
			{ loading ? (
				<div className="bs-loading">{ __( 'Lade Gutscheine...', 'bs-custom-mail' ) }</div>
			) : (
				<>
					<table className="wp-list-table widefat fixed striped">
						<thead>
							<tr>
								<th>{ __( 'Code', 'bs-custom-mail' ) }</th>
								<th>{ __( 'Wert', 'bs-custom-mail' ) }</th>
								<th>{ __( 'Empfänger', 'bs-custom-mail' ) }</th>
								<th>{ __( 'Status', 'bs-custom-mail' ) }</th>
								<th>{ __( 'Erstellt', 'bs-custom-mail' ) }</th>
								<th>{ __( 'Gültig bis', 'bs-custom-mail' ) }</th>
								<th>{ __( 'Aktionen', 'bs-custom-mail' ) }</th>
							</tr>
						</thead>
						<tbody>
							{ vouchers.length === 0 ? (
								<tr>
									<td colSpan={ 7 } style={ { textAlign: 'center', padding: '40px' } }>
										{ __( 'Keine Gutscheine gefunden.', 'bs-custom-mail' ) }
									</td>
								</tr>
							) : (
								vouchers.map( ( voucher ) => (
									<tr key={ voucher.id }>
										<td>
											<code style={ { fontSize: '14px' } }>
												{ voucher.voucher_code }
											</code>
										</td>
										<td>{ formatPrice( voucher.voucher_value ) }</td>
										<td>
											{ voucher.recipient_name && (
												<div style={ { fontWeight: 500 } }>
													{ voucher.recipient_name }
												</div>
											) }
											<div style={ { fontSize: '12px', color: '#666' } }>
												{ voucher.recipient_email }
											</div>
										</td>
										<td>{ getStatusBadge( voucher.status ) }</td>
										<td>{ formatDate( voucher.created_at ) }</td>
										<td>{ formatDate( voucher.expiry_date ) }</td>
										<td>
											{ voucher.status === 'active' && (
												<button
													className="button button-small"
													onClick={ () =>
														handleStatusChange( voucher.id, 'cancelled' )
													}
												>
													{ __( 'Stornieren', 'bs-custom-mail' ) }
												</button>
											) }
											{ voucher.pdf_path && (
												<a
													href={ voucher.pdf_path }
													target="_blank"
													rel="noopener noreferrer"
													className="button button-small"
													style={ { marginLeft: '4px' } }
												>
													{ __( 'PDF', 'bs-custom-mail' ) }
												</a>
											) }
										</td>
									</tr>
								) )
							) }
						</tbody>
					</table>

					{ /* Pagination */ }
					{ totalPages > 1 && (
						<div className="bs-pagination" style={ { marginTop: '20px' } }>
							<div className="tablenav">
								<div className="tablenav-pages">
									<span className="displaying-num">
										{ totalPages } { __( 'Seiten', 'bs-custom-mail' ) }
									</span>
									<span className="pagination-links">
										<button
											className="button"
											disabled={ page === 1 }
											onClick={ () => setPage( 1 ) }
										>
											«
										</button>
										<button
											className="button"
											disabled={ page === 1 }
											onClick={ () => setPage( page - 1 ) }
										>
											‹
										</button>
										<span className="paging-input">
											{ page } { __( 'von', 'bs-custom-mail' ) } { totalPages }
										</span>
										<button
											className="button"
											disabled={ page === totalPages }
											onClick={ () => setPage( page + 1 ) }
										>
											›
										</button>
										<button
											className="button"
											disabled={ page === totalPages }
											onClick={ () => setPage( totalPages ) }
										>
											»
										</button>
									</span>
								</div>
							</div>
						</div>
					) }
				</>
			) }
		</div>
	);
}
