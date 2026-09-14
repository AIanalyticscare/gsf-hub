<?php
/**
 * Plugin Name: GSF Hub Expert Workflow
 * Description: Adds expert application, review, and approval workflow for the GSF Hub directory.
 * Version: 1.2.0
 * Author: OpenAI
 * Text Domain: gsf-hub-expert-workflow
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'GSF_HUB_EXPERT_WORKFLOW_VERSION', '1.2.0' );
define( 'GSF_HUB_EXPERT_WORKFLOW_OPTION_VERSION', 'gsf_hub_expert_workflow_version' );
define( 'GSF_HUB_EXPERT_WORKFLOW_OPTION_PAGE_IDS', 'gsf_hub_expert_application_page_ids' );
define( 'GSF_HUB_EXPERT_WORKFLOW_OPTION_PORTAL_PAGE_IDS', 'gsf_hub_expert_portal_page_ids' );
define( 'GSF_HUB_EXPERT_WORKFLOW_OPTION_PENDING_ROLE_KEY', 'expert_pending' );
define( 'GSF_HUB_EXPERT_WORKFLOW_OPTION_APPROVED_ROLE_KEY', 'expert_approved' );
define( 'GSF_HUB_EXPERT_WORKFLOW_PENDING_ROLE', 'um_expert_pending' );
define( 'GSF_HUB_EXPERT_WORKFLOW_APPROVED_ROLE', 'um_expert_approved' );
define( 'GSF_HUB_EXPERT_WORKFLOW_USER_FLAG', 'gsf_hub_expert_application' );
define( 'GSF_HUB_EXPERT_WORKFLOW_USER_STATUS', 'gsf_hub_expert_application_status' );
define( 'GSF_HUB_EXPERT_WORKFLOW_USER_SUBMITTED_AT', 'gsf_hub_expert_application_submitted_at' );
define( 'GSF_HUB_EXPERT_WORKFLOW_USER_PROFILE_POST', 'gsf_hub_expert_profile_post_id' );
define( 'GSF_HUB_EXPERT_WORKFLOW_USER_REVIEW_NOTES', 'gsf_hub_expert_review_notes' );
define( 'GSF_HUB_EXPERT_WORKFLOW_USER_REVIEWED_AT', 'gsf_hub_expert_reviewed_at' );
define( 'GSF_HUB_EXPERT_WORKFLOW_USER_REVIEWED_BY', 'gsf_hub_expert_reviewed_by' );
define( 'GSF_HUB_EXPERT_WORKFLOW_STATUS_PENDING', 'pending' );
define( 'GSF_HUB_EXPERT_WORKFLOW_STATUS_APPROVED', 'approved' );
define( 'GSF_HUB_EXPERT_WORKFLOW_STATUS_CHANGES_REQUESTED', 'changes_requested' );
define( 'GSF_HUB_EXPERT_WORKFLOW_STATUS_REJECTED', 'rejected' );
define( 'GSF_HUB_EXPERT_WORKFLOW_FORM_KEY', 'gsf_hub_expert_application' );

/**
 * Returns whether the required plugins are available.
 *
 * @return bool
 */
function gsf_hub_expert_workflow_is_ready() {
	return function_exists( 'UM' ) && function_exists( 'pods_api' ) && post_type_exists( 'expert_directory' );
}

/**
 * Returns fallback metadata for a UM-style member role.
 *
 * @return array<string, mixed>
 */
function gsf_hub_expert_workflow_get_base_role_meta() {
	if ( function_exists( 'UM' ) && isset( UM()->config()->default_roles_metadata['subscriber'] ) ) {
		return UM()->config()->default_roles_metadata['subscriber'];
	}

	return array(
		'_um_can_access_wpadmin'         => 0,
		'_um_can_not_see_adminbar'       => 1,
		'_um_can_edit_everyone'          => 0,
		'_um_can_delete_everyone'        => 0,
		'_um_can_edit_profile'           => 1,
		'_um_can_delete_profile'         => 1,
		'_um_after_login'                => 'redirect_profile',
		'_um_after_logout'               => 'redirect_home',
		'_um_default_homepage'           => 1,
		'_um_can_view_all'               => 1,
		'_um_can_make_private_profile'   => 0,
		'_um_can_access_private_profile' => 0,
		'_um_status'                     => 'approved',
		'_um_auto_approve_act'           => 'redirect_profile',
	);
}

/**
 * Ensures a UM role exists with the desired metadata.
 *
 * @param string               $role_key  Role key without `um_` prefix.
 * @param string               $label     Display label.
 * @param string               $status    UM registration status.
 * @param array<string, mixed> $overrides Override metadata.
 */
function gsf_hub_expert_workflow_ensure_um_role( $role_key, $label, $status, $overrides = array() ) {
	$role_keys = get_option( 'um_roles', array() );
	$role_keys = is_array( $role_keys ) ? $role_keys : array();
	$base_meta = gsf_hub_expert_workflow_get_base_role_meta();
	$role_meta = array_merge(
		$base_meta,
		array(
			'name'            => $label,
			'wp_capabilities' => array( 'read' => true ),
			'_um_status'      => $status,
		),
		$overrides
	);

	if ( ! in_array( $role_key, $role_keys, true ) ) {
		$role_keys[] = $role_key;
		update_option( 'um_roles', array_values( array_unique( $role_keys ) ) );
	}

	update_option( 'um_role_' . $role_key . '_meta', $role_meta );
}

/**
 * Ensures expert-specific UM roles exist.
 */
function gsf_hub_expert_workflow_ensure_roles() {
	gsf_hub_expert_workflow_ensure_um_role(
		GSF_HUB_EXPERT_WORKFLOW_OPTION_PENDING_ROLE_KEY,
		__( 'Expert Applicant', 'gsf-hub-expert-workflow' ),
		'approved',
		array(
			'_um_after_login'      => 'redirect_url',
			'_um_auto_approve_act' => 'redirect_url',
			'_um_auto_approve_url' => home_url( '/' ),
		)
	);

	gsf_hub_expert_workflow_ensure_um_role(
		GSF_HUB_EXPERT_WORKFLOW_OPTION_APPROVED_ROLE_KEY,
		__( 'Expert Roster Member', 'gsf-hub-expert-workflow' ),
		'approved',
		array(
			'_um_after_login'      => 'redirect_url',
			'_um_auto_approve_act' => 'redirect_url',
			'_um_auto_approve_url' => home_url( '/' ),
		)
	);

	if ( function_exists( 'UM' ) && method_exists( UM()->roles(), 'um_roles_init' ) ) {
		UM()->roles()->um_roles_init( wp_roles() );
	}
}

/**
 * Returns the expert-directory pod definition.
 *
 * @return array<string, mixed>|false
 */
function gsf_hub_expert_workflow_get_expert_pod_definition() {
	if ( ! function_exists( 'pods_api' ) ) {
		return false;
	}

	return pods_api()->load_pod(
		array(
			'name' => 'expert_directory',
		)
	);
}

/**
 * Returns the field definitions used by the Pods application form.
 *
 * @return array<string, array<string, mixed>>
 */
function gsf_hub_expert_workflow_get_pod_field_definitions() {
	return array(
		'full_name' => array(
			'label'                         => __( 'Full Name', 'gsf-hub-expert-workflow' ),
			'type'                          => 'text',
			'required'                      => '1',
			'text_max_length'               => '255',
			'text_trim'                     => '1',
			'text_trim_lines'               => '0',
			'text_trim_p_brs'               => '0',
			'text_trim_extra_lines'         => '0',
			'text_allow_html'               => '0',
			'text_sanitize_html'            => '1',
			'text_allow_shortcode'          => '0',
		),
		'upload_photo' => array(
			'label'                         => __( 'Profile Photo', 'gsf-hub-expert-workflow' ),
			'type'                          => 'file',
			'file_format_type'              => 'single',
			'file_uploader'                 => 'attachment',
			'file_type'                     => 'images',
			'file_attachment_tab'           => 'upload',
			'file_attachment_current_post_only' => '0',
			'file_upload_dir'               => 'wp',
			'file_edit_title'               => '1',
			'file_show_edit_link'           => '0',
			'file_linked'                   => '0',
			'file_limit'                    => '0',
			'file_field_template'           => 'rows',
			'file_add_button'               => __( 'Add Photo', 'gsf-hub-expert-workflow' ),
			'file_modal_title'              => __( 'Attach a photo', 'gsf-hub-expert-workflow' ),
			'file_modal_add_button'         => __( 'Add Photo', 'gsf-hub-expert-workflow' ),
			'file_wp_gallery_link'          => 'file',
			'file_wp_gallery_columns'       => '3',
			'file_wp_gallery_size'          => 'thumbnail',
			'file_auto_set_featured_image'  => '0',
		),
		'organization' => array(
			'label'                         => __( 'Organization', 'gsf-hub-expert-workflow' ),
			'type'                          => 'text',
			'required'                      => '1',
			'text_max_length'               => '255',
			'text_trim'                     => '1',
			'text_trim_lines'               => '0',
			'text_trim_p_brs'               => '0',
			'text_trim_extra_lines'         => '0',
			'text_allow_html'               => '0',
			'text_sanitize_html'            => '1',
			'text_allow_shortcode'          => '0',
		),
		'job_title' => array(
			'label'                         => __( 'Job Title', 'gsf-hub-expert-workflow' ),
			'type'                          => 'text',
			'required'                      => '0',
			'text_max_length'               => '255',
			'text_trim'                     => '1',
			'text_trim_lines'               => '0',
			'text_trim_p_brs'               => '0',
			'text_trim_extra_lines'         => '0',
			'text_allow_html'               => '0',
			'text_sanitize_html'            => '1',
			'text_allow_shortcode'          => '0',
		),
		'country' => array(
			'label'                         => __( 'Country or Territory', 'gsf-hub-expert-workflow' ),
			'type'                          => 'pick',
			'pick_object'                   => 'taxonomy',
			'pick_val'                      => 'country',
			'pick_format_type'              => 'single',
			'pick_format_single'            => 'dropdown',
			'pick_format_multi'             => 'list',
			'pick_display_format_multi'     => 'default',
			'pick_display_format_separator' => ', ',
			'pick_allow_add_new'            => '1',
			'pick_taggable'                 => '0',
			'pick_show_icon'                => '1',
			'pick_show_edit_link'           => '1',
			'pick_show_view_link'           => '1',
			'pick_limit'                    => '0',
			'pick_post_status'              => 'publish',
			'pick_post_author'              => '0',
			'pick_sync_taxonomy'            => '0',
			'required'                      => '1',
		),
		'expertise_summary' => array(
			'label'                         => __( 'Expertise Areas', 'gsf-hub-expert-workflow' ),
			'type'                          => 'paragraph',
			'required'                      => '1',
		),
		'short_bio' => array(
			'label'                         => __( 'Short Bio', 'gsf-hub-expert-workflow' ),
			'type'                          => 'paragraph',
			'required'                      => '1',
		),
		'website' => array(
			'label'                         => __( 'Website URL', 'gsf-hub-expert-workflow' ),
			'type'                          => 'website',
			'required'                      => '0',
		),
		'linkedin' => array(
			'label'                         => __( 'LinkedIn URL', 'gsf-hub-expert-workflow' ),
			'type'                          => 'website',
			'required'                      => '0',
		),
	);
}

/**
 * Ensures the expert-directory pod exposes the fields needed by the application form.
 */
function gsf_hub_expert_workflow_ensure_pod_fields() {
	$pod = gsf_hub_expert_workflow_get_expert_pod_definition();
	if ( ! is_array( $pod ) || empty( $pod['id'] ) ) {
		return;
	}

	$api      = pods_api();
	$group_id = 0;

	if ( ! empty( $pod['groups'] ) && is_array( $pod['groups'] ) ) {
		$first_group = reset( $pod['groups'] );
		$group_id    = isset( $first_group['id'] ) ? (int) $first_group['id'] : 0;
	}

	$existing_fields = isset( $pod['fields'] ) && is_array( $pod['fields'] ) ? $pod['fields'] : array();
	$weight          = 0;

	foreach ( gsf_hub_expert_workflow_get_pod_field_definitions() as $name => $field ) {
		$params = array_merge(
			array(
				'pod_id'                  => (int) $pod['id'],
				'pod'                     => 'expert_directory',
				'group_id'                => $group_id,
				'name'                    => $name,
				'weight'                  => $weight,
				'roles_allowed'           => '',
				'logged_in_only'          => '0',
				'admin_only'              => '0',
				'restrict_role'           => '0',
				'restrict_capability'     => '0',
				'hidden'                  => '0',
				'read_only'               => '0',
				'read_only_restricted'    => '0',
				'repeatable'              => '0',
				'repeatable_format'       => 'default',
				'default_evaluate_tags'   => '0',
				'default_empty_fields'    => '0',
				'revisions_revision_field' => '0',
				'enable_conditional_logic' => '0',
				'conditional_logic_save_value' => '0',
				'rest_pick_response'      => 'array',
				'rest_pick_depth'         => '1',
				'required_help_boolean'   => '0',
			),
			$field
		);

		if ( isset( $existing_fields[ $name ]['id'] ) ) {
			$params['id'] = (int) $existing_fields[ $name ]['id'];
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

/**
 * Returns multilingual page definitions for the expert application page.
 *
 * @return array<string, array<string, string>>
 */
function gsf_hub_expert_workflow_get_application_page_definitions() {
	$shortcode = '[gsf_hub_expert_application]';

	return array(
		'en' => array(
			'title'   => __( 'Expert Application', 'gsf-hub-expert-workflow' ),
			'slug'    => 'expert-apply',
			'excerpt' => __( 'Apply to join the public GSF expert roster. Applications are reviewed before profiles go live.', 'gsf-hub-expert-workflow' ),
			'content' => "<!-- wp:paragraph --><p>" . esc_html__( 'Apply to join the public GSF expert roster. Applications are reviewed before profiles go live.', 'gsf-hub-expert-workflow' ) . "</p><!-- /wp:paragraph -->\n\n<!-- wp:shortcode -->\n" . $shortcode . "\n<!-- /wp:shortcode -->",
		),
		'fr' => array(
			'title'   => 'Demande d\'expert',
			'slug'    => 'demande-expert',
			'excerpt' => 'Soumettez votre candidature au repertoire public d\'experts du GSF.',
			'content' => "<!-- wp:paragraph --><p>Soumettez votre candidature au repertoire public d'experts du GSF. Les candidatures sont examinees avant la publication du profil.</p><!-- /wp:paragraph -->\n\n<!-- wp:shortcode -->\n" . $shortcode . "\n<!-- /wp:shortcode -->",
		),
		'es' => array(
			'title'   => 'Solicitud de experto',
			'slug'    => 'solicitud-experto',
			'excerpt' => 'Postule para unirse al directorio publico de expertos del GSF.',
			'content' => "<!-- wp:paragraph --><p>Postule para unirse al directorio publico de expertos del GSF. Las solicitudes se revisan antes de publicar el perfil.</p><!-- /wp:paragraph -->\n\n<!-- wp:shortcode -->\n" . $shortcode . "\n<!-- /wp:shortcode -->",
		),
		'nl' => array(
			'title'   => 'Expertaanvraag',
			'slug'    => 'expert-aanvraag',
			'excerpt' => 'Dien een aanvraag in voor opname in het openbare GSF-expertenrooster.',
			'content' => "<!-- wp:paragraph --><p>Dien een aanvraag in voor opname in het openbare GSF-expertenrooster. Aanvragen worden beoordeeld voordat profielen live gaan.</p><!-- /wp:paragraph -->\n\n<!-- wp:shortcode -->\n" . $shortcode . "\n<!-- /wp:shortcode -->",
		),
	);
}

/**
 * Returns multilingual page definitions for the expert portal page.
 *
 * @return array<string, array<string, string>>
 */
function gsf_hub_expert_workflow_get_portal_page_definitions() {
	$shortcode = '[gsf_hub_expert_portal]';

	return array(
		'en' => array(
			'title'   => __( 'Expert Roster Portal', 'gsf-hub-expert-workflow' ),
			'slug'    => 'expert-portal',
			'excerpt' => __( 'Start or manage your expert roster application from one place.', 'gsf-hub-expert-workflow' ),
			'content' => "<!-- wp:paragraph --><p>" . esc_html__( 'Use this page to start, continue, or review the status of your expert roster application.', 'gsf-hub-expert-workflow' ) . "</p><!-- /wp:paragraph -->\n\n<!-- wp:shortcode -->\n" . $shortcode . "\n<!-- /wp:shortcode -->",
		),
		'fr' => array(
			'title'   => 'Portail des experts',
			'slug'    => 'portail-expert',
			'excerpt' => 'Demarrez ou gerez votre candidature au repertoire des experts depuis un seul endroit.',
			'content' => "<!-- wp:paragraph --><p>Utilisez cette page pour demarrer, poursuivre ou verifier l'etat de votre candidature au repertoire des experts.</p><!-- /wp:paragraph -->\n\n<!-- wp:shortcode -->\n" . $shortcode . "\n<!-- /wp:shortcode -->",
		),
		'es' => array(
			'title'   => 'Portal de Expertos',
			'slug'    => 'portal-expertos',
			'excerpt' => 'Inicie o gestione su solicitud al directorio de expertos desde un solo lugar.',
			'content' => "<!-- wp:paragraph --><p>Use esta pagina para iniciar, continuar o revisar el estado de su solicitud al directorio de expertos.</p><!-- /wp:paragraph -->\n\n<!-- wp:shortcode -->\n" . $shortcode . "\n<!-- /wp:shortcode -->",
		),
		'nl' => array(
			'title'   => 'Expertenportaal',
			'slug'    => 'expertenportaal',
			'excerpt' => 'Start of beheer uw aanvraag voor het expertenrooster vanaf een centrale plek.',
			'content' => "<!-- wp:paragraph --><p>Gebruik deze pagina om uw aanvraag voor het expertenrooster te starten, voort te zetten of de status ervan te bekijken.</p><!-- /wp:paragraph -->\n\n<!-- wp:shortcode -->\n" . $shortcode . "\n<!-- /wp:shortcode -->",
		),
	);
}

/**
 * Resolves the current language slug for translated workflow pages.
 *
 * @return string
 */
function gsf_hub_expert_workflow_get_current_language_slug() {
	if ( function_exists( 'pll_current_language' ) ) {
		$current_language = pll_current_language( 'slug' );
		if ( is_string( $current_language ) && '' !== $current_language ) {
			return $current_language;
		}
	}

	return 'en';
}

/**
 * Returns a translated workflow page URL from a stored option map.
 *
 * @param string      $option_key Option name containing language=>page ID map.
 * @param string|null $language   Language slug.
 * @return string
 */
function gsf_hub_expert_workflow_get_translated_page_url( $option_key, $language = null ) {
	$page_ids = get_option( $option_key, array() );
	$page_ids = is_array( $page_ids ) ? $page_ids : array();
	$language = is_string( $language ) && '' !== $language ? $language : gsf_hub_expert_workflow_get_current_language_slug();

	$candidates = array( $language, 'en' );

	foreach ( $candidates as $candidate ) {
		if ( empty( $page_ids[ $candidate ] ) ) {
			continue;
		}

		$page_url = get_permalink( (int) $page_ids[ $candidate ] );
		if ( $page_url ) {
			return $page_url;
		}
	}

	return home_url( '/' );
}

/**
 * Ensures the expert application pages exist.
 */
function gsf_hub_expert_workflow_ensure_application_pages() {
	$page_ids    = get_option( GSF_HUB_EXPERT_WORKFLOW_OPTION_PAGE_IDS, array() );
	$page_ids    = is_array( $page_ids ) ? $page_ids : array();
	$definitions = gsf_hub_expert_workflow_get_application_page_definitions();

	foreach ( $definitions as $language => $definition ) {
		$page_id = ! empty( $page_ids[ $language ] ) ? (int) $page_ids[ $language ] : 0;

		if ( ! $page_id || 'page' !== get_post_type( $page_id ) ) {
			$existing = get_page_by_path( $definition['slug'], OBJECT, 'page' );
			if ( $existing instanceof WP_Post ) {
				$page_id = (int) $existing->ID;
			} else {
				$page_id = wp_insert_post(
					array(
						'post_type'    => 'page',
						'post_status'  => 'publish',
						'post_title'   => $definition['title'],
						'post_name'    => $definition['slug'],
						'post_excerpt' => $definition['excerpt'],
						'post_content' => $definition['content'],
					)
				);
			}
		}

		if ( ! $page_id || is_wp_error( $page_id ) ) {
			continue;
		}

		$page_ids[ $language ] = (int) $page_id;

		$current = get_post( $page_id );
		if ( $current instanceof WP_Post ) {
			$current_content = (string) $current->post_content;
			$update_args     = array(
				'ID'         => $page_id,
				'post_title' => $definition['title'],
				'post_name'  => $definition['slug'],
			);

			if ( has_shortcode( $current_content, 'ultimatemember' ) ) {
				$update_args['post_content'] = preg_replace( '/\[ultimatemember[^\]]+\]/', '[gsf_hub_expert_application]', $current_content );
			} elseif ( ! has_shortcode( $current_content, 'gsf_hub_expert_application' ) ) {
				$update_args['post_content'] = $definition['content'];
			}

			if ( '' === trim( (string) $current->post_excerpt ) ) {
				$update_args['post_excerpt'] = $definition['excerpt'];
			}

			wp_update_post( $update_args );
		}

		if ( function_exists( 'pll_set_post_language' ) ) {
			pll_set_post_language( $page_id, $language );
		}
	}

	update_option( GSF_HUB_EXPERT_WORKFLOW_OPTION_PAGE_IDS, $page_ids );

	if ( function_exists( 'pll_save_post_translations' ) && count( $page_ids ) > 1 ) {
		pll_save_post_translations( $page_ids );
	}
}

/**
 * Ensures the expert portal pages exist.
 */
function gsf_hub_expert_workflow_ensure_portal_pages() {
	$page_ids    = get_option( GSF_HUB_EXPERT_WORKFLOW_OPTION_PORTAL_PAGE_IDS, array() );
	$page_ids    = is_array( $page_ids ) ? $page_ids : array();
	$definitions = gsf_hub_expert_workflow_get_portal_page_definitions();

	foreach ( $definitions as $language => $definition ) {
		$page_id = ! empty( $page_ids[ $language ] ) ? (int) $page_ids[ $language ] : 0;

		if ( ! $page_id || 'page' !== get_post_type( $page_id ) ) {
			$existing = get_page_by_path( $definition['slug'], OBJECT, 'page' );
			if ( $existing instanceof WP_Post ) {
				$page_id = (int) $existing->ID;
			} else {
				$page_id = wp_insert_post(
					array(
						'post_type'    => 'page',
						'post_status'  => 'publish',
						'post_title'   => $definition['title'],
						'post_name'    => $definition['slug'],
						'post_excerpt' => $definition['excerpt'],
						'post_content' => $definition['content'],
					)
				);
			}
		}

		if ( ! $page_id || is_wp_error( $page_id ) ) {
			continue;
		}

		$page_ids[ $language ] = (int) $page_id;

		$current = get_post( $page_id );
		if ( $current instanceof WP_Post ) {
			$current_content = (string) $current->post_content;
			$update_args     = array(
				'ID'         => $page_id,
				'post_title' => $definition['title'],
				'post_name'  => $definition['slug'],
			);

			if ( ! has_shortcode( $current_content, 'gsf_hub_expert_portal' ) ) {
				$update_args['post_content'] = $definition['content'];
			}

			if ( '' === trim( (string) $current->post_excerpt ) ) {
				$update_args['post_excerpt'] = $definition['excerpt'];
			}

			wp_update_post( $update_args );
		}

		if ( function_exists( 'pll_set_post_language' ) ) {
			pll_set_post_language( $page_id, $language );
		}
	}

	update_option( GSF_HUB_EXPERT_WORKFLOW_OPTION_PORTAL_PAGE_IDS, $page_ids );

	if ( function_exists( 'pll_save_post_translations' ) && count( $page_ids ) > 1 ) {
		pll_save_post_translations( $page_ids );
	}
}

/**
 * Returns whether the user has legacy application data stored in user meta.
 *
 * @param int $user_id User ID.
 * @return bool
 */
function gsf_hub_expert_workflow_has_legacy_user_application_meta( $user_id ) {
	$legacy_keys = array(
		'organization',
		'job_title',
		'country_name',
		'expertise_summary',
		'short_bio',
		'website',
		'linkedin',
	);

	foreach ( $legacy_keys as $meta_key ) {
		$value = trim( (string) get_user_meta( $user_id, $meta_key, true ) );
		if ( '' !== $value ) {
			return true;
		}
	}

	return false;
}

/**
 * Returns a linked expert profile post ID for a user when available.
 *
 * @param int $user_id User ID.
 * @return int
 */
function gsf_hub_expert_workflow_get_profile_post_id( $user_id ) {
	$profile_post = (int) get_user_meta( $user_id, GSF_HUB_EXPERT_WORKFLOW_USER_PROFILE_POST, true );
	if ( $profile_post && 'expert_directory' === get_post_type( $profile_post ) ) {
		return $profile_post;
	}

	$posts = get_posts(
		array(
			'post_type'      => 'expert_directory',
			'post_status'    => array( 'draft', 'publish', 'pending', 'private' ),
			'author'         => $user_id,
			'posts_per_page' => 1,
			'orderby'        => 'date',
			'order'          => 'DESC',
			'fields'         => 'ids',
		)
	);

	if ( ! empty( $posts[0] ) ) {
		$profile_post = (int) $posts[0];
		update_user_meta( $user_id, GSF_HUB_EXPERT_WORKFLOW_USER_PROFILE_POST, $profile_post );
		return $profile_post;
	}

	return 0;
}

/**
 * Returns the expert-application status for a user.
 *
 * @param int $user_id User ID.
 * @return string
 */
function gsf_hub_expert_workflow_get_application_status( $user_id ) {
	$status = sanitize_key( (string) get_user_meta( $user_id, GSF_HUB_EXPERT_WORKFLOW_USER_STATUS, true ) );
	if ( in_array( $status, gsf_hub_expert_workflow_get_valid_statuses(), true ) ) {
		return $status;
	}

	$user = get_userdata( $user_id );
	if ( $user instanceof WP_User ) {
		if ( in_array( GSF_HUB_EXPERT_WORKFLOW_APPROVED_ROLE, (array) $user->roles, true ) ) {
			return GSF_HUB_EXPERT_WORKFLOW_STATUS_APPROVED;
		}

		if ( in_array( GSF_HUB_EXPERT_WORKFLOW_PENDING_ROLE, (array) $user->roles, true ) ) {
			return GSF_HUB_EXPERT_WORKFLOW_STATUS_PENDING;
		}
	}

	if ( gsf_hub_expert_workflow_get_profile_post_id( $user_id ) ) {
		return 'publish' === get_post_status( gsf_hub_expert_workflow_get_profile_post_id( $user_id ) ) ? GSF_HUB_EXPERT_WORKFLOW_STATUS_APPROVED : GSF_HUB_EXPERT_WORKFLOW_STATUS_PENDING;
	}

	if ( get_user_meta( $user_id, GSF_HUB_EXPERT_WORKFLOW_USER_FLAG, true ) ) {
		return GSF_HUB_EXPERT_WORKFLOW_STATUS_PENDING;
	}

	return '';
}

/**
 * Returns whether a user belongs to the expert application workflow.
 *
 * @param int $user_id User ID.
 * @return bool
 */
function gsf_hub_expert_workflow_is_expert_user( $user_id ) {
	if ( '' !== gsf_hub_expert_workflow_get_application_status( $user_id ) ) {
		return true;
	}

	if ( get_user_meta( $user_id, GSF_HUB_EXPERT_WORKFLOW_USER_FLAG, true ) ) {
		return true;
	}

	return gsf_hub_expert_workflow_has_legacy_user_application_meta( $user_id );
}

/**
 * Returns the valid application statuses.
 *
 * @return string[]
 */
function gsf_hub_expert_workflow_get_valid_statuses() {
	return array(
		GSF_HUB_EXPERT_WORKFLOW_STATUS_PENDING,
		GSF_HUB_EXPERT_WORKFLOW_STATUS_APPROVED,
		GSF_HUB_EXPERT_WORKFLOW_STATUS_CHANGES_REQUESTED,
		GSF_HUB_EXPERT_WORKFLOW_STATUS_REJECTED,
	);
}

/**
 * Returns a human-readable label for an application status.
 *
 * @param string $status Application status.
 * @return string
 */
function gsf_hub_expert_workflow_get_application_status_label( $status ) {
	if ( GSF_HUB_EXPERT_WORKFLOW_STATUS_APPROVED === $status ) {
		return __( 'Approved', 'gsf-hub-expert-workflow' );
	}

	if ( GSF_HUB_EXPERT_WORKFLOW_STATUS_CHANGES_REQUESTED === $status ) {
		return __( 'Changes Requested', 'gsf-hub-expert-workflow' );
	}

	if ( GSF_HUB_EXPERT_WORKFLOW_STATUS_REJECTED === $status ) {
		return __( 'Rejected', 'gsf-hub-expert-workflow' );
	}

	return __( 'Pending Review', 'gsf-hub-expert-workflow' );
}

/**
 * Returns the latest reviewer notes for an application.
 *
 * @param int $user_id User ID.
 * @return string
 */
function gsf_hub_expert_workflow_get_reviewer_notes( $user_id ) {
	return trim( (string) get_user_meta( $user_id, GSF_HUB_EXPERT_WORKFLOW_USER_REVIEW_NOTES, true ) );
}

/**
 * Assigns the expert workflow role that matches the application status.
 *
 * @param int    $user_id User ID.
 * @param string $status  Application status.
 */
function gsf_hub_expert_workflow_assign_role_for_status( $user_id, $status ) {
	$user = get_userdata( $user_id );
	if ( ! $user instanceof WP_User ) {
		return;
	}

	if ( GSF_HUB_EXPERT_WORKFLOW_STATUS_APPROVED === $status ) {
		if ( ! in_array( GSF_HUB_EXPERT_WORKFLOW_APPROVED_ROLE, (array) $user->roles, true ) ) {
			$user->add_role( GSF_HUB_EXPERT_WORKFLOW_APPROVED_ROLE );
		}
		if ( in_array( GSF_HUB_EXPERT_WORKFLOW_PENDING_ROLE, (array) $user->roles, true ) ) {
			$user->remove_role( GSF_HUB_EXPERT_WORKFLOW_PENDING_ROLE );
		}
		return;
	}

	if ( ! in_array( GSF_HUB_EXPERT_WORKFLOW_PENDING_ROLE, (array) $user->roles, true ) ) {
		$user->add_role( GSF_HUB_EXPERT_WORKFLOW_PENDING_ROLE );
	}
	if ( in_array( GSF_HUB_EXPERT_WORKFLOW_APPROVED_ROLE, (array) $user->roles, true ) ) {
		$user->remove_role( GSF_HUB_EXPERT_WORKFLOW_APPROVED_ROLE );
	}
}

/**
 * Sets the application status and synchronizes the linked expert profile.
 *
 * @param int         $user_id        User ID.
 * @param string      $status         Application status.
 * @param string|null $reviewer_notes Optional reviewer notes. Pass null to preserve existing notes.
 * @param int         $reviewed_by    Reviewer user ID.
 * @return int
 */
function gsf_hub_expert_workflow_set_application_status( $user_id, $status, $reviewer_notes = null, $reviewed_by = 0 ) {
	if ( ! in_array( $status, gsf_hub_expert_workflow_get_valid_statuses(), true ) ) {
		return 0;
	}

	update_user_meta( $user_id, GSF_HUB_EXPERT_WORKFLOW_USER_FLAG, 1 );
	update_user_meta( $user_id, GSF_HUB_EXPERT_WORKFLOW_USER_STATUS, $status );
	update_user_meta( $user_id, 'account_status', 'approved' );

	if ( ! get_user_meta( $user_id, GSF_HUB_EXPERT_WORKFLOW_USER_SUBMITTED_AT, true ) ) {
		update_user_meta( $user_id, GSF_HUB_EXPERT_WORKFLOW_USER_SUBMITTED_AT, current_time( 'mysql' ) );
	}

	if ( null !== $reviewer_notes ) {
		$reviewer_notes = trim( $reviewer_notes );
		if ( '' === $reviewer_notes ) {
			delete_user_meta( $user_id, GSF_HUB_EXPERT_WORKFLOW_USER_REVIEW_NOTES );
		} else {
			update_user_meta( $user_id, GSF_HUB_EXPERT_WORKFLOW_USER_REVIEW_NOTES, $reviewer_notes );
		}
	}

	if ( $reviewed_by > 0 ) {
		update_user_meta( $user_id, GSF_HUB_EXPERT_WORKFLOW_USER_REVIEWED_BY, $reviewed_by );
		update_user_meta( $user_id, GSF_HUB_EXPERT_WORKFLOW_USER_REVIEWED_AT, current_time( 'mysql' ) );
	}

	gsf_hub_expert_workflow_assign_role_for_status( $user_id, $status );

	return gsf_hub_expert_workflow_sync_profile_post( $user_id, GSF_HUB_EXPERT_WORKFLOW_STATUS_APPROVED === $status );
}

/**
 * Normalizes a stored attachment value into a single attachment ID.
 *
 * @param mixed $value Stored value.
 * @return int
 */
function gsf_hub_expert_workflow_normalize_attachment_id( $value ) {
	if ( is_array( $value ) ) {
		$value = reset( $value );
	}

	return absint( $value );
}

/**
 * Normalizes a stored country value into a single term ID.
 *
 * @param mixed $value Stored value.
 * @return int
 */
function gsf_hub_expert_workflow_normalize_country_term_id( $value ) {
	if ( is_string( $value ) ) {
		$maybe_array = maybe_unserialize( $value );
		if ( is_array( $maybe_array ) ) {
			$value = reset( $maybe_array );
		}
	}

	if ( is_array( $value ) ) {
		$value = reset( $value );
	}

	return absint( $value );
}

/**
 * Returns expert application data from the linked profile, falling back to legacy user meta.
 *
 * @param int $user_id User ID.
 * @return array<string, mixed>
 */
function gsf_hub_expert_workflow_get_application_data( $user_id ) {
	$user         = get_userdata( $user_id );
	$profile_post = gsf_hub_expert_workflow_get_profile_post_id( $user_id );
	$data         = array(
		'full_name'         => '',
		'organization'      => '',
		'job_title'         => '',
		'country_term_id'   => 0,
		'country_name'      => '',
		'expertise_summary' => '',
		'short_bio'         => '',
		'website'           => '',
		'linkedin'          => '',
		'upload_photo'      => 0,
		'user_login'        => $user instanceof WP_User ? $user->user_login : '',
		'user_email'        => $user instanceof WP_User ? $user->user_email : '',
	);

	if ( $profile_post ) {
		$data['full_name']         = trim( (string) get_post_meta( $profile_post, 'full_name', true ) );
		$data['organization']      = trim( (string) get_post_meta( $profile_post, 'organization', true ) );
		$data['job_title']         = trim( (string) get_post_meta( $profile_post, 'job_title', true ) );
		$data['expertise_summary'] = trim( (string) get_post_meta( $profile_post, 'expertise_summary', true ) );
		$data['short_bio']         = trim( (string) get_post_meta( $profile_post, 'short_bio', true ) );
		$data['website']           = trim( (string) get_post_meta( $profile_post, 'website', true ) );
		$data['linkedin']          = trim( (string) get_post_meta( $profile_post, 'linkedin', true ) );
		$data['upload_photo']      = gsf_hub_expert_workflow_normalize_attachment_id( get_post_meta( $profile_post, 'upload_photo', true ) );
		$data['country_term_id']   = gsf_hub_expert_workflow_normalize_country_term_id( get_post_meta( $profile_post, 'country', true ) );

		if ( ! $data['country_term_id'] ) {
			$data['country_term_id'] = gsf_hub_expert_workflow_normalize_country_term_id( get_post_meta( $profile_post, '_pods_country', true ) );
		}

		if ( '' === $data['full_name'] ) {
			$data['full_name'] = get_the_title( $profile_post );
		}

		if ( '' === $data['short_bio'] ) {
			$data['short_bio'] = trim( (string) get_post_meta( $profile_post, 'bio', true ) );
		}

		if ( $data['country_term_id'] && taxonomy_exists( 'country' ) ) {
			$term = get_term( $data['country_term_id'], 'country' );
			if ( $term instanceof WP_Term && ! is_wp_error( $term ) ) {
				$data['country_name'] = $term->name;
			}
		}
	}

	$legacy_full_name = trim(
		implode(
			' ',
			array_filter(
				array(
					trim( (string) get_user_meta( $user_id, 'first_name', true ) ),
					trim( (string) get_user_meta( $user_id, 'last_name', true ) ),
				)
			)
		)
	);

	if ( '' === $data['full_name'] ) {
		$data['full_name'] = '' !== $legacy_full_name ? $legacy_full_name : ( $user instanceof WP_User ? $user->display_name : '' );
	}

	$legacy_map = array(
		'organization'      => 'organization',
		'job_title'         => 'job_title',
		'country_name'      => 'country_name',
		'expertise_summary' => 'expertise_summary',
		'short_bio'         => 'short_bio',
		'website'           => 'website',
		'linkedin'          => 'linkedin',
	);

	foreach ( $legacy_map as $key => $meta_key ) {
		if ( '' === $data[ $key ] ) {
			$data[ $key ] = trim( (string) get_user_meta( $user_id, $meta_key, true ) );
		}
	}

	return $data;
}

/**
 * Finds or creates a country taxonomy term from a supplied country name.
 *
 * @param string $country_name Country or territory name.
 * @return int
 */
function gsf_hub_expert_workflow_resolve_country_term_id( $country_name ) {
	if ( '' === $country_name || ! taxonomy_exists( 'country' ) ) {
		return 0;
	}

	$existing = get_term_by( 'name', $country_name, 'country' );
	if ( $existing instanceof WP_Term ) {
		return (int) $existing->term_id;
	}

	$slug = sanitize_title( $country_name );
	if ( '' !== $slug ) {
		$existing = get_term_by( 'slug', $slug, 'country' );
		if ( $existing instanceof WP_Term ) {
			return (int) $existing->term_id;
		}
	}

	$created = wp_insert_term( $country_name, 'country' );
	if ( is_wp_error( $created ) || empty( $created['term_id'] ) ) {
		return 0;
	}

	return (int) $created['term_id'];
}

/**
 * Creates or updates the linked expert directory profile for a user.
 *
 * @param int  $user_id User ID.
 * @param bool $publish Whether to publish the profile.
 * @return int
 */
function gsf_hub_expert_workflow_sync_profile_post( $user_id, $publish = false ) {
	if ( ! post_type_exists( 'expert_directory' ) ) {
		return 0;
	}

	$data         = gsf_hub_expert_workflow_get_application_data( $user_id );
	$full_name    = '' !== trim( (string) $data['full_name'] ) ? trim( (string) $data['full_name'] ) : $data['user_login'];
	$profile_post = gsf_hub_expert_workflow_get_profile_post_id( $user_id );
	$post_status  = $publish ? 'publish' : 'draft';
	$post_args    = array(
		'post_type'    => 'expert_directory',
		'post_status'  => $post_status,
		'post_title'   => $full_name,
		'post_content' => (string) $data['short_bio'],
		'post_excerpt' => (string) $data['expertise_summary'],
		'post_author'  => $user_id,
	);

	if ( $profile_post && 'expert_directory' === get_post_type( $profile_post ) ) {
		$post_args['ID'] = $profile_post;
		$profile_post    = wp_update_post( $post_args, true );
	} else {
		$profile_post = wp_insert_post( $post_args, true );
	}

	if ( is_wp_error( $profile_post ) || ! $profile_post ) {
		return 0;
	}

	$profile_post = (int) $profile_post;
	update_user_meta( $user_id, GSF_HUB_EXPERT_WORKFLOW_USER_PROFILE_POST, $profile_post );

	update_post_meta( $profile_post, 'full_name', $full_name );
	update_post_meta( $profile_post, 'organization', (string) $data['organization'] );
	update_post_meta( $profile_post, 'job_title', (string) $data['job_title'] );
	update_post_meta( $profile_post, 'website', esc_url_raw( (string) $data['website'] ) );
	update_post_meta( $profile_post, 'linkedin', esc_url_raw( (string) $data['linkedin'] ) );
	update_post_meta( $profile_post, 'short_bio', (string) $data['short_bio'] );
	update_post_meta( $profile_post, 'bio', (string) $data['short_bio'] );
	update_post_meta( $profile_post, 'expertise_summary', (string) $data['expertise_summary'] );

	if ( ! empty( $data['upload_photo'] ) ) {
		update_post_meta( $profile_post, 'upload_photo', (int) $data['upload_photo'] );
	} else {
		delete_post_meta( $profile_post, 'upload_photo' );
	}

	$country_term_id = ! empty( $data['country_term_id'] ) ? (int) $data['country_term_id'] : 0;
	if ( ! $country_term_id && ! empty( $data['country_name'] ) ) {
		$country_term_id = gsf_hub_expert_workflow_resolve_country_term_id( (string) $data['country_name'] );
	}

	if ( $country_term_id ) {
		update_post_meta( $profile_post, 'country', (string) $country_term_id );
		update_post_meta( $profile_post, '_pods_country', maybe_serialize( array( $country_term_id ) ) );
		if ( taxonomy_exists( 'country' ) ) {
			wp_set_object_terms( $profile_post, array( $country_term_id ), 'country', false );
		}
	}

	return $profile_post;
}

/**
 * Returns or creates a profile draft for a legacy applicant before migration.
 *
 * @param int $user_id User ID.
 * @return int
 */
function gsf_hub_expert_workflow_ensure_legacy_profile_post( $user_id ) {
	$profile_post = gsf_hub_expert_workflow_get_profile_post_id( $user_id );
	if ( $profile_post ) {
		return $profile_post;
	}

	if ( ! gsf_hub_expert_workflow_has_legacy_user_application_meta( $user_id ) ) {
		return 0;
	}

	return gsf_hub_expert_workflow_sync_profile_post( $user_id, false );
}

/**
 * Migrates legacy workflow users to the profile-based application status model.
 */
function gsf_hub_expert_workflow_migrate_existing_applications() {
	$user_ids = get_users(
		array(
			'number' => 500,
			'fields' => 'ids',
		)
	);

	foreach ( $user_ids as $user_id ) {
		$user = get_userdata( $user_id );
		if ( ! $user instanceof WP_User ) {
			continue;
		}

		$has_expert_role = in_array( GSF_HUB_EXPERT_WORKFLOW_PENDING_ROLE, (array) $user->roles, true ) || in_array( GSF_HUB_EXPERT_WORKFLOW_APPROVED_ROLE, (array) $user->roles, true );
		$profile_post    = gsf_hub_expert_workflow_get_profile_post_id( $user_id );

		if ( ! get_user_meta( $user_id, GSF_HUB_EXPERT_WORKFLOW_USER_FLAG, true ) && ! $profile_post && ! $has_expert_role && ! gsf_hub_expert_workflow_has_legacy_user_application_meta( $user_id ) ) {
			continue;
		}

		update_user_meta( $user_id, GSF_HUB_EXPERT_WORKFLOW_USER_FLAG, 1 );

		if ( ! get_user_meta( $user_id, GSF_HUB_EXPERT_WORKFLOW_USER_SUBMITTED_AT, true ) ) {
			update_user_meta( $user_id, GSF_HUB_EXPERT_WORKFLOW_USER_SUBMITTED_AT, $user->user_registered ? $user->user_registered : current_time( 'mysql' ) );
		}

		if ( ! $profile_post ) {
			$profile_post = gsf_hub_expert_workflow_ensure_legacy_profile_post( $user_id );
		}

		$account_status = (string) get_user_meta( $user_id, 'account_status', true );
		if ( 'awaiting_admin_review' === $account_status ) {
			update_user_meta( $user_id, 'account_status', 'approved' );
		}

		$status = (string) get_user_meta( $user_id, GSF_HUB_EXPERT_WORKFLOW_USER_STATUS, true );
		if ( ! in_array( $status, gsf_hub_expert_workflow_get_valid_statuses(), true ) ) {
			$is_approved = in_array( GSF_HUB_EXPERT_WORKFLOW_APPROVED_ROLE, (array) $user->roles, true );
			if ( ! $is_approved && $profile_post ) {
				$is_approved = 'publish' === get_post_status( $profile_post );
			}
			update_user_meta( $user_id, GSF_HUB_EXPERT_WORKFLOW_USER_STATUS, $is_approved ? GSF_HUB_EXPERT_WORKFLOW_STATUS_APPROVED : GSF_HUB_EXPERT_WORKFLOW_STATUS_PENDING );
		}

		$current_status = gsf_hub_expert_workflow_get_application_status( $user_id );
		if ( $profile_post && $current_status ) {
			gsf_hub_expert_workflow_assign_role_for_status( $user_id, $current_status );
			gsf_hub_expert_workflow_sync_profile_post( $user_id, GSF_HUB_EXPERT_WORKFLOW_STATUS_APPROVED === $current_status );
		}
	}
}

/**
 * Runs plugin setup tasks.
 */
function gsf_hub_expert_workflow_run_setup() {
	if ( ! function_exists( 'UM' ) ) {
		return;
	}

	gsf_hub_expert_workflow_ensure_roles();
	gsf_hub_expert_workflow_ensure_pod_fields();
	gsf_hub_expert_workflow_ensure_portal_pages();
	gsf_hub_expert_workflow_ensure_application_pages();
	gsf_hub_expert_workflow_migrate_existing_applications();

	update_option( GSF_HUB_EXPERT_WORKFLOW_OPTION_VERSION, GSF_HUB_EXPERT_WORKFLOW_VERSION );
}

/**
 * Activation hook.
 */
function gsf_hub_expert_workflow_activate() {
	gsf_hub_expert_workflow_run_setup();
}
register_activation_hook( __FILE__, 'gsf_hub_expert_workflow_activate' );

/**
 * Ensures setup has run for the current plugin version.
 */
function gsf_hub_expert_workflow_maybe_run_setup() {
	if ( get_option( GSF_HUB_EXPERT_WORKFLOW_OPTION_VERSION ) === GSF_HUB_EXPERT_WORKFLOW_VERSION ) {
		return;
	}

	gsf_hub_expert_workflow_run_setup();
}
add_action( 'admin_init', 'gsf_hub_expert_workflow_maybe_run_setup' );

/**
 * Displays an admin notice when required plugins are unavailable.
 */
function gsf_hub_expert_workflow_admin_notice() {
	if ( gsf_hub_expert_workflow_is_ready() ) {
		return;
	}

	if ( ! current_user_can( 'activate_plugins' ) ) {
		return;
	}
	?>
	<div class="notice notice-warning">
		<p><?php esc_html_e( 'GSF Hub Expert Workflow requires Ultimate Member and Pods with the expert_directory post type enabled.', 'gsf-hub-expert-workflow' ); ?></p>
	</div>
	<?php
}
add_action( 'admin_notices', 'gsf_hub_expert_workflow_admin_notice' );

/**
 * Returns the URL to an Ultimate Member core page.
 *
 * @param string $option_key    UM option key.
 * @param string $fallback_path Fallback path.
 * @return string
 */
function gsf_hub_expert_workflow_get_um_page_url( $option_key, $fallback_path ) {
	$options = get_option( 'um_options', array() );
	$page_id = isset( $options[ $option_key ] ) ? absint( $options[ $option_key ] ) : 0;

	if ( $page_id ) {
		$page_url = get_permalink( $page_id );
		if ( $page_url ) {
			return $page_url;
		}
	}

	return home_url( $fallback_path );
}

/**
 * Returns the login URL with an optional redirect target.
 *
 * @param string $redirect_to Redirect target.
 * @return string
 */
function gsf_hub_expert_workflow_get_login_url( $redirect_to = '' ) {
	$url = gsf_hub_expert_workflow_get_um_page_url( 'core_login', '/login/' );
	return $redirect_to ? add_query_arg( 'redirect_to', $redirect_to, $url ) : $url;
}

/**
 * Returns the register URL with an optional redirect target.
 *
 * @param string $redirect_to Redirect target.
 * @return string
 */
function gsf_hub_expert_workflow_get_register_url( $redirect_to = '' ) {
	$url = gsf_hub_expert_workflow_get_um_page_url( 'core_register', '/register/' );
	return $redirect_to ? add_query_arg( 'redirect_to', $redirect_to, $url ) : $url;
}

/**
 * Returns the current page URL when available.
 *
 * @return string
 */
function gsf_hub_expert_workflow_get_current_page_url() {
	$page_id = get_queried_object_id();
	if ( $page_id ) {
		$page_url = get_permalink( $page_id );
		if ( $page_url ) {
			return $page_url;
		}
	}

	return home_url( '/' );
}

/**
 * Returns the translated expert application page URL.
 *
 * @param string|null $language Language slug.
 * @return string
 */
function gsf_hub_expert_workflow_get_application_page_url( $language = null ) {
	return gsf_hub_expert_workflow_get_translated_page_url( GSF_HUB_EXPERT_WORKFLOW_OPTION_PAGE_IDS, $language );
}

/**
 * Returns the translated expert portal page URL.
 *
 * @param string|null $language Language slug.
 * @return string
 */
function gsf_hub_expert_workflow_get_portal_page_url( $language = null ) {
	return gsf_hub_expert_workflow_get_translated_page_url( GSF_HUB_EXPERT_WORKFLOW_OPTION_PORTAL_PAGE_IDS, $language );
}

/**
 * Returns the editable Pods field names for the expert application form.
 *
 * @return string[]
 */
function gsf_hub_expert_workflow_get_application_form_field_names() {
	return array(
		'full_name',
		'upload_photo',
		'organization',
		'job_title',
		'country',
		'expertise_summary',
		'short_bio',
		'website',
		'linkedin',
	);
}

/**
 * Returns the signature used to identify the expert application Pods form submission.
 *
 * @return string
 */
function gsf_hub_expert_workflow_get_application_form_signature() {
	return implode( ',', gsf_hub_expert_workflow_get_application_form_field_names() );
}

/**
 * Renders the logged-out access gate for the expert application page.
 *
 * @param string $redirect_target Redirect target for login/register.
 * @param string $message         Optional gate message.
 * @return string
 */
function gsf_hub_expert_workflow_render_logged_out_gate( $redirect_target, $message = '' ) {
	if ( '' === trim( $message ) ) {
		$message = __( 'Create a member account first. After signing in, you can complete the separate expert roster application.', 'gsf-hub-expert-workflow' );
	}

	ob_start();
	?>
	<div class="gsf-expert-application-gate">
		<p><?php echo esc_html( $message ); ?></p>
		<p>
			<a class="button button-primary" href="<?php echo esc_url( gsf_hub_expert_workflow_get_login_url( $redirect_target ) ); ?>"><?php esc_html_e( 'Sign In', 'gsf-hub-expert-workflow' ); ?></a>
			<a class="button" href="<?php echo esc_url( gsf_hub_expert_workflow_get_register_url( $redirect_target ) ); ?>"><?php esc_html_e( 'Create Account', 'gsf-hub-expert-workflow' ); ?></a>
		</p>
	</div>
	<?php
	return (string) ob_get_clean();
}

/**
 * Renders the expert portal shortcode.
 *
 * @return string
 */
function gsf_hub_expert_workflow_render_portal_shortcode() {
	$current_url = gsf_hub_expert_workflow_get_current_page_url();

	if ( ! is_user_logged_in() ) {
		return gsf_hub_expert_workflow_render_logged_out_gate(
			$current_url,
			__( 'Sign in or create a member account to open your expert roster portal. From there you can start or manage the separate expert application.', 'gsf-hub-expert-workflow' )
		);
	}

	$user_id          = get_current_user_id();
	$user             = wp_get_current_user();
	$status           = gsf_hub_expert_workflow_get_application_status( $user_id );
	$status_label     = $status ? gsf_hub_expert_workflow_get_application_status_label( $status ) : __( 'Not Started', 'gsf-hub-expert-workflow' );
	$review_notes     = gsf_hub_expert_workflow_get_reviewer_notes( $user_id );
	$submitted_at     = (string) get_user_meta( $user_id, GSF_HUB_EXPERT_WORKFLOW_USER_SUBMITTED_AT, true );
	$reviewed_at      = (string) get_user_meta( $user_id, GSF_HUB_EXPERT_WORKFLOW_USER_REVIEWED_AT, true );
	$profile_post     = gsf_hub_expert_workflow_get_profile_post_id( $user_id );
	$application_url  = gsf_hub_expert_workflow_get_application_page_url();
	$account_url      = gsf_hub_expert_workflow_get_um_page_url( 'core_account', '/account/' );
	$public_profile   = $profile_post && 'publish' === get_post_status( $profile_post ) ? get_permalink( $profile_post ) : '';
	$primary_label    = __( 'Start Expert Application', 'gsf-hub-expert-workflow' );
	$status_message   = __( 'You have an account, but you have not started your expert roster application yet.', 'gsf-hub-expert-workflow' );
	$secondary_notice = __( 'The application opens on the next page and saves to your draft expert profile.', 'gsf-hub-expert-workflow' );

	if ( GSF_HUB_EXPERT_WORKFLOW_STATUS_PENDING === $status ) {
		$primary_label    = __( 'Continue Application', 'gsf-hub-expert-workflow' );
		$status_message   = __( 'Your application is under review. You can still review and refine the draft profile while it is pending.', 'gsf-hub-expert-workflow' );
		$secondary_notice = __( 'Use the application form to make updates if a reviewer asks for clarifications.', 'gsf-hub-expert-workflow' );
	} elseif ( GSF_HUB_EXPERT_WORKFLOW_STATUS_CHANGES_REQUESTED === $status ) {
		$primary_label    = __( 'Update and Resubmit', 'gsf-hub-expert-workflow' );
		$status_message   = __( 'A reviewer requested changes to your expert profile. Update the draft and submit it again for review.', 'gsf-hub-expert-workflow' );
		$secondary_notice = __( 'Reviewer notes are shown below so you know exactly what to revise.', 'gsf-hub-expert-workflow' );
	} elseif ( GSF_HUB_EXPERT_WORKFLOW_STATUS_REJECTED === $status ) {
		$primary_label    = __( 'Review Application Draft', 'gsf-hub-expert-workflow' );
		$status_message   = __( 'This application was not approved in its current form. Review the feedback and update the draft before submitting again if you are invited to reapply.', 'gsf-hub-expert-workflow' );
		$secondary_notice = __( 'The public roster profile stays offline until a reviewer approves it.', 'gsf-hub-expert-workflow' );
	} elseif ( GSF_HUB_EXPERT_WORKFLOW_STATUS_APPROVED === $status ) {
		$primary_label    = __( 'Manage Expert Profile', 'gsf-hub-expert-workflow' );
		$status_message   = __( 'Your profile is approved and live in the public roster. Use the application form to keep it up to date.', 'gsf-hub-expert-workflow' );
		$secondary_notice = __( 'Any changes you save from the form update the linked public profile.', 'gsf-hub-expert-workflow' );
	}

	ob_start();
	?>
	<div class="gsf-expert-application">
		<div class="gsf-expert-application-status">
			<strong><?php echo esc_html( $status_label ); ?></strong>
			<p>
				<?php
				printf(
					/* translators: %s: user display name */
					esc_html__( 'Hello %s. %s', 'gsf-hub-expert-workflow' ),
					esc_html( $user->display_name ? $user->display_name : $user->user_login ),
					esc_html( $status_message )
				);
				?>
			</p>
			<p><?php echo esc_html( $secondary_notice ); ?></p>
			<?php if ( $submitted_at ) : ?>
				<p><strong><?php esc_html_e( 'Submitted:', 'gsf-hub-expert-workflow' ); ?></strong> <?php echo esc_html( mysql2date( 'F j, Y g:i a', $submitted_at ) ); ?></p>
			<?php endif; ?>
			<?php if ( $reviewed_at && GSF_HUB_EXPERT_WORKFLOW_STATUS_PENDING !== $status ) : ?>
				<p><strong><?php esc_html_e( 'Last reviewed:', 'gsf-hub-expert-workflow' ); ?></strong> <?php echo esc_html( mysql2date( 'F j, Y g:i a', $reviewed_at ) ); ?></p>
			<?php endif; ?>
			<?php if ( '' !== $review_notes && in_array( $status, array( GSF_HUB_EXPERT_WORKFLOW_STATUS_CHANGES_REQUESTED, GSF_HUB_EXPERT_WORKFLOW_STATUS_REJECTED ), true ) ) : ?>
				<p><strong><?php esc_html_e( 'Reviewer notes:', 'gsf-hub-expert-workflow' ); ?></strong> <?php echo esc_html( $review_notes ); ?></p>
			<?php endif; ?>
			<p>
				<a class="button button-primary" href="<?php echo esc_url( $application_url ); ?>"><?php echo esc_html( $primary_label ); ?></a>
				<a class="button" href="<?php echo esc_url( $account_url ); ?>"><?php esc_html_e( 'My Account', 'gsf-hub-expert-workflow' ); ?></a>
				<?php if ( $public_profile ) : ?>
					<a class="button" href="<?php echo esc_url( $public_profile ); ?>"><?php esc_html_e( 'View Public Profile', 'gsf-hub-expert-workflow' ); ?></a>
				<?php endif; ?>
			</p>
		</div>
	</div>
	<?php
	return (string) ob_get_clean();
}
add_shortcode( 'gsf_hub_expert_portal', 'gsf_hub_expert_workflow_render_portal_shortcode' );

/**
 * Renders the expert-application shortcode.
 *
 * @return string
 */
function gsf_hub_expert_workflow_render_application_shortcode() {
	if ( ! function_exists( 'pods' ) || ! post_type_exists( 'expert_directory' ) ) {
		return current_user_can( 'edit_posts' ) ? '<p>' . esc_html__( 'The expert application form is not available because Pods is not ready.', 'gsf-hub-expert-workflow' ) . '</p>' : '';
	}

	$current_url = gsf_hub_expert_workflow_get_current_page_url();
	$portal_url  = gsf_hub_expert_workflow_get_portal_page_url();

	if ( ! is_user_logged_in() ) {
		return gsf_hub_expert_workflow_render_logged_out_gate(
			$portal_url ? $portal_url : $current_url,
			__( 'Sign in or create a member account to open your expert roster portal first. From there you can continue to the expert application form.', 'gsf-hub-expert-workflow' )
		);
	}

	$user_id      = get_current_user_id();
	$profile_post = gsf_hub_expert_workflow_get_profile_post_id( $user_id );
	$status       = gsf_hub_expert_workflow_get_application_status( $user_id );
	$review_notes = gsf_hub_expert_workflow_get_reviewer_notes( $user_id );
	$status_text  = '';
	$status_label = __( 'Draft Profile', 'gsf-hub-expert-workflow' );
	$button_text  = __( 'Submit for Review', 'gsf-hub-expert-workflow' );

	if ( GSF_HUB_EXPERT_WORKFLOW_STATUS_APPROVED === $status ) {
		$status_label = gsf_hub_expert_workflow_get_application_status_label( GSF_HUB_EXPERT_WORKFLOW_STATUS_APPROVED );
		$status_text = __( 'Your expert roster profile is live. Any changes you save here will update your public profile.', 'gsf-hub-expert-workflow' );
		$button_text = __( 'Save Profile Changes', 'gsf-hub-expert-workflow' );
	} elseif ( GSF_HUB_EXPERT_WORKFLOW_STATUS_PENDING === $status ) {
		$status_label = gsf_hub_expert_workflow_get_application_status_label( GSF_HUB_EXPERT_WORKFLOW_STATUS_PENDING );
		$status_text = __( 'Your application is under review. You can continue refining the draft profile while it is being reviewed.', 'gsf-hub-expert-workflow' );
		$button_text = __( 'Update Application', 'gsf-hub-expert-workflow' );
	} elseif ( GSF_HUB_EXPERT_WORKFLOW_STATUS_CHANGES_REQUESTED === $status ) {
		$status_label = gsf_hub_expert_workflow_get_application_status_label( GSF_HUB_EXPERT_WORKFLOW_STATUS_CHANGES_REQUESTED );
		$status_text = __( 'A reviewer requested updates to your expert profile. Revise the information below and resubmit it for review.', 'gsf-hub-expert-workflow' );
		$button_text = __( 'Resubmit for Review', 'gsf-hub-expert-workflow' );
	} elseif ( GSF_HUB_EXPERT_WORKFLOW_STATUS_REJECTED === $status ) {
		$status_label = gsf_hub_expert_workflow_get_application_status_label( GSF_HUB_EXPERT_WORKFLOW_STATUS_REJECTED );
		$status_text = __( 'This application was not approved. You can update the profile below and submit a new review request if you are invited to reapply.', 'gsf-hub-expert-workflow' );
		$button_text = __( 'Submit New Review Request', 'gsf-hub-expert-workflow' );
	} else {
		$status_text = __( 'Complete your roster profile and submit it for review.', 'gsf-hub-expert-workflow' );
	}

	$pods_object = $profile_post ? pods( 'expert_directory', $profile_post ) : pods( 'expert_directory' );
	if ( ! $pods_object || is_wp_error( $pods_object ) ) {
		return current_user_can( 'edit_posts' ) ? '<p>' . esc_html__( 'The expert application form could not be loaded.', 'gsf-hub-expert-workflow' ) . '</p>' : '';
	}

	$thank_you_url = add_query_arg( 'expert_application_saved', '1', $current_url );
	$form_html     = $pods_object->form(
		array(
			'fields'       => gsf_hub_expert_workflow_get_application_form_field_names(),
			'label'        => $button_text,
			'thank_you'    => $thank_you_url,
			'check_access' => false,
			'form_key'     => GSF_HUB_EXPERT_WORKFLOW_FORM_KEY,
		)
	);

	ob_start();
	?>
	<div class="gsf-expert-application">
		<?php if ( isset( $_GET['expert_application_saved'] ) ) : ?>
			<div class="notice notice-success" style="padding:12px 16px; margin: 0 0 1rem;">
				<p style="margin:0;">
					<?php
					if ( GSF_HUB_EXPERT_WORKFLOW_STATUS_APPROVED === $status ) {
						echo esc_html__( 'Your expert profile changes were saved.', 'gsf-hub-expert-workflow' );
					} elseif ( GSF_HUB_EXPERT_WORKFLOW_STATUS_PENDING === $status ) {
						echo esc_html__( 'Your expert application was submitted for review.', 'gsf-hub-expert-workflow' );
					} else {
						echo esc_html__( 'Your expert application draft was saved successfully.', 'gsf-hub-expert-workflow' );
					}
					?>
				</p>
			</div>
		<?php endif; ?>
		<div class="gsf-expert-application-status">
			<?php if ( $portal_url ) : ?>
				<p><a href="<?php echo esc_url( $portal_url ); ?>"><?php esc_html_e( 'Back to Expert Portal', 'gsf-hub-expert-workflow' ); ?></a></p>
			<?php endif; ?>
			<strong><?php echo esc_html( $status_label ); ?></strong>
			<p><?php echo esc_html( $status_text ); ?></p>
			<?php if ( '' !== $review_notes && in_array( $status, array( GSF_HUB_EXPERT_WORKFLOW_STATUS_CHANGES_REQUESTED, GSF_HUB_EXPERT_WORKFLOW_STATUS_REJECTED ), true ) ) : ?>
				<p><strong><?php esc_html_e( 'Reviewer notes:', 'gsf-hub-expert-workflow' ); ?></strong> <?php echo esc_html( $review_notes ); ?></p>
			<?php endif; ?>
		</div>
		<?php echo $form_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
	</div>
	<?php
	return (string) ob_get_clean();
}
add_shortcode( 'gsf_hub_expert_application', 'gsf_hub_expert_workflow_render_application_shortcode' );

/**
 * Marks an application as approved and publishes the linked profile.
 *
 * @param int $user_id User ID.
 * @return int
 */
function gsf_hub_expert_workflow_approve_application( $user_id ) {
	if ( ! gsf_hub_expert_workflow_is_expert_user( $user_id ) ) {
		return 0;
	}

	return gsf_hub_expert_workflow_set_application_status( $user_id, GSF_HUB_EXPERT_WORKFLOW_STATUS_APPROVED, null );
}

/**
 * Persists the Pods application form into the linked expert profile draft.
 *
 * @param int        $id          Saved item ID.
 * @param array      $params      Save parameters.
 * @param null|Pods  $obj         Pods object.
 * @param array      $form_params Original form params.
 * @param string     $thank_you   Redirect URL.
 */
function gsf_hub_expert_workflow_handle_pods_application_submit( $id, $params, $obj, $form_params, $thank_you ) {
	unset( $obj, $thank_you );

	$form_signature = isset( $form_params['_pods_form'] ) ? sanitize_text_field( wp_unslash( $form_params['_pods_form'] ) ) : '';
	if ( gsf_hub_expert_workflow_get_application_form_signature() !== $form_signature ) {
		return;
	}

	if ( ! is_user_logged_in() || ! $id || empty( $params['pod'] ) || 'expert_directory' !== $params['pod'] ) {
		return;
	}

	$user_id = get_current_user_id();
	update_user_meta( $user_id, GSF_HUB_EXPERT_WORKFLOW_USER_FLAG, 1 );
	update_user_meta( $user_id, GSF_HUB_EXPERT_WORKFLOW_USER_PROFILE_POST, (int) $id );

	if ( ! get_user_meta( $user_id, GSF_HUB_EXPERT_WORKFLOW_USER_SUBMITTED_AT, true ) ) {
		update_user_meta( $user_id, GSF_HUB_EXPERT_WORKFLOW_USER_SUBMITTED_AT, current_time( 'mysql' ) );
	}

	$status = gsf_hub_expert_workflow_get_application_status( $user_id );
	if ( GSF_HUB_EXPERT_WORKFLOW_STATUS_APPROVED !== $status ) {
		gsf_hub_expert_workflow_assign_role_for_status( $user_id, GSF_HUB_EXPERT_WORKFLOW_STATUS_PENDING );
		update_user_meta( $user_id, GSF_HUB_EXPERT_WORKFLOW_USER_STATUS, GSF_HUB_EXPERT_WORKFLOW_STATUS_PENDING );
		delete_user_meta( $user_id, GSF_HUB_EXPERT_WORKFLOW_USER_REVIEW_NOTES );
		$status = GSF_HUB_EXPERT_WORKFLOW_STATUS_PENDING;
	}

	update_user_meta( $user_id, 'account_status', 'approved' );

	wp_update_post(
		array(
			'ID'          => (int) $id,
			'post_author' => $user_id,
			'post_status' => GSF_HUB_EXPERT_WORKFLOW_STATUS_APPROVED === $status ? 'publish' : 'draft',
		)
	);

	gsf_hub_expert_workflow_sync_profile_post( $user_id, GSF_HUB_EXPERT_WORKFLOW_STATUS_APPROVED === $status );
}
add_action( 'pods_api_processed_form', 'gsf_hub_expert_workflow_handle_pods_application_submit', 20, 5 );

/**
 * Keeps legacy user-meta applicants from losing their draft profile during admin edits.
 *
 * @param int     $user_id       User ID.
 * @param WP_User $old_user_data Previous user object.
 */
function gsf_hub_expert_workflow_sync_profile_after_user_update( $user_id, $old_user_data ) {
	unset( $old_user_data );

	if ( gsf_hub_expert_workflow_get_profile_post_id( $user_id ) || ! gsf_hub_expert_workflow_has_legacy_user_application_meta( $user_id ) ) {
		return;
	}

	$status = gsf_hub_expert_workflow_get_application_status( $user_id );
	gsf_hub_expert_workflow_sync_profile_post( $user_id, GSF_HUB_EXPERT_WORKFLOW_STATUS_APPROVED === $status );
}
add_action( 'profile_update', 'gsf_hub_expert_workflow_sync_profile_after_user_update', 20, 2 );

/**
 * Backward-compatibility hook for any remaining UM approval actions.
 *
 * @param int $user_id User ID.
 */
function gsf_hub_expert_workflow_handle_user_approved( $user_id ) {
	if ( gsf_hub_expert_workflow_is_expert_user( $user_id ) ) {
		gsf_hub_expert_workflow_approve_application( $user_id );
	}
}
add_action( 'um_after_user_is_approved', 'gsf_hub_expert_workflow_handle_user_approved', 20 );

/**
 * Builds an admin action URL for the custom expert workflow actions.
 *
 * @param string $action  Workflow action.
 * @param int    $user_id User ID.
 * @return string
 */
function gsf_hub_expert_workflow_get_admin_action_url( $action, $user_id ) {
	return wp_nonce_url(
		add_query_arg(
			array(
				'action'  => $action,
				'user_id' => (int) $user_id,
			),
			admin_url( 'admin-post.php' )
		),
		$action . '_' . (int) $user_id
	);
}

/**
 * Returns the custom review action URL for queue links.
 *
 * @param int    $user_id       User ID.
 * @param string $review_action Review action slug.
 * @return string
 */
function gsf_hub_expert_workflow_get_review_action_url( $user_id, $review_action ) {
	return wp_nonce_url(
		add_query_arg(
			array(
				'action'        => 'gsf_hub_review_expert_application',
				'user_id'       => (int) $user_id,
				'review_action' => $review_action,
			),
			admin_url( 'admin-post.php' )
		),
		'gsf_hub_review_expert_application_' . $review_action . '_' . (int) $user_id
	);
}

/**
 * Handles the admin review actions.
 */
function gsf_hub_expert_workflow_handle_admin_review_action() {
	if ( ! current_user_can( 'list_users' ) ) {
		wp_die( esc_html__( 'You do not have permission to review expert applications.', 'gsf-hub-expert-workflow' ) );
	}

	$user_id       = isset( $_REQUEST['user_id'] ) ? absint( wp_unslash( $_REQUEST['user_id'] ) ) : 0;
	$review_action = isset( $_REQUEST['review_action'] ) ? sanitize_key( wp_unslash( $_REQUEST['review_action'] ) ) : '';
	$review_notes  = isset( $_REQUEST['gsf_hub_reviewer_notes'] ) ? sanitize_textarea_field( wp_unslash( $_REQUEST['gsf_hub_reviewer_notes'] ) ) : '';

	if ( ! in_array( $review_action, array( 'approve', 'request_changes', 'reject' ), true ) ) {
		wp_die( esc_html__( 'The requested review action is not valid.', 'gsf-hub-expert-workflow' ) );
	}

	check_admin_referer( 'gsf_hub_review_expert_application_' . $review_action . '_' . $user_id );

	$profile_post    = 0;
	$notice_slug     = 'review_updated';
	$redirect_status = GSF_HUB_EXPERT_WORKFLOW_STATUS_PENDING;

	if ( 'approve' === $review_action ) {
		$profile_post    = gsf_hub_expert_workflow_set_application_status( $user_id, GSF_HUB_EXPERT_WORKFLOW_STATUS_APPROVED, $review_notes, get_current_user_id() );
		$notice_slug     = $profile_post ? 'approved' : 'approval_failed';
		$redirect_status = GSF_HUB_EXPERT_WORKFLOW_STATUS_APPROVED;
	} elseif ( 'request_changes' === $review_action ) {
		$profile_post    = gsf_hub_expert_workflow_set_application_status( $user_id, GSF_HUB_EXPERT_WORKFLOW_STATUS_CHANGES_REQUESTED, $review_notes, get_current_user_id() );
		$notice_slug     = $profile_post ? 'changes_requested' : 'review_failed';
		$redirect_status = GSF_HUB_EXPERT_WORKFLOW_STATUS_CHANGES_REQUESTED;
	} elseif ( 'reject' === $review_action ) {
		$profile_post    = gsf_hub_expert_workflow_set_application_status( $user_id, GSF_HUB_EXPERT_WORKFLOW_STATUS_REJECTED, $review_notes, get_current_user_id() );
		$notice_slug     = $profile_post ? 'rejected' : 'review_failed';
		$redirect_status = GSF_HUB_EXPERT_WORKFLOW_STATUS_REJECTED;
	}

	$redirect_url = isset( $_REQUEST['redirect_to'] ) ? esc_url_raw( wp_unslash( $_REQUEST['redirect_to'] ) ) : '';
	if ( ! $redirect_url ) {
		$redirect_url = add_query_arg(
			array(
				'page'               => 'gsf-hub-expert-applications',
				'application_status' => $redirect_status,
				'workflow_notice'    => $notice_slug,
			),
			admin_url( 'users.php' )
		);
	} else {
		$redirect_url = add_query_arg( 'workflow_notice', $notice_slug, $redirect_url );
	}

	wp_safe_redirect( $redirect_url );
	exit;
}
add_action( 'admin_post_gsf_hub_review_expert_application', 'gsf_hub_expert_workflow_handle_admin_review_action' );

/**
 * Adds the expert applications admin screen.
 */
function gsf_hub_expert_workflow_register_admin_page() {
	add_users_page(
		__( 'Expert Applications', 'gsf-hub-expert-workflow' ),
		__( 'Expert Applications', 'gsf-hub-expert-workflow' ),
		'list_users',
		'gsf-hub-expert-applications',
		'gsf_hub_expert_workflow_render_admin_page'
	);
}
add_action( 'admin_menu', 'gsf_hub_expert_workflow_register_admin_page' );

/**
 * Returns the current review status filter for the admin page.
 *
 * @return string
 */
function gsf_hub_expert_workflow_get_admin_filter_status() {
	$status = isset( $_GET['application_status'] ) ? sanitize_key( wp_unslash( $_GET['application_status'] ) ) : GSF_HUB_EXPERT_WORKFLOW_STATUS_PENDING;
	return in_array( $status, array( GSF_HUB_EXPERT_WORKFLOW_STATUS_PENDING, GSF_HUB_EXPERT_WORKFLOW_STATUS_APPROVED, GSF_HUB_EXPERT_WORKFLOW_STATUS_CHANGES_REQUESTED, GSF_HUB_EXPERT_WORKFLOW_STATUS_REJECTED, 'all' ), true ) ? $status : GSF_HUB_EXPERT_WORKFLOW_STATUS_PENDING;
}

/**
 * Returns application users for the admin page.
 *
 * @param string $status Filter status.
 * @return WP_User[]
 */
function gsf_hub_expert_workflow_get_application_users( $status = GSF_HUB_EXPERT_WORKFLOW_STATUS_PENDING ) {
	$meta_query = array(
		array(
			'key'     => GSF_HUB_EXPERT_WORKFLOW_USER_FLAG,
			'value'   => '1',
			'compare' => '=',
		),
	);

	if ( in_array( $status, gsf_hub_expert_workflow_get_valid_statuses(), true ) ) {
		$meta_query[] = array(
			'key'     => GSF_HUB_EXPERT_WORKFLOW_USER_STATUS,
			'value'   => $status,
			'compare' => '=',
		);
	}

	$query = new WP_User_Query(
		array(
			'number'     => 200,
			'orderby'    => 'registered',
			'order'      => 'DESC',
			'meta_query' => $meta_query,
		)
	);

	return array_filter(
		(array) $query->get_results(),
		static function( $user ) {
			return $user instanceof WP_User;
		}
	);
}

/**
 * Renders the expert applications admin page.
 */
function gsf_hub_expert_workflow_render_admin_page() {
	if ( ! current_user_can( 'list_users' ) ) {
		wp_die( esc_html__( 'You do not have permission to view this page.', 'gsf-hub-expert-workflow' ) );
	}

	$current_status = gsf_hub_expert_workflow_get_admin_filter_status();
	$applicants     = gsf_hub_expert_workflow_get_application_users( $current_status );
	$counts         = array(
		GSF_HUB_EXPERT_WORKFLOW_STATUS_PENDING            => count( gsf_hub_expert_workflow_get_application_users( GSF_HUB_EXPERT_WORKFLOW_STATUS_PENDING ) ),
		GSF_HUB_EXPERT_WORKFLOW_STATUS_CHANGES_REQUESTED  => count( gsf_hub_expert_workflow_get_application_users( GSF_HUB_EXPERT_WORKFLOW_STATUS_CHANGES_REQUESTED ) ),
		GSF_HUB_EXPERT_WORKFLOW_STATUS_REJECTED           => count( gsf_hub_expert_workflow_get_application_users( GSF_HUB_EXPERT_WORKFLOW_STATUS_REJECTED ) ),
		GSF_HUB_EXPERT_WORKFLOW_STATUS_APPROVED           => count( gsf_hub_expert_workflow_get_application_users( GSF_HUB_EXPERT_WORKFLOW_STATUS_APPROVED ) ),
		'all'                                             => count( gsf_hub_expert_workflow_get_application_users( 'all' ) ),
	);
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Expert Applications', 'gsf-hub-expert-workflow' ); ?></h1>
		<p><?php esc_html_e( 'Review expert roster applications, inspect the linked draft profile, and decide whether to approve, return for edits, or reject the application.', 'gsf-hub-expert-workflow' ); ?></p>
		<?php if ( isset( $_GET['workflow_notice'] ) && 'approved' === sanitize_key( wp_unslash( $_GET['workflow_notice'] ) ) ) : ?>
			<div class="notice notice-success"><p><?php esc_html_e( 'The expert application was approved and the public profile has been published.', 'gsf-hub-expert-workflow' ); ?></p></div>
		<?php elseif ( isset( $_GET['workflow_notice'] ) && 'changes_requested' === sanitize_key( wp_unslash( $_GET['workflow_notice'] ) ) ) : ?>
			<div class="notice notice-success"><p><?php esc_html_e( 'The application was returned for edits and the reviewer notes were saved.', 'gsf-hub-expert-workflow' ); ?></p></div>
		<?php elseif ( isset( $_GET['workflow_notice'] ) && 'rejected' === sanitize_key( wp_unslash( $_GET['workflow_notice'] ) ) ) : ?>
			<div class="notice notice-success"><p><?php esc_html_e( 'The application was rejected and the reviewer notes were saved.', 'gsf-hub-expert-workflow' ); ?></p></div>
		<?php elseif ( isset( $_GET['workflow_notice'] ) && 'approval_failed' === sanitize_key( wp_unslash( $_GET['workflow_notice'] ) ) ) : ?>
			<div class="notice notice-error"><p><?php esc_html_e( 'The expert application could not be approved. Check the linked profile and try again.', 'gsf-hub-expert-workflow' ); ?></p></div>
		<?php elseif ( isset( $_GET['workflow_notice'] ) && 'review_failed' === sanitize_key( wp_unslash( $_GET['workflow_notice'] ) ) ) : ?>
			<div class="notice notice-error"><p><?php esc_html_e( 'The review action could not be completed. Check the application and try again.', 'gsf-hub-expert-workflow' ); ?></p></div>
		<?php endif; ?>
		<ul class="subsubsub">
			<?php
			$links = array(
				GSF_HUB_EXPERT_WORKFLOW_STATUS_PENDING           => __( 'Pending Review', 'gsf-hub-expert-workflow' ),
				GSF_HUB_EXPERT_WORKFLOW_STATUS_CHANGES_REQUESTED => __( 'Changes Requested', 'gsf-hub-expert-workflow' ),
				GSF_HUB_EXPERT_WORKFLOW_STATUS_REJECTED          => __( 'Rejected', 'gsf-hub-expert-workflow' ),
				GSF_HUB_EXPERT_WORKFLOW_STATUS_APPROVED          => __( 'Approved', 'gsf-hub-expert-workflow' ),
				'all'                                            => __( 'All Expert Applicants', 'gsf-hub-expert-workflow' ),
			);
			$index = 0;
			foreach ( $links as $slug => $label ) :
				$url = add_query_arg(
					array(
						'page'               => 'gsf-hub-expert-applications',
						'application_status' => $slug,
					),
					admin_url( 'users.php' )
				);
				?>
				<li>
					<a href="<?php echo esc_url( $url ); ?>" class="<?php echo $current_status === $slug ? 'current' : ''; ?>">
						<?php echo esc_html( $label ); ?> <span class="count">(<?php echo esc_html( (string) $counts[ $slug ] ); ?>)</span>
					</a>
					<?php if ( $index < count( $links ) - 1 ) : ?>
						|
					<?php endif; ?>
				</li>
				<?php
				++$index;
			endforeach;
			?>
		</ul>
		<table class="widefat striped" style="margin-top: 1rem;">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Applicant', 'gsf-hub-expert-workflow' ); ?></th>
					<th><?php esc_html_e( 'Organization', 'gsf-hub-expert-workflow' ); ?></th>
					<th><?php esc_html_e( 'Country', 'gsf-hub-expert-workflow' ); ?></th>
					<th><?php esc_html_e( 'Submitted', 'gsf-hub-expert-workflow' ); ?></th>
					<th><?php esc_html_e( 'Linked Profile', 'gsf-hub-expert-workflow' ); ?></th>
					<th><?php esc_html_e( 'Reviewer Notes', 'gsf-hub-expert-workflow' ); ?></th>
					<th><?php esc_html_e( 'Actions', 'gsf-hub-expert-workflow' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php if ( empty( $applicants ) ) : ?>
					<tr>
						<td colspan="7"><?php esc_html_e( 'No expert applications matched this filter.', 'gsf-hub-expert-workflow' ); ?></td>
					</tr>
				<?php else : ?>
					<?php foreach ( $applicants as $user ) : ?>
						<?php
						$data             = gsf_hub_expert_workflow_get_application_data( $user->ID );
						$status           = gsf_hub_expert_workflow_get_application_status( $user->ID );
						$review_notes     = gsf_hub_expert_workflow_get_reviewer_notes( $user->ID );
						$submitted_at     = (string) get_user_meta( $user->ID, GSF_HUB_EXPERT_WORKFLOW_USER_SUBMITTED_AT, true );
						$profile_post     = gsf_hub_expert_workflow_get_profile_post_id( $user->ID );
						$profile_edit_url = $profile_post ? get_edit_post_link( $profile_post ) : '';
						$profile_view_url = $profile_post ? get_permalink( $profile_post ) : '';
						$status_label     = gsf_hub_expert_workflow_get_application_status_label( $status );
						$approve_url      = gsf_hub_expert_workflow_get_review_action_url( $user->ID, 'approve' );
						$request_url      = gsf_hub_expert_workflow_get_review_action_url( $user->ID, 'request_changes' );
						$reject_url       = gsf_hub_expert_workflow_get_review_action_url( $user->ID, 'reject' );
						?>
						<tr>
							<td>
								<strong><?php echo esc_html( $data['full_name'] ? $data['full_name'] : $user->display_name ); ?></strong><br>
								<a href="<?php echo esc_url( get_edit_user_link( $user->ID ) ); ?>"><?php echo esc_html( $user->user_email ); ?></a>
								<br><span class="description"><?php echo esc_html( $status_label ); ?></span>
							</td>
							<td><?php echo esc_html( $data['organization'] ? $data['organization'] : '—' ); ?></td>
							<td><?php echo esc_html( $data['country_name'] ? $data['country_name'] : '—' ); ?></td>
							<td><?php echo esc_html( $submitted_at ? mysql2date( 'F j, Y g:i a', $submitted_at ) : '—' ); ?></td>
							<td>
								<?php if ( $profile_post && $profile_edit_url ) : ?>
									<a href="<?php echo esc_url( $profile_edit_url ); ?>"><?php echo esc_html( get_the_title( $profile_post ) ); ?></a>
									<?php if ( 'publish' === get_post_status( $profile_post ) && $profile_view_url ) : ?>
										<br><a href="<?php echo esc_url( $profile_view_url ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'View public profile', 'gsf-hub-expert-workflow' ); ?></a>
									<?php endif; ?>
								<?php else : ?>
									—
								<?php endif; ?>
							</td>
							<td><?php echo esc_html( '' !== $review_notes ? $review_notes : '—' ); ?></td>
							<td>
								<a class="button button-secondary" href="<?php echo esc_url( get_edit_user_link( $user->ID ) ); ?>"><?php esc_html_e( 'Review User', 'gsf-hub-expert-workflow' ); ?></a>
								<?php if ( $profile_edit_url ) : ?>
									<a class="button button-secondary" href="<?php echo esc_url( $profile_edit_url ); ?>"><?php esc_html_e( 'Edit Draft', 'gsf-hub-expert-workflow' ); ?></a>
								<?php endif; ?>
								<?php if ( GSF_HUB_EXPERT_WORKFLOW_STATUS_PENDING === $status || GSF_HUB_EXPERT_WORKFLOW_STATUS_CHANGES_REQUESTED === $status || GSF_HUB_EXPERT_WORKFLOW_STATUS_REJECTED === $status ) : ?>
									<a class="button button-primary" href="<?php echo esc_url( $approve_url ); ?>"><?php esc_html_e( 'Approve and Publish', 'gsf-hub-expert-workflow' ); ?></a>
									<a class="button button-secondary" href="<?php echo esc_url( $request_url ); ?>"><?php esc_html_e( 'Return for Edits', 'gsf-hub-expert-workflow' ); ?></a>
									<a class="button button-secondary" href="<?php echo esc_url( $reject_url ); ?>"><?php esc_html_e( 'Reject', 'gsf-hub-expert-workflow' ); ?></a>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
				<?php endif; ?>
			</tbody>
		</table>
	</div>
	<?php
}

/**
 * Renders application details on the user profile edit screen.
 *
 * @param WP_User $user User object.
 */
function gsf_hub_expert_workflow_render_user_profile_section( $user ) {
	if ( ! $user instanceof WP_User || ! gsf_hub_expert_workflow_is_expert_user( $user->ID ) ) {
		return;
	}

	$data          = gsf_hub_expert_workflow_get_application_data( $user->ID );
	$submitted_at  = (string) get_user_meta( $user->ID, GSF_HUB_EXPERT_WORKFLOW_USER_SUBMITTED_AT, true );
	$profile_post  = gsf_hub_expert_workflow_get_profile_post_id( $user->ID );
	$status        = gsf_hub_expert_workflow_get_application_status( $user->ID );
	$review_notes  = gsf_hub_expert_workflow_get_reviewer_notes( $user->ID );
	?>
	<h2><?php esc_html_e( 'Expert Application', 'gsf-hub-expert-workflow' ); ?></h2>
	<?php if ( isset( $_GET['workflow_notice'] ) && 'approved' === sanitize_key( wp_unslash( $_GET['workflow_notice'] ) ) ) : ?>
		<div class="notice notice-success inline"><p><?php esc_html_e( 'The expert profile was approved and published.', 'gsf-hub-expert-workflow' ); ?></p></div>
	<?php elseif ( isset( $_GET['workflow_notice'] ) && 'changes_requested' === sanitize_key( wp_unslash( $_GET['workflow_notice'] ) ) ) : ?>
		<div class="notice notice-success inline"><p><?php esc_html_e( 'The application was returned for edits.', 'gsf-hub-expert-workflow' ); ?></p></div>
	<?php elseif ( isset( $_GET['workflow_notice'] ) && 'rejected' === sanitize_key( wp_unslash( $_GET['workflow_notice'] ) ) ) : ?>
		<div class="notice notice-success inline"><p><?php esc_html_e( 'The application was rejected.', 'gsf-hub-expert-workflow' ); ?></p></div>
	<?php endif; ?>
	<table class="form-table" role="presentation">
		<tbody>
			<tr>
				<th><?php esc_html_e( 'Application Status', 'gsf-hub-expert-workflow' ); ?></th>
				<td><?php echo esc_html( gsf_hub_expert_workflow_get_application_status_label( $status ) ); ?></td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Submitted', 'gsf-hub-expert-workflow' ); ?></th>
				<td><?php echo esc_html( $submitted_at ? mysql2date( 'F j, Y g:i a', $submitted_at ) : '—' ); ?></td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Organization', 'gsf-hub-expert-workflow' ); ?></th>
				<td><?php echo esc_html( $data['organization'] ? $data['organization'] : '—' ); ?></td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Job Title', 'gsf-hub-expert-workflow' ); ?></th>
				<td><?php echo esc_html( $data['job_title'] ? $data['job_title'] : '—' ); ?></td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Country or Territory', 'gsf-hub-expert-workflow' ); ?></th>
				<td><?php echo esc_html( $data['country_name'] ? $data['country_name'] : '—' ); ?></td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Expertise Areas', 'gsf-hub-expert-workflow' ); ?></th>
				<td><?php echo esc_html( $data['expertise_summary'] ? $data['expertise_summary'] : '—' ); ?></td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Short Bio', 'gsf-hub-expert-workflow' ); ?></th>
				<td><?php echo esc_html( $data['short_bio'] ? $data['short_bio'] : '—' ); ?></td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Website', 'gsf-hub-expert-workflow' ); ?></th>
				<td><?php echo esc_html( $data['website'] ? $data['website'] : '—' ); ?></td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'LinkedIn', 'gsf-hub-expert-workflow' ); ?></th>
				<td><?php echo esc_html( $data['linkedin'] ? $data['linkedin'] : '—' ); ?></td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Linked Directory Profile', 'gsf-hub-expert-workflow' ); ?></th>
				<td>
					<?php if ( $profile_post ) : ?>
						<a href="<?php echo esc_url( get_edit_post_link( $profile_post ) ); ?>"><?php echo esc_html( get_the_title( $profile_post ) ); ?></a>
						<?php if ( 'publish' === get_post_status( $profile_post ) ) : ?>
							<br><a href="<?php echo esc_url( get_permalink( $profile_post ) ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'View public profile', 'gsf-hub-expert-workflow' ); ?></a>
						<?php endif; ?>
					<?php else : ?>
						<?php esc_html_e( 'No linked profile yet.', 'gsf-hub-expert-workflow' ); ?>
					<?php endif; ?>
				</td>
			</tr>
			<tr>
				<th><label for="gsf-hub-reviewer-notes"><?php esc_html_e( 'Reviewer Notes', 'gsf-hub-expert-workflow' ); ?></label></th>
				<td>
					<textarea class="large-text" rows="5" id="gsf-hub-reviewer-notes" name="gsf_hub_reviewer_notes"><?php echo esc_textarea( $review_notes ); ?></textarea>
					<p class="description"><?php esc_html_e( 'These notes are shown to applicants when the application is returned for edits or rejected.', 'gsf-hub-expert-workflow' ); ?></p>
				</td>
			</tr>
		</tbody>
	</table>
	<p>
		<a class="button" href="<?php echo esc_url( admin_url( 'users.php?page=gsf-hub-expert-applications' ) ); ?>"><?php esc_html_e( 'Open Expert Applications Queue', 'gsf-hub-expert-workflow' ); ?></a>
		<?php if ( GSF_HUB_EXPERT_WORKFLOW_STATUS_PENDING === $status || GSF_HUB_EXPERT_WORKFLOW_STATUS_CHANGES_REQUESTED === $status || GSF_HUB_EXPERT_WORKFLOW_STATUS_REJECTED === $status ) : ?>
			<button type="submit" class="button" name="gsf_hub_review_action" value="request_changes"><?php esc_html_e( 'Return for Edits', 'gsf-hub-expert-workflow' ); ?></button>
			<button type="submit" class="button" name="gsf_hub_review_action" value="reject"><?php esc_html_e( 'Reject Application', 'gsf-hub-expert-workflow' ); ?></button>
			<button type="submit" class="button button-primary" name="gsf_hub_review_action" value="approve"><?php esc_html_e( 'Approve and Publish Profile', 'gsf-hub-expert-workflow' ); ?></button>
		<?php endif; ?>
	</p>
	<?php
}

/**
 * Handles review actions submitted from the user-profile edit screen.
 *
 * @param int $user_id User ID.
 */
function gsf_hub_expert_workflow_handle_profile_review_submission( $user_id ) {
	if ( ! current_user_can( 'list_users' ) || ! gsf_hub_expert_workflow_is_expert_user( $user_id ) ) {
		return;
	}

	$review_action = isset( $_POST['gsf_hub_review_action'] ) ? sanitize_key( wp_unslash( $_POST['gsf_hub_review_action'] ) ) : '';
	if ( ! in_array( $review_action, array( 'approve', 'request_changes', 'reject' ), true ) ) {
		return;
	}

	check_admin_referer( 'update-user_' . $user_id );

	$review_notes = isset( $_POST['gsf_hub_reviewer_notes'] ) ? sanitize_textarea_field( wp_unslash( $_POST['gsf_hub_reviewer_notes'] ) ) : '';
	$notice_slug  = 'review_failed';

	if ( 'approve' === $review_action ) {
		$profile_post = gsf_hub_expert_workflow_set_application_status( $user_id, GSF_HUB_EXPERT_WORKFLOW_STATUS_APPROVED, $review_notes, get_current_user_id() );
		$notice_slug  = $profile_post ? 'approved' : 'approval_failed';
	} elseif ( 'request_changes' === $review_action ) {
		$profile_post = gsf_hub_expert_workflow_set_application_status( $user_id, GSF_HUB_EXPERT_WORKFLOW_STATUS_CHANGES_REQUESTED, $review_notes, get_current_user_id() );
		$notice_slug  = $profile_post ? 'changes_requested' : 'review_failed';
	} elseif ( 'reject' === $review_action ) {
		$profile_post = gsf_hub_expert_workflow_set_application_status( $user_id, GSF_HUB_EXPERT_WORKFLOW_STATUS_REJECTED, $review_notes, get_current_user_id() );
		$notice_slug  = $profile_post ? 'rejected' : 'review_failed';
	}

	wp_safe_redirect( add_query_arg( 'workflow_notice', $notice_slug, get_edit_user_link( $user_id ) ) );
	exit;
}
add_action( 'edit_user_profile', 'gsf_hub_expert_workflow_render_user_profile_section' );
add_action( 'show_user_profile', 'gsf_hub_expert_workflow_render_user_profile_section' );
add_action( 'edit_user_profile_update', 'gsf_hub_expert_workflow_handle_profile_review_submission' );
add_action( 'personal_options_update', 'gsf_hub_expert_workflow_handle_profile_review_submission' );
