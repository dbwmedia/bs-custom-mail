/**
 * Notices component
 */
import { Notice } from '@wordpress/components';
import { Notice as NoticeType } from '../types';

interface NoticesProps {
	notices: NoticeType[];
	onRemove: ( id: string ) => void;
}

export function Notices( { notices, onRemove }: NoticesProps ) {
	if ( notices.length === 0 ) {
		return null;
	}

	return (
		<div style={ { marginBottom: '20px' } }>
			{ notices.map( ( notice ) => (
				<Notice
					key={ notice.id }
					status={ notice.status }
					isDismissible
					onDismiss={ () => onRemove( notice.id ) }
				>
					{ notice.message }
				</Notice>
			) ) }
		</div>
	);
}
