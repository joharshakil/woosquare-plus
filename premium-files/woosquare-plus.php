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
define( 'PLUGIN_NAME_VERSION_WOOSQUARE_PLUS', '1.0.0' );
define('WOO_SQUARE_TABLE_DELETED_DATA','woo_square_integration_deleted_data');
define('WOO_SQUARE_TABLE_SYNC_LOGS','woo_square_integration_logs');

define('WOO_SQUARE_PLUGIN_URL_PLUS', plugin_dir_url(__FILE__));
define('WOO_SQUARE_PLUS_PLUGIN_PATH', plugin_dir_path(__FILE__));
if ( ! defined( 'WOO_SQUARE_PLUGIN_URL' ) ) {
	define( 'WOO_SQUARE_PLUGIN_URL', plugin_dir_url(__FILE__).'admin/modules/product-sync/' );
}


//inc freemius
// require_once( plugin_dir_path(__FILE__) . 'includes/square_freemius.php' );


//connection auth credentials

if (!defined('WOOSQU_PLUS_CONNECTURL')) define('WOOSQU_PLUS_CONNECTURL','https://connect.apiexperts.io');

$woocommerce_square_plus_settings = get_option('woocommerce_square_plus_settings');

if(@$woocommerce_square_plus_settings['enable_sandbox'] == 'yes'){
//	if (!defined('WOOSQU_PLUS_APPID')) define('WOOSQU_PLUS_APPID',$woocommerce_square_plus_settings['sandbox_application_id']);
	if (!defined('WOOSQU_PLUS_APPID')) define('WOOSQU_PLUS_APPID','sandbox-sq0idb-F6yOk7xyHAOEmRP4wYggsA');
} else {
	if (!defined('WOOSQU_PLUS_APPID')) define('WOOSQU_PLUS_APPID','sq0idp-z7vv-p7qmRlqcMRJLinEkA');
}




if (!defined('WOOSQU_PLUS_APPNAME')) define('WOOSQU_PLUS_APPNAME','Woo Plus');




	if(!defined('WOO_SQUARE_MAX_SYNC_TIME')){
		//max sync running time
		// numofpro*60
		if (get_option('_transient_timeout_transient_get_products' ) > time()){
			$total_productcount = get_transient( 'transient_get_products');
		} else {
			$args     = array( 	'post_type' => 'product', 
								'posts_per_page' => -1 
			);
			$products = get_posts( $args ); 		
			$total_productcount = count($products);
			set_transient( 'transient_get_products', $total_productcount , 720 );
			
		}
		if($total_productcount > 1){
			define('WOO_SQUARE_MAX_SYNC_TIME', $total_productcount*60 );
		} else {
			define('WOO_SQUARE_MAX_SYNC_TIME', 10*60 );
		}
	}


// define( 'WooSquare_PATH', untrailingslashit( plugin_dir_path( __FILE__ ) ) );
// define( 'WooSquare_PLUGIN_URL_PAYMENT', untrailingslashit( plugins_url( basename( plugin_dir_path( __FILE__ ) ), basename( __FILE__ ) ) ) );
@$woocommerce_square_plus_settings = get_option('woocommerce_square_plus_settings');


if(@$woocommerce_square_plus_settings['enable_sandbox'] == 'yes'){
	if ( ! defined( 'WOOSQU_ENABLE_STAGING' ) ) {
		define( 'WOOSQU_ENABLE_STAGING', true );
		define( 'WOOSQU_ENABLE_SANDBOX', 'SANDBOX' );
		define( 'WOOSQU_STAGING_URL', 'squareupsandbox' );
		define( 'WOOSQU_SUFFIX', '_sandbox' );
	}
} else {
	if ( ! defined( 'WOOSQU_ENABLE_STAGING' ) ) {
		define( 'WOOSQU_ENABLE_STAGING', false );
		define( 'WOOSQU_STAGING_URL', 'squareup' );
		define( 'WOOSQU_ENABLE_PRODUCTION', 'PRODUCTION' );
		define( 'WOOSQU_SUFFIX', '' );
	}
}


/**
 * The code that runs during plugin activation.
 * This action is documented in includes/class-woosquare-plus-activator.php
 */
function activate_woosquare_plus() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-woosquare-plus-activator.php';
	Woosquare_Plus_Activator::activate();
}

/**
 * The code that runs during plugin deactivation.
 * This action is documented in includes/class-woosquare-plus-deactivator.php
 */
function deactivate_woosquare_plus() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-woosquare-plus-deactivator.php';
	Woosquare_Plus_Deactivator::deactivate();
}

add_action( 'plugins_loaded', 'activate_woosquare_plus' );
add_action( 'plugins_loaded', 'deactivate_woosquare_plus' );

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
require plugin_dir_path( __FILE__ ) . 'includes/class-woosquare-plus.php';

/**
 * Begins execution of the plugin.
 *
 * Since everything within the plugin is registered via hooks,
 * then kicking off the plugin from this point in the file does
 * not affect the page life cycle.
 *
 * @since    1.0.0
 */
function run_woosquare_plus() {

	$plugin = new Woosquare_Plus();
	// global $qu_fs;
	// if (qu_fs()->can_use_premium_code()) {
		$plugin->run();
	// }

}

add_action('plugins_loaded', 'run_woosquare_plus', 0);


