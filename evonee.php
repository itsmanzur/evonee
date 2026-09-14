<?php
/**
 * Plugin Name: Evonee - Get Quote System
 * Description: Professional AJAX Quote Modal, Price Estimator, PDF Sheet Generator & CRM Lead Management for WordPress and WooCommerce.
 * Version:     1.0.0
 * Author:      Evonee Team
 * License:     GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: evonee
 * Domain Path: /languages
 */

if (!defined('ABSPATH')) {
    exit;
}

// Define Plugin Constants
define('EVONEE_VERSION', '1.0.0');
define('EVONEE_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('EVONEE_PLUGIN_URL', plugin_dir_url(__FILE__));
if (!defined('EQ_SALES_EMAIL')) {
    define('EQ_SALES_EMAIL', 'sales@evonee.com');
}

// Include Required Classes
require_once EVONEE_PLUGIN_DIR . 'inc/class-quote-pdf.php';
require_once EVONEE_PLUGIN_DIR . 'inc/class-quote-modal.php';
require_once EVONEE_PLUGIN_DIR . 'inc/class-quote-ajax.php';
require_once EVONEE_PLUGIN_DIR . 'inc/class-quote-mailer.php';
require_once EVONEE_PLUGIN_DIR . 'inc/class-quote-admin.php';
require_once EVONEE_PLUGIN_DIR . 'inc/class-quote-elementor.php';

// Plugin Activation Hook - Create Database Table
register_activation_hook(__FILE__, ['Evonee_Quote_Ajax', 'create_submissions_table']);

// Plugin Deactivation Hook - Clear scheduled cron jobs
register_deactivation_hook(__FILE__, function() {
    wp_clear_scheduled_hook('evonee_weekly_digest_cron');
    wp_clear_scheduled_hook('evonee_daily_quote_cron');
});

// Initialize Core Plugin Components
add_action('plugins_loaded', function() {
    load_plugin_textdomain('evonee', false, dirname(plugin_basename(__FILE__)) . '/languages');
    Evonee_Quote_Modal::init();
    Evonee_Quote_Ajax::init();
    Evonee_Quote_Admin::init();
    // Runtime DB migration: ensure admin_notes and estimated_total columns exist
    Evonee_Quote_Ajax::maybe_add_admin_notes_column();

    // FIX BUG #1: Schedule cron jobs inside plugins_loaded (not at top-level)
    // FIX BUG #8: Register daily cron that was missing
    if (!wp_next_scheduled('evonee_weekly_digest_cron')) {
        wp_schedule_event(time(), 'weekly', 'evonee_weekly_digest_cron');
    }
    if (!wp_next_scheduled('evonee_daily_quote_cron')) {
        wp_schedule_event(time(), 'daily', 'evonee_daily_quote_cron');
    }
});

// Register Elementor Widget (Phase 2.3)
add_action('elementor/widgets/register', function($widgets_manager) {
    if (class_exists('Evonee_Elementor_Quote_Button_Widget')) {
        $widgets_manager->register(new \Evonee_Elementor_Quote_Button_Widget());
    }
});

// Register Gutenberg Block (Phase 2.3)
add_action('init', function() {
    register_block_type('evonee/quote-button', [
        'render_callback' => function($attributes) {
            $product     = !empty($attributes['product']) ? sanitize_text_field($attributes['product']) : 'Silicone Wristband';
            $button_text = !empty($attributes['buttonText']) ? sanitize_text_field($attributes['buttonText']) : 'Get Quote';
            return Evonee_Quote_Modal::quote_button($product, '', '', $button_text);
        },
        'attributes' => [
            'product'    => ['type' => 'string', 'default' => 'Silicone Wristband'],
            'buttonText' => ['type' => 'string', 'default' => 'Get Quote'],
        ]
    ]);
});

// WooCommerce "Request a Quote" Button Hook on Single Product Page
add_action('woocommerce_after_add_to_cart_button', function() {
    Evonee_Quote_Modal::render_woocommerce_button();
});

// WP Dashboard Widget
add_action('wp_dashboard_setup', function() {
    wp_add_dashboard_widget(
        'evonee_dashboard_widget',
        '📥 Evonee Quote Submissions',
        ['Evonee_Quote_Admin', 'render_dashboard_widget']
    );
});

// Schedule Weekly Sales Digest Cron (Step 7)
add_action('evonee_weekly_digest_cron', ['Evonee_Quote_Mailer', 'send_weekly_digest']);

// Daily Cron: Expiry Reminders & Token Cleanup (Phase 4.1)
// FIX BUG #8: Register the daily cron hook handler (was missing)
add_action('evonee_daily_quote_cron', ['Evonee_Quote_Ajax', 'run_daily_quote_cron']);

// Handle Public Customer Quote Acceptance / Decline / PDF Download Endpoint (Phase 3 & Step 3)
add_action('template_redirect', function() {
    // Public PDF Quote Download Action (Step 3)
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended
    if (isset($_GET['eq_action']) && sanitize_text_field(wp_unslash($_GET['eq_action'])) === 'download_pdf') {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $id = isset($_GET['id']) ? intval($_GET['id']) : 0;
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $token = isset($_GET['token']) ? sanitize_text_field(wp_unslash($_GET['token'])) : '';
        global $wpdb;
        $quote = null;
        if (!empty($token) && $id > 0) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $quote = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}eq_quote_submissions WHERE id = %d AND acceptance_token = %s", $id, $token));
        } elseif (current_user_can('manage_options') && $id > 0) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $quote = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}eq_quote_submissions WHERE id = %d", $id));
        }

        if ($quote) {
            // FIX BUG #5: Instead of manually overriding nonces and GET superglobals,
            // call the PDF generator directly with verified quote object
            if (method_exists('Evonee_Quote_Admin', 'stream_pdf_for_quote')) {
                Evonee_Quote_Admin::stream_pdf_for_quote($quote);
            } else {
                // Fallback: set verified nonce securely
                $_GET['action']       = 'generate_pdf_quote';
                $_GET['id']           = absint($quote->id);
                $_REQUEST['_wpnonce'] = wp_create_nonce('eq_generate_pdf_' . absint($quote->id));
                $admin = new Evonee_Quote_Admin();
                $admin->render_submissions_page();
            }
            exit;
        }
    }

    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Public token acceptance link from customer email
    if (isset($_GET['eq_action'], $_GET['token']) && in_array(sanitize_text_field(wp_unslash($_GET['eq_action'])), ['accept_quote', 'decline_quote'], true)) {
        global $wpdb;
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $token  = sanitize_text_field(wp_unslash($_GET['token']));
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $action = sanitize_text_field(wp_unslash($_GET['eq_action']));
        $table  = $wpdb->prefix . 'eq_quote_submissions';

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $quote = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}eq_quote_submissions WHERE acceptance_token = %s", $token));

        if (!$quote) {
            wp_die('Invalid or expired quote acceptance link.', 'Evonee Quote System', ['response' => 404]);
        }

        if (in_array($quote->status, ['approved', 'rejected'], true) || empty($quote->acceptance_token)) {
            wp_die('This quote offer has already been responded to.', 'Evonee Quote System', ['response' => 409]);
        }

        if (!empty($quote->token_expiry) && strtotime($quote->token_expiry . ' UTC') < time()) {
            wp_die('This quote offer link has expired. Please contact support.', 'Evonee Quote System', ['response' => 410]);
        }

        // Handle Digital E-Signature Pad for Accept Quote
        if ($action === 'accept_quote' && (empty($_POST['sig_confirm']) || empty($_POST['signature_data']))) {
            ?>
            <!DOCTYPE html>
            <html>
            <head>
                <meta charset="UTF-8">
                <meta name="viewport" content="width=device-width, initial-scale=1.0">
                <title>Sign & Accept Quote — Evonee</title>
                <style>
                    body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background: #f8fafc; color: #1e293b; display: flex; align-items: center; justify-content: center; min-height: 100vh; margin: 0; padding: 20px; box-sizing: border-box; }
                    .card { background: #ffffff; padding: 32px; border-radius: 14px; border: 1px solid #e2e8f0; max-width: 520px; width: 100%; box-shadow: 0 10px 25px rgba(0,0,0,0.05); }
                    h1 { color: #16a34a; font-size: 22px; margin-top: 0; display: flex; align-items: center; gap: 8px; }
                    .quote-summary { background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 8px; padding: 14px; margin-bottom: 20px; font-size: 13.5px; }
                    .canvas-wrap { border: 2px dashed #cbd5e1; border-radius: 10px; background: #ffffff; margin-bottom: 14px; position: relative; touch-action: none; }
                    canvas { display: block; width: 100%; height: 160px; border-radius: 8px; cursor: crosshair; }
                    .btn-row { display: flex; gap: 10px; justify-content: space-between; align-items: center; }
                    .btn-submit { background: #16a34a; color: #ffffff; border: none; padding: 12px 24px; border-radius: 8px; font-weight: 700; font-size: 14px; cursor: pointer; }
                    .btn-clear { background: #f1f5f9; color: #64748b; border: 1px solid #cbd5e1; padding: 10px 16px; border-radius: 8px; font-weight: 600; font-size: 13px; cursor: pointer; }
                </style>
            </head>
            <body>
                <div class="card">
                    <h1>✍️ Sign & Accept Quote #<?php echo esc_html($quote->id); ?></h1>
                    <div class="quote-summary">
                        <strong>Product:</strong> <?php echo esc_html($quote->product); ?><br>
                        <strong>Quantity:</strong> <?php echo esc_html($quote->quantity); ?><br>
                        <strong>Quoted Price Offer:</strong> <span style="color:#16a34a; font-weight:800; font-size:16px;"><?php echo (!empty($quote->quoted_price) && floatval($quote->quoted_price) > 0) ? '$' . number_format($quote->quoted_price, 2) : 'Included in Offer'; ?></span>
                    </div>

                    <form method="post" id="sig-form">
                        <input type="hidden" name="sig_confirm" value="1">
                        <input type="hidden" name="signature_data" id="signature_data" value="">

                        <label style="font-size:12px; font-weight:700; color:#475569; display:block; margin-bottom:6px;">Please Draw Your Digital Signature Below:</label>
                        <div class="canvas-wrap">
                            <canvas id="eq-canvas" width="450" height="160"></canvas>
                        </div>

                        <div class="btn-row">
                            <button type="button" class="btn-clear" id="btn-clear-sig">✕ Clear</button>
                            <button type="submit" class="btn-submit" id="btn-submit-sig">✅ Confirm & Sign Quote</button>
                        </div>
                    </form>
                </div>

                <script>
                document.addEventListener('DOMContentLoaded', function() {
                    const canvas = document.getElementById('eq-canvas');
                    const ctx = canvas.getContext('2d');
                    let drawing = false;

                    // Set stroke styles
                    ctx.strokeStyle = '#0f172a';
                    ctx.lineWidth = 2.5;
                    ctx.lineCap = 'round';
                    ctx.lineJoin = 'round';

                    function getPos(e) {
                        const rect = canvas.getBoundingClientRect();
                        const clientX = e.touches ? e.touches[0].clientX : e.clientX;
                        const clientY = e.touches ? e.touches[0].clientY : e.clientY;
                        return { x: clientX - rect.left, y: clientY - rect.top };
                    }

                    function startDraw(e) {
                        drawing = true;
                        const pos = getPos(e);
                        ctx.beginPath();
                        ctx.moveTo(pos.x, pos.y);
                    }

                    function draw(e) {
                        if (!drawing) return;
                        e.preventDefault();
                        const pos = getPos(e);
                        ctx.lineTo(pos.x, pos.y);
                        ctx.stroke();
                    }

                    function stopDraw() { drawing = false; }

                    canvas.addEventListener('mousedown', startDraw);
                    canvas.addEventListener('mousemove', draw);
                    canvas.addEventListener('mouseup', stopDraw);
                    canvas.addEventListener('mouseleave', stopDraw);

                    canvas.addEventListener('touchstart', startDraw, { passive: false });
                    canvas.addEventListener('touchmove', draw, { passive: false });
                    canvas.addEventListener('touchend', stopDraw);

                    document.getElementById('btn-clear-sig').addEventListener('click', function() {
                        ctx.clearRect(0, 0, canvas.width, canvas.height);
                    });

                    document.getElementById('sig-form').addEventListener('submit', function(e) {
                        const dataUrl = canvas.toDataURL('image/png');
                        document.getElementById('signature_data').value = dataUrl;
                    });
                });
                </script>
            </body>
            </html>
            <?php
            exit;
        }

        // FIX BUG #4: Properly validate base64 PNG data URL instead of sanitize_text_field
        // which would truncate the base64 string
        $sig_data_raw = isset($_POST['signature_data']) ? wp_unslash($_POST['signature_data']) : '';
        $sig_data = '';
        if (!empty($sig_data_raw)) {
            // Only accept valid base64-encoded PNG data URLs (from canvas.toDataURL)
            if (preg_match('/^data:image\/png;base64,[A-Za-z0-9+\/]+=*$/', $sig_data_raw)) {
                $sig_data = $sig_data_raw;
            }
            // Reject anything that doesn't match — prevents XSS via data URI injection
        }
        $new_status = ($action === 'accept_quote') ? 'approved' : 'rejected';

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $wpdb->update(
            $table,
            [
                'status'            => $new_status,
                'digital_signature' => $sig_data,
                'acceptance_token'  => '',
                'token_expiry'      => null,
            ],
            ['id' => $quote->id]
        );

        $note = ($action === 'accept_quote') ? 'Customer SIGNED & ACCEPTED quote offer via public link' : 'Customer DECLINED quote offer via public link';
        Evonee_Quote_Ajax::log_activity($quote->id, 'customer_response', $note);

        $payment_url = '';
        if ($action === 'accept_quote' && class_exists('WooCommerce') && function_exists('wc_create_order') && floatval($quote->quoted_price) > 0) {
            try {
                $order = wc_create_order();
                $item = new WC_Order_Item_Fee();
                $item_name = !empty($quote->product) ? $quote->product : 'Custom Quote Package';
                if (!empty($quote->quantity)) {
                    $item_name .= ' (Qty: ' . $quote->quantity . ')';
                }
                $item->set_name($item_name);
                $item->set_total(floatval($quote->quoted_price));
                $order->add_item($item);

                $name_parts = explode(' ', trim($quote->full_name), 2);
                $first_name = $name_parts[0];
                $last_name  = isset($name_parts[1]) ? $name_parts[1] : '';

                $address = [
                    'first_name' => $first_name,
                    'last_name'  => $last_name,
                    'company'    => $quote->company,
                    'email'      => $quote->email,
                    'phone'      => $quote->phone,
                    'country'    => $quote->country,
                    'postcode'   => $quote->zip_code,
                ];
                $order->set_address($address, 'billing');

                if (!empty($quote->project_notes)) {
                    $order->set_customer_note($quote->project_notes);
                }

                $order->calculate_totals();
                $order->update_status('pending', 'Auto-created upon Customer acceptance of Quote Request #' . $quote->id);
                $order->save();

                $payment_url = $order->get_checkout_payment_url();
                Evonee_Quote_Ajax::log_activity($quote->id, 'wc_order_created', 'Auto-created WooCommerce Order #' . $order->get_id() . ' for customer checkout');
            } catch (\Exception $e) {
                error_log('Evonee WooCommerce order creation error: ' . $e->getMessage());
            }
        }

        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>Quote Response — Evonee</title>
            <style>
                body { font-family: 'Segoe UI', Arial, sans-serif; background: #f8fafc; color: #1e1b2e; display: flex; align-items: center; justify-content: center; min-height: 100vh; margin: 0; padding: 20px; box-sizing: border-box; }
                .card { background: #ffffff; padding: 40px; border-radius: 12px; border: 1px solid #e2e8f0; max-width: 500px; width: 100%; text-align: center; box-shadow: 0 10px 25px rgba(0,0,0,0.05); }
                h1 { color: <?php echo esc_attr($action === 'accept_quote' ? '#16a34a' : '#dc2626'); ?>; font-size: 24px; margin-top: 0; }
                p { color: #64748b; font-size: 15px; line-height: 1.6; }
                .btn { display: inline-block; margin-top: 15px; background: #6d28d9; color: #fff; text-decoration: none; padding: 12px 24px; border-radius: 6px; font-weight: 700; font-size: 15px; }
                .btn-secondary { display: inline-block; margin-top: 10px; background: transparent; color: #64748b; text-decoration: none; padding: 8px 16px; font-size: 13px; font-weight: 600; }
                .btn-pay { background: #16a34a; color: #fff; font-size: 16px; display: block; margin: 20px 0 10px 0; padding: 14px 20px; }
            </style>
        </head>
        <body>
            <div class="card">
                <h1><?php echo $action === 'accept_quote' ? '🎉 Quote Offer Signed & Accepted!' : 'Offer Response Received'; ?></h1>
                <p><?php echo $action === 'accept_quote' ? 'Thank you for signing and approving your quote request for <strong>' . esc_html($quote->product) . '</strong>.' : 'Thank you for letting us know. We have updated your quote request status.'; ?></p>
                
                <?php if (!empty($payment_url)): ?>
                    <div style="background:#f0fdf4; border:1px solid #bbf7d0; border-radius:8px; padding:16px; margin:20px 0;">
                        <span style="font-size:13px; font-weight:700; color:#15803d; text-transform:uppercase; letter-spacing:0.5px; display:block;">Total Quoted Amount</span>
                        <div style="font-size:28px; font-weight:800; color:#166534; margin:4px 0 12px 0;">
                            <?php echo esc_html(Evonee_Quote_Admin::format_price($quote->quoted_price)); ?>
                        </div>
                        <a href="<?php echo esc_url($payment_url); ?>" class="btn btn-pay">💳 Proceed to Payment & Checkout &rarr;</a>
                        <small style="color:#15803d; font-size:12px;">Instant secure payment via WooCommerce</small>
                    </div>
                <?php endif; ?>

                <?php if (!empty($sig_data)): ?>
                    <div style="margin-top:16px; background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; padding:10px;">
                        <small style="color:#64748b; font-weight:700; display:block;">Your Recorded Signature:</small>
                        <img src="<?php echo esc_url($sig_data); ?>" style="max-height:70px; margin-top:6px;">
                    </div>
                <?php endif; ?>

                <div>
                    <a href="<?php echo esc_url(home_url()); ?>" class="btn-secondary">Return to Homepage</a>
                </div>
            </div>
        </body>
        </html>
        <?php
        exit;
    }
});
