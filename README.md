# WordPress Visitor Stats Plugin

A comprehensive WordPress plugin that tracks visitor statistics and displays them in a beautiful dashboard within the WordPress admin area.

**Author:** [dayhkr](https://github.com/dayhkr)  
**Version:** 1.0.4  
**License:** GPL v2 or later

## Features

### 📊 Comprehensive Analytics
- **Page Views & Unique Visitors**: Track total visits and unique visitor counts
- **Geographic Data**: Country and city tracking based on IP addresses
- **Browser & Device Analytics**: Track browsers, devices, and operating systems
- **Referrer Tracking**: Monitor traffic sources and referrers
- **Behavior Analytics**: Time on page, scroll depth, and click tracking

### 🔒 Privacy Features
- **IP Anonymization**: Hash IP addresses for privacy protection
- **Do Not Track Support**: Respects DNT headers from browsers
- **Cookie Consent**: Optional cookie consent requirement
- **Data Retention**: Configurable data retention periods
- **IP Exclusion**: Exclude specific IP addresses or ranges from tracking
- **Logged-in User Exclusion**: Automatically excludes WordPress logged-in users

### 📈 Beautiful Dashboard
- **Real-time Statistics**: Live visitor data with auto-refresh
- **Interactive Charts**: Line charts, pie charts, and bar charts using Chart.js
- **Time Range Selection**: Today, last 7/30/90 days, all time, or custom ranges
- **Data Tables**: Top pages, referrers, and recent visitors
- **Export Functionality**: Export data as CSV files
- **Responsive Design**: Mobile-friendly dashboard interface

### ⚙️ Advanced Settings
- **Tracking Controls**: Enable/disable tracking globally
- **Behavior Tracking**: Toggle detailed behavior analytics
- **Geographic Data**: Enable/disable location tracking
- **Auto Cleanup**: Automatic removal of old data
- **Data Management**: Reset all data with one click

## Installation

### From GitHub
1. Download the latest release from [GitHub Releases](https://github.com/dayhkr/wordpress-vistor-stats/releases)
2. Upload the `visitor-stats` folder to your `/wp-content/plugins/` directory
3. Activate the plugin through the 'Plugins' menu in WordPress
4. Navigate to 'Visitor Stats' in your WordPress admin menu
5. Configure your settings in the Settings page

### Manual Installation
1. Clone this repository: `git clone https://github.com/dayhkr/wordpress-vistor-stats.git`
2. Copy the `visitor-stats` folder to your `/wp-content/plugins/` directory
3. Activate the plugin through the 'Plugins' menu in WordPress

## Usage

### Dashboard
Access the main dashboard at **WordPress Admin > Visitor Stats** to view:
- Overview statistics cards
- Visits over time chart
- Browser and device distribution
- Geographic data
- Top pages and referrers
- Recent visitor activity

### Settings
Configure the plugin at **WordPress Admin > Visitor Stats > Settings**:
- Enable/disable tracking
- Set IP anonymization preferences
- Configure cookie consent requirements
- Set data retention periods
- Exclude specific IP addresses
- Toggle behavior tracking features

### Data Export
Use the "Export CSV" button on the dashboard to download visitor data for external analysis.

## Technical Details

### Database Tables
The plugin creates three custom database tables:
- `wp_visitor_stats_visits`: Stores visit records with geographic and browser data
- `wp_visitor_stats_behavior`: Stores behavior analytics (time on page, scroll depth, clicks)
- `wp_visitor_stats_settings`: Stores plugin configuration settings

### Performance
- Asynchronous tracking to avoid blocking page loads
- Efficient database queries with proper indexing
- Background processing for heavy operations
- Automatic data cleanup to maintain performance

### Security
- Nonce verification for all AJAX requests
- Capability checks for admin functions
- SQL injection protection with prepared statements
- XSS protection with proper data sanitization

## Requirements

- WordPress 5.0 or higher
- PHP 7.4 or higher
- MySQL 5.6 or higher

## Privacy Considerations

This plugin is designed with privacy in mind:
- IP addresses can be anonymized/hashed
- No personal data is collected beyond what's necessary for analytics
- Respects Do Not Track headers
- Allows complete data deletion
- Configurable data retention periods

## Support

For support, feature requests, or bug reports, please:
- Open an [Issue](https://github.com/dayhkr/wordpress-vistor-stats/issues) on GitHub
- Check the [Discussions](https://github.com/dayhkr/wordpress-vistor-stats/discussions) section for community help

## Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

## License

This plugin is licensed under the GPL v2 or later.

## Changelog

### Version 1.0.0
- Initial release
- Complete visitor tracking system
- Dashboard with charts and analytics
- Privacy and GDPR compliance features
- Settings page with comprehensive options
- Data export functionality
