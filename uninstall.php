<?php
defined('WP_UNINSTALL_PLUGIN') || exit;
global $wpdb;
foreach(['fcb_retention','fcb_weekly_report','fcb_insights_cleanup'] as $hook){wp_clear_scheduled_hook($hook);}
$wpdb->query('DROP TABLE IF EXISTS ' . $wpdb->prefix . 'fcb_guards');
$config = get_option('fcb_live', []);
if (!empty($config['delete_uninstall'])) {
    $wpdb->query('DROP TABLE IF EXISTS ' . $wpdb->prefix . 'fcb_enquiries');
    $wpdb->query('DROP TABLE IF EXISTS ' . $wpdb->prefix . 'fcb_insights');
    foreach (['fcb_live','fcb_draft','fcb_schema','fcb_setup_dismissed','fcb_report_due','fcb_last_report','fcb_last_report_test','fcb_report_config_audit'] as $key) { delete_option($key); }
}
