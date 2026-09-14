<?php
if (!defined('ABSPATH')) {
    exit;
}

class Evonee_Quote_Admin {

    public static function init() {
        $instance = new self();
        add_action('admin_menu', [$instance, 'add_admin_menu']);
        add_action('admin_enqueue_scripts', [$instance, 'enqueue_admin_assets']);
    }

    public function enqueue_admin_assets($hook) {
        if (strpos($hook, 'evonee') === false) {
            return;
        }
        wp_enqueue_style('evonee-admin-css', EVONEE_PLUGIN_URL . 'assets/css/admin.css', [], EVONEE_VERSION);

        if (strpos($hook, 'evonee-analytics') !== false || strpos($hook, 'evonee-quotes') !== false || strpos($hook, 'evonee-dashboard') !== false) {
            wp_enqueue_script('chartjs', EVONEE_PLUGIN_URL . 'assets/js/chart.min.js', [], '4.4.1', true);
        }
    }

    public function add_admin_menu() {
        add_menu_page(
            'Evonee Quotes',
            'Evonee Quotes',
            'manage_options',
            'evonee-quotes',
            [$this, 'render_dashboard_page'],
            'dashicons-clipboard',
            30
        );

        add_submenu_page(
            'evonee-quotes',
            'Executive Dashboard',
            'Dashboard',
            'manage_options',
            'evonee-quotes',
            [$this, 'render_dashboard_page']
        );

        add_submenu_page(
            'evonee-quotes',
            'Quote Submissions',
            'Submissions',
            'manage_options',
            'evonee-submissions',
            [$this, 'render_submissions_page']
        );

        $settings = self::get_settings();
        if (!isset($settings['enable_analytics']) || $settings['enable_analytics'] === '1') {
            add_submenu_page(
                'evonee-quotes',
                'Analytics & Reports',
                'Analytics & Reports',
                'manage_options',
                'evonee-analytics',
                [$this, 'render_analytics_page']
            );
        }

        add_submenu_page(
            'evonee-quotes',
            'Settings & Modules',
            'Settings & Modules',
            'manage_options',
            'evonee-settings',
            [$this, 'render_settings_page']
        );

        add_submenu_page(
            'evonee-quotes',
            'Documentation & Help',
            'Documentation & Help',
            'manage_options',
            'evonee-docs',
            [$this, 'render_docs_page']
        );
    }

    /**
     * Get Plugin Options & Module Settings with Defaults
     */
    public static function get_settings() {
        $defaults = [
            'sales_email'              => 'sales@evonee.com',
            'currency_symbol'          => '$',
            'enable_price_calc'        => '1',
            'enable_pdf_quote'         => '1',
            'enable_analytics'         => '1',
            'enable_auto_reply'        => '1',
            'enable_wc_auto'           => '1',
            'show_field_company'       => '1',
            'show_field_text_specs'    => '1',
            'show_field_specific_date' => '1',
            'show_field_project_notes' => '1',
            // Email Template Builder (Phase 1.4)
            'email_logo_url'           => '',
            'email_header_color'       => '#6d28d9',
            'email_brand_name'         => 'Evonee',
            'email_footer_text'        => 'Evonee Promotional Products • sales@evonee.com',
            // Phase 3 Settings
            'enable_recaptcha'         => '0',
            'recaptcha_site_key'       => '',
            'recaptcha_secret_key'     => '',
            'enable_webhook'           => '0',
            'webhook_url'              => '',
            'enable_slack'             => '0',
            'slack_webhook_url'        => '',
            'enable_wc_quote_only'     => '0',
            'wc_quote_condition'       => 'all',
            'wc_hide_price'            => '0',
            'enable_cart_quote'        => '0',
            'products_grid_limit'      => '12',
            'show_products_title'      => '0',
            // Step 3 & 4 Enterprise Settings
            'pdf_company_name'         => 'Evonee Promotional Products',
            'pdf_tax_id'               => '',
            'pdf_accent_color'         => '#6d28d9',
            'pdf_terms_text'           => "1. Includes Free Digital Proof & Mockup preview before production.\n2. Price offer valid for 30 days from quote issue date.\n3. Standard production & delivery timeline applies upon artwork approval.",
            'enable_tiered_pricing'    => '1',
            'tiered_price_breaks'      => "50|5.00\n100|4.50\n500|3.80\n1000|3.20",
        ];

        $saved = get_option('evonee_quote_settings', []);
        return wp_parse_args($saved, $defaults);
    }

    /**
     * Dashboard Widget: Quick stats summary
     */
    public static function render_dashboard_widget() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'eq_quote_submissions';
        $today      = current_time('Y-m-d');

        // Check table exists
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_name)) !== $table_name) {
            echo '<p>Plugin table not found. Please deactivate and reactivate Evonee.</p>';
            return;
        }

        $stats = get_transient('evonee_dashboard_stats');
        if (false === $stats) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $today_count = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}eq_quote_submissions WHERE DATE(created_at) = %s", $today));
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $new_count   = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}eq_quote_submissions WHERE status = %s", 'new'));
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $quoted      = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}eq_quote_submissions WHERE status = %s", 'quoted'));
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $completed   = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}eq_quote_submissions WHERE status = %s", 'completed'));
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $total       = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}eq_quote_submissions");

            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $recent = $wpdb->get_results($wpdb->prepare(
                "SELECT id, full_name, product, status, created_at FROM {$wpdb->prefix}eq_quote_submissions WHERE DATE(created_at) = %s ORDER BY id DESC LIMIT 5",
                $today
            ));

            $stats = compact('today_count', 'new_count', 'quoted', 'completed', 'total', 'recent');
            set_transient('evonee_dashboard_stats', $stats, 10 * MINUTE_IN_SECONDS);
        } else {
            $today_count = $stats['today_count'];
            $new_count   = $stats['new_count'];
            $quoted      = $stats['quoted'];
            $completed   = $stats['completed'];
            $total       = $stats['total'];
            $recent      = $stats['recent'];
        }

        $status_colors = ['new' => '#16a34a', 'pending' => '#d97706', 'quoted' => '#2563eb', 'approved' => '#7c3aed', 'completed' => '#059669', 'rejected' => '#dc2626'];
        ?>
        <div style="font-family: -apple-system, sans-serif;">
            <!-- Stats Row -->
            <div style="display:grid; grid-template-columns:repeat(4,1fr); gap:10px; margin-bottom:14px;">
                <div style="background:#faf5ff; border:1px solid #e9d5ff; border-radius:8px; padding:10px; text-align:center;">
                    <div style="font-size:22px; font-weight:800; color:#6d28d9;"><?php echo esc_html($today_count); ?></div>
                    <div style="font-size:11px; color:#6b7280; margin-top:2px;">Today</div>
                </div>
                <div style="background:#f0fdf4; border:1px solid #bbf7d0; border-radius:8px; padding:10px; text-align:center;">
                    <div style="font-size:22px; font-weight:800; color:#16a34a;"><?php echo esc_html($new_count); ?></div>
                    <div style="font-size:11px; color:#6b7280; margin-top:2px;">New</div>
                </div>
                <div style="background:#eff6ff; border:1px solid #bfdbfe; border-radius:8px; padding:10px; text-align:center;">
                    <div style="font-size:22px; font-weight:800; color:#2563eb;"><?php echo esc_html($quoted); ?></div>
                    <div style="font-size:11px; color:#6b7280; margin-top:2px;">Quoted</div>
                </div>
                <div style="background:#f0fdf4; border:1px solid #a7f3d0; border-radius:8px; padding:10px; text-align:center;">
                    <div style="font-size:22px; font-weight:800; color:#059669;"><?php echo esc_html($completed); ?></div>
                    <div style="font-size:11px; color:#6b7280; margin-top:2px;">Completed</div>
                </div>
            </div>

            <!-- Today's Submissions -->
            <?php if (!empty($recent)): ?>
                <p style="font-size:12px; font-weight:700; color:#374151; margin:0 0 6px;">📋 Today's Submissions:</p>
                <ul style="margin:0; padding:0; list-style:none;">
                    <?php foreach ($recent as $r):
                        $sc = isset($status_colors[strtolower($r->status)]) ? $status_colors[strtolower($r->status)] : '#6b7280';
                    ?>
                        <li style="display:flex; justify-content:space-between; align-items:center; padding:5px 0; border-bottom:1px solid #f1f5f9; font-size:12px;">
                            <span><strong>#<?php echo esc_html($r->id); ?></strong> <?php echo esc_html($r->full_name); ?> — <em><?php echo esc_html($r->product); ?></em></span>
                            <span style="background:<?php echo esc_attr($sc); ?>; color:#fff; border-radius:4px; padding:2px 7px; font-size:10px; font-weight:700; text-transform:uppercase;"><?php echo esc_html($r->status ?: 'new'); ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <p style="color:#9ca3af; font-size:12px; text-align:center; margin:8px 0;">No submissions today yet.</p>
            <?php endif; ?>

            <!-- Footer -->
            <div style="margin-top:12px; display:flex; justify-content:space-between; align-items:center;">
                <span style="font-size:11px; color:#9ca3af;">Total: <strong><?php echo esc_html($total); ?></strong> submissions</span>
                <a href="<?php echo esc_url(admin_url('admin.php?page=evonee-submissions')); ?>" class="button button-primary button-small" style="background:#6d28d9; border-color:#6d28d9;">View All &rarr;</a>
            </div>
        </div>
        <?php
    }

    /**
     * Render Evonee Executive Dashboard Page (v3.1 Command Center)
     */
    public function render_dashboard_page() {
        // Fallback for direct query links to render Submissions page seamlessly
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if (!empty($_GET['s']) || !empty($_GET['status_filter']) || !empty($_GET['action']) || !empty($_GET['paged']) || isset($_GET['id']) || (isset($_GET['view']) && $_GET['view'] === 'submissions')) {
            $this->render_submissions_page();
            return;
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'eq_quote_submissions';

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_name)) !== $table_name) {
            Evonee_Quote_Ajax::create_submissions_table();
        }

        $stats = get_transient('evonee_dashboard_stats');
        if (false === $stats) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $total_count     = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}eq_quote_submissions");
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $new_count       = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}eq_quote_submissions WHERE status = %s", 'new'));
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $pending_count   = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}eq_quote_submissions WHERE status = %s", 'pending'));
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $quoted_count    = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}eq_quote_submissions WHERE status = %s", 'quoted'));
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $approved_count  = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}eq_quote_submissions WHERE status = %s", 'approved'));
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $completed_count = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}eq_quote_submissions WHERE status = %s", 'completed'));

            // Total Quoted Revenue Value ($)
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $total_pipeline_val = (float) $wpdb->get_var("SELECT SUM(quoted_price) FROM {$wpdb->prefix}eq_quote_submissions WHERE quoted_price IS NOT NULL AND quoted_price > 0");

            // Overdue / Urgent Action Needed Count
            $today = current_time('Y-m-d');
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $urgent_count = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}eq_quote_submissions WHERE (status IN ('new', 'pending') AND created_at <= %s) OR (follow_up_date IS NOT NULL AND follow_up_date <= %s)",
                wp_date('Y-m-d H:i:s', time() - (2 * DAY_IN_SECONDS)),
                $today
            ));

            // Recent 5 Submissions
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $recent_quotes = $wpdb->get_results("SELECT id, full_name, email, product, quantity, quoted_price, status, created_at FROM {$wpdb->prefix}eq_quote_submissions ORDER BY id DESC LIMIT 5");

            // Monthly Trends (Last 6 Months)
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $monthly_trends = $wpdb->get_results("
                SELECT DATE_FORMAT(created_at, '%b %Y') as month_label, COUNT(*) as total 
                FROM {$wpdb->prefix}eq_quote_submissions 
                GROUP BY DATE_FORMAT(created_at, '%Y-%m') 
                ORDER BY created_at ASC LIMIT 6
            ");

            // Top Requested Products (Top 3)
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $top_products = $wpdb->get_results("
                SELECT product, COUNT(*) as total
                FROM {$wpdb->prefix}eq_quote_submissions
                GROUP BY product
                ORDER BY total DESC
                LIMIT 3
            ");

            $stats = compact('total_count', 'new_count', 'pending_count', 'quoted_count', 'approved_count', 'completed_count', 'total_pipeline_val', 'urgent_count', 'recent_quotes', 'monthly_trends', 'top_products');
            set_transient('evonee_dashboard_stats', $stats, 10 * MINUTE_IN_SECONDS);
        } else {
            $total_count        = $stats['total_count'];
            $new_count          = $stats['new_count'];
            $pending_count      = $stats['pending_count'];
            $quoted_count       = $stats['quoted_count'];
            $approved_count     = $stats['approved_count'];
            $completed_count    = $stats['completed_count'];
            $total_pipeline_val = $stats['total_pipeline_val'];
            $urgent_count       = $stats['urgent_count'];
            $recent_quotes      = $stats['recent_quotes'];
            $monthly_trends     = $stats['monthly_trends'];
            $top_products       = $stats['top_products'];
        }

        $won_deals = $approved_count + $completed_count;
        $win_rate  = $total_count > 0 ? round(($won_deals / $total_count) * 100, 1) : 0;
        $settings  = self::get_settings();
        $curr_sym  = esc_html($settings['currency_symbol'] ?? '$');

        // System Health Diagnostics Checks
        $cron_active     = wp_next_scheduled('evonee_daily_quote_cron') ? true : false;
        $wc_active       = class_exists('WooCommerce');
        $recaptcha_ready = (!empty($settings['enable_recaptcha']) && !empty($settings['recaptcha_site_key']));
        $webhook_ready   = (!empty($settings['enable_webhook']) && !empty($settings['webhook_url']));
        $slack_ready     = (!empty($settings['enable_slack']) && !empty($settings['slack_webhook_url']));
        $user_name       = wp_get_current_user()->display_name ?: 'Admin';
        ?>
        <div class="wrap evonee-admin-wrap">
            <!-- Hero Header -->
            <div class="evonee-docs-header" style="background: linear-gradient(135deg, #0f172a 0%, #3b0764 50%, #6d28d9 100%); padding:28px 32px; border-radius:14px; margin-bottom:24px;">
                <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:16px;">
                    <div>
                        <h1 style="color:#ffffff; font-size:26px; font-weight:800; margin:0 0 6px; display:flex; align-items:center; gap:10px;">
                            ⚡ Evonee Executive Command Center
                        </h1>
                        <p class="subtitle" style="color:#cbd5e1; font-size:14px; margin:0;">
                            Welcome back, <strong><?php echo esc_html($user_name); ?></strong>! Overview of your B2B quotation pipeline, automated reminders, and revenue metrics.
                        </p>
                    </div>
                    <div class="evonee-docs-brand" style="text-align:right;">
                        <span style="background:rgba(255,255,255,0.15); backdrop-filter:blur(8px); color:#ffffff; padding:6px 14px; border-radius:20px; font-size:12px; font-weight:700; border:1px solid rgba(255,255,255,0.2);">
                            v1.0.0 Release Edition
                        </span>
                        <div style="font-size:11px; color:#cbd5e1; margin-top:6px;">📅 <?php echo esc_html(wp_date('F j, Y')); ?></div>
                    </div>
                </div>
            </div>

            <!-- Urgent Action Alert Banner -->
            <?php if ($urgent_count > 0): ?>
                <div class="evonee-dash-alert" style="background:#fff7ed; border:1px solid #ffedd5; border-left:4px solid #f97316; border-radius:10px; padding:14px 20px; margin-bottom:24px; display:flex; justify-content:space-between; align-items:center; gap:16px;">
                    <div style="display:flex; align-items:center; gap:12px;">
                        <span style="font-size:24px;">⚠️</span>
                        <div>
                            <strong style="color:#9a3412; font-size:14px;">Urgent Lead Action Required!</strong>
                            <p style="color:#c2410c; font-size:13px; margin:2px 0 0;">You have <strong><?php echo esc_html($urgent_count); ?></strong> quote request(s) awaiting response or overdue for follow-up.</p>
                        </div>
                    </div>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=evonee-submissions&date_range=followup_due')); ?>" class="button button-primary" style="background:#ea580c; border-color:#ea580c; font-weight:700; border-radius:6px; white-space:nowrap;">View Pending Quotes &rarr;</a>
                </div>
            <?php endif; ?>

            <!-- KPI Metric Cards Grid -->
            <div class="evonee-metrics-grid" style="display:grid; grid-template-columns:repeat(4, 1fr); gap:18px; margin-bottom:24px;">
                <div class="evonee-stat-card" style="background:#ffffff; border:1px solid #e2e8f0; border-radius:12px; padding:20px; display:flex; align-items:center; gap:16px; box-shadow:0 2px 4px rgba(0,0,0,0.02);">
                    <div class="evonee-stat-icon" style="background:#faf5ff; color:#6d28d9; font-size:24px; width:52px; height:52px; border-radius:12px; display:flex; align-items:center; justify-content:center; flex-shrink:0;">📥</div>
                    <div>
                        <div class="evonee-stat-val" style="font-size:26px; font-weight:800; color:#0f172a; line-height:1.2;"><?php echo esc_html(number_format($total_count)); ?></div>
                        <div class="evonee-stat-lbl" style="font-size:12px; color:#64748b; font-weight:600; margin-top:2px;">Total Quotes Received</div>
                    </div>
                </div>

                <div class="evonee-stat-card" style="background:#ffffff; border:1px solid #e2e8f0; border-radius:12px; padding:20px; display:flex; align-items:center; gap:16px; box-shadow:0 2px 4px rgba(0,0,0,0.02);">
                    <div class="evonee-stat-icon" style="background:#fff7ed; color:#ea580c; font-size:24px; width:52px; height:52px; border-radius:12px; display:flex; align-items:center; justify-content:center; flex-shrink:0;">⏳</div>
                    <div>
                        <div class="evonee-stat-val" style="font-size:26px; font-weight:800; color:#ea580c; line-height:1.2;"><?php echo esc_html(number_format($new_count + $pending_count)); ?></div>
                        <div class="evonee-stat-lbl" style="font-size:12px; color:#64748b; font-weight:600; margin-top:2px;">Action Needed (New / Pending)</div>
                    </div>
                </div>

                <div class="evonee-stat-card" style="background:#ffffff; border:1px solid #e2e8f0; border-radius:12px; padding:20px; display:flex; align-items:center; gap:16px; box-shadow:0 2px 4px rgba(0,0,0,0.02);">
                    <div class="evonee-stat-icon" style="background:#eff6ff; color:#2563eb; font-size:24px; width:52px; height:52px; border-radius:12px; display:flex; align-items:center; justify-content:center; flex-shrink:0;">💰</div>
                    <div>
                        <div class="evonee-stat-val" style="font-size:26px; font-weight:800; color:#2563eb; line-height:1.2;"><?php echo esc_html($curr_sym) . esc_html(number_format($total_pipeline_val, 2)); ?></div>
                        <div class="evonee-stat-lbl" style="font-size:12px; color:#64748b; font-weight:600; margin-top:2px;">Gross Quoted Pipeline</div>
                    </div>
                </div>

                <div class="evonee-stat-card" style="background:#ffffff; border:1px solid #e2e8f0; border-radius:12px; padding:20px; display:flex; align-items:center; gap:16px; box-shadow:0 2px 4px rgba(0,0,0,0.02);">
                    <div class="evonee-stat-icon" style="background:#f0fdf4; color:#16a34a; font-size:24px; width:52px; height:52px; border-radius:12px; display:flex; align-items:center; justify-content:center; flex-shrink:0;">🏆</div>
                    <div>
                        <div class="evonee-stat-val" style="font-size:26px; font-weight:800; color:#16a34a; line-height:1.2;"><?php echo esc_html(number_format($won_deals)); ?></div>
                        <div class="evonee-stat-lbl" style="font-size:12px; color:#64748b; font-weight:600; margin-top:2px;">Won Deals (<?php echo esc_html($win_rate); ?>% Win Rate)</div>
                    </div>
                </div>
            </div>

            <!-- Quick Action Launcher Grid -->
            <div class="evonee-doc-card" style="margin-bottom:24px;">
                <div class="evonee-card-header" style="padding:14px 20px; background:#f8fafc; border-bottom:1px solid #e2e8f0; border-top-left-radius:12px; border-top-right-radius:12px; display:flex; align-items:center; gap:8px;">
                    <span class="dashicons dashicons-external" style="color:#6d28d9;"></span>
                    <h2 style="font-size:14px; font-weight:700; color:#1e293b; margin:0;">Quick Launcher & Action Center</h2>
                </div>
                <div class="evonee-card-body" style="padding:18px 20px;">
                    <div style="display:grid; grid-template-columns:repeat(5, 1fr); gap:12px;">
                        <a href="<?php echo esc_url(admin_url('admin.php?page=evonee-submissions')); ?>" class="button" style="display:flex; align-items:center; justify-content:center; gap:8px; height:42px; background:#faf5ff; border:1px solid #e9d5ff; color:#6d28d9; font-weight:700; border-radius:8px;">
                            <span>📥 Submissions</span>
                        </a>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=evonee-analytics')); ?>" class="button" style="display:flex; align-items:center; justify-content:center; gap:8px; height:42px; background:#eff6ff; border:1px solid #bfdbfe; color:#1d4ed8; font-weight:700; border-radius:8px;">
                            <span>📊 Analytics</span>
                        </a>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=evonee-settings')); ?>" class="button" style="display:flex; align-items:center; justify-content:center; gap:8px; height:42px; background:#f0fdf4; border:1px solid #bbf7d0; color:#15803d; font-weight:700; border-radius:8px;">
                            <span>⚙️ Settings</span>
                        </a>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=evonee-docs')); ?>" class="button" style="display:flex; align-items:center; justify-content:center; gap:8px; height:42px; background:#fff7ed; border:1px solid #fed7aa; color:#c2410c; font-weight:700; border-radius:8px;">
                            <span>📖 Docs & Guide</span>
                        </a>
                        <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin.php?page=evonee-submissions&action=export_csv'), 'eq_export_csv_nonce')); ?>" class="button" style="display:flex; align-items:center; justify-content:center; gap:8px; height:42px; background:#f8fafc; border:1px solid #cbd5e1; color:#334155; font-weight:700; border-radius:8px;">
                            <span>📥 Export CSV</span>
                        </a>
                    </div>
                </div>
            </div>

            <!-- Two-Column Main Body -->
            <div style="display:grid; grid-template-columns:2fr 1fr; gap:24px;">
                <!-- Left Column -->
                <div>
                    <!-- Recent Submissions Card -->
                    <div class="evonee-doc-card" style="margin-bottom:24px;">
                        <div class="evonee-card-header" style="padding:14px 20px; background:#f8fafc; border-bottom:1px solid #e2e8f0; border-top-left-radius:12px; border-top-right-radius:12px; display:flex; justify-content:space-between; align-items:center;">
                            <div style="display:flex; align-items:center; gap:8px;">
                                <span class="dashicons dashicons-list-view" style="color:#6d28d9;"></span>
                                <h2 style="font-size:14px; font-weight:700; color:#1e293b; margin:0;">📋 Recent Quote Submissions</h2>
                            </div>
                            <a href="<?php echo esc_url(admin_url('admin.php?page=evonee-submissions')); ?>" style="font-size:12px; font-weight:700; color:#6d28d9; text-decoration:none;">View All Submissions &rarr;</a>
                        </div>
                        <div class="evonee-card-body" style="padding:0;">
                            <?php if (!empty($recent_quotes)): ?>
                                <table class="wp-list-table widefat fixed striped" style="border:none; box-shadow:none;">
                                    <thead>
                                        <tr>
                                            <th style="font-size:11px; text-transform:uppercase; color:#64748b;">ID / Date</th>
                                            <th style="font-size:11px; text-transform:uppercase; color:#64748b;">Customer</th>
                                            <th style="font-size:11px; text-transform:uppercase; color:#64748b;">Product</th>
                                            <th style="font-size:11px; text-transform:uppercase; color:#64748b;">Status</th>
                                            <th style="font-size:11px; text-transform:uppercase; color:#64748b; text-align:right;">Quoted Price</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($recent_quotes as $q):
                                            $sc_colors = ['new' => '#16a34a', 'pending' => '#d97706', 'quoted' => '#2563eb', 'approved' => '#7c3aed', 'completed' => '#059669', 'rejected' => '#dc2626'];
                                            $sc_bg = isset($sc_colors[strtolower($q->status)]) ? $sc_colors[strtolower($q->status)] : '#6b7280';
                                        ?>
                                            <tr>
                                                <td>
                                                    <strong>#<?php echo esc_html($q->id); ?></strong><br>
                                                    <small style="color:#94a3b8;"><?php echo esc_html(wp_date('M j, H:i', strtotime($q->created_at))); ?></small>
                                                </td>
                                                <td>
                                                    <strong><?php echo esc_html($q->full_name); ?></strong><br>
                                                    <small style="color:#64748b;"><?php echo esc_html($q->email); ?></small>
                                                </td>
                                                <td>
                                                    <?php echo esc_html($q->product); ?><br>
                                                    <small style="color:#64748b;">Qty: <?php echo esc_html($q->quantity); ?></small>
                                                </td>
                                                <td>
                                                    <span style="background:<?php echo esc_attr($sc_bg); ?>; color:#ffffff; border-radius:4px; padding:3px 8px; font-size:10px; font-weight:700; text-transform:uppercase;">
                                                        <?php echo esc_html($q->status ?: 'new'); ?>
                                                    </span>
                                                </td>
                                                <td style="text-align:right; font-weight:700; color:#0f172a;">
                                                    <?php echo (!empty($q->quoted_price) && floatval($q->quoted_price) > 0) ? esc_html($curr_sym) . number_format($q->quoted_price, 2) : '<span style="color:#94a3b8; font-weight:normal;">Unquoted</span>'; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            <?php else: ?>
                                <p style="padding:20px; text-align:center; color:#94a3b8; margin:0;">No quote submissions received yet.</p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Mini Trend Graph Card -->
                    <div class="evonee-doc-card">
                        <div class="evonee-card-header" style="padding:14px 20px; background:#f8fafc; border-bottom:1px solid #e2e8f0; border-top-left-radius:12px; border-top-right-radius:12px; display:flex; align-items:center; gap:8px;">
                            <span class="dashicons dashicons-chart-bar" style="color:#6d28d9;"></span>
                            <h2 style="font-size:14px; font-weight:700; color:#1e293b; margin:0;">📈 6-Month Lead Growth Trend</h2>
                        </div>
                        <div class="evonee-card-body" style="padding:20px;">
                            <canvas id="dashTrendChart" style="max-height:220px;"></canvas>
                        </div>
                    </div>
                </div>

                <!-- Right Column -->
                <div>
                    <!-- System Health Diagnostic Card -->
                    <div class="evonee-doc-card" style="margin-bottom:24px;">
                        <div class="evonee-card-header" style="padding:14px 20px; background:#f8fafc; border-bottom:1px solid #e2e8f0; border-top-left-radius:12px; border-top-right-radius:12px; display:flex; align-items:center; gap:8px;">
                            <span class="dashicons dashicons-heart" style="color:#6d28d9;"></span>
                            <h2 style="font-size:14px; font-weight:700; color:#1e293b; margin:0;">🩺 System Health & Integrations</h2>
                        </div>
                        <div class="evonee-card-body" style="padding:16px 20px;">
                            <ul style="margin:0; padding:0; list-style:none;">
                                <li style="display:flex; justify-content:space-between; align-items:center; padding:10px 0; border-bottom:1px solid #f1f5f9; font-size:13px;">
                                    <span>⏱️ <strong>WP-Cron Expiry Automation</strong></span>
                                    <?php if ($cron_active): ?>
                                        <span style="background:#dcfce7; color:#15803d; border-radius:12px; padding:2px 10px; font-size:11px; font-weight:700;">Active ✓</span>
                                    <?php else: ?>
                                        <span style="background:#fee2e2; color:#b91c1c; border-radius:12px; padding:2px 10px; font-size:11px; font-weight:700;">Inactive ⚠</span>
                                    <?php endif; ?>
                                </li>
                                <li style="display:flex; justify-content:space-between; align-items:center; padding:10px 0; border-bottom:1px solid #f1f5f9; font-size:13px;">
                                    <span>🛒 <strong>WooCommerce Engine</strong></span>
                                    <?php if ($wc_active): ?>
                                        <span style="background:#dcfce7; color:#15803d; border-radius:12px; padding:2px 10px; font-size:11px; font-weight:700;">Connected ✓</span>
                                    <?php else: ?>
                                        <span style="background:#f1f5f9; color:#64748b; border-radius:12px; padding:2px 10px; font-size:11px; font-weight:700;">Not Installed</span>
                                    <?php endif; ?>
                                </li>
                                <li style="display:flex; justify-content:space-between; align-items:center; padding:10px 0; border-bottom:1px solid #f1f5f9; font-size:13px;">
                                    <span>🛡️ <strong>reCAPTCHA v3 Protection</strong></span>
                                    <?php if ($recaptcha_ready): ?>
                                        <span style="background:#dcfce7; color:#15803d; border-radius:12px; padding:2px 10px; font-size:11px; font-weight:700;">Protected ✓</span>
                                    <?php else: ?>
                                        <span style="background:#fef3c7; color:#b45309; border-radius:12px; padding:2px 10px; font-size:11px; font-weight:700;">Disabled</span>
                                    <?php endif; ?>
                                </li>
                                <li style="display:flex; justify-content:space-between; align-items:center; padding:10px 0; border-bottom:1px solid #f1f5f9; font-size:13px;">
                                    <span>🔗 <strong>Webhook / Zapier Catch</strong></span>
                                    <?php if ($webhook_ready): ?>
                                        <span style="background:#dcfce7; color:#15803d; border-radius:12px; padding:2px 10px; font-size:11px; font-weight:700;">Connected ✓</span>
                                    <?php else: ?>
                                        <span style="background:#f1f5f9; color:#64748b; border-radius:12px; padding:2px 10px; font-size:11px; font-weight:700;">Off</span>
                                    <?php endif; ?>
                                </li>
                                <li style="display:flex; justify-content:space-between; align-items:center; padding:10px 0; font-size:13px;">
                                    <span>💬 <strong>Slack Lead Channel</strong></span>
                                    <?php if ($slack_ready): ?>
                                        <span style="background:#dcfce7; color:#15803d; border-radius:12px; padding:2px 10px; font-size:11px; font-weight:700;">Connected ✓</span>
                                    <?php else: ?>
                                        <span style="background:#f1f5f9; color:#64748b; border-radius:12px; padding:2px 10px; font-size:11px; font-weight:700;">Off</span>
                                    <?php endif; ?>
                                </li>
                            </ul>
                        </div>
                    </div>

                    <!-- Top Requested Products Widget -->
                    <div class="evonee-doc-card">
                        <div class="evonee-card-header" style="padding:14px 20px; background:#f8fafc; border-bottom:1px solid #e2e8f0; border-top-left-radius:12px; border-top-right-radius:12px; display:flex; align-items:center; gap:8px;">
                            <span class="dashicons dashicons-star-filled" style="color:#d97706;"></span>
                            <h2 style="font-size:14px; font-weight:700; color:#1e293b; margin:0;">🔥 Top Demanded Products</h2>
                        </div>
                        <div class="evonee-card-body" style="padding:16px 20px;">
                            <?php if (!empty($top_products)): ?>
                                <?php foreach ($top_products as $tp):
                                    $pct = $total_count > 0 ? round(($tp->total / $total_count) * 100) : 0;
                                ?>
                                    <div style="margin-bottom:14px;">
                                        <div style="display:flex; justify-content:space-between; font-size:12px; margin-bottom:4px;">
                                            <strong><?php echo esc_html($tp->product); ?></strong>
                                            <span style="color:#64748b; font-weight:600;"><?php echo esc_html($tp->total); ?> quotes (<?php echo esc_html($pct); ?>%)</span>
                                        </div>
                                        <div style="background:#f1f5f9; border-radius:10px; height:8px; overflow:hidden;">
                                            <div style="background:linear-gradient(90deg, #6d28d9 0%, #7c3aed 100%); height:100%; width:<?php echo esc_attr($pct); ?>%; border-radius:10px;"></div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <p style="color:#94a3b8; font-size:12px; text-align:center; margin:0;">No product data available yet.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <script>
        document.addEventListener('DOMContentLoaded', function() {
            const trendCtx = document.getElementById('dashTrendChart');
            if (trendCtx) {
                const trendLabels = <?php echo json_encode(array_column($monthly_trends, 'month_label') ?: ['Current']); ?>;
                const trendData   = <?php echo json_encode(array_map('intval', array_column($monthly_trends, 'total')) ?: [$total_count]); ?>;

                new Chart(trendCtx, {
                    type: 'line',
                    data: {
                        labels: trendLabels,
                        datasets: [{
                            label: 'Quote Submissions',
                            data: trendData,
                            borderColor: '#6d28d9',
                            backgroundColor: 'rgba(109, 40, 217, 0.08)',
                            borderWidth: 3,
                            fill: true,
                            tension: 0.3,
                            pointBackgroundColor: '#6d28d9',
                            pointRadius: 4
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: { legend: { display: false } },
                        scales: {
                            y: { beginAtZero: true, grid: { color: '#f1f5f9' } },
                            x: { grid: { display: false } }
                        }
                    }
                });
            }
        });
        </script>
        <?php
    }

    /**
     * Render Quote Submissions Viewer Page (Full CRM Submissions Suite)
     */
    public function render_submissions_page() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'eq_quote_submissions';

        // Check if table exists
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_name)) !== $table_name) {
            Evonee_Quote_Ajax::create_submissions_table();
        }

        // Handle CSV Export
        if (isset($_GET['action']) && $_GET['action'] === 'export_csv') {
            if (!current_user_can('manage_options')) {
                wp_die('Unauthorized action.');
            }
            if (!check_admin_referer('eq_export_csv_nonce')) {
                wp_die('Security check failed.');
            }

            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $rows = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}eq_quote_submissions ORDER BY id DESC", ARRAY_A);

            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename=evonee_quote_submissions_' . gmdate('Y-m-d_H-i') . '.csv');

            $output = fopen('php://output', 'w');

            // UTF-8 BOM for Excel compatibility
            fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

            // Column Headers
            fputcsv($output, ['ID', 'Date', 'Customer Name', 'Company', 'Email', 'Phone', 'Country', 'Product', 'Quantity', 'Product Details', 'Delivery Timeframe', 'Need Date', 'ZIP Code', 'Artwork URL', 'Project Notes', 'Quoted Price', 'Status']);

            if (!empty($rows)) {
                foreach ($rows as $row) {
                    $art_urls = Evonee_Quote_Ajax::parse_artwork_urls($row['artwork_url'] ?? '');
                    fputcsv($output, [
                        $row['id'],
                        $row['created_at'],
                        $row['full_name'],
                        $row['company'],
                        $row['email'],
                        $row['phone'],
                        $row['country'],
                        $row['product'],
                        $row['quantity'],
                        Evonee_Quote_Ajax::format_product_details_text($row['product_details'] ?? ''),
                        $row['timeframe'],
                        $row['specific_date'],
                        $row['zip_code'],
                        implode(' | ', $art_urls),
                        $row['project_notes'],
                        $row['quoted_price'] ?? '',
                        strtoupper(isset($row['status']) ? $row['status'] : 'new')
                    ]);
                }
            }

            // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
            fclose($output);
            exit;
        }

        // Handle PDF Quote Sheet Generation & Printing
        if (isset($_GET['action'], $_GET['id']) && $_GET['action'] === 'generate_pdf_quote') {
            if (!current_user_can('manage_options')) {
                wp_die('Unauthorized action.');
            }
            if (!check_admin_referer('eq_generate_pdf_' . intval($_GET['id']))) {
                wp_die('Security check failed.');
            }

            $quote_id = intval($_GET['id']);
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $quote = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}eq_quote_submissions WHERE id = %d", $quote_id));

            if (!$quote) {
                wp_die('Quote submission not found.');
            }

            ?>
            <!DOCTYPE html>
            <html>
            <head>
                <meta charset="UTF-8">
                <title>Evonee Official Quote Sheet #<?php echo esc_html($quote->id); ?></title>
                <style>
                    body { font-family: 'Segoe UI', Arial, sans-serif; background: #f8fafc; color: #1e1b2e; margin: 0; padding: 40px; }
                    .pdf-wrap { max-width: 800px; margin: 0 auto; background: #ffffff; padding: 40px; border-radius: 12px; border: 1px solid #e2e8f0; box-shadow: 0 10px 25px rgba(0,0,0,0.05); }
                    .pdf-header { display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 2px solid #6d28d9; padding-bottom: 20px; margin-bottom: 30px; }
                    .pdf-logo { font-size: 28px; font-weight: 800; color: #6d28d9; }
                    .pdf-meta { text-align: right; font-size: 13px; color: #64748b; }
                    .pdf-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 30px; margin-bottom: 30px; }
                    .pdf-card { background: #faf9fd; border: 1px solid #e5e0f5; padding: 18px; border-radius: 8px; }
                    .pdf-card h3 { margin: 0 0 10px; font-size: 14px; color: #4c1d95; text-transform: uppercase; letter-spacing: 0.05em; }
                    .pdf-table { width: 100%; border-collapse: collapse; margin-bottom: 30px; }
                    .pdf-table th { background: #6d28d9; color: #ffffff; text-align: left; padding: 12px; font-size: 13px; }
                    .pdf-table td { padding: 12px; border-bottom: 1px solid #e2e8f0; font-size: 13px; }
                    .pdf-terms { background: #f1f5f9; padding: 16px; border-radius: 8px; font-size: 12px; color: #64748b; margin-top: 30px; }
                    .pdf-actions { margin-bottom: 20px; text-align: right; }
                    .btn-print { background: #6d28d9; color: #fff; padding: 10px 20px; border: none; border-radius: 6px; font-size: 14px; font-weight: 700; cursor: pointer; text-decoration: none; }
                    @media print { .pdf-actions { display: none; } body { padding: 0; background: #fff; } .pdf-wrap { border: none; box-shadow: none; } }
                </style>
            </head>
            <body>
                <div class="pdf-actions">
                    <button type="button" class="btn-print" onclick="window.print();">🖨️ Print / Save as PDF</button>
                </div>
                <div class="pdf-wrap">
                    <div class="pdf-header">
                        <?php
                        $pdf_settings = self::get_settings();
                        $pdf_logo = !empty($pdf_settings['email_logo_url']) ? esc_url($pdf_settings['email_logo_url']) : '';
                        $pdf_brand = !empty($pdf_settings['email_brand_name']) ? esc_html($pdf_settings['email_brand_name']) : 'EVONEE';
                        ?>
                        <div>
                            <?php if (!empty($pdf_logo)): ?>
                                <img src="<?php echo esc_url($pdf_logo); ?>" alt="<?php echo esc_attr($pdf_brand); ?>" style="max-height:50px; display:block; margin-bottom:6px;">
                            <?php else: ?>
                                <div class="pdf-logo"><?php echo esc_html(strtoupper($pdf_brand)); ?></div>
                            <?php endif; ?>
                            <div style="font-size:12px; color:#64748b; margin-top:4px;"><?php echo esc_html($pdf_brand); ?> Promotional Products & Custom Goods</div>
                        </div>
                        <div class="pdf-meta">
                            <strong style="font-size:16px; color:#1e1b2e;">OFFICIAL QUOTE #<?php echo esc_html($quote->id); ?></strong><br>
                            Date: <?php echo esc_html(wp_date('F d, Y', strtotime($quote->created_at))); ?><br>
                            Valid Until: <?php echo esc_html(wp_date('F d, Y', strtotime('+30 days', strtotime($quote->created_at)))); ?>
                        </div>
                    </div>

                    <div class="pdf-grid">
                        <div class="pdf-card">
                            <h3>Bill To (Customer Information)</h3>
                            <strong><?php echo esc_html($quote->full_name); ?></strong><br>
                            <?php if (!empty($quote->company)): ?>Company: <?php echo esc_html($quote->company); ?><br><?php endif; ?>
                            Email: <?php echo esc_html($quote->email); ?><br>
                            Phone: <?php echo esc_html($quote->phone); ?><br>
                            Country: <?php echo esc_html($quote->country); ?><br>
                            ZIP Code: <?php echo esc_html($quote->zip_code); ?>
                        </div>
                        <div class="pdf-card">
                            <h3>Quotation Details</h3>
                            Product: <strong><?php echo esc_html($quote->product); ?></strong><br>
                            Quantity: <strong><?php echo esc_html($quote->quantity); ?></strong><br>
                            Delivery Timeframe: <?php echo esc_html($quote->timeframe); ?><br>
                            <?php if (!empty($quote->specific_date)): ?>Need By Date: <strong><?php echo esc_html($quote->specific_date); ?></strong><br><?php endif; ?>
                            Status: <span style="color:#6d28d9; font-weight:700; text-transform:uppercase;"><?php echo esc_html($quote->status ?: 'new'); ?></span>
                            <?php
                            $pdf_details = Evonee_Quote_Ajax::parse_product_details($quote->product_details ?? '');
                            if (!empty($pdf_details)):
                                foreach ($pdf_details as $dkey => $dval):
                                    if ($dval === '' || $dval === null) {
                                        continue;
                                    }
                                    $dlabel = ucwords(str_replace('_', ' ', (string) $dkey));
                                    $dvalue = is_array($dval) ? implode(', ', $dval) : (string) $dval;
                            ?>
                            <br><?php echo esc_html($dlabel); ?>: <?php echo esc_html($dvalue); ?>
                            <?php
                                endforeach;
                            endif;
                            ?>
                        </div>
                    </div>

                    <table class="pdf-table">
                        <thead>
                            <tr>
                                <th>Item Description</th>
                                <th>Quantity</th>
                                <th>Timeframe</th>
                                <th>Status</th>
                                <th>Price Offer</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>
                                    <strong>Custom <?php echo esc_html($quote->product); ?></strong>
                                    <?php if (!empty($quote->project_notes)): ?>
                                        <br><small style="color:#64748b;"><?php echo esc_html($quote->project_notes); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo esc_html($quote->quantity); ?></td>
                                <td><?php echo esc_html($quote->timeframe); ?></td>
                                <td><?php echo esc_html(strtoupper($quote->status ?: 'NEW')); ?></td>
                                <td><strong><?php echo (!empty($quote->quoted_price) && floatval($quote->quoted_price) > 0) ? '$' . number_format($quote->quoted_price, 2) : 'Included in Offer'; ?></strong></td>
                            </tr>
                        </tbody>
                    </table>

                    <div class="pdf-terms">
                        <strong>📌 Terms & Conditions:</strong><br>
                        1. Includes Free Digital Proof & Mockup preview before production.<br>
                        2. Price offer valid for 30 days from quote issue date.<br>
                        3. Standard production & delivery timeline applies upon artwork approval.
                    </div>

                    <?php if (!empty($quote->digital_signature)): ?>
                        <div style="margin-top:20px; padding:12px; border:1px dashed #cbd5e1; border-radius:8px; display:inline-block; background:#fafafa;">
                            <strong style="font-size:11px; color:#475569; display:block;">✍️ Customer Acceptance Signature:</strong>
                            <img src="<?php echo esc_url($quote->digital_signature); ?>" style="max-height:60px; margin-top:4px;">
                        </div>
                    <?php endif; ?>
                </div>
            </body>
            </html>
            <?php
            exit;
        }

        // Handle Single Deletion
        if (isset($_GET['action'], $_GET['id']) && $_GET['action'] === 'delete' && check_admin_referer('delete_quote_' . intval($_GET['id']))) {
            $delete_id = intval($_GET['id']);
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->delete($table_name, ['id' => $delete_id], ['%d']);
            wp_safe_redirect(admin_url('admin.php?page=evonee-submissions&eq_notice=deleted&eq_id=' . $delete_id));
            exit;
        }

        // Handle Status Update
        if (isset($_POST['eq_action'], $_POST['sub_id'], $_POST['status']) && $_POST['eq_action'] === 'update_status' && check_admin_referer('eq_update_status')) {
            $sub_id     = intval($_POST['sub_id']);
            $new_status = sanitize_text_field(wp_unslash($_POST['status']));
            $allowed_statuses = ['new', 'pending', 'quoted', 'approved', 'completed', 'rejected'];
            if (!in_array($new_status, $allowed_statuses, true)) {
                $new_status = 'new';
            }
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->update($table_name, ['status' => $new_status], ['id' => $sub_id], ['%s'], ['%d']);
            Evonee_Quote_Ajax::log_activity($sub_id, 'status_change', 'Status updated to ' . strtoupper($new_status));
            wp_safe_redirect(admin_url('admin.php?page=evonee-submissions&eq_notice=status&eq_id=' . $sub_id));
            exit;
        }

        // Handle Direct Customer Email Reply
        if (isset($_POST['eq_action'], $_POST['sub_id'], $_POST['customer_email'], $_POST['email_subject'], $_POST['email_body']) && $_POST['eq_action'] === 'send_customer_reply' && check_admin_referer('eq_send_reply')) {
            $sub_id         = intval($_POST['sub_id']);
            $customer_email = sanitize_email(wp_unslash($_POST['customer_email']));
            $subject        = sanitize_text_field(wp_unslash($_POST['email_subject']));
            $reply_body     = wp_kses_post(wp_unslash($_POST['email_body']));

            // Generate Token for Quote Acceptance (Phase 3.1)
            $token  = wp_generate_password(32, false);
            $expiry = gmdate('Y-m-d H:i:s', time() + (30 * DAY_IN_SECONDS));
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->update($table_name, ['acceptance_token' => $token, 'token_expiry' => $expiry], ['id' => $sub_id], ['%s', '%s'], ['%d']);

            $accept_url  = add_query_arg(['eq_action' => 'accept_quote', 'token' => $token], home_url());
            $decline_url = add_query_arg(['eq_action' => 'decline_quote', 'token' => $token], home_url());

            $sales_email = Evonee_Quote_Ajax::get_sales_email();
            $headers = [
                'Content-Type: text/html; charset=UTF-8',
                'From: Evonee Sales <' . $sales_email . '>',
                'Reply-To: ' . $sales_email
            ];

            $mail_body = '
            <!DOCTYPE html>
            <html>
            <head><meta charset="UTF-8"></head>
            <body style="font-family: Arial, sans-serif; line-height: 1.6; color: #1e1b2e; background-color: #f8fafc; padding: 20px;">
                <div style="max-width: 600px; margin: 0 auto; background: #ffffff; border-radius: 10px; overflow: hidden; border: 1px solid #e2e8f0; box-shadow: 0 4px 10px rgba(0,0,0,0.05);">
                    <div style="background: #6d28d9; padding: 20px 24px; color: #ffffff;">
                        <h2 style="margin:0; font-size: 20px;">Evonee Custom Quote Response</h2>
                    </div>
                    <div style="padding: 24px; color: #334155;">
                        ' . nl2br($reply_body) . '
                    </div>
                    <div style="padding: 20px 24px; background: #faf5ff; border-top: 1px solid #f3e8ff; text-align: center;">
                        <p style="margin:0 0 12px; font-weight:bold; color:#4c1d95; font-size:14px;">Ready to proceed with this quote?</p>
                        <a href="' . esc_url($accept_url) . '" style="display:inline-block; background:#16a34a; color:#ffffff; padding:10px 20px; border-radius:6px; font-weight:bold; text-decoration:none; margin-right:10px;">✅ Accept Quote</a>
                        <a href="' . esc_url($decline_url) . '" style="display:inline-block; background:#dc2626; color:#ffffff; padding:10px 16px; border-radius:6px; font-weight:bold; text-decoration:none;">❌ Decline Quote</a>
                    </div>
                    <div style="background: #f1f5f9; padding: 14px 24px; font-size: 12px; color: #64748b; text-align: center;">
                        Evonee Promotional Products &bull; <a href="mailto:' . esc_attr($sales_email) . '" style="color:#6d28d9;">' . esc_html($sales_email) . '</a>
                    </div>
                </div>
            </body>
            </html>';

            $sent = wp_mail($customer_email, $subject, $mail_body, $headers);
            Evonee_Quote_Ajax::log_email($sub_id, $customer_email, $subject, 'admin_reply', $sent ? 'sent' : 'failed');

            if ($sent) {
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                $wpdb->update($table_name, ['status' => 'quoted'], ['id' => $sub_id], ['%s'], ['%d']);
                Evonee_Quote_Ajax::log_activity($sub_id, 'status_change', 'Status updated to QUOTED via Email Reply');
                wp_safe_redirect(admin_url('admin.php?page=evonee-submissions&eq_notice=email_sent'));
                exit;
            }
            wp_safe_redirect(admin_url('admin.php?page=evonee-submissions&eq_notice=email_failed'));
            exit;
        }

        // Handle Bulk Deletion / Bulk Status Update
        if (isset($_POST['bulk_action'], $_POST['bulk_ids']) && !empty($_POST['bulk_ids']) && check_admin_referer('eq_bulk_action')) {
            $bulk_ids = array_map('intval', (array) wp_unslash($_POST['bulk_ids']));
            $bulk_ids = array_filter($bulk_ids);
            $action   = sanitize_text_field(wp_unslash($_POST['bulk_action']));

            if (!empty($bulk_ids) && $action === 'delete') {
                $placeholders = implode(',', array_fill(0, count($bulk_ids), '%d'));
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, PluginCheck.Security.DirectDB.UnescapedDBParameter
                $wpdb->query($wpdb->prepare("DELETE FROM {$table_name} WHERE id IN ($placeholders)", ...$bulk_ids));
                wp_safe_redirect(admin_url('admin.php?page=evonee-submissions&eq_notice=bulk_deleted&eq_count=' . count($bulk_ids)));
                exit;
            } elseif (!empty($bulk_ids) && in_array($action, ['status_new', 'status_pending', 'status_quoted', 'status_approved', 'status_completed', 'status_rejected'], true)) {
                $status_val = str_replace('status_', '', $action);
                $placeholders = implode(',', array_fill(0, count($bulk_ids), '%d'));
                $query_args = array_merge([$status_val], $bulk_ids);
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, PluginCheck.Security.DirectDB.UnescapedDBParameter
                $wpdb->query($wpdb->prepare("UPDATE {$table_name} SET status = %s WHERE id IN ($placeholders)", ...$query_args));
                foreach ($bulk_ids as $bid) {
                    Evonee_Quote_Ajax::log_activity($bid, 'status_change', 'Bulk status update to ' . strtoupper($status_val));
                }
                wp_safe_redirect(admin_url('admin.php?page=evonee-submissions&eq_notice=bulk_status'));
                exit;
            }
        }

        // Search, Date & Status Filter Query
        $search        = isset($_GET['s']) ? sanitize_text_field(wp_unslash($_GET['s'])) : '';
        $status_filter = isset($_GET['status_filter']) ? sanitize_text_field(wp_unslash($_GET['status_filter'])) : '';
        $date_range    = isset($_GET['date_range']) ? sanitize_text_field(wp_unslash($_GET['date_range'])) : '';

        // Column Sorting (Phase 2.5)
        $allowed_orderby = ['id', 'created_at', 'full_name', 'product', 'quantity', 'status', 'follow_up_date', 'quoted_price'];
        $raw_orderby     = isset($_GET['orderby']) ? sanitize_text_field(wp_unslash($_GET['orderby'])) : 'id';
        $orderby         = in_array($raw_orderby, $allowed_orderby, true) ? $raw_orderby : 'id';
        $raw_order       = isset($_GET['order']) ? sanitize_text_field(wp_unslash($_GET['order'])) : 'DESC';
        $order           = strtoupper($raw_order) === 'ASC' ? 'ASC' : 'DESC';

        // Pagination
        $allowed_per_page = [20, 50, 100];
        $per_page  = isset($_GET['per_page']) ? intval($_GET['per_page']) : 20;
        if (!in_array($per_page, $allowed_per_page)) $per_page = 20;
        $current_page = max(1, isset($_GET['paged']) ? intval($_GET['paged']) : 1);
        $offset       = ($current_page - 1) * $per_page;

        $where_clauses = [];
        if (!empty($search)) {
            $like = '%' . $wpdb->esc_like($search) . '%';
            $where_clauses[] = $wpdb->prepare("(full_name LIKE %s OR email LIKE %s OR phone LIKE %s OR product LIKE %s OR company LIKE %s OR zip_code LIKE %s)", $like, $like, $like, $like, $like, $like);
        }
        if (!empty($status_filter)) {
            $where_clauses[] = $wpdb->prepare("status = %s", $status_filter);
        }

        // Date Range Filtering
        if ($date_range === 'today') {
            $where_clauses[] = $wpdb->prepare("DATE(created_at) = %s", current_time('Y-m-d'));
        } elseif ($date_range === 'last_7_days') {
            $where_clauses[] = $wpdb->prepare("created_at >= %s", wp_date('Y-m-d H:i:s', time() - (7 * DAY_IN_SECONDS)));
        } elseif ($date_range === 'this_month') {
            $where_clauses[] = $wpdb->prepare("created_at >= %s", wp_date('Y-m-01 00:00:00'));
        } elseif ($date_range === 'followup_due') {
            $where_clauses[] = $wpdb->prepare("follow_up_date IS NOT NULL AND follow_up_date <= %s", current_time('Y-m-d'));
        }

        $where_sql   = !empty($where_clauses) ? 'WHERE ' . implode(' AND ', $where_clauses) : '';
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $total_count = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}eq_quote_submissions $where_sql");
        $total_pages = $per_page > 0 ? (int) ceil($total_count / $per_page) : 1;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $results    = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->prefix}eq_quote_submissions $where_sql ORDER BY " . sanitize_sql_orderby("$orderby $order") . " LIMIT %d OFFSET %d", $per_page, $offset));
        $export_url = wp_nonce_url(admin_url('admin.php?page=evonee-submissions&action=export_csv'), 'eq_export_csv_nonce');
        ?>
        <div class="wrap evonee-admin-wrap">
            <?php
            if (isset($_GET['eq_notice'])) {
                $notice = sanitize_text_field(wp_unslash($_GET['eq_notice']));
                $nid    = isset($_GET['eq_id']) ? intval($_GET['eq_id']) : 0;
                $ncount = isset($_GET['eq_count']) ? intval($_GET['eq_count']) : 0;
                $msgs   = [
                    'deleted'       => 'Submission #' . $nid . ' deleted successfully.',
                    'status'        => 'Submission #' . $nid . ' status updated.',
                    'email_sent'    => 'Quote reply email sent successfully. Status updated to QUOTED.',
                    'email_failed'  => 'Failed to send email. Please check your WordPress email server settings.',
                    'bulk_deleted'  => $ncount . ' submissions deleted successfully.',
                    'bulk_status'   => 'Selected submissions updated.',
                ];
                if (isset($msgs[$notice])) {
                    $class = ($notice === 'email_failed') ? 'notice-error' : 'notice-success';
                    echo '<div class="notice ' . esc_attr($class) . ' is-dismissible"><p>' . esc_html($msgs[$notice]) . '</p></div>';
                }
            }
            ?>
            <div class="evonee-admin-header">
                <div>
                    <h1 class="wp-heading-inline">📥 Evonee Quote Submissions</h1>
                    <p class="subtitle">Manage customer quote requests, track quotation status, export reports, and send email replies directly.</p>
                </div>
                <div class="evonee-header-actions">
                    <a href="<?php echo esc_url($export_url); ?>" class="button button-primary button-large" style="background:#16a34a; border-color:#16a34a;">📊 Export to CSV</a>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=evonee-docs')); ?>" class="button button-secondary button-large">📖 Documentation & Help</a>
                </div>
            </div>

            <!-- Search & Filter Bar -->
            <div class="evonee-filter-bar">
                <form method="get" class="evonee-filter-form">
                    <input type="hidden" name="page" value="evonee-submissions">
                    
                    <div class="evonee-filter-group">
                        <select name="status_filter" onchange="this.form.submit();">
                            <option value="">All Statuses</option>
                            <option value="new" <?php selected($status_filter, 'new'); ?>>🟢 New</option>
                            <option value="pending" <?php selected($status_filter, 'pending'); ?>>🟡 Pending</option>
                            <option value="quoted" <?php selected($status_filter, 'quoted'); ?>>🔵 Quoted</option>
                            <option value="approved" <?php selected($status_filter, 'approved'); ?>>💜 Approved</option>
                            <option value="completed" <?php selected($status_filter, 'completed'); ?>>💚 Completed</option>
                            <option value="rejected" <?php selected($status_filter, 'rejected'); ?>>🔴 Rejected</option>
                        </select>

                        <select name="date_range" onchange="this.form.submit();">
                            <option value="">All Time</option>
                            <option value="today" <?php selected($date_range, 'today'); ?>>📅 Today</option>
                            <option value="last_7_days" <?php selected($date_range, 'last_7_days'); ?>>🗓️ Last 7 Days</option>
                            <option value="this_month" <?php selected($date_range, 'this_month'); ?>>📆 This Month</option>
                            <option value="followup_due" <?php selected($date_range, 'followup_due'); ?>>⏰ Follow-up Due</option>
                        </select>

                        <div class="evonee-search-input">
                            <input type="search" name="s" value="<?php echo esc_attr($search); ?>" placeholder="Search by name, email, phone, product...">
                            <button type="submit" class="button">Search</button>
                        </div>

                        <?php if (!empty($search) || !empty($status_filter) || !empty($date_range)): ?>
                            <a href="<?php echo esc_url(admin_url('admin.php?page=evonee-submissions')); ?>" class="button button-link-delete">Reset Filters</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>

            <div class="evonee-admin-card">
                <?php if (empty($results)): ?>
                    <div class="evonee-empty-state">
                        <span class="dashicons dashicons-email-alt" style="font-size: 48px; width: 48px; height: 48px; color: #6d28d9;"></span>
                        <h3>No Submissions Found</h3>
                        <p>No quote submissions match your filter or search criteria.</p>
                    </div>
                <?php else: ?>
                    <form method="post" id="eq-bulk-form">
                        <?php wp_nonce_field('eq_bulk_action'); ?>
                        
                        <div class="evonee-bulk-actions">
                            <select name="bulk_action">
                                <option value="">Bulk Actions</option>
                                <option value="delete">Delete Selected</option>
                                <option value="status_new">Mark as New</option>
                                <option value="status_pending">Mark as Pending</option>
                                <option value="status_quoted">Mark as Quoted</option>
                                <option value="status_approved">Mark as Approved</option>
                                <option value="status_completed">Mark as Completed</option>
                            </select>
                            <button type="submit" onclick="return confirm('Apply bulk action to selected items?');" class="button">Apply</button>
                            <div class="evonee-pagination-info" style="display:flex; align-items:center; gap:12px; flex-wrap:wrap;">
                                <span class="evonee-count-tag"><?php echo esc_html($total_count); ?> Total Submissions</span>
                                <span style="color:#94a3b8; font-size:12px;">
                                    Showing <?php echo esc_html(min(($offset + 1), $total_count)); ?>–<?php echo esc_html(min($offset + $per_page, $total_count)); ?> of <?php echo esc_html($total_count); ?>
                                </span>
                                <span style="display:inline-flex; align-items:center; gap:4px; margin:0;">
                                    <label style="font-size:12px; color:#64748b;">Per page:</label>
                                    <select name="per_page" form="eq-per-page-form" onchange="document.getElementById('eq-per-page-form').submit();" style="font-size:12px; padding:2px 4px;">
                                        <?php foreach ([20, 50, 100] as $pp): ?>
                                            <option value="<?php echo esc_attr($pp); ?>" <?php selected($per_page, $pp); ?>><?php echo esc_html($pp); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </span>
                            </div>
                        </div>

                        <?php
                        $sort_link = function($col, $title) use ($orderby, $order, $search, $status_filter, $date_range, $per_page) {
                            $next_order = ($orderby === $col && $order === 'ASC') ? 'DESC' : 'ASC';
                            $url = admin_url('admin.php?' . http_build_query(array_filter([
                                'page'          => 'evonee-quotes',
                                'orderby'       => $col,
                                'order'         => $next_order,
                                's'             => $search,
                                'status_filter' => $status_filter,
                                'date_range'    => $date_range,
                                'per_page'      => $per_page
                            ])));
                            $icon = ($orderby === $col) ? ($order === 'ASC' ? ' ▲' : ' ▼') : '';
                            return sprintf('<a href="%s" style="color:inherit; text-decoration:none;">%s%s</a>', esc_url($url), esc_html($title), esc_html($icon));
                        };
                        ?>
                        <table class="wp-list-table widefat fixed striped evonee-submissions-table">
                            <thead>
                                <tr>
                                    <th style="width: 32px;"><input type="checkbox" id="eq-select-all"></th>
                                    <th style="width: 60px;"><?php echo wp_kses_post($sort_link('id', 'ID')); ?></th>
                                    <th style="width: 140px;"><?php echo wp_kses_post($sort_link('created_at', 'Date')); ?></th>
                                    <th><?php echo wp_kses_post($sort_link('full_name', 'Customer & Company')); ?></th>
                                    <th>Contact Info</th>
                                    <th><?php echo wp_kses_post($sort_link('product', 'Product')); ?></th>
                                    <th style="width: 60px;"><?php echo wp_kses_post($sort_link('quantity', 'Qty')); ?></th>
                                    <th style="width: 100px;"><?php echo wp_kses_post($sort_link('quoted_price', 'Offer Price')); ?></th>
                                    <th>Delivery & Follow-up</th>
                                    <th>Artwork</th>
                                    <th style="width: 110px;"><?php echo wp_kses_post($sort_link('status', 'Status')); ?></th>
                                    <th style="width: 170px;">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $row_settings = self::get_settings();
                                $show_pdf_btn = (!isset($row_settings['enable_pdf_quote']) || $row_settings['enable_pdf_quote'] === '1');
                                foreach ($results as $row):
                                    $st = isset($row->status) ? strtolower($row->status) : 'new';
                                ?>
                                    <tr data-row='<?php echo esc_attr(wp_json_encode($row)); ?>'>
                                        <td><input type="checkbox" name="bulk_ids[]" value="<?php echo esc_attr($row->id); ?>"></td>
                                        <td><strong>#<?php echo esc_html($row->id); ?></strong></td>
                                        <td><small><?php echo esc_html(gmdate('M d, Y h:i A', strtotime($row->created_at))); ?></small></td>
                                        <td>
                                            <strong><?php echo esc_html($row->full_name); ?></strong>
                                            <?php if (!empty($row->company)): ?>
                                                <br><small style="color: #64748b;"><?php echo esc_html($row->company); ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <a href="mailto:<?php echo esc_attr($row->email); ?>"><?php echo esc_html($row->email); ?></a>
                                            <br><small><?php echo esc_html($row->phone); ?></small>
                                        </td>
                                        <td><span class="evonee-badge-product"><?php echo esc_html($row->product); ?></span></td>
                                        <td><strong><?php echo esc_html($row->quantity); ?></strong></td>
                                        <td>
                                            <?php if (!empty($row->quoted_price) && floatval($row->quoted_price) > 0): ?>
                                                <strong style="color:#16a34a;">$<?php echo number_format($row->quoted_price, 2); ?></strong>
                                            <?php else: ?>
                                                <span style="color:#94a3b8; font-size:11px;">Not Set</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <small><?php echo esc_html($row->timeframe); ?></small>
                                            <?php if (!empty($row->specific_date)): ?>
                                                <br><small style="color: #6d28d9; font-weight:700;">Need by: <?php echo esc_html($row->specific_date); ?></small>
                                            <?php endif; ?>
                                            <?php if (!empty($row->follow_up_date)):
                                                $is_due = (strtotime($row->follow_up_date) <= strtotime(current_time('Y-m-d')));
                                                $badge_color = $is_due ? '#dc2626' : '#d97706';
                                            ?>
                                                <br><span style="background:<?php echo esc_attr($badge_color); ?>; color:#fff; font-size:10px; padding:2px 5px; border-radius:3px; font-weight:700;">⏰ Follow-up: <?php echo esc_html($row->follow_up_date); ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php
                                            $art_urls = Evonee_Quote_Ajax::parse_artwork_urls($row->artwork_url ?? '');
                                            if (!empty($art_urls)):
                                                foreach ($art_urls as $ai => $aurl):
                                            ?>
                                                <a href="<?php echo esc_url($aurl); ?>" target="_blank" rel="noopener noreferrer" class="button button-small button-secondary">File <?php echo esc_html((string) ($ai + 1)); ?></a>
                                            <?php
                                                endforeach;
                                            else:
                                            ?>
                                                <span style="color: #94a3b8; font-style: italic; font-size:11.5px;">Design Help</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <select class="evonee-status-select evonee-status-<?php echo esc_attr($st); ?>" data-id="<?php echo esc_attr($row->id); ?>">
                                                <option value="new" <?php selected($st, 'new'); ?>>New</option>
                                                <option value="pending" <?php selected($st, 'pending'); ?>>Pending</option>
                                                <option value="quoted" <?php selected($st, 'quoted'); ?>>Quoted</option>
                                                <option value="approved" <?php selected($st, 'approved'); ?>>Approved</option>
                                                <option value="completed" <?php selected($st, 'completed'); ?>>Completed</option>
                                                <option value="rejected" <?php selected($st, 'rejected'); ?>>Rejected</option>
                                            </select>
                                        </td>
                                        <td>
                                            <div class="evonee-action-btns">
                                                <button type="button" class="button button-small button-primary eq-view-detail-btn" data-id="<?php echo esc_attr($row->id); ?>">👁️ View</button>
                                                <button type="button" class="button button-small button-secondary eq-reply-email-btn" data-id="<?php echo esc_attr($row->id); ?>" data-email="<?php echo esc_attr($row->email); ?>" data-name="<?php echo esc_attr($row->full_name); ?>" data-product="<?php echo esc_attr($row->product); ?>">✉️ Reply</button>
                                                <?php
                                                // PDF Quote Button — only shown if enable_pdf_quote setting is ON
                                                if ($show_pdf_btn):
                                                    $pdf_url = wp_nonce_url(
                                                        admin_url('admin.php?page=evonee-quotes&action=generate_pdf_quote&id=' . $row->id),
                                                        'eq_generate_pdf_' . $row->id
                                                    );
                                                ?>
                                                <a href="<?php echo esc_url($pdf_url); ?>" target="_blank" class="button button-small" style="color:#6d28d9; border-color:#6d28d9;">📄 PDF</a>
                                                <?php endif; ?>
                                                <?php
                                                $delete_url = wp_nonce_url(admin_url('admin.php?page=evonee-quotes&action=delete&id=' . $row->id), 'delete_quote_' . $row->id);
                                                ?>
                                                <a href="<?php echo esc_url($delete_url); ?>" onclick="return confirm('Are you sure you want to delete this submission?');" class="button button-small button-link-delete">Delete</a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </form>
                    <form method="post" id="eq-status-form" style="display:none;">
                        <?php wp_nonce_field('eq_update_status'); ?>
                        <input type="hidden" name="eq_action" value="update_status">
                        <input type="hidden" name="sub_id" id="eq-status-sub-id" value="">
                        <input type="hidden" name="status" id="eq-status-value" value="">
                    </form>
                    <form method="get" id="eq-per-page-form" style="display:none;">
                        <input type="hidden" name="page" value="evonee-quotes">
                        <?php if (!empty($search)): ?><input type="hidden" name="s" value="<?php echo esc_attr($search); ?>"><?php endif; ?>
                        <?php if (!empty($status_filter)): ?><input type="hidden" name="status_filter" value="<?php echo esc_attr($status_filter); ?>"><?php endif; ?>
                        <?php if (!empty($date_range)): ?><input type="hidden" name="date_range" value="<?php echo esc_attr($date_range); ?>"><?php endif; ?>
                    </form>

                    <?php if ($total_pages > 1): ?>
                    <div class="evonee-pagination-nav" style="display:flex; justify-content:center; align-items:center; gap:6px; margin-top:16px; flex-wrap:wrap;">
                        <?php
                        $base_url_args = ['page' => 'evonee-quotes'];
                        if (!empty($search)) $base_url_args['s'] = $search;
                        if (!empty($status_filter)) $base_url_args['status_filter'] = $status_filter;
                        if ($per_page !== 20) $base_url_args['per_page'] = $per_page;

                        // Previous
                        if ($current_page > 1):
                            $prev_url = admin_url('admin.php?' . http_build_query(array_merge($base_url_args, ['paged' => $current_page - 1])));
                        ?>
                            <a href="<?php echo esc_url($prev_url); ?>" class="button">&laquo; Prev</a>
                        <?php endif; ?>

                        <?php
                        // Numbered pages (show max 7 links)
                        $start = max(1, $current_page - 3);
                        $end   = min($total_pages, $current_page + 3);
                        for ($p = $start; $p <= $end; $p++):
                            $pg_url = admin_url('admin.php?' . http_build_query(array_merge($base_url_args, ['paged' => $p])));
                            $active_style = ($p === $current_page) ? 'background:#6d28d9; color:#fff; border-color:#6d28d9;' : '';
                        ?>
                            <a href="<?php echo esc_url($pg_url); ?>" class="button" style="<?php echo esc_attr($active_style); ?>"><?php echo esc_html($p); ?></a>
                        <?php endfor; ?>

                        <?php if ($current_page < $total_pages):
                            $next_url = admin_url('admin.php?' . http_build_query(array_merge($base_url_args, ['paged' => $current_page + 1])));
                        ?>
                            <a href="<?php echo esc_url($next_url); ?>" class="button">Next &raquo;</a>
                        <?php endif; ?>

                        <span style="font-size:12px; color:#9ca3af; margin-left:8px;">Page <?php echo esc_html($current_page); ?> of <?php echo esc_html($total_pages); ?></span>
                    </div>
                    <?php endif; ?>

                <?php endif; ?>
            </div>
        </div>

        <!-- Detail Modal Drawer -->
        <div id="eq-admin-modal" class="eq-admin-modal-wrap" style="display:none;">
            <div class="eq-admin-modal-overlay"></div>
            <div class="eq-admin-modal-box">
                <button type="button" class="eq-admin-modal-close">&times;</button>
                <div class="eq-admin-modal-header">
                    <h2>📋 Quote Submission Details <span id="eq-detail-id"></span></h2>
                </div>
                <div class="eq-admin-modal-content" id="eq-detail-body">
                    <!-- Populated via JS -->
                </div>
                <!-- Follow-up Date & Offer Price Controls (Phase 2.2 & 2.3) -->
                <div style="border-top:1px solid #e2e8f0; padding:14px 20px; background:#f0fdf4; display:grid; grid-template-columns:1fr 1fr; gap:16px; align-items:center;">
                    <div>
                        <label style="font-size:12px; font-weight:700; color:#166534; display:block; margin-bottom:4px;">⏰ Set Follow-up Reminder Date:</label>
                        <div style="display:flex; gap:6px;">
                            <input type="date" id="eq-followup-date-input" style="font-size:12px; padding:4px 8px; border:1px solid #bbf7d0; border-radius:4px;">
                            <button type="button" id="eq-save-followup-btn" class="button button-small button-secondary">Set Date</button>
                        </div>
                        <span id="eq-followup-msg" style="font-size:11px; color:#166534; display:none;">Saved!</span>
                    </div>
                    <div>
                        <label style="font-size:12px; font-weight:700; color:#1e40af; display:block; margin-bottom:4px;">💰 Quoted Price Offer ($):</label>
                        <div style="display:flex; gap:6px;">
                            <input type="number" step="0.01" id="eq-quoted-price-input" placeholder="e.g. 250.00" style="font-size:12px; padding:4px 8px; border:1px solid #bfdbfe; border-radius:4px; width:120px;">
                            <button type="button" id="eq-save-price-btn" class="button button-small button-primary" style="background:#2563eb; border-color:#2563eb;">Save Price</button>
                        </div>
                        <span id="eq-price-msg" style="font-size:11px; color:#2563eb; display:none;">Saved!</span>
                    </div>
                </div>

                <!-- 1-Click WooCommerce Order Conversion (Phase 1.2) -->
                <div style="border-top:1px solid #e2e8f0; padding:12px 20px; background:#fff7ed; display:flex; justify-content:space-between; align-items:center;">
                    <div>
                        <strong style="font-size:12.5px; color:#c2410c;">🛒 WooCommerce Order Integration:</strong>
                        <span style="font-size:12px; color:#7c2d12; margin-left:4px;">Instantly convert quote into a WooCommerce Pending Order.</span>
                    </div>
                    <div>
                        <button type="button" id="eq-convert-wc-btn" class="button button-primary" style="background:#ea580c; border-color:#ea580c;">🛒 Convert to WC Order</button>
                    </div>
                </div>

                <!-- Internal Admin Notes (Phase 1.3) -->
                <div style="border-top:1px solid #e2e8f0; padding:16px 20px; background:#f8fafc;">
                    <h3 style="margin:0 0 8px; font-size:13px; color:#374151; font-weight:700;">🔒 Internal Team Notes <small style="font-weight:400; color:#9ca3af;">(Not visible to customer)</small></h3>
                    <textarea id="eq-admin-notes-text" rows="3" style="width:100%; font-size:13px; padding:8px; border:1px solid #d1d5db; border-radius:6px; resize:vertical;" placeholder="Add internal notes, follow-up reminders, or team comments..."></textarea>
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-top:8px;">
                        <span id="eq-notes-saved-msg" style="font-size:11px; color:#16a34a; display:none;">✅ Notes saved!</span>
                        <button type="button" id="eq-save-notes-btn" class="button button-primary" style="background:#6d28d9; border-color:#6d28d9;">💾 Save Notes</button>
                    </div>
                </div>

                <!-- Quote Discussion Thread (Step 2) -->
                <div style="border-top:1px solid #e2e8f0; padding:16px 20px; background:#faf5ff;">
                    <h3 style="margin:0 0 10px; font-size:13px; color:#6d28d9; font-weight:700;">💬 Quote Discussion Thread (Live Customer Messages)</h3>
                    <div id="eq-discussion-messages" style="max-height:160px; overflow-y:auto; font-size:12px; background:#ffffff; padding:10px; border-radius:6px; border:1px solid #e9d5ff; margin-bottom:10px;">
                        <em style="color:#9ca3af;">Loading messages...</em>
                    </div>
                    <div style="display:flex; gap:8px;">
                        <input type="text" id="eq-admin-chat-input" placeholder="Type a message to customer..." style="flex:1; font-size:12px; padding:6px 10px; border:1px solid #d8b4fe; border-radius:6px;">
                        <button type="button" id="eq-send-admin-chat-btn" class="button button-primary" style="background:#6d28d9; border-color:#6d28d9;">Send Response</button>
                    </div>
                </div>

                <!-- Activity Log & Email History (Phase 2.1 & 2.4) -->
                <div style="border-top:1px solid #e2e8f0; padding:16px 20px; background:#ffffff;">
                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px;">
                        <div>
                            <h4 style="margin:0 0 8px; font-size:12px; color:#4c1d95; text-transform:uppercase;">📜 Activity Log Timeline</h4>
                            <div id="eq-activity-log-body" style="max-height:140px; overflow-y:auto; font-size:11.5px; background:#faf9fd; padding:10px; border-radius:6px; border:1px solid #e5e0f5;">
                                <!-- Populated via JS -->
                            </div>
                        </div>
                        <div>
                            <h4 style="margin:0 0 8px; font-size:12px; color:#2563eb; text-transform:uppercase;">📧 Email Dispatch History</h4>
                            <div id="eq-email-log-body" style="max-height:140px; overflow-y:auto; font-size:11.5px; background:#eff6ff; padding:10px; border-radius:6px; border:1px solid #bfdbfe;">
                                <!-- Populated via JS -->
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Email Reply Modal Drawer -->
        <div id="eq-reply-modal" class="eq-admin-modal-wrap" style="display:none;">
            <div class="eq-admin-modal-overlay"></div>
            <div class="eq-admin-modal-box" style="max-width: 580px;">
                <button type="button" class="eq-admin-modal-close">&times;</button>
                <div class="eq-admin-modal-header">
                    <h2>✉️ Send Price Quote Email to Customer</h2>
                </div>
                <form method="post" style="padding: 20px;">
                    <?php wp_nonce_field('eq_send_reply'); ?>
                    <input type="hidden" name="eq_action" value="send_customer_reply">
                    <input type="hidden" name="sub_id" id="eq-reply-sub-id" value="">
                    
                    <div style="margin-bottom: 12px;">
                        <label style="font-weight:700; display:block; margin-bottom:4px;">Customer Email:</label>
                        <input type="email" name="customer_email" id="eq-reply-email" class="widefat" readonly required style="background:#f8fafc;">
                    </div>

                    <?php
                    $reply_templates = get_option('evonee_reply_templates', []);
                    if (!empty($reply_templates)):
                    ?>
                    <div style="margin-bottom:12px; background:#faf5ff; border:1px solid #e9d5ff; border-radius:6px; padding:10px 12px;">
                        <label style="font-weight:700; display:block; margin-bottom:6px; font-size:12px; color:#6d28d9;">⚡ Load Quick Template:</label>
                        <select id="eq-template-select" style="width:100%; font-size:13px; padding:6px;">
                            <option value="">— Select a template —</option>
                            <?php foreach ($reply_templates as $idx => $tmpl): ?>
                                <option value="<?php echo esc_attr($idx); ?>"
                                    data-subject="<?php echo esc_attr($tmpl['subject'] ?? ''); ?>"
                                    data-body="<?php echo esc_attr($tmpl['body'] ?? ''); ?>">
                                    <?php echo esc_html($tmpl['title'] ?? 'Template ' . ($idx + 1)); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php endif; ?>

                    <div style="margin-bottom: 12px;">
                        <label style="font-weight:700; display:block; margin-bottom:4px;">Subject Line:</label>
                        <input type="text" name="email_subject" id="eq-reply-subject" class="widefat" required>
                    </div>

                    <div style="margin-bottom: 16px;">
                        <label style="font-weight:700; display:block; margin-bottom:4px;">Quote Details & Message:</label>
                        <textarea name="email_body" id="eq-reply-body" rows="8" class="widefat" required></textarea>
                    </div>

                    <div style="display:flex; justify-content:flex-end; gap:10px;">
                        <button type="button" class="button eq-close-modal-btn">Cancel</button>
                        <button type="submit" class="button button-primary button-large" style="background:#6d28d9; border-color:#6d28d9;">📤 Send Email Quote</button>
                    </div>
                </form>
            </div>
        </div>

        <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Select All Checkbox
            const selectAll = document.getElementById('eq-select-all');
            if (selectAll) {
                selectAll.addEventListener('change', function() {
                    document.querySelectorAll('input[name="bulk_ids[]"]').forEach(cb => cb.checked = this.checked);
                });
            }

            const statusForm = document.getElementById('eq-status-form');
            document.querySelectorAll('.evonee-status-select').forEach(sel => {
                sel.addEventListener('change', function() {
                    if (!statusForm) return;
                    document.getElementById('eq-status-sub-id').value = this.getAttribute('data-id');
                    document.getElementById('eq-status-value').value = this.value;
                    statusForm.submit();
                });
            });

            function escapeHtml(str) {
                if (str == null || str === '') return '';
                return String(str).replace(/[&<>"']/g, function (s) {
                    return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[s];
                });
            }

            function parseArtworkUrls(raw) {
                if (!raw) return [];
                if (Array.isArray(raw)) return raw.filter(Boolean);
                try {
                    const decoded = JSON.parse(raw);
                    if (Array.isArray(decoded)) return decoded.filter(Boolean);
                } catch (e) {}
                return String(raw).split(/\s*,\s*/).filter(Boolean);
            }

            function parseProductDetails(raw) {
                if (!raw) return {};
                if (typeof raw === 'object' && !Array.isArray(raw)) return raw;
                try {
                    const decoded = JSON.parse(raw);
                    return (decoded && typeof decoded === 'object') ? decoded : {};
                } catch (e) {
                    return {};
                }
            }

            // View Details Modal
            const detailModal = document.getElementById('eq-admin-modal');
            const detailBody  = document.getElementById('eq-detail-body');
            const detailId    = document.getElementById('eq-detail-id');

            const notesText      = document.getElementById('eq-admin-notes-text');
            const saveNotesBtn   = document.getElementById('eq-save-notes-btn');
            const notesSavedMsg  = document.getElementById('eq-notes-saved-msg');
            let currentDetailId  = null;

            document.querySelectorAll('.eq-view-detail-btn').forEach(btn => {
                btn.addEventListener('click', function() {
                    const row = JSON.parse(this.closest('tr').getAttribute('data-row'));
                    currentDetailId = row.id;
                    detailId.textContent = '#' + row.id;

                    // Load admin notes
                    if (notesText) {
                        notesText.value = row.admin_notes || '';
                        if (notesSavedMsg) notesSavedMsg.style.display = 'none';
                    }

                    // Load follow-up date & offer price
                    const followupInput = document.getElementById('eq-followup-date-input');
                    const priceInput    = document.getElementById('eq-quoted-price-input');
                    if (followupInput) followupInput.value = row.follow_up_date || '';
                    if (priceInput) priceInput.value = row.quoted_price || '';

                    // Fetch Activity Log & Email Log
                    const actBody   = document.getElementById('eq-activity-log-body');
                    const emailBody = document.getElementById('eq-email-log-body');
                    if (actBody) actBody.innerHTML = '<em style="color:#9ca3af;">Loading timeline...</em>';
                    if (emailBody) emailBody.innerHTML = '<em style="color:#9ca3af;">Loading email logs...</em>';

                    const artUrls = parseArtworkUrls(row.artwork_url);
                    let artworkHtml = artUrls.length
                        ? artUrls.map((url, i) => `<a href="${escapeHtml(url)}" target="_blank" rel="noopener noreferrer" class="button button-primary">Download Artwork ${i + 1}</a>`).join(' ')
                        : `<span style="color:#94a3b8; font-style:italic;">No artwork uploaded (Customer requested design assistance)</span>`;

                    const details = parseProductDetails(row.product_details);
                    const detailRows = Object.keys(details).filter(k => details[k] !== '' && details[k] != null).map(k => {
                        const label = k.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase());
                        const val = Array.isArray(details[k]) ? details[k].join(', ') : details[k];
                        return `<p><strong>${escapeHtml(label)}:</strong> ${escapeHtml(val)}</p>`;
                    }).join('');

                    detailBody.innerHTML = `
                        <div class="eq-detail-grid">
                            <div class="eq-detail-card">
                                <h3>Customer Information</h3>
                                <p><strong>Name:</strong> ${escapeHtml(row.full_name)}</p>
                                <p><strong>Company:</strong> ${escapeHtml(row.company || 'N/A')}</p>
                                <p><strong>Email:</strong> <a href="mailto:${escapeHtml(row.email)}">${escapeHtml(row.email)}</a></p>
                                <p><strong>Phone / WhatsApp:</strong> ${escapeHtml(row.phone)}</p>
                                <p><strong>Country:</strong> ${escapeHtml(row.country)}</p>
                                <p><strong>ZIP / Postal Code:</strong> ${escapeHtml(row.zip_code)}</p>
                            </div>
                            <div class="eq-detail-card">
                                <h3>Product & Order Requirements</h3>
                                <p><strong>Product Requested:</strong> <span class="evonee-badge-product">${escapeHtml(row.product)}</span></p>
                                <p><strong>Quantity:</strong> ${escapeHtml(row.quantity)}</p>
                                <p><strong>Timeframe:</strong> ${escapeHtml(row.timeframe)}</p>
                                <p><strong>Specific Need Date:</strong> ${escapeHtml(row.specific_date || 'N/A')}</p>
                                <p><strong>Submitted Date:</strong> ${escapeHtml(row.created_at)}</p>
                                <p><strong>Quoted Price Offer:</strong> <strong style="color:#16a34a;">${row.quoted_price ? '$' + parseFloat(row.quoted_price).toFixed(2) : 'Not Set'}</strong></p>
                                <p><strong>Current Status:</strong> <strong style="text-transform:uppercase; color:#6d28d9;">${escapeHtml(row.status || 'NEW')}</strong></p>
                            </div>
                        </div>

                        <div class="eq-detail-card" style="margin-top: 14px;">
                            <h3>Product Details</h3>
                            ${detailRows || '<p style="color:#94a3b8;">No additional product details.</p>'}
                        </div>

                        <div class="eq-detail-card" style="margin-top: 14px;">
                            <h3>Uploaded Artwork</h3>
                            ${artworkHtml}
                        </div>

                        <div class="eq-detail-card" style="margin-top: 14px;">
                            <h3>Customer Project Notes & Requirements</h3>
                            <div style="background:#f8fafc; padding:12px 16px; border-radius:6px; border:1px solid #e2e8f0; font-size:13.5px; white-space:pre-wrap;">${escapeHtml(row.project_notes || 'No additional notes provided.')}</div>
                        </div>
                    `;

                    // Load Logs via PHP queries (passed via inline JSON data)
                    <?php
                    global $wpdb;
                    $act_table   = $wpdb->prefix . 'eq_quote_activity_log';
                    $email_table = $wpdb->prefix . 'eq_email_log';
                    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
                    $all_act     = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}eq_quote_activity_log ORDER BY id DESC LIMIT 500");
                    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
                    $all_email   = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}eq_email_log ORDER BY id DESC LIMIT 500");
                    ?>
                    const allActivities = <?php echo json_encode($all_act ?: []); ?>;
                    const allEmails     = <?php echo json_encode($all_email ?: []); ?>;

                    const subActs = allActivities.filter(a => String(a.submission_id) === String(row.id));
                    const subEmails = allEmails.filter(e => String(e.submission_id) === String(row.id));

                    if (actBody) {
                        if (subActs.length > 0) {
                            actBody.innerHTML = subActs.map(a => `<div style="margin-bottom:4px; border-bottom:1px dashed #e2e8f0; padding-bottom:4px;"><strong>${escapeHtml(a.action)}</strong>: ${escapeHtml(a.notes)} <br><small style="color:#94a3b8;">${escapeHtml(a.created_at)}</small></div>`).join('');
                        } else {
                            actBody.innerHTML = '<span style="color:#94a3b8;">No activity logged yet.</span>';
                        }
                    }

                    function loadDiscussionMessages(subId) {
                        const discBox = document.getElementById('eq-discussion-messages');
                        if (!discBox) return;
                        discBox.innerHTML = '<em style="color:#9ca3af;">Loading messages...</em>';
                        fetch('<?php echo esc_url(admin_url('admin-ajax.php')); ?>?action=eq_get_messages&submission_id=' + subId)
                            .then(r => r.json())
                            .then(data => {
                                if (data.success && data.data && data.data.messages && data.data.messages.length > 0) {
                                    discBox.innerHTML = data.data.messages.map(m => {
                                        const isAdmin = m.sender_type === 'admin';
                                        const bg = isAdmin ? '#faf5ff' : '#eff6ff';
                                        const border = isAdmin ? '#e9d5ff' : '#bfdbfe';
                                        const align = isAdmin ? 'right' : 'left';
                                        const senderLabel = isAdmin ? '🛡️ Admin (' + escapeHtml(m.sender_name) + ')' : '👤 ' + escapeHtml(m.sender_name);
                                        return `<div style="background:${bg}; border:1px solid ${border}; padding:6px 10px; border-radius:6px; margin-bottom:6px; text-align:${align};">
                                            <strong>${senderLabel}</strong> <small style="color:#94a3b8;">${escapeHtml(m.created_at)}</small>
                                            <div style="margin-top:2px; color:#1e293b;">${escapeHtml(m.message)}</div>
                                        </div>`;
                                    }).join('');
                                    discBox.scrollTop = discBox.scrollHeight;
                                } else {
                                    discBox.innerHTML = '<span style="color:#94a3b8; font-style:italic;">No messages in discussion thread yet.</span>';
                                }
                            })
                            .catch(() => { discBox.innerHTML = '<span style="color:#dc2626;">Error loading messages.</span>'; });
                    }

                    if (emailBody) {
                        if (subEmails.length > 0) {
                            emailBody.innerHTML = subEmails.map(e => `<div style="margin-bottom:4px; border-bottom:1px dashed #bfdbfe; padding-bottom:4px;"><strong>${escapeHtml(e.type)}</strong> (${escapeHtml(e.status)}) → ${escapeHtml(e.recipient)}<br><em>${escapeHtml(e.subject)}</em><br><small style="color:#94a3b8;">${escapeHtml(e.sent_at)}</small></div>`).join('');
                        } else {
                            emailBody.innerHTML = '<span style="color:#94a3b8;">No email history recorded yet.</span>';
                        }
                    }

                    loadDiscussionMessages(row.id);

                    detailModal.style.display = 'flex';
                });
            });

            // Save Admin Notes via AJAX
            if (saveNotesBtn) {
                saveNotesBtn.addEventListener('click', function() {
                    if (!currentDetailId) return;
                    const notes = notesText ? notesText.value : '';
                    saveNotesBtn.disabled = true;
                    saveNotesBtn.textContent = 'Saving...';

                    const fd = new FormData();
                    fd.append('action', 'eq_save_admin_notes');
                    fd.append('nonce', '<?php echo esc_js(wp_create_nonce('eq_save_notes_nonce')); ?>');
                    fd.append('submission_id', currentDetailId);
                    fd.append('notes', notes);

                    fetch('<?php echo esc_url(admin_url('admin-ajax.php')); ?>', { method: 'POST', body: fd })
                        .then(r => r.json())
                        .then(data => {
                            saveNotesBtn.disabled = false;
                            saveNotesBtn.textContent = '💾 Save Notes';
                            if (data.success && notesSavedMsg) {
                                notesSavedMsg.style.display = 'inline';
                                setTimeout(() => notesSavedMsg.style.display = 'none', 3000);
                                const tr = document.querySelector(`.eq-view-detail-btn[data-id="${currentDetailId}"]`)?.closest('tr');
                                if (tr) {
                                    const rowData = JSON.parse(tr.getAttribute('data-row'));
                                    rowData.admin_notes = notes;
                                    tr.setAttribute('data-row', JSON.stringify(rowData));
                                }
                            }
                        })
                        .catch(() => { saveNotesBtn.disabled = false; saveNotesBtn.textContent = '💾 Save Notes'; });
                });
            }

            // Send Admin Chat Message (Step 2)
            const sendChatBtn = document.getElementById('eq-send-admin-chat-btn');
            const chatInput   = document.getElementById('eq-admin-chat-input');
            if (sendChatBtn && chatInput) {
                sendChatBtn.addEventListener('click', function() {
                    if (!currentDetailId) return;
                    const msg = chatInput.value.trim();
                    if (!msg) return;
                    sendChatBtn.disabled = true;
                    sendChatBtn.textContent = 'Sending...';

                    const fd = new FormData();
                    fd.append('action', 'eq_send_message');
                    fd.append('submission_id', currentDetailId);
                    fd.append('sender_type', 'admin');
                    fd.append('message', msg);

                    fetch('<?php echo esc_url(admin_url('admin-ajax.php')); ?>', { method: 'POST', body: fd })
                        .then(r => r.json())
                        .then(data => {
                            sendChatBtn.disabled = false;
                            sendChatBtn.textContent = 'Send Response';
                            if (data.success) {
                                chatInput.value = '';
                                const discBox = document.getElementById('eq-discussion-messages');
                                if (discBox) {
                                    fetch('<?php echo esc_url(admin_url('admin-ajax.php')); ?>?action=eq_get_messages&submission_id=' + currentDetailId)
                                        .then(r => r.json())
                                        .then(d => {
                                            if (d.success && d.data && d.data.messages && d.data.messages.length > 0) {
                                                discBox.innerHTML = d.data.messages.map(m => {
                                                    const isAdmin = m.sender_type === 'admin';
                                                    const bg = isAdmin ? '#faf5ff' : '#eff6ff';
                                                    const border = isAdmin ? '#e9d5ff' : '#bfdbfe';
                                                    const align = isAdmin ? 'right' : 'left';
                                                    const senderLabel = isAdmin ? '🛡️ Admin (' + escapeHtml(m.sender_name) + ')' : '👤 ' + escapeHtml(m.sender_name);
                                                    return `<div style="background:${bg}; border:1px solid ${border}; padding:6px 10px; border-radius:6px; margin-bottom:6px; text-align:${align};">
                                                        <strong>${senderLabel}</strong> <small style="color:#94a3b8;">${escapeHtml(m.created_at)}</small>
                                                        <div style="margin-top:2px; color:#1e293b;">${escapeHtml(m.message)}</div>
                                                    </div>`;
                                                }).join('');
                                                discBox.scrollTop = discBox.scrollHeight;
                                            }
                                        });
                                }
                            } else {
                                alert(data.data ? data.data.message : 'Error sending message.');
                            }
                        })
                        .catch(() => { sendChatBtn.disabled = false; sendChatBtn.textContent = 'Send Response'; });
                });
            }

            // Save Follow-up Date via AJAX
            const saveFollowupBtn = document.getElementById('eq-save-followup-btn');
            const followupMsg     = document.getElementById('eq-followup-msg');
            if (saveFollowupBtn) {
                saveFollowupBtn.addEventListener('click', function() {
                    if (!currentDetailId) return;
                    const fDate = document.getElementById('eq-followup-date-input')?.value || '';
                    saveFollowupBtn.disabled = true;

                    const fd = new FormData();
                    fd.append('action', 'eq_save_followup');
                    fd.append('nonce', '<?php echo esc_js(wp_create_nonce('eq_save_followup_nonce')); ?>');
                    fd.append('submission_id', currentDetailId);
                    fd.append('follow_up_date', fDate);

                    fetch('<?php echo esc_url(admin_url('admin-ajax.php')); ?>', { method: 'POST', body: fd })
                        .then(r => r.json())
                        .then(data => {
                            saveFollowupBtn.disabled = false;
                            if (data.success && followupMsg) {
                                followupMsg.style.display = 'inline';
                                setTimeout(() => followupMsg.style.display = 'none', 3000);
                                const tr = document.querySelector(`.eq-view-detail-btn[data-id="${currentDetailId}"]`)?.closest('tr');
                                if (tr) {
                                    const rowData = JSON.parse(tr.getAttribute('data-row'));
                                    rowData.follow_up_date = fDate;
                                    tr.setAttribute('data-row', JSON.stringify(rowData));
                                }
                            }
                        })
                        .catch(() => { saveFollowupBtn.disabled = false; });
                });
            }

            // Save Quoted Price Offer via AJAX
            const savePriceBtn = document.getElementById('eq-save-price-btn');
            const priceMsg    = document.getElementById('eq-price-msg');
            if (savePriceBtn) {
                savePriceBtn.addEventListener('click', function() {
                    if (!currentDetailId) return;
                    const priceVal = document.getElementById('eq-quoted-price-input')?.value || 0;
                    savePriceBtn.disabled = true;

                    const fd = new FormData();
                    fd.append('action', 'eq_save_price_offer');
                    fd.append('nonce', '<?php echo esc_js(wp_create_nonce('eq_save_price_offer_nonce')); ?>');
                    fd.append('submission_id', currentDetailId);
                    fd.append('quoted_price', priceVal);

                    fetch('<?php echo esc_url(admin_url('admin-ajax.php')); ?>', { method: 'POST', body: fd })
                        .then(r => r.json())
                        .then(data => {
                            savePriceBtn.disabled = false;
                            if (data.success && priceMsg) {
                                priceMsg.style.display = 'inline';
                                setTimeout(() => priceMsg.style.display = 'none', 3000);
                                const tr = document.querySelector(`.eq-view-detail-btn[data-id="${currentDetailId}"]`)?.closest('tr');
                                if (tr) {
                                    const rowData = JSON.parse(tr.getAttribute('data-row'));
                                    rowData.quoted_price = priceVal;
                                    tr.setAttribute('data-row', JSON.stringify(rowData));
                                }
                            }
                        })
                        .catch(() => { savePriceBtn.disabled = false; });
                });
            }

            // Convert to WooCommerce Order via AJAX (Phase 1.2)
            const convertWcBtn = document.getElementById('eq-convert-wc-btn');
            if (convertWcBtn) {
                convertWcBtn.addEventListener('click', function() {
                    if (!currentDetailId) return;
                    if (!confirm('Convert Quote #' + currentDetailId + ' to a WooCommerce Order?')) return;

                    convertWcBtn.disabled = true;
                    convertWcBtn.textContent = 'Converting...';

                    const fd = new FormData();
                    fd.append('action', 'eq_convert_to_wc_order');
                    fd.append('nonce', '<?php echo esc_js(wp_create_nonce('eq_convert_to_wc_order_nonce')); ?>');
                    fd.append('submission_id', currentDetailId);

                    fetch('<?php echo esc_url(admin_url('admin-ajax.php')); ?>', { method: 'POST', body: fd })
                        .then(r => r.json())
                        .then(data => {
                            convertWcBtn.disabled = false;
                            convertWcBtn.textContent = '🛒 Convert to WC Order';
                            if (data.success) {
                                alert(data.data.message + '\n\nOpening WooCommerce Order edit page...');
                                window.open(data.data.order_edit_url, '_blank');
                            } else {
                                alert('Error: ' + (data.data?.message || 'Failed to convert order.'));
                            }
                        })
                        .catch(() => {
                            convertWcBtn.disabled = false;
                            convertWcBtn.textContent = '🛒 Convert to WC Order';
                            alert('An unexpected error occurred.');
                        });
                });
            }

            // Reply Email Modal
            const replyModal   = document.getElementById('eq-reply-modal');
            const replySubId   = document.getElementById('eq-reply-sub-id');
            const replyEmail   = document.getElementById('eq-reply-email');
            const replySubject = document.getElementById('eq-reply-subject');
            const replyBody    = document.getElementById('eq-reply-body');

            document.querySelectorAll('.eq-reply-email-btn').forEach(btn => {
                btn.addEventListener('click', function() {
                    const id      = this.getAttribute('data-id');
                    const email   = this.getAttribute('data-email');
                    const name    = this.getAttribute('data-name');
                    const product = this.getAttribute('data-product');

                    replySubId.value = id;
                    replyEmail.value = email;
                    replyEmail.dataset.name = name || '';
                    replyEmail.dataset.product = product || '';
                    replySubject.value = `Official Price Quote for ${product} - Evonee (#${id})`;
                    replyBody.value = `Hi ${name},\n\nThank you for reaching out to Evonee regarding your quote request for ${product}.\n\nWe have reviewed your specifications and are pleased to provide you with the following price quote:\n\n- Product: ${product}\n- Price: $ [Enter Price Here]\n- Turnaround Time: [Enter Delivery Timeframe]\n\nPlease let us know if you would like to proceed or if you have any questions!\n\nBest regards,\nEvonee Sales Team\nsales@evonee.com`;

                    replyModal.style.display = 'flex';
                });
            });

            // Close Admin Modals
            document.querySelectorAll('.eq-admin-modal-close, .eq-close-modal-btn, .eq-admin-modal-overlay').forEach(el => {
                el.addEventListener('click', function() {
                    detailModal.style.display = 'none';
                    replyModal.style.display = 'none';
                });
            });

            // Quick Reply Template Select Handler (Phase 1.5)
            const templateSelect = document.getElementById('eq-template-select');
            if (templateSelect) {
                templateSelect.addEventListener('change', function() {
                    const selected = this.options[this.selectedIndex];
                    if (!selected || !selected.value) return;
                    const custName = replyEmail ? replyEmail.dataset.name || '' : '';
                    const product  = replyEmail ? replyEmail.dataset.product || '' : '';
                    const quoteId  = replySubId ? replySubId.value || '' : '';

                    const replaceVars = (str) => str
                        .replace(/\{customer_name\}/g, custName)
                        .replace(/\{product\}/g, product)
                        .replace(/\{quote_id\}/g, quoteId);

                    if (replySubject && selected.dataset.subject) {
                        replySubject.value = replaceVars(selected.dataset.subject);
                    }
                    if (replyBody && selected.dataset.body) {
                        replyBody.value = replaceVars(selected.dataset.body);
                    }
                    // Reset selector after use
                    this.selectedIndex = 0;
                });
            }
        });
        </script>
        <?php
    }

    /**
     * Render Documentation & Help Admin Page
     */
    public function render_docs_page() {
        ?>
        <div class="wrap evonee-admin-wrap">
            <div class="evonee-docs-header">
                <div>
                    <h1>📖 Evonee Get Quote — Plugin Documentation & Help</h1>
                    <p class="subtitle">Official setup guide, shortcode reference, responsive column customization, and developer hooks.</p>
                </div>
                <div class="evonee-docs-brand">
                    <span>Evonee v<?php echo esc_html(EVONEE_VERSION); ?></span>
                </div>
            </div>

            <!-- Docs Content Layout -->
            <div class="evonee-docs-grid">
                
                <!-- Main Content Column -->
                <div class="evonee-docs-main">
                    
                    <!-- Section 1: Quick Overview -->
                    <div class="evonee-doc-card">
                        <div class="evonee-card-header">
                            <div class="dashicons-badge"><span class="dashicons dashicons-welcome-widgets-menus"></span></div>
                            <h2>1. Quick Start & v1.0.0 Overview</h2>
                        </div>
                        <div class="evonee-card-body">
                            <p>The <strong>Evonee Get Quote Plugin (v1.0.0)</strong> provides a complete B2B Lead Management & Custom Quote Automation System. It embeds a site-wide modal popup, instant price estimator, front-end customer portal, 1-click WooCommerce order conversion, and automated WP-Cron expiry reminders.</p>

                            <div class="evonee-feature-box">
                                <ul>
                                    <li>⚡ <strong>9 Core Modules & 40+ Features:</strong> Full CRM pipeline, Email Builder, Front-end Portal, WooCommerce Conversion, Analytics, Integrations, and Cron Automations.</li>
                                    <li>👤 <strong>Front-end Customer Portal:</strong> Embed <code>[evonee_customer_portal]</code> for buyers to track submitted quotes and accept/decline offers.</li>
                                    <li>🛒 <strong>1-Click WooCommerce Order Conversion:</strong> Convert quote submissions into WooCommerce pending orders directly from the CRM drawer.</li>
                                    <li>⏰ <strong>Automated 3-Day Expiry Reminders:</strong> Daily WP-Cron automatically dispatches reminder emails to customers before quote offers expire.</li>
                                    <li>🔗 <strong>Webhook, Zapier, Slack & Elementor Native:</strong> Native Elementor Widget, Gutenberg Block, and webhook automation endpoints.</li>
                                </ul>
                            </div>
                        </div>
                    </div>

                    <!-- Section 2: Module Features Guide -->
                    <div class="evonee-doc-card">
                        <div class="evonee-card-header">
                            <div class="dashicons-badge"><span class="dashicons dashicons-admin-generic"></span></div>
                            <h2>2. Complete v1.0 Module Guide</h2>
                        </div>
                        <div class="evonee-card-body">
                            <table class="evonee-docs-table">
                                <thead>
                                    <tr>
                                        <th>Module</th>
                                        <th>Key Capabilities</th>
                                        <th>Where to Configure</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td><span class="evonee-module-badge">📦 Module 1 — CRM</span></td>
                                        <td>Pagination, Internal Admin Notes, Follow-up Reminders, Activity Log Timeline, Column Sorting & Date Range Filters.</td>
                                        <td><code>Evonee Quotes ➔ Submissions</code></td>
                                    </tr>
                                    <tr>
                                        <td><span class="evonee-module-badge">✉️ Module 2 — Email</span></td>
                                        <td>Visual Branding Builder (Logo, Colors, Footer), Email Dispatch History Log, Quick Reply Templates (`{customer_name}`, `{product}`).</td>
                                        <td><code>Evonee Quotes ➔ Settings</code> & ➔ <code>Email Log</code></td>
                                    </tr>
                                    <tr>
                                        <td><span class="evonee-module-badge">💰 Module 3 — Pricing</span></td>
                                        <td>Quoted Price Entry in CRM, 1-Click Printable PDF Quote Sheet, Tokenized Customer Accept/Decline Email Buttons.</td>
                                        <td><code>Submissions ➔ View Detail</code></td>
                                    </tr>
                                    <tr>
                                        <td><span class="evonee-module-badge">📊 Module 4 — Analytics</span></td>
                                        <td>Chart.js Monthly Trends Bar Chart, Status Distribution Doughnut Chart, and Conversion Funnel Cards.</td>
                                        <td><code>Evonee Quotes ➔ Analytics & Reports</code></td>
                                    </tr>
                                    <tr>
                                        <td><span class="evonee-module-badge">🛒 Module 5 — WooCommerce</span></td>
                                        <td>1-Click Quote to Order Conversion, Conditional Quote Rules (Out of Stock, Guest Users), Price Hiding, and Bulk Cart Quote Request.</td>
                                        <td><code>Evonee Quotes ➔ Settings</code> & ➔ <code>Submissions</code></td>
                                    </tr>
                                    <tr>
                                        <td><span class="evonee-module-badge">🔗 Module 6 — Integrations</span></td>
                                        <td>Webhook URL endpoint (Zapier, Make, HubSpot), Native Elementor Widget, Gutenberg Block, and Slack Channel Lead Notifications.</td>
                                        <td><code>Evonee Quotes ➔ Settings</code></td>
                                    </tr>
                                    <tr>
                                        <td><span class="evonee-module-badge">🛡️ Module 7 — Security & UX</span></td>
                                        <td>Google reCAPTCHA v3, Multi-file Upload (up to 3 files), LocalStorage Form Draft Auto-Resume.</td>
                                        <td><code>Evonee Quotes ➔ Settings</code></td>
                                    </tr>
                                    <tr>
                                        <td><span class="evonee-module-badge">🎨 Module 8 — UI/UX</span></td>
                                        <td>Multi-step Gradient Progress Bar, Social Proof Badge ("⚡ X quotes today"), Floating WhatsApp Button, Branded PDF Logo.</td>
                                        <td>Modal & PDF Sheet Header</td>
                                    </tr>
                                    <tr>
                                        <td><span class="evonee-module-badge">⏰ Module 9 — Automations</span></td>
                                        <td>Daily WP-Cron background task for automated 3-day expiry reminder emails and expired token cleanup.</td>
                                        <td>Automated System Background Cron</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Section 3: Elementor & Shortcode Usage -->
                    <div class="evonee-doc-card">
                        <div class="evonee-card-header">
                            <div class="dashicons-badge"><span class="dashicons dashicons-shortcode"></span></div>
                            <h2>3. Elementor & Shortcode Usage</h2>
                        </div>
                        <div class="evonee-card-body">
                            <p>Use the shortcode <code>[evonee_products]</code> inside Elementor's <strong>Shortcode Widget</strong> or any page builder to render the popular products grid.</p>

                            <h3>Primary Shortcode:</h3>
                            <div class="evonee-code-snippet">
                                <code>[evonee_products]</code>
                                <button type="button" class="evonee-copy-code" data-code="[evonee_products]">Copy</button>
                            </div>

                            <h3>Shortcode Parameters Reference:</h3>
                            <table class="evonee-docs-table">
                                <thead>
                                    <tr>
                                        <th>Attribute</th>
                                        <th>Default</th>
                                        <th>Options / Format</th>
                                        <th>Description</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td><code>cols</code> / <code>columns</code></td>
                                        <td><code>6</code></td>
                                        <td><code>1</code> to <code>6</code></td>
                                        <td>Number of product columns to display on Desktop viewports.</td>
                                    </tr>
                                    <tr>
                                        <td><code>cols_tablet</code></td>
                                        <td><code>3</code></td>
                                        <td><code>1</code> to <code>4</code></td>
                                        <td>Number of product columns to display on Tablet viewports.</td>
                                    </tr>
                                    <tr>
                                        <td><code>cols_mobile</code></td>
                                        <td><code>2</code></td>
                                        <td><code>1</code>, <code>2</code></td>
                                        <td>Number of product columns to display on Mobile viewports.</td>
                                    </tr>
                                    <tr>
                                        <td><code>gap</code></td>
                                        <td><code>18px</code></td>
                                        <td>e.g. <code>12px</code>, <code>20px</code></td>
                                        <td>Whitespace gap between product cards.</td>
                                    </tr>
                                    <tr>
                                        <td><code>img_height</code></td>
                                        <td><code>140px</code></td>
                                        <td>e.g. <code>140px</code>, <code>180px</code></td>
                                        <td>Thumbnail container height for uniform image alignment.</td>
                                    </tr>
                                    <tr>
                                        <td><code>limit</code></td>
                                        <td>Settings value (default <code>12</code>)</td>
                                        <td>e.g. <code>8</code>, <code>12</code>, <code>0</code></td>
                                        <td>Maximum number of products to show. <code>0</code> displays all. Overrides the Settings option.</td>
                                    </tr>
                                    <tr>
                                        <td><code>show_title</code></td>
                                        <td><code>no</code></td>
                                        <td><code>yes</code> / <code>no</code></td>
                                        <td>Show the grid heading. Off by default.</td>
                                    </tr>
                                    <tr>
                                        <td><code>title</code></td>
                                        <td><code>Popular Products</code></td>
                                        <td>Any text</td>
                                        <td>Header title text (only if <code>show_title="yes"</code>).</td>
                                    </tr>
                                </tbody>
                            </table>

                            <h3>Available Shortcodes:</h3>

                            <div style="margin-bottom:14px;">
                                <strong>1. Products Grid Shortcode:</strong>
                                <div class="evonee-code-snippet">
                                    <code>[evonee_products cols="6" cols_tablet="3" cols_mobile="2"]</code>
                                    <button type="button" class="evonee-copy-code" data-code='[evonee_products cols="6" cols_tablet="3" cols_mobile="2"]'>Copy</button>
                                </div>
                            </div>

                            <div style="margin-bottom:14px;">
                                <strong>2. Quote Trigger Button Shortcode:</strong>
                                <div class="evonee-code-snippet">
                                    <code>[evonee_quote_button product="Silicone Wristband" text="Get Free Quote"]</code>
                                    <button type="button" class="evonee-copy-code" data-code='[evonee_quote_button product="Silicone Wristband" text="Get Free Quote"]'>Copy</button>
                                </div>
                            </div>

                            <div style="margin-bottom:14px;">
                                <strong>3. Front-end Customer Quote Portal Shortcode (Phase 3):</strong>
                                <div class="evonee-code-snippet">
                                    <code>[evonee_customer_portal]</code>
                                    <button type="button" class="evonee-copy-code" data-code='[evonee_customer_portal]'>Copy</button>
                                </div>
                                <p class="description">Renders a front-end portal where buyers can view their quote history, track real-time statuses, view price offers, and 1-click accept or decline offers.</p>
                            </div>

                            <h3>Native Page Builders & WooCommerce Integration:</h3>
                            <ul style="line-height:1.6; color:#475569; padding-left:20px;">
                                <li><strong>Elementor Native Widget:</strong> Search for <code>Evonee Quote Button</code> under Elementor's <em>General</em> widget category.</li>
                                <li><strong>Gutenberg Block:</strong> Search for <code>evonee/quote-button</code> block in WordPress block editor or Full Site Editing (FSE).</li>
                                <li><strong>1-Click WooCommerce Order Conversion:</strong> In <code>Evonee Quotes ➔ Submissions</code>, open any quote drawer and click <code>🛒 Convert to WC Order</code> to generate a WooCommerce pending order.</li>
                                <li><strong>Bulk Cart Quote Request:</strong> Enable <code>Bulk Cart Quote Request</code> in Settings to add a B2B quote request button on WooCommerce Cart & Checkout pages.</li>
                            </ul>
                    </div>

                    <!-- Section 4: Webhook & Slack Integrations -->
                    <div class="evonee-doc-card">
                        <div class="evonee-card-header">
                            <div class="dashicons-badge"><span class="dashicons dashicons-share-alt"></span></div>
                            <h2>4. Webhook, Zapier & Slack Configuration</h2>
                        </div>
                        <div class="evonee-card-body">
                            <p><strong>Zapier / Make / HubSpot Webhooks:</strong> Navigate to <code>Evonee Quotes ➔ Settings ➔ Section 6</code>, enable Webhooks, and paste your Target Catch Webhook URL. The plugin sends the following JSON payload on submission:</p>
                            <pre class="evonee-pre-block">{
  "quote_id": 42,
  "full_name": "John Doe",
  "email": "john@example.com",
  "phone": "+1 555-0199",
  "product": "Silicone Wristband",
  "quantity": "500",
  "country": "United States",
  "submitted_at": "2026-08-20 11:30:00"
}</pre>

                            <p><strong>Slack Incoming Webhook:</strong> Paste your Slack Webhook URL in Settings. Every new lead instantly posts a rich card to your sales channel.</p>
                        </div>
                    </div>

                </div>

                <!-- Right Sidebar Column -->
                <div class="evonee-docs-sidebar">
                    
                    <div class="evonee-sidebar-card">
                        <h3>📌 Email Notifications</h3>
                        <p>Quote request notifications are automatically dispatched to:</p>
                        <ul>
                            <li><strong>Sales Email:</strong> <code><?php echo esc_html($settings['sales_email'] ?? 'sales@evonee.com'); ?></code></li>
                            <li><strong>Admin Email:</strong> <code><?php echo esc_html(get_option('admin_email')); ?></code></li>
                        </ul>
                    </div>

                    <div class="evonee-sidebar-card">
                        <h3>📁 Upload Safety</h3>
                        <p>Uploaded artwork files are saved securely in:</p>
                        <code>wp-content/uploads/evonee-quotes/YYYY/MM/</code>
                        <p><small style="color:#94a3b8; display:block; margin-top:4px;">Supports up to 3 files: AI, PDF, EPS, SVG, PNG, JPG (Max 20MB per file).</small></p>
                    </div>

                    <div class="evonee-sidebar-card" style="background:linear-gradient(135deg, #faf5ff 0%, #f3e8ff 100%); border-color:#e9d5ff;">
                        <h3 style="color:#4c1d95;">💬 Need Support or Customization?</h3>
                        <p style="color:#6b21a8;">Evonee v1.0.0 Plugin Documentation & Support.</p>
                        <a href="mailto:sales@evonee.com" class="button button-primary button-large" style="width:100%; text-align:center; background:linear-gradient(135deg, #6d28d9 0%, #7c3aed 100%); border-color:#6d28d9; border-radius:8px; font-weight:700; margin-top:8px;">Contact Developer Team</a>
                    </div>

                </div>

            </div>
        </div>

        <script>
        document.addEventListener('DOMContentLoaded', function() {
            function copyToClipboard(text) {
                if (navigator.clipboard && window.isSecureContext) {
                    return navigator.clipboard.writeText(text);
                } else {
                    return new Promise((resolve, reject) => {
                        const textArea = document.createElement('textarea');
                        textArea.value = text;
                        textArea.style.position = 'fixed';
                        textArea.style.left = '-9999px';
                        textArea.style.top = '-9999px';
                        document.body.appendChild(textArea);
                        textArea.focus();
                        textArea.select();
                        try {
                            const successful = document.execCommand('copy');
                            document.body.removeChild(textArea);
                            if (successful) {
                                resolve();
                            } else {
                                reject(new Error('Copy command failed'));
                            }
                        } catch (err) {
                            document.body.removeChild(textArea);
                            reject(err);
                        }
                    });
                }
            }

            document.addEventListener('click', function(e) {
                const btn = e.target.closest('.evonee-copy-code');
                if (!btn) return;
                e.preventDefault();

                let code = btn.getAttribute('data-code');
                if (!code && btn.previousElementSibling) {
                    code = btn.previousElementSibling.textContent.trim();
                }

                if (!code) return;

                copyToClipboard(code).then(() => {
                    const originalText = btn.textContent;
                    btn.textContent = 'Copied! ✓';
                    btn.style.background = '#16a34a';
                    setTimeout(() => {
                        btn.textContent = originalText;
                        btn.style.background = '#6d28d9';
                    }, 1500);
                }).catch(err => {
                    console.error('Copy error:', err);
                    prompt('Copy shortcode:', code);
                });
            });
        });
        </script>
        <?php
    }

    /**
     * Render Analytics & Reports Dashboard Page
     */
    public function render_analytics_page() {
        $settings = self::get_settings();
        if (isset($settings['enable_analytics']) && $settings['enable_analytics'] !== '1') {
            wp_safe_redirect(admin_url('admin.php?page=evonee-settings'));
            exit;
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'eq_quote_submissions';

        // Check if table exists
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_name)) !== $table_name) {
            Evonee_Quote_Ajax::create_submissions_table();
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $total_submissions = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}eq_quote_submissions");
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $new_count         = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}eq_quote_submissions WHERE status = %s", 'new'));
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $quoted_count      = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}eq_quote_submissions WHERE status = %s", 'quoted'));
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $approved_count    = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}eq_quote_submissions WHERE status = %s", 'approved'));
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $completed_count   = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}eq_quote_submissions WHERE status = %s", 'completed'));

        // Monthly Trend Data for Chart.js
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $monthly_trends = $wpdb->get_results("
            SELECT DATE_FORMAT(created_at, '%b %Y') as month_label, COUNT(*) as total 
            FROM {$wpdb->prefix}eq_quote_submissions 
            GROUP BY DATE_FORMAT(created_at, '%Y-%m') 
            ORDER BY created_at ASC LIMIT 12
        ");

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $top_products = $wpdb->get_results("
            SELECT product, COUNT(*) as total
            FROM {$wpdb->prefix}eq_quote_submissions
            GROUP BY product
            ORDER BY total DESC
            LIMIT 10
        ");

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $top_countries = $wpdb->get_results("
            SELECT country, COUNT(*) as total
            FROM {$wpdb->prefix}eq_quote_submissions
            GROUP BY country
            ORDER BY total DESC
            LIMIT 10
        ");

        // Conversion Funnel Calculations (Phase 4.2)
        $pct_quoted   = $total_submissions > 0 ? round(($quoted_count / $total_submissions) * 100, 1) : 0;
        $pct_approved = $total_submissions > 0 ? round(($approved_count / $total_submissions) * 100, 1) : 0;
        $pct_completed= $total_submissions > 0 ? round(($completed_count / $total_submissions) * 100, 1) : 0;

        ?>
        <div class="wrap evonee-admin-wrap">
            <div class="evonee-docs-header" style="background: linear-gradient(135deg, #1e1b2e 0%, #4c1d95 100%);">
                <div>
                    <h1>📊 Evonee Quotes — Analytics & Business Metrics</h1>
                    <p class="subtitle">Real-time quotation pipeline overview, conversion funnel charts, and business growth insights.</p>
                </div>
                <div class="evonee-docs-brand">
                    <span>v1.0.0 Business Reports</span>
                </div>
            </div>

            <!-- Metric Stats Grid -->
            <div class="evonee-metrics-grid">
                <div class="evonee-stat-card">
                    <span class="evonee-stat-icon" style="background:#f3f0fc; color:#6d28d9;">📥</span>
                    <div>
                        <div class="evonee-stat-val"><?php echo number_format($total_submissions); ?></div>
                        <div class="evonee-stat-lbl">Total Quotes Received</div>
                    </div>
                </div>

                <div class="evonee-stat-card">
                    <span class="evonee-stat-icon" style="background:#dcfce7; color:#15803d;">🟢</span>
                    <div>
                        <div class="evonee-stat-val"><?php echo number_format($new_count); ?></div>
                        <div class="evonee-stat-lbl">New Requests</div>
                    </div>
                </div>

                <div class="evonee-stat-card">
                    <span class="evonee-stat-icon" style="background:#dbeafe; color:#1d4ed8;">🔵</span>
                    <div>
                        <div class="evonee-stat-val"><?php echo number_format($quoted_count); ?></div>
                        <div class="evonee-stat-lbl">Quotes Dispatched</div>
                    </div>
                </div>

                <div class="evonee-stat-card">
                    <span class="evonee-stat-icon" style="background:#f3e8ff; color:#6b21a8;">💜</span>
                    <div>
                        <div class="evonee-stat-val"><?php echo number_format($approved_count + $completed_count); ?></div>
                        <div class="evonee-stat-lbl">Approved & Completed</div>
                    </div>
                </div>
            </div>

            <!-- Conversion Funnel Section (Phase 4.2) -->
            <div class="evonee-doc-card" style="margin-top:20px;">
                <div class="evonee-card-header">
                    <span class="dashicons dashicons-filter"></span>
                    <h2>📉 Quote Conversion Funnel Overview</h2>
                </div>
                <div class="evonee-card-body" style="display:grid; grid-template-columns:repeat(4,1fr); gap:16px; text-align:center;">
                    <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; padding:16px;">
                        <div style="font-size:12px; color:#64748b; font-weight:700;">1. Total Submissions</div>
                        <div style="font-size:24px; font-weight:800; color:#1e1b2e; margin:6px 0;"><?php echo esc_html($total_submissions); ?></div>
                        <div style="font-size:11px; color:#16a34a; font-weight:700;">100% Baseline</div>
                    </div>
                    <div style="background:#eff6ff; border:1px solid #bfdbfe; border-radius:8px; padding:16px;">
                        <div style="font-size:12px; color:#1d4ed8; font-weight:700;">2. Quotes Dispatched</div>
                        <div style="font-size:24px; font-weight:800; color:#2563eb; margin:6px 0;"><?php echo esc_html($quoted_count); ?></div>
                        <div style="font-size:11px; color:#2563eb; font-weight:700;"><?php echo esc_html($pct_quoted); ?>% Quoted Rate</div>
                    </div>
                    <div style="background:#faf5ff; border:1px solid #e9d5ff; border-radius:8px; padding:16px;">
                        <div style="font-size:12px; color:#6b21a8; font-weight:700;">3. Customer Approved</div>
                        <div style="font-size:24px; font-weight:800; color:#7c3aed; margin:6px 0;"><?php echo esc_html($approved_count); ?></div>
                        <div style="font-size:11px; color:#7c3aed; font-weight:700;"><?php echo esc_html($pct_approved); ?>% Approval Rate</div>
                    </div>
                    <div style="background:#f0fdf4; border:1px solid #bbf7d0; border-radius:8px; padding:16px;">
                        <div style="font-size:12px; color:#15803d; font-weight:700;">4. Deals Completed</div>
                        <div style="font-size:24px; font-weight:800; color:#059669; margin:6px 0;"><?php echo esc_html($completed_count); ?></div>
                        <div style="font-size:11px; color:#059669; font-weight:700;"><?php echo esc_html($pct_completed); ?>% Won Deals</div>
                    </div>
                </div>
            </div>

            <!-- Chart.js Interactive Graphs -->
            <div class="evonee-analytics-two-col" style="margin-top:20px; display:grid; grid-template-columns:2fr 1fr; gap:20px;">
                <!-- Monthly Trend Bar Chart -->
                <div class="evonee-doc-card">
                    <div class="evonee-card-header">
                        <span class="dashicons dashicons-chart-area"></span>
                        <h2>📈 Monthly Submission Trend</h2>
                    </div>
                    <div class="evonee-card-body">
                        <canvas id="monthlyTrendChart" style="max-height:280px;"></canvas>
                    </div>
                </div>

                <!-- Status Distribution Doughnut Chart -->
                <div class="evonee-doc-card">
                    <div class="evonee-card-header">
                        <span class="dashicons dashicons-chart-pie"></span>
                        <h2>🍩 Status Distribution</h2>
                    </div>
                    <div class="evonee-card-body">
                        <canvas id="statusPieChart" style="max-height:280px;"></canvas>
                    </div>
                </div>
            </div>

            <script>
            document.addEventListener('DOMContentLoaded', function() {
                // Monthly Trend Chart
                const monthlyCtx = document.getElementById('monthlyTrendChart');
                if (monthlyCtx) {
                    const monthLabels = <?php echo json_encode(array_column($monthly_trends, 'month_label') ?: ['Current Month']); ?>;
                    const monthTotals = <?php echo json_encode(array_map('intval', array_column($monthly_trends, 'total')) ?: [$total_submissions]); ?>;

                    new Chart(monthlyCtx, {
                        type: 'bar',
                        data: {
                            labels: monthLabels,
                            datasets: [{
                                label: 'Submissions',
                                data: monthTotals,
                                backgroundColor: '#6d28d9',
                                borderRadius: 6
                            }]
                        },
                        options: { responsive: true, maintainAspectRatio: false }
                    });
                }

                // Status Pie Chart
                const statusCtx = document.getElementById('statusPieChart');
                if (statusCtx) {
                    new Chart(statusCtx, {
                        type: 'doughnut',
                        data: {
                            labels: ['New', 'Quoted', 'Approved', 'Completed'],
                            datasets: [{
                                data: [<?php echo esc_js(implode(',', array_map('intval', [$new_count, $quoted_count, $approved_count, $completed_count]))); ?>],
                                backgroundColor: ['#16a34a', '#2563eb', '#7c3aed', '#059669']
                            }]
                        },
                        options: { responsive: true, maintainAspectRatio: false }
                    });
                }
            });
            </script>

            <div class="evonee-analytics-two-col" style="margin-top:20px;">
                <!-- Top Products Breakdown -->
                <div class="evonee-doc-card">
                    <div class="evonee-card-header">
                        <span class="dashicons dashicons-products"></span>
                        <h2>🔥 Most Requested Products</h2>
                    </div>
                    <div class="evonee-card-body">
                        <?php if (empty($top_products)): ?>
                            <p style="color:#64748b; text-align:center;">No product data available yet.</p>
                        <?php else: ?>
                            <?php foreach ($top_products as $p): 
                                $pct = $total_submissions > 0 ? round(($p->total / $total_submissions) * 100) : 0;
                            ?>
                                <div class="evonee-bar-row">
                                    <div class="evonee-bar-lbl">
                                        <strong><?php echo esc_html($p->product); ?></strong>
                                        <span><?php echo esc_html($p->total); ?> requests (<?php echo esc_html($pct); ?>%)</span>
                                    </div>
                                    <div class="evonee-bar-track">
                                        <div class="evonee-bar-fill" style="width: <?php echo esc_attr($pct); ?>%;"></div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Top Countries Breakdown -->
                <div class="evonee-doc-card">
                    <div class="evonee-card-header">
                        <span class="dashicons dashicons-admin-site-alt3"></span>
                        <h2>🌍 Top Customer Locations</h2>
                    </div>
                    <div class="evonee-card-body">
                        <?php if (empty($top_countries)): ?>
                            <p style="color:#64748b; text-align:center;">No location data available yet.</p>
                        <?php else: ?>
                            <?php foreach ($top_countries as $c): 
                                $pct = $total_submissions > 0 ? round(($c->total / $total_submissions) * 100) : 0;
                            ?>
                                <div class="evonee-bar-row">
                                    <div class="evonee-bar-lbl">
                                        <strong><?php echo esc_html($c->country ?: 'Unspecified'); ?></strong>
                                        <span><?php echo esc_html($c->total); ?> quotes (<?php echo esc_html($pct); ?>%)</span>
                                    </div>
                                    <div class="evonee-bar-track">
                                        <div class="evonee-bar-fill" style="width: <?php echo esc_attr($pct); ?>%; background:#8b5cf6;"></div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

        </div>
        <?php
    }

    /**
     * Render Plugin Settings & Module Manager Page
     */
    public function render_settings_page() {
        if (isset($_POST['eq_save_settings']) && current_user_can('manage_options') && check_admin_referer('eq_save_settings_nonce')) {
            $new_settings = [
                'sales_email'              => sanitize_email(wp_unslash($_POST['sales_email'] ?? '')),
                'currency_symbol'          => sanitize_text_field(wp_unslash($_POST['currency_symbol'] ?? '$')),
                'enable_price_calc'        => isset($_POST['enable_price_calc']) ? '1' : '0',
                'enable_pdf_quote'         => isset($_POST['enable_pdf_quote']) ? '1' : '0',
                'enable_analytics'         => isset($_POST['enable_analytics']) ? '1' : '0',
                'enable_auto_reply'        => isset($_POST['enable_auto_reply']) ? '1' : '0',
                'enable_wc_auto'           => isset($_POST['enable_wc_auto']) ? '1' : '0',
                'show_field_company'       => isset($_POST['show_field_company']) ? '1' : '0',
                'show_field_text_specs'    => isset($_POST['show_field_text_specs']) ? '1' : '0',
                'show_field_specific_date' => isset($_POST['show_field_specific_date']) ? '1' : '0',
                'show_field_project_notes' => isset($_POST['show_field_project_notes']) ? '1' : '0',
                // Phase 3 Settings
                'enable_recaptcha'         => isset($_POST['enable_recaptcha']) ? '1' : '0',
                'recaptcha_site_key'       => sanitize_text_field(wp_unslash($_POST['recaptcha_site_key'] ?? '')),
                'recaptcha_secret_key'     => sanitize_text_field(wp_unslash($_POST['recaptcha_secret_key'] ?? '')),
                'enable_webhook'           => isset($_POST['enable_webhook']) ? '1' : '0',
                'webhook_url'              => sanitize_text_field(wp_unslash($_POST['webhook_url'] ?? '')),
                'enable_slack'             => isset($_POST['enable_slack']) ? '1' : '0',
                'slack_webhook_url'        => sanitize_text_field(wp_unslash($_POST['slack_webhook_url'] ?? '')),
                'enable_wc_quote_only'     => isset($_POST['enable_wc_quote_only']) ? '1' : '0',
                'wc_quote_condition'       => sanitize_text_field(wp_unslash($_POST['wc_quote_condition'] ?? 'all')),
                'wc_hide_price'            => isset($_POST['wc_hide_price']) ? '1' : '0',
                'enable_cart_quote'        => isset($_POST['enable_cart_quote']) ? '1' : '0',
                'email_logo_url'           => esc_url_raw(wp_unslash($_POST['email_logo_url'] ?? '')),
                'email_header_color'       => sanitize_hex_color(wp_unslash($_POST['email_header_color'] ?? '')) ?: '#6d28d9',
                'email_brand_name'         => sanitize_text_field(wp_unslash($_POST['email_brand_name'] ?? '')),
                'email_footer_text'        => sanitize_text_field(wp_unslash($_POST['email_footer_text'] ?? '')),
                'products_grid_limit'      => max(0, intval($_POST['products_grid_limit'] ?? 12)),
                'show_products_title'      => isset($_POST['show_products_title']) ? '1' : '0',
                'pdf_company_name'         => sanitize_text_field(wp_unslash($_POST['pdf_company_name'] ?? '')),
                'pdf_tax_id'               => sanitize_text_field(wp_unslash($_POST['pdf_tax_id'] ?? '')),
                'pdf_accent_color'         => sanitize_hex_color(wp_unslash($_POST['pdf_accent_color'] ?? '')) ?: '#6d28d9',
                'pdf_terms_text'           => sanitize_textarea_field(wp_unslash($_POST['pdf_terms_text'] ?? '')),
                'enable_tiered_pricing'    => isset($_POST['enable_tiered_pricing']) ? '1' : '0',
                'tiered_price_breaks'      => sanitize_textarea_field(wp_unslash($_POST['tiered_price_breaks'] ?? '')),
            ];

            update_option('evonee_quote_settings', $new_settings);

            // Handle Custom Fields Save
            $custom_fields = [];
            // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
            if (!empty($_POST['custom_fields']) && is_array($_POST['custom_fields'])) {
                // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
                $raw_custom_fields = wp_unslash($_POST['custom_fields']);
                foreach ($raw_custom_fields as $cf) {
                    if (!empty($cf['label'])) {
                        $custom_fields[] = [
                            'label'    => sanitize_text_field($cf['label']),
                            'type'     => sanitize_text_field($cf['type']),
                            'options'  => sanitize_text_field($cf['options'] ?? ''),
                            'required' => !empty($cf['required']) ? 1 : 0
                        ];
                    }
                }
            }
            update_option('evonee_quote_custom_fields', $custom_fields);

            // Handle Quick Reply Templates Save (Phase 1.5)
            $reply_templates = [];
            // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
            if (!empty($_POST['reply_templates']) && is_array($_POST['reply_templates'])) {
                // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
                $raw_templates = wp_unslash($_POST['reply_templates']);
                foreach ($raw_templates as $tmpl) {
                    if (!empty($tmpl['title'])) {
                        $reply_templates[] = [
                            'title'   => sanitize_text_field($tmpl['title']),
                            'subject' => sanitize_text_field($tmpl['subject'] ?? ''),
                            'body'    => sanitize_textarea_field($tmpl['body'] ?? ''),
                        ];
                    }
                }
            }
            update_option('evonee_reply_templates', $reply_templates);

            wp_safe_redirect(admin_url('admin.php?page=evonee-settings&eq_notice=saved'));
            exit;
        }

        $settings = self::get_settings();
        ?>
        <div class="wrap evonee-admin-wrap">
            <?php if (isset($_GET['eq_notice']) && sanitize_text_field(wp_unslash($_GET['eq_notice'])) === 'saved'): ?>
                <div class="notice notice-success is-dismissible"><p>Evonee Quote settings saved successfully!</p></div>
            <?php endif; ?>
            <div class="evonee-docs-header" style="background: linear-gradient(135deg, #4c1d95 0%, #6d28d9 100%);">
                <div>
                    <h1>⚙️ Evonee Quote — Plugin Settings & Module Manager</h1>
                    <p class="subtitle">Enable or disable specific features, manage popup form field visibility, and set sales email preferences.</p>
                </div>
                <div class="evonee-docs-brand">
                    <span>Module Manager</span>
                </div>
            </div>

            <form method="post" action="<?php echo esc_url(admin_url('admin.php?page=evonee-settings')); ?>">
                <?php wp_nonce_field('eq_save_settings_nonce'); ?>
                <input type="hidden" name="eq_save_settings" value="1">

                <div class="evonee-docs-grid" style="grid-template-columns: 1fr;">
                    <div class="evonee-docs-main">

                        <!-- Module Toggles Card -->
                        <div class="evonee-doc-card">
                            <div class="evonee-card-header">
                                <span class="dashicons dashicons-admin-settings"></span>
                                <h2>1. Module Feature Toggles (ON / OFF)</h2>
                            </div>
                            <div class="evonee-card-body">
                                
                                <div class="evonee-setting-row">
                                    <div class="evonee-setting-info">
                                        <strong>💡 Live Instant Price Calculator</strong>
                                        <p>Displays a real-time unit price and total price estimate card in the modal sidebar as customers change quantity or options.</p>
                                    </div>
                                    <label class="evonee-toggle">
                                        <input type="checkbox" name="enable_price_calc" value="1" <?php checked($settings['enable_price_calc'], '1'); ?>>
                                        <span class="evonee-slider"></span>
                                    </label>
                                </div>

                                <div class="evonee-setting-row">
                                    <div class="evonee-setting-info">
                                        <strong>📄 1-Click Printable / PDF Quote Sheet</strong>
                                        <p>Generates official printable quote sheets for customers from the Submissions table with 1 click.</p>
                                    </div>
                                    <label class="evonee-toggle">
                                        <input type="checkbox" name="enable_pdf_quote" value="1" <?php checked($settings['enable_pdf_quote'], '1'); ?>>
                                        <span class="evonee-slider"></span>
                                    </label>
                                </div>

                                <div class="evonee-setting-row">
                                    <div class="evonee-setting-info">
                                        <strong>📊 Analytics & Business Reports Dashboard</strong>
                                        <p>Enables the Analytics & Reports admin submenu with graphs of top requested products and customer locations.</p>
                                    </div>
                                    <label class="evonee-toggle">
                                        <input type="checkbox" name="enable_analytics" value="1" <?php checked($settings['enable_analytics'], '1'); ?>>
                                        <span class="evonee-slider"></span>
                                    </label>
                                </div>

                                <div class="evonee-setting-row">
                                    <div class="evonee-setting-info">
                                        <strong>📧 Customer Auto-Reply Email</strong>
                                        <p>Sends an automated branded confirmation email to customers immediately after they submit a quote request.</p>
                                    </div>
                                    <label class="evonee-toggle">
                                        <input type="checkbox" name="enable_auto_reply" value="1" <?php checked($settings['enable_auto_reply'], '1'); ?>>
                                        <span class="evonee-slider"></span>
                                    </label>
                                </div>

                                <div class="evonee-setting-row">
                                    <div class="evonee-setting-info">
                                        <strong>🛒 WooCommerce Auto-Integration</strong>
                                        <p>Automatically appends the "Get Quote" button to WooCommerce shop loop items if WooCommerce is active.</p>
                                    </div>
                                    <label class="evonee-toggle">
                                        <input type="checkbox" name="enable_wc_auto" value="1" <?php checked($settings['enable_wc_auto'], '1'); ?>>
                                        <span class="evonee-slider"></span>
                                    </label>
                                </div>

                                <div class="evonee-setting-row">
                                    <div class="evonee-setting-info">
                                        <strong>🛍️ WooCommerce "Quote Only" Mode</strong>
                                        <p>Hides traditional "Add to Cart" buttons and forces products into Quote-Only mode for custom B2B pricing.</p>
                                    </div>
                                    <label class="evonee-toggle">
                                        <input type="checkbox" name="enable_wc_quote_only" value="1" <?php checked($settings['enable_wc_quote_only'], '1'); ?>>
                                        <span class="evonee-slider"></span>
                                    </label>
                                </div>

                                <div class="evonee-setting-row">
                                    <div class="evonee-setting-info">
                                        <strong>🎯 WooCommerce Quote Trigger Condition</strong>
                                        <p>Choose when to trigger Quote mode on WooCommerce product pages.</p>
                                    </div>
                                    <select name="wc_quote_condition" style="min-width:180px;">
                                        <option value="all" <?php selected($settings['wc_quote_condition'], 'all'); ?>>All Products & Catalog</option>
                                        <option value="out_of_stock" <?php selected($settings['wc_quote_condition'], 'out_of_stock'); ?>>Out of Stock Products Only</option>
                                        <option value="guests" <?php selected($settings['wc_quote_condition'], 'guests'); ?>>Guest / Unauthenticated Users Only</option>
                                    </select>
                                </div>

                                <div class="evonee-setting-row">
                                    <div class="evonee-setting-info">
                                        <strong>🙈 Hide Product Prices (Call for Quote)</strong>
                                        <p>Hides traditional WooCommerce product price display and replaces with "Price Available Upon Quote".</p>
                                    </div>
                                    <label class="evonee-toggle">
                                        <input type="checkbox" name="wc_hide_price" value="1" <?php checked($settings['wc_hide_price'], '1'); ?>>
                                        <span class="evonee-slider"></span>
                                    </label>
                                </div>

                                <div class="evonee-setting-row">
                                    <div class="evonee-setting-info">
                                        <strong>🛒 Enable Bulk Cart Quote Request (Cart & Checkout)</strong>
                                        <p>Appends a "Request Quote for Cart" button on WooCommerce Cart & Checkout pages to convert cart contents into a bulk B2B quote request.</p>
                                    </div>
                                    <label class="evonee-toggle">
                                        <input type="checkbox" name="enable_cart_quote" value="1" <?php checked($settings['enable_cart_quote'], '1'); ?>>
                                        <span class="evonee-slider"></span>
                                    </label>
                                </div>

                            </div>
                        </div>

                        <!-- Form Fields Manager Card -->
                        <div class="evonee-doc-card" style="margin-top: 20px;">
                            <div class="evonee-card-header">
                                <span class="dashicons dashicons-forms"></span>
                                <h2>2. Popup Form Fields Manager (Show / Hide Fields)</h2>
                            </div>
                            <div class="evonee-card-body">
                                
                                <div class="evonee-setting-row">
                                    <div class="evonee-setting-info">
                                        <strong>🏢 Company Name Field (Section 1)</strong>
                                        <p>Displays an optional "Company Name" input field in the contact information section.</p>
                                    </div>
                                    <label class="evonee-toggle">
                                        <input type="checkbox" name="show_field_company" value="1" <?php checked($settings['show_field_company'], '1'); ?>>
                                        <span class="evonee-slider"></span>
                                    </label>
                                </div>

                                <div class="evonee-setting-row">
                                    <div class="evonee-setting-info">
                                        <strong>✍️ Debossed Text & Text Color Fields (Section 3)</strong>
                                        <p>Displays optional text inputs for custom debossed/embossed text and custom text colors.</p>
                                    </div>
                                    <label class="evonee-toggle">
                                        <input type="checkbox" name="show_field_text_specs" value="1" <?php checked($settings['show_field_text_specs'], '1'); ?>>
                                        <span class="evonee-slider"></span>
                                    </label>
                                </div>

                                <div class="evonee-setting-row">
                                    <div class="evonee-setting-info">
                                        <strong>📅 Need by Specific Date Field (Section 5)</strong>
                                        <p>Displays an optional date picker for customers requiring products by an exact calendar date.</p>
                                    </div>
                                    <label class="evonee-toggle">
                                        <input type="checkbox" name="show_field_specific_date" value="1" <?php checked($settings['show_field_specific_date'], '1'); ?>>
                                        <span class="evonee-slider"></span>
                                    </label>
                                </div>

                                <div class="evonee-setting-row">
                                    <div class="evonee-setting-info">
                                        <strong>📝 Tell Us About Your Project Textarea (Section 6)</strong>
                                        <p>Displays an optional project requirements textarea for custom instructions or packaging details.</p>
                                    </div>
                                    <label class="evonee-toggle">
                                        <input type="checkbox" name="show_field_project_notes" value="1" <?php checked($settings['show_field_project_notes'], '1'); ?>>
                                        <span class="evonee-slider"></span>
                                    </label>
                                </div>

                            </div>
                        </div>

                        <!-- Visual Custom Field Builder Card -->
                        <div class="evonee-doc-card" style="margin-top: 20px;">
                            <div class="evonee-card-header">
                                <span class="dashicons dashicons-plus-alt2"></span>
                                <h2>3. 🎨 Visual Custom Field Builder (No-Code)</h2>
                            </div>
                            <div class="evonee-card-body">
                                <p style="color:#64748b; font-size:13px; margin-top:0;">Add custom fields to the Get Quote popup modal without writing code. Fields render automatically in the form and save to customer submissions.</p>

                                <div id="eq-custom-fields-list">
                                    <?php 
                                    $custom_fields = get_option('evonee_quote_custom_fields', []);
                                    if (!empty($custom_fields) && is_array($custom_fields)):
                                        foreach ($custom_fields as $index => $field):
                                    ?>
                                        <div class="eq-custom-field-item" style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; padding:12px 14px; margin-bottom:12px; display:flex; align-items:center; gap:12px; flex-wrap:wrap;">
                                            <div style="flex:2; min-width:180px;">
                                                <label style="font-size:11px; font-weight:700; color:#64748b; display:block; margin-bottom:2px;">Field Label:</label>
                                                <input type="text" name="custom_fields[<?php echo esc_attr($index); ?>][label]" value="<?php echo esc_attr($field['label']); ?>" placeholder="e.g. Event Name" class="widefat" required>
                                            </div>
                                            <div style="flex:1; min-width:130px;">
                                                <label style="font-size:11px; font-weight:700; color:#64748b; display:block; margin-bottom:2px;">Field Type:</label>
                                                <select name="custom_fields[<?php echo esc_attr($index); ?>][type]" class="widefat">
                                                    <option value="text" <?php selected($field['type'], 'text'); ?>>Text Input</option>
                                                    <option value="select" <?php selected($field['type'], 'select'); ?>>Dropdown Select</option>
                                                    <option value="number" <?php selected($field['type'], 'number'); ?>>Number</option>
                                                    <option value="textarea" <?php selected($field['type'], 'textarea'); ?>>Textarea</option>
                                                </select>
                                            </div>
                                            <div style="flex:2; min-width:180px;">
                                                <label style="font-size:11px; font-weight:700; color:#64748b; display:block; margin-bottom:2px;">Options (for Select dropdown):</label>
                                                <input type="text" name="custom_fields[<?php echo esc_attr($index); ?>][options]" value="<?php echo esc_attr(isset($field['options']) ? $field['options'] : ''); ?>" placeholder="Option 1, Option 2, Option 3" class="widefat">
                                            </div>
                                            <div style="flex:0 0 auto; padding-top:14px;">
                                                <label style="font-size:12px; font-weight:600;"><input type="checkbox" name="custom_fields[<?php echo esc_attr($index); ?>][required]" value="1" <?php checked(!empty($field['required'])); ?>> Required</label>
                                            </div>
                                            <div style="flex:0 0 auto; padding-top:14px;">
                                                <button type="button" class="button button-link-delete eq-remove-field-btn">&times; Delete</button>
                                            </div>
                                        </div>
                                    <?php 
                                        endforeach;
                                    endif; 
                                    ?>
                                </div>

                                <button type="button" id="eq-add-custom-field-btn" class="button button-secondary" style="margin-top:6px;">➕ Add Custom Field</button>
                            </div>
                        </div>

                        <!-- General Configuration Card -->
                        <div class="evonee-doc-card" style="margin-top: 20px;">
                            <div class="evonee-card-header">
                                <span class="dashicons dashicons-email-alt"></span>
                                <h2>4. General Email & Pricing Configuration</h2>
                            </div>
                            <div class="evonee-card-body">
                                
                                <div style="margin-bottom: 16px;">
                                    <label style="font-weight:700; display:block; margin-bottom:6px;">Sales Notification Recipient Email:</label>
                                    <input type="email" name="sales_email" value="<?php echo esc_attr($settings['sales_email']); ?>" class="regular-text" required>
                                    <p class="description">All incoming customer quote request notifications will be sent to this email address.</p>
                                </div>

                                <div style="margin-bottom: 16px;">
                                    <label style="font-weight:700; display:block; margin-bottom:6px;">Currency Symbol:</label>
                                    <input type="text" name="currency_symbol" value="<?php echo esc_attr($settings['currency_symbol']); ?>" style="width:80px;" required>
                                    <p class="description">Currency symbol used for live price calculation estimates (e.g. $, €, £, ৳, AED).</p>
                                </div>

                                <div style="margin-bottom: 16px;">
                                    <label style="font-weight:700; display:block; margin-bottom:6px;">Product Grid — Items to Display:</label>
                                    <input type="number" name="products_grid_limit" value="<?php echo esc_attr($settings['products_grid_limit']); ?>" min="0" max="100" style="width:80px;" required>
                                    <p class="description">How many products to show in the <code>[evonee_products]</code> grid. Use <code>0</code> to show all. Shortcode <code>limit</code> attribute overrides this.</p>
                                </div>

                                <div class="evonee-setting-row" style="border-bottom:none; padding-bottom:0;">
                                    <div class="evonee-setting-info">
                                        <strong>Show Product Grid Title</strong>
                                        <p>Displays the "Popular Products" heading above the grid. Off by default.</p>
                                    </div>
                                    <label class="evonee-toggle">
                                        <input type="checkbox" name="show_products_title" value="1" <?php checked($settings['show_products_title'], '1'); ?>>
                                        <span class="evonee-slider"></span>
                                    </label>
                                </div>

                            </div>
                        </div>

                        <!-- Email Template Builder (Phase 1.4) -->
                        <div class="evonee-doc-card" style="margin-top:20px;">
                            <div class="evonee-card-header">
                                <span class="dashicons dashicons-email-alt"></span>
                                <h2>5. Email Template Branding</h2>
                            </div>
                            <div class="evonee-card-body">
                                <p>Customize how your notification and auto-reply emails look. Changes apply to both admin and customer emails.</p>

                                <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px; margin-bottom:14px;">
                                    <div>
                                        <label style="font-weight:700; display:block; margin-bottom:4px;">Brand Name:</label>
                                        <input type="text" name="email_brand_name" value="<?php echo esc_attr($settings['email_brand_name']); ?>" class="widefat" placeholder="Evonee">
                                        <p class="description">Shown in email header (e.g. "EVONEE").</p>
                                    </div>
                                    <div>
                                        <label style="font-weight:700; display:block; margin-bottom:4px;">Header Color:</label>
                                        <div style="display:flex; gap:8px; align-items:center;">
                                            <input type="color" name="email_header_color" value="<?php echo esc_attr($settings['email_header_color']); ?>" style="height:36px; width:60px; cursor:pointer; border:1px solid #d1d5db; border-radius:4px;">
                                            <input type="text" id="email-header-color-text" value="<?php echo esc_attr($settings['email_header_color']); ?>" style="width:90px; font-family:monospace;" placeholder="#6d28d9">
                                        </div>
                                        <p class="description">Email header background color.</p>
                                    </div>
                                </div>

                                <div style="margin-bottom:14px;">
                                    <label style="font-weight:700; display:block; margin-bottom:4px;">Logo Image URL:</label>
                                    <input type="url" name="email_logo_url" value="<?php echo esc_attr($settings['email_logo_url']); ?>" class="widefat" placeholder="https://yoursite.com/logo.png">
                                    <p class="description">Paste a full image URL. Leave blank to use text brand name. Recommended: 200×60px PNG with transparent background.</p>
                                </div>

                                <div>
                                    <label style="font-weight:700; display:block; margin-bottom:4px;">Email Footer Text:</label>
                                    <input type="text" name="email_footer_text" value="<?php echo esc_attr($settings['email_footer_text']); ?>" class="widefat" placeholder="Evonee Promotional Products • sales@evonee.com">
                                    <p class="description">Shown at the bottom of every email.</p>
                                </div>
                            </div>
                        </div>

                        <!-- Quick Reply Templates (Phase 1.5) -->
                        <div class="evonee-doc-card" style="margin-top:20px;">
                            <div class="evonee-card-header">
                                <span class="dashicons dashicons-format-chat"></span>
                                <h2>6. Quick Reply Email Templates</h2>
                            </div>
                            <div class="evonee-card-body">
                                <p>Add pre-written email templates for common responses. Templates appear as a dropdown in the "Reply to Customer" modal. Use variables: <code>{customer_name}</code>, <code>{product}</code>, <code>{quote_id}</code>.</p>

                                <div id="eq-reply-templates-list">
                                    <?php
                                    $saved_templates = get_option('evonee_reply_templates', []);
                                    foreach ($saved_templates as $ti => $tmpl): ?>
                                    <div class="eq-reply-template-item" style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; padding:14px; margin-bottom:12px;">
                                        <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px; margin-bottom:8px;">
                                            <div>
                                                <label style="font-size:11px; font-weight:700; color:#64748b; display:block; margin-bottom:2px;">Template Name:</label>
                                                <input type="text" name="reply_templates[<?php echo esc_attr($ti); ?>][title]" value="<?php echo esc_attr($tmpl['title']); ?>" class="widefat" placeholder="e.g. Standard Quote" required>
                                            </div>
                                            <div>
                                                <label style="font-size:11px; font-weight:700; color:#64748b; display:block; margin-bottom:2px;">Email Subject:</label>
                                                <input type="text" name="reply_templates[<?php echo esc_attr($ti); ?>][subject]" value="<?php echo esc_attr($tmpl['subject']); ?>" class="widefat" placeholder="Your Quote for {product} - Evonee">
                                            </div>
                                        </div>
                                        <div>
                                            <label style="font-size:11px; font-weight:700; color:#64748b; display:block; margin-bottom:2px;">Email Body:</label>
                                            <textarea name="reply_templates[<?php echo esc_attr($ti); ?>][body]" rows="4" class="widefat" placeholder="Hi {customer_name},..."><?php echo esc_textarea($tmpl['body']); ?></textarea>
                                        </div>
                                        <div style="text-align:right; margin-top:6px;">
                                            <button type="button" class="button button-link-delete eq-remove-template-btn">✕ Remove</button>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>

                                <button type="button" id="eq-add-reply-template-btn" class="button button-secondary">+ Add New Template</button>
                            </div>
                        </div>

                        <!-- Security & Integrations Card (Phase 3) -->
                        <div class="evonee-doc-card" style="margin-top:20px;">
                            <div class="evonee-card-header">
                                <span class="dashicons dashicons-shield"></span>
                                <h2>7. Security & Integrations (reCAPTCHA v3, Webhooks & Slack)</h2>
                            </div>
                            <div class="evonee-card-body">
                                
                                <!-- reCAPTCHA v3 -->
                                <div style="margin-bottom:20px; border-bottom:1px solid #e2e8f0; padding-bottom:16px;">
                                    <div class="evonee-setting-row" style="margin-bottom:10px;">
                                        <div class="evonee-setting-info">
                                            <strong>🛡️ Google reCAPTCHA v3 Integration</strong>
                                            <p>Protect quote modal from spam bots using invisible Google reCAPTCHA v3.</p>
                                        </div>
                                        <label class="evonee-toggle">
                                            <input type="checkbox" name="enable_recaptcha" value="1" <?php checked($settings['enable_recaptcha'], '1'); ?>>
                                            <span class="evonee-slider"></span>
                                        </label>
                                    </div>
                                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
                                        <div>
                                            <label style="font-size:11px; font-weight:700; color:#64748b; display:block; margin-bottom:2px;">Site Key:</label>
                                            <input type="text" name="recaptcha_site_key" value="<?php echo esc_attr($settings['recaptcha_site_key']); ?>" class="widefat" placeholder="Google reCAPTCHA v3 Site Key">
                                        </div>
                                        <div>
                                            <label style="font-size:11px; font-weight:700; color:#64748b; display:block; margin-bottom:2px;">Secret Key:</label>
                                            <input type="text" name="recaptcha_secret_key" value="<?php echo esc_attr($settings['recaptcha_secret_key']); ?>" class="widefat" placeholder="Google reCAPTCHA v3 Secret Key">
                                        </div>
                                    </div>
                                </div>

                                <!-- Webhook / Zapier -->
                                <div style="margin-bottom:20px; border-bottom:1px solid #e2e8f0; padding-bottom:16px;">
                                    <div class="evonee-setting-row" style="margin-bottom:10px;">
                                        <div class="evonee-setting-info">
                                            <strong>🔗 Webhook / Zapier Integration</strong>
                                            <p>Automatically send a JSON POST request payload to external services (Zapier, Make, HubSpot) when a quote is submitted.</p>
                                        </div>
                                        <label class="evonee-toggle">
                                            <input type="checkbox" name="enable_webhook" value="1" <?php checked($settings['enable_webhook'], '1'); ?>>
                                            <span class="evonee-slider"></span>
                                        </label>
                                    </div>
                                    <div>
                                        <label style="font-size:11px; font-weight:700; color:#64748b; display:block; margin-bottom:2px;">Webhook Target URL:</label>
                                        <input type="url" name="webhook_url" value="<?php echo esc_attr($settings['webhook_url']); ?>" class="widefat" placeholder="https://hooks.zapier.com/hooks/catch/...">
                                    </div>
                                </div>

                                <!-- Slack Notification -->
                                <div>
                                    <div class="evonee-setting-row" style="margin-bottom:10px;">
                                        <div class="evonee-setting-info">
                                            <strong>💬 Slack Channel Notifications</strong>
                                            <p>Send formatted lead notifications directly to a Slack channel when a new quote is received.</p>
                                        </div>
                                        <label class="evonee-toggle">
                                            <input type="checkbox" name="enable_slack" value="1" <?php checked($settings['enable_slack'], '1'); ?>>
                                            <span class="evonee-slider"></span>
                                        </label>
                                    </div>
                                    <div>
                                        <label style="font-size:11px; font-weight:700; color:#64748b; display:block; margin-bottom:2px;">Slack Incoming Webhook URL:</label>
                                        <input type="url" name="slack_webhook_url" value="<?php echo esc_attr($settings['slack_webhook_url']); ?>" class="widefat" placeholder="https://hooks.slack.com/services/T00/B00/XXX">
                                    </div>
                        <!-- PDF Customizer Card (Step 3) -->
                        <div class="evonee-doc-card" style="margin-top:20px;">
                            <div class="evonee-card-header">
                                <span class="dashicons dashicons-pdf"></span>
                                <h2>8. PDF Quote Sheet Customizer & Branding</h2>
                            </div>
                            <div class="evonee-card-body">
                                <p>Customize the official PDF quote sheet generated for customers and printable quotes.</p>

                                <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px; margin-bottom:14px;">
                                    <div>
                                        <label style="font-weight:700; display:block; margin-bottom:4px;">Company Name on PDF:</label>
                                        <input type="text" name="pdf_company_name" value="<?php echo esc_attr($settings['pdf_company_name'] ?? ''); ?>" class="widefat" placeholder="Evonee Promotional Products">
                                    </div>
                                    <div>
                                        <label style="font-weight:700; display:block; margin-bottom:4px;">Tax / VAT / Business ID:</label>
                                        <input type="text" name="pdf_tax_id" value="<?php echo esc_attr($settings['pdf_tax_id'] ?? ''); ?>" class="widefat" placeholder="VAT-123456789 / EIN">
                                    </div>
                                </div>

                                <div style="margin-bottom:14px;">
                                    <label style="font-weight:700; display:block; margin-bottom:4px;">PDF Header Accent Color:</label>
                                    <input type="color" name="pdf_accent_color" value="<?php echo esc_attr($settings['pdf_accent_color'] ?? '#6d28d9'); ?>" style="height:36px; width:60px; cursor:pointer; border:1px solid #d1d5db; border-radius:4px;">
                                </div>

                                <div>
                                    <label style="font-weight:700; display:block; margin-bottom:4px;">Terms & Conditions Text:</label>
                                    <textarea name="pdf_terms_text" rows="4" class="widefat" placeholder="1. Free proof included..."><?php echo esc_textarea($settings['pdf_terms_text'] ?? ''); ?></textarea>
                                </div>
                            </div>
                        </div>

                        <!-- Dynamic Volume Tiered Pricing Card (Step 4) -->
                        <div class="evonee-doc-card" style="margin-top:20px;">
                            <div class="evonee-card-header">
                                <span class="dashicons dashicons-chart-line"></span>
                                <h2>9. Dynamic Volume Tiered Pricing (Bulk Discount Breaks)</h2>
                            </div>
                            <div class="evonee-card-body">
                                <div class="evonee-setting-row" style="margin-bottom:14px;">
                                    <div class="evonee-setting-info">
                                        <strong>📊 Enable Volume Discount Table in Modal</strong>
                                        <p>Displays a live volume discount table in the quote modal as customers enter quantity.</p>
                                    </div>
                                    <label class="evonee-toggle">
                                        <input type="checkbox" name="enable_tiered_pricing" value="1" <?php checked($settings['enable_tiered_pricing'] ?? '1', '1'); ?>>
                                        <span class="evonee-slider"></span>
                                    </label>
                                </div>

                                <div>
                                    <label style="font-weight:700; display:block; margin-bottom:4px;">Quantity Tier Breaks & Unit Prices (Format: <code>Quantity|UnitPrice</code> per line):</label>
                                    <textarea name="tiered_price_breaks" rows="5" class="widefat" style="font-family:monospace;" placeholder="50|5.00&#10;100|4.50&#10;500|3.80&#10;1000|3.20"><?php echo esc_textarea($settings['tiered_price_breaks'] ?? ''); ?></textarea>
                                    <p class="description">Example: <code>50|5.00</code> means for 50+ units, estimated unit price is $5.00.</p>
                                </div>
                            </div>
                        </div>

                        <p class="submit" style="margin-top: 20px;">
                            <button type="submit" class="button button-primary button-large" style="background:#6d28d9; border-color:#6d28d9;">💾 Save Settings & Options</button>
                        </p>

                    </div>
                </div>
            </form>
        </div>

        <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Color picker text sync
            const colorPicker = document.querySelector('input[name="email_header_color"]');
            const colorText   = document.getElementById('email-header-color-text');
            if (colorPicker && colorText) {
                colorPicker.addEventListener('input', function() { colorText.value = this.value; });
                colorText.addEventListener('input', function() { if (/^#[0-9A-F]{6}$/i.test(this.value)) colorPicker.value = this.value; });
            }

            // Custom Fields add/remove
            const listContainer = document.getElementById('eq-custom-fields-list');
            const addBtn = document.getElementById('eq-add-custom-field-btn');

            if (addBtn && listContainer) {
                addBtn.addEventListener('click', function() {
                    const index = Date.now();
                    const fieldHtml = `
                        <div class="eq-custom-field-item" style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; padding:12px 14px; margin-bottom:12px; display:flex; align-items:center; gap:12px; flex-wrap:wrap;">
                            <div style="flex:2; min-width:180px;">
                                <label style="font-size:11px; font-weight:700; color:#64748b; display:block; margin-bottom:2px;">Field Label:</label>
                                <input type="text" name="custom_fields[${index}][label]" placeholder="e.g. Event Name" class="widefat" required>
                            </div>
                            <div style="flex:1; min-width:130px;">
                                <label style="font-size:11px; font-weight:700; color:#64748b; display:block; margin-bottom:2px;">Field Type:</label>
                                <select name="custom_fields[${index}][type]" class="widefat">
                                    <option value="text">Text Input</option>
                                    <option value="select">Dropdown Select</option>
                                    <option value="number">Number</option>
                                    <option value="textarea">Textarea</option>
                                </select>
                            </div>
                            <div style="flex:2; min-width:180px;">
                                <label style="font-size:11px; font-weight:700; color:#64748b; display:block; margin-bottom:2px;">Options (for Select dropdown):</label>
                                <input type="text" name="custom_fields[${index}][options]" placeholder="Option 1, Option 2, Option 3" class="widefat">
                            </div>
                            <div style="flex:0 0 auto; padding-top:14px;">
                                <label style="font-size:12px; font-weight:600;"><input type="checkbox" name="custom_fields[${index}][required]" value="1"> Required</label>
                            </div>
                            <div style="flex:0 0 auto; padding-top:14px;">
                                <button type="button" class="button button-link-delete eq-remove-field-btn">&times; Delete</button>
                            </div>
                        </div>
                    `;
                    listContainer.insertAdjacentHTML('beforeend', fieldHtml);
                });

                listContainer.addEventListener('click', function(e) {
                    if (e.target.classList.contains('eq-remove-field-btn')) {
                        e.target.closest('.eq-custom-field-item').remove();
                    }
                });
            }

            // Quick Reply Templates add/remove
            const tmplListContainer = document.getElementById('eq-reply-templates-list');
            const addTmplBtn        = document.getElementById('eq-add-reply-template-btn');

            if (addTmplBtn && tmplListContainer) {
                addTmplBtn.addEventListener('click', function() {
                    const index = Date.now();
                    const tmplHtml = `
                        <div class="eq-reply-template-item" style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; padding:14px; margin-bottom:12px;">
                            <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px; margin-bottom:8px;">
                                <div>
                                    <label style="font-size:11px; font-weight:700; color:#64748b; display:block; margin-bottom:2px;">Template Name:</label>
                                    <input type="text" name="reply_templates[${index}][title]" class="widefat" placeholder="e.g. Standard Quote" required>
                                </div>
                                <div>
                                    <label style="font-size:11px; font-weight:700; color:#64748b; display:block; margin-bottom:2px;">Email Subject:</label>
                                    <input type="text" name="reply_templates[${index}][subject]" class="widefat" placeholder="Your Quote for {product} - Evonee">
                                </div>
                            </div>
                            <div>
                                <label style="font-size:11px; font-weight:700; color:#64748b; display:block; margin-bottom:2px;">Email Body:</label>
                                <textarea name="reply_templates[${index}][body]" rows="4" class="widefat" placeholder="Hi {customer_name},..."></textarea>
                            </div>
                            <div style="text-align:right; margin-top:6px;">
                                <button type="button" class="button button-link-delete eq-remove-template-btn">✕ Remove</button>
                            </div>
                        </div>
                    `;
                    tmplListContainer.insertAdjacentHTML('beforeend', tmplHtml);
                });

                tmplListContainer.addEventListener('click', function(e) {
                    if (e.target.classList.contains('eq-remove-template-btn')) {
                        e.target.closest('.eq-reply-template-item').remove();
                    }
                });
            }
        });
        </script>
        <?php
    }
}
