<?php
/**
 * Template Name: Forum
 * Description: Styled landing page shell for the bbPress community forum.
 *
 * @package GSF_Hub
 */

get_header();
?>

<main id="primary" class="site-main site-main--standard">
	<div class="site-shell">
		<?php while ( have_posts() ) : the_post(); ?>
			<?php
			$stats              = function_exists( 'gsf_hub_get_forum_stats' ) ? gsf_hub_get_forum_stats() : array( 'forums' => 0, 'topics' => 0, 'replies' => 0 );
			$current_url        = get_permalink( get_the_ID() );
			$login_url          = function_exists( 'gsf_hub_expert_workflow_get_login_url' ) ? gsf_hub_expert_workflow_get_login_url( $current_url ) : wp_login_url( $current_url );
			$register_url       = function_exists( 'gsf_hub_expert_workflow_get_register_url' ) ? gsf_hub_expert_workflow_get_register_url( $current_url ) : wp_registration_url();
			$account_url        = function_exists( 'gsf_hub_expert_workflow_get_um_page_url' ) ? gsf_hub_expert_workflow_get_um_page_url( 'core_account', '/account/' ) : home_url( '/account/' );
			$raw_content        = (string) get_post_field( 'post_content', get_the_ID() );
			$has_forum_shortcode = has_shortcode( $raw_content, 'bbp-forum-index' );
			$body_text          = trim( wp_strip_all_tags( strip_shortcodes( $raw_content ) ) );
			$primary_label      = __( 'Sign In to Participate', 'gsf-hub' );
			$primary_url        = $login_url;
			$secondary_label    = __( 'Create Account', 'gsf-hub' );
			$secondary_url      = $register_url;
			$hero_note          = __( 'Member accounts can browse publicly, then sign in to post topics, reply to discussions, and follow community activity.', 'gsf-hub' );

			if ( is_user_logged_in() ) {
				$primary_label   = __( 'Member Hub', 'gsf-hub' );
				$primary_url     = $account_url;
				$secondary_label = '';
				$secondary_url   = '';
				$hero_note       = __( 'Use the community to ask practical questions, continue webinar conversations, and connect discussions to training and roster activity.', 'gsf-hub' );
			}
			?>
			<section class="forum-hero">
				<div class="forum-hero__copy">
					<p class="section-heading__eyebrow"><?php esc_html_e( 'Community Forum', 'gsf-hub' ); ?></p>
					<h1><?php the_title(); ?></h1>
					<?php if ( has_excerpt() ) : ?>
						<p class="forum-hero__text"><?php echo esc_html( get_the_excerpt() ); ?></p>
					<?php else : ?>
						<p class="forum-hero__text"><?php esc_html_e( 'Join topical discussions, ask practical questions, and continue learning with peers across the GSF community.', 'gsf-hub' ); ?></p>
					<?php endif; ?>
					<div class="forum-hero__actions">
						<a class="button button--primary" href="<?php echo esc_url( $primary_url ); ?>"><?php echo esc_html( $primary_label ); ?></a>
						<?php if ( $secondary_label && $secondary_url ) : ?>
							<a class="button button--ghost" href="<?php echo esc_url( $secondary_url ); ?>"><?php echo esc_html( $secondary_label ); ?></a>
						<?php endif; ?>
					</div>
					<p class="forum-hero__note"><?php echo esc_html( $hero_note ); ?></p>
				</div>
				<div class="forum-hero__panel">
					<div class="directory-stat-card">
						<span class="directory-stat-card__icon"><?php echo gsf_hub_get_icon( 'message' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
						<strong><?php echo esc_html( (string) $stats['forums'] ); ?></strong>
						<span><?php esc_html_e( 'Forum spaces', 'gsf-hub' ); ?></span>
					</div>
					<div class="directory-stat-card">
						<span class="directory-stat-card__icon"><?php echo gsf_hub_get_icon( 'book' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
						<strong><?php echo esc_html( (string) $stats['topics'] ); ?></strong>
						<span><?php esc_html_e( 'Discussion topics', 'gsf-hub' ); ?></span>
					</div>
					<div class="directory-stat-card">
						<span class="directory-stat-card__icon"><?php echo gsf_hub_get_icon( 'users' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
						<strong><?php echo esc_html( (string) $stats['replies'] ); ?></strong>
						<span><?php esc_html_e( 'Community replies', 'gsf-hub' ); ?></span>
					</div>
				</div>
			</section>

			<section class="content-panel forum-panel">
				<article <?php post_class( 'content-card forum-card' ); ?>>
					<?php if ( $body_text && ! $has_forum_shortcode ) : ?>
						<header class="entry-header">
							<p class="section-heading__eyebrow"><?php esc_html_e( 'Forum Overview', 'gsf-hub' ); ?></p>
						</header>
					<?php endif; ?>
					<div class="entry-content forum-page-content">
						<?php the_content(); ?>
						<?php wp_link_pages(); ?>
					</div>
				</article>
			</section>
		<?php endwhile; ?>
	</div>
</main>

<?php get_footer(); ?>
