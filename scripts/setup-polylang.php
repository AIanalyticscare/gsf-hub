<?php
/**
 * Configures Polylang languages and translated home pages for the GSF Hub theme.
 */

if ( ! defined( 'WP_CLI' ) ) {
	return;
}

if ( ! function_exists( 'pll_languages_list' ) || ! function_exists( 'pll_set_post_language' ) || ! function_exists( 'pll_save_post_translations' ) ) {
	WP_CLI::error( 'Polylang must be installed and active before running this script.' );
}

$language_blueprint = array(
	'en' => array(
		'locale' => 'en_US',
		'title'  => 'Home',
		'slug'   => 'home',
	),
	'fr' => array(
		'locale' => 'fr_FR',
		'title'  => 'Accueil',
		'slug'   => 'accueil',
	),
	'es' => array(
		'locale' => 'es_ES',
		'title'  => 'Inicio',
		'slug'   => 'inicio',
	),
	'nl' => array(
		'locale' => 'nl_NL',
		'title'  => 'Startpagina',
		'slug'   => 'startpagina',
	),
);

$languages = array();

foreach ( $language_blueprint as $lang_slug => $definition ) {
	$language = PLL()->model->get_language( $definition['locale'] );

	if ( ! $language ) {
		$language = PLL()->model->add_language(
			array(
				'locale'         => $definition['locale'],
				'term_group'     => count( $languages ),
				'no_default_cat' => true,
			)
		);

		if ( is_wp_error( $language ) ) {
			WP_CLI::error( sprintf( 'Failed to add language %s: %s', $definition['locale'], $language->get_error_message() ) );
		}
	}

	$languages[ $lang_slug ] = $language;
}

PLL()->model->update_default_lang( 'en' );

$polylang_options                  = get_option( 'polylang', array() );
$polylang_options['redirect_lang'] = true;
update_option( 'polylang', $polylang_options );

$page_ids = get_posts(
	array(
		'post_type'      => 'page',
		'post_status'    => array( 'publish', 'draft', 'private', 'pending' ),
		'posts_per_page' => -1,
		'fields'         => 'ids',
	)
);

foreach ( $page_ids as $page_id ) {
	if ( ! pll_get_post_language( $page_id ) ) {
		pll_set_post_language( $page_id, 'en' );
	}
}

$front_page_id = (int) get_option( 'page_on_front' );

if ( ! $front_page_id ) {
	WP_CLI::error( 'No static front page is assigned under Settings > Reading.' );
}

pll_set_post_language( $front_page_id, 'en' );

$translations = pll_get_post_translations( $front_page_id );

if ( empty( $translations['en'] ) ) {
	$translations['en'] = $front_page_id;
}

$home_post = get_post( $front_page_id );

if ( ! $home_post instanceof WP_Post ) {
	WP_CLI::error( 'The configured front page could not be loaded.' );
}

$homepage_meta_keys = array();

if ( function_exists( 'gsf_hub_get_homepage_field_definitions' ) ) {
	foreach ( array_keys( gsf_hub_get_homepage_field_definitions() ) as $field_key ) {
		$homepage_meta_keys[] = 'gsf_hub_' . $field_key;
	}
}

foreach ( $language_blueprint as $lang_slug => $definition ) {
	if ( 'en' === $lang_slug ) {
		continue;
	}

	$translation_id = isset( $translations[ $lang_slug ] ) ? (int) $translations[ $lang_slug ] : 0;
	$created        = false;

	if ( ! $translation_id ) {
		$translation_id = wp_insert_post(
			array(
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_title'   => $definition['title'],
				'post_name'    => $definition['slug'],
				'post_content' => '',
				'post_author'  => (int) $home_post->post_author,
				'menu_order'   => (int) $home_post->menu_order,
			),
			true
		);

		if ( is_wp_error( $translation_id ) ) {
			WP_CLI::error( sprintf( 'Failed to create the %s front page translation: %s', strtoupper( $lang_slug ), $translation_id->get_error_message() ) );
		}

		$created = true;
	}

	pll_set_post_language( $translation_id, $lang_slug );
	$translations[ $lang_slug ] = (int) $translation_id;

	if ( $created ) {
		foreach ( $homepage_meta_keys as $meta_key ) {
			$meta_value = get_post_meta( $front_page_id, $meta_key, true );
			update_post_meta( $translation_id, $meta_key, $meta_value );
		}
	}
}

pll_save_post_translations( $translations );

if ( isset( PLL()->static_pages ) && method_exists( PLL()->static_pages, 'clean_cache' ) ) {
	PLL()->static_pages->clean_cache();
}

flush_rewrite_rules();

$summary = array(
	'default_language' => 'en',
	'front_pages'      => $translations,
	'languages'        => pll_languages_list(),
);

WP_CLI::success( wp_json_encode( $summary ) );
