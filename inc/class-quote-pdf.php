<?php
/**
 * Evonee Quote PDF Generator Class
 * Formats quote details into a clean PDF document for attachments & downloads.
 */

if (!defined('ABSPATH')) {
    exit;
}

require_once EVONEE_PLUGIN_DIR . 'inc/lib/fpdf.php';

class Evonee_Quote_PDF {

    /**
     * Generate PDF binary content or save to file
     * 
     * @param object|array $quote Quote record from database
     * @param string $output_mode 'S' for string binary, 'F' for file path
     * @param string $file_path File path if output_mode is 'F'
     * @return string PDF binary string or file path
     */
    public static function generate($quote, $output_mode = 'S', $file_path = '') {
        $quote_obj = is_array($quote) ? (object)$quote : $quote;

        $pdf = new FPDF('P', 'mm', 'A4');
        $pdf->SetMargins(15, 15, 15);
        $pdf->SetAutoPageBreak(true, 15);
        $pdf->AddPage();

        // Colors
        $primary_r = 109; $primary_g = 40; $primary_b = 217; // Purple #6d28d9
        $dark_r = 30; $dark_g = 27; $dark_b = 46;          // Dark #1e1b2e
        $gray_r = 100; $gray_g = 116; $gray_b = 139;       // Slate #64748b
        $bg_r = 248; $bg_g = 250; $bg_b = 252;             // Soft Light #f8fafc

        // Header Background Banner
        $pdf->SetFillColor($primary_r, $primary_g, $primary_b);
        $pdf->Rect(0, 0, 210, 32, 'F');

        // Header Title
        $pdf->SetFont('Helvetica', 'B', 18);
        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetXY(15, 8);
        $pdf->Cell(110, 10, 'EVONEE — OFFICIAL QUOTE ESTIMATE', 0, 0, 'L');

        // Quote Reference ID on Top Right
        $quote_id = !empty($quote_obj->id) ? '#EQ-' . str_pad($quote_obj->id, 5, '0', STR_PAD_LEFT) : '#EQ-ESTIMATE';
        $pdf->SetFont('Helvetica', 'B', 12);
        $pdf->SetXY(135, 8);
        $pdf->Cell(60, 10, $quote_id, 0, 1, 'R');

        // Header Subtitle
        $pdf->SetFont('Helvetica', '', 9);
        $pdf->SetXY(15, 18);
        $pdf->Cell(180, 5, 'Generated on ' . date('F j, Y - g:i A') . ' UTC', 0, 1, 'L');

        $pdf->SetY(38);

        // Client & Order Overview Cards (Side by Side)
        $pdf->SetFillColor($bg_r, $bg_g, $bg_b);
        $pdf->SetDrawColor(226, 232, 240);
        $pdf->Rect(15, 38, 88, 48, 'DF');
        $pdf->Rect(107, 38, 88, 48, 'DF');

        // Client Details Box
        $pdf->SetFont('Helvetica', 'B', 11);
        $pdf->SetTextColor($primary_r, $primary_g, $primary_b);
        $pdf->SetXY(18, 42);
        $pdf->Cell(82, 6, 'CLIENT INFORMATION', 0, 1, 'L');

        $pdf->SetFont('Helvetica', '', 9);
        $pdf->SetTextColor($dark_r, $dark_g, $dark_b);

        $name = !empty($quote_obj->name) ? $quote_obj->name : 'N/A';
        $email = !empty($quote_obj->email) ? $quote_obj->email : 'N/A';
        $phone = !empty($quote_obj->phone) ? $quote_obj->phone : 'N/A';
        $company = !empty($quote_obj->company) ? $quote_obj->company : 'N/A';

        $pdf->SetXY(18, 50);
        $pdf->Cell(82, 5, 'Name: ' . $name, 0, 1, 'L');
        $pdf->SetXY(18, 56);
        $pdf->Cell(82, 5, 'Email: ' . $email, 0, 1, 'L');
        $pdf->SetXY(18, 62);
        $pdf->Cell(82, 5, 'Phone: ' . $phone, 0, 1, 'L');
        $pdf->SetXY(18, 68);
        $pdf->Cell(82, 5, 'Company: ' . $company, 0, 1, 'L');

        // Quote Status Box
        $pdf->SetFont('Helvetica', 'B', 11);
        $pdf->SetTextColor($primary_r, $primary_g, $primary_b);
        $pdf->SetXY(110, 42);
        $pdf->Cell(82, 6, 'QUOTE SPECIFICATIONS', 0, 1, 'L');

        $pdf->SetFont('Helvetica', '', 9);
        $pdf->SetTextColor($dark_r, $dark_g, $dark_b);

        $product = !empty($quote_obj->product) ? $quote_obj->product : 'Standard Print & Design';
        $status = !empty($quote_obj->status) ? strtoupper($quote_obj->status) : 'PENDING';
        $date = !empty($quote_obj->created_at) ? date('M j, Y', strtotime($quote_obj->created_at)) : date('M j, Y');
        $est_total = !empty($quote_obj->estimated_total) ? '$' . number_format((float)$quote_obj->estimated_total, 2) : 'Under Review';

        $pdf->SetXY(110, 50);
        $pdf->Cell(82, 5, 'Product/Service: ' . $product, 0, 1, 'L');
        $pdf->SetXY(110, 56);
        $pdf->Cell(82, 5, 'Submission Date: ' . $date, 0, 1, 'L');
        $pdf->SetXY(110, 62);
        $pdf->Cell(82, 5, 'Status: ' . $status, 0, 1, 'L');
        $pdf->SetXY(110, 68);
        $pdf->SetFont('Helvetica', 'B', 10);
        $pdf->SetTextColor($primary_r, $primary_g, $primary_b);
        $pdf->Cell(82, 5, 'Estimated Total: ' . $est_total, 0, 1, 'L');

        $pdf->SetY(94);

        // Requested Specifications Table
        $pdf->SetFont('Helvetica', 'B', 11);
        $pdf->SetTextColor($dark_r, $dark_g, $dark_b);
        $pdf->Cell(180, 8, 'Detailed Specifications & Requirements', 0, 1, 'L');

        // Table Header
        $pdf->SetFillColor(241, 245, 249);
        $pdf->SetFont('Helvetica', 'B', 9);
        $pdf->SetTextColor($dark_r, $dark_g, $dark_b);
        $pdf->Cell(60, 7, ' Parameter', 1, 0, 'L', true);
        $pdf->Cell(120, 7, ' Specification Value', 1, 1, 'L', true);

        $pdf->SetFont('Helvetica', '', 9);

        // Decode custom fields if present
        $custom_fields = array();
        if (!empty($quote_obj->custom_fields)) {
            $decoded = json_decode($quote_obj->custom_fields, true);
            if (is_array($decoded)) {
                $custom_fields = $decoded;
            }
        }

        $specs = array();
        if (!empty($quote_obj->quantity)) $specs['Quantity'] = $quote_obj->quantity;
        if (!empty($quote_obj->dimensions)) $specs['Dimensions / Size'] = $quote_obj->dimensions;
        if (!empty($quote_obj->material)) $specs['Material / Stock'] = $quote_obj->material;
        if (!empty($quote_obj->printing_type)) $specs['Printing Process'] = $quote_obj->printing_type;
        if (!empty($quote_obj->turnaround)) $specs['Turnaround Time'] = $quote_obj->turnaround;

        foreach ($custom_fields as $key => $val) {
            $label = ucwords(str_replace(['_', '-'], ' ', $key));
            $specs[$label] = is_array($val) ? implode(', ', $val) : (string)$val;
        }

        if (empty($specs)) {
            $specs['Request Overview'] = 'Standard Custom Quote Request';
        }

        $fill = false;
        foreach ($specs as $param => $value) {
            $pdf->SetFillColor($fill ? 250 : 255, $fill ? 250 : 255, $fill ? 250 : 255);
            $pdf->Cell(60, 6, ' ' . $param, 1, 0, 'L', true);
            $pdf->Cell(120, 6, ' ' . $value, 1, 1, 'L', true);
            $fill = !$fill;
        }

        $pdf->Ln(5);

        // Project Description / Additional Notes
        if (!empty($quote_obj->notes) || !empty($quote_obj->description)) {
            $notes_text = !empty($quote_obj->notes) ? $quote_obj->notes : $quote_obj->description;
            $pdf->SetFont('Helvetica', 'B', 10);
            $pdf->SetTextColor($dark_r, $dark_g, $dark_b);
            $pdf->Cell(180, 6, 'Project Notes & Instructions:', 0, 1, 'L');
            $pdf->SetFont('Helvetica', '', 9);
            $pdf->SetTextColor($gray_r, $gray_g, $gray_b);
            $pdf->MultiCell(180, 5, $notes_text, 1, 'L');
            $pdf->Ln(4);
        }

        // Uploaded Artwork Files List
        $files = array();
        if (!empty($quote_obj->artwork_files)) {
            $decoded_files = json_decode($quote_obj->artwork_files, true);
            if (is_array($decoded_files)) {
                $files = $decoded_files;
            } elseif (is_string($quote_obj->artwork_files)) {
                $files = explode(',', $quote_obj->artwork_files);
            }
        }

        if (!empty($files)) {
            $pdf->SetFont('Helvetica', 'B', 10);
            $pdf->SetTextColor($dark_r, $dark_g, $dark_b);
            $pdf->Cell(180, 6, 'Uploaded Artwork & Attachments:', 0, 1, 'L');
            $pdf->SetFont('Helvetica', '', 8);
            $pdf->SetTextColor($primary_r, $primary_g, $primary_b);
            foreach ($files as $idx => $f) {
                $f_name = basename(trim($f));
                $pdf->Cell(180, 4, ' • Attachment #' . ($idx + 1) . ': ' . $f_name, 0, 1, 'L');
            }
            $pdf->Ln(4);
        }

        // Terms & Footer Notice
        $pdf->SetY(-35);
        $pdf->SetDrawColor(226, 232, 240);
        $pdf->Line(15, $pdf->GetY(), 195, $pdf->GetY());
        $pdf->Ln(3);

        $pdf->SetFont('Helvetica', 'B', 8);
        $pdf->SetTextColor($dark_r, $dark_g, $dark_b);
        $pdf->Cell(180, 4, 'Terms & Conditions:', 0, 1, 'L');
        $pdf->SetFont('Helvetica', '', 7);
        $pdf->SetTextColor($gray_r, $gray_g, $gray_b);
        $pdf->Cell(180, 3, '1. Estimated totals are subject to final artwork verification and material availability.', 0, 1, 'L');
        $pdf->Cell(180, 3, '2. All custom print production begins upon proof approval and payment confirmation.', 0, 1, 'L');
        $pdf->Cell(180, 3, '3. Quote estimates remain valid for 30 days from date of issue.', 0, 1, 'L');

        if ($output_mode === 'F' && !empty($file_path)) {
            $pdf->Output('F', $file_path);
            return $file_path;
        }

        return $pdf->Output('S');
    }
}
