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
	$instant_filters_path = get_template_directory() . '/assets/js/instant-filters.js';

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

	if ( file_exists( $instant_filters_path ) ) {
		wp_enqueue_script(
			'gsf-hub-instant-filters',
			get_template_directory_uri() . '/assets/js/instant-filters.js',
			array(),
			filemtime( $instant_filters_path ) ?: $theme->get( 'Version' ),
			true
		);

		wp_localize_script(
			'gsf-hub-instant-filters',
			'gsfHubInstantFilters',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'gsf_hub_instant_filters' ),
				'action'  => 'gsf_hub_instant_filter',
			)
		);
	}
}
add_action( 'wp_enqueue_scripts', 'gsf_hub_enqueue_assets' );

/**
 * Enables public hub records as translatable post types in Polylang.
 *
 * @param string[] $post_types  Existing post types.
 * @param bool     $is_settings Whether Polylang is loading settings UI context.
 * @return string[]
 */
function gsf_hub_polylang_post_types( $post_types, $is_settings ) {
	$hub_post_types = array(
		'expert_directory',
		'gsf_resource',
		'gsf_case_study',
		'gsf_project',
		'gsf_indicator',
		'gsf_ind_result',
		'gsf_ecoequity',
		'gsf_data_story',
		'forum',
		'topic',
		'reply',
	);

	foreach ( $hub_post_types as $post_type ) {
		$post_types[] = $post_type;
	}

	return array_values( array_unique( $post_types ) );
}
add_filter( 'pll_get_post_types', 'gsf_hub_polylang_post_types', 10, 2 );

/**
 * Returns the active Polylang language slug for frontend content queries.
 *
 * @return string
 */
function gsf_hub_get_current_language_slug() {
	if ( function_exists( 'pll_current_language' ) ) {
		$current_language = pll_current_language( 'slug' );

		if ( is_string( $current_language ) && '' !== $current_language ) {
			return $current_language;
		}
	}

	return '';
}

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
 * Returns a useful excerpt for search result cards.
 *
 * @param int $post_id Post ID.
 * @return string
 */
function gsf_hub_get_search_result_excerpt( $post_id ) {
	$post_type = get_post_type( $post_id );

	if ( 'gsf_project' === $post_type ) {
		$parts = array_filter(
			array(
				get_post_meta( $post_id, 'country', true ),
				get_post_meta( $post_id, 'project_type', true ),
				get_post_meta( $post_id, 'project_status', true ),
				get_post_meta( $post_id, 'implementing_party', true ),
				wp_trim_words( wp_strip_all_tags( (string) get_post_meta( $post_id, 'milestone_output', true ) ), 22 ),
			)
		);

		return implode( ' | ', array_map( 'sanitize_text_field', $parts ) );
	}

	if ( 'gsf_indicator' === $post_type ) {
		$parts = array_filter(
			array(
				get_post_meta( $post_id, 'indicator_code', true ),
				get_post_meta( $post_id, 'indicator_level', true ),
				wp_trim_words( wp_strip_all_tags( (string) get_post_meta( $post_id, 'indicator_text', true ) ), 24 ),
			)
		);

		return implode( ' | ', array_map( 'sanitize_text_field', $parts ) );
	}

	if ( 'gsf_ind_result' === $post_type ) {
		$parts = array_filter(
			array(
				get_post_meta( $post_id, 'reporting_year_period', true ),
				get_post_meta( $post_id, 'result_value', true ),
				wp_trim_words( wp_strip_all_tags( (string) get_post_meta( $post_id, 'result_narrative', true ) ), 24 ),
			)
		);

		return implode( ' | ', array_map( 'sanitize_text_field', $parts ) );
	}

	if ( 'gsf_ecoequity' === $post_type ) {
		$parts = array_filter(
			array(
				get_post_meta( $post_id, 'organization_name', true ),
				get_post_meta( $post_id, 'organization_type', true ),
				get_post_meta( $post_id, 'baseline_score', true ) ? __( 'Baseline:', 'gsf-hub' ) . ' ' . get_post_meta( $post_id, 'baseline_score', true ) : '',
				get_post_meta( $post_id, 'endline_score', true ) ? __( 'Endline:', 'gsf-hub' ) . ' ' . get_post_meta( $post_id, 'endline_score', true ) : '',
			)
		);

		return implode( ' | ', array_map( 'sanitize_text_field', $parts ) );
	}

	$excerpt = get_the_excerpt( $post_id );

	return $excerpt ? $excerpt : wp_trim_words( wp_strip_all_tags( get_post_field( 'post_content', $post_id ) ), 28 );
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
	$language    = gsf_hub_get_current_language_slug();

	if ( '' === $search_term ) {
		return array();
	}

	$meta_keys      = array( 'full_name', 'organization', 'short_bio', 'bio', 'profile_bio', 'summary', 'job_title' );
	$meta_sql       = implode( ', ', array_fill( 0, count( $meta_keys ), '%s' ) );
	$search_pattern = '%' . $wpdb->esc_like( $search_term ) . '%';
	$language_join  = '';
	$language_where = '';
	$query_values   = array_merge(
		array( 'expert_directory', $search_pattern, $search_pattern ),
		$meta_keys,
		array( $search_pattern )
	);

	if ( $language && function_exists( 'pll_current_language' ) ) {
		$language_join  = " INNER JOIN {$wpdb->term_relationships} AS pll_rel ON pll_rel.object_id = posts.ID
			INNER JOIN {$wpdb->term_taxonomy} AS pll_tt ON pll_tt.term_taxonomy_id = pll_rel.term_taxonomy_id
			INNER JOIN {$wpdb->terms} AS pll_term ON pll_term.term_id = pll_tt.term_id";
		$language_where = ' AND pll_tt.taxonomy = %s AND pll_term.slug = %s';
		$query_values[] = 'language';
		$query_values[] = $language;
	}

	$sql = $wpdb->prepare(
		"
		SELECT DISTINCT posts.ID
		FROM {$wpdb->posts} AS posts
		LEFT JOIN {$wpdb->postmeta} AS meta
			ON posts.ID = meta.post_id
		{$language_join}
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
			{$language_where}
		ORDER BY posts.post_title ASC
		",
		$query_values
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
		$page_id                = isset( $view_model['page_id'] ) ? (int) $view_model['page_id'] : 0;
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
			$filter_form = gsf_hub_prepare_expert_filter_markup( $filter_form, $page_id );

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
		<form class="directory-filters" method="get" action="<?php echo esc_url( $directory_anchor_url ); ?>" data-gsf-instant-filter="expert-directory" data-gsf-filter-target="expert-directory" data-gsf-page-id="<?php echo esc_attr( (string) $page_id ); ?>">
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
	$current_language       = gsf_hub_get_current_language_slug();
	$available_terms        = taxonomy_exists( 'country' )
		? get_terms(
			array(
				'taxonomy'   => 'country',
				'hide_empty' => false,
				'orderby'    => 'name',
				'order'      => 'ASC',
				'lang'       => $current_language,
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
		'lang'           => $current_language,
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
					'lang'       => $current_language,
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
				'lang'           => $current_language,
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
	<section id="expert-directory" class="expert-directory-listing">
		<div class="directory-toolbar section-block">
			<?php echo gsf_hub_render_expert_directory_filters( $view_model ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		</div>

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
	</section>
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
 * Marks Search & Filter generated expert forms for instant filtering.
 *
 * @param string $markup  Existing rendered form markup.
 * @param int    $page_id Directory page ID.
 * @return string
 */
function gsf_hub_prepare_expert_filter_markup( $markup, $page_id ) {
	if ( ! is_string( $markup ) || '' === trim( $markup ) ) {
		return $markup;
	}

	if ( false === strpos( $markup, 'data-gsf-instant-filter=' ) ) {
		$markup = preg_replace(
			'#<form\b#i',
			'<form data-gsf-instant-filter="expert-directory" data-gsf-filter-target="expert-directory" data-gsf-page-id="' . esc_attr( (string) $page_id ) . '"',
			$markup,
			1
		);
	}

	return $markup;
}

/**
 * Returns refreshed markup for instant filter requests.
 *
 * @return void
 */
function gsf_hub_instant_filter_ajax() {
	check_ajax_referer( 'gsf_hub_instant_filters', 'nonce' );

	$filter_type = isset( $_POST['filter_type'] ) ? sanitize_key( wp_unslash( $_POST['filter_type'] ) ) : '';
	$page_id     = isset( $_POST['page_id'] ) ? absint( $_POST['page_id'] ) : 0;
	$query       = isset( $_POST['query'] ) && is_array( $_POST['query'] ) ? wp_unslash( $_POST['query'] ) : array();

	foreach ( $query as $key => $value ) {
		$clean_key = sanitize_key( (string) $key );
		if ( '' === $clean_key ) {
			continue;
		}

		$_GET[ $clean_key ] = is_array( $value ) ? array_map( 'sanitize_text_field', $value ) : sanitize_text_field( (string) $value );
	}

	if ( 'expert-directory' === $filter_type && function_exists( 'gsf_hub_render_expert_directory_listing' ) ) {
		wp_send_json_success(
			array(
				'html' => gsf_hub_render_expert_directory_listing( $page_id ),
			)
		);
	}

	if ( 'resource-library' === $filter_type && function_exists( 'gsf_hub_sunrise_resource_library_shortcode' ) ) {
		wp_send_json_success(
			array(
				'html' => gsf_hub_sunrise_resource_library_shortcode(),
			)
		);
	}

	if ( 'case-study-library' === $filter_type && function_exists( 'gsf_hub_sunrise_case_study_library_shortcode' ) ) {
		wp_send_json_success(
			array(
				'html' => gsf_hub_sunrise_case_study_library_shortcode(),
			)
		);
	}

	wp_send_json_error(
		array(
			'message' => __( 'Unknown filter request.', 'gsf-hub' ),
		),
		400
	);
}
add_action( 'wp_ajax_gsf_hub_instant_filter', 'gsf_hub_instant_filter_ajax' );
add_action( 'wp_ajax_nopriv_gsf_hub_instant_filter', 'gsf_hub_instant_filter_ajax' );

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
 * Returns display metadata for a Polylang language item.
 *
 * @param array<string, mixed> $item Language item.
 * @return array{code:string,name:string,flag:string}
 */
function gsf_hub_get_language_display_data( $item ) {
	$slug = isset( $item['slug'] ) ? strtolower( (string) $item['slug'] ) : '';
	$name = isset( $item['name'] ) ? (string) $item['name'] : strtoupper( $slug );
	$map  = array(
		'en' => array(
			'name' => __( 'English', 'gsf-hub' ),
			'flag' => '🇬🇧',
		),
		'fr' => array(
			'name' => __( 'Français', 'gsf-hub' ),
			'flag' => '🇫🇷',
		),
		'es' => array(
			'name' => __( 'Español', 'gsf-hub' ),
			'flag' => '🇪🇸',
		),
		'nl' => array(
			'name' => __( 'Nederlands', 'gsf-hub' ),
			'flag' => '🇳🇱',
		),
	);

	return array(
		'code' => strtoupper( $slug ? $slug : substr( $name, 0, 2 ) ),
		'name' => isset( $map[ $slug ] ) ? $map[ $slug ]['name'] : $name,
		'flag' => isset( $map[ $slug ] ) ? $map[ $slug ]['flag'] : '',
	);
}

/**
 * Renders the compact language dropdown used in the header.
 *
 * @param array<int, array<string, mixed>> $language_items Polylang items.
 */
function gsf_hub_render_language_dropdown( $language_items ) {
	if ( empty( $language_items ) ) {
		?>
		<span class="language-dropdown language-dropdown--static">
			<span class="language-dropdown__current">
				<span class="button__icon"><?php echo gsf_hub_get_icon( 'globe' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
				<span><?php esc_html_e( 'EN', 'gsf-hub' ); ?></span>
			</span>
		</span>
		<?php
		return;
	}

	$current_item = null;
	foreach ( $language_items as $item ) {
		if ( ! empty( $item['current_lang'] ) ) {
			$current_item = $item;
			break;
		}
	}

	if ( ! $current_item ) {
		$current_item = reset( $language_items );
	}

	$current_display = is_array( $current_item ) ? gsf_hub_get_language_display_data( $current_item ) : array(
		'code' => 'EN',
		'name' => __( 'English', 'gsf-hub' ),
		'flag' => '🇬🇧',
	);
	?>
	<details class="language-dropdown">
		<summary class="language-dropdown__current" aria-label="<?php esc_attr_e( 'Choose language', 'gsf-hub' ); ?>">
			<?php if ( $current_display['flag'] ) : ?>
				<span class="language-dropdown__flag" aria-hidden="true"><?php echo esc_html( $current_display['flag'] ); ?></span>
			<?php else : ?>
				<span class="button__icon"><?php echo gsf_hub_get_icon( 'globe' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
			<?php endif; ?>
			<span class="language-dropdown__code"><?php echo esc_html( $current_display['code'] ); ?></span>
			<span class="language-dropdown__chevron" aria-hidden="true"></span>
		</summary>
		<ul class="language-dropdown__menu">
			<?php foreach ( $language_items as $item ) : ?>
				<?php
				if ( ! is_array( $item ) ) {
					continue;
				}

				$display    = gsf_hub_get_language_display_data( $item );
				$is_current = ! empty( $item['current_lang'] );
				?>
				<li class="language-dropdown__item">
					<?php if ( $is_current ) : ?>
						<span class="language-dropdown__option is-current" aria-current="page">
					<?php else : ?>
						<a class="language-dropdown__option" href="<?php echo esc_url( (string) $item['url'] ); ?>" hreflang="<?php echo esc_attr( (string) $item['locale'] ); ?>" lang="<?php echo esc_attr( (string) $item['locale'] ); ?>">
					<?php endif; ?>
							<span class="language-dropdown__flag" aria-hidden="true"><?php echo esc_html( $display['flag'] ); ?></span>
							<span class="language-dropdown__name"><?php echo esc_html( $display['name'] ); ?></span>
							<span class="language-dropdown__short"><?php echo esc_html( $display['code'] ); ?></span>
							<?php if ( $is_current ) : ?>
								<span class="language-dropdown__check" aria-hidden="true"></span>
							<?php endif; ?>
					<?php if ( $is_current ) : ?>
						</span>
					<?php else : ?>
						</a>
					<?php endif; ?>
				</li>
			<?php endforeach; ?>
		</ul>
	</details>
	<?php
}

/**
 * Ensures homepage custom fields are copied when Polylang duplicates a page translation.
 *
 * @param string[] $keys Existing meta keys.
 * @return string[]
 */
function gsf_hub_polylang_copy_homepage_meta_keys( $keys ) {
	return $keys;
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
		$value          = '' !== $stored ? $stored : $field['default'];
		$values[ $key ] = gsf_hub_translate_homepage_stock_value( $key, $value );
	}

	return $values;
}

/**
 * Returns safe inline formatting for homepage copy fields.
 *
 * @param string $value Saved homepage field value.
 * @return string
 */
function gsf_hub_kses_homepage_inline( $value ) {
	$allowed_html = array(
		'a'      => array(
			'href'   => true,
			'title'  => true,
			'target' => true,
			'rel'    => true,
		),
		'br'     => array(),
		'strong' => array(),
		'b'      => array(),
		'em'     => array(),
		'i'      => array(),
		'span'   => array(
			'class' => true,
		),
		'mark'   => array(),
		'small'  => array(),
		'sub'    => array(),
		'sup'    => array(),
	);

	return wp_kses( (string) $value, $allowed_html );
}

/**
 * Prints safe inline formatting for homepage copy fields.
 *
 * @param string $value Saved homepage field value.
 */
function gsf_hub_homepage_inline( $value ) {
	echo gsf_hub_kses_homepage_inline( $value ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
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
	$sections      = array();

	foreach ( $definitions as $field ) {
		$section = (string) $field['section'];

		if ( ! isset( $sections[ $section ] ) ) {
			$sections[ $section ] = sanitize_title( $section );
		}
	}

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
	echo '<div class="gsf-hub-homepage-editor">';
	echo '<aside class="gsf-hub-homepage-editor__sidebar" aria-label="' . esc_attr__( 'Homepage sections', 'gsf-hub' ) . '">';
	echo '<p class="gsf-hub-homepage-editor__sidebar-title">' . esc_html__( 'Editing', 'gsf-hub' ) . '</p>';
	echo '<nav class="gsf-hub-section-nav">';

	foreach ( $sections as $section_label => $section_id ) {
		echo '<a class="gsf-hub-section-nav__link" href="#gsf-hub-section-' . esc_attr( $section_id ) . '" data-section="' . esc_attr( $section_id ) . '">' . esc_html( $section_label ) . '</a>';
	}

	echo '</nav>';
	echo '</aside>';
	echo '<div class="gsf-hub-fields">';

	foreach ( $definitions as $key => $field ) {
		if ( $current_group !== $field['section'] ) {
			if ( $current_group ) {
				echo '</div>';
			}

			$current_group = $field['section'];
			echo '<div class="gsf-hub-fields__section" id="gsf-hub-section-' . esc_attr( sanitize_title( $current_group ) ) . '" data-section="' . esc_attr( sanitize_title( $current_group ) ) . '">';
			echo '<h2 class="gsf-hub-fields__heading">' . esc_html( $current_group ) . '</h2>';
		}

		$meta_key = 'gsf_hub_' . $key;
		$value    = $values[ $key ];

		echo '<div class="gsf-hub-fields__row">';
		echo '<label class="gsf-hub-fields__label" for="' . esc_attr( $meta_key ) . '">' . esc_html( $field['label'] ) . '</label>';

		if ( 'textarea' === $field['type'] ) {
			wp_editor(
				$value,
				$meta_key,
				array(
					'textarea_name' => $meta_key,
					'textarea_rows' => 4,
					'media_buttons' => false,
					'teeny'         => true,
					'quicktags'     => array(
						'buttons' => 'strong,em,link,close',
					),
					'tinymce'       => array(
						'toolbar1'      => 'bold,italic,link,unlink,removeformat',
						'toolbar2'      => '',
						'block_formats' => '',
					),
				)
			);
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
			$value = wp_kses_post( $raw_value );
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
	wp_enqueue_editor();
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
 * Returns a language-aware home URL.
 *
 * @return string
 */
function gsf_hub_get_home_url() {
	if ( function_exists( 'pll_home_url' ) ) {
		$home_url = pll_home_url();

		if ( is_string( $home_url ) && '' !== $home_url ) {
			return $home_url;
		}
	}

	return home_url( '/' );
}

/**
 * Returns small UI strings translated for the currently selected Polylang language.
 *
 * This keeps shared chrome multilingual even when compiled theme translation files are
 * not present on the site.
 *
 * @param string $key      String key.
 * @param string $fallback English fallback.
 * @return string
 */
function gsf_hub_ui_text( $key, $fallback = '' ) {
	$language = function_exists( 'gsf_hub_get_current_language_slug' ) ? gsf_hub_get_current_language_slug() : '';
	$strings  = array(
		'fr' => array(
			'footer_description' => 'Une plateforme du CBF mise en oeuvre par le projet CORE, qui soutient la conservation de la biodiversite sensible au genre et la resilience climatique dans les Caraibes.',
			'platform'           => 'Plateforme',
			'quick_access'       => 'Acces rapide',
			'partnership'        => 'Partenariat',
			'mailing_list'       => 'Liste de diffusion',
			'mailing_text'       => 'Recevez les mises a jour du GSF Hub, les opportunites d apprentissage et les annonces de la plateforme.',
			'learning'           => 'Apprentissage',
			'learn'              => 'Apprendre',
			'resources'          => 'Ressources',
			'case_studies'       => 'Etudes de cas',
			'data_centre'        => 'Centre de donnees',
			'directory'          => 'Annuaire',
			'forum'              => 'Forum',
			'resource_library'   => 'Bibliotheque de ressources',
			'events_webinars'    => 'Evenements et webinaires',
			'expert_directory'   => 'Annuaire des experts',
			'my_account'         => 'Mon compte',
			'member_login'       => 'Connexion membre',
			'sign_in'            => 'Connexion',
			'log_out'            => 'Deconnexion',
			'primary_menu'       => 'Menu principal',
			'toggle_navigation'  => 'Afficher ou masquer la navigation',
			'brand_note'         => 'Inclusif. Resilient. Prospere.',
			'search'             => 'Rechercher',
			'search_the_hub'     => 'Rechercher dans le Hub',
			'search_the_site'    => 'Rechercher sur le site',
			'search_placeholder' => 'Rechercher des ressources, cours, etudes de cas, experts et webinaires...',
			'search_hub_placeholder' => 'Rechercher dans le Hub...',
			'canada_alt'         => 'En partenariat avec le Canada',
			'cbf_alt'            => 'Fonds caribeen pour la biodiversite',
		),
		'es' => array(
			'footer_description' => 'Una plataforma de CBF implementada a traves del proyecto CORE, que apoya la conservacion de la biodiversidad con enfoque de genero y la resiliencia climatica en el Caribe.',
			'platform'           => 'Plataforma',
			'quick_access'       => 'Acceso rapido',
			'partnership'        => 'Alianza',
			'mailing_list'       => 'Lista de correo',
			'mailing_text'       => 'Reciba actualizaciones de GSF Hub, oportunidades de aprendizaje y anuncios de la plataforma.',
			'learning'           => 'Aprendizaje',
			'learn'              => 'Aprender',
			'resources'          => 'Recursos',
			'case_studies'       => 'Estudios de caso',
			'data_centre'        => 'Centro de datos',
			'directory'          => 'Directorio',
			'forum'              => 'Foro',
			'resource_library'   => 'Biblioteca de recursos',
			'events_webinars'    => 'Eventos y seminarios web',
			'expert_directory'   => 'Directorio de expertos',
			'my_account'         => 'Mi cuenta',
			'member_login'       => 'Inicio de sesion',
			'sign_in'            => 'Iniciar sesion',
			'log_out'            => 'Cerrar sesion',
			'primary_menu'       => 'Menu principal',
			'toggle_navigation'  => 'Mostrar navegacion',
			'brand_note'         => 'Inclusivo. Resiliente. Prospero.',
			'search'             => 'Buscar',
			'search_the_hub'     => 'Buscar en el Hub',
			'search_the_site'    => 'Buscar en el sitio',
			'search_placeholder' => 'Buscar recursos, cursos, estudios de caso, expertos y seminarios web...',
			'search_hub_placeholder' => 'Buscar en el Hub...',
			'canada_alt'         => 'En alianza con Canada',
			'cbf_alt'            => 'Fondo Caribeno para la Biodiversidad',
		),
		'nl' => array(
			'footer_description' => 'Een CBF-platform uitgevoerd via het CORE-project, ter ondersteuning van genderresponsief biodiversiteitsbehoud en klimaatbestendigheid in het Caribisch gebied.',
			'platform'           => 'Platform',
			'quick_access'       => 'Snelle toegang',
			'partnership'        => 'Partnerschap',
			'mailing_list'       => 'Mailinglijst',
			'mailing_text'       => 'Ontvang GSF Hub-updates, leermogelijkheden en platformaankondigingen.',
			'learning'           => 'Leren',
			'learn'              => 'Leren',
			'resources'          => 'Bronnen',
			'case_studies'       => 'Casestudies',
			'data_centre'        => 'Datacentrum',
			'directory'          => 'Overzicht',
			'forum'              => 'Forum',
			'resource_library'   => 'Bronnenbibliotheek',
			'events_webinars'    => 'Evenementen en webinars',
			'expert_directory'   => 'Expertenoverzicht',
			'my_account'         => 'Mijn account',
			'member_login'       => 'Ledenlogin',
			'sign_in'            => 'Aanmelden',
			'log_out'            => 'Afmelden',
			'primary_menu'       => 'Hoofdmenu',
			'toggle_navigation'  => 'Navigatie wisselen',
			'brand_note'         => 'Inclusief. Veerkrachtig. Bloeiend.',
			'search'             => 'Zoeken',
			'search_the_hub'     => 'Zoeken in de Hub',
			'search_the_site'    => 'Zoeken op de site',
			'search_placeholder' => 'Zoek bronnen, cursussen, casestudies, experts en webinars...',
			'search_hub_placeholder' => 'Zoeken in de Hub...',
			'canada_alt'         => 'In partnerschap met Canada',
			'cbf_alt'            => 'Caribbean Biodiversity Fund',
		),
	);

	if ( isset( $strings[ $language ][ $key ] ) ) {
		return $strings[ $language ][ $key ];
	}

	return $fallback;
}

/**
 * Returns a meaningful eyebrow label for standard content pages.
 *
 * @param WP_Post|null $post Current page post.
 * @return string
 */
function gsf_hub_get_page_eyebrow( $post = null ) {
	$post = $post instanceof WP_Post ? $post : get_post();
	if ( ! $post ) {
		return __( 'GSF Hub', 'gsf-hub' );
	}

	$slug_labels = array(
		'login'                => __( 'Member Access', 'gsf-hub' ),
		'register'             => __( 'Create Account', 'gsf-hub' ),
		'student-registration' => __( 'Create Account', 'gsf-hub' ),
		'account'              => __( 'Member Account', 'gsf-hub' ),
		'dashboard'            => __( 'Learner Dashboard', 'gsf-hub' ),
		'learn'                => __( 'Learning', 'gsf-hub' ),
		'learning'             => __( 'Learning', 'gsf-hub' ),
		'resources'            => __( 'Resource Library', 'gsf-hub' ),
		'case-studies'         => __( 'Case Study Library', 'gsf-hub' ),
		'data-centre'          => __( 'Data Centre', 'gsf-hub' ),
		'directory'            => __( 'Expert Directory', 'gsf-hub' ),
		'forum'                => __( 'Community Forum', 'gsf-hub' ),
	);

	if ( isset( $slug_labels[ $post->post_name ] ) ) {
		return $slug_labels[ $post->post_name ];
	}

	return get_the_title( $post );
}

/**
 * Returns a display title for standard pages when the stored title is too terse.
 *
 * @param WP_Post|null $post Current page post.
 * @return string
 */
function gsf_hub_get_page_display_title( $post = null ) {
	$post = $post instanceof WP_Post ? $post : get_post();
	if ( $post && 'login' === $post->post_name ) {
		return __( 'Sign in', 'gsf-hub' );
	}

	return $post ? get_the_title( $post ) : '';
}

/**
 * Keeps the browser title aligned with frontend authentication wording.
 *
 * @param array $parts Document title parts.
 * @return array
 */
function gsf_hub_document_title_parts( $parts ) {
	if ( is_page( 'login' ) ) {
		$parts['title'] = __( 'Sign in', 'gsf-hub' );
	}

	return $parts;
}
add_filter( 'document_title_parts', 'gsf_hub_document_title_parts' );

/**
 * Adjusts frontend authentication wording without editing Ultimate Member.
 *
 * @param string $translation Translated text.
 * @param string $text        Source text.
 * @param string $domain      Text domain.
 * @return string
 */
function gsf_hub_auth_wording( $translation, $text, $domain ) {
	if ( is_admin() || ! in_array( $domain, array( 'ultimate-member', 'gsf-hub' ), true ) ) {
		return $translation;
	}

	$replacements = array(
		'Login'             => 'Sign in',
		'Keep me signed in' => 'Stay logged in',
	);

	return isset( $replacements[ $text ] ) ? $replacements[ $text ] : $translation;
}
add_filter( 'gettext', 'gsf_hub_auth_wording', 20, 3 );

/**
 * Returns translated homepage copy for stock English values.
 *
 * Saved homepage fields are editable content, so only replace values that still
 * match the stock English defaults. Custom translated copy remains untouched.
 *
 * @param string $key   Homepage field key.
 * @param string $value Current field value.
 * @return string
 */
function gsf_hub_translate_homepage_stock_value( $key, $value ) {
	$language = function_exists( 'gsf_hub_get_current_language_slug' ) ? gsf_hub_get_current_language_slug() : '';

	if ( ! $language || 'en' === $language ) {
		return $value;
	}

	$translations = array(
		'fr' => array(
			'hero_eyebrow'          => 'Plateforme de connaissances du projet CORE',
			'hero_title'            => 'Conservation pratique et sensible au genre pour les Caraibes.',
			'hero_text'             => 'Apprenez, echangez et suivez les progres des Fonds fiduciaires nationaux pour la conservation, des organisations de defense des droits des femmes et des partenaires de conservation.',
			'hero_primary_label'    => 'Commencer a apprendre',
			'hero_secondary_label'  => 'Parcourir les ressources',
			'hero_visual_label'     => 'Paysages cotiers des Caraibes',
			'hero_visual_caption'   => 'Ecosystemes marins, resilience insulaire et priorites de conservation dans toute la region',
			'hero_stat_1_label'     => 'Territoires CORE',
			'hero_stat_2_label'     => 'Langues',
			'hero_stat_3_label'     => 'Accessible',
			'explore_eyebrow'       => 'Explorer le Hub',
			'explore_title'         => 'Trouvez ce dont vous avez besoin en deux clics.',
			'explore_action_label'  => 'Voir toutes les sections',
		),
		'es' => array(
			'hero_eyebrow'          => 'Plataforma de conocimiento del proyecto CORE',
			'hero_title'            => 'Conservacion practica con enfoque de genero para el Caribe.',
			'hero_text'             => 'Aprenda, intercambie y siga el progreso de los Fondos Fiduciarios Nacionales de Conservacion, organizaciones de derechos de las mujeres y socios de conservacion.',
			'hero_primary_label'    => 'Comenzar aprendizaje',
			'hero_secondary_label'  => 'Explorar recursos',
			'hero_visual_label'     => 'Paisajes costeros del Caribe',
			'hero_visual_caption'   => 'Ecosistemas marinos, resiliencia insular y prioridades de conservacion en toda la region',
			'hero_stat_1_label'     => 'Territorios CORE',
			'hero_stat_2_label'     => 'Idiomas',
			'hero_stat_3_label'     => 'Accesible',
			'explore_eyebrow'       => 'Explorar el Hub',
			'explore_title'         => 'Encuentre lo que necesita en dos clics.',
			'explore_action_label'  => 'Ver todas las secciones',
		),
		'nl' => array(
			'hero_eyebrow'          => 'Kennisplatform van het CORE-project',
			'hero_title'            => 'Praktische genderslimme natuurbehoud voor het Caribisch gebied.',
			'hero_text'             => 'Leer, wissel uit en volg vooruitgang bij nationale natuurfondsen, vrouwenrechtenorganisaties en natuurbeschermingspartners.',
			'hero_primary_label'    => 'Begin met leren',
			'hero_secondary_label'  => 'Bronnen bekijken',
			'hero_visual_label'     => 'Caribische kustlandschappen',
			'hero_visual_caption'   => 'Mariene ecosystemen, eilandveerkracht en natuurbehoudsprioriteiten in de regio',
			'hero_stat_1_label'     => 'CORE-gebieden',
			'hero_stat_2_label'     => 'Talen',
			'hero_stat_3_label'     => 'Toegankelijk',
			'explore_eyebrow'       => 'Verken de Hub',
			'explore_title'         => 'Vind wat u nodig hebt in twee klikken.',
			'explore_action_label'  => 'Bekijk alle secties',
		),
	);

	if ( empty( $translations[ $language ][ $key ] ) ) {
		return $value;
	}

	$definitions = gsf_hub_get_homepage_field_definitions();
	$default     = isset( $definitions[ $key ]['default'] ) ? (string) $definitions[ $key ]['default'] : '';

	if ( '' !== $default && trim( wp_strip_all_tags( (string) $value ) ) === trim( wp_strip_all_tags( $default ) ) ) {
		return $translations[ $language ][ $key ];
	}

	return $value;
}

/**
 * Returns the translation key for a standard hub navigation item.
 *
 * @param string $title Menu item title.
 * @param string $url   Menu item URL.
 * @param string $slug  Object slug.
 * @return string
 */
function gsf_hub_get_nav_item_translation_key( $title, $url = '', $slug = '' ) {
	$haystack = strtolower( trim( wp_strip_all_tags( $title ) . ' ' . $url . ' ' . $slug ) );
	$haystack = str_replace( array( '_', '%20' ), '-', $haystack );

	$matches = array(
		'case_studies' => array( 'case-studies', 'case studies', 'estudios-de-caso', 'etudes-de-cas', 'casestudies' ),
		'data_centre'  => array( 'data-centre', 'data centre', 'data-center', 'gender-data-centre', 'centre-de-donnees', 'centro-de-datos', 'datacentrum' ),
		'directory'    => array( 'directory', 'expert-directory', 'annuaire', 'directorio', 'overzicht' ),
		'resources'    => array( 'resources', 'resource-library', 'ressources', 'recursos', 'bronnen' ),
		'forum'        => array( 'forum', 'foro' ),
		'learn'        => array( 'learn', 'learning', 'apprendre', 'aprendizaje', 'aprender', 'leren' ),
	);

	foreach ( $matches as $key => $needles ) {
		foreach ( $needles as $needle ) {
			if ( false !== strpos( $haystack, $needle ) ) {
				return $key;
			}
		}
	}

	return '';
}

/**
 * Translates standard WordPress menu item labels for the active Polylang language.
 *
 * @param string  $title Menu item title.
 * @param WP_Post $item  Menu item object.
 * @param object  $args  Menu render args.
 * @param int     $depth Menu item depth.
 * @return string
 */
function gsf_hub_translate_nav_menu_item_title( $title, $item, $args, $depth ) {
	if ( ! is_object( $args ) || ! in_array( $args->theme_location ?? '', array( 'primary', 'footer' ), true ) ) {
		return $title;
	}

	$slug = '';

	if ( ! empty( $item->object_id ) ) {
		$post = get_post( (int) $item->object_id );

		if ( $post instanceof WP_Post ) {
			$slug = $post->post_name;
		}
	}

	$key = gsf_hub_get_nav_item_translation_key( $title, (string) ( $item->url ?? '' ), $slug );

	if ( ! $key ) {
		return $title;
	}

	return gsf_hub_ui_text( $key, $title );
}
add_filter( 'nav_menu_item_title', 'gsf_hub_translate_nav_menu_item_title', 10, 4 );

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
 * Returns bbPress post type slugs when bbPress is active.
 *
 * @return string[]
 */
function gsf_hub_get_bbpress_post_types() {
	$post_types = array();

	if ( function_exists( 'bbp_get_forum_post_type' ) ) {
		$post_types[] = bbp_get_forum_post_type();
	}

	if ( function_exists( 'bbp_get_topic_post_type' ) ) {
		$post_types[] = bbp_get_topic_post_type();
	}

	if ( function_exists( 'bbp_get_reply_post_type' ) ) {
		$post_types[] = bbp_get_reply_post_type();
	}

	return array_values( array_filter( array_unique( $post_types ) ) );
}

/**
 * Scopes frontend bbPress index queries to the active Polylang language.
 *
 * @param WP_Query $query Query object.
 */
function gsf_hub_scope_bbpress_queries_by_language( $query ) {
	if ( is_admin() || ! $query instanceof WP_Query ) {
		return;
	}

	$current_language = gsf_hub_get_current_language_slug();

	if ( ! $current_language || $query->get( 'lang' ) ) {
		return;
	}

	$query_post_types = $query->get( 'post_type' );

	if ( empty( $query_post_types ) ) {
		return;
	}

	$query_post_types = is_array( $query_post_types ) ? $query_post_types : array( $query_post_types );

	if ( array_intersect( $query_post_types, gsf_hub_get_bbpress_post_types() ) ) {
		$query->set( 'lang', $current_language );
	}
}
add_action( 'pre_get_posts', 'gsf_hub_scope_bbpress_queries_by_language', 20 );

/**
 * Resolves the language a forum child record should inherit.
 *
 * @param int $post_id Post ID.
 * @param string $post_type Post type.
 * @return string
 */
function gsf_hub_get_bbpress_inherited_language( $post_id, $post_type ) {
	if ( ! function_exists( 'pll_get_post_language' ) ) {
		return '';
	}

	$parent_id = 0;

	if ( function_exists( 'bbp_get_topic_post_type' ) && function_exists( 'bbp_get_topic_forum_id' ) && bbp_get_topic_post_type() === $post_type ) {
		$parent_id = (int) bbp_get_topic_forum_id( $post_id );
	} elseif ( function_exists( 'bbp_get_reply_post_type' ) && function_exists( 'bbp_get_reply_topic_id' ) && bbp_get_reply_post_type() === $post_type ) {
		$parent_id = (int) bbp_get_reply_topic_id( $post_id );
	}

	if ( ! $parent_id ) {
		$post = get_post( $post_id );
		$parent_id = $post instanceof WP_Post ? (int) $post->post_parent : 0;
	}

	if ( $parent_id ) {
		$language = pll_get_post_language( $parent_id, 'slug' );

		if ( is_string( $language ) && $language ) {
			return $language;
		}
	}

	return '';
}

/**
 * Assigns Polylang languages to forum records that do not have one yet.
 *
 * @param int     $post_id Post ID.
 * @param WP_Post $post    Saved post object.
 */
function gsf_hub_set_bbpress_language_on_save( $post_id, $post ) {
	if ( wp_is_post_revision( $post_id ) || ! in_array( $post->post_type, gsf_hub_get_bbpress_post_types(), true ) ) {
		return;
	}

	if ( ! function_exists( 'pll_get_post_language' ) || ! function_exists( 'pll_set_post_language' ) || ! function_exists( 'pll_default_language' ) ) {
		return;
	}

	if ( pll_get_post_language( $post_id, 'slug' ) ) {
		return;
	}

	$language = gsf_hub_get_bbpress_inherited_language( $post_id, $post->post_type );

	if ( ! $language ) {
		$language = pll_default_language( 'slug' );
	}

	if ( $language ) {
		pll_set_post_language( $post_id, $language );
	}
}
add_action( 'save_post', 'gsf_hub_set_bbpress_language_on_save', 20, 2 );

/**
 * Backfills default/inherited language assignment for existing forum content.
 */
function gsf_hub_sync_bbpress_languages() {
	if ( ! function_exists( 'pll_get_post_language' ) || ! function_exists( 'pll_set_post_language' ) || ! function_exists( 'pll_default_language' ) ) {
		return;
	}

	$post_types = gsf_hub_get_bbpress_post_types();

	if ( ! $post_types ) {
		return;
	}

	foreach ( $post_types as $post_type ) {
		$record_ids = get_posts(
			array(
				'post_type'      => $post_type,
				'post_status'    => array( 'publish', 'closed', 'private', 'hidden', 'spam', 'trash', 'pending', 'draft' ),
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'orderby'        => 'ID',
				'order'          => 'ASC',
				'lang'           => '',
			)
		);

		foreach ( $record_ids as $record_id ) {
			$post = get_post( $record_id );

			if ( ! $post instanceof WP_Post || pll_get_post_language( $record_id, 'slug' ) ) {
				continue;
			}

			$language = gsf_hub_get_bbpress_inherited_language( $record_id, $post->post_type );

			if ( ! $language ) {
				$language = pll_default_language( 'slug' );
			}

			if ( $language ) {
				pll_set_post_language( $record_id, $language );
			}
		}
	}
}
add_action( 'admin_init', 'gsf_hub_sync_bbpress_languages' );

/**
 * Counts published bbPress records for the active language.
 *
 * @param string $post_type Post type slug.
 * @return int
 */
function gsf_hub_count_bbpress_posts_for_current_language( $post_type ) {
	if ( ! post_type_exists( $post_type ) ) {
		return 0;
	}

	$args = array(
		'post_type'      => $post_type,
		'post_status'    => 'publish',
		'posts_per_page' => -1,
		'fields'         => 'ids',
	);

	$current_language = gsf_hub_get_current_language_slug();

	if ( $current_language ) {
		$args['lang'] = $current_language;
	}

	return count( get_posts( $args ) );
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
		$stats['forums'] = gsf_hub_count_bbpress_posts_for_current_language( bbp_get_forum_post_type() );
	}

	if ( function_exists( 'bbp_get_topic_post_type' ) ) {
		$stats['topics'] = gsf_hub_count_bbpress_posts_for_current_language( bbp_get_topic_post_type() );
	}

	if ( function_exists( 'bbp_get_reply_post_type' ) ) {
		$stats['replies'] = gsf_hub_count_bbpress_posts_for_current_language( bbp_get_reply_post_type() );
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
				'label' => gsf_hub_ui_text( 'learn', __( 'Learn', 'gsf-hub' ) ),
				'slug'  => 'learning',
			),
			array(
				'label' => gsf_hub_ui_text( 'resources', __( 'Resources', 'gsf-hub' ) ),
				'slug'  => 'resources',
			),
			array(
				'label' => gsf_hub_ui_text( 'case_studies', __( 'Case Studies', 'gsf-hub' ) ),
				'slug'  => 'case-studies',
			),
			array(
				'label' => gsf_hub_ui_text( 'data_centre', __( 'Data Centre', 'gsf-hub' ) ),
				'slug'  => 'data-centre',
			),
			array(
				'label' => gsf_hub_ui_text( 'directory', __( 'Directory', 'gsf-hub' ) ),
				'slug'  => 'directory',
			),
			array(
				'label' => gsf_hub_ui_text( 'forum', __( 'Forum', 'gsf-hub' ) ),
				'slug'  => 'forum',
			),
		),
		'footer'  => array(
			array(
				'label' => gsf_hub_ui_text( 'learning', __( 'Learning', 'gsf-hub' ) ),
				'slug'  => 'learning',
			),
			array(
				'label' => gsf_hub_ui_text( 'resources', __( 'Resources', 'gsf-hub' ) ),
				'slug'  => 'resources',
			),
			array(
				'label' => gsf_hub_ui_text( 'data_centre', __( 'Data Centre', 'gsf-hub' ) ),
				'slug'  => 'data-centre',
			),
			array(
				'label' => gsf_hub_ui_text( 'forum', __( 'Forum', 'gsf-hub' ) ),
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
