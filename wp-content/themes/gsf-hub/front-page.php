<?php
/**
 * Front page template.
 *
 * @package GSF_Hub
 */

get_header();

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

$project_progress_metrics = gsf_hub_get_home_project_progress_metrics();

if ( ! empty( $project_progress_metrics ) ) {
	$metrics = $project_progress_metrics;
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
?>

<main id="primary" class="site-main">
	<section class="hero" style="background-image: radial-gradient(circle at 18% 22%, rgba(255, 255, 255, 0.28), transparent 0 18rem), radial-gradient(circle at 78% 24%, rgba(255, 255, 255, 0.12), transparent 0 16rem), radial-gradient(circle at 52% 84%, rgba(255, 255, 255, 0.12), transparent 0 15rem), linear-gradient(135deg, rgba(15, 94, 101, 0.9) 0%, rgba(18, 107, 120, 0.82) 44%, rgba(18, 58, 94, 0.9) 100%), url('<?php echo esc_url( $content['hero_background_image'] ); ?>');">
		<div class="site-shell hero__grid">
			<div class="hero__content">
				<div class="hero__eyebrow">
					<span class="hero__eyebrow-icon"><?php echo gsf_hub_get_icon( 'award' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
					<span><?php echo esc_html( $content['hero_eyebrow'] ); ?></span>
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
				<div class="hero-card__visual" style="background-image: linear-gradient(180deg, rgba(7, 42, 62, 0.14) 0%, rgba(7, 42, 62, 0.55) 100%), url('<?php echo esc_url( $content['hero_card_image'] ); ?>');">
					<div class="hero-card__avatar">
						<?php echo gsf_hub_get_icon( 'users' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					</div>
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
		<section class="search-panel-wrapper" aria-label="<?php echo esc_attr( gsf_hub_ui_text( 'search_the_hub', __( 'Search the hub', 'gsf-hub' ) ) ); ?>">
			<form class="search-panel" role="search" method="get" action="<?php echo esc_url( home_url( '/' ) ); ?>">
				<label class="screen-reader-text" for="gsf-site-search"><?php echo esc_html( gsf_hub_ui_text( 'search_the_site', __( 'Search the site', 'gsf-hub' ) ) ); ?></label>
				<div class="search-panel__field">
					<span class="search-panel__icon"><?php echo gsf_hub_get_icon( 'search' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
					<input id="gsf-site-search" type="search" name="s" value="<?php echo esc_attr( get_search_query() ); ?>" placeholder="<?php echo esc_attr( gsf_hub_ui_text( 'search_placeholder', __( 'Search resources, courses, case studies, experts, and webinars...', 'gsf-hub' ) ) ); ?>">
				</div>
				<button class="button button--primary" type="submit"><?php echo esc_html( gsf_hub_ui_text( 'search', __( 'Search', 'gsf-hub' ) ) ); ?></button>
			</form>
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
				<div class="story-card__media" style="background-image: linear-gradient(180deg, rgba(8, 29, 26, 0.12) 0%, rgba(8, 29, 26, 0.52) 100%), url('<?php echo esc_url( $content['case_image'] ); ?>');">
					<div class="story-card__avatar">
						<?php echo gsf_hub_get_icon( 'book' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					</div>
					<p class="story-card__label"><?php echo esc_html( $content['case_label'] ); ?></p>
				</div>
				<div class="story-card__content">
					<p class="section-heading__eyebrow"><?php echo esc_html( $content['case_eyebrow'] ); ?></p>
					<h2><?php gsf_hub_homepage_inline( $content['case_title'] ); ?></h2>
					<p><?php gsf_hub_homepage_inline( $content['case_text'] ); ?></p>
					<ul class="story-list">
						<?php foreach ( $stories as $story ) : ?>
							<li><?php echo esc_html( $story ); ?></li>
						<?php endforeach; ?>
					</ul>
				</div>
			</article>

			<aside class="data-card">
				<p class="section-heading__eyebrow section-heading__eyebrow--light"><?php echo esc_html( $content['data_eyebrow'] ); ?></p>
				<h2><?php gsf_hub_homepage_inline( $content['data_title'] ); ?></h2>
				<div class="metric-list">
					<?php foreach ( $metrics as $metric ) : ?>
						<div class="metric">
							<div class="metric__meta">
								<span><?php echo esc_html( $metric['label'] ); ?></span>
								<strong><?php echo esc_html( $metric['value'] ); ?>%</strong>
							</div>
							<div class="metric__track">
								<div class="metric__fill" style="width: <?php echo esc_attr( $metric['value'] ); ?>%"></div>
							</div>
						</div>
					<?php endforeach; ?>
				</div>
				<a class="button button--light button--data" href="<?php echo esc_url( $content['data_button_url'] ); ?>"><?php echo esc_html( $content['data_button_label'] ); ?></a>
			</aside>
		</section>

		<section class="resource-layout section-block">
			<article class="resource-card" style="background-image: linear-gradient(180deg, rgba(255, 255, 255, 0.92) 0%, rgba(248, 251, 252, 0.97) 100%), url('<?php echo esc_url( $content['resource_background'] ); ?>');">
				<div class="resource-card__header">
					<div>
						<p class="section-heading__eyebrow"><?php echo esc_html( $content['resource_eyebrow'] ); ?></p>
						<h2><?php gsf_hub_homepage_inline( $content['resource_title'] ); ?></h2>
					</div>
					<span class="resource-card__icon"><?php echo gsf_hub_get_icon( 'download' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
				</div>

				<div class="resource-grid">
					<?php foreach ( $resources as $resource ) : ?>
						<div class="mini-resource-card">
							<h3><?php echo esc_html( $resource['title'] ); ?></h3>
							<p><?php echo esc_html( $resource['meta'] ); ?></p>
							<a class="button button--ghost button--full" href="<?php echo esc_url( $resource['url'] ); ?>"><?php echo esc_html( $content['resource_button_label'] ); ?></a>
						</div>
					<?php endforeach; ?>
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
