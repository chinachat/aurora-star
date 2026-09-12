<?php
/**
 * 特色图：文章没有设置时自动回退到「默认特色图」。
 *
 * 默认特色图存的是**附件 ID**（而不是图片 URL），这样可以走
 * wp_get_attachment_image()，自动带 srcset / sizes / alt / width / height，
 * 也能被 WordPress 按注册的尺寸裁剪。
 *
 * @package Aurora Star
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * 默认特色图的附件 ID。
 *
 * @return int 0 表示未设置或已失效
 */
function aurora_star_default_thumbnail_id() {
	$id = (int) get_theme_mod( 'aurora_star_default_thumbnail', 0 );

	if ( $id <= 0 ) {
		return 0;
	}

	// 附件可能已被删除，或改成了非图片。
	$mime = get_post_mime_type( $id );
	if ( ! $mime || 0 !== strpos( (string) $mime, 'image/' ) ) {
		return 0;
	}

	/**
	 * 过滤默认特色图的附件 ID。
	 *
	 * @param int $id 附件 ID。
	 */
	return (int) apply_filters( 'aurora_star_default_thumbnail_id', $id );
}

/**
 * 取文章的特色图 ID，没有则回退到默认特色图。
 *
 * @param int|null $post_id 文章 ID，null 表示当前文章。
 * @return int
 */
function aurora_star_thumbnail_id( $post_id = null ) {
	$own = (int) get_post_thumbnail_id( $post_id );

	if ( $own > 0 ) {
		return $own;
	}

	return aurora_star_default_thumbnail_id();
}

/**
 * 特色图的 HTML（含默认图回退）。
 *
 * @param string   $size    图片尺寸。
 * @param array    $attr    img 属性。
 * @param int|null $post_id 文章 ID。
 * @return string 没有可用图片时返回空串
 */
function aurora_star_thumbnail_html( $size = 'aurora-star-card', $attr = array(), $post_id = null ) {
	$id = aurora_star_thumbnail_id( $post_id );

	if ( $id <= 0 ) {
		return '';
	}

	return wp_get_attachment_image( $id, $size, false, $attr );
}

/**
 * 列表卡片是否显示缩略图。
 *
 * @param int|null $post_id 文章 ID。
 * @return bool
 */
function aurora_star_should_show_card_media( $post_id = null ) {
	if ( get_post_thumbnail_id( $post_id ) ) {
		return true;
	}

	return aurora_star_default_thumbnail_id() > 0
		&& (bool) get_theme_mod( 'aurora_star_default_thumbnail_in_list', true );
}

/**
 * 文章 / 页面顶部是否显示特色大图。
 *
 * 同时受「文章内显示特色图片」与「默认特色图用于文章页」两个开关约束：
 * 文章自己的特色图只看前者，回退到默认图时两个都要满足。
 *
 * @param int|null $post_id 文章 ID。
 * @return bool
 */
function aurora_star_should_show_hero( $post_id = null ) {
	if ( ! get_theme_mod( 'aurora_star_show_thumbnail', true ) ) {
		return false;
	}

	if ( get_post_thumbnail_id( $post_id ) ) {
		return true;
	}

	return aurora_star_default_thumbnail_id() > 0
		&& (bool) get_theme_mod( 'aurora_star_default_thumbnail_in_single', true );
}
