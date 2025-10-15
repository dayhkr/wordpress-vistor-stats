<?php

namespace VisitorStats;

if (!defined('ABSPATH')) {
    exit;
}

class AdminDashboard {
    
    private $database;
    
    public function __construct() {
        $this->database = new Database();
        
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        add_action('wp_ajax_visitor_stats_get_data', array($this, 'ajax_get_dashboard_data'));
        add_action('wp_ajax_visitor_stats_export_data', array($this, 'ajax_export_data'));
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_menu_page(
            'Visitor Stats',
            'Visitor Stats',
            'manage_options',
            'visitor-stats',
            array($this, 'dashboard_page'),
            'dashicons-chart-line',
            30
        );
        
        // Add Settings submenu
        add_submenu_page(
            'visitor-stats',
            'Settings',
            'Settings',
            'manage_options',
            'visitor-stats-settings',
            array($this, 'settings_page')
        );
        
        // Test data functionality removed for production
    }
    
    /**
     * Enqueue admin scripts and styles
     */
    public function enqueue_admin_scripts($hook) {
        if ($hook !== 'toplevel_page_visitor-stats') {
            return;
        }
        
        // Enqueue Chart.js
        wp_enqueue_script(
            'chart-js',
            'https://cdn.jsdelivr.net/npm/chart.js',
            array(),
            '3.9.1',
            true
        );
        
        // Enqueue admin script
        wp_enqueue_script(
            'visitor-stats-admin',
            VISITOR_STATS_PLUGIN_URL . 'assets/js/admin-script.js',
            array('jquery', 'chart-js'),
            VISITOR_STATS_VERSION,
            true
        );
        
        // Enqueue admin styles
        wp_enqueue_style(
            'visitor-stats-admin',
            VISITOR_STATS_PLUGIN_URL . 'assets/css/admin-style.css',
            array(),
            VISITOR_STATS_VERSION
        );
        
        // Localize script
        wp_localize_script('visitor-stats-admin', 'visitorStatsAdmin', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('visitor_stats_admin'),
            'strings' => array(
                'loading' => __('Loading...', 'visitor-stats'),
                'error' => __('Error loading data', 'visitor-stats'),
                'noData' => __('No data available', 'visitor-stats')
            )
        ));
        
        // Script loaded successfully
    }
    
    /**
     * Dashboard page HTML
     */
    public function dashboard_page() {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'visitor-stats'));
        }
        
        ?>
        <div class="wrap visitor-stats-dashboard">
            <h1><?php _e('Visitor Stats Dashboard', 'visitor-stats'); ?></h1>
            
            <!-- Dashboard is ready -->
            
            <!-- Time Range Selector -->
            <div class="visitor-stats-controls">
                <div class="visitor-stats-time-range">
                    <label for="time-range"><?php _e('Time Range:', 'visitor-stats'); ?></label>
                    <select id="time-range" name="time_range">
                        <option value="today"><?php _e('Today', 'visitor-stats'); ?></option>
                        <option value="last_7_days" selected><?php _e('Last 7 Days', 'visitor-stats'); ?></option>
                        <option value="last_30_days"><?php _e('Last 30 Days', 'visitor-stats'); ?></option>
                        <option value="last_90_days"><?php _e('Last 90 Days', 'visitor-stats'); ?></option>
                        <option value="all_time"><?php _e('All Time', 'visitor-stats'); ?></option>
                        <option value="custom"><?php _e('Custom Range', 'visitor-stats'); ?></option>
                    </select>
                </div>
                
                <div class="visitor-stats-custom-range" style="display: none;">
                    <label for="start-date"><?php _e('Start Date:', 'visitor-stats'); ?></label>
                    <input type="date" id="start-date" name="start_date">
                    
                    <label for="end-date"><?php _e('End Date:', 'visitor-stats'); ?></label>
                    <input type="date" id="end-date" name="end_date">
                </div>
                
                <button type="button" id="refresh-data" class="button button-primary">
                    <?php _e('Refresh', 'visitor-stats'); ?>
                </button>
                
                <button type="button" id="export-data" class="button">
                    <?php _e('Export CSV', 'visitor-stats'); ?>
                </button>
            </div>
            
            <!-- Overview Cards -->
            <div class="visitor-stats-overview">
                <div class="visitor-stats-card">
                    <h3><?php _e('Total Visits', 'visitor-stats'); ?></h3>
                    <div class="visitor-stats-number" id="total-visits">-</div>
                </div>
                
                <div class="visitor-stats-card">
                    <h3><?php _e('Unique Visitors', 'visitor-stats'); ?></h3>
                    <div class="visitor-stats-number" id="unique-visitors">-</div>
                </div>
                
                <div class="visitor-stats-card">
                    <h3><?php _e('Page Views', 'visitor-stats'); ?></h3>
                    <div class="visitor-stats-number" id="page-views">-</div>
                </div>
                
                <div class="visitor-stats-card">
                    <h3><?php _e('Bounce Rate', 'visitor-stats'); ?></h3>
                    <div class="visitor-stats-number" id="bounce-rate">-</div>
                </div>
            </div>
            
            <!-- Charts Row -->
            <div class="visitor-stats-charts">
                <div class="visitor-stats-chart-container">
                    <h3><?php _e('Visits Over Time', 'visitor-stats'); ?></h3>
                    <canvas id="visits-chart"></canvas>
                </div>
                
                <div class="visitor-stats-chart-container">
                    <h3><?php _e('Top Browsers', 'visitor-stats'); ?></h3>
                    <canvas id="browsers-chart"></canvas>
                </div>
            </div>
            
            <div class="visitor-stats-charts">
                <div class="visitor-stats-chart-container">
                    <h3><?php _e('Device Types', 'visitor-stats'); ?></h3>
                    <canvas id="devices-chart"></canvas>
                </div>
                
                <div class="visitor-stats-chart-container">
                    <h3><?php _e('Top Countries', 'visitor-stats'); ?></h3>
                    <canvas id="countries-chart"></canvas>
                </div>
            </div>
            
            <!-- Data Tables -->
            <div class="visitor-stats-tables">
                <div class="visitor-stats-table-container">
                    <h3><?php _e('Top Pages', 'visitor-stats'); ?></h3>
                    <div class="visitor-stats-table-wrapper">
                        <table id="top-pages-table" class="wp-list-table widefat fixed striped">
                            <thead>
                                <tr>
                                    <th><?php _e('Page URL', 'visitor-stats'); ?></th>
                                    <th><?php _e('Views', 'visitor-stats'); ?></th>
                                    <th><?php _e('Unique Visitors', 'visitor-stats'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td colspan="3" class="visitor-stats-loading">
                                        <?php _e('Loading...', 'visitor-stats'); ?>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <div class="visitor-stats-table-container">
                    <h3><?php _e('Top Referrers', 'visitor-stats'); ?></h3>
                    <div class="visitor-stats-table-wrapper">
                        <table id="top-referrers-table" class="wp-list-table widefat fixed striped">
                            <thead>
                                <tr>
                                    <th><?php _e('Referrer', 'visitor-stats'); ?></th>
                                    <th><?php _e('Visits', 'visitor-stats'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td colspan="2" class="visitor-stats-loading">
                                        <?php _e('Loading...', 'visitor-stats'); ?>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            
            <!-- Real-time Visitors -->
            <div class="visitor-stats-realtime">
                <h3><?php _e('Recent Visitors', 'visitor-stats'); ?></h3>
                <div class="visitor-stats-table-wrapper">
                    <table id="recent-visitors-table" class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th><?php _e('Time', 'visitor-stats'); ?></th>
                                <th><?php _e('Page', 'visitor-stats'); ?></th>
                                <th><?php _e('Country', 'visitor-stats'); ?></th>
                                <th><?php _e('Browser', 'visitor-stats'); ?></th>
                                <th><?php _e('Device', 'visitor-stats'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td colspan="5" class="visitor-stats-loading">
                                    <?php _e('Loading...', 'visitor-stats'); ?>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Settings page
     */
    public function settings_page() {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'visitor-stats'));
        }
        
        $settings = $this->get_all_settings();
        ?>
        <div class="wrap">
            <h1><?php _e('Visitor Stats Settings', 'visitor-stats'); ?></h1>
            
            <form id="visitor-stats-settings-form">
                <?php wp_nonce_field('visitor_stats_settings', 'visitor_stats_nonce'); ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php _e('Enable Tracking', 'visitor-stats'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="tracking_enabled" value="1" <?php checked($settings['tracking_enabled'], true); ?>>
                                <?php _e('Enable visitor tracking', 'visitor-stats'); ?>
                            </label>
                            <p class="description"><?php _e('When disabled, no new visitor data will be collected.', 'visitor-stats'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php _e('IP Anonymization', 'visitor-stats'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="ip_anonymization" value="1" <?php checked($settings['ip_anonymization'], true); ?>>
                                <?php _e('Anonymize IP addresses (GDPR compliant)', 'visitor-stats'); ?>
                            </label>
                            <p class="description"><?php _e('IP addresses will be hashed to protect visitor privacy.', 'visitor-stats'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php _e('Data Retention', 'visitor-stats'); ?></th>
                        <td>
                            <input type="number" name="data_retention_days" value="<?php echo esc_attr($settings['data_retention_days']); ?>" min="1" max="3650">
                            <p class="description"><?php _e('Number of days to keep visitor data (1-3650).', 'visitor-stats'); ?></p>
                        </td>
                    </tr>
                </table>
                
                <div class="visitor-stats-settings-actions">
                    <button type="submit" class="button button-primary"><?php _e('Save Settings', 'visitor-stats'); ?></button>
                    <button type="button" id="visitor-stats-reset-data" class="button button-secondary"><?php _e('Reset All Data', 'visitor-stats'); ?></button>
                </div>
            </form>
            
            <div id="visitor-stats-message" style="display: none;"></div>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            $('#visitor-stats-settings-form').on('submit', function(e) {
                e.preventDefault();
                
                var formData = $(this).serialize();
                var messageDiv = $('#visitor-stats-message');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: formData + '&action=visitor_stats_save_settings',
                    success: function(response) {
                        if (response.success) {
                            messageDiv.removeClass('notice-error').addClass('notice notice-success is-dismissible')
                                .html('<p>' + response.data.message + '</p>').show();
                        } else {
                            messageDiv.removeClass('notice-success').addClass('notice notice-error is-dismissible')
                                .html('<p>' + response.data.message + '</p>').show();
                        }
                    },
                    error: function() {
                        messageDiv.removeClass('notice-success').addClass('notice notice-error is-dismissible')
                            .html('<p>Error saving settings. Please try again.</p>').show();
                    }
                });
            });
        });
        </script>
        <?php
    }
    
    // Test data methods removed for production
    
    /**
     * Get all settings (helper method)
     */
    private function get_all_settings() {
        return array(
            'tracking_enabled' => $this->database->get_setting('tracking_enabled', true),
            'ip_anonymization' => $this->database->get_setting('ip_anonymization', true),
            'data_retention_days' => $this->database->get_setting('data_retention_days', 365)
        );
    }
    
    /**
     * AJAX handler for getting dashboard data
     */
    public function ajax_get_dashboard_data() {
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Insufficient permissions'));
            return;
        }
        
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'visitor_stats_admin')) {
            wp_send_json_error(array('message' => 'Security check failed'));
            return;
        }
        
        $time_range = sanitize_text_field($_POST['time_range']);
        $start_date = sanitize_text_field($_POST['start_date'] ?? '');
        $end_date = sanitize_text_field($_POST['end_date'] ?? '');
        
        // Calculate date range
        $dates = $this->calculate_date_range($time_range, $start_date, $end_date);
        
        try {
        $data = array(
            'overview' => $this->get_overview_data($dates['start'], $dates['end']),
            'visits_over_time' => $this->get_visits_over_time($dates['start'], $dates['end']),
            'browser_stats' => $this->get_browser_stats($dates['start'], $dates['end']),
            'device_stats' => $this->get_device_stats($dates['start'], $dates['end']),
            'geo_stats' => $this->get_geo_stats($dates['start'], $dates['end']),
            'top_pages' => $this->get_top_pages($dates['start'], $dates['end']),
            'top_referrers' => $this->get_referrer_stats($dates['start'], $dates['end']),
            'recent_visitors' => $this->get_recent_visitors()
        );
        
        // Data loaded successfully
        } catch (Exception $e) {
            wp_send_json_error(array('message' => 'Error loading data: ' . $e->getMessage()));
            return;
        }
        
        wp_send_json_success($data);
    }
    
    /**
     * AJAX handler for exporting data
     */
    public function ajax_export_data() {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions.', 'visitor-stats'));
        }
        
        if (!wp_verify_nonce($_POST['nonce'], 'visitor_stats_admin')) {
            wp_die(__('Security check failed.', 'visitor-stats'));
        }
        
        $time_range = sanitize_text_field($_POST['time_range']);
        $start_date = sanitize_text_field($_POST['start_date'] ?? '');
        $end_date = sanitize_text_field($_POST['end_date'] ?? '');
        
        $dates = $this->calculate_date_range($time_range, $start_date, $end_date);
        
        $csv_data = $this->generate_csv_data($dates['start'], $dates['end']);
        
        $filename = 'visitor-stats-' . date('Y-m-d') . '.csv';
        
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        
        echo $csv_data;
        exit;
    }
    
    /**
     * Calculate date range based on selection
     */
    private function calculate_date_range($time_range, $start_date = '', $end_date = '') {
        $now = current_time('timestamp');
        
        switch ($time_range) {
            case 'today':
                $start = date('Y-m-d 00:00:00', $now);
                $end = date('Y-m-d 23:59:59', $now);
                break;
            case 'last_7_days':
                $start = date('Y-m-d 00:00:00', $now - (7 * 24 * 60 * 60));
                $end = date('Y-m-d 23:59:59', $now);
                break;
            case 'last_30_days':
                $start = date('Y-m-d 00:00:00', $now - (30 * 24 * 60 * 60));
                $end = date('Y-m-d 23:59:59', $now);
                break;
            case 'last_90_days':
                $start = date('Y-m-d 00:00:00', $now - (90 * 24 * 60 * 60));
                $end = date('Y-m-d 23:59:59', $now);
                break;
            case 'custom':
                $start = $start_date ? $start_date . ' 00:00:00' : null;
                $end = $end_date ? $end_date . ' 23:59:59' : null;
                break;
            case 'all_time':
            default:
                $start = null;
                $end = null;
                break;
        }
        
        return array(
            'start' => $start,
            'end' => $end
        );
    }
    
    /**
     * Get overview statistics
     */
    private function get_overview_data($start_date = null, $end_date = null) {
        $stats = $this->database->get_visit_stats($start_date, $end_date);
        
        return array(
            'total_visits' => intval($stats->total_visits),
            'unique_visitors' => intval($stats->unique_visitors),
            'page_views' => intval($stats->total_visits), // Same as total visits for now
            'bounce_rate' => '0%' // TODO: Calculate bounce rate
        );
    }
    
    /**
     * Get visits over time data
     */
    private function get_visits_over_time($start_date = null, $end_date = null) {
        return $this->database->get_visits_over_time($start_date, $end_date);
    }
    
    /**
     * Get browser statistics
     */
    private function get_browser_stats($start_date = null, $end_date = null) {
        return $this->database->get_browser_stats($start_date, $end_date);
    }
    
    /**
     * Get device statistics
     */
    private function get_device_stats($start_date = null, $end_date = null) {
        return $this->database->get_device_stats($start_date, $end_date);
    }
    
    /**
     * Get geographic statistics
     */
    private function get_geo_stats($start_date = null, $end_date = null) {
        return $this->database->get_geo_stats($start_date, $end_date);
    }
    
    /**
     * Get top pages
     */
    private function get_top_pages($start_date = null, $end_date = null) {
        return $this->database->get_top_pages($start_date, $end_date, 10);
    }
    
    /**
     * Get referrer statistics
     */
    private function get_referrer_stats($start_date = null, $end_date = null) {
        return $this->database->get_referrer_stats($start_date, $end_date, 10);
    }
    
    /**
     * Get recent visitors
     */
    private function get_recent_visitors() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'visitor_stats_visits';
        
        $sql = "SELECT 
                    timestamp,
                    page_url,
                    country,
                    browser,
                    device_type
                FROM {$table_name} 
                ORDER BY timestamp DESC 
                LIMIT 20";
        
        return $wpdb->get_results($sql);
    }
    
    /**
     * Generate CSV data for export
     */
    private function generate_csv_data($start_date = null, $end_date = null) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'visitor_stats_visits';
        
        $where_clause = '';
        if ($start_date) {
            $where_clause .= $wpdb->prepare(" AND timestamp >= %s", $start_date);
        }
        if ($end_date) {
            $where_clause .= $wpdb->prepare(" AND timestamp <= %s", $end_date);
        }
        
        $sql = "SELECT 
                    timestamp,
                    page_url,
                    referrer,
                    country,
                    city,
                    browser,
                    device_type,
                    is_unique_visitor
                FROM {$table_name} 
                WHERE 1=1 {$where_clause}
                ORDER BY timestamp DESC";
        
        $results = $wpdb->get_results($sql);
        
        $output = fopen('php://output', 'w');
        
        // CSV header
        fputcsv($output, array(
            'Timestamp',
            'Page URL',
            'Referrer',
            'Country',
            'City',
            'Browser',
            'Device Type',
            'Unique Visitor'
        ));
        
        // CSV data
        foreach ($results as $row) {
            fputcsv($output, array(
                $row->timestamp,
                $row->page_url,
                $row->referrer,
                $row->country,
                $row->city,
                $row->browser,
                $row->device_type,
                $row->is_unique_visitor ? 'Yes' : 'No'
            ));
        }
        
        fclose($output);
    }
}
