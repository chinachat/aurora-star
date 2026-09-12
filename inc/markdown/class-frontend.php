<?php
/**
 * 前台表现：wpautop 处理、样式、文章类名。
 *
 * @package Mdp
 */

defined( 'ABSPATH' ) || exit;

if ( class_exists( 'Mdp_Frontend', false ) ) {
	return;
}

/**
 * 前台模块。
 */
class Mdp_Frontend {

	/**
	 * 插件实例。
	 *
	 * @var Mdp_Plugin
	 */
	protected $plugin;

	/**
	 * 是否已经调整过 wpautop。
	 *
	 * @var bool
	 */
	protected $autop_moved = false;

	/**
	 * 构造。
	 *
	 * @param Mdp_Plugin $plugin 插件实例。
	 */
	public function __construct( $plugin ) {
		$this->plugin = $plugin;

		add_filter( 'the_content', array( $this, 'maybe_toggle_wpautop' ), 9 );
		add_filter( 'post_class', array( $this, 'post_class' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue' ) );
		add_filter( 'the_content', array( $this, 'maybe_render_source' ), 6 );
		// 每篇文章开始前还原 wpautop，避免同一页混合展示 Markdown 文章与普通文章时普通文章被漏处理。
		add_action( 'the_post', array( $this, 'restore_wpautop' ) );
		add_action( 'loop_end', array( $this, 'restore_wpautop' ) );
	}

	/**
	 * 兼容旧版本：正文为空但存在 Markdown 原文时即时渲染。
	 *
	 * @param string $content 内容。
	 * @return string
	 */
	public function maybe_render_source( $content ) {
		if ( '' !== trim( (string) $content ) || ! is_singular() ) {
			return $content;
		}

		$post = get_post();
		if ( ! $post || ! $this->plugin->is_markdown_post( $post ) ) {
			return $content;
		}

		$source = $this->plugin->get_source( $post->ID );
		if ( '' === trim( $source ) ) {
			return $content;
		}

		$result = $this->plugin->render( $source );
		return $result['html'];
	}

	/**
	 * Markdown 文章关闭 wpautop（渲染结果已经带好段落）。
	 *
	 * @param string $content 内容。
	 * @return string
	 */
	public function maybe_toggle_wpautop( $content ) {
		if ( empty( $this->plugin->option( 'disable_wpautop' ) ) ) {
			return $content;
		}

		$post    = get_post();
		$is_md   = ( $post && $this->plugin->is_markdown_post( $post ) );
		$has_raw = ( false !== strpos( (string) $content, '[markdown' ) || false !== strpos( (string) $content, '[md]' ) );

		if ( $is_md && ! $has_raw ) {
			remove_filter( 'the_content', 'wpautop', 10 );
			remove_filter( 'the_content', 'wpautop', 11 );
			add_filter( 'the_content', 'wpautop', 11 );
			$this->autop_moved = true;
		} else {
			$this->restore_wpautop();
		}

		return $content;
	}

	/**
	 * 还原 wpautop。
	 *
	 * @return void
	 */
	public function restore_wpautop() {
		if ( ! $this->autop_moved ) {
			return;
		}
		remove_filter( 'the_content', 'wpautop', 11 );
		if ( ! has_filter( 'the_content', 'wpautop' ) ) {
			add_filter( 'the_content', 'wpautop', 10 );
		}
		$this->autop_moved = false;
	}

	/**
	 * 给 Markdown 文章加类名。
	 *
	 * @param array $classes 类名。
	 * @return array
	 */
	public function post_class( $classes ) {
		$post = get_post();
		if ( $post && $this->plugin->is_markdown_post( $post ) ) {
			$classes[] = 'mdp-markdown-post';
		}
		return $classes;
	}

	/**
	 * 前台样式。
	 *
	 * @return void
	 */
	public function enqueue() {
		wp_register_style( 'mdp-frontend', MDP_URL . 'frontend.css', array(), MDP_VERSION );

		if ( empty( $this->plugin->option( 'frontend_css' ) ) ) {
			return;
		}

		if ( is_singular() ) {
			$post = get_post();
			if ( $post && $this->plugin->is_markdown_post( $post ) ) {
				wp_enqueue_style( 'mdp-frontend' );
				return;
			}
			if ( $post && false !== strpos( (string) $post->post_content, '[markdown' ) ) {
				wp_enqueue_style( 'mdp-frontend' );
			}
		}
	}
}
