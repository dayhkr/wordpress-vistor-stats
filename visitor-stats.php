<?php
/**
 * Plugin Name: Visitor Stats
 * Plugin URI: https://github.com/dayhkr/wordpress-vistor-stats
 * Description: Track visitor statistics and display them in a comprehensive WordPress dashboard. Includes privacy features.
 * Version: 1.0.4
 * Author: dayhkr
 * Author URI: https://github.com/dayhkr
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: visitor-stats
 * Domain Path: /languages
 * GitHub Plugin URI: https://github.com/dayhkr/wordpress-vistor-stats
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('VISITOR_STATS_VERSION', '1.0.4');
define('VISITOR_STATS_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('VISITOR_STATS_PLUGIN_URL', plugin_dir_url(__FILE__));
define('VISITOR_STATS_PLUGIN_FILE', __FILE__);

// Load all required files immediately
require_once VISITOR_STATS_PLUGIN_DIR . 'includes/class-database.php';
require_once VISITOR_STATS_PLUGIN_DIR . 'includes/class-settings.php';
require_once VISITOR_STATS_PLUGIN_DIR . 'includes/class-visitor-tracker.php';
require_once VISITOR_STATS_PLUGIN_DIR . 'includes/class-admin-dashboard.php';

/**
 * Main plugin class
 */
class VisitorStats {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('init', array($this, 'init'));
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
    }
    
    public function init() {
        // Load text domain for translations
        load_plugin_textdomain('visitor-stats', false, dirname(plugin_basename(__FILE__)) . '/languages');
        
        // Initialize components
        try {
            new VisitorStats\Database();
            new VisitorStats\VisitorTracker();
            
            // Only load admin dashboard for admin users
            if (is_admin()) {
                new VisitorStats\AdminDashboard();
            }
            
            // Sample data generation disabled for real tracking
            
        } catch (Exception $e) {
            add_action('admin_notices', function() use ($e) {
                echo '<div class="notice notice-error"><p>Visitor Stats Error: ' . esc_html($e->getMessage()) . '</p></div>';
            });
        }
    }
    
    public function activate() {
        try {
            // Create database tables
            $database = new VisitorStats\Database();
            $database->create_tables();
            
            // Set default options
            $database->set_setting('tracking_enabled', true);
            $database->set_setting('ip_anonymization', true);
            $database->set_setting('data_retention_days', 365);
            
            // Flush rewrite rules
            flush_rewrite_rules();
            
        } catch (Exception $e) {
            wp_die('Visitor Stats activation failed: ' . $e->getMessage());
        }
    }
    
    public function deactivate() {
        // Flush rewrite rules
        flush_rewrite_rules();
    }
    
    /**
     * Add sample data for testing
     */
    public function maybe_add_sample_data() {
        $database = new VisitorStats\Database();
        
        // Check if we already have data
        $existing_data = $database->get_visit_stats();
        if ($existing_data && $existing_data->total_visits > 0) {
            return; // Already have data, don't add sample
        }
        
        // Add some sample data
        $sample_data = array(
            'ip_hash' => hash('sha256', '192.168.1.1' . wp_salt()),
            'page_url' => home_url('/'),
            'referrer' => 'https://google.com',
            'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
            'session_id' => wp_generate_uuid4(),
            'country' => 'United States',
            'city' => 'New York',
            'browser' => 'Chrome',
            'device_type' => 'Desktop',
            'is_unique_visitor' => 1
        );
        
        // Add multiple sample visits
        for ($i = 0; $i < 10; $i++) {
            $sample_data['timestamp'] = date('Y-m-d H:i:s', time() - ($i * 3600)); // Spread over 10 hours
            $sample_data['session_id'] = wp_generate_uuid4();
            $sample_data['page_url'] = home_url('/page-' . ($i + 1));
            $database->record_visit($sample_data);
        }
    }
}

// Initialize the plugin immediately
VisitorStats::get_instance();