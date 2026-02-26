<?php
/**
 * Plugin Name:       WPMU WhatsApp
 * Plugin URI:        https://github.com/martinulc/wpmu-whatsapp
 * Description:       Fixed WhatsApp chat button with schedule, message customisation, and visibility controls. No third-party dependencies, no theme conflicts.
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Martin Ulč
 * Author URI:        https://martinulc.cz
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       wpmu-whatsapp
 * Domain Path:       /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'WPMU_WHATSAPP_VERSION',    '1.0.0' );
define( 'WPMU_WHATSAPP_DIR',        plugin_dir_path( __FILE__ ) );
define( 'WPMU_WHATSAPP_URL',        plugin_dir_url( __FILE__ ) );
define( 'WPMU_WHATSAPP_OPTION_KEY', 'wpmu_whatsapp_settings' );

require_once WPMU_WHATSAPP_DIR . 'includes/class-wpmu-whatsapp-admin.php';
require_once WPMU_WHATSAPP_DIR . 'includes/class-wpmu-whatsapp-frontend.php';

/**
 * Bootstrap the plugin after all plugins are loaded.
 */
function wpmu_whatsapp_init() {
	load_plugin_textdomain( 'wpmu-whatsapp', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );

	$admin    = new WPMUWhatsApp_Admin();
	$frontend = new WPMUWhatsApp_Frontend();

	$admin->init();
	$frontend->init();
}
add_action( 'plugins_loaded', 'wpmu_whatsapp_init' );

/**
 * Store default settings on first activation.
 */
register_activation_hook( __FILE__, 'wpmu_whatsapp_activate' );

function wpmu_whatsapp_activate() {
	if ( false === get_option( WPMU_WHATSAPP_OPTION_KEY ) ) {
		add_option(
			WPMU_WHATSAPP_OPTION_KEY,
			array(
				'enabled'            => 0,
				'phone'              => '',
				'position'           => 'right',
				'label'              => '',
				'default_message'    => '',
				'active_days'        => array( '1', '2', '3', '4', '5' ),
				'time_from'          => '09:00',
				'time_to'            => '17:00',
				'exclude_post_types' => array(),
				'exclude_page_ids'   => array(),
			)
		);
	}
}
