<?php
/**
 * Plugin Name: GSF Resource Recommendation Assistant
 * Description: Adds a self-contained, cited resource recommendation assistant powered entirely by WordPress.
 * Version: 1.0.3
 * Requires at least: 6.2
 * Requires PHP: 7.4
 * Author: GSF Hub
 * Text Domain: gsf-resource-assistant
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once plugin_dir_path( __FILE__ ) . 'includes/class-gsf-resource-recommendation-engine.php';

final class GSF_Resource_Recommendation_Assistant {
	const VERSION       = '1.0.3';
	const SETTINGS_PAGE = 'gsf-resource-assistant';
	const REST_NAMESPACE = 'gsf-resource-assistant/v1';

	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'register_rest_routes' ) );
		add_action( 'admin_menu', array( __CLASS__, 'register_settings_page' ) );
		add_shortcode( 'gsf_resource_assistant', array( __CLASS__, 'render_shortcode' ) );
		add_shortcode( 'gsf_resource_assistant_home', array( __CLASS__, 'render_home_shortcode' ) );
		add_filter( 'get_search_form', array( __CLASS__, 'replace_search_form' ), 20, 2 );
		add_action( 'template_redirect', array( __CLASS__, 'redirect_native_search' ), 5 );
	}

	public static function replace_search_form( $form, $args = array() ) {
		$field_id = wp_unique_id( 'gsf-assistant-search-' );
		$query    = isset( $_GET['q'] ) ? sanitize_text_field( wp_unslash( $_GET['q'] ) ) : get_search_query();

		return sprintf(
			'<form class="search-form-inline gsf-assistant-search-form" role="search" method="get" action="%1$s"><label class="screen-reader-text" for="%2$s">%3$s</label><input id="%2$s" type="search" name="q" value="%4$s" placeholder="%5$s"><button class="button button--primary" type="submit">%6$s</button></form>',
			esc_url( home_url( '/resource-assistant/' ) ),
			esc_attr( $field_id ),
			esc_html__( 'Describe what you need:', 'gsf-resource-assistant' ),
			esc_attr( $query ),
			esc_attr__( 'Ask about a goal, challenge, course, tool, or dataset…', 'gsf-resource-assistant' ),
			esc_html__( 'Ask the Hub', 'gsf-resource-assistant' )
		);
	}

	public static function redirect_native_search() {
		if ( is_admin() || wp_doing_ajax() || ! is_search() ) {
			return;
		}
		$traditional_search = isset( $_GET['traditional_search'] ) ? sanitize_text_field( wp_unslash( $_GET['traditional_search'] ) ) : '';
		if ( '1' === $traditional_search ) {
			return;
		}
		$query = trim( get_search_query( false ) );
		if ( '' === $query ) {
			return;
		}
		wp_safe_redirect(
			add_query_arg( 'q', $query, home_url( '/resource-assistant/' ) ),
			302
		);
		exit;
	}

	public static function register_rest_routes() {
		register_rest_route(
			self::REST_NAMESPACE,
			'/corpus',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'handle_corpus' ),
				'permission_callback' => '__return_true',
			)
		);
		register_rest_route(
			self::REST_NAMESPACE,
			'/recommend',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'handle_recommendation' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	public static function handle_corpus() {
		$supported_types = array(
			'gsf_resource',
			'gsf_course',
			'gsf_case_study',
			'forum',
			'topic',
			'gsf_data_story',
			'gsf_project',
			'gsf_indicator',
			'gsf_activity',
			'gsf_ecoequity',
			'expert_directory',
			'zoom-meetings',
		);
		$post_types = array_values( array_filter( $supported_types, 'post_type_exists' ) );
		if ( empty( $post_types ) ) {
			return rest_ensure_response( array( 'generated_at' => gmdate( 'c' ), 'count' => 0, 'posts' => array() ) );
		}

		$corpus_posts = array();
		foreach ( $post_types as $post_type ) {
			$type_query   = new WP_Query(
				array(
					'post_type'              => $post_type,
					'post_status'            => 'publish',
					'posts_per_page'         => 500,
					'orderby'                => 'modified',
					'order'                  => 'DESC',
					'no_found_rows'          => true,
					'ignore_sticky_posts'    => true,
					'suppress_filters'       => true,
					'update_post_meta_cache' => true,
					'update_post_term_cache' => true,
				)
			);
			$corpus_posts = array_merge( $corpus_posts, $type_query->posts );
		}

		$posts = array();
		foreach ( $corpus_posts as $post ) {
			if ( ! self::is_public_corpus_post( $post ) ) {
				continue;
			}
			$content      = trim( wp_strip_all_tags( strip_shortcodes( $post->post_content ), true ) );
			$excerpt      = trim( wp_strip_all_tags( $post->post_excerpt, true ) );
			$public_meta  = self::public_corpus_meta( $post->ID );
			$meta_summary = self::public_corpus_meta_summary( $public_meta );
			$content      = trim( $content . ' ' . $meta_summary );
			if ( strlen( $excerpt . ' ' . $content ) < 20 ) {
				continue;
			}

			$posts[] = array(
				'post_type'     => $post->post_type,
				'post_status'   => $post->post_status,
				'post_title'    => html_entity_decode( get_the_title( $post ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ),
				'post_excerpt'  => $excerpt,
				'post_content'  => $content,
				'post_modified' => get_post_modified_time( 'Y-m-d H:i:s', true, $post ),
				'url'           => get_permalink( $post ),
				'meta'          => $public_meta,
				'topics'        => self::public_corpus_terms( $post ),
			);
		}
		wp_reset_postdata();

		$posts[] = array(
			'post_type'     => 'gsf_data_centre',
			'post_status'   => 'publish',
			'post_title'    => __( 'Explore the GSF Data Centre', 'gsf-resource-assistant' ),
			'post_excerpt'  => __( 'Explore programme evidence and results across the GSF Hub.', 'gsf-resource-assistant' ),
			'post_content'  => __( 'Browse projects, indicators, activities, Ecoequity scores, organizations, countries, evidence, and programme progress for CBF, NCTFs, and partners.', 'gsf-resource-assistant' ),
			'post_modified' => gmdate( 'Y-m-d H:i:s' ),
			'url'           => home_url( '/data-centre/' ),
			'meta'          => array(),
			'topics'        => array( 'NCTF', 'Programme results', 'Indicators', 'Projects', 'Ecoequity' ),
		);

		$response = rest_ensure_response(
			array(
				'generated_at' => gmdate( 'c' ),
				'count'        => count( $posts ),
				'posts'        => $posts,
			)
		);
		$response->header( 'Cache-Control', 'public, max-age=60' );
		return $response;
	}

	private static function is_public_corpus_post( $post ) {
		if ( ! $post instanceof WP_Post || 'publish' !== $post->post_status || ! empty( $post->post_password ) ) {
			return false;
		}
		if ( preg_match( '/^(?:local\s+test|test(?:\s|$))/i', trim( $post->post_title ) ) ) {
			return false;
		}
		if ( 'gsf_activity' === $post->post_type && 'draft' === strtolower( (string) get_post_meta( $post->ID, 'activity_status', true ) ) ) {
			return false;
		}
		$post_type = get_post_type_object( $post->post_type );
		if ( ! $post_type || ( ! $post_type->public && ! $post_type->publicly_queryable ) ) {
			return false;
		}
		if ( 'forum' === $post->post_type && function_exists( 'bbp_is_forum_public' ) ) {
			return bbp_is_forum_public( $post->ID );
		}
		if ( 'topic' === $post->post_type && function_exists( 'bbp_get_topic_forum_id' ) && function_exists( 'bbp_is_forum_public' ) ) {
			$forum_id = bbp_get_topic_forum_id( $post->ID );
			return $forum_id ? bbp_is_forum_public( $forum_id ) : false;
		}
		return true;
	}

	private static function public_corpus_meta( $post_id ) {
		$allowed = array(
			'resource_type',
			'country_region',
			'country',
			'topic',
			'focus_area',
			'case_study_type',
			'target_users',
			'audience',
			'sectors',
			'sector',
			'application_deadline',
			'deadline',
			'financial_support',
			'contact_information',
			'contact',
			'eligibility_rules',
			'eligibility',
			'external_url',
			'gsf_story_country',
			'gsf_story_url',
			'specialization',
			'expertise',
			'project_type',
			'implementing_party',
			'funding_source',
			'organizations',
			'project_status',
			'percent_complete',
			'milestone_output',
			'indicator_code',
			'pmf_reference',
			'result_level',
			'indicator_text',
			'indicator_type',
			'unit_of_measure',
			'baseline_summary',
			'target_summary',
			'definition_criteria',
			'target_population',
			'responsible_party',
			'activity_type',
			'activity_date',
			'delivery_mode',
			'participant_count',
			'activity_status',
			'notes',
			'organization_name',
			'organization_type',
			'reporting_period',
			'assessment_stage',
			'total_score',
			'score_status',
			'evidence_title',
			'baseline_score',
		);
		$meta = array();
		foreach ( $allowed as $key ) {
			$values = array_map( 'sanitize_text_field', get_post_meta( $post_id, $key, false ) );
			if ( in_array( $key, array( 'country', 'country_region', 'gsf_story_country' ), true ) ) {
				$values = array_map(
					function ( $value ) {
						if ( ctype_digit( (string) $value ) ) {
							$term = get_term( (int) $value );
							if ( $term && ! is_wp_error( $term ) ) {
								return sanitize_text_field( $term->name );
							}
						}
						return $value;
					},
					$values
				);
			}
			$values = array_values( array_filter( $values ) );
			if ( ! empty( $values ) ) {
				$meta[ $key ] = $values;
			}
		}
		return $meta;
	}

	private static function public_corpus_meta_summary( $meta ) {
		$parts = array();
		foreach ( $meta as $key => $values ) {
			$clean_values = array_values(
				array_filter(
					(array) $values,
					function ( $value ) {
						return '' !== trim( (string) $value ) && '0' !== trim( (string) $value );
					}
				)
			);
			if ( empty( $clean_values ) ) {
				continue;
			}
			$parts[] = ucwords( str_replace( '_', ' ', $key ) ) . ': ' . implode( ', ', $clean_values ) . '.';
		}
		return implode( ' ', $parts );
	}

	private static function public_corpus_terms( $post ) {
		$topics = array();
		foreach ( get_object_taxonomies( $post->post_type, 'names' ) as $taxonomy ) {
			if ( in_array( $taxonomy, array( 'language', 'post_translations' ), true ) ) {
				continue;
			}
			$terms = wp_get_post_terms( $post->ID, $taxonomy, array( 'fields' => 'names' ) );
			if ( ! is_wp_error( $terms ) ) {
				$topics = array_merge( $topics, $terms );
			}
		}
		return array_values( array_unique( array_filter( array_map( 'sanitize_text_field', $topics ) ) ) );
	}

	public static function register_settings_page() {
		add_options_page(
			__( 'Resource Assistant', 'gsf-resource-assistant' ),
			__( 'Resource Assistant', 'gsf-resource-assistant' ),
			'manage_options',
			self::SETTINGS_PAGE,
			array( __CLASS__, 'render_settings_page' )
		);
	}

	public static function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$corpus_response = self::handle_corpus();
		$corpus_data     = $corpus_response instanceof WP_REST_Response ? $corpus_response->get_data() : array();
		$library_count   = isset( $corpus_data['count'] ) ? absint( $corpus_data['count'] ) : 0;
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'GSF Resource Recommendation Assistant', 'gsf-resource-assistant' ); ?></h1>
			<div class="notice notice-success inline"><p><?php esc_html_e( 'Local WordPress engine active. No external recommendation service or API credentials are required.', 'gsf-resource-assistant' ); ?></p></div>
			<table class="widefat striped" style="max-width:760px">
				<tbody>
					<tr><th><?php esc_html_e( 'Engine', 'gsf-resource-assistant' ); ?></th><td><?php esc_html_e( 'Self-contained PHP matching and ranking', 'gsf-resource-assistant' ); ?></td></tr>
					<tr><th><?php esc_html_e( 'Public library items', 'gsf-resource-assistant' ); ?></th><td><?php echo esc_html( number_format_i18n( $library_count ) ); ?></td></tr>
					<tr><th><?php esc_html_e( 'Assistant page shortcode', 'gsf-resource-assistant' ); ?></th><td><code>[gsf_resource_assistant]</code></td></tr>
					<tr><th><?php esc_html_e( 'Homepage prompt shortcode', 'gsf-resource-assistant' ); ?></th><td><code>[gsf_resource_assistant_home]</code></td></tr>
					<tr><th><?php esc_html_e( 'Sources', 'gsf-resource-assistant' ); ?></th><td><?php esc_html_e( 'Published Learn, Resources, Data Centre, Case Studies, Forums, Experts, and Events content', 'gsf-resource-assistant' ); ?></td></tr>
				</tbody>
			</table>
		</div>
		<?php
	}

	public static function render_home_shortcode() {
		$asset_url = plugin_dir_url( __FILE__ ) . 'assets/';
		wp_enqueue_style(
			'gsf-resource-assistant',
			$asset_url . 'resource-assistant.css',
			array(),
			self::VERSION
		);

		$field_id   = wp_unique_id( 'gsf-assistant-home-search-' );
		$heading_id = wp_unique_id( 'gsf-assistant-home-title-' );
		$examples   = self::example_questions();

		ob_start();
		?>
		<section class="gsf-resource-assistant-home" aria-labelledby="<?php echo esc_attr( $heading_id ); ?>">
			<div class="gsf-resource-assistant-home__copy">
				<p class="gsf-resource-assistant-home__eyebrow"><?php esc_html_e( 'Resource Assistant', 'gsf-resource-assistant' ); ?></p>
				<h2 id="<?php echo esc_attr( $heading_id ); ?>"><?php esc_html_e( 'What could the GSF Hub help you find?', 'gsf-resource-assistant' ); ?></h2>
				<p><?php esc_html_e( 'Describe your role, goal, or challenge. We will map relevant learning, resources, evidence, people, and discussions.', 'gsf-resource-assistant' ); ?></p>
			</div>
			<form class="gsf-resource-assistant-home__form" role="search" method="get" action="<?php echo esc_url( home_url( '/resource-assistant/' ) ); ?>">
				<label class="gsf-resource-assistant-home__screen-reader" for="<?php echo esc_attr( $field_id ); ?>"><?php esc_html_e( 'Ask the GSF Hub', 'gsf-resource-assistant' ); ?></label>
				<input id="<?php echo esc_attr( $field_id ); ?>" type="search" name="q" placeholder="<?php esc_attr_e( 'Describe your role, goal, or challenge…', 'gsf-resource-assistant' ); ?>" required />
				<button type="submit"><?php esc_html_e( 'Ask the Hub', 'gsf-resource-assistant' ); ?></button>
			</form>
			<div class="gsf-resource-assistant-home__examples" aria-label="<?php esc_attr_e( 'Example questions', 'gsf-resource-assistant' ); ?>">
				<span><?php esc_html_e( 'Try an example:', 'gsf-resource-assistant' ); ?></span>
				<?php foreach ( $examples as $example ) : ?>
					<a href="<?php echo esc_url( add_query_arg( 'q', $example['query'], home_url( '/resource-assistant/' ) ) ); ?>"><?php echo esc_html( $example['label'] ); ?></a>
				<?php endforeach; ?>
			</div>
		</section>
		<?php
		return ob_get_clean();
	}

	private static function example_questions() {
		return array(
			array(
				'label' => __( 'Support my NCTF', 'gsf-resource-assistant' ),
				'query' => __( 'I work for a National Conservation Trust Fund. What courses, templates, experts, peer discussions, and Data Centre evidence can help strengthen our governance, operations, and gender-responsive grantmaking?', 'gsf-resource-assistant' ),
			),
			array(
				'label' => __( 'Plan an inclusive project', 'gsf-resource-assistant' ),
				'query' => __( 'Help me design an inclusive biodiversity project. I need practical guidance, a toolkit, case studies, and experts for gender-responsive planning and implementation.', 'gsf-resource-assistant' ),
			),
			array(
				'label' => __( 'Find training and tools', 'gsf-resource-assistant' ),
				'query' => __( 'My team needs practical training and ready-to-use tools. What Learn courses, templates, webinars, and community discussions are available?', 'gsf-resource-assistant' ),
			),
			array(
				'label' => __( 'Report programme results', 'gsf-resource-assistant' ),
				'query' => __( 'I am preparing a report for partners and funders. Which Data Centre indicators and programme evidence should I use to track gender-responsive biodiversity results?', 'gsf-resource-assistant' ),
			),
		);
	}

	public static function render_shortcode() {
		$asset_url = plugin_dir_url( __FILE__ ) . 'assets/';
		wp_enqueue_style(
			'gsf-resource-assistant',
			$asset_url . 'resource-assistant.css',
			array(),
			self::VERSION
		);
		wp_enqueue_script(
			'gsf-resource-assistant',
			$asset_url . 'resource-assistant.js',
			array(),
			self::VERSION,
			true
		);
		wp_localize_script(
			'gsf-resource-assistant',
			'gsfResourceAssistant',
			array(
				'endpoint'             => esc_url_raw( rest_url( self::REST_NAMESPACE . '/recommend' ) ),
				'traditionalSearchUrl' => esc_url_raw( home_url( '/' ) ),
				'labels'               => array(
					'loading' => __( 'Finding supported matches…', 'gsf-resource-assistant' ),
					'error'   => __( 'The assistant could not complete this request. Please try again later.', 'gsf-resource-assistant' ),
				),
			)
		);

		ob_start();
		?>
		<section class="gsf-resource-assistant" data-gsf-resource-assistant>
			<form class="gsf-resource-assistant__form">
				<div class="gsf-resource-assistant__intro">
					<p class="gsf-resource-assistant__kicker"><?php esc_html_e( 'One question. Many paths.', 'gsf-resource-assistant' ); ?></p>
					<h2><?php esc_html_e( 'What could the GSF Hub help you find?', 'gsf-resource-assistant' ); ?></h2>
					<p><?php esc_html_e( 'Tell us who you are or what you are working on. We will map useful learning, resources, evidence, people, and community discussions across the Hub.', 'gsf-resource-assistant' ); ?></p>
				</div>
				<label class="gsf-resource-assistant__wide">
					<span><?php esc_html_e( 'Ask the Hub', 'gsf-resource-assistant' ); ?></span>
					<textarea name="situation" rows="3" maxlength="4000" required placeholder="<?php esc_attr_e( 'Describe your role, goal, or challenge—for example, training, project design, reporting, or NCTF support.', 'gsf-resource-assistant' ); ?>"></textarea>
				</label>
				<div class="gsf-resource-assistant__suggestions gsf-resource-assistant__wide" aria-label="<?php esc_attr_e( 'Example questions', 'gsf-resource-assistant' ); ?>">
					<span><?php esc_html_e( 'Try an example:', 'gsf-resource-assistant' ); ?></span>
					<button type="button" data-gsf-suggestion="I work for a National Conservation Trust Fund. What courses, templates, experts, peer discussions, and Data Centre evidence can help strengthen our governance, operations, and gender-responsive grantmaking?"><?php esc_html_e( 'Support my NCTF', 'gsf-resource-assistant' ); ?></button>
					<button type="button" data-gsf-suggestion="Help me design an inclusive biodiversity project. I need practical guidance, a toolkit, case studies, and experts for gender-responsive planning and implementation."><?php esc_html_e( 'Plan an inclusive project', 'gsf-resource-assistant' ); ?></button>
					<button type="button" data-gsf-suggestion="My team needs practical training and ready-to-use tools. What Learn courses, templates, webinars, and community discussions are available?"><?php esc_html_e( 'Find training and tools', 'gsf-resource-assistant' ); ?></button>
					<button type="button" data-gsf-suggestion="I am preparing a report for partners and funders. Which Data Centre indicators and programme evidence should I use to track gender-responsive biodiversity results?"><?php esc_html_e( 'Report programme results', 'gsf-resource-assistant' ); ?></button>
				</div>
				<div class="gsf-resource-assistant__actions gsf-resource-assistant__wide">
					<button type="submit"><?php esc_html_e( 'Build my resource map', 'gsf-resource-assistant' ); ?></button>
				</div>
			</form>
			<div class="gsf-resource-assistant__status" role="status" aria-live="polite"></div>
			<div class="gsf-resource-assistant__traditional-search" hidden>
				<span><?php esc_html_e( 'Looking for an exact title or phrase?', 'gsf-resource-assistant' ); ?></span>
				<a href="#"><?php esc_html_e( 'View traditional search results', 'gsf-resource-assistant' ); ?></a>
			</div>
			<div class="gsf-resource-assistant__results" aria-live="polite"></div>
			<noscript><p><?php esc_html_e( 'JavaScript is required to use the resource assistant.', 'gsf-resource-assistant' ); ?></p></noscript>
		</section>
		<?php
		return ob_get_clean();
	}

	public static function handle_recommendation( WP_REST_Request $request ) {
		// This is a public, read-only recommendation endpoint. WordPress visitor
		// nonces are intentionally not required because cached pages can retain an
		// expired nonce. Abuse protection is provided by the request rate limit and
		// strict input length/sanitization below.
		if ( ! self::within_rate_limit() ) {
			return new WP_Error( 'gsf_resource_rate_limit', __( 'Too many requests. Please wait and try again.', 'gsf-resource-assistant' ), array( 'status' => 429 ) );
		}

		$payload   = $request->get_json_params();
		$situation = isset( $payload['situation'] ) ? sanitize_textarea_field( $payload['situation'] ) : '';
		if ( strlen( $situation ) < 3 ) {
			return new WP_Error( 'gsf_resource_missing_situation', __( 'Please describe your situation.', 'gsf-resource-assistant' ), array( 'status' => 400 ) );
		}

		$corpus_response = self::handle_corpus();
		$corpus_data     = $corpus_response instanceof WP_REST_Response ? $corpus_response->get_data() : array();
		$posts           = isset( $corpus_data['posts'] ) && is_array( $corpus_data['posts'] ) ? $corpus_data['posts'] : array();
		$result          = GSF_Resource_Recommendation_Engine::recommend( $posts, substr( $situation, 0, 4000 ), 6 );

		return rest_ensure_response( $result );
	}

	private static function within_rate_limit() {
		$address = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : 'unknown';
		$key     = 'gsf_ra_' . substr( hash_hmac( 'sha256', $address, wp_salt( 'nonce' ) ), 0, 32 );
		$count   = (int) get_transient( $key );
		if ( $count >= 20 ) {
			return false;
		}
		set_transient( $key, $count + 1, MINUTE_IN_SECONDS );
		return true;
	}

}

GSF_Resource_Recommendation_Assistant::init();
