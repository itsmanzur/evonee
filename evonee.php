<?php
/**
 * Plugin Name: Evonee - Get Quote System
 * Plugin URI:  https://evonee.com
 * Description: Custom site-wide AJAX Quote Modal & Product Lead Capture System for Evonee.
 * Version:     2.0.0
 * Author:      Evonee Team
 * License:     GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: evonee
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

// Define Plugin Constants
define('EVONEE_VERSION', '2.0.0');
define('EVONEE_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('EVONEE_PLUGIN_URL', plugin_dir_url(__FILE__));
if (!defined('EQ_SALES_EMAIL')) {
    define('EQ_SALES_EMAIL', 'sales@evonee.com');
}

// Include Required Classes
require_once EVONEE_PLUGIN_DIR . 'inc/class-quote-modal.php';
require_once EVONEE_PLUGIN_DIR . 'inc/class-quote-ajax.php';
require_once EVONEE_PLUGIN_DIR . 'inc/class-quote-mailer.php';
require_once EVONEE_PLUGIN_DIR . 'inc/class-quote-admin.php';
require_once EVONEE_PLUGIN_DIR . 'inc/class-quote-elementor.php';

// Plugin Activation Hook - Create Database Table
register_activation_hook(__FILE__, ['Evonee_Quote_Ajax', 'create_submissions_table']);

// Initialize Core Plugin Components
add_action('plugins_loaded', function() {
    Evonee_Quote_Modal::init();
    Evonee_Quote_Ajax::init();
    Evonee_Quote_Admin::init();
    // Runtime DB migration: ensure admin_notes column exists
    Evonee_Quote_Ajax::maybe_add_admin_notes_column();
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

// WP Dashboard Widget
add_action('wp_dashboard_setup', function() {
    wp_add_dashboard_widget(
        'evonee_dashboard_widget',
        '📥 Evonee Quote Submissions',
        ['Evonee_Quote_Admin', 'render_dashboard_widget']
    );
});

// Handle Public Customer Quote Acceptance / Decline Token Endpoint (Phase 3.1)
add_action('template_redirect', function() {
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

        $new_status = ($action === 'accept_quote') ? 'approved' : 'rejected';
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $wpdb->update(
            $table,
            [
                'status'            => $new_status,
                'acceptance_token'  => '',
                'token_expiry'      => null,
            ],
            ['id' => $quote->id]
        );

        $note = ($action === 'accept_quote') ? 'Customer ACCEPTED quote offer via public link' : 'Customer DECLINED quote offer via public link';
        Evonee_Quote_Ajax::log_activity($quote->id, 'customer_response', $note);

        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>Quote Response — Evonee</title>
            <style>
                body { font-family: 'Segoe UI', Arial, sans-serif; background: #f8fafc; color: #1e1b2e; display: flex; align-items: center; justify-content: center; height: 100vh; margin: 0; }
                .card { background: #ffffff; padding: 40px; border-radius: 12px; border: 1px solid #e2e8f0; max-width: 500px; text-align: center; box-shadow: 0 10px 25px rgba(0,0,0,0.05); }
                h1 { color: <?php echo esc_attr($action === 'accept_quote' ? '#16a34a' : '#dc2626'); ?>; font-size: 24px; margin-top: 0; }
                p { color: #64748b; font-size: 15px; line-height: 1.6; }
                .btn { display: inline-block; margin-top: 20px; background: #6d28d9; color: #fff; text-decoration: none; padding: 10px 20px; border-radius: 6px; font-weight: 700; }
            </style>
        </head>
        <body>
            <div class="card">
                <h1><?php echo $action === 'accept_quote' ? '🎉 Quote Offer Accepted!' : 'Offer Response Received'; ?></h1>
                <p><?php echo $action === 'accept_quote' ? 'Thank you for approving your quote request for <strong>' . esc_html($quote->product) . '</strong>. Our team will contact you shortly to begin production.' : 'Thank you for letting us know. We have updated your quote request status.'; ?></p>
                <a href="<?php echo esc_url(home_url()); ?>" class="btn">Return to Homepage</a>
            </div>
        </body>
        </html>
        <?php
        exit;
    }
});
