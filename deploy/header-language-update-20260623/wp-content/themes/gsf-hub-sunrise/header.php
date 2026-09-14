<?php
/**
 * Theme header.
 *
 * @package GSF_Hub
 */

$canada_logo_uri = gsf_hub_asset_uri( 'assets/images/canada-partnership.jpg' );
$cbf_logo_uri    = gsf_hub_asset_uri( 'assets/images/cbf-logo.png' );
$brand_icon_uri  = gsf_hub_sunrise_asset_uri( 'assets/images/icon-logo.png' );
$brand_logo_uri  = gsf_hub_sunrise_asset_uri( 'assets/images/gsf-logo-updated.png' );
$language_items  = gsf_hub_get_language_switcher_items();
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
			<a class="sunrise-branding__compact" href="<?php echo esc_url( home_url( '/' ) ); ?>" rel="home" aria-label="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>">
				<img class="sunrise-branding__icon" src="<?php echo esc_url( $brand_icon_uri ); ?>" alt="">
				<span class="sunrise-branding__name"><?php bloginfo( 'name' ); ?></span>
			</a>

			<a class="sunrise-branding__lockup" href="<?php echo esc_url( home_url( '/' ) ); ?>" rel="home" aria-label="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>">
				<img class="sunrise-branding__logo" src="<?php echo esc_url( $brand_logo_uri ); ?>" alt="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>">
			</a>
		</div>

		<button class="nav-toggle" type="button" aria-expanded="false" aria-controls="primary-navigation" data-nav-toggle>
			<span class="nav-toggle__icon"><?php echo gsf_hub_get_icon( 'menu' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
			<span class="screen-reader-text"><?php esc_html_e( 'Toggle navigation', 'gsf-hub' ); ?></span>
		</button>

		<nav id="primary-navigation" class="site-navigation" aria-label="<?php esc_attr_e( 'Primary menu', 'gsf-hub' ); ?>">
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
			<?php gsf_hub_render_language_dropdown( $language_items ); ?>
			<?php if ( is_user_logged_in() ) : ?>
				<a class="button button--primary" href="<?php echo esc_url( $logout_url ); ?>"><?php esc_html_e( 'Log out', 'gsf-hub' ); ?></a>
			<?php else : ?>
				<a class="button button--primary" href="<?php echo esc_url( $login_url ); ?>"><?php esc_html_e( 'Sign in', 'gsf-hub' ); ?></a>
			<?php endif; ?>
		</div>
	</div>
</header>
