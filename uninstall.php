<?php
/**
 * Uninstall Visitor Stats Plugin
 * 
 * This file is executed when the plugin is uninstalled (deleted) from WordPress.
 */

// If uninstall not called from WordPress, then exit
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Remove database tables
global $wpdb;

$visits_table = $wpdb->prefix . 'visitor_stats_visits';
$behavior_table = $wpdb->prefix . 'visitor_stats_behavior';
$settings_table = $wpdb->prefix . 'visitor_stats_settings';

$wpdb->query("DROP TABLE IF EXISTS {$visits_table}");
$wpdb->query("DROP TABLE IF EXISTS {$behavior_table}");
$wpdb->query("DROP TABLE IF EXISTS {$settings_table}");

// Remove scheduled events
wp_clear_scheduled_hook('visitor_stats_daily_cleanup');

// Remove plugin options
delete_option('visitor_stats_db_version');

// Clear any cached data
wp_cache_flush();
