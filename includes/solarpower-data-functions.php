<?php
// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Utility functions and data fetching logic

// Fetch data from Home Assistant
function solarpower_fetch_entity_data($entity_id, $token) {
    $options       = get_option('solarpower_options');
    $api_base_url  = rtrim($options['home_assistant_url'], '/');
    $url           = $api_base_url . '/api/states/' . $entity_id;

    $response = wp_remote_get($url, array(
        'headers' => array(
            'Authorization' => 'Bearer ' . $token,
            'Content-Type'  => 'application/json'
        ),
        'timeout'   => 10,
        'sslverify' => true,
    ));

    if (is_wp_error($response)) {
        error_log('Solar Power Data: API request failed for ' . $entity_id . '. ' . $response->get_error_message());
        return null;
    }

    $status_code = wp_remote_retrieve_response_code($response);
    if ($status_code !== 200) {
        error_log('Solar Power Data: API request returned status code ' . $status_code . ' for ' . $entity_id);
        return null;
    }

    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body);

    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log('Solar Power Data: JSON decode error for ' . $entity_id . ': ' . json_last_error_msg());
        return null;
    }

    return isset($data->state) ? floatval($data->state) : null;
}

// Fetch and store data
function solarpower_fetch_and_store_data() {
    global $solarpower_db;
    $table_name = $solarpower_db->prefix . 'solarpower_data';

    $options  = get_option('solarpower_options');
    $token    = sanitize_text_field($options['solarpower_token']);
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
        $threshold    = time() - ($days_to_keep * DAY_IN_SECONDS);
        $solarpower_db->query($solarpower_db->prepare("DELETE FROM $table_name WHERE timestamp < %d", $threshold));
    }
}

// Fetch historical data (if needed)
function solarpower_fetch_historical_data($start_timestamp, $end_timestamp) {
    // Implement historical data fetching logic here
    // This can use Home Assistant's history API to retrieve data over a range
}
