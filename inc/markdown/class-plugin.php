<?php
/**
 * 插件核心：选项、解析器配置、渲染管线。
 *
 * @package Mdp
 */

defined( 'ABSPATH' ) || exit;

if ( class_exists( 'Mdp_Plugin', false ) ) {
	return;
}

/**
 * 插件主类。
 */
class Mdp_Plugin {

	/**
	 * 单例。
	 *
	 * @var Mdp_Plugin|null
	 */
	protected static $instance = null;

	/**
	 * 选项缓存。
	 *
	 * @var array|null
	 */
	protected $options = null;

	/**
	 * 子模块。
	 *
	 * @var Mdp_Post_Editor
	 */
	public $editor;

	/**
	 * @var Mdp_Importer
	 */
	public $importer;

	/**
	 * @var Mdp_Admin
	 */
	public $admin;

	/**
	 * @var Mdp_Frontend
	 */
	public $frontend;

	/**
	 * @var Mdp_Shortcodes
	 */
	public $shortcodes;

	/**
	 * @var Mdp_Rest
	 */
	public $rest;

	/**
	 * 取得单例。
	 *
	 * @return Mdp_Plugin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * 构造。
	 */
	protected function __construct() {
		$this->editor     = new Mdp_Post_Editor( $this );
		$this->importer   = new Mdp_Importer( $this );
		$this->shortcodes = new Mdp_Shortcodes( $this );
		$this->frontend   = new Mdp_Frontend( $this );
		$this->rest       = new Mdp_Rest( $this );
		$this->admin      = new Mdp_Admin( $this );

		add_action( 'init', array( $this, 'on_init' ) );
	}

	/**
	 * 初始化：注册文章元数据、加载语言包。
	 *
	 * @return void
	 */
	public function on_init() {
		// 本模块随主题分发，翻译文件在 inc/markdown/languages/ 下。
		// load_plugin_textdomain() 会把相对路径拼到 WP_PLUGIN_DIR 上，在主题里用不了，
		// 因此直接用 load_textdomain() 指定绝对路径；文件不存在时它只返回 false。
		$mo = MDP_DIR . 'languages/' . determine_locale() . '.mo';
		if ( is_readable( $mo ) ) {
			load_textdomain( 'wp-markdown-publisher', $mo );
		}

		$auth  = array( $this, 'can_edit_post_meta' );
		$types = $this->enabled_post_types();
		if ( empty( $types ) ) {
			$types = array( 'post' );
		}

		foreach ( $types as $post_type ) {
			if ( ! post_type_exists( $post_type ) ) {
				continue;
			}

			register_post_meta(
				$post_type,
				MDP_META_SOURCE,
				array(
					'type'              => 'string',
					'single'            => true,
					'show_in_rest'      => true,
					'auth_callback'     => $auth,
					'sanitize_callback' => array( $this, 'sanitize_source' ),
				)
			);

			register_post_meta(
				$post_type,
				MDP_META_TOC,
				array(
					'type'          => 'string',
					'single'        => true,
					'show_in_rest'  => true,
					'auth_callback' => $auth,
				)
			);

			register_post_meta(
				$post_type,
				MDP_META_ENABLED,
				array(
					'type'          => 'string',
					'single'        => true,
					'show_in_rest'  => true,
					'auth_callback' => $auth,
				)
			);
		}
	}

	/**
	 * 元数据权限回调。
	 *
	 * @param bool   $allowed 是否允许。
	 * @param string $meta_key 键。
	 * @param int    $post_id 文章 ID。
	 * @return bool
	 */
	public function can_edit_post_meta( $allowed, $meta_key, $post_id ) {
		return current_user_can( 'edit_post', $post_id );
	}

	/**
	 * Markdown 原文的清理：保留原文，仅去掉非法控制字符。
	 *
	 * @param string $value 值。
	 * @return string
	 */
	public function sanitize_source( $value ) {
		$value = (string) $value;
		return preg_replace( '/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $value );
	}

	/**
	 * 默认选项。
	 *
	 * @return array
	 */
	public static function defaults() {
		return array(
			'post_types'      => array( 'post' ),
			'allow_html'      => 1,
			'sanitize_urls'   => 1,
			'tables'          => 1,
			'tasklists'       => 1,
			'footnotes'       => 1,
			'heading_ids'     => 1,
			'autolink'        => 1,
			'hard_wrap'       => 0,
			'toc_min'         => 1,
			'toc_max'         => 4,
			'toc_title'       => '目录',
			'auto_title'      => 1,
			'strip_first_h1'  => 1,
			'auto_excerpt'    => 1,
			'disable_wpautop' => 1,
			'frontend_css'    => 1,
			'kses_output'     => 0,
			'import_post_type' => 'post',
			'import_status'   => 'draft',
			'import_author'   => 0,
			'import_duplicate' => 'skip',
			'import_taxonomy' => 1,
			'import_excerpt'  => 1,
			'import_date'     => 1,
			'delete_data'     => 0,
		);
	}

	/**
	 * 读取全部选项。
	 *
	 * @return array
	 */
	public function options() {
		if ( null === $this->options ) {
			$saved         = get_option( MDP_OPTION, array() );
			$this->options = array_merge( self::defaults(), is_array( $saved ) ? $saved : array() );
		}
		return $this->options;
	}

	/**
	 * 读取单个选项。
	 *
	 * @param string $key     键。
	 * @param mixed  $default 默认值。
	 * @return mixed
	 */
	public function option( $key, $default = null ) {
		$options = $this->options();
		if ( ! array_key_exists( $key, $options ) ) {
			return $default;
		}
		return $options[ $key ];
	}

	/**
	 * 保存选项。
	 *
	 * @param array $options 选项。
	 * @return void
	 */
	public function save_options( $options ) {
		$this->options = array_merge( self::defaults(), $options );
		update_option( MDP_OPTION, $this->options );
	}

	/**
	 * 允许使用 Markdown 编辑器的文章类型。
	 *
	 * @return array
	 */
	public function enabled_post_types() {
		$types = $this->option( 'post_types', array( 'post' ) );
		if ( ! is_array( $types ) ) {
			$types = array( $types );
		}
		$types = array_values( array_filter( array_map( 'strval', $types ) ) );
		return apply_filters( 'mdp_enabled_post_types', $types );
	}

	/**
	 * 该文章类型是否启用 Markdown。
	 *
	 * @param string $post_type 类型。
	 * @return bool
	 */
	public function is_enabled_type( $post_type ) {
		return in_array( $post_type, $this->enabled_post_types(), true );
	}

	/**
	 * 创建按当前设置配置好的解析器。
	 *
	 * @param array $args 覆盖参数。
	 * @return Mdp_Markdown
	 */
	public function parser( $args = array() ) {
		$o = $this->options();

		$options = array(
			'allow_html'    => ! empty( $o['allow_html'] ),
			'sanitize_urls' => ! empty( $o['sanitize_urls'] ),
			'tables'        => ! empty( $o['tables'] ),
			'tasklists'     => ! empty( $o['tasklists'] ),
			'footnotes'     => ! empty( $o['footnotes'] ),
			'heading_ids'   => ! empty( $o['heading_ids'] ),
			'autolink'      => ! empty( $o['autolink'] ),
			'breaks'        => ! empty( $o['hard_wrap'] ),
			'toc_min'       => (int) $o['toc_min'],
			'toc_max'       => (int) $o['toc_max'],
			'toc_title'     => (string) $o['toc_title'],
		);

		$options = array_merge( $options, $args );

		return new Mdp_Markdown( apply_filters( 'mdp_parser_options', $options ) );
	}

	/**
	 * 渲染 Markdown。
	 *
	 * @param string $markdown 原文。
	 * @param array  $args     覆盖参数。
	 * @return array
	 */
	public function render( $markdown, $args = array() ) {
		$result = $this->parser( $args )->parse( $markdown );

		if ( ! empty( $this->options()['kses_output'] ) ) {
			$result['html'] = $this->kses( $result['html'] );
		}

		return apply_filters( 'mdp_render_result', $result, $markdown, $args );
	}

	/**
	 * 取出 Markdown 原文。
	 *
	 * @param int $post_id 文章 ID。
	 * @return string
	 */
	public function get_source( $post_id ) {
		$source = get_post_meta( $post_id, MDP_META_SOURCE, true );
		return is_string( $source ) ? $source : '';
	}

	/**
	 * 该文章是否为 Markdown 模式。
	 *
	 * @param int|WP_Post|null $post 文章。
	 * @return bool
	 */
	public function is_markdown_post( $post = null ) {
		$post = get_post( $post );
		if ( ! $post ) {
			return false;
		}

		if ( 'revision' === $post->post_type ) {
			$post = get_post( $post->post_parent );
			if ( ! $post ) {
				return false;
			}
		}

		if ( ! $this->is_enabled_type( $post->post_type ) ) {
			return false;
		}

		$flag = get_post_meta( $post->ID, MDP_META_ENABLED, true );

		if ( '1' === $flag ) {
			return true;
		}
		if ( '0' === $flag ) {
			return false;
		}

		// 未标记：新建（自动草稿）文章默认进入 Markdown 模式，已有文章保持原样。
		return ( 'auto-draft' === $post->post_status );
	}

	/**
	 * 使用 kses 过滤输出（保留插件需要的标签与属性）。
	 *
	 * @param string $html HTML。
	 * @return string
	 */
	public function kses( $html ) {
		if ( ! function_exists( 'wp_kses' ) ) {
			return $html;
		}

		$allowed = wp_kses_allowed_html( 'post' );

		$global = array(
			'class' => true,
			'id'    => true,
			'style' => true,
			'role'  => true,
		);

		foreach ( array( 'div', 'span', 'p', 'nav', 'ol', 'ul', 'li', 'table', 'thead', 'tbody', 'tr', 'th', 'td', 'pre', 'code', 'blockquote', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'a', 'img', 'hr', 'br', 'sup', 'sub', 'del', 'strong', 'em', 'input', 'label' ) as $tag ) {
			if ( ! isset( $allowed[ $tag ] ) ) {
				$allowed[ $tag ] = array();
			}
			$allowed[ $tag ] = array_merge( $allowed[ $tag ], $global );
		}

		$allowed['input'] = array_merge(
			isset( $allowed['input'] ) ? $allowed['input'] : array(),
			array(
				'type'     => true,
				'checked'  => true,
				'disabled' => true,
				'name'     => true,
				'value'    => true,
				'class'    => true,
				'id'       => true,
			)
		);

		$allowed['nav']['aria-label'] = true;
		$allowed['code']['data-lang'] = true;
		$allowed['code']['data-info'] = true;
		$allowed['sup']['data-id']    = true;
		$allowed['span']['data-id']   = true;
		$allowed['a']['target']       = true;
		$allowed['a']['rel']          = true;
		$allowed['a']['title']        = true;
		$allowed['img']['alt']        = true;
		$allowed['img']['title']      = true;
		$allowed['img']['width']      = true;
		$allowed['img']['height']     = true;
		$allowed['img']['srcset']     = true;
		$allowed['img']['loading']    = true;
		$allowed['th']['align']       = true;
		$allowed['td']['align']       = true;

		return wp_kses( $html, $allowed );
	}

	/**
	 * 所有可选文章类型。
	 *
	 * @return array
	 */
	public static function post_type_choices() {
		$types   = get_post_types( array( 'public' => true ), 'objects' );
		$choices = array();
		foreach ( $types as $type ) {
			if ( in_array( $type->name, array( 'attachment', 'revision', 'nav_menu_item' ), true ) ) {
				continue;
			}
			$choices[ $type->name ] = $type->labels->singular_name . ' (' . $type->name . ')';
		}
		return $choices;
	}

	/**
	 * 激活插件。
	 *
	 * @return void
	 */
	public static function on_activate() {
		$saved = get_option( MDP_OPTION, array() );
		if ( ! is_array( $saved ) ) {
			$saved = array();
		}
		update_option( MDP_OPTION, array_merge( self::defaults(), $saved ) );
		update_option( 'mdp_version', MDP_VERSION );
	}
}
