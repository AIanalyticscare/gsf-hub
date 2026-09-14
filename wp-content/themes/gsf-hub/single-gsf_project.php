<?php
/**
 * Single Data Centre project template.
 *
 * @package GSF_Hub
 */

get_header();

$data_centre_url = home_url( '/data-centre/' );
$data_page       = get_page_by_path( 'data-centre' );

if ( $data_page instanceof WP_Post ) {
	$data_centre_url = get_permalink( $data_page );
}

/**
 * Returns project meta for the current Data Centre project.
 *
 * @param int    $post_id Project post ID.
 * @param string $key     Meta key.
 * @return string
 */
function gsf_hub_project_detail_meta( $post_id, $key ) {
	$values = get_post_meta( $post_id, $key, false );
	$values = array_filter(
		array_map(
			static function ( $value ) {
				return trim( wp_strip_all_tags( (string) $value ) );
			},
			$values
		)
	);

	return implode( ', ', array_unique( $values ) );
}
?>

<main id="primary" class="site-main site-main--standard">
	<div class="site-shell">
		<?php while ( have_posts() ) : ?>
			<?php
			the_post();

			$project_id = get_the_ID();
			$fields     = array(
				__( 'Country', 'gsf-hub' )                 => gsf_hub_project_detail_meta( $project_id, 'country' ),
				__( 'Project type', 'gsf-hub' )            => gsf_hub_project_detail_meta( $project_id, 'project_type' ),
				__( 'Status', 'gsf-hub' )                  => gsf_hub_project_detail_meta( $project_id, 'project_status' ),
				__( 'Percent complete', 'gsf-hub' )        => gsf_hub_project_detail_meta( $project_id, 'percent_complete' ),
				__( 'Implementing party', 'gsf-hub' )      => gsf_hub_project_detail_meta( $project_id, 'implementing_party' ),
				__( 'Organizations', 'gsf-hub' )           => gsf_hub_project_detail_meta( $project_id, 'organizations' ),
				__( 'Funding source', 'gsf-hub' )          => gsf_hub_project_detail_meta( $project_id, 'funding_source' ),
				__( 'Gender responsiveness', 'gsf-hub' )   => gsf_hub_project_detail_meta( $project_id, 'gender_responsiveness' ),
				__( 'People affected', 'gsf-hub' )         => gsf_hub_project_detail_meta( $project_id, 'people_affected_total' ),
				__( 'Women affected', 'gsf-hub' )          => gsf_hub_project_detail_meta( $project_id, 'people_affected_female' ),
				__( 'Men affected', 'gsf-hub' )            => gsf_hub_project_detail_meta( $project_id, 'people_affected_male' ),
				__( 'Climate hazard score', 'gsf-hub' )    => gsf_hub_project_detail_meta( $project_id, 'climate_hazard_score' ),
				__( 'Habitat quality score', 'gsf-hub' )   => gsf_hub_project_detail_meta( $project_id, 'habitat_quality_score' ),
				__( 'Resilience score', 'gsf-hub' )        => gsf_hub_project_detail_meta( $project_id, 'social_economic_resilience_score' ),
				__( 'Durability score', 'gsf-hub' )        => gsf_hub_project_detail_meta( $project_id, 'durability_score' ),
			);
			$milestone = gsf_hub_project_detail_meta( $project_id, 'milestone_output' );
			?>
			<section class="content-panel">
				<article <?php post_class( 'content-card data-project-detail' ); ?>>
					<header class="entry-header data-project-detail__header">
						<p class="section-heading__eyebrow"><?php esc_html_e( 'Data Centre Project', 'gsf-hub' ); ?></p>
						<h1><?php the_title(); ?></h1>
						<p>
							<a class="button button--ghost" href="<?php echo esc_url( $data_centre_url ); ?>">
								<?php esc_html_e( 'Back to Data Centre', 'gsf-hub' ); ?>
							</a>
						</p>
					</header>

					<?php if ( $milestone ) : ?>
						<section class="data-project-detail__summary">
							<h2><?php esc_html_e( 'Milestone / Output', 'gsf-hub' ); ?></h2>
							<p><?php echo esc_html( $milestone ); ?></p>
						</section>
					<?php endif; ?>

					<dl class="data-project-detail__grid">
						<?php foreach ( $fields as $label => $value ) : ?>
							<?php if ( '' === $value ) : ?>
								<?php continue; ?>
							<?php endif; ?>
							<div>
								<dt><?php echo esc_html( $label ); ?></dt>
								<dd><?php echo esc_html( $value ); ?></dd>
							</div>
						<?php endforeach; ?>
					</dl>

					<?php if ( trim( wp_strip_all_tags( get_the_content() ) ) ) : ?>
						<div class="entry-content">
							<?php the_content(); ?>
						</div>
					<?php endif; ?>
				</article>
			</section>
		<?php endwhile; ?>
	</div>
</main>

<?php get_footer(); ?>
