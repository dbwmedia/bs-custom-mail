/**
	 * Build attachments section for email body.
	 *
	 * @since    1.0.0
	 * @param    array    $attachments    Array of attachment data.
	 * @return   string                   HTML for attachments section.
	 */
	private function build_attachments_section( $attachments ) {
		$html = '
		<div style="margin-top: 32px; padding: 24px; background: #f9f9f9; border-radius: 12px; border: 1px solid #eee;">
			<div style="display: flex; align-items: center; gap: 12px; margin-bottom: 16px;">
				<div style="width: 40px; height: 40px; background: #000; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 20px;">📎</div>
				<div>
					<h3 style="margin: 0; font-size: 16px; font-weight: 600; color: #000;">
						' . esc_html__( 'Dokumente', 'bs-custom-mail' ) . '
					</h3>
					<p style="margin: 2px 0 0; font-size: 13px; color: #666;">
						' . esc_html__( 'Im Anhang dieser E-Mail', 'bs-custom-mail' ) . '
					</p>
				</div>
			</div>
			<div style="display: flex; flex-direction: column; gap: 8px;">';

		foreach ( $attachments as $attachment ) {
			$file_size = size_format( filesize( $attachment['path'] ) );
			$file_icon = $this->get_file_icon( $attachment['type'] );
			
			$html .= '
			<div style="display: flex; align-items: center; padding: 16px; background: #fff; border: 1px solid #e5e7eb; border-radius: 10px;">
				<div style="width: 44px; height: 44px; margin-right: 16px; background: #f5f5f5; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 22px;">
					' . $file_icon . '
				</div>
				<div style="flex: 1;">
					<div style="font-weight: 600; font-size: 14px; color: #111827; margin-bottom: 4px;">
						' . esc_html( $attachment['name'] ) . '
					</div>
					<div style="font-size: 12px; color: #6b7280; text-transform: uppercase; letter-spacing: 0.5px;">
						' . esc_html( strtoupper( $attachment['extension'] ) ) . ' • ' . esc_html( $file_size ) . '
					</div>
				</div>
			</div>';
		}

		$html .= '
		</div>
	</div>';

		return $html;
	}
