<?php

namespace VisitorStats;

if (!defined('ABSPATH')) {
    exit;
}

class Database {
    
    private $wpdb;
    private $visits_table;
    private $behavior_table;
    private $settings_table;
    
    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->visits_table = $wpdb->prefix . 'visitor_stats_visits';
        $this->behavior_table = $wpdb->prefix . 'visitor_stats_behavior';
        $this->settings_table = $wpdb->prefix . 'visitor_stats_settings';
    }
    
    /**
     * Create database tables
     */
    public function create_tables() {
        global $wpdb;
        $this->wpdb = $wpdb;
        $charset_collate = $this->wpdb->get_charset_collate();
        
        // Visits table
        $visits_sql = "CREATE TABLE {$this->visits_table} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            timestamp datetime NOT NULL,
            ip_hash varchar(255) NOT NULL,
            page_url varchar(2048) NOT NULL,
            referrer varchar(2048) DEFAULT NULL,
            user_agent text,
            session_id varchar(255) NOT NULL,
            country varchar(100) DEFAULT NULL,
            city varchar(100) DEFAULT NULL,
            browser varchar(100) DEFAULT NULL,
            device_type varchar(50) DEFAULT NULL,
            is_unique_visitor tinyint(1) DEFAULT 0,
            PRIMARY KEY (id),
            KEY timestamp (timestamp),
            KEY session_id (session_id),
            KEY ip_hash (ip_hash),
            KEY page_url (page_url(191))
        ) $charset_collate;";
        
        // Behavior table
        $behavior_sql = "CREATE TABLE {$this->behavior_table} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            session_id varchar(255) NOT NULL,
            page_url varchar(2048) NOT NULL,
            time_on_page int(11) DEFAULT 0,
            scroll_depth int(3) DEFAULT 0,
            clicks int(11) DEFAULT 0,
            exit_time datetime DEFAULT NULL,
            PRIMARY KEY (id),
            KEY session_id (session_id),
            KEY page_url (page_url(191))
        ) $charset_collate;";
        
        // Settings table
        $settings_sql = "CREATE TABLE {$this->settings_table} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            setting_key varchar(100) NOT NULL,
            setting_value longtext,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY setting_key (setting_key)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($visits_sql);
        dbDelta($behavior_sql);
        dbDelta($settings_sql);
        
        // Update database version
        update_option('visitor_stats_db_version', '1.0');
    }
    
    /**
     * Record a visit
     */
    public function record_visit($data) {
        return $this->wpdb->insert(
            $this->visits_table,
            array(
                'timestamp' => current_time('mysql'),
                'ip_hash' => $data['ip_hash'],
                'page_url' => $data['page_url'],
                'referrer' => $data['referrer'],
                'user_agent' => $data['user_agent'],
                'session_id' => $data['session_id'],
                'country' => $data['country'],
                'city' => $data['city'],
                'browser' => $data['browser'],
                'device_type' => $data['device_type'],
                'is_unique_visitor' => $data['is_unique_visitor']
            ),
            array('%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d')
        );
    }
    
    /**
     * Record behavior data
     */
    public function record_behavior($data) {
        return $this->wpdb->insert(
            $this->behavior_table,
            array(
                'session_id' => $data['session_id'],
                'page_url' => $data['page_url'],
                'time_on_page' => $data['time_on_page'],
                'scroll_depth' => $data['scroll_depth'],
                'clicks' => $data['clicks'],
                'exit_time' => current_time('mysql')
            ),
            array('%s', '%s', '%d', '%d', '%d', '%s')
        );
    }
    
    /**
     * Get visit statistics
     */
    public function get_visit_stats($start_date = null, $end_date = null) {
        $where_clause = $this->build_date_where_clause($start_date, $end_date);
        
        $sql = "SELECT 
                    COUNT(*) as total_visits,
                    COUNT(DISTINCT session_id) as unique_visitors,
                    COUNT(DISTINCT ip_hash) as unique_ips
                FROM {$this->visits_table} 
                WHERE 1=1 {$where_clause}";
        
        return $this->wpdb->get_row($sql);
    }
    
    /**
     * Get top pages
     */
    public function get_top_pages($start_date = null, $end_date = null, $limit = 10) {
        $where_clause = $this->build_date_where_clause($start_date, $end_date);
        
        $sql = $this->wpdb->prepare(
            "SELECT 
                page_url,
                COUNT(*) as page_views,
                COUNT(DISTINCT session_id) as unique_visitors
            FROM {$this->visits_table} 
            WHERE 1=1 {$where_clause}
            GROUP BY page_url 
            ORDER BY page_views DESC 
            LIMIT %d",
            $limit
        );
        
        return $this->wpdb->get_results($sql);
    }
    
    /**
     * Get browser statistics
     */
    public function get_browser_stats($start_date = null, $end_date = null) {
        $where_clause = $this->build_date_where_clause($start_date, $end_date);
        
        $sql = "SELECT 
                    browser,
                    COUNT(*) as count
                FROM {$this->visits_table} 
                WHERE 1=1 {$where_clause} AND browser IS NOT NULL
                GROUP BY browser 
                ORDER BY count DESC";
        
        return $this->wpdb->get_results($sql);
    }
    
    /**
     * Get device statistics
     */
    public function get_device_stats($start_date = null, $end_date = null) {
        $where_clause = $this->build_date_where_clause($start_date, $end_date);
        
        $sql = "SELECT 
                    device_type,
                    COUNT(*) as count
                FROM {$this->visits_table} 
                WHERE 1=1 {$where_clause} AND device_type IS NOT NULL
                GROUP BY device_type 
                ORDER BY count DESC";
        
        return $this->wpdb->get_results($sql);
    }
    
    /**
     * Get geographic statistics
     */
    public function get_geo_stats($start_date = null, $end_date = null) {
        $where_clause = $this->build_date_where_clause($start_date, $end_date);
        
        $sql = "SELECT 
                    country,
                    COUNT(*) as count
                FROM {$this->visits_table} 
                WHERE 1=1 {$where_clause} AND country IS NOT NULL
                GROUP BY country 
                ORDER BY count DESC";
        
        return $this->wpdb->get_results($sql);
    }
    
    /**
     * Get referrer statistics
     */
    public function get_referrer_stats($start_date = null, $end_date = null, $limit = 10) {
        $where_clause = $this->build_date_where_clause($start_date, $end_date);
        
        $sql = $this->wpdb->prepare(
            "SELECT 
                referrer,
                COUNT(*) as count
            FROM {$this->visits_table} 
            WHERE 1=1 {$where_clause} AND referrer IS NOT NULL AND referrer != ''
            GROUP BY referrer 
            ORDER BY count DESC 
            LIMIT %d",
            $limit
        );
        
        return $this->wpdb->get_results($sql);
    }
    
    /**
     * Get visits over time (for charts)
     */
    public function get_visits_over_time($start_date = null, $end_date = null, $group_by = 'day') {
        $where_clause = $this->build_date_where_clause($start_date, $end_date);
        
        switch ($group_by) {
            case 'hour':
                $date_format = '%Y-%m-%d %H:00:00';
                break;
            case 'day':
            default:
                $date_format = '%Y-%m-%d';
                break;
            case 'week':
                $date_format = '%Y-%u';
                break;
            case 'month':
                $date_format = '%Y-%m';
                break;
        }
        
        $sql = "SELECT 
                    DATE_FORMAT(timestamp, '{$date_format}') as period,
                    COUNT(*) as visits,
                    COUNT(DISTINCT session_id) as unique_visitors
                FROM {$this->visits_table} 
                WHERE 1=1 {$where_clause}
                GROUP BY period 
                ORDER BY period ASC";
        
        return $this->wpdb->get_results($sql);
    }
    
    /**
     * Check if visitor is unique within time period
     */
    public function is_unique_visitor($session_id, $ip_hash, $hours = 24) {
        $sql = $this->wpdb->prepare(
            "SELECT COUNT(*) 
            FROM {$this->visits_table} 
            WHERE (session_id = %s OR ip_hash = %s) 
            AND timestamp > DATE_SUB(NOW(), INTERVAL %d HOUR)",
            $session_id,
            $ip_hash,
            $hours
        );
        
        return $this->wpdb->get_var($sql) == 0;
    }
    
    /**
     * Clean old data based on retention period
     */
    public function clean_old_data($days = 365) {
        $sql = $this->wpdb->prepare(
            "DELETE FROM {$this->visits_table} 
            WHERE timestamp < DATE_SUB(NOW(), INTERVAL %d DAY)",
            $days
        );
        
        $this->wpdb->query($sql);
        
        $sql = $this->wpdb->prepare(
            "DELETE FROM {$this->behavior_table} 
            WHERE exit_time < DATE_SUB(NOW(), INTERVAL %d DAY)",
            $days
        );
        
        return $this->wpdb->query($sql);
    }
    
    /**
     * Get setting value
     */
    public function get_setting($key, $default = null) {
        $sql = $this->wpdb->prepare(
            "SELECT setting_value FROM {$this->settings_table} WHERE setting_key = %s",
            $key
        );
        
        $value = $this->wpdb->get_var($sql);
        return $value !== null ? maybe_unserialize($value) : $default;
    }
    
    /**
     * Set setting value
     */
    public function set_setting($key, $value) {
        $serialized_value = maybe_serialize($value);
        
        return $this->wpdb->replace(
            $this->settings_table,
            array(
                'setting_key' => $key,
                'setting_value' => $serialized_value
            ),
            array('%s', '%s')
        );
    }
    
    /**
     * Build date where clause for queries
     */
    private function build_date_where_clause($start_date = null, $end_date = null) {
        $where = '';
        
        if ($start_date) {
            $where .= $this->wpdb->prepare(" AND timestamp >= %s", $start_date);
        }
        
        if ($end_date) {
            $where .= $this->wpdb->prepare(" AND timestamp <= %s", $end_date);
        }
        
        return $where;
    }
    
    /**
     * Get table names
     */
    public function get_table_names() {
        return array(
            'visits' => $this->visits_table,
            'behavior' => $this->behavior_table,
            'settings' => $this->settings_table
        );
    }
}
