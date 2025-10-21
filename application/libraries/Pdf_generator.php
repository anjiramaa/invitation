<?php
defined('BASEPATH') OR exit('No direct script access allowed');
/**
 * Pdf_generator
 * - Wrapper around Dompdf (recommended) or wkhtmltopdf
 * - Version: 2025-10-17_v1
 *
 * Note: This library expects dompdf library available (composer require dompdf/dompdf)
 * If your environment doesn't have dompdf, you can install or switch to wkhtmltopdf wrapper.
 */

use Dompdf\Dompdf;
use Dompdf\Options;

class Pdf_generator
{
    protected $CI;
    public function __construct($params = array())
    {
        $this->CI =& get_instance();
        // optional config load
        // Ensure Dompdf is loaded via composer autoload (application/third_party or vendor)
        if (!class_exists('Dompdf')) {
            log_message('error', 'Dompdf class not found. Install dompdf via composer.');
        }
    }

    /**
     * generate_pdf_from_html
     * - $html: source
     * - $output_path: full server path where file will be saved
     * - $options: array('paper'=>'A4','orientation'=>'portrait')
     */
    public function generate_pdf_from_html($html, $output_path, $options = array())
    {
        try {
            $dompdf_options = new Options();
            $dompdf_options->set('isRemoteEnabled', TRUE);
            $dompdf = new Dompdf($dompdf_options);
            $dompdf->loadHtml($html);
            $paper = isset($options['paper']) ? $options['paper'] : 'A4';
            $orientation = isset($options['orientation']) ? $options['orientation'] : 'portrait';
            $dompdf->setPaper($paper, $orientation);
            $dompdf->render();
            $output = $dompdf->output();
            file_put_contents($output_path, $output);
            return true;
        } catch (Exception $e) {
            if (defined('ENVIRONMENT') && ENVIRONMENT === 'development') {
                log_message('error', 'Pdf_generator error: ' . $e->getMessage());
            }
            return false;
        }
    }
}
