<?php

/**
 * PDF Generator for vouchers.
 *
 * @link       https://jltzbrg.com
 * @since      2.0.0
 *
 * @package    Bs_Custom_Mail
 * @subpackage Bs_Custom_Mail/includes
 */

/**
 * PDF Generator class.
 *
 * Generates PDF vouchers using FPDF library.
 *
 * @since      2.0.0
 * @package    Bs_Custom_Mail
 * @subpackage Bs_Custom_Mail/includes
 * @author     Julio Litzenberg <jltbrg@gmail.com>
 */
class Bs_Custom_Mail_PDF_Generator {

	/**
	 * Upload directory.
	 *
	 * @since    2.0.0
	 * @access   private
	 * @var      string
	 */
	private $upload_dir;

	/**
	 * Upload URL.
	 *
	 * @since    2.0.0
	 * @access   private
	 * @var      string
	 */
	private $upload_url;

	/**
	 * Constructor.
	 *
	 * @since    2.0.0
	 */
	public function __construct() {
		$wp_upload = wp_upload_dir();
		$this->upload_dir = $wp_upload['basedir'] . '/bs-vouchers/';
		$this->upload_url = $wp_upload['baseurl'] . '/bs-vouchers/';

		// Ensure directory exists
		if ( ! file_exists( $this->upload_dir ) ) {
			wp_mkdir_p( $this->upload_dir );
		}
	}

	/**
	 * Generate PDF voucher.
	 *
	 * @since    2.0.0
	 * @param    string    $template_path    Path to PDF template.
	 * @param    string    $template_json    JSON configuration.
	 * @param    array     $data             Voucher data.
	 * @return   array|false
	 */
	public function generate( $template_path, $template_json, $data ) {
		// Load FPDF
		if ( ! class_exists( 'FPDF' ) ) {
			$fpdf_path = plugin_dir_path( __FILE__ ) . '../vendor/fpdf/fpdf.php';
			if ( ! file_exists( $fpdf_path ) ) {
				// Fallback: try to use TCPDF or other library
				return $this->generate_with_tcpdf( $template_path, $template_json, $data );
			}
			require_once $fpdf_path;
		}

		// Parse template configuration
		$positions = json_decode( $template_json, true );
		if ( ! is_array( $positions ) ) {
			$positions = array();
		}

		// Default positions
		$defaults = array(
			'wert' => array( 'x' => 105, 'y' => 100 ),
			'code' => array( 'x' => 105, 'y' => 130 ),
			'name' => array( 'x' => 105, 'y' => 160 ),
			'expiry' => array( 'x' => 105, 'y' => 190 )
		);

		$positions = wp_parse_args( $positions, $defaults );

		// Create PDF
		$pdf = new FPDF( 'P', 'mm', 'A4' );
		$pdf->AddPage();

		// Add template background
		if ( file_exists( $template_path ) ) {
			$file_info = wp_check_filetype( basename( $template_path ), array(
				'pdf' => 'application/pdf',
				'jpg' => 'image/jpeg',
				'jpeg' => 'image/jpeg',
				'png' => 'image/png'
			) );

			if ( in_array( $file_info['ext'], array( 'jpg', 'jpeg', 'png' ) ) ) {
				// Image template
				$pdf->Image( $template_path, 0, 0, 210 );
			} else {
				// PDF template - white background
				$pdf->SetFillColor( 255, 255, 255 );
				$pdf->Rect( 0, 0, 210, 297, 'F' );
			}
		}

		$font_size = isset( $data['font_size'] ) ? intval( $data['font_size'] ) : 16;

		// Add value
		if ( isset( $positions['wert'] ) && isset( $data['wert'] ) ) {
			$pdf->SetFont( 'Arial', 'B', $font_size + 4 );
			$pdf->SetTextColor( 5, 150, 105 );
			$pdf->SetXY( $positions['wert']['x'], $positions['wert']['y'] );
			$pdf->Cell( 0, 10, $this->format_price( $data['wert'] ), 0, 0, 'C' );
		}

		// Add code
		if ( isset( $positions['code'] ) && isset( $data['code'] ) ) {
			$pdf->SetFont( 'Courier', 'B', $font_size + 2 );
			$pdf->SetTextColor( 30, 58, 138 );
			$pdf->SetXY( $positions['code']['x'], $positions['code']['y'] );
			$pdf->Cell( 0, 10, strtoupper( $data['code'] ), 0, 0, 'C' );
		}

		// Add name
		if ( isset( $positions['name'] ) && ! empty( $data['name'] ) ) {
			$pdf->SetFont( 'Arial', '', $font_size );
			$pdf->SetTextColor( 55, 65, 81 );
			$pdf->SetXY( $positions['name']['x'], $positions['name']['y'] );
			$pdf->Cell( 0, 10, $this->sanitize_text( $data['name'] ), 0, 0, 'C' );
		}

		// Add expiry date
		if ( isset( $positions['expiry'] ) && isset( $data['expiry'] ) ) {
			$pdf->SetFont( 'Arial', '', $font_size - 2 );
			$pdf->SetTextColor( 107, 114, 128 );
			$pdf->SetXY( $positions['expiry']['x'], $positions['expiry']['y'] );
			$pdf->Cell( 0, 10, __( 'Gueltig bis:', 'bs-custom-mail' ) . ' ' . $data['expiry'], 0, 0, 'C' );
		}

		// Footer
		$pdf->SetFont( 'Arial', '', 8 );
		$pdf->SetTextColor( 156, 163, 175 );
		$pdf->SetY( 280 );
		$pdf->Cell( 0, 10, 'Bootsschule Berlin Koepenick - Gruenauer Strasse 3, 12557 Berlin', 0, 0, 'C' );

		// Save PDF
		$filename = 'gutschein-' . sanitize_file_name( $data['code'] ) . '-' . time() . '.pdf';
		$filepath = $this->upload_dir . $filename;
		$fileurl = $this->upload_url . $filename;

		$pdf->Output( 'F', $filepath );

		if ( file_exists( $filepath ) ) {
			return array(
				'path' => $filepath,
				'url' => $fileurl,
				'filename' => $filename
			);
		}

		return false;
	}

	/**
	 * Generate PDF using TCPDF (fallback).
	 *
	 * @since    2.0.0
	 * @param    string    $template_path    Path to PDF template.
	 * @param    string    $template_json    JSON configuration.
	 * @param    array     $data             Voucher data.
	 * @return   array|false
	 */
	private function generate_with_tcpdf( $template_path, $template_json, $data ) {
		// This is a placeholder for TCPDF implementation
		// You would need to include TCPDF library
		return false;
	}

	/**
	 * Format price for PDF.
	 *
	 * @since    2.0.0
	 * @param    float     $value    Price value.
	 * @return   string
	 */
	private function format_price( $value ) {
		return number_format( $value, 2, ',', '.' ) . ' EUR';
	}

	/**
	 * Sanitize text for PDF.
	 *
	 * @since    2.0.0
	 * @param    string    $text    Text to sanitize.
	 * @return   string
	 */
	private function sanitize_text( $text ) {
		$text = sanitize_text_field( $text );

		// Replace German umlauts for FPDF
		$search = array( 'Ä', 'Ö', 'Ü', 'ä', 'ö', 'ü', 'ß' );
		$replace = array( 'Ae', 'Oe', 'Ue', 'ae', 'oe', 'ue', 'ss' );
		$text = str_replace( $search, $replace, $text );

		// Convert to Latin-1
		$text = iconv( 'UTF-8', 'ISO-8859-1//TRANSLIT//IGNORE', $text );

		return $text;
	}

	/**
	 * Cleanup old PDFs.
	 *
	 * @since    2.0.0
	 * @param    int    $max_age_days    Maximum age in days.
	 */
	public function cleanup_old_pdfs( $max_age_days = 7 ) {
		if ( ! file_exists( $this->upload_dir ) ) {
			return;
		}

		$files = glob( $this->upload_dir . '*.pdf' );
		$now = time();
		$max_age = $max_age_days * 24 * 60 * 60;

		foreach ( $files as $file ) {
			if ( is_file( $file ) && ( $now - filemtime( $file ) ) > $max_age ) {
				@unlink( $file );
			}
		}
	}
}
