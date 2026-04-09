/**
 * Voucher List - Modern Black/White Design
 */
import { useState, useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import { Voucher, VoucherStats, ViewType } from '../types';

interface Props {
	onNavigate: ( view: ViewType ) => void;
}

export function VoucherList( { onNavigate }: Props ) {
	const [ vouchers, setVouchers ] = useState<Voucher[]>( [] );
	const [ stats, setStats ] = useState<VoucherStats | null>( null );
	const [ loading, setLoading ] = useState( true );
	const [ searchTerm, setSearchTerm ] = useState( '' );
	const [ statusFilter, setStatusFilter ] = useState<string>( '' );

	const fetchData = async () => {
		try {
			setLoading( true );
			const [ vouchersResponse, statsResponse ] = await Promise.all( [
				apiFetch( { path: 'bs-custom-mail/v1/vouchers' } ),
				apiFetch( { path: 'bs-custom-mail/v1/vouchers/stats' } ),
			] );
			setVouchers( ( vouchersResponse as { vouchers: Voucher[] } ).vouchers || [] );
			setStats( statsResponse as VoucherStats );
		} catch ( error ) {
			console.error( 'Error fetching vouchers:', error );
		} finally {
			setLoading( false );
		}
	};

	useEffect( () => {
		fetchData();
	}, [] );

	const cancelVoucher = async ( voucherId: number ) => {
		if ( ! window.confirm( __( 'Gutschein wirklich stornieren?', 'bs-custom-mail' ) ) ) {
			return;
		}
		try {
			await apiFetch( {
				path: `bs-custom-mail/v1/vouchers/${ voucherId }`,
				method: 'PUT',
				data: { status: 'cancelled' },
			} );
			fetchData();
		} catch ( error ) {
			console.error( 'Error cancelling voucher:', error );
			alert( __( 'Fehler beim Stornieren', 'bs-custom-mail' ) );
		}
	};

	const formatDate = ( dateString: string ) => {
		if ( ! dateString ) return '-';
		return new Date( dateString ).toLocaleDateString( 'de-DE' );
	};

	const formatCurrency = ( value: number ) => {
		return new Intl.NumberFormat( 'de-DE', {
			style: 'currency',
			currency: 'EUR',
		} ).format( value );
	};

	const getStatusColor = ( status: string ) => {
		switch ( status ) {
			case 'active':
				return '#000';
			case 'used':
				return '#10b981';
			case 'cancelled':
				return '#ef4444';
			default:
				return '#6b7280';
		}
	};

	const getStatusLabel = ( status: string ) => {
		switch ( status ) {
			case 'active':
				return __( 'Aktiv', 'bs-custom-mail' );
			case 'used':
				return __( 'Eingelöst', 'bs-custom-mail' );
			case 'cancelled':
				return __( 'Storniert', 'bs-custom-mail' );
			default:
				return status;
		}
	};

	const filteredVouchers = vouchers.filter( ( voucher ) => {
		const matchesSearch =
			voucher.voucher_code.toLowerCase().includes( searchTerm.toLowerCase() ) ||
			voucher.recipient_email.toLowerCase().includes( searchTerm.toLowerCase() ) ||
			voucher.recipient_name.toLowerCase().includes( searchTerm.toLowerCase() );
		const matchesStatus = ! statusFilter || voucher.status === statusFilter;
		return matchesSearch && matchesStatus;
	} );

	if ( loading ) {
		return (
			<div style={ { display: 'flex', justifyContent: 'center', padding: '64px' } }>
				<div style={ {
					width: '40px',
					height: '40px',
					border: '3px solid #e5e7eb',
					borderTop: '3px solid #000',
					borderRadius: '50%',
					animation: 'spin 1s linear infinite',
				} } />
				<style>{`@keyframes spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }`}</style>
			</div>
		);
	}

	return (
		<div style={ { display: 'flex', flexDirection: 'column', gap: '24px' } }>
			{ stats && (
				<div style={ { display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(200px, 1fr))', gap: '16px' } }>
					<div style={ { background: '#fff', borderRadius: '12px', padding: '20px', border: '1px solid #e5e7eb' } }>
						<div style={ { fontSize: '13px', color: '#6b7280', marginBottom: '8px' } }>{ __( 'Gesamt', 'bs-custom-mail' ) }</div>
						<div style={ { fontSize: '28px', fontWeight: 700, color: '#000' } }>{ stats.total }</div>
					</div>
					<div style={ { background: '#fff', borderRadius: '12px', padding: '20px', border: '1px solid #e5e7eb' } }>
						<div style={ { fontSize: '13px', color: '#6b7280', marginBottom: '8px' } }>{ __( 'Aktiv', 'bs-custom-mail' ) }</div>
						<div style={ { fontSize: '28px', fontWeight: 700, color: '#000' } }>{ stats.active }</div>
					</div>
					<div style={ { background: '#fff', borderRadius: '12px', padding: '20px', border: '1px solid #e5e7eb' } }>
						<div style={ { fontSize: '13px', color: '#6b7280', marginBottom: '8px' } }>{ __( 'Eingelöst', 'bs-custom-mail' ) }</div>
						<div style={ { fontSize: '28px', fontWeight: 700, color: '#10b981' } }>{ stats.used }</div>
					</div>
					<div style={ { background: '#fff', borderRadius: '12px', padding: '20px', border: '1px solid #e5e7eb' } }>
						<div style={ { fontSize: '13px', color: '#6b7280', marginBottom: '8px' } }>{ __( 'Storniert', 'bs-custom-mail' ) }</div>
						<div style={ { fontSize: '28px', fontWeight: 700, color: '#ef4444' } }>{ stats.cancelled }</div>
					</div>
					<div style={ { background: '#fff', borderRadius: '12px', padding: '20px', border: '1px solid #e5e7eb' } }>
						<div style={ { fontSize: '13px', color: '#6b7280', marginBottom: '8px' } }>{ __( 'Gesamtwert', 'bs-custom-mail' ) }</div>
						<div style={ { fontSize: '28px', fontWeight: 700, color: '#000' } }>{ formatCurrency( stats.total_value ) }</div>
					</div>
				</div>
			) }

			<div style={ { display: 'flex', justifyContent: 'space-between', alignItems: 'center', flexWrap: 'wrap', gap: '16px' } }>
				<div style={ { display: 'flex', gap: '12px', flexWrap: 'wrap' } }>
					<input
						type="text"
						placeholder={ __( 'Suche...', 'bs-custom-mail' ) }
						value={ searchTerm }
						onChange={ ( e ) => setSearchTerm( e.target.value ) }
						style={ {
							padding: '10px 16px',
							border: '1px solid #e5e7eb',
							borderRadius: '8px',
							fontSize: '14px',
							minWidth: '250px',
						} }
					/>
					<select
						value={ statusFilter }
						onChange={ ( e ) => setStatusFilter( e.target.value ) }
						style={ {
							padding: '10px 16px',
							border: '1px solid #e5e7eb',
							borderRadius: '8px',
							fontSize: '14px',
							background: '#fff',
						} }
					>
						<option value="">{ __( 'Alle Status', 'bs-custom-mail' ) }</option>
						<option value="active">{ __( 'Aktiv', 'bs-custom-mail' ) }</option>
						<option value="used">{ __( 'Eingelöst', 'bs-custom-mail' ) }</option>
						<option value="cancelled">{ __( 'Storniert', 'bs-custom-mail' ) }</option>
					</select>
				</div>
				<button
					onClick={ () => onNavigate( 'pdf-templates' ) }
					style={ {
						padding: '12px 20px',
						background: '#000',
						color: '#fff',
						border: 'none',
						borderRadius: '10px',
						cursor: 'pointer',
						fontWeight: 600,
						fontSize: '14px',
					} }
				>
					{ __( 'PDF Templates', 'bs-custom-mail' ) }
				</button>
			</div>

			<div style={ { background: '#fff', borderRadius: '16px', border: '1px solid #e5e7eb', overflow: 'hidden' } }>
				{ filteredVouchers.length === 0 ? (
					<div style={ { padding: '64px 32px', textAlign: 'center' } }>
						<div style={ { fontSize: '48px', marginBottom: '16px' } }>🎁</div>
						<h3 style={ { fontSize: '18px', fontWeight: 600, color: '#000', marginBottom: '8px' } }>
							{ __( 'Keine Gutscheine gefunden', 'bs-custom-mail' ) }
						</h3>
						<p style={ { fontSize: '14px', color: '#6b7280' } }>
							{ __( 'Es wurden noch keine Gutscheine erstellt.', 'bs-custom-mail' ) }
						</p>
					</div>
				) : (
					<div style={ { overflowX: 'auto' } }>
						<table style={ { width: '100%', borderCollapse: 'collapse' } }>
							<thead>
								<tr style={ { background: '#fafafa', borderBottom: '1px solid #e5e7eb' } }>
									<th style={ { padding: '16px', textAlign: 'left', fontSize: '13px', fontWeight: 600, color: '#374151', textTransform: 'uppercase', letterSpacing: '0.5px' } }>
										{ __( 'Code', 'bs-custom-mail' ) }
									</th>
									<th style={ { padding: '16px', textAlign: 'left', fontSize: '13px', fontWeight: 600, color: '#374151', textTransform: 'uppercase', letterSpacing: '0.5px' } }>
										{ __( 'Wert', 'bs-custom-mail' ) }
									</th>
									<th style={ { padding: '16px', textAlign: 'left', fontSize: '13px', fontWeight: 600, color: '#374151', textTransform: 'uppercase', letterSpacing: '0.5px' } }>
										{ __( 'Empfänger', 'bs-custom-mail' ) }
									</th>
									<th style={ { padding: '16px', textAlign: 'left', fontSize: '13px', fontWeight: 600, color: '#374151', textTransform: 'uppercase', letterSpacing: '0.5px' } }>
										{ __( 'Status', 'bs-custom-mail' ) }
									</th>
									<th style={ { padding: '16px', textAlign: 'left', fontSize: '13px', fontWeight: 600, color: '#374151', textTransform: 'uppercase', letterSpacing: '0.5px' } }>
										{ __( 'Bestellung', 'bs-custom-mail' ) }
									</th>
									<th style={ { padding: '16px', textAlign: 'left', fontSize: '13px', fontWeight: 600, color: '#374151', textTransform: 'uppercase', letterSpacing: '0.5px' } }>
										{ __( 'Ablauf', 'bs-custom-mail' ) }
									</th>
									<th style={ { padding: '16px', textAlign: 'right', fontSize: '13px', fontWeight: 600, color: '#374151', textTransform: 'uppercase', letterSpacing: '0.5px' } }>
										{ __( 'Aktionen', 'bs-custom-mail' ) }
									</th>
								</tr>
							</thead>
							<tbody>
								{ filteredVouchers.map( ( voucher ) => (
									<tr
										key={ voucher.id }
										style={ { borderBottom: '1px solid #f3f4f6' } }
										onMouseEnter={ ( e ) => {
											e.currentTarget.style.background = '#fafafa';
										} }
										onMouseLeave={ ( e ) => {
											e.currentTarget.style.background = 'transparent';
										} }
									>
										<td style={ { padding: '16px' } }>
											<code style={ { background: '#f3f4f6', padding: '4px 8px', borderRadius: '4px', fontSize: '13px', fontWeight: 600 } }>
												{ voucher.voucher_code }
											</code>
										</td>
										<td style={ { padding: '16px', fontWeight: 600 } }>
											{ formatCurrency( voucher.voucher_value ) }
										</td>
										<td style={ { padding: '16px' } }>
											<div>{ voucher.recipient_name }</div>
											<div style={ { fontSize: '13px', color: '#6b7280' } }>{ voucher.recipient_email }</div>
										</td>
										<td style={ { padding: '16px' } }>
											<span style={ {
												display: 'inline-flex',
												padding: '4px 12px',
												borderRadius: '20px',
												fontSize: '12px',
												fontWeight: 600,
												background: `${ getStatusColor( voucher.status ) }15`,
												color: getStatusColor( voucher.status ),
											} }>
												{ getStatusLabel( voucher.status ) }
											</span>
										</td>
										<td style={ { padding: '16px', fontSize: '13px', color: '#6b7280' } }>
											#{ voucher.order_id }
										</td>
										<td style={ { padding: '16px', fontSize: '13px', color: '#6b7280' } }>
											{ formatDate( voucher.expiry_date ) }
										</td>
										<td style={ { padding: '16px', textAlign: 'right' } }>
											{ voucher.status === 'active' && (
												<button
													onClick={ () => cancelVoucher( voucher.id ) }
													style={ {
														padding: '6px 12px',
														background: '#fef2f2',
														color: '#dc2626',
														border: '1px solid #fecaca',
														borderRadius: '6px',
														cursor: 'pointer',
														fontSize: '12px',
														fontWeight: 500,
													} }
												>
													{ __( 'Stornieren', 'bs-custom-mail' ) }
												</button>
											) }
										</td>
									</tr>
								) ) }
							</tbody>
						</table>
					</div>
				) }
			</div>
		</div>
	);
}
