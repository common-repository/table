<?php

/**
 * Plugin Name: TABLE
 * Plugin URI:  https://table.co
 * Description: Embed TABLE and automatically authenicate WordPress user accounts
 * Version:     0.0.3
 * Author:      TABLE
 * License:     GPLv2
 * Text Domain: table
 * Domain Path: /languages
 *
 * @link https://table.co
 *
 * @package TABLE
 * @version 0.0.3
 */

class TABLE_For_Wordpress_Plugin
{
    public function __construct()
    {
        // Hook into the admin menu
        add_action('admin_menu', array($this, 'create_plugin_settings_page'));
        // Add settings
        add_action('admin_init', array($this, 'setup_sections'));
        add_action('admin_init', array($this, 'setup_fields'));

        //Register fields
        register_setting('table_wp', 'experience_id');
        register_setting('table_wp', 'customer_id');
        register_setting('table_wp', 'table_secret');
        register_setting('table_wp', 'open_timeout');

        //Generate Embed
        add_action('wp_footer', 'generateEmbed');
    }

    public function create_plugin_settings_page()
    {
        // Add the menu item and page
        $page_title = 'TABLE Settings';
        $menu_title = 'TABLE Settings';
        $capability = 'manage_options';
        $slug = 'table_wp';
        $callback = array($this, 'plugin_settings_page_content');
        add_submenu_page('options-general.php', $page_title, $menu_title, $capability, $slug, $callback);
    }

    public function setup_sections()
    {
        add_settings_section('table_options_section', 'TABLE Embed Options', false, 'table_wp');
    }

    public function setup_fields()
    {
        if (!in_array('sha256', hash_hmac_algos(), true)) {
            $sha256Available = 'disabled';
            $sha256Help = '<strong>Not supported by your WordPress host.</strong> Please ask them to enable support for the SHA-256 encryption algorithm and the hash_hmac function.';
        } else {
            $sha256Available = '';
            $sha256Help = 'Required to identify logged in users. Leave blank to disable logged in functionality';
        }

        $fields = array(
            array(
                'uid' => 'experience_id',
                'label' => 'Experience Short Code*',
                'section' => 'table_options_section',
                'type' => 'text',
                'placeholder' => '',
                'help_text' => 'You can get this in the Experiences section of the Admin area',
                'default' => '',
                'pattern' => '[A-Za-z0-9]+',
                'required' => 'required'
            ),
            array(
                'uid' => 'customer_id',
                'label' => 'Workspace URL *',
                'section' => 'table_options_section',
                'type' => 'url',
                'placeholder' => 'https://yourcompany.table.co',
                'help_text' => 'This is the complete URL of your TABLE installation, for example https://company.table.co',
                'default' => '',
                'pattern' => 'https://.*',
                'required' => 'required'
            ),
            array(
                'uid' => 'table_secret',
                'label' => 'Secret',
                'section' => 'table_options_section',
                'type' => 'text',
                'placeholder' => '',
                'help_text' => esc_html($sha256Help),
                'default' => '',
                'pattern' => '[A-Za-z0-9]+',
                'required' => esc_attr($sha256Available)
            ),
            array(
                'uid' => 'open_timeout',
                'label' => 'Open Timeout',
                'section' => 'table_options_section',
                'type' => 'number',
                'placeholder' => '5',
                'help_text' => 'Optional delay (in seconds) before preview of first Experience message',
                'pattern' => '[0-9]+',
                'default' => '0'
            )
        );
        foreach ($fields as $field) {
            add_settings_field($field['uid'], $field['label'], array($this, 'fieldCallback'), 'table_wp', $field['section'], $field);
            //Each field must be referenced specifically in the class constructor
        }
    }

    public function fieldCallback($arguments)
    {
        // Switch for field types
        switch ($arguments['type']) {
        case 'text': // If it is a text field
            $value = get_option($arguments['uid']); // Get the current value
            if (!$value) { // If no value exists
                $value = $arguments['default']; // Set to default
            }
            printf('<input name="%1$s" id="%1$s" type="%2$s" placeholder="%3$s" value="%4$s" %5$s pattern="%6$s" />', esc_attr($arguments['uid']), esc_attr($arguments['type']),  esc_attr($arguments['placeholder']),  esc_attr($value), esc_attr($arguments['required']), esc_attr($arguments['pattern']));
            break;
        case 'url': // If it is a URL field
            $value = get_option($arguments['uid']); // Get the current value
            if (!$value) { // If no value exists
                $value = $arguments['default']; // Set to default
            }
            printf('<input name="%1$s" id="%1$s" type="%2$s" placeholder="%3$s" value="%4$s" %5$s pattern="%6$s" />', esc_attr($arguments['uid']), esc_attr($arguments['type']),  esc_attr($arguments['placeholder']),  esc_attr($value), esc_attr($arguments['required']), esc_attr($arguments['pattern']));
            break;
        case 'number': // If it is a numeric field
            $value = get_option($arguments['uid']); // Get the current value
            if (!$value) { // If no value exists
                $value = $arguments['default']; // Set to default
            }
            printf('<input name="%1$s" id="%1$s" type="%2$s" placeholder="%3$s" value="%4$s" pattern="%5$s" />', esc_attr($arguments['uid']), esc_attr($arguments['type']),  esc_attr($arguments['placeholder']),  esc_attr($value), esc_attr($arguments['pattern']));
            break;
        }

        // If there is help text
        if ($arguments['help_text']) {
            printf('<p class="description">%s</p>', esc_html($arguments['help_text'])); // Show it
        }
    }

    public function plugin_settings_page_content()
    {
        echo '<div class="wrap">';
        echo '<form method="post" action="options.php">';
        settings_fields('table_wp');
        do_settings_sections('table_wp');
        submit_button();
        echo '</form>';
        echo '</div>';
    }
}

new TABLE_For_Wordpress_Plugin();

add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'settingsLink');
function settingsLink($links)
{
    $links[] = '<a href="' .
        admin_url('options-general.php?page=table_wp') .
        '">' . __('Settings') . '</a>';
    return $links;
}

function generateEmbed()
{
    if (!get_option('experience_id') && !get_option('customer_id')) {
        return false;
    }

    $experience_id = get_option('experience_id');
    $customer_id = get_option('customer_id');
    $open_timeout = get_option('open_timeout');

    if (get_option('table_secret') && is_user_logged_in()) {
        $current_user = wp_get_current_user();

        $secret = hash_hmac(
            'sha256', // hash function
            get_current_user_id(), // your user's id
            get_option('table_secret') // secret key
        );

        $table_settings = array(
            'email' => $current_user->user_email,
            'short_code' => $experience_id,
            'user_id' => get_current_user_id(),
            'user_hash' => $secret,
            'created_at' => strtotime($current_user->user_registered)
        );

        if ($current_user->user_firstname && $current_user->user_lastname) {
            $user = array(
                'first_name' => $current_user->user_firstname,
                'last_name' => $current_user->user_lastname,
            );
            $table_settings = array_merge($user, $table_settings);
        }
    } else {
        $table_settings = array(
            'short_code' => $experience_id,
            'open_timeout' => $open_timeout
        );
    }

    echo "<script> window.tableSettings = " . wp_json_encode($table_settings) . ';</script>';
    wp_enqueue_script('table_iac', $customer_id . '/static/widget/inappchat.js', array(), '1.0.0', true);
}
 
function add_id_to_script( $tag, $handle, $src )
{
    if ('table_iac' === $handle ) {
        $tag = '<script id="__table_iac" type="text/javascript" src="' . esc_url($src) . '"></script>';
    }
 
    return $tag;
}

add_filter('script_loader_tag', 'add_id_to_script', 10, 3);