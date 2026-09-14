<?php
/**
 * PMF, MEAL, and Gender Monitoring extensions for the GSF Data Centre.
 *
 * @package GSF_Data_Centre
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class GSF_Data_Centre_MEAL {
	const VERSION        = '0.3.0';
	const SCHEMA_VERSION = '2026070301';

	public static function init() {
		add_action( 'init', array( __CLASS__, 'register_post_types' ), 9 );
		add_action( 'init', array( __CLASS__, 'register_taxonomies' ), 10 );
		add_action( 'init', array( __CLASS__, 'register_shortcodes' ), 11 );
		add_filter( 'register_post_type_args', array( __CLASS__, 'filter_data_centre_post_type_capabilities' ), 30, 2 );
		add_action( 'admin_init', array( __CLASS__, 'maybe_upgrade' ) );
		add_action( 'admin_menu', array( __CLASS__, 'register_admin_menu' ), 94 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_admin_assets' ) );
		add_action( 'manage_posts_extra_tablenav', array( __CLASS__, 'render_project_csv_actions' ) );
		add_action( 'rest_api_init', array( __CLASS__, 'register_rest_routes' ) );
		add_action( 'admin_post_gsf_data_centre_export_csv', array( __CLASS__, 'handle_csv_export' ) );
		add_action( 'admin_post_gsf_data_centre_import_csv', array( __CLASS__, 'handle_csv_import' ) );
		add_action( 'admin_post_gsf_data_centre_download_template', array( __CLASS__, 'handle_template_download' ) );
		add_action( 'admin_post_gsf_data_centre_save_disaggregation', array( __CLASS__, 'handle_disaggregation_save' ) );
	}

	public static function activate() {
		self::register_post_types();
		self::register_taxonomies();
		self::ensure_pods();
		self::sync_roles();
		self::seed_reference_data();
		self::write_csv_templates();
	}

	public static function normalize_ecoequity_stage( $stage ) {
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

	public static function maybe_upgrade() {
		if ( get_option( 'gsf_data_centre_meal_schema_version' ) !== self::SCHEMA_VERSION ) {
			self::ensure_pods();
			self::seed_reference_data();
			update_option( 'gsf_data_centre_meal_schema_version', self::SCHEMA_VERSION );
		}

		if ( get_option( 'gsf_data_centre_meal_roles_version' ) !== self::VERSION ) {
			self::sync_roles();
			update_option( 'gsf_data_centre_meal_roles_version', self::VERSION );
		}

		self::write_csv_templates();
	}

	public static function register_post_types() {
		$post_types = array(
			'gsf_organization' => array( 'Organizations', 'Organization', 'data-centre/organizations' ),
			'gsf_evidence'     => array( 'Evidence', 'Evidence Item', 'data-centre/evidence' ),
			'gsf_activity'     => array( 'Activities', 'Activity', 'data-centre/activities' ),
			'gsf_index'        => array( 'Index Scores', 'Index Score', 'data-centre/index-scores' ),
		);

		if ( ! function_exists( 'pods_api' ) ) {
			$post_types['gsf_indicator']  = array( 'Indicators', 'Indicator', 'data-centre/indicators' );
			$post_types['gsf_ind_result'] = array( 'Indicator Results', 'Indicator Result', 'data-centre/results' );
			$post_types['gsf_ecoequity']  = array( 'Ecoequity Scores', 'Ecoequity Score', 'data-centre/ecoequity' );
		}

		if ( ! post_type_exists( 'gsf_case_study' ) ) {
			$post_types['gsf_case_study'] = array( 'Case Studies', 'Case Study', 'case-studies' );
		}

		foreach ( $post_types as $post_type => $labels ) {
			if ( post_type_exists( $post_type ) ) {
				continue;
			}

			register_post_type(
				$post_type,
				array(
					'labels'              => array(
						'name'          => __( $labels[0], 'gsf-data-centre' ),
						'singular_name' => __( $labels[1], 'gsf-data-centre' ),
						'add_new_item'  => sprintf( __( 'Add %s', 'gsf-data-centre' ), __( $labels[1], 'gsf-data-centre' ) ),
						'edit_item'     => sprintf( __( 'Edit %s', 'gsf-data-centre' ), __( $labels[1], 'gsf-data-centre' ) ),
					),
					'public'              => true,
					'publicly_queryable'  => true,
					'exclude_from_search' => false,
					'has_archive'         => false,
					'rewrite'             => array( 'slug' => $labels[2] ),
					'show_ui'             => true,
					'show_in_menu'        => false,
					'show_in_rest'        => true,
					'supports'            => array( 'title', 'editor', 'thumbnail', 'custom-fields' ),
					'capability_type'     => 'post',
				)
			);
		}
	}

	public static function register_taxonomies() {
		$objects = array( 'gsf_project', 'gsf_indicator', 'gsf_ind_result', 'gsf_evidence', 'gsf_case_study', 'gsf_activity', 'gsf_index', 'gsf_organization' );
		$taxonomies = array(
			'gsf_result_level'      => array( 'Result Levels', 'Result Level', true ),
			'gsf_country'           => array( 'Countries', 'Country', true ),
			'gsf_organization_type' => array( 'Organization Types', 'Organization Type', true ),
			'gsf_project_type'      => array( 'Project Types', 'Project Type', true ),
			'gsf_conservation_type' => array( 'Conservation Types', 'Conservation Type', true ),
			'gsf_gender_theme'      => array( 'Gender Themes', 'Gender Theme', true ),
			'gsf_reporting_status'  => array( 'Reporting Statuses', 'Reporting Status', false ),
		);

		foreach ( $taxonomies as $taxonomy => $labels ) {
			register_taxonomy(
				$taxonomy,
				$objects,
				array(
					'labels'            => array(
						'name'          => __( $labels[0], 'gsf-data-centre' ),
						'singular_name' => __( $labels[1], 'gsf-data-centre' ),
					),
					'hierarchical'      => (bool) $labels[2],
					'public'            => true,
					'show_admin_column' => true,
					'show_in_rest'      => true,
					'rewrite'           => array( 'slug' => str_replace( 'gsf_', 'data-centre/', $taxonomy ) ),
				)
			);
		}
	}

	public static function register_shortcodes() {
		add_shortcode( 'gsf_dashboard_overview', array( __CLASS__, 'dashboard_overview_shortcode' ) );
		add_shortcode( 'gsf_dashboard_inclusion', array( __CLASS__, 'dashboard_inclusion_shortcode' ) );
		add_shortcode( 'gsf_dashboard_indicators', array( __CLASS__, 'dashboard_indicators_shortcode' ) );
		add_shortcode( 'gsf_dashboard_ecoequity', array( __CLASS__, 'dashboard_ecoequity_shortcode' ) );
		add_shortcode( 'gsf_indicator_detail', array( __CLASS__, 'indicator_detail_shortcode' ) );
		add_shortcode( 'gsf_upload_form', array( __CLASS__, 'upload_form_shortcode' ) );
		add_shortcode( 'gsf_report_exports', array( __CLASS__, 'report_exports_shortcode' ) );
	}

	public static function filter_data_centre_post_type_capabilities( $args, $post_type ) {
		$capability = self::post_type_management_capability( $post_type );

		if ( ! $capability ) {
			return $args;
		}

		$meta_cap = str_replace( '-', '_', sanitize_key( $post_type ) );

		$args['map_meta_cap'] = true;
		$args['capabilities'] = array(
			'edit_post'              => 'edit_' . $meta_cap,
			'read_post'              => 'read_' . $meta_cap,
			'delete_post'            => 'delete_' . $meta_cap,
			'edit_posts'             => $capability,
			'edit_others_posts'      => $capability,
			'delete_posts'           => $capability,
			'publish_posts'          => $capability,
			'read_private_posts'     => $capability,
			'delete_private_posts'   => $capability,
			'delete_published_posts' => $capability,
			'delete_others_posts'    => $capability,
			'edit_private_posts'     => $capability,
			'edit_published_posts'   => $capability,
			'create_posts'           => $capability,
		);

		return $args;
	}

	private static function post_type_management_capability( $post_type ) {
		$capabilities = array(
			'gsf_project'      => 'gsf_manage_projects',
			'gsf_organization' => 'gsf_manage_projects',
			'gsf_indicator'    => 'gsf_manage_indicators',
			'gsf_ind_result'   => 'gsf_submit_results',
			'gsf_evidence'     => 'gsf_upload_evidence',
			'gsf_activity'     => 'gsf_submit_results',
			'gsf_index'        => 'gsf_manage_ecoequity',
			'gsf_ecoequity'    => 'gsf_manage_ecoequity',
		);

		return $capabilities[ $post_type ] ?? '';
	}

	public static function register_admin_menu() {
		add_submenu_page( 'gsf-data-centre', __( 'PMF Dashboard', 'gsf-data-centre' ), __( 'Dashboard', 'gsf-data-centre' ), 'gsf_view_dashboard', 'gsf-data-centre', array( __CLASS__, 'render_pmf_admin_page' ) );
		add_submenu_page( 'gsf-data-centre', __( 'Indicators', 'gsf-data-centre' ), __( 'Indicators', 'gsf-data-centre' ), 'gsf_manage_indicators', 'gsf-data-centre-indicators', array( __CLASS__, 'render_indicators_admin_page' ) );
		add_submenu_page( 'gsf-data-centre', __( 'Indicator Results', 'gsf-data-centre' ), __( 'Indicator Results', 'gsf-data-centre' ), 'gsf_submit_results', 'edit.php?post_type=gsf_ind_result' );
		add_submenu_page( 'gsf-data-centre', __( 'Disaggregation', 'gsf-data-centre' ), __( 'Disaggregation', 'gsf-data-centre' ), 'gsf_submit_results', 'gsf-data-centre-disaggregation', array( __CLASS__, 'render_disaggregation_admin_page' ) );
		add_submenu_page( 'gsf-data-centre', __( 'Organizations', 'gsf-data-centre' ), __( 'Organizations', 'gsf-data-centre' ), 'gsf_manage_projects', 'edit.php?post_type=gsf_organization' );
		add_submenu_page( 'gsf-data-centre', __( 'Projects', 'gsf-data-centre' ), __( 'Projects', 'gsf-data-centre' ), 'gsf_manage_projects', 'edit.php?post_type=gsf_project' );
		add_submenu_page( 'gsf-data-centre', __( 'Evidence', 'gsf-data-centre' ), __( 'Evidence', 'gsf-data-centre' ), 'gsf_upload_evidence', 'edit.php?post_type=gsf_evidence' );
		add_submenu_page( 'gsf-data-centre', __( 'Activities', 'gsf-data-centre' ), __( 'Activities', 'gsf-data-centre' ), 'gsf_submit_results', 'edit.php?post_type=gsf_activity' );
		add_submenu_page( 'gsf-data-centre', __( 'Ecoequity Index', 'gsf-data-centre' ), __( 'Ecoequity Index', 'gsf-data-centre' ), 'gsf_manage_ecoequity', 'gsf-data-centre-ecoequity-index', array( __CLASS__, 'render_ecoequity_admin_page' ) );
		add_submenu_page( 'gsf-data-centre', __( 'CSV Import / Export', 'gsf-data-centre' ), __( 'CSV Import / Export', 'gsf-data-centre' ), 'gsf_export_reports', 'gsf-data-centre-import-export', array( __CLASS__, 'render_import_export_page' ) );
		add_submenu_page( 'gsf-data-centre', __( 'PMF Settings', 'gsf-data-centre' ), __( 'PMF Settings', 'gsf-data-centre' ), 'gsf_manage_settings', 'gsf-data-centre-meal-settings', array( __CLASS__, 'render_settings_page' ) );
	}

	public static function enqueue_admin_assets( $hook_suffix ) {
		if ( false === strpos( (string) $hook_suffix, 'gsf-data-centre' ) ) {
			return;
		}

		self::enqueue_public_assets();
	}

	public static function install_schema() {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$charset = $wpdb->get_charset_collate();
		$tables  = self::tables();
		$sql     = array();

		$sql[] = "CREATE TABLE {$tables['result_levels']} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			code varchar(40) NOT NULL,
			name varchar(191) NOT NULL,
			parent_id bigint(20) unsigned NULL,
			sort_order int(11) NOT NULL DEFAULT 0,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			UNIQUE KEY code (code)
		) $charset;";

		$sql[] = "CREATE TABLE {$tables['indicators']} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			wp_post_id bigint(20) unsigned NULL,
			result_level_id bigint(20) unsigned NULL,
			code varchar(80) NOT NULL,
			title text NOT NULL,
			definition longtext NULL,
			unit varchar(80) NULL,
			direction varchar(20) NOT NULL DEFAULT 'increase',
			frequency varchar(40) NULL,
			source varchar(191) NULL,
			responsible_party varchar(191) NULL,
			gender_responsive tinyint(1) NOT NULL DEFAULT 1,
			is_active tinyint(1) NOT NULL DEFAULT 1,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY code (code),
			KEY wp_post_id (wp_post_id)
		) $charset;";

		$sql[] = "CREATE TABLE {$tables['indicator_targets']} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			indicator_id bigint(20) unsigned NOT NULL,
			period_code varchar(40) NOT NULL,
			baseline_value decimal(18,4) NULL,
			target_value decimal(18,4) NULL,
			target_narrative longtext NULL,
			PRIMARY KEY  (id),
			KEY indicator_id (indicator_id)
		) $charset;";

		$sql[] = "CREATE TABLE {$tables['indicator_methods']} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			indicator_id bigint(20) unsigned NOT NULL,
			method_type varchar(80) NULL,
			method_description longtext NULL,
			quality_rules longtext NULL,
			PRIMARY KEY  (id),
			KEY indicator_id (indicator_id)
		) $charset;";

		$sql[] = "CREATE TABLE {$tables['data_sources']} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			name varchar(191) NOT NULL,
			source_type varchar(80) NULL,
			owner varchar(191) NULL,
			description longtext NULL,
			PRIMARY KEY  (id)
		) $charset;";

		$sql[] = "CREATE TABLE {$tables['reporting_periods']} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			code varchar(40) NOT NULL,
			label varchar(120) NOT NULL,
			start_date date NULL,
			end_date date NULL,
			status varchar(40) NOT NULL DEFAULT 'open',
			PRIMARY KEY  (id),
			UNIQUE KEY code (code)
		) $charset;";

		$sql[] = "CREATE TABLE {$tables['indicator_results']} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			indicator_id bigint(20) unsigned NOT NULL,
			project_id bigint(20) unsigned NULL,
			organization_id bigint(20) unsigned NULL,
			reporting_period_id bigint(20) unsigned NULL,
			result_value decimal(18,4) NULL,
			result_text longtext NULL,
			status varchar(40) NOT NULL DEFAULT 'draft',
			submitted_by bigint(20) unsigned NULL,
			verified_by bigint(20) unsigned NULL,
			submitted_at datetime NULL,
			verified_at datetime NULL,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime NULL,
			PRIMARY KEY  (id),
			KEY indicator_id (indicator_id),
			KEY project_id (project_id),
			KEY reporting_period_id (reporting_period_id)
		) $charset;";

		$sql[] = "CREATE TABLE {$tables['disaggregation']} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			result_id bigint(20) unsigned NOT NULL,
			category varchar(80) NOT NULL,
			value_label varchar(120) NOT NULL,
			value_number decimal(18,4) NULL,
			PRIMARY KEY  (id),
			KEY result_id (result_id),
			KEY category (category)
		) $charset;";

		$sql[] = "CREATE TABLE {$tables['evidence']} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			wp_post_id bigint(20) unsigned NULL,
			result_id bigint(20) unsigned NULL,
			project_id bigint(20) unsigned NULL,
			file_id bigint(20) unsigned NULL,
			evidence_type varchar(80) NULL,
			title varchar(191) NOT NULL,
			source_url text NULL,
			status varchar(40) NOT NULL DEFAULT 'submitted',
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY result_id (result_id),
			KEY project_id (project_id)
		) $charset;";

		$sql[] = "CREATE TABLE {$tables['activity_events']} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			wp_post_id bigint(20) unsigned NULL,
			project_id bigint(20) unsigned NULL,
			title varchar(191) NOT NULL,
			activity_type varchar(80) NULL,
			country varchar(120) NULL,
			start_date date NULL,
			end_date date NULL,
			status varchar(40) NOT NULL DEFAULT 'planned',
			PRIMARY KEY  (id),
			KEY project_id (project_id)
		) $charset;";

		$sql[] = "CREATE TABLE {$tables['activity_participation']} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			activity_id bigint(20) unsigned NOT NULL,
			gender varchar(40) NULL,
			age_group varchar(40) NULL,
			stakeholder_type varchar(120) NULL,
			count_value int(11) NOT NULL DEFAULT 0,
			PRIMARY KEY  (id),
			KEY activity_id (activity_id)
		) $charset;";

		$sql[] = "CREATE TABLE {$tables['index_frameworks']} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			code varchar(80) NOT NULL,
			name varchar(191) NOT NULL,
			description longtext NULL,
			is_active tinyint(1) NOT NULL DEFAULT 1,
			PRIMARY KEY  (id),
			UNIQUE KEY code (code)
		) $charset;";

		$sql[] = "CREATE TABLE {$tables['index_criteria']} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			framework_id bigint(20) unsigned NOT NULL,
			code varchar(80) NOT NULL,
			name varchar(191) NOT NULL,
			description longtext NULL,
			weight decimal(8,4) NOT NULL DEFAULT 1,
			sort_order int(11) NOT NULL DEFAULT 0,
			PRIMARY KEY  (id),
			KEY framework_id (framework_id)
		) $charset;";

		$sql[] = "CREATE TABLE {$tables['index_scores']} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			framework_id bigint(20) unsigned NOT NULL,
			organization_id bigint(20) unsigned NULL,
			project_id bigint(20) unsigned NULL,
			reporting_period_id bigint(20) unsigned NULL,
			overall_score decimal(8,4) NULL,
			status varchar(40) NOT NULL DEFAULT 'draft',
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY framework_id (framework_id)
		) $charset;";

		$sql[] = "CREATE TABLE {$tables['index_criteria_scores']} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			index_score_id bigint(20) unsigned NOT NULL,
			criteria_id bigint(20) unsigned NOT NULL,
			score decimal(8,4) NULL,
			narrative longtext NULL,
			PRIMARY KEY  (id),
			KEY index_score_id (index_score_id),
			KEY criteria_id (criteria_id)
		) $charset;";

		foreach ( $sql as $statement ) {
			dbDelta( $statement );
		}

		update_option( 'gsf_data_centre_meal_schema_version', self::SCHEMA_VERSION );
	}

	public static function sync_roles() {
		$capabilities = self::capabilities();
		$role_sets    = array(
			'gsf_viewer'           => array( 'GSF Viewer', array( 'gsf_view_dashboard' ) ),
			'gsf_nctf_contributor' => array( 'GSF NCTF Contributor', array( 'gsf_view_dashboard', 'gsf_submit_results', 'gsf_upload_evidence' ) ),
			'gsf_meal_officer'     => array( 'GSF MEAL Officer', array( 'list_users', 'create_users', 'gsf_view_dashboard', 'gsf_manage_projects', 'gsf_manage_indicators', 'gsf_submit_results', 'gsf_verify_results', 'gsf_upload_evidence', 'gsf_manage_ecoequity', 'gsf_export_reports' ) ),
			'gsf_cbf_staff'        => array( 'GSF CBF Staff', array_keys( $capabilities ) ),
			'cbf_staff'            => array( 'CBF staff', array_keys( $capabilities ) ),
			'gsf_admin'            => array( 'GSF Admin', array_keys( $capabilities ) ),
		);

		foreach ( $role_sets as $role_key => $role_data ) {
			$role = get_role( $role_key );
			if ( ! $role ) {
				add_role( $role_key, $role_data[0], array( 'read' => true ) );
				$role = get_role( $role_key );
			}
			if ( $role ) {
				foreach ( $role_data[1] as $cap ) {
					$role->add_cap( $cap );
				}
			}
		}

		self::sync_ultimate_member_roles( $role_sets );

		$admin = get_role( 'administrator' );
		if ( $admin ) {
			foreach ( array_keys( $capabilities ) as $cap ) {
				$admin->add_cap( $cap );
			}
		}
	}

	private static function sync_ultimate_member_roles( array $role_sets ) {
		$um_role_keys = get_option( 'um_roles', array() );
		$um_role_keys = is_array( $um_role_keys ) ? $um_role_keys : array();
		$base_meta    = function_exists( 'UM' ) && isset( UM()->config()->default_roles_metadata['subscriber'] )
			? UM()->config()->default_roles_metadata['subscriber']
			: array(
				'_um_can_access_wpadmin'   => 0,
				'_um_can_not_see_adminbar' => 1,
				'_um_after_login'          => 'redirect_profile',
				'_um_after_logout'         => 'redirect_home',
				'_um_default_homepage'     => 1,
				'_um_status'               => 'approved',
				'_um_auto_approve_act'     => 'redirect_profile',
			);

		foreach ( $role_sets as $role_key => $role_data ) {
			$role = get_role( $role_key );
			if ( ! $role ) {
				continue;
			}

			if ( ! in_array( $role_key, $um_role_keys, true ) ) {
				$um_role_keys[] = $role_key;
			}

			$allow_wp_admin = in_array( $role_key, array( 'gsf_meal_officer', 'gsf_cbf_staff', 'cbf_staff', 'gsf_admin' ), true );
			$role_meta      = array_merge(
				$base_meta,
				array(
					'name'                       => $role_data[0],
					'wp_capabilities'            => $role->capabilities,
					'_um_status'                 => 'approved',
					'_um_can_access_wpadmin'     => $allow_wp_admin ? 1 : 0,
					'_um_can_not_see_adminbar'   => $allow_wp_admin ? 0 : 1,
					'_um_after_login'            => $allow_wp_admin ? 'redirect_admin' : 'redirect_profile',
					'_um_auto_approve_act'       => $allow_wp_admin ? 'redirect_admin' : 'redirect_profile',
					'_um_priority'               => $allow_wp_admin ? 30 : 5,
					'_um_can_edit_profile'       => 1,
					'_um_can_delete_profile'     => 0,
					'_um_default_homepage'       => 1,
					'_um_can_view_all'           => 1,
				)
			);

			update_option( 'um_role_' . $role_key . '_meta', $role_meta );
		}

		update_option( 'um_roles', array_values( array_unique( $um_role_keys ) ) );

		if ( function_exists( 'UM' ) && method_exists( UM()->roles(), 'um_roles_init' ) ) {
			UM()->roles()->um_roles_init( wp_roles() );
		}

		if ( function_exists( 'UM' ) && method_exists( UM()->user(), 'remove_cache_all_users' ) ) {
			UM()->user()->remove_cache_all_users();
		}
	}

	public static function seed_reference_data() {
		self::seed_terms();
		self::seed_pods_defaults();
	}

	public static function ensure_pods() {
		if ( ! function_exists( 'pods_api' ) ) {
			return;
		}

		self::ensure_pod( 'gsf_indicator', __( 'Indicator', 'gsf-data-centre' ), __( 'Indicators', 'gsf-data-centre' ) );
		self::ensure_pod( 'gsf_ind_result', __( 'Indicator Result', 'gsf-data-centre' ), __( 'Indicator Results', 'gsf-data-centre' ) );
		self::ensure_pod( 'gsf_organization', __( 'Organization', 'gsf-data-centre' ), __( 'Organizations', 'gsf-data-centre' ) );
		self::ensure_pod( 'gsf_evidence', __( 'Evidence Item', 'gsf-data-centre' ), __( 'Evidence', 'gsf-data-centre' ) );
		self::ensure_pod( 'gsf_activity', __( 'Activity', 'gsf-data-centre' ), __( 'Activities', 'gsf-data-centre' ) );
		self::ensure_pod( 'gsf_index', __( 'Index Framework', 'gsf-data-centre' ), __( 'Index Frameworks', 'gsf-data-centre' ) );

		self::save_pod_fields( 'gsf_indicator', self::indicator_pod_fields() );
		self::save_pod_fields( 'gsf_ind_result', self::indicator_result_pod_fields() );
		self::save_pod_fields( 'gsf_organization', self::organization_pod_fields() );
		self::save_pod_fields( 'gsf_evidence', self::evidence_pod_fields() );
		self::save_pod_fields( 'gsf_activity', self::activity_pod_fields() );
		self::save_pod_fields( 'gsf_index', self::index_pod_fields() );
	}

	public static function register_rest_routes() {
		register_rest_route(
			'gsf/v1',
			'/indicators',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'rest_get_indicators' ),
				'permission_callback' => array( __CLASS__, 'can_view_rest' ),
			)
		);

		register_rest_route(
			'gsf/v1',
			'/indicators/(?P<id>\\d+)',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'rest_get_indicator' ),
				'permission_callback' => array( __CLASS__, 'can_view_rest' ),
			)
		);

		register_rest_route(
			'gsf/v1',
			'/results',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( __CLASS__, 'rest_get_results' ),
					'permission_callback' => array( __CLASS__, 'can_view_rest' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( __CLASS__, 'rest_create_result' ),
					'permission_callback' => array( __CLASS__, 'can_submit_rest' ),
				),
			)
		);

		register_rest_route(
			'gsf/v1',
			'/dashboard',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'rest_get_dashboard' ),
				'permission_callback' => array( __CLASS__, 'can_view_rest' ),
			)
		);
	}

	public static function render_pmf_admin_page() {
		self::require_capability( 'gsf_view_dashboard' );
		$summary = self::dashboard_summary();
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Gender Monitoring & Data Centre', 'gsf-data-centre' ); ?></h1>
			<p><?php esc_html_e( 'PMF, MEAL, evidence, activity, and Ecoequity monitoring foundation for the GSF Hub.', 'gsf-data-centre' ); ?></p>
			<div class="gsf-meal-admin-grid">
				<?php foreach ( $summary as $label => $value ) : ?>
					<div class="gsf-meal-admin-card">
						<strong><?php echo esc_html( number_format_i18n( (float) $value ) ); ?></strong>
						<span><?php echo esc_html( $label ); ?></span>
					</div>
				<?php endforeach; ?>
			</div>
			<h2><?php esc_html_e( 'Available Shortcodes', 'gsf-data-centre' ); ?></h2>
			<p><code>[gsf_dashboard_overview]</code> <code>[gsf_dashboard_inclusion]</code> <code>[gsf_dashboard_indicators]</code> <code>[gsf_dashboard_ecoequity]</code> <code>[gsf_upload_form]</code> <code>[gsf_report_exports]</code></p>
		</div>
		<?php
	}

	public static function render_indicators_admin_page() {
		self::require_capability( 'gsf_manage_indicators' );
		self::enqueue_public_assets();

		$indicators = get_posts(
			array(
				'post_type'        => 'gsf_indicator',
				'post_status'      => array( 'publish', 'draft', 'pending', 'private' ),
				'posts_per_page'   => -1,
				'orderby'          => 'meta_value',
				'meta_key'         => 'indicator_code',
				'order'            => 'ASC',
				'suppress_filters' => true,
			)
		);

		?>
		<div class="wrap gsf-meal-admin">
			<h1><?php esc_html_e( 'PMF Indicators', 'gsf-data-centre' ); ?></h1>
			<p><?php esc_html_e( 'Pods-managed PMF indicator register. Use this table to review the full indicator framework, baselines, targets, reporting values, and status.', 'gsf-data-centre' ); ?></p>
			<p>
				<a class="button button-primary" href="<?php echo esc_url( admin_url( 'post-new.php?post_type=gsf_indicator' ) ); ?>"><?php esc_html_e( 'Add Indicator', 'gsf-data-centre' ); ?></a>
				<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=gsf-data-centre-import-export' ) ); ?>"><?php esc_html_e( 'Import / Export Indicators', 'gsf-data-centre' ); ?></a>
			</p>
			<div class="gsf-meal-table-scroll">
				<table class="widefat striped gsf-meal-indicator-table">
					<caption class="screen-reader-text"><?php esc_html_e( 'PMF indicator tracking table', 'gsf-data-centre' ); ?></caption>
					<thead>
						<tr>
							<th><?php esc_html_e( 'Indicator Code', 'gsf-data-centre' ); ?></th>
							<th><?php esc_html_e( 'Result Level', 'gsf-data-centre' ); ?></th>
							<th><?php esc_html_e( 'Indicator Statement', 'gsf-data-centre' ); ?></th>
							<th><?php esc_html_e( 'Baseline', 'gsf-data-centre' ); ?></th>
							<th><?php esc_html_e( 'Target', 'gsf-data-centre' ); ?></th>
							<th><?php esc_html_e( 'Year 3 Value', 'gsf-data-centre' ); ?></th>
							<th><?php esc_html_e( 'Year 4 Value', 'gsf-data-centre' ); ?></th>
							<th><?php esc_html_e( 'Status', 'gsf-data-centre' ); ?></th>
							<th><?php esc_html_e( 'Last Updated', 'gsf-data-centre' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php if ( $indicators ) : ?>
							<?php foreach ( $indicators as $indicator ) : ?>
								<?php $row = self::indicator_table_row( $indicator ); ?>
								<tr>
									<td><a href="<?php echo esc_url( get_edit_post_link( $indicator->ID ) ); ?>"><code><?php echo esc_html( $row['indicator_code'] ); ?></code></a></td>
									<td><?php echo esc_html( $row['result_level'] ); ?></td>
									<td class="gsf-meal-indicator-table__statement"><?php echo esc_html( $row['indicator_text'] ); ?></td>
									<td><?php echo esc_html( $row['baseline_summary'] ); ?></td>
									<td><?php echo esc_html( $row['target_summary'] ); ?></td>
									<td><?php echo esc_html( $row['year_3_value'] ); ?></td>
									<td><?php echo esc_html( $row['year_4_value'] ); ?></td>
									<td><span class="gsf-meal-status gsf-meal-status--<?php echo esc_attr( sanitize_html_class( $row['status'] ) ); ?>"><?php echo esc_html( self::status_label( $row['status'] ) ); ?></span></td>
									<td><?php echo esc_html( $row['last_updated'] ); ?></td>
								</tr>
							<?php endforeach; ?>
						<?php else : ?>
							<tr><td colspan="9"><?php esc_html_e( 'No PMF indicators have been added yet. Add indicators manually or import the indicator CSV template.', 'gsf-data-centre' ); ?></td></tr>
						<?php endif; ?>
					</tbody>
				</table>
			</div>
		</div>
		<?php
	}

	public static function render_disaggregation_admin_page() {
		self::require_capability( 'gsf_submit_results' );

		global $wpdb;
		$tables       = self::tables();
		$per_page     = 50;
		$current_page = max( 1, absint( filter_input( INPUT_GET, 'gsf_disaggregation_page', FILTER_VALIDATE_INT ) ) );
		$total_rows   = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$tables['disaggregation']}" );
		$total_pages  = max( 1, (int) ceil( $total_rows / $per_page ) );
		$current_page = min( $current_page, $total_pages );
		$offset       = ( $current_page - 1 ) * $per_page;
		$rows         = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, result_id, category, value_label, value_number FROM {$tables['disaggregation']} ORDER BY id ASC LIMIT %d OFFSET %d",
				$per_page,
				$offset
			),
			ARRAY_A
		);

		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Disaggregation', 'gsf-data-centre' ); ?></h1>
			<p><?php esc_html_e( 'Edit the disaggregated rows that feed inclusion totals and homepage beneficiary metrics. Each row stores one category/value/count combination linked to an indicator result ID.', 'gsf-data-centre' ); ?></p>

			<?php if ( isset( $_GET['gsf_disaggregation_saved'] ) ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Disaggregation rows updated.', 'gsf-data-centre' ); ?></p></div>
			<?php endif; ?>
			<?php if ( isset( $_GET['gsf_disaggregation_added'] ) ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Disaggregation row added.', 'gsf-data-centre' ); ?></p></div>
			<?php endif; ?>
			<?php if ( isset( $_GET['gsf_disaggregation_deleted'] ) ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Disaggregation row deleted.', 'gsf-data-centre' ); ?></p></div>
			<?php endif; ?>

			<?php echo wp_kses_post( self::dashboard_inclusion_shortcode() ); ?>

			<h2><?php esc_html_e( 'Edit Rows', 'gsf-data-centre' ); ?></h2>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="gsf_data_centre_save_disaggregation">
				<input type="hidden" name="gsf_disaggregation_page" value="<?php echo esc_attr( $current_page ); ?>">
				<?php wp_nonce_field( 'gsf_data_centre_save_disaggregation' ); ?>

				<div class="gsf-meal-table-scroll">
					<table class="widefat striped">
						<thead>
							<tr>
								<th><?php esc_html_e( 'ID', 'gsf-data-centre' ); ?></th>
								<th><?php esc_html_e( 'Result ID', 'gsf-data-centre' ); ?></th>
								<th><?php esc_html_e( 'Category', 'gsf-data-centre' ); ?></th>
								<th><?php esc_html_e( 'Value label', 'gsf-data-centre' ); ?></th>
								<th><?php esc_html_e( 'Value', 'gsf-data-centre' ); ?></th>
								<th><?php esc_html_e( 'Actions', 'gsf-data-centre' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php if ( $rows ) : ?>
								<?php foreach ( $rows as $row ) : ?>
									<tr>
										<td>
											<?php echo esc_html( (int) $row['id'] ); ?>
											<input type="hidden" name="rows[<?php echo esc_attr( (int) $row['id'] ); ?>][id]" value="<?php echo esc_attr( (int) $row['id'] ); ?>">
										</td>
										<td><input class="small-text" type="number" min="0" step="1" name="rows[<?php echo esc_attr( (int) $row['id'] ); ?>][result_id]" value="<?php echo esc_attr( (int) $row['result_id'] ); ?>"></td>
										<td><?php self::render_disaggregation_category_select( 'rows[' . (int) $row['id'] . '][category]', (string) $row['category'] ); ?></td>
										<td><input class="regular-text" type="text" name="rows[<?php echo esc_attr( (int) $row['id'] ); ?>][value_label]" value="<?php echo esc_attr( $row['value_label'] ); ?>"></td>
										<td><input class="regular-text" type="number" step="0.0001" min="0" name="rows[<?php echo esc_attr( (int) $row['id'] ); ?>][value_number]" value="<?php echo esc_attr( $row['value_number'] ); ?>"></td>
										<td><button class="button button-link-delete" type="submit" name="delete_disaggregation_id" value="<?php echo esc_attr( (int) $row['id'] ); ?>" onclick="return confirm('<?php echo esc_js( __( 'Delete this disaggregation row?', 'gsf-data-centre' ) ); ?>');"><?php esc_html_e( 'Delete', 'gsf-data-centre' ); ?></button></td>
									</tr>
								<?php endforeach; ?>
							<?php else : ?>
								<tr><td colspan="6"><?php esc_html_e( 'No disaggregation rows found.', 'gsf-data-centre' ); ?></td></tr>
							<?php endif; ?>
						</tbody>
					</table>
				</div>

				<?php echo self::render_disaggregation_pagination( $current_page, $total_pages ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>

				<p><button class="button button-primary" type="submit" name="save_disaggregation_rows" value="1"><?php esc_html_e( 'Save Rows', 'gsf-data-centre' ); ?></button></p>

				<h2><?php esc_html_e( 'Add Row', 'gsf-data-centre' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="new-result-id"><?php esc_html_e( 'Result ID', 'gsf-data-centre' ); ?></label></th>
						<td><input id="new-result-id" class="small-text" type="number" min="0" step="1" name="new_row[result_id]" value="0"></td>
					</tr>
					<tr>
						<th scope="row"><label for="new-category"><?php esc_html_e( 'Category', 'gsf-data-centre' ); ?></label></th>
						<td><?php self::render_disaggregation_category_select( 'new_row[category]', '', 'new-category' ); ?></td>
					</tr>
					<tr>
						<th scope="row"><label for="new-value-label"><?php esc_html_e( 'Value label', 'gsf-data-centre' ); ?></label></th>
						<td><input id="new-value-label" class="regular-text" type="text" name="new_row[value_label]" value=""></td>
					</tr>
					<tr>
						<th scope="row"><label for="new-value-number"><?php esc_html_e( 'Value', 'gsf-data-centre' ); ?></label></th>
						<td><input id="new-value-number" class="regular-text" type="number" step="0.0001" min="0" name="new_row[value_number]" value=""></td>
					</tr>
				</table>
				<p><button class="button" type="submit" name="add_disaggregation_row" value="1"><?php esc_html_e( 'Add Row', 'gsf-data-centre' ); ?></button></p>
			</form>
		</div>
		<?php
	}

	public static function render_ecoequity_admin_page() {
		self::require_capability( 'gsf_manage_ecoequity' );
		echo '<div class="wrap"><h1>' . esc_html__( 'Ecoequity Index', 'gsf-data-centre' ) . '</h1>';
		echo wp_kses_post( self::dashboard_ecoequity_shortcode() );
		echo '</div>';
	}

	public static function render_import_export_page() {
		self::require_capability( 'gsf_export_reports' );
		$exports   = array( 'projects', 'indicators', 'indicator_results', 'disaggregation', 'evidence', 'activities', 'ecoequity_scores' );
		$templates = array( 'projects', 'indicators', 'indicator_results', 'disaggregation', 'evidence', 'activities', 'ecoequity_scores' );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'CSV Import / Export', 'gsf-data-centre' ); ?></h1>
			<?php if ( isset( $_GET['gsf_imported'] ) ) : ?>
				<div class="notice notice-success"><p>
					<?php
					echo esc_html( sprintf( __( 'Imported %d rows.', 'gsf-data-centre' ), absint( $_GET['gsf_imported'] ) ) );
					if ( isset( $_GET['gsf_skipped'] ) && absint( $_GET['gsf_skipped'] ) > 0 ) {
						echo ' ' . esc_html( sprintf( __( 'Skipped %d rows that could not be matched.', 'gsf-data-centre' ), absint( $_GET['gsf_skipped'] ) ) );
					}
					?>
				</p></div>
			<?php endif; ?>
			<h2><?php esc_html_e( 'Import CSV', 'gsf-data-centre' ); ?></h2>
			<p><?php esc_html_e( 'Project imports update matching records by project_id, then project_slug, then project_title. Leave those identifiers blank only when creating a new project.', 'gsf-data-centre' ); ?></p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data">
				<input type="hidden" name="action" value="gsf_data_centre_import_csv">
				<?php wp_nonce_field( 'gsf_data_centre_import_csv' ); ?>
				<select name="import_type">
					<?php foreach ( $templates as $template ) : ?>
						<option value="<?php echo esc_attr( $template ); ?>"><?php echo esc_html( ucwords( str_replace( '_', ' ', $template ) ) ); ?></option>
					<?php endforeach; ?>
				</select>
				<input type="file" name="csv_file" accept=".csv,text/csv" required>
				<button type="submit" class="button button-primary"><?php esc_html_e( 'Import CSV', 'gsf-data-centre' ); ?></button>
			</form>
			<h2><?php esc_html_e( 'Download Import Templates', 'gsf-data-centre' ); ?></h2>
			<p>
				<?php foreach ( $templates as $template ) : ?>
					<a class="button" href="<?php echo esc_url( self::admin_action_url( 'gsf_data_centre_download_template', array( 'template' => $template ) ) ); ?>"><?php echo esc_html( ucwords( str_replace( '_', ' ', $template ) ) ); ?></a>
				<?php endforeach; ?>
			</p>
			<h2><?php esc_html_e( 'Export Reports', 'gsf-data-centre' ); ?></h2>
			<p>
				<?php foreach ( $exports as $export ) : ?>
					<a class="button button-primary" href="<?php echo esc_url( self::admin_action_url( 'gsf_data_centre_export_csv', array( 'export' => $export ) ) ); ?>"><?php echo esc_html( ucwords( str_replace( '_', ' ', $export ) ) ); ?></a>
				<?php endforeach; ?>
			</p>
		</div>
		<?php
	}

	public static function render_project_csv_actions( $which ) {
		if ( 'top' !== $which || ! current_user_can( 'gsf_export_reports' ) ) {
			return;
		}

		$screen = get_current_screen();
		if ( ! $screen || 'edit-gsf_project' !== $screen->id ) {
			return;
		}
		?>
		<div class="alignleft actions">
			<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=gsf-data-centre-import-export' ) ); ?>"><?php esc_html_e( 'Import Projects CSV', 'gsf-data-centre' ); ?></a>
			<a class="button" href="<?php echo esc_url( self::admin_action_url( 'gsf_data_centre_export_csv', array( 'export' => 'projects' ) ) ); ?>"><?php esc_html_e( 'Download Projects CSV', 'gsf-data-centre' ); ?></a>
		</div>
		<?php
	}

	public static function render_settings_page() {
		self::require_capability( 'gsf_manage_settings' );
		echo '<div class="wrap"><h1>' . esc_html__( 'PMF Settings', 'gsf-data-centre' ) . '</h1>';
		echo '<p>' . esc_html__( 'The Data Centre is configured as a Pods-first PMF system. Indicators, results, organizations, evidence, activities, projects, and Ecoequity records are managed as WordPress/PODs content with structured fields, taxonomies, REST support, and CSV import/export.', 'gsf-data-centre' ) . '</p>';
		echo '<table class="widefat striped"><thead><tr><th>' . esc_html__( 'Record Type', 'gsf-data-centre' ) . '</th><th>' . esc_html__( 'Storage', 'gsf-data-centre' ) . '</th></tr></thead><tbody>';
		foreach ( array( 'gsf_indicator', 'gsf_ind_result', 'gsf_organization', 'gsf_project', 'gsf_evidence', 'gsf_activity', 'gsf_index' ) as $post_type ) {
			$post_type_object = get_post_type_object( $post_type );
			echo '<tr><td><code>' . esc_html( $post_type ) . '</code></td><td>' . esc_html( $post_type_object ? $post_type_object->labels->name : __( 'Pods / WordPress CPT', 'gsf-data-centre' ) ) . '</td></tr>';
		}
		echo '</tbody></table></div>';
	}

	public static function dashboard_overview_shortcode() {
		self::enqueue_public_assets();

		return self::summary_cards_html( self::dashboard_summary() );
	}

	public static function dashboard_inclusion_shortcode() {
		self::enqueue_public_assets();

		global $wpdb;
		$tables = self::tables();
		$rows   = $wpdb->get_results( "SELECT category, value_label, SUM(value_number) total FROM {$tables['disaggregation']} GROUP BY category, value_label ORDER BY category, value_label LIMIT 40", ARRAY_A );

		ob_start();
		echo '<section class="gsf-meal-panel"><h2>' . esc_html__( 'Inclusion Dashboard', 'gsf-data-centre' ) . '</h2>';
		if ( empty( $rows ) ) {
			echo '<p>' . esc_html__( 'No disaggregated results have been submitted yet.', 'gsf-data-centre' ) . '</p>';
		} else {
			echo '<table><thead><tr><th>' . esc_html__( 'Category', 'gsf-data-centre' ) . '</th><th>' . esc_html__( 'Value', 'gsf-data-centre' ) . '</th><th>' . esc_html__( 'Total', 'gsf-data-centre' ) . '</th></tr></thead><tbody>';
			foreach ( $rows as $row ) {
				echo '<tr><td>' . esc_html( $row['category'] ) . '</td><td>' . esc_html( $row['value_label'] ) . '</td><td>' . esc_html( number_format_i18n( (float) $row['total'] ) ) . '</td></tr>';
			}
			echo '</tbody></table>';
		}
		echo '</section>';
		return ob_get_clean();
	}

	public static function dashboard_indicators_shortcode() {
		self::enqueue_public_assets();

		$indicators = get_posts(
			array(
				'post_type'        => 'gsf_indicator',
				'post_status'      => array( 'publish', 'draft', 'pending', 'private' ),
				'posts_per_page'   => 100,
				'orderby'          => 'meta_value',
				'meta_key'         => 'indicator_code',
				'order'            => 'ASC',
				'suppress_filters' => true,
			)
		);

		ob_start();
		echo '<section class="gsf-meal-panel"><h2>' . esc_html__( 'Indicator Dashboard', 'gsf-data-centre' ) . '</h2>';
		echo self::summary_cards_html(
			array(
				__( 'Indicators Tracked', 'gsf-data-centre' ) => self::count_posts( 'gsf_indicator' ),
				__( 'Reported', 'gsf-data-centre' )           => self::count_indicators_by_status( array( 'submitted', 'reported', 'verified' ) ),
				__( 'Pending', 'gsf-data-centre' )            => self::count_indicators_by_status( array( 'pending', 'draft', 'partial_data' ) ),
				__( 'No Data', 'gsf-data-centre' )            => self::count_indicators_by_status( array( 'no_data' ) ),
				__( 'Evidence Files', 'gsf-data-centre' )     => self::count_posts( 'gsf_evidence' ),
			)
		); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		if ( empty( $indicators ) ) {
			echo '<p>' . esc_html__( 'No PMF indicators have been added yet.', 'gsf-data-centre' ) . '</p>';
		} else {
			echo '<div class="gsf-meal-table-scroll"><table><thead><tr><th>' . esc_html__( 'Indicator Code', 'gsf-data-centre' ) . '</th><th>' . esc_html__( 'Result Level', 'gsf-data-centre' ) . '</th><th>' . esc_html__( 'Indicator Statement', 'gsf-data-centre' ) . '</th><th>' . esc_html__( 'Baseline', 'gsf-data-centre' ) . '</th><th>' . esc_html__( 'Target', 'gsf-data-centre' ) . '</th><th>' . esc_html__( 'Year 3 Value', 'gsf-data-centre' ) . '</th><th>' . esc_html__( 'Year 4 Value', 'gsf-data-centre' ) . '</th><th>' . esc_html__( 'Status', 'gsf-data-centre' ) . '</th><th>' . esc_html__( 'Last Updated', 'gsf-data-centre' ) . '</th></tr></thead><tbody>';
			foreach ( $indicators as $indicator ) {
				$row = self::indicator_table_row( $indicator );
				echo '<tr><td><code>' . esc_html( $row['indicator_code'] ) . '</code></td><td>' . esc_html( $row['result_level'] ) . '</td><td>' . esc_html( $row['indicator_text'] ) . '</td><td>' . esc_html( $row['baseline_summary'] ) . '</td><td>' . esc_html( $row['target_summary'] ) . '</td><td>' . esc_html( $row['year_3_value'] ) . '</td><td>' . esc_html( $row['year_4_value'] ) . '</td><td>' . esc_html( self::status_label( $row['status'] ) ) . '</td><td>' . esc_html( $row['last_updated'] ) . '</td></tr>';
			}
			echo '</tbody></table></div>';
		}
		echo '</section>';
		return ob_get_clean();
	}

	public static function dashboard_ecoequity_shortcode() {
		self::enqueue_public_assets();

		global $wpdb;
		$tables = self::tables();
		$rows   = $wpdb->get_results(
			"SELECT f.name framework, s.overall_score, s.status, s.created_at
			FROM {$tables['index_scores']} s
			INNER JOIN {$tables['index_frameworks']} f ON f.id = s.framework_id
			ORDER BY s.created_at DESC
			LIMIT 50",
			ARRAY_A
		);

		ob_start();
		echo '<section class="gsf-meal-panel"><h2>' . esc_html__( 'Ecoequity Dashboard', 'gsf-data-centre' ) . '</h2>';
		if ( empty( $rows ) ) {
			echo '<p>' . esc_html__( 'No Ecoequity index scores have been submitted yet.', 'gsf-data-centre' ) . '</p>';
		} else {
			echo '<table><thead><tr><th>' . esc_html__( 'Framework', 'gsf-data-centre' ) . '</th><th>' . esc_html__( 'Score', 'gsf-data-centre' ) . '</th><th>' . esc_html__( 'Status', 'gsf-data-centre' ) . '</th><th>' . esc_html__( 'Created', 'gsf-data-centre' ) . '</th></tr></thead><tbody>';
			foreach ( $rows as $row ) {
				echo '<tr><td>' . esc_html( $row['framework'] ) . '</td><td>' . esc_html( is_null( $row['overall_score'] ) ? '-' : number_format_i18n( (float) $row['overall_score'], 2 ) ) . '</td><td>' . esc_html( ucfirst( $row['status'] ) ) . '</td><td>' . esc_html( mysql2date( get_option( 'date_format' ), $row['created_at'] ) ) . '</td></tr>';
			}
			echo '</tbody></table>';
		}
		echo '</section>';
		return ob_get_clean();
	}

	public static function indicator_detail_shortcode( $atts ) {
		self::enqueue_public_assets();

		$atts         = shortcode_atts( array( 'indicator_id' => 0 ), $atts, 'gsf_indicator_detail' );
		$indicator_id = absint( $atts['indicator_id'] );
		if ( ! $indicator_id ) {
			return '<p>' . esc_html__( 'No indicator selected.', 'gsf-data-centre' ) . '</p>';
		}

		$indicator = get_post( $indicator_id );
		if ( ! $indicator || 'gsf_indicator' !== $indicator->post_type ) {
			return '<p>' . esc_html__( 'Indicator not found.', 'gsf-data-centre' ) . '</p>';
		}

		$row = self::indicator_table_row( $indicator );
		ob_start();
		echo '<section class="gsf-meal-panel"><h2>' . esc_html( $row['indicator_code'] . ': ' . $row['indicator_text'] ) . '</h2>';
		echo '<dl><dt>' . esc_html__( 'Result Level', 'gsf-data-centre' ) . '</dt><dd>' . esc_html( $row['result_level'] ) . '</dd><dt>' . esc_html__( 'Baseline', 'gsf-data-centre' ) . '</dt><dd>' . esc_html( $row['baseline_summary'] ) . '</dd><dt>' . esc_html__( 'Target', 'gsf-data-centre' ) . '</dt><dd>' . esc_html( $row['target_summary'] ) . '</dd><dt>' . esc_html__( 'Frequency', 'gsf-data-centre' ) . '</dt><dd>' . esc_html( self::first_meta( $indicator_id, array( 'frequency' ) ) ) . '</dd><dt>' . esc_html__( 'Responsible Party', 'gsf-data-centre' ) . '</dt><dd>' . esc_html( self::first_meta( $indicator_id, array( 'responsible_party' ) ) ) . '</dd></dl>';
		echo '</section>';
		return ob_get_clean();
	}

	public static function upload_form_shortcode() {
		self::enqueue_public_assets();

		if ( ! is_user_logged_in() ) {
			return '<p>' . esc_html__( 'Please log in to submit monitoring evidence.', 'gsf-data-centre' ) . '</p>';
		}
		if ( ! current_user_can( 'gsf_upload_evidence' ) ) {
			return '<p>' . esc_html__( 'Your account does not have permission to upload evidence.', 'gsf-data-centre' ) . '</p>';
		}

		return '<form class="gsf-meal-upload-form" method="post" enctype="multipart/form-data"><p>' . esc_html__( 'Evidence upload workflow is enabled. Use the Evidence admin screen for managed submissions in this release.', 'gsf-data-centre' ) . '</p><a class="button" href="' . esc_url( admin_url( 'post-new.php?post_type=gsf_evidence' ) ) . '">' . esc_html__( 'Add Evidence', 'gsf-data-centre' ) . '</a></form>';
	}

	public static function report_exports_shortcode() {
		self::enqueue_public_assets();

		if ( ! is_user_logged_in() || ! current_user_can( 'gsf_export_reports' ) ) {
			return '<p>' . esc_html__( 'You do not have permission to export reports.', 'gsf-data-centre' ) . '</p>';
		}

		$exports = array( 'projects', 'indicators', 'indicator_results', 'disaggregation', 'evidence', 'activities', 'ecoequity_scores' );
		$html    = '<section class="gsf-meal-panel"><h2>' . esc_html__( 'Report Exports', 'gsf-data-centre' ) . '</h2><p>';
		foreach ( $exports as $export ) {
			$html .= '<a class="button" href="' . esc_url( self::admin_action_url( 'gsf_data_centre_export_csv', array( 'export' => $export ) ) ) . '">' . esc_html( ucwords( str_replace( '_', ' ', $export ) ) ) . '</a> ';
		}
		$html .= '</p></section>';
		return $html;
	}

	public static function handle_csv_export() {
		self::require_capability( 'gsf_export_reports' );
		check_admin_referer( 'gsf_data_centre_export_csv' );
		$export = isset( $_GET['export'] ) ? sanitize_key( wp_unslash( $_GET['export'] ) ) : '';
		$rows   = self::export_rows( $export );

		if ( ! $rows ) {
			wp_die( esc_html__( 'Export not found.', 'gsf-data-centre' ) );
		}

		self::send_csv( 'gsf-' . $export . '-' . gmdate( 'Ymd-His' ) . '.csv', $rows );
	}

	public static function handle_csv_import() {
		self::require_capability( 'gsf_export_reports' );
		check_admin_referer( 'gsf_data_centre_import_csv' );
		self::install_schema();

		$import_type = isset( $_POST['import_type'] ) ? sanitize_key( wp_unslash( $_POST['import_type'] ) ) : '';
		if ( empty( $_FILES['csv_file']['tmp_name'] ) ) {
			wp_die( esc_html__( 'Valid import type and CSV file are required.', 'gsf-data-centre' ) );
		}

		$file = $_FILES['csv_file'];
		if ( ! empty( $file['error'] ) ) {
			wp_die( esc_html__( 'The CSV file could not be uploaded.', 'gsf-data-centre' ) );
		}

		$handle = fopen( $file['tmp_name'], 'r' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		if ( ! $handle ) {
			wp_die( esc_html__( 'The CSV file could not be opened.', 'gsf-data-centre' ) );
		}

		$headers = self::normalize_csv_headers( fgetcsv( $handle ) );
		if ( ! $headers ) {
			wp_die( esc_html__( 'The CSV file must include a header row.', 'gsf-data-centre' ) );
		}

		$detected_type = self::detect_import_type_from_headers( $headers );
		if ( $detected_type ) {
			$import_type = $detected_type;
		}

		if ( ! self::template_headers( $import_type ) ) {
			wp_die( esc_html__( 'Valid import type and CSV file are required.', 'gsf-data-centre' ) );
		}

		$imported = 0;
		$skipped  = 0;
		while ( ( $row = fgetcsv( $handle ) ) !== false ) {
			if ( empty( array_filter( $row ) ) || ! is_array( $headers ) ) {
				continue;
			}
			$data = array();
			foreach ( $headers as $index => $header ) {
				$data[ sanitize_key( $header ) ] = isset( $row[ $index ] ) ? sanitize_text_field( wp_unslash( $row[ $index ] ) ) : '';
			}
			if ( self::import_row( $import_type, $data ) ) {
				$imported++;
			} else {
				$skipped++;
			}
		}
		fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'         => 'gsf-data-centre-import-export',
					'gsf_imported' => $imported,
					'gsf_skipped'  => $skipped,
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	private static function normalize_csv_headers( $headers ) {
		if ( ! is_array( $headers ) ) {
			return array();
		}

		return array_map(
			static function ( $header ) {
				$header = preg_replace( '/^\xEF\xBB\xBF/', '', (string) $header );
				return sanitize_key( trim( $header ) );
			},
			$headers
		);
	}

	private static function detect_import_type_from_headers( array $headers ) {
		$header_lookup = array_fill_keys( array_filter( $headers ), true );

		if ( isset( $header_lookup['category'], $header_lookup['value_label'] ) || isset( $header_lookup['sex_gender'], $header_lookup['indigenous_status'] ) ) {
			return 'disaggregation';
		}

		$best_type  = '';
		$best_score = 0;
		foreach ( array( 'projects', 'indicators', 'indicator_results', 'evidence', 'activities', 'ecoequity_scores' ) as $type ) {
			$template_headers = self::template_headers( $type );
			$score            = count( array_intersect( $headers, $template_headers ) );
			if ( $score > $best_score ) {
				$best_score = $score;
				$best_type  = $type;
			}
		}

		return $best_score >= 3 ? $best_type : '';
	}

	public static function handle_template_download() {
		self::require_capability( 'gsf_export_reports' );
		check_admin_referer( 'gsf_data_centre_download_template' );
		$template = isset( $_GET['template'] ) ? sanitize_key( wp_unslash( $_GET['template'] ) ) : '';
		$path     = self::template_path( $template );

		if ( ! $path || ! file_exists( $path ) ) {
			wp_die( esc_html__( 'Template not found.', 'gsf-data-centre' ) );
		}

		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . basename( $path ) . '"' );
		readfile( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile
		exit;
	}

	public static function handle_disaggregation_save() {
		self::require_capability( 'gsf_submit_results' );
		check_admin_referer( 'gsf_data_centre_save_disaggregation' );

		global $wpdb;
		$tables       = self::tables();
		$current_page = isset( $_POST['gsf_disaggregation_page'] ) ? max( 1, absint( wp_unslash( $_POST['gsf_disaggregation_page'] ) ) ) : 1;
		$redirect     = add_query_arg(
			array(
				'page'                    => 'gsf-data-centre-disaggregation',
				'gsf_disaggregation_page' => $current_page,
			),
			admin_url( 'admin.php' )
		);

		if ( isset( $_POST['delete_disaggregation_id'] ) ) {
			$row_id = absint( wp_unslash( $_POST['delete_disaggregation_id'] ) );
			if ( $row_id ) {
				$wpdb->delete( $tables['disaggregation'], array( 'id' => $row_id ), array( '%d' ) );
			}

			wp_safe_redirect( add_query_arg( 'gsf_disaggregation_deleted', '1', $redirect ) );
			exit;
		}

		if ( isset( $_POST['add_disaggregation_row'] ) && isset( $_POST['new_row'] ) && is_array( $_POST['new_row'] ) ) {
			$row = self::sanitize_disaggregation_row( wp_unslash( $_POST['new_row'] ) );
			if ( '' !== $row['category'] && '' !== $row['value_label'] ) {
				$wpdb->insert(
					$tables['disaggregation'],
					$row,
					array( '%d', '%s', '%s', '%f' )
				);
			}

			wp_safe_redirect( add_query_arg( 'gsf_disaggregation_added', '1', $redirect ) );
			exit;
		}

		if ( isset( $_POST['rows'] ) && is_array( $_POST['rows'] ) ) {
			$rows = wp_unslash( $_POST['rows'] );
			foreach ( $rows as $row_id => $row ) {
				$row_id = absint( $row_id );
				if ( ! $row_id || ! is_array( $row ) ) {
					continue;
				}

				$data = self::sanitize_disaggregation_row( $row );
				if ( '' === $data['category'] || '' === $data['value_label'] ) {
					continue;
				}

				$wpdb->update(
					$tables['disaggregation'],
					$data,
					array( 'id' => $row_id ),
					array( '%d', '%s', '%s', '%f' ),
					array( '%d' )
				);
			}
		}

		wp_safe_redirect( add_query_arg( 'gsf_disaggregation_saved', '1', $redirect ) );
		exit;
	}

	public static function rest_get_indicators() {
		global $wpdb;
		$tables = self::tables();
		return rest_ensure_response( $wpdb->get_results( "SELECT id, code, title, unit, frequency, source, responsible_party, is_active FROM {$tables['indicators']} ORDER BY code ASC", ARRAY_A ) );
	}

	public static function rest_get_indicator( WP_REST_Request $request ) {
		global $wpdb;
		$tables = self::tables();
		$row    = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$tables['indicators']} WHERE id = %d", absint( $request['id'] ) ), ARRAY_A );
		if ( ! $row ) {
			return new WP_Error( 'gsf_not_found', __( 'Indicator not found.', 'gsf-data-centre' ), array( 'status' => 404 ) );
		}
		return rest_ensure_response( $row );
	}

	public static function rest_get_results() {
		global $wpdb;
		$tables = self::tables();
		$rows   = $wpdb->get_results(
			"SELECT r.id, i.code indicator_code, i.title indicator_title, r.result_value, r.result_text, r.status, r.updated_at
			FROM {$tables['indicator_results']} r
			INNER JOIN {$tables['indicators']} i ON i.id = r.indicator_id
			ORDER BY r.created_at DESC
			LIMIT 200",
			ARRAY_A
		);
		return rest_ensure_response( $rows );
	}

	public static function rest_create_result( WP_REST_Request $request ) {
		global $wpdb;
		$tables       = self::tables();
		$indicator_id = absint( $request->get_param( 'indicator_id' ) );
		if ( ! $indicator_id ) {
			return new WP_Error( 'gsf_missing_indicator', __( 'indicator_id is required.', 'gsf-data-centre' ), array( 'status' => 400 ) );
		}

		$wpdb->insert(
			$tables['indicator_results'],
			array(
				'indicator_id'        => $indicator_id,
				'project_id'          => absint( $request->get_param( 'project_id' ) ),
				'organization_id'     => absint( $request->get_param( 'organization_id' ) ),
				'reporting_period_id' => absint( $request->get_param( 'reporting_period_id' ) ),
				'result_value'        => is_null( $request->get_param( 'result_value' ) ) ? null : (float) $request->get_param( 'result_value' ),
				'result_text'         => wp_kses_post( (string) $request->get_param( 'result_text' ) ),
				'status'              => self::sanitize_status( (string) $request->get_param( 'status' ) ),
				'submitted_by'        => get_current_user_id(),
				'submitted_at'        => current_time( 'mysql' ),
				'updated_at'          => current_time( 'mysql' ),
			),
			array( '%d', '%d', '%d', '%d', '%f', '%s', '%s', '%d', '%s', '%s' )
		);

		return rest_ensure_response( array( 'id' => (int) $wpdb->insert_id ) );
	}

	public static function rest_get_dashboard() {
		return rest_ensure_response( self::dashboard_summary() );
	}

	public static function can_view_rest() {
		return current_user_can( 'gsf_view_dashboard' ) || current_user_can( 'edit_posts' );
	}

	public static function can_submit_rest() {
		return current_user_can( 'gsf_submit_results' );
	}

	private static function capabilities() {
		return array(
			'gsf_view_dashboard'     => true,
			'gsf_manage_projects'    => true,
			'gsf_manage_indicators'  => true,
			'gsf_submit_results'     => true,
			'gsf_verify_results'     => true,
			'gsf_upload_evidence'    => true,
			'gsf_manage_ecoequity'   => true,
			'gsf_export_reports'     => true,
			'gsf_manage_settings'    => true,
		);
	}

	private static function indicator_table_row( WP_Post $indicator ) {
		$indicator_id = $indicator->ID;
		$code         = self::first_meta( $indicator_id, array( 'indicator_code', 'code' ) );
		$status       = self::indicator_reporting_status( $indicator_id );
		$updated      = get_post_modified_time( get_option( 'date_format' ), false, $indicator, true );

		return array(
			'indicator_code'   => $code ? $code : (string) $indicator_id,
			'result_level'     => self::first_meta( $indicator_id, array( 'result_level', 'indicator_level', 'pmf_reference' ) ),
			'indicator_text'   => self::first_meta( $indicator_id, array( 'indicator_text', 'title' ) ) ?: $indicator->post_title,
			'baseline_summary' => self::first_meta( $indicator_id, array( 'baseline_summary', 'baseline' ) ),
			'target_summary'   => self::first_meta( $indicator_id, array( 'target_summary', 'target' ) ),
			'year_3_value'     => self::indicator_period_value( $indicator_id, 'Year 3' ),
			'year_4_value'     => self::indicator_period_value( $indicator_id, 'Year 4' ),
			'status'           => $status,
			'last_updated'     => $updated ? $updated : '-',
		);
	}

	private static function first_meta( $post_id, array $keys ) {
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

	private static function indicator_period_value( $indicator_id, $period_label ) {
		$results = get_posts(
			array(
				'post_type'        => 'gsf_ind_result',
				'post_status'      => array( 'publish', 'draft', 'pending', 'private' ),
				'posts_per_page'   => 1,
				'orderby'          => 'modified',
				'order'            => 'DESC',
				'suppress_filters' => true,
				'meta_query'       => array(
					'relation' => 'AND',
					array(
						'key'     => 'indicator',
						'value'   => (string) $indicator_id,
						'compare' => '=',
					),
					array(
						'key'     => 'reporting_year_period',
						'value'   => $period_label,
						'compare' => 'LIKE',
					),
				),
			)
		);

		if ( ! $results ) {
			return '-';
		}

		$value = self::first_meta( $results[0]->ID, array( 'result_value', 'value_numeric', 'value_text' ) );
		return $value ? $value : '-';
	}

	private static function indicator_reporting_status( $indicator_id ) {
		$results = get_posts(
			array(
				'post_type'        => 'gsf_ind_result',
				'post_status'      => array( 'publish', 'draft', 'pending', 'private' ),
				'posts_per_page'   => -1,
				'fields'           => 'ids',
				'suppress_filters' => true,
				'meta_key'         => 'indicator',
				'meta_value'       => (string) $indicator_id,
			)
		);

		if ( ! $results ) {
			return 'no_data';
		}

		foreach ( $results as $result_id ) {
			$status = sanitize_key( self::first_meta( (int) $result_id, array( 'reporting_status' ) ) );
			if ( in_array( $status, array( 'verified', 'reported', 'submitted' ), true ) ) {
				return $status;
			}
		}

		return 'pending';
	}

	private static function status_label( $status ) {
		$labels = array(
			'draft'        => __( 'Draft', 'gsf-data-centre' ),
			'submitted'    => __( 'Submitted', 'gsf-data-centre' ),
			'reported'     => __( 'Reported', 'gsf-data-centre' ),
			'verified'     => __( 'Verified', 'gsf-data-centre' ),
			'pending'      => __( 'Pending', 'gsf-data-centre' ),
			'partial_data' => __( 'Partial Data', 'gsf-data-centre' ),
			'no_data'      => __( 'No Data', 'gsf-data-centre' ),
			'rejected'     => __( 'Rejected', 'gsf-data-centre' ),
		);

		return isset( $labels[ $status ] ) ? $labels[ $status ] : ucwords( str_replace( '_', ' ', $status ) );
	}

	private static function ensure_pod( $name, $singular_label, $plural_label ) {
		$api = pods_api();
		$pod = self::load_pod( $name );

		if ( ! self::pod_id( $pod ) ) {
			$params = post_type_exists( $name )
				? array(
					'create_extend'   => 'extend',
					'extend_pod_type' => 'post_type',
					'extend_post_type' => $name,
					'extend_storage'  => 'meta',
				)
				: array(
					'create_extend'             => 'create',
					'create_pod_type'           => 'post_type',
					'create_name'               => $name,
					'create_label_singular'     => $singular_label,
					'create_label_plural'       => $plural_label,
					'create_storage'            => 'meta',
					'create_public'             => 1,
					'create_publicly_queryable' => 1,
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
				'public'             => 1,
				'publicly_queryable' => 1,
				'show_ui'            => 1,
				'show_in_menu'       => 0,
				'rest_enable'        => 1,
				'supports_title'     => 1,
				'supports_editor'    => in_array( $name, array( 'gsf_evidence', 'gsf_activity' ), true ) ? 1 : 0,
				'supports_thumbnail' => in_array( $name, array( 'gsf_evidence', 'gsf_activity' ), true ) ? 1 : 0,
			)
		);
	}

	private static function indicator_pod_fields() {
		return array(
			'indicator_code'      => self::text_field_args( __( 'Indicator Code', 'gsf-data-centre' ), 100 ),
			'pmf_reference'       => self::text_field_args( __( 'PMF Reference', 'gsf-data-centre' ), 255 ),
			'result_code'         => self::text_field_args( __( 'Result Code', 'gsf-data-centre' ), 50 ),
			'result_level'        => self::pick_field_args( __( 'Result Level', 'gsf-data-centre' ), array( 'Ultimate Outcome', 'Intermediate Outcome', 'Immediate Outcome', 'Output' ) ),
			'indicator_text'      => array( 'label' => __( 'Indicator Statement', 'gsf-data-centre' ), 'type' => 'paragraph' ),
			'indicator_type'      => self::pick_field_args( __( 'Indicator Type', 'gsf-data-centre' ), array( 'number', 'percentage', 'currency', 'hectares', 'index', 'milestone', 'text' ) ),
			'unit_of_measure'     => self::text_field_args( __( 'Unit of Measure', 'gsf-data-centre' ), 100 ),
			'baseline_summary'    => array( 'label' => __( 'Baseline Summary', 'gsf-data-centre' ), 'type' => 'paragraph' ),
			'target_summary'      => array( 'label' => __( 'Target Summary', 'gsf-data-centre' ), 'type' => 'paragraph' ),
			'frequency'           => self::text_field_args( __( 'Frequency', 'gsf-data-centre' ), 100 ),
			'calculation_type'    => self::pick_field_args( __( 'Calculation Type', 'gsf-data-centre' ), array( 'simple_count', 'percentage', 'sum', 'average', 'index_change', 'milestone_yes_no', 'manual_entry' ) ),
			'higher_is_better'    => array( 'label' => __( 'Higher Is Better', 'gsf-data-centre' ), 'type' => 'boolean' ),
			'data_source'         => array( 'label' => __( 'Data Source', 'gsf-data-centre' ), 'type' => 'paragraph' ),
			'method'              => array( 'label' => __( 'Collection Method', 'gsf-data-centre' ), 'type' => 'paragraph' ),
			'disaggregation'      => array( 'label' => __( 'Disaggregation Requirements', 'gsf-data-centre' ), 'type' => 'paragraph' ),
			'definition_criteria' => array( 'label' => __( 'Definition / Criteria', 'gsf-data-centre' ), 'type' => 'paragraph' ),
			'target_population'   => array( 'label' => __( 'Target Population', 'gsf-data-centre' ), 'type' => 'paragraph' ),
			'responsible_party'   => self::text_field_args( __( 'Responsible Party', 'gsf-data-centre' ), 255 ),
			'additional_remarks'  => array( 'label' => __( 'Additional Remarks', 'gsf-data-centre' ), 'type' => 'paragraph' ),
			'is_public'           => array( 'label' => __( 'Show on Public Dashboards', 'gsf-data-centre' ), 'type' => 'boolean' ),
		);
	}

	private static function indicator_result_pod_fields() {
		return array(
			'indicator'             => self::post_pick_field_args( __( 'Indicator', 'gsf-data-centre' ), 'gsf_indicator' ),
			'reporting_year_period' => self::text_field_args( __( 'Reporting Year / Period', 'gsf-data-centre' ), 100 ),
			'quarter'               => self::text_field_args( __( 'Quarter', 'gsf-data-centre' ), 50 ),
			'country'               => self::pick_field_args( __( 'Country', 'gsf-data-centre' ), self::country_values() ),
			'organization'          => self::post_pick_field_args( __( 'Organization', 'gsf-data-centre' ), 'gsf_organization' ),
			'project'               => self::post_pick_field_args( __( 'Project', 'gsf-data-centre' ), 'gsf_project' ),
			'value_numeric'         => array( 'label' => __( 'Numeric Value', 'gsf-data-centre' ), 'type' => 'number', 'number_decimals' => '4' ),
			'value_text'            => array( 'label' => __( 'Text / Milestone Value', 'gsf-data-centre' ), 'type' => 'paragraph' ),
			'currency'              => self::text_field_args( __( 'Currency', 'gsf-data-centre' ), 20 ),
			'reporting_status'      => self::pick_field_args( __( 'Reporting Status', 'gsf-data-centre' ), self::status_values() ),
			'evidence_comment'      => array( 'label' => __( 'Evidence Comment', 'gsf-data-centre' ), 'type' => 'paragraph' ),
			'data_quality_note'     => array( 'label' => __( 'Data Quality Note', 'gsf-data-centre' ), 'type' => 'paragraph' ),
		);
	}

	private static function organization_pod_fields() {
		return array(
			'organization_type' => self::pick_field_args( __( 'Organization Type', 'gsf-data-centre' ), array( 'NCTF', 'EWRO', 'Environmental Organization', 'Women’s Rights Organization', 'Community-Based Organization', 'CBF', 'Cuso International', 'Government', 'Donor', 'Partner', 'Other' ) ),
			'country'           => self::pick_field_args( __( 'Country', 'gsf-data-centre' ), self::country_values() ),
			'contact_name'      => self::text_field_args( __( 'Contact Name', 'gsf-data-centre' ), 180 ),
			'contact_email'     => self::text_field_args( __( 'Contact Email', 'gsf-data-centre' ), 180 ),
		);
	}

	private static function evidence_pod_fields() {
		return array(
			'indicator'           => self::post_pick_field_args( __( 'Indicator', 'gsf-data-centre' ), 'gsf_indicator' ),
			'indicator_result'    => self::post_pick_field_args( __( 'Indicator Result', 'gsf-data-centre' ), 'gsf_ind_result' ),
			'organization'        => self::post_pick_field_args( __( 'Organization', 'gsf-data-centre' ), 'gsf_organization' ),
			'project'             => self::post_pick_field_args( __( 'Project', 'gsf-data-centre' ), 'gsf_project' ),
			'evidence_type'       => self::pick_field_args( __( 'Evidence Type', 'gsf-data-centre' ), array( 'report', 'spreadsheet', 'financial_record', 'survey', 'attendance_log', 'photo', 'video', 'gis_file', 'hub_analytics', 'registry', 'policy_document', 'strategic_plan', 'proposal_template', 'assessment', 'other' ) ),
			'file_url'            => self::text_field_args( __( 'File URL', 'gsf-data-centre' ), 255 ),
			'source_organization' => self::text_field_args( __( 'Source Organization', 'gsf-data-centre' ), 255 ),
			'evidence_date'       => array( 'label' => __( 'Evidence Date', 'gsf-data-centre' ), 'type' => 'date' ),
			'visibility'          => self::pick_field_args( __( 'Visibility', 'gsf-data-centre' ), array( 'public', 'internal', 'restricted' ) ),
			'notes'               => array( 'label' => __( 'Notes', 'gsf-data-centre' ), 'type' => 'paragraph' ),
		);
	}

	private static function activity_pod_fields() {
		return array(
			'activity_type'     => self::pick_field_args( __( 'Activity Type', 'gsf-data-centre' ), array( 'training', 'workshop', 'webinar', 'learning_exchange', 'dialogue', 'survey', 'hub_registration', 'hub_download', 'hub_course_completion', 'technical_assessment', 'meeting', 'other' ) ),
			'activity_date'     => array( 'label' => __( 'Activity Date', 'gsf-data-centre' ), 'type' => 'date' ),
			'country'           => self::pick_field_args( __( 'Country', 'gsf-data-centre' ), self::country_values() ),
			'organization'      => self::post_pick_field_args( __( 'Organization', 'gsf-data-centre' ), 'gsf_organization' ),
			'project'           => self::post_pick_field_args( __( 'Project', 'gsf-data-centre' ), 'gsf_project' ),
			'indicator'         => self::post_pick_field_args( __( 'Indicator', 'gsf-data-centre' ), 'gsf_indicator' ),
			'delivery_mode'     => self::pick_field_args( __( 'Delivery Mode', 'gsf-data-centre' ), array( 'virtual', 'in_person', 'hybrid', 'self_paced' ) ),
			'participant_count' => array( 'label' => __( 'Participant Count', 'gsf-data-centre' ), 'type' => 'number', 'number_decimals' => '0' ),
			'rating_score'      => array( 'label' => __( 'Rating Score', 'gsf-data-centre' ), 'type' => 'number', 'number_decimals' => '2' ),
			'activity_status'   => self::pick_field_args( __( 'Activity Status', 'gsf-data-centre' ), self::status_values() ),
			'notes'             => array( 'label' => __( 'Notes', 'gsf-data-centre' ), 'type' => 'paragraph' ),
		);
	}

	private static function index_pod_fields() {
		return array(
			'index_description' => array( 'label' => __( 'Index Description', 'gsf-data-centre' ), 'type' => 'paragraph' ),
			'scoring_scale'     => self::text_field_args( __( 'Scoring Scale', 'gsf-data-centre' ), 100 ),
			'min_score'         => array( 'label' => __( 'Minimum Score', 'gsf-data-centre' ), 'type' => 'number', 'number_decimals' => '2' ),
			'max_score'         => array( 'label' => __( 'Maximum Score', 'gsf-data-centre' ), 'type' => 'number', 'number_decimals' => '2' ),
			'higher_is_better'  => array( 'label' => __( 'Higher Is Better', 'gsf-data-centre' ), 'type' => 'boolean' ),
			'frequency'         => self::text_field_args( __( 'Frequency', 'gsf-data-centre' ), 100 ),
		);
	}

	private static function text_field_args( $label, $max_length = 255 ) {
		return array(
			'label'           => $label,
			'type'            => 'text',
			'text_max_length' => (string) $max_length,
		);
	}

	private static function pick_field_args( $label, array $values ) {
		return array(
			'label'                 => $label,
			'type'                  => 'pick',
			'pick_object'           => 'custom-simple',
			'pick_custom'           => self::format_options( $values ),
			'pick_format_type'      => 'single',
			'pick_format_single'    => 'dropdown',
			'pick_show_select_text' => '1',
		);
	}

	private static function post_pick_field_args( $label, $post_type ) {
		return array(
			'label'                 => $label,
			'type'                  => 'pick',
			'pick_object'           => 'post_type',
			'pick_val'              => $post_type,
			'pick_format_type'      => 'single',
			'pick_format_single'    => 'dropdown',
			'pick_show_select_text' => '1',
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
					'pod_id'              => $pod_id,
					'pod'                 => $pod_name,
					'group_id'            => $group_id,
					'name'                => $name,
					'weight'              => $weight,
					'required'            => '0',
					'rest_pick_response'  => 'array',
					'rest_pick_depth'     => '1',
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

	private static function format_options( array $values ) {
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
		return array( 'Belize', 'Dominica', 'Grenada', 'Jamaica', 'Saint Lucia', 'St Vincent and the Grenadines', 'Suriname', 'Trinidad and Tobago', 'Regional', 'Other' );
	}

	private static function status_values() {
		return array( 'draft', 'submitted', 'reported', 'verified', 'pending', 'partial_data', 'no_data', 'rejected' );
	}

	private static function enqueue_public_assets() {
		if ( wp_style_is( 'gsf-data-centre', 'registered' ) ) {
			wp_enqueue_style( 'gsf-data-centre' );
			return;
		}

		$css_path = plugin_dir_path( dirname( __FILE__ ) ) . 'assets/data-centre.css';
		wp_enqueue_style(
			'gsf-data-centre',
			plugins_url( 'assets/data-centre.css', dirname( __FILE__ ) ),
			array(),
			file_exists( $css_path ) ? (string) filemtime( $css_path ) : self::VERSION
		);
	}

	private static function tables() {
		global $wpdb;
		$prefix = $wpdb->prefix . 'gsf_';
		return array(
			'result_levels'         => $prefix . 'result_levels',
			'indicators'            => $prefix . 'indicators',
			'indicator_targets'     => $prefix . 'indicator_targets',
			'indicator_methods'     => $prefix . 'indicator_methods',
			'data_sources'          => $prefix . 'data_sources',
			'reporting_periods'     => $prefix . 'reporting_periods',
			'indicator_results'     => $prefix . 'indicator_results',
			'disaggregation'        => $prefix . 'disaggregation',
			'evidence'              => $prefix . 'evidence',
			'activity_events'       => $prefix . 'activity_events',
			'activity_participation' => $prefix . 'activity_participation',
			'index_frameworks'      => $prefix . 'index_frameworks',
			'index_criteria'        => $prefix . 'index_criteria',
			'index_scores'          => $prefix . 'index_scores',
			'index_criteria_scores' => $prefix . 'index_criteria_scores',
		);
	}

	private static function seed_terms() {
		$terms = array(
			'gsf_result_level'      => array( 'Ultimate Outcome', 'Intermediate Outcome', 'Immediate Outcome', 'Output' ),
			'gsf_country'           => array( 'Belize', 'Dominica', 'Grenada', 'Jamaica', 'Saint Lucia', 'St Vincent and the Grenadines', 'Suriname', 'Trinidad and Tobago', 'Regional', 'Other' ),
			'gsf_organization_type' => array( 'NCTF', 'EWRO', 'Environmental Organization', 'Women’s Rights Organization', 'Community-Based Organization', 'CBF', 'Cuso International', 'Government', 'Donor', 'Partner', 'Other' ),
			'gsf_project_type'      => array( 'EbA', 'Circular Economy', 'NbCS', 'Capacity Building', 'Technical Assistance', 'Learning Exchange', 'Webinar', 'Policy / Governance', 'Other' ),
			'gsf_conservation_type' => array( 'Terrestrial', 'Aquatic', 'Coastal', 'Marine', 'Forest', 'Protected Area', 'Climate Adaptation', 'Ecosystem Restoration', 'Conservation Finance', 'Other' ),
			'gsf_gender_theme'      => array( 'Gender-responsive planning', 'Gender budgeting', 'Gender analysis', 'GESI', 'Women’s leadership', 'Indigenous inclusion', 'Disability inclusion', 'Gender monitoring', 'Human rights-based approach' ),
			'gsf_reporting_status'  => array( 'Draft', 'Submitted', 'Reported', 'Verified', 'Pending', 'Partial Data', 'No Data', 'Rejected' ),
		);

		foreach ( $terms as $taxonomy => $names ) {
			if ( ! taxonomy_exists( $taxonomy ) ) {
				continue;
			}
			foreach ( $names as $name ) {
				if ( ! term_exists( $name, $taxonomy ) ) {
					wp_insert_term( $name, $taxonomy );
				}
			}
		}
	}

	private static function seed_tables() {
		global $wpdb;
		$tables = self::tables();

		$levels = array(
			array( '1000', 'Impact', 0, 10 ),
			array( '1100', 'Outcome', 0, 20 ),
			array( '1110', 'Output', 0, 30 ),
			array( '1111', 'Activity', 0, 40 ),
		);
		foreach ( $levels as $level ) {
			$exists = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$tables['result_levels']} WHERE code = %s", $level[0] ) );
			if ( ! $exists ) {
				$wpdb->insert( $tables['result_levels'], array( 'code' => $level[0], 'name' => $level[1], 'parent_id' => null, 'sort_order' => $level[3] ), array( '%s', '%s', '%d', '%d' ) );
			}
		}

		$periods = array(
			array( '2026-Q1', '2026 Quarter 1', '2026-01-01', '2026-03-31' ),
			array( '2026-Q2', '2026 Quarter 2', '2026-04-01', '2026-06-30' ),
			array( '2026-Q3', '2026 Quarter 3', '2026-07-01', '2026-09-30' ),
			array( '2026-Q4', '2026 Quarter 4', '2026-10-01', '2026-12-31' ),
		);
		foreach ( $periods as $period ) {
			$exists = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$tables['reporting_periods']} WHERE code = %s", $period[0] ) );
			if ( ! $exists ) {
				$wpdb->insert( $tables['reporting_periods'], array( 'code' => $period[0], 'label' => $period[1], 'start_date' => $period[2], 'end_date' => $period[3], 'status' => 'open' ), array( '%s', '%s', '%s', '%s', '%s' ) );
			}
		}

		$framework_id = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$tables['index_frameworks']} WHERE code = %s", 'ecoequity' ) );
		if ( ! $framework_id ) {
			$wpdb->insert( $tables['index_frameworks'], array( 'code' => 'ecoequity', 'name' => 'Ecoequity Index', 'description' => 'Organization-level assessment of gender-responsive conservation capacity.' ), array( '%s', '%s', '%s' ) );
			$framework_id = (int) $wpdb->insert_id;
		}

		$criteria = array(
			array( 'leadership', 'Leadership and Governance' ),
			array( 'participation', 'Inclusive Participation' ),
			array( 'safeguarding', 'Safeguarding and Accountability' ),
			array( 'data', 'Gender Data and Learning' ),
			array( 'resourcing', 'Gender-Responsive Resourcing' ),
		);
		foreach ( $criteria as $index => $criterion ) {
			$exists = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$tables['index_criteria']} WHERE framework_id = %d AND code = %s", $framework_id, $criterion[0] ) );
			if ( ! $exists ) {
				$wpdb->insert( $tables['index_criteria'], array( 'framework_id' => $framework_id, 'code' => $criterion[0], 'name' => $criterion[1], 'weight' => 1, 'sort_order' => ( $index + 1 ) * 10 ), array( '%d', '%s', '%s', '%f', '%d' ) );
			}
		}
	}

	private static function seed_pods_defaults() {
		if ( ! post_type_exists( 'gsf_index' ) ) {
			return;
		}

		$existing = get_page_by_title( 'Ecoequity Index', OBJECT, 'gsf_index' );
		if ( $existing ) {
			return;
		}

		$post_id = wp_insert_post(
			array(
				'post_type'   => 'gsf_index',
				'post_status' => 'publish',
				'post_title'  => 'Ecoequity Index',
			)
		);

		if ( $post_id && ! is_wp_error( $post_id ) ) {
			update_post_meta( $post_id, 'index_description', 'Organization-level capacity scorecard for inclusive, gender-responsive conservation operations.' );
			update_post_meta( $post_id, 'scoring_scale', '1-5' );
			update_post_meta( $post_id, 'min_score', '1' );
			update_post_meta( $post_id, 'max_score', '5' );
			update_post_meta( $post_id, 'higher_is_better', '1' );
			update_post_meta( $post_id, 'frequency', 'Baseline, Mid-term, Endline' );
		}
	}

	private static function dashboard_summary() {
		return array(
			__( 'Indicators', 'gsf-data-centre' )        => self::count_posts( 'gsf_indicator' ),
			__( 'Reported Indicators', 'gsf-data-centre' ) => self::count_indicators_by_status( array( 'submitted', 'reported', 'verified' ) ),
			__( 'Pending Indicators', 'gsf-data-centre' ) => self::count_indicators_by_status( array( 'pending', 'draft', 'partial_data' ) ),
			__( 'No Data Indicators', 'gsf-data-centre' ) => self::count_indicators_by_status( array( 'no_data' ) ),
			__( 'Evidence Files', 'gsf-data-centre' )    => self::count_posts( 'gsf_evidence' ),
			__( 'Organizations', 'gsf-data-centre' )     => self::count_posts( 'gsf_organization' ),
			__( 'Projects', 'gsf-data-centre' )          => self::count_posts( 'gsf_project' ),
			__( 'Ecoequity Assessments', 'gsf-data-centre' ) => self::count_posts( 'gsf_ecoequity' ) + self::count_posts( 'gsf_index' ),
		);
	}

	private static function count_posts( $post_type ) {
		$count = wp_count_posts( $post_type );
		if ( ! $count ) {
			return 0;
		}

		return (int) $count->publish + (int) $count->draft + (int) $count->pending + (int) $count->private;
	}

	private static function count_indicators_by_status( array $statuses ) {
		$indicators = get_posts(
			array(
				'post_type'        => 'gsf_indicator',
				'post_status'      => array( 'publish', 'draft', 'pending', 'private' ),
				'posts_per_page'   => -1,
				'fields'           => 'ids',
				'suppress_filters' => true,
			)
		);
		$count = 0;
		foreach ( $indicators as $indicator_id ) {
			if ( in_array( self::indicator_reporting_status( (int) $indicator_id ), $statuses, true ) ) {
				$count++;
			}
		}
		return $count;
	}

	private static function summary_cards_html( array $summary ) {
		$html = '<section class="gsf-meal-summary">';
		foreach ( $summary as $label => $value ) {
			$html .= '<div class="gsf-meal-summary__card"><strong>' . esc_html( number_format_i18n( (float) $value ) ) . '</strong><span>' . esc_html( $label ) . '</span></div>';
		}
		$html .= '</section>';
		return $html;
	}

	private static function export_rows( $export ) {
		if ( 'projects' === $export ) {
			return self::export_project_rows();
		}

		if ( 'indicators' === $export ) {
			return self::export_indicator_rows();
		}

		if ( 'ecoequity_scores' === $export ) {
			return self::export_ecoequity_score_rows();
		}

		if ( 'disaggregation' === $export ) {
			return self::export_disaggregation_rows();
		}

		global $wpdb;
		$tables = self::tables();
		$map    = array(
			'indicator_results'  => "SELECT id, indicator_id, project_id, organization_id, reporting_period_id, result_value, result_text, status, submitted_at, verified_at FROM {$tables['indicator_results']} ORDER BY created_at DESC",
			'evidence'           => "SELECT result_id, project_id, evidence_type, title, source_url, status, created_at FROM {$tables['evidence']} ORDER BY created_at DESC",
			'activities'         => "SELECT project_id, title, activity_type, country, start_date, end_date, status FROM {$tables['activity_events']} ORDER BY start_date DESC",
		);

		if ( empty( $map[ $export ] ) ) {
			return array();
		}

		$rows = $wpdb->get_results( $map[ $export ], ARRAY_A );
		if ( ! $rows ) {
			return array( self::template_headers( $export ) );
		}

		array_unshift( $rows, array_keys( $rows[0] ) );
		return $rows;
	}

	private static function export_disaggregation_rows() {
		global $wpdb;
		$tables  = self::tables();
		$headers = self::template_headers( 'disaggregation' );
		$rows    = array( $headers );
		$records = $wpdb->get_results(
			"SELECT d.result_id, d.category, d.value_label, d.value_number, r.indicator_id, r.project_id, r.organization_id, r.reporting_period_id, p.code AS reporting_year
			FROM {$tables['disaggregation']} d
			LEFT JOIN {$tables['indicator_results']} r ON r.id = d.result_id
			LEFT JOIN {$tables['reporting_periods']} p ON p.id = r.reporting_period_id
			ORDER BY d.result_id ASC, d.id ASC",
			ARRAY_A
		);

		foreach ( (array) $records as $record ) {
			$project_id      = isset( $record['project_id'] ) ? absint( $record['project_id'] ) : 0;
			$organization_id = isset( $record['organization_id'] ) ? absint( $record['organization_id'] ) : 0;
			$context         = array(
				'indicator_code' => self::indicator_code_from_id( isset( $record['indicator_id'] ) ? absint( $record['indicator_id'] ) : 0 ),
				'result_id'      => isset( $record['result_id'] ) ? (string) absint( $record['result_id'] ) : '',
				'reporting_year' => isset( $record['reporting_year'] ) ? (string) $record['reporting_year'] : '',
				'country'        => $project_id ? (string) get_post_meta( $project_id, 'country', true ) : '',
				'organization'   => $organization_id ? get_the_title( $organization_id ) : '',
				'project'        => $project_id ? get_the_title( $project_id ) : '',
				'category'       => isset( $record['category'] ) ? (string) $record['category'] : '',
				'value_label'    => isset( $record['value_label'] ) ? (string) $record['value_label'] : '',
				'value'          => isset( $record['value_number'] ) ? (string) $record['value_number'] : '',
				'notes'          => '',
			);

			$row = array();
			foreach ( $headers as $header ) {
				$row[] = isset( $context[ $header ] ) ? $context[ $header ] : '';
			}
			$rows[] = $row;
		}

		return $rows;
	}

	private static function export_ecoequity_score_rows() {
		$headers = self::template_headers( 'ecoequity_scores' );
		$rows    = array( $headers );
		$posts   = get_posts(
			array(
				'post_type'        => 'gsf_ecoequity',
				'post_status'      => array( 'publish', 'draft', 'pending', 'private' ),
				'posts_per_page'   => -1,
				'orderby'          => 'date',
				'order'            => 'DESC',
				'suppress_filters' => true,
			)
		);

		foreach ( $posts as $post ) {
			$organization_name = self::first_meta( $post->ID, array( 'organization_name' ) );
			$total_score       = self::first_meta( $post->ID, array( 'total_score', 'overall_score', 'baseline_score', 'midterm_score', 'endline_score' ) );

			if ( '' === $organization_name || '' === $total_score ) {
				continue;
			}

			$row = array();
			foreach ( $headers as $key ) {
				if ( 'index_id' === $key ) {
					$row[] = self::first_meta( $post->ID, array( 'index_id', 'index_name', 'framework_code' ) );
				} elseif ( 'total_score' === $key ) {
					$row[] = $total_score;
				} elseif ( 'organization_name' === $key ) {
					$row[] = $organization_name;
				} elseif ( 'score_status' === $key ) {
					$row[] = self::first_meta( $post->ID, array( 'score_status', 'status' ) );
				} else {
					$row[] = self::first_meta( $post->ID, array( $key ) );
				}
			}
			$rows[] = $row;
		}

		if ( count( $rows ) > 1 ) {
			return $rows;
		}

		return self::export_ecoequity_score_table_rows( $headers );
	}

	private static function export_ecoequity_score_table_rows( array $headers ) {
		global $wpdb;
		$tables = self::tables();
		$rows   = array( $headers );
		$sql    = "SELECT f.code AS index_id, p.code AS reporting_period, s.overall_score AS total_score, s.status AS score_status, s.created_at
			FROM {$tables['index_scores']} s
			INNER JOIN {$tables['index_frameworks']} f ON f.id = s.framework_id
			LEFT JOIN {$tables['reporting_periods']} p ON p.id = s.reporting_period_id
			ORDER BY s.created_at DESC";
		$scores = $wpdb->get_results( $sql, ARRAY_A );

		foreach ( (array) $scores as $score ) {
			$row = array();
			foreach ( $headers as $key ) {
				$row[] = isset( $score[ $key ] ) ? $score[ $key ] : '';
			}
			$rows[] = $row;
		}

		return $rows;
	}

	private static function export_indicator_rows() {
		$headers = self::template_headers( 'indicators' );
		$rows    = array( $headers );
		$posts   = get_posts(
			array(
				'post_type'        => 'gsf_indicator',
				'post_status'      => array( 'publish', 'draft', 'pending', 'private' ),
				'posts_per_page'   => -1,
				'orderby'          => 'meta_value',
				'meta_key'         => 'indicator_code',
				'order'            => 'ASC',
				'suppress_filters' => true,
			)
		);

		foreach ( $posts as $post ) {
			$row = array();
			foreach ( $headers as $key ) {
				$row[] = self::first_meta( $post->ID, array( $key ) );
			}
			$rows[] = $row;
		}

		return $rows;
	}

	private static function export_project_rows() {
		$headers = self::template_headers( 'projects' );
		$rows    = array( $headers );
		$posts   = get_posts(
			array(
				'post_type'        => 'gsf_project',
				'post_status'      => array( 'publish', 'draft', 'pending', 'private' ),
				'posts_per_page'   => -1,
				'orderby'          => 'title',
				'order'            => 'ASC',
				'suppress_filters' => true,
			)
		);

		foreach ( $posts as $post ) {
			$row = array();
			foreach ( $headers as $key ) {
				if ( 'project_id' === $key ) {
					$row[] = (string) $post->ID;
				} elseif ( 'project_title' === $key ) {
					$row[] = $post->post_title;
				} elseif ( 'project_slug' === $key ) {
					$row[] = $post->post_name;
				} elseif ( 'post_status' === $key ) {
					$row[] = $post->post_status;
				} elseif ( 'description' === $key ) {
					$row[] = $post->post_content;
				} elseif ( 'organizations' === $key ) {
					$organizations = array_filter( array_map( 'trim', array_map( 'strval', get_post_meta( $post->ID, 'organizations', false ) ) ) );
					$row[]         = implode( ' | ', $organizations );
				} else {
					$row[] = get_post_meta( $post->ID, $key, true );
				}
			}
			$rows[] = $row;
		}

		return $rows;
	}

	private static function import_row( $type, array $data ) {
		global $wpdb;
		$tables = self::tables();

		if ( 'projects' === $type ) {
			$title = trim( (string) ( $data['project_title'] ?? '' ) );
			if ( '' === $title ) {
				return false;
			}

			$post_id = isset( $data['project_id'] ) ? absint( $data['project_id'] ) : 0;
			if ( $post_id && 'gsf_project' !== get_post_type( $post_id ) ) {
				$post_id = 0;
			}

			$project_slug = sanitize_title( (string) ( $data['project_slug'] ?? '' ) );
			if ( ! $post_id && '' !== $project_slug ) {
				$existing = get_page_by_path( $project_slug, OBJECT, 'gsf_project' );
				$post_id  = $existing ? (int) $existing->ID : 0;
			}

			if ( ! $post_id ) {
				$existing = get_posts(
					array(
						'post_type'        => 'gsf_project',
						'post_status'      => 'any',
						'posts_per_page'   => 1,
						'fields'           => 'ids',
						'title'            => $title,
						'suppress_filters' => true,
					)
				);
				$post_id = $existing ? (int) $existing[0] : 0;
			}

			$post_status = sanitize_key( (string) ( $data['post_status'] ?? 'publish' ) );
			if ( ! in_array( $post_status, array( 'publish', 'draft', 'pending', 'private' ), true ) ) {
				$post_status = 'publish';
			}

			$postarr = array(
				'post_type'    => 'gsf_project',
				'post_status'  => $post_status,
				'post_title'   => $title,
				'post_content' => (string) ( $data['description'] ?? '' ),
			);
			if ( '' !== $project_slug ) {
				$postarr['post_name'] = $project_slug;
			}

			if ( $post_id ) {
				$postarr['ID'] = $post_id;
				$post_id       = wp_update_post( $postarr, true );
			} else {
				$post_id = wp_insert_post( $postarr, true );
			}

			if ( is_wp_error( $post_id ) || ! $post_id ) {
				return false;
			}

			if ( array_key_exists( 'organizations', $data ) ) {
				delete_post_meta( $post_id, 'organizations' );
				$organizations = preg_split( '/\s*[|;]\s*/', (string) $data['organizations'] );
				foreach ( array_unique( array_filter( array_map( 'trim', (array) $organizations ) ) ) as $organization ) {
					add_post_meta( $post_id, 'organizations', sanitize_text_field( $organization ) );
				}
			}

			$integer_fields = array( 'percent_complete', 'people_affected_total', 'people_affected_female', 'people_affected_male', 'people_affected_other', 'people_affected_non_indigenous_male', 'people_affected_non_indigenous_female', 'people_affected_indigenous_male', 'people_affected_indigenous_female' );
			$score_fields   = array( 'climate_hazard_score', 'habitat_quality_score', 'social_economic_resilience_score', 'durability_score' );
			$core_fields    = array( 'project_id', 'project_title', 'project_slug', 'post_status', 'description', 'organizations' );

			foreach ( self::template_headers( 'projects' ) as $key ) {
				if ( in_array( $key, $core_fields, true ) || ! array_key_exists( $key, $data ) ) {
					continue;
				}

				$value = $data[ $key ];
				if ( in_array( $key, $integer_fields, true ) ) {
					$value = '' === trim( (string) $value ) ? '' : max( 0, absint( $value ) );
					if ( 'percent_complete' === $key && '' !== $value ) {
						$value = min( 100, $value );
					}
				} elseif ( in_array( $key, $score_fields, true ) ) {
					$value = '' === trim( (string) $value ) ? '' : min( 100, max( 0, (float) $value ) );
				}

				update_post_meta( $post_id, $key, $value );
			}

			return true;
		}

		if ( 'indicators' === $type ) {
			if ( empty( $data['indicator_code'] ) || empty( $data['indicator_text'] ) ) {
				return false;
			}

			$existing = get_posts(
				array(
					'post_type'        => 'gsf_indicator',
					'post_status'      => 'any',
					'posts_per_page'   => 1,
					'fields'           => 'ids',
					'suppress_filters' => true,
					'meta_key'         => 'indicator_code',
					'meta_value'       => $data['indicator_code'],
				)
			);

			$post_id = $existing ? (int) $existing[0] : 0;
			$postarr = array(
				'post_type'    => 'gsf_indicator',
				'post_status'  => 'publish',
				'post_title'   => $data['indicator_code'] . ' - ' . wp_trim_words( $data['indicator_text'], 12, '' ),
				'post_content' => isset( $data['definition_criteria'] ) ? $data['definition_criteria'] : '',
			);
			if ( $post_id ) {
				$postarr['ID'] = $post_id;
				$post_id       = wp_update_post( $postarr, true );
			} else {
				$post_id = wp_insert_post( $postarr, true );
			}

			if ( is_wp_error( $post_id ) || ! $post_id ) {
				return false;
			}

			foreach ( self::template_headers( 'indicators' ) as $key ) {
				if ( isset( $data[ $key ] ) ) {
					update_post_meta( $post_id, $key, $data[ $key ] );
				}
			}
			return true;
		}

		if ( 'indicator_results' === $type ) {
			$indicator_id = self::indicator_id_from_code( isset( $data['indicator_code'] ) ? $data['indicator_code'] : '' );
			if ( ! $indicator_id ) {
				return false;
			}

			$reporting_period = isset( $data['reporting_year'] ) ? $data['reporting_year'] : '';
			$title_bits       = array_filter( array( $data['indicator_code'] ?? '', $reporting_period, $data['quarter'] ?? '' ) );
			$post_id          = wp_insert_post(
				array(
					'post_type'    => 'gsf_ind_result',
					'post_status'  => 'publish',
					'post_title'   => implode( ' - ', $title_bits ),
					'post_content' => ! empty( $data['result_narrative'] ) ? $data['result_narrative'] : ( $data['evidence_comment'] ?? '' ),
				),
				true
			);

			if ( is_wp_error( $post_id ) || ! $post_id ) {
				return false;
			}

			$meta = array(
				'indicator'             => $indicator_id,
				'reporting_year_period' => $reporting_period,
				'quarter'               => $data['quarter'] ?? '',
				'country'               => $data['country'] ?? '',
				'organization'          => self::post_id_from_title( 'gsf_organization', $data['organization'] ?? '' ),
				'project'               => self::post_id_from_title( 'gsf_project', $data['project'] ?? '' ),
				'result_value'          => $data['result_value'] ?? '',
				'value_numeric'         => $data['value_numeric'] ?? '',
				'value_text'            => $data['value_text'] ?? '',
				'result_narrative'      => $data['result_narrative'] ?? '',
				'currency'              => $data['currency'] ?? '',
				'reporting_status'      => self::sanitize_status( $data['reporting_status'] ?? 'draft' ),
				'evidence_comment'      => $data['evidence_comment'] ?? '',
				'data_quality_note'     => $data['data_quality_note'] ?? '',
			);

			foreach ( $meta as $key => $value ) {
				update_post_meta( $post_id, $key, $value );
			}

			$wpdb->insert(
				$tables['indicator_results'],
				array(
					'indicator_id'        => $indicator_id,
					'project_id'          => absint( $meta['project'] ),
					'organization_id'     => absint( $meta['organization'] ),
					'reporting_period_id' => self::reporting_period_id_from_code( $reporting_period ),
					'result_value'        => '' !== (string) $meta['value_numeric'] ? (float) $meta['value_numeric'] : null,
					'result_text'         => (string) $meta['value_text'],
					'status'              => (string) $meta['reporting_status'],
					'submitted_by'        => get_current_user_id(),
					'submitted_at'        => current_time( 'mysql' ),
					'created_at'          => current_time( 'mysql' ),
					'updated_at'          => current_time( 'mysql' ),
				)
			);

			return true;
		}

		if ( 'disaggregation' === $type ) {
			$result_id    = self::resolve_result_id_for_disaggregation( $data );
			$raw_value    = isset( $data['value'] ) ? $data['value'] : ( $data['value_number'] ?? '' );
			$value_number = '' !== trim( (string) $raw_value ) ? (float) $raw_value : null;
			$category     = isset( $data['category'] ) ? sanitize_key( $data['category'] ) : '';
			$value_label  = isset( $data['value_label'] ) ? $data['value_label'] : '';

			if ( '' === $category || '' === trim( (string) $value_label ) ) {
				$breakdown = self::disaggregation_breakdown_from_row( $data );
				$category  = $breakdown['category'];
				$value_label = $breakdown['value_label'];
			}

			if ( '' === $category || '' === trim( (string) $value_label ) || null === $value_number ) {
				return false;
			}

			return false !== $wpdb->insert(
				$tables['disaggregation'],
				array(
					'result_id'     => $result_id,
					'category'      => $category,
					'value_label'   => $value_label,
					'value_number'  => $value_number,
				)
			);
		}

		if ( 'evidence' === $type ) {
			if ( empty( $data['title'] ) ) {
				return false;
			}
			return false !== $wpdb->insert( $tables['evidence'], array( 'result_id' => isset( $data['result_id'] ) ? absint( $data['result_id'] ) : 0, 'project_id' => isset( $data['project_id'] ) ? absint( $data['project_id'] ) : 0, 'evidence_type' => isset( $data['evidence_type'] ) ? $data['evidence_type'] : '', 'title' => $data['title'], 'source_url' => isset( $data['source_url'] ) ? esc_url_raw( $data['source_url'] ) : '', 'status' => self::sanitize_status( isset( $data['status'] ) ? $data['status'] : 'submitted' ), 'created_at' => current_time( 'mysql' ) ) );
		}

		if ( 'activities' === $type ) {
			$title = trim( (string) ( $data['activity_name'] ?? $data['title'] ?? '' ) );
			if ( '' === $title ) {
				return false;
			}

			$post_id = wp_insert_post(
				array(
					'post_type'    => 'gsf_activity',
					'post_status'  => 'publish',
					'post_title'   => $title,
					'post_content' => $data['notes'] ?? '',
				),
				true
			);

			if ( is_wp_error( $post_id ) || ! $post_id ) {
				return false;
			}

			$project_id = self::post_id_from_title( 'gsf_project', $data['project'] ?? '' );
			$meta       = array(
				'activity_type'     => $data['activity_type'] ?? '',
				'activity_date'     => $data['activity_date'] ?? '',
				'country'           => $data['country'] ?? '',
				'organization'      => self::post_id_from_title( 'gsf_organization', $data['organization'] ?? '' ),
				'project'           => $project_id,
				'indicator'         => self::indicator_id_from_code( $data['indicator_code'] ?? '' ),
				'delivery_mode'     => $data['delivery_mode'] ?? '',
				'participant_count' => $data['participant_count'] ?? '',
				'rating_score'      => $data['rating_score'] ?? '',
				'activity_status'   => self::sanitize_status( $data['activity_status'] ?? 'draft' ),
				'notes'             => $data['notes'] ?? '',
			);

			foreach ( $meta as $key => $value ) {
				update_post_meta( $post_id, $key, $value );
			}

			$wpdb->insert(
				$tables['activity_events'],
				array(
					'wp_post_id'     => $post_id,
					'project_id'     => $project_id,
					'title'          => $title,
					'activity_type'  => $meta['activity_type'],
					'country'        => $meta['country'],
					'start_date'     => $meta['activity_date'] ?: null,
					'end_date'       => $meta['activity_date'] ?: null,
					'status'         => $meta['activity_status'],
				)
			);

			return true;
		}

		if ( 'ecoequity_scores' === $type ) {
			$framework_id = self::framework_id_from_code( $data['index_id'] ?? $data['framework_code'] ?? $data['index_name'] ?? 'ecoequity' );
			if ( ! $framework_id ) {
				return false;
			}

			$organization_name = trim( (string) ( $data['organization_name'] ?? '' ) );
			$total_score       = $data['total_score'] ?? $data['overall_score'] ?? '';
			$assessment_stage  = self::normalize_ecoequity_stage( $data['assessment_stage'] ?? '' );
			$reporting_period  = $data['reporting_period'] ?? $data['reporting_period_code'] ?? '';

			if ( '' === trim( (string) $reporting_period ) && ! empty( $data['reporting_period_id'] ) ) {
				$reporting_period = self::reporting_period_code_from_id( absint( $data['reporting_period_id'] ) );
			}

			if ( '' === $organization_name || '' === trim( (string) $total_score ) ) {
				return false;
			}

			$post_id = wp_insert_post(
				array(
					'post_type'    => 'gsf_ecoequity',
					'post_status'  => 'publish',
					'post_title'   => $organization_name . ' - ' . ( $data['assessment_stage'] ?? __( 'Assessment', 'gsf-data-centre' ) ),
					'post_content' => $data['notes'] ?? '',
				),
				true
			);

			if ( is_wp_error( $post_id ) || ! $post_id ) {
				return false;
			}

			$meta = array(
				'score_upload_id'       => $data['score_upload_id'] ?? '',
				'index_id'              => $data['index_id'] ?? $data['index_name'] ?? $data['framework_code'] ?? '',
				'organization_id'       => $data['organization_id'] ?? '',
				'organization_name'     => $organization_name,
				'organization_type'     => $data['organization_type'] ?? '',
				'country'               => $data['country'] ?? '',
				'reporting_period'      => $reporting_period,
				'assessment_stage'      => $data['assessment_stage'] ?? '',
				'total_score'           => $total_score,
				'score_status'          => self::sanitize_status( $data['score_status'] ?? $data['status'] ?? 'draft' ),
				'evidence_title'        => $data['evidence_title'] ?? '',
				'notes'                 => $data['notes'] ?? '',
				'source_sheet'          => $data['source_sheet'] ?? '',
			);

			$score_stage = '' !== $assessment_stage ? $assessment_stage : 'baseline';
			$meta[ $score_stage . '_score' ] = $total_score;

			foreach ( $meta as $key => $value ) {
				update_post_meta( $post_id, $key, $value );
			}

			$wpdb->insert(
				$tables['index_scores'],
				array(
					'framework_id'        => $framework_id,
					'organization_id'     => self::post_id_from_title( 'gsf_organization', $organization_name ),
					'project_id'          => isset( $data['project_id'] ) ? absint( $data['project_id'] ) : 0,
					'reporting_period_id' => self::reporting_period_id_from_code( $reporting_period ),
					'overall_score'       => (float) $total_score,
					'status'              => $meta['score_status'],
					'created_at'          => current_time( 'mysql' ),
				)
			);

			return true;
		}

		return false;
	}

	private static function indicator_code_from_id( $indicator_id ) {
		$indicator_id = absint( $indicator_id );
		if ( ! $indicator_id ) {
			return '';
		}

		$post = get_post( $indicator_id );
		if ( $post && 'gsf_indicator' === $post->post_type ) {
			$code = self::first_meta( $indicator_id, array( 'indicator_code', 'code' ) );
			return $code ? $code : (string) $post->post_title;
		}

		global $wpdb;
		$tables = self::tables();
		return (string) $wpdb->get_var( $wpdb->prepare( "SELECT code FROM {$tables['indicators']} WHERE id = %d", $indicator_id ) );
	}

	private static function indicator_id_from_code( $code ) {
		global $wpdb;
		$tables = self::tables();
		$code   = trim( (string) $code );
		if ( '' === $code ) {
			return 0;
		}

		$post_ids = get_posts(
			array(
				'post_type'        => 'gsf_indicator',
				'post_status'      => 'any',
				'posts_per_page'   => 1,
				'fields'           => 'ids',
				'suppress_filters' => true,
				'meta_query'       => array(
					'relation' => 'OR',
					array(
						'key'     => 'indicator_code',
						'value'   => $code,
						'compare' => '=',
					),
					array(
						'key'     => 'code',
						'value'   => $code,
						'compare' => '=',
					),
				),
			)
		);

		if ( $post_ids ) {
			return (int) $post_ids[0];
		}

		$normalized_code = self::normalize_lookup_code( $code );
		$indicator_posts = get_posts(
			array(
				'post_type'        => 'gsf_indicator',
				'post_status'      => 'any',
				'posts_per_page'   => -1,
				'fields'           => 'ids',
				'suppress_filters' => true,
				'meta_query'       => array(
					'relation' => 'OR',
					array(
						'key'     => 'indicator_code',
						'compare' => 'EXISTS',
					),
					array(
						'key'     => 'code',
						'compare' => 'EXISTS',
					),
				),
			)
		);

		foreach ( $indicator_posts as $post_id ) {
			$stored_codes = array(
				get_post_meta( (int) $post_id, 'indicator_code', true ),
				get_post_meta( (int) $post_id, 'code', true ),
			);

			foreach ( $stored_codes as $stored_code ) {
				if ( $normalized_code && self::normalize_lookup_code( $stored_code ) === $normalized_code ) {
					return (int) $post_id;
				}
			}
		}

		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$tables['indicators']} WHERE code = %s", $code ) );
	}

	private static function normalize_lookup_code( $code ) {
		$code = strtoupper( trim( (string) $code ) );
		$code = preg_replace( '/^GSF[-_\s]*/', '', $code );
		$code = preg_replace( '/[^A-Z0-9]/', '', $code );

		return (string) $code;
	}

	private static function post_id_from_title( $post_type, $title ) {
		$title = trim( (string) $title );
		if ( '' === $title ) {
			return 0;
		}

		$posts = get_posts(
			array(
				'post_type'        => $post_type,
				'post_status'      => 'any',
				'posts_per_page'   => 1,
				'fields'           => 'ids',
				'title'            => $title,
				'suppress_filters' => true,
			)
		);

		return $posts ? (int) $posts[0] : 0;
	}

	private static function resolve_result_id_for_disaggregation( array $data ) {
		if ( ! empty( $data['result_id'] ) ) {
			return absint( $data['result_id'] );
		}

		global $wpdb;
		$tables       = self::tables();
		$indicator_id = self::indicator_id_from_code( $data['indicator_code'] ?? '' );
		if ( ! $indicator_id ) {
			return 0;
		}

		$result_id = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$tables['indicator_results']} WHERE indicator_id = %d ORDER BY updated_at DESC, created_at DESC LIMIT 1",
				$indicator_id
			)
		);

		if ( $result_id ) {
			return $result_id;
		}

		$results = get_posts(
			array(
				'post_type'        => 'gsf_ind_result',
				'post_status'      => 'any',
				'posts_per_page'   => 1,
				'fields'           => 'ids',
				'suppress_filters' => true,
				'meta_query'       => array(
					array(
						'key'     => 'indicator',
						'value'   => (string) $indicator_id,
						'compare' => '=',
					),
				),
			)
		);

		return $results ? (int) $results[0] : 0;
	}

	private static function disaggregation_breakdown_from_row( array $data ) {
		$breakdown_keys = array(
			'sex_gender',
			'indigenous_status',
			'age_group',
			'disability_status',
			'ethnicity',
			'language_group',
			'organization_type',
			'ecosystem_type',
			'sector',
			'project_type',
		);

		foreach ( $breakdown_keys as $key ) {
			if ( ! empty( $data[ $key ] ) ) {
				return array(
					'category'    => $key,
					'value_label' => self::disaggregation_value_label( $data, $key ),
				);
			}
		}

		foreach ( array( 'organization', 'project', 'country' ) as $key ) {
			if ( ! empty( $data[ $key ] ) ) {
				return array(
					'category'    => $key,
					'value_label' => (string) $data[ $key ],
				);
			}
		}

		return array(
			'category'    => 'total',
			'value_label' => __( 'Total', 'gsf-data-centre' ),
		);
	}

	private static function disaggregation_value_label( array $data, $primary_key ) {
		$label    = (string) $data[ $primary_key ];
		$contexts = array();

		foreach ( array( 'organization', 'project', 'country' ) as $context_key ) {
			if ( ! empty( $data[ $context_key ] ) ) {
				$contexts[] = (string) $data[ $context_key ];
			}
		}

		if ( ! empty( $contexts ) ) {
			$label .= ' (' . implode( ', ', $contexts ) . ')';
		}

		return $label;
	}

	private static function reporting_period_id_from_code( $code ) {
		global $wpdb;
		$tables = self::tables();
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$tables['reporting_periods']} WHERE code = %s", $code ) );
	}

	private static function reporting_period_code_from_id( $id ) {
		global $wpdb;
		$tables = self::tables();
		return (string) $wpdb->get_var( $wpdb->prepare( "SELECT code FROM {$tables['reporting_periods']} WHERE id = %d", $id ) );
	}

	private static function framework_id_from_code( $code ) {
		global $wpdb;
		$tables = self::tables();
		$code   = trim( (string) $code );
		$id     = (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$tables['index_frameworks']} WHERE code = %s", $code ) );

		if ( $id ) {
			return $id;
		}

		if ( '' !== $code && 0 === strpos( strtoupper( $code ), 'ECOEQ' ) ) {
			return (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$tables['index_frameworks']} WHERE code = %s", 'ecoequity' ) );
		}

		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$tables['index_frameworks']} WHERE code = %s", 'ecoequity' ) );
	}

	private static function send_csv( $filename, array $rows ) {
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . sanitize_file_name( $filename ) . '"' );
		$output = fopen( 'php://output', 'w' );
		foreach ( $rows as $row ) {
			fputcsv( $output, array_values( $row ) );
		}
		fclose( $output );
		exit;
	}

	private static function write_csv_templates() {
		$dir = plugin_dir_path( dirname( __FILE__ ) ) . 'templates/csv';
		if ( ! is_dir( $dir ) || ! wp_is_writable( $dir ) ) {
			return;
		}

		foreach ( array( 'projects', 'indicators', 'indicator_results', 'disaggregation', 'evidence', 'activities', 'ecoequity_scores' ) as $template ) {
			$path = self::template_path( $template );
			if ( $path ) {
				$header_line     = implode( ',', self::template_headers( $template ) );
				$current_content = file_exists( $path ) ? (string) file_get_contents( $path ) : ''; // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
				$current_header  = trim( (string) strtok( $current_content, "\r\n" ) );
				if ( $current_header !== $header_line ) {
					file_put_contents( $path, $header_line . "\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
				}
			}
		}
	}

	private static function template_path( $template ) {
		$allowed = array( 'projects', 'indicators', 'indicator_results', 'disaggregation', 'evidence', 'activities', 'ecoequity_scores' );
		if ( ! in_array( $template, $allowed, true ) ) {
			return '';
		}
		return plugin_dir_path( dirname( __FILE__ ) ) . 'templates/csv/' . $template . '.csv';
	}

	private static function template_headers( $template ) {
		$headers = array(
			'projects'          => array( 'project_id', 'project_title', 'project_slug', 'post_status', 'description', 'project_type', 'country', 'organizations', 'implementing_party', 'funding_source', 'milestone_output', 'project_status', 'percent_complete', 'gender_responsiveness', 'people_affected_total', 'people_affected_female', 'people_affected_male', 'people_affected_other', 'people_affected_non_indigenous_male', 'people_affected_non_indigenous_female', 'people_affected_indigenous_male', 'people_affected_indigenous_female', 'climate_hazard_score', 'habitat_quality_score', 'social_economic_resilience_score', 'durability_score' ),
			'indicators'        => array( 'indicator_code', 'pmf_reference', 'result_code', 'result_level', 'indicator_text', 'indicator_type', 'unit_of_measure', 'baseline_summary', 'target_summary', 'frequency', 'calculation_type', 'higher_is_better', 'data_source', 'method', 'disaggregation', 'definition_criteria', 'target_population', 'responsible_party', 'additional_remarks' ),
			'indicator_results' => array( 'indicator_code', 'reporting_year', 'quarter', 'country', 'organization', 'project', 'result_value', 'value_numeric', 'value_text', 'result_narrative', 'currency', 'reporting_status', 'evidence_comment', 'data_quality_note' ),
			'disaggregation'    => array( 'indicator_code', 'result_id', 'reporting_year', 'country', 'organization', 'project', 'category', 'value_label', 'value', 'notes' ),
			'evidence'          => array( 'indicator_code', 'result_id', 'organization', 'project', 'evidence_type', 'title', 'file_url', 'source_organization', 'evidence_date', 'visibility', 'notes' ),
			'activities'        => array( 'activity_name', 'activity_type', 'activity_date', 'country', 'organization', 'project', 'indicator_code', 'delivery_mode', 'participant_count', 'rating_score', 'activity_status', 'notes' ),
			'ecoequity_scores'  => array( 'score_upload_id', 'index_id', 'organization_id', 'organization_name', 'organization_type', 'country', 'reporting_period', 'assessment_stage', 'total_score', 'score_status', 'evidence_title', 'notes', 'source_sheet' ),
		);
		return isset( $headers[ $template ] ) ? $headers[ $template ] : array();
	}

	private static function sanitize_status( $status ) {
		$status  = sanitize_key( $status );
		$allowed = array( 'draft', 'submitted', 'reported', 'verified', 'pending', 'partial_data', 'no_data', 'rejected', 'returned', 'approved' );
		return in_array( $status, $allowed, true ) ? $status : 'draft';
	}

	private static function disaggregation_category_options() {
		return array(
			'total'              => __( 'Total', 'gsf-data-centre' ),
			'sex_gender'         => __( 'Sex / gender', 'gsf-data-centre' ),
			'indigenous_status'  => __( 'Indigenous status', 'gsf-data-centre' ),
			'age_group'          => __( 'Age group', 'gsf-data-centre' ),
			'disability_status'  => __( 'Disability status', 'gsf-data-centre' ),
			'ethnicity'          => __( 'Ethnicity', 'gsf-data-centre' ),
			'language_group'     => __( 'Language group', 'gsf-data-centre' ),
			'organization'       => __( 'Organization', 'gsf-data-centre' ),
			'organization_type'  => __( 'Organization type', 'gsf-data-centre' ),
			'project'            => __( 'Project', 'gsf-data-centre' ),
			'project_type'       => __( 'Project type', 'gsf-data-centre' ),
			'country'            => __( 'Country', 'gsf-data-centre' ),
			'ecosystem_type'     => __( 'Ecosystem type', 'gsf-data-centre' ),
			'sector'             => __( 'Sector', 'gsf-data-centre' ),
		);
	}

	private static function render_disaggregation_category_select( $name, $selected, $id = '' ) {
		$selected = sanitize_key( $selected );
		$id_attr  = $id ? ' id="' . esc_attr( $id ) . '"' : '';
		echo '<select' . $id_attr . ' name="' . esc_attr( $name ) . '">'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		foreach ( self::disaggregation_category_options() as $value => $label ) {
			echo '<option value="' . esc_attr( $value ) . '"' . selected( $selected, $value, false ) . '>' . esc_html( $label ) . '</option>';
		}
		if ( '' !== $selected && ! isset( self::disaggregation_category_options()[ $selected ] ) ) {
			echo '<option value="' . esc_attr( $selected ) . '" selected>' . esc_html( $selected ) . '</option>';
		}
		echo '</select>';
	}

	private static function sanitize_disaggregation_row( array $row ) {
		$value_number = isset( $row['value_number'] ) ? (float) $row['value_number'] : 0;

		return array(
			'result_id'    => isset( $row['result_id'] ) ? absint( $row['result_id'] ) : 0,
			'category'     => isset( $row['category'] ) ? sanitize_key( $row['category'] ) : '',
			'value_label'  => isset( $row['value_label'] ) ? sanitize_text_field( $row['value_label'] ) : '',
			'value_number' => max( 0, $value_number ),
		);
	}

	private static function render_disaggregation_pagination( $current_page, $total_pages ) {
		if ( $total_pages <= 1 ) {
			return '';
		}

		ob_start();
		?>
		<div class="tablenav bottom">
			<div class="tablenav-pages">
				<span class="displaying-num">
					<?php
					echo esc_html(
						sprintf(
							/* translators: 1: current page, 2: total pages. */
							__( 'Page %1$d of %2$d', 'gsf-data-centre' ),
							$current_page,
							$total_pages
						)
					);
					?>
				</span>
				<?php if ( $current_page > 1 ) : ?>
					<a class="button" href="<?php echo esc_url( add_query_arg( array( 'page' => 'gsf-data-centre-disaggregation', 'gsf_disaggregation_page' => $current_page - 1 ), admin_url( 'admin.php' ) ) ); ?>"><?php esc_html_e( 'Previous', 'gsf-data-centre' ); ?></a>
				<?php endif; ?>
				<?php if ( $current_page < $total_pages ) : ?>
					<a class="button" href="<?php echo esc_url( add_query_arg( array( 'page' => 'gsf-data-centre-disaggregation', 'gsf_disaggregation_page' => $current_page + 1 ), admin_url( 'admin.php' ) ) ); ?>"><?php esc_html_e( 'Next', 'gsf-data-centre' ); ?></a>
				<?php endif; ?>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	private static function require_capability( $capability ) {
		if ( ! current_user_can( $capability ) && ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'gsf-data-centre' ) );
		}
	}

	private static function admin_action_url( $action, array $args ) {
		$args['action'] = $action;
		$url            = add_query_arg( $args, admin_url( 'admin-post.php' ) );
		return wp_nonce_url( $url, $action );
	}
}
