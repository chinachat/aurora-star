<?php
/**
 * 主题设置选项（WordPress 自定义器）。
 *
 * @package Aurora Star
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * 自定义器面板与设置。
 *
 * @param WP_Customize_Manager $wp_customize 自定义器管理器。
 */
function aurora_star_customize_register( $wp_customize ) {

	// ---------- 主题选项 ----------
	$wp_customize->add_panel(
		'aurora_star_panel',
		array(
			'title'    => __( 'Aurora Star 主题设置', 'aurora-star' ),
			'priority' => 20,
		)
	);

	// ========== 外观：颜色 ==========
	$wp_customize->add_section(
		'aurora_star_colors',
		array(
			'title' => __( '颜色', 'aurora-star' ),
			'panel' => 'aurora_star_panel',
		)
	);

	$wp_customize->add_setting(
		'aurora_star_primary_color',
		array(
			'default'           => '#6366f1',
			'sanitize_callback' => 'sanitize_hex_color',
		)
	);
	$wp_customize->add_control(
		new WP_Customize_Color_Control(
			$wp_customize,
			'aurora_star_primary_color',
			array(
				'label'   => __( '主题主色', 'aurora-star' ),
				'section' => 'aurora_star_colors',
			)
		)
	);

	$wp_customize->add_setting(
		'aurora_star_link_color',
		array(
			'default'           => '#4f46e5',
			'sanitize_callback' => 'sanitize_hex_color',
		)
	);
	$wp_customize->add_control(
		new WP_Customize_Color_Control(
			$wp_customize,
			'aurora_star_link_color',
			array(
				'label'   => __( '链接颜色', 'aurora-star' ),
				'section' => 'aurora_star_colors',
			)
		)
	);

	// ========== 暗黑模式 ==========
	$wp_customize->add_section(
		'aurora_star_dark',
		array(
			'title' => __( '暗黑模式', 'aurora-star' ),
			'panel' => 'aurora_star_panel',
		)
	);

	$wp_customize->add_setting(
		'aurora_star_dark_default',
		array(
			'default'           => 'system',
			'sanitize_callback' => 'aurora_star_sanitize_dark_default',
		)
	);
	$wp_customize->add_control(
		'aurora_star_dark_default',
		array(
			'label'   => __( '默认模式', 'aurora-star' ),
			'section' => 'aurora_star_dark',
			'type'    => 'select',
			'choices' => array(
				'system' => __( '跟随系统', 'aurora-star' ),
				'light'  => __( '明亮', 'aurora-star' ),
				'dark'   => __( '暗黑', 'aurora-star' ),
			),
		)
	);

	$wp_customize->add_setting(
		'aurora_star_dark_toggle',
		array(
			'default'           => true,
			'sanitize_callback' => 'wp_validate_boolean',
		)
	);
	$wp_customize->add_control(
		'aurora_star_dark_toggle',
		array(
			'label'   => __( '显示切换按钮', 'aurora-star' ),
			'section' => 'aurora_star_dark',
			'type'    => 'checkbox',
		)
	);

	$wp_customize->add_setting(
		'aurora_star_dark_default_for_late',
		array(
			'default'           => true,
			'sanitize_callback' => 'wp_validate_boolean',
		)
	);
	$wp_customize->add_control(
		'aurora_star_dark_default_for_late',
		array(
			'label'       => __( '按时间段自动切换', 'aurora-star' ),
			'description' => __( '夜间（20:00 - 次日 6:00）自动启用暗黑模式。', 'aurora-star' ),
			'section'     => 'aurora_star_dark',
			'type'        => 'checkbox',
		)
	);

	// ========== 文章：目录 ==========
	$wp_customize->add_section(
		'aurora_star_toc',
		array(
			'title' => __( '文章目录', 'aurora-star' ),
			'panel' => 'aurora_star_panel',
		)
	);

	$wp_customize->add_setting(
		'aurora_star_toc_enable',
		array(
			'default'           => true,
			'sanitize_callback' => 'wp_validate_boolean',
		)
	);
	$wp_customize->add_control(
		'aurora_star_toc_enable',
		array(
			'label'   => __( '启用文章浮动目录', 'aurora-star' ),
			'section' => 'aurora_star_toc',
			'type'    => 'checkbox',
		)
	);

	$wp_customize->add_setting(
		'aurora_star_toc_depth',
		array(
			'default'           => 3,
			'sanitize_callback' => 'absint',
		)
	);
	$wp_customize->add_control(
		'aurora_star_toc_depth',
		array(
			'label'       => __( '目录层级深度', 'aurora-star' ),
			'description' => __( '1-6，数字越大包含的标题层级越多。', 'aurora-star' ),
			'section'     => 'aurora_star_toc',
			'type'        => 'number',
			'input_attrs' => array( 'min' => 1, 'max' => 6, 'step' => 1 ),
		)
	);

	$wp_customize->add_setting(
		'aurora_star_toc_word_min',
		array(
			'default'           => 300,
			'sanitize_callback' => 'absint',
		)
	);
	$wp_customize->add_control(
		'aurora_star_toc_word_min',
		array(
			'label'       => __( '最少字数显示目录', 'aurora-star' ),
			'description' => __( '文章字数少于该值时不显示目录。', 'aurora-star' ),
			'section'     => 'aurora_star_toc',
			'type'        => 'number',
			'input_attrs' => array( 'min' => 0, 'max' => 5000, 'step' => 50 ),
		)
	);

	// ========== 代码高亮 ==========
	$wp_customize->add_section(
		'aurora_star_highlight',
		array(
			'title' => __( '代码高亮', 'aurora-star' ),
			'panel' => 'aurora_star_panel',
		)
	);

	$wp_customize->add_setting(
		'aurora_star_highlight',
		array(
			'default'           => true,
			'sanitize_callback' => 'wp_validate_boolean',
		)
	);
	$wp_customize->add_control(
		'aurora_star_highlight',
		array(
			'label'   => __( '启用代码高亮（Prism）', 'aurora-star' ),
			'section' => 'aurora_star_highlight',
			'type'    => 'checkbox',
		)
	);

	$wp_customize->add_setting(
		'aurora_star_highlight_theme',
		array(
			'default'           => 'okaidia',
			'sanitize_callback' => 'aurora_star_sanitize_highlight_theme',
		)
	);
	$wp_customize->add_control(
		'aurora_star_highlight_theme',
		array(
			'label'   => __( '高亮主题', 'aurora-star' ),
			'section' => 'aurora_star_highlight',
			'type'    => 'select',
			'choices' => array(
				'okaidia' => __( 'Okaidia（暗色）', 'aurora-star' ),
			),
		)
	);

	// ========== 图片灯箱 ==========
	$wp_customize->add_section(
		'aurora_star_lightbox',
		array(
			'title' => __( '图片灯箱', 'aurora-star' ),
			'panel' => 'aurora_star_panel',
		)
	);

	$wp_customize->add_setting(
		'aurora_star_lightbox',
		array(
			'default'           => true,
			'sanitize_callback' => 'wp_validate_boolean',
		)
	);
	$wp_customize->add_control(
		'aurora_star_lightbox',
		array(
			'label'   => __( '启用图片点击放大', 'aurora-star' ),
			'section' => 'aurora_star_lightbox',
			'type'    => 'checkbox',
		)
	);

	$wp_customize->add_setting(
		'aurora_star_lightbox_zoom',
		array(
			'default'           => true,
			'sanitize_callback' => 'wp_validate_boolean',
		)
	);
	$wp_customize->add_control(
		'aurora_star_lightbox_zoom',
		array(
			'label'   => __( '允许缩放与旋转', 'aurora-star' ),
			'section' => 'aurora_star_lightbox',
			'type'    => 'checkbox',
		)
	);

	// ========== 文章 ==========
	$wp_customize->add_section(
		'aurora_star_post',
		array(
			'title' => __( '文章', 'aurora-star' ),
			'panel' => 'aurora_star_panel',
		)
	);

	$wp_customize->add_setting(
		'aurora_star_show_thumbnail',
		array(
			'default'           => true,
			'sanitize_callback' => 'wp_validate_boolean',
		)
	);
	$wp_customize->add_control(
		'aurora_star_show_thumbnail',
		array(
			'label'   => __( '文章内显示特色图片', 'aurora-star' ),
			'description' => __( '单篇文章/页面顶部是否显示特色大图。', 'aurora-star' ),
			'section' => 'aurora_star_post',
			'type'    => 'checkbox',
		)
	);

	// ========== 页脚 ==========
	$wp_customize->add_section(
		'aurora_star_footer',
		array(
			'title' => __( '页脚', 'aurora-star' ),
			'panel' => 'aurora_star_panel',
		)
	);

	$wp_customize->add_setting(
		'aurora_star_footer_text',
		array(
			'default'           => '',
			'sanitize_callback' => 'wp_kses_post',
		)
	);
	$wp_customize->add_control(
		'aurora_star_footer_text',
		array(
			'label'   => __( '页脚版权文案', 'aurora-star' ),
			'section' => 'aurora_star_footer',
			'type'    => 'textarea',
		)
	);

	// 备案信息（为空则不显示）。
	$wp_customize->add_setting(
		'aurora_star_icp',
		array(
			'default'           => '',
			'sanitize_callback' => 'sanitize_text_field',
		)
	);
	$wp_customize->add_control(
		'aurora_star_icp',
		array(
			'label'       => __( 'ICP 备案号', 'aurora-star' ),
			'description' => __( '如：京ICP备00000000号。留空则不显示。', 'aurora-star' ),
			'section'     => 'aurora_star_footer',
			'type'        => 'text',
		)
	);

	$wp_customize->add_setting(
		'aurora_star_icp_link',
		array(
			'default'           => 'https://beian.miit.gov.cn/',
			'sanitize_callback' => 'esc_url_raw',
		)
	);
	$wp_customize->add_control(
		'aurora_star_icp_link',
		array(
			'label'   => __( 'ICP 备案链接', 'aurora-star' ),
			'section' => 'aurora_star_footer',
			'type'    => 'url',
		)
	);

	$wp_customize->add_setting(
		'aurora_star_police',
		array(
			'default'           => '',
			'sanitize_callback' => 'sanitize_text_field',
		)
	);
	$wp_customize->add_control(
		'aurora_star_police',
		array(
			'label'       => __( '公安备案号', 'aurora-star' ),
			'description' => __( '如：京公网安备 11000000000000 号。留空则不显示。', 'aurora-star' ),
			'section'     => 'aurora_star_footer',
			'type'        => 'text',
		)
	);

	$wp_customize->add_setting(
		'aurora_star_police_link',
		array(
			'default'           => '',
			'sanitize_callback' => 'esc_url_raw',
		)
	);
	$wp_customize->add_control(
		'aurora_star_police_link',
		array(
			'label'   => __( '公安备案链接', 'aurora-star' ),
			'section' => 'aurora_star_footer',
			'type'    => 'url',
		)
	);

	// ========== 背景 ==========
	$wp_customize->add_section(
		'aurora_star_background',
		array(
			'title' => __( '背景', 'aurora-star' ),
			'panel' => 'aurora_star_panel',
		)
	);

	$wp_customize->add_setting(
		'aurora_star_bg_enable',
		array(
			'default'           => true,
			'sanitize_callback' => 'wp_validate_boolean',
		)
	);
	$wp_customize->add_control(
		'aurora_star_bg_enable',
		array(
			'label'   => __( '启用极光背景图', 'aurora-star' ),
			'description' => __( '浅色主题显示浅色极光，暗色主题显示暗色极光。', 'aurora-star' ),
			'section' => 'aurora_star_background',
			'type'    => 'checkbox',
		)
	);

	// 输出动态 CSS。
	$wp_customize->selective_refresh->add_partial(
		'aurora_star_footer_text',
		array(
			'selector'        => '.site-footer__copyright',
			'render_callback' => 'aurora_star_footer_copyright',
		)
	);
}
add_action( 'customize_register', 'aurora_star_customize_register' );

/**
 * 校验暗黑默认值。
 *
 * @param string $input 输入值。
 * @return string
 */
function aurora_star_sanitize_dark_default( $input ) {
	return in_array( $input, array( 'system', 'light', 'dark' ), true ) ? $input : 'system';
}

/**
 * 校验高亮主题。
 *
 * @param string $input 输入值。
 * @return string
 */
function aurora_star_sanitize_highlight_theme( $input ) {
	return in_array( $input, array( 'okaidia' ), true ) ? $input : 'okaidia';
}

/**
 * 页脚版权（含备案信息，为空不显示）。
 *
 * @return string
 */
function aurora_star_footer_copyright() {
	$text = get_theme_mod( 'aurora_star_footer_text', '' );
	if ( empty( $text ) ) {
		$text = sprintf(
			/* translators: %s: 年份。 */
			__( '© %s %s', 'aurora-star' ),
			date_i18n( 'Y' ),
			get_bloginfo( 'name' )
		);
	}

	// 备案信息。
	$links = array();

	$icp = get_theme_mod( 'aurora_star_icp', '' );
	if ( $icp ) {
		$icp_url = get_theme_mod( 'aurora_star_icp_link', 'https://beian.miit.gov.cn/' );
		$links[] = '<a href="' . esc_url( $icp_url ) . '" target="_blank" rel="nofollow">' . esc_html( $icp ) . '</a>';
	}

	$police = get_theme_mod( 'aurora_star_police', '' );
	if ( $police ) {
		$police_url = get_theme_mod( 'aurora_star_police_link', '' );
		$badge_url  = get_template_directory_uri() . '/assets/img/police-logo.png';
		$badge_html = '<img class="aurora-police-logo" src="' . esc_url( $badge_url ) . '" alt="' . esc_attr__( '公安备案', 'aurora-star' ) . '" /> ';
		if ( $police_url ) {
			$links[] = '<a class="aurora-police" href="' . esc_url( $police_url ) . '" target="_blank" rel="nofollow">' . $badge_html . esc_html( $police ) . '</a>';
		} else {
			$links[] = '<span class="aurora-police">' . $badge_html . esc_html( $police ) . '</span>';
		}
	}

	if ( ! empty( $links ) ) {
		$text .= ' <span class="site-footer__sep">·</span> ' . implode( ' <span class="site-footer__sep">·</span> ', $links );
	}

	return $text;
}

/**
 * 输出主题动态 CSS（主色 + 极光背景）。
 */
function aurora_star_dynamic_css() {
	$primary = get_theme_mod( 'aurora_star_primary_color', '#6366f1' );
	$link    = get_theme_mod( 'aurora_star_link_color', '#4f46e5' );

	$css = ':root{--aurora-star-primary:' . $primary . ';--aurora-star-link:' . $link . ';}';

	if ( get_theme_mod( 'aurora_star_bg_enable', true ) ) {
		$bg_url = get_template_directory_uri() . '/assets/img/bg-aurora-light.svg';
		$css   .= 'body{background-image:url("' . $bg_url . '");background-size:cover;background-attachment:fixed;background-position:center;background-repeat:no-repeat;}';
		$css   .= 'html[data-theme="dark"] body{background-image:url("' . get_template_directory_uri() . '/assets/img/bg-aurora-dark.svg");}';
	}

	printf(
		'<style id="aurora-star-inline-css">%1$s</style>',
		$css // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	);
}
add_action( 'wp_head', 'aurora_star_dynamic_css', 20 );
