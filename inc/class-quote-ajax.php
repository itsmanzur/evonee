<?php
if (!defined('ABSPATH')) {
    exit;
}

class Evonee_Quote_Ajax {

    public static function init() {
        $instance = new self();
        add_action('wp_ajax_eq_submit_quote',         [$instance, 'handle_submit']);
        add_action('wp_ajax_nopriv_eq_submit_quote',  [$instance, 'handle_submit']);
        add_action('wp_ajax_eq_save_admin_notes',     [$instance, 'handle_save_notes']);
        add_action('wp_ajax_eq_get_today_stats',      [$instance, 'handle_today_stats']);
        add_action('wp_ajax_eq_save_followup',        [$instance, 'handle_save_followup']);
        add_action('wp_ajax_eq_save_price_offer',     [$instance, 'handle_save_price_offer']);
    }

    /**
     * Handle saving internal admin notes for a submission
     */
    public function handle_save_notes() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Unauthorized.'], 403);
        }
        if (!check_ajax_referer('eq_save_notes_nonce', 'nonce', false)) {
            wp_send_json_error(['message' => 'Security check failed.'], 403);
        }

        $submission_id = isset($_POST['submission_id']) ? intval($_POST['submission_id']) : 0;
        $notes         = isset($_POST['notes']) ? sanitize_textarea_field(wp_unslash($_POST['notes'])) : '';

        if (!$submission_id) {
            wp_send_json_error(['message' => 'Invalid submission ID.']);
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'eq_quote_submissions';
        self::maybe_run_phase2_migrations();

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $updated = $wpdb->update(
            $table_name,
            ['admin_notes' => $notes],
            ['id'          => $submission_id],
            ['%s'],
            ['%d']
        );

        if ($updated === false) {
            wp_send_json_error(['message' => 'Failed to save notes.']);
        }

        self::log_activity($submission_id, 'notes_updated', 'Updated internal notes');
        wp_send_json_success(['message' => 'Notes saved successfully.', 'saved_at' => current_time('mysql')]);
    }

    /**
     * Handle today's stats for Dashboard Widget (AJAX)
     */
    public function handle_today_stats() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Unauthorized.'], 403);
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'eq_quote_submissions';
        $today      = current_time('Y-m-d');

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $today_val = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}eq_quote_submissions WHERE DATE(created_at) = %s", $today));
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $new_val   = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}eq_quote_submissions WHERE status = %s", 'new'));
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $pending_val = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}eq_quote_submissions WHERE status = %s", 'pending'));
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $quoted_val  = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}eq_quote_submissions WHERE status = %s", 'quoted'));
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $completed_val = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}eq_quote_submissions WHERE status = %s", 'completed'));
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $total_val   = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}eq_quote_submissions");

        $stats = [
            'today'     => $today_val,
            'new'       => $new_val,
            'pending'   => $pending_val,
            'quoted'    => $quoted_val,
            'completed' => $completed_val,
            'total'     => $total_val,
        ];

        wp_send_json_success($stats);
    }

    /**
     * Handle saving follow-up date for a submission (Phase 2)
     */
    public function handle_save_followup() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Unauthorized.'], 403);
        }
        if (!check_ajax_referer('eq_save_followup_nonce', 'nonce', false)) {
            wp_send_json_error(['message' => 'Security check failed.'], 403);
        }

        $submission_id = isset($_POST['submission_id']) ? intval($_POST['submission_id']) : 0;
        $followup_date = isset($_POST['follow_up_date']) ? sanitize_text_field(wp_unslash($_POST['follow_up_date'])) : '';

        if (!$submission_id) {
            wp_send_json_error(['message' => 'Invalid submission ID.']);
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'eq_quote_submissions';
        self::maybe_run_phase2_migrations();

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $updated = $wpdb->update(
            $table_name,
            ['follow_up_date' => !empty($followup_date) ? $followup_date : NULL],
            ['id'             => $submission_id],
            ['%s'],
            ['%d']
        );

        if ($updated === false) {
            wp_send_json_error(['message' => 'Failed to save follow-up date.']);
        }

        self::log_activity($submission_id, 'followup_updated', 'Set follow-up date to: ' . ($followup_date ?: 'None'));
        wp_send_json_success(['message' => 'Follow-up date saved successfully.']);
    }

    /**
     * Handle saving quoted price offer for a submission (Phase 2)
     */
    public function handle_save_price_offer() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Unauthorized.'], 403);
        }
        if (!check_ajax_referer('eq_save_price_offer_nonce', 'nonce', false)) {
            wp_send_json_error(['message' => 'Security check failed.'], 403);
        }

        $submission_id = isset($_POST['submission_id']) ? intval($_POST['submission_id']) : 0;
        $price         = isset($_POST['quoted_price']) ? floatval($_POST['quoted_price']) : 0.00;

        if (!$submission_id) {
            wp_send_json_error(['message' => 'Invalid submission ID.']);
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'eq_quote_submissions';
        self::maybe_run_phase2_migrations();

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $updated = $wpdb->update(
            $table_name,
            ['quoted_price' => $price],
            ['id'           => $submission_id],
            ['%f'],
            ['%d']
        );

        if ($updated === false) {
            wp_send_json_error(['message' => 'Failed to save price offer.']);
        }

        self::log_activity($submission_id, 'price_updated', 'Set price offer to $' . number_format($price, 2));
        wp_send_json_success(['message' => 'Price offer saved successfully.']);
    }

    /**
     * Helper to log activity (Phase 2)
     */
    public static function log_activity($submission_id, $action, $notes = '') {
        global $wpdb;
        $table = $wpdb->prefix . 'eq_quote_activity_log';
        self::maybe_run_phase2_migrations();
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
        $wpdb->insert(
            $table,
            [
                'submission_id' => intval($submission_id),
                'action'        => sanitize_text_field($action),
                'notes'         => sanitize_textarea_field($notes),
                'user_id'       => get_current_user_id()
            ],
            ['%d', '%s', '%s', '%d']
        );
    }

    /**
     * Helper to log email sent (Phase 2)
     */
    public static function log_email($submission_id, $recipient, $subject, $type = 'reply', $status = 'sent') {
        global $wpdb;
        $table = $wpdb->prefix . 'eq_email_log';
        self::maybe_run_phase2_migrations();
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
        $wpdb->insert(
            $table,
            [
                'submission_id' => intval($submission_id),
                'recipient'     => sanitize_email($recipient),
                'subject'       => sanitize_text_field($subject),
                'type'          => sanitize_text_field($type),
                'status'        => sanitize_text_field($status)
            ],
            ['%d', '%s', '%s', '%s', '%s']
        );
    }

    /**
     * Runtime migration: Phase 2 DB Columns & Tables
     */
    public static function maybe_run_phase2_migrations() {
        global $wpdb;
        $sub_table = $wpdb->prefix . 'eq_quote_submissions';
        $charset_collate = $wpdb->get_charset_collate();

        // 1. Columns for eq_quote_submissions
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $has_notes = $wpdb->get_results($wpdb->prepare("SHOW COLUMNS FROM {$wpdb->prefix}eq_quote_submissions LIKE %s", 'admin_notes'));
        if (empty($has_notes)) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $wpdb->query("ALTER TABLE {$wpdb->prefix}eq_quote_submissions ADD COLUMN admin_notes TEXT DEFAULT '' AFTER project_notes;");
        }
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $has_followup = $wpdb->get_results($wpdb->prepare("SHOW COLUMNS FROM {$wpdb->prefix}eq_quote_submissions LIKE %s", 'follow_up_date'));
        if (empty($has_followup)) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $wpdb->query("ALTER TABLE {$wpdb->prefix}eq_quote_submissions ADD COLUMN follow_up_date DATE DEFAULT NULL AFTER status;");
        }
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $has_price = $wpdb->get_results($wpdb->prepare("SHOW COLUMNS FROM {$wpdb->prefix}eq_quote_submissions LIKE %s", 'quoted_price'));
        if (empty($has_price)) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $wpdb->query("ALTER TABLE {$wpdb->prefix}eq_quote_submissions ADD COLUMN quoted_price DECIMAL(10,2) DEFAULT '0.00' AFTER follow_up_date;");
        }
        // Phase 3: Token-based Quote Acceptance
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $has_token = $wpdb->get_results($wpdb->prepare("SHOW COLUMNS FROM {$wpdb->prefix}eq_quote_submissions LIKE %s", 'acceptance_token'));
        if (empty($has_token)) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $wpdb->query("ALTER TABLE {$wpdb->prefix}eq_quote_submissions ADD COLUMN acceptance_token VARCHAR(64) DEFAULT NULL AFTER quoted_price;");
        }
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $has_expiry = $wpdb->get_results($wpdb->prepare("SHOW COLUMNS FROM {$wpdb->prefix}eq_quote_submissions LIKE %s", 'token_expiry'));
        if (empty($has_expiry)) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $wpdb->query("ALTER TABLE {$wpdb->prefix}eq_quote_submissions ADD COLUMN token_expiry DATETIME DEFAULT NULL AFTER acceptance_token;");
        }

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

        // 2. Activity Log Table
        $act_table = $wpdb->prefix . 'eq_quote_activity_log';
        $sql_act = "CREATE TABLE $act_table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            submission_id bigint(20) NOT NULL,
            action varchar(100) NOT NULL,
            notes text DEFAULT '',
            user_id bigint(20) DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            PRIMARY KEY  (id),
            KEY submission_id (submission_id)
        ) $charset_collate;";
        dbDelta($sql_act);

        // 3. Email Log Table
        $email_table = $wpdb->prefix . 'eq_email_log';
        $sql_email = "CREATE TABLE $email_table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            submission_id bigint(20) NOT NULL,
            recipient varchar(200) NOT NULL,
            subject varchar(255) NOT NULL,
            type varchar(50) DEFAULT 'notification',
            status varchar(20) DEFAULT 'sent',
            sent_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            PRIMARY KEY  (id),
            KEY submission_id (submission_id)
        ) $charset_collate;";
        dbDelta($sql_email);
    }

    public static function maybe_add_admin_notes_column() {
        self::maybe_run_phase2_migrations();
    }

    /**
     * Create custom database table on plugin activation
     */
    public static function create_submissions_table() {
        global $wpdb;

        $table_name = $wpdb->prefix . 'eq_quote_submissions';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            full_name varchar(100) NOT NULL,
            company varchar(100) DEFAULT '',
            email varchar(100) NOT NULL,
            phone varchar(50) NOT NULL,
            country varchar(100) NOT NULL,
            product varchar(150) NOT NULL,
            quantity varchar(50) NOT NULL,
            product_details longtext DEFAULT '',
            artwork_url varchar(255) DEFAULT '',
            timeframe varchar(50) NOT NULL,
            specific_date varchar(50) DEFAULT '',
            zip_code varchar(30) NOT NULL,
            project_notes text DEFAULT '',
            status varchar(30) DEFAULT 'new' NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            PRIMARY KEY  (id),
            KEY status_idx (status),
            KEY created_idx (created_at)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);

        // Ensure status column exists if updating existing installation
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $has_status = $wpdb->get_results($wpdb->prepare("SHOW COLUMNS FROM {$wpdb->prefix}eq_quote_submissions LIKE %s", 'status'));
        if (empty($has_status)) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $wpdb->query("ALTER TABLE {$wpdb->prefix}eq_quote_submissions ADD status varchar(30) DEFAULT 'new' NOT NULL;");
        }
    }

    /**
     * Handle AJAX Form Submission
     */
    public function handle_submit() {
        // 1. Verify Nonce
        if (!check_ajax_referer('eq_submit_quote', 'eq_nonce', false)) {
            wp_send_json_error(['message' => 'Security check failed. Please refresh the page and try again.'], 403);
        }

        // 2. Honeypot Check (Spam bot detection)
        if (!empty($_POST['eq_website'])) {
            // Return fake success response to trick spambots
            wp_send_json_success([
                'message' => 'Quote request received! We will get back to you within 24 hours.'
            ]);
            exit;
        }

        // 3. Minimum Time-on-Page Check (Bots submit instantly)
        $open_time = isset($_POST['eq_open_time']) ? intval($_POST['eq_open_time']) : 0;
        if ($open_time > 0 && (time() - $open_time) < 3) {
            wp_send_json_error(['message' => 'Submission processed too quickly. Please try again.']);
        }

        // 4. Rate Limiting Check (Max 5 submissions per 15 min per IP)
        $client_ip = $this->get_client_ip();
        $transient_key = 'eq_rate_' . md5($client_ip);
        $submission_count = get_transient($transient_key) ?: 0;

        if ($submission_count >= 5) {
            wp_send_json_error(['message' => 'Too many requests. Please wait a few minutes before submitting again.']);
        }

        // 4.5. reCAPTCHA v3 Verification (Phase 3.2)
        $settings = Evonee_Quote_Admin::get_settings();
        if (!empty($settings['enable_recaptcha']) && $settings['enable_recaptcha'] === '1' && !empty($settings['recaptcha_secret_key'])) {
            $recaptcha_token = isset($_POST['eq_recaptcha_token']) ? sanitize_text_field(wp_unslash($_POST['eq_recaptcha_token'])) : '';
            if (empty($recaptcha_token)) {
                wp_send_json_error(['message' => 'reCAPTCHA verification failed. Token missing.']);
            }
            $verify_response = wp_remote_post('https://www.google.com/recaptcha/api/siteverify', [
                'body' => [
                    'secret'   => $settings['recaptcha_secret_key'],
                    'response' => $recaptcha_token,
                    'remoteip' => $client_ip
                ]
            ]);
            if (is_wp_error($verify_response)) {
                wp_send_json_error(['message' => 'reCAPTCHA verification server error.']);
            }
            $res_data = json_decode(wp_remote_retrieve_body($verify_response), true);
            if (empty($res_data['success']) || (isset($res_data['score']) && $res_data['score'] < 0.5)) {
                wp_send_json_error(['message' => 'reCAPTCHA verification failed. Low trust score.']);
            }
        }

        // 5. Sanitize & Collect Input Data
        $full_name    = isset($_POST['full_name']) ? sanitize_text_field(wp_unslash($_POST['full_name'])) : '';
        $company      = isset($_POST['company']) ? sanitize_text_field(wp_unslash($_POST['company'])) : '';
        $email        = isset($_POST['email']) ? sanitize_email(wp_unslash($_POST['email'])) : '';
        $phone        = isset($_POST['phone']) ? sanitize_text_field(wp_unslash($_POST['phone'])) : '';
        $country_raw   = isset($_POST['country']) ? sanitize_text_field(wp_unslash($_POST['country'])) : '';
        $other_country = isset($_POST['other_country']) ? sanitize_text_field(wp_unslash($_POST['other_country'])) : '';
        $country       = ($country_raw === 'Other' || $country_raw === 'other') ? ('Other (' . $other_country . ')') : $country_raw;

        $product      = isset($_POST['product']) ? sanitize_text_field(wp_unslash($_POST['product'])) : '';
        $quantity_raw = isset($_POST['quantity']) ? sanitize_text_field(wp_unslash($_POST['quantity'])) : '';
        $other_qty    = isset($_POST['other_quantity']) ? sanitize_text_field(wp_unslash($_POST['other_quantity'])) : '';
        
        $quantity     = ($quantity_raw === 'other' || $quantity_raw === 'Other') ? ('Other (' . $other_qty . ')') : $quantity_raw;

        $timeframe    = isset($_POST['timeframe']) ? sanitize_text_field(wp_unslash($_POST['timeframe'])) : '';
        $specific_date= isset($_POST['specific_date']) ? sanitize_text_field(wp_unslash($_POST['specific_date'])) : '';
        $zip_code     = isset($_POST['zip_code']) ? sanitize_text_field(wp_unslash($_POST['zip_code'])) : '';
        $project_notes= isset($_POST['project_notes']) ? sanitize_textarea_field(wp_unslash($_POST['project_notes'])) : '';
        $consent      = isset($_POST['consent']) ? true : false;
        $no_artwork   = isset($_POST['no_artwork']) ? true : false;

        // Collect Product Details (Section 3)
        $product_details = [
            'wristband_type'  => isset($_POST['wristband_type']) ? sanitize_text_field(wp_unslash($_POST['wristband_type'])) : '',
            'size'            => isset($_POST['size']) ? sanitize_text_field(wp_unslash($_POST['size'])) : '',
            'color'           => isset($_POST['color']) ? sanitize_text_field(wp_unslash($_POST['color'])) : '',
            'has_pms_color'   => isset($_POST['has_pms_color']) ? sanitize_text_field(wp_unslash($_POST['has_pms_color'])) : '',
            'debossed_text'   => isset($_POST['debossed_text']) ? sanitize_text_field(wp_unslash($_POST['debossed_text'])) : '',
            'text_color'      => isset($_POST['text_color']) ? sanitize_text_field(wp_unslash($_POST['text_color'])) : '',
        ];

        // Dynamically capture No-Code custom fields
        $custom_builder_fields = get_option('evonee_quote_custom_fields', []);
        if (!empty($custom_builder_fields) && is_array($custom_builder_fields)) {
            foreach ($custom_builder_fields as $cf) {
                $f_key = 'custom_field_' . sanitize_title($cf['label']);
                if (isset($_POST[$f_key])) {
                    $product_details[$cf['label']] = sanitize_text_field(wp_unslash($_POST[$f_key]));
                }
            }
        }

        // 6. Server-Side Validation
        $errors = [];
        if (empty($full_name)) {
            $errors['full_name'] = 'Full Name is required.';
        }
        if (empty($email) || !is_email($email)) {
            $errors['email'] = 'A valid email address is required.';
        }
        if (empty($phone)) {
            $errors['phone'] = 'Phone / WhatsApp number is required.';
        }
        if (empty($country)) {
            $errors['country'] = 'Please select your country.';
        }
        if (empty($product)) {
            $errors['product'] = 'Product selection is required.';
        }
        if (empty($quantity)) {
            $errors['quantity'] = 'Quantity selection is required.';
        }
        if (empty($timeframe)) {
            $errors['timeframe'] = 'Delivery timeframe is required.';
        }
        if (empty($zip_code)) {
            $errors['zip_code'] = 'ZIP / Postal Code is required.';
        }
        if (!$consent) {
            $errors['consent'] = 'You must agree to allow Evonee to contact you.';
        }

        if (!empty($errors)) {
            wp_send_json_error(['errors' => $errors]);
        }

        // 7. Validate & Process Artwork Upload (Multi-file support up to 3 files)
        $artwork_urls = [];
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Artwork file is validated and uploaded via validate_and_upload_artwork()
        if (!$no_artwork && isset($_FILES['eq_artwork']) && !empty($_FILES['eq_artwork']['name'])) {
            // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
            $files = $_FILES['eq_artwork'];
            if (is_array($files['name'])) {
                $count = min(count($files['name']), 3);
                for ($i = 0; $i < $count; $i++) {
                    if (!empty($files['name'][$i]) && $files['error'][$i] === UPLOAD_ERR_OK) {
                        $single_file = [
                            'name'     => $files['name'][$i],
                            'type'     => $files['type'][$i],
                            'tmp_name' => $files['tmp_name'][$i],
                            'error'    => $files['error'][$i],
                            'size'     => $files['size'][$i],
                        ];
                        $upload_result = $this->validate_and_upload_artwork($single_file);
                        if (!is_wp_error($upload_result)) {
                            $artwork_urls[] = $upload_result;
                        }
                    }
                }
            } else {
                $upload_result = $this->validate_and_upload_artwork($files);
                if (!is_wp_error($upload_result)) {
                    $artwork_urls[] = $upload_result;
                }
            }
        }
        $artwork_url = implode(', ', $artwork_urls);

        // 8. Prepare Submission Array
        $submission = [
            'full_name'       => $full_name,
            'company'         => $company,
            'email'           => $email,
            'phone'           => $phone,
            'country'         => $country,
            'product'         => $product,
            'quantity'        => $quantity,
            'product_details' => $product_details,
            'artwork_url'     => $artwork_url,
            'timeframe'       => $timeframe,
            'specific_date'   => $specific_date,
            'zip_code'        => $zip_code,
            'project_notes'   => $project_notes,
            'created_at'      => current_time('mysql')
        ];

        // 9. Save to Database
        global $wpdb;
        $table_name = $wpdb->prefix . 'eq_quote_submissions';
        
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
        $inserted = $wpdb->insert(
            $table_name,
            [
                'full_name'       => $submission['full_name'],
                'company'         => $submission['company'],
                'email'           => $submission['email'],
                'phone'           => $submission['phone'],
                'country'         => $submission['country'],
                'product'         => $submission['product'],
                'quantity'        => $submission['quantity'],
                'product_details' => json_encode($submission['product_details']),
                'artwork_url'     => $submission['artwork_url'],
                'timeframe'       => $submission['timeframe'],
                'specific_date'   => $submission['specific_date'],
                'zip_code'        => $submission['zip_code'],
                'project_notes'   => $submission['project_notes'],
                'created_at'      => $submission['created_at']
            ],
            ['%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s']
        );

        if ($inserted === false) {
            wp_send_json_error(['message' => 'An internal error occurred while saving your request. Please try again.'], 500);
        }

        delete_transient('evonee_dashboard_stats');

        // Increment rate limit counter only after successful DB insert
        set_transient($transient_key, $submission_count + 1, 15 * MINUTE_IN_SECONDS);

        // 10. Send Notification Emails
        Evonee_Quote_Mailer::send_notification($submission);

        // 11. Dispatch Webhook & Slack (Phase 3)
        self::dispatch_webhook($submission);
        self::dispatch_slack($submission);

        // 12. Return Success Response
        wp_send_json_success([
            'message' => 'Quote request received! We will get back to you with a custom quote and digital proof within 24 hours.'
        ]);
    }

    /**
     * Dispatch Webhook POST payload (Phase 3.3)
     */
    public static function dispatch_webhook(array $submission) {
        $settings = Evonee_Quote_Admin::get_settings();
        if (empty($settings['enable_webhook']) || $settings['enable_webhook'] !== '1' || empty($settings['webhook_url'])) {
            return;
        }

        $response = wp_remote_post(esc_url_raw($settings['webhook_url']), [
            'headers' => ['Content-Type' => 'application/json; charset=utf-8'],
            'body'    => json_encode($submission),
            'timeout' => 15
        ]);

        $status = is_wp_error($response) ? 'Error: ' . $response->get_error_message() : 'HTTP ' . wp_remote_retrieve_response_code($response);
        if (!empty($submission['id'])) {
            self::log_activity($submission['id'], 'webhook_sent', 'Dispatched Webhook: ' . $status);
        }
    }

    /**
     * Dispatch Slack Notification (Phase 3.4)
     */
    public static function dispatch_slack(array $submission) {
        $settings = Evonee_Quote_Admin::get_settings();
        if (empty($settings['enable_slack']) || $settings['enable_slack'] !== '1' || empty($settings['slack_webhook_url'])) {
            return;
        }

        $slack_payload = [
            'text' => sprintf("📥 *New Quote Request Received on Evonee*\n*Customer:* %s (%s)\n*Product:* %s (Qty: %s)\n*Email:* %s\n*Country:* %s",
                esc_html($submission['full_name']),
                esc_html($submission['company'] ?: 'No Company'),
                esc_html($submission['product']),
                esc_html($submission['quantity']),
                esc_html($submission['email']),
                esc_html($submission['country'])
            )
        ];

        $response = wp_remote_post(esc_url_raw($settings['slack_webhook_url']), [
            'headers' => ['Content-Type' => 'application/json; charset=utf-8'],
            'body'    => json_encode($slack_payload),
            'timeout' => 15
        ]);

        $status = is_wp_error($response) ? 'Error: ' . $response->get_error_message() : 'HTTP ' . wp_remote_retrieve_response_code($response);
        if (!empty($submission['id'])) {
            self::log_activity($submission['id'], 'slack_sent', 'Dispatched Slack Notification: ' . $status);
        }
    }

    /**
     * Validate and Upload Artwork File
     *
     * @param array $file_array
     * @return string|WP_Error File URL or WP_Error on failure
     */
    private function validate_and_upload_artwork(array $file_array) {
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/image.php');

        // Check upload errors
        if ($file_array['error'] !== UPLOAD_ERR_OK) {
            return new WP_Error('upload_error', 'File upload error code: ' . $file_array['error']);
        }

        // Max file size: 20MB
        $max_size = 20 * 1024 * 1024;
        if ($file_array['size'] > $max_size) {
            return new WP_Error('file_too_large', 'File size exceeds the 20MB limit.');
        }

        // Allowed extensions and MIME types
        $mimes = [
            'pdf'  => 'application/pdf',
            'png'  => 'image/png',
            'jpg'  => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'svg'  => 'image/svg+xml',
            'ai'   => ['application/postscript', 'application/pdf', 'application/illustrator', 'application/vnd.adobe.illustrator'],
            'eps'  => ['application/postscript', 'image/x-eps', 'application/eps']
        ];

        // Validate MIME type & Extension
        $wp_filetype = wp_check_filetype_and_ext($file_array['tmp_name'], $file_array['name'], $mimes);
        
        $ext = strtolower(pathinfo($file_array['name'], PATHINFO_EXTENSION));
        $allowed_exts = ['ai', 'pdf', 'eps', 'svg', 'png', 'jpg', 'jpeg'];
        
        if (!in_array($ext, $allowed_exts)) {
            return new WP_Error('invalid_extension', 'Invalid file format. Allowed formats: AI, PDF, EPS, SVG, PNG, JPG.');
        }

        // Custom upload directory callback
        $upload_dir_filter = function($uploads) {
            $folder = '/evonee-quotes/' . gmdate('Y/m');
            $uploads['subdir'] = $folder;
            $uploads['path']   = $uploads['basedir'] . $folder;
            $uploads['url']    = $uploads['baseurl'] . $folder;
            return $uploads;
        };

        add_filter('upload_dir', $upload_dir_filter);

        $upload_overrides = [
            'test_form' => false,
            'mimes'     => $mimes
        ];

        $move_file = wp_handle_upload($file_array, $upload_overrides);

        remove_filter('upload_dir', $upload_dir_filter);

        if (isset($move_file['error'])) {
            return new WP_Error('upload_failed', $move_file['error']);
        }

        $uploaded_file_path = $move_file['file'];

        // SVG Sanitization pass — comprehensive XSS prevention
        if ($ext === 'svg' && file_exists($uploaded_file_path)) {
            $svg_content = file_get_contents($uploaded_file_path);
            if ($svg_content !== false) {
                // Strip <script> tags
                $clean_svg = preg_replace('/<script\b[^>]*>.*?<\/script>/is', '', $svg_content);
                // Strip inline event handlers (on*=) in both single and double quotes
                $clean_svg = preg_replace('/\s+on[a-z]+\s*=\s*"[^"]*"/i', '', $clean_svg);
                $clean_svg = preg_replace("/\s+on[a-z]+\s*=\s*'[^']*'/i", '', $clean_svg);
                // Strip <use> tags which can load external/malicious resources
                $clean_svg = preg_replace('/<use\b[^>]*\/?>/i', '', $clean_svg);
                // Strip <foreignObject> tags (allows arbitrary HTML injection)
                $clean_svg = preg_replace('/<foreignObject[^>]*>.*?<\/foreignObject>/is', '', $clean_svg);
                // Strip xlink:href and href with javascript: or data: URIs
                $clean_svg = preg_replace('/(?:xlink:href|href)\s*=\s*["\']?\s*(?:javascript|data):[^"\'>\s]*/i', 'href="#"', $clean_svg);
                // Strip style attributes containing javascript: or expression()
                $clean_svg = preg_replace('/style\s*=\s*"[^"]*(?:javascript:|expression\s*\()[^"]*"/i', '', $clean_svg);
                file_put_contents($uploaded_file_path, $clean_svg);
            }
        }

        return esc_url_raw($move_file['url']);
    }

    /**
     * Get Visitor Real Client IP Address
     */
    private function get_client_ip() {
        $ip_keys = ['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'];
        foreach ($ip_keys as $key) {
            if (!empty($_SERVER[$key])) {
                // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
                $raw_ip = sanitize_text_field(wp_unslash($_SERVER[$key]));
                $ip = trim(explode(',', $raw_ip)[0]);
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }
        return '127.0.0.1';
    }
}
