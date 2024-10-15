<?php
/*
Plugin Name: Solar Power Data
Description: Fetches data from Home Assistant and stores it in the WordPress or external database. Displays solar power data with customizable graphs using Chart.js.
Version: 3.0
Author: Elias Haisch
Text Domain: solarpower-data
*/

// Prevent direct access to the file
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('SOLARPOWER_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('SOLARPOWER_PLUGIN_URL', plugin_dir_url(__FILE__));

// Include necessary files
require_once SOLARPOWER_PLUGIN_DIR . 'includes/solarpower-data-functions.php';
require_once SOLARPOWER_PLUGIN_DIR . 'includes/solarpower-data-admin.php';
require_once SOLARPOWER_PLUGIN_DIR . 'includes/solarpower-data-shortcode.php';

// Enqueue frontend styles
function solarpower_enqueue_frontend_styles() {
    wp_enqueue_style(
        'solarpower-data-styles',
        SOLARPOWER_PLUGIN_URL . 'css/solarpower-data-styles.css',
        array(),
        '1.0',
        'all'
    );
}
add_action('wp_enqueue_scripts', 'solarpower_enqueue_frontend_styles');

// Load plugin text domain for translations
function solarpower_load_textdomain() {
    load_plugin_textdomain('solarpower-data', false, dirname(plugin_basename(__FILE__)) . '/languages');
}
add_action('plugins_loaded', 'solarpower_load_textdomain');

// Initialize database connection
global $solarpower_db;
$solarpower_db = null;

// Initialize database
function solarpower_init_database() {
    global $wpdb, $solarpower_db;

    $options = get_option('solarpower_options');
    if (isset($options['use_external_db']) && $options['use_external_db']) {
        // Establish external database connection
        $db_host     = $options['external_db_host'];
        $db_name     = $options['external_db_name'];
        $db_user     = $options['external_db_user'];
        $db_password = $options['external_db_password'];
        $db_charset  = $wpdb->charset;

        $solarpower_db = new wpdb($db_user, $db_password, $db_name, $db_host);
        if ($solarpower_db->has_cap('collation')) {
            $solarpower_db->set_charset($solarpower_db->dbh, $db_charset);
        }
    } else {
        // Use WordPress database
        $solarpower_db = $wpdb;
    }

    // Create database table
    solarpower_create_table();
}
add_action('init', 'solarpower_init_database');

// Create database table
function solarpower_create_table() {
    global $solarpower_db;
    $table_name      = $solarpower_db->prefix . 'solarpower_data';
    $charset_collate = $solarpower_db->get_charset_collate();

    $sql = "CREATE TABLE IF NOT EXISTS $table_name (
        id INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
        timestamp INT(11) UNSIGNED NOT NULL,
        entity_id VARCHAR(255) NOT NULL,
        value FLOAT NOT NULL,
        PRIMARY KEY (id),
        INDEX (timestamp),
        INDEX (entity_id)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}

// Schedule data fetch event
function solarpower_schedule_event() {
    $options  = get_option('solarpower_options');
    $interval = isset($options['data_fetch_interval']) ? $options['data_fetch_interval'] : 'hourly';

    if (!wp_next_scheduled('solarpower_fetch_data_event')) {
        wp_schedule_event(time(), $interval, 'solarpower_fetch_data_event');
    } else {
        // Reschedule event if interval has changed
        $timestamp = wp_next_scheduled('solarpower_fetch_data_event');
        wp_unschedule_event($timestamp, 'solarpower_fetch_data_event');
        wp_schedule_event(time(), $interval, 'solarpower_fetch_data_event');
    }
}
add_action('init', 'solarpower_schedule_event');

// Add intervals to Cron schedules
function solarpower_add_cron_intervals($schedules) {
    $options = get_option('solarpower_options');
    if (isset($options['custom_intervals']) && is_array($options['custom_intervals'])) {
        foreach ($options['custom_intervals'] as $interval) {
            $schedules[$interval['name']] = array(
                'interval' => intval($interval['seconds']),
                'display'  => esc_html($interval['display'])
            );
        }
    }
    // Example of a one-minute interval
    $schedules['every_minute'] = array(
        'interval' => 60,
        'display'  => __('Every Minute', 'solarpower-data')
    );
    return $schedules;
}
add_filter('cron_schedules', 'solarpower_add_cron_intervals');

// Hook to the scheduled event
add_action('solarpower_fetch_data_event', 'solarpower_fetch_and_store_data');

// Fetch and store data
function solarpower_fetch_and_store_data() {
    global $solarpower_db;
    $table_name = $solarpower_db->prefix . 'solarpower_data';

    $options = get_option('solarpower_options');
    $token   = sanitize_text_field($options['solarpower_token']);
    $entities = isset($options['entities']) ? $options['entities'] : array();

    $timestamp = time();

    foreach ($entities as $entity_id => $entity) {
        if (!$entity['enabled']) {
            continue;
        }

        $value = solarpower_fetch_entity_data($entity_id, $token);
        if ($value !== null) {
            // Check if data already exists to prevent duplicates
            $existing = $solarpower_db->get_var($solarpower_db->prepare(
                "SELECT COUNT(*) FROM $table_name WHERE timestamp = %d AND entity_id = %s",
                $timestamp,
                $entity_id
            ));

            if ($existing == 0) {
                // Insert data into the database
                $solarpower_db->insert(
                    $table_name,
                    array(
                        'timestamp' => $timestamp,
                        'entity_id' => $entity_id,
                        'value'     => $value
                    ),
                    array(
                        '%d',
                        '%s',
                        '%f'
                    )
                );
            }
        }
    }

    // Data cleanup if enabled
    if (isset($options['enable_data_cleanup']) && $options['enable_data_cleanup']) {
        $days_to_keep = isset($options['data_retention_days']) ? intval($options['data_retention_days']) : 30;
        $threshold = time() - ($days_to_keep * DAY_IN_SECONDS);
        $solarpower_db->query($solarpower_db->prepare("DELETE FROM $table_name WHERE timestamp < %d", $threshold));
    }
}

// Fetch entity data from Home Assistant
function solarpower_fetch_entity_data($entity_id, $token) {
    $options = get_option('solarpower_options');
    $api_base_url = rtrim($options['home_assistant_url'], '/');
    $url = $api_base_url . '/api/states/' . $entity_id;

    $response = wp_remote_get($url, array(
        'headers' => array(
            'Authorization' => 'Bearer ' . $token,
            'Content-Type'  => 'application/json'
        ),
        'timeout'   => 10,
        'sslverify' => true,
    ));

    if (is_wp_error($response)) {
        error_log('Solar Power Data: API request failed. ' . $response->get_error_message());
        return null;
    }

    $status_code = wp_remote_retrieve_response_code($response);
    if ($status_code !== 200) {
        error_log('Solar Power Data: API request returned status code ' . $status_code);
        return null;
    }

    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body);

    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log('Solar Power Data: JSON decode error: ' . json_last_error_msg());
        return null;
    }

    return isset($data->state) ? floatval($data->state) : null;
}

// Deactivate scheduled event upon plugin deactivation
register_deactivation_hook(__FILE__, 'solarpower_deactivate');
function solarpower_deactivate() {
    $timestamp = wp_next_scheduled('solarpower_fetch_data_event');
    wp_unschedule_event($timestamp, 'solarpower_fetch_data_event');
}
