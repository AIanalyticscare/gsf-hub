<?php
/**
 * Plugin Name: GSF Hub Transfer
 * Description: Exports and imports GSF Hub content between WordPress installs without a full database restore.
 * Version: 0.1.0
 * Author: AI Analyticscare
 * Text Domain: gsf-hub-transfer
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class GSF_Hub_Transfer {
	const SLUG    = 'gsf-hub-transfer';
	const VERSION = '0.1.0';

	private static $post_types = array(
		'page',
		'attachment',
		'nav_menu_item',
		'gsf_course',
		'gsf_resource',
		'gsf_case_study',
		'gsf_data_story',
		'expert_directory',
		'zoom-meetings',
		'forum',
		'topic',
		'reply',
		'um_form',
	);

	private static $option_names = array(
		'blogname',
		'blogdescription',
		'show_on_front',
		'page_on_front',
		'page_for_posts',
		'permalink_structure',
		'stylesheet',
		'template',
		'gsf_data_centre_display_settings',
		'gsf_scorm_lite_certificate_settings',
	);

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'register_admin_page' ) );
		add_action( 'admin_post_gsf_hub_transfer_export', array( __CLASS__, 'handle_export_download' ) );
		add_action( 'admin_post_gsf_hub_transfer_import', array( __CLASS__, 'handle_import_upload' ) );

		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			WP_CLI::add_command( 'gsf-transfer', 'GSF_Hub_Transfer_CLI' );
		}
	}

	public static function register_admin_page() {
		add_management_page(
			__( 'GSF Hub Transfer', 'gsf-hub-transfer' ),
			__( 'GSF Hub Transfer', 'gsf-hub-transfer' ),
			'manage_options',
			self::SLUG,
			array( __CLASS__, 'render_admin_page' )
		);
	}

	public static function render_admin_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'gsf-hub-transfer' ) );
		}

		$import_notice = get_transient( 'gsf_hub_transfer_import_notice' );
		delete_transient( 'gsf_hub_transfer_import_notice' );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'GSF Hub Transfer', 'gsf-hub-transfer' ); ?></h1>

			<?php if ( $import_notice ) : ?>
				<div class="notice notice-success is-dismissible">
					<p><?php echo esc_html( $import_notice ); ?></p>
				</div>
			<?php endif; ?>

			<p><?php esc_html_e( 'Use this tool to move GSF Hub content into a fresh WordPress install. Copy wp-content/uploads separately before or after importing.', 'gsf-hub-transfer' ); ?></p>

			<h2><?php esc_html_e( 'Export from this site', 'gsf-hub-transfer' ); ?></h2>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="gsf_hub_transfer_export" />
				<?php wp_nonce_field( 'gsf_hub_transfer_export' ); ?>
				<?php submit_button( __( 'Download GSF transfer package', 'gsf-hub-transfer' ) ); ?>
			</form>

			<hr />

			<h2><?php esc_html_e( 'Import into this site', 'gsf-hub-transfer' ); ?></h2>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data">
				<input type="hidden" name="action" value="gsf_hub_transfer_import" />
				<?php wp_nonce_field( 'gsf_hub_transfer_import' ); ?>
				<input type="file" name="gsf_transfer_file" accept="application/json,.json" required />
				<?php submit_button( __( 'Import GSF transfer package', 'gsf-hub-transfer' ) ); ?>
			</form>
		</div>
		<?php
	}

	public static function handle_export_download() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to export.', 'gsf-hub-transfer' ) );
		}

		check_admin_referer( 'gsf_hub_transfer_export' );

		$package = self::build_export_package();
		$json    = wp_json_encode( $package, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );

		if ( false === $json ) {
			wp_die( esc_html__( 'Unable to encode export package.', 'gsf-hub-transfer' ) );
		}

		nocache_headers();
		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="gsf-hub-transfer-' . gmdate( 'Ymd-His' ) . '.json"' );
		echo $json; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		exit;
	}

	public static function handle_import_upload() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to import.', 'gsf-hub-transfer' ) );
		}

		check_admin_referer( 'gsf_hub_transfer_import' );

		if ( empty( $_FILES['gsf_transfer_file']['tmp_name'] ) ) {
			wp_die( esc_html__( 'No import file was uploaded.', 'gsf-hub-transfer' ) );
		}

		$raw = file_get_contents( sanitize_text_field( wp_unslash( $_FILES['gsf_transfer_file']['tmp_name'] ) ) );
		if ( false === $raw ) {
			wp_die( esc_html__( 'Unable to read import file.', 'gsf-hub-transfer' ) );
		}

		$package = json_decode( $raw, true );
		if ( ! is_array( $package ) ) {
			wp_die( esc_html__( 'The import file is not valid JSON.', 'gsf-hub-transfer' ) );
		}

		$result = self::import_package( $package );
		set_transient( 'gsf_hub_transfer_import_notice', self::format_import_result( $result ), 60 );
		wp_safe_redirect( admin_url( 'tools.php?page=' . self::SLUG ) );
		exit;
	}

	public static function build_export_package() {
		return array(
			'format'     => 'gsf-hub-transfer',
			'version'    => self::VERSION,
			'created_at' => gmdate( 'c' ),
			'site_url'   => home_url(),
			'options'    => self::export_options(),
			'theme_mods' => self::export_theme_mods(),
			'terms'      => self::export_terms(),
			'posts'      => self::export_posts(),
		);
	}

	private static function export_options() {
		$options = array();

		foreach ( self::$option_names as $option_name ) {
			$options[ $option_name ] = get_option( $option_name );
		}

		foreach ( array( 'gsf-hub', 'gsf-hub-sunrise' ) as $theme_slug ) {
			$options[ 'theme_mods_' . $theme_slug ] = get_option( 'theme_mods_' . $theme_slug );
		}

		return $options;
	}

	private static function export_theme_mods() {
		return array(
			'active_stylesheet' => get_stylesheet(),
			'mods'             => get_theme_mods(),
		);
	}

	private static function export_terms() {
		$terms = get_terms(
			array(
				'taxonomy'   => get_taxonomies( array(), 'names' ),
				'hide_empty' => false,
			)
		);

		if ( is_wp_error( $terms ) ) {
			return array();
		}

		$exported = array();
		foreach ( $terms as $term ) {
			$exported[] = array(
				'term_id'     => (int) $term->term_id,
				'taxonomy'    => $term->taxonomy,
				'name'        => $term->name,
				'slug'        => $term->slug,
				'description' => $term->description,
				'parent'      => (int) $term->parent,
				'meta'        => get_term_meta( $term->term_id ),
			);
		}

		return $exported;
	}

	private static function export_posts() {
		$post_types = array_values( array_filter( self::$post_types, 'post_type_exists' ) );

		$posts = get_posts(
			array(
				'post_type'      => $post_types,
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'orderby'        => 'ID',
				'order'          => 'ASC',
			)
		);

		$exported = array();
		foreach ( $posts as $post ) {
			$exported[] = self::export_post( $post );
		}

		return $exported;
	}

	private static function export_post( WP_Post $post ) {
		$taxonomies = get_object_taxonomies( $post->post_type, 'names' );
		$terms      = array();

		foreach ( $taxonomies as $taxonomy ) {
			$post_terms = wp_get_object_terms( $post->ID, $taxonomy );
			if ( is_wp_error( $post_terms ) ) {
				continue;
			}

			foreach ( $post_terms as $term ) {
				$terms[] = array(
					'taxonomy' => $taxonomy,
					'slug'     => $term->slug,
				);
			}
		}

		return array(
			'ID'                    => (int) $post->ID,
			'post_author'           => (int) $post->post_author,
			'post_date'             => $post->post_date,
			'post_date_gmt'         => $post->post_date_gmt,
			'post_content'          => $post->post_content,
			'post_title'            => $post->post_title,
			'post_excerpt'          => $post->post_excerpt,
			'post_status'           => $post->post_status,
			'comment_status'        => $post->comment_status,
			'ping_status'           => $post->ping_status,
			'post_password'         => $post->post_password,
			'post_name'             => $post->post_name,
			'to_ping'               => $post->to_ping,
			'pinged'                => $post->pinged,
			'post_modified'         => $post->post_modified,
			'post_modified_gmt'     => $post->post_modified_gmt,
			'post_content_filtered' => $post->post_content_filtered,
			'post_parent'           => (int) $post->post_parent,
			'guid'                  => $post->guid,
			'menu_order'            => (int) $post->menu_order,
			'post_type'             => $post->post_type,
			'post_mime_type'        => $post->post_mime_type,
			'comment_count'         => (int) $post->comment_count,
			'meta'                  => get_post_meta( $post->ID ),
			'terms'                 => $terms,
		);
	}

	public static function import_package( array $package ) {
		if ( empty( $package['format'] ) || 'gsf-hub-transfer' !== $package['format'] ) {
			return new WP_Error( 'invalid_package', __( 'This is not a GSF Hub transfer package.', 'gsf-hub-transfer' ) );
		}

		wp_suspend_cache_invalidation( true );

		$map = array(
			'terms' => array(),
			'posts' => array(),
		);

		$result = array(
			'terms'   => 0,
			'posts'   => 0,
			'options' => 0,
			'errors'  => array(),
		);

		self::import_terms( $package['terms'] ?? array(), $map, $result );
		self::import_posts( $package['posts'] ?? array(), $map, $result );
		self::import_post_relationships( $package['posts'] ?? array(), $map );
		self::import_options( $package['options'] ?? array(), $map, $result );

		flush_rewrite_rules();
		wp_suspend_cache_invalidation( false );

		return $result;
	}

	private static function import_terms( array $terms, array &$map, array &$result ) {
		foreach ( $terms as $term ) {
			if ( empty( $term['taxonomy'] ) || ! taxonomy_exists( $term['taxonomy'] ) || empty( $term['slug'] ) ) {
				continue;
			}

			$existing = get_term_by( 'slug', $term['slug'], $term['taxonomy'] );
			if ( $existing ) {
				$new_term_id = (int) $existing->term_id;
			} else {
				$created = wp_insert_term(
					$term['name'],
					$term['taxonomy'],
					array(
						'slug'        => $term['slug'],
						'description' => $term['description'] ?? '',
					)
				);

				if ( is_wp_error( $created ) ) {
					$result['errors'][] = $created->get_error_message();
					continue;
				}

				$new_term_id = (int) $created['term_id'];
				$result['terms']++;
			}

			$map['terms'][ (int) $term['term_id'] ] = $new_term_id;
			if ( ! empty( $term['meta'] ) && is_array( $term['meta'] ) ) {
				self::replace_all_meta( 'term', $new_term_id, $term['meta'] );
			}
		}
	}

	private static function import_posts( array $posts, array &$map, array &$result ) {
		$ordered = array_merge(
			array_filter( $posts, static function ( $post ) {
				return 'attachment' === ( $post['post_type'] ?? '' );
			} ),
			array_filter( $posts, static function ( $post ) {
				return 'attachment' !== ( $post['post_type'] ?? '' );
			} )
		);

		foreach ( $ordered as $post ) {
			if ( empty( $post['post_type'] ) || ! post_type_exists( $post['post_type'] ) ) {
				continue;
			}

			$postarr = self::prepare_post_array( $post, $map );
			$found   = self::find_existing_post( $post );

			if ( $found ) {
				$postarr['ID'] = $found;
				$new_post_id   = wp_update_post( wp_slash( $postarr ), true );
			} else {
				$new_post_id = wp_insert_post( wp_slash( $postarr ), true );
			}

			if ( is_wp_error( $new_post_id ) ) {
				$result['errors'][] = $new_post_id->get_error_message();
				continue;
			}

			$map['posts'][ (int) $post['ID'] ] = (int) $new_post_id;
			self::replace_all_meta( 'post', (int) $new_post_id, $post['meta'] ?? array(), $map );
			$result['posts']++;
		}
	}

	private static function prepare_post_array( array $post, array $map ) {
		$keys    = array(
			'post_author',
			'post_date',
			'post_date_gmt',
			'post_content',
			'post_title',
			'post_excerpt',
			'post_status',
			'comment_status',
			'ping_status',
			'post_password',
			'post_name',
			'to_ping',
			'pinged',
			'post_modified',
			'post_modified_gmt',
			'post_content_filtered',
			'post_parent',
			'guid',
			'menu_order',
			'post_type',
			'post_mime_type',
		);
		$postarr = array();

		foreach ( $keys as $key ) {
			if ( array_key_exists( $key, $post ) ) {
				$postarr[ $key ] = $post[ $key ];
			}
		}

		$old_parent = (int) ( $post['post_parent'] ?? 0 );
		if ( $old_parent && ! empty( $map['posts'][ $old_parent ] ) ) {
			$postarr['post_parent'] = $map['posts'][ $old_parent ];
		} else {
			$postarr['post_parent'] = 0;
		}

		$postarr['post_author'] = get_current_user_id() ?: 1;
		return $postarr;
	}

	private static function find_existing_post( array $post ) {
		if ( empty( $post['post_type'] ) || empty( $post['post_name'] ) ) {
			return 0;
		}

		if ( 'attachment' === $post['post_type'] && ! empty( $post['meta']['_wp_attached_file'][0] ) ) {
			$attachments = get_posts(
				array(
					'post_type'      => 'attachment',
					'post_status'    => 'any',
					'posts_per_page' => 1,
					'meta_key'       => '_wp_attached_file',
					'meta_value'     => $post['meta']['_wp_attached_file'][0],
					'fields'         => 'ids',
				)
			);

			return $attachments ? (int) $attachments[0] : 0;
		}

		$existing = get_page_by_path( $post['post_name'], OBJECT, $post['post_type'] );
		return $existing ? (int) $existing->ID : 0;
	}

	private static function import_post_relationships( array $posts, array $map ) {
		foreach ( $posts as $post ) {
			$old_post_id = (int) ( $post['ID'] ?? 0 );
			$new_post_id = $map['posts'][ $old_post_id ] ?? 0;
			if ( ! $new_post_id ) {
				continue;
			}

			if ( ! empty( $post['post_parent'] ) && ! empty( $map['posts'][ (int) $post['post_parent'] ] ) ) {
				wp_update_post(
					array(
						'ID'          => $new_post_id,
						'post_parent' => $map['posts'][ (int) $post['post_parent'] ],
					)
				);
			}

			$terms_by_taxonomy = array();
			foreach ( $post['terms'] ?? array() as $term ) {
				if ( empty( $term['taxonomy'] ) || empty( $term['slug'] ) || ! taxonomy_exists( $term['taxonomy'] ) ) {
					continue;
				}

				$existing = get_term_by( 'slug', $term['slug'], $term['taxonomy'] );
				if ( $existing ) {
					$terms_by_taxonomy[ $term['taxonomy'] ][] = (int) $existing->term_id;
				}
			}

			foreach ( $terms_by_taxonomy as $taxonomy => $term_ids ) {
				wp_set_object_terms( $new_post_id, $term_ids, $taxonomy, false );
			}
		}
	}

	private static function import_options( array $options, array $map, array &$result ) {
		foreach ( self::$option_names as $option_name ) {
			if ( ! array_key_exists( $option_name, $options ) ) {
				continue;
			}

			$value = self::remap_option_value( $option_name, $options[ $option_name ], $map );
			update_option( $option_name, $value );
			$result['options']++;
		}

		foreach ( $options as $option_name => $value ) {
			if ( 0 !== strpos( $option_name, 'theme_mods_' ) ) {
				continue;
			}

			update_option( $option_name, self::remap_value( $value, $map ) );
			$result['options']++;
		}
	}

	private static function remap_option_value( $option_name, $value, array $map ) {
		if ( in_array( $option_name, array( 'page_on_front', 'page_for_posts' ), true ) ) {
			$old_id = (int) $value;
			return $old_id && ! empty( $map['posts'][ $old_id ] ) ? (int) $map['posts'][ $old_id ] : $old_id;
		}

		return self::remap_value( $value, $map );
	}

	private static function replace_all_meta( $object_type, $object_id, array $meta, array $map = array() ) {
		foreach ( $meta as $meta_key => $values ) {
			if ( 0 === strpos( $meta_key, '_' ) && ! in_array( $meta_key, array( '_thumbnail_id', '_wp_attached_file', '_wp_attachment_metadata', '_menu_item_object_id', '_menu_item_menu_item_parent', '_menu_item_object', '_menu_item_type', '_menu_item_url' ), true ) ) {
				// Keep protected plugin meta too; this branch is deliberately empty.
			}

			if ( 'post' === $object_type ) {
				delete_post_meta( $object_id, $meta_key );
			} else {
				delete_term_meta( $object_id, $meta_key );
			}

			foreach ( (array) $values as $value ) {
				$value = maybe_unserialize( $value );
				$value = self::remap_value( $value, $map, $meta_key );

				if ( 'post' === $object_type ) {
					add_post_meta( $object_id, $meta_key, $value );
				} else {
					add_term_meta( $object_id, $meta_key, $value );
				}
			}
		}
	}

	private static function remap_value( $value, array $map, $meta_key = '' ) {
		if ( is_array( $value ) ) {
			foreach ( $value as $key => $item ) {
				$value[ $key ] = self::remap_value( $item, $map, $meta_key );
			}
			return $value;
		}

		if ( is_object( $value ) ) {
			foreach ( get_object_vars( $value ) as $key => $item ) {
				$value->$key = self::remap_value( $item, $map, $meta_key );
			}
			return $value;
		}

		if ( is_numeric( $value ) && in_array( $meta_key, array( '_thumbnail_id', '_menu_item_object_id', '_menu_item_menu_item_parent', 'resource_file', 'case_study_files' ), true ) ) {
			$old_id = (int) $value;
			return $map['posts'][ $old_id ] ?? $old_id;
		}

		return $value;
	}

	private static function format_import_result( $result ) {
		if ( is_wp_error( $result ) ) {
			return $result->get_error_message();
		}

		return sprintf(
			/* translators: 1: posts, 2: terms, 3: options */
			__( 'Import complete. Posts processed: %1$d. Terms created: %2$d. Options updated: %3$d.', 'gsf-hub-transfer' ),
			(int) $result['posts'],
			(int) $result['terms'],
			(int) $result['options']
		);
	}
}

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	class GSF_Hub_Transfer_CLI {
		/**
		 * Export a GSF Hub transfer package.
		 *
		 * ## OPTIONS
		 *
		 * <file>
		 * : Destination JSON file.
		 */
		public function export( $args ) {
			$file    = $args[0];
			$package = GSF_Hub_Transfer::build_export_package();
			$json    = wp_json_encode( $package, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );

			if ( false === $json || false === file_put_contents( $file, $json ) ) {
				WP_CLI::error( 'Unable to write transfer package.' );
			}

			WP_CLI::success( 'Transfer package written to ' . $file );
		}

		/**
		 * Import a GSF Hub transfer package.
		 *
		 * ## OPTIONS
		 *
		 * <file>
		 * : Source JSON file.
		 */
		public function import( $args ) {
			$file = $args[0];
			if ( ! file_exists( $file ) ) {
				WP_CLI::error( 'Import file does not exist.' );
			}

			$package = json_decode( file_get_contents( $file ), true );
			if ( ! is_array( $package ) ) {
				WP_CLI::error( 'Import file is not valid JSON.' );
			}

			$result = GSF_Hub_Transfer::import_package( $package );
			if ( is_wp_error( $result ) ) {
				WP_CLI::error( $result->get_error_message() );
			}

			WP_CLI::success( 'Import complete. Posts processed: ' . (int) $result['posts'] );
		}
	}
}

GSF_Hub_Transfer::init();
