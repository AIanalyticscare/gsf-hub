<?php
/**
 * Single expert profile template.
 *
 * @package GSF_Hub
 */

get_header();
?>

<main id="primary" class="site-main site-main--standard">
	<div class="site-shell">
		<?php while ( have_posts() ) : the_post(); ?>
			<?php $expert = gsf_hub_get_expert_profile_data( get_the_ID() ); ?>
			<section class="expert-profile">
				<div class="expert-profile__hero">
					<div class="expert-profile__identity">
						<a class="expert-back-link" href="<?php echo esc_url( gsf_hub_get_page_url( 'directory' ) ); ?>"><?php esc_html_e( 'Back to Expert Directory', 'gsf-hub' ); ?></a>
						<p class="section-heading__eyebrow"><?php esc_html_e( 'Expert Profile', 'gsf-hub' ); ?></p>
						<h1><?php echo esc_html( $expert['name'] ); ?></h1>
						<?php if ( $expert['organization'] ) : ?>
							<p class="expert-profile__org"><?php echo esc_html( $expert['organization'] ); ?></p>
						<?php endif; ?>
						<?php if ( ! empty( $expert['country'] ) ) : ?>
							<div class="expert-profile__tags">
								<?php foreach ( $expert['country'] as $country_name ) : ?>
									<span class="expert-tag"><?php echo esc_html( $country_name ); ?></span>
								<?php endforeach; ?>
							</div>
						<?php endif; ?>
					</div>
					<div class="expert-profile__portrait">
						<?php if ( $expert['image_url'] ) : ?>
							<img src="<?php echo esc_url( $expert['image_url'] ); ?>" alt="<?php echo esc_attr( $expert['name'] ); ?>">
						<?php else : ?>
							<div class="expert-profile__placeholder"><?php echo esc_html( $expert['initials'] ); ?></div>
						<?php endif; ?>
					</div>
				</div>

				<div class="expert-profile__grid">
					<article class="content-card expert-profile__content">
						<h2><?php esc_html_e( 'Overview', 'gsf-hub' ); ?></h2>
						<?php if ( $expert['bio'] ) : ?>
							<p><?php echo esc_html( $expert['bio'] ); ?></p>
						<?php else : ?>
							<p><?php esc_html_e( 'This profile will show a longer biography, areas of expertise, and supporting context as the expert roster grows.', 'gsf-hub' ); ?></p>
						<?php endif; ?>
						<div class="entry-content">
							<?php the_content(); ?>
						</div>
					</article>

					<aside class="content-card expert-profile__sidebar">
						<h2><?php esc_html_e( 'Profile Details', 'gsf-hub' ); ?></h2>
						<ul class="expert-detail-list">
							<?php if ( $expert['organization'] ) : ?>
								<li><strong><?php esc_html_e( 'Organization', 'gsf-hub' ); ?></strong><span><?php echo esc_html( $expert['organization'] ); ?></span></li>
							<?php endif; ?>
							<?php if ( $expert['job_title'] ) : ?>
								<li><strong><?php esc_html_e( 'Role', 'gsf-hub' ); ?></strong><span><?php echo esc_html( $expert['job_title'] ); ?></span></li>
							<?php endif; ?>
							<?php if ( ! empty( $expert['country'] ) ) : ?>
								<li><strong><?php esc_html_e( 'Country', 'gsf-hub' ); ?></strong><span><?php echo esc_html( implode( ', ', $expert['country'] ) ); ?></span></li>
							<?php endif; ?>
							<?php if ( ! empty( $expert['languages'] ) ) : ?>
								<li><strong><?php esc_html_e( 'Languages', 'gsf-hub' ); ?></strong><span><?php echo esc_html( implode( ', ', $expert['languages'] ) ); ?></span></li>
							<?php endif; ?>
							<?php if ( ! empty( $expert['expertise'] ) ) : ?>
								<li><strong><?php esc_html_e( 'Expertise', 'gsf-hub' ); ?></strong><span><?php echo esc_html( implode( ', ', $expert['expertise'] ) ); ?></span></li>
							<?php endif; ?>
							<?php if ( $expert['website'] ) : ?>
								<li><strong><?php esc_html_e( 'Website', 'gsf-hub' ); ?></strong><span><a href="<?php echo esc_url( $expert['website'] ); ?>"><?php echo esc_html( $expert['website'] ); ?></a></span></li>
							<?php endif; ?>
							<?php if ( $expert['linkedin'] ) : ?>
								<li><strong><?php esc_html_e( 'LinkedIn', 'gsf-hub' ); ?></strong><span><a href="<?php echo esc_url( $expert['linkedin'] ); ?>"><?php esc_html_e( 'View Profile', 'gsf-hub' ); ?></a></span></li>
							<?php endif; ?>
							<?php if ( $expert['email'] ) : ?>
								<li><strong><?php esc_html_e( 'Email', 'gsf-hub' ); ?></strong><span><a href="mailto:<?php echo antispambot( esc_attr( $expert['email'] ) ); ?>"><?php echo esc_html( antispambot( $expert['email'] ) ); ?></a></span></li>
							<?php endif; ?>
						</ul>
					</aside>
				</div>
			</section>
		<?php endwhile; ?>
	</div>
</main>

<?php get_footer(); ?>
