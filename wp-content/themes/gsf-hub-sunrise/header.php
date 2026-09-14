<?php
/**
 * Theme header.
 *
 * @package GSF_Hub
 */

$canada_logo_uri = gsf_hub_sunrise_asset_uri( 'assets/images/canada-partnership.png' );
$cbf_logo_uri    = gsf_hub_sunrise_asset_uri( 'assets/images/cbf-logo.png' );
$brand_icon_uri  = gsf_hub_sunrise_asset_uri( 'assets/images/icon-logo.png' );
$brand_logo_uri  = gsf_hub_sunrise_asset_uri( 'assets/images/gsf-logo-updated.png' );
$has_gtranslate  = shortcode_exists( 'gtranslate' );
$account_url     = function_exists( 'gsf_hub_get_member_account_url' ) ? gsf_hub_get_member_account_url() : home_url( '/account/' );
$login_url       = function_exists( 'gsf_hub_get_member_login_url' ) ? gsf_hub_get_member_login_url( $account_url ) : wp_login_url( $account_url );
$logout_url      = wp_logout_url( home_url( '/' ) );
?><!doctype html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<a class="screen-reader-text skip-link" href="#primary"><?php esc_html_e( 'Skip to content', 'gsf-hub' ); ?></a>

<div class="partnership-bar">
	<div class="site-shell partnership-bar__inner">
		<div class="partnership-bar__logo-wrap">
			<img class="partnership-bar__logo partnership-bar__logo--canada" src="<?php echo esc_url( $canada_logo_uri ); ?>" alt="<?php esc_attr_e( 'In partnership with Canada', 'gsf-hub' ); ?>">
		</div>
		<div class="partnership-bar__logo-wrap partnership-bar__logo-wrap--partner">
			<img class="partnership-bar__logo partnership-bar__logo--cbf" src="<?php echo esc_url( $cbf_logo_uri ); ?>" alt="<?php esc_attr_e( 'Caribbean Biodiversity Fund', 'gsf-hub' ); ?>">
		</div>
	</div>
</div>

<header class="site-header">
	<div class="site-shell site-header__inner">
		<div class="site-branding">
			<a class="sunrise-branding__compact notranslate" href="<?php echo esc_url( home_url( '/' ) ); ?>" rel="home" aria-label="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>">
				<img class="sunrise-branding__icon" src="<?php echo esc_url( $brand_icon_uri ); ?>" alt="">
				<span class="sunrise-branding__name"><?php bloginfo( 'name' ); ?></span>
			</a>

			<a class="sunrise-branding__lockup notranslate" href="<?php echo esc_url( home_url( '/' ) ); ?>" rel="home" aria-label="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>">
				<img class="sunrise-branding__logo" src="<?php echo esc_url( $brand_logo_uri ); ?>" alt="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>">
			</a>
		</div>

		<button class="nav-toggle" type="button" aria-expanded="false" aria-controls="primary-navigation" data-nav-toggle>
			<span class="nav-toggle__icon"><?php echo gsf_hub_get_icon( 'menu' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
			<span class="screen-reader-text"><?php echo esc_html( gsf_hub_ui_text( 'toggle_navigation', __( 'Toggle navigation', 'gsf-hub' ) ) ); ?></span>
		</button>

		<nav id="primary-navigation" class="site-navigation" aria-label="<?php echo esc_attr( gsf_hub_ui_text( 'primary_menu', __( 'Primary menu', 'gsf-hub' ) ) ); ?>">
			<?php
			if ( has_nav_menu( 'primary' ) ) {
				wp_nav_menu(
					array(
						'theme_location' => 'primary',
						'container'      => false,
						'menu_class'     => 'primary-menu',
					)
				);
			} else {
				gsf_hub_render_menu_fallback( 'primary', 'primary-menu' );
			}
			?>
		</nav>

		<div class="site-actions">
			<?php if ( $has_gtranslate ) : ?>
				<div class="site-language-switcher site-language-switcher--gtranslate notranslate" aria-label="<?php esc_attr_e( 'Choose language', 'gsf-hub' ); ?>">
					<?php echo do_shortcode( '[gtranslate widget_look="dropdown_with_flags"]' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</div>
			<?php else : ?>
				<span class="language-dropdown language-dropdown--static">
					<span class="language-dropdown__current">
						<span class="button__icon"><?php echo gsf_hub_get_icon( 'globe' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
						<span><?php esc_html_e( 'EN', 'gsf-hub' ); ?></span>
					</span>
				</span>
			<?php endif; ?>
			<?php if ( is_user_logged_in() ) : ?>
				<a class="button button--primary" href="<?php echo esc_url( $logout_url ); ?>"><?php echo esc_html( gsf_hub_ui_text( 'log_out', __( 'Log out', 'gsf-hub' ) ) ); ?></a>
			<?php else : ?>
				<a class="button button--primary" href="<?php echo esc_url( $login_url ); ?>"><?php echo esc_html( gsf_hub_ui_text( 'sign_in', __( 'Sign in', 'gsf-hub' ) ) ); ?></a>
			<?php endif; ?>
		</div>
	</div>
</header>
