<?php
/**
 * Preserves the parent homepage and replaces only its search panel.
 *
 * @package GSF_Hub_Assistant_Child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$parent_front_page = trailingslashit( get_template_directory() ) . 'front-page.php';

if ( ! file_exists( $parent_front_page ) ) {
	get_template_part( 'index' );
	return;
}

$assistant_markup = shortcode_exists( 'gsf_resource_assistant_home' )
	? do_shortcode( '[gsf_resource_assistant_home]' )
	: '';

ob_start();
require $parent_front_page;
$parent_markup = ob_get_clean();

if ( '' === $assistant_markup ) {
	echo $parent_markup; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	return;
}

$replacement_count = 0;
$updated_markup     = preg_replace(
	'#<section\b[^>]*class=(["\'])[^"\']*\bsearch-panel-wrapper\b[^"\']*\1[^>]*>.*?</section>#is',
	$assistant_markup,
	$parent_markup,
	1,
	$replacement_count
);

echo $replacement_count > 0 && is_string( $updated_markup ) ? $updated_markup : $parent_markup; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
