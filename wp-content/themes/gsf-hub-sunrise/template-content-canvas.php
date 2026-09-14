<?php
/**
 * Template Name: Content Canvas
 * Template Post Type: page
 *
 * @package GSF_Hub_Sunrise
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();
?>

<main id="primary" class="site-main site-main--canvas">
	<?php while ( have_posts() ) : the_post(); ?>
		<article <?php post_class( 'page-canvas' ); ?>>
			<div class="page-canvas__content entry-content">
				<?php the_content(); ?>
				<?php wp_link_pages(); ?>
			</div>
		</article>
	<?php endwhile; ?>
</main>

<?php get_footer(); ?>
