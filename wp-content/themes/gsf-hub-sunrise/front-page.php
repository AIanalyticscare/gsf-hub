<?php
/**
 * Front page template.
 *
 * @package GSF_Hub
 */

get_header();

$brand_icon_uri = gsf_hub_sunrise_asset_uri( 'assets/images/icon-logo.png' );
$sunrise_hero_card_image_uri = gsf_hub_sunrise_asset_uri( 'assets/images/hero-card-coastal.png' );

$home_page_id = get_queried_object_id();

if ( ! $home_page_id ) {
	$home_page_id = (int) get_option( 'page_on_front' );
}

$content      = gsf_hub_get_homepage_content( $home_page_id );

$quick_links = array(
	array(
		'icon'  => 'book',
		'title' => $content['quick_link_1_title'],
		'text'  => $content['quick_link_1_text'],
		'url'   => $content['quick_link_1_url'],
	),
	array(
		'icon'  => 'search',
		'title' => $content['quick_link_2_title'],
		'text'  => $content['quick_link_2_text'],
		'url'   => $content['quick_link_2_url'],
	),
	array(
		'icon'  => 'chart',
		'title' => $content['quick_link_3_title'],
		'text'  => $content['quick_link_3_text'],
		'url'   => $content['quick_link_3_url'],
	),
	array(
		'icon'  => 'users',
		'title' => $content['quick_link_4_title'],
		'text'  => $content['quick_link_4_text'],
		'url'   => $content['quick_link_4_url'],
	),
);

$stories = array(
	$content['case_story_1'],
	$content['case_story_2'],
	$content['case_story_3'],
);

$homepage_case_studies = new WP_Query(
	array(
		'post_type'           => 'gsf_case_study',
		'post_status'         => 'publish',
		'posts_per_page'      => 3,
		'ignore_sticky_posts' => true,
		'meta_query'          => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
			array(
				'key'     => 'featured_case_study',
				'value'   => '1',
				'compare' => '=',
			),
		),
		'orderby'             => array(
			'meta_value' => 'DESC',
			'date'       => 'DESC',
		),
		'meta_key'            => 'implementation_date', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
	)
);

if ( ! $homepage_case_studies->have_posts() ) {
	$homepage_case_studies = new WP_Query(
		array(
			'post_type'           => 'gsf_case_study',
			'post_status'         => 'publish',
			'posts_per_page'      => 3,
			'ignore_sticky_posts' => true,
			'orderby'             => array(
				'meta_value' => 'DESC',
				'date'       => 'DESC',
			),
			'meta_key'            => 'implementation_date', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
		)
	);
}

$metrics = array(
	array(
		'label' => $content['metric_1_label'],
		'value' => $content['metric_1_value'],
	),
	array(
		'label' => $content['metric_2_label'],
		'value' => $content['metric_2_value'],
	),
	array(
		'label' => $content['metric_3_label'],
		'value' => $content['metric_3_value'],
	),
	array(
		'label' => $content['metric_4_label'],
		'value' => $content['metric_4_value'],
	),
);

$data_metrics = array();

if ( class_exists( 'GSF_Data_Centre' ) && method_exists( 'GSF_Data_Centre', 'get_public_dashboard_metrics' ) ) {
	$data_metrics = GSF_Data_Centre::get_public_dashboard_metrics();
}

if ( empty( $data_metrics ) ) {
	$project_progress_metrics = gsf_hub_get_home_project_progress_metrics();
	$fallback_metrics         = ! empty( $project_progress_metrics ) ? $project_progress_metrics : $metrics;

	foreach ( $fallback_metrics as $index => $metric ) {
		$value = isset( $metric['value'] ) ? (string) $metric['value'] : '';

		$data_metrics[] = array(
			'key'   => 'metric-' . ( $index + 1 ),
			'title' => isset( $metric['label'] ) ? (string) $metric['label'] : '',
			'value' => isset( $metric['total'] ) ? $value . '%' : $value,
			'label' => isset( $metric['total'] )
				? sprintf(
					/* translators: %d: number of project records. */
					_n( '%d project record', '%d project records', (int) $metric['total'], 'gsf-hub' ),
					(int) $metric['total']
				)
				: __( 'tracked homepage progress', 'gsf-hub' ),
		);
	}
}

$resources = array(
	array(
		'title' => $content['resource_1_title'],
		'meta'  => $content['resource_1_meta'],
		'url'   => $content['resource_1_url'],
	),
	array(
		'title' => $content['resource_2_title'],
		'meta'  => $content['resource_2_meta'],
		'url'   => $content['resource_2_url'],
	),
	array(
		'title' => $content['resource_3_title'],
		'meta'  => $content['resource_3_meta'],
		'url'   => $content['resource_3_url'],
	),
);

$homepage_resources = new WP_Query(
	array(
		'post_type'           => 'gsf_resource',
		'post_status'         => 'publish',
		'posts_per_page'      => 2,
		'ignore_sticky_posts' => true,
		'orderby'             => array(
			'meta_value' => 'DESC',
			'date'       => 'DESC',
		),
		'meta_key'            => 'publication_year', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
	)
);

$homepage_course = new WP_Query(
	array(
		'post_type'           => 'gsf_course',
		'post_status'         => 'publish',
		'posts_per_page'      => 1,
		'ignore_sticky_posts' => true,
		'orderby'             => 'rand',
	)
);

$upcoming_zoom_events = new WP_Query(
	array(
		'post_type'           => 'zoom-meetings',
		'post_status'         => 'publish',
		'posts_per_page'      => 3,
		'ignore_sticky_posts' => true,
		'meta_key'            => '_meeting_field_start_date_utc', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
		'orderby'             => 'meta_value',
		'order'               => 'ASC',
		'meta_query'          => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
			array(
				'key'     => '_meeting_field_start_date_utc',
				'value'   => gmdate( 'Y-m-d H:i:s' ),
				'compare' => '>=',
				'type'    => 'DATETIME',
			),
		),
	)
);

$homepage_zoom_events = $upcoming_zoom_events;
$event_card_heading   = __( 'Upcoming events', 'gsf-hub' );
$event_card_eyebrow   = $content['event_eyebrow'];
$event_button_label   = $content['event_button_label'];

if ( ! $homepage_zoom_events->have_posts() ) {
	$homepage_zoom_events = new WP_Query(
		array(
			'post_type'           => 'zoom-meetings',
			'post_status'         => 'publish',
			'posts_per_page'      => 3,
			'ignore_sticky_posts' => true,
			'orderby'             => 'date',
			'order'               => 'DESC',
		)
	);
	$event_card_heading = __( 'Latest events', 'gsf-hub' );
	$event_card_eyebrow = __( 'Events and Webinars', 'gsf-hub' );
	$event_button_label = __( 'View event', 'gsf-hub' );
}

$upcoming_zoom_events_url = '';

if ( $homepage_zoom_events->have_posts() ) {
	$upcoming_zoom_events_url = get_permalink( $homepage_zoom_events->posts[0] );
}

$hero_card_image = (string) $content['hero_card_image'];

if ( '' === $hero_card_image || false !== strpos( $hero_card_image, 'hero-reef.jpg' ) ) {
	$hero_card_image = $sunrise_hero_card_image_uri;
}
?>

<main id="primary" class="site-main">
	<section class="hero" style="background-image: radial-gradient(circle at 16% 20%, rgba(242, 180, 61, 0.28), transparent 0 16rem), radial-gradient(circle at 84% 18%, rgba(255, 122, 89, 0.18), transparent 0 16rem), radial-gradient(circle at 70% 82%, rgba(73, 178, 107, 0.16), transparent 0 15rem), linear-gradient(132deg, rgba(13, 91, 99, 0.92) 0%, rgba(28, 166, 166, 0.78) 36%, rgba(0, 94, 122, 0.88) 68%, rgba(255, 122, 89, 0.64) 100%), url('<?php echo esc_url( $content['hero_background_image'] ); ?>');">
		<div class="site-shell hero__grid">
			<div class="hero__content">
				<div class="hero__eyebrow">
					<span class="hero__eyebrow-icon"><?php echo gsf_hub_get_icon( 'award' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
					<span><?php echo esc_html( $content['hero_eyebrow'] ); ?></span>
				</div>
				<div class="sunrise-brand-note">
					<img src="<?php echo esc_url( $brand_icon_uri ); ?>" alt="">
					<span><?php echo esc_html( gsf_hub_ui_text( 'brand_note', __( 'Inclusive. Resilient. Thriving.', 'gsf-hub' ) ) ); ?></span>
				</div>
				<h1><?php gsf_hub_homepage_inline( $content['hero_title'] ); ?></h1>
				<p><?php gsf_hub_homepage_inline( $content['hero_text'] ); ?></p>
				<div class="hero__actions">
					<a class="button button--light" href="<?php echo esc_url( $content['hero_primary_url'] ); ?>">
						<span><?php echo esc_html( $content['hero_primary_label'] ); ?></span>
						<span class="button__icon"><?php echo gsf_hub_get_icon( 'chevron-right' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
					</a>
					<a class="button button--outline-light" href="<?php echo esc_url( $content['hero_secondary_url'] ); ?>">
						<?php echo esc_html( $content['hero_secondary_label'] ); ?>
					</a>
				</div>
			</div>

			<div class="hero-card">
				<div class="hero-card__visual" style="background-image: linear-gradient(180deg, rgba(3, 44, 58, 0.08) 0%, rgba(5, 52, 65, 0.34) 48%, rgba(8, 37, 50, 0.74) 100%), url('<?php echo esc_url( $hero_card_image ); ?>');">
					<p class="hero-card__label"><?php echo esc_html( $content['hero_visual_label'] ); ?></p>
					<p class="hero-card__caption"><?php gsf_hub_homepage_inline( $content['hero_visual_caption'] ); ?></p>
				</div>
				<div class="hero-card__stats">
					<div class="stat-card stat-card--teal">
						<strong><?php echo esc_html( $content['hero_stat_1_value'] ); ?></strong>
						<span><?php echo esc_html( $content['hero_stat_1_label'] ); ?></span>
					</div>
					<div class="stat-card stat-card--blue">
						<strong><?php echo esc_html( $content['hero_stat_2_value'] ); ?></strong>
						<span><?php echo esc_html( $content['hero_stat_2_label'] ); ?></span>
					</div>
					<div class="stat-card stat-card--amber">
						<strong><?php echo esc_html( $content['hero_stat_3_value'] ); ?></strong>
						<span><?php echo esc_html( $content['hero_stat_3_label'] ); ?></span>
					</div>
				</div>
			</div>
		</div>
	</section>

	<div class="site-shell">
		<section class="search-panel-wrapper homepage-assistant" aria-labelledby="homepage-assistant-title">
			<div class="homepage-assistant__copy">
				<p class="homepage-assistant__eyebrow"><?php esc_html_e( 'Resource Assistant', 'gsf-hub' ); ?></p>
				<h2 id="homepage-assistant-title"><?php esc_html_e( 'What could the GSF Hub help you find?', 'gsf-hub' ); ?></h2>
				<p><?php esc_html_e( 'Describe your role, goal, or challenge. We will map relevant learning, resources, evidence, people, and discussions.', 'gsf-hub' ); ?></p>
			</div>

			<form class="search-panel search-panel--assistant" role="search" method="get" action="<?php echo esc_url( home_url( '/resource-assistant/' ) ); ?>">
				<label class="screen-reader-text" for="gsf-assistant-search"><?php esc_html_e( 'Ask the GSF Hub', 'gsf-hub' ); ?></label>
				<div class="search-panel__field">
					<span class="search-panel__icon"><?php echo gsf_hub_get_icon( 'search' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
					<input id="gsf-assistant-search" type="search" name="q" placeholder="<?php esc_attr_e( 'Describe your role, goal, or challenge…', 'gsf-hub' ); ?>" required>
				</div>
				<button class="button button--primary" type="submit"><?php esc_html_e( 'Ask the Hub', 'gsf-hub' ); ?></button>
			</form>

			<div class="homepage-assistant__examples" aria-label="<?php esc_attr_e( 'Example questions', 'gsf-hub' ); ?>">
				<span><?php esc_html_e( 'Try an example:', 'gsf-hub' ); ?></span>
				<a href="<?php echo esc_url( add_query_arg( 'q', 'I work for a National Conservation Trust Fund. What courses, templates, experts, peer discussions, and Data Centre evidence can help strengthen our governance, operations, and gender-responsive grantmaking?', home_url( '/resource-assistant/' ) ) ); ?>"><?php esc_html_e( 'Support my NCTF', 'gsf-hub' ); ?></a>
				<a href="<?php echo esc_url( add_query_arg( 'q', 'Help me design an inclusive biodiversity project. I need practical guidance, a toolkit, case studies, and experts for gender-responsive planning and implementation.', home_url( '/resource-assistant/' ) ) ); ?>"><?php esc_html_e( 'Plan an inclusive project', 'gsf-hub' ); ?></a>
				<a href="<?php echo esc_url( add_query_arg( 'q', 'My team needs practical training and ready-to-use tools. What Learn courses, templates, webinars, and community discussions are available?', home_url( '/resource-assistant/' ) ) ); ?>"><?php esc_html_e( 'Find training and tools', 'gsf-hub' ); ?></a>
				<a href="<?php echo esc_url( add_query_arg( 'q', 'I am preparing a report for partners and funders. Which Data Centre indicators and programme evidence should I use to track gender-responsive biodiversity results?', home_url( '/resource-assistant/' ) ) ); ?>"><?php esc_html_e( 'Report programme results', 'gsf-hub' ); ?></a>
			</div>
		</section>

		<section class="section-block">
			<div class="section-heading">
				<div>
					<p class="section-heading__eyebrow"><?php echo esc_html( $content['explore_eyebrow'] ); ?></p>
					<h2><?php gsf_hub_homepage_inline( $content['explore_title'] ); ?></h2>
				</div>
				<a class="button button--ghost section-heading__action" href="<?php echo esc_url( $content['explore_action_url'] ); ?>"><?php echo esc_html( $content['explore_action_label'] ); ?></a>
			</div>

			<div class="quick-links-grid">
				<?php foreach ( $quick_links as $item ) : ?>
					<a class="quick-card" href="<?php echo esc_url( $item['url'] ); ?>">
						<span class="quick-card__icon"><?php echo gsf_hub_get_icon( $item['icon'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
						<h3><?php echo esc_html( $item['title'] ); ?></h3>
						<p><?php gsf_hub_homepage_inline( $item['text'] ); ?></p>
					</a>
				<?php endforeach; ?>
			</div>
		</section>

		<section class="story-grid section-block">
			<article class="story-card">
				<div class="story-card__media" style="background-image: linear-gradient(180deg, rgba(10, 55, 66, 0.14) 0%, rgba(0, 94, 122, 0.58) 52%, rgba(255, 122, 89, 0.42) 100%), url('<?php echo esc_url( $content['case_image'] ); ?>');">
					<p class="story-card__label"><?php echo esc_html( $content['case_label'] ); ?></p>
				</div>
				<div class="story-card__content">
					<p class="section-heading__eyebrow"><?php echo esc_html( $content['case_eyebrow'] ); ?></p>
						<h2><?php gsf_hub_homepage_inline( $content['case_title'] ); ?></h2>
						<p><?php gsf_hub_homepage_inline( $content['case_text'] ); ?></p>
						<ul class="story-list">
							<?php if ( $homepage_case_studies->have_posts() ) : ?>
								<?php
								while ( $homepage_case_studies->have_posts() ) :
									$homepage_case_studies->the_post();
									?>
									<li><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></li>
								<?php endwhile; ?>
								<?php wp_reset_postdata(); ?>
							<?php else : ?>
								<?php foreach ( $stories as $story ) : ?>
									<li><?php echo esc_html( $story ); ?></li>
								<?php endforeach; ?>
							<?php endif; ?>
						</ul>
						<a class="story-card__more" href="<?php echo esc_url( gsf_hub_get_page_url( 'case-studies' ) ); ?>"><?php esc_html_e( 'See more case studies', 'gsf-hub' ); ?></a>
					</div>
				</article>

			<aside class="data-card">
				<p class="section-heading__eyebrow section-heading__eyebrow--light"><?php echo esc_html( $content['data_eyebrow'] ); ?></p>
				<h2><?php gsf_hub_homepage_inline( $content['data_title'] ); ?></h2>
				<div class="data-metric-grid">
					<?php foreach ( $data_metrics as $metric ) : ?>
						<div class="data-metric-card data-metric-card--<?php echo esc_attr( sanitize_html_class( $metric['key'] ?? 'metric' ) ); ?>">
							<div class="data-metric-card__body">
								<span><?php echo esc_html( $metric['title'] ?? '' ); ?></span>
								<strong><?php echo esc_html( $metric['value'] ?? '' ); ?></strong>
								<small><?php echo esc_html( $metric['label'] ?? '' ); ?></small>
							</div>
						</div>
					<?php endforeach; ?>
				</div>
				<a class="button button--light button--data" href="<?php echo esc_url( $content['data_button_url'] ); ?>"><?php echo esc_html( $content['data_button_label'] ); ?></a>
			</aside>
		</section>

		<section class="resource-layout section-block">
			<article class="resource-card" style="background-image: linear-gradient(180deg, rgba(255, 255, 255, 0.9) 0%, rgba(255, 247, 238, 0.96) 100%), url('<?php echo esc_url( $content['resource_background'] ); ?>');">
					<div class="resource-card__header">
						<div>
							<p class="section-heading__eyebrow"><?php echo esc_html( $content['resource_eyebrow'] ); ?></p>
							<h2><?php gsf_hub_homepage_inline( $content['resource_title'] ); ?></h2>
						</div>
					</div>

					<div class="resource-grid">
						<?php if ( $homepage_resources->have_posts() ) : ?>
							<?php
							while ( $homepage_resources->have_posts() ) :
								$homepage_resources->the_post();

								$resource_type = get_post_meta( get_the_ID(), 'resource_type', true );
								$publication_date = get_post_meta( get_the_ID(), 'publication_year', true );
								$publication_label = '';

								if ( $publication_date ) {
									$publication_timestamp = strtotime( $publication_date );
									$publication_label     = $publication_timestamp ? wp_date( 'M j, Y', $publication_timestamp ) : $publication_date;
								}
								?>
								<div class="mini-resource-card">
									<p class="mini-resource-card__eyebrow"><?php echo esc_html( $resource_type ?: __( 'Resource', 'gsf-hub' ) ); ?></p>
									<h3><?php the_title(); ?></h3>
									<p>
										<?php
										if ( has_excerpt() ) {
											echo esc_html( get_the_excerpt() );
										} else {
											echo esc_html( wp_trim_words( wp_strip_all_tags( get_the_content() ), 18 ) );
										}
										?>
									</p>
									<?php if ( $publication_label ) : ?>
										<span class="mini-resource-card__meta"><?php echo esc_html( $publication_label ); ?></span>
									<?php endif; ?>
									<a class="button button--ghost button--full" href="<?php the_permalink(); ?>"><?php esc_html_e( 'View resource', 'gsf-hub' ); ?></a>
								</div>
							<?php endwhile; ?>
							<?php wp_reset_postdata(); ?>
						<?php else : ?>
							<?php foreach ( array_slice( $resources, 0, 2 ) as $resource ) : ?>
								<div class="mini-resource-card">
									<h3><?php echo esc_html( $resource['title'] ); ?></h3>
									<p><?php echo esc_html( $resource['meta'] ); ?></p>
									<a class="button button--ghost button--full" href="<?php echo esc_url( $resource['url'] ); ?>"><?php echo esc_html( $content['resource_button_label'] ); ?></a>
								</div>
							<?php endforeach; ?>
						<?php endif; ?>

						<?php if ( $homepage_course->have_posts() ) : ?>
							<?php
							while ( $homepage_course->have_posts() ) :
								$homepage_course->the_post();

								$course_duration = get_post_meta( get_the_ID(), '_gsf_course_duration', true );
								?>
								<div class="mini-resource-card mini-resource-card--course">
									<p class="mini-resource-card__eyebrow"><?php esc_html_e( 'Featured course', 'gsf-hub' ); ?></p>
									<h3><?php the_title(); ?></h3>
									<p>
										<?php
										if ( has_excerpt() ) {
											echo esc_html( get_the_excerpt() );
										} else {
											echo esc_html( wp_trim_words( wp_strip_all_tags( get_the_content() ), 18 ) );
										}
										?>
									</p>
									<?php if ( $course_duration ) : ?>
										<span class="mini-resource-card__meta"><?php echo esc_html( $course_duration ); ?></span>
									<?php endif; ?>
									<a class="button button--primary button--full" href="<?php the_permalink(); ?>"><?php esc_html_e( 'Open course', 'gsf-hub' ); ?></a>
								</div>
							<?php endwhile; ?>
							<?php wp_reset_postdata(); ?>
						<?php endif; ?>
					</div>
				</article>

			<aside class="event-card">
				<div class="event-card__icon"><?php echo gsf_hub_get_icon( 'calendar' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
				<p class="section-heading__eyebrow"><?php echo esc_html( $event_card_eyebrow ); ?></p>
				<h2><?php gsf_hub_homepage_inline( $homepage_zoom_events->have_posts() ? $event_card_heading : $content['event_title'] ); ?></h2>
				<?php if ( $homepage_zoom_events->have_posts() ) : ?>
					<div class="event-card__events">
						<?php
						while ( $homepage_zoom_events->have_posts() ) :
							$homepage_zoom_events->the_post();

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
							<article class="event-card__event">
								<?php if ( $meeting_time ) : ?>
									<p class="event-card__date"><?php echo esc_html( $meeting_time ); ?></p>
								<?php endif; ?>
								<h3><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h3>
								<a class="event-card__link" href="<?php the_permalink(); ?>"><?php esc_html_e( 'View event', 'gsf-hub' ); ?></a>
							</article>
						<?php endwhile; ?>
						<?php wp_reset_postdata(); ?>
					</div>
				<?php else : ?>
					<p><?php gsf_hub_homepage_inline( $content['event_text'] ); ?></p>
				<?php endif; ?>
				<a class="button button--secondary button--full" href="<?php echo esc_url( $upcoming_zoom_events_url ?: $content['event_button_url'] ); ?>"><?php echo esc_html( $event_button_label ); ?></a>
			</aside>
		</section>

		<?php if ( is_page() && have_posts() ) : ?>
			<?php while ( have_posts() ) : the_post(); ?>
				<?php if ( trim( wp_strip_all_tags( get_the_content() ) ) ) : ?>
					<section class="content-panel">
						<article <?php post_class( 'content-card' ); ?>>
							<div class="entry-content">
								<?php the_content(); ?>
							</div>
						</article>
					</section>
				<?php endif; ?>
			<?php endwhile; ?>
		<?php endif; ?>
	</div>
</main>

<?php get_footer(); ?>
