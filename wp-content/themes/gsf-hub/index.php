<?php
/**
 * Default archive and blog index template.
 *
 * @package GSF_Hub
 */

get_header();
?>

<main id="primary" class="site-main site-main--standard">
	<div class="site-shell">
		<header class="archive-header">
			<p class="section-heading__eyebrow"><?php esc_html_e( 'Stories and Updates', 'gsf-hub' ); ?></p>
			<h1><?php echo esc_html( is_home() ? get_bloginfo( 'name' ) : wp_get_document_title() ); ?></h1>
		</header>

		<?php if ( have_posts() ) : ?>
			<div class="post-grid">
				<?php while ( have_posts() ) : the_post(); ?>
					<article <?php post_class( 'post-card' ); ?>>
						<?php if ( has_post_thumbnail() ) : ?>
							<a class="post-card__image" href="<?php the_permalink(); ?>">
								<?php the_post_thumbnail( 'large' ); ?>
							</a>
						<?php endif; ?>

						<div class="post-card__content">
							<p class="entry-meta"><?php echo esc_html( get_the_date() ); ?></p>
							<h2><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2>
							<p><?php echo esc_html( get_the_excerpt() ); ?></p>
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
					<h2><?php esc_html_e( 'No posts found', 'gsf-hub' ); ?></h2>
					<p><?php esc_html_e( 'Start publishing stories, resources, or updates and they will appear here.', 'gsf-hub' ); ?></p>
				</article>
			</section>
		<?php endif; ?>
	</div>
</main>

<?php get_footer(); ?>

