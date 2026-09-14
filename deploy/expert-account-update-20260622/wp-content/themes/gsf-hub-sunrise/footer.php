<?php
/**
 * Theme footer.
 *
 * @package GSF_Hub
 */

$canada_logo_uri = gsf_hub_asset_uri( 'assets/images/canada-partnership.jpg' );
$cbf_logo_uri    = gsf_hub_asset_uri( 'assets/images/cbf-logo.png' );
$brand_logo_uri  = gsf_hub_sunrise_asset_uri( 'assets/images/gsf-logo-updated.png' );
$account_url     = function_exists( 'gsf_hub_get_member_account_url' ) ? gsf_hub_get_member_account_url() : home_url( '/account/' );
$login_url       = function_exists( 'gsf_hub_get_member_login_url' ) ? gsf_hub_get_member_login_url( $account_url ) : wp_login_url( $account_url );
?>
<footer class="site-footer">
	<div class="site-shell site-footer__grid">
		<div class="site-footer__brand sunrise-footer-brand">
			<a class="sunrise-footer-brand__lockup" href="<?php echo esc_url( home_url( '/' ) ); ?>" rel="home" aria-label="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>">
				<img class="sunrise-footer-brand__logo" src="<?php echo esc_url( $brand_logo_uri ); ?>" alt="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>">
			</a>
			<p><?php esc_html_e( 'A CBF platform delivered through the CORE project, supporting gender-responsive biodiversity conservation and climate resilience across the Caribbean.', 'gsf-hub' ); ?></p>
		</div>

		<div class="site-footer__column">
			<h2><?php esc_html_e( 'Platform', 'gsf-hub' ); ?></h2>
			<?php
			if ( has_nav_menu( 'footer' ) ) {
				wp_nav_menu(
					array(
						'theme_location' => 'footer',
						'container'      => false,
						'menu_class'     => 'footer-menu',
					)
				);
			} else {
				gsf_hub_render_menu_fallback( 'footer', 'footer-menu' );
			}
			?>
		</div>

		<div class="site-footer__column">
			<h2><?php esc_html_e( 'Quick Access', 'gsf-hub' ); ?></h2>
			<ul class="footer-menu">
				<li><a href="<?php echo esc_url( gsf_hub_get_page_url( 'resources' ) ); ?>"><?php esc_html_e( 'Resource Library', 'gsf-hub' ); ?></a></li>
				<li><a href="<?php echo esc_url( gsf_hub_get_page_url( 'events' ) ); ?>"><?php esc_html_e( 'Events and Webinars', 'gsf-hub' ); ?></a></li>
				<li><a href="<?php echo esc_url( gsf_hub_get_page_url( 'directory' ) ); ?>"><?php esc_html_e( 'Expert Directory', 'gsf-hub' ); ?></a></li>
				<li><a href="<?php echo esc_url( is_user_logged_in() ? $account_url : $login_url ); ?>"><?php echo esc_html( is_user_logged_in() ? __( 'My Account', 'gsf-hub' ) : __( 'Member Login', 'gsf-hub' ) ); ?></a></li>
			</ul>
		</div>

		<?php if ( shortcode_exists( 'mailchimpsf_form' ) ) : ?>
			<div class="site-footer__column site-footer__newsletter">
				<h2><?php esc_html_e( 'Mailing List', 'gsf-hub' ); ?></h2>
				<p><?php esc_html_e( 'Get GSF Hub updates, learning opportunities, and platform announcements.', 'gsf-hub' ); ?></p>
				<?php echo do_shortcode( '[mailchimpsf_form]' ); ?>
			</div>
		<?php endif; ?>

		<div class="site-footer__column">
			<h2><?php esc_html_e( 'Partnership', 'gsf-hub' ); ?></h2>
			<div class="site-footer__logos">
				<img class="site-footer__logo site-footer__logo--canada" src="<?php echo esc_url( $canada_logo_uri ); ?>" alt="<?php esc_attr_e( 'In partnership with Canada', 'gsf-hub' ); ?>">
				<img class="site-footer__logo site-footer__logo--cbf" src="<?php echo esc_url( $cbf_logo_uri ); ?>" alt="<?php esc_attr_e( 'Caribbean Biodiversity Fund', 'gsf-hub' ); ?>">
			</div>
		</div>
	</div>
</footer>

<?php wp_footer(); ?>
</body>
</html>
