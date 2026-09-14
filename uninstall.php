<?php
/**
 * Evonee Uninstall Script
 *
 * Runs when plugin is uninstalled/deleted via WordPress Admin.
 *
 * @package Evonee
 */

// If uninstall not called from WordPress, exit.
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

global $wpdb;

// Check if user requested to wipe data on uninstall in settings
$settings = get_option('evonee_quote_settings', []);
$delete_data_on_uninstall = !empty($settings['delete_data_on_uninstall']);

// Clear scheduled cron jobs regardless
wp_clear_scheduled_hook('evonee_weekly_digest_cron');
wp_clear_scheduled_hook('evonee_daily_quote_cron');

if ($delete_data_on_uninstall) {
    // Drop all custom tables
    $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}eq_quote_submissions");
    $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}eq_quote_activity_log");
    $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}eq_email_log");
    $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}eq_quote_messages");

    // Delete options
    delete_option('evonee_quote_settings');
    delete_option('evonee_db_schema_version');
    delete_option('evonee_dashboard_stats');
    delete_option('evonee_db_version');

    // Delete all related transients
    $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_eq_%' OR option_name LIKE '_transient_timeout_eq_%'");
}
