<?php
/**
 * Plugin Name: GSF SCORM Lite
 * Description: Lightweight SCORM 1.2 launcher and progress recorder for GSF Hub proof-of-concept courses.
 * Version: 0.2.5
 * Author: GSF Hub
 * Text Domain: gsf-scorm-lite
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'GSF_SCORM_LITE_VERSION', '0.2.5' );
define( 'GSF_SCORM_LITE_PLUGIN_FILE', __FILE__ );
define( 'GSF_SCORM_LITE_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'GSF_SCORM_LITE_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'GSF_SCORM_LITE_MANAGE_CAP', 'gsf_manage_learn' );
define( 'GSF_SCORM_LITE_REMINDER_HOOK', 'gsf_scorm_lite_send_weekly_reminders' );

/**
 * Returns whether the current user can manage Learn settings and reports.
 *
 * @return bool
 */
function gsf_scorm_lite_current_user_can_manage_learn() {
	return current_user_can( GSF_SCORM_LITE_MANAGE_CAP ) || current_user_can( 'manage_options' );
}

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
	gsf_scorm_lite_schedule_reminders();
}
register_activation_hook( __FILE__, 'gsf_scorm_lite_activate' );

/**
 * Removes scheduled reminder jobs when the plugin is deactivated.
 */
function gsf_scorm_lite_deactivate() {
	wp_clear_scheduled_hook( GSF_SCORM_LITE_REMINDER_HOOK );
}
register_deactivation_hook( __FILE__, 'gsf_scorm_lite_deactivate' );

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

	foreach ( array( 'administrator', 'gsf_content_staff' ) as $role_name ) {
		$role = get_role( $role_name );
		if ( $role ) {
			$role->add_cap( GSF_SCORM_LITE_MANAGE_CAP, true );
		}
	}

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
 * Adds the weekly interval used by learner reminders.
 *
 * @param array<string,array<string,mixed>> $schedules Cron schedules.
 * @return array<string,array<string,mixed>>
 */
function gsf_scorm_lite_cron_schedules( $schedules ) {
	$schedules['gsf_weekly'] = array(
		'interval' => WEEK_IN_SECONDS,
		'display'  => __( 'Once weekly (GSF learner reminders)', 'gsf-scorm-lite' ),
	);

	return $schedules;
}
add_filter( 'cron_schedules', 'gsf_scorm_lite_cron_schedules' );

/**
 * Ensures the weekly reminder job exists.
 */
function gsf_scorm_lite_schedule_reminders() {
	if ( ! wp_next_scheduled( GSF_SCORM_LITE_REMINDER_HOOK ) ) {
		wp_schedule_event( time() + HOUR_IN_SECONDS, 'gsf_weekly', GSF_SCORM_LITE_REMINDER_HOOK );
	}
}
add_action( 'init', 'gsf_scorm_lite_schedule_reminders' );

/**
 * Routes localhost email to Mailpit when the Docker-only environment variable is set.
 * Production mail behavior is unchanged when the variable is absent.
 *
 * @param PHPMailer\PHPMailer\PHPMailer $phpmailer WordPress mailer instance.
 */
function gsf_scorm_lite_configure_local_mailpit( $phpmailer ) {
	$host = getenv( 'GSF_SCORM_LITE_LOCAL_SMTP_HOST' );

	if ( ! $host ) {
		return;
	}

	$phpmailer->isSMTP();
	$phpmailer->Host       = sanitize_text_field( $host );
	$phpmailer->Port       = absint( getenv( 'GSF_SCORM_LITE_LOCAL_SMTP_PORT' ) ?: 1025 );
	$phpmailer->SMTPAuth   = false;
	$phpmailer->SMTPSecure = '';
	$phpmailer->SMTPAutoTLS = false;
	$phpmailer->setFrom( 'wordpress@gsf-hub.local', wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ), false );
}
add_action( 'phpmailer_init', 'gsf_scorm_lite_configure_local_mailpit' );

/**
 * Supplies a valid sender domain for localhost Mailpit messages.
 *
 * @param string $email Existing sender email.
 * @return string
 */
function gsf_scorm_lite_local_mail_from( $email ) {
	return getenv( 'GSF_SCORM_LITE_LOCAL_SMTP_HOST' ) ? 'wordpress@gsf-hub.local' : $email;
}
add_filter( 'wp_mail_from', 'gsf_scorm_lite_local_mail_from' );

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

	if ( '' === (string) $certificate_enabled ) {
		$certificate_enabled = '1';
	}

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
			<?php esc_html_e( 'Issue certificate after completion and feedback', 'gsf-scorm-lite' ); ?>
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
 * Returns whether a numeric SCORM value represents full completion.
 *
 * @param mixed $value          Raw score/progress value.
 * @param bool  $allow_fraction Whether 1.0 should count as 100%.
 * @return bool
 */
function gsf_scorm_lite_is_full_completion_value( $value, $allow_fraction = false ) {
	if ( '' === (string) $value || ! is_numeric( $value ) ) {
		return false;
	}

	$value = (float) $value;

	return $value >= 100 || ( $allow_fraction && $value >= 1.0 && $value <= 1.0001 );
}

/**
 * Returns whether a SCORM payload should count as completed.
 *
 * @param array<string,mixed> $data SCORM runtime data.
 * @return bool
 */
function gsf_scorm_lite_is_scorm_payload_complete( array $data ) {
	$lesson_status     = sanitize_key( $data['cmi.core.lesson_status'] ?? '' );
	$completion_status = sanitize_key( $data['cmi.completion_status'] ?? '' );

	return in_array( $lesson_status, array( 'completed', 'passed' ), true ) || in_array( $completion_status, array( 'completed', 'passed' ), true );
}

/**
 * Returns whether an enrollment row is completed.
 *
 * @param array<string,mixed> $enrollment Enrollment row.
 * @return bool
 */
function gsf_scorm_lite_is_enrollment_complete( array $enrollment ) {
	if ( empty( $enrollment ) ) {
		return false;
	}

	if ( 'completed' === sanitize_key( $enrollment['status'] ?? '' ) ) {
		return true;
	}

	return gsf_scorm_lite_is_full_completion_value( $enrollment['score_raw'] ?? '' );
}

/**
 * Returns whether a learner has completed a course attempt enough to unlock feedback.
 *
 * Scores/progress alone are intentionally not enough because some SCORM packages
 * can write 100 before the final completion screen commits a completed status.
 *
 * @param int $user_id   User ID.
 * @param int $course_id Course ID.
 * @return bool
 */
function gsf_scorm_lite_can_submit_feedback( $user_id, $course_id ) {
	$enrollment = gsf_scorm_lite_get_enrollment( $user_id, $course_id );

	if ( empty( $enrollment ) || 'completed' !== sanitize_key( $enrollment['status'] ?? '' ) ) {
		return false;
	}

	$slug = (string) get_post_meta( $course_id, '_gsf_scorm_slug', true );

	if ( '' === $slug ) {
		return false;
	}

	$attempt = gsf_scorm_lite_get_attempt( $user_id, $slug );
	$lesson_status = sanitize_key( $attempt['lesson_status'] ?? '' );
	$completion_status = sanitize_key( $attempt['completion_status'] ?? '' );

	return in_array( $lesson_status, array( 'completed', 'passed' ), true ) || in_array( $completion_status, array( 'completed', 'passed' ), true );
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

	$previous_enrollment = gsf_scorm_lite_get_enrollment( $user_id, $course->ID );
	$was_completed       = gsf_scorm_lite_is_enrollment_complete( $previous_enrollment );

	gsf_scorm_lite_enroll_user( $user_id, $course->ID, 'in_progress' );

	$lesson_status = sanitize_key( $data['cmi.core.lesson_status'] ?? 'in_progress' );
	$status        = gsf_scorm_lite_is_scorm_payload_complete( $data ) ? 'completed' : 'in_progress';
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
	}

	$updated = $wpdb->update(
		gsf_scorm_lite_enrollments_table_name(),
		$payload,
		array(
			'user_id'   => $user_id,
			'course_id' => $course->ID,
		)
	);

	if ( false !== $updated && 'completed' === $status && ! $was_completed ) {
		do_action( 'gsf_scorm_lite_course_completed', $user_id, $course->ID, $payload );
	}
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
 * Returns learner reminder settings.
 *
 * @return array<string,mixed>
 */
function gsf_scorm_lite_get_reminder_settings() {
	$defaults = array(
		'enabled'         => false,
		'inactivity_days' => 7,
		'subject'         => __( 'Continue your GSF Hub learning', 'gsf-scorm-lite' ),
	);
	$settings = get_option( 'gsf_scorm_lite_reminder_settings', array() );

	return wp_parse_args( is_array( $settings ) ? $settings : array(), $defaults );
}

/**
 * Returns administrator course-completion notification settings.
 *
 * @return array<string,mixed>
 */
function gsf_scorm_lite_get_completion_notification_settings() {
	$defaults = array(
		'enabled'    => false,
		'recipients' => array( sanitize_email( get_option( 'admin_email' ) ) ),
		'subject'    => __( 'Course completed: {course_title}', 'gsf-scorm-lite' ),
	);
	$settings = get_option( 'gsf_scorm_lite_completion_notification_settings', array() );
	$settings = wp_parse_args( is_array( $settings ) ? $settings : array(), $defaults );

	if ( ! is_array( $settings['recipients'] ) ) {
		$settings['recipients'] = preg_split( '/[\s,;]+/', (string) $settings['recipients'], -1, PREG_SPLIT_NO_EMPTY );
	}

	$settings['recipients'] = array_values( array_unique( array_filter( array_map( 'sanitize_email', $settings['recipients'] ), 'is_email' ) ) );

	return $settings;
}

/**
 * Sends an administrator notification for a newly completed course.
 *
 * @param int                 $user_id   Learner user ID.
 * @param int                 $course_id Course post ID.
 * @param array<string,mixed> $payload   Enrollment update payload.
 * @param bool                $is_test   Whether to send regardless of the enabled setting.
 * @return bool Whether WordPress accepted all messages for delivery.
 */
function gsf_scorm_lite_send_completion_notification( $user_id, $course_id, array $payload = array(), $is_test = false ) {
	$settings = gsf_scorm_lite_get_completion_notification_settings();

	if ( ! $is_test && empty( $settings['enabled'] ) ) {
		return false;
	}

	$user       = get_userdata( $user_id );
	$course     = get_post( $course_id );
	$recipients = $settings['recipients'];

	if ( ! $user || ! $course instanceof WP_Post || 'gsf_course' !== $course->post_type || empty( $recipients ) ) {
		return false;
	}

	$learner_name = $user->display_name ?: $user->user_login;
	$course_title = get_the_title( $course );
	$subject      = strtr(
		(string) $settings['subject'],
		array(
			'{learner_name}' => $learner_name,
			'{course_title}' => $course_title,
		)
	);

	if ( $is_test ) {
		$subject = sprintf( __( '[TEST] %s', 'gsf-scorm-lite' ), $subject );
	}

	$lines   = array();
	$lines[] = __( 'A learner has completed a GSF Hub course.', 'gsf-scorm-lite' );
	$lines[] = '';
	$lines[] = sprintf( __( 'Learner: %1$s (%2$s)', 'gsf-scorm-lite' ), $learner_name, $user->user_email );
	$lines[] = sprintf( __( 'Course: %s', 'gsf-scorm-lite' ), $course_title );
	$lines[] = sprintf( __( 'Completed: %s', 'gsf-scorm-lite' ), $payload['completed_at'] ?? current_time( 'mysql' ) );

	if ( '' !== (string) ( $payload['score_raw'] ?? '' ) ) {
		$lines[] = sprintf( __( 'Score: %s', 'gsf-scorm-lite' ), sanitize_text_field( $payload['score_raw'] ) );
	}

	$lines[] = '';
	$lines[] = __( 'View Student Progress:', 'gsf-scorm-lite' );
	$lines[] = admin_url( 'admin.php?page=gsf-scorm-lite-progress&course_id=' . absint( $course_id ) . '&status=completed' );

	$sent = true;
	foreach ( $recipients as $recipient ) {
		if ( ! wp_mail( $recipient, $subject, implode( "\n", $lines ) ) ) {
			$sent = false;
		}
	}

	return $sent;
}

/**
 * Sends the configured administrator notification on the first completion transition.
 *
 * @param int                 $user_id   Learner user ID.
 * @param int                 $course_id Course post ID.
 * @param array<string,mixed> $payload   Enrollment update payload.
 */
function gsf_scorm_lite_notify_admins_of_course_completion( $user_id, $course_id, array $payload ) {
	gsf_scorm_lite_send_completion_notification( $user_id, $course_id, $payload );
}
add_action( 'gsf_scorm_lite_course_completed', 'gsf_scorm_lite_notify_admins_of_course_completion', 10, 3 );

/**
 * Finds the main learner dashboard URL.
 *
 * @return string
 */
function gsf_scorm_lite_get_dashboard_url() {
	foreach ( array( 'learn', 'learning', 'dashboard' ) as $slug ) {
		$page = get_page_by_path( $slug );
		if ( $page instanceof WP_Post && has_shortcode( $page->post_content, 'gsf_lms_dashboard' ) ) {
			return get_permalink( $page );
		}
	}

	$pages = get_posts(
		array(
			'post_type'      => 'page',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
		)
	);

	foreach ( $pages as $page ) {
		if ( has_shortcode( $page->post_content, 'gsf_lms_dashboard' ) ) {
			return get_permalink( $page );
		}

	}

	return home_url( '/' );
}

/**
 * Returns incomplete enrollments that qualify for a reminder.
 *
 * @param int  $user_id           Optional user filter.
 * @param bool $respect_inactivity Whether to apply the inactivity threshold.
 * @return array<int,array<string,mixed>>
 */
function gsf_scorm_lite_get_reminder_enrollments( $user_id = 0, $respect_inactivity = true ) {
	global $wpdb;

	$settings = gsf_scorm_lite_get_reminder_settings();
	$table    = gsf_scorm_lite_enrollments_table_name();
	$where    = array( "e.status IN ('enrolled', 'in_progress', 'failed')" );
	$args     = array();

	if ( $user_id ) {
		$where[] = 'e.user_id = %d';
		$args[]  = absint( $user_id );
	}

	if ( $respect_inactivity ) {
		$days    = max( 1, absint( $settings['inactivity_days'] ) );
		$cutoff  = current_datetime()->modify( '-' . $days . ' days' )->format( 'Y-m-d H:i:s' );
		$where[] = 'COALESCE(e.last_accessed_at, e.started_at, e.enrolled_at) <= %s';
		$args[]  = $cutoff;
	}

	$sql = "SELECT e.*, u.display_name, u.user_email, p.post_title
		FROM {$table} e
		INNER JOIN {$wpdb->users} u ON u.ID = e.user_id
		INNER JOIN {$wpdb->posts} p ON p.ID = e.course_id
		WHERE " . implode( ' AND ', $where ) . "
		AND p.post_status = 'publish'
		ORDER BY e.user_id ASC, e.last_accessed_at DESC, e.enrolled_at DESC";

	if ( $args ) {
		$sql = $wpdb->prepare( $sql, $args );
	}

	return $wpdb->get_results( $sql, ARRAY_A );
}

/**
 * Sends one consolidated progress reminder.
 *
 * @param string                            $email       Destination email.
 * @param string                            $name        Learner display name.
 * @param array<int,array<string,mixed>>    $enrollments Incomplete enrollments.
 * @param bool                              $is_test     Whether this is an admin test.
 * @return bool
 */
function gsf_scorm_lite_send_reminder_email( $email, $name, array $enrollments, $is_test = false ) {
	$settings = gsf_scorm_lite_get_reminder_settings();
	$subject  = (string) $settings['subject'];

	if ( $is_test ) {
		$subject = sprintf( __( '[TEST] %s', 'gsf-scorm-lite' ), $subject );
	}

	$lines   = array();
	$lines[] = sprintf( __( 'Hello %s,', 'gsf-scorm-lite' ), $name ?: __( 'learner', 'gsf-scorm-lite' ) );
	$lines[] = '';
	$lines[] = __( 'You have learning in progress on GSF Hub. Continue where you left off:', 'gsf-scorm-lite' );
	$lines[] = '';

	if ( empty( $enrollments ) ) {
		$lines[] = __( 'Example course in progress', 'gsf-scorm-lite' );
		$lines[] = gsf_scorm_lite_get_dashboard_url();
	} else {
		foreach ( $enrollments as $enrollment ) {
			$lines[] = '- ' . (string) $enrollment['post_title'];
			$lines[] = get_permalink( (int) $enrollment['course_id'] );
			$lines[] = '';
		}
	}

	$lines[] = __( 'View all of your course progress:', 'gsf-scorm-lite' );
	$lines[] = gsf_scorm_lite_get_dashboard_url();
	$lines[] = '';
	$lines[] = __( 'GSF Hub', 'gsf-scorm-lite' );

	return wp_mail( sanitize_email( $email ), $subject, implode( "\n", $lines ) );
}

/**
 * Sends reminders to inactive learners. Runs from WordPress Cron.
 *
 * @return int Number of messages accepted by WordPress mail.
 */
function gsf_scorm_lite_send_weekly_reminders() {
	$settings = gsf_scorm_lite_get_reminder_settings();

	if ( empty( $settings['enabled'] ) ) {
		return 0;
	}

	$grouped = array();
	foreach ( gsf_scorm_lite_get_reminder_enrollments() as $enrollment ) {
		$grouped[ (int) $enrollment['user_id'] ][] = $enrollment;
	}

	$sent = 0;
	foreach ( $grouped as $user_id => $enrollments ) {
		$last_sent = (int) get_user_meta( $user_id, '_gsf_lms_last_reminder_at', true );
		if ( $last_sent && $last_sent > time() - ( 6 * DAY_IN_SECONDS ) ) {
			continue;
		}

		$first = reset( $enrollments );
		if ( empty( $first['user_email'] ) ) {
			continue;
		}

		if ( gsf_scorm_lite_send_reminder_email( $first['user_email'], $first['display_name'], $enrollments ) ) {
			update_user_meta( $user_id, '_gsf_lms_last_reminder_at', time() );
			++$sent;
		}
	}

	return $sent;
}
add_action( GSF_SCORM_LITE_REMINDER_HOOK, 'gsf_scorm_lite_send_weekly_reminders' );

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
			'label'       => __( 'Gender', 'gsf-scorm-lite' ),
			'type'        => 'select',
			'other_key'   => 'gsf_sex_other',
			'other_label' => __( 'Please specify gender', 'gsf-scorm-lite' ),
			'choices'     => array(
				'women'            => __( 'Women', 'gsf-scorm-lite' ),
				'men'              => __( 'Men', 'gsf-scorm-lite' ),
				'non_binary'       => __( 'Non-binary', 'gsf-scorm-lite' ),
				'prefer_not_to_say'=> __( 'Prefer not to say', 'gsf-scorm-lite' ),
				'other'            => __( 'Other', 'gsf-scorm-lite' ),
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
		'gsf_stakeholder_groups' => array(
			'label'   => __( 'Do you belong to any of the following stakeholder groups? Select all that apply.', 'gsf-scorm-lite' ),
			'type'    => 'checkbox',
			'choices' => array(
				'People with Disabilities' => __( 'People with Disabilities', 'gsf-scorm-lite' ),
				'Indigenous Peoples'       => __( 'Indigenous Peoples', 'gsf-scorm-lite' ),
				'Women Led Organization'    => __( 'Women Led Organization', 'gsf-scorm-lite' ),
				'Youth Led Organization'    => __( 'Youth Led Organization', 'gsf-scorm-lite' ),
			),
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

	$value   = get_user_meta( $user_id, $key, true );
	$choices = (array) ( $field['choices'] ?? array() );

	if ( is_array( $value ) ) {
		$labels = array();
		foreach ( $value as $selected ) {
			$selected = (string) $selected;
			if ( isset( $choices[ $selected ] ) ) {
				$labels[] = $choices[ $selected ];
			}
		}

		return implode( '; ', $labels );
	}

	$value = (string) $value;

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
				'title'                          => __( 'Gender', 'gsf-scorm-lite' ),
				'metakey'                        => 'gsf_sex',
				'type'                           => 'select',
				'label'                          => __( 'Gender', 'gsf-scorm-lite' ),
				'public'                         => 0,
				'placeholder'                    => __( 'Select', 'gsf-scorm-lite' ),
				'custom_dropdown_options_source' => 'gsf_scorm_lite_um_sex_options',
				'position'                       => 6,
			)
		),
		'gsf_sex_other'          => array(
			'title'      => __( 'Please specify gender', 'gsf-scorm-lite' ),
			'metakey'    => 'gsf_sex_other',
			'type'       => 'text',
			'label'      => __( 'Please specify gender', 'gsf-scorm-lite' ),
			'required'   => 0,
			'public'     => 0,
			'editable'   => true,
			'position'   => 7,
			'in_row'     => '_um_row_1',
			'in_sub_row' => '0',
			'in_column'  => 1,
			'in_group'   => '',
			'conditional_action'   => 'show',
			'conditional_field'    => 'gsf_sex',
			'conditional_operator' => 'equals to',
			'conditional_value'    => 'other',
			'conditions'           => array(
				array( 'show', 'gsf_sex', 'equals to', 'other' ),
			),
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
				'position'                       => 8,
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
				'position'                       => 9,
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
			'position'   => 10,
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
				'position'                       => 11,
			)
		),
		'gsf_stakeholder_groups' => array(
			'title'      => __( 'Stakeholder groups', 'gsf-scorm-lite' ),
			'metakey'    => 'gsf_stakeholder_groups',
			'type'       => 'checkbox',
			'label'      => __( 'Do you belong to any of the following stakeholder groups? Select all that apply.', 'gsf-scorm-lite' ),
			'options'    => array_values( (array) ( gsf_scorm_lite_profile_fields()['gsf_stakeholder_groups']['choices'] ?? array() ) ),
			'required'   => 0,
			'public'     => 0,
			'editable'   => true,
			'position'   => 12,
			'in_row'     => '_um_row_1',
			'in_sub_row' => '0',
			'in_column'  => 1,
			'in_group'   => '',
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
		$existing = isset( $fields[ $key ] ) && is_array( $fields[ $key ] ) ? $fields[ $key ] : array();
		$updated  = array_merge( $existing, $field );

		if ( $updated !== $existing ) {
			$fields[ $key ] = $updated;
			$changed        = true;
		}
	}

	if ( $changed ) {
		update_post_meta( $form_id, '_um_custom_fields', $fields );
	}
}
add_action( 'init', 'gsf_scorm_lite_sync_um_registration_fields', 20 );

/**
 * Migrates the previous binary Sex values to the new Gender values once.
 */
function gsf_scorm_lite_migrate_gender_values() {
	if ( '1' === get_option( 'gsf_scorm_lite_gender_values_migrated' ) ) {
		return;
	}

	$mappings = array(
		'female' => 'women',
		'male'   => 'men',
	);

	foreach ( $mappings as $legacy => $updated ) {
		$user_ids = get_users(
			array(
				'fields'     => 'ids',
				'meta_key'   => 'gsf_sex',
				'meta_value' => $legacy,
			)
		);

		foreach ( $user_ids as $user_id ) {
			update_user_meta( $user_id, 'gsf_sex', $updated );
		}
	}

	update_option( 'gsf_scorm_lite_gender_values_migrated', '1', false );
}
add_action( 'init', 'gsf_scorm_lite_migrate_gender_values', 25 );

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
			<?php $value = get_user_meta( $user->ID, $key, true ); ?>
			<tr>
				<th><label for="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $field['label'] ); ?></label></th>
				<td>
					<?php if ( 'checkbox' === ( $field['type'] ?? '' ) ) : ?>
						<fieldset id="<?php echo esc_attr( $key ); ?>">
							<?php foreach ( (array) $field['choices'] as $choice_key => $choice_label ) : ?>
								<label style="display:block;margin:.35rem 0;">
									<input type="checkbox" name="<?php echo esc_attr( $key ); ?>[]" value="<?php echo esc_attr( $choice_key ); ?>" <?php checked( in_array( $choice_key, (array) $value, true ) ); ?> />
									<?php echo esc_html( $choice_label ); ?>
								</label>
							<?php endforeach; ?>
						</fieldset>
					<?php else : ?>
						<select name="<?php echo esc_attr( $key ); ?>" id="<?php echo esc_attr( $key ); ?>">
							<option value=""><?php esc_html_e( 'Select', 'gsf-scorm-lite' ); ?></option>
							<?php foreach ( (array) $field['choices'] as $choice_key => $choice_label ) : ?>
								<option value="<?php echo esc_attr( $choice_key ); ?>" <?php selected( $value, $choice_key ); ?>><?php echo esc_html( $choice_label ); ?></option>
							<?php endforeach; ?>
						</select>
					<?php endif; ?>
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

		if ( 'checkbox' === ( $field['type'] ?? '' ) ) {
			$submitted = (array) ( $_POST[ $key ] ?? array() );
			$submitted = array_map(
				static function ( $value ) {
					return sanitize_text_field( wp_unslash( $value ) );
				},
				$submitted
			);
			$value = array_values( array_intersect( $submitted, array_keys( $choices ) ) );
			update_user_meta( $user_id, $key, $value );
			continue;
		}

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

	$incomplete_count = 0;
	foreach ( $enrollments as $enrollment ) {
		if ( ! gsf_scorm_lite_is_enrollment_complete( $enrollment ) ) {
			++$incomplete_count;
		}
	}

	$out = '<div class="gsf-lms-dashboard">';
	if ( $incomplete_count ) {
		$out .= '<div class="gsf-lms-progress-alert" role="status">';
		$out .= '<strong>' . esc_html__( 'Keep going—you have learning in progress.', 'gsf-scorm-lite' ) . '</strong> ';
		$out .= esc_html( _n( 'Continue your course to complete your learning.', 'Continue your courses to complete your learning.', $incomplete_count, 'gsf-scorm-lite' ) );
		$out .= '</div>';
	}

	$out .= '<table><thead><tr><th>' . esc_html__( 'Course', 'gsf-scorm-lite' ) . '</th><th>' . esc_html__( 'Status', 'gsf-scorm-lite' ) . '</th><th>' . esc_html__( 'Score', 'gsf-scorm-lite' ) . '</th><th></th></tr></thead><tbody>';

	foreach ( $enrollments as $enrollment ) {
		$course = get_post( (int) $enrollment['course_id'] );

		if ( ! $course ) {
			continue;
		}

		$out .= '<tr>';
		$out .= '<td>' . esc_html( get_the_title( $course ) ) . '</td>';
		$out .= '<td>' . esc_html( gsf_scorm_lite_status_label( $enrollment['status'] ) ) . '</td>';
		$out .= '<td>' . esc_html( $enrollment['score_raw'] ) . '</td>';
		$action_label = gsf_scorm_lite_is_enrollment_complete( $enrollment ) ? __( 'View course', 'gsf-scorm-lite' ) : __( 'Continue course', 'gsf-scorm-lite' );
		$out .= '<td><a class="button button--primary" href="' . esc_url( get_permalink( $course ) ) . '">' . esc_html( $action_label ) . '</a></td>';
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
 * Gets a user's feedback survey for a course.
 *
 * @param int $user_id   User ID.
 * @param int $course_id Course ID.
 * @return array<string,mixed>
 */
function gsf_scorm_lite_get_feedback( $user_id, $course_id ) {
	global $wpdb;

	$table = gsf_scorm_lite_feedback_table_name();
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
 * Returns whether a learner has completed course feedback.
 *
 * @param int $user_id   User ID.
 * @param int $course_id Course ID.
 * @return bool
 */
function gsf_scorm_lite_has_feedback( $user_id, $course_id ) {
	return ! empty( gsf_scorm_lite_get_feedback( $user_id, $course_id ) );
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

	if ( ! gsf_scorm_lite_can_submit_feedback( $user_id, $course_id ) ) {
		wp_die( esc_html__( 'Complete the course before submitting feedback.', 'gsf-scorm-lite' ) );
	}

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

	gsf_scorm_lite_maybe_issue_certificate( $user_id, $course_id );

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

	$user_id       = get_current_user_id();
	$enrollment    = gsf_scorm_lite_get_enrollment( $user_id, $course_id );
	$is_completed  = gsf_scorm_lite_can_submit_feedback( $user_id, $course_id );
	$has_feedback  = gsf_scorm_lite_has_feedback( $user_id, $course_id );
	$has_certificate = '1' === get_post_meta( $course_id, '_gsf_certificate_enabled', true );
	$slug          = get_post_meta( $course_id, '_gsf_scorm_slug', true );
	$locked_class  = $is_completed ? '' : ' gsf-lms-feedback--locked';
	$form_style    = $is_completed ? '' : ' style="display:none"';
	$disabled_attr = $is_completed ? '' : ' disabled';

	if ( empty( $enrollment ) ) {
		return '';
	}

	$out  = '<section id="gsf-lms-feedback" class="gsf-lms-panel gsf-lms-feedback' . esc_attr( $locked_class ) . '" data-gsf-lms-feedback-course-slug="' . esc_attr( $slug ) . '">';
	$out .= '<h2>' . esc_html__( 'Course feedback', 'gsf-scorm-lite' ) . '</h2>';

	if ( $has_feedback ) {
		$out .= '<p class="gsf-lms-feedback__notice">' . esc_html( $has_certificate ? __( 'Feedback survey completed. Your course certificate is now available.', 'gsf-scorm-lite' ) : __( 'Feedback survey completed.', 'gsf-scorm-lite' ) ) . '</p>';
		if ( $has_certificate ) {
			$out .= '<p><a class="button button--primary" href="#gsf-lms-certificate">' . esc_html__( 'View certificate', 'gsf-scorm-lite' ) . '</a></p>';
		}

		return $out . '</section>';
	}

	$out .= '<p class="gsf-lms-feedback__notice">' . esc_html( $is_completed ? __( 'Your course is complete. Submit the feedback survey to unlock your certificate.', 'gsf-scorm-lite' ) : __( 'Complete the course to 100% to unlock the feedback survey.', 'gsf-scorm-lite' ) ) . '</p>';
	$out .= '<p><button type="button" class="button button--primary gsf-lms-feedback__toggle"' . $disabled_attr . '>' . esc_html__( 'Submit feedback survey', 'gsf-scorm-lite' ) . '</button></p>';
	$out .= '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="gsf-lms-feedback__form"' . $form_style . '>';
	$out .= '<input type="hidden" name="action" value="gsf_lms_feedback" />';
	$out .= '<input type="hidden" name="course_id" value="' . esc_attr( $course_id ) . '" />';
	$out .= wp_nonce_field( 'gsf_lms_feedback_' . $course_id, '_wpnonce', true, false );
	$out .= '<fieldset><legend>' . esc_html__( 'On a scale of 1-5, with 1 being the lowest and 5 the highest, how would you rate this capacity building course in relation to your improved knowledge and understanding of the subject matter?', 'gsf-scorm-lite' ) . '</legend>';
	foreach ( range( 1, 5 ) as $value ) {
		$out .= '<label class="gsf-lms-radio"><input type="radio" name="knowledge_rating" value="' . esc_attr( $value ) . '" required' . $disabled_attr . ' /> ' . esc_html( (string) $value ) . '</label>';
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
		$out .= '<label class="gsf-lms-check"><input type="checkbox" name="application_plan[]" value="' . esc_attr( $key ) . '"' . $disabled_attr . ' /> ' . esc_html( $label ) . '</label>';
	}
	$out .= '<p><label>' . esc_html__( 'Other, please specify', 'gsf-scorm-lite' ) . '<br><input type="text" name="application_other"' . $disabled_attr . ' /></label></p>';
	$out .= '</fieldset>';
	$out .= '<fieldset><legend>' . esc_html__( 'Did the capacity building course meet your expectations?', 'gsf-scorm-lite' ) . '</legend>';
	$out .= '<label class="gsf-lms-radio"><input type="radio" name="met_expectations" value="yes" required' . $disabled_attr . ' /> ' . esc_html__( 'Yes', 'gsf-scorm-lite' ) . '</label>';
	$out .= '<label class="gsf-lms-radio"><input type="radio" name="met_expectations" value="no" required' . $disabled_attr . ' /> ' . esc_html__( 'No', 'gsf-scorm-lite' ) . '</label>';
	$out .= '</fieldset>';
	$out .= '<p><label>' . esc_html__( 'What will you take away from this capacity building course to support your daily work in climate change adaptation and gender responsive programming?', 'gsf-scorm-lite' ) . '<br><textarea name="takeaway" rows="4" required' . $disabled_attr . '></textarea></label></p>';
	$out .= '<p><label>' . esc_html__( 'How could we improve this capacity building course in the future?', 'gsf-scorm-lite' ) . '<br><textarea name="improvements" rows="4"' . $disabled_attr . '></textarea></label></p>';
	$out .= '<p><button type="submit" class="button button--primary"' . $disabled_attr . '>' . esc_html__( 'Submit feedback', 'gsf-scorm-lite' ) . '</button></p>';
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

	$user_id      = get_current_user_id();
	$has_feedback = gsf_scorm_lite_has_feedback( $user_id, $course_id );
	$certificate  = $has_feedback ? gsf_scorm_lite_get_certificate( $user_id, $course_id ) : array();

	if ( $has_feedback && empty( $certificate ) ) {
		$certificate = gsf_scorm_lite_maybe_issue_certificate( $user_id, $course_id );
	}

	if ( ! empty( $certificate ) ) {
		$out .= '<div id="gsf-lms-certificate">';
		$out .= gsf_scorm_lite_render_certificate_card( $user_id, $course_id, $certificate );
		$out .= '</div>';
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
	$launch_file  = gsf_scorm_lite_get_package_launch_file( $slug );
	$launch_path  = '' !== $launch_file ? trailingslashit( $base['path'] ) . $slug . '/' . $launch_file : '';
	$launch_url   = '' !== $launch_file ? trailingslashit( $base['url'] ) . $slug . '/' . $launch_file : '';
	$height       = absint( $atts['height'] );
	$current_user = wp_get_current_user();

	if ( '' === $launch_path || ! file_exists( $launch_path ) ) {
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
	$css = '.gsf-scorm-lite{width:100%;margin:2rem 0;overflow:hidden;border:1px solid rgba(19,71,66,.16);border-radius:8px;background:#fff}.gsf-scorm-lite__frame{display:block;width:100%;min-height:760px;border:0}.gsf-scorm-lite__notice{padding:1rem;border:1px solid rgba(126,53,37,.24);border-radius:8px;background:#fff7f2;color:#7e3525}.single-gsf_course .entry-hero-image{max-width:34rem;margin:1.5rem auto 2rem;overflow:hidden;border-radius:8px}.single-gsf_course .entry-hero-image img{display:block;width:100%;height:auto;max-height:20rem;object-fit:cover}.gsf-lms-course-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:1.4rem;margin:2rem 0}.gsf-lms-course-card,.gsf-lms-panel{padding:1rem;border:1px solid rgba(19,71,66,.16);border-radius:8px;background:#fff}.gsf-lms-course-card{display:flex;flex-direction:column;align-items:flex-start;gap:.65rem;padding:1.35rem}.gsf-lms-course-card__image{width:100%;height:auto;margin-bottom:.35rem;border-radius:6px}.gsf-lms-course-card h3{margin:.25rem 0 0;line-height:1.15}.gsf-lms-course-card p{margin:.15rem 0}.gsf-lms-course-card>.button{align-self:center;margin-top:auto}.gsf-lms-course-card>.button.button--primary{color:#fff}.gsf-lms-course-card>.button.button--primary:hover,.gsf-lms-course-card>.button.button--primary:focus-visible{color:#fff}.gsf-lms-meta{color:#54717b;font-weight:700}.gsf-lms-progress-alert{margin:0 0 1rem;padding:1rem 1.1rem;border:1px solid rgba(28,166,166,.45);border-left:5px solid #1ca6a6;border-radius:8px;background:#eefafa;color:#0a3742}.gsf-lms-dashboard table{width:100%;border-collapse:collapse}.gsf-lms-dashboard th,.gsf-lms-dashboard td{padding:.75rem;border-bottom:1px solid rgba(19,71,66,.12);text-align:left}.gsf-lms-feedback fieldset{margin:1rem 0;padding:1rem;border:1px solid rgba(19,71,66,.16);border-radius:8px}.gsf-lms-feedback legend{font-weight:800;color:#0a3742}.gsf-lms-feedback textarea,.gsf-lms-feedback input[type=text]{width:100%;max-width:42rem}.gsf-lms-radio,.gsf-lms-check{display:block;margin:.45rem 0}.gsf-lms-certificate{margin:2rem 0;padding:1rem;border:1px solid color-mix(in srgb,var(--gsf-cert-accent,#1ca6a6) 40%,#fff);border-radius:8px;background:#fff}.gsf-lms-certificate__inner{padding:2rem;text-align:center;border:3px solid var(--gsf-cert-primary,#005e7a);border-radius:6px}.gsf-lms-certificate__logo{display:block;width:min(15rem,60%);height:auto;margin:0 auto 1rem}.gsf-lms-certificate__partner-row{display:flex;align-items:center;justify-content:space-between;gap:1rem 2rem;flex-wrap:wrap;margin:0 auto 1.25rem;max-width:44rem}.gsf-lms-certificate__partner-logo{display:block;max-width:12rem;max-height:4.5rem;width:auto;height:auto;object-fit:contain}.gsf-lms-certificate__partner-logo--canada{max-width:11rem}.gsf-lms-certificate__partner-logo--cbf{max-width:13rem}.gsf-lms-certificate__eyebrow{margin:0 0 1rem;color:var(--gsf-cert-accent,#1ca6a6);font-weight:800;text-transform:uppercase;letter-spacing:.08em}.gsf-lms-certificate h2{margin:.25rem 0 1.5rem;color:var(--gsf-cert-primary,#005e7a);font-size:clamp(2rem,5vw,3rem)}.gsf-lms-certificate__intro,.gsf-lms-certificate__body{margin:.5rem 0;color:#54717b}.gsf-lms-certificate__learner{margin:.6rem 0;color:#123;font-size:clamp(1.8rem,5vw,2.6rem);font-weight:900}.gsf-lms-certificate__course{margin:.6rem auto 1.5rem;max-width:46rem;color:var(--gsf-cert-primary,#005e7a);font-size:1.25rem;font-weight:800}.gsf-lms-certificate__meta{display:flex;flex-wrap:wrap;justify-content:center;gap:.75rem 1.25rem;color:#54717b;font-size:.95rem}.gsf-lms-certificate__print{margin:1.5rem 0 0}@media(max-width:640px){.gsf-lms-certificate__partner-row{justify-content:center}.gsf-lms-dashboard{overflow-x:auto}}@media print{body *{visibility:hidden}.gsf-lms-certificate,.gsf-lms-certificate *{visibility:visible}.gsf-lms-certificate{position:absolute;inset:0;margin:0;border:0}.gsf-lms-certificate__print{display:none}}';
	wp_register_style( 'gsf-scorm-lite', false, array(), GSF_SCORM_LITE_VERSION );
	wp_enqueue_style( 'gsf-scorm-lite' );
	wp_add_inline_style( 'gsf-scorm-lite', $css );
	wp_add_inline_style( 'gsf-scorm-lite', '.gsf-lms-feedback__notice{margin:.25rem 0 1rem;color:#54717b;font-weight:700}.gsf-lms-feedback--locked{background:#f7faf9}.gsf-lms-feedback--locked .gsf-lms-feedback__toggle,.gsf-lms-feedback__toggle:disabled{opacity:.55;cursor:not-allowed}.gsf-lms-feedback__form{margin-top:1rem}' );
}
add_action( 'wp_enqueue_scripts', 'gsf_scorm_lite_enqueue_styles' );

/**
 * Adds admin menu.
 */
function gsf_scorm_lite_admin_menu() {
	add_menu_page(
		__( 'Learn', 'gsf-scorm-lite' ),
		__( 'Learn', 'gsf-scorm-lite' ),
		GSF_SCORM_LITE_MANAGE_CAP,
		'gsf-scorm-lite',
		'gsf_scorm_lite_render_admin_page',
		'dashicons-welcome-learn-more',
		58
	);

	add_submenu_page(
		'gsf-scorm-lite',
		__( 'Upload SCORM Package', 'gsf-scorm-lite' ),
		__( 'Upload SCORM Package', 'gsf-scorm-lite' ),
		GSF_SCORM_LITE_MANAGE_CAP,
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
		GSF_SCORM_LITE_MANAGE_CAP,
		'gsf-scorm-lite-progress',
		'gsf_scorm_lite_render_progress_page'
	);

	add_submenu_page(
		'gsf-scorm-lite',
		__( 'Email Notifications', 'gsf-scorm-lite' ),
		__( 'Email Notifications', 'gsf-scorm-lite' ),
		GSF_SCORM_LITE_MANAGE_CAP,
		'gsf-scorm-lite-reminders',
		'gsf_scorm_lite_render_reminder_settings_page'
	);

	// The GSF themes consolidate plugin screens under their own Learn menu.
	add_submenu_page(
		'gsf-admin-learn',
		__( 'Email Notifications', 'gsf-scorm-lite' ),
		__( 'Email Notifications', 'gsf-scorm-lite' ),
		GSF_SCORM_LITE_MANAGE_CAP,
		'gsf-scorm-lite-reminders',
		'gsf_scorm_lite_render_reminder_settings_page'
	);

	add_submenu_page(
		'gsf-scorm-lite',
		__( 'Certificate Settings', 'gsf-scorm-lite' ),
		__( 'Certificate Settings', 'gsf-scorm-lite' ),
		GSF_SCORM_LITE_MANAGE_CAP,
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
 * Returns a human-readable PHP upload error.
 *
 * @param int $error_code PHP upload error code.
 * @return string
 */
function gsf_scorm_lite_upload_error_message( $error_code ) {
	$upload_max = size_format( wp_max_upload_size() );

	switch ( (int) $error_code ) {
		case UPLOAD_ERR_INI_SIZE:
		case UPLOAD_ERR_FORM_SIZE:
			return sprintf(
				/* translators: %s: maximum upload size. */
				__( 'The ZIP file is larger than the server upload limit (%s).', 'gsf-scorm-lite' ),
				$upload_max
			);
		case UPLOAD_ERR_PARTIAL:
			return __( 'The ZIP file was only partially uploaded. Please try again.', 'gsf-scorm-lite' );
		case UPLOAD_ERR_NO_FILE:
			return __( 'Choose a SCORM ZIP file.', 'gsf-scorm-lite' );
		case UPLOAD_ERR_NO_TMP_DIR:
			return __( 'The server is missing a temporary upload directory.', 'gsf-scorm-lite' );
		case UPLOAD_ERR_CANT_WRITE:
			return __( 'The server could not write the uploaded ZIP file.', 'gsf-scorm-lite' );
		case UPLOAD_ERR_EXTENSION:
			return __( 'A PHP extension stopped the upload.', 'gsf-scorm-lite' );
		default:
			return __( 'The ZIP file could not be uploaded.', 'gsf-scorm-lite' );
	}
}

/**
 * Normalizes a package-relative path and rejects path traversal.
 *
 * @param string $path Raw package path.
 * @return string
 */
function gsf_scorm_lite_normalize_package_path( $path ) {
	$path = str_replace( '\\', '/', rawurldecode( (string) $path ) );
	$path = preg_replace( '~[?#].*$~', '', $path );
	$path = ltrim( $path, '/' );

	if ( '' === $path || preg_match( '#^[a-z][a-z0-9+.-]*:#i', $path ) ) {
		return '';
	}

	$parts = array();

	foreach ( explode( '/', $path ) as $part ) {
		if ( '' === $part || '.' === $part ) {
			continue;
		}

		if ( '..' === $part ) {
			return '';
		}

		$parts[] = $part;
	}

	return implode( '/', $parts );
}

/**
 * Reads the launch file from a SCORM manifest when possible.
 *
 * @param string $manifest_path Absolute manifest path.
 * @return string Package-relative launch file path.
 */
function gsf_scorm_lite_get_manifest_launch_file( $manifest_path ) {
	if ( ! file_exists( $manifest_path ) || ! function_exists( 'simplexml_load_file' ) ) {
		return '';
	}

	$manifest = simplexml_load_file( $manifest_path );

	if ( ! $manifest instanceof SimpleXMLElement ) {
		return '';
	}

	$default_org = '';
	$organizations = $manifest->organizations;

	if ( $organizations instanceof SimpleXMLElement ) {
		$default_org = (string) $organizations['default'];
	}

	$identifier_ref = '';

	foreach ( $manifest->organizations->organization as $organization ) {
		if ( '' !== $default_org && (string) $organization['identifier'] !== $default_org ) {
			continue;
		}

		foreach ( $organization->xpath( './/*[local-name()="item"][@identifierref]' ) as $item ) {
			$identifier_ref = (string) $item['identifierref'];
			break 2;
		}
	}

	$resource_href = '';

	foreach ( $manifest->resources->resource as $resource ) {
		if ( '' !== $identifier_ref && (string) $resource['identifier'] !== $identifier_ref ) {
			continue;
		}

		$resource_href = (string) $resource['href'];

		if ( '' !== $resource_href ) {
			break;
		}
	}

	return gsf_scorm_lite_normalize_package_path( $resource_href );
}

/**
 * Finds a package launch file using the manifest first and common fallbacks second.
 *
 * @param string $package_dir Absolute package directory path.
 * @return string Package-relative launch file path.
 */
function gsf_scorm_lite_find_launch_file( $package_dir ) {
	$manifest_launch = gsf_scorm_lite_get_manifest_launch_file( trailingslashit( $package_dir ) . 'imsmanifest.xml' );

	if ( '' !== $manifest_launch && file_exists( trailingslashit( $package_dir ) . $manifest_launch ) ) {
		return $manifest_launch;
	}

	$fallbacks = array(
		'scormdriver/indexAPI.html',
		'scormdriver/indexAPI.htm',
		'index_lms.html',
		'index_lms.htm',
		'story.html',
		'story_html5.html',
		'index.html',
		'index.htm',
	);

	foreach ( $fallbacks as $fallback ) {
		if ( file_exists( trailingslashit( $package_dir ) . $fallback ) ) {
			return $fallback;
		}
	}

	return '';
}

/**
 * Writes package metadata used by the launcher.
 *
 * @param string $package_dir Absolute package directory path.
 * @param string $slug        Package slug.
 * @param string $launch_file Package-relative launch file path.
 * @return void
 */
function gsf_scorm_lite_write_package_metadata( $package_dir, $slug, $launch_file ) {
	$data = array(
		'slug'        => sanitize_title( $slug ),
		'launch_file' => gsf_scorm_lite_normalize_package_path( $launch_file ),
		'imported_at' => current_time( 'mysql' ),
	);

	file_put_contents( trailingslashit( $package_dir ) . '.gsf-scorm-package.json', wp_json_encode( $data, JSON_PRETTY_PRINT ) );
}

/**
 * Returns the package launch file for a package slug.
 *
 * @param string $slug Package slug.
 * @return string Package-relative launch file path.
 */
function gsf_scorm_lite_get_package_launch_file( $slug ) {
	$base        = gsf_scorm_lite_package_base();
	$slug        = sanitize_title( $slug );
	$package_dir = trailingslashit( $base['path'] ) . $slug;

	if ( ! is_dir( $package_dir ) ) {
		return '';
	}

	$metadata_path = trailingslashit( $package_dir ) . '.gsf-scorm-package.json';

	if ( file_exists( $metadata_path ) ) {
		$metadata = json_decode( (string) file_get_contents( $metadata_path ), true );
		$launch   = is_array( $metadata ) ? gsf_scorm_lite_normalize_package_path( $metadata['launch_file'] ?? '' ) : '';

		if ( '' !== $launch && file_exists( trailingslashit( $package_dir ) . $launch ) ) {
			return $launch;
		}
	}

	$launch = gsf_scorm_lite_find_launch_file( $package_dir );

	if ( '' !== $launch ) {
		gsf_scorm_lite_write_package_metadata( $package_dir, $slug, $launch );
	}

	return $launch;
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

	if ( ! gsf_scorm_lite_current_user_can_manage_learn() ) {
		return '<div class="notice notice-error"><p>' . esc_html__( 'You do not have permission to import SCORM packages.', 'gsf-scorm-lite' ) . '</p></div>';
	}

	check_admin_referer( 'gsf_scorm_lite_upload', 'gsf_scorm_lite_nonce' );

	if ( empty( $_FILES['gsf_scorm_zip'] ) || ! is_array( $_FILES['gsf_scorm_zip'] ) ) {
		return '<div class="notice notice-error"><p>' . esc_html__( 'Choose a SCORM ZIP file.', 'gsf-scorm-lite' ) . '</p></div>';
	}

	$upload_error = (int) ( $_FILES['gsf_scorm_zip']['error'] ?? UPLOAD_ERR_NO_FILE );

	if ( UPLOAD_ERR_OK !== $upload_error ) {
		return '<div class="notice notice-error"><p>' . esc_html( gsf_scorm_lite_upload_error_message( $upload_error ) ) . '</p></div>';
	}

	if ( empty( $_FILES['gsf_scorm_zip']['tmp_name'] ) || ! is_uploaded_file( $_FILES['gsf_scorm_zip']['tmp_name'] ) ) {
		return '<div class="notice notice-error"><p>' . esc_html__( 'The uploaded SCORM ZIP could not be read.', 'gsf-scorm-lite' ) . '</p></div>';
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

	if ( ! wp_mkdir_p( $temp_dir ) || ! wp_mkdir_p( $base['path'] ) ) {
		return '<div class="notice notice-error"><p>' . esc_html__( 'Could not create SCORM package directory.', 'gsf-scorm-lite' ) . '</p></div>';
	}

	require_once ABSPATH . 'wp-admin/includes/file.php';

	$unzipped = unzip_file( $_FILES['gsf_scorm_zip']['tmp_name'], $temp_dir );

	if ( is_wp_error( $unzipped ) ) {
		gsf_scorm_lite_delete_dir( $temp_dir );

		return '<div class="notice notice-error"><p>' . esc_html(
			sprintf(
				/* translators: %s: unzip error message. */
				__( 'Could not unzip the SCORM package: %s', 'gsf-scorm-lite' ),
				$unzipped->get_error_message()
			)
		) . '</p></div>';
	}

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

	$launch_file = gsf_scorm_lite_find_launch_file( $final_dir );

	if ( '' === $launch_file ) {
		return '<div class="notice notice-warning"><p>' . esc_html__( 'Package imported, but Learn could not identify a launch file from imsmanifest.xml or the common SCORM launch filenames.', 'gsf-scorm-lite' ) . '</p></div>';
	}

	gsf_scorm_lite_write_package_metadata( $final_dir, $slug, $launch_file );

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
	) . '</p><p>' . esc_html(
		sprintf(
			/* translators: %s: launch file path. */
			__( 'Launch file: %s', 'gsf-scorm-lite' ),
			$launch_file
		)
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

	if ( ! gsf_scorm_lite_current_user_can_manage_learn() ) {
		return '<div class="notice notice-error"><p>' . esc_html__( 'You do not have permission to create starter courses.', 'gsf-scorm-lite' ) . '</p></div>';
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
	if ( ! gsf_scorm_lite_current_user_can_manage_learn() ) {
		wp_die( esc_html__( 'You do not have permission to manage Learn.', 'gsf-scorm-lite' ) );
	}

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
 * Gets admin learner progress report rows.
 *
 * @param int    $course_filter Course ID filter.
 * @param string $status_filter Enrollment status filter.
 * @return array<int,array<string,mixed>>
 */
function gsf_scorm_lite_get_progress_report_rows( $course_filter = 0, $status_filter = '' ) {
	global $wpdb;

	$enrollments_table  = gsf_scorm_lite_enrollments_table_name();
	$attempts_table     = gsf_scorm_lite_table_name();
	$feedback_table     = gsf_scorm_lite_feedback_table_name();
	$certificates_table = gsf_scorm_lite_certificates_table_name();
	$course_filter      = absint( $course_filter );
	$status_filter      = sanitize_key( $status_filter );
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

	return $wpdb->get_results( $sql, ARRAY_A );
}

/**
 * Formats progress rows for table export.
 *
 * @param array<int,array<string,mixed>> $rows Report rows.
 * @return array<int,array<string,string>>
 */
function gsf_scorm_lite_format_progress_export_rows( array $rows ) {
	$export_rows = array();
	$plan_labels = gsf_scorm_lite_application_plan_labels();

	foreach ( $rows as $row ) {
		$survey       = ! empty( $row['comments'] ) ? gsf_scorm_lite_parse_feedback_survey( $row['comments'] ) : array();
		$plans        = array();
		$profile_bits = array();
		$gender       = gsf_scorm_lite_get_profile_display_value( (int) $row['user_id'], 'gsf_sex' );
		$stakeholders = gsf_scorm_lite_get_profile_display_value( (int) $row['user_id'], 'gsf_stakeholder_groups' );

		foreach ( gsf_scorm_lite_profile_fields() as $profile_key => $profile_field ) {
			if ( in_array( $profile_key, array( 'gsf_sex', 'gsf_stakeholder_groups' ), true ) ) {
				continue;
			}

			$profile_value = gsf_scorm_lite_get_profile_display_value( (int) $row['user_id'], $profile_key );
			if ( '' !== $profile_value ) {
				$profile_bits[] = $profile_field['label'] . ': ' . $profile_value;
			}
		}

		foreach ( (array) ( $survey['application_plan'] ?? array() ) as $plan ) {
			$plans[] = $plan_labels[ $plan ] ?? $plan;
		}

		if ( ! empty( $survey['application_other'] ) ) {
			$plans[] = $survey['application_other'];
		}

		$export_rows[] = array(
			'Student'                  => (string) ( $row['display_name'] ?: __( 'Unknown user', 'gsf-scorm-lite' ) ),
			'Email'                    => (string) ( $row['user_email'] ?? '' ),
			'Gender'                   => $gender,
			'Stakeholder Groups'       => $stakeholders,
			'Profile'                  => implode( ' | ', $profile_bits ),
			'Course'                   => (string) ( $row['post_title'] ?? '' ),
			'SCORM Slug'               => (string) ( $row['scorm_slug'] ?? '' ),
			'Status'                   => gsf_scorm_lite_status_label( (string) ( $row['status'] ?? '' ) ),
			'SCORM Status'             => (string) ( $row['scorm_lesson_status'] ?? '' ),
			'Score'                    => (string) ( $row['score_raw'] ?? '' ),
			'Enrolled'                 => (string) ( $row['enrolled_at'] ?? '' ),
			'Completed'                => (string) ( $row['completed_at'] ?? '' ),
			'SCORM Commit'             => (string) ( $row['scorm_updated_at'] ?? '' ),
			'Bookmark'                 => (string) ( $row['lesson_location'] ?? '' ),
			'Feedback Rating'          => ! empty( $row['rating'] ) ? (string) $row['rating'] . '/5' : '',
			'Met Expectations'         => (string) ( $survey['met_expectations'] ?? '' ),
			'Application Plan'         => implode( '; ', $plans ),
			'Key Takeaway'             => (string) ( $survey['takeaway'] ?? '' ),
			'Suggested Improvements'   => (string) ( $survey['improvements'] ?? '' ),
			'Certificate Code'         => (string) ( $row['certificate_code'] ?? '' ),
			'Certificate Issued'       => (string) ( $row['issued_at'] ?? '' ),
		);
	}

	return $export_rows;
}

/**
 * Prepares an export cell for spreadsheet applications.
 *
 * @param mixed $value Cell value.
 * @return string
 */
function gsf_scorm_lite_prepare_export_cell( $value ) {
	$value = wp_strip_all_tags( (string) $value );

	if ( preg_match( '/^[=\-+@]/', $value ) ) {
		return "'" . $value;
	}

	return $value;
}

/**
 * Handles student progress CSV and Excel exports.
 */
function gsf_scorm_lite_handle_progress_export() {
	if ( ! gsf_scorm_lite_current_user_can_manage_learn() ) {
		wp_die( esc_html__( 'You do not have permission to export student progress.', 'gsf-scorm-lite' ) );
	}

	check_admin_referer( 'gsf_scorm_lite_export_progress' );

	$course_filter = absint( $_GET['course_id'] ?? 0 );
	$status_filter = sanitize_key( $_GET['status'] ?? '' );
	$format        = 'xls' === sanitize_key( $_GET['format'] ?? 'csv' ) ? 'xls' : 'csv';
	$rows          = gsf_scorm_lite_format_progress_export_rows( gsf_scorm_lite_get_progress_report_rows( $course_filter, $status_filter ) );
	$filename      = 'gsf-student-progress-' . gmdate( 'Y-m-d-His' ) . '.' . $format;
	$headers       = ! empty( $rows ) ? array_keys( reset( $rows ) ) : array(
		'Student',
		'Email',
		'Gender',
		'Stakeholder Groups',
		'Profile',
		'Course',
		'SCORM Slug',
		'Status',
		'SCORM Status',
		'Score',
		'Enrolled',
		'Completed',
		'SCORM Commit',
		'Bookmark',
		'Feedback Rating',
		'Met Expectations',
		'Application Plan',
		'Key Takeaway',
		'Suggested Improvements',
		'Certificate Code',
		'Certificate Issued',
	);

	if ( 'xls' === $format ) {
		header( 'Content-Type: application/vnd.ms-excel; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		header( 'Pragma: no-cache' );
		header( 'Expires: 0' );

		echo '<table><thead><tr>';
		foreach ( $headers as $header ) {
			echo '<th>' . esc_html( $header ) . '</th>';
		}
		echo '</tr></thead><tbody>';
		foreach ( $rows as $row ) {
			echo '<tr>';
			foreach ( $headers as $header ) {
				echo '<td>' . esc_html( gsf_scorm_lite_prepare_export_cell( $row[ $header ] ?? '' ) ) . '</td>';
			}
			echo '</tr>';
		}
		echo '</tbody></table>';
		exit;
	}

	header( 'Content-Type: text/csv; charset=utf-8' );
	header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
	header( 'Pragma: no-cache' );
	header( 'Expires: 0' );

	$output = fopen( 'php://output', 'w' );
	fwrite( $output, "\xEF\xBB\xBF" );
	fputcsv( $output, $headers );
	foreach ( $rows as $row ) {
		fputcsv( $output, array_map( static function ( $header ) use ( $row ) {
			return gsf_scorm_lite_prepare_export_cell( $row[ $header ] ?? '' );
		}, $headers ) );
	}
	fclose( $output );
	exit;
}
add_action( 'admin_post_gsf_scorm_lite_export_progress', 'gsf_scorm_lite_handle_progress_export' );

/**
 * Renders admin learner progress report.
 */
function gsf_scorm_lite_render_progress_page() {
	if ( ! gsf_scorm_lite_current_user_can_manage_learn() ) {
		wp_die( esc_html__( 'You do not have permission to view student progress.', 'gsf-scorm-lite' ) );
	}

	$course_filter = absint( $_GET['course_id'] ?? 0 );
	$status_filter = sanitize_key( $_GET['status'] ?? '' );
	$rows          = gsf_scorm_lite_get_progress_report_rows( $course_filter, $status_filter );
	$courses = get_posts(
		array(
			'post_type'      => 'gsf_course',
			'post_status'    => 'any',
			'posts_per_page' => -1,
			'orderby'        => 'title',
			'order'          => 'ASC',
		)
	);
	$export_args = array(
		'action'    => 'gsf_scorm_lite_export_progress',
		'course_id' => $course_filter,
		'status'    => $status_filter,
	);
	$csv_export_url = wp_nonce_url(
		add_query_arg( array_merge( $export_args, array( 'format' => 'csv' ) ), admin_url( 'admin-post.php' ) ),
		'gsf_scorm_lite_export_progress'
	);
	$excel_export_url = wp_nonce_url(
		add_query_arg( array_merge( $export_args, array( 'format' => 'xls' ) ), admin_url( 'admin-post.php' ) ),
		'gsf_scorm_lite_export_progress'
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
			<a class="button button-secondary" href="<?php echo esc_url( $csv_export_url ); ?>"><?php esc_html_e( 'Download CSV', 'gsf-scorm-lite' ); ?></a>
			<a class="button button-secondary" href="<?php echo esc_url( $excel_export_url ); ?>"><?php esc_html_e( 'Download Excel', 'gsf-scorm-lite' ); ?></a>
		</form>
		<?php if ( empty( $rows ) ) : ?>
			<p><?php esc_html_e( 'No enrollments found yet.', 'gsf-scorm-lite' ); ?></p>
		<?php else : ?>
			<table class="widefat striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Student', 'gsf-scorm-lite' ); ?></th>
						<th><?php esc_html_e( 'Gender', 'gsf-scorm-lite' ); ?></th>
						<th><?php esc_html_e( 'Stakeholder groups', 'gsf-scorm-lite' ); ?></th>
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
									if ( in_array( $profile_key, array( 'gsf_sex', 'gsf_stakeholder_groups' ), true ) ) {
										continue;
									}

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
							<td><?php echo esc_html( gsf_scorm_lite_get_profile_display_value( (int) $row['user_id'], 'gsf_sex' ) ); ?></td>
							<td><?php echo esc_html( gsf_scorm_lite_get_profile_display_value( (int) $row['user_id'], 'gsf_stakeholder_groups' ) ); ?></td>
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
 * Redirects back to the learner reminder settings page with a result notice.
 *
 * @param string $result Result key.
 */
function gsf_scorm_lite_redirect_reminder_settings( $result ) {
	wp_safe_redirect(
		add_query_arg(
			array(
				'page'                => 'gsf-scorm-lite-reminders',
				'reminder_result'     => sanitize_key( $result ),
			),
			admin_url( 'admin.php' )
		)
	);
	exit;
}

/**
 * Saves learner reminder settings.
 */
function gsf_scorm_lite_handle_reminder_settings_save() {
	if ( ! gsf_scorm_lite_current_user_can_manage_learn() ) {
		wp_die( esc_html__( 'You do not have permission to manage learner reminders.', 'gsf-scorm-lite' ) );
	}

	check_admin_referer( 'gsf_scorm_lite_save_reminders' );

	$subject = sanitize_text_field( wp_unslash( $_POST['subject'] ?? '' ) );
	if ( '' === $subject ) {
		$subject = __( 'Continue your GSF Hub learning', 'gsf-scorm-lite' );
	}

	update_option(
		'gsf_scorm_lite_reminder_settings',
		array(
			'enabled'         => ! empty( $_POST['enabled'] ),
			'inactivity_days' => min( 365, max( 1, absint( $_POST['inactivity_days'] ?? 7 ) ) ),
			'subject'         => $subject,
		)
	);

	$completion_subject = sanitize_text_field( wp_unslash( $_POST['completion_subject'] ?? '' ) );
	if ( '' === $completion_subject ) {
		$completion_subject = __( 'Course completed: {course_title}', 'gsf-scorm-lite' );
	}

	$recipient_values = preg_split( '/[\s,;]+/', wp_unslash( $_POST['completion_recipients'] ?? '' ), -1, PREG_SPLIT_NO_EMPTY );
	$recipients       = array_values( array_unique( array_filter( array_map( 'sanitize_email', $recipient_values ), 'is_email' ) ) );

	update_option(
		'gsf_scorm_lite_completion_notification_settings',
		array(
			'enabled'    => ! empty( $_POST['completion_enabled'] ),
			'recipients' => $recipients,
			'subject'    => $completion_subject,
		)
	);

	gsf_scorm_lite_redirect_reminder_settings( 'saved' );
}
add_action( 'admin_post_gsf_scorm_lite_save_reminders', 'gsf_scorm_lite_handle_reminder_settings_save' );

/**
 * Sends an administrator-requested test reminder.
 */
function gsf_scorm_lite_handle_reminder_test_email() {
	if ( ! gsf_scorm_lite_current_user_can_manage_learn() ) {
		wp_die( esc_html__( 'You do not have permission to test learner reminders.', 'gsf-scorm-lite' ) );
	}

	check_admin_referer( 'gsf_scorm_lite_test_reminder' );

	$email = sanitize_email( wp_unslash( $_POST['test_email'] ?? '' ) );
	if ( ! is_email( $email ) ) {
		gsf_scorm_lite_redirect_reminder_settings( 'invalid_email' );
	}

	$user        = wp_get_current_user();
	$enrollments = gsf_scorm_lite_get_reminder_enrollments( $user->ID, false );
	$sent        = gsf_scorm_lite_send_reminder_email( $email, $user->display_name, $enrollments, true );

	gsf_scorm_lite_redirect_reminder_settings( $sent ? 'test_sent' : 'test_failed' );
}
add_action( 'admin_post_gsf_scorm_lite_test_reminder', 'gsf_scorm_lite_handle_reminder_test_email' );

/**
 * Sends an administrator-requested test completion notification.
 */
function gsf_scorm_lite_handle_completion_notification_test() {
	if ( ! gsf_scorm_lite_current_user_can_manage_learn() ) {
		wp_die( esc_html__( 'You do not have permission to test course completion notifications.', 'gsf-scorm-lite' ) );
	}

	check_admin_referer( 'gsf_scorm_lite_test_completion_notification' );

	$course_id = absint( $_POST['test_course_id'] ?? 0 );
	$course    = get_post( $course_id );
	if ( ! $course instanceof WP_Post || 'gsf_course' !== $course->post_type ) {
		gsf_scorm_lite_redirect_reminder_settings( 'invalid_course' );
	}

	$sent = gsf_scorm_lite_send_completion_notification(
		get_current_user_id(),
		$course_id,
		array(
			'completed_at' => current_time( 'mysql' ),
			'score_raw'    => '100',
		),
		true
	);

	gsf_scorm_lite_redirect_reminder_settings( $sent ? 'completion_test_sent' : 'completion_test_failed' );
}
add_action( 'admin_post_gsf_scorm_lite_test_completion_notification', 'gsf_scorm_lite_handle_completion_notification_test' );

/**
 * Renders learner reminder settings and test controls.
 */
function gsf_scorm_lite_render_reminder_settings_page() {
	if ( ! gsf_scorm_lite_current_user_can_manage_learn() ) {
		wp_die( esc_html__( 'You do not have permission to manage learner reminders.', 'gsf-scorm-lite' ) );
	}

	$settings            = gsf_scorm_lite_get_reminder_settings();
	$completion_settings = gsf_scorm_lite_get_completion_notification_settings();
	$result              = sanitize_key( $_GET['reminder_result'] ?? '' );
	$next_run            = wp_next_scheduled( GSF_SCORM_LITE_REMINDER_HOOK );
	$courses             = get_posts(
		array(
			'post_type'      => 'gsf_course',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'orderby'        => 'title',
			'order'          => 'ASC',
		)
	);
	$messages = array(
		'saved'         => array( 'success', __( 'Learn email settings saved.', 'gsf-scorm-lite' ) ),
		'test_sent'     => array( 'success', __( 'Test reminder accepted for delivery. On localhost, open Mailpit to view it.', 'gsf-scorm-lite' ) ),
		'test_failed'   => array( 'error', __( 'WordPress could not send the test reminder. Check the local mail service or production mail configuration.', 'gsf-scorm-lite' ) ),
		'invalid_email' => array( 'error', __( 'Enter a valid test email address.', 'gsf-scorm-lite' ) ),
		'invalid_course' => array( 'error', __( 'Choose a published course for the completion notification test.', 'gsf-scorm-lite' ) ),
		'completion_test_sent' => array( 'success', __( 'Test completion notification accepted for delivery. On localhost, open Mailpit to view it.', 'gsf-scorm-lite' ) ),
		'completion_test_failed' => array( 'error', __( 'WordPress could not send the test completion notification. Check the recipients and mail configuration.', 'gsf-scorm-lite' ) ),
	);
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Learn Email Notifications', 'gsf-scorm-lite' ); ?></h1>
		<?php if ( isset( $messages[ $result ] ) ) : ?>
			<div class="notice notice-<?php echo esc_attr( $messages[ $result ][0] ); ?> is-dismissible"><p><?php echo esc_html( $messages[ $result ][1] ); ?></p></div>
		<?php endif; ?>
		<p><?php esc_html_e( 'Send one consolidated weekly email to learners who have unfinished courses and have been inactive for the configured number of days.', 'gsf-scorm-lite' ); ?></p>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="gsf_scorm_lite_save_reminders" />
			<?php wp_nonce_field( 'gsf_scorm_lite_save_reminders' ); ?>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><?php esc_html_e( 'Weekly reminders', 'gsf-scorm-lite' ); ?></th>
					<td><label><input type="checkbox" name="enabled" value="1" <?php checked( ! empty( $settings['enabled'] ) ); ?> /> <?php esc_html_e( 'Enable automatic weekly reminder emails', 'gsf-scorm-lite' ); ?></label></td>
				</tr>
				<tr>
					<th scope="row"><label for="inactivity_days"><?php esc_html_e( 'Inactive for', 'gsf-scorm-lite' ); ?></label></th>
					<td><input type="number" min="1" max="365" name="inactivity_days" id="inactivity_days" value="<?php echo esc_attr( (int) $settings['inactivity_days'] ); ?>" class="small-text" /> <?php esc_html_e( 'days', 'gsf-scorm-lite' ); ?></td>
				</tr>
				<tr>
					<th scope="row"><label for="subject"><?php esc_html_e( 'Email subject', 'gsf-scorm-lite' ); ?></label></th>
					<td><input type="text" name="subject" id="subject" value="<?php echo esc_attr( $settings['subject'] ); ?>" class="regular-text" /></td>
				</tr>
				<tr><th colspan="2"><h2><?php esc_html_e( 'Administrator completion notifications', 'gsf-scorm-lite' ); ?></h2></th></tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Course completions', 'gsf-scorm-lite' ); ?></th>
					<td><label><input type="checkbox" name="completion_enabled" value="1" <?php checked( ! empty( $completion_settings['enabled'] ) ); ?> /> <?php esc_html_e( 'Email administrators when a learner completes a course', 'gsf-scorm-lite' ); ?></label></td>
				</tr>
				<tr>
					<th scope="row"><label for="completion_recipients"><?php esc_html_e( 'Administrator recipients', 'gsf-scorm-lite' ); ?></label></th>
					<td>
						<textarea name="completion_recipients" id="completion_recipients" class="large-text" rows="3"><?php echo esc_textarea( implode( "\n", $completion_settings['recipients'] ) ); ?></textarea>
						<p class="description"><?php esc_html_e( 'Enter one or more email addresses separated by commas or new lines.', 'gsf-scorm-lite' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="completion_subject"><?php esc_html_e( 'Completion subject', 'gsf-scorm-lite' ); ?></label></th>
					<td>
						<input type="text" name="completion_subject" id="completion_subject" value="<?php echo esc_attr( $completion_settings['subject'] ); ?>" class="regular-text" />
						<p class="description"><?php esc_html_e( 'Available fields: {learner_name}, {course_title}', 'gsf-scorm-lite' ); ?></p>
					</td>
				</tr>
			</table>
			<?php submit_button( __( 'Save Email Settings', 'gsf-scorm-lite' ) ); ?>
		</form>

		<hr />
		<h2><?php esc_html_e( 'Send a test reminder', 'gsf-scorm-lite' ); ?></h2>
		<p><?php esc_html_e( 'The test is sent immediately and does not change any learner reminder history.', 'gsf-scorm-lite' ); ?></p>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="gsf_scorm_lite_test_reminder" />
			<?php wp_nonce_field( 'gsf_scorm_lite_test_reminder' ); ?>
			<label for="test_email" class="screen-reader-text"><?php esc_html_e( 'Test email address', 'gsf-scorm-lite' ); ?></label>
			<input type="email" name="test_email" id="test_email" value="<?php echo esc_attr( wp_get_current_user()->user_email ); ?>" class="regular-text" required />
			<?php submit_button( __( 'Send Test Reminder', 'gsf-scorm-lite' ), 'secondary', 'submit', false ); ?>
		</form>

		<hr />
		<h2><?php esc_html_e( 'Send a test completion notification', 'gsf-scorm-lite' ); ?></h2>
		<p><?php esc_html_e( 'The test goes to the configured administrator recipients and does not change learner progress.', 'gsf-scorm-lite' ); ?></p>
		<?php if ( empty( $courses ) ) : ?>
			<p><?php esc_html_e( 'Publish a course before sending a completion test.', 'gsf-scorm-lite' ); ?></p>
		<?php else : ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="gsf_scorm_lite_test_completion_notification" />
				<?php wp_nonce_field( 'gsf_scorm_lite_test_completion_notification' ); ?>
				<label for="test_course_id" class="screen-reader-text"><?php esc_html_e( 'Test course', 'gsf-scorm-lite' ); ?></label>
				<select name="test_course_id" id="test_course_id" required>
					<?php foreach ( $courses as $course ) : ?>
						<option value="<?php echo esc_attr( $course->ID ); ?>"><?php echo esc_html( get_the_title( $course ) ); ?></option>
					<?php endforeach; ?>
				</select>
				<?php submit_button( __( 'Send Test Completion Email', 'gsf-scorm-lite' ), 'secondary', 'submit', false ); ?>
			</form>
		<?php endif; ?>

		<h2><?php esc_html_e( 'Schedule status', 'gsf-scorm-lite' ); ?></h2>
		<p>
			<?php
			if ( $next_run ) {
				echo esc_html( sprintf( __( 'Next WordPress Cron check: %s', 'gsf-scorm-lite' ), wp_date( 'F j, Y g:i a T', $next_run ) ) );
			} else {
				esc_html_e( 'The weekly WordPress Cron event is not currently scheduled.', 'gsf-scorm-lite' );
			}
			?>
		</p>
		<?php if ( getenv( 'GSF_SCORM_LITE_LOCAL_SMTP_HOST' ) ) : ?>
			<p><strong><?php esc_html_e( 'Local email capture is active:', 'gsf-scorm-lite' ); ?></strong> <a href="http://localhost:8025/" target="_blank" rel="noopener noreferrer">http://localhost:8025/</a></p>
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

	if ( ! gsf_scorm_lite_current_user_can_manage_learn() ) {
		return '<div class="notice notice-error"><p>' . esc_html__( 'You do not have permission to save certificate settings.', 'gsf-scorm-lite' ) . '</p></div>';
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
	if ( ! gsf_scorm_lite_current_user_can_manage_learn() ) {
		wp_die( esc_html__( 'You do not have permission to manage certificate settings.', 'gsf-scorm-lite' ) );
	}

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
