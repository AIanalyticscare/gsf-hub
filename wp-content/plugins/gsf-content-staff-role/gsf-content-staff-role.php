<?php
/**
 * Plugin Name: GSF Content Staff Role
 * Description: Creates and maintains the GSF Content Staff role for limited content administration.
 * Version: 1.0.0
 * Author: AI Analyticscare
 * Text Domain: gsf-content-staff-role
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const GSF_CONTENT_STAFF_ROLE = 'gsf_content_staff';

/**
 * Returns the capabilities granted to GSF Content Staff.
 *
 * @return array<string, bool>
 */
function gsf_content_staff_role_capabilities() {
	return array(
		'read'                   => true,
		'upload_files'           => true,
		'edit_posts'             => true,
		'edit_published_posts'   => true,
		'edit_others_posts'      => true,
		'publish_posts'          => true,
		'delete_posts'           => true,
		'delete_published_posts' => true,
		'delete_others_posts'    => true,
		'edit_pages'             => true,
		'edit_published_pages'   => true,
		'edit_others_pages'      => true,
		'level_0'                => true,
		'level_1'                => true,
		'gsf_manage_learn'       => true,
	);
}

/**
 * Creates or updates the WordPress role.
 */
function gsf_content_staff_role_sync_wp_role() {
	$capabilities = gsf_content_staff_role_capabilities();
	$role         = get_role( GSF_CONTENT_STAFF_ROLE );

	if ( ! $role ) {
		add_role( GSF_CONTENT_STAFF_ROLE, __( 'GSF Content Staff', 'gsf-content-staff-role' ), $capabilities );
		$role = get_role( GSF_CONTENT_STAFF_ROLE );
	}

	if ( ! $role ) {
		return;
	}

	foreach ( $capabilities as $capability => $grant ) {
		$role->add_cap( $capability, $grant );
	}

	$administrator = get_role( 'administrator' );
	if ( $administrator ) {
		$administrator->add_cap( 'gsf_manage_learn', true );
	}
}

/**
 * Creates or updates Ultimate Member role metadata when Ultimate Member is installed.
 */
function gsf_content_staff_role_sync_um_role() {
	$capabilities = gsf_content_staff_role_capabilities();
	$role_keys    = get_option( 'um_roles', array() );
	$role_keys    = is_array( $role_keys ) ? $role_keys : array();

	if ( ! in_array( GSF_CONTENT_STAFF_ROLE, $role_keys, true ) ) {
		$role_keys[] = GSF_CONTENT_STAFF_ROLE;
		update_option( 'um_roles', array_values( array_unique( $role_keys ) ) );
	}

	update_option(
		'um_role_' . GSF_CONTENT_STAFF_ROLE . '_meta',
		array(
			'name'                           => 'GSF Content Staff',
			'wp_capabilities'                => $capabilities,
			'_um_can_access_wpadmin'         => 1,
			'_um_can_not_see_adminbar'       => 0,
			'_um_can_edit_everyone'          => 0,
			'_um_can_delete_everyone'        => 0,
			'_um_can_edit_profile'           => 1,
			'_um_can_delete_profile'         => 0,
			'_um_after_login'                => 'redirect_admin',
			'_um_after_logout'               => 'redirect_home',
			'_um_default_homepage'           => 1,
			'_um_can_view_all'               => 1,
			'_um_can_make_private_profile'   => 0,
			'_um_can_access_private_profile' => 0,
			'_um_status'                     => 'approved',
			'_um_auto_approve_act'           => 'redirect_admin',
		)
	);

	if ( function_exists( 'UM' ) && method_exists( UM()->roles(), 'um_roles_init' ) ) {
		UM()->roles()->um_roles_init( wp_roles() );
	}
}

/**
 * Syncs all role data.
 */
function gsf_content_staff_role_sync() {
	gsf_content_staff_role_sync_wp_role();
	gsf_content_staff_role_sync_um_role();
}

register_activation_hook( __FILE__, 'gsf_content_staff_role_sync' );
add_action( 'admin_init', 'gsf_content_staff_role_sync' );

/**
 * Adds the settings page.
 */
function gsf_content_staff_role_admin_menu() {
	add_options_page(
		__( 'GSF Staff Role', 'gsf-content-staff-role' ),
		__( 'GSF Staff Role', 'gsf-content-staff-role' ),
		'manage_options',
		'gsf-content-staff-role',
		'gsf_content_staff_role_render_admin_page'
	);
}
add_action( 'admin_menu', 'gsf_content_staff_role_admin_menu' );

/**
 * Handles manual sync form submissions.
 */
function gsf_content_staff_role_handle_manual_sync() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You do not have permission to sync this role.', 'gsf-content-staff-role' ) );
	}

	check_admin_referer( 'gsf_content_staff_role_sync' );
	gsf_content_staff_role_sync();

	wp_safe_redirect(
		add_query_arg(
			array(
				'page'    => 'gsf-content-staff-role',
				'updated' => '1',
			),
			admin_url( 'options-general.php' )
		)
	);
	exit;
}
add_action( 'admin_post_gsf_content_staff_role_sync', 'gsf_content_staff_role_handle_manual_sync' );

/**
 * Renders the settings page.
 */
function gsf_content_staff_role_render_admin_page() {
	$role = get_role( GSF_CONTENT_STAFF_ROLE );
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'GSF Content Staff Role', 'gsf-content-staff-role' ); ?></h1>

		<?php if ( isset( $_GET['updated'] ) ) : ?>
			<div class="notice notice-success is-dismissible">
				<p><?php esc_html_e( 'The GSF Content Staff role has been synced.', 'gsf-content-staff-role' ); ?></p>
			</div>
		<?php endif; ?>

		<p><?php esc_html_e( 'This plugin creates a limited staff role for adding and editing GSF Hub content without granting access to plugins, themes, users, or site settings.', 'gsf-content-staff-role' ); ?></p>

		<h2><?php esc_html_e( 'Status', 'gsf-content-staff-role' ); ?></h2>
		<table class="widefat striped" style="max-width: 760px;">
			<tbody>
				<tr>
					<th scope="row"><?php esc_html_e( 'Role', 'gsf-content-staff-role' ); ?></th>
					<td><?php echo $role ? esc_html__( 'Created', 'gsf-content-staff-role' ) : esc_html__( 'Missing', 'gsf-content-staff-role' ); ?></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Ultimate Member admin access', 'gsf-content-staff-role' ); ?></th>
					<td><?php echo esc_html__( 'Enabled for this role when Ultimate Member is active.', 'gsf-content-staff-role' ); ?></td>
				</tr>
			</tbody>
		</table>

		<h2><?php esc_html_e( 'Capabilities', 'gsf-content-staff-role' ); ?></h2>
		<ul>
			<?php foreach ( array_keys( gsf_content_staff_role_capabilities() ) as $capability ) : ?>
				<li><code><?php echo esc_html( $capability ); ?></code></li>
			<?php endforeach; ?>
		</ul>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<?php wp_nonce_field( 'gsf_content_staff_role_sync' ); ?>
			<input type="hidden" name="action" value="gsf_content_staff_role_sync">
			<?php submit_button( __( 'Sync Role Now', 'gsf-content-staff-role' ) ); ?>
		</form>
	</div>
	<?php
}
