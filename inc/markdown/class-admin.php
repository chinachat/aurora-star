<?php
/**
 * 后台菜单、页面与资源加载。
 *
 * @package Mdp
 */

defined( 'ABSPATH' ) || exit;

if ( class_exists( 'Mdp_Admin', false ) ) {
	return;
}

/**
 * 后台模块。
 */
class Mdp_Admin {

	/**
	 * 插件实例。
	 *
	 * @var Mdp_Plugin
	 */
	protected $plugin;

	/**
	 * 构造。
	 *
	 * @param Mdp_Plugin $plugin 插件实例。
	 */
	public function __construct( $plugin ) {
		$this->plugin = $plugin;

		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
		add_action( 'admin_notices', array( $this, 'notices' ) );
		add_action( 'admin_post_mdp_save_settings', array( $this, 'handle_save_settings' ) );
		add_filter( 'display_post_states', array( $this, 'post_states' ), 10, 2 );
	}

	/**
	 * 菜单。
	 *
	 * @return void
	 */
	public function menu() {
		$cap = 'edit_posts';

		add_menu_page(
			__( 'Markdown 发布器', 'wp-markdown-publisher' ),
			__( 'Markdown 发布', 'wp-markdown-publisher' ),
			$cap,
			'mdp-import',
			array( $this, 'render_import_page' ),
			'dashicons-editor-code',
			58
		);

		add_submenu_page(
			'mdp-import',
			__( '导入 Markdown', 'wp-markdown-publisher' ),
			__( '导入 Markdown', 'wp-markdown-publisher' ),
			$cap,
			'mdp-import',
			array( $this, 'render_import_page' )
		);

		add_submenu_page(
			'mdp-import',
			__( '批量重新渲染', 'wp-markdown-publisher' ),
			__( '批量重新渲染', 'wp-markdown-publisher' ),
			'manage_options',
			'mdp-tools',
			array( $this, 'render_tools_page' )
		);

		add_submenu_page(
			'mdp-import',
			__( '设置', 'wp-markdown-publisher' ),
			__( '设置', 'wp-markdown-publisher' ),
			'manage_options',
			'mdp-settings',
			array( $this, 'render_settings_page' )
		);

		add_submenu_page(
			'mdp-import',
			__( '语法与说明', 'wp-markdown-publisher' ),
			__( '语法与说明', 'wp-markdown-publisher' ),
			$cap,
			'mdp-help',
			array( $this, 'render_help_page' )
		);
	}

	/**
	 * 注册设置。
	 *
	 * @return void
	 */
	public function register_settings() {
		register_setting(
			'mdp_settings_group',
			MDP_OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize_settings' ),
				'default'           => Mdp_Plugin::defaults(),
			)
		);
	}

	/**
	 * 设置清理。
	 *
	 * @param mixed $input 输入。
	 * @return array
	 */
	public function sanitize_settings( $input ) {
		$defaults = Mdp_Plugin::defaults();
		$output   = array();
		$input    = is_array( $input ) ? $input : array();

		$bools = array( 'allow_html', 'sanitize_urls', 'tables', 'tasklists', 'footnotes', 'heading_ids', 'autolink', 'hard_wrap', 'auto_title', 'strip_first_h1', 'auto_excerpt', 'disable_wpautop', 'frontend_css', 'kses_output', 'import_taxonomy', 'import_excerpt', 'import_date', 'delete_data' );
		foreach ( $bools as $key ) {
			$output[ $key ] = empty( $input[ $key ] ) ? 0 : 1;
		}

		$types = isset( $input['post_types'] ) ? (array) $input['post_types'] : array();
		$types = array_values( array_filter( array_map( 'sanitize_key', $types ) ) );
		$types = array_values( array_filter( $types, 'post_type_exists' ) );
		$output['post_types'] = $types;

		$output['toc_min']       = min( 6, max( 1, (int) ( isset( $input['toc_min'] ) ? $input['toc_min'] : $defaults['toc_min'] ) ) );
		$output['toc_max']       = min( 6, max( $output['toc_min'], (int) ( isset( $input['toc_max'] ) ? $input['toc_max'] : $defaults['toc_max'] ) ) );
		$output['toc_title']     = sanitize_text_field( isset( $input['toc_title'] ) ? $input['toc_title'] : $defaults['toc_title'] );

		$output['import_post_type'] = sanitize_key( isset( $input['import_post_type'] ) ? $input['import_post_type'] : 'post' );
		if ( ! post_type_exists( $output['import_post_type'] ) ) {
			$output['import_post_type'] = 'post';
		}

		$status = isset( $input['import_status'] ) ? sanitize_key( $input['import_status'] ) : 'draft';
		if ( ! in_array( $status, array( 'draft', 'publish', 'pending', 'private' ), true ) ) {
			$status = 'draft';
		}
		$output['import_status'] = $status;

		$duplicate = isset( $input['import_duplicate'] ) ? sanitize_key( $input['import_duplicate'] ) : 'skip';
		if ( ! in_array( $duplicate, array( 'skip', 'update', 'new' ), true ) ) {
			$duplicate = 'skip';
		}
		$output['import_duplicate'] = $duplicate;

		$output['import_author'] = (int) ( isset( $input['import_author'] ) ? $input['import_author'] : 0 );

		return array_merge( $defaults, $output );
	}

	/**
	 * 资源加载。
	 *
	 * @param string $hook 当前页面。
	 * @return void
	 */
	public function enqueue( $hook ) {
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$is_plugin_page = in_array( $page, array( 'mdp-import', 'mdp-tools', 'mdp-settings', 'mdp-help' ), true );
		$is_editor_page = in_array( $hook, array( 'post.php', 'post-new.php' ), true );

		if ( ! $is_plugin_page && ! $is_editor_page ) {
			return;
		}

		wp_enqueue_style( 'mdp-admin', MDP_URL . 'admin.css', array(), MDP_VERSION );

		$config = array(
			'restUrl'   => esc_url_raw( rest_url( Mdp_Rest::NS ) ),
			'nonce'     => wp_create_nonce( 'wp_rest' ),
			'postTypes' => $this->plugin->enabled_post_types(),
			'i18n'      => array(
				'previewFailed'  => __( '预览失败：', 'wp-markdown-publisher' ),
				'importing'      => __( '正在导入…', 'wp-markdown-publisher' ),
				'done'           => __( '导入完成。', 'wp-markdown-publisher' ),
				'confirmReplace' => __( '编辑框中已有内容，是否用文件内容覆盖？', 'wp-markdown-publisher' ),
				'confirmEnable'  => __( '启用 Markdown 模式后，保存时将由 Markdown 原文重新生成文章正文。当前正文已载入编辑框，确认启用？', 'wp-markdown-publisher' ),
				'noFile'         => __( '请选择文件。', 'wp-markdown-publisher' ),
				'rendering'      => __( '正在渲染…', 'wp-markdown-publisher' ),
				'renderDone'     => __( '渲染完成。', 'wp-markdown-publisher' ),
				'uploadFailed'   => __( '上传失败。', 'wp-markdown-publisher' ),
			),
		);

		if ( $is_plugin_page ) {
			wp_enqueue_script( 'mdp-import', MDP_URL . 'import.js', array(), MDP_VERSION, true );
			wp_localize_script( 'mdp-import', 'mdpAdmin', $config );
		}

		if ( $is_editor_page ) {
			wp_enqueue_script( 'mdp-editor', MDP_URL . 'editor.js', array(), MDP_VERSION, true );
			wp_localize_script( 'mdp-editor', 'mdpEditor', $config );
		}
	}

	/**
	 * 提示信息。
	 *
	 * @return void
	 */
	public function notices() {
		$notice = get_transient( 'mdp_notice_' . get_current_user_id() );
		if ( ! $notice || ! is_array( $notice ) ) {
			return;
		}
		delete_transient( 'mdp_notice_' . get_current_user_id() );

		$type = isset( $notice['type'] ) ? $notice['type'] : 'success';
		printf(
			'<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>',
			esc_attr( $type ),
			esc_html( isset( $notice['message'] ) ? $notice['message'] : '' )
		);
	}

	/**
	 * 列表页显示 Markdown 标记。
	 *
	 * @param array   $states 状态。
	 * @param WP_Post $post   文章。
	 * @return array
	 */
	public function post_states( $states, $post ) {
		if ( $this->plugin->is_markdown_post( $post ) ) {
			$states['mdp'] = __( 'Markdown', 'wp-markdown-publisher' );
		}
		return $states;
	}

	/**
	 * 处理设置表单提交。
	 *
	 * @return void
	 */
	public function handle_save_settings() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( '权限不足。', 'wp-markdown-publisher' ) );
		}
		check_admin_referer( 'mdp_save_settings' );

		$input  = isset( $_POST['mdp'] ) ? wp_unslash( $_POST['mdp'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$saved  = $this->sanitize_settings( $input );
		$this->plugin->save_options( $saved );

		set_transient(
			'mdp_notice_' . get_current_user_id(),
			array(
				'type'    => 'success',
				'message' => __( '设置已保存。', 'wp-markdown-publisher' ),
			),
			60
		);

		wp_safe_redirect( admin_url( 'admin.php?page=mdp-settings&updated=1' ) );
		exit;
	}

	/**
	 * 导入页面。
	 *
	 * @return void
	 */
	public function render_import_page() {
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_die( esc_html__( '权限不足。', 'wp-markdown-publisher' ) );
		}
		$this->view( 'import' );
	}

	/**
	 * 批量渲染页面。
	 *
	 * @return void
	 */
	public function render_tools_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( '权限不足。', 'wp-markdown-publisher' ) );
		}
		$this->view( 'tools' );
	}

	/**
	 * 设置页面。
	 *
	 * @return void
	 */
	public function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( '权限不足。', 'wp-markdown-publisher' ) );
		}
		$this->view( 'settings' );
	}

	/**
	 * 帮助页面。
	 *
	 * @return void
	 */
	public function render_help_page() {
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_die( esc_html__( '权限不足。', 'wp-markdown-publisher' ) );
		}
		$this->view( 'help' );
	}

	/**
	 * 载入视图。
	 *
	 * @param string $name 视图名。
	 * @return void
	 */
	protected function view( $name ) {
		$file = MDP_DIR . 'views/' . $name . '.php';
		if ( ! is_file( $file ) ) {
			return;
		}
		$plugin = $this->plugin;
		include $file;
	}
}
