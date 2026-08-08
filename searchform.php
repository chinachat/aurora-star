<?php
/**
 * 搜索表单。
 *
 * @package Aurora Star
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$aurora_star_search_id = 'search-' . uniqid();
?>
<form role="search" method="get" class="search-form" action="<?php echo esc_url( home_url( '/' ) ); ?>">
	<label for="<?php echo esc_attr( $aurora_star_search_id ); ?>" class="screen-reader-text"><?php esc_html_e( '搜索：', 'aurora-star' ); ?></label>
	<div class="search-form__field">
		<i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
		<input type="search" id="<?php echo esc_attr( $aurora_star_search_id ); ?>" class="search-form__input" placeholder="<?php esc_attr_e( '输入关键词…', 'aurora-star' ); ?>" value="<?php echo esc_attr( get_search_query() ); ?>" name="s" />
		<button type="submit" class="search-form__submit"><?php esc_html_e( '搜索', 'aurora-star' ); ?></button>
	</div>
</form>
