<?php
/**
 * Search form template.
 *
 * @package GSF_Hub
 */
?>
<form class="search-form-inline" role="search" method="get" action="<?php echo esc_url( home_url( '/' ) ); ?>">
	<label class="screen-reader-text" for="search-form-field"><?php esc_html_e( 'Search for:', 'gsf-hub' ); ?></label>
	<input id="search-form-field" type="search" name="s" value="<?php echo esc_attr( get_search_query() ); ?>" placeholder="<?php echo esc_attr( gsf_hub_ui_text( 'search_hub_placeholder', __( 'Search the hub...', 'gsf-hub' ) ) ); ?>">
	<button class="button button--primary" type="submit"><?php echo esc_html( gsf_hub_ui_text( 'search', __( 'Search', 'gsf-hub' ) ) ); ?></button>
</form>
