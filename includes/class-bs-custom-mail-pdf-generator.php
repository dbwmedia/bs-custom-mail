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
 * Supports multiple paper formats, orientations, and color backgrounds.
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
	 * Paper dimensions mapping.
	 *
	 * @since    2.1.0
	 * @access   private
	 * @var      array
	 */
	private $paper_sizes = array(
		'A4' => array( 'width' => 210, 'height' => 297 ),
		'A5' => array( 'width' => 148, 'height' => 210 ),
		'A6' => array( 'width' => 105, 'height' => 148 ),
	);

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
	 * @since    2.1.0 Added support for paper formats, orientations, and color backgrounds
	 * @param    string    $template_path    Path to PDF template (or empty for color background).
	 * @param    string    $template_json    JSON configuration.
	 * @param    array     $data             Voucher data.
	 * @param    array     $options          Additional options (paper_size, orientation, background_color, etc.).
	 * @return   array|false
	 */
	public function generate( $template_path, $template_json, $data, $options = array() ) {
		// Load FPDF
		if ( ! class_exists( 'FPDF' ) ) {
			// Try composer path first
			$fpdf_path = plugin_dir_path( __FILE__ ) . '../vendor/setasign/fpdf/fpdf.php';
			if ( ! file_exists( $fpdf_path ) ) {
				// Fallback to alternative path
				$fpdf_path = plugin_dir_path( __FILE__ ) . '../vendor/fpdf/fpdf.php';
			}
			if ( ! file_exists( $fpdf_path ) ) {
				return $this->generate_with_tcpdf( $template_path, $template_json, $data, $options );
			}
			require_once $fpdf_path;
		}

		// Parse options with defaults
		$options = wp_parse_args( $options, array(
			'paper_size'       => 'A4',
			'orientation'      => 'portrait',
			'background_color' => '#ffffff',
			'background_type'  => 'color',
			'active_fields'    => array( 'wert', 'code', 'name', 'expiry' ),
		) );

		// Parse template configuration
		$positions = json_decode( $template_json, true );
		if ( ! is_array( $positions ) ) {
			$positions = array();
		}

		// Get paper size
		$paper_size = isset( $this->paper_sizes[ $options['paper_size'] ] ) 
			? $this->paper_sizes[ $options['paper_size'] ] 
			: $this->paper_sizes['A4'];

		// Get dimensions based on orientation
		if ( $options['orientation'] === 'landscape' ) {
			$page_width = $paper_size['height'];
			$page_height = $paper_size['width'];
		} else {
			$page_width = $paper_size['width'];
			$page_height = $paper_size['height'];
		}

		// Create PDF with proper format
		$format = $options['paper_size'];
		if ( $options['orientation'] === 'landscape' ) {
			// FPDF doesn't support landscape directly for custom sizes, use array
			$format = array( $page_width, $page_height );
			$pdf = new FPDF( 'L', 'mm', $format );
		} else {
			$pdf = new FPDF( 'P', 'mm', $format );
		}

		$pdf->AddPage();

		// Add background
		if ( $options['background_type'] === 'image' && ! empty( $template_path ) && file_exists( $template_path ) ) {
			$this->add_image_background( $pdf, $template_path, $page_width, $page_height );
		} else {
			$this->add_color_background( $pdf, $options['background_color'], $page_width, $page_height );
		}

		// Get font size
		$font_size = isset( $data['font_size'] ) ? intval( $data['font_size'] ) : 16;

		// Get active fields
		$active_fields = is_array( $options['active_fields'] ) 
			? $options['active_fields'] 
			: json_decode( $options['active_fields'], true );
		if ( ! is_array( $active_fields ) ) {
			$active_fields = array( 'wert', 'code', 'name', 'expiry' );
		}

		// Render active fields
		foreach ( $active_fields as $field_key ) {
			if ( ! isset( $positions[ $field_key ] ) ) {
				continue;
			}

			$position = $positions[ $field_key ];
			$x = isset( $position['x'] ) ? floatval( $position['x'] ) : 50;
			$y = isset( $position['y'] ) ? floatval( $position['y'] ) : 50;
			$field_font_size = isset( $position['fontSize'] ) ? intval( $position['fontSize'] ) : $font_size;

			switch ( $field_key ) {
				case 'wert':
					if ( isset( $data['wert'] ) ) {
						$this->render_value_field( $pdf, $data['wert'], $x, $y, $field_font_size );
					}
					break;

				case 'code':
					if ( isset( $data['code'] ) ) {
						$this->render_code_field( $pdf, $data['code'], $x, $y, $field_font_size );
					}
					break;

				case 'name':
					if ( ! empty( $data['name'] ) ) {
						$this->render_name_field( $pdf, $data['name'], $x, $y, $field_font_size );
					}
					break;

				case 'expiry':
					if ( isset( $data['expiry'] ) ) {
						$this->render_expiry_field( $pdf, $data['expiry'], $x, $y, $field_font_size );
					}
					break;

				case 'adressant':
					if ( ! empty( $data['adressant'] ) ) {
						$this->render_text_field( $pdf, $data['adressant'], $x, $y, $field_font_size, 'L' );
					}
					break;

				case 'notiz':
					if ( ! empty( $data['notiz'] ) ) {
						$this->render_text_field( $pdf, $data['notiz'], $x, $y, $field_font_size, 'C' );
					}
					break;
			}
		}

		// Footer
		$pdf->SetFont( 'Arial', '', 8 );
		$pdf->SetTextColor( 156, 163, 175 );
		$pdf->SetY( $page_height - 17 );
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
				'filename' => $filename,
			);
		}

		return false;
	}

	/**
	 * Add image background to PDF.
	 *
	 * @since    2.1.0
	 * @param    FPDF      $pdf           PDF object.
	 * @param    string    $image_path    Path to image.
	 * @param    float     $page_width    Page width.
	 * @param    float     $page_height   Page height.
	 */
	private function add_image_background( $pdf, $image_path, $page_width, $page_height ) {
		$file_info = wp_check_filetype( basename( $image_path ), array(
			'pdf'  => 'application/pdf',
			'jpg'  => 'image/jpeg',
			'jpeg' => 'image/jpeg',
			'png'  => 'image/png',
		) );

		if ( in_array( $file_info['ext'], array( 'jpg', 'jpeg', 'png' ) ) ) {
			// Image template - scale to fit page
			$pdf->Image( $image_path, 0, 0, $page_width );
		}
	}

	/**
	 * Add color background to PDF.
	 *
	 * @since    2.1.0
	 * @param    FPDF      $pdf           PDF object.
	 * @param    string    $color         Hex color code.
	 * @param    float     $page_width    Page width.
	 * @param    float     $page_height   Page height.
	 */
	private function add_color_background( $pdf, $color, $page_width, $page_height ) {
		$rgb = $this->hex_to_rgb( $color );
		$pdf->SetFillColor( $rgb['r'], $rgb['g'], $rgb['b'] );
		$pdf->Rect( 0, 0, $page_width, $page_height, 'F' );
	}

	/**
	 * Convert hex color to RGB.
	 *
	 * @since    2.1.0
	 * @param    string    $hex    Hex color code.
	 * @return   array
	 */
	private function hex_to_rgb( $hex ) {
		$hex = ltrim( $hex, '#' );
		
		if ( strlen( $hex ) === 3 ) {
			$r = hexdec( str_repeat( substr( $hex, 0, 1 ), 2 ) );
			$g = hexdec( str_repeat( substr( $hex, 1, 1 ), 2 ) );
			$b = hexdec( str_repeat( substr( $hex, 2, 1 ), 2 ) );
		} else {
			$r = hexdec( substr( $hex, 0, 2 ) );
			$g = hexdec( substr( $hex, 2, 2 ) );
			$b = hexdec( substr( $hex, 4, 2 ) );
		}

		return array( 'r' => $r, 'g' => $g, 'b' => $b );
	}

	/**
	 * Render value field.
	 *
	 * @since    2.1.0
	 * @param    FPDF      $pdf        PDF object.
	 * @param    float     $value      Value to display.
	 * @param    float     $x          X position.
	 * @param    float     $y          Y position.
	 * @param    int       $font_size  Font size.
	 */
	private function render_value_field( $pdf, $value, $x, $y, $font_size ) {
		$pdf->SetFont( 'Arial', 'B', $font_size );
		$pdf->SetTextColor( 5, 150, 105 );
		$pdf->SetXY( $x, $y );
		$pdf->Cell( 0, 10, $this->format_price( $value ), 0, 0, 'C' );
	}

	/**
	 * Render code field.
	 *
	 * @since    2.1.0
	 * @param    FPDF      $pdf        PDF object.
	 * @param    string    $code       Code to display.
	 * @param    float     $x          X position.
	 * @param    float     $y          Y position.
	 * @param    int       $font_size  Font size.
	 */
	private function render_code_field( $pdf, $code, $x, $y, $font_size ) {
		$pdf->SetFont( 'Courier', 'B', $font_size );
		$pdf->SetTextColor( 30, 58, 138 );
		$pdf->SetXY( $x, $y );
		$pdf->Cell( 0, 10, strtoupper( $code ), 0, 0, 'C' );
	}

	/**
	 * Render name field.
	 *
	 * @since    2.1.0
	 * @param    FPDF      $pdf        PDF object.
	 * @param    string    $name       Name to display.
	 * @param    float     $x          X position.
	 * @param    float     $y          Y position.
	 * @param    int       $font_size  Font size.
	 */
	private function render_name_field( $pdf, $name, $x, $y, $font_size ) {
		$pdf->SetFont( 'Arial', '', $font_size );
		$pdf->SetTextColor( 55, 65, 81 );
		$pdf->SetXY( $x, $y );
		$pdf->Cell( 0, 10, $this->sanitize_text( $name ), 0, 0, 'C' );
	}

	/**
	 * Render expiry field.
	 *
	 * @since    2.1.0
	 * @param    FPDF      $pdf        PDF object.
	 * @param    string    $expiry     Expiry date to display.
	 * @param    float     $x          X position.
	 * @param    float     $y          Y position.
	 * @param    int       $font_size  Font size.
	 */
	private function render_expiry_field( $pdf, $expiry, $x, $y, $font_size ) {
		$pdf->SetFont( 'Arial', '', $font_size );
		$pdf->SetTextColor( 107, 114, 128 );
		$pdf->SetXY( $x, $y );
		$pdf->Cell( 0, 10, __( 'Gueltig bis:', 'bs-custom-mail' ) . ' ' . $expiry, 0, 0, 'C' );
	}

	/**
	 * Render generic text field.
	 *
	 * @since    2.1.0
	 * @param    FPDF      $pdf        PDF object.
	 * @param    string    $text       Text to display.
	 * @param    float     $x          X position.
	 * @param    float     $y          Y position.
	 * @param    int       $font_size  Font size.
	 * @param    string    $align      Alignment (L, C, R).
	 */
	private function render_text_field( $pdf, $text, $x, $y, $font_size, $align = 'C' ) {
		$pdf->SetFont( 'Arial', '', $font_size );
		$pdf->SetTextColor( 55, 65, 81 );
		$pdf->SetXY( $x, $y );
		$pdf->Cell( 0, 10, $this->sanitize_text( $text ), 0, 0, $align );
	}

	/**
	 * Generate PDF using TCPDF (fallback).
	 *
	 * @since    2.0.0
	 * @param    string    $template_path    Path to PDF template.
	 * @param    string    $template_json    JSON configuration.
	 * @param    array     $data             Voucher data.
	 * @param    array     $options          Additional options.
	 * @return   array|false
	 */
	private function generate_with_tcpdf( $template_path, $template_json, $data, $options = array() ) {
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
