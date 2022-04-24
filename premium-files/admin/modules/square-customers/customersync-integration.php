<?php

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Currently plugin version.
 * Start at version 1.0.0 and use SemVer - https://semver.org
 * Rename this for your plugin and update it as you release new versions.
 */
define( 'PLUGIN_NAME_VERSION', '1.0.0' );
define('SQUARE_CUSTOMER_SYNC_PLUGIN_PATH', plugin_dir_path(__FILE__));

/**
 * The code that runs during plugin activation.
 * This action is documented in includes/class-customersync-integration-activator.php
 */
function activate_customersync_integration() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-customersync-integration-activator.php';
	Customer_Sync_Integration_Activator::activate();
}

/**
 * The code that runs during plugin deactivation.
 * This action is documented in includes/class-customersync-integration-deactivator.php
 */
function deactivate_customersync_integration() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-customersync-integration-deactivator.php';
	Customer_Sync_Integration_Deactivator::deactivate();
}

register_activation_hook( __FILE__, 'activate_customersync_integration' );
register_deactivation_hook( __FILE__, 'deactivate_customersync_integration' );

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
require plugin_dir_path( __FILE__ ) . 'includes/class-customersync-integration.php';

/**
 * Begins execution of the plugin.
 *
 * Since everything within the plugin is registered via hooks,
 * then kicking off the plugin from this point in the file does
 * not affect the page life cycle.
 *
 * @since    1.0.0
 */
function run_customersync_integration() {

	$plugin = new Customer_Sync_Integration();
	$plugin->run();

}
run_customersync_integration();
