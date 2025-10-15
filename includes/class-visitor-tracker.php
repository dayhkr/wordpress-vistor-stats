<?php

namespace VisitorStats;

if (!defined('ABSPATH')) {
    exit;
}

class VisitorTracker {
    
    private $database;
    private $settings;
    
    public function __construct() {
        $this->database = new Database();
        $this->settings = new Settings();
        
        add_action('wp', array($this, 'track_visit'));
        add_action('wp_head', array($this, 'add_tracking_script'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_tracking_scripts'));
        add_action('wp_ajax_visitor_stats_track_behavior', array($this, 'ajax_track_behavior'));
        add_action('wp_ajax_nopriv_visitor_stats_track_behavior', array($this, 'ajax_track_behavior'));
        add_action('wp_ajax_visitor_stats_get_geo_data', array($this, 'ajax_get_geo_data'));
        add_action('wp_ajax_nopriv_visitor_stats_get_geo_data', array($this, 'ajax_get_geo_data'));
        add_action('wp_ajax_visitor_stats_record_visit', array($this, 'ajax_record_visit'));
        add_action('wp_ajax_nopriv_visitor_stats_record_visit', array($this, 'ajax_record_visit'));
        add_action('wp_ajax_visitor_stats_track_page', array($this, 'ajax_track_page'));
        add_action('wp_ajax_nopriv_visitor_stats_track_page', array($this, 'ajax_track_page'));
        
        // Schedule cleanup if auto cleanup is enabled
        if ($this->settings->get_setting('auto_cleanup', true)) {
            add_action('visitor_stats_daily_cleanup', array($this, 'daily_cleanup'));
            if (!wp_next_scheduled('visitor_stats_daily_cleanup')) {
                wp_schedule_event(time(), 'daily', 'visitor_stats_daily_cleanup');
            }
        }
    }
    
    /**
     * Add simple tracking script to head
     */
    public function add_tracking_script() {
        // Only track on frontend
        if (is_admin()) {
            return;
        }
        
        // Check if tracking is enabled
        if (!$this->settings->get_setting('tracking_enabled', true)) {
            return;
        }
        
        // Don't track logged-in users
        if (is_user_logged_in()) {
            return;
        }
        
        // Simple tracking script
        echo '<script>
        (function() {
            if (document.readyState === "loading") {
                document.addEventListener("DOMContentLoaded", trackVisit);
            } else {
                trackVisit();
            }
            
            function trackVisit() {
                var xhr = new XMLHttpRequest();
                xhr.open("POST", "' . admin_url('admin-ajax.php') . '", true);
                xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
                xhr.send("action=visitor_stats_track_page&nonce=' . wp_create_nonce('visitor_stats_track_page') . '");
            }
        })();
        </script>';
    }
    
    /**
     * Track visit on page load (server-side)
     */
    public function track_visit() {
        // Only track on frontend
        if (is_admin()) {
            return;
        }
        
        // Track visit - no debug needed for production
        
        // Check if tracking is enabled
        if (!$this->settings->get_setting('tracking_enabled', true)) {
            return;
        }
        
        // Don't track logged-in users
        if (is_user_logged_in()) {
            return;
        }
        
        // Check DNT header
        if ($this->settings->get_setting('respect_dnt_header', true) && $this->has_dnt_header()) {
            return;
        }
        
        // Get visitor IP
        $ip = $this->get_visitor_ip();
        if (!$ip || $this->settings->is_ip_excluded($ip)) {
            return;
        }
        
        // Anonymize IP if setting is enabled
        $ip_hash = $this->settings->get_setting('ip_anonymization', true) ? 
                   hash('sha256', $ip . wp_salt()) : $ip;
        
        // Generate or get session ID
        $session_id = $this->get_session_id();
        
        // Check if this is a unique visitor
        $is_unique = $this->database->is_unique_visitor($session_id, $ip_hash, 24);
        
        // Get page URL
        $page_url = $this->get_current_page_url();
        
        // Get referrer
        $referrer = $this->get_referrer();
        
        // Get user agent
        $user_agent = $this->get_user_agent();
        
        // Parse user agent for browser and device info
        $browser_info = $this->parse_user_agent($user_agent);
        
        // Get geographic data (async via AJAX to avoid blocking page load)
        $geo_data = array('country' => null, 'city' => null);
        
        $visit_data = array(
            'ip_hash' => $ip_hash,
            'page_url' => $page_url,
            'referrer' => $referrer,
            'user_agent' => $user_agent,
            'session_id' => $session_id,
            'country' => $geo_data['country'],
            'city' => $geo_data['city'],
            'browser' => $browser_info['browser'],
            'device_type' => $browser_info['device_type'],
            'is_unique_visitor' => $is_unique
        );
        
        // Record visit directly (will be fast enough for most sites)
        $this->database->record_visit($visit_data);
    }
    
    /**
     * Record visit asynchronously
     */
    private function record_visit_async($visit_data) {
        // Use WordPress background processing or simple async call
        wp_remote_post(
            admin_url('admin-ajax.php'),
            array(
                'body' => array(
                    'action' => 'visitor_stats_record_visit',
                    'visit_data' => wp_json_encode($visit_data),
                    'nonce' => wp_create_nonce('visitor_stats_record_visit')
                ),
                'blocking' => false,
                'timeout' => 1
            )
        );
    }
    
    /**
     * AJAX handler for tracking page visits
     */
    public function ajax_track_page() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'visitor_stats_track_page')) {
            wp_die('Security check failed');
        }
        
        // Get visitor data
        $ip = $this->get_visitor_ip();
        if (!$ip || $this->settings->is_ip_excluded($ip)) {
            wp_die();
        }
        
        // Anonymize IP if setting is enabled
        $ip_hash = $this->settings->get_setting('ip_anonymization', true) ? 
                   hash('sha256', $ip . wp_salt()) : $ip;
        
        // Generate or get session ID
        $session_id = $this->get_session_id();
        
        // Check if this is a unique visitor
        $is_unique = $this->database->is_unique_visitor($session_id, $ip_hash, 24);
        
        // Get page URL
        $page_url = $this->get_current_page_url();
        
        // Get referrer
        $referrer = $this->get_referrer();
        
        // Get user agent
        $user_agent = $this->get_user_agent();
        
        // Parse user agent for browser and device info
        $browser_info = $this->parse_user_agent($user_agent);
        
        // Get geographic data (try multiple services for better reliability)
        $geo_data = $this->get_geo_data_reliable($ip);
        
        $visit_data = array(
            'ip_hash' => $ip_hash,
            'page_url' => $page_url,
            'referrer' => $referrer,
            'user_agent' => $user_agent,
            'session_id' => $session_id,
            'country' => $geo_data['country'],
            'city' => $geo_data['city'],
            'browser' => $browser_info['browser'],
            'device_type' => $browser_info['device_type'],
            'is_unique_visitor' => $is_unique
        );
        
        // Record visit
        $this->database->record_visit($visit_data);
        
        wp_die();
    }
    
    /**
     * AJAX handler for recording visits
     */
    public function ajax_record_visit() {
        if (!wp_verify_nonce($_POST['nonce'], 'visitor_stats_record_visit')) {
            wp_die('Security check failed');
        }
        
        $visit_data = json_decode(stripslashes($_POST['visit_data']), true);
        
        if ($visit_data) {
            $this->database->record_visit($visit_data);
        }
        
        wp_die();
    }
    
    /**
     * Enqueue tracking scripts
     */
    public function enqueue_tracking_scripts() {
        // Only enqueue on frontend and if tracking is enabled
        if (is_admin() || !$this->settings->get_setting('tracking_enabled', true)) {
            return;
        }
        
        // Don't track logged-in users
        if (is_user_logged_in()) {
            return;
        }
        
        // Check if cookie consent is required
        if ($this->settings->get_setting('cookie_consent_required', false)) {
            // Only enqueue if consent is given
            if (!$this->has_tracking_consent()) {
                return;
            }
        }
        
        // Enqueue behavior tracking script
        if ($this->settings->get_setting('track_behavior', true)) {
            wp_enqueue_script(
                'visitor-stats-tracker',
                VISITOR_STATS_PLUGIN_URL . 'assets/js/tracker.js',
                array('jquery'),
                VISITOR_STATS_VERSION,
                true
            );
            
            wp_localize_script('visitor-stats-tracker', 'visitorStats', array(
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('visitor_stats_track_behavior'),
                'sessionId' => $this->get_session_id(),
                'pageUrl' => $this->get_current_page_url(),
                'geoEnabled' => $this->settings->get_setting('geoip_enabled', true)
            ));
        }
    }
    
    /**
     * AJAX handler for behavior tracking
     */
    public function ajax_track_behavior() {
        if (!wp_verify_nonce($_POST['nonce'], 'visitor_stats_track_behavior')) {
            wp_die('Security check failed');
        }
        
        $behavior_data = array(
            'session_id' => sanitize_text_field($_POST['session_id']),
            'page_url' => esc_url_raw($_POST['page_url']),
            'time_on_page' => intval($_POST['time_on_page']),
            'scroll_depth' => intval($_POST['scroll_depth']),
            'clicks' => intval($_POST['clicks'])
        );
        
        $this->database->record_behavior($behavior_data);
        
        wp_send_json_success();
    }
    
    /**
     * AJAX handler for getting geographic data
     */
    public function ajax_get_geo_data() {
        if (!wp_verify_nonce($_POST['nonce'], 'visitor_stats_get_geo_data')) {
            wp_die('Security check failed');
        }
        
        $ip = $this->get_visitor_ip();
        $geo_data = $this->get_geo_data($ip);
        
        wp_send_json_success($geo_data);
    }
    
    /**
     * Get visitor IP address
     */
    private function get_visitor_ip() {
        $ip_keys = array(
            'HTTP_CF_CONNECTING_IP',
            'HTTP_CLIENT_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_X_CLUSTER_CLIENT_IP',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'REMOTE_ADDR'
        );
        
        foreach ($ip_keys as $key) {
            if (array_key_exists($key, $_SERVER) === true) {
                $ip = $_SERVER[$key];
                if (strpos($ip, ',') !== false) {
                    $ip = explode(',', $ip)[0];
                }
                $ip = trim($ip);
                
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? '';
    }
    
    /**
     * Get or generate session ID
     */
    private function get_session_id() {
        $session_name = 'visitor_stats_session';
        
        if (!isset($_COOKIE[$session_name])) {
            $session_id = wp_generate_uuid4();
            setcookie($session_name, $session_id, time() + (30 * 24 * 60 * 60), '/', '', is_ssl(), true);
            $_COOKIE[$session_name] = $session_id;
        }
        
        return $_COOKIE[$session_name];
    }
    
    /**
     * Get current page URL
     */
    private function get_current_page_url() {
        global $wp;
        return home_url(add_query_arg(array(), $wp->request));
    }
    
    /**
     * Get referrer
     */
    private function get_referrer() {
        $referrer = $_SERVER['HTTP_REFERER'] ?? '';
        
        // Don't track internal referrers
        if ($referrer && strpos($referrer, home_url()) === 0) {
            return '';
        }
        
        return esc_url_raw($referrer);
    }
    
    /**
     * Get user agent
     */
    private function get_user_agent() {
        return $_SERVER['HTTP_USER_AGENT'] ?? '';
    }
    
    /**
     * Parse user agent for browser and device info
     */
    private function parse_user_agent($user_agent) {
        $browser = 'Unknown';
        $device_type = 'Desktop';
        
        // Detect mobile devices
        if (preg_match('/(android|iphone|ipad|ipod|blackberry|windows phone)/i', $user_agent)) {
            $device_type = 'Mobile';
            if (preg_match('/ipad/i', $user_agent)) {
                $device_type = 'Tablet';
            }
        }
        
        // Detect browsers
        if (strpos($user_agent, 'Chrome') !== false) {
            $browser = 'Chrome';
        } elseif (strpos($user_agent, 'Firefox') !== false) {
            $browser = 'Firefox';
        } elseif (strpos($user_agent, 'Safari') !== false) {
            $browser = 'Safari';
        } elseif (strpos($user_agent, 'Edge') !== false) {
            $browser = 'Edge';
        } elseif (strpos($user_agent, 'MSIE') !== false || strpos($user_agent, 'Trident') !== false) {
            $browser = 'Internet Explorer';
        } elseif (strpos($user_agent, 'Opera') !== false) {
            $browser = 'Opera';
        }
        
        return array(
            'browser' => $browser,
            'device_type' => $device_type
        );
    }
    
    /**
     * Get geographic data with multiple fallback services
     */
    private function get_geo_data_reliable($ip) {
        // For local/private IPs, try to get country data anyway (might be US)
        if ($this->is_private_ip($ip)) {
            // Try to get geographic data even for private IPs
            $geo_data = $this->get_geo_data_ipapi($ip);
            if ($geo_data['country']) {
                return $geo_data;
            }
            // If no country found, return Local
            return array('country' => 'Local', 'city' => 'Local');
        }
        
        // Try multiple services for better reliability
        $geo_data = $this->get_geo_data_ipapi($ip);
        if ($geo_data['country']) {
            return $geo_data;
        }
        
        $geo_data = $this->get_geo_data_ipinfo($ip);
        if ($geo_data['country']) {
            return $geo_data;
        }
        
        $geo_data = $this->get_geo_data_ipapi_com($ip);
        if ($geo_data['country']) {
            return $geo_data;
        }
        
        return array('country' => 'Unknown', 'city' => 'Unknown');
    }
    
    /**
     * Check if IP is private/local
     */
    private function is_private_ip($ip) {
        return !filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
    }
    
    /**
     * Get geographic data from ip-api.com
     */
    private function get_geo_data_ipapi($ip) {
        $response = wp_remote_get("http://ip-api.com/json/{$ip}?fields=country,city,status", array(
            'timeout' => 3,
            'user-agent' => 'WordPress Visitor Stats Plugin'
        ));
        
        if (is_wp_error($response)) {
            return array('country' => null, 'city' => null);
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if ($data && $data['status'] === 'success') {
            return array(
                'country' => $data['country'] ?? null,
                'city' => $data['city'] ?? null
            );
        }
        
        return array('country' => null, 'city' => null);
    }
    
    /**
     * Get geographic data from ipinfo.io
     */
    private function get_geo_data_ipinfo($ip) {
        $response = wp_remote_get("https://ipinfo.io/{$ip}/json", array(
            'timeout' => 3,
            'user-agent' => 'WordPress Visitor Stats Plugin'
        ));
        
        if (is_wp_error($response)) {
            return array('country' => null, 'city' => null);
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if ($data && isset($data['country'])) {
            return array(
                'country' => $data['country'] ?? null,
                'city' => $data['city'] ?? null
            );
        }
        
        return array('country' => null, 'city' => null);
    }
    
    /**
     * Get geographic data from ip-api.com (alternative)
     */
    private function get_geo_data_ipapi_com($ip) {
        $response = wp_remote_get("https://ip-api.com/json/{$ip}?fields=country,city,status", array(
            'timeout' => 3,
            'user-agent' => 'WordPress Visitor Stats Plugin'
        ));
        
        if (is_wp_error($response)) {
            return array('country' => null, 'city' => null);
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if ($data && $data['status'] === 'success') {
            return array(
                'country' => $data['country'] ?? null,
                'city' => $data['city'] ?? null
            );
        }
        
        return array('country' => null, 'city' => null);
    }
    
    /**
     * Get geographic data from IP (original method)
     */
    private function get_geo_data($ip) {
        if (!$this->settings->get_setting('geoip_enabled', true)) {
            return array('country' => null, 'city' => null);
        }
        
        // Use free IP API service
        $response = wp_remote_get("http://ip-api.com/json/{$ip}?fields=country,city,status");
        
        if (is_wp_error($response)) {
            return array('country' => null, 'city' => null);
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if ($data && $data['status'] === 'success') {
            return array(
                'country' => $data['country'] ?? null,
                'city' => $data['city'] ?? null
            );
        }
        
        return array('country' => null, 'city' => null);
    }
    
    /**
     * Check if visitor has Do Not Track header
     */
    private function has_dnt_header() {
        return isset($_SERVER['HTTP_DNT']) && $_SERVER['HTTP_DNT'] === '1';
    }
    
    /**
     * Check if visitor has given tracking consent
     */
    private function has_tracking_consent() {
        return isset($_COOKIE['visitor_stats_consent']) && $_COOKIE['visitor_stats_consent'] === 'true';
    }
    
    /**
     * Daily cleanup task
     */
    public function daily_cleanup() {
        $retention_days = $this->settings->get_setting('data_retention_days', 365);
        $this->database->clean_old_data($retention_days);
    }
}
