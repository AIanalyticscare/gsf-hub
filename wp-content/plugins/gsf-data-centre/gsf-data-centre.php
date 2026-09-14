<?php
/**
 * Plugin Name: GSF Data Centre
 * Description: Managed dashboard content and shortcodes for the GSF Hub Data Centre.
 * Version: 0.3.9
 * Author: AI Analyticscare
 * Text Domain: gsf-data-centre
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once plugin_dir_path( __FILE__ ) . 'includes/class-gsf-data-centre-meal.php';

final class GSF_Data_Centre {
	const VERSION = '0.3.9';
	const DISPLAY_OPTION = 'gsf_data_centre_display_settings';
	const GRANTEE_PROJECT_SYNC_OPTION = 'gsf_data_centre_grantee_projects_synced';
	const GRANTEE_PROJECT_SYNC_VERSION = '2026071101';
	const PODS_SCHEMA_OPTION = 'gsf_data_centre_pods_schema_version';
	const PODS_SCHEMA_VERSION = '2026081101';

	public static function init() {
		if ( class_exists( 'GSF_Data_Centre_MEAL' ) ) {
			GSF_Data_Centre_MEAL::init();
		}

		add_filter( 'register_post_type_args', array( __CLASS__, 'filter_searchable_post_type_args' ), 20, 2 );
		add_action( 'init', array( __CLASS__, 'register_post_types' ) );
		add_action( 'add_meta_boxes', array( __CLASS__, 'register_meta_boxes' ) );
		add_action( 'add_meta_boxes_gsf_indicator', array( __CLASS__, 'remove_indicator_pods_meta_box' ), 100 );
		add_action( 'save_post_gsf_data_story', array( __CLASS__, 'save_story_meta' ) );
		add_action( 'save_post_gsf_indicator', array( __CLASS__, 'save_indicator_meta' ) );
		add_shortcode( 'gsf_data_centre', array( __CLASS__, 'render_shortcode' ) );
		add_action( 'admin_menu', array( __CLASS__, 'register_admin_menu' ), 92 );
		add_action( 'admin_menu', array( __CLASS__, 'cleanup_duplicate_admin_menus' ), 999 );
		add_action( 'admin_init', array( __CLASS__, 'maybe_upgrade_project_pods' ) );
		add_action( 'admin_init', array( __CLASS__, 'sync_grantee_projects' ), 20 );
		add_action( 'admin_post_gsf_data_centre_save_display', array( __CLASS__, 'save_display_settings' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'register_assets' ) );
		add_action( 'pre_get_posts', array( __CLASS__, 'include_data_centre_in_search' ) );
		add_filter( 'posts_search', array( __CLASS__, 'search_data_centre_meta' ), 20, 2 );
		add_filter( 'posts_distinct', array( __CLASS__, 'distinct_search_results' ), 20, 2 );
		add_filter( 'pll_get_post_types', array( __CLASS__, 'register_polylang_post_types' ), 10, 2 );

		foreach ( self::searchable_post_types() as $post_type ) {
			add_action( 'save_post_' . $post_type, array( __CLASS__, 'assign_default_language' ), 20 );
		}

		add_action( 'admin_init', array( __CLASS__, 'backfill_default_language' ) );
	}

	public static function activate() {
		self::register_post_types();
		if ( class_exists( 'GSF_Data_Centre_MEAL' ) ) {
			GSF_Data_Centre_MEAL::activate();
		}
		if ( self::ensure_project_pods() ) {
			update_option( self::PODS_SCHEMA_OPTION, self::PODS_SCHEMA_VERSION );
		}
		self::seed_defaults();
		self::sync_grantee_projects( true );
		flush_rewrite_rules();
	}

	public static function register_post_types() {
		register_post_type(
			'gsf_data_story',
			array(
				'labels'       => array(
					'name'          => __( 'Data Stories', 'gsf-data-centre' ),
					'singular_name' => __( 'Data Story', 'gsf-data-centre' ),
					'add_new_item'  => __( 'Add Data Story', 'gsf-data-centre' ),
					'edit_item'     => __( 'Edit Data Story', 'gsf-data-centre' ),
				),
				'public'       => true,
				'publicly_queryable' => true,
				'exclude_from_search' => false,
				'has_archive'  => false,
				'rewrite'      => array( 'slug' => 'data-stories' ),
				'show_ui'      => true,
				'show_in_menu' => false,
				'supports'     => array( 'title', 'editor', 'thumbnail', 'page-attributes' ),
				'capability_type' => 'post',
			)
		);

		if ( ! function_exists( 'pods_api' ) ) {
			register_post_type(
				'gsf_project',
				array(
					'labels'          => array(
						'name'          => __( 'Projects', 'gsf-data-centre' ),
						'singular_name' => __( 'Project', 'gsf-data-centre' ),
						'add_new_item'  => __( 'Add Project', 'gsf-data-centre' ),
						'edit_item'     => __( 'Edit Project', 'gsf-data-centre' ),
					),
					'public'          => true,
					'publicly_queryable' => true,
					'exclude_from_search' => false,
					'has_archive'     => false,
					'rewrite'         => array( 'slug' => 'data-centre/projects' ),
					'show_ui'         => true,
					'show_in_menu'    => false,
					'supports'        => array( 'title', 'page-attributes' ),
					'capability_type' => 'post',
				)
			);
		}
	}

	/**
	 * Makes data centre record post types available to frontend search.
	 *
	 * @param array<string,mixed> $args      Post type args.
	 * @param string              $post_type Post type key.
	 * @return array<string,mixed>
	 */
	public static function filter_searchable_post_type_args( $args, $post_type ) {
		if ( ! in_array( $post_type, self::searchable_post_types(), true ) ) {
			return $args;
		}

		$args['public']              = true;
		$args['exclude_from_search'] = false;
		$args['publicly_queryable']  = true;
		$args['has_archive']         = false;

		if ( empty( $args['rewrite'] ) || ! is_array( $args['rewrite'] ) ) {
			$args['rewrite'] = array();
		}

		if ( 'gsf_project' === $post_type ) {
			$args['rewrite']['slug'] = 'data-centre/projects';
		} elseif ( 'gsf_data_story' === $post_type ) {
			$args['rewrite']['slug'] = 'data-stories';
		} elseif ( 'gsf_indicator' === $post_type ) {
			$args['rewrite']['slug'] = 'data-centre/indicators';
		} elseif ( 'gsf_ind_result' === $post_type ) {
			$args['rewrite']['slug'] = 'data-centre/results';
		} elseif ( 'gsf_ecoequity' === $post_type ) {
			$args['rewrite']['slug'] = 'data-centre/ecoequity';
		}

		return $args;
	}

	/**
	 * Returns Data Centre post types that should participate in public search.
	 *
	 * @return string[]
	 */
	private static function searchable_post_types() {
		return array( 'gsf_project', 'gsf_indicator', 'gsf_ind_result', 'gsf_ecoequity', 'gsf_data_story' );
	}

	/**
	 * Registers Data Centre records as translatable Polylang post types.
	 *
	 * @param string[] $post_types  Existing post types.
	 * @param bool     $is_settings Whether Polylang is loading settings UI context.
	 * @return string[]
	 */
	public static function register_polylang_post_types( $post_types, $is_settings ) {
		unset( $is_settings );

		foreach ( self::searchable_post_types() as $post_type ) {
			$post_types[] = $post_type;
		}

		return array_values( array_unique( $post_types ) );
	}

	/**
	 * Returns the active Polylang language slug for frontend Data Centre queries.
	 *
	 * @return string
	 */
	private static function current_language() {
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
	 * Returns Data Centre meta keys searched by the global site search.
	 *
	 * @return string[]
	 */
	private static function searchable_meta_keys() {
		return array(
			'project_type',
			'country',
			'organizations',
			'implementing_party',
			'funding_source',
			'milestone_output',
			'project_status',
			'gender_responsiveness',
			'indicator_code',
			'indicator_level',
			'indicator_text',
			'baseline',
			'target',
			'frequency',
			'method',
			'source',
			'responsible_party',
			'reporting_year_period',
			'result_value',
			'result_narrative',
			'organization_name',
			'organization_type',
		);
	}

	/**
	 * Returns whether a query is the frontend global search query.
	 *
	 * @param WP_Query $query Query object.
	 * @return bool
	 */
	private static function is_frontend_search_query( $query ) {
		return $query instanceof WP_Query && ! is_admin() && $query->is_main_query() && $query->is_search();
	}

	/**
	 * Includes Data Centre records in the global frontend search results.
	 *
	 * @param WP_Query $query Query object.
	 * @return void
	 */
	public static function include_data_centre_in_search( $query ) {
		if ( ! self::is_frontend_search_query( $query ) ) {
			return;
		}

		$post_types = $query->get( 'post_type' );

		if ( empty( $post_types ) || 'any' === $post_types ) {
			$post_types = get_post_types(
				array(
					'public'              => true,
					'exclude_from_search' => false,
				),
				'names'
			);
		}

		if ( is_string( $post_types ) ) {
			$post_types = array( $post_types );
		}

		$post_types = array_merge( (array) $post_types, self::searchable_post_types() );
		$post_types = array_diff( $post_types, array( 'attachment' ) );
		$post_types = array_values( array_filter( array_unique( $post_types ), 'post_type_exists' ) );

		$query->set( 'post_type', $post_types );
	}

	/**
	 * Extends frontend search to Data Centre custom fields.
	 *
	 * @param string   $search Search SQL.
	 * @param WP_Query $query  Query object.
	 * @return string
	 */
	public static function search_data_centre_meta( $search, $query ) {
		if ( ! self::is_frontend_search_query( $query ) || '' === trim( $search ) ) {
			return $search;
		}

		global $wpdb;

		$terms = $query->get( 'search_terms' );

		if ( empty( $terms ) ) {
			$raw_terms = preg_split( '/\s+/', (string) $query->get( 's' ) );
			$terms     = is_array( $raw_terms ) ? $raw_terms : array();
		}

		$terms = array_values(
			array_filter(
				array_map(
					static function ( $term ) {
						return trim( (string) $term );
					},
					(array) $terms
				)
			)
		);

		if ( empty( $terms ) ) {
			return $search;
		}

		$meta_like_parts = array();

		foreach ( $terms as $term ) {
			$meta_like_parts[] = $wpdb->prepare( 'pm.meta_value LIKE %s', '%' . $wpdb->esc_like( $term ) . '%' );
		}

		$post_type_placeholders = implode( ', ', array_fill( 0, count( self::searchable_post_types() ), '%s' ) );
		$meta_key_placeholders  = implode( ', ', array_fill( 0, count( self::searchable_meta_keys() ), '%s' ) );
		$core_search            = preg_replace( '/^\s*AND\s+/i', '', $search );

		if ( '' === trim( (string) $core_search ) ) {
			return $search;
		}

		$current_language = self::current_language();
		$language_join    = '';
		$language_where   = '';
		$query_values     = array_merge( self::searchable_post_types(), self::searchable_meta_keys() );

		if ( $current_language && function_exists( 'pll_current_language' ) ) {
			$language_join  = " INNER JOIN {$wpdb->term_relationships} pll_rel ON pll_rel.object_id = pmeta.ID
				INNER JOIN {$wpdb->term_taxonomy} pll_tt ON pll_tt.term_taxonomy_id = pll_rel.term_taxonomy_id
				INNER JOIN {$wpdb->terms} pll_term ON pll_term.term_id = pll_tt.term_id";
			$language_where = ' AND pll_tt.taxonomy = %s AND pll_term.slug = %s';
			$query_values[] = 'language';
			$query_values[] = $current_language;
		}

		$meta_search = $wpdb->prepare(
			"{$wpdb->posts}.ID IN (
				SELECT pm.post_id
				FROM {$wpdb->postmeta} pm
				INNER JOIN {$wpdb->posts} pmeta ON pmeta.ID = pm.post_id
				{$language_join}
				WHERE pmeta.post_type IN ({$post_type_placeholders})
					AND pm.meta_key IN ({$meta_key_placeholders})
					AND (" . implode( ' OR ', $meta_like_parts ) . ")
					{$language_where}
			)",
			$query_values
		);

		return ' AND ( ' . $core_search . ' OR ' . $meta_search . ' ) ';
	}

	/**
	 * Keeps search results unique when meta search is active.
	 *
	 * @param string   $distinct Distinct clause.
	 * @param WP_Query $query    Query object.
	 * @return string
	 */
	public static function distinct_search_results( $distinct, $query ) {
		if ( self::is_frontend_search_query( $query ) ) {
			return 'DISTINCT';
		}

		return $distinct;
	}

	/**
	 * Assigns the default Polylang language to Data Centre records.
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	public static function assign_default_language( $post_id ) {
		if ( ! function_exists( 'pll_get_post_language' ) || ! function_exists( 'pll_set_post_language' ) || wp_is_post_revision( $post_id ) ) {
			return;
		}

		if ( pll_get_post_language( $post_id ) ) {
			return;
		}

		$default_language = function_exists( 'pll_default_language' ) ? pll_default_language( 'slug' ) : 'en';

		if ( $default_language ) {
			pll_set_post_language( $post_id, $default_language );
		}
	}

	/**
	 * Backfills default language assignment for existing Data Centre records.
	 *
	 * @return void
	 */
	public static function backfill_default_language() {
		if ( get_option( 'gsf_data_centre_language_backfilled' ) === self::VERSION || ! function_exists( 'pll_get_post_language' ) ) {
			return;
		}

		$records = get_posts(
			array(
				'post_type'        => self::searchable_post_types(),
				'post_status'      => 'any',
				'posts_per_page'   => -1,
				'fields'           => 'ids',
				'suppress_filters' => true,
			)
		);

		foreach ( $records as $post_id ) {
			self::assign_default_language( (int) $post_id );
		}

		update_option( 'gsf_data_centre_language_backfilled', self::VERSION );
	}

	public static function register_admin_menu() {
		$callback = class_exists( 'GSF_Data_Centre_MEAL' )
			? array( 'GSF_Data_Centre_MEAL', 'render_pmf_admin_page' )
			: array( __CLASS__, 'render_data_entry_page' );

		add_menu_page(
			__( 'Data Centre', 'gsf-data-centre' ),
			__( 'Data Centre', 'gsf-data-centre' ),
			'gsf_view_dashboard',
			'gsf-data-centre',
			$callback,
			'dashicons-chart-area',
			27
		);

		add_submenu_page(
			'gsf-data-centre',
			__( 'Display Settings', 'gsf-data-centre' ),
			__( 'Display Settings', 'gsf-data-centre' ),
			'manage_options',
			'gsf-data-centre-display',
			array( __CLASS__, 'render_display_settings_page' )
		);
	}

	public static function cleanup_duplicate_admin_menus() {
		foreach ( array( 'gsf_project', 'gsf_indicator', 'gsf_ind_result', 'gsf_ecoequity', 'gsf_organization', 'gsf_evidence', 'gsf_activity', 'gsf_index', 'gsf_data_story' ) as $post_type ) {
			remove_menu_page( 'edit.php?post_type=' . $post_type );
		}
	}

	public static function register_assets() {
		$css_path = plugin_dir_path( __FILE__ ) . 'assets/data-centre.css';
		$js_path  = plugin_dir_path( __FILE__ ) . 'assets/data-centre-charts.js';
		$export_js_path = plugin_dir_path( __FILE__ ) . 'assets/data-centre-export.js';

		wp_register_style(
			'leaflet',
			'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css',
			array(),
			'1.9.4'
		);

		wp_register_style(
			'gsf-data-centre',
			plugins_url( 'assets/data-centre.css', __FILE__ ),
			array( 'leaflet' ),
			file_exists( $css_path ) ? (string) filemtime( $css_path ) : self::VERSION
		);

		wp_register_script(
			'leaflet',
			'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js',
			array(),
			'1.9.4',
			true
		);

		wp_register_script(
			'chartjs',
			'https://cdn.jsdelivr.net/npm/chart.js@4.4.9/dist/chart.umd.min.js',
			array(),
			'4.4.9',
			true
		);

		wp_register_script(
			'gsf-data-centre-charts',
			plugins_url( 'assets/data-centre-charts.js', __FILE__ ),
			array( 'chartjs', 'leaflet' ),
			file_exists( $js_path ) ? (string) filemtime( $js_path ) : self::VERSION,
			true
		);

		wp_register_script(
			'gsf-data-centre-export',
			plugins_url( 'assets/data-centre-export.js', __FILE__ ),
			array(),
			file_exists( $export_js_path ) ? (string) filemtime( $export_js_path ) : self::VERSION,
			true
		);
	}

	public static function render_data_entry_page() {
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'gsf-data-centre' ) );
		}
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Data Centre', 'gsf-data-centre' ); ?></h1>
			<p><?php esc_html_e( 'Use Data Entry to manage the numbers and stories. Use Display Settings to choose how the public dashboard presents charts and maps.', 'gsf-data-centre' ); ?></p>
			<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:16px;max-width:980px;margin-top:20px;">
				<?php
				self::admin_card( __( 'Stories', 'gsf-data-centre' ), __( 'Featured data story and story-level outcome figures.', 'gsf-data-centre' ), admin_url( 'edit.php?post_type=gsf_data_story' ), __( 'Edit stories', 'gsf-data-centre' ) );
				self::admin_card( __( 'Projects', 'gsf-data-centre' ), __( 'Track country, type, organizations, implementing party, gender responsiveness, outputs, status, and percent complete.', 'gsf-data-centre' ), admin_url( 'edit.php?post_type=gsf_project' ), __( 'Edit projects', 'gsf-data-centre' ) );
				self::admin_card( __( 'Indicators', 'gsf-data-centre' ), __( 'Manage indicator code, level, text, baseline, target, frequency, method, source, responsibility, and gender responsiveness.', 'gsf-data-centre' ), admin_url( 'edit.php?post_type=gsf_indicator' ), __( 'Edit indicators', 'gsf-data-centre' ) );
				self::admin_card( __( 'Indicator Results', 'gsf-data-centre' ), __( 'Enter one row per indicator per reporting year or period.', 'gsf-data-centre' ), admin_url( 'edit.php?post_type=gsf_ind_result' ), __( 'Edit results', 'gsf-data-centre' ) );
				self::admin_card( __( 'Ecoequity Scores', 'gsf-data-centre' ), __( 'Track organization-level baseline, mid-term, and endline scores for EWROs and NCTFs.', 'gsf-data-centre' ), admin_url( 'edit.php?post_type=gsf_ecoequity' ), __( 'Edit scores', 'gsf-data-centre' ) );
				self::admin_card( __( 'Display', 'gsf-data-centre' ), __( 'Select chart types for participation, completions, and territory data.', 'gsf-data-centre' ), admin_url( 'admin.php?page=gsf-data-centre-display' ), __( 'Display settings', 'gsf-data-centre' ) );
				?>
			</div>
		</div>
		<?php
	}

	private static function admin_card( $title, $description, $url, $button ) {
		?>
		<div style="background:#fff;border:1px solid #dcdcde;border-radius:8px;padding:18px;">
			<h2 style="margin-top:0;"><?php echo esc_html( $title ); ?></h2>
			<p><?php echo esc_html( $description ); ?></p>
			<a class="button button-primary" href="<?php echo esc_url( $url ); ?>"><?php echo esc_html( $button ); ?></a>
		</div>
		<?php
	}

	public static function render_display_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'gsf-data-centre' ) );
		}

		$settings = self::get_display_settings();
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Data Centre Display Settings', 'gsf-data-centre' ); ?></h1>
			<p><?php esc_html_e( 'Choose how each public dashboard section should render. These settings affect the [gsf_data_centre] shortcode.', 'gsf-data-centre' ); ?></p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="gsf_data_centre_save_display" />
				<?php wp_nonce_field( 'gsf_data_centre_save_display' ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="hero_eyebrow"><?php esc_html_e( 'Dashboard eyebrow', 'gsf-data-centre' ); ?></label></th>
						<td>
							<input class="regular-text" id="hero_eyebrow" name="hero_eyebrow" type="text" maxlength="80" value="<?php echo esc_attr( $settings['hero_eyebrow'] ); ?>" />
							<p class="description"><?php esc_html_e( 'The short label displayed above the dashboard title.', 'gsf-data-centre' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="hero_title"><?php esc_html_e( 'Dashboard title', 'gsf-data-centre' ); ?></label></th>
						<td><input class="large-text" id="hero_title" name="hero_title" type="text" maxlength="140" value="<?php echo esc_attr( $settings['hero_title'] ); ?>" /></td>
					</tr>
					<tr>
						<th scope="row"><label for="hero_description"><?php esc_html_e( 'Dashboard introduction', 'gsf-data-centre' ); ?></label></th>
						<td>
							<textarea class="large-text" id="hero_description" name="hero_description" rows="3" maxlength="500"><?php echo esc_textarea( $settings['hero_description'] ); ?></textarea>
							<p class="description"><?php esc_html_e( 'A short public description of what visitors can explore in the Data Centre.', 'gsf-data-centre' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="participation_chart"><?php esc_html_e( 'Participation chart', 'gsf-data-centre' ); ?></label></th>
						<td><?php self::select_field( 'participation_chart', $settings['participation_chart'], array( 'doughnut' => __( 'Doughnut', 'gsf-data-centre' ), 'pie' => __( 'Pie', 'gsf-data-centre' ), 'bar' => __( 'Bar', 'gsf-data-centre' ) ) ); ?></td>
					</tr>
					<tr>
						<th scope="row"><label for="completion_chart"><?php esc_html_e( 'Completion chart', 'gsf-data-centre' ); ?></label></th>
						<td><?php self::select_field( 'completion_chart', $settings['completion_chart'], array( 'line' => __( 'Line', 'gsf-data-centre' ), 'bar' => __( 'Bar', 'gsf-data-centre' ) ) ); ?></td>
					</tr>
					<tr>
						<th scope="row"><label for="territory_display"><?php esc_html_e( 'Territory display', 'gsf-data-centre' ); ?></label></th>
						<td><?php self::select_field( 'territory_display', $settings['territory_display'], array( 'map' => __( 'Map', 'gsf-data-centre' ), 'bar' => __( 'Bar chart', 'gsf-data-centre' ) ) ); ?></td>
					</tr>
				</table>
				<?php submit_button( __( 'Save display settings', 'gsf-data-centre' ) ); ?>
			</form>
		</div>
		<?php
	}

	private static function select_field( $name, $selected, array $options ) {
		printf( '<select id="%1$s" name="%1$s">', esc_attr( $name ) );
		foreach ( $options as $value => $label ) {
			printf( '<option value="%1$s" %2$s>%3$s</option>', esc_attr( $value ), selected( $selected, $value, false ), esc_html( $label ) );
		}
		echo '</select>';
	}

	public static function save_display_settings() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to save these settings.', 'gsf-data-centre' ) );
		}

		check_admin_referer( 'gsf_data_centre_save_display' );

		$defaults = self::display_defaults();
		$settings = array(
			'hero_eyebrow'        => self::sanitize_text_setting( 'hero_eyebrow', $defaults['hero_eyebrow'], 80 ),
			'hero_title'          => self::sanitize_text_setting( 'hero_title', $defaults['hero_title'], 140 ),
			'hero_description'    => self::sanitize_text_setting( 'hero_description', $defaults['hero_description'], 500, true ),
			'participation_chart' => self::sanitize_choice( 'participation_chart', array( 'doughnut', 'pie', 'bar' ), 'doughnut' ),
			'completion_chart'    => self::sanitize_choice( 'completion_chart', array( 'line', 'bar' ), 'line' ),
			'territory_display'   => self::sanitize_choice( 'territory_display', array( 'map', 'bar' ), 'map' ),
		);

		update_option( self::DISPLAY_OPTION, $settings );
		wp_safe_redirect( add_query_arg( 'updated', '1', admin_url( 'admin.php?page=gsf-data-centre-display' ) ) );
		exit;
	}

	private static function sanitize_choice( $key, array $allowed, $default ) {
		$value = sanitize_key( wp_unslash( $_POST[ $key ] ?? $default ) );
		return in_array( $value, $allowed, true ) ? $value : $default;
	}

	private static function sanitize_text_setting( $key, $default, $max_length, $textarea = false ) {
		$value = isset( $_POST[ $key ] ) ? wp_unslash( $_POST[ $key ] ) : $default;
		$value = $textarea ? sanitize_textarea_field( $value ) : sanitize_text_field( $value );
		$value = trim( $value );

		if ( '' === $value ) {
			return $default;
		}

		return wp_html_excerpt( $value, $max_length, '' );
	}

	private static function display_defaults() {
		return array(
			'hero_eyebrow'        => __( 'Data Centre', 'gsf-data-centre' ),
			'hero_title'          => __( 'Storytelling dashboard', 'gsf-data-centre' ),
			'hero_description'    => __( 'WordPress-friendly impact storytelling for Caribbean projects, learning, participation, programme outcomes, and field stories.', 'gsf-data-centre' ),
			'participation_chart' => 'doughnut',
			'completion_chart'    => 'line',
			'territory_display'   => 'map',
		);
	}

	private static function get_display_settings() {
		$defaults = self::display_defaults();
		$saved    = get_option( self::DISPLAY_OPTION, array() );
		return wp_parse_args( is_array( $saved ) ? $saved : array(), $defaults );
	}

	/**
	 * Runs Pods schema changes once for each plugin schema version.
	 *
	 * Re-saving every field on every wp-admin request can conflict with Pods'
	 * cached field objects and makes ordinary screens such as Add Project mutate
	 * configuration. Versioning keeps schema work out of normal admin requests.
	 */
	public static function maybe_upgrade_project_pods() {
		if ( self::PODS_SCHEMA_VERSION === get_option( self::PODS_SCHEMA_OPTION ) ) {
			return;
		}

		if ( self::ensure_project_pods() ) {
			update_option( self::PODS_SCHEMA_OPTION, self::PODS_SCHEMA_VERSION );
		}
	}

	/**
	 * Ensures the Data Centre Pods and fields exist.
	 *
	 * @return bool True when the schema is available or Pods is not installed.
	 */
	public static function ensure_project_pods() {
		if ( ! function_exists( 'pods_api' ) ) {
			return true;
		}

		$results = array(
			self::ensure_project_pod(),
			self::ensure_indicator_pod(),
			self::ensure_indicator_result_pod(),
			self::ensure_ecoequity_score_pod(),
			self::ensure_case_study_project_field(),
		);

		return ! in_array( false, $results, true );
	}

	private static function ensure_project_pod() {
		$api = pods_api();
		$pod = self::load_pod( 'gsf_project' );

		if ( ! self::pod_id( $pod ) ) {
			$params = post_type_exists( 'gsf_project' )
				? array(
					'create_extend'    => 'extend',
					'extend_pod_type'  => 'post_type',
					'extend_post_type' => 'gsf_project',
					'extend_storage'   => 'meta',
				)
				: array(
					'create_extend'             => 'create',
					'create_pod_type'           => 'post_type',
					'create_name'               => 'gsf_project',
					'create_label_singular'     => __( 'Project', 'gsf-data-centre' ),
					'create_label_plural'       => __( 'Projects', 'gsf-data-centre' ),
					'create_storage'            => 'meta',
					'create_public'             => 0,
					'create_publicly_queryable' => 0,
					'create_rest_api'           => 1,
				);

			$api->add_pod( $params );
			$pod = self::load_pod( 'gsf_project' );
		}

		$pod_id = self::pod_id( $pod );
		if ( ! $pod_id ) {
			return false;
		}

		$saved = $api->save_pod(
			array(
				'id'                 => $pod_id,
				'name'               => 'gsf_project',
				'label'              => __( 'Projects', 'gsf-data-centre' ),
				'label_singular'     => __( 'Project', 'gsf-data-centre' ),
				'type'               => 'post_type',
				'storage'            => 'meta',
				'public'             => 0,
				'publicly_queryable' => 0,
				'show_ui'            => 1,
				'show_in_menu'       => 0,
				'rest_enable'        => 1,
				'supports_title'     => 1,
				'supports_editor'    => 1,
				'supports_thumbnail' => 0,
			)
		);

		if ( false === $saved || is_wp_error( $saved ) ) {
			return false;
		}

		return self::save_pod_fields( 'gsf_project', self::project_pod_fields() );
	}

	private static function ensure_case_study_project_field() {
		if ( ! post_type_exists( 'gsf_case_study' ) ) {
			return true;
		}

		return self::save_pod_fields(
			'gsf_case_study',
			array(
				'related_projects' => array(
					'label'                 => __( 'Related Projects', 'gsf-data-centre' ),
					'type'                  => 'pick',
					'pick_object'           => 'post_type',
					'pick_val'              => 'gsf_project',
					'pick_format_type'      => 'multi',
					'pick_format_multi'     => 'list',
					'pick_show_select_text' => '1',
				),
			)
		);
	}

	private static function project_pod_fields() {
		return array(
			'project_type' => array(
				'label'                 => __( 'Type', 'gsf-data-centre' ),
				'type'                  => 'pick',
				'pick_object'           => 'custom-simple',
				'pick_custom'           => self::format_simple_options( array( 'Grant project', 'Technical assistance', 'Capacity building', 'Research', 'Policy support', 'Community initiative', 'Other' ) ),
				'pick_format_type'      => 'single',
				'pick_format_single'    => 'dropdown',
				'pick_show_select_text' => '1',
			),
			'country' => array(
				'label'                 => __( 'Country', 'gsf-data-centre' ),
				'type'                  => 'pick',
				'pick_object'           => 'custom-simple',
				'pick_custom'           => self::format_simple_options( self::country_values() ),
				'pick_format_type'      => 'single',
				'pick_format_single'    => 'dropdown',
				'pick_show_select_text' => '1',
			),
			'organizations' => array(
				'label'             => __( 'Organizations', 'gsf-data-centre' ),
				'type'              => 'text',
				'text_max_length'   => '180',
				'repeatable'        => '1',
				'repeatable_format' => 'default',
			),
			'implementing_party' => array(
				'label'           => __( 'Implementing Party', 'gsf-data-centre' ),
				'type'            => 'text',
				'text_max_length' => '180',
			),
			'funding_source' => array(
				'label'           => __( 'Funding Source', 'gsf-data-centre' ),
				'type'            => 'text',
				'text_max_length' => '180',
			),
			'milestone_output' => array(
				'label' => __( 'Milestone / Output', 'gsf-data-centre' ),
				'type'  => 'paragraph',
			),
			'project_status' => array(
				'label'                 => __( 'Status', 'gsf-data-centre' ),
				'type'                  => 'pick',
				'pick_object'           => 'custom-simple',
				'pick_custom'           => self::format_simple_options( array( 'Not started', 'In progress', 'At risk', 'Delayed', 'Completed' ) ),
				'pick_format_type'      => 'single',
				'pick_format_single'    => 'dropdown',
				'pick_show_select_text' => '1',
			),
			'percent_complete' => array(
				'label'             => __( 'Percent Complete', 'gsf-data-centre' ),
				'type'              => 'number',
				'number_format'     => '9999.99',
				'number_decimals'   => '0',
				'number_min'        => '0',
				'number_max'        => '100',
				'number_step'       => '1',
			),
			'gender_responsiveness' => array(
				'label'           => __( 'Gender Responsiveness', 'gsf-data-centre' ),
				'type'            => 'text',
				'text_max_length' => '220',
			),
			'people_affected_total' => array(
				'label'           => __( 'People Affected: Total', 'gsf-data-centre' ),
				'type'            => 'number',
				'number_decimals' => '0',
				'number_min'      => '0',
				'number_step'     => '1',
			),
			'people_affected_female' => array(
				'label'           => __( 'People Affected: Female / Women', 'gsf-data-centre' ),
				'type'            => 'number',
				'number_decimals' => '0',
				'number_min'      => '0',
				'number_step'     => '1',
			),
			'people_affected_male' => array(
				'label'           => __( 'People Affected: Male / Men', 'gsf-data-centre' ),
				'type'            => 'number',
				'number_decimals' => '0',
				'number_min'      => '0',
				'number_step'     => '1',
			),
			'people_affected_other' => array(
				'label'           => __( 'People Affected: Other / Prefer not to say', 'gsf-data-centre' ),
				'type'            => 'number',
				'number_decimals' => '0',
				'number_min'      => '0',
				'number_step'     => '1',
			),
			'people_affected_non_indigenous_male' => array(
				'label'           => __( 'People Affected: Non-Indigenous Male', 'gsf-data-centre' ),
				'type'            => 'number',
				'number_decimals' => '0',
				'number_min'      => '0',
				'number_step'     => '1',
			),
			'people_affected_non_indigenous_female' => array(
				'label'           => __( 'People Affected: Non-Indigenous Female', 'gsf-data-centre' ),
				'type'            => 'number',
				'number_decimals' => '0',
				'number_min'      => '0',
				'number_step'     => '1',
			),
			'people_affected_indigenous_male' => array(
				'label'           => __( 'People Affected: Indigenous Male', 'gsf-data-centre' ),
				'type'            => 'number',
				'number_decimals' => '0',
				'number_min'      => '0',
				'number_step'     => '1',
			),
			'people_affected_indigenous_female' => array(
				'label'           => __( 'People Affected: Indigenous Female', 'gsf-data-centre' ),
				'type'            => 'number',
				'number_decimals' => '0',
				'number_min'      => '0',
				'number_step'     => '1',
			),
			'climate_hazard_score' => array(
				'label'           => __( 'Resilience Index: Climate Hazard', 'gsf-data-centre' ),
				'type'            => 'number',
				'number_decimals' => '2',
				'number_min'      => '0',
				'number_max'      => '100',
				'number_step'     => '0.01',
			),
			'habitat_quality_score' => array(
				'label'           => __( 'Resilience Index: Habitat Quality / Ecological Condition / Environmental Resilience', 'gsf-data-centre' ),
				'type'            => 'number',
				'number_decimals' => '2',
				'number_min'      => '0',
				'number_max'      => '100',
				'number_step'     => '0.01',
			),
			'social_economic_resilience_score' => array(
				'label'           => __( 'Resilience Index: Social / Economic Resilience', 'gsf-data-centre' ),
				'type'            => 'number',
				'number_decimals' => '2',
				'number_min'      => '0',
				'number_max'      => '100',
				'number_step'     => '0.01',
			),
			'durability_score' => array(
				'label'           => __( 'Resilience Index: Durability', 'gsf-data-centre' ),
				'type'            => 'number',
				'number_decimals' => '2',
				'number_min'      => '0',
				'number_max'      => '100',
				'number_step'     => '0.01',
			),
		);
	}

	private static function ensure_indicator_pod() {
		self::ensure_simple_pod( 'gsf_indicator', __( 'Indicator', 'gsf-data-centre' ), __( 'Indicators', 'gsf-data-centre' ) );
		return self::save_pod_fields(
			'gsf_indicator',
			array(
				'indicator_code' => array(
					'label'           => __( 'Indicator Code', 'gsf-data-centre' ),
					'type'            => 'text',
					'text_max_length' => '80',
				),
				'indicator_level' => array(
					'label'                 => __( 'Level', 'gsf-data-centre' ),
					'type'                  => 'pick',
					'pick_object'           => 'custom-simple',
					'pick_custom'           => self::format_simple_options( array( 'Impact', 'Outcome', 'Output', 'Activity', 'Project', 'Organization' ) ),
					'pick_format_type'      => 'single',
					'pick_format_single'    => 'dropdown',
					'pick_show_select_text' => '1',
				),
				'indicator_text' => array(
					'label' => __( 'Indicator Text', 'gsf-data-centre' ),
					'type'  => 'paragraph',
				),
				'baseline' => array(
					'label'           => __( 'Baseline', 'gsf-data-centre' ),
					'type'            => 'text',
					'text_max_length' => '160',
				),
				'target' => array(
					'label'           => __( 'Target', 'gsf-data-centre' ),
					'type'            => 'text',
					'text_max_length' => '160',
				),
				'frequency' => array(
					'label'                 => __( 'Frequency', 'gsf-data-centre' ),
					'type'                  => 'pick',
					'pick_object'           => 'custom-simple',
					'pick_custom'           => self::format_simple_options( array( 'Monthly', 'Quarterly', 'Semi-annual', 'Annual', 'Mid-term', 'Endline', 'As needed' ) ),
					'pick_format_type'      => 'single',
					'pick_format_single'    => 'dropdown',
					'pick_show_select_text' => '1',
				),
				'method' => array(
					'label' => __( 'Method', 'gsf-data-centre' ),
					'type'  => 'paragraph',
				),
				'source' => array(
					'label'           => __( 'Source', 'gsf-data-centre' ),
					'type'            => 'text',
					'text_max_length' => '180',
				),
				'responsible_party' => array(
					'label'           => __( 'Responsible Party', 'gsf-data-centre' ),
					'type'            => 'text',
					'text_max_length' => '180',
				),
				'gender_responsiveness' => array(
					'label'           => __( 'Gender Responsiveness', 'gsf-data-centre' ),
					'type'            => 'text',
					'text_max_length' => '220',
				),
			)
		);
	}

	private static function ensure_indicator_result_pod() {
		self::ensure_simple_pod( 'gsf_ind_result', __( 'Indicator Result', 'gsf-data-centre' ), __( 'Indicator Results', 'gsf-data-centre' ) );
		return self::save_pod_fields(
			'gsf_ind_result',
			array(
				'indicator' => array(
					'label'                 => __( 'Indicator', 'gsf-data-centre' ),
					'type'                  => 'pick',
					'pick_object'           => 'post_type',
					'pick_val'              => 'gsf_indicator',
					'pick_format_type'      => 'single',
					'pick_format_single'    => 'dropdown',
					'pick_show_select_text' => '1',
				),
				'reporting_year_period' => array(
					'label'           => __( 'Reporting Year / Period', 'gsf-data-centre' ),
					'type'            => 'text',
					'text_max_length' => '80',
				),
				'quarter' => array(
					'label'           => __( 'Quarter', 'gsf-data-centre' ),
					'type'            => 'text',
					'text_max_length' => '50',
				),
				'result_value' => array(
					'label'           => __( 'Result Value', 'gsf-data-centre' ),
					'type'            => 'text',
					'text_max_length' => '160',
				),
				'value_numeric' => array(
					'label'           => __( 'Numeric Value', 'gsf-data-centre' ),
					'type'            => 'text',
					'text_max_length' => '160',
				),
				'value_text' => array(
					'label' => __( 'Text / Milestone Value', 'gsf-data-centre' ),
					'type'  => 'paragraph',
				),
				'result_narrative' => array(
					'label' => __( 'Result Narrative', 'gsf-data-centre' ),
					'type'  => 'paragraph',
				),
				'reporting_status' => array(
					'label'                 => __( 'Reporting Status', 'gsf-data-centre' ),
					'type'                  => 'pick',
					'pick_object'           => 'custom-simple',
					'pick_custom'           => self::format_simple_options( array( 'draft', 'submitted', 'reported', 'verified', 'pending', 'partial_data', 'no_data' ) ),
					'pick_format_type'      => 'single',
					'pick_format_single'    => 'dropdown',
					'pick_show_select_text' => '1',
				),
				'evidence_comment' => array(
					'label' => __( 'Evidence Comment', 'gsf-data-centre' ),
					'type'  => 'paragraph',
				),
				'data_quality_note' => array(
					'label' => __( 'Data Quality Note', 'gsf-data-centre' ),
					'type'  => 'paragraph',
				),
				'project' => array(
					'label'                 => __( 'Project', 'gsf-data-centre' ),
					'type'                  => 'pick',
					'pick_object'           => 'post_type',
					'pick_val'              => 'gsf_project',
					'pick_format_type'      => 'single',
					'pick_format_single'    => 'dropdown',
					'pick_show_select_text' => '1',
				),
			)
		);
	}

	private static function ensure_ecoequity_score_pod() {
		self::ensure_simple_pod( 'gsf_ecoequity', __( 'Ecoequity Score', 'gsf-data-centre' ), __( 'Ecoequity Scores', 'gsf-data-centre' ) );
		return self::save_pod_fields(
			'gsf_ecoequity',
			array(
				'score_upload_id' => array(
					'label'           => __( 'Score Upload ID', 'gsf-data-centre' ),
					'type'            => 'text',
					'text_max_length' => '80',
				),
				'index_id' => array(
					'label'           => __( 'Index ID', 'gsf-data-centre' ),
					'type'            => 'text',
					'text_max_length' => '80',
				),
				'organization_id' => array(
					'label'           => __( 'Organization ID', 'gsf-data-centre' ),
					'type'            => 'text',
					'text_max_length' => '180',
				),
				'organization_name' => array(
					'label'           => __( 'Organization Name', 'gsf-data-centre' ),
					'type'            => 'text',
					'text_max_length' => '180',
				),
				'organization_type' => array(
					'label'                 => __( 'Organization Type', 'gsf-data-centre' ),
					'type'                  => 'pick',
					'pick_object'           => 'custom-simple',
					'pick_custom'           => self::format_simple_options( array( 'EWRO', 'NCTF', 'Environmental Organization', 'Women’s Rights Organization', 'Community-based Organization', 'Other' ) ),
					'pick_format_type'      => 'single',
					'pick_format_single'    => 'dropdown',
					'pick_show_select_text' => '1',
				),
				'country' => array(
					'label'           => __( 'Country', 'gsf-data-centre' ),
					'type'            => 'text',
					'text_max_length' => '120',
				),
				'reporting_period' => array(
					'label'           => __( 'Reporting Period', 'gsf-data-centre' ),
					'type'            => 'text',
					'text_max_length' => '80',
				),
				'assessment_stage' => array(
					'label'           => __( 'Assessment Stage', 'gsf-data-centre' ),
					'type'            => 'text',
					'text_max_length' => '80',
				),
				'total_score' => array(
					'label'           => __( 'Total Score', 'gsf-data-centre' ),
					'type'            => 'number',
					'number_decimals' => '2',
					'number_min'      => '0',
					'number_max'      => '100',
					'number_step'     => '0.01',
				),
				'score_status' => array(
					'label'           => __( 'Score Status', 'gsf-data-centre' ),
					'type'            => 'text',
					'text_max_length' => '80',
				),
				'evidence_title' => array(
					'label'           => __( 'Evidence Title', 'gsf-data-centre' ),
					'type'            => 'text',
					'text_max_length' => '180',
				),
				'source_sheet' => array(
					'label'           => __( 'Source Sheet', 'gsf-data-centre' ),
					'type'            => 'text',
					'text_max_length' => '180',
				),
				'notes' => array(
					'label' => __( 'Notes', 'gsf-data-centre' ),
					'type'  => 'paragraph',
				),
				'baseline_score' => array(
					'label'           => __( 'Organization-level Baseline Score', 'gsf-data-centre' ),
					'type'            => 'number',
					'number_decimals' => '2',
					'number_min'      => '0',
					'number_max'      => '100',
					'number_step'     => '0.01',
				),
				'midterm_score' => array(
					'label'           => __( 'Organization-level Mid-term Score', 'gsf-data-centre' ),
					'type'            => 'number',
					'number_decimals' => '2',
					'number_min'      => '0',
					'number_max'      => '100',
					'number_step'     => '0.01',
				),
				'endline_score' => array(
					'label'           => __( 'Organization-level Endline Score', 'gsf-data-centre' ),
					'type'            => 'number',
					'number_decimals' => '2',
					'number_min'      => '0',
					'number_max'      => '100',
					'number_step'     => '0.01',
				),
				'ecoequity_category' => array(
					'label' => __( 'Ecoequity Category', 'gsf-data-centre' ),
					'type'  => 'paragraph',
				),
				'criteria_activity' => array(
					'label' => __( 'Criteria / Activity', 'gsf-data-centre' ),
					'type'  => 'paragraph',
				),
				'description_relevance' => array(
					'label' => __( 'Description / Relevance', 'gsf-data-centre' ),
					'type'  => 'paragraph',
				),
				'category_score' => array(
					'label'           => __( 'Category / Criteria Score', 'gsf-data-centre' ),
					'type'            => 'number',
					'number_decimals' => '2',
					'number_min'      => '0',
					'number_max'      => '100',
					'number_step'     => '0.01',
				),
				'category_baseline_score' => array(
					'label'           => __( 'Category Baseline Score', 'gsf-data-centre' ),
					'type'            => 'number',
					'number_decimals' => '2',
					'number_min'      => '0',
					'number_max'      => '100',
					'number_step'     => '0.01',
				),
				'category_midterm_score' => array(
					'label'           => __( 'Category Mid-term Score', 'gsf-data-centre' ),
					'type'            => 'number',
					'number_decimals' => '2',
					'number_min'      => '0',
					'number_max'      => '100',
					'number_step'     => '0.01',
				),
				'category_endline_score' => array(
					'label'           => __( 'Category Endline Score', 'gsf-data-centre' ),
					'type'            => 'number',
					'number_decimals' => '2',
					'number_min'      => '0',
					'number_max'      => '100',
					'number_step'     => '0.01',
				),
			)
		);
	}

	private static function save_pod_fields( $pod_name, array $fields ) {
		$api    = pods_api();
		$pod    = self::load_pod( $pod_name );
		$pod_id = self::pod_id( $pod );
		if ( ! $pod_id ) {
			return false;
		}

		$group_id        = self::pod_group_id( $pod );
		$existing_fields = self::pod_fields( $pod );
		$weight          = 0;

		foreach ( $fields as $name => $field ) {
			$params = array_merge(
				array(
					'pod_id'                       => $pod_id,
					'pod'                          => $pod_name,
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

			$existing_field = $api->load_field(
				array(
					'pod'          => $pod_name,
					'name'         => $name,
					'bypass_cache' => true,
				)
			);

			// Existing fields may contain production data and customized Pods
			// settings. Leave them untouched; schema setup only creates fields that
			// are genuinely missing. This also avoids Pods 3.x's strict-ID rename
			// conflict when a cached field identifier has a different scalar type.
			if (
				( is_object( $existing_field ) && method_exists( $existing_field, 'get_id' ) )
				|| ( is_array( $existing_field ) && ! empty( $existing_field['id'] ) )
				|| self::pod_field_id( $existing_fields, $name )
			) {
				$weight++;
				continue;
			}

			$saved = $api->save_field( $params );
			if ( false === $saved || is_wp_error( $saved ) ) {
				return false;
			}
			$weight++;
		}

		return true;
	}

	private static function ensure_simple_pod( $name, $singular_label, $plural_label ) {
		$api = pods_api();
		$pod = self::load_pod( $name );

		if ( ! self::pod_id( $pod ) ) {
			$params = post_type_exists( $name )
				? array(
					'create_extend'    => 'extend',
					'extend_pod_type'  => 'post_type',
					'extend_post_type' => $name,
					'extend_storage'   => 'meta',
				)
				: array(
					'create_extend'             => 'create',
					'create_pod_type'           => 'post_type',
					'create_name'               => $name,
					'create_label_singular'     => $singular_label,
					'create_label_plural'       => $plural_label,
					'create_storage'            => 'meta',
					'create_public'             => 0,
					'create_publicly_queryable' => 0,
					'create_rest_api'           => 1,
				);

			$api->add_pod( $params );
			$pod = self::load_pod( $name );
		}

		$pod_id = self::pod_id( $pod );
		if ( ! $pod_id ) {
			return;
		}

		$api->save_pod(
			array(
				'id'                 => $pod_id,
				'name'               => $name,
				'label'              => $plural_label,
				'label_singular'     => $singular_label,
				'type'               => 'post_type',
				'storage'            => 'meta',
				'public'             => 0,
				'publicly_queryable' => 0,
				'show_ui'            => 1,
				'show_in_menu'       => 0,
				'rest_enable'        => 1,
				'supports_title'     => 1,
				'supports_editor'    => 0,
				'supports_thumbnail' => 0,
			)
		);
	}

	private static function load_pod( $name ) {
		return pods_api()->load_pod( array( 'name' => $name ) );
	}

	private static function pod_id( $pod ) {
		if ( is_array( $pod ) && ! empty( $pod['id'] ) ) {
			return (int) $pod['id'];
		}
		if ( is_object( $pod ) && method_exists( $pod, 'get_arg' ) ) {
			return (int) $pod->get_arg( 'id' );
		}
		return 0;
	}

	private static function pod_group_id( $pod ) {
		$groups = array();
		if ( is_array( $pod ) && ! empty( $pod['groups'] ) && is_array( $pod['groups'] ) ) {
			$groups = $pod['groups'];
		} elseif ( is_object( $pod ) && method_exists( $pod, 'get_groups' ) ) {
			$groups = $pod->get_groups();
		}
		$first = $groups ? reset( $groups ) : null;
		if ( is_array( $first ) && isset( $first['id'] ) ) {
			return (int) $first['id'];
		}
		if ( is_object( $first ) && method_exists( $first, 'get_arg' ) ) {
			return (int) $first->get_arg( 'id' );
		}
		return 0;
	}

	private static function pod_fields( $pod ) {
		if ( is_array( $pod ) && isset( $pod['fields'] ) && is_array( $pod['fields'] ) ) {
			return $pod['fields'];
		}
		if ( is_object( $pod ) && method_exists( $pod, 'get_fields' ) ) {
			return $pod->get_fields();
		}
		return array();
	}

	private static function pod_field_id( array $fields, $name ) {
		if ( empty( $fields[ $name ] ) ) {
			return 0;
		}
		$field = $fields[ $name ];
		if ( is_array( $field ) && isset( $field['id'] ) ) {
			return $field['id'];
		}
		if ( is_object( $field ) && method_exists( $field, 'get_arg' ) ) {
			return $field->get_arg( 'id' );
		}
		return 0;
	}

	private static function format_simple_options( array $values ) {
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

	private static function country_values() {
		return array( 'Regional', 'Antigua and Barbuda', 'Bahamas', 'Barbados', 'Belize', 'Cuba', 'Dominica', 'Dominican Republic', 'Grenada', 'Guyana', 'Haiti', 'Jamaica', 'Montserrat', 'Saint Lucia', 'St. Kitts and Nevis', 'St. Vincent and the Grenadines', 'Suriname', 'Trinidad and Tobago', 'Other' );
	}

	public static function register_meta_boxes() {
		add_meta_box( 'gsf_data_story_settings', __( 'Story Settings', 'gsf-data-centre' ), array( __CLASS__, 'render_story_meta_box' ), 'gsf_data_story', 'normal', 'high' );
		add_meta_box( 'gsf_indicator_details', __( 'Indicator Details', 'gsf-data-centre' ), array( __CLASS__, 'render_indicator_meta_box' ), 'gsf_indicator', 'normal', 'high' );
	}

	public static function remove_indicator_pods_meta_box() {
		remove_meta_box( 'pods-meta-more-fields', 'gsf_indicator', 'normal' );
	}

	public static function render_story_meta_box( $post ) {
		wp_nonce_field( 'gsf_data_story_save', 'gsf_data_story_nonce' );
		self::text_field( $post->ID, 'gsf_story_country', __( 'Country / region', 'gsf-data-centre' ), 'Saint Lucia' );
		self::text_field( $post->ID, 'gsf_story_metric_one_value', __( 'Metric 1 value', 'gsf-data-centre' ), '2,350' );
		self::text_field( $post->ID, 'gsf_story_metric_one_label', __( 'Metric 1 label', 'gsf-data-centre' ), 'Mangroves planted' );
		self::text_field( $post->ID, 'gsf_story_metric_two_value', __( 'Metric 2 value', 'gsf-data-centre' ), '180' );
		self::text_field( $post->ID, 'gsf_story_metric_two_label', __( 'Metric 2 label', 'gsf-data-centre' ), 'Women involved' );
		self::text_field( $post->ID, 'gsf_story_metric_three_value', __( 'Metric 3 value', 'gsf-data-centre' ), '12 ha' );
		self::text_field( $post->ID, 'gsf_story_metric_three_label', __( 'Metric 3 label', 'gsf-data-centre' ), 'Area restored' );
		self::text_field( $post->ID, 'gsf_story_url', __( 'Story URL', 'gsf-data-centre' ), home_url( '/case-studies/' ) );
		self::checkbox_field( $post->ID, 'gsf_story_featured', __( 'Featured story', 'gsf-data-centre' ) );
	}

	public static function render_indicator_meta_box( $post ) {
		wp_nonce_field( 'gsf_indicator_save', 'gsf_indicator_nonce' );
		self::text_field( $post->ID, 'indicator_code', __( 'Indicator Code', 'gsf-data-centre' ), 'GSF-1000-01' );
		self::text_field( $post->ID, 'indicator_level', __( 'Level', 'gsf-data-centre' ), 'Outcome', self::first_meta_value( $post->ID, array( 'indicator_level', 'result_level', 'pmf_reference' ) ) );
		self::textarea_field( $post->ID, 'indicator_text', __( 'Indicator Text', 'gsf-data-centre' ) );
		self::text_field( $post->ID, 'baseline', __( 'Baseline', 'gsf-data-centre' ), '', self::first_meta_value( $post->ID, array( 'baseline', 'baseline_summary' ) ) );
		self::text_field( $post->ID, 'target', __( 'Target', 'gsf-data-centre' ), '', self::first_meta_value( $post->ID, array( 'target', 'target_summary' ) ) );
		self::text_field( $post->ID, 'frequency', __( 'Frequency', 'gsf-data-centre' ), 'Annual' );
		self::textarea_field( $post->ID, 'method', __( 'Method', 'gsf-data-centre' ), '', self::first_meta_value( $post->ID, array( 'method', 'data_collection_method' ) ) );
		self::text_field( $post->ID, 'source', __( 'Source', 'gsf-data-centre' ), '', self::first_meta_value( $post->ID, array( 'source', 'data_source' ) ) );
		self::text_field( $post->ID, 'responsible_party', __( 'Responsible Party', 'gsf-data-centre' ) );
		self::text_field( $post->ID, 'gender_responsiveness', __( 'Gender Responsiveness', 'gsf-data-centre' ) );
	}

	private static function text_field( $post_id, $key, $label, $placeholder = '', $value = null ) {
		if ( null === $value ) {
			$value = get_post_meta( $post_id, $key, true );
		}
		printf(
			'<p><label for="%1$s"><strong>%2$s</strong></label><br><input type="text" class="widefat" id="%1$s" name="%1$s" value="%3$s" placeholder="%4$s"></p>',
			esc_attr( $key ),
			esc_html( $label ),
			esc_attr( $value ),
			esc_attr( $placeholder )
		);
	}

	private static function number_field( $post_id, $key, $label, $placeholder = '', $min = 0, $max = '' ) {
		$value = get_post_meta( $post_id, $key, true );
		printf(
			'<p><label for="%1$s"><strong>%2$s</strong></label><br><input type="number" class="widefat" id="%1$s" name="%1$s" value="%3$s" placeholder="%4$s" min="%5$s" %6$s></p>',
			esc_attr( $key ),
			esc_html( $label ),
			esc_attr( $value ),
			esc_attr( $placeholder ),
			esc_attr( $min ),
			'' !== $max ? 'max="' . esc_attr( $max ) . '"' : ''
		);
	}

	private static function textarea_field( $post_id, $key, $label, $placeholder = '', $value = null ) {
		if ( null === $value ) {
			$value = get_post_meta( $post_id, $key, true );
		}
		printf(
			'<p><label for="%1$s"><strong>%2$s</strong></label><br><textarea class="widefat" rows="4" id="%1$s" name="%1$s" placeholder="%4$s">%3$s</textarea></p>',
			esc_attr( $key ),
			esc_html( $label ),
			esc_textarea( $value ),
			esc_attr( $placeholder )
		);
	}

	private static function country_field( $post_id, $key, $label ) {
		$countries = array( 'Antigua and Barbuda', 'Bahamas', 'Barbados', 'Belize', 'Cuba', 'Dominica', 'Dominican Republic', 'Grenada', 'Guyana', 'Haiti', 'Jamaica', 'Montserrat', 'Saint Lucia', 'St. Kitts and Nevis', 'St. Vincent and the Grenadines', 'Suriname', 'Trinidad and Tobago', 'Regional', 'Other' );
		$value     = get_post_meta( $post_id, $key, true );

		printf( '<p><label for="%1$s"><strong>%2$s</strong></label><br><select class="widefat" id="%1$s" name="%1$s">', esc_attr( $key ), esc_html( $label ) );
		echo '<option value="">' . esc_html__( 'Select country / region', 'gsf-data-centre' ) . '</option>';
		foreach ( $countries as $country ) {
			printf( '<option value="%1$s" %2$s>%1$s</option>', esc_attr( $country ), selected( $value, $country, false ) );
		}
		echo '</select></p>';
	}

	private static function status_field( $post_id, $key, $label ) {
		$statuses = array(
			'Not started' => __( 'Not started', 'gsf-data-centre' ),
			'In progress' => __( 'In progress', 'gsf-data-centre' ),
			'At risk' => __( 'At risk', 'gsf-data-centre' ),
			'Delayed' => __( 'Delayed', 'gsf-data-centre' ),
			'Completed' => __( 'Completed', 'gsf-data-centre' ),
		);
		$value = get_post_meta( $post_id, $key, true );

		printf( '<p><label for="%1$s"><strong>%2$s</strong></label><br><select class="widefat" id="%1$s" name="%1$s">', esc_attr( $key ), esc_html( $label ) );
		foreach ( $statuses as $status => $label_text ) {
			printf( '<option value="%1$s" %2$s>%3$s</option>', esc_attr( $status ), selected( $value, $status, false ), esc_html( $label_text ) );
		}
		echo '</select></p>';
	}

	private static function checkbox_field( $post_id, $key, $label ) {
		$value = get_post_meta( $post_id, $key, true );
		printf(
			'<p><label><input type="checkbox" name="%1$s" value="1" %2$s> %3$s</label></p>',
			esc_attr( $key ),
			checked( '1', $value, false ),
			esc_html( $label )
		);
	}

	public static function save_story_meta( $post_id ) {
		if ( ! self::can_save( $post_id, 'gsf_data_story_nonce', 'gsf_data_story_save' ) ) {
			return;
		}

		self::save_text_meta(
			$post_id,
			array(
				'gsf_story_country',
				'gsf_story_metric_one_value',
				'gsf_story_metric_one_label',
				'gsf_story_metric_two_value',
				'gsf_story_metric_two_label',
				'gsf_story_metric_three_value',
				'gsf_story_metric_three_label',
				'gsf_story_url',
			)
		);
		update_post_meta( $post_id, 'gsf_story_featured', isset( $_POST['gsf_story_featured'] ) ? '1' : '0' );
	}

	public static function save_indicator_meta( $post_id ) {
		if ( ! self::can_save( $post_id, 'gsf_indicator_nonce', 'gsf_indicator_save' ) ) {
			return;
		}

		self::save_text_meta(
			$post_id,
			array(
				'indicator_code',
				'indicator_level',
				'baseline',
				'target',
				'frequency',
				'source',
				'responsible_party',
				'gender_responsiveness',
			)
		);

		self::save_textarea_meta(
			$post_id,
			array(
				'indicator_text',
				'method',
			)
		);

		self::sync_indicator_alias_meta( $post_id );
	}

	private static function can_save( $post_id, $nonce_key, $nonce_action ) {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return false;
		}

		if ( ! isset( $_POST[ $nonce_key ] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ $nonce_key ] ) ), $nonce_action ) ) {
			return false;
		}

		return current_user_can( 'edit_post', $post_id );
	}

	private static function save_text_meta( $post_id, array $keys ) {
		foreach ( $keys as $key ) {
			update_post_meta( $post_id, $key, sanitize_text_field( wp_unslash( $_POST[ $key ] ?? '' ) ) );
		}
	}

	private static function save_textarea_meta( $post_id, array $keys ) {
		foreach ( $keys as $key ) {
			update_post_meta( $post_id, $key, sanitize_textarea_field( wp_unslash( $_POST[ $key ] ?? '' ) ) );
		}
	}

	private static function sync_indicator_alias_meta( $post_id ) {
		$aliases = array(
			'indicator_level' => 'result_level',
			'baseline'        => 'baseline_summary',
			'target'          => 'target_summary',
			'source'          => 'data_source',
			'method'          => 'data_collection_method',
		);

		foreach ( $aliases as $source_key => $alias_key ) {
			update_post_meta( $post_id, $alias_key, get_post_meta( $post_id, $source_key, true ) );
		}
	}

	private static function first_meta_value( $post_id, array $keys ) {
		foreach ( $keys as $key ) {
			$value = get_post_meta( $post_id, $key, true );
			if ( is_array( $value ) ) {
				$value = implode( ', ', array_filter( array_map( 'strval', $value ) ) );
			}
			if ( '' !== trim( (string) $value ) ) {
				return (string) $value;
			}
		}

		return '';
	}

	private static function public_indicator_result_rows( array $indicator_ids ) {
		$indicator_ids = array_values( array_filter( array_map( 'absint', $indicator_ids ) ) );
		$rows          = array();

		foreach ( $indicator_ids as $indicator_id ) {
			$rows[ $indicator_id ] = array();
		}

		if ( empty( $indicator_ids ) ) {
			return $rows;
		}

		$results = get_posts(
			array(
				'post_type'        => 'gsf_ind_result',
				'post_status'      => 'publish',
				'posts_per_page'   => -1,
				'orderby'          => 'modified',
				'order'            => 'DESC',
				'suppress_filters' => true,
				'meta_query'       => array(
					array(
						'key'     => 'indicator',
						'value'   => $indicator_ids,
						'compare' => 'IN',
					),
				),
			)
		);

		foreach ( $results as $result ) {
			$indicator_id = self::indicator_result_indicator_id( $result->ID );
			if ( ! isset( $rows[ $indicator_id ] ) ) {
				continue;
			}

			$period     = trim( (string) get_post_meta( $result->ID, 'reporting_year_period', true ) );
			$period_key = $period ? strtolower( preg_replace( '/\s+/', ' ', $period ) ) : 'result-' . $result->ID;
			if ( ! isset( $rows[ $indicator_id ][ $period_key ] ) ) {
				$rows[ $indicator_id ][ $period_key ] = $result;
			}
		}

		foreach ( $rows as &$indicator_results ) {
			$indicator_results = array_values( $indicator_results );
			usort(
				$indicator_results,
				function ( $left, $right ) {
					$left_period  = get_post_meta( $left->ID, 'reporting_year_period', true );
					$right_period = get_post_meta( $right->ID, 'reporting_year_period', true );
					return strnatcasecmp( (string) $left_period, (string) $right_period );
				}
			);
		}
		unset( $indicator_results );

		return $rows;
	}

	/**
	 * Returns the reporting periods used by the visible indicator table, in a
	 * stable natural order. A result without a period is represented as an
	 * undated result instead of being silently omitted.
	 *
	 * @param array<int, array<int, WP_Post>> $indicator_result_rows Result rows by indicator ID.
	 * @return string[]
	 */
	private static function indicator_table_periods( array $indicator_result_rows ) {
		$periods = array();

		foreach ( $indicator_result_rows as $results ) {
			foreach ( $results as $result ) {
				$period = self::indicator_result_period_label( $result );
				if ( ! in_array( $period, $periods, true ) ) {
					$periods[] = $period;
				}
			}
		}

		natcasesort( $periods );
		return array_values( $periods );
	}

	private static function indicator_result_indicator_id( $result_id ) {
		$value = get_post_meta( $result_id, 'indicator', true );
		if ( is_array( $value ) ) {
			$value = reset( $value );
		}
		if ( is_object( $value ) && isset( $value->ID ) ) {
			$value = $value->ID;
		}

		return absint( $value );
	}

	private static function indicator_result_display_value( $result, $indicator_id ) {
		if ( ! $result instanceof WP_Post ) {
			return __( '—', 'gsf-data-centre' );
		}

		$result_value = get_post_meta( $result->ID, 'result_value', true );
		if ( '' !== trim( (string) $result_value ) ) {
			return (string) $result_value;
		}

		$numeric_value = get_post_meta( $result->ID, 'value_numeric', true );
		if ( '' !== trim( (string) $numeric_value ) ) {
			$value    = (string) $numeric_value;
			$currency = trim( (string) get_post_meta( $result->ID, 'currency', true ) );
			$unit     = strtolower( self::first_meta_value( $indicator_id, array( 'unit_of_measure', 'indicator_type' ) ) );

			if ( $currency ) {
				return $currency . ' ' . $value;
			}
			if ( false !== strpos( $unit, 'percent' ) && '%' !== substr( $value, -1 ) ) {
				return $value . '%';
			}

			return $value;
		}

		$text_value = get_post_meta( $result->ID, 'value_text', true );
		if ( '' !== trim( (string) $text_value ) ) {
			return (string) $text_value;
		}

		return 'no_data' === self::indicator_result_status( $result->ID ) ? __( 'No data', 'gsf-data-centre' ) : __( '—', 'gsf-data-centre' );
	}

	private static function indicator_result_status( $result_id ) {
		$status = sanitize_key( get_post_meta( $result_id, 'reporting_status', true ) );
		return $status ? $status : 'reported';
	}

	private static function indicator_result_field_value( $result, $field ) {
		if ( ! $result instanceof WP_Post ) {
			return __( '—', 'gsf-data-centre' );
		}

		$value = get_post_meta( $result->ID, $field, true );
		if ( is_array( $value ) ) {
			$value = implode( ', ', array_filter( array_map( 'strval', $value ) ) );
		}
		if ( is_object( $value ) && isset( $value->post_title ) ) {
			$value = $value->post_title;
		}

		return '' !== trim( (string) $value ) ? (string) $value : __( '—', 'gsf-data-centre' );
	}

	private static function indicator_result_period_label( $result ) {
		if ( ! $result instanceof WP_Post ) {
			return __( '—', 'gsf-data-centre' );
		}

		$period  = trim( (string) get_post_meta( $result->ID, 'reporting_year_period', true ) );
		$quarter = trim( (string) get_post_meta( $result->ID, 'quarter', true ) );
		if ( $period && $quarter ) {
			return $period . ' — ' . $quarter;
		}

		return $period ?: ( $quarter ?: __( '—', 'gsf-data-centre' ) );
	}

	private static function indicator_result_narrative( $result ) {
		return self::indicator_result_field_value( $result, 'result_narrative' );
	}

	private static function indicator_result_status_label( $status ) {
		$labels = array(
			'approved'     => __( 'Approved', 'gsf-data-centre' ),
			'draft'        => __( 'Draft', 'gsf-data-centre' ),
			'no_data'      => __( 'No data', 'gsf-data-centre' ),
			'partial_data' => __( 'Partial data', 'gsf-data-centre' ),
			'pending'      => __( 'Pending', 'gsf-data-centre' ),
			'reported'     => __( 'Reported', 'gsf-data-centre' ),
			'submitted'    => __( 'Submitted', 'gsf-data-centre' ),
			'verified'     => __( 'Verified', 'gsf-data-centre' ),
		);

		return isset( $labels[ $status ] ) ? $labels[ $status ] : ucwords( str_replace( '_', ' ', $status ) );
	}

	private static function current_project_table_page() {
		return self::current_table_page( 'gsf_project_page' );
	}

	private static function current_indicator_table_page() {
		return self::current_table_page( 'gsf_indicator_page' );
	}

	private static function current_table_page( $query_arg ) {
		$page = filter_input( INPUT_GET, sanitize_key( $query_arg ), FILTER_VALIDATE_INT, array( 'options' => array( 'min_range' => 1 ) ) );
		return $page ? (int) $page : 1;
	}

	private static function table_page_url( $query_arg, $page, $anchor ) {
		$query_arg = sanitize_key( $query_arg );
		$anchor    = sanitize_html_class( $anchor );
		$page      = max( 1, (int) $page );
		$url       = add_query_arg( $query_arg, $page );

		if ( 1 === $page ) {
			$url = remove_query_arg( $query_arg, $url );
		}

		return esc_url( $url . '#' . $anchor );
	}

	private static function render_project_table_pagination( $current_page, $total_pages, $total_items, $per_page ) {
		return self::render_table_pagination(
			$current_page,
			$total_pages,
			$total_items,
			$per_page,
			__( 'projects', 'gsf-data-centre' ),
			__( 'Project table pages', 'gsf-data-centre' ),
			'gsf_project_page',
			'gsf-project-table'
		);
	}

	private static function render_indicator_table_pagination( $current_page, $total_pages, $total_items, $per_page ) {
		return self::render_table_pagination(
			$current_page,
			$total_pages,
			$total_items,
			$per_page,
			__( 'indicators', 'gsf-data-centre' ),
			__( 'Indicator table pages', 'gsf-data-centre' ),
			'gsf_indicator_page',
			'gsf-indicator-table'
		);
	}

	private static function render_table_pagination( $current_page, $total_pages, $total_items, $per_page, $item_label, $aria_label, $query_arg, $anchor ) {
		if ( $total_items <= $per_page ) {
			return '';
		}

		$start = ( ( $current_page - 1 ) * $per_page ) + 1;
		$end   = min( $total_items, $current_page * $per_page );

		ob_start();
		?>
		<nav class="gsf-project-pagination" aria-label="<?php echo esc_attr( $aria_label ); ?>">
			<span class="gsf-project-pagination__summary">
				<?php
				echo esc_html(
					sprintf(
						/* translators: 1: first visible item number, 2: last visible item number, 3: total items, 4: item label. */
						__( 'Showing %1$d-%2$d of %3$d %4$s', 'gsf-data-centre' ),
						$start,
						$end,
						$total_items,
						$item_label
					)
				);
				?>
			</span>
			<div class="gsf-project-pagination__links">
				<?php if ( $current_page > 1 ) : ?>
					<a class="gsf-project-pagination__link" href="<?php echo self::table_page_url( $query_arg, $current_page - 1, $anchor ); ?>"><?php esc_html_e( 'Previous', 'gsf-data-centre' ); ?></a>
				<?php else : ?>
					<span class="gsf-project-pagination__link is-disabled"><?php esc_html_e( 'Previous', 'gsf-data-centre' ); ?></span>
				<?php endif; ?>

				<?php for ( $page = 1; $page <= $total_pages; $page++ ) : ?>
					<?php if ( $page === $current_page ) : ?>
						<span class="gsf-project-pagination__link is-current" aria-current="page"><?php echo esc_html( $page ); ?></span>
					<?php else : ?>
						<a class="gsf-project-pagination__link" href="<?php echo self::table_page_url( $query_arg, $page, $anchor ); ?>"><?php echo esc_html( $page ); ?></a>
					<?php endif; ?>
				<?php endfor; ?>

				<?php if ( $current_page < $total_pages ) : ?>
					<a class="gsf-project-pagination__link" href="<?php echo self::table_page_url( $query_arg, $current_page + 1, $anchor ); ?>"><?php esc_html_e( 'Next', 'gsf-data-centre' ); ?></a>
				<?php else : ?>
					<span class="gsf-project-pagination__link is-disabled"><?php esc_html_e( 'Next', 'gsf-data-centre' ); ?></span>
				<?php endif; ?>
			</div>
		</nav>
		<?php
		return ob_get_clean();
	}

	public static function render_shortcode() {
		$metrics                    = self::get_dashboard_metrics();
		$story                      = self::get_featured_story();
		$projects                   = self::get_posts( 'gsf_project', -1 );
		$indicators                 = self::get_posts( 'gsf_indicator', -1 );
		$ecoequity_scores           = self::get_posts( 'gsf_ecoequity', 100 );
		$display                    = self::get_display_settings();
		$project_table_per_page     = 5;
		$project_table_total        = count( $projects );
		$project_table_pages        = max( 1, (int) ceil( $project_table_total / $project_table_per_page ) );
		$project_table_page         = min( self::current_project_table_page(), $project_table_pages );
		$project_table_projects     = array_slice( $projects, ( $project_table_page - 1 ) * $project_table_per_page, $project_table_per_page );
		$indicator_table_per_page   = 5;
		$indicator_table_total      = count( $indicators );
		$indicator_table_pages      = max( 1, (int) ceil( $indicator_table_total / $indicator_table_per_page ) );
		$indicator_table_page       = min( self::current_indicator_table_page(), $indicator_table_pages );
		$indicator_table_indicators = array_slice( $indicators, ( $indicator_table_page - 1 ) * $indicator_table_per_page, $indicator_table_per_page );
		$indicator_result_rows        = self::public_indicator_result_rows( wp_list_pluck( $indicator_table_indicators, 'ID' ) );
		$indicator_table_periods      = self::indicator_table_periods( $indicator_result_rows );

		wp_enqueue_script( 'gsf-data-centre-charts' );
		wp_enqueue_script( 'gsf-data-centre-export' );
		wp_enqueue_style( 'gsf-data-centre' );

		ob_start();
		?>
		<div class="gsf-story-dashboard">
		<section class="hub-page-hero hub-page-hero--data-centre gsf-story-dashboard__hero alignfull">
			<div class="hub-page-hero__inner alignwide">
				<p class="hub-page-hero__eyebrow"><?php echo esc_html( $display['hero_eyebrow'] ); ?></p>
				<h1 class="hub-page-hero__title"><?php echo esc_html( $display['hero_title'] ); ?></h1>
				<p class="hub-page-hero__text"><?php echo esc_html( $display['hero_description'] ); ?></p>
				<div class="hub-page-hero__actions wp-block-buttons">
					<div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="<?php echo esc_url( home_url( '/case-studies/' ) ); ?>"><?php esc_html_e( 'View case studies', 'gsf-data-centre' ); ?></a></div>
					<div class="wp-block-button is-style-outline"><a class="wp-block-button__link wp-element-button" href="<?php echo esc_url( home_url( '/resources/' ) ); ?>"><?php esc_html_e( 'Browse resources', 'gsf-data-centre' ); ?></a></div>
				</div>
			</div>
		</section>

		<section class="hub-section alignwide">
			<div class="hub-kpi-grid hub-kpi-grid--data-centre">
				<?php foreach ( $metrics as $metric ) : ?>
					<div class="hub-kpi-card gsf-story-kpi-card gsf-story-kpi-card--<?php echo esc_attr( $metric['key'] ); ?>">
						<span class="hub-kpi-card__icon gsf-story-kpi-card__icon" aria-hidden="true"><?php echo self::metric_icon_svg( $metric['key'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
						<div class="gsf-story-kpi-card__body">
							<p class="hub-page-hero__eyebrow"><?php echo esc_html( $metric['title'] ); ?></p>
							<p class="hub-kpi-card__value"><?php echo esc_html( $metric['value'] ); ?></p>
							<p class="hub-kpi-card__label"><?php echo esc_html( $metric['label'] ); ?></p>
						</div>
					</div>
				<?php endforeach; ?>
			</div>
		</section>

		<section class="hub-section alignwide">
			<div class="hub-panel-grid hub-panel-grid--storytelling">
				<?php echo self::render_story_panel( $story ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<div class="hub-story-panel">
					<p class="hub-section__eyebrow"><?php esc_html_e( 'Story metrics', 'gsf-data-centre' ); ?></p>
					<h3><?php echo esc_html( $story ? $story->post_title : __( 'Programme outcomes connected to one narrative', 'gsf-data-centre' ) ); ?></h3>
					<div class="hub-inline-stats">
						<?php for ( $i = 1; $i <= 3; $i++ ) : ?>
							<?php
							$key = array( 1 => 'one', 2 => 'two', 3 => 'three' )[ $i ];
							?>
							<div class="hub-inline-stat">
								<strong><?php echo esc_html( $story ? get_post_meta( $story->ID, 'gsf_story_metric_' . $key . '_value', true ) : '' ); ?></strong>
								<span><?php echo esc_html( $story ? get_post_meta( $story->ID, 'gsf_story_metric_' . $key . '_label', true ) : '' ); ?></span>
							</div>
						<?php endfor; ?>
					</div>
					<p class="hub-caption"><?php echo esc_html( wp_strip_all_tags( $story ? $story->post_content : '' ) ); ?></p>
				</div>
				<div class="hub-chart-stack">
						<div class="hub-chart-panel">
							<p class="hub-section__eyebrow"><?php esc_html_e( 'Gender participation', 'gsf-data-centre' ); ?></p>
							<h3><?php esc_html_e( 'Participation snapshot', 'gsf-data-centre' ); ?></h3>
							<?php echo self::render_chart_canvas( $display['participation_chart'], self::get_participation_chart_data(), __( 'Gender participation chart', 'gsf-data-centre' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						</div>
					</div>
				</div>
			</section>

		<section class="hub-section alignwide">
			<div class="hub-chart-panel">
				<p class="hub-section__eyebrow"><?php esc_html_e( 'Projects by territory', 'gsf-data-centre' ); ?></p>
				<h3><?php esc_html_e( 'Geographic spread', 'gsf-data-centre' ); ?></h3>
				<?php
				if ( 'bar' === $display['territory_display'] ) {
					echo self::render_chart_canvas( 'bar', self::get_territory_chart_data( $projects ), __( 'Projects by territory chart', 'gsf-data-centre' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				} else {
					echo self::render_territory_map( $projects ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				}
				?>
			</div>
		</section>

		<?php if ( $projects ) : ?>
			<section class="hub-section alignwide gsf-story-project-cards">
				<div class="gsf-story-mini-grid">
					<?php echo self::render_project_story_cards( $projects ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</div>
			</section>
		<?php endif; ?>

		<section class="hub-section alignwide">
			<div class="hub-panel-grid hub-panel-grid--summary">
				<div class="hub-table-panel">
					<p class="hub-section__eyebrow"><?php esc_html_e( 'Evidence for partners', 'gsf-data-centre' ); ?></p>
					<h3><?php esc_html_e( 'Project progress by type', 'gsf-data-centre' ); ?></h3>
					<ul class="hub-progress-table">
						<?php foreach ( self::get_project_type_progress_rows( $projects ) as $row ) : ?>
							<li>
								<div class="hub-progress-row">
									<div class="hub-progress-row__meta">
										<strong><?php echo esc_html( $row['type'] ); ?></strong>
										<span><?php echo esc_html( sprintf( __( '%1$d active / %2$d total', 'gsf-data-centre' ), $row['active'], $row['total'] ) ); ?></span>
										<span><?php echo esc_html( $row['percent'] . '%' ); ?></span>
									</div>
									<div class="hub-progress-row__track"><div class="hub-progress-row__fill" style="width:<?php echo esc_attr( $row['percent'] ); ?>%"></div></div>
								</div>
							</li>
						<?php endforeach; ?>
					</ul>
				</div>
				<div class="hub-quote-panel">
					<blockquote>
						<p><?php esc_html_e( 'When we invest in women and nature together, our islands become more resilient, our communities thrive, and our future is brighter.', 'gsf-data-centre' ); ?></p>
						<cite class="hub-caption"><?php esc_html_e( 'GSF Hub partner', 'gsf-data-centre' ); ?></cite>
					</blockquote>
				</div>
			</div>
		</section>

			<?php if ( $projects ) : ?>
				<section class="hub-section alignwide" id="gsf-project-table">
					<div class="hub-table-panel gsf-milestone-panel">
						<p class="hub-section__eyebrow"><?php esc_html_e( 'Projects', 'gsf-data-centre' ); ?></p>
						<h3><?php esc_html_e( 'Project status and outputs', 'gsf-data-centre' ); ?></h3>
					<div class="gsf-milestone-table-wrap">
						<table class="gsf-milestone-table">
							<thead>
								<tr>
									<th><?php esc_html_e( 'Project', 'gsf-data-centre' ); ?></th>
									<th><?php esc_html_e( 'Type', 'gsf-data-centre' ); ?></th>
									<th><?php esc_html_e( 'Country', 'gsf-data-centre' ); ?></th>
									<th><?php esc_html_e( 'Organizations', 'gsf-data-centre' ); ?></th>
									<th><?php esc_html_e( 'Implementing party', 'gsf-data-centre' ); ?></th>
									<th><?php esc_html_e( 'Funding', 'gsf-data-centre' ); ?></th>
									<th><?php esc_html_e( 'Milestone / output', 'gsf-data-centre' ); ?></th>
									<th><?php esc_html_e( 'Gender responsiveness', 'gsf-data-centre' ); ?></th>
									<th><?php esc_html_e( 'People affected', 'gsf-data-centre' ); ?></th>
									<th><?php esc_html_e( 'Female / women affected', 'gsf-data-centre' ); ?></th>
									<th><?php esc_html_e( 'Male / men affected', 'gsf-data-centre' ); ?></th>
									<th><?php esc_html_e( 'Other / prefer not to say', 'gsf-data-centre' ); ?></th>
									<th><?php esc_html_e( 'Non-Indigenous male', 'gsf-data-centre' ); ?></th>
									<th><?php esc_html_e( 'Non-Indigenous female', 'gsf-data-centre' ); ?></th>
									<th><?php esc_html_e( 'Indigenous male', 'gsf-data-centre' ); ?></th>
									<th><?php esc_html_e( 'Indigenous female', 'gsf-data-centre' ); ?></th>
									<th><?php esc_html_e( 'Climate hazard', 'gsf-data-centre' ); ?></th>
									<th><?php esc_html_e( 'Habitat / ecological condition', 'gsf-data-centre' ); ?></th>
									<th><?php esc_html_e( 'Social / economic resilience', 'gsf-data-centre' ); ?></th>
									<th><?php esc_html_e( 'Durability', 'gsf-data-centre' ); ?></th>
									<th><?php esc_html_e( 'Status', 'gsf-data-centre' ); ?></th>
									<th><?php esc_html_e( 'Complete', 'gsf-data-centre' ); ?></th>
								</tr>
								</thead>
								<tbody>
									<?php foreach ( $project_table_projects as $project ) : ?>
										<?php
										$percent      = min( 100, max( 0, (int) get_post_meta( $project->ID, 'percent_complete', true ) ) );
										$status       = get_post_meta( $project->ID, 'project_status', true );
									$organization = self::format_project_organizations( get_post_meta( $project->ID, 'organizations', false ) );
									?>
									<tr>
										<td><strong><?php echo esc_html( $project->post_title ); ?></strong></td>
										<td><?php echo esc_html( get_post_meta( $project->ID, 'project_type', true ) ); ?></td>
										<td><?php echo esc_html( get_post_meta( $project->ID, 'country', true ) ); ?></td>
										<td><?php echo esc_html( $organization ); ?></td>
										<td><?php echo esc_html( get_post_meta( $project->ID, 'implementing_party', true ) ); ?></td>
										<td><?php echo esc_html( get_post_meta( $project->ID, 'funding_source', true ) ); ?></td>
										<td><?php echo esc_html( get_post_meta( $project->ID, 'milestone_output', true ) ); ?></td>
										<td><?php echo esc_html( get_post_meta( $project->ID, 'gender_responsiveness', true ) ); ?></td>
										<td><?php echo esc_html( self::format_metric_number( get_post_meta( $project->ID, 'people_affected_total', true ) ) ); ?></td>
										<td><?php echo esc_html( self::format_metric_number( get_post_meta( $project->ID, 'people_affected_female', true ) ) ); ?></td>
										<td><?php echo esc_html( self::format_metric_number( get_post_meta( $project->ID, 'people_affected_male', true ) ) ); ?></td>
										<td><?php echo esc_html( self::format_metric_number( get_post_meta( $project->ID, 'people_affected_other', true ) ) ); ?></td>
										<td><?php echo esc_html( self::format_metric_number( get_post_meta( $project->ID, 'people_affected_non_indigenous_male', true ) ) ); ?></td>
										<td><?php echo esc_html( self::format_metric_number( get_post_meta( $project->ID, 'people_affected_non_indigenous_female', true ) ) ); ?></td>
										<td><?php echo esc_html( self::format_metric_number( get_post_meta( $project->ID, 'people_affected_indigenous_male', true ) ) ); ?></td>
										<td><?php echo esc_html( self::format_metric_number( get_post_meta( $project->ID, 'people_affected_indigenous_female', true ) ) ); ?></td>
										<td><?php echo esc_html( get_post_meta( $project->ID, 'climate_hazard_score', true ) ); ?></td>
										<td><?php echo esc_html( get_post_meta( $project->ID, 'habitat_quality_score', true ) ); ?></td>
										<td><?php echo esc_html( get_post_meta( $project->ID, 'social_economic_resilience_score', true ) ); ?></td>
										<td><?php echo esc_html( get_post_meta( $project->ID, 'durability_score', true ) ); ?></td>
										<td><span class="gsf-milestone-status gsf-milestone-status--<?php echo esc_attr( sanitize_html_class( strtolower( str_replace( ' ', '-', $status ) ) ) ); ?>"><?php echo esc_html( $status ); ?></span></td>
										<td>
											<span class="gsf-milestone-percent"><?php echo esc_html( $percent . '%' ); ?></span>
											<span class="gsf-milestone-track"><span style="width:<?php echo esc_attr( $percent ); ?>%"></span></span>
										</td>
									</tr>
								<?php endforeach; ?>
								</tbody>
							</table>
						</div>
						<?php echo self::render_project_table_pagination( $project_table_page, $project_table_pages, $project_table_total, $project_table_per_page ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					</div>
				</section>
			<?php endif; ?>

		<?php if ( $indicators ) : ?>
			<section class="hub-section alignwide" id="gsf-indicator-table">
				<div class="hub-table-panel gsf-milestone-panel">
					<p class="hub-section__eyebrow"><?php esc_html_e( 'Indicators', 'gsf-data-centre' ); ?></p>
					<h3><?php esc_html_e( 'Indicator framework', 'gsf-data-centre' ); ?></h3>
					<p class="hub-caption"><?php esc_html_e( 'Reporting years and periods appear in columns. Where duplicate records exist for a period, the most recently updated published result is shown.', 'gsf-data-centre' ); ?></p>
					<div class="gsf-table-downloads" aria-label="<?php esc_attr_e( 'Download indicator framework', 'gsf-data-centre' ); ?>">
						<span class="gsf-table-downloads__label"><?php esc_html_e( 'Download this table:', 'gsf-data-centre' ); ?></span>
						<button type="button" class="gsf-table-downloads__button" data-gsf-table-download="excel" data-gsf-table-id="gsf-indicator-framework-table"><?php esc_html_e( 'Excel', 'gsf-data-centre' ); ?></button>
						<button type="button" class="gsf-table-downloads__button" data-gsf-table-download="csv" data-gsf-table-id="gsf-indicator-framework-table"><?php esc_html_e( 'CSV', 'gsf-data-centre' ); ?></button>
						<button type="button" class="gsf-table-downloads__button" data-gsf-table-download="pdf" data-gsf-table-id="gsf-indicator-framework-table"><?php esc_html_e( 'PDF', 'gsf-data-centre' ); ?></button>
					</div>
					<div class="gsf-milestone-table-wrap">
						<table id="gsf-indicator-framework-table" class="gsf-milestone-table gsf-indicator-framework-table" data-gsf-export-name="indicator-framework">
							<thead>
								<tr>
									<th><?php esc_html_e( 'Indicator Details', 'gsf-data-centre' ); ?></th>
									<?php foreach ( $indicator_table_periods as $period ) : ?>
										<th><?php echo esc_html( $period ); ?></th>
									<?php endforeach; ?>
								</tr>
								</thead>
							<tbody>
								<?php foreach ( $indicator_table_indicators as $indicator ) : ?>
									<?php
									$results_for_indicator = $indicator_result_rows[ $indicator->ID ] ?? array();
									$results_by_period      = array();
									foreach ( $results_for_indicator as $result ) {
										$results_by_period[ self::indicator_result_period_label( $result ) ] = $result;
									}
									?>
									<tr>
										<td class="gsf-indicator-details">
													<strong><?php echo esc_html( self::first_meta_value( $indicator->ID, array( 'indicator_code' ) ) ?: $indicator->post_title ); ?></strong>
													<span class="gsf-indicator-details__level"><?php echo esc_html( self::first_meta_value( $indicator->ID, array( 'indicator_level', 'result_level', 'pmf_reference' ) ) ); ?></span>
													<p><?php echo esc_html( self::first_meta_value( $indicator->ID, array( 'indicator_text' ) ) ); ?></p>
													<dl>
														<dt><?php esc_html_e( 'Baseline', 'gsf-data-centre' ); ?></dt>
														<dd><?php echo esc_html( self::first_meta_value( $indicator->ID, array( 'baseline', 'baseline_summary' ) ) ?: '—' ); ?></dd>
														<dt><?php esc_html_e( 'Target', 'gsf-data-centre' ); ?></dt>
														<dd><?php echo esc_html( self::first_meta_value( $indicator->ID, array( 'target', 'target_summary' ) ) ?: '—' ); ?></dd>
													</dl>
												</td>
										<?php foreach ( $indicator_table_periods as $period ) : ?>
											<?php $result = $results_by_period[ $period ] ?? null; ?>
											<td class="gsf-indicator-period-result">
												<?php if ( $result ) : ?>
													<?php $status = self::indicator_result_status( $result->ID ); ?>
													<?php if ( self::indicator_result_field_value( $result, 'value_text' ) ) : ?><strong class="gsf-indicator-result-text-value"><?php echo esc_html( self::indicator_result_field_value( $result, 'value_text' ) ); ?></strong><?php endif; ?>
													<?php if ( self::indicator_result_field_value( $result, 'result_value' ) ) : ?><strong class="gsf-indicator-result-value"><?php echo esc_html( self::indicator_result_field_value( $result, 'result_value' ) ); ?></strong><?php endif; ?>
													<?php if ( self::indicator_result_narrative( $result ) ) : ?><p class="gsf-indicator-result-narrative"><?php echo esc_html( self::indicator_result_narrative( $result ) ); ?></p><?php endif; ?>
													<span class="gsf-milestone-status gsf-milestone-status--<?php echo esc_attr( sanitize_html_class( str_replace( '_', '-', $status ) ) ); ?>"><?php echo esc_html( self::indicator_result_status_label( $status ) ); ?></span>
													<?php if ( self::indicator_result_field_value( $result, 'country' ) ) : ?><span class="gsf-indicator-result-country"><?php echo esc_html( self::indicator_result_field_value( $result, 'country' ) ); ?></span><?php endif; ?>
												<?php else : ?>
													<span class="gsf-indicator-period-result__empty">—</span>
												<?php endif; ?>
											</td>
										<?php endforeach; ?>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					</div>
					<?php echo self::render_indicator_table_pagination( $indicator_table_page, $indicator_table_pages, $indicator_table_total, $indicator_table_per_page ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</div>
			</section>
		<?php endif; ?>

			<?php if ( $ecoequity_scores ) : ?>
			<?php
			$has_uploaded_ecoequity_scores = self::has_ecoequity_uploaded_scores( $ecoequity_scores );
			$ecoequity_display_scores      = $has_uploaded_ecoequity_scores ? self::filter_ecoequity_uploaded_scores( $ecoequity_scores ) : $ecoequity_scores;
			$has_ecoequity_categories      = ! $has_uploaded_ecoequity_scores && self::has_ecoequity_category_scores( $ecoequity_scores );
			?>
			<section class="hub-section alignwide">
				<div class="hub-chart-panel gsf-ecoequity-panel">
					<p class="hub-section__eyebrow"><?php esc_html_e( 'Ecoequity scores', 'gsf-data-centre' ); ?></p>
					<h3><?php echo esc_html( $has_ecoequity_categories ? __( 'Category scores by organization type', 'gsf-data-centre' ) : __( 'Average organization scores by type', 'gsf-data-centre' ) ); ?></h3>
					<p class="hub-caption"><?php echo esc_html( $has_ecoequity_categories ? __( 'Each organization type has its own category chart showing baseline, mid-term, and endline average scores.', 'gsf-data-centre' ) : __( 'Scores are grouped by organization type and averaged across published Ecoequity score records.', 'gsf-data-centre' ) ); ?></p>
					<?php if ( $has_ecoequity_categories ) : ?>
						<div class="gsf-ecoequity-type-grid">
							<?php foreach ( self::get_ecoequity_category_type_panels( $ecoequity_scores ) as $panel ) : ?>
								<div class="gsf-ecoequity-type-panel">
									<div class="gsf-ecoequity-type-panel__header">
										<h4><?php echo esc_html( $panel['type'] ); ?></h4>
										<span><?php echo esc_html( sprintf( _n( '%d category', '%d categories', count( $panel['rows'] ), 'gsf-data-centre' ), count( $panel['rows'] ) ) ); ?></span>
									</div>
									<?php echo self::render_chart_canvas( 'stackedBar', $panel['chart'], sprintf( __( 'Ecoequity category scores for %s', 'gsf-data-centre' ), $panel['type'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
									<div class="gsf-ecoequity-summary gsf-ecoequity-summary--inline">
										<?php foreach ( $panel['rows'] as $row ) : ?>
											<article class="gsf-ecoequity-card">
												<span><?php echo esc_html( $row['short_label'] ); ?></span>
												<strong><?php echo esc_html( $row['display_score'] ); ?></strong>
												<small><?php echo esc_html( $row['display_label'] ); ?></small>
											</article>
										<?php endforeach; ?>
									</div>
								</div>
							<?php endforeach; ?>
						</div>
					<?php else : ?>
						<div class="gsf-ecoequity-layout">
							<?php echo self::render_chart_canvas( 'groupedBar', self::get_ecoequity_chart_data( $ecoequity_display_scores ), __( 'Ecoequity average scores by organization type', 'gsf-data-centre' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
							<div class="gsf-ecoequity-summary">
								<?php foreach ( self::get_ecoequity_summary_rows( $ecoequity_display_scores ) as $row ) : ?>
									<article class="gsf-ecoequity-card">
										<span><?php echo esc_html( $row['type'] ); ?></span>
										<strong><?php echo esc_html( $row['display_score'] ); ?></strong>
										<small><?php echo esc_html( $row['display_label'] ); ?></small>
									</article>
								<?php endforeach; ?>
							</div>
						</div>
					<?php endif; ?>
				</div>
			</section>
		<?php endif; ?>
		</div>
		<?php
		return ob_get_clean();
	}

	private static function render_chart_canvas( $type, array $data, $label ) {
		$type = sanitize_key( $type );
		if ( ! in_array( $type, array( 'doughnut', 'pie', 'line', 'bar', 'groupedbar', 'stackedbar' ), true ) ) {
			$type = 'bar';
		}

		ob_start();
		?>
		<div class="gsf-data-chart gsf-data-chart--<?php echo esc_attr( $type ); ?>">
			<canvas
				data-gsf-chart="<?php echo esc_attr( $type ); ?>"
				data-chart="<?php echo esc_attr( wp_json_encode( $data ) ); ?>"
				aria-label="<?php echo esc_attr( $label ); ?>"
				role="img"
			></canvas>
		</div>
		<?php
		return ob_get_clean();
	}

	private static function get_ecoequity_chart_data( array $scores ) {
		$rows = self::get_ecoequity_summary_rows( $scores );

		return array(
			'labels'   => wp_list_pluck( $rows, 'type' ),
			'max'      => 4,
			'datasets' => array(
				array(
					'label' => __( 'Baseline', 'gsf-data-centre' ),
					'data'  => wp_list_pluck( $rows, 'baseline_raw' ),
				),
				array(
					'label' => __( 'Mid-term', 'gsf-data-centre' ),
					'data'  => wp_list_pluck( $rows, 'midterm_raw' ),
				),
				array(
					'label' => __( 'Endline', 'gsf-data-centre' ),
					'data'  => wp_list_pluck( $rows, 'endline_raw' ),
				),
			),
		);
	}

	private static function has_ecoequity_category_scores( array $scores ) {
		foreach ( $scores as $score ) {
			if ( '' !== trim( (string) get_post_meta( $score->ID, 'ecoequity_category', true ) ) && '' !== trim( (string) get_post_meta( $score->ID, 'category_score', true ) ) ) {
				return true;
			}
		}

		return false;
	}

	private static function has_ecoequity_uploaded_scores( array $scores ) {
		foreach ( $scores as $score ) {
			if ( self::is_uploaded_ecoequity_score( $score ) ) {
				return true;
			}
		}

		return false;
	}

	private static function filter_ecoequity_uploaded_scores( array $scores ) {
		return array_values(
			array_filter(
				$scores,
				static function ( $score ) {
					return self::is_uploaded_ecoequity_score( $score );
				}
			)
		);
	}

	private static function is_uploaded_ecoequity_score( $score ) {
		$upload_id   = trim( (string) get_post_meta( $score->ID, 'score_upload_id', true ) );
		$total_score = trim( (string) get_post_meta( $score->ID, 'total_score', true ) );
		$stage       = self::normalize_ecoequity_stage( get_post_meta( $score->ID, 'assessment_stage', true ) );

		return '' !== $total_score && ( '' !== $stage || 1 === preg_match( '/^EQS-[A-Za-z0-9_-]+$/', $upload_id ) );
	}

	private static function normalize_ecoequity_stage( $stage ) {
		if ( class_exists( 'GSF_Data_Centre_MEAL' ) && method_exists( 'GSF_Data_Centre_MEAL', 'normalize_ecoequity_stage' ) ) {
			return GSF_Data_Centre_MEAL::normalize_ecoequity_stage( $stage );
		}

		$stage = strtolower( remove_accents( wp_strip_all_tags( (string) $stage ) ) );
		$stage = trim( (string) preg_replace( '/[^a-z0-9]+/', ' ', $stage ) );

		if ( '' === $stage ) {
			return '';
		}

		if ( preg_match( '/\b(base|baseline|initial|start|starting)\b/', $stage ) ) {
			return 'baseline';
		}

		if ( preg_match( '/\b(mid|midterm|midline|midpoint|interim|intermediate)\b/', $stage ) || preg_match( '/\bmid\s+term\b/', $stage ) ) {
			return 'midterm';
		}

		if ( preg_match( '/\b(end|endline|final|finale|terminal|completion|closeout)\b/', $stage ) || preg_match( '/\bend\s+line\b/', $stage ) || preg_match( '/\bclose\s+out\b/', $stage ) ) {
			return 'endline';
		}

		return '';
	}

	private static function get_ecoequity_category_chart_data( array $scores ) {
		$rows = self::get_ecoequity_category_rows( $scores );

		return array(
			'label'  => __( 'Average category score', 'gsf-data-centre' ),
			'labels' => wp_list_pluck( $rows, 'short_label' ),
			'values' => wp_list_pluck( $rows, 'score_raw' ),
		);
	}

	private static function get_ecoequity_stacked_category_chart_data( array $scores ) {
		$types      = array();
		$categories = array();
		$values     = array();

		foreach ( $scores as $score ) {
			$category = trim( (string) get_post_meta( $score->ID, 'ecoequity_category', true ) );
			if ( '' === $category || '' === trim( (string) get_post_meta( $score->ID, 'category_score', true ) ) ) {
				continue;
			}

			$type = trim( (string) get_post_meta( $score->ID, 'organization_type', true ) );
			if ( '' === $type ) {
				$type = __( 'Unspecified', 'gsf-data-centre' );
			}

			$short_category = self::short_category_label( $category );
			$types[ $type ] = true;
			$categories[ $short_category ] = true;

			if ( ! isset( $values[ $short_category ][ $type ] ) ) {
				$values[ $short_category ][ $type ] = array(
					'total' => 0,
					'count' => 0,
				);
			}

			$values[ $short_category ][ $type ]['total'] += self::numeric_meta_value( $score->ID, 'category_score' );
			$values[ $short_category ][ $type ]['count']++;
		}

		$type_labels     = array_keys( $types );
		$category_labels = array_keys( $categories );

		sort( $type_labels );
		sort( $category_labels );

		$datasets = array();
		foreach ( $category_labels as $category_label ) {
			$data = array();
			foreach ( $type_labels as $type_label ) {
				$entry  = $values[ $category_label ][ $type_label ] ?? array( 'total' => 0, 'count' => 0 );
				$count  = max( 1, (int) $entry['count'] );
				$data[] = $entry['count'] ? (int) round( $entry['total'] / $count ) : 0;
			}

			$datasets[] = array(
				'label' => $category_label,
				'data'  => $data,
			);
		}

		return array(
			'labels'   => $type_labels,
			'datasets' => $datasets,
		);
	}

	private static function get_ecoequity_category_type_panels( array $scores ) {
		$groups = array();

		foreach ( $scores as $score ) {
			$category = trim( (string) get_post_meta( $score->ID, 'ecoequity_category', true ) );
			if ( '' === $category ) {
				continue;
			}

			$type = trim( (string) get_post_meta( $score->ID, 'organization_type', true ) );
			if ( '' === $type ) {
				$type = __( 'Unspecified', 'gsf-data-centre' );
			}

			$short_category = self::short_category_label( $category );

			if ( ! isset( $groups[ $type ][ $short_category ] ) ) {
				$groups[ $type ][ $short_category ] = array(
					'category' => $category,
					'baseline' => array( 'total' => 0, 'count' => 0 ),
					'midterm'  => array( 'total' => 0, 'count' => 0 ),
					'endline'  => array( 'total' => 0, 'count' => 0 ),
					'rows'     => 0,
				);
			}

			$stage_scores = array(
				'baseline' => self::ecoequity_stage_score( $score->ID, 'baseline', 'category_baseline_score', 'category_score' ),
				'midterm'  => self::ecoequity_stage_score( $score->ID, 'midterm', 'category_midterm_score', 'category_score' ),
				'endline'  => self::ecoequity_stage_score( $score->ID, 'endline', 'category_endline_score', 'category_score' ),
			);

			$has_stage_score = false;
			foreach ( $stage_scores as $stage => $score_result ) {
				if ( empty( $score_result['has_score'] ) ) {
					continue;
				}

				$groups[ $type ][ $short_category ][ $stage ]['total'] += $score_result['value'];
				$groups[ $type ][ $short_category ][ $stage ]['count']++;
				$has_stage_score = true;
			}

			if ( ! $has_stage_score ) {
				unset( $groups[ $type ][ $short_category ] );
				if ( empty( $groups[ $type ] ) ) {
					unset( $groups[ $type ] );
				}
				continue;
			}

			$groups[ $type ][ $short_category ]['rows']++;
		}

		$type_order = array( 'NCTF', 'EWRO' );
		$type_names = array_unique( array_merge( $type_order, array_keys( $groups ) ) );
		$panels = array();

		foreach ( $type_names as $type ) {
			if ( empty( $groups[ $type ] ) ) {
				continue;
			}

			ksort( $groups[ $type ] );
			$rows = array();
			foreach ( $groups[ $type ] as $short_category => $entry ) {
				$baseline = self::average_score_group( $entry['baseline'] );
				$midterm  = self::average_score_group( $entry['midterm'] );
				$endline  = self::average_score_group( $entry['endline'] );
				if ( ! empty( $entry['endline']['count'] ) ) {
					$display_score = $endline;
					$display_label = sprintf( __( 'Endline, %+d from baseline', 'gsf-data-centre' ), $endline - $baseline );
				} elseif ( ! empty( $entry['midterm']['count'] ) ) {
					$display_score = $midterm;
					$display_label = sprintf( __( 'Mid-term, %+d from baseline', 'gsf-data-centre' ), $midterm - $baseline );
				} else {
					$display_score = $baseline;
					$display_label = __( 'Baseline score', 'gsf-data-centre' );
				}

				$rows[] = array(
					'short_label'   => $short_category,
					'baseline_raw'  => $baseline,
					'midterm_raw'   => $midterm,
					'endline_raw'   => $endline,
					'endline'       => number_format_i18n( $endline ),
					'change'        => $endline - $baseline,
					'display_score' => number_format_i18n( $display_score ),
					'display_label' => $display_label,
					'count'         => (int) $entry['rows'],
				);
			}

			$panels[] = array(
				'type'  => $type,
				'rows'  => $rows,
				'chart' => array(
					'labels'   => array(
						__( 'Baseline', 'gsf-data-centre' ),
						__( 'Mid-term', 'gsf-data-centre' ),
						__( 'Endline', 'gsf-data-centre' ),
					),
					'datasets' => array_map(
						static function ( $row ) {
							return array(
								'label' => $row['short_label'],
								'data'  => array( $row['baseline_raw'], $row['midterm_raw'], $row['endline_raw'] ),
							);
						},
						$rows
					),
					'suggestedMax' => self::stacked_chart_suggested_max( $rows ),
				),
			);
		}

		return $panels;
	}

	private static function stacked_chart_suggested_max( array $rows ) {
		$totals = array( 0, 0, 0 );
		foreach ( $rows as $row ) {
			$totals[0] += (int) $row['baseline_raw'];
			$totals[1] += (int) $row['midterm_raw'];
			$totals[2] += (int) $row['endline_raw'];
		}

		return max( 5, max( $totals ) + 1 );
	}

	private static function average_score_group( array $group ) {
		if ( empty( $group['count'] ) ) {
			return 0;
		}

		return (int) round( $group['total'] / max( 1, (int) $group['count'] ) );
	}

	private static function get_ecoequity_category_rows( array $scores ) {
		$groups = array();

		foreach ( $scores as $score ) {
			$category = trim( (string) get_post_meta( $score->ID, 'ecoequity_category', true ) );
			if ( '' === $category ) {
				continue;
			}

			$score_value = get_post_meta( $score->ID, 'category_score', true );
			if ( '' === trim( (string) $score_value ) ) {
				continue;
			}

			if ( ! isset( $groups[ $category ] ) ) {
				$groups[ $category ] = array(
					'total' => 0,
					'count' => 0,
				);
			}

			$groups[ $category ]['total'] += self::numeric_meta_value( $score->ID, 'category_score' );
			$groups[ $category ]['count']++;
		}

		$rows = array();
		foreach ( $groups as $category => $group ) {
			$count   = max( 1, (int) $group['count'] );
			$average = (int) round( $group['total'] / $count );

			$rows[] = array(
				'category'    => $category,
				'short_label' => self::short_category_label( $category ),
				'score'       => number_format_i18n( $average ),
				'score_raw'   => $average,
				'count'       => $count,
			);
		}

		usort(
			$rows,
			static function ( $a, $b ) {
				return $b['score_raw'] <=> $a['score_raw'];
			}
		);

		return $rows;
	}

	private static function short_category_label( $category ) {
		$category = trim( wp_strip_all_tags( (string) $category ) );
		if ( strlen( $category ) <= 54 ) {
			return $category;
		}

		return rtrim( substr( $category, 0, 51 ) ) . '...';
	}

	private static function get_ecoequity_summary_rows( array $scores ) {
		$groups = array();

		foreach ( $scores as $score ) {
			$stage_scores = array(
				'baseline' => self::ecoequity_stage_score( $score->ID, 'baseline', 'baseline_score', 'total_score' ),
				'midterm'  => self::ecoequity_stage_score( $score->ID, 'midterm', 'midterm_score', 'total_score' ),
				'endline'  => self::ecoequity_stage_score( $score->ID, 'endline', 'endline_score', 'total_score' ),
			);

			$has_stage_score = false;
			foreach ( $stage_scores as $score_result ) {
				if ( ! empty( $score_result['has_score'] ) ) {
					$has_stage_score = true;
					break;
				}
			}

			if ( ! $has_stage_score ) {
				continue;
			}

			$type = trim( (string) get_post_meta( $score->ID, 'organization_type', true ) );
			if ( '' === $type ) {
				$type = __( 'Unspecified', 'gsf-data-centre' );
			}

			if ( ! isset( $groups[ $type ] ) ) {
				$groups[ $type ] = array(
					'baseline'       => 0,
					'baseline_count' => 0,
					'midterm'        => 0,
					'midterm_count'  => 0,
					'endline'        => 0,
					'endline_count'  => 0,
				);
			}

			foreach ( $stage_scores as $stage => $score_result ) {
				if ( ! empty( $score_result['has_score'] ) ) {
					$groups[ $type ][ $stage ] += $score_result['value'];
					$groups[ $type ][ $stage . '_count' ]++;
				}
			}
		}

		$rows = array();
		foreach ( $groups as $type => $group ) {
			$baseline = ! empty( $group['baseline_count'] ) ? (float) $group['baseline'] / (int) $group['baseline_count'] : 0;
			$midterm  = ! empty( $group['midterm_count'] ) ? (float) $group['midterm'] / (int) $group['midterm_count'] : 0;
			$endline  = ! empty( $group['endline_count'] ) ? (float) $group['endline'] / (int) $group['endline_count'] : 0;

			if ( ! empty( $group['endline_count'] ) ) {
				$display_score = $endline;
				$display_label = sprintf( __( 'Average endline, %+s from baseline', 'gsf-data-centre' ), number_format_i18n( $endline - $baseline, 1 ) );
			} elseif ( ! empty( $group['midterm_count'] ) ) {
				$display_score = $midterm;
				$display_label = sprintf( __( 'Average mid-term, %+s from baseline', 'gsf-data-centre' ), number_format_i18n( $midterm - $baseline, 1 ) );
			} else {
				$display_score = $baseline;
				$display_label = __( 'Average baseline score', 'gsf-data-centre' );
			}

			$rows[] = array(
				'type'         => $type,
				'baseline'     => number_format_i18n( $baseline, 1 ),
				'midterm'      => number_format_i18n( $midterm, 1 ),
				'endline'      => number_format_i18n( $endline, 1 ),
				'baseline_raw' => $baseline,
				'midterm_raw'  => $midterm,
				'endline_raw'  => $endline,
				'change'       => $endline - $baseline,
				'display_score' => number_format_i18n( $display_score, 1 ),
				'display_label' => $display_label,
			);
		}

		usort(
			$rows,
			static function ( $a, $b ) {
				return strcmp( $a['type'], $b['type'] );
			}
		);

		return $rows;
	}

	private static function ecoequity_stage_score( $post_id, $stage, $specific_key, $fallback_key ) {
		$stage          = self::normalize_ecoequity_stage( $stage );
		$reported_stage = self::normalize_ecoequity_stage( get_post_meta( $post_id, 'assessment_stage', true ) );
		$fallback_value = get_post_meta( $post_id, $fallback_key, true );

		if ( '' !== trim( (string) $fallback_value ) && '' !== $reported_stage ) {
			if ( $reported_stage !== $stage ) {
				return array(
					'has_score' => false,
					'value'     => 0,
				);
			}

			return array(
				'has_score' => true,
				'value'     => self::numeric_string_value( $fallback_value ),
			);
		}

		$value = get_post_meta( $post_id, $specific_key, true );
		if ( '' !== trim( (string) $value ) ) {
			return array(
				'has_score' => true,
				'value'     => self::numeric_string_value( $value ),
			);
		}

		if ( 'baseline' === $stage && '' !== trim( (string) $fallback_value ) ) {
			return array(
				'has_score' => true,
				'value'     => self::numeric_string_value( $fallback_value ),
			);
		}

		return array(
			'has_score' => false,
			'value'     => 0,
		);
	}

	private static function format_project_organizations( array $values ) {
		$organizations = array();
		foreach ( $values as $value ) {
			$value = maybe_unserialize( $value );
			if ( is_array( $value ) ) {
				foreach ( $value as $item ) {
					if ( '' !== trim( (string) $item ) ) {
						$organizations[] = trim( (string) $item );
					}
				}
			} elseif ( '' !== trim( (string) $value ) ) {
				$organizations[] = trim( (string) $value );
			}
		}

		return implode( ', ', array_unique( $organizations ) );
	}

	private static function linked_post_title( $value ) {
		$value = maybe_unserialize( $value );
		if ( is_array( $value ) ) {
			$value = reset( $value );
		}

		if ( is_object( $value ) && isset( $value->ID ) ) {
			$value = $value->ID;
		}

		$post_id = absint( $value );
		if ( ! $post_id ) {
			return '';
		}

		$current_language = self::current_language();

		if ( $current_language && function_exists( 'pll_get_post' ) ) {
			$translated_id = pll_get_post( $post_id, $current_language );

			if ( $translated_id ) {
				$post_id = (int) $translated_id;
			}
		}

		return get_the_title( $post_id );
	}

	private static function get_project_type_progress_rows( array $projects ) {
		$groups = array();

		foreach ( $projects as $project ) {
			$type = trim( (string) get_post_meta( $project->ID, 'project_type', true ) );
			if ( '' === $type ) {
				$type = __( 'Unspecified', 'gsf-data-centre' );
			}

			if ( ! isset( $groups[ $type ] ) ) {
				$groups[ $type ] = array(
					'total'        => 0,
					'active'       => 0,
					'percent_sum'  => 0,
				);
			}

			$status  = strtolower( (string) get_post_meta( $project->ID, 'project_status', true ) );
			$percent = min( 100, max( 0, (int) get_post_meta( $project->ID, 'percent_complete', true ) ) );

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
				'type'    => $type,
				'total'   => (int) $group['total'],
				'active'  => (int) $group['active'],
				'percent' => (int) round( $group['percent_sum'] / $total ),
			);
		}

		usort(
			$rows,
			static function ( $a, $b ) {
				return $b['percent'] <=> $a['percent'];
			}
		);

		return $rows;
	}

	private static function get_dashboard_metrics() {
		return array(
			array(
				'key'   => 'territories',
				'title' => __( 'Territories', 'gsf-data-centre' ),
				'value' => number_format_i18n( self::get_project_territory_count() ),
				'label' => __( 'countries / regions represented by projects', 'gsf-data-centre' ),
			),
			array(
				'key'   => 'projects',
				'title' => __( 'Projects', 'gsf-data-centre' ),
				'value' => number_format_i18n( self::get_published_count( 'gsf_project' ) ),
				'label' => __( 'published project records in the Data Centre', 'gsf-data-centre' ),
			),
			array(
				'key'   => 'beneficiaries',
				'title' => __( 'Beneficiaries Reached', 'gsf-data-centre' ),
				'value' => self::format_metric_number( self::get_project_people_affected_total() ),
				'label' => __( 'people affected across published projects', 'gsf-data-centre' ),
			),
			array(
				'key'   => 'women',
				'title' => __( 'Women Participants', 'gsf-data-centre' ),
				'value' => self::get_project_women_affected_percent(),
				'label' => __( 'female / women share of people affected', 'gsf-data-centre' ),
			),
			array(
				'key'   => 'indigenous',
				'title' => __( 'Indigenous Participants', 'gsf-data-centre' ),
				'value' => self::format_metric_number( self::get_project_indigenous_affected_total() ),
				'label' => __( 'Indigenous people affected', 'gsf-data-centre' ),
			),
			array(
				'key'   => 'completions',
				'title' => __( 'Course Completions', 'gsf-data-centre' ),
				'value' => number_format_i18n( self::get_lms_completion_count() ),
				'label' => __( 'completed Learn course enrollments', 'gsf-data-centre' ),
			),
		);
	}

	public static function get_public_dashboard_metrics() {
		return self::get_dashboard_metrics();
	}

	private static function get_published_count( $post_type ) {
		return count( self::get_posts( $post_type, -1 ) );
	}

	private static function get_project_territory_count() {
		$projects = self::get_posts( 'gsf_project', -1 );
		$countries = array();

		foreach ( $projects as $project ) {
			$country = trim( (string) get_post_meta( $project->ID, 'country', true ) );
			if ( '' !== $country ) {
				$countries[ strtolower( $country ) ] = true;
			}
		}

		return count( $countries );
	}

	private static function get_project_people_affected_total() {
		$totals = self::get_project_people_affected_totals();
		return $totals['total'];
	}

	private static function get_project_indigenous_affected_total() {
		$totals = self::get_project_people_affected_totals();
		return $totals['indigenous'];
	}

	private static function get_project_women_affected_percent() {
		$totals = self::get_project_people_affected_totals();
		if ( ! $totals['total'] || ! $totals['female'] ) {
			return '0%';
		}

		return round( ( $totals['female'] / $totals['total'] ) * 100 ) . '%';
	}

	private static function get_project_people_affected_totals() {
		$projects = self::get_posts( 'gsf_project', -1 );
		$totals   = array(
			'total'                 => 0,
			'female'                => 0,
			'male'                  => 0,
			'other'                 => 0,
			'indigenous'            => 0,
			'indigenous_female'     => 0,
			'indigenous_male'       => 0,
			'non_indigenous_female' => 0,
			'non_indigenous_male'   => 0,
		);

		foreach ( $projects as $project ) {
			$female                = self::numeric_meta_value( $project->ID, 'people_affected_female' );
			$male                  = self::numeric_meta_value( $project->ID, 'people_affected_male' );
			$other                 = self::numeric_meta_value( $project->ID, 'people_affected_other' );
			$total                 = self::numeric_meta_value( $project->ID, 'people_affected_total' );
			$indigenous_female     = self::numeric_meta_value( $project->ID, 'people_affected_indigenous_female' );
			$indigenous_male       = self::numeric_meta_value( $project->ID, 'people_affected_indigenous_male' );
			$non_indigenous_female = self::numeric_meta_value( $project->ID, 'people_affected_non_indigenous_female' );
			$non_indigenous_male   = self::numeric_meta_value( $project->ID, 'people_affected_non_indigenous_male' );

			if ( ! $total && ( $female || $male || $other ) ) {
				$total = $female + $male + $other;
			}

			$totals['total']                 += $total;
			$totals['female']                += $female;
			$totals['male']                  += $male;
			$totals['other']                 += $other;
			$totals['indigenous_female']     += $indigenous_female;
			$totals['indigenous_male']       += $indigenous_male;
			$totals['non_indigenous_female'] += $non_indigenous_female;
			$totals['non_indigenous_male']   += $non_indigenous_male;
		}

		$totals['indigenous'] = $totals['indigenous_female'] + $totals['indigenous_male'];

		foreach ( self::get_disaggregation_people_affected_totals() as $key => $value ) {
			if ( isset( $totals[ $key ] ) ) {
				$totals[ $key ] += $value;
			}
		}

		return $totals;
	}

	private static function get_disaggregation_people_affected_totals() {
		global $wpdb;

		$table = $wpdb->prefix . 'gsf_disaggregation';
		$totals = array(
			'total'      => 0,
			'female'     => 0,
			'male'       => 0,
			'other'      => 0,
			'indigenous' => 0,
		);

		if ( ! self::table_exists( $table ) ) {
			return $totals;
		}

		$rows = $wpdb->get_results(
			"SELECT category, value_label, SUM(value_number) AS total
			FROM {$table}
			WHERE category IN ('sex_gender', 'indigenous_status', 'total')
			GROUP BY category, value_label",
			ARRAY_A
		);

		foreach ( $rows as $row ) {
			$category = sanitize_key( $row['category'] ?? '' );
			$label    = self::normalize_disaggregation_label( $row['value_label'] ?? '' );
			$value    = max( 0, (float) ( $row['total'] ?? 0 ) );

			if ( 'sex_gender' === $category ) {
				if ( false !== strpos( $label, 'female' ) || false !== strpos( $label, 'women' ) || false !== strpos( $label, 'woman' ) ) {
					$totals['female'] += $value;
				} elseif ( false !== strpos( $label, 'male' ) || false !== strpos( $label, 'men' ) || false !== strpos( $label, 'man' ) ) {
					$totals['male'] += $value;
				} else {
					$totals['other'] += $value;
				}
			} elseif ( 'indigenous_status' === $category ) {
				if ( false === strpos( $label, 'non-indigenous' ) && false === strpos( $label, 'non indigenous' ) && false !== strpos( $label, 'indigenous' ) ) {
					$totals['indigenous'] += $value;
				}
			} elseif ( 'total' === $category ) {
				$totals['total'] += $value;
			}
		}

		$sex_gender_total = $totals['female'] + $totals['male'] + $totals['other'];
		if ( $sex_gender_total > $totals['total'] ) {
			$totals['total'] = $sex_gender_total;
		}

		return $totals;
	}

	private static function normalize_disaggregation_label( $label ) {
		$label = strtolower( wp_strip_all_tags( (string) $label ) );
		$label = preg_replace( '/\s*\(.*/', '', $label );
		$label = str_replace( array( '_', '/' ), array( ' ', ' ' ), $label );
		$label = preg_replace( '/\s+/', ' ', $label );

		return trim( $label );
	}

	private static function numeric_meta_value( $post_id, $key ) {
		$value = get_post_meta( $post_id, $key, true );
		if ( '' === $value || null === $value ) {
			return 0;
		}

		return max( 0, (int) preg_replace( '/[^0-9]/', '', (string) $value ) );
	}

	private static function format_metric_number( $value, $fallback = '' ) {
		if ( '' === $value || null === $value ) {
			return $fallback;
		}

		$number = is_numeric( $value ) ? (int) $value : self::numeric_string_value( $value );
		if ( ! $number && '' !== $fallback ) {
			return $fallback;
		}

		return number_format_i18n( $number );
	}

	private static function numeric_string_value( $value ) {
		$value = str_replace( ',', '', (string) $value );
		if ( preg_match( '/-?\d+(?:\.\d+)?/', $value, $matches ) ) {
			return (float) $matches[0];
		}

		return 0;
	}

	private static function get_lms_enrollments_table() {
		global $wpdb;

		if ( function_exists( 'gsf_scorm_lite_enrollments_table_name' ) ) {
			return gsf_scorm_lite_enrollments_table_name();
		}

		return $wpdb->prefix . 'gsf_lms_enrollments';
	}

	private static function table_exists( $table ) {
		global $wpdb;

		return $table === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
	}

	private static function get_lms_completion_count() {
		global $wpdb;

		$table = self::get_lms_enrollments_table();
		if ( ! self::table_exists( $table ) ) {
			return 0;
		}

		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE status = 'completed'" );
	}

	private static function get_lms_women_participant_percent( $fallback ) {
		global $wpdb;

		$table = self::get_lms_enrollments_table();
		if ( ! self::table_exists( $table ) ) {
			return $fallback;
		}

		$total = (int) $wpdb->get_var( "SELECT COUNT(DISTINCT user_id) FROM {$table}" );
		if ( ! $total ) {
			return $fallback;
		}

		$female = (int) $wpdb->get_var(
			"SELECT COUNT(DISTINCT e.user_id)
			FROM {$table} e
			INNER JOIN {$wpdb->usermeta} um ON um.user_id = e.user_id
			WHERE um.meta_key = 'gsf_sex' AND um.meta_value = 'female'"
		);

		if ( ! $female ) {
			return $fallback;
		}

		return round( ( $female / $total ) * 100 ) . '%';
	}

	private static function metric_icon_svg( $key ) {
		$icons = array(
			'territories'   => '<svg viewBox="0 0 24 24" focusable="false" aria-hidden="true"><circle cx="12" cy="12" r="9"></circle><path d="M3 12h18M12 3c3 3 3 15 0 18M12 3c-3 3-3 15 0 18"></path></svg>',
			'projects'      => '<svg viewBox="0 0 24 24" focusable="false" aria-hidden="true"><path d="M12 21V9"></path><path d="M7 10c-2-2-2-5 0-7 3 0 5 2 5 5-3 0-5 1-5 2Z"></path><path d="M17 13c2-2 2-5 0-7-3 0-5 2-5 5 3 0 5 1 5 2Z"></path><path d="M5 21h14"></path></svg>',
			'beneficiaries' => '<svg viewBox="0 0 24 24" focusable="false" aria-hidden="true"><path d="M16 11a4 4 0 1 0-8 0"></path><circle cx="12" cy="7" r="3"></circle><path d="M4 21v-2a5 5 0 0 1 5-5h6a5 5 0 0 1 5 5v2"></path><path d="M19 8a2 2 0 0 1 0 4M5 8a2 2 0 0 0 0 4"></path></svg>',
			'women'         => '<svg viewBox="0 0 24 24" focusable="false" aria-hidden="true"><circle cx="12" cy="8" r="5"></circle><path d="M12 13v8M8 17h8"></path></svg>',
			'indigenous'    => '<svg viewBox="0 0 24 24" focusable="false" aria-hidden="true"><circle cx="12" cy="8" r="4"></circle><path d="M5 21v-2a7 7 0 0 1 14 0v2"></path><path d="M17 3c-3 0-5 2-5 5 3 0 5-2 5-5Z"></path><path d="M12 8c-2-2-4-2-6-1 1 3 3 4 6 3"></path></svg>',
			'completions'   => '<svg viewBox="0 0 24 24" focusable="false" aria-hidden="true"><path d="M4 10 12 5l8 5-8 5-8-5Z"></path><path d="M7 12v4c2 2 8 2 10 0v-4"></path><path d="M20 10v5"></path></svg>',
		);

		return $icons[ $key ] ?? $icons['projects'];
	}

	private static function render_project_story_cards( array $projects ) {
		$cards = array_slice( $projects, 0, 3 );
		ob_start();
		foreach ( $cards as $project ) :
			$type    = get_post_meta( $project->ID, 'project_type', true );
			$country = get_post_meta( $project->ID, 'country', true );
			$status  = get_post_meta( $project->ID, 'project_status', true );
			$percent = min( 100, max( 0, (int) get_post_meta( $project->ID, 'percent_complete', true ) ) );
			$has_image = has_post_thumbnail( $project );
			?>
			<article class="gsf-story-mini-card <?php echo $has_image ? '' : 'gsf-story-mini-card--text-only'; ?>">
				<?php if ( $has_image ) : ?>
					<a class="gsf-story-mini-card__media" href="<?php echo esc_url( get_permalink( $project ) ); ?>">
						<?php echo get_the_post_thumbnail( $project, 'medium_large', array( 'alt' => '' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					</a>
				<?php endif; ?>
				<div class="gsf-story-mini-card__content">
					<div class="gsf-story-mini-card__meta">
						<?php if ( $type ) : ?><span><?php echo esc_html( $type ); ?></span><?php endif; ?>
						<?php if ( $country ) : ?><span><?php echo esc_html( $country ); ?></span><?php endif; ?>
					</div>
					<h3><a href="<?php echo esc_url( get_permalink( $project ) ); ?>"><?php echo esc_html( $project->post_title ); ?></a></h3>
					<?php if ( $project->post_excerpt || $project->post_content ) : ?>
						<p><?php echo esc_html( wp_trim_words( $project->post_excerpt ?: wp_strip_all_tags( $project->post_content ), 18 ) ); ?></p>
					<?php endif; ?>
					<div class="gsf-story-mini-card__footer">
						<span class="gsf-milestone-status gsf-milestone-status--<?php echo esc_attr( sanitize_html_class( strtolower( str_replace( ' ', '-', $status ) ) ) ); ?>"><?php echo esc_html( $status ?: __( 'Tracked', 'gsf-data-centre' ) ); ?></span>
						<span class="gsf-story-mini-card__percent"><?php echo esc_html( $percent . '%' ); ?></span>
					</div>
					<span class="gsf-milestone-track"><span style="width:<?php echo esc_attr( $percent ); ?>%"></span></span>
				</div>
			</article>
			<?php
		endforeach;

		return ob_get_clean();
	}

	private static function render_story_panel( $story ) {
		$url     = $story ? get_post_meta( $story->ID, 'gsf_story_url', true ) : home_url( '/case-studies/' );
		$image   = $story && has_post_thumbnail( $story ) ? get_the_post_thumbnail( $story, 'large', array( 'alt' => '' ) ) : '';
		$country = $story ? get_post_meta( $story->ID, 'gsf_story_country', true ) : '';

		ob_start();
		?>
		<div class="hub-story-panel hub-story-panel--featured">
			<?php if ( $image ) : ?>
				<div class="hub-highlight-card__media"><?php echo $image; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
			<?php endif; ?>
			<p class="hub-section__eyebrow"><?php esc_html_e( 'Featured story', 'gsf-data-centre' ); ?></p>
			<h3><?php echo esc_html( $story ? $story->post_title : __( 'Featured data story', 'gsf-data-centre' ) ); ?></h3>
			<?php if ( $country ) : ?>
				<p class="hub-caption"><?php echo esc_html( $country ); ?></p>
			<?php endif; ?>
			<div class="hub-story-panel__actions wp-block-buttons"><div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="<?php echo esc_url( $url ); ?>"><?php esc_html_e( 'Read story', 'gsf-data-centre' ); ?></a></div></div>
		</div>
		<?php
		return ob_get_clean();
	}

	private static function get_participation_chart_data() {
		$totals = self::get_project_people_affected_totals();
		$other  = $totals['other'];

		if ( $totals['total'] > ( $totals['female'] + $totals['male'] + $totals['other'] ) ) {
			$other += $totals['total'] - ( $totals['female'] + $totals['male'] + $totals['other'] );
		}

		return array(
			'labels' => array(
				__( 'Female / women', 'gsf-data-centre' ),
				__( 'Male / men', 'gsf-data-centre' ),
				__( 'Other / not specified', 'gsf-data-centre' ),
			),
			'values' => array(
				$totals['female'],
				$totals['male'],
				$other,
			),
		);
	}

	private static function get_completion_chart_data() {
		$live_data = self::get_lms_completion_chart_data();
		if ( $live_data ) {
			return $live_data;
		}

		return array(
			'label'  => __( 'Course completions', 'gsf-data-centre' ),
			'labels' => array( 'May 2023', 'Sep 2023', 'Jan 2024', 'May 2024', 'Sep 2024', 'Jan 2025', 'May 2025' ),
			'values' => array( 180, 260, 440, 560, 720, 980, 1485 ),
		);
	}

	private static function get_lms_completion_chart_data() {
		global $wpdb;

		$table = self::get_lms_enrollments_table();
		if ( ! self::table_exists( $table ) ) {
			return array();
		}

		$rows = $wpdb->get_results(
			"SELECT DATE_FORMAT(completed_at, '%Y-%m-01') AS month_key, COUNT(*) AS completions
			FROM {$table}
			WHERE status = 'completed' AND completed_at IS NOT NULL
			GROUP BY month_key
			ORDER BY month_key ASC",
			ARRAY_A
		);

		if ( empty( $rows ) ) {
			return array();
		}

		$labels = array();
		$values = array();

		foreach ( $rows as $row ) {
			$labels[] = date_i18n( 'M Y', strtotime( $row['month_key'] ) );
			$values[] = (int) $row['completions'];
		}

		return array(
			'label'  => __( 'Course completions', 'gsf-data-centre' ),
			'labels' => $labels,
			'values' => $values,
		);
	}

	private static function get_territory_chart_data( $projects ) {
		$rows = self::get_project_territory_rows( $projects );

		return array(
			'label'  => __( 'Projects', 'gsf-data-centre' ),
			'labels' => wp_list_pluck( $rows, 'label' ),
			'values' => wp_list_pluck( $rows, 'count' ),
		);
	}

	private static function get_project_territory_rows( $projects ) {
		$counts = array();

		foreach ( $projects as $project ) {
			$country = trim( (string) get_post_meta( $project->ID, 'country', true ) );

			if ( '' === $country ) {
				$country = __( 'Unspecified', 'gsf-data-centre' );
			}

			$key = strtolower( $country );
			if ( ! isset( $counts[ $key ] ) ) {
				$counts[ $key ] = array(
					'label' => $country,
					'count' => 0,
				);
			}

			$counts[ $key ]['count']++;
		}

		usort(
			$counts,
			function ( $a, $b ) {
				return strcasecmp( $a['label'], $b['label'] );
			}
		);

		return array_values( $counts );
	}

	private static function get_territory_coordinates() {
		return array(
			'antigua and barbuda'             => array( 17.0608, -61.7964 ),
			'bahamas'                         => array( 25.0343, -77.3963 ),
			'barbados'                        => array( 13.1939, -59.5432 ),
			'belize'                          => array( 17.1899, -88.4976 ),
			'cuba'                            => array( 21.5218, -77.7812 ),
			'dominica'                        => array( 15.4150, -61.3710 ),
			'dominican republic'              => array( 18.7357, -70.1627 ),
			'grenada'                         => array( 12.1165, -61.6790 ),
			'guyana'                          => array( 4.8604, -58.9302 ),
			'haiti'                           => array( 18.9712, -72.2852 ),
			'jamaica'                         => array( 18.1096, -77.2975 ),
			'montserrat'                      => array( 16.7425, -62.1874 ),
			'regional'                        => array( 18.2208, -66.5901 ),
			'saint lucia'                     => array( 13.9094, -60.9789 ),
			'st. kitts and nevis'             => array( 17.3578, -62.7830 ),
			'st.kitts and nevis'              => array( 17.3578, -62.7830 ),
			'st. vincent and the grenadines'  => array( 12.9843, -61.2872 ),
			'st vincent and the grenadines'   => array( 12.9843, -61.2872 ),
			'suriname'                        => array( 3.9193, -56.0278 ),
			'trinidad and tobago'             => array( 10.6918, -61.2225 ),
			't&t'                             => array( 10.6918, -61.2225 ),
		);
	}

	private static function get_territory_map_points( $projects ) {
		$rows = self::get_project_territory_rows( $projects );
		$coordinates = self::get_territory_coordinates();
		$counts = wp_list_pluck( $rows, 'count' );
		$max_count = $counts ? max( 1, (int) max( $counts ) ) : 1;
		$points = array();

		foreach ( $rows as $row ) {
			$key = strtolower( $row['label'] );

			if ( ! isset( $coordinates[ $key ] ) ) {
				continue;
			}

			$points[] = array(
				'label' => $row['label'],
				'count' => (int) $row['count'],
				'radius' => (int) round( 9000 + ( ( (int) $row['count'] / $max_count ) * 18000 ) ),
				'lat'   => $coordinates[ $key ][0],
				'lng'   => $coordinates[ $key ][1],
			);
		}

		return $points;
	}

	private static function render_territory_map( $projects ) {
		$points = self::get_territory_map_points( $projects );
		$rows   = self::get_project_territory_rows( $projects );

		ob_start();
		?>
		<div class="gsf-data-map" aria-label="<?php esc_attr_e( 'OpenStreetMap view of projects by Caribbean territory', 'gsf-data-centre' ); ?>">
			<div
				class="gsf-data-map__canvas"
				data-gsf-leaflet-map
				data-points="<?php echo esc_attr( wp_json_encode( $points ) ); ?>"
			>
				<noscript><?php esc_html_e( 'Enable JavaScript to view the interactive project map.', 'gsf-data-centre' ); ?></noscript>
			</div>
			<ul class="gsf-data-map__legend">
				<?php foreach ( $rows as $row ) : ?>
					<li><span><?php echo esc_html( $row['label'] ); ?></span><strong><?php echo esc_html( $row['count'] ); ?></strong></li>
				<?php endforeach; ?>
			</ul>
		</div>
		<?php
		return ob_get_clean();
	}

	private static function get_posts( $post_type, $limit ) {
		$args = array(
			'post_type'      => $post_type,
			'post_status'    => 'publish',
			'posts_per_page' => $limit,
			'orderby'        => 'menu_order title',
			'order'          => 'ASC',
		);

		$current_language = self::current_language();

		if ( $current_language ) {
			$args['lang'] = $current_language;
		}

		return get_posts(
			$args
		);
	}

	private static function get_featured_story() {
		$args = array(
			'post_type'      => 'gsf_data_story',
			'post_status'    => 'publish',
			'posts_per_page' => 1,
			'meta_key'       => 'gsf_story_featured',
			'meta_value'     => '1',
		);

		$current_language = self::current_language();

		if ( $current_language ) {
			$args['lang'] = $current_language;
		}

		$stories = get_posts( $args );

		if ( $stories ) {
			return $stories[0];
		}

		$stories = self::get_posts( 'gsf_data_story', 1 );
		return $stories ? $stories[0] : null;
	}

	public static function sync_grantee_projects( $force = false ) {
		if ( ! post_type_exists( 'gsf_project' ) ) {
			return array();
		}

		self::delete_legacy_seed_projects();

		if ( ! $force && get_option( self::GRANTEE_PROJECT_SYNC_OPTION ) === self::GRANTEE_PROJECT_SYNC_VERSION ) {
			return array();
		}

		$project_ids = array();
		$projects    = self::grantee_project_records();

		foreach ( $projects as $index => $project ) {
			$post_id = self::find_grantee_project_id( $project );
			$args    = array(
				'post_type'   => 'gsf_project',
				'post_title'  => $project['title'],
				'post_status' => 'publish',
				'menu_order'  => $index,
			);

			if ( $post_id ) {
				$args['ID'] = $post_id;
				$post_id    = wp_update_post( $args, true );
			} else {
				$post_id = wp_insert_post( $args, true );
			}

			if ( ! $post_id || is_wp_error( $post_id ) ) {
				continue;
			}

			update_post_meta( $post_id, 'project_type', 'Grant project' );
			update_post_meta( $post_id, 'country', $project['country'] );
			update_post_meta( $post_id, 'implementing_party', $project['grantee'] );
			update_post_meta( $post_id, 'funding_source', $project['nctf'] );
			update_post_meta( $post_id, 'gsf_project_source_key', self::grantee_project_source_key( $project ) );
			delete_post_meta( $post_id, 'organizations' );
			add_post_meta( $post_id, 'organizations', $project['grantee'] );
			self::assign_default_language( (int) $post_id );

			$project_ids[] = (int) $post_id;
		}

		if ( count( $project_ids ) === count( $projects ) ) {
			update_option( self::GRANTEE_PROJECT_SYNC_OPTION, self::GRANTEE_PROJECT_SYNC_VERSION );
		}

		return $project_ids;
	}

	private static function grantee_project_records() {
		return array(
			array( 'nctf' => 'GSDTF', 'country' => 'Grenada', 'grantee' => 'GSDTF (Singleproject)', 'title' => 'Enhanced MPA Management and Monitoring of the Woburn Clarkes Court Bay Marine Protected Area' ),
			array( 'nctf' => 'DNCTF', 'country' => 'Dominica', 'grantee' => '(AKTA) Inc.', 'title' => 'Building Capacity for Ecosystem Restoration in the Castle Bruce District' ),
			array( 'nctf' => 'DNCTF', 'country' => 'Dominica', 'grantee' => 'Inter-American Institute for Cooperation on Agriculture (IICA)', 'title' => 'Climate Smart Forage Transformation: Strengthening Dominica’s Livestock Sector with Resilient Low-Cost Feed Solutions' ),
			array( 'nctf' => 'PACT', 'country' => 'Belize', 'grantee' => 'Belize Wildlife & Referral Clinic (BWRC)', 'title' => 'Wildlife Ambassador Program - One Health Fellowship and Gender-Responsive Capacity Building' ),
			array( 'nctf' => 'PACT', 'country' => 'Belize', 'grantee' => 'Belize Women’s Seaweed Farmers Association (BWSFA)', 'title' => 'Building a Resilient, Gender Inclusive Seaweed Enterprise - BWSFA Seaweed Powder, Tourism & Market Pilot' ),
			array( 'nctf' => 'PACT', 'country' => 'Belize', 'grantee' => 'Home of Indigenous Arts Belize Limited (HIAB)', 'title' => 'Climate Resilience Through Indigenous Arts and Ecosystem Stewardship' ),
			array( 'nctf' => 'PACT', 'country' => 'Belize', 'grantee' => 'Community Baboon Sanctuary Women’s Conservation Group (CBSWCG)', 'title' => 'Growing Resilience & Gender Equity through Backyard Gardens and Local Markets' ),
			array( 'nctf' => 'PACT', 'country' => 'Belize', 'grantee' => 'Ya’axche Conservation Trust', 'title' => 'Voices of the Watershed: Women and Youths Restoring Rivers' ),
			array( 'nctf' => 'NCTFJ', 'country' => 'Jamaica', 'grantee' => 'Alligator Head Foundation', 'title' => 'Restoring Resilience: Improving Coral Reef Ecosystem Health in the East Portland Fish Sanctuary' ),
			array( 'nctf' => 'NCTFJ', 'country' => 'Jamaica', 'grantee' => 'Alloa Fisherman’s Cooperative Union', 'title' => 'Offshore Sustainable Pelagic Fishing for Jamaica’s Coastal Communities' ),
			array( 'nctf' => 'NCTFJ', 'country' => 'Jamaica', 'grantee' => 'I-SEEED Youths Ltd', 'title' => 'Roots and Roominants: Nourishing livelihoods, protecting nature 1.0' ),
			array( 'nctf' => 'NCTFJ', 'country' => 'Jamaica', 'grantee' => 'National Environment and Planning Agency (NEPA)', 'title' => 'Wetland Restoration and Conservation' ),
			array( 'nctf' => 'NCTFJ', 'country' => 'Jamaica', 'grantee' => 'UWI CMS Discovery Bay Marine Laboratory and Field Station', 'title' => 'Protecting Sea Turtle Nesting Beaches with Improved Surveillance and Protection in Discovery Bay, St. Ann, Jamaica' ),
			array( 'nctf' => 'NCTFJ', 'country' => 'Jamaica', 'grantee' => 'Department of Life Sciences, UWI Mona Campus', 'title' => 'Bitterwood (Picrasma excelsa) for Pest Management: Protecting Crops, Empowering Women, Conserving Ecosystems' ),
			array( 'nctf' => 'NCTFJ', 'country' => 'Jamaica', 'grantee' => 'White River Marine Association', 'title' => 'Strengthening Protection and Community Advocacy in the White River Fish Sanctuary' ),
			array( 'nctf' => 'SVGCF', 'country' => 'St. Vincent and the Grenadines', 'grantee' => 'Ministry of Education, St. Vincent and the Grenadines', 'title' => 'Ministry of Education, St. Vincent and the Grenadines' ),
			array( 'nctf' => 'SVGCF', 'country' => 'St. Vincent and the Grenadines', 'grantee' => 'Marion House', 'title' => 'Ministry of Education, St. Vincent and the Grenadines' ),
			array( 'nctf' => 'GHFS', 'country' => 'Suriname', 'grantee' => 'ARELIS (Apiculture & Agriculture Research & Learning Institute in Suriname)', 'title' => 'Growing Strong with Arelis, for a Resilient Future - Preserving Cultural Heritage and Empowering Maroon wo(men) through Agroforestry and Social Inclusion' ),
			array( 'nctf' => 'GHFS', 'country' => 'Suriname', 'grantee' => 'Kibii Foundation', 'title' => 'Preservation of Taro and Cocoyam Varieties in the District of Marowijne' ),
			array( 'nctf' => 'GHFS', 'country' => 'Suriname', 'grantee' => 'Stichting Het Moederhart', 'title' => 'Breath for the Mangrove: A Gender-Responsive and Ecosystem-Based Rehabilitation of the Nickerie River-Banks' ),
			array( 'nctf' => 'GHFS', 'country' => 'Suriname', 'grantee' => 'Stichting Tropenbos International Suriname', 'title' => 'Empowering Saamaka Youth for Climate-Resilient Landscapes and Locally Driven Solutions' ),
			array( 'nctf' => 'SLUNCF', 'country' => 'Saint Lucia', 'grantee' => 'Bel Ti Jardin Twizin (BTJT)', 'title' => 'Enhancing Backyard Garden Production and Livelihoods of BTJT Members' ),
			array( 'nctf' => 'SLUNCF', 'country' => 'Saint Lucia', 'grantee' => 'Making A Difference (MAD)', 'title' => 'Seeds of Change: Youth Agricultural Development Programme' ),
			array( 'nctf' => 'SLUNCF', 'country' => 'Saint Lucia', 'grantee' => 'Tech Roots Foundation', 'title' => 'Water for All: Gender-Smart Water Access & Climate Resilience Support for Smallholder Agribusinesses in Saint Lucia' ),
			array( 'nctf' => 'PAT', 'country' => 'Guyana', 'grantee' => 'Protected Areas Commission (PAC) {Single project}', 'title' => 'Strengthening Inclusive Participation and Practical Safeguards in Guyana’s National Protected Areas System (NPAS)' ),
		);
	}

	private static function legacy_seed_projects() {
		return array(
			array( 'title' => 'Women-led mangrove restoration', 'implementing_party' => 'Community Restoration Group', 'funding_source' => 'GSF / CBF' ),
			array( 'title' => 'Protected area management support', 'implementing_party' => 'National Conservation Trust Fund', 'funding_source' => 'Canada' ),
			array( 'title' => 'Community climate resilience monitoring', 'implementing_party' => 'Local Environmental Organization', 'funding_source' => 'GSF' ),
		);
	}

	private static function delete_legacy_seed_projects() {
		foreach ( self::legacy_seed_projects() as $project ) {
			foreach ( self::find_project_ids_by_title( $project['title'] ) as $post_id ) {
				$implementing_party = trim( (string) get_post_meta( $post_id, 'implementing_party', true ) );
				$funding_source     = trim( (string) get_post_meta( $post_id, 'funding_source', true ) );

				if ( $project['implementing_party'] === $implementing_party || $project['funding_source'] === $funding_source ) {
					wp_delete_post( $post_id, true );
				}
			}
		}
	}

	private static function find_grantee_project_id( array $project ) {
		$source_key = self::grantee_project_source_key( $project );
		$matches    = get_posts(
			array(
				'post_type'        => 'gsf_project',
				'post_status'      => 'any',
				'posts_per_page'   => 1,
				'fields'           => 'ids',
				'meta_key'         => 'gsf_project_source_key',
				'meta_value'       => $source_key,
				'suppress_filters' => true,
			)
		);

		if ( $matches ) {
			return (int) $matches[0];
		}

		$title_matches = self::find_project_ids_by_title( $project['title'] );
		foreach ( $title_matches as $post_id ) {
			$implementing_party = trim( (string) get_post_meta( $post_id, 'implementing_party', true ) );
			$organizations      = array_map( 'trim', array_map( 'strval', get_post_meta( $post_id, 'organizations', false ) ) );

			if ( $project['grantee'] === $implementing_party || in_array( $project['grantee'], $organizations, true ) ) {
				return (int) $post_id;
			}
		}

		if ( ! self::grantee_project_title_is_duplicate( $project['title'] ) && $title_matches ) {
			return (int) $title_matches[0];
		}

		return 0;
	}

	private static function find_project_ids_by_title( $title ) {
		global $wpdb;

		return array_map(
			'intval',
			$wpdb->get_col(
				$wpdb->prepare(
					"SELECT ID FROM {$wpdb->posts} WHERE post_type = %s AND post_title = %s AND post_status <> 'trash' ORDER BY ID ASC",
					'gsf_project',
					$title
				)
			)
		);
	}

	private static function grantee_project_title_is_duplicate( $title ) {
		static $title_counts = null;

		if ( null === $title_counts ) {
			$title_counts = array();
			foreach ( self::grantee_project_records() as $project ) {
				$key = strtolower( $project['title'] );
				if ( ! isset( $title_counts[ $key ] ) ) {
					$title_counts[ $key ] = 0;
				}
				$title_counts[ $key ]++;
			}
		}

		$key = strtolower( $title );
		return isset( $title_counts[ $key ] ) && $title_counts[ $key ] > 1;
	}

	private static function grantee_project_source_key( array $project ) {
		return hash( 'sha256', $project['nctf'] . '|' . $project['grantee'] . '|' . $project['title'] );
	}

	private static function seed_defaults() {
		if ( get_option( 'gsf_data_centre_seeded' ) ) {
			return;
		}

		$story_id = wp_insert_post(
			array(
				'post_type'    => 'gsf_data_story',
				'post_title'   => 'Women-led mangrove restoration in Saint Lucia',
				'post_content' => 'This project has strengthened community leadership and coastal resilience through women-led restoration work.',
				'post_status'  => 'publish',
			)
		);
		if ( $story_id && ! is_wp_error( $story_id ) ) {
			update_post_meta( $story_id, 'gsf_story_country', 'Saint Lucia' );
			update_post_meta( $story_id, 'gsf_story_metric_one_value', '2,350' );
			update_post_meta( $story_id, 'gsf_story_metric_one_label', 'Mangroves planted' );
			update_post_meta( $story_id, 'gsf_story_metric_two_value', '180' );
			update_post_meta( $story_id, 'gsf_story_metric_two_label', 'Women involved' );
			update_post_meta( $story_id, 'gsf_story_metric_three_value', '12 ha' );
			update_post_meta( $story_id, 'gsf_story_metric_three_label', 'Area restored' );
			update_post_meta( $story_id, 'gsf_story_url', home_url( '/case-studies/' ) );
			update_post_meta( $story_id, 'gsf_story_featured', '1' );
		}

		$project_ids = self::sync_grantee_projects( true );

		$indicator_id = wp_insert_post(
			array(
				'post_type'   => 'gsf_indicator',
				'post_title'  => 'Gender-responsive resilience capacity improved',
				'post_status' => 'draft',
			)
		);
		if ( $indicator_id && ! is_wp_error( $indicator_id ) ) {
			update_post_meta( $indicator_id, 'indicator_code', 'OUT-1.1' );
			update_post_meta( $indicator_id, 'indicator_level', 'Outcome' );
			update_post_meta( $indicator_id, 'indicator_text', 'Number of partner organizations applying gender-responsive resilience practices.' );
			update_post_meta( $indicator_id, 'baseline', '0' );
			update_post_meta( $indicator_id, 'target', '12 organizations' );
			update_post_meta( $indicator_id, 'frequency', 'Annual' );
			update_post_meta( $indicator_id, 'method', 'Partner reporting and validation interviews.' );
			update_post_meta( $indicator_id, 'source', 'Annual partner reports' );
			update_post_meta( $indicator_id, 'responsible_party', 'GSF Hub Secretariat' );
			update_post_meta( $indicator_id, 'gender_responsiveness', 'Tracks institutional use of gender-responsive programming practices.' );

			$result_id = wp_insert_post(
				array(
					'post_type'   => 'gsf_ind_result',
					'post_title'  => 'OUT-1.1 2026 result',
					'post_status' => 'publish',
				)
			);
			if ( $result_id && ! is_wp_error( $result_id ) ) {
				update_post_meta( $result_id, 'indicator', $indicator_id );
				update_post_meta( $result_id, 'reporting_year_period', '2026' );
				update_post_meta( $result_id, 'result_value', '3 organizations' );
				update_post_meta( $result_id, 'result_narrative', 'Initial uptake documented through project reporting.' );
				update_post_meta( $result_id, 'project', $project_ids[0] ?? 0 );
			}
		}

		$score_id = wp_insert_post(
			array(
				'post_type'   => 'gsf_ecoequity',
				'post_title'  => 'National Conservation Trust Fund',
				'post_status' => 'publish',
			)
		);
		if ( $score_id && ! is_wp_error( $score_id ) ) {
			update_post_meta( $score_id, 'organization_name', 'National Conservation Trust Fund' );
			update_post_meta( $score_id, 'organization_type', 'NCTF' );
			update_post_meta( $score_id, 'baseline_score', '42' );
			update_post_meta( $score_id, 'midterm_score', '61' );
			update_post_meta( $score_id, 'endline_score', '78' );
		}

		update_option( 'gsf_data_centre_seeded', '1' );
	}
}

register_activation_hook( __FILE__, array( 'GSF_Data_Centre', 'activate' ) );
GSF_Data_Centre::init();
