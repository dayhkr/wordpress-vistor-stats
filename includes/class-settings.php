<?php

namespace VisitorStats;

if (!defined('ABSPATH')) {
    exit;
}

class Settings {
    
    private $database;
    
    public function __construct() {
        $this->database = new Database();
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('wp_ajax_visitor_stats_save_settings', array($this, 'ajax_save_settings'));
        add_action('wp_ajax_visitor_stats_reset_data', array($this, 'ajax_reset_data'));
    }
    
    /**
     * Add settings page to admin menu
     */
    public function add_admin_menu() {
        // Settings menu is now handled by AdminDashboard class
        // This method is kept for compatibility but doesn't add menu items
    }
    
    /**
     * Register settings
     */
    public function register_settings() {
        // Settings are handled via custom AJAX endpoints
    }
    
    /**
     * Set default options
     */
    public function set_default_options() {
        $defaults = array(
            'tracking_enabled' => true,
            'ip_anonymization' => true,
            'cookie_consent_required' => false,
            'data_retention_days' => 365,
            'excluded_ips' => '',
            'excluded_user_roles' => array('administrator', 'editor'),
            'respect_dnt_header' => true,
            'track_behavior' => true,
            'geoip_enabled' => true,
            'auto_cleanup' => true
        );
        
        foreach ($defaults as $key => $value) {
            $this->database->set_setting($key, $value);
        }
    }
    
    /**
     * Settings page HTML
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
                        <th scope="row"><?php _e('Cookie Consent', 'visitor-stats'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="cookie_consent_required" value="1" <?php checked($settings['cookie_consent_required'], true); ?>>
                                <?php _e('Require cookie consent before tracking', 'visitor-stats'); ?>
                            </label>
                            <p class="description"><?php _e('Visitors must consent before tracking begins.', 'visitor-stats'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php _e('Respect Do Not Track', 'visitor-stats'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="respect_dnt_header" value="1" <?php checked($settings['respect_dnt_header'], true); ?>>
                                <?php _e('Respect Do Not Track header', 'visitor-stats'); ?>
                            </label>
                            <p class="description"><?php _e('Do not track visitors who send DNT header.', 'visitor-stats'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php _e('Data Retention', 'visitor-stats'); ?></th>
                        <td>
                            <input type="number" name="data_retention_days" value="<?php echo esc_attr($settings['data_retention_days']); ?>" min="1" max="3650">
                            <p class="description"><?php _e('Number of days to keep visitor data (1-3650).', 'visitor-stats'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php _e('Excluded IP Addresses', 'visitor-stats'); ?></th>
                        <td>
                            <textarea name="excluded_ips" rows="3" cols="50" placeholder="192.168.1.1&#10;10.0.0.0/8"><?php echo esc_textarea($settings['excluded_ips']); ?></textarea>
                            <p class="description"><?php _e('One IP address or CIDR block per line. These will be excluded from tracking.', 'visitor-stats'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php _e('Track Behavior', 'visitor-stats'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="track_behavior" value="1" <?php checked($settings['track_behavior'], true); ?>>
                                <?php _e('Track time on page, scroll depth, and clicks', 'visitor-stats'); ?>
                            </label>
                            <p class="description"><?php _e('Collect detailed behavior analytics.', 'visitor-stats'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php _e('Geographic Data', 'visitor-stats'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="geoip_enabled" value="1" <?php checked($settings['geoip_enabled'], true); ?>>
                                <?php _e('Enable geographic data collection', 'visitor-stats'); ?>
                            </label>
                            <p class="description"><?php _e('Collect country and city data based on IP address.', 'visitor-stats'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php _e('Auto Cleanup', 'visitor-stats'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="auto_cleanup" value="1" <?php checked($settings['auto_cleanup'], true); ?>>
                                <?php _e('Automatically clean old data', 'visitor-stats'); ?>
                            </label>
                            <p class="description"><?php _e('Automatically remove data older than retention period.', 'visitor-stats'); ?></p>
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
            
            $('#visitor-stats-reset-data').on('click', function(e) {
                e.preventDefault();
                
                if (!confirm('<?php _e('Are you sure you want to delete all visitor data? This action cannot be undone.', 'visitor-stats'); ?>')) {
                    return;
                }
                
                var messageDiv = $('#visitor-stats-message');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'visitor_stats_reset_data',
                        nonce: '<?php echo wp_create_nonce('visitor_stats_reset_data'); ?>'
                    },
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
                            .html('<p>Error resetting data. Please try again.</p>').show();
                    }
                });
            });
        });
        </script>
        <?php
    }
    
    /**
     * Get all settings
     */
    public function get_all_settings() {
        return array(
            'tracking_enabled' => $this->database->get_setting('tracking_enabled', true),
            'ip_anonymization' => $this->database->get_setting('ip_anonymization', true),
            'cookie_consent_required' => $this->database->get_setting('cookie_consent_required', false),
            'data_retention_days' => $this->database->get_setting('data_retention_days', 365),
            'excluded_ips' => $this->database->get_setting('excluded_ips', ''),
            'excluded_user_roles' => $this->database->get_setting('excluded_user_roles', array('administrator', 'editor')),
            'respect_dnt_header' => $this->database->get_setting('respect_dnt_header', true),
            'track_behavior' => $this->database->get_setting('track_behavior', true),
            'geoip_enabled' => $this->database->get_setting('geoip_enabled', true),
            'auto_cleanup' => $this->database->get_setting('auto_cleanup', true)
        );
    }
    
    /**
     * Get a specific setting
     */
    public function get_setting($key, $default = null) {
        return $this->database->get_setting($key, $default);
    }
    
    /**
     * Set a specific setting
     */
    public function set_setting($key, $value) {
        return $this->database->set_setting($key, $value);
    }
    
    /**
     * AJAX handler for saving settings
     */
    public function ajax_save_settings() {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions.', 'visitor-stats'));
        }
        
        if (!wp_verify_nonce($_POST['visitor_stats_nonce'], 'visitor_stats_settings')) {
            wp_die(__('Security check failed.', 'visitor-stats'));
        }
        
        $settings = array(
            'tracking_enabled' => isset($_POST['tracking_enabled']),
            'ip_anonymization' => isset($_POST['ip_anonymization']),
            'cookie_consent_required' => isset($_POST['cookie_consent_required']),
            'data_retention_days' => intval($_POST['data_retention_days']),
            'excluded_ips' => sanitize_textarea_field($_POST['excluded_ips']),
            'respect_dnt_header' => isset($_POST['respect_dnt_header']),
            'track_behavior' => isset($_POST['track_behavior']),
            'geoip_enabled' => isset($_POST['geoip_enabled']),
            'auto_cleanup' => isset($_POST['auto_cleanup'])
        );
        
        foreach ($settings as $key => $value) {
            $this->database->set_setting($key, $value);
        }
        
        wp_send_json_success(array('message' => __('Settings saved successfully.', 'visitor-stats')));
    }
    
    /**
     * AJAX handler for resetting data
     */
    public function ajax_reset_data() {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions.', 'visitor-stats'));
        }
        
        if (!wp_verify_nonce($_POST['nonce'], 'visitor_stats_reset_data')) {
            wp_die(__('Security check failed.', 'visitor-stats'));
        }
        
        $table_names = $this->database->get_table_names();
        
        global $wpdb;
        $wpdb->query("TRUNCATE TABLE {$table_names['visits']}");
        $wpdb->query("TRUNCATE TABLE {$table_names['behavior']}");
        
        wp_send_json_success(array('message' => __('All visitor data has been deleted.', 'visitor-stats')));
    }
    
    /**
     * Check if IP should be excluded
     */
    public function is_ip_excluded($ip) {
        $excluded_ips = $this->get_setting('excluded_ips', '');
        if (empty($excluded_ips)) {
            return false;
        }
        
        $excluded_list = array_map('trim', explode("\n", $excluded_ips));
        
        foreach ($excluded_list as $excluded_ip) {
            if (empty($excluded_ip)) continue;
            
            // Check for CIDR notation
            if (strpos($excluded_ip, '/') !== false) {
                if ($this->ip_in_cidr($ip, $excluded_ip)) {
                    return true;
                }
            } else {
                // Direct IP match
                if ($ip === $excluded_ip) {
                    return true;
                }
            }
        }
        
        return false;
    }
    
    /**
     * Check if IP is in CIDR range
     */
    private function ip_in_cidr($ip, $cidr) {
        list($subnet, $mask) = explode('/', $cidr);
        $ip_long = ip2long($ip);
        $subnet_long = ip2long($subnet);
        $mask_long = -1 << (32 - $mask);
        
        return ($ip_long & $mask_long) === ($subnet_long & $mask_long);
    }
}
