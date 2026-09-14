<?php
/**
 * Theme setup and shared helpers for GSF Hub.
 *
 * @package GSF_Hub
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Sets up theme defaults and WordPress supports.
 */
function gsf_hub_setup() {
	load_theme_textdomain( 'gsf-hub', get_template_directory() . '/languages' );

	add_theme_support( 'title-tag' );
	add_theme_support( 'post-thumbnails' );
	add_theme_support( 'responsive-embeds' );
	add_theme_support(
		'custom-logo',
		array(
			'height'      => 96,
			'width'       => 96,
			'flex-height' => true,
			'flex-width'  => true,
		)
	);
	add_theme_support(
		'html5',
		array(
			'search-form',
			'comment-form',
			'comment-list',
			'gallery',
			'caption',
			'style',
			'script',
		)
	);

	register_nav_menus(
		array(
			'primary' => __( 'Primary Menu', 'gsf-hub' ),
			'footer'  => __( 'Footer Menu', 'gsf-hub' ),
		)
	);
}
add_action( 'after_setup_theme', 'gsf_hub_setup' );

/**
 * Enqueues theme styles and scripts.
 */
function gsf_hub_enqueue_assets() {
	$theme = wp_get_theme();
	$stylesheet_version = filemtime( get_stylesheet_directory() . '/style.css' );
	$theme_css_version  = filemtime( get_template_directory() . '/assets/css/theme.css' );
	$nav_js_version     = filemtime( get_template_directory() . '/assets/js/navigation.js' );

	wp_enqueue_style(
		'gsf-hub-fonts',
		'https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800;900&display=swap',
		array(),
		null
	);

	wp_enqueue_style(
		'gsf-hub-style',
		get_stylesheet_uri(),
		array(),
		$stylesheet_version ? $stylesheet_version : $theme->get( 'Version' )
	);
	wp_enqueue_style(
		'gsf-hub-theme',
		get_template_directory_uri() . '/assets/css/theme.css',
		array( 'gsf-hub-style', 'gsf-hub-fonts' ),
		$theme_css_version ? $theme_css_version : $theme->get( 'Version' )
	);

	wp_enqueue_script(
		'gsf-hub-navigation',
		get_template_directory_uri() . '/assets/js/navigation.js',
		array(),
		$nav_js_version ? $nav_js_version : $theme->get( 'Version' ),
		true
	);
}
add_action( 'wp_enqueue_scripts', 'gsf_hub_enqueue_assets' );

/**
 * Enables expert directory entries as a translatable post type in Polylang.
 *
 * @param string[] $post_types  Existing post types.
 * @param bool     $is_settings Whether Polylang is loading settings UI context.
 * @return string[]
 */
function gsf_hub_polylang_post_types( $post_types, $is_settings ) {
	$post_types[] = 'expert_directory';

	return array_values( array_unique( $post_types ) );
}
add_filter( 'pll_get_post_types', 'gsf_hub_polylang_post_types', 10, 2 );

/**
 * Enables expert directory taxonomies in Polylang.
 *
 * @param string[] $taxonomies  Existing taxonomies.
 * @param bool     $is_settings Whether Polylang is loading settings UI context.
 * @return string[]
 */
function gsf_hub_polylang_taxonomies( $taxonomies, $is_settings ) {
	$taxonomies[] = 'country';

	return array_values( array_unique( $taxonomies ) );
}
add_filter( 'pll_get_taxonomies', 'gsf_hub_polylang_taxonomies', 10, 2 );

/**
 * Returns a normalized expert image payload from a raw field value.
 *
 * @param mixed $value Field value.
 * @return array{id:int,url:string}
 */
function gsf_hub_get_media_reference( $value ) {
	$value = maybe_unserialize( $value );

	if ( is_array( $value ) && isset( $value[0] ) && 1 === count( $value ) ) {
		$value = maybe_unserialize( $value[0] );
	}

	if ( is_numeric( $value ) ) {
		$attachment_id = (int) $value;
		$image_url     = wp_get_attachment_image_url( $attachment_id, 'large' );

		return array(
			'id'  => $attachment_id,
			'url' => $image_url ? $image_url : '',
		);
	}

	if ( is_array( $value ) ) {
		if ( isset( $value['ID'] ) || isset( $value['id'] ) ) {
			$attachment_id = (int) ( $value['ID'] ?? $value['id'] );
			$image_url     = wp_get_attachment_image_url( $attachment_id, 'large' );

			if ( $image_url ) {
				return array(
					'id'  => $attachment_id,
					'url' => $image_url,
				);
			}
		}

		foreach ( array( 'guid', 'url', '_src', 'src' ) as $url_key ) {
			if ( ! empty( $value[ $url_key ] ) && filter_var( $value[ $url_key ], FILTER_VALIDATE_URL ) ) {
				return array(
					'id'  => 0,
					'url' => (string) $value[ $url_key ],
				);
			}
		}

		if ( isset( $value[0] ) ) {
			return gsf_hub_get_media_reference( $value[0] );
		}
	}

	if ( is_string( $value ) && filter_var( $value, FILTER_VALIDATE_URL ) ) {
		return array(
			'id'  => 0,
			'url' => $value,
		);
	}

	return array(
		'id'  => 0,
		'url' => '',
	);
}

/**
 * Returns expert taxonomy term names.
 *
 * @param int    $post_id   Post ID.
 * @param string $taxonomy  Taxonomy slug.
 * @return string[]
 */
function gsf_hub_get_expert_term_names( $post_id, $taxonomy ) {
	if ( ! taxonomy_exists( $taxonomy ) ) {
		return array();
	}

	$terms = wp_get_post_terms( $post_id, $taxonomy );

	if ( is_wp_error( $terms ) || empty( $terms ) ) {
		return array();
	}

	return array_values(
		array_filter(
			array_map(
				static function ( $term ) {
					return $term instanceof WP_Term ? $term->name : '';
				},
				$terms
			)
		)
	);
}

/**
 * Normalizes a Pods pick/meta value into integer IDs.
 *
 * @param mixed $value Raw meta value.
 * @return int[]
 */
function gsf_hub_normalize_meta_ids( $value ) {
	$value = maybe_unserialize( $value );

	if ( is_numeric( $value ) ) {
		return array( (int) $value );
	}

	if ( is_string( $value ) && strpos( $value, ',' ) !== false ) {
		$value = array_map( 'trim', explode( ',', $value ) );
	}

	if ( ! is_array( $value ) ) {
		return array();
	}

	$ids = array();

	foreach ( $value as $item ) {
		if ( is_numeric( $item ) ) {
			$ids[] = (int) $item;
		}
	}

	return array_values( array_unique( array_filter( $ids ) ) );
}

/**
 * Returns a non-negative integer value from post meta.
 *
 * @param int    $post_id Post ID.
 * @param string $key     Meta key.
 * @return int
 */
function gsf_hub_get_numeric_meta_value( $post_id, $key ) {
	$value = get_post_meta( $post_id, $key, true );

	if ( '' === $value || null === $value ) {
		return 0;
	}

	return max( 0, (int) preg_replace( '/[^0-9]/', '', (string) $value ) );
}

/**
 * Returns project progress rows grouped by project type for homepage display.
 *
 * @param int $limit Maximum number of rows.
 * @return array<int,array{label:string,value:int,total:int,active:int}>
 */
function gsf_hub_get_home_project_progress_metrics( $limit = 4 ) {
	$projects = get_posts(
		array(
			'post_type'      => 'gsf_project',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'orderby'        => 'menu_order title',
			'order'          => 'ASC',
		)
	);

	if ( empty( $projects ) ) {
		return array();
	}

	$groups = array();

	foreach ( $projects as $project ) {
		$type = trim( (string) get_post_meta( $project->ID, 'project_type', true ) );

		if ( '' === $type ) {
			$type = __( 'Unspecified projects', 'gsf-hub' );
		}

		if ( ! isset( $groups[ $type ] ) ) {
			$groups[ $type ] = array(
				'total'       => 0,
				'active'      => 0,
				'percent_sum' => 0,
			);
		}

		$status  = strtolower( (string) get_post_meta( $project->ID, 'project_status', true ) );
		$percent = min( 100, gsf_hub_get_numeric_meta_value( $project->ID, 'percent_complete' ) );

		$groups[ $type ]['total']++;
		$groups[ $type ]['percent_sum'] += $percent;

		if ( ! in_array( $status, array( 'completed', 'not started' ), true ) ) {
			$groups[ $type ]['active']++;
		}
	}

	$rows = array();

	foreach ( $groups as $type => $group ) {
		$total = max( 1, (int) $group['total'] );
		$rows[] = array(
			'label'  => $type,
			'value'  => (int) round( $group['percent_sum'] / $total ),
			'total'  => (int) $group['total'],
			'active' => (int) $group['active'],
		);
	}

	usort(
		$rows,
		static function ( $a, $b ) {
			return $b['value'] <=> $a['value'];
		}
	);

	return array_slice( $rows, 0, max( 1, (int) $limit ) );
}

/**
 * Returns term IDs for an expert Pods pick field, with taxonomy sync fallback.
 *
 * @param int    $post_id    Post ID.
 * @param string $field_name Pods field name.
 * @param string $taxonomy   Taxonomy slug.
 * @return int[]
 */
function gsf_hub_get_expert_pick_taxonomy_ids( $post_id, $field_name, $taxonomy ) {
	$term_ids = array();

	if ( taxonomy_exists( $taxonomy ) ) {
		$term_ids = wp_get_post_terms(
			$post_id,
			$taxonomy,
			array(
				'fields' => 'ids',
			)
		);

		if ( is_wp_error( $term_ids ) ) {
			$term_ids = array();
		}
	}

	foreach ( array( $field_name, '_pods_' . $field_name ) as $meta_key ) {
		$term_ids = array_merge( $term_ids, gsf_hub_normalize_meta_ids( get_post_meta( $post_id, $meta_key, true ) ) );
	}

	return array_values( array_unique( array_map( 'intval', array_filter( $term_ids ) ) ) );
}

/**
 * Returns term names for an expert Pods pick field, with taxonomy sync fallback.
 *
 * @param int    $post_id    Post ID.
 * @param string $field_name Pods field name.
 * @param string $taxonomy   Taxonomy slug.
 * @return string[]
 */
function gsf_hub_get_expert_pick_taxonomy_names( $post_id, $field_name, $taxonomy ) {
	if ( ! taxonomy_exists( $taxonomy ) ) {
		return array();
	}

	$names = array();

	foreach ( gsf_hub_get_expert_pick_taxonomy_ids( $post_id, $field_name, $taxonomy ) as $term_id ) {
		$term = get_term( $term_id, $taxonomy );

		if ( $term instanceof WP_Term ) {
			$names[] = $term->name;
		}
	}

	return array_values( array_unique( array_filter( $names ) ) );
}

/**
 * Returns the best-available biography text for an expert.
 *
 * @param int $post_id Post ID.
 * @return string
 */
function gsf_hub_get_expert_bio( $post_id ) {
	foreach ( array( 'short_bio', 'bio', 'profile_bio', 'summary' ) as $meta_key ) {
		$value = get_post_meta( $post_id, $meta_key, true );

		if ( is_string( $value ) && trim( $value ) ) {
			return trim( wp_strip_all_tags( $value ) );
		}
	}

	$excerpt = get_the_excerpt( $post_id );

	if ( is_string( $excerpt ) && trim( $excerpt ) ) {
		return trim( wp_strip_all_tags( $excerpt ) );
	}

	$content = get_post_field( 'post_content', $post_id );

	if ( is_string( $content ) && trim( $content ) ) {
		return wp_trim_words( wp_strip_all_tags( $content ), 38 );
	}

	return '';
}

/**
 * Returns expert initials for avatar fallbacks.
 *
 * @param string $name Expert name.
 * @return string
 */
function gsf_hub_get_expert_initials( $name ) {
	$parts = preg_split( '/\s+/', trim( $name ) );
	$parts = array_values( array_filter( $parts ) );

	if ( empty( $parts ) ) {
		return 'GSF';
	}

	$initials = '';

	foreach ( array_slice( $parts, 0, 2 ) as $part ) {
		$initials .= strtoupper( substr( $part, 0, 1 ) );
	}

	return $initials;
}

/**
 * Returns normalized data for an expert roster card/profile.
 *
 * @param int $post_id Post ID.
 * @return array<string, mixed>
 */
function gsf_hub_get_expert_profile_data( $post_id ) {
	$name         = get_post_meta( $post_id, 'full_name', true );
	$name         = is_string( $name ) && trim( $name ) ? trim( $name ) : get_the_title( $post_id );
	$organization = get_post_meta( $post_id, 'organization', true );
	$organization = is_string( $organization ) ? trim( $organization ) : '';
	$image        = gsf_hub_get_media_reference( get_post_meta( $post_id, 'upload_photo', true ) );

	if ( ! $image['url'] && has_post_thumbnail( $post_id ) ) {
		$image = array(
			'id'  => (int) get_post_thumbnail_id( $post_id ),
			'url' => (string) get_the_post_thumbnail_url( $post_id, 'large' ),
		);
	}

	$data = array(
		'id'           => (int) $post_id,
		'name'         => $name,
		'initials'     => gsf_hub_get_expert_initials( $name ),
		'organization' => $organization,
		'image_id'     => $image['id'],
		'image_url'    => $image['url'],
		'bio'          => gsf_hub_get_expert_bio( $post_id ),
		'country'      => gsf_hub_get_expert_pick_taxonomy_names( $post_id, 'country', 'country' ),
		'expertise'    => gsf_hub_get_expert_term_names( $post_id, 'expertise' ),
		'languages'    => gsf_hub_get_expert_term_names( $post_id, 'language' ),
		'permalink'    => add_query_arg(
			'expert_directory',
			get_post_field( 'post_name', $post_id ),
			home_url( '/' )
		),
	);

	foreach ( array( 'job_title', 'email', 'website', 'linkedin', 'contact_link' ) as $meta_key ) {
		$value = get_post_meta( $post_id, $meta_key, true );
		$data[ $meta_key ] = is_string( $value ) ? trim( $value ) : '';
	}

	return $data;
}

/**
 * Returns roster-level counts for the expert directory.
 *
 * @return array<string, int>
 */
function gsf_hub_get_expert_directory_stats() {
	$expert_ids = get_posts(
		array(
			'post_type'      => 'expert_directory',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'orderby'        => 'title',
			'order'          => 'ASC',
			'lang'           => '',
		)
	);

	$organizations = array();
	$country_ids   = array();

	foreach ( $expert_ids as $expert_id ) {
		$organization = trim( (string) get_post_meta( $expert_id, 'organization', true ) );
		$country_ids  = array_merge( $country_ids, gsf_hub_get_expert_pick_taxonomy_ids( $expert_id, 'country', 'country' ) );

		if ( $organization ) {
			$organizations[] = strtolower( $organization );
		}
	}

	return array(
		'experts'       => count( $expert_ids ),
		'countries'     => count( array_unique( array_filter( array_map( 'intval', $country_ids ) ) ) ),
		'organizations' => count( array_unique( $organizations ) ),
	);
}

/**
 * Returns expert IDs that match a directory search term across core fields and meta.
 *
 * @param string $search_term Raw search term.
 * @return int[]
 */
function gsf_hub_get_expert_search_ids( $search_term ) {
	global $wpdb;

	$search_term = trim( $search_term );

	if ( '' === $search_term ) {
		return array();
	}

	$meta_keys      = array( 'full_name', 'organization', 'short_bio', 'bio', 'profile_bio', 'summary', 'job_title' );
	$meta_sql       = implode( ', ', array_fill( 0, count( $meta_keys ), '%s' ) );
	$search_pattern = '%' . $wpdb->esc_like( $search_term ) . '%';
	$sql            = $wpdb->prepare(
		"
		SELECT DISTINCT posts.ID
		FROM {$wpdb->posts} AS posts
		LEFT JOIN {$wpdb->postmeta} AS meta
			ON posts.ID = meta.post_id
		WHERE posts.post_type = %s
			AND posts.post_status = 'publish'
			AND (
				posts.post_title LIKE %s
				OR posts.post_content LIKE %s
				OR (
					meta.meta_key IN ($meta_sql)
					AND meta.meta_value LIKE %s
				)
			)
		ORDER BY posts.post_title ASC
		",
		array_merge(
			array( 'expert_directory', $search_pattern, $search_pattern ),
			$meta_keys,
			array( $search_pattern )
		)
	);

	return array_values( array_map( 'intval', $wpdb->get_col( $sql ) ) );
}

/**
 * Renders the expert directory filter UI, using Search & Filter when available.
 *
 * @param array<string, mixed> $view_model Directory query state.
 * @return string
 */
	function gsf_hub_render_expert_directory_filters( $view_model ) {
		$page_url               = isset( $view_model['page_url'] ) ? (string) $view_model['page_url'] : home_url( '/' );
		$directory_anchor_url   = untrailingslashit( $page_url ) . '#expert-directory';
		$selected_term          = isset( $view_model['selected_term'] ) ? (string) $view_model['selected_term'] : '';
	$selected_country_value = isset( $view_model['selected_country_value'] ) ? (string) $view_model['selected_country_value'] : '';
	$search_term            = isset( $view_model['search_term'] ) ? (string) $view_model['search_term'] : '';
	$available_terms        = isset( $view_model['available_terms'] ) && is_array( $view_model['available_terms'] ) ? $view_model['available_terms'] : array();

	if ( shortcode_exists( 'searchandfilter' ) ) {
		$restore_language       = false;
		$current_language_slug  = '';

		if ( function_exists( 'pll_current_language' ) && function_exists( 'pll_default_language' ) && function_exists( 'pll_switch_language' ) ) {
			$current_language_slug = (string) pll_current_language( 'slug' );
			$default_language_slug = (string) pll_default_language( 'slug' );

			if ( $current_language_slug && $default_language_slug && $current_language_slug !== $default_language_slug ) {
				pll_switch_language( $default_language_slug );
				$restore_language = true;
			}
		}

		$filter_form = do_shortcode(
			'[searchandfilter fields="search,country" types="text,select" headings=", " search_placeholder="' . esc_attr__( 'Search by name, organization, role, or profile text', 'gsf-hub' ) . '" submit_label="' . esc_attr__( 'Apply Filters', 'gsf-hub' ) . '" hide_empty="0,0" all_items_labels=",All countries" post_types="expert_directory" class="directory-filters gsf-directory-search-filter"]'
		);

		if ( $restore_language ) {
			pll_switch_language( $current_language_slug );
		}

		if ( is_string( $filter_form ) && '' !== trim( $filter_form ) ) {
			ob_start();
			?>
			<option value="0" <?php selected( '', $selected_country_value ); ?>><?php esc_html_e( 'All countries', 'gsf-hub' ); ?></option>
			<?php foreach ( $available_terms as $term ) : ?>
				<?php if ( ! $term instanceof WP_Term ) : ?>
					<?php continue; ?>
				<?php endif; ?>
				<option class="level-0" value="<?php echo esc_attr( (string) $term->term_id ); ?>" <?php selected( $selected_country_value, (string) $term->term_id ); ?>><?php echo esc_html( $term->name ); ?></option>
			<?php endforeach; ?>
			<?php
			$country_options_markup = trim( (string) ob_get_clean() );
			$filter_form            = preg_replace( '#<h4>\s*</h4>#', '', $filter_form );

			if ( is_string( $country_options_markup ) && '' !== $country_options_markup ) {
				$filter_form = preg_replace(
					'#(<select[^>]*name=[\'"]ofcountry[\'"][^>]*>).*?(</select>)#is',
					'$1' . $country_options_markup . '$2',
					$filter_form,
					1
				);
			}

				$filter_form = preg_replace( '#action=(["\'])\1#i', 'action="' . esc_url( $directory_anchor_url ) . '"', $filter_form, 1 );
				$filter_form = preg_replace( '#method=(["\'])post\1#i', 'method="get"', $filter_form, 1 );
			$filter_form = str_replace( array( 'name="ofsearch"', "name='ofsearch'" ), 'name="expert_search"', $filter_form );
			$filter_form = str_replace( array( 'name="ofcountry"', "name='ofcountry'" ), 'name="country"', $filter_form );
			$filter_form = preg_replace(
				'#(<input[^>]*name=["\']expert_search["\'][^>]*value=["\']).*?(["\'][^>]*>)#is',
				'$1' . esc_attr( $search_term ) . '$2',
				$filter_form,
				1
			);
			$filter_form = preg_replace( '#<input[^>]*name=["\']ofpost_types\[\]["\'][^>]*>#i', '', $filter_form );
			$filter_form = preg_replace( '#<input[^>]*name=["\']ofcountry_operator["\'][^>]*>#i', '', $filter_form );
			$filter_form = preg_replace( '#<input[^>]*name=["\']_searchandfilter_nonce["\'][^>]*>#i', '', $filter_form );
			$filter_form = preg_replace( '#<input[^>]*name=["\']_wp_http_referer["\'][^>]*>#i', '', $filter_form );
			$filter_form = preg_replace( '#<input[^>]*name=["\']ofsubmitted["\'][^>]*>#i', '', $filter_form );

			ob_start();
			?>
			<div class="directory-filter-plugin">
					<?php echo $filter_form; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					<?php if ( $search_term || $selected_term ) : ?>
						<a class="button button--ghost directory-filter-plugin__reset" href="<?php echo esc_url( $directory_anchor_url ); ?>"><?php esc_html_e( 'Reset', 'gsf-hub' ); ?></a>
					<?php endif; ?>
				</div>
			<?php
			return (string) ob_get_clean();
		}
	}

	ob_start();
	?>
		<form class="directory-filters" method="get" action="<?php echo esc_url( $directory_anchor_url ); ?>">
		<div class="directory-filter directory-filter--search">
			<label class="screen-reader-text" for="expert-directory-search"><?php esc_html_e( 'Search experts', 'gsf-hub' ); ?></label>
			<span class="directory-filter__icon"><?php echo gsf_hub_get_icon( 'search' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
			<input id="expert-directory-search" type="search" name="expert_search" value="<?php echo esc_attr( $search_term ); ?>" placeholder="<?php esc_attr_e( 'Search by name, organization, role, or profile text', 'gsf-hub' ); ?>">
		</div>
		<div class="directory-filter">
			<label class="screen-reader-text" for="expert-directory-country"><?php esc_html_e( 'Filter by country', 'gsf-hub' ); ?></label>
			<select id="expert-directory-country" name="country">
				<option value=""><?php esc_html_e( 'All countries', 'gsf-hub' ); ?></option>
				<?php foreach ( $available_terms as $term ) : ?>
					<?php if ( ! $term instanceof WP_Term ) : ?>
						<?php continue; ?>
					<?php endif; ?>
					<option value="<?php echo esc_attr( $term->slug ); ?>" <?php selected( $selected_term, $term->slug ); ?>><?php echo esc_html( $term->name ); ?></option>
				<?php endforeach; ?>
			</select>
		</div>
			<button class="button button--primary" type="submit"><?php esc_html_e( 'Apply Filters', 'gsf-hub' ); ?></button>
			<?php if ( $search_term || $selected_term ) : ?>
				<a class="button button--ghost" href="<?php echo esc_url( $directory_anchor_url ); ?>"><?php esc_html_e( 'Reset', 'gsf-hub' ); ?></a>
			<?php endif; ?>
		</form>
	<?php

	return (string) ob_get_clean();
}

/**
 * Returns current expert directory query state for rendering.
 *
 * @param int $page_id Directory page ID.
 * @return array<string, mixed>
 */
function gsf_hub_get_expert_directory_view_model( $page_id = 0 ) {
	$page_id                = $page_id ? (int) $page_id : (int) get_the_ID();
	$raw_selected_term      = isset( $_GET['country'] ) ? trim( (string) wp_unslash( $_GET['country'] ) ) : '';
	$selected_term          = '';
	$selected_country_value = '';
	$selected_country_term  = null;
	$raw_search_term        = isset( $_GET['expert_search'] ) ? wp_unslash( $_GET['expert_search'] ) : ( isset( $_GET['s'] ) ? wp_unslash( $_GET['s'] ) : '' );
	$search_term            = sanitize_text_field( $raw_search_term );
	$current_paged          = max( 1, (int) get_query_var( 'paged' ), (int) get_query_var( 'page' ) );
	$filtered_expert_ids    = null;
	$available_terms        = taxonomy_exists( 'country' )
		? get_terms(
			array(
				'taxonomy'   => 'country',
				'hide_empty' => false,
				'orderby'    => 'name',
				'order'      => 'ASC',
				'lang'       => '',
			)
		)
		: array();

	$query_args = array(
		'post_type'      => 'expert_directory',
		'post_status'    => 'publish',
		'posts_per_page' => 9,
		'paged'          => $current_paged,
		'orderby'        => 'title',
		'order'          => 'ASC',
		'lang'           => '',
	);

	if ( $search_term ) {
		$filtered_expert_ids = gsf_hub_get_expert_search_ids( $search_term );
	}

	if ( $raw_selected_term && taxonomy_exists( 'country' ) ) {
		if ( ctype_digit( $raw_selected_term ) ) {
			$selected_country_term = get_term_by( 'id', (int) $raw_selected_term, 'country' );
		} else {
			$matching_terms = get_terms(
				array(
					'taxonomy'   => 'country',
					'hide_empty' => false,
					'slug'       => sanitize_title( $raw_selected_term ),
					'lang'       => '',
					'number'     => 1,
				)
			);
			$selected_country_term = ! empty( $matching_terms[0] ) && $matching_terms[0] instanceof WP_Term ? $matching_terms[0] : null;
		}
	}

	if ( $selected_country_term instanceof WP_Term ) {
		$selected_term          = $selected_country_term->slug;
		$selected_country_value = (string) $selected_country_term->term_id;
		$country_match_ids      = get_posts(
			array(
				'post_type'      => 'expert_directory',
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'orderby'        => 'title',
				'order'          => 'ASC',
				'lang'           => '',
				'meta_query'     => array(
					'relation' => 'OR',
					array(
						'key'     => 'country',
						'value'   => (string) $selected_country_term->term_id,
						'compare' => '=',
					),
					array(
						'key'     => '_pods_country',
						'value'   => 'i:' . (string) $selected_country_term->term_id . ';',
						'compare' => 'LIKE',
					),
				),
			)
		);

		$filtered_expert_ids = null === $filtered_expert_ids
			? $country_match_ids
			: array_values( array_intersect( $filtered_expert_ids, $country_match_ids ) );
	}

	if ( null !== $filtered_expert_ids ) {
		$query_args['post__in'] = ! empty( $filtered_expert_ids ) ? $filtered_expert_ids : array( 0 );
	}

	return array(
		'page_id'         => $page_id,
		'page_url'        => $page_id ? get_permalink( $page_id ) : get_permalink(),
		'selected_term'   => $selected_term,
		'selected_country_value' => $selected_country_value,
		'search_term'     => $search_term,
		'current_paged'   => $current_paged,
		'available_terms' => $available_terms,
		'query'           => new WP_Query( $query_args ),
	);
}

/**
 * Renders the expert directory listing UI.
 *
 * @param int $page_id Directory page ID.
 * @return string
 */
function gsf_hub_render_expert_directory_listing( $page_id = 0 ) {
	$view_model      = gsf_hub_get_expert_directory_view_model( $page_id );
	$selected_term   = $view_model['selected_term'];
	$search_term     = $view_model['search_term'];
	$experts         = $view_model['query'];

	ob_start();
	?>
		<section id="expert-directory" class="directory-toolbar section-block">
			<?php echo gsf_hub_render_expert_directory_filters( $view_model ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		</section>

	<?php if ( $experts->have_posts() ) : ?>
		<section class="expert-grid">
			<?php while ( $experts->have_posts() ) : ?>
				<?php
				$experts->the_post();
				$expert = gsf_hub_get_expert_profile_data( get_the_ID() );
				?>
				<article <?php post_class( 'expert-card' ); ?>>
					<div class="expert-card__media">
						<?php if ( $expert['image_url'] ) : ?>
							<img src="<?php echo esc_url( $expert['image_url'] ); ?>" alt="<?php echo esc_attr( $expert['name'] ); ?>">
						<?php else : ?>
							<div class="expert-card__placeholder"><?php echo esc_html( $expert['initials'] ); ?></div>
						<?php endif; ?>
					</div>
					<div class="expert-card__content">
						<?php if ( ! empty( $expert['country'] ) ) : ?>
							<div class="expert-card__tags">
								<?php foreach ( $expert['country'] as $country_name ) : ?>
									<span class="expert-tag"><?php echo esc_html( $country_name ); ?></span>
								<?php endforeach; ?>
							</div>
						<?php endif; ?>
						<h2><a href="<?php echo esc_url( $expert['permalink'] ); ?>"><?php echo esc_html( $expert['name'] ); ?></a></h2>
						<?php if ( $expert['job_title'] ) : ?>
							<p class="expert-card__role"><?php echo esc_html( $expert['job_title'] ); ?></p>
						<?php endif; ?>
						<?php if ( $expert['organization'] ) : ?>
							<p class="expert-card__org"><?php echo esc_html( $expert['organization'] ); ?></p>
						<?php endif; ?>
						<p><?php echo esc_html( $expert['bio'] ? $expert['bio'] : __( 'Profile details and biography will appear here as experts are added to the roster.', 'gsf-hub' ) ); ?></p>
						<div class="expert-card__actions">
							<a class="button button--ghost" href="<?php echo esc_url( $expert['permalink'] ); ?>"><?php esc_html_e( 'View Profile', 'gsf-hub' ); ?></a>
						</div>
					</div>
				</article>
			<?php endwhile; ?>
		</section>

		<div class="pagination-wrap">
			<?php
			echo wp_kses_post(
				paginate_links(
					array(
						'total'   => (int) $experts->max_num_pages,
						'current' => (int) $view_model['current_paged'],
						'add_args' => array_filter(
							array(
								'expert_search' => $search_term,
								'country'       => $selected_term,
							)
						),
					)
				)
			);
			?>
		</div>
	<?php else : ?>
		<section class="content-panel">
			<article class="content-card not-found">
				<h2><?php esc_html_e( 'No experts matched your filters', 'gsf-hub' ); ?></h2>
				<p><?php esc_html_e( 'Try a broader search term or clear the country filter to see the full roster.', 'gsf-hub' ); ?></p>
			</article>
		</section>
	<?php endif; ?>
	<?php
	wp_reset_postdata();

	return (string) ob_get_clean();
}

/**
 * Shortcode wrapper for the expert directory listing.
 *
 * @param array<string, mixed> $atts Shortcode attributes.
 * @return string
 */
function gsf_hub_expert_directory_shortcode( $atts ) {
	$atts = shortcode_atts(
		array(
			'page_id' => 0,
		),
		(array) $atts,
		'gsf_expert_directory'
	);

	return gsf_hub_render_expert_directory_listing( (int) $atts['page_id'] );
}
add_shortcode( 'gsf_expert_directory', 'gsf_hub_expert_directory_shortcode' );

/**
 * Seeds the public expert directory pages and translations.
 */
function gsf_hub_maybe_seed_directory_pages() {
	if ( get_option( 'gsf_hub_directory_pages_seeded' ) ) {
		return;
	}

	$page_definitions = array(
		'en' => array(
			'title' => __( 'Expert Directory', 'gsf-hub' ),
			'slug'  => 'directory',
		),
		'fr' => array(
			'title' => __( 'Repertoire des experts', 'gsf-hub' ),
			'slug'  => 'annuaire-experts',
		),
		'es' => array(
			'title' => __( 'Directorio de Expertos', 'gsf-hub' ),
			'slug'  => 'directorio-expertos',
		),
		'nl' => array(
			'title' => __( 'Expertenoverzicht', 'gsf-hub' ),
			'slug'  => 'expertenoverzicht',
		),
	);

	$base_definition = $page_definitions['en'];
	$base_page       = get_page_by_path( $base_definition['slug'] );

	if ( ! $base_page ) {
		$base_page_id = wp_insert_post(
			array(
				'post_title'   => $base_definition['title'],
				'post_name'    => $base_definition['slug'],
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_content' => '',
			),
			true
		);

		if ( is_wp_error( $base_page_id ) ) {
			return;
		}

		$base_page = get_post( $base_page_id );
	}

	if ( ! $base_page instanceof WP_Post ) {
		return;
	}

	update_post_meta( $base_page->ID, '_wp_page_template', 'template-expert-directory.php' );

	if ( function_exists( 'pll_set_post_language' ) && function_exists( 'pll_save_post_translations' ) && function_exists( 'pll_languages_list' ) ) {
		pll_set_post_language( $base_page->ID, 'en' );

		$translations       = pll_get_post_translations( $base_page->ID );
		$translations['en'] = $base_page->ID;
		$available_langs    = pll_languages_list();

		foreach ( $page_definitions as $lang_slug => $definition ) {
			if ( 'en' === $lang_slug || ! in_array( $lang_slug, $available_langs, true ) ) {
				continue;
			}

			$translated_id = ! empty( $translations[ $lang_slug ] ) ? (int) $translations[ $lang_slug ] : 0;

			if ( ! $translated_id ) {
				$translated_page = get_page_by_path( $definition['slug'] );

				if ( $translated_page instanceof WP_Post ) {
					$translated_id = (int) $translated_page->ID;
				} else {
					$translated_id = wp_insert_post(
						array(
							'post_title'   => $definition['title'],
							'post_name'    => $definition['slug'],
							'post_type'    => 'page',
							'post_status'  => 'publish',
							'post_content' => '',
						),
						true
					);

					if ( is_wp_error( $translated_id ) ) {
						continue;
					}
				}
			}

			if ( $translated_id ) {
				pll_set_post_language( $translated_id, $lang_slug );
				update_post_meta( $translated_id, '_wp_page_template', 'template-expert-directory.php' );
				$translations[ $lang_slug ] = (int) $translated_id;
			}
		}

		pll_save_post_translations( $translations );
	}

	update_option( 'gsf_hub_directory_pages_seeded', 1 );
}
add_action( 'after_switch_theme', 'gsf_hub_maybe_seed_directory_pages' );

/**
 * Ensures the expert directory pages exist after theme updates.
 */
function gsf_hub_admin_seed_directory_pages() {
	if ( current_user_can( 'manage_options' ) ) {
		gsf_hub_maybe_seed_directory_pages();
	}
}
add_action( 'admin_init', 'gsf_hub_admin_seed_directory_pages' );

/**
 * Backfills empty directory pages with the expert directory shortcode.
 */
function gsf_hub_backfill_directory_page_content() {
	if ( ! current_user_can( 'manage_options' ) || get_option( 'gsf_hub_directory_page_content_seeded' ) ) {
		return;
	}

	foreach ( array( 'directory', 'annuaire-experts', 'directorio-expertos', 'expertenoverzicht' ) as $slug ) {
		$page = get_page_by_path( $slug );

		if ( ! $page instanceof WP_Post ) {
			continue;
		}

		if ( 'template-expert-directory.php' !== get_post_meta( $page->ID, '_wp_page_template', true ) ) {
			continue;
		}

		if ( trim( (string) $page->post_content ) ) {
			continue;
		}

		wp_update_post(
			array(
				'ID'           => $page->ID,
				'post_content' => '[gsf_expert_directory]',
			)
		);
	}

	update_option( 'gsf_hub_directory_page_content_seeded', 1 );
}
add_action( 'admin_init', 'gsf_hub_backfill_directory_page_content' );

/**
 * Ensures the expert directory post type supports public profile URLs.
 *
 * @param array  $args      Post type args.
 * @param string $post_type Post type slug.
 * @return array
 */
function gsf_hub_filter_expert_directory_post_type_args( $args, $post_type ) {
	if ( 'expert_directory' !== $post_type ) {
		return $args;
	}

	$args['public']              = true;
	$args['publicly_queryable']  = true;
	$args['exclude_from_search'] = false;
	$args['has_archive']         = false;

	if ( empty( $args['rewrite'] ) || ! is_array( $args['rewrite'] ) ) {
		$args['rewrite'] = array(
			'slug'       => 'expert-directory',
			'with_front' => true,
		);
	}

	return $args;
}
add_filter( 'register_post_type_args', 'gsf_hub_filter_expert_directory_post_type_args', 20, 2 );

/**
 * Assigns a default language to expert entries that do not have one yet.
 */
function gsf_hub_sync_expert_directory_languages() {
	if ( ! function_exists( 'pll_get_post_language' ) || ! function_exists( 'pll_set_post_language' ) || ! function_exists( 'pll_default_language' ) ) {
		return;
	}

	$default_language = pll_default_language( 'slug' );

	if ( ! $default_language ) {
		return;
	}

	$expert_ids = get_posts(
		array(
			'post_type'      => 'expert_directory',
			'post_status'    => array( 'publish', 'draft', 'pending', 'private' ),
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'orderby'        => 'ID',
			'order'          => 'ASC',
			'lang'           => '',
		)
	);

	foreach ( $expert_ids as $expert_id ) {
		if ( pll_get_post_language( $expert_id, 'slug' ) ) {
			continue;
		}

		pll_set_post_language( $expert_id, $default_language );
	}
}
add_action( 'admin_init', 'gsf_hub_sync_expert_directory_languages' );

/**
 * Ensures new expert entries receive a language assignment.
 *
 * @param int     $post_id Post ID.
 * @param WP_Post $post    Saved post object.
 */
function gsf_hub_set_expert_directory_language_on_save( $post_id, $post ) {
	if ( wp_is_post_revision( $post_id ) || 'expert_directory' !== $post->post_type ) {
		return;
	}

	if ( ! function_exists( 'pll_get_post_language' ) || ! function_exists( 'pll_set_post_language' ) || ! function_exists( 'pll_default_language' ) ) {
		return;
	}

	if ( pll_get_post_language( $post_id, 'slug' ) ) {
		return;
	}

	$default_language = pll_default_language( 'slug' );

	if ( $default_language ) {
		pll_set_post_language( $post_id, $default_language );
	}
}
add_action( 'save_post', 'gsf_hub_set_expert_directory_language_on_save', 20, 2 );

/**
 * Prevents canonical redirects from collapsing expert profile URLs to the homepage.
 *
 * @param string|false $redirect_url  Proposed redirect target.
 * @param string       $requested_url Requested URL.
 * @return string|false
 */
function gsf_hub_disable_expert_directory_canonical_redirects( $redirect_url, $requested_url ) {
	if ( is_admin() ) {
		return $redirect_url;
	}

	$requested_path = wp_parse_url( $requested_url, PHP_URL_PATH );
	$is_expert_path = is_string( $requested_path ) && preg_match( '#/expert-directory/[^/]+/?$#', $requested_path );

	if ( is_singular( 'expert_directory' ) || get_query_var( 'expert_directory' ) || $is_expert_path ) {
		return false;
	}

	return $redirect_url;
}
add_filter( 'redirect_canonical', 'gsf_hub_disable_expert_directory_canonical_redirects', 10, 2 );

/**
 * Returns the front page IDs, including Polylang translations when available.
 *
 * @return int[]
 */
function gsf_hub_get_front_page_ids() {
	$front_page_id = (int) get_option( 'page_on_front' );

	if ( ! $front_page_id ) {
		return array();
	}

	$front_page_ids = array( $front_page_id );

	if ( function_exists( 'pll_get_post_translations' ) ) {
		$translations = pll_get_post_translations( $front_page_id );

		if ( is_array( $translations ) ) {
			$front_page_ids = array_merge( $front_page_ids, array_values( $translations ) );
		}
	}

	return array_values( array_unique( array_map( 'intval', $front_page_ids ) ) );
}

/**
 * Returns whether a page is the front page or one of its translations.
 *
 * @param int $post_id Page ID.
 * @return bool
 */
function gsf_hub_is_homepage_editor_page( $post_id ) {
	return in_array( (int) $post_id, gsf_hub_get_front_page_ids(), true );
}

/**
 * Returns Polylang language switcher items for the current page.
 *
 * @return array<int, array<string, mixed>>
 */
function gsf_hub_get_language_switcher_items() {
	if ( ! function_exists( 'pll_the_languages' ) ) {
		return array();
	}

	$items = pll_the_languages(
		array(
			'raw'                    => 1,
			'hide_if_empty'          => 0,
			'hide_if_no_translation' => 0,
			'hide_current'           => 0,
		)
	);

	return is_array( $items ) ? $items : array();
}

/**
 * Ensures homepage custom fields are copied when Polylang duplicates a page translation.
 *
 * @param string[] $keys Existing meta keys.
 * @return string[]
 */
function gsf_hub_polylang_copy_homepage_meta_keys( $keys ) {
	foreach ( array_keys( gsf_hub_get_homepage_field_definitions() ) as $key ) {
		$keys[] = 'gsf_hub_' . $key;
	}

	return array_values( array_unique( $keys ) );
}
add_filter( 'pll_copy_post_metas', 'gsf_hub_polylang_copy_homepage_meta_keys' );

/**
 * Returns homepage field definitions.
 *
 * @return array
 */
function gsf_hub_get_homepage_field_definitions() {
	return array(
		'hero_eyebrow'            => array(
			'label'   => __( 'Hero eyebrow', 'gsf-hub' ),
			'type'    => 'text',
			'default' => __( 'CORE Project Knowledge Platform', 'gsf-hub' ),
			'section' => __( 'Hero', 'gsf-hub' ),
		),
		'hero_title'              => array(
			'label'   => __( 'Hero title', 'gsf-hub' ),
			'type'    => 'textarea',
			'default' => __( 'Practical gender-smart conservation for the Caribbean.', 'gsf-hub' ),
			'section' => __( 'Hero', 'gsf-hub' ),
		),
		'hero_text'               => array(
			'label'   => __( 'Hero description', 'gsf-hub' ),
			'type'    => 'textarea',
			'default' => __( 'Learn, exchange, and track progress across National Conservation Trust Funds, women\'s rights organisations, and conservation partners.', 'gsf-hub' ),
			'section' => __( 'Hero', 'gsf-hub' ),
		),
		'hero_primary_label'      => array(
			'label'   => __( 'Primary button label', 'gsf-hub' ),
			'type'    => 'text',
			'default' => __( 'Start Learning', 'gsf-hub' ),
			'section' => __( 'Hero', 'gsf-hub' ),
		),
		'hero_primary_url'        => array(
			'label'   => __( 'Primary button URL', 'gsf-hub' ),
			'type'    => 'url',
			'default' => gsf_hub_get_page_url( 'learning' ),
			'section' => __( 'Hero', 'gsf-hub' ),
		),
		'hero_secondary_label'    => array(
			'label'   => __( 'Secondary button label', 'gsf-hub' ),
			'type'    => 'text',
			'default' => __( 'Browse Resources', 'gsf-hub' ),
			'section' => __( 'Hero', 'gsf-hub' ),
		),
		'hero_secondary_url'      => array(
			'label'   => __( 'Secondary button URL', 'gsf-hub' ),
			'type'    => 'url',
			'default' => gsf_hub_get_page_url( 'resources' ),
			'section' => __( 'Hero', 'gsf-hub' ),
		),
		'hero_visual_label'       => array(
			'label'   => __( 'Hero image label', 'gsf-hub' ),
			'type'    => 'text',
			'default' => __( 'Caribbean coastal seascapes', 'gsf-hub' ),
			'section' => __( 'Hero', 'gsf-hub' ),
		),
		'hero_visual_caption'     => array(
			'label'   => __( 'Hero image caption', 'gsf-hub' ),
			'type'    => 'textarea',
			'default' => __( 'Marine ecosystems, island resilience, and conservation priorities across the region', 'gsf-hub' ),
			'section' => __( 'Hero', 'gsf-hub' ),
		),
		'hero_stat_1_value'       => array(
			'label'   => __( 'Stat 1 value', 'gsf-hub' ),
			'type'    => 'text',
			'default' => '8',
			'section' => __( 'Hero', 'gsf-hub' ),
		),
		'hero_stat_1_label'       => array(
			'label'   => __( 'Stat 1 label', 'gsf-hub' ),
			'type'    => 'text',
			'default' => __( 'CORE territories', 'gsf-hub' ),
			'section' => __( 'Hero', 'gsf-hub' ),
		),
		'hero_stat_2_value'       => array(
			'label'   => __( 'Stat 2 value', 'gsf-hub' ),
			'type'    => 'text',
			'default' => '4',
			'section' => __( 'Hero', 'gsf-hub' ),
		),
		'hero_stat_2_label'       => array(
			'label'   => __( 'Stat 2 label', 'gsf-hub' ),
			'type'    => 'text',
			'default' => __( 'Languages', 'gsf-hub' ),
			'section' => __( 'Hero', 'gsf-hub' ),
		),
		'hero_stat_3_value'       => array(
			'label'   => __( 'Stat 3 value', 'gsf-hub' ),
			'type'    => 'text',
			'default' => 'AA',
			'section' => __( 'Hero', 'gsf-hub' ),
		),
		'hero_stat_3_label'       => array(
			'label'   => __( 'Stat 3 label', 'gsf-hub' ),
			'type'    => 'text',
			'default' => __( 'Accessible', 'gsf-hub' ),
			'section' => __( 'Hero', 'gsf-hub' ),
		),
		'explore_eyebrow'         => array(
			'label'   => __( 'Explore eyebrow', 'gsf-hub' ),
			'type'    => 'text',
			'default' => __( 'Explore the Hub', 'gsf-hub' ),
			'section' => __( 'Explore section', 'gsf-hub' ),
		),
		'explore_title'           => array(
			'label'   => __( 'Explore heading', 'gsf-hub' ),
			'type'    => 'textarea',
			'default' => __( 'Find what you need in two clicks.', 'gsf-hub' ),
			'section' => __( 'Explore section', 'gsf-hub' ),
		),
		'explore_action_label'    => array(
			'label'   => __( 'Explore action label', 'gsf-hub' ),
			'type'    => 'text',
			'default' => __( 'View all sections', 'gsf-hub' ),
			'section' => __( 'Explore section', 'gsf-hub' ),
		),
		'explore_action_url'      => array(
			'label'   => __( 'Explore action URL', 'gsf-hub' ),
			'type'    => 'url',
			'default' => home_url( '/sitemap/' ),
			'section' => __( 'Explore section', 'gsf-hub' ),
		),
		'quick_link_1_title'      => array(
			'label'   => __( 'Quick link 1 title', 'gsf-hub' ),
			'type'    => 'text',
			'default' => __( 'Learning Modules', 'gsf-hub' ),
			'section' => __( 'Quick links', 'gsf-hub' ),
		),
		'quick_link_1_text'       => array(
			'label'   => __( 'Quick link 1 text', 'gsf-hub' ),
			'type'    => 'textarea',
			'default' => __( 'Self-paced courses, quizzes, progress tracking, and certificates.', 'gsf-hub' ),
			'section' => __( 'Quick links', 'gsf-hub' ),
		),
		'quick_link_1_url'        => array(
			'label'   => __( 'Quick link 1 URL', 'gsf-hub' ),
			'type'    => 'url',
			'default' => gsf_hub_get_page_url( 'learning' ),
			'section' => __( 'Quick links', 'gsf-hub' ),
		),
		'quick_link_2_title'      => array(
			'label'   => __( 'Quick link 2 title', 'gsf-hub' ),
			'type'    => 'text',
			'default' => __( 'Resource Library', 'gsf-hub' ),
			'section' => __( 'Quick links', 'gsf-hub' ),
		),
		'quick_link_2_text'       => array(
			'label'   => __( 'Quick link 2 text', 'gsf-hub' ),
			'type'    => 'textarea',
			'default' => __( 'Tools, templates, guidance documents, videos, and offline packs.', 'gsf-hub' ),
			'section' => __( 'Quick links', 'gsf-hub' ),
		),
		'quick_link_2_url'        => array(
			'label'   => __( 'Quick link 2 URL', 'gsf-hub' ),
			'type'    => 'url',
			'default' => gsf_hub_get_page_url( 'resources' ),
			'section' => __( 'Quick links', 'gsf-hub' ),
		),
		'quick_link_3_title'      => array(
			'label'   => __( 'Quick link 3 title', 'gsf-hub' ),
			'type'    => 'text',
			'default' => __( 'Gender Data Centre', 'gsf-hub' ),
			'section' => __( 'Quick links', 'gsf-hub' ),
		),
		'quick_link_3_text'       => array(
			'label'   => __( 'Quick link 3 text', 'gsf-hub' ),
			'type'    => 'textarea',
			'default' => __( 'Regional dashboards, NCTF uploads, and downloadable reports.', 'gsf-hub' ),
			'section' => __( 'Quick links', 'gsf-hub' ),
		),
		'quick_link_3_url'        => array(
			'label'   => __( 'Quick link 3 URL', 'gsf-hub' ),
			'type'    => 'url',
			'default' => gsf_hub_get_page_url( 'data-centre' ),
			'section' => __( 'Quick links', 'gsf-hub' ),
		),
		'quick_link_4_title'      => array(
			'label'   => __( 'Quick link 4 title', 'gsf-hub' ),
			'type'    => 'text',
			'default' => __( 'Peer Exchange', 'gsf-hub' ),
			'section' => __( 'Quick links', 'gsf-hub' ),
		),
		'quick_link_4_text'       => array(
			'label'   => __( 'Quick link 4 text', 'gsf-hub' ),
			'type'    => 'textarea',
			'default' => __( 'Forum discussions, expert directory, and community learning.', 'gsf-hub' ),
			'section' => __( 'Quick links', 'gsf-hub' ),
		),
		'quick_link_4_url'        => array(
			'label'   => __( 'Quick link 4 URL', 'gsf-hub' ),
			'type'    => 'url',
			'default' => gsf_hub_get_page_url( 'forum' ),
			'section' => __( 'Quick links', 'gsf-hub' ),
		),
		'case_label'              => array(
			'label'   => __( 'Case study image label', 'gsf-hub' ),
			'type'    => 'text',
			'default' => __( 'Biodiversity in focus', 'gsf-hub' ),
			'section' => __( 'Case study', 'gsf-hub' ),
		),
		'case_eyebrow'            => array(
			'label'   => __( 'Case study eyebrow', 'gsf-hub' ),
			'type'    => 'text',
			'default' => __( 'Featured Case Study', 'gsf-hub' ),
			'section' => __( 'Case study', 'gsf-hub' ),
		),
		'case_title'              => array(
			'label'   => __( 'Case study title', 'gsf-hub' ),
			'type'    => 'textarea',
			'default' => __( 'Caribbean stories, practical lessons, real outcomes.', 'gsf-hub' ),
			'section' => __( 'Case study', 'gsf-hub' ),
		),
		'case_text'               => array(
			'label'   => __( 'Case study description', 'gsf-hub' ),
			'type'    => 'textarea',
			'default' => __( 'Highlight Gender Smart Facility grantees, NCTF initiatives, and community-led conservation approaches with clear outcomes and reusable lessons.', 'gsf-hub' ),
			'section' => __( 'Case study', 'gsf-hub' ),
		),
		'case_story_1'            => array(
			'label'   => __( 'Case study bullet 1', 'gsf-hub' ),
			'type'    => 'text',
			'default' => __( 'Women-led mangrove restoration in coastal communities', 'gsf-hub' ),
			'section' => __( 'Case study', 'gsf-hub' ),
		),
		'case_story_2'            => array(
			'label'   => __( 'Case study bullet 2', 'gsf-hub' ),
			'type'    => 'text',
			'default' => __( 'Gender-responsive protected area management', 'gsf-hub' ),
			'section' => __( 'Case study', 'gsf-hub' ),
		),
		'case_story_3'            => array(
			'label'   => __( 'Case study bullet 3', 'gsf-hub' ),
			'type'    => 'text',
			'default' => __( 'Youth and community monitoring for climate resilience', 'gsf-hub' ),
			'section' => __( 'Case study', 'gsf-hub' ),
		),
		'data_eyebrow'            => array(
			'label'   => __( 'Data section eyebrow', 'gsf-hub' ),
			'type'    => 'text',
			'default' => __( 'Gender Monitoring and Data Centre', 'gsf-hub' ),
			'section' => __( 'Data section', 'gsf-hub' ),
		),
		'data_title'              => array(
			'label'   => __( 'Data section title', 'gsf-hub' ),
			'type'    => 'textarea',
			'default' => __( 'Regional progress at a glance.', 'gsf-hub' ),
			'section' => __( 'Data section', 'gsf-hub' ),
		),
		'metric_1_label'          => array(
			'label'   => __( 'Metric 1 label', 'gsf-hub' ),
			'type'    => 'text',
			'default' => __( 'NCTF data submissions', 'gsf-hub' ),
			'section' => __( 'Data section', 'gsf-hub' ),
		),
		'metric_1_value'          => array(
			'label'   => __( 'Metric 1 value', 'gsf-hub' ),
			'type'    => 'number',
			'default' => '72',
			'section' => __( 'Data section', 'gsf-hub' ),
		),
		'metric_2_label'          => array(
			'label'   => __( 'Metric 2 label', 'gsf-hub' ),
			'type'    => 'text',
			'default' => __( 'Training completion', 'gsf-hub' ),
			'section' => __( 'Data section', 'gsf-hub' ),
		),
		'metric_2_value'          => array(
			'label'   => __( 'Metric 2 value', 'gsf-hub' ),
			'type'    => 'number',
			'default' => '58',
			'section' => __( 'Data section', 'gsf-hub' ),
		),
		'metric_3_label'          => array(
			'label'   => __( 'Metric 3 label', 'gsf-hub' ),
			'type'    => 'text',
			'default' => __( 'Resources downloaded', 'gsf-hub' ),
			'section' => __( 'Data section', 'gsf-hub' ),
		),
		'metric_3_value'          => array(
			'label'   => __( 'Metric 3 value', 'gsf-hub' ),
			'type'    => 'number',
			'default' => '84',
			'section' => __( 'Data section', 'gsf-hub' ),
		),
		'metric_4_label'          => array(
			'label'   => __( 'Metric 4 label', 'gsf-hub' ),
			'type'    => 'text',
			'default' => __( 'Webinar engagement', 'gsf-hub' ),
			'section' => __( 'Data section', 'gsf-hub' ),
		),
		'metric_4_value'          => array(
			'label'   => __( 'Metric 4 value', 'gsf-hub' ),
			'type'    => 'number',
			'default' => '66',
			'section' => __( 'Data section', 'gsf-hub' ),
		),
		'data_button_label'       => array(
			'label'   => __( 'Data button label', 'gsf-hub' ),
			'type'    => 'text',
			'default' => __( 'Open Dashboard', 'gsf-hub' ),
			'section' => __( 'Data section', 'gsf-hub' ),
		),
		'data_button_url'         => array(
			'label'   => __( 'Data button URL', 'gsf-hub' ),
			'type'    => 'url',
			'default' => gsf_hub_get_page_url( 'data-centre' ),
			'section' => __( 'Data section', 'gsf-hub' ),
		),
		'resource_eyebrow'        => array(
			'label'   => __( 'Resources eyebrow', 'gsf-hub' ),
			'type'    => 'text',
			'default' => __( 'Resource Library', 'gsf-hub' ),
			'section' => __( 'Resources section', 'gsf-hub' ),
		),
		'resource_title'          => array(
			'label'   => __( 'Resources heading', 'gsf-hub' ),
			'type'    => 'textarea',
			'default' => __( 'Tools ready for field use.', 'gsf-hub' ),
			'section' => __( 'Resources section', 'gsf-hub' ),
		),
		'resource_1_title'        => array(
			'label'   => __( 'Resource card 1 title', 'gsf-hub' ),
			'type'    => 'text',
			'default' => __( 'Gender checklist', 'gsf-hub' ),
			'section' => __( 'Resources section', 'gsf-hub' ),
		),
		'resource_1_meta'         => array(
			'label'   => __( 'Resource card 1 meta', 'gsf-hub' ),
			'type'    => 'text',
			'default' => __( 'PDF | EN/FR/ES/NL | Download tracking', 'gsf-hub' ),
			'section' => __( 'Resources section', 'gsf-hub' ),
		),
		'resource_1_url'          => array(
			'label'   => __( 'Resource card 1 URL', 'gsf-hub' ),
			'type'    => 'url',
			'default' => gsf_hub_get_page_url( 'resources' ),
			'section' => __( 'Resources section', 'gsf-hub' ),
		),
		'resource_2_title'        => array(
			'label'   => __( 'Resource card 2 title', 'gsf-hub' ),
			'type'    => 'text',
			'default' => __( 'Reporting template', 'gsf-hub' ),
			'section' => __( 'Resources section', 'gsf-hub' ),
		),
		'resource_2_meta'         => array(
			'label'   => __( 'Resource card 2 meta', 'gsf-hub' ),
			'type'    => 'text',
			'default' => __( 'DOCX | EN/FR/ES/NL | Editable file', 'gsf-hub' ),
			'section' => __( 'Resources section', 'gsf-hub' ),
		),
		'resource_2_url'          => array(
			'label'   => __( 'Resource card 2 URL', 'gsf-hub' ),
			'type'    => 'url',
			'default' => gsf_hub_get_page_url( 'resources' ),
			'section' => __( 'Resources section', 'gsf-hub' ),
		),
		'resource_3_title'        => array(
			'label'   => __( 'Resource card 3 title', 'gsf-hub' ),
			'type'    => 'text',
			'default' => __( 'Offline toolkit', 'gsf-hub' ),
			'section' => __( 'Resources section', 'gsf-hub' ),
		),
		'resource_3_meta'         => array(
			'label'   => __( 'Resource card 3 meta', 'gsf-hub' ),
			'type'    => 'text',
			'default' => __( 'ZIP | Field-ready pack | Mobile friendly', 'gsf-hub' ),
			'section' => __( 'Resources section', 'gsf-hub' ),
		),
		'resource_3_url'          => array(
			'label'   => __( 'Resource card 3 URL', 'gsf-hub' ),
			'type'    => 'url',
			'default' => gsf_hub_get_page_url( 'resources' ),
			'section' => __( 'Resources section', 'gsf-hub' ),
		),
		'resource_button_label'   => array(
			'label'   => __( 'Resource card button label', 'gsf-hub' ),
			'type'    => 'text',
			'default' => __( 'Download', 'gsf-hub' ),
			'section' => __( 'Resources section', 'gsf-hub' ),
		),
		'event_eyebrow'           => array(
			'label'   => __( 'Webinar eyebrow', 'gsf-hub' ),
			'type'    => 'text',
			'default' => __( 'Upcoming Webinar', 'gsf-hub' ),
			'section' => __( 'Webinar card', 'gsf-hub' ),
		),
		'event_title'             => array(
			'label'   => __( 'Webinar title', 'gsf-hub' ),
			'type'    => 'textarea',
			'default' => __( 'Integrating gender into marine conservation', 'gsf-hub' ),
			'section' => __( 'Webinar card', 'gsf-hub' ),
		),
		'event_text'              => array(
			'label'   => __( 'Webinar description', 'gsf-hub' ),
			'type'    => 'textarea',
			'default' => __( 'Registration, reminders, and recording archive integrated with the platform.', 'gsf-hub' ),
			'section' => __( 'Webinar card', 'gsf-hub' ),
		),
		'event_button_label'      => array(
			'label'   => __( 'Webinar button label', 'gsf-hub' ),
			'type'    => 'text',
			'default' => __( 'Register', 'gsf-hub' ),
			'section' => __( 'Webinar card', 'gsf-hub' ),
		),
		'event_button_url'        => array(
			'label'   => __( 'Webinar button URL', 'gsf-hub' ),
			'type'    => 'url',
			'default' => gsf_hub_get_page_url( 'events' ),
			'section' => __( 'Webinar card', 'gsf-hub' ),
		),
		'hero_background_image'   => array(
			'label'   => __( 'Hero background image URL', 'gsf-hub' ),
			'type'    => 'image',
			'default' => gsf_hub_asset_uri( 'assets/images/hero-beach.jpg' ),
			'section' => __( 'Images', 'gsf-hub' ),
		),
		'hero_card_image'         => array(
			'label'   => __( 'Hero card image URL', 'gsf-hub' ),
			'type'    => 'image',
			'default' => gsf_hub_asset_uri( 'assets/images/hero-reef.jpg' ),
			'section' => __( 'Images', 'gsf-hub' ),
		),
		'case_image'              => array(
			'label'   => __( 'Case study image URL', 'gsf-hub' ),
			'type'    => 'image',
			'default' => gsf_hub_asset_uri( 'assets/images/featured-lizard.jpg' ),
			'section' => __( 'Images', 'gsf-hub' ),
		),
		'resource_background'     => array(
			'label'   => __( 'Resource section background image URL', 'gsf-hub' ),
			'type'    => 'image',
			'default' => gsf_hub_asset_uri( 'assets/images/resource-flowers.jpg' ),
			'section' => __( 'Images', 'gsf-hub' ),
		),
	);
}

/**
 * Returns default homepage field values.
 *
 * @return array
 */
function gsf_hub_get_homepage_defaults() {
	$definitions = gsf_hub_get_homepage_field_definitions();
	$defaults    = array();

	foreach ( $definitions as $key => $field ) {
		$defaults[ $key ] = $field['default'];
	}

	return $defaults;
}

/**
 * Returns saved homepage values merged with defaults.
 *
 * @param int $post_id Page ID.
 * @return array
 */
function gsf_hub_get_homepage_content( $post_id ) {
	$defaults = gsf_hub_get_homepage_defaults();

	if ( ! $post_id ) {
		return $defaults;
	}

	$values = array();

	foreach ( gsf_hub_get_homepage_field_definitions() as $key => $field ) {
		$stored = get_post_meta( $post_id, 'gsf_hub_' . $key, true );
		$values[ $key ] = '' !== $stored ? $stored : $field['default'];
	}

	return $values;
}

/**
 * Seeds a static homepage once for this theme.
 */
function gsf_hub_maybe_seed_front_page() {
	if ( get_option( 'gsf_hub_front_page_seeded' ) ) {
		return;
	}

	$current_front_page = (int) get_option( 'page_on_front' );
	$show_on_front      = get_option( 'show_on_front' );

	if ( $current_front_page && 'page' === $show_on_front ) {
		update_option( 'gsf_hub_front_page_seeded', 1 );
		return;
	}

	$home_page = get_page_by_path( 'home' );

	if ( ! $home_page ) {
		$home_page_id = wp_insert_post(
			array(
				'post_title'   => __( 'Home', 'gsf-hub' ),
				'post_name'    => 'home',
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_content' => '',
			),
			true
		);

		if ( is_wp_error( $home_page_id ) ) {
			return;
		}

		$home_page = get_post( $home_page_id );
	}

	if ( ! $home_page instanceof WP_Post ) {
		return;
	}

	update_option( 'show_on_front', 'page' );
	update_option( 'page_on_front', (int) $home_page->ID );
	update_option( 'gsf_hub_front_page_seeded', 1 );
}
add_action( 'after_switch_theme', 'gsf_hub_maybe_seed_front_page' );

/**
 * Ensures the current site gets a static home page after theme updates.
 */
function gsf_hub_admin_seed_front_page() {
	if ( current_user_can( 'manage_options' ) ) {
		gsf_hub_maybe_seed_front_page();
	}
}
add_action( 'admin_init', 'gsf_hub_admin_seed_front_page' );

/**
 * Adds the homepage content meta box.
 */
function gsf_hub_add_homepage_meta_box() {
	if ( ! gsf_hub_get_front_page_ids() ) {
		return;
	}

	add_meta_box(
		'gsf-hub-homepage-content',
		__( 'Homepage Content', 'gsf-hub' ),
		'gsf_hub_render_homepage_meta_box',
		'page',
		'normal',
		'high'
	);
}
add_action( 'add_meta_boxes_page', 'gsf_hub_add_homepage_meta_box' );

/**
 * Renders the homepage content meta box.
 *
 * @param WP_Post $post Current post object.
 */
function gsf_hub_render_homepage_meta_box( $post ) {
	if ( ! gsf_hub_is_homepage_editor_page( $post->ID ) ) {
		echo '<p>' . esc_html__( 'This editor panel is only used on the page assigned as the homepage and its translations.', 'gsf-hub' ) . '</p>';
		return;
	}

	wp_nonce_field( 'gsf_hub_save_homepage_meta', 'gsf_hub_homepage_nonce' );

	$values       = gsf_hub_get_homepage_content( $post->ID );
	$definitions  = gsf_hub_get_homepage_field_definitions();
	$current_group = '';
	$language_code = function_exists( 'pll_get_post_language' ) ? pll_get_post_language( $post->ID, 'slug' ) : '';

	if ( is_string( $language_code ) && $language_code ) {
		printf(
			'<p>%s</p>',
			esc_html(
				sprintf(
					/* translators: %s: language code. */
					__( 'Update the homepage copy, links, stats, and image URLs for %s here. Save the page when finished.', 'gsf-hub' ),
					strtoupper( $language_code )
				)
			)
		);
	} else {
		echo '<p>' . esc_html__( 'Update the homepage copy, links, stats, and image URLs here. Save the page when finished.', 'gsf-hub' ) . '</p>';
	}
	echo '<div class="gsf-hub-fields">';

	foreach ( $definitions as $key => $field ) {
		if ( $current_group !== $field['section'] ) {
			if ( $current_group ) {
				echo '</div>';
			}

			$current_group = $field['section'];
			echo '<div class="gsf-hub-fields__section">';
			echo '<h2 class="gsf-hub-fields__heading">' . esc_html( $current_group ) . '</h2>';
		}

		$meta_key = 'gsf_hub_' . $key;
		$value    = $values[ $key ];

		echo '<div class="gsf-hub-fields__row">';
		echo '<label class="gsf-hub-fields__label" for="' . esc_attr( $meta_key ) . '">' . esc_html( $field['label'] ) . '</label>';

		if ( 'textarea' === $field['type'] ) {
			echo '<textarea class="widefat" rows="3" id="' . esc_attr( $meta_key ) . '" name="' . esc_attr( $meta_key ) . '">' . esc_textarea( $value ) . '</textarea>';
		} elseif ( 'image' === $field['type'] ) {
			echo '<div class="gsf-hub-image-field">';
			echo '<input class="widefat gsf-hub-image-input" type="url" id="' . esc_attr( $meta_key ) . '" name="' . esc_attr( $meta_key ) . '" value="' . esc_attr( $value ) . '">';
			echo '<button type="button" class="button gsf-hub-image-button" data-target="' . esc_attr( $meta_key ) . '">' . esc_html__( 'Select image', 'gsf-hub' ) . '</button>';
			echo '</div>';
		} else {
			echo '<input class="widefat" type="' . esc_attr( $field['type'] ) . '" id="' . esc_attr( $meta_key ) . '" name="' . esc_attr( $meta_key ) . '" value="' . esc_attr( $value ) . '">';
		}

		echo '</div>';
	}

	if ( $current_group ) {
		echo '</div>';
	}

	echo '</div>';
}

/**
 * Saves homepage meta box values.
 *
 * @param int $post_id Current post ID.
 */
function gsf_hub_save_homepage_meta_box( $post_id ) {
	if ( ! isset( $_POST['gsf_hub_homepage_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['gsf_hub_homepage_nonce'] ) ), 'gsf_hub_save_homepage_meta' ) ) {
		return;
	}

	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}

	if ( 'page' !== get_post_type( $post_id ) || ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	foreach ( gsf_hub_get_homepage_field_definitions() as $key => $field ) {
		$meta_key = 'gsf_hub_' . $key;

		if ( ! isset( $_POST[ $meta_key ] ) ) {
			continue;
		}

		$raw_value = wp_unslash( $_POST[ $meta_key ] );

		if ( in_array( $field['type'], array( 'url', 'image' ), true ) ) {
			$value = esc_url_raw( $raw_value );
		} elseif ( 'textarea' === $field['type'] ) {
			$value = sanitize_textarea_field( $raw_value );
		} elseif ( 'number' === $field['type'] ) {
			$value = preg_replace( '/[^0-9.]/', '', (string) $raw_value );
		} else {
			$value = sanitize_text_field( $raw_value );
		}

		update_post_meta( $post_id, $meta_key, $value );
	}
}
add_action( 'save_post_page', 'gsf_hub_save_homepage_meta_box' );

/**
 * Enqueues admin assets for the homepage editor.
 *
 * @param string $hook_suffix Current admin hook.
 */
function gsf_hub_enqueue_admin_assets( $hook_suffix ) {
	if ( ! in_array( $hook_suffix, array( 'post.php', 'post-new.php' ), true ) ) {
		return;
	}

	$screen = get_current_screen();

	if ( ! $screen || 'page' !== $screen->post_type ) {
		return;
	}

	wp_enqueue_media();
	wp_enqueue_style(
		'gsf-hub-admin',
		get_template_directory_uri() . '/assets/css/admin-homepage.css',
		array(),
		filemtime( get_template_directory() . '/assets/css/admin-homepage.css' )
	);
	wp_enqueue_script(
		'gsf-hub-admin',
		get_template_directory_uri() . '/assets/js/admin-homepage.js',
		array( 'jquery' ),
		filemtime( get_template_directory() . '/assets/js/admin-homepage.js' ),
		true
	);
}
add_action( 'admin_enqueue_scripts', 'gsf_hub_enqueue_admin_assets' );

/**
 * Returns a theme asset URI.
 *
 * @param string $relative_path Relative path inside the theme.
 * @return string
 */
function gsf_hub_asset_uri( $relative_path ) {
	return trailingslashit( get_template_directory_uri() ) . ltrim( $relative_path, '/' );
}

/**
 * Returns a page URL from a preferred slug, even before the page exists.
 *
 * @param string $slug Page slug.
 * @return string
 */
function gsf_hub_get_page_url( $slug ) {
	$page = get_page_by_path( trim( $slug, '/' ) );

	if ( $page instanceof WP_Post && function_exists( 'pll_current_language' ) && function_exists( 'pll_get_post' ) ) {
		$current_language = pll_current_language( 'slug' );
		$translated_id    = $current_language ? pll_get_post( $page->ID, $current_language ) : 0;

		if ( $translated_id ) {
			$translated_page = get_post( $translated_id );

			if ( $translated_page instanceof WP_Post ) {
				$page = $translated_page;
			}
		}
	}

	if ( $page instanceof WP_Post ) {
		return get_permalink( $page );
	}

	return home_url( '/' . trim( $slug, '/' ) . '/' );
}

/**
 * Returns whether a page behaves as the Ultimate Member account hub.
 *
 * @param WP_Post|int|null $post Optional post object or ID.
 * @return bool
 */
function gsf_hub_is_account_hub_page( $post = null ) {
	$post = get_post( $post );

	if ( ! $post instanceof WP_Post || 'page' !== $post->post_type ) {
		return false;
	}

	if ( 'account' === $post->post_name ) {
		return true;
	}

	return has_shortcode( (string) $post->post_content, 'ultimatemember_account' );
}

/**
 * Returns the public member account URL.
 *
 * @return string
 */
function gsf_hub_get_member_account_url() {
	if ( function_exists( 'gsf_hub_expert_workflow_get_um_page_url' ) ) {
		return gsf_hub_expert_workflow_get_um_page_url( 'core_account', '/account/' );
	}

	return gsf_hub_get_page_url( 'account' );
}

/**
 * Returns the public member login URL.
 *
 * @param string $redirect_to Optional redirect target.
 * @return string
 */
function gsf_hub_get_member_login_url( $redirect_to = '' ) {
	if ( function_exists( 'gsf_hub_expert_workflow_get_login_url' ) ) {
		return gsf_hub_expert_workflow_get_login_url( $redirect_to );
	}

	$page_url = gsf_hub_get_page_url( 'login' );
	return $redirect_to ? add_query_arg( 'redirect_to', $redirect_to, $page_url ) : $page_url;
}

/**
 * Returns the public member registration URL.
 *
 * @param string $redirect_to Optional redirect target.
 * @return string
 */
function gsf_hub_get_member_register_url( $redirect_to = '' ) {
	if ( function_exists( 'gsf_hub_expert_workflow_get_register_url' ) ) {
		return gsf_hub_expert_workflow_get_register_url( $redirect_to );
	}

	$page_url = gsf_hub_get_page_url( 'register' );
	return $redirect_to ? add_query_arg( 'redirect_to', $redirect_to, $page_url ) : $page_url;
}

/**
 * Returns account hub action cards for logged-in members.
 *
 * @return array<int, array<string, string>>
 */
function gsf_hub_get_account_hub_actions() {
	$portal_url        = function_exists( 'gsf_hub_expert_workflow_get_portal_page_url' ) ? gsf_hub_expert_workflow_get_portal_page_url() : home_url( '/expert-portal/' );
	$directory_url     = gsf_hub_get_page_url( 'directory' );
	$training_url      = gsf_hub_get_page_url( 'learning' );
	$expert_label      = __( 'Apply for Roster', 'gsf-hub' );
	$expert_text       = __( 'Start your application to join the public expert roster and share your work across the region.', 'gsf-hub' );
	$expert_title      = __( 'Expert Roster', 'gsf-hub' );
	$expert_button_css = 'button button--primary';

	if ( is_user_logged_in() && function_exists( 'gsf_hub_expert_workflow_get_application_status' ) ) {
		$status = gsf_hub_expert_workflow_get_application_status( get_current_user_id() );

		if ( 'approved' === $status ) {
			$expert_label = __( 'Manage Expert Profile', 'gsf-hub' );
			$expert_text  = __( 'Your roster profile is live. Open the portal to keep the profile current and review its public status.', 'gsf-hub' );
		} elseif ( 'pending' === $status ) {
			$expert_label = __( 'Open Expert Portal', 'gsf-hub' );
			$expert_text  = __( 'Your roster application is under review. Use the portal to check status and continue refining your draft profile.', 'gsf-hub' );
		} elseif ( 'changes_requested' === $status ) {
			$expert_label = __( 'Update Application', 'gsf-hub' );
			$expert_text  = __( 'A reviewer requested changes. Open the portal, review the notes, and resubmit your updated profile.', 'gsf-hub' );
		} elseif ( 'rejected' === $status ) {
			$expert_label = __( 'Review Application', 'gsf-hub' );
			$expert_text  = __( 'Your application needs revision before it can move forward. Open the portal to review the feedback.', 'gsf-hub' );
		}
	}

	return array(
		array(
			'icon'        => 'users',
			'title'       => $expert_title,
			'text'        => $expert_text,
			'url'         => $portal_url,
			'label'       => $expert_label,
			'button_css'  => $expert_button_css,
		),
		array(
			'icon'        => 'book',
			'title'       => __( 'Learn', 'gsf-hub' ),
			'text'        => __( 'Open your learning dashboard to access courses, track progress, and complete quizzes or certificates.', 'gsf-hub' ),
			'url'         => $training_url,
			'label'       => __( 'Open Learn Dashboard', 'gsf-hub' ),
			'button_css'  => 'button button--secondary',
		),
		array(
			'icon'        => 'search',
			'title'       => __( 'Browse Expert Directory', 'gsf-hub' ),
			'text'        => __( 'Explore the public roster to discover practitioners, researchers, and partner organizations.', 'gsf-hub' ),
			'url'         => $directory_url,
			'label'       => __( 'View Expert Directory', 'gsf-hub' ),
			'button_css'  => 'button button--ghost',
		),
	);
}

/**
 * Returns whether a page behaves as the forum landing page.
 *
 * @param WP_Post|int|null $post Optional post object or ID.
 * @return bool
 */
function gsf_hub_is_forum_hub_page( $post = null ) {
	$post = get_post( $post );

	if ( ! $post instanceof WP_Post || 'page' !== $post->post_type ) {
		return false;
	}

	if ( 'template-forum.php' === get_post_meta( $post->ID, '_wp_page_template', true ) ) {
		return true;
	}

	return has_shortcode( (string) $post->post_content, 'bbp-forum-index' );
}

/**
 * Returns high-level bbPress forum statistics for the landing page.
 *
 * @return array{forums:int,topics:int,replies:int}
 */
function gsf_hub_get_forum_stats() {
	$stats = array(
		'forums'  => 0,
		'topics'  => 0,
		'replies' => 0,
	);

	if ( function_exists( 'bbp_get_forum_post_type' ) ) {
		$counts           = wp_count_posts( bbp_get_forum_post_type() );
		$stats['forums']  = isset( $counts->publish ) ? (int) $counts->publish : 0;
	}

	if ( function_exists( 'bbp_get_topic_post_type' ) ) {
		$counts           = wp_count_posts( bbp_get_topic_post_type() );
		$stats['topics']  = isset( $counts->publish ) ? (int) $counts->publish : 0;
	}

	if ( function_exists( 'bbp_get_reply_post_type' ) ) {
		$counts            = wp_count_posts( bbp_get_reply_post_type() );
		$stats['replies']  = isset( $counts->publish ) ? (int) $counts->publish : 0;
	}

	return $stats;
}

/**
 * Renders the account hub shortcut section for logged-in members.
 *
 * @return string
 */
function gsf_hub_render_account_hub_shortcuts() {
	if ( ! is_user_logged_in() ) {
		return '';
	}

	$actions = gsf_hub_get_account_hub_actions();

	if ( empty( $actions ) ) {
		return '';
	}

	ob_start();
	?>
	<section class="account-hub">
		<div class="account-hub__header">
			<p class="section-heading__eyebrow"><?php esc_html_e( 'Quick Access', 'gsf-hub' ); ?></p>
			<h2><?php esc_html_e( 'Choose Your Next Step', 'gsf-hub' ); ?></h2>
			<p><?php esc_html_e( 'Use your account as a launch point for roster participation, learning, and networking across the platform.', 'gsf-hub' ); ?></p>
		</div>
		<div class="account-hub__grid">
			<?php foreach ( $actions as $action ) : ?>
				<article class="quick-card account-action-card">
					<span class="quick-card__icon"><?php echo gsf_hub_get_icon( $action['icon'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
					<h3><?php echo esc_html( $action['title'] ); ?></h3>
					<p><?php echo esc_html( $action['text'] ); ?></p>
					<a class="<?php echo esc_attr( $action['button_css'] ); ?>" href="<?php echo esc_url( $action['url'] ); ?>"><?php echo esc_html( $action['label'] ); ?></a>
				</article>
			<?php endforeach; ?>
		</div>
	</section>
	<?php

	return (string) ob_get_clean();
}

/**
 * Outputs a simple fallback menu when no WordPress menu is assigned.
 *
 * @param string $location Menu location.
 * @param string $class    Menu class.
 */
function gsf_hub_render_menu_fallback( $location, $class ) {
	$menus = array(
		'primary' => array(
			array(
				'label' => __( 'Learn', 'gsf-hub' ),
				'slug'  => 'learning',
			),
			array(
				'label' => __( 'Resources', 'gsf-hub' ),
				'slug'  => 'resources',
			),
			array(
				'label' => __( 'Case Studies', 'gsf-hub' ),
				'slug'  => 'case-studies',
			),
			array(
				'label' => __( 'Data Centre', 'gsf-hub' ),
				'slug'  => 'data-centre',
			),
			array(
				'label' => __( 'Directory', 'gsf-hub' ),
				'slug'  => 'directory',
			),
			array(
				'label' => __( 'Forum', 'gsf-hub' ),
				'slug'  => 'forum',
			),
		),
		'footer'  => array(
			array(
				'label' => __( 'Learning', 'gsf-hub' ),
				'slug'  => 'learning',
			),
			array(
				'label' => __( 'Resources', 'gsf-hub' ),
				'slug'  => 'resources',
			),
			array(
				'label' => __( 'Data Centre', 'gsf-hub' ),
				'slug'  => 'data-centre',
			),
			array(
				'label' => __( 'Forum', 'gsf-hub' ),
				'slug'  => 'forum',
			),
		),
	);

	if ( empty( $menus[ $location ] ) ) {
		return;
	}

	echo '<ul class="' . esc_attr( $class ) . '">';

	foreach ( $menus[ $location ] as $item ) {
		echo '<li><a href="' . esc_url( gsf_hub_get_page_url( $item['slug'] ) ) . '">' . esc_html( $item['label'] ) . '</a></li>';
	}

	echo '</ul>';
}

/**
 * Returns inline SVG icons used throughout the theme.
 *
 * @param string $name Icon key.
 * @return string
 */
function gsf_hub_get_icon( $name ) {
	$icons = array(
		'award'         => '<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="8" r="5"></circle><path d="M8.5 13.5 7 21l5-2.6L17 21l-1.5-7.5"></path></svg>',
		'book'          => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 5.5A2.5 2.5 0 0 1 6.5 3H20v15.5A2.5 2.5 0 0 0 17.5 16H6.5A2.5 2.5 0 0 0 4 18.5Z"></path><path d="M8 7h8"></path><path d="M8 11h8"></path><path d="M8 15h5"></path></svg>',
		'calendar'      => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M7 2v4"></path><path d="M17 2v4"></path><rect x="3" y="4.5" width="18" height="16.5" rx="2"></rect><path d="M3 9.5h18"></path><path d="M8 13h3"></path><path d="M13 13h3"></path><path d="M8 17h3"></path></svg>',
		'chevron-right' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="m9 6 6 6-6 6"></path></svg>',
		'download'      => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 4v10"></path><path d="m7 10 5 5 5-5"></path><path d="M4 20h16"></path></svg>',
		'globe'         => '<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"></circle><path d="M3 12h18"></path><path d="M12 3a15 15 0 0 1 0 18"></path><path d="M12 3a15 15 0 0 0 0 18"></path></svg>',
		'menu'          => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 7h16"></path><path d="M4 12h16"></path><path d="M4 17h16"></path></svg>',
		'message'       => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 18.5A2.5 2.5 0 0 1 2.5 16V6A2.5 2.5 0 0 1 5 3.5h14A2.5 2.5 0 0 1 21.5 6v10a2.5 2.5 0 0 1-2.5 2.5H9l-4 2v-2Z"></path><path d="M7 9h10"></path><path d="M7 13h6"></path></svg>',
		'search'        => '<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7"></circle><path d="m20 20-3.5-3.5"></path></svg>',
		'users'         => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M16 20v-1a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v1"></path><circle cx="10" cy="8" r="4"></circle><path d="M20 20v-1a4 4 0 0 0-3-3.87"></path><path d="M15 4.13a4 4 0 0 1 0 7.75"></path></svg>',
		'chart'         => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 20h16"></path><path d="M7 16V9"></path><path d="M12 16V5"></path><path d="M17 16v-4"></path></svg>',
	);

	return $icons[ $name ] ?? '';
}
