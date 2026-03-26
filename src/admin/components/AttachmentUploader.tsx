/**
 * Attachment uploader component
 */
import { Button } from '@wordpress/components';
// @ts-expect-error - @wordpress/media-utils has no TypeScript definitions
import { MediaUpload, MediaUploadCheck } from '@wordpress/media-utils';
import { __ } from '@wordpress/i18n';
import { Attachment } from '../types';

interface AttachmentUploaderProps {
	attachments: Attachment[];
	onChange: ( attachments: Attachment[] ) => void;
}

interface MediaItem {
	id: number;
	filename?: string;
	title?: string;
	filesizeHumanReadable?: string;
	icon?: string;
	url?: string;
}

export function AttachmentUploader( { attachments, onChange }: AttachmentUploaderProps ) {
	const removeAttachment = ( id: number ) => {
		onChange( attachments.filter( ( a ) => a.id !== id ) );
	};

	const addAttachments = ( media: MediaItem[] ) => {
		const newAttachments: Attachment[] = media.map( ( item ) => ( {
			id: item.id,
			name: item.filename || item.title || '',
			extension: item.filename?.split( '.' ).pop()?.toUpperCase() || '',
			size: item.filesizeHumanReadable || '-',
			icon: item.icon || item.url || '',
		} ) );

		// Filter out duplicates
		const existingIds = attachments.map( ( a ) => a.id );
		const uniqueNewAttachments = newAttachments.filter(
			( a ) => ! existingIds.includes( a.id )
		);

		onChange( [ ...attachments, ...uniqueNewAttachments ] );
	};

	return (
		<div className="bs-attachments-section">
			<MediaUploadCheck>
				<MediaUpload
					onSelect={ addAttachments }
					allowedTypes={ [
						'application/pdf',
						'application/msword',
						'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
						'application/vnd.ms-excel',
						'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
						'image/*',
					] }
					multiple
					render={ ( { open }: { open: () => void } ) => (
						<Button variant="secondary" onClick={ open }>
							{ __( 'Dateien hinzufügen', 'bs-custom-mail' ) }
						</Button>
					) }
				/>
			</MediaUploadCheck>

			{ attachments.length > 0 && (
				<div className="bs-attachments-list">
					{ attachments.map( ( attachment ) => (
						<div key={ attachment.id } className="bs-attachment-item">
							<img
								src={ attachment.icon }
								alt=""
								className="bs-attachment-icon-file"
							/>
							<div className="bs-attachment-info">
								<span className="bs-attachment-name">{ attachment.name }</span>
								<span className="bs-attachment-meta">
									{ attachment.extension } • { attachment.size }
								</span>
							</div>
							<Button
								variant="tertiary"
								isDestructive
								onClick={ () => removeAttachment( attachment.id ) }
							>
								×
							</Button>
						</div>
					) ) }
				</div>
			) }

			{ attachments.length === 0 && (
				<p className="bs-no-attachments">
					{ __( 'Noch keine Anhänge vorhanden.', 'bs-custom-mail' ) }
				</p>
			) }
		</div>
	);
}
