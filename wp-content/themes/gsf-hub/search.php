<?php
/**
 * Search results template.
 *
 * @package GSF_Hub
 */

get_header();
?>

<main id="primary" class="site-main site-main--standard">
	<div class="site-shell">
		<header class="archive-header">
			<p class="section-heading__eyebrow"><?php esc_html_e( 'Search Results', 'gsf-hub' ); ?></p>
			<h1>
				<?php
				printf(
					/* translators: %s: search query. */
					esc_html__( 'Results for "%s"', 'gsf-hub' ),
					esc_html( get_search_query() )
				);
				?>
			</h1>
			<?php get_search_form(); ?>
		</header>

		<?php if ( have_posts() ) : ?>
			<div class="post-grid">
				<?php while ( have_posts() ) : the_post(); ?>
					<?php $post_type = get_post_type_object( get_post_type() ); ?>
					<article <?php post_class( 'post-card' ); ?>>
						<div class="post-card__content">
							<p class="entry-meta">
								<?php
								echo esc_html(
									$post_type && isset( $post_type->labels->singular_name )
										? $post_type->labels->singular_name
										: __( 'Content', 'gsf-hub' )
								);
								?>
							</p>
							<h2><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2>
							<p><?php echo esc_html( gsf_hub_get_search_result_excerpt( get_the_ID() ) ); ?></p>
						</div>
					</article>
				<?php endwhile; ?>
			</div>

			<div class="pagination-wrap">
				<?php the_posts_pagination(); ?>
			</div>
		<?php else : ?>
			<section class="content-panel">
				<article class="content-card not-found">
					<h2><?php esc_html_e( 'No matching content found', 'gsf-hub' ); ?></h2>
					<p><?php esc_html_e( 'Try a broader search term or create a dedicated resource page for the topic you need.', 'gsf-hub' ); ?></p>
				</article>
			</section>
		<?php endif; ?>
	</div>
</main>

<?php get_footer(); ?>
