<?php
/**
 * 404 template.
 *
 * @package GSF_Hub
 */

get_header();
?>

<main id="primary" class="site-main site-main--standard">
	<div class="site-shell">
		<section class="content-panel">
			<article class="content-card not-found">
				<p class="section-heading__eyebrow"><?php esc_html_e( '404', 'gsf-hub' ); ?></p>
				<h1><?php esc_html_e( 'That page could not be found.', 'gsf-hub' ); ?></h1>
				<p><?php esc_html_e( 'Use the site search below or return to the homepage.', 'gsf-hub' ); ?></p>
				<?php get_search_form(); ?>
				<p><a class="button button--primary" href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Back to Homepage', 'gsf-hub' ); ?></a></p>
			</article>
		</section>
	</div>
</main>

<?php get_footer(); ?>

