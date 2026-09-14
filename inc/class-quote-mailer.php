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
     * Send automated quote expiration reminder email to customer (Phase 4.1)
     */
    public static function send_reminder_email($quote) {
        if (empty($quote->email) || empty($quote->acceptance_token)) return false;

        $settings = Evonee_Quote_Admin::get_settings();
        $brand_name = !empty($settings['email_brand_name']) ? $settings['email_brand_name'] : 'Evonee';
        // FIX BUG #13: Use get_sales_email() instead of hardcoded EQ_SALES_EMAIL constant
        // This respects the sales email configured in plugin settings
        $sales_email = Evonee_Quote_Ajax::get_sales_email();
        $accept_url  = add_query_arg(['eq_action' => 'accept_quote', 'token' => $quote->acceptance_token], home_url());
        $decline_url = add_query_arg(['eq_action' => 'decline_quote', 'token' => $quote->acceptance_token], home_url());

        $subject = '⏰ Reminder: Your Quote for ' . $quote->product . ' is expiring soon!';
        $headers = [
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . $brand_name . ' Sales <' . $sales_email . '>',
            'Reply-To: ' . $sales_email
        ];

        $mail_body = '
        <!DOCTYPE html>
        <html>
        <head><meta charset="UTF-8"></head>
        <body style="font-family:sans-serif; background:#f8fafc; padding:20px;">
            <div style="max-width:550px; margin:0 auto; background:#ffffff; border-radius:10px; overflow:hidden; border:1px solid #e2e8f0;">
                <div style="background:#6d28d9; color:#fff; padding:20px; text-align:center;">
                    <h2 style="margin:0;">⏰ Your Quote Offer is Expiring Soon</h2>
                </div>
                <div style="padding:20px;">
                    <p>Hi <strong>' . esc_html($quote->full_name) . '</strong>,</p>
                    <p>This is a quick reminder that your negotiated price quote for <strong>' . esc_html($quote->product) . '</strong> will expire in 3 days.</p>
                    <p style="text-align:center; margin:24px 0;">
                        <a href="' . esc_url($accept_url) . '" style="background:#16a34a; color:#fff; padding:10px 20px; text-decoration:none; border-radius:6px; font-weight:bold; margin-right:10px;">✅ Accept Offer</a>
                        <a href="' . esc_url($decline_url) . '" style="background:#dc2626; color:#fff; padding:10px 16px; text-decoration:none; border-radius:6px; font-weight:bold;">❌ Decline Offer</a>
                    </p>
                </div>
            </div>
        </body>
        </html>';

        return wp_mail($quote->email, $subject, $mail_body, $headers);
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

            $headers = [
                'Content-Type: text/html; charset=UTF-8',
                'Reply-To: ' . esc_html($full_name) . ' <' . sanitize_email($submission['email']) . '>'
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

            $sent = wp_mail($recipients, $subject, $message, $headers);
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

            $headers = [
                'Content-Type: text/html; charset=UTF-8',
                'From: ' . $brand_name . ' Team <' . $sales_email . '>'
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

            $sent = wp_mail($to, $subject, $message, $headers);
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
     * Send Weekly Sales Digest Email (Step 7)
     */
    public static function send_weekly_digest() {
        global $wpdb;
        $sales_email = Evonee_Quote_Ajax::get_sales_email();
        $settings    = Evonee_Quote_Admin::get_settings();
        $brand_name  = !empty($settings['email_brand_name']) ? esc_html($settings['email_brand_name']) : 'Evonee';

        // Fetch last 7 days quote metrics
        $week_ago = gmdate('Y-m-d H:i:s', strtotime('-7 days'));
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $total_leads = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}eq_quote_submissions WHERE created_at >= %s", $week_ago));
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $total_val   = (float) $wpdb->get_var($wpdb->prepare("SELECT SUM(quoted_price) FROM {$wpdb->prefix}eq_quote_submissions WHERE created_at >= %s AND quoted_price > 0", $week_ago));
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $approved_cnt = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}eq_quote_submissions WHERE created_at >= %s AND status = %s", $week_ago, 'approved'));

        $subject = "📊 Weekly Sales & Quote Digest — " . $brand_name . " (" . gmdate('M j') . ")";
        $headers = [
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . $brand_name . ' Digest <' . $sales_email . '>'
        ];

        $body = '
        <!DOCTYPE html>
        <html>
        <head><meta charset="UTF-8"></head>
        <body style="font-family:sans-serif; background:#f8fafc; padding:20px; color:#1e293b;">
            <div style="max-width:600px; margin:0 auto; background:#ffffff; border-radius:10px; border:1px solid #e2e8f0; overflow:hidden;">
                <div style="background:#6d28d9; color:#ffffff; padding:24px; text-align:center;">
                    <h2 style="margin:0; font-size:22px;">📊 Weekly Sales Digest & Performance Report</h2>
                    <p style="margin:6px 0 0; opacity:0.9; font-size:13px;">' . esc_html($brand_name) . ' Quote Analytics</p>
                </div>
                <div style="padding:24px;">
                    <div style="display:grid; grid-template-columns:1fr 1fr 1fr; gap:12px; margin-bottom:20px; text-align:center;">
                        <div style="background:#faf5ff; border:1px solid #e9d5ff; padding:12px; border-radius:8px;">
                            <span style="font-size:22px; font-weight:800; color:#6d28d9;">' . $total_leads . '</span><br>
                            <small style="color:#64748b;">New Quote Leads</small>
                        </div>
                        <div style="background:#f0fdf4; border:1px solid #bbf7d0; padding:12px; border-radius:8px;">
                            <span style="font-size:22px; font-weight:800; color:#16a34a;">$' . number_format($total_val, 2) . '</span><br>
                            <small style="color:#64748b;">Quoted Pipeline</small>
                        </div>
                        <div style="background:#eff6ff; border:1px solid #bfdbfe; padding:12px; border-radius:8px;">
                            <span style="font-size:22px; font-weight:800; color:#2563eb;">' . $approved_cnt . '</span><br>
                            <small style="color:#64748b;">Accepted Quotes</small>
                        </div>
                    </div>
                    <p style="font-size:13.5px; color:#475569;">Keep up the great momentum! Log in to your WordPress dashboard to manage pending quotes and follow up with leads.</p>
                    <div style="text-align:center; margin-top:20px;">
                        <a href="' . admin_url('admin.php?page=evonee-submissions') . '" style="background:#6d28d9; color:#fff; padding:10px 20px; border-radius:6px; font-weight:bold; text-decoration:none;">Open Evonee CRM Drawer &rarr;</a>
                    </div>
                </div>
            </div>
        </body>
        </html>';

        return wp_mail($sales_email, $subject, $body, $headers);
    }
}
