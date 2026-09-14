<?php
/**
 * Archive template.
 *
 * @package GSF_Hub
 */

get_header();
?>

<main id="primary" class="site-main site-main--standard">
	<div class="site-shell">
		<header class="archive-header">
			<p class="section-heading__eyebrow"><?php esc_html_e( 'Archive', 'gsf-hub' ); ?></p>
			<h1><?php the_archive_title(); ?></h1>
			<?php the_archive_description( '<div class="archive-description">', '</div>' ); ?>
		</header>

		<?php if ( have_posts() ) : ?>
			<div class="post-grid">
				<?php while ( have_posts() ) : the_post(); ?>
					<article <?php post_class( 'post-card' ); ?>>
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
					<h2><?php esc_html_e( 'Nothing here yet', 'gsf-hub' ); ?></h2>
					<p><?php esc_html_e( 'This archive will populate once content is published.', 'gsf-hub' ); ?></p>
				</article>
			</section>
		<?php endif; ?>
	</div>
</main>

<?php get_footer(); ?>

