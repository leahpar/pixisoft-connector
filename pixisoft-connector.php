<?php

/*
Plugin Name: Pixisoft Connector
Plugin URI: https://github.com/leahpar/pixisoft-connector
Description: Connecteur WP pour Pixisoft
Version: 0.3.3
Author: Raphaël Bacco
Author URI: https://github.com/leahpar
License: MIT
*/

// URL de check de nouvelle version
const PX_CONNECTOR_JSON_URL = 'https://raw.githubusercontent.com/leahpar/pixisoft-connector/master/info.json';

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

/**
 * The code that runs during plugin activation.
 */
function activate_pixisoft_connetor() {
    require_once plugin_dir_path( __FILE__ ) . 'includes/pixisoft-connector-activator.php';
    Pixisoft_Connector_Activator::activate();
}
register_activation_hook( __FILE__, 'activate_pixisoft_connetor');

/**
 * The code that runs during plugin deactivation.
 */
function deactivate_pixisoft_connetor() {
    require_once plugin_dir_path( __FILE__ ) . 'includes/pixisoft-connector-activator.php';
    Pixisoft_Connector_Activator::deactivate();
}
register_deactivation_hook( __FILE__, 'deactivate_pixisoft_connetor');

/**
 * The core plugin class.
 */
require plugin_dir_path( __FILE__ ) . 'includes/pixisoft-connector-core.php';
$plugin = new Pixisoft_Connector_Core();
$plugin->init();

