<?php
// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Shortcode functionality

// Add shortcode to display solar power data
add_shortcode('display_solarpower_data', 'solarpower_display_data');

function solarpower_display_data($atts) {
    global $solarpower_db;
    $table_name = $solarpower_db->prefix . 'solarpower_data';

    // Process shortcode attributes
    $atts = shortcode_atts(
        array(
            'days'      => 7,       // Number of days to display
            'chart_type' => 'line', // Chart type (line, bar, etc.)
            'entities'  => '',      // Comma-separated list of entity IDs to display
            'granularity' => '',    // Data granularity (hourly, daily, weekly)
        ),
        $atts,
        'display_solarpower_data'
    );

    $days = intval($atts['days']);
    $chart_type = sanitize_text_field($atts['chart_type']);
    $entities_to_show = array_map('trim', explode(',', $atts['entities']));
    $granularity = sanitize_text_field($atts['granularity']);

    if (empty($entities_to_show)) {
        return __('No entities specified.', 'solarpower-data');
    }

    // Calculate time range
    $to_timestamp   = current_time('timestamp');
    $from_timestamp = $to_timestamp - ($days * DAY_IN_SECONDS);

    // Determine granularity
    $granularity = solarpower_determine_granularity($days);

    // Fetch data from the database
    $data = array();
    foreach ($entities_to_show as $entity_id) {
        $entity_data = solarpower_get_aggregated_data($entity_id, $from_timestamp, $to_timestamp, $granularity);
        if ($entity_data) {
            $data[$entity_id] = $entity_data;
        }
    }

    if (empty($data)) {
        return __('No data available for the selected entities.', 'solarpower-data');
    }

    // Enqueue Chart.js and necessary scripts
    wp_enqueue_script('chartjs', 'https://cdn.jsdelivr.net/npm/chart.js@4.3.0/dist/chart.umd.min.js', array(), '4.3.0', true);
    wp_enqueue_script('date-fns', 'https://cdn.jsdelivr.net/npm/date-fns@2.29.2', array(), '2.29.2', true);
    wp_enqueue_script('chartjs-adapter-date-fns', 'https://cdn.jsdelivr.net/npm/chartjs-adapter-date-fns@3.0.0/dist/chartjs-adapter-date-fns.bundle.min.js', array('chartjs', 'date-fns'), '3.0.0', true);
    wp_enqueue_script('solarpower-data-scripts', SOLARPOWER_PLUGIN_URL . 'js/solarpower-data-scripts.js', array('jquery', 'chartjs', 'chartjs-adapter-date-fns'), null, true);

    // Prepare data for JavaScript
    $chart_data = array(
        'entities'   => array(),
        'chartType'  => $chart_type,
        'granularity' => $granularity,
    );

    foreach ($data as $entity_id => $entity_data) {
        $formatted_data = array();
        foreach ($entity_data as $data_point) {
            $formatted_data[] = array(
                'x' => date('Y-m-d H:i:s', $data_point->time_group),
                'y' => $data_point->value
            );
        }

        $options = get_option('solarpower_options');
        $entity_settings = isset($options['entities'][$entity_id]) ? $options['entities'][$entity_id] : array();

        $chart_data['entities'][] = array(
            'entity_id' => $entity_id,
            'label'     => isset($entity_settings['label']) ? $entity_settings['label'] : $entity_id,
            'unit'      => isset($entity_settings['unit']) ? $entity_settings['unit'] : '',
            'data'      => $formatted_data,
        );
    }

    // Localize the script with data
    wp_localize_script('solarpower-data-scripts', 'SolarPowerData', $chart_data);

    // Output the chart container
    $output = '<div class="solarpower-charts">';
    $output .= '<canvas id="solarpowerChart" width="400" height="200"></canvas>';
    $output .= '</div>';

    return $output;
}

// Determine granularity based on days
function solarpower_determine_granularity($days) {
    if ($days <= 7) {
        return 'hourly';
    } elseif ($days <= 30) {
        return 'daily';
    } else {
        return 'weekly';
    }
}

// Get aggregated data
function solarpower_get_aggregated_data($entity_id, $from_timestamp, $to_timestamp, $granularity) {
    global $solarpower_db;
    $table_name = $solarpower_db->prefix . 'solarpower_data';

    // Define the interval in seconds based on granularity
    switch ($granularity) {
        case 'hourly':
            $interval = HOUR_IN_SECONDS;
            break;
        case 'daily':
            $interval = DAY_IN_SECONDS;
            break;
        case 'weekly':
            $interval = WEEK_IN_SECONDS;
            break;
        default:
            $interval = HOUR_IN_SECONDS;
    }

    // Adjust timestamps to align with intervals
    $from_timestamp = floor($from_timestamp / $interval) * $interval;
    $to_timestamp = ceil($to_timestamp / $interval) * $interval;

    // SQL query to aggregate data
    $query = $solarpower_db->prepare(
        "SELECT
            FLOOR(timestamp / %d) * %d AS time_group,
            AVG(value) AS value
        FROM $table_name
        WHERE entity_id = %s AND timestamp BETWEEN %d AND %d
        GROUP BY time_group
        ORDER BY time_group ASC",
        $interval,
        $interval,
        $entity_id,
        $from_timestamp,
        $to_timestamp
    );

    $results = $solarpower_db->get_results($query);

    return $results;
}
