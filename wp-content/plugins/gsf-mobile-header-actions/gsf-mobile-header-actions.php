<?php
/**
 * Plugin Name: GSF Mobile Header Actions
 * Description: Makes the GSF Hub language selector and Sign in/Log out action available inside the expanded mobile header menu.
 * Version: 1.0.0
 * Author: GSF Hub
 * Text Domain: gsf-mobile-header-actions
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'GSF_MOBILE_HEADER_ACTIONS_VERSION', '1.0.0' );
define( 'GSF_MOBILE_HEADER_ACTIONS_DIR', plugin_dir_path( __FILE__ ) );
define( 'GSF_MOBILE_HEADER_ACTIONS_URL', plugin_dir_url( __FILE__ ) );

/**
 * Enqueues the mobile header enhancement after the GSF theme assets.
 */
function gsf_mobile_header_actions_enqueue_assets() {
	$style_path = GSF_MOBILE_HEADER_ACTIONS_DIR . 'assets/mobile-header-actions.css';
	$script_path = GSF_MOBILE_HEADER_ACTIONS_DIR . 'assets/mobile-header-actions.js';
	$style_dependencies = array( 'gsf-hub-theme' );

	if ( wp_style_is( 'gsf-hub-sunrise-theme', 'registered' ) ) {
		$style_dependencies[] = 'gsf-hub-sunrise-theme';
	}

	wp_enqueue_style(
		'gsf-mobile-header-actions',
		GSF_MOBILE_HEADER_ACTIONS_URL . 'assets/mobile-header-actions.css',
		$style_dependencies,
		file_exists( $style_path ) ? filemtime( $style_path ) : GSF_MOBILE_HEADER_ACTIONS_VERSION
	);

	wp_enqueue_script(
		'gsf-mobile-header-actions',
		GSF_MOBILE_HEADER_ACTIONS_URL . 'assets/mobile-header-actions.js',
		array( 'gsf-hub-navigation' ),
		file_exists( $script_path ) ? filemtime( $script_path ) : GSF_MOBILE_HEADER_ACTIONS_VERSION,
		true
	);
}
add_action( 'wp_enqueue_scripts', 'gsf_mobile_header_actions_enqueue_assets', 30 );
