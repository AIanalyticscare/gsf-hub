<?php
/**
 * Plugin Name: GSF Learn Feedback Gate
 * Description: Portable fixes for GSF Learn: require verified course completion before feedback, require feedback before certificates, and backfill existing Learn certificates.
 * Version: 1.1.0
 * Author: GSF Hub
 * Text Domain: gsf-learn-feedback-gate
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'GSF_LEARN_FEEDBACK_GATE_VERSION', '1.1.0' );
define( 'GSF_LEARN_FEEDBACK_GATE_FILE', __FILE__ );
define( 'GSF_LEARN_FEEDBACK_GATE_DIR', plugin_dir_path( __FILE__ ) );
define( 'GSF_LEARN_FEEDBACK_GATE_URL', plugin_dir_url( __FILE__ ) );

/**
 * Returns whether the base GSF SCORM Lite plugin API is available.
 *
 * @return bool
 */
function gsf_learn_feedback_gate_has_scorm_lite() {
	return function_exists( 'gsf_scorm_lite_enrollments_table_name' )
		&& function_exists( 'gsf_scorm_lite_feedback_table_name' )
		&& function_exists( 'gsf_scorm_lite_certificates_table_name' )
		&& function_exists( 'gsf_scorm_lite_table_name' );
}

/**
 * Adds an admin warning if the base Learn plugin is missing.
 *
 * @return void
 */
function gsf_learn_feedback_gate_dependency_notice() {
	if ( gsf_learn_feedback_gate_has_scorm_lite() ) {
		return;
	}

	echo '<div class="notice notice-warning"><p>' . esc_html__( 'GSF Learn Feedback Gate requires the GSF SCORM Lite plugin to be active.', 'gsf-learn-feedback-gate' ) . '</p></div>';
}
add_action( 'admin_notices', 'gsf_learn_feedback_gate_dependency_notice' );

/**
 * Returns the course connected to a SCORM slug.
 *
 * @param string $slug SCORM package slug.
 * @return WP_Post|null
 */
function gsf_learn_feedback_gate_get_course_by_slug( $slug ) {
	if ( function_exists( 'gsf_scorm_lite_get_course_by_scorm_slug' ) ) {
		return gsf_scorm_lite_get_course_by_scorm_slug( $slug );
	}

	$courses = get_posts(
		array(
			'post_type'      => 'gsf_course',
			'post_status'    => 'any',
			'posts_per_page' => 1,
			'meta_key'       => '_gsf_scorm_slug',
			'meta_value'     => sanitize_title( $slug ),
		)
	);

	return ! empty( $courses[0] ) ? $courses[0] : null;
}

/**
 * Returns whether a lesson/completion status is complete.
 *
 * @param string $status Status value.
 * @return bool
 */
function gsf_learn_feedback_gate_is_complete_status( $status ) {
	return in_array( sanitize_key( $status ), array( 'completed', 'passed' ), true );
}

/**
 * Gets a SCORM attempt row.
 *
 * @param int    $user_id User ID.
 * @param string $slug    SCORM slug.
 * @return array<string,mixed>
 */
function gsf_learn_feedback_gate_get_attempt( $user_id, $slug ) {
	if ( function_exists( 'gsf_scorm_lite_get_attempt' ) ) {
		return gsf_scorm_lite_get_attempt( $user_id, $slug );
	}

	global $wpdb;

	$table = gsf_scorm_lite_table_name();
	$row   = $wpdb->get_row(
		$wpdb->prepare(
			"SELECT * FROM {$table} WHERE user_id = %d AND course_slug = %s",
			$user_id,
			sanitize_title( $slug )
		),
		ARRAY_A
	);

	return is_array( $row ) ? $row : array();
}

/**
 * Returns whether a learner has a verified completed/passed SCORM attempt.
 *
 * @param int $user_id   User ID.
 * @param int $course_id Course ID.
 * @return bool
 */
function gsf_learn_feedback_gate_can_submit_feedback( $user_id, $course_id ) {
	if ( ! gsf_learn_feedback_gate_has_scorm_lite() ) {
		return false;
	}

	$slug = (string) get_post_meta( $course_id, '_gsf_scorm_slug', true );

	if ( '' === $slug ) {
		return false;
	}

	$attempt = gsf_learn_feedback_gate_get_attempt( $user_id, $slug );

	return gsf_learn_feedback_gate_is_complete_status( $attempt['lesson_status'] ?? '' )
		|| gsf_learn_feedback_gate_is_complete_status( $attempt['completion_status'] ?? '' );
}

/**
 * Returns whether a learner has submitted feedback for a course.
 *
 * @param int $user_id   User ID.
 * @param int $course_id Course ID.
 * @return bool
 */
function gsf_learn_feedback_gate_has_feedback( $user_id, $course_id ) {
	if ( function_exists( 'gsf_scorm_lite_has_feedback' ) ) {
		return gsf_scorm_lite_has_feedback( $user_id, $course_id );
	}

	global $wpdb;

	$table = gsf_scorm_lite_feedback_table_name();

	return (bool) $wpdb->get_var(
		$wpdb->prepare(
			"SELECT id FROM {$table} WHERE user_id = %d AND course_id = %d",
			$user_id,
			$course_id
		)
	);
}

/**
 * Gets a certificate row.
 *
 * @param int $user_id   User ID.
 * @param int $course_id Course ID.
 * @return array<string,mixed>
 */
function gsf_learn_feedback_gate_get_certificate( $user_id, $course_id ) {
	if ( function_exists( 'gsf_scorm_lite_get_certificate' ) ) {
		return gsf_scorm_lite_get_certificate( $user_id, $course_id );
	}

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
 * Issues a certificate using SCORM Lite when available, with a fallback insert.
 *
 * @param int $user_id   User ID.
 * @param int $course_id Course ID.
 * @return array<string,mixed>
 */
function gsf_learn_feedback_gate_issue_certificate( $user_id, $course_id ) {
	if ( '1' !== get_post_meta( $course_id, '_gsf_certificate_enabled', true ) ) {
		return array();
	}

	if ( function_exists( 'gsf_scorm_lite_maybe_issue_certificate' ) ) {
		return gsf_scorm_lite_maybe_issue_certificate( $user_id, $course_id );
	}

	global $wpdb;

	$existing = gsf_learn_feedback_gate_get_certificate( $user_id, $course_id );

	if ( ! empty( $existing ) ) {
		return $existing;
	}

	$table = gsf_scorm_lite_certificates_table_name();
	$code  = strtoupper( wp_generate_password( 12, false, false ) );

	$wpdb->insert(
		$table,
		array(
			'user_id'          => $user_id,
			'course_id'        => $course_id,
			'certificate_code' => $code,
			'issued_at'        => current_time( 'mysql' ),
		)
	);

	return gsf_learn_feedback_gate_get_certificate( $user_id, $course_id );
}

/**
 * Enables certificates for Learn courses by default.
 *
 * @return void
 */
function gsf_learn_feedback_gate_enable_course_certificates() {
	if ( ! post_type_exists( 'gsf_course' ) ) {
		return;
	}

	$course_ids = get_posts(
		array(
			'post_type'      => 'gsf_course',
			'post_status'    => 'any',
			'posts_per_page' => -1,
			'fields'         => 'ids',
		)
	);

	foreach ( $course_ids as $course_id ) {
		update_post_meta( (int) $course_id, '_gsf_certificate_enabled', '1' );
	}
}

/**
 * Deletes certificates that do not yet have matching feedback.
 *
 * @return void
 */
function gsf_learn_feedback_gate_remove_ungated_certificates() {
	if ( ! gsf_learn_feedback_gate_has_scorm_lite() ) {
		return;
	}

	global $wpdb;

	$certificates = gsf_scorm_lite_certificates_table_name();
	$feedback     = gsf_scorm_lite_feedback_table_name();

	$wpdb->query(
		"DELETE c FROM {$certificates} c
		LEFT JOIN {$feedback} f ON f.user_id = c.user_id AND f.course_id = c.course_id
		WHERE f.id IS NULL"
	);
}

/**
 * Issues missing certificates for already submitted feedback.
 *
 * @return void
 */
function gsf_learn_feedback_gate_issue_missing_feedback_certificates() {
	if ( ! gsf_learn_feedback_gate_has_scorm_lite() ) {
		return;
	}

	global $wpdb;

	$rows = $wpdb->get_results(
		"SELECT user_id, course_id FROM " . gsf_scorm_lite_feedback_table_name(),
		ARRAY_A
	);

	foreach ( $rows as $row ) {
		$user_id   = (int) $row['user_id'];
		$course_id = (int) $row['course_id'];

		if ( empty( gsf_learn_feedback_gate_get_certificate( $user_id, $course_id ) ) ) {
			gsf_learn_feedback_gate_issue_certificate( $user_id, $course_id );
		}
	}
}

/**
 * Runs backfill work.
 *
 * @return void
 */
function gsf_learn_feedback_gate_backfill() {
	gsf_learn_feedback_gate_enable_course_certificates();
	gsf_learn_feedback_gate_remove_ungated_certificates();
	gsf_learn_feedback_gate_issue_missing_feedback_certificates();
}
register_activation_hook( __FILE__, 'gsf_learn_feedback_gate_backfill' );

/**
 * Runs the backfill once when dependencies become available after activation.
 *
 * @return void
 */
function gsf_learn_feedback_gate_maybe_backfill() {
	if ( ! gsf_learn_feedback_gate_has_scorm_lite() || get_option( 'gsf_learn_feedback_gate_backfilled' ) === GSF_LEARN_FEEDBACK_GATE_VERSION ) {
		return;
	}

	gsf_learn_feedback_gate_backfill();
	update_option( 'gsf_learn_feedback_gate_backfilled', GSF_LEARN_FEEDBACK_GATE_VERSION );
}
add_action( 'admin_init', 'gsf_learn_feedback_gate_maybe_backfill' );

/**
 * Blocks feedback POST requests unless completion is verified server-side.
 *
 * @return void
 */
function gsf_learn_feedback_gate_validate_feedback_submission() {
	if ( ! is_user_logged_in() ) {
		wp_die( esc_html__( 'Login required.', 'gsf-learn-feedback-gate' ) );
	}

	$course_id = absint( $_POST['course_id'] ?? 0 );

	if ( ! $course_id ) {
		wp_die( esc_html__( 'Invalid feedback request.', 'gsf-learn-feedback-gate' ) );
	}

	if ( ! gsf_learn_feedback_gate_can_submit_feedback( get_current_user_id(), $course_id ) ) {
		wp_die( esc_html__( 'Complete the course before submitting feedback.', 'gsf-learn-feedback-gate' ) );
	}

	update_post_meta( $course_id, '_gsf_certificate_enabled', '1' );
}
add_action( 'admin_post_gsf_lms_feedback', 'gsf_learn_feedback_gate_validate_feedback_submission', 1 );

/**
 * Cleans up premature certificates and loose completion states after SCORM REST saves.
 *
 * @param mixed           $response REST response.
 * @param WP_REST_Server  $handler  REST server.
 * @param WP_REST_Request $request  REST request.
 * @return mixed
 */
function gsf_learn_feedback_gate_after_scorm_save( $response, $handler, $request ) {
	if ( ! gsf_learn_feedback_gate_has_scorm_lite() || 'POST' !== $request->get_method() ) {
		return $response;
	}

	$route = (string) $request->get_route();

	if ( ! preg_match( '#^/gsf-scorm-lite/v1/attempt/([a-z0-9-]+)$#', $route, $matches ) ) {
		return $response;
	}

	$user_id = get_current_user_id();
	$slug    = sanitize_title( $matches[1] );
	$course  = gsf_learn_feedback_gate_get_course_by_slug( $slug );

	if ( ! $user_id || ! $course ) {
		return $response;
	}

	$attempt = gsf_learn_feedback_gate_get_attempt( $user_id, $slug );

	if ( ! gsf_learn_feedback_gate_is_complete_status( $attempt['lesson_status'] ?? '' ) && ! gsf_learn_feedback_gate_is_complete_status( $attempt['completion_status'] ?? '' ) ) {
		global $wpdb;

		$wpdb->update(
			gsf_scorm_lite_enrollments_table_name(),
			array(
				'status'       => 'in_progress',
				'completed_at' => null,
			),
			array(
				'user_id'   => $user_id,
				'course_id' => $course->ID,
			)
		);
	}

	if ( ! gsf_learn_feedback_gate_has_feedback( $user_id, $course->ID ) ) {
		global $wpdb;

		$wpdb->delete(
			gsf_scorm_lite_certificates_table_name(),
			array(
				'user_id'   => $user_id,
				'course_id' => $course->ID,
			)
		);
	}

	return $response;
}
add_filter( 'rest_request_after_callbacks', 'gsf_learn_feedback_gate_after_scorm_save', 10, 3 );

/**
 * Returns current feedback/certificate access status for a course.
 *
 * @param WP_REST_Request $request REST request.
 * @return WP_REST_Response
 */
function gsf_learn_feedback_gate_rest_status( WP_REST_Request $request ) {
	$user_id   = get_current_user_id();
	$course_id = absint( $request->get_param( 'course_id' ) );

	if ( ! $user_id ) {
		return new WP_REST_Response( array( 'message' => __( 'Login required.', 'gsf-learn-feedback-gate' ) ), 401 );
	}

	if ( ! $course_id || 'gsf_course' !== get_post_type( $course_id ) ) {
		return new WP_REST_Response( array( 'message' => __( 'Invalid course.', 'gsf-learn-feedback-gate' ) ), 400 );
	}

	$can_submit  = gsf_learn_feedback_gate_can_submit_feedback( $user_id, $course_id );
	$has_feedback = gsf_learn_feedback_gate_has_feedback( $user_id, $course_id );
	$certificate = gsf_learn_feedback_gate_get_certificate( $user_id, $course_id );

	if ( $has_feedback && empty( $certificate ) ) {
		update_post_meta( $course_id, '_gsf_certificate_enabled', '1' );
		$certificate = gsf_learn_feedback_gate_issue_certificate( $user_id, $course_id );
	}

	return new WP_REST_Response(
		array(
			'ok'              => true,
			'courseId'        => $course_id,
			'canSubmit'       => $can_submit,
			'hasFeedback'     => $has_feedback,
			'hasCertificate'  => ! empty( $certificate ),
			'certificateCode' => ! empty( $certificate['certificate_code'] ) ? (string) $certificate['certificate_code'] : '',
			'messages'        => array(
				'ready'      => __( 'Your course is complete. Submit the feedback survey to unlock your certificate.', 'gsf-learn-feedback-gate' ),
				'incomplete' => __( 'Complete the course before submitting feedback.', 'gsf-learn-feedback-gate' ),
				'done'       => __( 'Feedback survey completed. Your course certificate is now available.', 'gsf-learn-feedback-gate' ),
			),
		)
	);
}

/**
 * Registers status route used by the feedback button.
 *
 * @return void
 */
function gsf_learn_feedback_gate_register_routes() {
	register_rest_route(
		'gsf-learn-feedback-gate/v1',
		'/status/(?P<course_id>\d+)',
		array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => 'gsf_learn_feedback_gate_rest_status',
			'permission_callback' => 'is_user_logged_in',
		)
	);
}
add_action( 'rest_api_init', 'gsf_learn_feedback_gate_register_routes' );

/**
 * Appends a certificate after feedback if the base plugin did not render one.
 *
 * @param string $content Post content.
 * @return string
 */
function gsf_learn_feedback_gate_append_certificate( $content ) {
	if ( ! is_singular( 'gsf_course' ) || ! in_the_loop() || ! is_main_query() || ! is_user_logged_in() ) {
		return $content;
	}

	if ( false !== strpos( $content, 'gsf-lms-certificate' ) ) {
		return $content;
	}

	$user_id   = get_current_user_id();
	$course_id = get_the_ID();

	if ( ! gsf_learn_feedback_gate_has_feedback( $user_id, $course_id ) ) {
		return $content;
	}

	update_post_meta( $course_id, '_gsf_certificate_enabled', '1' );

	$certificate = gsf_learn_feedback_gate_get_certificate( $user_id, $course_id );

	if ( empty( $certificate ) ) {
		$certificate = gsf_learn_feedback_gate_issue_certificate( $user_id, $course_id );
	}

	if ( empty( $certificate ) || ! function_exists( 'gsf_scorm_lite_render_certificate_card' ) ) {
		return $content;
	}

	$content .= '<div id="gsf-lms-certificate">';
	$content .= gsf_scorm_lite_render_certificate_card( $user_id, $course_id, $certificate );
	$content .= '</div>';

	return $content;
}
add_filter( 'the_content', 'gsf_learn_feedback_gate_append_certificate', 999 );

/**
 * Enqueues frontend gate script and styles.
 *
 * @return void
 */
function gsf_learn_feedback_gate_enqueue_assets() {
	wp_enqueue_script(
		'gsf-learn-feedback-gate',
		GSF_LEARN_FEEDBACK_GATE_URL . 'assets/feedback-gate.js',
		array(),
		GSF_LEARN_FEEDBACK_GATE_VERSION,
		true
	);

	wp_localize_script(
		'gsf-learn-feedback-gate',
		'gsfLearnFeedbackGate',
		array(
			'restBase' => esc_url_raw( rest_url( 'gsf-learn-feedback-gate/v1/status/' ) ),
			'nonce'    => wp_create_nonce( 'wp_rest' ),
		)
	);

	wp_add_inline_style(
		'gsf-scorm-lite',
		'.gsf-lms-feedback__toggle{opacity:1;cursor:pointer}.gsf-lms-feedback__toggle:disabled{opacity:.7;cursor:progress}.gsf-lms-feedback__notice{font-weight:700}.gsf-lms-feedback__form[hidden]{display:none!important}'
	);
}
add_action( 'wp_enqueue_scripts', 'gsf_learn_feedback_gate_enqueue_assets', 30 );
