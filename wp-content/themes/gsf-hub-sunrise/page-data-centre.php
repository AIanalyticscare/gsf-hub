<?php
/**
 * Data Centre page template.
 *
 * @package GSF_Hub_Sunrise
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();
?>

<main id="primary" class="site-main site-main--canvas">
	<article <?php post_class( 'page-canvas page-canvas--data-centre' ); ?>>
		<div class="page-canvas__content entry-content">
			<?php echo do_shortcode( '[gsf_data_centre]' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		</div>
	</article>
</main>

<?php get_footer(); ?>
