<?php
/**
 * Theme footer.
 *
 * @package GSF_Hub
 */

$canada_logo_uri = gsf_hub_sunrise_asset_uri( 'assets/images/canada-partnership.png' );
$cbf_logo_uri    = gsf_hub_sunrise_asset_uri( 'assets/images/cbf-logo.png' );
$brand_logo_uri  = gsf_hub_sunrise_asset_uri( 'assets/images/gsf-logo-updated.png' );
$account_url     = function_exists( 'gsf_hub_get_member_account_url' ) ? gsf_hub_get_member_account_url() : home_url( '/account/' );
$login_url       = function_exists( 'gsf_hub_get_member_login_url' ) ? gsf_hub_get_member_login_url( $account_url ) : wp_login_url( $account_url );
?>
<footer class="site-footer">
	<div class="site-shell site-footer__grid">
		<div class="site-footer__brand sunrise-footer-brand">
			<a class="sunrise-footer-brand__lockup" href="<?php echo esc_url( gsf_hub_get_home_url() ); ?>" rel="home" aria-label="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>">
				<img class="sunrise-footer-brand__logo" src="<?php echo esc_url( $brand_logo_uri ); ?>" alt="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>">
			</a>
			<p><?php echo esc_html( gsf_hub_ui_text( 'footer_description', __( 'A CBF platform delivered through the CORE project, supporting gender-responsive biodiversity conservation and climate resilience across the Caribbean.', 'gsf-hub' ) ) ); ?></p>
		</div>

		<div class="site-footer__column">
			<h2><?php echo esc_html( gsf_hub_ui_text( 'platform', __( 'Platform', 'gsf-hub' ) ) ); ?></h2>
			<?php gsf_hub_render_menu_fallback( 'footer', 'footer-menu' ); ?>
		</div>

		<div class="site-footer__column">
			<h2><?php echo esc_html( gsf_hub_ui_text( 'quick_access', __( 'Quick Access', 'gsf-hub' ) ) ); ?></h2>
			<ul class="footer-menu">
				<li><a href="<?php echo esc_url( gsf_hub_get_page_url( 'resources' ) ); ?>"><?php echo esc_html( gsf_hub_ui_text( 'resource_library', __( 'Resource Library', 'gsf-hub' ) ) ); ?></a></li>
				<li><a href="<?php echo esc_url( gsf_hub_get_page_url( 'events' ) ); ?>"><?php echo esc_html( gsf_hub_ui_text( 'events_webinars', __( 'Events and Webinars', 'gsf-hub' ) ) ); ?></a></li>
				<li><a href="<?php echo esc_url( gsf_hub_get_page_url( 'directory' ) ); ?>"><?php echo esc_html( gsf_hub_ui_text( 'expert_directory', __( 'Expert Directory', 'gsf-hub' ) ) ); ?></a></li>
				<li><a href="<?php echo esc_url( is_user_logged_in() ? $account_url : $login_url ); ?>"><?php echo esc_html( is_user_logged_in() ? gsf_hub_ui_text( 'my_account', __( 'My Account', 'gsf-hub' ) ) : gsf_hub_ui_text( 'member_login', __( 'Member Login', 'gsf-hub' ) ) ); ?></a></li>
			</ul>
		</div>

		<?php if ( shortcode_exists( 'mailchimpsf_form' ) ) : ?>
			<div class="site-footer__column site-footer__newsletter">
				<h2><?php echo esc_html( gsf_hub_ui_text( 'mailing_list', __( 'Mailing List', 'gsf-hub' ) ) ); ?></h2>
				<p><?php echo esc_html( gsf_hub_ui_text( 'mailing_text', __( 'Get GSF Hub updates, learning opportunities, and platform announcements.', 'gsf-hub' ) ) ); ?></p>
				<?php echo do_shortcode( '[mailchimpsf_form]' ); ?>
			</div>
		<?php endif; ?>

		<div class="site-footer__column">
			<h2><?php echo esc_html( gsf_hub_ui_text( 'partnership', __( 'Partnership', 'gsf-hub' ) ) ); ?></h2>
			<div class="site-footer__logos">
				<img class="site-footer__logo site-footer__logo--canada" src="<?php echo esc_url( $canada_logo_uri ); ?>" alt="<?php echo esc_attr( gsf_hub_ui_text( 'canada_alt', __( 'In partnership with Canada', 'gsf-hub' ) ) ); ?>">
				<img class="site-footer__logo site-footer__logo--cbf" src="<?php echo esc_url( $cbf_logo_uri ); ?>" alt="<?php echo esc_attr( gsf_hub_ui_text( 'cbf_alt', __( 'Caribbean Biodiversity Fund', 'gsf-hub' ) ) ); ?>">
			</div>
		</div>
	</div>
</footer>

<?php wp_footer(); ?>
</body>
</html>
