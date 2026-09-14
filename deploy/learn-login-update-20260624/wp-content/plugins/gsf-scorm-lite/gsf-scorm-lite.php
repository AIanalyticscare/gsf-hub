<?php
/**
 * Plugin Name: GSF SCORM Lite
 * Description: Lightweight SCORM 1.2 launcher and progress recorder for GSF Hub proof-of-concept courses.
 * Version: 0.2.0
 * Author: GSF Hub
 * Text Domain: gsf-scorm-lite
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'GSF_SCORM_LITE_VERSION', '0.2.0' );
define( 'GSF_SCORM_LITE_PLUGIN_FILE', __FILE__ );
define( 'GSF_SCORM_LITE_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'GSF_SCORM_LITE_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

/**
 * Returns the database table used for learner attempts.
 *
 * @return string
 */
function gsf_scorm_lite_table_name() {
	global $wpdb;

	return $wpdb->prefix . 'gsf_scorm_attempts';
}

/**
 * Returns the enrollment table name.
 *
 * @return string
 */
function gsf_scorm_lite_enrollments_table_name() {
	global $wpdb;

	return $wpdb->prefix . 'gsf_lms_enrollments';
}

/**
 * Returns the feedback table name.
 *
 * @return string
 */
function gsf_scorm_lite_feedback_table_name() {
	global $wpdb;

	return $wpdb->prefix . 'gsf_lms_feedback';
}

/**
 * Returns the certificate table name.
 *
 * @return string
 */
function gsf_scorm_lite_certificates_table_name() {
	global $wpdb;

	return $wpdb->prefix . 'gsf_lms_certificates';
}

/**
 * Creates the attempt table.
 */
function gsf_scorm_lite_activate() {
	gsf_scorm_lite_install_schema();
}
register_activation_hook( __FILE__, 'gsf_scorm_lite_activate' );

/**
 * Installs or updates plugin database tables and roles.
 */
function gsf_scorm_lite_install_schema() {
	global $wpdb;

	require_once ABSPATH . 'wp-admin/includes/upgrade.php';

	$attempts_table     = gsf_scorm_lite_table_name();
	$enrollments_table  = gsf_scorm_lite_enrollments_table_name();
	$feedback_table     = gsf_scorm_lite_feedback_table_name();
	$certificates_table = gsf_scorm_lite_certificates_table_name();
	$charset_collate = $wpdb->get_charset_collate();

	$sql = "CREATE TABLE {$attempts_table} (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		user_id bigint(20) unsigned NOT NULL DEFAULT 0,
		course_slug varchar(191) NOT NULL,
		lesson_status varchar(50) NOT NULL DEFAULT 'not attempted',
		completion_status varchar(50) NOT NULL DEFAULT '',
		score_raw varchar(50) NOT NULL DEFAULT '',
		score_min varchar(50) NOT NULL DEFAULT '',
		score_max varchar(50) NOT NULL DEFAULT '',
		lesson_location text NULL,
		suspend_data longtext NULL,
		total_time varchar(50) NOT NULL DEFAULT '',
		session_time varchar(50) NOT NULL DEFAULT '',
		scorm_data longtext NULL,
		created_at datetime NOT NULL,
		updated_at datetime NOT NULL,
		PRIMARY KEY  (id),
		UNIQUE KEY user_course (user_id, course_slug),
		KEY course_slug (course_slug)
	) {$charset_collate};";

	dbDelta( $sql );

	$sql = "CREATE TABLE {$enrollments_table} (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		user_id bigint(20) unsigned NOT NULL,
		course_id bigint(20) unsigned NOT NULL,
		scorm_slug varchar(191) NOT NULL DEFAULT '',
		status varchar(50) NOT NULL DEFAULT 'enrolled',
		score_raw varchar(50) NOT NULL DEFAULT '',
		enrolled_at datetime NOT NULL,
		started_at datetime NULL,
		completed_at datetime NULL,
		last_accessed_at datetime NULL,
		PRIMARY KEY  (id),
		UNIQUE KEY user_course (user_id, course_id),
		KEY course_id (course_id),
		KEY status (status)
	) {$charset_collate};";

	dbDelta( $sql );

	$sql = "CREATE TABLE {$feedback_table} (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		user_id bigint(20) unsigned NOT NULL,
		course_id bigint(20) unsigned NOT NULL,
		rating tinyint(3) unsigned NOT NULL DEFAULT 0,
		comments text NULL,
		created_at datetime NOT NULL,
		PRIMARY KEY  (id),
		UNIQUE KEY user_course (user_id, course_id),
		KEY course_id (course_id)
	) {$charset_collate};";

	dbDelta( $sql );

	$sql = "CREATE TABLE {$certificates_table} (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		user_id bigint(20) unsigned NOT NULL,
		course_id bigint(20) unsigned NOT NULL,
		certificate_code varchar(64) NOT NULL,
		issued_at datetime NOT NULL,
		PRIMARY KEY  (id),
		UNIQUE KEY user_course (user_id, course_id),
		UNIQUE KEY certificate_code (certificate_code)
	) {$charset_collate};";

	dbDelta( $sql );

	add_role(
		'gsf_student',
		__( 'GSF Student', 'gsf-scorm-lite' ),
		array(
			'read' => true,
		)
	);

	update_option( 'gsf_scorm_lite_db_version', GSF_SCORM_LITE_VERSION );
}

/**
 * Updates schema when plugin files change while already active.
 */
function gsf_scorm_lite_maybe_install_schema() {
	if ( get_option( 'gsf_scorm_lite_db_version' ) !== GSF_SCORM_LITE_VERSION ) {
		gsf_scorm_lite_install_schema();
	}
}
add_action( 'plugins_loaded', 'gsf_scorm_lite_maybe_install_schema' );

/**
 * Registers the GSF course post type.
 */
function gsf_scorm_lite_register_course_post_type() {
	register_post_type(
		'gsf_course',
		array(
			'labels'       => array(
				'name'          => __( 'GSF Courses', 'gsf-scorm-lite' ),
				'singular_name' => __( 'GSF Course', 'gsf-scorm-lite' ),
				'add_new_item'  => __( 'Add New GSF Course', 'gsf-scorm-lite' ),
				'edit_item'     => __( 'Edit GSF Course', 'gsf-scorm-lite' ),
			),
			'public'       => true,
			'menu_icon'    => 'dashicons-welcome-learn-more',
			'show_in_menu' => false,
			'supports'     => array( 'title', 'editor', 'excerpt', 'thumbnail' ),
			'has_archive'  => true,
			'rewrite'      => array( 'slug' => 'gsf-courses' ),
			'show_in_rest' => true,
		)
	);
}
add_action( 'init', 'gsf_scorm_lite_register_course_post_type' );

/**
 * Adds course settings metabox.
 */
function gsf_scorm_lite_add_course_metabox() {
	add_meta_box(
		'gsf_scorm_lite_course_settings',
		__( 'GSF LMS Settings', 'gsf-scorm-lite' ),
		'gsf_scorm_lite_render_course_metabox',
		'gsf_course',
		'side',
		'default'
	);
}
add_action( 'add_meta_boxes', 'gsf_scorm_lite_add_course_metabox' );

/**
 * Renders course settings metabox.
 *
 * @param WP_Post $post Course post.
 */
function gsf_scorm_lite_render_course_metabox( $post ) {
	$slug                = get_post_meta( $post->ID, '_gsf_scorm_slug', true );
	$duration            = get_post_meta( $post->ID, '_gsf_course_duration', true );
	$certificate_enabled = get_post_meta( $post->ID, '_gsf_certificate_enabled', true );
	$packages            = gsf_scorm_lite_get_packages();

	if ( empty( $slug ) && ! empty( $_GET['gsf_scorm_slug'] ) ) {
		$requested_slug = sanitize_title( wp_unslash( $_GET['gsf_scorm_slug'] ) );
		if ( in_array( $requested_slug, $packages, true ) ) {
			$slug = $requested_slug;
		}
	}

	wp_nonce_field( 'gsf_scorm_lite_course_settings', 'gsf_scorm_lite_course_nonce' );
	?>
	<p>
		<label for="gsf_scorm_slug"><strong><?php esc_html_e( 'SCORM package', 'gsf-scorm-lite' ); ?></strong></label>
		<select name="gsf_scorm_slug" id="gsf_scorm_slug" style="width:100%">
			<option value=""><?php esc_html_e( 'None', 'gsf-scorm-lite' ); ?></option>
			<?php foreach ( $packages as $package ) : ?>
				<option value="<?php echo esc_attr( $package ); ?>" <?php selected( $slug, $package ); ?>><?php echo esc_html( $package ); ?></option>
			<?php endforeach; ?>
		</select>
	</p>
	<p>
		<label for="gsf_course_duration"><strong><?php esc_html_e( 'Duration label', 'gsf-scorm-lite' ); ?></strong></label>
		<input type="text" name="gsf_course_duration" id="gsf_course_duration" value="<?php echo esc_attr( $duration ); ?>" class="widefat" placeholder="45 minutes" />
	</p>
	<p>
		<label>
			<input type="checkbox" name="gsf_certificate_enabled" value="1" <?php checked( $certificate_enabled, '1' ); ?> />
			<?php esc_html_e( 'Issue certificate on completion', 'gsf-scorm-lite' ); ?>
		</label>
	</p>
	<?php
}

/**
 * Saves course settings.
 *
 * @param int $post_id Post ID.
 */
function gsf_scorm_lite_save_course_metabox( $post_id ) {
	if ( ! isset( $_POST['gsf_scorm_lite_course_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['gsf_scorm_lite_course_nonce'] ) ), 'gsf_scorm_lite_course_settings' ) ) {
		return;
	}

	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}

	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	update_post_meta( $post_id, '_gsf_scorm_slug', sanitize_title( wp_unslash( $_POST['gsf_scorm_slug'] ?? '' ) ) );
	update_post_meta( $post_id, '_gsf_course_duration', sanitize_text_field( wp_unslash( $_POST['gsf_course_duration'] ?? '' ) ) );
	update_post_meta( $post_id, '_gsf_certificate_enabled', ! empty( $_POST['gsf_certificate_enabled'] ) ? '1' : '0' );
}
add_action( 'save_post_gsf_course', 'gsf_scorm_lite_save_course_metabox' );

/**
 * Gets a course linked to a SCORM slug.
 *
 * @param string $slug SCORM slug.
 * @return WP_Post|null
 */
function gsf_scorm_lite_get_course_by_scorm_slug( $slug ) {
	$courses = get_posts(
		array(
			'post_type'      => 'gsf_course',
			'post_status'    => 'publish',
			'posts_per_page' => 1,
			'meta_key'       => '_gsf_scorm_slug',
			'meta_value'     => sanitize_title( $slug ),
		)
	);

	return ! empty( $courses[0] ) ? $courses[0] : null;
}

/**
 * Gets a learner enrollment.
 *
 * @param int $user_id   User ID.
 * @param int $course_id Course ID.
 * @return array<string,mixed>
 */
function gsf_scorm_lite_get_enrollment( $user_id, $course_id ) {
	global $wpdb;

	$table = gsf_scorm_lite_enrollments_table_name();
	$row   = $wpdb->get_row(
		$wpdb->prepare(
			"SELECT * FROM {$table} WHERE user_id = %d AND course_id = %d",
			$user_id,
			$course_id
		),
		ARRAY_A
	);

	return is_array( $row ) ? $row : array();
}

/**
 * Enrolls a learner into a course if needed.
 *
 * @param int    $user_id   User ID.
 * @param int    $course_id Course ID.
 * @param string $status    Status.
 * @return void
 */
function gsf_scorm_lite_enroll_user( $user_id, $course_id, $status = 'enrolled' ) {
	global $wpdb;

	$existing = gsf_scorm_lite_get_enrollment( $user_id, $course_id );
	$table    = gsf_scorm_lite_enrollments_table_name();
	$now      = current_time( 'mysql' );
	$slug     = get_post_meta( $course_id, '_gsf_scorm_slug', true );

	if ( ! empty( $existing ) ) {
		return;
	}

	$wpdb->insert(
		$table,
		array(
			'user_id'          => $user_id,
			'course_id'        => $course_id,
			'scorm_slug'       => sanitize_title( $slug ),
			'status'           => sanitize_key( $status ),
			'enrolled_at'      => $now,
			'last_accessed_at' => $now,
		)
	);
}

/**
 * Updates an enrollment from SCORM progress.
 *
 * @param int    $user_id User ID.
 * @param string $slug    SCORM slug.
 * @param array  $data    SCORM data.
 */
function gsf_scorm_lite_sync_enrollment_from_scorm( $user_id, $slug, array $data ) {
	global $wpdb;

	$course = gsf_scorm_lite_get_course_by_scorm_slug( $slug );

	if ( ! $course ) {
		return;
	}

	gsf_scorm_lite_enroll_user( $user_id, $course->ID, 'in_progress' );

	$lesson_status = sanitize_key( $data['cmi.core.lesson_status'] ?? 'in_progress' );
	$status        = in_array( $lesson_status, array( 'completed', 'passed' ), true ) ? 'completed' : 'in_progress';
	$now           = current_time( 'mysql' );
	$payload       = array(
		'status'           => $status,
		'score_raw'        => sanitize_text_field( $data['cmi.core.score.raw'] ?? '' ),
		'last_accessed_at' => $now,
	);

	if ( 'in_progress' === $status ) {
		$payload['started_at'] = $now;
	}

	if ( 'completed' === $status ) {
		$payload['completed_at'] = $now;
		gsf_scorm_lite_maybe_issue_certificate( $user_id, $course->ID );
	}

	$wpdb->update(
		gsf_scorm_lite_enrollments_table_name(),
		$payload,
		array(
			'user_id'   => $user_id,
			'course_id' => $course->ID,
		)
	);
}

/**
 * Handles frontend enrollment.
 */
function gsf_scorm_lite_handle_enroll() {
	if ( ! is_user_logged_in() ) {
		wp_safe_redirect( gsf_scorm_lite_get_member_login_url( wp_get_referer() ?: home_url( '/' ) ) );
		exit;
	}

	$course_id = absint( $_POST['course_id'] ?? 0 );

	if ( ! $course_id || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ?? '' ) ), 'gsf_lms_enroll_' . $course_id ) ) {
		wp_die( esc_html__( 'Invalid enrollment request.', 'gsf-scorm-lite' ) );
	}

	gsf_scorm_lite_enroll_user( get_current_user_id(), $course_id );
	wp_safe_redirect( get_permalink( $course_id ) );
	exit;
}
add_action( 'admin_post_gsf_lms_enroll', 'gsf_scorm_lite_handle_enroll' );

/**
 * Returns the public member login URL when the GSF theme helper is available.
 *
 * @param string $redirect_to Redirect target after login.
 * @return string
 */
function gsf_scorm_lite_get_member_login_url( $redirect_to = '' ) {
	if ( function_exists( 'gsf_hub_get_member_login_url' ) ) {
		return gsf_hub_get_member_login_url( $redirect_to );
	}

	if ( function_exists( 'gsf_hub_expert_workflow_get_login_url' ) ) {
		return gsf_hub_expert_workflow_get_login_url( $redirect_to );
	}

	return wp_login_url( $redirect_to );
}

/**
 * Renders an enrollment button.
 *
 * @param int $course_id Course ID.
 * @return string
 */
function gsf_scorm_lite_enroll_button( $course_id ) {
	if ( ! is_user_logged_in() ) {
		return '<a class="button button--primary" href="' . esc_url( gsf_scorm_lite_get_member_login_url( get_permalink( $course_id ) ) ) . '">' . esc_html__( 'Log in to enroll', 'gsf-scorm-lite' ) . '</a>';
	}

	if ( gsf_scorm_lite_get_enrollment( get_current_user_id(), $course_id ) ) {
		return '<a class="button button--primary" href="' . esc_url( get_permalink( $course_id ) ) . '">' . esc_html__( 'Continue course', 'gsf-scorm-lite' ) . '</a>';
	}

	return '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="gsf-lms-enroll-form">'
		. '<input type="hidden" name="action" value="gsf_lms_enroll" />'
		. '<input type="hidden" name="course_id" value="' . esc_attr( $course_id ) . '" />'
		. wp_nonce_field( 'gsf_lms_enroll_' . $course_id, '_wpnonce', true, false )
		. '<button type="submit" class="button button--primary">' . esc_html__( 'Enroll', 'gsf-scorm-lite' ) . '</button>'
		. '</form>';
}

/**
 * Returns a status label.
 *
 * @param string $status Status key.
 * @return string
 */
function gsf_scorm_lite_status_label( $status ) {
	$labels = array(
		'enrolled'    => __( 'Enrolled', 'gsf-scorm-lite' ),
		'in_progress' => __( 'In progress', 'gsf-scorm-lite' ),
		'completed'   => __( 'Completed', 'gsf-scorm-lite' ),
		'failed'      => __( 'Failed', 'gsf-scorm-lite' ),
	);

	return $labels[ $status ] ?? ucwords( str_replace( '_', ' ', $status ) );
}

/**
 * Returns labels for survey application plan choices.
 *
 * @return array<string,string>
 */
function gsf_scorm_lite_application_plan_labels() {
	return array(
		'presentation' => __( 'Presentation or briefing session', 'gsf-scorm-lite' ),
		'processes'    => __( 'Integrate into work processes and share informally', 'gsf-scorm-lite' ),
		'tools'        => __( 'Develop or revise tools, guidelines, or SOPs', 'gsf-scorm-lite' ),
		'mentor'       => __( 'Mentor or coach colleagues', 'gsf-scorm-lite' ),
		'unsure'       => __( 'Unsure at this time', 'gsf-scorm-lite' ),
		'other'        => __( 'Other', 'gsf-scorm-lite' ),
	);
}

/**
 * Returns country choices for shared GSF user profile fields.
 *
 * @return array<string,string>
 */
function gsf_scorm_lite_country_choices() {
	return array(
		'afghanistan' => __( 'Afghanistan', 'gsf-scorm-lite' ),
		'albania' => __( 'Albania', 'gsf-scorm-lite' ),
		'algeria' => __( 'Algeria', 'gsf-scorm-lite' ),
		'andorra' => __( 'Andorra', 'gsf-scorm-lite' ),
		'angola' => __( 'Angola', 'gsf-scorm-lite' ),
		'antigua_barbuda' => __( 'Antigua and Barbuda', 'gsf-scorm-lite' ),
		'argentina' => __( 'Argentina', 'gsf-scorm-lite' ),
		'armenia' => __( 'Armenia', 'gsf-scorm-lite' ),
		'australia' => __( 'Australia', 'gsf-scorm-lite' ),
		'austria' => __( 'Austria', 'gsf-scorm-lite' ),
		'azerbaijan' => __( 'Azerbaijan', 'gsf-scorm-lite' ),
		'bahamas' => __( 'Bahamas', 'gsf-scorm-lite' ),
		'bahrain' => __( 'Bahrain', 'gsf-scorm-lite' ),
		'bangladesh' => __( 'Bangladesh', 'gsf-scorm-lite' ),
		'barbados' => __( 'Barbados', 'gsf-scorm-lite' ),
		'belarus' => __( 'Belarus', 'gsf-scorm-lite' ),
		'belgium' => __( 'Belgium', 'gsf-scorm-lite' ),
		'belize' => __( 'Belize', 'gsf-scorm-lite' ),
		'benin' => __( 'Benin', 'gsf-scorm-lite' ),
		'bhutan' => __( 'Bhutan', 'gsf-scorm-lite' ),
		'bolivia' => __( 'Bolivia', 'gsf-scorm-lite' ),
		'bosnia_herzegovina' => __( 'Bosnia and Herzegovina', 'gsf-scorm-lite' ),
		'botswana' => __( 'Botswana', 'gsf-scorm-lite' ),
		'brazil' => __( 'Brazil', 'gsf-scorm-lite' ),
		'brunei' => __( 'Brunei', 'gsf-scorm-lite' ),
		'bulgaria' => __( 'Bulgaria', 'gsf-scorm-lite' ),
		'burkina_faso' => __( 'Burkina Faso', 'gsf-scorm-lite' ),
		'burundi' => __( 'Burundi', 'gsf-scorm-lite' ),
		'cabo_verde' => __( 'Cabo Verde', 'gsf-scorm-lite' ),
		'cambodia' => __( 'Cambodia', 'gsf-scorm-lite' ),
		'cameroon' => __( 'Cameroon', 'gsf-scorm-lite' ),
		'canada' => __( 'Canada', 'gsf-scorm-lite' ),
		'central_african_republic' => __( 'Central African Republic', 'gsf-scorm-lite' ),
		'chad' => __( 'Chad', 'gsf-scorm-lite' ),
		'chile' => __( 'Chile', 'gsf-scorm-lite' ),
		'china' => __( 'China', 'gsf-scorm-lite' ),
		'colombia' => __( 'Colombia', 'gsf-scorm-lite' ),
		'comoros' => __( 'Comoros', 'gsf-scorm-lite' ),
		'congo' => __( 'Congo', 'gsf-scorm-lite' ),
		'costa_rica' => __( 'Costa Rica', 'gsf-scorm-lite' ),
		'cote_divoire' => __( "Cote d'Ivoire", 'gsf-scorm-lite' ),
		'croatia' => __( 'Croatia', 'gsf-scorm-lite' ),
		'cuba' => __( 'Cuba', 'gsf-scorm-lite' ),
		'cyprus' => __( 'Cyprus', 'gsf-scorm-lite' ),
		'czechia' => __( 'Czechia', 'gsf-scorm-lite' ),
		'democratic_republic_congo' => __( 'Democratic Republic of the Congo', 'gsf-scorm-lite' ),
		'denmark' => __( 'Denmark', 'gsf-scorm-lite' ),
		'djibouti' => __( 'Djibouti', 'gsf-scorm-lite' ),
		'dominica' => __( 'Dominica', 'gsf-scorm-lite' ),
		'dominican_republic' => __( 'Dominican Republic', 'gsf-scorm-lite' ),
		'ecuador' => __( 'Ecuador', 'gsf-scorm-lite' ),
		'egypt' => __( 'Egypt', 'gsf-scorm-lite' ),
		'el_salvador' => __( 'El Salvador', 'gsf-scorm-lite' ),
		'equatorial_guinea' => __( 'Equatorial Guinea', 'gsf-scorm-lite' ),
		'eritrea' => __( 'Eritrea', 'gsf-scorm-lite' ),
		'estonia' => __( 'Estonia', 'gsf-scorm-lite' ),
		'eswatini' => __( 'Eswatini', 'gsf-scorm-lite' ),
		'ethiopia' => __( 'Ethiopia', 'gsf-scorm-lite' ),
		'fiji' => __( 'Fiji', 'gsf-scorm-lite' ),
		'finland' => __( 'Finland', 'gsf-scorm-lite' ),
		'france' => __( 'France', 'gsf-scorm-lite' ),
		'gabon' => __( 'Gabon', 'gsf-scorm-lite' ),
		'gambia' => __( 'Gambia', 'gsf-scorm-lite' ),
		'georgia' => __( 'Georgia', 'gsf-scorm-lite' ),
		'germany' => __( 'Germany', 'gsf-scorm-lite' ),
		'ghana' => __( 'Ghana', 'gsf-scorm-lite' ),
		'greece' => __( 'Greece', 'gsf-scorm-lite' ),
		'grenada' => __( 'Grenada', 'gsf-scorm-lite' ),
		'guatemala' => __( 'Guatemala', 'gsf-scorm-lite' ),
		'guinea' => __( 'Guinea', 'gsf-scorm-lite' ),
		'guinea_bissau' => __( 'Guinea-Bissau', 'gsf-scorm-lite' ),
		'guyana' => __( 'Guyana', 'gsf-scorm-lite' ),
		'haiti' => __( 'Haiti', 'gsf-scorm-lite' ),
		'honduras' => __( 'Honduras', 'gsf-scorm-lite' ),
		'hungary' => __( 'Hungary', 'gsf-scorm-lite' ),
		'iceland' => __( 'Iceland', 'gsf-scorm-lite' ),
		'india' => __( 'India', 'gsf-scorm-lite' ),
		'indonesia' => __( 'Indonesia', 'gsf-scorm-lite' ),
		'iran' => __( 'Iran', 'gsf-scorm-lite' ),
		'iraq' => __( 'Iraq', 'gsf-scorm-lite' ),
		'ireland' => __( 'Ireland', 'gsf-scorm-lite' ),
		'israel' => __( 'Israel', 'gsf-scorm-lite' ),
		'italy' => __( 'Italy', 'gsf-scorm-lite' ),
		'jamaica' => __( 'Jamaica', 'gsf-scorm-lite' ),
		'japan' => __( 'Japan', 'gsf-scorm-lite' ),
		'jordan' => __( 'Jordan', 'gsf-scorm-lite' ),
		'kazakhstan' => __( 'Kazakhstan', 'gsf-scorm-lite' ),
		'kenya' => __( 'Kenya', 'gsf-scorm-lite' ),
		'kiribati' => __( 'Kiribati', 'gsf-scorm-lite' ),
		'kuwait' => __( 'Kuwait', 'gsf-scorm-lite' ),
		'kyrgyzstan' => __( 'Kyrgyzstan', 'gsf-scorm-lite' ),
		'laos' => __( 'Laos', 'gsf-scorm-lite' ),
		'latvia' => __( 'Latvia', 'gsf-scorm-lite' ),
		'lebanon' => __( 'Lebanon', 'gsf-scorm-lite' ),
		'lesotho' => __( 'Lesotho', 'gsf-scorm-lite' ),
		'liberia' => __( 'Liberia', 'gsf-scorm-lite' ),
		'libya' => __( 'Libya', 'gsf-scorm-lite' ),
		'liechtenstein' => __( 'Liechtenstein', 'gsf-scorm-lite' ),
		'lithuania' => __( 'Lithuania', 'gsf-scorm-lite' ),
		'luxembourg' => __( 'Luxembourg', 'gsf-scorm-lite' ),
		'madagascar' => __( 'Madagascar', 'gsf-scorm-lite' ),
		'malawi' => __( 'Malawi', 'gsf-scorm-lite' ),
		'malaysia' => __( 'Malaysia', 'gsf-scorm-lite' ),
		'maldives' => __( 'Maldives', 'gsf-scorm-lite' ),
		'mali' => __( 'Mali', 'gsf-scorm-lite' ),
		'malta' => __( 'Malta', 'gsf-scorm-lite' ),
		'marshall_islands' => __( 'Marshall Islands', 'gsf-scorm-lite' ),
		'mauritania' => __( 'Mauritania', 'gsf-scorm-lite' ),
		'mauritius' => __( 'Mauritius', 'gsf-scorm-lite' ),
		'mexico' => __( 'Mexico', 'gsf-scorm-lite' ),
		'micronesia' => __( 'Micronesia', 'gsf-scorm-lite' ),
		'moldova' => __( 'Moldova', 'gsf-scorm-lite' ),
		'monaco' => __( 'Monaco', 'gsf-scorm-lite' ),
		'mongolia' => __( 'Mongolia', 'gsf-scorm-lite' ),
		'montenegro' => __( 'Montenegro', 'gsf-scorm-lite' ),
		'morocco' => __( 'Morocco', 'gsf-scorm-lite' ),
		'mozambique' => __( 'Mozambique', 'gsf-scorm-lite' ),
		'myanmar' => __( 'Myanmar', 'gsf-scorm-lite' ),
		'namibia' => __( 'Namibia', 'gsf-scorm-lite' ),
		'nauru' => __( 'Nauru', 'gsf-scorm-lite' ),
		'nepal' => __( 'Nepal', 'gsf-scorm-lite' ),
		'netherlands' => __( 'Netherlands', 'gsf-scorm-lite' ),
		'new_zealand' => __( 'New Zealand', 'gsf-scorm-lite' ),
		'nicaragua' => __( 'Nicaragua', 'gsf-scorm-lite' ),
		'niger' => __( 'Niger', 'gsf-scorm-lite' ),
		'nigeria' => __( 'Nigeria', 'gsf-scorm-lite' ),
		'north_korea' => __( 'North Korea', 'gsf-scorm-lite' ),
		'north_macedonia' => __( 'North Macedonia', 'gsf-scorm-lite' ),
		'norway' => __( 'Norway', 'gsf-scorm-lite' ),
		'oman' => __( 'Oman', 'gsf-scorm-lite' ),
		'pakistan' => __( 'Pakistan', 'gsf-scorm-lite' ),
		'palau' => __( 'Palau', 'gsf-scorm-lite' ),
		'palestine' => __( 'Palestine', 'gsf-scorm-lite' ),
		'panama' => __( 'Panama', 'gsf-scorm-lite' ),
		'papua_new_guinea' => __( 'Papua New Guinea', 'gsf-scorm-lite' ),
		'paraguay' => __( 'Paraguay', 'gsf-scorm-lite' ),
		'peru' => __( 'Peru', 'gsf-scorm-lite' ),
		'philippines' => __( 'Philippines', 'gsf-scorm-lite' ),
		'poland' => __( 'Poland', 'gsf-scorm-lite' ),
		'portugal' => __( 'Portugal', 'gsf-scorm-lite' ),
		'qatar' => __( 'Qatar', 'gsf-scorm-lite' ),
		'romania' => __( 'Romania', 'gsf-scorm-lite' ),
		'russia' => __( 'Russia', 'gsf-scorm-lite' ),
		'rwanda' => __( 'Rwanda', 'gsf-scorm-lite' ),
		'saint_kitts_nevis' => __( 'Saint Kitts and Nevis', 'gsf-scorm-lite' ),
		'saint_lucia' => __( 'Saint Lucia', 'gsf-scorm-lite' ),
		'saint_vincent_grenadines' => __( 'Saint Vincent and the Grenadines', 'gsf-scorm-lite' ),
		'samoa' => __( 'Samoa', 'gsf-scorm-lite' ),
		'san_marino' => __( 'San Marino', 'gsf-scorm-lite' ),
		'sao_tome_principe' => __( 'Sao Tome and Principe', 'gsf-scorm-lite' ),
		'saudi_arabia' => __( 'Saudi Arabia', 'gsf-scorm-lite' ),
		'senegal' => __( 'Senegal', 'gsf-scorm-lite' ),
		'serbia' => __( 'Serbia', 'gsf-scorm-lite' ),
		'seychelles' => __( 'Seychelles', 'gsf-scorm-lite' ),
		'sierra_leone' => __( 'Sierra Leone', 'gsf-scorm-lite' ),
		'singapore' => __( 'Singapore', 'gsf-scorm-lite' ),
		'slovakia' => __( 'Slovakia', 'gsf-scorm-lite' ),
		'slovenia' => __( 'Slovenia', 'gsf-scorm-lite' ),
		'solomon_islands' => __( 'Solomon Islands', 'gsf-scorm-lite' ),
		'somalia' => __( 'Somalia', 'gsf-scorm-lite' ),
		'south_africa' => __( 'South Africa', 'gsf-scorm-lite' ),
		'south_korea' => __( 'South Korea', 'gsf-scorm-lite' ),
		'south_sudan' => __( 'South Sudan', 'gsf-scorm-lite' ),
		'spain' => __( 'Spain', 'gsf-scorm-lite' ),
		'sri_lanka' => __( 'Sri Lanka', 'gsf-scorm-lite' ),
		'sudan' => __( 'Sudan', 'gsf-scorm-lite' ),
		'suriname' => __( 'Suriname', 'gsf-scorm-lite' ),
		'sweden' => __( 'Sweden', 'gsf-scorm-lite' ),
		'switzerland' => __( 'Switzerland', 'gsf-scorm-lite' ),
		'syria' => __( 'Syria', 'gsf-scorm-lite' ),
		'tajikistan' => __( 'Tajikistan', 'gsf-scorm-lite' ),
		'tanzania' => __( 'Tanzania', 'gsf-scorm-lite' ),
		'thailand' => __( 'Thailand', 'gsf-scorm-lite' ),
		'timor_leste' => __( 'Timor-Leste', 'gsf-scorm-lite' ),
		'togo' => __( 'Togo', 'gsf-scorm-lite' ),
		'tonga' => __( 'Tonga', 'gsf-scorm-lite' ),
		'trinidad_tobago' => __( 'Trinidad and Tobago', 'gsf-scorm-lite' ),
		'tunisia' => __( 'Tunisia', 'gsf-scorm-lite' ),
		'turkey' => __( 'Turkey', 'gsf-scorm-lite' ),
		'turkmenistan' => __( 'Turkmenistan', 'gsf-scorm-lite' ),
		'tuvalu' => __( 'Tuvalu', 'gsf-scorm-lite' ),
		'uganda' => __( 'Uganda', 'gsf-scorm-lite' ),
		'ukraine' => __( 'Ukraine', 'gsf-scorm-lite' ),
		'united_arab_emirates' => __( 'United Arab Emirates', 'gsf-scorm-lite' ),
		'united_kingdom' => __( 'United Kingdom', 'gsf-scorm-lite' ),
		'united_states' => __( 'United States', 'gsf-scorm-lite' ),
		'uruguay' => __( 'Uruguay', 'gsf-scorm-lite' ),
		'uzbekistan' => __( 'Uzbekistan', 'gsf-scorm-lite' ),
		'vanuatu' => __( 'Vanuatu', 'gsf-scorm-lite' ),
		'vatican_city' => __( 'Vatican City', 'gsf-scorm-lite' ),
		'venezuela' => __( 'Venezuela', 'gsf-scorm-lite' ),
		'vietnam' => __( 'Vietnam', 'gsf-scorm-lite' ),
		'yemen' => __( 'Yemen', 'gsf-scorm-lite' ),
		'zambia' => __( 'Zambia', 'gsf-scorm-lite' ),
		'zimbabwe' => __( 'Zimbabwe', 'gsf-scorm-lite' ),
		'other' => __( 'Other', 'gsf-scorm-lite' ),
	);
}

/**
 * Returns shared GSF profile field definitions.
 *
 * @return array<string,array<string,mixed>>
 */
function gsf_scorm_lite_profile_fields() {
	return array(
		'gsf_sex'       => array(
			'label'   => __( 'Sex', 'gsf-scorm-lite' ),
			'type'    => 'select',
			'choices' => array(
				'male'   => __( 'Male', 'gsf-scorm-lite' ),
				'female' => __( 'Female', 'gsf-scorm-lite' ),
			),
		),
		'gsf_age'       => array(
			'label'   => __( 'Age', 'gsf-scorm-lite' ),
			'type'    => 'select',
			'choices' => array(
				'18-24' => __( '18-24', 'gsf-scorm-lite' ),
				'25-44' => __( '25-44', 'gsf-scorm-lite' ),
				'45-64' => __( '45-64', 'gsf-scorm-lite' ),
				'65+'   => __( '65 years and over', 'gsf-scorm-lite' ),
			),
		),
		'gsf_org_type'  => array(
			'label'       => __( 'Organization type', 'gsf-scorm-lite' ),
			'type'        => 'select',
			'other_key'   => 'gsf_org_type_other',
			'other_label' => __( 'Other organization type', 'gsf-scorm-lite' ),
			'choices'     => array(
				'regional_ngo'             => __( 'Regional NGO', 'gsf-scorm-lite' ),
				'national_conservation_tf' => __( 'National Conservation Trust Fund', 'gsf-scorm-lite' ),
				'national_environmental'   => __( 'National Environmental Organization', 'gsf-scorm-lite' ),
				'local_community_env'      => __( 'Local / Community Environmental Organization', 'gsf-scorm-lite' ),
				'womens_organization'      => __( "Women's Organization", 'gsf-scorm-lite' ),
				'local_non_profit'         => __( 'Local non-profit Organization/Association', 'gsf-scorm-lite' ),
				'academia'                 => __( 'Academia', 'gsf-scorm-lite' ),
				'ngo'                      => __( 'NGO', 'gsf-scorm-lite' ),
				'government'               => __( 'Government', 'gsf-scorm-lite' ),
				'private_sector'           => __( 'Private Sector', 'gsf-scorm-lite' ),
				'other'                    => __( 'Other', 'gsf-scorm-lite' ),
			),
		),
		'gsf_location'  => array(
			'label'       => __( 'Country', 'gsf-scorm-lite' ),
			'type'        => 'select',
			'other_key'   => 'gsf_location_other',
			'other_label' => __( 'Other location', 'gsf-scorm-lite' ),
			'choices'     => gsf_scorm_lite_country_choices(),
		),
	);
}

/**
 * Returns a display value for a student profile field.
 *
 * @param int    $user_id User ID.
 * @param string $key     Meta key.
 * @return string
 */
function gsf_scorm_lite_get_profile_display_value( $user_id, $key ) {
	$fields = gsf_scorm_lite_profile_fields();
	$field  = $fields[ $key ] ?? null;

	if ( empty( $field ) ) {
		return '';
	}

	$value   = (string) get_user_meta( $user_id, $key, true );
	$choices = (array) ( $field['choices'] ?? array() );

	if ( 'other' === $value && ! empty( $field['other_key'] ) ) {
		$other = (string) get_user_meta( $user_id, (string) $field['other_key'], true );
		return '' !== $other ? $other : ( $choices['other'] ?? __( 'Other', 'gsf-scorm-lite' ) );
	}

	return $choices[ $value ] ?? $value;
}

/**
 * Returns sex choices for Ultimate Member dropdowns.
 *
 * @return array<string,string>
 */
function gsf_scorm_lite_um_sex_options() {
	$fields = gsf_scorm_lite_profile_fields();

	return (array) ( $fields['gsf_sex']['choices'] ?? array() );
}

/**
 * Returns age choices for Ultimate Member dropdowns.
 *
 * @return array<string,string>
 */
function gsf_scorm_lite_um_age_options() {
	$fields = gsf_scorm_lite_profile_fields();

	return (array) ( $fields['gsf_age']['choices'] ?? array() );
}

/**
 * Returns organization type choices for Ultimate Member dropdowns.
 *
 * @return array<string,string>
 */
function gsf_scorm_lite_um_org_type_options() {
	$fields = gsf_scorm_lite_profile_fields();

	return (array) ( $fields['gsf_org_type']['choices'] ?? array() );
}

/**
 * Returns location/country choices for Ultimate Member dropdowns.
 *
 * @return array<string,string>
 */
function gsf_scorm_lite_um_location_options() {
	$fields = gsf_scorm_lite_profile_fields();

	return (array) ( $fields['gsf_location']['choices'] ?? array() );
}

/**
 * Finds the active Ultimate Member registration form ID.
 *
 * @return int
 */
function gsf_scorm_lite_get_um_registration_form_id() {
	$register_page = get_page_by_path( 'register' );

	if ( $register_page instanceof WP_Post && preg_match( '/\[ultimatemember[^\]]*form_id=["\']?(\d+)/', (string) $register_page->post_content, $matches ) ) {
		return absint( $matches[1] );
	}

	$forms = get_posts(
		array(
			'post_type'      => 'um_form',
			'post_status'    => 'any',
			'posts_per_page' => 1,
			'meta_key'       => '_um_mode',
			'meta_value'     => 'register',
			'fields'         => 'ids',
		)
	);

	return ! empty( $forms[0] ) ? absint( $forms[0] ) : 0;
}

/**
 * Returns shared GSF fields formatted for an Ultimate Member registration form.
 *
 * @return array<string,array<string,mixed>>
 */
function gsf_scorm_lite_um_registration_fields() {
	$base = array(
		'required'   => 1,
		'public'     => 1,
		'editable'   => true,
		'in_row'     => '_um_row_1',
		'in_sub_row' => '0',
		'in_column'  => 1,
		'in_group'   => '',
	);

	return array(
		'gsf_sex'                => array_merge(
			$base,
			array(
				'title'                          => __( 'Sex', 'gsf-scorm-lite' ),
				'metakey'                        => 'gsf_sex',
				'type'                           => 'select',
				'label'                          => __( 'Sex', 'gsf-scorm-lite' ),
				'placeholder'                    => __( 'Select', 'gsf-scorm-lite' ),
				'custom_dropdown_options_source' => 'gsf_scorm_lite_um_sex_options',
				'position'                       => 6,
			)
		),
		'gsf_age'                => array_merge(
			$base,
			array(
				'title'                          => __( 'Age', 'gsf-scorm-lite' ),
				'metakey'                        => 'gsf_age',
				'type'                           => 'select',
				'label'                          => __( 'Age', 'gsf-scorm-lite' ),
				'placeholder'                    => __( 'Select', 'gsf-scorm-lite' ),
				'custom_dropdown_options_source' => 'gsf_scorm_lite_um_age_options',
				'position'                       => 7,
			)
		),
		'gsf_org_type'           => array_merge(
			$base,
			array(
				'title'                          => __( 'Organization type', 'gsf-scorm-lite' ),
				'metakey'                        => 'gsf_org_type',
				'type'                           => 'select',
				'label'                          => __( 'Organization type', 'gsf-scorm-lite' ),
				'placeholder'                    => __( 'Select', 'gsf-scorm-lite' ),
				'custom_dropdown_options_source' => 'gsf_scorm_lite_um_org_type_options',
				'position'                       => 8,
			)
		),
		'gsf_org_type_other'     => array(
			'title'      => __( 'Other organization type', 'gsf-scorm-lite' ),
			'metakey'    => 'gsf_org_type_other',
			'type'       => 'text',
			'label'      => __( 'Other organization type', 'gsf-scorm-lite' ),
			'required'   => 0,
			'public'     => 1,
			'editable'   => true,
			'position'   => 9,
			'in_row'     => '_um_row_1',
			'in_sub_row' => '0',
			'in_column'  => 1,
			'in_group'   => '',
		),
		'gsf_location'           => array_merge(
			$base,
			array(
				'title'                          => __( 'Country', 'gsf-scorm-lite' ),
				'metakey'                        => 'gsf_location',
				'type'                           => 'select',
				'label'                          => __( 'Country', 'gsf-scorm-lite' ),
				'placeholder'                    => __( 'Select country', 'gsf-scorm-lite' ),
				'custom_dropdown_options_source' => 'gsf_scorm_lite_um_location_options',
				'position'                       => 10,
			)
		),
	);
}

/**
 * Adds shared GSF profile fields to the default Ultimate Member registration form.
 */
function gsf_scorm_lite_sync_um_registration_fields() {
	if ( ! post_type_exists( 'um_form' ) ) {
		return;
	}

	$form_id = gsf_scorm_lite_get_um_registration_form_id();

	if ( ! $form_id ) {
		return;
	}

	$fields = get_post_meta( $form_id, '_um_custom_fields', true );

	if ( ! is_array( $fields ) ) {
		return;
	}

	$changed = false;

	foreach ( gsf_scorm_lite_um_registration_fields() as $key => $field ) {
		if ( ! isset( $fields[ $key ] ) ) {
			$fields[ $key ] = $field;
			$changed        = true;
		}
	}

	if ( $changed ) {
		update_post_meta( $form_id, '_um_custom_fields', $fields );
	}
}
add_action( 'init', 'gsf_scorm_lite_sync_um_registration_fields', 20 );

/**
 * Renders shared GSF profile fields in wp-admin user profiles.
 *
 * @param WP_User $user User being edited.
 */
function gsf_scorm_lite_render_user_profile_fields( $user ) {
	?>
	<h2><?php esc_html_e( 'GSF Profile Information', 'gsf-scorm-lite' ); ?></h2>
	<p><?php esc_html_e( 'These shared profile details can be used by learning reports, expert roster workflows, and other GSF Hub features.', 'gsf-scorm-lite' ); ?></p>
	<table class="form-table" role="presentation">
		<?php foreach ( gsf_scorm_lite_profile_fields() as $key => $field ) : ?>
			<?php $value = (string) get_user_meta( $user->ID, $key, true ); ?>
			<tr>
				<th><label for="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $field['label'] ); ?></label></th>
				<td>
					<select name="<?php echo esc_attr( $key ); ?>" id="<?php echo esc_attr( $key ); ?>">
						<option value=""><?php esc_html_e( 'Select', 'gsf-scorm-lite' ); ?></option>
						<?php foreach ( (array) $field['choices'] as $choice_key => $choice_label ) : ?>
							<option value="<?php echo esc_attr( $choice_key ); ?>" <?php selected( $value, $choice_key ); ?>><?php echo esc_html( $choice_label ); ?></option>
						<?php endforeach; ?>
					</select>
					<?php if ( ! empty( $field['other_key'] ) ) : ?>
						<?php $other_value = (string) get_user_meta( $user->ID, (string) $field['other_key'], true ); ?>
						<p>
							<label for="<?php echo esc_attr( $field['other_key'] ); ?>"><?php echo esc_html( $field['other_label'] ); ?></label><br />
							<input type="text" name="<?php echo esc_attr( $field['other_key'] ); ?>" id="<?php echo esc_attr( $field['other_key'] ); ?>" class="regular-text" value="<?php echo esc_attr( $other_value ); ?>" />
						</p>
					<?php endif; ?>
				</td>
			</tr>
		<?php endforeach; ?>
	</table>
	<?php
}
add_action( 'show_user_profile', 'gsf_scorm_lite_render_user_profile_fields' );
add_action( 'edit_user_profile', 'gsf_scorm_lite_render_user_profile_fields' );

/**
 * Saves student profile fields from wp-admin user profiles.
 *
 * @param int $user_id User ID.
 */
function gsf_scorm_lite_save_user_profile_fields( $user_id ) {
	if ( ! current_user_can( 'edit_user', $user_id ) ) {
		return;
	}

	foreach ( gsf_scorm_lite_profile_fields() as $key => $field ) {
		$choices = (array) ( $field['choices'] ?? array() );
		$value   = sanitize_key( wp_unslash( $_POST[ $key ] ?? '' ) );

		if ( '' !== $value && ! array_key_exists( $value, $choices ) ) {
			$value = '';
		}

		update_user_meta( $user_id, $key, $value );

		if ( ! empty( $field['other_key'] ) ) {
			update_user_meta( $user_id, (string) $field['other_key'], sanitize_text_field( wp_unslash( $_POST[ $field['other_key'] ] ?? '' ) ) );
		}
	}
}
add_action( 'personal_options_update', 'gsf_scorm_lite_save_user_profile_fields' );
add_action( 'edit_user_profile_update', 'gsf_scorm_lite_save_user_profile_fields' );

/**
 * Parses a stored feedback survey JSON payload.
 *
 * @param string $comments Stored comments field.
 * @return array<string,mixed>
 */
function gsf_scorm_lite_parse_feedback_survey( $comments ) {
	$decoded = json_decode( (string) $comments, true );

	if ( is_array( $decoded ) ) {
		return $decoded;
	}

	return array(
		'takeaway' => (string) $comments,
	);
}

/**
 * Renders the course catalog.
 *
 * @return string
 */
function gsf_scorm_lite_catalog_shortcode() {
	$courses = get_posts(
		array(
			'post_type'      => 'gsf_course',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'orderby'        => 'menu_order title',
			'order'          => 'ASC',
		)
	);

	if ( empty( $courses ) ) {
		return '<p>' . esc_html__( 'No courses are available yet.', 'gsf-scorm-lite' ) . '</p>';
	}

	$out = '<div class="gsf-lms-course-grid">';

	foreach ( $courses as $course ) {
		$duration = get_post_meta( $course->ID, '_gsf_course_duration', true );
		$out     .= '<article class="gsf-lms-course-card">';
		$out     .= get_the_post_thumbnail( $course->ID, 'medium_large', array( 'class' => 'gsf-lms-course-card__image' ) );
		$out     .= '<h3><a href="' . esc_url( get_permalink( $course ) ) . '">' . esc_html( get_the_title( $course ) ) . '</a></h3>';

		if ( ! empty( $duration ) ) {
			$out .= '<p class="gsf-lms-meta">' . esc_html( $duration ) . '</p>';
		}

		$out .= '<p>' . esc_html( get_the_excerpt( $course ) ) . '</p>';
		$out .= gsf_scorm_lite_enroll_button( $course->ID );
		$out .= '</article>';
	}

	$out .= '</div>';

	return $out;
}
add_shortcode( 'gsf_lms_catalog', 'gsf_scorm_lite_catalog_shortcode' );

/**
 * Renders learner dashboard.
 *
 * @return string
 */
function gsf_scorm_lite_dashboard_shortcode() {
	if ( ! is_user_logged_in() ) {
		return '<p><a href="' . esc_url( gsf_scorm_lite_get_member_login_url( get_permalink() ) ) . '">' . esc_html__( 'Log in to view your courses.', 'gsf-scorm-lite' ) . '</a></p>';
	}

	global $wpdb;

	$table       = gsf_scorm_lite_enrollments_table_name();
	$enrollments = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT * FROM {$table} WHERE user_id = %d ORDER BY last_accessed_at DESC, enrolled_at DESC",
			get_current_user_id()
		),
		ARRAY_A
	);

	if ( empty( $enrollments ) ) {
		return '<p>' . esc_html__( 'You are not enrolled in any courses yet.', 'gsf-scorm-lite' ) . '</p>';
	}

	$out = '<div class="gsf-lms-dashboard"><table><thead><tr><th>' . esc_html__( 'Course', 'gsf-scorm-lite' ) . '</th><th>' . esc_html__( 'Status', 'gsf-scorm-lite' ) . '</th><th>' . esc_html__( 'Score', 'gsf-scorm-lite' ) . '</th><th></th></tr></thead><tbody>';

	foreach ( $enrollments as $enrollment ) {
		$course = get_post( (int) $enrollment['course_id'] );

		if ( ! $course ) {
			continue;
		}

		$out .= '<tr>';
		$out .= '<td>' . esc_html( get_the_title( $course ) ) . '</td>';
		$out .= '<td>' . esc_html( gsf_scorm_lite_status_label( $enrollment['status'] ) ) . '</td>';
		$out .= '<td>' . esc_html( $enrollment['score_raw'] ) . '</td>';
		$out .= '<td><a href="' . esc_url( get_permalink( $course ) ) . '">' . esc_html__( 'Open', 'gsf-scorm-lite' ) . '</a></td>';
		$out .= '</tr>';
	}

	$out .= '</tbody></table></div>';

	return $out;
}
add_shortcode( 'gsf_lms_dashboard', 'gsf_scorm_lite_dashboard_shortcode' );

/**
 * Issues a certificate if the course allows it.
 *
 * @param int $user_id   User ID.
 * @param int $course_id Course ID.
 * @return array<string,mixed>
 */
function gsf_scorm_lite_maybe_issue_certificate( $user_id, $course_id ) {
	global $wpdb;

	if ( '1' !== get_post_meta( $course_id, '_gsf_certificate_enabled', true ) ) {
		return array();
	}

	$table    = gsf_scorm_lite_certificates_table_name();
	$existing = $wpdb->get_row(
		$wpdb->prepare(
			"SELECT * FROM {$table} WHERE user_id = %d AND course_id = %d",
			$user_id,
			$course_id
		),
		ARRAY_A
	);

	if ( is_array( $existing ) ) {
		return $existing;
	}

	$code = strtoupper( wp_generate_password( 12, false, false ) );

	$wpdb->insert(
		$table,
		array(
			'user_id'          => $user_id,
			'course_id'        => $course_id,
			'certificate_code' => $code,
			'issued_at'        => current_time( 'mysql' ),
		)
	);

	return $wpdb->get_row(
		$wpdb->prepare(
			"SELECT * FROM {$table} WHERE certificate_code = %s",
			$code
		),
		ARRAY_A
	) ?: array();
}

/**
 * Gets a user's certificate for a course.
 *
 * @param int $user_id   User ID.
 * @param int $course_id Course ID.
 * @return array<string,mixed>
 */
function gsf_scorm_lite_get_certificate( $user_id, $course_id ) {
	global $wpdb;

	$table = gsf_scorm_lite_certificates_table_name();
	$row   = $wpdb->get_row(
		$wpdb->prepare(
			"SELECT * FROM {$table} WHERE user_id = %d AND course_id = %d",
			$user_id,
			$course_id
		),
		ARRAY_A
	);

	return is_array( $row ) ? $row : array();
}

/**
 * Renders a learner certificate card.
 *
 * @param int                  $user_id      User ID.
 * @param int                  $course_id    Course ID.
 * @param array<string,string> $certificate Certificate row.
 * @return string
 */
function gsf_scorm_lite_render_certificate_card( $user_id, $course_id, array $certificate ) {
	$user     = get_userdata( $user_id );
	$settings = gsf_scorm_lite_get_certificate_settings();
	$name     = $user ? $user->display_name : __( 'Learner', 'gsf-scorm-lite' );
	$date     = ! empty( $certificate['issued_at'] ) ? date_i18n( get_option( 'date_format' ), strtotime( $certificate['issued_at'] ) ) : date_i18n( get_option( 'date_format' ) );

	$out  = '<div class="gsf-lms-certificate" style="--gsf-cert-primary:' . esc_attr( $settings['primary_color'] ) . ';--gsf-cert-accent:' . esc_attr( $settings['accent_color'] ) . '">';
	$out .= '<div class="gsf-lms-certificate__inner">';
	if ( ! empty( $settings['canada_logo_url'] ) || ! empty( $settings['cbf_logo_url'] ) ) {
		$out .= '<div class="gsf-lms-certificate__partner-row" aria-label="' . esc_attr__( 'Course partners', 'gsf-scorm-lite' ) . '">';
		if ( ! empty( $settings['canada_logo_url'] ) ) {
			$out .= '<img class="gsf-lms-certificate__partner-logo gsf-lms-certificate__partner-logo--canada" src="' . esc_url( $settings['canada_logo_url'] ) . '" alt="' . esc_attr__( 'In partnership with Canada', 'gsf-scorm-lite' ) . '" />';
		}
		if ( ! empty( $settings['cbf_logo_url'] ) ) {
			$out .= '<img class="gsf-lms-certificate__partner-logo gsf-lms-certificate__partner-logo--cbf" src="' . esc_url( $settings['cbf_logo_url'] ) . '" alt="' . esc_attr__( 'Caribbean Biodiversity Fund', 'gsf-scorm-lite' ) . '" />';
		}
		$out .= '</div>';
	}
	if ( ! empty( $settings['logo_url'] ) ) {
		$out .= '<img class="gsf-lms-certificate__logo" src="' . esc_url( $settings['logo_url'] ) . '" alt="' . esc_attr( $settings['issuer'] ) . '" />';
	}
	$out .= '<p class="gsf-lms-certificate__eyebrow">' . esc_html( $settings['footer'] ) . '</p>';
	$out .= '<h2>' . esc_html( $settings['title'] ) . '</h2>';
	$out .= '<p class="gsf-lms-certificate__intro">' . esc_html( $settings['intro'] ) . '</p>';
	$out .= '<p class="gsf-lms-certificate__learner">' . esc_html( $name ) . '</p>';
	$out .= '<p class="gsf-lms-certificate__body">' . esc_html( $settings['body'] ) . '</p>';
	$out .= '<p class="gsf-lms-certificate__course">' . esc_html( get_the_title( $course_id ) ) . '</p>';
	$out .= '<div class="gsf-lms-certificate__meta">';
	$out .= '<span>' . esc_html__( 'Issued:', 'gsf-scorm-lite' ) . ' ' . esc_html( $date ) . '</span>';
	$out .= '<span>' . esc_html__( 'Certificate ID:', 'gsf-scorm-lite' ) . ' ' . esc_html( $certificate['certificate_code'] ) . '</span>';
	$out .= '<span>' . esc_html__( 'Issuer:', 'gsf-scorm-lite' ) . ' ' . esc_html( $settings['issuer'] ) . '</span>';
	$out .= '</div>';
	$out .= '<p class="gsf-lms-certificate__print"><button type="button" class="button" onclick="window.print()">' . esc_html__( 'Print or Save PDF', 'gsf-scorm-lite' ) . '</button></p>';
	$out .= '</div></div>';

	return $out;
}

/**
 * Handles feedback submissions.
 */
function gsf_scorm_lite_handle_feedback() {
	if ( ! is_user_logged_in() ) {
		wp_die( esc_html__( 'Login required.', 'gsf-scorm-lite' ) );
	}

	$course_id = absint( $_POST['course_id'] ?? 0 );

	if ( ! $course_id || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ?? '' ) ), 'gsf_lms_feedback_' . $course_id ) ) {
		wp_die( esc_html__( 'Invalid feedback request.', 'gsf-scorm-lite' ) );
	}

	global $wpdb;

	$table  = gsf_scorm_lite_feedback_table_name();
	$user_id = get_current_user_id();
	$rating = min( 5, max( 1, absint( $_POST['knowledge_rating'] ?? 0 ) ) );
	$plans  = array_map( 'sanitize_text_field', wp_unslash( $_POST['application_plan'] ?? array() ) );
	$survey = array(
		'knowledge_rating' => $rating,
		'application_plan' => array_values( array_filter( (array) $plans ) ),
		'application_other'=> sanitize_text_field( wp_unslash( $_POST['application_other'] ?? '' ) ),
		'met_expectations' => sanitize_text_field( wp_unslash( $_POST['met_expectations'] ?? '' ) ),
		'takeaway'         => sanitize_textarea_field( wp_unslash( $_POST['takeaway'] ?? '' ) ),
		'improvements'     => sanitize_textarea_field( wp_unslash( $_POST['improvements'] ?? '' ) ),
	);
	$data   = array(
		'user_id'    => $user_id,
		'course_id'  => $course_id,
		'rating'     => $rating,
		'comments'   => wp_json_encode( $survey ),
		'created_at' => current_time( 'mysql' ),
	);

	$existing = $wpdb->get_var(
		$wpdb->prepare(
			"SELECT id FROM {$table} WHERE user_id = %d AND course_id = %d",
			$user_id,
			$course_id
		)
	);

	if ( $existing ) {
		$wpdb->update( $table, $data, array( 'id' => (int) $existing ) );
	} else {
		$wpdb->insert( $table, $data );
	}

	wp_safe_redirect( add_query_arg( 'feedback', 'saved', get_permalink( $course_id ) ) );
	exit;
}
add_action( 'admin_post_gsf_lms_feedback', 'gsf_scorm_lite_handle_feedback' );

/**
 * Renders feedback form.
 *
 * @param int $course_id Course ID.
 * @return string
 */
function gsf_scorm_lite_feedback_form( $course_id ) {
	if ( ! is_user_logged_in() ) {
		return '';
	}

	$enrollment = gsf_scorm_lite_get_enrollment( get_current_user_id(), $course_id );

	if ( empty( $enrollment ) || 'completed' !== $enrollment['status'] ) {
		return '';
	}

	$out  = '<section class="gsf-lms-panel gsf-lms-feedback"><h2>' . esc_html__( 'Course feedback', 'gsf-scorm-lite' ) . '</h2>';
	$out .= '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
	$out .= '<input type="hidden" name="action" value="gsf_lms_feedback" />';
	$out .= '<input type="hidden" name="course_id" value="' . esc_attr( $course_id ) . '" />';
	$out .= wp_nonce_field( 'gsf_lms_feedback_' . $course_id, '_wpnonce', true, false );
	$out .= '<fieldset><legend>' . esc_html__( 'On a scale of 1-5, with 1 being the lowest and 5 the highest, how would you rate this capacity building course in relation to your improved knowledge and understanding of the subject matter?', 'gsf-scorm-lite' ) . '</legend>';
	foreach ( range( 1, 5 ) as $value ) {
		$out .= '<label class="gsf-lms-radio"><input type="radio" name="knowledge_rating" value="' . esc_attr( $value ) . '" required /> ' . esc_html( (string) $value ) . '</label>';
	}
	$out .= '</fieldset>';
	$out .= '<fieldset><legend>' . esc_html__( 'How do you plan to apply and share the knowledge gained from this course to support organizational strengthening in the next 12 months?', 'gsf-scorm-lite' ) . '</legend>';
	$plans = array(
		'presentation' => __( 'I will deliver a presentation or briefing session to my team/unit.', 'gsf-scorm-lite' ),
		'processes'    => __( 'I will integrate the new knowledge into existing work processes and share practices informally.', 'gsf-scorm-lite' ),
		'tools'        => __( 'I will develop or revise tools, guidelines, or Standard Operating Procedures based on the training content.', 'gsf-scorm-lite' ),
		'mentor'       => __( 'I will mentor or coach colleagues using insights gained from the training.', 'gsf-scorm-lite' ),
		'unsure'       => __( 'I am unsure at this time how I will share or apply the knowledge.', 'gsf-scorm-lite' ),
		'other'        => __( 'Other', 'gsf-scorm-lite' ),
	);
	foreach ( $plans as $key => $label ) {
		$out .= '<label class="gsf-lms-check"><input type="checkbox" name="application_plan[]" value="' . esc_attr( $key ) . '" /> ' . esc_html( $label ) . '</label>';
	}
	$out .= '<p><label>' . esc_html__( 'Other, please specify', 'gsf-scorm-lite' ) . '<br><input type="text" name="application_other" /></label></p>';
	$out .= '</fieldset>';
	$out .= '<fieldset><legend>' . esc_html__( 'Did the capacity building course meet your expectations?', 'gsf-scorm-lite' ) . '</legend>';
	$out .= '<label class="gsf-lms-radio"><input type="radio" name="met_expectations" value="yes" required /> ' . esc_html__( 'Yes', 'gsf-scorm-lite' ) . '</label>';
	$out .= '<label class="gsf-lms-radio"><input type="radio" name="met_expectations" value="no" required /> ' . esc_html__( 'No', 'gsf-scorm-lite' ) . '</label>';
	$out .= '</fieldset>';
	$out .= '<p><label>' . esc_html__( 'What will you take away from this capacity building course to support your daily work in climate change adaptation and gender responsive programming?', 'gsf-scorm-lite' ) . '<br><textarea name="takeaway" rows="4" required></textarea></label></p>';
	$out .= '<p><label>' . esc_html__( 'How could we improve this capacity building course in the future?', 'gsf-scorm-lite' ) . '<br><textarea name="improvements" rows="4"></textarea></label></p>';
	$out .= '<p><button type="submit" class="button button--primary">' . esc_html__( 'Submit feedback', 'gsf-scorm-lite' ) . '</button></p>';
	$out .= '</form></section>';

	return $out;
}

/**
 * Renders course LMS content on single course pages.
 *
 * @param string $content Post content.
 * @return string
 */
function gsf_scorm_lite_append_course_content( $content ) {
	if ( ! is_singular( 'gsf_course' ) || ! in_the_loop() || ! is_main_query() ) {
		return $content;
	}

	$course_id = get_the_ID();
	$slug      = get_post_meta( $course_id, '_gsf_scorm_slug', true );
	$out       = '<section class="gsf-lms-panel">';

	if ( ! is_user_logged_in() ) {
		$out .= '<p>' . esc_html__( 'Log in to enroll and launch this course.', 'gsf-scorm-lite' ) . '</p>';
		$out .= gsf_scorm_lite_enroll_button( $course_id );
		$out .= '</section>';

		return $content . $out;
	}

	$enrollment = gsf_scorm_lite_get_enrollment( get_current_user_id(), $course_id );

	if ( empty( $enrollment ) ) {
		$out .= '<p>' . esc_html__( 'Enroll to start this course.', 'gsf-scorm-lite' ) . '</p>';
		$out .= gsf_scorm_lite_enroll_button( $course_id );
		$out .= '</section>';

		return $content . $out;
	}

	if ( ! empty( $enrollment['status'] ) ) {
		$out .= '<p class="gsf-lms-meta">' . esc_html__( 'Status:', 'gsf-scorm-lite' ) . ' ' . esc_html( gsf_scorm_lite_status_label( $enrollment['status'] ) ) . '</p>';
	}

	if ( ! empty( $slug ) ) {
		$out .= do_shortcode( '[gsf_scorm_player slug="' . esc_attr( $slug ) . '" title="' . esc_attr( get_the_title( $course_id ) ) . '"]' );
	}

	$certificate = gsf_scorm_lite_get_certificate( get_current_user_id(), $course_id );

	if ( ! empty( $certificate ) ) {
		$out .= gsf_scorm_lite_render_certificate_card( get_current_user_id(), $course_id, $certificate );
	}

	$out .= '</section>';
	$out .= gsf_scorm_lite_feedback_form( $course_id );

	return $content . $out;
}
add_filter( 'the_content', 'gsf_scorm_lite_append_course_content', 20 );

/**
 * Returns the upload base directory for SCORM packages.
 *
 * @return array{path:string,url:string}
 */
function gsf_scorm_lite_package_base() {
	$uploads = wp_upload_dir();

	return array(
		'path' => trailingslashit( $uploads['basedir'] ) . 'gsf-scorm-packages',
		'url'  => trailingslashit( $uploads['baseurl'] ) . 'gsf-scorm-packages',
	);
}

/**
 * Gets a saved attempt for the current user and course.
 *
 * @param int    $user_id User ID.
 * @param string $slug    Course slug.
 * @return array<string,mixed>
 */
function gsf_scorm_lite_get_attempt( $user_id, $slug ) {
	global $wpdb;

	$table_name = gsf_scorm_lite_table_name();
	$row        = $wpdb->get_row(
		$wpdb->prepare(
			"SELECT * FROM {$table_name} WHERE user_id = %d AND course_slug = %s",
			$user_id,
			$slug
		),
		ARRAY_A
	);

	if ( empty( $row ) ) {
		return array();
	}

	$data = json_decode( (string) $row['scorm_data'], true );

	if ( ! is_array( $data ) ) {
		$data = array();
	}

	return array(
		'id'                => (int) $row['id'],
		'user_id'           => (int) $row['user_id'],
		'course_slug'       => $row['course_slug'],
		'lesson_status'     => $row['lesson_status'],
		'completion_status' => $row['completion_status'],
		'score_raw'         => $row['score_raw'],
		'score_min'         => $row['score_min'],
		'score_max'         => $row['score_max'],
		'lesson_location'   => $row['lesson_location'],
		'suspend_data'      => $row['suspend_data'],
		'total_time'        => $row['total_time'],
		'session_time'      => $row['session_time'],
		'scorm_data'        => $data,
		'updated_at'        => $row['updated_at'],
	);
}

/**
 * Saves SCORM state for the current user.
 *
 * @param WP_REST_Request $request REST request.
 * @return WP_REST_Response
 */
function gsf_scorm_lite_rest_save_attempt( WP_REST_Request $request ) {
	global $wpdb;

	$user_id = get_current_user_id();

	if ( ! $user_id ) {
		return new WP_REST_Response( array( 'message' => 'Login required.' ), 401 );
	}

	$slug = sanitize_title( (string) $request->get_param( 'slug' ) );
	$data = $request->get_param( 'data' );

	if ( empty( $slug ) || ! is_array( $data ) ) {
		return new WP_REST_Response( array( 'message' => 'Invalid SCORM payload.' ), 400 );
	}

	$now          = current_time( 'mysql' );
	$table_name   = gsf_scorm_lite_table_name();
	$lesson_status = isset( $data['cmi.core.lesson_status'] ) ? sanitize_text_field( $data['cmi.core.lesson_status'] ) : 'incomplete';
	$payload       = array(
		'user_id'           => $user_id,
		'course_slug'       => $slug,
		'lesson_status'     => $lesson_status,
		'completion_status' => isset( $data['cmi.completion_status'] ) ? sanitize_text_field( $data['cmi.completion_status'] ) : '',
		'score_raw'         => isset( $data['cmi.core.score.raw'] ) ? sanitize_text_field( $data['cmi.core.score.raw'] ) : '',
		'score_min'         => isset( $data['cmi.core.score.min'] ) ? sanitize_text_field( $data['cmi.core.score.min'] ) : '',
		'score_max'         => isset( $data['cmi.core.score.max'] ) ? sanitize_text_field( $data['cmi.core.score.max'] ) : '',
		'lesson_location'   => isset( $data['cmi.core.lesson_location'] ) ? wp_unslash( (string) $data['cmi.core.lesson_location'] ) : '',
		'suspend_data'      => isset( $data['cmi.suspend_data'] ) ? wp_unslash( (string) $data['cmi.suspend_data'] ) : '',
		'total_time'        => isset( $data['cmi.core.total_time'] ) ? sanitize_text_field( $data['cmi.core.total_time'] ) : '',
		'session_time'      => isset( $data['cmi.core.session_time'] ) ? sanitize_text_field( $data['cmi.core.session_time'] ) : '',
		'scorm_data'        => wp_json_encode( $data ),
		'updated_at'        => $now,
	);

	$existing = gsf_scorm_lite_get_attempt( $user_id, $slug );

	if ( ! empty( $existing['id'] ) ) {
		$wpdb->update(
			$table_name,
			$payload,
			array( 'id' => (int) $existing['id'] )
		);
	} else {
		$payload['created_at'] = $now;
		$wpdb->insert( $table_name, $payload );
	}

	gsf_scorm_lite_sync_enrollment_from_scorm( $user_id, $slug, $data );

	return new WP_REST_Response(
		array(
			'ok'      => true,
			'attempt' => gsf_scorm_lite_get_attempt( $user_id, $slug ),
		)
	);
}

/**
 * Returns SCORM state for the current user.
 *
 * @param WP_REST_Request $request REST request.
 * @return WP_REST_Response
 */
function gsf_scorm_lite_rest_get_attempt( WP_REST_Request $request ) {
	$user_id = get_current_user_id();

	if ( ! $user_id ) {
		return new WP_REST_Response( array( 'message' => 'Login required.' ), 401 );
	}

	$slug = sanitize_title( (string) $request->get_param( 'slug' ) );

	if ( empty( $slug ) ) {
		return new WP_REST_Response( array( 'message' => 'Missing course slug.' ), 400 );
	}

	return new WP_REST_Response(
		array(
			'ok'      => true,
			'attempt' => gsf_scorm_lite_get_attempt( $user_id, $slug ),
		)
	);
}

/**
 * Registers REST routes used by the SCORM runtime.
 */
function gsf_scorm_lite_register_rest_routes() {
	register_rest_route(
		'gsf-scorm-lite/v1',
		'/attempt/(?P<slug>[a-z0-9-]+)',
		array(
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => 'gsf_scorm_lite_rest_get_attempt',
				'permission_callback' => 'is_user_logged_in',
			),
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => 'gsf_scorm_lite_rest_save_attempt',
				'permission_callback' => 'is_user_logged_in',
			),
		)
	);
}
add_action( 'rest_api_init', 'gsf_scorm_lite_register_rest_routes' );

/**
 * Renders a SCORM package player.
 *
 * @param array<string,string> $atts Shortcode attributes.
 * @return string
 */
function gsf_scorm_lite_shortcode( $atts ) {
	$atts = shortcode_atts(
		array(
			'slug'   => '',
			'title'  => __( 'SCORM course', 'gsf-scorm-lite' ),
			'height' => '760',
		),
		$atts,
		'gsf_scorm_player'
	);

	if ( ! is_user_logged_in() ) {
		return '<p class="gsf-scorm-lite__notice">' . esc_html__( 'Please log in to launch this course.', 'gsf-scorm-lite' ) . '</p>';
	}

	$slug = sanitize_title( $atts['slug'] );

	if ( empty( $slug ) ) {
		return '';
	}

	$base         = gsf_scorm_lite_package_base();
	$launch_path  = trailingslashit( $base['path'] ) . $slug . '/scormdriver/indexAPI.html';
	$launch_url   = trailingslashit( $base['url'] ) . $slug . '/scormdriver/indexAPI.html';
	$height       = absint( $atts['height'] );
	$current_user = wp_get_current_user();

	if ( ! file_exists( $launch_path ) ) {
		return current_user_can( 'edit_pages' )
			? '<p class="gsf-scorm-lite__notice">' . esc_html__( 'SCORM package launch file not found.', 'gsf-scorm-lite' ) . '</p>'
			: '';
	}

	wp_enqueue_script(
		'gsf-scorm-lite-player',
		GSF_SCORM_LITE_PLUGIN_URL . 'assets/player.js',
		array(),
		GSF_SCORM_LITE_VERSION,
		true
	);

	$attempt = gsf_scorm_lite_get_attempt( get_current_user_id(), $slug );
	$data    = array(
		'slug'      => $slug,
		'restUrl'   => esc_url_raw( rest_url( 'gsf-scorm-lite/v1/attempt/' . $slug ) ),
		'nonce'     => wp_create_nonce( 'wp_rest' ),
		'studentId' => (string) get_current_user_id(),
		'name'      => trim( $current_user->last_name . ', ' . $current_user->first_name ),
		'attempt'   => $attempt,
	);

	if ( empty( $data['name'] ) ) {
		$data['name'] = $current_user->display_name;
	}

	wp_add_inline_script(
		'gsf-scorm-lite-player',
		'window.gsfScormLitePlayers = window.gsfScormLitePlayers || {}; window.gsfScormLitePlayers[' . wp_json_encode( $slug ) . '] = ' . wp_json_encode( $data ) . ';',
		'before'
	);

	return sprintf(
		'<div class="gsf-scorm-lite" data-gsf-scorm-slug="%1$s"><iframe class="gsf-scorm-lite__frame" src="about:blank" data-src="%2$s" title="%3$s" style="min-height:%4$dpx" allowfullscreen></iframe></div>',
		esc_attr( $slug ),
		esc_url( $launch_url ),
		esc_attr( $atts['title'] ),
		$height > 0 ? $height : 760
	);
}
add_shortcode( 'gsf_scorm_player', 'gsf_scorm_lite_shortcode' );

/**
 * Enqueues small frontend styles.
 */
function gsf_scorm_lite_enqueue_styles() {
	$css = '.gsf-scorm-lite{width:100%;margin:2rem 0;overflow:hidden;border:1px solid rgba(19,71,66,.16);border-radius:8px;background:#fff}.gsf-scorm-lite__frame{display:block;width:100%;min-height:760px;border:0}.gsf-scorm-lite__notice{padding:1rem;border:1px solid rgba(126,53,37,.24);border-radius:8px;background:#fff7f2;color:#7e3525}.single-gsf_course .entry-hero-image{max-width:34rem;margin:1.5rem auto 2rem;overflow:hidden;border-radius:8px}.single-gsf_course .entry-hero-image img{display:block;width:100%;height:auto;max-height:20rem;object-fit:cover}.gsf-lms-course-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:1.4rem;margin:2rem 0}.gsf-lms-course-card,.gsf-lms-panel{padding:1rem;border:1px solid rgba(19,71,66,.16);border-radius:8px;background:#fff}.gsf-lms-course-card{display:flex;flex-direction:column;align-items:flex-start;gap:.65rem;padding:1.35rem}.gsf-lms-course-card__image{width:100%;height:auto;margin-bottom:.35rem;border-radius:6px}.gsf-lms-course-card h3{margin:.25rem 0 0;line-height:1.15}.gsf-lms-course-card p{margin:.15rem 0}.gsf-lms-course-card>.button{align-self:center;margin-top:auto}.gsf-lms-course-card>.button.button--primary{color:#fff}.gsf-lms-course-card>.button.button--primary:hover,.gsf-lms-course-card>.button.button--primary:focus-visible{color:#fff}.gsf-lms-meta{color:#54717b;font-weight:700}.gsf-lms-dashboard table{width:100%;border-collapse:collapse}.gsf-lms-dashboard th,.gsf-lms-dashboard td{padding:.75rem;border-bottom:1px solid rgba(19,71,66,.12);text-align:left}.gsf-lms-feedback fieldset{margin:1rem 0;padding:1rem;border:1px solid rgba(19,71,66,.16);border-radius:8px}.gsf-lms-feedback legend{font-weight:800;color:#0a3742}.gsf-lms-feedback textarea,.gsf-lms-feedback input[type=text]{width:100%;max-width:42rem}.gsf-lms-radio,.gsf-lms-check{display:block;margin:.45rem 0}.gsf-lms-certificate{margin:2rem 0;padding:1rem;border:1px solid color-mix(in srgb,var(--gsf-cert-accent,#1ca6a6) 40%,#fff);border-radius:8px;background:#fff}.gsf-lms-certificate__inner{padding:2rem;text-align:center;border:3px solid var(--gsf-cert-primary,#005e7a);border-radius:6px}.gsf-lms-certificate__logo{display:block;width:min(15rem,60%);height:auto;margin:0 auto 1rem}.gsf-lms-certificate__partner-row{display:flex;align-items:center;justify-content:space-between;gap:1rem 2rem;flex-wrap:wrap;margin:0 auto 1.25rem;max-width:44rem}.gsf-lms-certificate__partner-logo{display:block;max-width:12rem;max-height:4.5rem;width:auto;height:auto;object-fit:contain}.gsf-lms-certificate__partner-logo--canada{max-width:11rem}.gsf-lms-certificate__partner-logo--cbf{max-width:13rem}.gsf-lms-certificate__eyebrow{margin:0 0 1rem;color:var(--gsf-cert-accent,#1ca6a6);font-weight:800;text-transform:uppercase;letter-spacing:.08em}.gsf-lms-certificate h2{margin:.25rem 0 1.5rem;color:var(--gsf-cert-primary,#005e7a);font-size:clamp(2rem,5vw,3rem)}.gsf-lms-certificate__intro,.gsf-lms-certificate__body{margin:.5rem 0;color:#54717b}.gsf-lms-certificate__learner{margin:.6rem 0;color:#123;font-size:clamp(1.8rem,5vw,2.6rem);font-weight:900}.gsf-lms-certificate__course{margin:.6rem auto 1.5rem;max-width:46rem;color:var(--gsf-cert-primary,#005e7a);font-size:1.25rem;font-weight:800}.gsf-lms-certificate__meta{display:flex;flex-wrap:wrap;justify-content:center;gap:.75rem 1.25rem;color:#54717b;font-size:.95rem}.gsf-lms-certificate__print{margin:1.5rem 0 0}@media(max-width:640px){.gsf-lms-certificate__partner-row{justify-content:center}}@media print{body *{visibility:hidden}.gsf-lms-certificate,.gsf-lms-certificate *{visibility:visible}.gsf-lms-certificate{position:absolute;inset:0;margin:0;border:0}.gsf-lms-certificate__print{display:none}}';
	wp_register_style( 'gsf-scorm-lite', false, array(), GSF_SCORM_LITE_VERSION );
	wp_enqueue_style( 'gsf-scorm-lite' );
	wp_add_inline_style( 'gsf-scorm-lite', $css );
}
add_action( 'wp_enqueue_scripts', 'gsf_scorm_lite_enqueue_styles' );

/**
 * Adds admin menu.
 */
function gsf_scorm_lite_admin_menu() {
	add_menu_page(
		__( 'Learn', 'gsf-scorm-lite' ),
		__( 'Learn', 'gsf-scorm-lite' ),
		'manage_options',
		'gsf-scorm-lite',
		'gsf_scorm_lite_render_admin_page',
		'dashicons-welcome-learn-more',
		58
	);

	add_submenu_page(
		'gsf-scorm-lite',
		__( 'Upload SCORM Package', 'gsf-scorm-lite' ),
		__( 'Upload SCORM Package', 'gsf-scorm-lite' ),
		'manage_options',
		'gsf-scorm-lite',
		'gsf_scorm_lite_render_admin_page'
	);

	add_submenu_page(
		'gsf-scorm-lite',
		__( 'All Courses', 'gsf-scorm-lite' ),
		__( 'All Courses', 'gsf-scorm-lite' ),
		'edit_posts',
		'edit.php?post_type=gsf_course'
	);

	add_submenu_page(
		'gsf-scorm-lite',
		__( 'Add Course', 'gsf-scorm-lite' ),
		__( 'Add Course', 'gsf-scorm-lite' ),
		'edit_posts',
		'post-new.php?post_type=gsf_course'
	);

	add_submenu_page(
		'gsf-scorm-lite',
		__( 'Student Progress', 'gsf-scorm-lite' ),
		__( 'Student Progress', 'gsf-scorm-lite' ),
		'manage_options',
		'gsf-scorm-lite-progress',
		'gsf_scorm_lite_render_progress_page'
	);

	add_submenu_page(
		'gsf-scorm-lite',
		__( 'Certificate Settings', 'gsf-scorm-lite' ),
		__( 'Certificate Settings', 'gsf-scorm-lite' ),
		'manage_options',
		'gsf-scorm-lite-certificates',
		'gsf_scorm_lite_render_certificate_settings_page'
	);
}
add_action( 'admin_menu', 'gsf_scorm_lite_admin_menu' );

/**
 * Recursively copies a directory.
 *
 * @param string $source Source directory.
 * @param string $target Target directory.
 * @return bool
 */
function gsf_scorm_lite_copy_dir( $source, $target ) {
	if ( ! is_dir( $source ) ) {
		return false;
	}

	if ( ! wp_mkdir_p( $target ) ) {
		return false;
	}

	$items = scandir( $source );

	if ( ! is_array( $items ) ) {
		return false;
	}

	foreach ( $items as $item ) {
		if ( '.' === $item || '..' === $item ) {
			continue;
		}

		$source_item = trailingslashit( $source ) . $item;
		$target_item = trailingslashit( $target ) . $item;

		if ( is_dir( $source_item ) ) {
			gsf_scorm_lite_copy_dir( $source_item, $target_item );
		} else {
			copy( $source_item, $target_item );
		}
	}

	return true;
}

/**
 * Deletes a directory recursively.
 *
 * @param string $path Directory path.
 */
function gsf_scorm_lite_delete_dir( $path ) {
	if ( ! is_dir( $path ) ) {
		return;
	}

	$items = scandir( $path );

	if ( ! is_array( $items ) ) {
		return;
	}

	foreach ( $items as $item ) {
		if ( '.' === $item || '..' === $item ) {
			continue;
		}

		$item_path = trailingslashit( $path ) . $item;

		if ( is_dir( $item_path ) ) {
			gsf_scorm_lite_delete_dir( $item_path );
		} else {
			unlink( $item_path );
		}
	}

	rmdir( $path );
}

/**
 * Finds the directory containing imsmanifest.xml.
 *
 * @param string $path Directory path.
 * @return string
 */
function gsf_scorm_lite_find_manifest_dir( $path ) {
	$manifest = trailingslashit( $path ) . 'imsmanifest.xml';

	if ( file_exists( $manifest ) ) {
		return $path;
	}

	$items = scandir( $path );

	if ( ! is_array( $items ) ) {
		return '';
	}

	foreach ( $items as $item ) {
		if ( '.' === $item || '..' === $item ) {
			continue;
		}

		$item_path = trailingslashit( $path ) . $item;

		if ( is_dir( $item_path ) ) {
			$found = gsf_scorm_lite_find_manifest_dir( $item_path );

			if ( ! empty( $found ) ) {
				return $found;
			}
		}
	}

	return '';
}

/**
 * Returns a unique package slug unless replacement was requested.
 *
 * @param string $slug Slug requested by the admin.
 * @param string $base_path Package base path.
 * @return string
 */
function gsf_scorm_lite_unique_package_slug( $slug, $base_path ) {
	$slug      = sanitize_title( $slug );
	$candidate = $slug;
	$index     = 2;

	while ( is_dir( trailingslashit( $base_path ) . $candidate ) ) {
		$candidate = $slug . '-' . $index;
		$index++;
	}

	return $candidate;
}

/**
 * Imports an uploaded SCORM ZIP.
 *
 * @return string Admin notice HTML.
 */
function gsf_scorm_lite_handle_upload() {
	if ( empty( $_POST['gsf_scorm_lite_upload'] ) ) {
		return '';
	}

	check_admin_referer( 'gsf_scorm_lite_upload', 'gsf_scorm_lite_nonce' );

	if ( empty( $_FILES['gsf_scorm_zip']['tmp_name'] ) ) {
		return '<div class="notice notice-error"><p>' . esc_html__( 'Choose a SCORM ZIP file.', 'gsf-scorm-lite' ) . '</p></div>';
	}

	if ( ! class_exists( 'ZipArchive' ) ) {
		return '<div class="notice notice-error"><p>' . esc_html__( 'PHP ZipArchive is required to import SCORM ZIP files.', 'gsf-scorm-lite' ) . '</p></div>';
	}

	$slug = sanitize_title( wp_unslash( $_POST['gsf_scorm_slug'] ?? '' ) );

	if ( empty( $slug ) ) {
		$slug = sanitize_title( pathinfo( (string) $_FILES['gsf_scorm_zip']['name'], PATHINFO_FILENAME ) );
	}

	if ( empty( $slug ) ) {
		return '<div class="notice notice-error"><p>' . esc_html__( 'Enter a package slug.', 'gsf-scorm-lite' ) . '</p></div>';
	}

	$base      = gsf_scorm_lite_package_base();
	$replace   = ! empty( $_POST['gsf_scorm_replace_existing'] );
	$slug      = $replace ? $slug : gsf_scorm_lite_unique_package_slug( $slug, $base['path'] );
	$temp_dir  = trailingslashit( $base['path'] ) . '_tmp_' . wp_generate_password( 8, false, false );
	$final_dir = trailingslashit( $base['path'] ) . $slug;
	$zip       = new ZipArchive();

	if ( ! wp_mkdir_p( $temp_dir ) || ! wp_mkdir_p( $base['path'] ) ) {
		return '<div class="notice notice-error"><p>' . esc_html__( 'Could not create SCORM package directory.', 'gsf-scorm-lite' ) . '</p></div>';
	}

	if ( true !== $zip->open( $_FILES['gsf_scorm_zip']['tmp_name'] ) ) {
		gsf_scorm_lite_delete_dir( $temp_dir );

		return '<div class="notice notice-error"><p>' . esc_html__( 'Could not open the ZIP file.', 'gsf-scorm-lite' ) . '</p></div>';
	}

	$zip->extractTo( $temp_dir );
	$zip->close();

	$manifest_dir = gsf_scorm_lite_find_manifest_dir( $temp_dir );

	if ( empty( $manifest_dir ) ) {
		gsf_scorm_lite_delete_dir( $temp_dir );

		return '<div class="notice notice-error"><p>' . esc_html__( 'No imsmanifest.xml file found in the ZIP.', 'gsf-scorm-lite' ) . '</p></div>';
	}

	if ( is_dir( $final_dir ) && $replace ) {
		gsf_scorm_lite_delete_dir( $final_dir );
	}

	gsf_scorm_lite_copy_dir( $manifest_dir, $final_dir );
	gsf_scorm_lite_delete_dir( $temp_dir );

	if ( ! file_exists( trailingslashit( $final_dir ) . 'scormdriver/indexAPI.html' ) ) {
		return '<div class="notice notice-warning"><p>' . esc_html__( 'Package imported, but scormdriver/indexAPI.html was not found. The shortcode may not launch this package.', 'gsf-scorm-lite' ) . '</p></div>';
	}

	$add_course_url = add_query_arg(
		array(
			'post_type'      => 'gsf_course',
			'gsf_scorm_slug' => $slug,
		),
		admin_url( 'post-new.php' )
	);

	return '<div class="notice notice-success"><p>' . sprintf(
		/* translators: %s: shortcode */
		esc_html__( 'SCORM package imported. Use shortcode: %s', 'gsf-scorm-lite' ),
		'<code>[gsf_scorm_player slug="' . esc_attr( $slug ) . '" title="Course title"]</code>'
	) . '</p><p><a class="button button-primary" href="' . esc_url( $add_course_url ) . '">' . esc_html__( 'Create a course with this package', 'gsf-scorm-lite' ) . '</a></p></div>';
}

/**
 * Seeds the current gender course as a GSF Course post.
 *
 * @return string Admin notice HTML.
 */
function gsf_scorm_lite_handle_seed_gender_course() {
	if ( empty( $_POST['gsf_scorm_lite_seed_gender'] ) ) {
		return '';
	}

	check_admin_referer( 'gsf_scorm_lite_seed_gender', 'gsf_scorm_lite_seed_nonce' );

	$existing = get_page_by_path( 'gender-mainstreaming-conservation', OBJECT, 'gsf_course' );
	$content  = '<p>Build practical skills for integrating gender-responsive approaches into conservation fund management, project design, implementation, and institutional practice.</p>';
	$postarr  = array(
		'post_title'   => 'Gender Mainstreaming in Conservation Fund Management: Tools for CTFs',
		'post_name'    => 'gender-mainstreaming-conservation',
		'post_type'    => 'gsf_course',
		'post_status'  => 'publish',
		'post_excerpt' => 'A self-paced Articulate course for CTF staff and partners working on gender-smart conservation delivery.',
		'post_content' => $content,
	);

	if ( $existing instanceof WP_Post ) {
		$postarr['ID'] = $existing->ID;
		$course_id     = wp_update_post( $postarr, true );
	} else {
		$course_id = wp_insert_post( $postarr, true );
	}

	if ( is_wp_error( $course_id ) || ! $course_id ) {
		return '<div class="notice notice-error"><p>' . esc_html__( 'Could not create the course post.', 'gsf-scorm-lite' ) . '</p></div>';
	}

	update_post_meta( $course_id, '_gsf_scorm_slug', 'gender-mainstreaming-conservation-lms' );
	update_post_meta( $course_id, '_gsf_course_duration', 'Self-paced' );
	update_post_meta( $course_id, '_gsf_certificate_enabled', '1' );

	return '<div class="notice notice-success"><p>' . sprintf(
		/* translators: %s: course URL */
		esc_html__( 'Gender course is ready: %s', 'gsf-scorm-lite' ),
		'<a href="' . esc_url( get_permalink( $course_id ) ) . '">' . esc_html__( 'View course', 'gsf-scorm-lite' ) . '</a>'
	) . '</p></div>';
}

/**
 * Returns installed SCORM package slugs.
 *
 * @return string[]
 */
function gsf_scorm_lite_get_packages() {
	$base = gsf_scorm_lite_package_base();

	if ( ! is_dir( $base['path'] ) ) {
		return array();
	}

	$items = scandir( $base['path'] );

	if ( ! is_array( $items ) ) {
		return array();
	}

	$packages = array();

	foreach ( $items as $item ) {
		if ( '.' === $item || '..' === $item || 0 === strpos( $item, '_tmp_' ) ) {
			continue;
		}

		if ( is_dir( trailingslashit( $base['path'] ) . $item ) ) {
			$packages[] = $item;
		}
	}

	sort( $packages );

	return $packages;
}

/**
 * Renders plugin admin page.
 */
function gsf_scorm_lite_render_admin_page() {
	$notice   = gsf_scorm_lite_handle_upload();
	$notice  .= gsf_scorm_lite_handle_seed_gender_course();
	$packages = gsf_scorm_lite_get_packages();
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Learn', 'gsf-scorm-lite' ); ?></h1>
		<?php echo wp_kses_post( $notice ); ?>
		<p><?php esc_html_e( 'Upload Articulate SCORM 1.2 ZIP files and connect them to Learn courses. This is a lightweight course player and progress recorder for GSF Hub.', 'gsf-scorm-lite' ); ?></p>
		<form method="post" enctype="multipart/form-data">
			<?php wp_nonce_field( 'gsf_scorm_lite_upload', 'gsf_scorm_lite_nonce' ); ?>
			<input type="hidden" name="gsf_scorm_lite_upload" value="1" />
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="gsf_scorm_slug"><?php esc_html_e( 'Package slug', 'gsf-scorm-lite' ); ?></label></th>
					<td>
						<input name="gsf_scorm_slug" id="gsf_scorm_slug" class="regular-text" value="" placeholder="<?php esc_attr_e( 'Leave blank to use the ZIP file name', 'gsf-scorm-lite' ); ?>" />
						<p class="description"><?php esc_html_e( 'Use a unique package slug for each course. If this is left blank, Learn will create one from the ZIP filename.', 'gsf-scorm-lite' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="gsf_scorm_zip"><?php esc_html_e( 'SCORM ZIP', 'gsf-scorm-lite' ); ?></label></th>
					<td><input type="file" name="gsf_scorm_zip" id="gsf_scorm_zip" accept=".zip,application/zip" required /></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Existing package', 'gsf-scorm-lite' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="gsf_scorm_replace_existing" value="1" />
							<?php esc_html_e( 'Replace an existing package if the slug already exists', 'gsf-scorm-lite' ); ?>
						</label>
						<p class="description"><?php esc_html_e( 'Leave unchecked for new courses. Learn will add -2, -3, etc. instead of overwriting an existing package.', 'gsf-scorm-lite' ); ?></p>
					</td>
				</tr>
			</table>
			<?php submit_button( __( 'Import SCORM Package', 'gsf-scorm-lite' ) ); ?>
		</form>
		<hr />
		<h2><?php esc_html_e( 'Starter Course', 'gsf-scorm-lite' ); ?></h2>
		<p><?php esc_html_e( 'Create or update the Gender Mainstreaming course post and connect it to the imported SCORM package.', 'gsf-scorm-lite' ); ?></p>
		<form method="post">
			<?php wp_nonce_field( 'gsf_scorm_lite_seed_gender', 'gsf_scorm_lite_seed_nonce' ); ?>
			<input type="hidden" name="gsf_scorm_lite_seed_gender" value="1" />
			<?php submit_button( __( 'Create Gender Course', 'gsf-scorm-lite' ), 'secondary' ); ?>
		</form>
		<h2><?php esc_html_e( 'Installed Packages', 'gsf-scorm-lite' ); ?></h2>
		<?php if ( empty( $packages ) ) : ?>
			<p><?php esc_html_e( 'No SCORM packages imported yet.', 'gsf-scorm-lite' ); ?></p>
		<?php else : ?>
			<table class="widefat striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Slug', 'gsf-scorm-lite' ); ?></th>
						<th><?php esc_html_e( 'Shortcode', 'gsf-scorm-lite' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $packages as $package ) : ?>
						<tr>
							<td><code><?php echo esc_html( $package ); ?></code></td>
							<td><code>[gsf_scorm_player slug="<?php echo esc_attr( $package ); ?>" title="Course title"]</code></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>
	</div>
	<?php
}

/**
 * Renders admin learner progress report.
 */
function gsf_scorm_lite_render_progress_page() {
	global $wpdb;

	$enrollments_table  = gsf_scorm_lite_enrollments_table_name();
	$attempts_table     = gsf_scorm_lite_table_name();
	$feedback_table     = gsf_scorm_lite_feedback_table_name();
	$certificates_table = gsf_scorm_lite_certificates_table_name();
	$course_filter      = absint( $_GET['course_id'] ?? 0 );
	$status_filter      = sanitize_key( $_GET['status'] ?? '' );
	$where              = array( '1=1' );
	$args               = array();

	if ( $course_filter ) {
		$where[] = 'e.course_id = %d';
		$args[]  = $course_filter;
	}

	if ( $status_filter ) {
		$where[] = 'e.status = %s';
		$args[]  = $status_filter;
	}

	$sql = "SELECT e.*, u.display_name, u.user_email, p.post_title,
		a.lesson_status AS scorm_lesson_status,
		a.lesson_location,
		a.suspend_data,
		a.updated_at AS scorm_updated_at,
		f.rating,
		f.comments,
		c.certificate_code,
		c.issued_at
		FROM {$enrollments_table} e
		LEFT JOIN {$wpdb->users} u ON u.ID = e.user_id
		LEFT JOIN {$wpdb->posts} p ON p.ID = e.course_id
		LEFT JOIN {$attempts_table} a ON a.user_id = e.user_id AND a.course_slug = e.scorm_slug
		LEFT JOIN {$feedback_table} f ON f.user_id = e.user_id AND f.course_id = e.course_id
		LEFT JOIN {$certificates_table} c ON c.user_id = e.user_id AND c.course_id = e.course_id
		WHERE " . implode( ' AND ', $where ) . '
		ORDER BY e.last_accessed_at DESC, e.enrolled_at DESC';

	if ( ! empty( $args ) ) {
		$sql = $wpdb->prepare( $sql, $args );
	}

	$rows    = $wpdb->get_results( $sql, ARRAY_A );
	$courses = get_posts(
		array(
			'post_type'      => 'gsf_course',
			'post_status'    => 'any',
			'posts_per_page' => -1,
			'orderby'        => 'title',
			'order'          => 'ASC',
		)
	);
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Student Progress', 'gsf-scorm-lite' ); ?></h1>
		<form method="get" style="margin: 1rem 0;">
			<input type="hidden" name="page" value="gsf-scorm-lite-progress" />
			<select name="course_id">
				<option value="0"><?php esc_html_e( 'All courses', 'gsf-scorm-lite' ); ?></option>
				<?php foreach ( $courses as $course ) : ?>
					<option value="<?php echo esc_attr( $course->ID ); ?>" <?php selected( $course_filter, $course->ID ); ?>><?php echo esc_html( get_the_title( $course ) ); ?></option>
				<?php endforeach; ?>
			</select>
			<select name="status">
				<option value=""><?php esc_html_e( 'All statuses', 'gsf-scorm-lite' ); ?></option>
				<?php foreach ( array( 'enrolled', 'in_progress', 'completed', 'failed' ) as $status ) : ?>
					<option value="<?php echo esc_attr( $status ); ?>" <?php selected( $status_filter, $status ); ?>><?php echo esc_html( gsf_scorm_lite_status_label( $status ) ); ?></option>
				<?php endforeach; ?>
			</select>
			<?php submit_button( __( 'Filter', 'gsf-scorm-lite' ), 'secondary', '', false ); ?>
		</form>
		<?php if ( empty( $rows ) ) : ?>
			<p><?php esc_html_e( 'No enrollments found yet.', 'gsf-scorm-lite' ); ?></p>
		<?php else : ?>
			<table class="widefat striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Student', 'gsf-scorm-lite' ); ?></th>
						<th><?php esc_html_e( 'Course', 'gsf-scorm-lite' ); ?></th>
						<th><?php esc_html_e( 'Status', 'gsf-scorm-lite' ); ?></th>
						<th><?php esc_html_e( 'Score', 'gsf-scorm-lite' ); ?></th>
						<th><?php esc_html_e( 'Enrolled', 'gsf-scorm-lite' ); ?></th>
						<th><?php esc_html_e( 'Completed', 'gsf-scorm-lite' ); ?></th>
						<th><?php esc_html_e( 'SCORM Commit', 'gsf-scorm-lite' ); ?></th>
						<th><?php esc_html_e( 'Feedback', 'gsf-scorm-lite' ); ?></th>
						<th><?php esc_html_e( 'Certificate', 'gsf-scorm-lite' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $rows as $row ) : ?>
						<tr>
							<td>
								<strong><?php echo esc_html( $row['display_name'] ?: __( 'Unknown user', 'gsf-scorm-lite' ) ); ?></strong><br />
								<a href="mailto:<?php echo esc_attr( $row['user_email'] ); ?>"><?php echo esc_html( $row['user_email'] ); ?></a>
								<?php
								$profile_bits = array();
								foreach ( gsf_scorm_lite_profile_fields() as $profile_key => $profile_field ) {
									$profile_value = gsf_scorm_lite_get_profile_display_value( (int) $row['user_id'], $profile_key );
									if ( '' !== $profile_value ) {
										$profile_bits[] = sprintf(
											/* translators: 1: profile field label, 2: profile field value. */
											__( '%1$s: %2$s', 'gsf-scorm-lite' ),
											$profile_field['label'],
											$profile_value
										);
									}
								}
								?>
								<?php if ( ! empty( $profile_bits ) ) : ?>
									<br /><small><?php echo esc_html( implode( ' | ', $profile_bits ) ); ?></small>
								<?php endif; ?>
							</td>
							<td>
								<a href="<?php echo esc_url( get_edit_post_link( (int) $row['course_id'] ) ); ?>"><?php echo esc_html( $row['post_title'] ); ?></a><br />
								<code><?php echo esc_html( $row['scorm_slug'] ); ?></code>
							</td>
							<td>
								<?php echo esc_html( gsf_scorm_lite_status_label( $row['status'] ) ); ?>
								<?php if ( ! empty( $row['scorm_lesson_status'] ) ) : ?>
									<br /><small><?php esc_html_e( 'SCORM:', 'gsf-scorm-lite' ); ?> <?php echo esc_html( $row['scorm_lesson_status'] ); ?></small>
								<?php endif; ?>
							</td>
							<td><?php echo esc_html( $row['score_raw'] ); ?></td>
							<td><?php echo esc_html( $row['enrolled_at'] ); ?></td>
							<td><?php echo esc_html( $row['completed_at'] ); ?></td>
							<td>
								<?php echo esc_html( $row['scorm_updated_at'] ); ?>
								<?php if ( ! empty( $row['lesson_location'] ) ) : ?>
									<br /><small><?php esc_html_e( 'Bookmark:', 'gsf-scorm-lite' ); ?> <?php echo esc_html( wp_trim_words( $row['lesson_location'], 8 ) ); ?></small>
								<?php endif; ?>
							</td>
							<td>
								<?php if ( ! empty( $row['rating'] ) ) : ?>
									<?php
									$survey = gsf_scorm_lite_parse_feedback_survey( $row['comments'] );
									$labels = gsf_scorm_lite_application_plan_labels();
									$plans  = array();
									foreach ( (array) ( $survey['application_plan'] ?? array() ) as $plan ) {
										$plans[] = $labels[ $plan ] ?? $plan;
									}
									if ( ! empty( $survey['application_other'] ) ) {
										$plans[] = $survey['application_other'];
									}
									?>
									<?php echo esc_html( $row['rating'] ); ?>/5
									<?php if ( ! empty( $survey['met_expectations'] ) ) : ?>
										<br /><small><?php esc_html_e( 'Expectations:', 'gsf-scorm-lite' ); ?> <?php echo esc_html( ucfirst( $survey['met_expectations'] ) ); ?></small>
									<?php endif; ?>
									<?php if ( ! empty( $plans ) ) : ?>
										<br /><small><?php esc_html_e( 'Apply:', 'gsf-scorm-lite' ); ?> <?php echo esc_html( wp_trim_words( implode( '; ', $plans ), 16 ) ); ?></small>
									<?php endif; ?>
									<?php if ( ! empty( $survey['takeaway'] ) ) : ?>
										<br /><small><?php esc_html_e( 'Takeaway:', 'gsf-scorm-lite' ); ?> <?php echo esc_html( wp_trim_words( $survey['takeaway'], 12 ) ); ?></small>
									<?php endif; ?>
									<?php if ( ! empty( $survey['improvements'] ) ) : ?>
										<br /><small><?php esc_html_e( 'Improve:', 'gsf-scorm-lite' ); ?> <?php echo esc_html( wp_trim_words( $survey['improvements'], 12 ) ); ?></small>
									<?php endif; ?>
								<?php else : ?>
									<span aria-hidden="true">-</span>
								<?php endif; ?>
							</td>
							<td>
								<?php if ( ! empty( $row['certificate_code'] ) ) : ?>
									<code><?php echo esc_html( $row['certificate_code'] ); ?></code><br />
									<small><?php echo esc_html( $row['issued_at'] ); ?></small>
								<?php else : ?>
									<span aria-hidden="true">-</span>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>
	</div>
	<?php
}

/**
 * Returns certificate template settings.
 *
 * @return array<string,string>
 */
function gsf_scorm_lite_get_certificate_settings() {
	$defaults = array(
		'title'        => __( 'Certificate of Completion', 'gsf-scorm-lite' ),
		'intro'        => __( 'This certifies that', 'gsf-scorm-lite' ),
		'body'         => __( 'has successfully completed', 'gsf-scorm-lite' ),
		'issuer'       => __( 'GSF Hub', 'gsf-scorm-lite' ),
		'footer'       => __( 'Caribbean Organizations for a Resilient Environment', 'gsf-scorm-lite' ),
		'logo_url'     => content_url( 'themes/gsf-hub-sunrise/assets/images/gsf-logo-updated.png' ),
		'canada_logo_url' => content_url( 'themes/gsf-hub/assets/images/canada-partnership.jpg' ),
		'cbf_logo_url' => content_url( 'themes/gsf-hub/assets/images/cbf-logo.png' ),
		'primary_color' => '#005e7a',
		'accent_color' => '#1ca6a6',
	);
	$options = get_option( 'gsf_scorm_lite_certificate_settings', array() );

	return wp_parse_args( is_array( $options ) ? $options : array(), $defaults );
}

/**
 * Handles certificate settings save.
 *
 * @return string Notice HTML.
 */
function gsf_scorm_lite_handle_certificate_settings_save() {
	if ( empty( $_POST['gsf_scorm_lite_certificate_save'] ) ) {
		return '';
	}

	check_admin_referer( 'gsf_scorm_lite_certificate_settings', 'gsf_scorm_lite_certificate_nonce' );

	$settings = array(
		'title'         => sanitize_text_field( wp_unslash( $_POST['title'] ?? '' ) ),
		'intro'         => sanitize_text_field( wp_unslash( $_POST['intro'] ?? '' ) ),
		'body'          => sanitize_text_field( wp_unslash( $_POST['body'] ?? '' ) ),
		'issuer'        => sanitize_text_field( wp_unslash( $_POST['issuer'] ?? '' ) ),
		'footer'        => sanitize_text_field( wp_unslash( $_POST['footer'] ?? '' ) ),
		'logo_url'      => esc_url_raw( wp_unslash( $_POST['logo_url'] ?? '' ) ),
		'canada_logo_url' => esc_url_raw( wp_unslash( $_POST['canada_logo_url'] ?? '' ) ),
		'cbf_logo_url'  => esc_url_raw( wp_unslash( $_POST['cbf_logo_url'] ?? '' ) ),
		'primary_color' => sanitize_hex_color( wp_unslash( $_POST['primary_color'] ?? '' ) ) ?: '#005e7a',
		'accent_color'  => sanitize_hex_color( wp_unslash( $_POST['accent_color'] ?? '' ) ) ?: '#1ca6a6',
	);

	update_option( 'gsf_scorm_lite_certificate_settings', $settings );

	return '<div class="notice notice-success"><p>' . esc_html__( 'Certificate settings saved.', 'gsf-scorm-lite' ) . '</p></div>';
}

/**
 * Renders certificate settings page.
 */
function gsf_scorm_lite_render_certificate_settings_page() {
	$notice   = gsf_scorm_lite_handle_certificate_settings_save();
	$settings = gsf_scorm_lite_get_certificate_settings();
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Certificate Settings', 'gsf-scorm-lite' ); ?></h1>
		<?php echo wp_kses_post( $notice ); ?>
		<p><?php esc_html_e( 'Configure the global certificate text and visual styling used when a learner completes a certificate-enabled course.', 'gsf-scorm-lite' ); ?></p>
		<form method="post">
			<?php wp_nonce_field( 'gsf_scorm_lite_certificate_settings', 'gsf_scorm_lite_certificate_nonce' ); ?>
			<input type="hidden" name="gsf_scorm_lite_certificate_save" value="1" />
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="title"><?php esc_html_e( 'Title', 'gsf-scorm-lite' ); ?></label></th>
					<td><input name="title" id="title" class="regular-text" value="<?php echo esc_attr( $settings['title'] ); ?>" /></td>
				</tr>
				<tr>
					<th scope="row"><label for="intro"><?php esc_html_e( 'Intro line', 'gsf-scorm-lite' ); ?></label></th>
					<td><input name="intro" id="intro" class="regular-text" value="<?php echo esc_attr( $settings['intro'] ); ?>" /></td>
				</tr>
				<tr>
					<th scope="row"><label for="body"><?php esc_html_e( 'Completion line', 'gsf-scorm-lite' ); ?></label></th>
					<td><input name="body" id="body" class="regular-text" value="<?php echo esc_attr( $settings['body'] ); ?>" /></td>
				</tr>
				<tr>
					<th scope="row"><label for="issuer"><?php esc_html_e( 'Issuer', 'gsf-scorm-lite' ); ?></label></th>
					<td><input name="issuer" id="issuer" class="regular-text" value="<?php echo esc_attr( $settings['issuer'] ); ?>" /></td>
				</tr>
				<tr>
					<th scope="row"><label for="footer"><?php esc_html_e( 'Footer', 'gsf-scorm-lite' ); ?></label></th>
					<td><input name="footer" id="footer" class="regular-text" value="<?php echo esc_attr( $settings['footer'] ); ?>" /></td>
				</tr>
				<tr>
					<th scope="row"><label for="logo_url"><?php esc_html_e( 'Main GSF logo URL', 'gsf-scorm-lite' ); ?></label></th>
					<td>
						<input name="logo_url" id="logo_url" class="regular-text" value="<?php echo esc_attr( $settings['logo_url'] ); ?>" />
						<?php if ( ! empty( $settings['logo_url'] ) ) : ?>
							<p><img src="<?php echo esc_url( $settings['logo_url'] ); ?>" alt="" style="max-width:180px;height:auto;background:#fff;padding:8px;border:1px solid #ddd" /></p>
						<?php endif; ?>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="canada_logo_url"><?php esc_html_e( 'Canada logo URL', 'gsf-scorm-lite' ); ?></label></th>
					<td>
						<input name="canada_logo_url" id="canada_logo_url" class="regular-text" value="<?php echo esc_attr( $settings['canada_logo_url'] ); ?>" />
						<?php if ( ! empty( $settings['canada_logo_url'] ) ) : ?>
							<p><img src="<?php echo esc_url( $settings['canada_logo_url'] ); ?>" alt="" style="max-width:180px;height:auto;background:#fff;padding:8px;border:1px solid #ddd" /></p>
						<?php endif; ?>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="cbf_logo_url"><?php esc_html_e( 'Caribbean Biodiversity Fund logo URL', 'gsf-scorm-lite' ); ?></label></th>
					<td>
						<input name="cbf_logo_url" id="cbf_logo_url" class="regular-text" value="<?php echo esc_attr( $settings['cbf_logo_url'] ); ?>" />
						<?php if ( ! empty( $settings['cbf_logo_url'] ) ) : ?>
							<p><img src="<?php echo esc_url( $settings['cbf_logo_url'] ); ?>" alt="" style="max-width:180px;height:auto;background:#fff;padding:8px;border:1px solid #ddd" /></p>
						<?php endif; ?>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="primary_color"><?php esc_html_e( 'Primary color', 'gsf-scorm-lite' ); ?></label></th>
					<td><input type="color" name="primary_color" id="primary_color" value="<?php echo esc_attr( $settings['primary_color'] ); ?>" /></td>
				</tr>
				<tr>
					<th scope="row"><label for="accent_color"><?php esc_html_e( 'Accent color', 'gsf-scorm-lite' ); ?></label></th>
					<td><input type="color" name="accent_color" id="accent_color" value="<?php echo esc_attr( $settings['accent_color'] ); ?>" /></td>
				</tr>
			</table>
			<?php submit_button( __( 'Save Certificate Settings', 'gsf-scorm-lite' ) ); ?>
		</form>
		<h2><?php esc_html_e( 'Available Dynamic Fields', 'gsf-scorm-lite' ); ?></h2>
		<p><?php esc_html_e( 'Certificates automatically include learner name, course title, completion date, certificate ID, and issuer.', 'gsf-scorm-lite' ); ?></p>
	</div>
	<?php
}
