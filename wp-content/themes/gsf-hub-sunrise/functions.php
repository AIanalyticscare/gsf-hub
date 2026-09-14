<?php
/**
 * Child theme setup for the GSF Hub Sunrise theme.
 *
 * @package GSF_Hub_Sunrise
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Adds block-editor layout supports used by the page mockups.
 */
function gsf_hub_sunrise_setup() {
	add_theme_support( 'align-wide' );
}
add_action( 'after_setup_theme', 'gsf_hub_sunrise_setup', 20 );

/**
 * Returns a child-theme asset URI.
 *
 * @param string $relative_path Relative path inside the child theme.
 * @return string
 */
function gsf_hub_sunrise_asset_uri( $relative_path ) {
	return trailingslashit( get_stylesheet_directory_uri() ) . ltrim( $relative_path, '/' );
}

/**
 * Enqueues the child theme stylesheet after the parent theme assets.
 */
function gsf_hub_sunrise_enqueue_assets() {
	$theme = wp_get_theme();
	$path  = get_stylesheet_directory() . '/assets/css/theme-sunrise.css';

	wp_enqueue_style(
		'gsf-hub-sunrise-theme',
		gsf_hub_sunrise_asset_uri( 'assets/css/theme-sunrise.css' ),
		array( 'gsf-hub-theme' ),
		file_exists( $path ) ? filemtime( $path ) : $theme->get( 'Version' )
	);
}
add_action( 'wp_enqueue_scripts', 'gsf_hub_sunrise_enqueue_assets', 20 );

/**
 * Adds a body class so the child theme can scope overrides cleanly.
 *
 * @param string[] $classes Existing body classes.
 * @return string[]
 */
function gsf_hub_sunrise_body_class( $classes ) {
	$classes[] = 'gsf-hub-sunrise-theme';

	if ( is_page() ) {
		$page = get_queried_object();

		if ( $page instanceof WP_Post && ! empty( $page->post_name ) ) {
			$classes[] = 'gsf-page-' . sanitize_html_class( $page->post_name );
		}
	}

	return $classes;
}
add_filter( 'body_class', 'gsf_hub_sunrise_body_class' );

/**
 * Embeds a self-hosted Articulate web export.
 *
 * Expected package location:
 * wp-content/uploads/articulate-courses/{slug}/index.html
 *
 * @param array<string,string> $atts Shortcode attributes.
 * @return string
 */
function gsf_hub_sunrise_articulate_course_shortcode( $atts ) {
	$atts = shortcode_atts(
		array(
			'slug'   => '',
			'title'  => __( 'Interactive course', 'gsf-hub' ),
			'height' => '760',
		),
		$atts,
		'gsf_articulate_course'
	);

	$slug = sanitize_title( $atts['slug'] );

	if ( empty( $slug ) ) {
		return '';
	}

	$course_path = WP_CONTENT_DIR . '/uploads/articulate-courses/' . $slug . '/index.html';

	if ( ! file_exists( $course_path ) ) {
		return current_user_can( 'edit_pages' )
			? '<p class="gsf-articulate-course__notice">' . esc_html__( 'Course package not found.', 'gsf-hub' ) . '</p>'
			: '';
	}

	$height = absint( $atts['height'] );
	$height = $height > 0 ? $height : 760;
	$url    = content_url( 'uploads/articulate-courses/' . $slug . '/index.html' );

	return sprintf(
		'<div class="gsf-articulate-course"><iframe class="gsf-articulate-course__frame" src="%1$s" title="%2$s" loading="lazy" style="min-height:%3$dpx" allowfullscreen></iframe></div>',
		esc_url( $url ),
		esc_attr( $atts['title'] ),
		$height
	);
}
add_shortcode( 'gsf_articulate_course', 'gsf_hub_sunrise_articulate_course_shortcode' );

/**
 * Adds a GSF-styled registration panel to Zoom event pages.
 *
 * The Zoom plugin only stores a join URL after the API connection succeeds.
 * Until then, keep a visible registration area instead of leaving the page blank.
 */
function gsf_hub_sunrise_render_zoom_registration_panel() {
	if ( ! is_singular( 'zoom-meetings' ) ) {
		return;
	}

	$post_id  = get_the_ID();
	$join_url = get_post_meta( $post_id, '_meeting_zoom_join_url', true );
	$error    = get_post_meta( $post_id, '_meeting_zoom_details', true );
	$message  = '';

	if ( is_object( $error ) && ! empty( $error->message ) ) {
		$message = (string) $error->message;
	}
	?>
	<div class="gsf-zoom-registration">
		<h3><?php esc_html_e( 'Registration', 'gsf-hub' ); ?></h3>
		<?php if ( $join_url ) : ?>
			<p><?php esc_html_e( 'Register or join this virtual event through Zoom.', 'gsf-hub' ); ?></p>
			<a class="button button--primary button--full" href="<?php echo esc_url( $join_url ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Register for event', 'gsf-hub' ); ?></a>
		<?php else : ?>
			<p><?php esc_html_e( 'Registration will be available once the Zoom connection is configured for this event.', 'gsf-hub' ); ?></p>
			<?php if ( current_user_can( 'manage_options' ) && $message ) : ?>
				<p class="gsf-zoom-registration__notice"><?php echo esc_html( $message ); ?></p>
			<?php endif; ?>
		<?php endif; ?>
	</div>
	<?php
}
add_action( 'vczoom_single_content_right', 'gsf_hub_sunrise_render_zoom_registration_panel', 40 );

/**
 * Renders a simple listing of Zoom events and webinars.
 *
 * @return string
 */
function gsf_hub_sunrise_events_webinars_shortcode() {
	$events = new WP_Query(
		array(
			'post_type'           => 'zoom-meetings',
			'post_status'         => 'publish',
			'posts_per_page'      => 12,
			'ignore_sticky_posts' => true,
			'meta_key'            => '_meeting_field_start_date_utc', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			'orderby'             => array(
				'meta_value' => 'ASC',
				'date'       => 'DESC',
			),
		)
	);

	ob_start();
	?>
	<section class="gsf-events-library">
		<?php if ( $events->have_posts() ) : ?>
			<div class="gsf-events-library__grid">
				<?php
				while ( $events->have_posts() ) :
					$events->the_post();

					$meeting_fields = get_post_meta( get_the_ID(), '_meeting_fields', true );
					$meeting_time   = '';

					if ( is_array( $meeting_fields ) && ! empty( $meeting_fields['start_date'] ) ) {
						$meeting_timezone = ! empty( $meeting_fields['timezone'] ) ? $meeting_fields['timezone'] : wp_timezone_string();

						try {
							$meeting_date = new DateTime( $meeting_fields['start_date'], new DateTimeZone( $meeting_timezone ) );
							$meeting_time = $meeting_date->format( 'M j, Y · g:i a T' );
						} catch ( Exception $exception ) {
							$meeting_time = '';
						}
					}

					if ( '' === $meeting_time ) {
						$meeting_start_utc = get_post_meta( get_the_ID(), '_meeting_field_start_date_utc', true );
						$meeting_timestamp = $meeting_start_utc ? strtotime( $meeting_start_utc . ' UTC' ) : false;

						if ( $meeting_timestamp ) {
							$meeting_time = wp_date( 'M j, Y · g:i a T', $meeting_timestamp );
						}
					}
					?>
					<article class="gsf-event-listing-card">
						<?php if ( $meeting_time ) : ?>
							<p class="gsf-event-listing-card__date"><?php echo esc_html( $meeting_time ); ?></p>
						<?php endif; ?>
						<h2><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2>
						<?php if ( has_excerpt() ) : ?>
							<p><?php echo esc_html( get_the_excerpt() ); ?></p>
						<?php endif; ?>
						<a class="button button--secondary" href="<?php the_permalink(); ?>"><?php esc_html_e( 'View event', 'gsf-hub' ); ?></a>
					</article>
				<?php endwhile; ?>
			</div>
			<?php wp_reset_postdata(); ?>
		<?php else : ?>
			<div class="gsf-resource-library__empty">
				<h2><?php esc_html_e( 'No events are currently published', 'gsf-hub' ); ?></h2>
				<p><?php esc_html_e( 'Upcoming events and webinars will appear here once they are added in WP Admin.', 'gsf-hub' ); ?></p>
			</div>
		<?php endif; ?>
	</section>
	<?php
	return ob_get_clean();
}
add_shortcode( 'gsf_events_webinars', 'gsf_hub_sunrise_events_webinars_shortcode' );

/**
 * Returns controlled resource type values.
 *
 * @return string[]
 */
function gsf_hub_sunrise_get_resource_type_values() {
	return array(
		'Toolkits',
		'Templates',
		'Policies and Guidance',
		'Research and Publications',
		'Learning Resources',
		'External Resource Hub',
		'Multimedia Resources',
	);
}

/**
 * Returns controlled country/region values for resources.
 *
 * @return string[]
 */
function gsf_hub_sunrise_get_resource_region_values() {
	return array(
		'Caribbean',
		'Greater Antilles',
		'Lesser Antilles',
		'Leeward Islands',
		'Windward Islands',
		'CARICOM',
		'OECS',
		'Anguilla',
		'Antigua and Barbuda',
		'Aruba',
		'Bahamas',
		'Barbados',
		'Belize',
		'Bermuda',
		'Bonaire',
		'British Virgin Islands',
		'Cayman Islands',
		'Cuba',
		'Curacao',
		'Dominica',
		'Dominican Republic',
		'Grenada',
		'Guadeloupe',
		'Guyana',
		'Haiti',
		'Jamaica',
		'Martinique',
		'Montserrat',
		'Puerto Rico',
		'Saba',
		'Saint Barthelemy',
		'Saint Kitts and Nevis',
		'Saint Lucia',
		'Saint Martin',
		'Saint Vincent and the Grenadines',
		'Sint Eustatius',
		'Sint Maarten',
		'Suriname',
		'Trinidad and Tobago',
		'Turks and Caicos Islands',
		'U.S. Virgin Islands',
		'Other',
	);
}

/**
 * Formats values for Pods custom-simple pick fields.
 *
 * @param string[] $values Field values.
 * @return string
 */
function gsf_hub_sunrise_format_pods_custom_simple_options( $values ) {
	return implode(
		"\n",
		array_map(
			static function ( $value ) {
				return $value . '|' . $value;
			},
			$values
		)
	);
}

/**
 * Returns the active Polylang language slug for child-theme content queries.
 *
 * @return string
 */
function gsf_hub_sunrise_get_current_language_slug() {
	if ( function_exists( 'gsf_hub_get_current_language_slug' ) ) {
		return gsf_hub_get_current_language_slug();
	}

	if ( function_exists( 'pll_current_language' ) ) {
		$current_language = pll_current_language( 'slug' );

		if ( is_string( $current_language ) && '' !== $current_language ) {
			return $current_language;
		}
	}

	return '';
}

/**
 * Assigns the default Polylang language to library records that do not have one.
 *
 * @param int     $post_id Post ID.
 * @param WP_Post $post    Saved post object.
 */
function gsf_hub_sunrise_set_library_record_language_on_save( $post_id, $post ) {
	if ( wp_is_post_revision( $post_id ) || ! in_array( $post->post_type, array( 'gsf_resource', 'gsf_case_study' ), true ) ) {
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
add_action( 'save_post', 'gsf_hub_sunrise_set_library_record_language_on_save', 20, 2 );

/**
 * Backfills default language assignment for existing resource and case study records.
 */
function gsf_hub_sunrise_sync_library_record_languages() {
	if ( ! function_exists( 'pll_get_post_language' ) || ! function_exists( 'pll_set_post_language' ) || ! function_exists( 'pll_default_language' ) ) {
		return;
	}

	$default_language = pll_default_language( 'slug' );

	if ( ! $default_language ) {
		return;
	}

	$record_ids = get_posts(
		array(
			'post_type'      => array( 'gsf_resource', 'gsf_case_study' ),
			'post_status'    => array( 'publish', 'draft', 'pending', 'private' ),
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'orderby'        => 'ID',
			'order'          => 'ASC',
			'lang'           => '',
		)
	);

	foreach ( $record_ids as $record_id ) {
		if ( pll_get_post_language( $record_id, 'slug' ) ) {
			continue;
		}

		pll_set_post_language( $record_id, $default_language );
	}
}
add_action( 'admin_init', 'gsf_hub_sunrise_sync_library_record_languages' );

/**
 * Returns the Pods field definitions for the resource library.
 *
 * @return array<string, array<string, mixed>>
 */
function gsf_hub_sunrise_get_resource_pod_fields() {
	return array(
		'resource_type' => array(
			'label'                 => __( 'Resource Type', 'gsf-hub' ),
			'type'                  => 'pick',
			'pick_object'           => 'custom-simple',
			'pick_custom'           => gsf_hub_sunrise_format_pods_custom_simple_options( gsf_hub_sunrise_get_resource_type_values() ),
			'pick_format_type'      => 'single',
			'pick_format_single'    => 'dropdown',
			'pick_show_select_text' => '1',
		),
		'topic' => array(
			'label'           => __( 'Topic', 'gsf-hub' ),
			'type'            => 'text',
			'text_max_length' => '160',
		),
		'country_region' => array(
			'label'                 => __( 'Country / Region', 'gsf-hub' ),
			'type'                  => 'pick',
			'pick_object'           => 'custom-simple',
			'pick_custom'           => gsf_hub_sunrise_format_pods_custom_simple_options( gsf_hub_sunrise_get_resource_region_values() ),
			'pick_format_type'      => 'single',
			'pick_format_single'    => 'dropdown',
			'pick_show_select_text' => '1',
		),
		'publication_year' => array(
			'label'       => __( 'Publication Date', 'gsf-hub' ),
			'type'        => 'date',
			'date_type'   => 'format',
			'date_format' => 'ymd_dash',
		),
		'source_organization' => array(
			'label'           => __( 'Source Organization', 'gsf-hub' ),
			'type'            => 'text',
			'text_max_length' => '180',
		),
		'resource_file' => array(
			'label'                         => __( 'Resource File', 'gsf-hub' ),
			'type'                          => 'file',
			'file_format_type'              => 'multi',
			'file_uploader'                 => 'attachment',
			'file_type'                     => 'any',
			'file_attachment_tab'           => 'upload',
			'file_attachment_current_post_only' => '0',
			'file_upload_dir'               => 'wp',
			'file_edit_title'               => '1',
			'file_show_edit_link'           => '0',
			'file_linked'                   => '0',
			'file_limit'                    => '0',
			'file_field_template'           => 'rows',
			'file_add_button'               => __( 'Add File', 'gsf-hub' ),
			'file_modal_title'              => __( 'Attach a resource file', 'gsf-hub' ),
			'file_modal_add_button'         => __( 'Add File', 'gsf-hub' ),
			'file_wp_gallery_link'          => 'file',
			'file_wp_gallery_columns'       => '3',
			'file_wp_gallery_size'          => 'thumbnail',
			'file_auto_set_featured_image'  => '0',
		),
		'external_url' => array(
			'label' => __( 'External URL', 'gsf-hub' ),
			'type'  => 'website',
		),
		'featured_resource' => array(
			'label'       => __( 'Featured Resource', 'gsf-hub' ),
			'type'        => 'boolean',
			'boolean_yes' => __( 'Yes', 'gsf-hub' ),
			'boolean_no'  => __( 'No', 'gsf-hub' ),
		),
	);
}

/**
 * Ensures the Pods-powered Resource Library content type exists.
 */
function gsf_hub_sunrise_ensure_resource_pod() {
	if ( ! function_exists( 'pods_api' ) ) {
		return;
	}

	$api = pods_api();
	$pod = $api->load_pod(
		array(
			'name' => 'gsf_resource',
		)
	);

	$existing_pod_id = 0;
	if ( is_array( $pod ) && ! empty( $pod['id'] ) ) {
		$existing_pod_id = (int) $pod['id'];
	} elseif ( is_object( $pod ) && method_exists( $pod, 'get_arg' ) ) {
		$existing_pod_id = (int) $pod->get_arg( 'id' );
	}

	if ( ! $existing_pod_id ) {
		$params = post_type_exists( 'gsf_resource' )
			? array(
				'create_extend'    => 'extend',
				'extend_pod_type'  => 'post_type',
				'extend_post_type' => 'gsf_resource',
				'extend_storage'   => 'meta',
			)
			: array(
				'create_extend'             => 'create',
				'create_pod_type'           => 'post_type',
				'create_name'               => 'gsf_resource',
				'create_label_singular'     => __( 'Resource', 'gsf-hub' ),
				'create_label_plural'       => __( 'Resource Library', 'gsf-hub' ),
				'create_storage'            => 'meta',
				'create_public'             => 1,
				'create_publicly_queryable' => 1,
				'create_rest_api'           => 1,
			);

		$api->add_pod( $params );

		$pod = $api->load_pod(
			array(
				'name' => 'gsf_resource',
			)
		);
	}

	$pod_id = 0;
	if ( is_array( $pod ) && ! empty( $pod['id'] ) ) {
		$pod_id = (int) $pod['id'];
	} elseif ( is_object( $pod ) && method_exists( $pod, 'get_arg' ) ) {
		$pod_id = (int) $pod->get_arg( 'id' );
	}

	if ( ! $pod_id ) {
		return;
	}

	$api->save_pod(
		array(
			'id'                 => $pod_id,
			'name'               => 'gsf_resource',
			'label'              => __( 'Resource Library', 'gsf-hub' ),
			'label_singular'     => __( 'Resource', 'gsf-hub' ),
			'type'               => 'post_type',
			'storage'            => 'meta',
			'public'             => 1,
			'publicly_queryable' => 1,
			'show_ui'            => 1,
			'show_in_menu'       => 1,
			'menu_name'          => __( 'Resource Library', 'gsf-hub' ),
			'menu_icon'          => 'dashicons-media-document',
			'rewrite'            => 1,
			'rewrite_slug'       => 'resource-library',
			'has_archive'        => 1,
			'has_archive_slug'   => 'resource-library',
			'rest_enable'        => 1,
			'supports_title'     => 1,
			'supports_editor'    => 1,
			'supports_excerpt'   => 1,
			'supports_thumbnail' => 1,
		)
	);

	$pod = $api->load_pod(
		array(
			'name' => 'gsf_resource',
		)
	);

	$group_id = 0;
	if ( is_array( $pod ) && ! empty( $pod['groups'] ) && is_array( $pod['groups'] ) ) {
		$first_group = reset( $pod['groups'] );
		$group_id    = isset( $first_group['id'] ) ? (int) $first_group['id'] : 0;
	} elseif ( is_object( $pod ) && method_exists( $pod, 'get_groups' ) ) {
		$groups = $pod->get_groups();
		if ( $groups ) {
			$first_group = reset( $groups );
			if ( is_array( $first_group ) && isset( $first_group['id'] ) ) {
				$group_id = (int) $first_group['id'];
			} elseif ( is_object( $first_group ) && method_exists( $first_group, 'get_arg' ) ) {
				$group_id = (int) $first_group->get_arg( 'id' );
			}
		}
	}

	$existing_fields = array();
	if ( is_array( $pod ) && isset( $pod['fields'] ) && is_array( $pod['fields'] ) ) {
		$existing_fields = $pod['fields'];
	} elseif ( is_object( $pod ) && method_exists( $pod, 'get_fields' ) ) {
		$existing_fields = $pod->get_fields();
	}

	$weight = 0;

	foreach ( gsf_hub_sunrise_get_resource_pod_fields() as $name => $field ) {
		$params = array_merge(
			array(
				'pod_id'                       => $pod_id,
				'pod'                          => 'gsf_resource',
				'group_id'                     => $group_id,
				'name'                         => $name,
				'weight'                       => $weight,
				'required'                     => '0',
				'roles_allowed'                => '',
				'logged_in_only'               => '0',
				'admin_only'                   => '0',
				'restrict_role'                => '0',
				'restrict_capability'          => '0',
				'hidden'                       => '0',
				'read_only'                    => '0',
				'read_only_restricted'         => '0',
				'repeatable'                   => '0',
				'repeatable_format'            => 'default',
				'default_evaluate_tags'        => '0',
				'default_empty_fields'         => '0',
				'revisions_revision_field'     => '0',
				'enable_conditional_logic'     => '0',
				'conditional_logic_save_value' => '0',
				'rest_pick_response'           => 'array',
				'rest_pick_depth'              => '1',
				'required_help_boolean'        => '0',
			),
			$field
		);

		$field_id = 0;
		if ( isset( $existing_fields[ $name ] ) ) {
			if ( is_array( $existing_fields[ $name ] ) && isset( $existing_fields[ $name ]['id'] ) ) {
				$field_id = (int) $existing_fields[ $name ]['id'];
			} elseif ( is_object( $existing_fields[ $name ] ) && method_exists( $existing_fields[ $name ], 'get_arg' ) ) {
				$field_id = (int) $existing_fields[ $name ]->get_arg( 'id' );
			}
		}

		if ( $field_id ) {
			$params['id'] = $field_id;
			$api->save_field( $params );
		} else {
			$api->add_field( $params );
		}

		++$weight;
	}

	if ( method_exists( $api, 'cache_flush_pods' ) ) {
		$api->cache_flush_pods( $pod );
	}
	if ( method_exists( $api, 'cache_flush_groups' ) ) {
		$api->cache_flush_groups();
	}
	if ( method_exists( $api, 'cache_flush_fields' ) ) {
		$api->cache_flush_fields();
	}
}
add_action( 'admin_init', 'gsf_hub_sunrise_ensure_resource_pod' );

/**
 * Returns distinct meta values for resource filters.
 *
 * @param string $meta_key Meta key.
 * @return string[]
 */
function gsf_hub_sunrise_get_resource_meta_options( $meta_key ) {
	global $wpdb;

	$current_language = gsf_hub_sunrise_get_current_language_slug();
	$language_join    = '';
	$language_where   = '';
	$query_values     = array( $meta_key );

	if ( $current_language && function_exists( 'pll_current_language' ) ) {
		$language_join  = " INNER JOIN {$wpdb->term_relationships} pll_rel ON pll_rel.object_id = p.ID
			INNER JOIN {$wpdb->term_taxonomy} pll_tt ON pll_tt.term_taxonomy_id = pll_rel.term_taxonomy_id
			INNER JOIN {$wpdb->terms} pll_term ON pll_term.term_id = pll_tt.term_id";
		$language_where = ' AND pll_tt.taxonomy = %s AND pll_term.slug = %s';
		$query_values[] = 'language';
		$query_values[] = $current_language;
	}

	$values = $wpdb->get_col(
		$wpdb->prepare(
			"SELECT DISTINCT pm.meta_value
			FROM {$wpdb->postmeta} pm
			INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
			{$language_join}
			WHERE pm.meta_key = %s
				AND pm.meta_value <> ''
				AND p.post_type = 'gsf_resource'
				AND p.post_status = 'publish'
				{$language_where}
			ORDER BY pm.meta_value ASC",
			$query_values
		)
	);

	return array_values( array_filter( array_map( 'sanitize_text_field', (array) $values ) ) );
}

/**
 * Returns controlled options currently used by published resources.
 *
 * @param string   $meta_key Meta key.
 * @param string[] $allowed_values Controlled values.
 * @return string[]
 */
function gsf_hub_sunrise_get_used_controlled_resource_options( $meta_key, $allowed_values ) {
	$used = gsf_hub_sunrise_get_resource_meta_options( $meta_key );

	return array_values(
		array_filter(
			$allowed_values,
			static function ( $value ) use ( $used ) {
				return in_array( $value, $used, true );
			}
		)
	);
}

/**
 * Resolves a Pods file field value to a public URL.
 *
 * @param mixed $value Raw field value.
 * @return string
 */
function gsf_hub_sunrise_get_resource_file_url( $value ) {
	if ( is_numeric( $value ) ) {
		return (string) wp_get_attachment_url( (int) $value );
	}

	if ( is_array( $value ) ) {
		$first = reset( $value );
		return gsf_hub_sunrise_get_resource_file_url( $first );
	}

	if ( is_string( $value ) && preg_match( '/^https?:\/\//', $value ) ) {
		return $value;
	}

	return '';
}

/**
 * Resolves one or more Pods file field values to public file items.
 *
 * @param mixed $value Raw field value.
 * @return array<int, array{url:string,label:string}>
 */
function gsf_hub_sunrise_get_resource_file_items( $value ) {
	$items = array();

	if ( empty( $value ) ) {
		return $items;
	}

	if ( is_string( $value ) && is_serialized( $value ) ) {
		$value = maybe_unserialize( $value );
	}

	$values = is_array( $value ) ? $value : array( $value );

	foreach ( $values as $item ) {
		$url   = '';
		$label = '';

		if ( is_array( $item ) ) {
			$id    = isset( $item['ID'] ) ? absint( $item['ID'] ) : ( isset( $item['id'] ) ? absint( $item['id'] ) : 0 );
			$url   = $id ? (string) wp_get_attachment_url( $id ) : ( isset( $item['guid'] ) ? (string) $item['guid'] : '' );
			$label = isset( $item['post_title'] ) ? (string) $item['post_title'] : ( isset( $item['title'] ) ? (string) $item['title'] : '' );
		} elseif ( is_numeric( $item ) ) {
			$id    = absint( $item );
			$url   = (string) wp_get_attachment_url( $id );
			$label = get_the_title( $id );
		} elseif ( is_string( $item ) && preg_match( '/^https?:\/\//', $item ) ) {
			$url   = $item;
			$label = wp_basename( wp_parse_url( $item, PHP_URL_PATH ) );
		}

		if ( $url ) {
			$items[] = array(
				'url'   => $url,
				'label' => $label ?: wp_basename( wp_parse_url( $url, PHP_URL_PATH ) ),
			);
		}
	}

	return $items;
}

/**
 * Returns visible metadata for an individual resource page.
 *
 * @param int $post_id Resource post ID.
 * @return array<int, array{label:string,value:string}>
 */
function gsf_hub_sunrise_get_resource_detail_items( $post_id ) {
	$publication_date = get_post_meta( $post_id, 'publication_year', true );
	$publication_label = '';

	if ( $publication_date ) {
		$publication_timestamp = strtotime( $publication_date );
		$publication_label     = $publication_timestamp ? wp_date( 'M j, Y', $publication_timestamp ) : $publication_date;
	}

	$fields = array(
		__( 'Resource type', 'gsf-hub' ) => get_post_meta( $post_id, 'resource_type', true ),
		__( 'Publication date', 'gsf-hub' ) => $publication_label,
		__( 'Topic', 'gsf-hub' ) => get_post_meta( $post_id, 'topic', true ),
		__( 'Country / Region', 'gsf-hub' ) => get_post_meta( $post_id, 'country_region', true ),
		__( 'Source', 'gsf-hub' ) => get_post_meta( $post_id, 'source_organization', true ),
	);

	$items = array();

	foreach ( $fields as $label => $value ) {
		if ( '' !== trim( (string) $value ) ) {
			$items[] = array(
				'label' => $label,
				'value' => (string) $value,
			);
		}
	}

	return $items;
}

/**
 * Adds resource metadata and download links to individual resource pages.
 *
 * @param string $content Main content.
 * @return string
 */
function gsf_hub_sunrise_enhance_resource_content( $content ) {
	if ( ! is_singular( 'gsf_resource' ) || ! in_the_loop() || ! is_main_query() ) {
		return $content;
	}

	$post_id      = get_the_ID();
	$detail_items = gsf_hub_sunrise_get_resource_detail_items( $post_id );
	$file_items   = gsf_hub_sunrise_get_resource_file_items( get_post_meta( $post_id, 'resource_file', false ) );
	$external_url = get_post_meta( $post_id, 'external_url', true );

	if ( filter_var( $external_url, FILTER_VALIDATE_URL ) ) {
		$file_items[] = array(
			'url'   => $external_url,
			'label' => __( 'External resource', 'gsf-hub' ),
		);
	}

	if ( ! $detail_items && ! $file_items ) {
		return $content;
	}

	ob_start();
	?>
	<?php if ( $detail_items ) : ?>
		<section class="gsf-resource-details" aria-label="<?php esc_attr_e( 'Resource details', 'gsf-hub' ); ?>">
			<?php foreach ( $detail_items as $item ) : ?>
				<div class="gsf-resource-details__item">
					<span><?php echo esc_html( $item['label'] ); ?></span>
					<strong><?php echo esc_html( $item['value'] ); ?></strong>
				</div>
			<?php endforeach; ?>
		</section>
	<?php endif; ?>
	<?php
	$details_html = ob_get_clean();

	ob_start();
	?>
	<?php if ( $file_items ) : ?>
		<section class="gsf-resource-downloads">
			<h2><?php esc_html_e( 'Resource files', 'gsf-hub' ); ?></h2>
			<ul class="gsf-resource-downloads__list">
				<?php foreach ( $file_items as $item ) : ?>
					<li>
						<a href="<?php echo esc_url( $item['url'] ); ?>" target="_blank" rel="noopener noreferrer">
							<?php echo esc_html( $item['label'] ); ?>
						</a>
					</li>
				<?php endforeach; ?>
			</ul>
		</section>
	<?php endif; ?>
	<?php
	$downloads_html = ob_get_clean();

	return $details_html . $content . $downloads_html;
}
add_filter( 'the_content', 'gsf_hub_sunrise_enhance_resource_content', 20 );

/**
 * Renders the Pods-backed resource library.
 *
 * @return string
 */
function gsf_hub_sunrise_resource_library_shortcode() {
	$search = isset( $_GET['resource_search'] ) ? sanitize_text_field( wp_unslash( $_GET['resource_search'] ) ) : '';
	$type   = isset( $_GET['resource_type'] ) ? sanitize_text_field( wp_unslash( $_GET['resource_type'] ) ) : '';
	$topic  = isset( $_GET['resource_topic'] ) ? sanitize_text_field( wp_unslash( $_GET['resource_topic'] ) ) : '';
	$region = isset( $_GET['resource_region'] ) ? sanitize_text_field( wp_unslash( $_GET['resource_region'] ) ) : '';
	$page_id = isset( $_REQUEST['page_id'] ) ? absint( wp_unslash( $_REQUEST['page_id'] ) ) : (int) get_queried_object_id();
	$current_language = gsf_hub_sunrise_get_current_language_slug();

	$meta_query = array();
	foreach ( array( 'resource_type' => $type, 'topic' => $topic, 'country_region' => $region ) as $key => $value ) {
		if ( '' !== $value ) {
			$meta_query[] = array(
				'key'     => $key,
				'value'   => $value,
				'compare' => '=',
			);
		}
	}

	$query_args = array(
		'post_type'           => 'gsf_resource',
		'post_status'         => 'publish',
		'posts_per_page'      => 12,
		'ignore_sticky_posts' => true,
		's'                   => $search,
		'orderby'             => array(
			'meta_value' => 'DESC',
			'date'       => 'DESC',
		),
		'meta_key'            => 'publication_year', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
	);

	if ( $current_language ) {
		$query_args['lang'] = $current_language;
	}

	if ( $meta_query ) {
		$query_args['meta_query'] = $meta_query; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
	}

	$resources = new WP_Query( $query_args );
	$types     = gsf_hub_sunrise_get_used_controlled_resource_options( 'resource_type', gsf_hub_sunrise_get_resource_type_values() );
	$topics    = gsf_hub_sunrise_get_resource_meta_options( 'topic' );
	$regions   = gsf_hub_sunrise_get_used_controlled_resource_options( 'country_region', gsf_hub_sunrise_get_resource_region_values() );
	$library_url = untrailingslashit( get_permalink( $page_id ?: get_queried_object_id() ) );

	ob_start();
	?>
		<section id="resource-library" class="gsf-resource-library">
			<form class="gsf-resource-library__filters" method="get" action="<?php echo esc_url( $library_url . '#resource-library' ); ?>" data-gsf-instant-filter="resource-library" data-gsf-filter-target="resource-library" data-gsf-page-id="<?php echo esc_attr( (string) $page_id ); ?>">
			<label>
				<span><?php esc_html_e( 'Search', 'gsf-hub' ); ?></span>
				<input type="search" name="resource_search" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Search resources...', 'gsf-hub' ); ?>">
			</label>
			<label>
				<span><?php esc_html_e( 'Type', 'gsf-hub' ); ?></span>
				<select name="resource_type">
					<option value=""><?php esc_html_e( 'All types', 'gsf-hub' ); ?></option>
					<?php foreach ( $types as $option ) : ?>
						<option value="<?php echo esc_attr( $option ); ?>" <?php selected( $type, $option ); ?>><?php echo esc_html( $option ); ?></option>
					<?php endforeach; ?>
				</select>
			</label>
			<label>
				<span><?php esc_html_e( 'Topic', 'gsf-hub' ); ?></span>
				<select name="resource_topic">
					<option value=""><?php esc_html_e( 'All topics', 'gsf-hub' ); ?></option>
					<?php foreach ( $topics as $option ) : ?>
						<option value="<?php echo esc_attr( $option ); ?>" <?php selected( $topic, $option ); ?>><?php echo esc_html( $option ); ?></option>
					<?php endforeach; ?>
				</select>
			</label>
			<label>
				<span><?php esc_html_e( 'Country / Region', 'gsf-hub' ); ?></span>
				<select name="resource_region">
					<option value=""><?php esc_html_e( 'All countries/regions', 'gsf-hub' ); ?></option>
					<?php foreach ( $regions as $option ) : ?>
						<option value="<?php echo esc_attr( $option ); ?>" <?php selected( $region, $option ); ?>><?php echo esc_html( $option ); ?></option>
					<?php endforeach; ?>
				</select>
			</label>
				<div class="gsf-resource-library__filter-actions">
					<button class="button button--primary" type="submit"><?php esc_html_e( 'Apply filters', 'gsf-hub' ); ?></button>
					<a class="button button--secondary" href="<?php echo esc_url( $library_url . '#resource-library' ); ?>">
						<?php esc_html_e( 'Reset', 'gsf-hub' ); ?>
					</a>
				</div>
			</form>

		<?php if ( $resources->have_posts() ) : ?>
			<div class="gsf-resource-library__grid">
				<?php
				while ( $resources->have_posts() ) :
					$resources->the_post();

					$post_id       = get_the_ID();
					$resource_type = get_post_meta( $post_id, 'resource_type', true );
					$resource_topic = get_post_meta( $post_id, 'topic', true );
					$publication_date = get_post_meta( $post_id, 'publication_year', true );
					$publication_label = '';
					$org           = get_post_meta( $post_id, 'source_organization', true );
					$action_url    = get_permalink();

					if ( $publication_date ) {
						$publication_timestamp = strtotime( $publication_date );
						$publication_label     = $publication_timestamp ? wp_date( 'M j, Y', $publication_timestamp ) : $publication_date;
					}
					?>
					<article class="gsf-resource-card">
						<div class="gsf-resource-card__meta">
							<?php if ( $resource_type ) : ?>
								<span><?php echo esc_html( $resource_type ); ?></span>
							<?php endif; ?>
							<?php if ( $publication_label ) : ?>
								<span><?php echo esc_html( $publication_label ); ?></span>
							<?php endif; ?>
						</div>
						<h3><a href="<?php echo esc_url( get_permalink() ); ?>"><?php the_title(); ?></a></h3>
						<?php if ( has_excerpt() ) : ?>
							<p><?php echo esc_html( get_the_excerpt() ); ?></p>
						<?php else : ?>
							<p><?php echo esc_html( wp_trim_words( wp_strip_all_tags( get_the_content() ), 22 ) ); ?></p>
						<?php endif; ?>
						<div class="gsf-resource-card__details">
							<?php if ( $resource_topic ) : ?>
								<span><?php echo esc_html( $resource_topic ); ?></span>
							<?php endif; ?>
							<?php if ( $org ) : ?>
								<span><?php echo esc_html( $org ); ?></span>
							<?php endif; ?>
						</div>
						<a class="button button--secondary button--full" href="<?php echo esc_url( $action_url ); ?>">
							<?php esc_html_e( 'View details', 'gsf-hub' ); ?>
						</a>
					</article>
				<?php endwhile; ?>
			</div>
			<?php wp_reset_postdata(); ?>
		<?php else : ?>
			<div class="gsf-resource-library__empty">
				<h3><?php esc_html_e( 'No resources found', 'gsf-hub' ); ?></h3>
				<p><?php esc_html_e( 'Add resources from WP Admin -> Resource Library, or clear the filters and try again.', 'gsf-hub' ); ?></p>
			</div>
		<?php endif; ?>
	</section>
	<?php
	return ob_get_clean();
}
add_shortcode( 'gsf_resource_library', 'gsf_hub_sunrise_resource_library_shortcode' );

/**
 * Returns controlled case study type values.
 *
 * @return string[]
 */
function gsf_hub_sunrise_get_case_study_type_values() {
	return array(
		'Community Story',
		'Project Case Study',
		'Policy Practice',
		'Learning Note',
		'Partner Spotlight',
	);
}

/**
 * Returns controlled case study focus area values.
 *
 * @return string[]
 */
function gsf_hub_sunrise_get_case_study_focus_values() {
	return array(
		'Gender-responsive conservation',
		'Climate resilience',
		'Biodiversity finance',
		'Community livelihoods',
		'Organizational strengthening',
		'Knowledge sharing',
	);
}

/**
 * Returns the Pods field definitions for case studies.
 *
 * @return array<string, array<string, mixed>>
 */
function gsf_hub_sunrise_get_case_study_pod_fields() {
	return array(
		'case_study_type' => array(
			'label'                 => __( 'Case Study Type', 'gsf-hub' ),
			'type'                  => 'pick',
			'pick_object'           => 'custom-simple',
			'pick_custom'           => gsf_hub_sunrise_format_pods_custom_simple_options( gsf_hub_sunrise_get_case_study_type_values() ),
			'pick_format_type'      => 'single',
			'pick_format_single'    => 'dropdown',
			'pick_show_select_text' => '1',
		),
		'focus_area' => array(
			'label'                 => __( 'Focus Area', 'gsf-hub' ),
			'type'                  => 'pick',
			'pick_object'           => 'custom-simple',
			'pick_custom'           => gsf_hub_sunrise_format_pods_custom_simple_options( gsf_hub_sunrise_get_case_study_focus_values() ),
			'pick_format_type'      => 'single',
			'pick_format_single'    => 'dropdown',
			'pick_show_select_text' => '1',
		),
		'country_region' => array(
			'label'                 => __( 'Country / Region', 'gsf-hub' ),
			'type'                  => 'pick',
			'pick_object'           => 'custom-simple',
			'pick_custom'           => gsf_hub_sunrise_format_pods_custom_simple_options( gsf_hub_sunrise_get_resource_region_values() ),
			'pick_format_type'      => 'single',
			'pick_format_single'    => 'dropdown',
			'pick_show_select_text' => '1',
		),
		'implementation_date' => array(
			'label'       => __( 'Implementation Date', 'gsf-hub' ),
			'type'        => 'date',
			'date_type'   => 'format',
			'date_format' => 'ymd_dash',
		),
		'lead_organization' => array(
			'label'           => __( 'Lead Organization', 'gsf-hub' ),
			'type'            => 'text',
			'text_max_length' => '180',
		),
			'partners' => array(
				'label'           => __( 'Partners', 'gsf-hub' ),
				'type'            => 'text',
				'text_max_length' => '220',
			),
			'case_study_files' => array(
				'label'                         => __( 'Case Study Files', 'gsf-hub' ),
				'type'                          => 'file',
				'file_format_type'              => 'multi',
				'file_uploader'                 => 'attachment',
				'file_type'                     => 'any',
				'file_attachment_tab'           => 'upload',
				'file_attachment_current_post_only' => '0',
				'file_upload_dir'               => 'wp',
				'file_edit_title'               => '1',
				'file_show_edit_link'           => '0',
				'file_linked'                   => '0',
				'file_limit'                    => '0',
				'file_field_template'           => 'rows',
				'file_add_button'               => __( 'Add File', 'gsf-hub' ),
				'file_modal_title'              => __( 'Attach a case study file', 'gsf-hub' ),
				'file_modal_add_button'         => __( 'Add File', 'gsf-hub' ),
				'file_wp_gallery_link'          => 'file',
				'file_wp_gallery_columns'       => '3',
				'file_wp_gallery_size'          => 'thumbnail',
				'file_auto_set_featured_image'  => '0',
			),
			'external_url' => array(
				'label' => __( 'External URL', 'gsf-hub' ),
				'type'  => 'website',
		),
		'featured_case_study' => array(
			'label'       => __( 'Featured Case Study', 'gsf-hub' ),
			'type'        => 'boolean',
			'boolean_yes' => __( 'Yes', 'gsf-hub' ),
			'boolean_no'  => __( 'No', 'gsf-hub' ),
		),
	);
}

/**
 * Ensures the Pods-powered Case Studies content type exists.
 */
function gsf_hub_sunrise_ensure_case_study_pod() {
	if ( ! function_exists( 'pods_api' ) ) {
		return;
	}

	$api = pods_api();
	$pod = $api->load_pod(
		array(
			'name' => 'gsf_case_study',
		)
	);

	$existing_pod_id = 0;
	if ( is_array( $pod ) && ! empty( $pod['id'] ) ) {
		$existing_pod_id = (int) $pod['id'];
	} elseif ( is_object( $pod ) && method_exists( $pod, 'get_arg' ) ) {
		$existing_pod_id = (int) $pod->get_arg( 'id' );
	}

	if ( ! $existing_pod_id ) {
		$params = post_type_exists( 'gsf_case_study' )
			? array(
				'create_extend'    => 'extend',
				'extend_pod_type'  => 'post_type',
				'extend_post_type' => 'gsf_case_study',
				'extend_storage'   => 'meta',
			)
			: array(
				'create_extend'             => 'create',
				'create_pod_type'           => 'post_type',
				'create_name'               => 'gsf_case_study',
				'create_label_singular'     => __( 'Case Study', 'gsf-hub' ),
				'create_label_plural'       => __( 'Case Studies', 'gsf-hub' ),
				'create_storage'            => 'meta',
				'create_public'             => 1,
				'create_publicly_queryable' => 1,
				'create_rest_api'           => 1,
			);

		$api->add_pod( $params );

		$pod = $api->load_pod(
			array(
				'name' => 'gsf_case_study',
			)
		);
	}

	$pod_id = 0;
	if ( is_array( $pod ) && ! empty( $pod['id'] ) ) {
		$pod_id = (int) $pod['id'];
	} elseif ( is_object( $pod ) && method_exists( $pod, 'get_arg' ) ) {
		$pod_id = (int) $pod->get_arg( 'id' );
	}

	if ( ! $pod_id ) {
		return;
	}

	$api->save_pod(
		array(
			'id'                 => $pod_id,
			'name'               => 'gsf_case_study',
			'label'              => __( 'Case Studies', 'gsf-hub' ),
			'label_singular'     => __( 'Case Study', 'gsf-hub' ),
			'type'               => 'post_type',
			'storage'            => 'meta',
			'public'             => 1,
			'publicly_queryable' => 1,
			'show_ui'            => 1,
			'show_in_menu'       => 1,
			'menu_name'          => __( 'Case Studies', 'gsf-hub' ),
			'menu_icon'          => 'dashicons-welcome-write-blog',
			'rewrite'            => 1,
			'rewrite_slug'       => 'case-study',
			'has_archive'        => 1,
			'has_archive_slug'   => 'case-studies-library',
			'rest_enable'        => 1,
			'supports_title'     => 1,
			'supports_editor'    => 1,
			'supports_excerpt'   => 1,
			'supports_thumbnail' => 1,
		)
	);

	$pod = $api->load_pod(
		array(
			'name' => 'gsf_case_study',
		)
	);

	$group_id = 0;
	if ( is_array( $pod ) && ! empty( $pod['groups'] ) && is_array( $pod['groups'] ) ) {
		$first_group = reset( $pod['groups'] );
		$group_id    = isset( $first_group['id'] ) ? (int) $first_group['id'] : 0;
	} elseif ( is_object( $pod ) && method_exists( $pod, 'get_groups' ) ) {
		$groups = $pod->get_groups();
		if ( $groups ) {
			$first_group = reset( $groups );
			if ( is_array( $first_group ) && isset( $first_group['id'] ) ) {
				$group_id = (int) $first_group['id'];
			} elseif ( is_object( $first_group ) && method_exists( $first_group, 'get_arg' ) ) {
				$group_id = (int) $first_group->get_arg( 'id' );
			}
		}
	}

	$existing_fields = array();
	if ( is_array( $pod ) && isset( $pod['fields'] ) && is_array( $pod['fields'] ) ) {
		$existing_fields = $pod['fields'];
	} elseif ( is_object( $pod ) && method_exists( $pod, 'get_fields' ) ) {
		$existing_fields = $pod->get_fields();
	}

	$weight = 0;

	foreach ( gsf_hub_sunrise_get_case_study_pod_fields() as $name => $field ) {
		$params = array_merge(
			array(
				'pod_id'                       => $pod_id,
				'pod'                          => 'gsf_case_study',
				'group_id'                     => $group_id,
				'name'                         => $name,
				'weight'                       => $weight,
				'required'                     => '0',
				'roles_allowed'                => '',
				'logged_in_only'               => '0',
				'admin_only'                   => '0',
				'restrict_role'                => '0',
				'restrict_capability'          => '0',
				'hidden'                       => '0',
				'read_only'                    => '0',
				'read_only_restricted'         => '0',
				'repeatable'                   => '0',
				'repeatable_format'            => 'default',
				'default_evaluate_tags'        => '0',
				'default_empty_fields'         => '0',
				'revisions_revision_field'     => '0',
				'enable_conditional_logic'     => '0',
				'conditional_logic_save_value' => '0',
				'rest_pick_response'           => 'array',
				'rest_pick_depth'              => '1',
				'required_help_boolean'        => '0',
			),
			$field
		);

		$field_id = 0;
		if ( isset( $existing_fields[ $name ] ) ) {
			if ( is_array( $existing_fields[ $name ] ) && isset( $existing_fields[ $name ]['id'] ) ) {
				$field_id = (int) $existing_fields[ $name ]['id'];
			} elseif ( is_object( $existing_fields[ $name ] ) && method_exists( $existing_fields[ $name ], 'get_arg' ) ) {
				$field_id = (int) $existing_fields[ $name ]->get_arg( 'id' );
			}
		}

		if ( $field_id ) {
			$params['id'] = $field_id;
			$api->save_field( $params );
		} else {
			$api->add_field( $params );
		}

		++$weight;
	}

	if ( method_exists( $api, 'cache_flush_pods' ) ) {
		$api->cache_flush_pods( $pod );
	}
	if ( method_exists( $api, 'cache_flush_groups' ) ) {
		$api->cache_flush_groups();
	}
	if ( method_exists( $api, 'cache_flush_fields' ) ) {
		$api->cache_flush_fields();
	}
}
add_action( 'admin_init', 'gsf_hub_sunrise_ensure_case_study_pod' );

/**
 * Returns distinct meta values for published case studies.
 *
 * @param string $meta_key Meta key.
 * @return string[]
 */
function gsf_hub_sunrise_get_case_study_meta_options( $meta_key ) {
	global $wpdb;

	$current_language = gsf_hub_sunrise_get_current_language_slug();
	$language_join    = '';
	$language_where   = '';
	$query_values     = array( $meta_key );

	if ( $current_language && function_exists( 'pll_current_language' ) ) {
		$language_join  = " INNER JOIN {$wpdb->term_relationships} pll_rel ON pll_rel.object_id = p.ID
			INNER JOIN {$wpdb->term_taxonomy} pll_tt ON pll_tt.term_taxonomy_id = pll_rel.term_taxonomy_id
			INNER JOIN {$wpdb->terms} pll_term ON pll_term.term_id = pll_tt.term_id";
		$language_where = ' AND pll_tt.taxonomy = %s AND pll_term.slug = %s';
		$query_values[] = 'language';
		$query_values[] = $current_language;
	}

	$values = $wpdb->get_col(
		$wpdb->prepare(
			"SELECT DISTINCT pm.meta_value
			FROM {$wpdb->postmeta} pm
			INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
			{$language_join}
			WHERE pm.meta_key = %s
				AND pm.meta_value <> ''
				AND p.post_type = 'gsf_case_study'
				AND p.post_status = 'publish'
				{$language_where}
			ORDER BY pm.meta_value ASC",
			$query_values
		)
	);

	return array_values( array_filter( array_map( 'sanitize_text_field', (array) $values ) ) );
}

/**
 * Returns controlled case study options currently used by published entries.
 *
 * @param string   $meta_key Meta key.
 * @param string[] $allowed_values Controlled values.
 * @return string[]
 */
function gsf_hub_sunrise_get_used_controlled_case_study_options( $meta_key, $allowed_values ) {
	$used = gsf_hub_sunrise_get_case_study_meta_options( $meta_key );

	return array_values(
		array_filter(
			$allowed_values,
			static function ( $value ) use ( $used ) {
				return in_array( $value, $used, true );
			}
		)
	);
}

/**
 * Returns visible metadata for an individual case study page.
 *
 * @param int $post_id Case study post ID.
 * @return array<int, array{label:string,value:string}>
 */
function gsf_hub_sunrise_get_case_study_detail_items( $post_id ) {
	$implementation_date  = get_post_meta( $post_id, 'implementation_date', true );
	$implementation_label = '';

	if ( $implementation_date ) {
		$implementation_timestamp = strtotime( $implementation_date );
		$implementation_label     = $implementation_timestamp ? wp_date( 'M j, Y', $implementation_timestamp ) : $implementation_date;
	}

	$fields = array(
		__( 'Case study type', 'gsf-hub' ) => get_post_meta( $post_id, 'case_study_type', true ),
		__( 'Focus area', 'gsf-hub' ) => get_post_meta( $post_id, 'focus_area', true ),
		__( 'Country / Region', 'gsf-hub' ) => get_post_meta( $post_id, 'country_region', true ),
		__( 'Implementation date', 'gsf-hub' ) => $implementation_label,
		__( 'Lead organization', 'gsf-hub' ) => get_post_meta( $post_id, 'lead_organization', true ),
		__( 'Partners', 'gsf-hub' ) => get_post_meta( $post_id, 'partners', true ),
	);

	$items = array();

	foreach ( $fields as $label => $value ) {
		if ( '' !== trim( (string) $value ) ) {
			$items[] = array(
				'label' => $label,
				'value' => (string) $value,
			);
		}
	}

	return $items;
}

/**
 * Adds case study metadata and optional external link to individual pages.
 *
 * @param string $content Main content.
 * @return string
 */
function gsf_hub_sunrise_enhance_case_study_content( $content ) {
	if ( ! is_singular( 'gsf_case_study' ) || ! in_the_loop() || ! is_main_query() ) {
		return $content;
	}

	$post_id      = get_the_ID();
	$detail_items = gsf_hub_sunrise_get_case_study_detail_items( $post_id );
	$file_items   = gsf_hub_sunrise_get_resource_file_items( get_post_meta( $post_id, 'case_study_files', false ) );
	$external_url = get_post_meta( $post_id, 'external_url', true );

	if ( ! $detail_items && ! $file_items && ! filter_var( $external_url, FILTER_VALIDATE_URL ) ) {
		return $content;
	}

	ob_start();
	?>
	<?php if ( $detail_items ) : ?>
		<section class="gsf-resource-details gsf-case-study-details" aria-label="<?php esc_attr_e( 'Case study details', 'gsf-hub' ); ?>">
			<?php foreach ( $detail_items as $item ) : ?>
				<div class="gsf-resource-details__item">
					<span><?php echo esc_html( $item['label'] ); ?></span>
					<strong><?php echo esc_html( $item['value'] ); ?></strong>
				</div>
			<?php endforeach; ?>
		</section>
	<?php endif; ?>
	<?php
	$details_html = ob_get_clean();

	ob_start();
	?>
	<?php if ( $file_items ) : ?>
		<section class="gsf-resource-downloads gsf-case-study-downloads">
			<h2><?php esc_html_e( 'Case study files', 'gsf-hub' ); ?></h2>
			<ul class="gsf-resource-downloads__list">
				<?php foreach ( $file_items as $item ) : ?>
					<li>
						<a href="<?php echo esc_url( $item['url'] ); ?>" target="_blank" rel="noopener noreferrer">
							<?php echo esc_html( $item['label'] ); ?>
						</a>
					</li>
				<?php endforeach; ?>
			</ul>
		</section>
	<?php endif; ?>
	<?php if ( filter_var( $external_url, FILTER_VALIDATE_URL ) ) : ?>
		<p class="gsf-case-study-external-link">
			<a class="button button--secondary" href="<?php echo esc_url( $external_url ); ?>" target="_blank" rel="noopener noreferrer">
				<?php esc_html_e( 'Open related link', 'gsf-hub' ); ?>
			</a>
		</p>
	<?php endif; ?>
	<?php
	$supporting_html = ob_get_clean();

	return $details_html . $content . $supporting_html;
}
add_filter( 'the_content', 'gsf_hub_sunrise_enhance_case_study_content', 20 );

/**
 * Renders a featured case study panel.
 *
 * @return string
 */
function gsf_hub_sunrise_featured_case_study_shortcode() {
	$current_language = gsf_hub_sunrise_get_current_language_slug();
	$query_args = array(
		'post_type'           => 'gsf_case_study',
		'post_status'         => 'publish',
		'posts_per_page'      => 1,
		'ignore_sticky_posts' => true,
		'meta_key'            => 'featured_case_study', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
		'meta_value'          => '1', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
	);

	if ( $current_language ) {
		$query_args['lang'] = $current_language;
	}

	$featured = new WP_Query( $query_args );

	if ( ! $featured->have_posts() ) {
		$fallback_args = array(
			'post_type'           => 'gsf_case_study',
			'post_status'         => 'publish',
			'posts_per_page'      => 1,
			'ignore_sticky_posts' => true,
			'orderby'             => array(
				'meta_value' => 'DESC',
				'date'       => 'DESC',
			),
			'meta_key'            => 'implementation_date', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
		);

		if ( $current_language ) {
			$fallback_args['lang'] = $current_language;
		}

		$featured = new WP_Query( $fallback_args );
	}

	if ( ! $featured->have_posts() ) {
		return '';
	}

	ob_start();
	?>
	<section class="gsf-featured-case-study">
		<?php
		while ( $featured->have_posts() ) :
			$featured->the_post();

			$post_id        = get_the_ID();
			$case_type      = get_post_meta( $post_id, 'case_study_type', true );
			$focus_area     = get_post_meta( $post_id, 'focus_area', true );
			$region_label   = get_post_meta( $post_id, 'country_region', true );
			$lead_org       = get_post_meta( $post_id, 'lead_organization', true );
			$hero_image_url = has_post_thumbnail() ? get_the_post_thumbnail_url( $post_id, 'large' ) : gsf_hub_sunrise_asset_uri( 'assets/images/hero-card-coastal.png' );
			?>
			<article class="gsf-featured-case-study__card">
				<a class="gsf-featured-case-study__media" href="<?php the_permalink(); ?>" style="background-image: linear-gradient(180deg, rgba(10, 55, 66, 0.12), rgba(0, 94, 122, 0.58)), url('<?php echo esc_url( $hero_image_url ); ?>');">
					<span><?php esc_html_e( 'Featured story', 'gsf-hub' ); ?></span>
				</a>
				<div class="gsf-featured-case-study__content">
					<p class="section-heading__eyebrow"><?php esc_html_e( 'Featured case study', 'gsf-hub' ); ?></p>
					<h2><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2>
					<p>
						<?php
						if ( has_excerpt() ) {
							echo esc_html( get_the_excerpt() );
						} else {
							echo esc_html( wp_trim_words( wp_strip_all_tags( get_the_content() ), 32 ) );
						}
						?>
					</p>
					<div class="gsf-featured-case-study__meta">
						<?php foreach ( array_filter( array( $case_type, $focus_area, $region_label, $lead_org ) ) as $item ) : ?>
							<span><?php echo esc_html( $item ); ?></span>
						<?php endforeach; ?>
					</div>
					<a class="button button--primary" href="<?php the_permalink(); ?>"><?php esc_html_e( 'Read featured case', 'gsf-hub' ); ?></a>
				</div>
			</article>
		<?php endwhile; ?>
		<?php wp_reset_postdata(); ?>
	</section>
	<?php
	return ob_get_clean();
}
add_shortcode( 'gsf_featured_case_study', 'gsf_hub_sunrise_featured_case_study_shortcode' );

/**
 * Renders the Pods-backed case study library.
 *
 * @return string
 */
function gsf_hub_sunrise_case_study_library_shortcode() {
	$search = isset( $_GET['case_search'] ) ? sanitize_text_field( wp_unslash( $_GET['case_search'] ) ) : '';
	$type   = isset( $_GET['case_type'] ) ? sanitize_text_field( wp_unslash( $_GET['case_type'] ) ) : '';
	$focus  = isset( $_GET['case_focus'] ) ? sanitize_text_field( wp_unslash( $_GET['case_focus'] ) ) : '';
	$region = isset( $_GET['case_region'] ) ? sanitize_text_field( wp_unslash( $_GET['case_region'] ) ) : '';
	$page_id = isset( $_REQUEST['page_id'] ) ? absint( wp_unslash( $_REQUEST['page_id'] ) ) : (int) get_queried_object_id();
	$current_language = gsf_hub_sunrise_get_current_language_slug();

	$meta_query = array();
	foreach ( array( 'case_study_type' => $type, 'focus_area' => $focus, 'country_region' => $region ) as $key => $value ) {
		if ( '' !== $value ) {
			$meta_query[] = array(
				'key'     => $key,
				'value'   => $value,
				'compare' => '=',
			);
		}
	}

	$query_args = array(
		'post_type'           => 'gsf_case_study',
		'post_status'         => 'publish',
		'posts_per_page'      => 12,
		'ignore_sticky_posts' => true,
		's'                   => $search,
		'orderby'             => array(
			'meta_value' => 'DESC',
			'date'       => 'DESC',
		),
		'meta_key'            => 'implementation_date', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
	);

	if ( $current_language ) {
		$query_args['lang'] = $current_language;
	}

	if ( $meta_query ) {
		$query_args['meta_query'] = $meta_query; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
	}

	$case_studies = new WP_Query( $query_args );
	$types        = gsf_hub_sunrise_get_used_controlled_case_study_options( 'case_study_type', gsf_hub_sunrise_get_case_study_type_values() );
	$focus_areas  = gsf_hub_sunrise_get_used_controlled_case_study_options( 'focus_area', gsf_hub_sunrise_get_case_study_focus_values() );
	$regions      = gsf_hub_sunrise_get_used_controlled_case_study_options( 'country_region', gsf_hub_sunrise_get_resource_region_values() );
	$library_url  = untrailingslashit( get_permalink( $page_id ?: get_queried_object_id() ) );

	ob_start();
	?>
	<section id="case-study-library" class="gsf-resource-library gsf-case-study-library">
		<form class="gsf-resource-library__filters" method="get" action="<?php echo esc_url( $library_url . '#case-study-library' ); ?>" data-gsf-instant-filter="case-study-library" data-gsf-filter-target="case-study-library" data-gsf-page-id="<?php echo esc_attr( (string) $page_id ); ?>">
			<label>
				<span><?php esc_html_e( 'Search', 'gsf-hub' ); ?></span>
				<input type="search" name="case_search" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Search case studies...', 'gsf-hub' ); ?>">
			</label>
			<label>
				<span><?php esc_html_e( 'Type', 'gsf-hub' ); ?></span>
				<select name="case_type">
					<option value=""><?php esc_html_e( 'All types', 'gsf-hub' ); ?></option>
					<?php foreach ( $types as $option ) : ?>
						<option value="<?php echo esc_attr( $option ); ?>" <?php selected( $type, $option ); ?>><?php echo esc_html( $option ); ?></option>
					<?php endforeach; ?>
				</select>
			</label>
			<label>
				<span><?php esc_html_e( 'Focus area', 'gsf-hub' ); ?></span>
				<select name="case_focus">
					<option value=""><?php esc_html_e( 'All focus areas', 'gsf-hub' ); ?></option>
					<?php foreach ( $focus_areas as $option ) : ?>
						<option value="<?php echo esc_attr( $option ); ?>" <?php selected( $focus, $option ); ?>><?php echo esc_html( $option ); ?></option>
					<?php endforeach; ?>
				</select>
			</label>
			<label>
				<span><?php esc_html_e( 'Country / Region', 'gsf-hub' ); ?></span>
				<select name="case_region">
					<option value=""><?php esc_html_e( 'All countries/regions', 'gsf-hub' ); ?></option>
					<?php foreach ( $regions as $option ) : ?>
						<option value="<?php echo esc_attr( $option ); ?>" <?php selected( $region, $option ); ?>><?php echo esc_html( $option ); ?></option>
					<?php endforeach; ?>
				</select>
			</label>
			<div class="gsf-resource-library__filter-actions">
				<button class="button button--primary" type="submit"><?php esc_html_e( 'Apply filters', 'gsf-hub' ); ?></button>
				<a class="button button--secondary" href="<?php echo esc_url( $library_url . '#case-study-library' ); ?>">
					<?php esc_html_e( 'Reset', 'gsf-hub' ); ?>
				</a>
			</div>
		</form>

		<?php if ( $case_studies->have_posts() ) : ?>
			<div class="gsf-resource-library__grid">
				<?php
				while ( $case_studies->have_posts() ) :
					$case_studies->the_post();

					$post_id       = get_the_ID();
					$case_type     = get_post_meta( $post_id, 'case_study_type', true );
					$focus_area    = get_post_meta( $post_id, 'focus_area', true );
					$region_label  = get_post_meta( $post_id, 'country_region', true );
					$case_date     = get_post_meta( $post_id, 'implementation_date', true );
					$date_label    = '';

					if ( $case_date ) {
						$date_timestamp = strtotime( $case_date );
						$date_label     = $date_timestamp ? wp_date( 'M j, Y', $date_timestamp ) : $case_date;
					}
					?>
					<article class="gsf-resource-card gsf-case-study-card">
						<?php if ( has_post_thumbnail() ) : ?>
							<a class="gsf-case-study-card__image" href="<?php the_permalink(); ?>">
								<?php the_post_thumbnail( 'medium_large' ); ?>
							</a>
						<?php endif; ?>
						<div class="gsf-resource-card__meta">
							<?php if ( $case_type ) : ?>
								<span><?php echo esc_html( $case_type ); ?></span>
							<?php endif; ?>
							<?php if ( $date_label ) : ?>
								<span><?php echo esc_html( $date_label ); ?></span>
							<?php endif; ?>
						</div>
						<h3><a href="<?php echo esc_url( get_permalink() ); ?>"><?php the_title(); ?></a></h3>
						<?php if ( has_excerpt() ) : ?>
							<p><?php echo esc_html( get_the_excerpt() ); ?></p>
						<?php else : ?>
							<p><?php echo esc_html( wp_trim_words( wp_strip_all_tags( get_the_content() ), 24 ) ); ?></p>
						<?php endif; ?>
						<div class="gsf-resource-card__details">
							<?php if ( $focus_area ) : ?>
								<span><?php echo esc_html( $focus_area ); ?></span>
							<?php endif; ?>
							<?php if ( $region_label ) : ?>
								<span><?php echo esc_html( $region_label ); ?></span>
							<?php endif; ?>
						</div>
						<a class="button button--secondary button--full" href="<?php echo esc_url( get_permalink() ); ?>">
							<?php esc_html_e( 'Read case study', 'gsf-hub' ); ?>
						</a>
					</article>
				<?php endwhile; ?>
			</div>
			<?php wp_reset_postdata(); ?>
		<?php else : ?>
			<div class="gsf-resource-library__empty">
				<h3><?php esc_html_e( 'No case studies found', 'gsf-hub' ); ?></h3>
				<p><?php esc_html_e( 'Add case studies from WP Admin -> Case Studies, or clear the filters and try again.', 'gsf-hub' ); ?></p>
			</div>
		<?php endif; ?>
	</section>
	<?php
	return ob_get_clean();
}
add_shortcode( 'gsf_case_study_library', 'gsf_hub_sunrise_case_study_library_shortcode' );

/**
 * Redirects synthetic admin section landing pages to their primary screen.
 *
 * @param string $target Admin-relative target URL.
 */
function gsf_hub_sunrise_admin_redirect_to( $target ) {
	wp_safe_redirect( admin_url( $target ) );
	exit;
}

/**
 * Returns an admin edit URL for a page slug.
 *
 * @param string $slug Page slug.
 * @return string
 */
function gsf_hub_sunrise_admin_page_edit_target( $slug ) {
	$page = get_page_by_path( $slug );

	if ( $page instanceof WP_Post ) {
		return 'post.php?post=' . (int) $page->ID . '&action=edit';
	}

	return 'edit.php?post_type=page';
}

/**
 * Reorganizes the admin sidebar around GSF Hub platform sections.
 */
function gsf_hub_sunrise_register_platform_admin_menus() {
	if ( ! is_admin() ) {
		return;
	}

	add_menu_page(
		__( 'Learn', 'gsf-hub' ),
		__( 'Learn', 'gsf-hub' ),
		'edit_posts',
		'gsf-admin-learn',
		static function () {
			gsf_hub_sunrise_admin_redirect_to( 'edit.php?post_type=gsf_course' );
		},
		'dashicons-welcome-learn-more',
		56
	);
	add_submenu_page( 'gsf-admin-learn', __( 'Courses', 'gsf-hub' ), __( 'Courses', 'gsf-hub' ), 'edit_posts', 'edit.php?post_type=gsf_course' );
	add_submenu_page( 'gsf-admin-learn', __( 'Add Course', 'gsf-hub' ), __( 'Add Course', 'gsf-hub' ), 'edit_posts', 'post-new.php?post_type=gsf_course' );
	add_submenu_page( 'gsf-admin-learn', __( 'Upload SCORM Package', 'gsf-hub' ), __( 'Upload SCORM Package', 'gsf-hub' ), 'gsf_manage_learn', 'gsf-scorm-lite' );
	add_submenu_page( 'gsf-admin-learn', __( 'Student Progress', 'gsf-hub' ), __( 'Student Progress', 'gsf-hub' ), 'gsf_manage_learn', 'gsf-scorm-lite-progress' );
	add_submenu_page( 'gsf-admin-learn', __( 'Certificate Settings', 'gsf-hub' ), __( 'Certificate Settings', 'gsf-hub' ), 'gsf_manage_learn', 'gsf-scorm-lite-certificates' );

	add_menu_page(
		__( 'Resources', 'gsf-hub' ),
		__( 'Resources', 'gsf-hub' ),
		'edit_posts',
		'gsf-admin-resources',
		static function () {
			gsf_hub_sunrise_admin_redirect_to( 'edit.php?post_type=gsf_resource' );
		},
		'dashicons-media-document',
		57
	);
	add_submenu_page( 'gsf-admin-resources', __( 'All Resources', 'gsf-hub' ), __( 'All Resources', 'gsf-hub' ), 'edit_posts', 'edit.php?post_type=gsf_resource' );
	add_submenu_page( 'gsf-admin-resources', __( 'Add Resource', 'gsf-hub' ), __( 'Add Resource', 'gsf-hub' ), 'edit_posts', 'post-new.php?post_type=gsf_resource' );
	add_submenu_page( 'gsf-admin-resources', __( 'Resources Page', 'gsf-hub' ), __( 'Resources Page', 'gsf-hub' ), 'edit_pages', gsf_hub_sunrise_admin_page_edit_target( 'resources' ) );

	add_menu_page(
		__( 'Case Studies', 'gsf-hub' ),
		__( 'Case Studies', 'gsf-hub' ),
		'edit_posts',
		'gsf-admin-case-studies',
		static function () {
			gsf_hub_sunrise_admin_redirect_to( 'edit.php?post_type=gsf_case_study' );
		},
		'dashicons-welcome-write-blog',
		58
	);
	add_submenu_page( 'gsf-admin-case-studies', __( 'All Case Studies', 'gsf-hub' ), __( 'All Case Studies', 'gsf-hub' ), 'edit_posts', 'edit.php?post_type=gsf_case_study' );
	add_submenu_page( 'gsf-admin-case-studies', __( 'Add Case Study', 'gsf-hub' ), __( 'Add Case Study', 'gsf-hub' ), 'edit_posts', 'post-new.php?post_type=gsf_case_study' );

	add_menu_page(
		__( 'Forum', 'gsf-hub' ),
		__( 'Forum', 'gsf-hub' ),
		'edit_posts',
		'gsf-admin-forum',
		static function () {
			gsf_hub_sunrise_admin_redirect_to( 'edit.php?post_type=forum' );
		},
		'dashicons-format-chat',
		59
	);
	add_submenu_page( 'gsf-admin-forum', __( 'Forums', 'gsf-hub' ), __( 'Forums', 'gsf-hub' ), 'edit_posts', 'edit.php?post_type=forum' );
	add_submenu_page( 'gsf-admin-forum', __( 'Topics', 'gsf-hub' ), __( 'Topics', 'gsf-hub' ), 'edit_posts', 'edit.php?post_type=topic' );
	add_submenu_page( 'gsf-admin-forum', __( 'Replies', 'gsf-hub' ), __( 'Replies', 'gsf-hub' ), 'edit_posts', 'edit.php?post_type=reply' );

	add_menu_page(
		__( 'Directory', 'gsf-hub' ),
		__( 'Directory', 'gsf-hub' ),
		'edit_posts',
		'gsf-admin-directory',
		static function () {
			gsf_hub_sunrise_admin_redirect_to( 'edit.php?post_type=expert_directory' );
		},
		'dashicons-groups',
		60
	);
	add_submenu_page( 'gsf-admin-directory', __( 'Experts', 'gsf-hub' ), __( 'Experts', 'gsf-hub' ), 'edit_posts', 'edit.php?post_type=expert_directory' );
	add_submenu_page( 'gsf-admin-directory', __( 'Add Expert', 'gsf-hub' ), __( 'Add Expert', 'gsf-hub' ), 'edit_posts', 'post-new.php?post_type=expert_directory' );
	add_submenu_page( 'gsf-admin-directory', __( 'Directory Page', 'gsf-hub' ), __( 'Directory Page', 'gsf-hub' ), 'edit_pages', gsf_hub_sunrise_admin_page_edit_target( 'directory' ) );

	remove_menu_page( 'gsf-scorm-lite' );
	remove_menu_page( 'edit.php?post_type=gsf_resource' );
	remove_menu_page( 'edit.php?post_type=gsf_case_study' );
	remove_menu_page( 'edit.php?post_type=expert_directory' );
	remove_menu_page( 'edit.php?post_type=forum' );
	remove_menu_page( 'edit.php?post_type=topic' );
	remove_menu_page( 'edit.php?post_type=reply' );
}
add_action( 'admin_menu', 'gsf_hub_sunrise_register_platform_admin_menus', 90 );

/**
 * Forces the Data Centre page to render the live PMF dashboard template.
 *
 * The page previously used a static canvas template; keeping this override in
 * code ensures the public route stays connected to the Pods-backed Data Centre.
 *
 * @param string $template Current resolved template.
 * @return string
 */
function gsf_hub_sunrise_data_centre_template( $template ) {
	if ( ! is_page( 'data-centre' ) ) {
		return $template;
	}

	$data_centre_template = get_stylesheet_directory() . '/page-data-centre.php';
	return file_exists( $data_centre_template ) ? $data_centre_template : $template;
}
add_filter( 'template_include', 'gsf_hub_sunrise_data_centre_template', 99 );
