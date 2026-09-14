<?php
/**
 * Single post template.
 *
 * @package GSF_Hub
 */

get_header();
?>

<main id="primary" class="site-main site-main--standard">
	<div class="site-shell">
		<?php while ( have_posts() ) : the_post(); ?>
			<section class="content-panel">
				<article <?php post_class( 'content-card' ); ?>>
					<header class="entry-header">
						<p class="entry-meta"><?php echo esc_html( get_the_date() ); ?></p>
						<h1><?php the_title(); ?></h1>
					</header>

					<?php if ( has_post_thumbnail() ) : ?>
						<div class="entry-hero-image">
							<?php the_post_thumbnail( 'large' ); ?>
						</div>
					<?php endif; ?>

					<div class="entry-content">
						<?php the_content(); ?>
						<?php wp_link_pages(); ?>
					</div>
				</article>
			</section>
		<?php endwhile; ?>
	</div>
</main>

<?php get_footer(); ?>

