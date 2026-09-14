<?php
if (!defined('ABSPATH')) {
    exit;
}

class Evonee_Quote_Mailer {

    /**
     * Send notification email to admin/sales team & auto-reply to customer
     *
     * @param array $submission
     * @return bool
     */
    public static function send_notification(array $submission) {
        $sub_id = !empty($submission['id']) ? intval($submission['id']) : 0;
        if ($sub_id) {
            Evonee_Quote_Ajax::log_activity($sub_id, 'quote_created', 'New quote submission received');
        }

        $admin_success = self::send_admin_notification($submission);

        // Respect the enable_auto_reply plugin setting
        $settings = Evonee_Quote_Admin::get_settings();
        $customer_success = false;
        if (!isset($settings['enable_auto_reply']) || $settings['enable_auto_reply'] === '1') {
            $customer_success = self::send_customer_autoreply($submission);
        }

        return $admin_success || $customer_success;
    }

    /**
     * Send structured HTML email notification to sales/admin team
     */
    private static function send_admin_notification(array $submission) {
        try {
            $admin_email = get_option('admin_email');
            $sales_email = Evonee_Quote_Ajax::get_sales_email();
            $recipients  = array_values(array_unique(array_filter([$admin_email, $sales_email], 'is_email')));
            $recipients  = apply_filters('evonee_notification_recipient', $recipients);

            $product_name = !empty($submission['product']) ? esc_html($submission['product']) : 'Custom Product';
            $full_name    = !empty($submission['full_name']) ? esc_html($submission['full_name']) : 'Customer';

            $subject = sprintf('New Quote Request — %s from %s', $product_name, $full_name);

            $reply_name = self::encode_email_name($submission['full_name'] ?? 'Customer');
            $headers = [
                'Content-Type: text/html; charset=UTF-8',
                'Reply-To: ' . (!empty($reply_name) ? $reply_name . ' ' : '') . '<' . sanitize_email($submission['email']) . '>'
            ];

            $artwork_urls = Evonee_Quote_Ajax::parse_artwork_urls($submission['artwork_url'] ?? '');
            if (!empty($artwork_urls)) {
                $links = [];
                foreach ($artwork_urls as $i => $url) {
                    $links[] = sprintf('<a href="%1$s" target="_blank" style="color: #6d28d9; font-weight: bold;">Artwork %2$d</a>', esc_url($url), $i + 1);
                }
                $artwork_html = implode(' &nbsp;|&nbsp; ', $links);
            } else {
                $artwork_html = '<em>No artwork attached (Design help requested)</em>';
            }

            $product_details = '';
            if (!empty($submission['product_details']) && is_array($submission['product_details'])) {
                foreach ($submission['product_details'] as $key => $val) {
                    if (!empty($val)) {
                        $label = esc_html(ucwords(str_replace('_', ' ', $key)));
                        $value = esc_html(is_array($val) ? implode(', ', $val) : $val);
                        $product_details .= sprintf('<tr><td style="padding: 8px 12px; border: 1px solid #e5e0f5; background: #faf9fd; font-weight: 600;">%s</td><td style="padding: 8px 12px; border: 1px solid #e5e0f5;">%s</td></tr>', $label, $value);
                    }
                }
            }

            $settings = Evonee_Quote_Admin::get_settings();
            $brand_name   = !empty($settings['email_brand_name']) ? esc_html($settings['email_brand_name']) : 'Evonee';
            $header_color = !empty($settings['email_header_color']) ? esc_attr($settings['email_header_color']) : '#6d28d9';
            $footer_text  = !empty($settings['email_footer_text']) ? esc_html($settings['email_footer_text']) : 'Evonee Promotional Products • sales@evonee.com';
            $logo_url     = !empty($settings['email_logo_url']) ? esc_url($settings['email_logo_url']) : '';

            $header_logo_html = !empty($logo_url)
                ? sprintf('<img src="%s" alt="%s" style="max-height: 50px; display: block; margin-bottom: 10px;">', $logo_url, $brand_name)
                : sprintf('<h2 style="color: %s; margin-top: 0; border-bottom: 2px solid %s; padding-bottom: 10px; font-size: 20px;">📥 New Quote Request Received — %s</h2>', $header_color, $header_color, $brand_name);

            $message = '
            <!DOCTYPE html>
            <html>
            <head>
                <meta charset="UTF-8">
                <style>
                    body { font-family: Segoe UI, Helvetica, Arial, sans-serif; color: #1e1b2e; background-color: #f7f6fb; margin: 0; padding: 20px; }
                    .container { max-width: 650px; background: #ffffff; margin: 0 auto; padding: 25px; border-radius: 10px; border: 1px solid #e5e0f5; }
                    table { width: 100%; border-collapse: collapse; margin-top: 15px; font-size: 14px; }
                    .section-title { background: ' . $header_color . '; color: #ffffff; padding: 8px 12px; font-weight: bold; font-size: 13px; text-transform: uppercase; }
                    td { font-size: 13px; }
                </style>
            </head>
            <body>
                <div class="container">
                    ' . $header_logo_html . '
                    <p style="font-size: 14px; color: #6b6479;">A new quote submission has been submitted on <strong>' . $brand_name . '</strong>.</p>
                    
                    <table>
                        <tr><td colspan="2" class="section-title">1. Contact Information</td></tr>
                        <tr><td style="padding: 8px 12px; border: 1px solid #e5e0f5; background: #faf9fd; font-weight: 600; width: 35%;">Full Name</td><td style="padding: 8px 12px; border: 1px solid #e5e0f5;">' . esc_html($submission['full_name']) . '</td></tr>
                        <tr><td style="padding: 8px 12px; border: 1px solid #e5e0f5; background: #faf9fd; font-weight: 600;">Company / Org</td><td style="padding: 8px 12px; border: 1px solid #e5e0f5;">' . esc_html($submission['company'] ?? 'N/A') . '</td></tr>
                        <tr><td style="padding: 8px 12px; border: 1px solid #e5e0f5; background: #faf9fd; font-weight: 600;">Email Address</td><td style="padding: 8px 12px; border: 1px solid #e5e0f5;"><a href="mailto:' . esc_attr($submission['email']) . '">' . esc_html($submission['email']) . '</a></td></tr>
                        <tr><td style="padding: 8px 12px; border: 1px solid #e5e0f5; background: #faf9fd; font-weight: 600;">Phone / WhatsApp</td><td style="padding: 8px 12px; border: 1px solid #e5e0f5;">' . esc_html($submission['phone']) . '</td></tr>
                        <tr><td style="padding: 8px 12px; border: 1px solid #e5e0f5; background: #faf9fd; font-weight: 600;">Country</td><td style="padding: 8px 12px; border: 1px solid #e5e0f5;">' . esc_html($submission['country']) . '</td></tr>

                        <tr><td colspan="2" class="section-title">2. Product Information</td></tr>
                        <tr><td style="padding: 8px 12px; border: 1px solid #e5e0f5; background: #faf9fd; font-weight: 600;">Product</td><td style="padding: 8px 12px; border: 1px solid #e5e0f5; font-weight: bold; color: ' . $header_color . ';">' . esc_html($submission['product']) . '</td></tr>
                        <tr><td style="padding: 8px 12px; border: 1px solid #e5e0f5; background: #faf9fd; font-weight: 600;">Quantity</td><td style="padding: 8px 12px; border: 1px solid #e5e0f5;">' . esc_html($submission['quantity']) . '</td></tr>

                        <tr><td colspan="2" class="section-title">3. Product Details</td></tr>
                        ' . $product_details . '

                        <tr><td colspan="2" class="section-title">4. Artwork Upload</td></tr>
                        <tr><td style="padding: 8px 12px; border: 1px solid #e5e0f5; background: #faf9fd; font-weight: 600;">Artwork File</td><td style="padding: 8px 12px; border: 1px solid #e5e0f5;">' . $artwork_html . '</td></tr>

                        <tr><td colspan="2" class="section-title">5. Delivery Information</td></tr>
                        <tr><td style="padding: 8px 12px; border: 1px solid #e5e0f5; background: #faf9fd; font-weight: 600;">Timeframe</td><td style="padding: 8px 12px; border: 1px solid #e5e0f5;">' . esc_html($submission['timeframe']) . '</td></tr>
                        <tr><td style="padding: 8px 12px; border: 1px solid #e5e0f5; background: #faf9fd; font-weight: 600;">Specific Date</td><td style="padding: 8px 12px; border: 1px solid #e5e0f5;">' . esc_html($submission['specific_date'] ?? 'N/A') . '</td></tr>
                        <tr><td style="padding: 8px 12px; border: 1px solid #e5e0f5; background: #faf9fd; font-weight: 600;">ZIP / Postal Code</td><td style="padding: 8px 12px; border: 1px solid #e5e0f5;">' . esc_html($submission['zip_code']) . '</td></tr>

                        <tr><td colspan="2" class="section-title">6. Additional Notes</td></tr>
                        <tr><td style="padding: 8px 12px; border: 1px solid #e5e0f5; background: #faf9fd; font-weight: 600;">Project Details</td><td style="padding: 8px 12px; border: 1px solid #e5e0f5;">' . nl2br(esc_html($submission['project_notes'] ?? 'None')) . '</td></tr>
                        <tr><td style="padding: 8px 12px; border: 1px solid #e5e0f5; background: #faf9fd; font-weight: 600;">Submitted At</td><td style="padding: 8px 12px; border: 1px solid #e5e0f5;">' . esc_html($submission['created_at'] ?? current_time('mysql')) . '</td></tr>
                    </table>

                    <div style="margin-top: 20px; font-size: 12px; color: #8c859b; text-align: center; border-top: 1px solid #e5e0f5; padding-top: 10px;">
                        ' . $footer_text . '
                    </div>
                </div>
            </body>
            </html>
            ';

            $attachments = [];
            $temp_pdf_path = '';
            if (!isset($settings['enable_pdf_attachment']) || $settings['enable_pdf_attachment'] === '1') {
                $upload_dir = wp_upload_dir();
                $temp_dir = $upload_dir['basedir'] . '/evonee-quotes/pdf-temp';
                if (!file_exists($temp_dir)) {
                    wp_mkdir_p($temp_dir);
                }
                $pdf_id = !empty($submission['id']) ? $submission['id'] : time();
                $temp_pdf_path = $temp_dir . '/Quote-Estimate-' . $pdf_id . '.pdf';
                Evonee_Quote_PDF::generate($submission, 'F', $temp_pdf_path);
                if (file_exists($temp_pdf_path)) {
                    $attachments[] = $temp_pdf_path;
                }
            }

            $sent = wp_mail($recipients, $subject, $message, $headers, $attachments);

            if (!empty($temp_pdf_path) && file_exists($temp_pdf_path)) {
                @unlink($temp_pdf_path);
            }

            $sub_id = !empty($submission['id']) ? intval($submission['id']) : 0;
            if ($sub_id) {
                $recip_str = is_array($recipients) ? implode(', ', $recipients) : $recipients;
                Evonee_Quote_Ajax::log_email($sub_id, $recip_str, $subject, 'admin_notification', $sent ? 'sent' : 'failed');
            }
            return $sent;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Send confirmation auto-reply email to customer
     */
    private static function send_customer_autoreply(array $submission) {
        if (empty($submission['email']) || !is_email($submission['email'])) {
            return false;
        }

        try {
            $to = sanitize_email($submission['email']);
            $full_name = !empty($submission['full_name']) ? esc_html($submission['full_name']) : 'Valued Customer';
            $product   = !empty($submission['product']) ? esc_html($submission['product']) : 'custom products';

            $settings     = Evonee_Quote_Admin::get_settings();
            $sales_email  = Evonee_Quote_Ajax::get_sales_email();
            $brand_name   = !empty($settings['email_brand_name']) ? esc_html($settings['email_brand_name']) : 'Evonee';
            $header_color = !empty($settings['email_header_color']) ? esc_attr($settings['email_header_color']) : '#6d28d9';
            $footer_text  = !empty($settings['email_footer_text']) ? esc_html($settings['email_footer_text']) : 'Evonee Promotional Products • sales@evonee.com';
            $logo_url     = !empty($settings['email_logo_url']) ? esc_url($settings['email_logo_url']) : '';

            $subject = "We've Received Your Quote Request - " . $brand_name;

            $from_name = self::encode_email_name($brand_name . ' Team');
            $headers = [
                'Content-Type: text/html; charset=UTF-8',
                'From: ' . (!empty($from_name) ? $from_name . ' ' : '') . '<' . $sales_email . '>'
            ];

            $header_logo_html = !empty($logo_url)
                ? sprintf('<div class="logo"><img src="%s" alt="%s" style="max-height: 50px;"></div>', $logo_url, $brand_name)
                : '';

            $message = '
            <!DOCTYPE html>
            <html>
            <head>
                <meta charset="UTF-8">
                <style>
                    body { font-family: Segoe UI, Helvetica, Arial, sans-serif; color: #1e1b2e; background-color: #f7f6fb; margin: 0; padding: 20px; }
                    .container { max-width: 600px; background: #ffffff; margin: 0 auto; padding: 30px; border-radius: 10px; border: 1px solid #e5e0f5; text-align: left; }
                    .logo { text-align: center; margin-bottom: 20px; }
                    h2 { color: ' . $header_color . '; font-size: 22px; margin-top: 0; }
                    p { line-height: 1.6; color: #4a4556; font-size: 15px; }
                    .box { background: #f3f0fc; border-left: 4px solid ' . $header_color . '; padding: 15px 20px; border-radius: 4px; margin: 20px 0; }
                    .footer { text-align: center; margin-top: 30px; font-size: 12px; color: #8c859b; border-top: 1px solid #e5e0f5; padding-top: 15px; }
                </style>
            </head>
            <body>
                <div class="container">
                    ' . $header_logo_html . '
                    <h2>Hello ' . $full_name . ',</h2>
                    <p>Thank you for requesting a quote for <strong>' . $product . '</strong> on ' . $brand_name . '!</p>
                    
                    <div class="box">
                        <strong style="color: ' . $header_color . '; font-size: 16px;">What Happens Next?</strong>
                        <p style="margin: 8px 0 0 0;">Our design and quote team is reviewing your requirements. We will reach back out to you with a <strong>custom quote and free digital proof within 24 hours</strong>.</p>
                    </div>

                    <p>If you have additional design files or questions in the meantime, feel free to reply directly to this email or reach us at <a href="mailto:' . $sales_email . '" style="color: ' . $header_color . '; text-decoration: none; font-weight: bold;">' . $sales_email . '</a>.</p>

                    <p>Best regards,<br><strong>The ' . $brand_name . ' Team</strong><br>Custom Products. Made Simple.</p>

                    <div class="footer">
                        ' . $footer_text . '
                    </div>
                </div>
            </body>
            </html>
            ';

            $attachments = [];
            $temp_pdf_path = '';
            if (!isset($settings['enable_pdf_attachment']) || $settings['enable_pdf_attachment'] === '1') {
                $upload_dir = wp_upload_dir();
                $temp_dir = $upload_dir['basedir'] . '/evonee-quotes/pdf-temp';
                if (!file_exists($temp_dir)) {
                    wp_mkdir_p($temp_dir);
                }
                $pdf_id = !empty($submission['id']) ? $submission['id'] : time();
                $temp_pdf_path = $temp_dir . '/Quote-Estimate-' . $pdf_id . '.pdf';
                Evonee_Quote_PDF::generate($submission, 'F', $temp_pdf_path);
                if (file_exists($temp_pdf_path)) {
                    $attachments[] = $temp_pdf_path;
                }
            }

            $sent = wp_mail($to, $subject, $message, $headers, $attachments);

            if (!empty($temp_pdf_path) && file_exists($temp_pdf_path)) {
                @unlink($temp_pdf_path);
            }

            $sub_id = !empty($submission['id']) ? intval($submission['id']) : 0;
            if ($sub_id) {
                Evonee_Quote_Ajax::log_email($sub_id, $to, $subject, 'customer_autoreply', $sent ? 'sent' : 'failed');
            }
            return $sent;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Safely format and encode name for email header (MIME / Special character safe)
     *
     * @param string $name
     * @return string
     */
    private static function encode_email_name($name) {
        $name = trim(preg_replace('/[\r\n]+/', '', (string) $name));
        if (empty($name)) {
            return '';
        }
        if (preg_match('/[^\x20-\x7E]/', $name)) {
            if (function_exists('mb_encode_mimeheader')) {
                return mb_encode_mimeheader($name, 'UTF-8', 'B');
            }
        }
        if (preg_match('/[\x22\x2C\x3B]/', $name)) {
            return '"' . addcslashes($name, '"\\') . '"';
        }
        return $name;
    }
}
