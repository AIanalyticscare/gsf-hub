<?php
/**
 * Template Name: Expert Directory
 * Description: Editable WordPress page shell for the expert roster.
 *
 * @package GSF_Hub
 */

get_header();
?>

<main id="primary" class="site-main site-main--standard">
	<div class="site-shell">
		<?php while ( have_posts() ) : the_post(); ?>
			<?php
			$stats                   = gsf_hub_get_expert_directory_stats();
			$raw_content             = (string) get_post_field( 'post_content', get_the_ID() );
			$has_directory_shortcode = has_shortcode( $raw_content, 'gsf_expert_directory' );
			$has_custom_content      = trim( wp_strip_all_tags( strip_shortcodes( $raw_content ) ) );
			$portal_url              = function_exists( 'gsf_hub_expert_workflow_get_portal_page_url' ) ? gsf_hub_expert_workflow_get_portal_page_url() : home_url( '/expert-portal/' );
			$account_url             = function_exists( 'gsf_hub_get_member_account_url' ) ? gsf_hub_get_member_account_url() : ( function_exists( 'gsf_hub_expert_workflow_get_um_page_url' ) ? gsf_hub_expert_workflow_get_um_page_url( 'core_account', '/account/' ) : home_url( '/account/' ) );
			$register_url            = function_exists( 'gsf_hub_get_member_register_url' ) ? gsf_hub_get_member_register_url( $portal_url ) : ( function_exists( 'gsf_hub_expert_workflow_get_register_url' ) ? gsf_hub_expert_workflow_get_register_url( $portal_url ) : wp_registration_url() );
			$cta_label               = __( 'Join the Expert Roster', 'gsf-hub' );
			$cta_url                 = $portal_url;
			$secondary_label         = __( 'Create Account', 'gsf-hub' );
			$secondary_url           = $register_url;
			$cta_note                = __( 'Create a member account first, then complete the separate expert roster application. Applications are reviewed before profiles are published.', 'gsf-hub' );

			if ( is_user_logged_in() ) {
				$secondary_label = __( 'My Account', 'gsf-hub' );
				$secondary_url   = $account_url;

				if ( function_exists( 'gsf_hub_expert_workflow_get_application_status' ) ) {
					$status = gsf_hub_expert_workflow_get_application_status( get_current_user_id() );

					if ( 'approved' === $status ) {
						$cta_label = __( 'Manage Expert Profile', 'gsf-hub' );
					} elseif ( 'changes_requested' === $status ) {
						$cta_label = __( 'Update Application', 'gsf-hub' );
					} elseif ( 'rejected' === $status ) {
						$cta_label = __( 'Review Application', 'gsf-hub' );
					} elseif ( 'pending' === $status ) {
						$cta_label = __( 'Open Expert Portal', 'gsf-hub' );
					} else {
						$cta_label = __( 'Start Expert Application', 'gsf-hub' );
					}
				} else {
					$cta_label = __( 'Open Expert Portal', 'gsf-hub' );
				}
			}
			?>
			<section class="directory-hero">
				<div class="directory-hero__copy">
					<p class="section-heading__eyebrow"><?php esc_html_e( 'Expert Roster', 'gsf-hub' ); ?></p>
					<h1><?php the_title(); ?></h1>
					<?php if ( has_excerpt() ) : ?>
						<p class="directory-hero__text"><?php echo esc_html( get_the_excerpt() ); ?></p>
					<?php else : ?>
						<p class="directory-hero__text"><?php esc_html_e( 'Browse a curated roster of practitioners, researchers, and partners contributing to gender-smart conservation across the Caribbean.', 'gsf-hub' ); ?></p>
					<?php endif; ?>
					<div class="directory-hero__actions">
						<a class="button button--primary" href="<?php echo esc_url( $cta_url ); ?>"><?php echo esc_html( $cta_label ); ?></a>
						<a class="button button--ghost" href="<?php echo esc_url( $secondary_url ); ?>"><?php echo esc_html( $secondary_label ); ?></a>
					</div>
					<p class="directory-hero__note"><?php echo esc_html( $cta_note ); ?></p>
				</div>
				<div class="directory-hero__panel">
					<div class="directory-stat-card">
						<span class="directory-stat-card__icon"><?php echo gsf_hub_get_icon( 'users' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
						<strong><?php echo esc_html( (string) $stats['experts'] ); ?></strong>
						<span><?php esc_html_e( 'Published experts', 'gsf-hub' ); ?></span>
					</div>
					<div class="directory-stat-card">
						<span class="directory-stat-card__icon"><?php echo gsf_hub_get_icon( 'globe' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
						<strong><?php echo esc_html( (string) $stats['countries'] ); ?></strong>
						<span><?php esc_html_e( 'Countries represented', 'gsf-hub' ); ?></span>
					</div>
					<div class="directory-stat-card">
						<span class="directory-stat-card__icon"><?php echo gsf_hub_get_icon( 'award' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
						<strong><?php echo esc_html( (string) $stats['organizations'] ); ?></strong>
						<span><?php esc_html_e( 'Organizations in the roster', 'gsf-hub' ); ?></span>
					</div>
				</div>
			</section>

			<?php if ( $raw_content ) : ?>
				<?php if ( $has_directory_shortcode ) : ?>
					<div class="directory-page-content entry-content">
						<?php the_content(); ?>
						<?php wp_link_pages(); ?>
					</div>
				<?php elseif ( $has_custom_content ) : ?>
					<section class="content-panel directory-page-content">
						<article <?php post_class( 'content-card' ); ?>>
							<div class="entry-content">
								<?php the_content(); ?>
								<?php wp_link_pages(); ?>
							</div>
						</article>
					</section>
				<?php endif; ?>
			<?php endif; ?>

			<?php if ( ! $has_directory_shortcode ) : ?>
				<?php echo do_shortcode( '[gsf_expert_directory page_id="' . get_the_ID() . '"]' ); ?>
			<?php endif; ?>
		<?php endwhile; ?>
	</div>
</main>

<?php get_footer(); ?>
