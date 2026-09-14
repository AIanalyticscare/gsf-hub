<?php
/**
 * Generic page template.
 *
 * @package GSF_Hub
 */

get_header();
?>

<main id="primary" class="site-main site-main--standard">
	<div class="site-shell">
		<?php while ( have_posts() ) : the_post(); ?>
			<?php
			$account_shortcuts = '';
			if ( function_exists( 'gsf_hub_is_account_hub_page' ) && gsf_hub_is_account_hub_page( get_post() ) && function_exists( 'gsf_hub_render_account_hub_shortcuts' ) ) {
				$account_shortcuts = gsf_hub_render_account_hub_shortcuts();
			}
			?>
			<section class="content-panel">
				<article <?php post_class( 'content-card' ); ?>>
					<header class="entry-header">
						<p class="section-heading__eyebrow"><?php echo esc_html( function_exists( 'gsf_hub_get_page_eyebrow' ) ? gsf_hub_get_page_eyebrow( get_post() ) : get_the_title() ); ?></p>
						<h1><?php echo esc_html( function_exists( 'gsf_hub_get_page_display_title' ) ? gsf_hub_get_page_display_title( get_post() ) : get_the_title() ); ?></h1>
					</header>
					<?php if ( $account_shortcuts ) : ?>
						<?php echo $account_shortcuts; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
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
