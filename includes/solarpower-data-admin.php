<?php
// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Admin settings page

// Add settings page
add_action('admin_menu', 'solarpower_add_admin_menu');
add_action('admin_init', 'solarpower_settings_init');

function solarpower_add_admin_menu() {
    add_options_page(
        __('Solar Power Data', 'solarpower-data'),
        __('Solar Power Data', 'solarpower-data'),
        'manage_options',
        'solarpower_data',
        'solarpower_options_page'
    );
}

function solarpower_settings_init() {
    register_setting('solarpower_settings', 'solarpower_options', 'solarpower_options_validate');

    add_settings_section(
        'solarpower_settings_section',
        __('Settings', 'solarpower-data'),
        'solarpower_settings_section_callback',
        'solarpower_settings'
    );

    add_settings_field(
        'home_assistant_url',
        __('Home Assistant URL', 'solarpower-data'),
        'solarpower_home_assistant_url_render',
        'solarpower_settings',
        'solarpower_settings_section'
    );

    add_settings_field(
        'solarpower_token',
        __('API Token', 'solarpower-data'),
        'solarpower_token_render',
        'solarpower_settings',
        'solarpower_settings_section'
    );

    // Entities management field
    add_settings_field(
        'entities',
        __('Entities Management', 'solarpower-data'),
        'solarpower_entities_management_render',
        'solarpower_settings',
        'solarpower_settings_section'
    );

    // Data fetch interval
    add_settings_field(
        'data_fetch_interval',
        __('Data Fetch Interval', 'solarpower-data'),
        'solarpower_data_fetch_interval_render',
        'solarpower_settings',
        'solarpower_settings_section'
    );

    // Data cleanup settings
    add_settings_field(
        'enable_data_cleanup',
        __('Enable Data Cleanup', 'solarpower-data'),
        'solarpower_enable_data_cleanup_render',
        'solarpower_settings',
        'solarpower_settings_section'
    );

    add_settings_field(
        'data_retention_days',
        __('Data Retention Days', 'solarpower-data'),
        'solarpower_data_retention_days_render',
        'solarpower_settings',
        'solarpower_settings_section'
    );

    // External database settings
    add_settings_field(
        'use_external_db',
        __('Use External Database', 'solarpower-data'),
        'solarpower_use_external_db_render',
        'solarpower_settings',
        'solarpower_settings_section'
    );

    add_settings_field(
        'external_db_host',
        __('External DB Host', 'solarpower-data'),
        'solarpower_external_db_host_render',
        'solarpower_settings',
        'solarpower_settings_section'
    );

    add_settings_field(
        'external_db_name',
        __('External DB Name', 'solarpower-data'),
        'solarpower_external_db_name_render',
        'solarpower_settings',
        'solarpower_settings_section'
    );

    add_settings_field(
        'external_db_user',
        __('External DB User', 'solarpower-data'),
        'solarpower_external_db_user_render',
        'solarpower_settings',
        'solarpower_settings_section'
    );

    add_settings_field(
        'external_db_password',
        __('External DB Password', 'solarpower-data'),
        'solarpower_external_db_password_render',
        'solarpower_settings',
        'solarpower_settings_section'
    );
}

function solarpower_options_validate($input) {
    $options = array();

    $options['home_assistant_url'] = esc_url_raw($input['home_assistant_url']);
    $options['solarpower_token']   = sanitize_text_field($input['solarpower_token']);

    // Validate entities
    $entities = array();
    if (isset($input['entities']) && is_array($input['entities'])) {
        foreach ($input['entities'] as $entity_id => $entity) {
            $entities[$entity_id] = array(
                'enabled'     => isset($entity['enabled']) ? true : false,
                'label'       => sanitize_text_field($entity['label']),
                'unit'        => sanitize_text_field($entity['unit']),
                'aggregation' => sanitize_text_field($entity['aggregation']),
            );
        }
    }
    $options['entities'] = $entities;

    $options['data_fetch_interval'] = sanitize_text_field($input['data_fetch_interval']);

    $options['enable_data_cleanup'] = isset($input['enable_data_cleanup']) ? true : false;
    $options['data_retention_days'] = intval($input['data_retention_days']);

    $options['use_external_db']       = isset($input['use_external_db']) ? true : false;
    $options['external_db_host']      = sanitize_text_field($input['external_db_host']);
    $options['external_db_name']      = sanitize_text_field($input['external_db_name']);
    $options['external_db_user']      = sanitize_text_field($input['external_db_user']);
    $options['external_db_password']  = sanitize_text_field($input['external_db_password']);

    return $options;
}

function solarpower_settings_section_callback() {
    echo '<p>' . __('Configure your Home Assistant API settings and manage entities below:', 'solarpower-data') . '</p>';
}

function solarpower_home_assistant_url_render() {
    $options = get_option('solarpower_options');
    ?>
    <input type='text' name='solarpower_options[home_assistant_url]' value='<?php echo esc_attr($options['home_assistant_url']); ?>' style="width: 100%;">
    <p class="description"><?php _e('Enter the base URL of your Home Assistant instance (e.g., https://your-home-assistant:8123)', 'solarpower-data'); ?></p>
    <?php
}

function solarpower_token_render() {
    $options = get_option('solarpower_options');
    ?>
    <input type='text' name='solarpower_options[solarpower_token]' value='<?php echo esc_attr($options['solarpower_token']); ?>' style="width: 100%;">
    <p class="description"><?php _e('Enter your Home Assistant Long-Lived Access Token.', 'solarpower-data'); ?></p>
    <?php
}

function solarpower_entities_management_render() {
    $options = get_option('solarpower_options');
    $entities = isset($options['entities']) ? $options['entities'] : array();

    // Render predefined entities and allow adding custom ones
    ?>
    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th><?php _e('Enable', 'solarpower-data'); ?></th>
                <th><?php _e('Entity ID', 'solarpower-data'); ?></th>
                <th><?php _e('Label', 'solarpower-data'); ?></th>
                <th><?php _e('Unit', 'solarpower-data'); ?></th>
                <th><?php _e('Aggregation', 'solarpower-data'); ?></th>
                <th><?php _e('Actions', 'solarpower-data'); ?></th>
            </tr>
        </thead>
        <tbody id="solarpower-entities-table">
            <?php
            if (!empty($entities)) {
                foreach ($entities as $entity_id => $entity) {
                    ?>
                    <tr>
                        <td>
                            <input type="checkbox" name="solarpower_options[entities][<?php echo esc_attr($entity_id); ?>][enabled]" <?php checked($entity['enabled'], true); ?> value="1">
                        </td>
                        <td>
                            <input type="text" name="solarpower_options[entities][<?php echo esc_attr($entity_id); ?>][entity_id]" value="<?php echo esc_attr($entity_id); ?>" readonly>
                        </td>
                        <td>
                            <input type="text" name="solarpower_options[entities][<?php echo esc_attr($entity_id); ?>][label]" value="<?php echo esc_attr($entity['label']); ?>">
                        </td>
                        <td>
                            <input type="text" name="solarpower_options[entities][<?php echo esc_attr($entity_id); ?>][unit]" value="<?php echo esc_attr($entity['unit']); ?>">
                        </td>
                        <td>
                            <select name="solarpower_options[entities][<?php echo esc_attr($entity_id); ?>][aggregation]">
                                <option value="average" <?php selected($entity['aggregation'], 'average'); ?>><?php _e('Average', 'solarpower-data'); ?></option>
                                <option value="sum" <?php selected($entity['aggregation'], 'sum'); ?>><?php _e('Sum', 'solarpower-data'); ?></option>
                            </select>
                        </td>
                        <td>
                            <button class="button solarpower-remove-entity"><?php _e('Remove', 'solarpower-data'); ?></button>
                        </td>
                    </tr>
                    <?php
                }
            }
            ?>
        </tbody>
    </table>
    <button id="solarpower-add-entity" class="button"><?php _e('Add Entity', 'solarpower-data'); ?></button>
    <script>
        // JavaScript to handle adding/removing entities
        (function($) {
            $('#solarpower-add-entity').on('click', function(e) {
                e.preventDefault();
                var newRow = `<tr>
                    <td><input type="checkbox" name="" value="1"></td>
                    <td><input type="text" name="" value=""></td>
                    <td><input type="text" name="" value=""></td>
                    <td><input type="text" name="" value=""></td>
                    <td>
                        <select name="">
                            <option value="average"><?php _e('Average', 'solarpower-data'); ?></option>
                            <option value="sum"><?php _e('Sum', 'solarpower-data'); ?></option>
                        </select>
                    </td>
                    <td><button class="button solarpower-remove-entity"><?php _e('Remove', 'solarpower-data'); ?></button></td>
                </tr>`;
                $('#solarpower-entities-table').append(newRow);
            });

            $('#solarpower-entities-table').on('click', '.solarpower-remove-entity', function(e) {
                e.preventDefault();
                $(this).closest('tr').remove();
            });
        })(jQuery);
    </script>
    <?php
}

function solarpower_data_fetch_interval_render() {
    $options   = get_option('solarpower_options');
    $interval  = isset($options['data_fetch_interval']) ? $options['data_fetch_interval'] : 'hourly';
    $schedules = wp_get_schedules();
    ?>
    <select name='solarpower_options[data_fetch_interval]'>
        <?php foreach ($schedules as $key => $schedule): ?>
            <option value="<?php echo esc_attr($key); ?>" <?php selected($interval, $key); ?>>
                <?php echo esc_html($schedule['display']); ?>
            </option>
        <?php endforeach; ?>
    </select>
    <p class="description"><?php _e('Select how often data should be fetched.', 'solarpower-data'); ?></p>
    <?php
}

function solarpower_enable_data_cleanup_render() {
    $options = get_option('solarpower_options');
    $enabled = isset($options['enable_data_cleanup']) ? $options['enable_data_cleanup'] : false;
    ?>
    <input type='checkbox' name='solarpower_options[enable_data_cleanup]' <?php checked($enabled, true); ?> value="1">
    <label for='enable_data_cleanup'><?php _e('Enable automatic data cleanup', 'solarpower-data'); ?></label>
    <?php
}

function solarpower_data_retention_days_render() {
    $options = get_option('solarpower_options');
    $days    = isset($options['data_retention_days']) ? $options['data_retention_days'] : 30;
    ?>
    <input type='number' name='solarpower_options[data_retention_days]' value='<?php echo esc_attr($days); ?>' min="1" max="365">
    <p class="description"><?php _e('Number of days to retain data.', 'solarpower-data'); ?></p>
    <?php
}

function solarpower_use_external_db_render() {
    $options = get_option('solarpower_options');
    $enabled = isset($options['use_external_db']) ? $options['use_external_db'] : false;
    ?>
    <input type='checkbox' name='solarpower_options[use_external_db]' <?php checked($enabled, true); ?> value="1" id="use_external_db">
    <label for='use_external_db'><?php _e('Use external database for data storage', 'solarpower-data'); ?></label>
    <?php
}

function solarpower_external_db_host_render() {
    $options = get_option('solarpower_options');
    ?>
    <input type='text' name='solarpower_options[external_db_host]' value='<?php echo esc_attr($options['external_db_host']); ?>' style="width: 100%;">
    <p class="description"><?php _e('Example: localhost or 127.0.0.1', 'solarpower-data'); ?></p>
    <?php
}

function solarpower_external_db_name_render() {
    $options = get_option('solarpower_options');
    ?>
    <input type='text' name='solarpower_options[external_db_name]' value='<?php echo esc_attr($options['external_db_name']); ?>' style="width: 100%;">
    <p class="description"><?php _e('Name of the external database.', 'solarpower-data'); ?></p>
    <?php
}

function solarpower_external_db_user_render() {
    $options = get_option('solarpower_options');
    ?>
    <input type='text' name='solarpower_options[external_db_user]' value='<?php echo esc_attr($options['external_db_user']); ?>' style="width: 100%;">
    <p class="description"><?php _e('Username for the external database.', 'solarpower-data'); ?></p>
    <?php
}

function solarpower_external_db_password_render() {
    $options = get_option('solarpower_options');
    ?>
    <input type='password' name='solarpower_options[external_db_password]' value='<?php echo esc_attr($options['external_db_password']); ?>' style="width: 100%;">
    <p class="description"><?php _e('Password for the external database.', 'solarpower-data'); ?></p>
    <?php
}

function solarpower_options_page() {
    ?>
    <div class="wrap">
        <h2><?php _e('Solar Power Data Settings', 'solarpower-data'); ?></h2>
        <form action='options.php' method='post'>
            <?php
            settings_fields('solarpower_settings');
            do_settings_sections('solarpower_settings');
            submit_button();
            ?>
        </form>
    </div>
    <?php
}

// Enqueue admin scripts
add_action('admin_enqueue_scripts', 'solarpower_admin_enqueue_scripts');
function solarpower_admin_enqueue_scripts($hook) {
    if ($hook !== 'settings_page_solarpower_data') {
        return;
    }

    // Enqueue admin JavaScript
    wp_enqueue_script(
        'solarpower-data-admin',
        SOLARPOWER_PLUGIN_URL . 'js/solarpower-data-admin.js',
        array('jquery'),
        '1.0',
        true
    );

    // Enqueue admin CSS
    wp_enqueue_style(
        'solarpower-data-admin-styles',
        SOLARPOWER_PLUGIN_URL . 'css/solarpower-data-styles.css',
        array(),
        '1.0',
        'all'
    );
}
