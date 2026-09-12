<?php
/**
 * REST 接口：实时预览、文件导入、批量重新渲染。
 *
 * @package Mdp
 */

defined( 'ABSPATH' ) || exit;

if ( class_exists( 'Mdp_Rest', false ) ) {
	return;
}

/**
 * REST 模块。
 */
class Mdp_Rest {

	/**
	 * 命名空间。
	 */
	const NS = 'mdp/v1';

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
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * 注册路由。
	 *
	 * @return void
	 */
	public function register_routes() {
		register_rest_route(
			self::NS,
			'/preview',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'preview' ),
				'permission_callback' => array( $this, 'can_edit_posts' ),
				'args'                => array(
					'markdown' => array(
						'type'     => 'string',
						'required' => true,
					),
					'options'  => array(
						'type'    => 'object',
						'default' => array(),
					),
				),
			)
		);

		register_rest_route(
			self::NS,
			'/import',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'import' ),
				'permission_callback' => array( $this, 'can_import' ),
			)
		);

		register_rest_route(
			self::NS,
			'/rerender',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'rerender' ),
				'permission_callback' => array( $this, 'can_manage' ),
				'args'                => array(
					'page'       => array(
						'type'    => 'integer',
						'default' => 1,
					),
					'per_page'   => array(
						'type'    => 'integer',
						'default' => 20,
					),
					'post_types' => array(
						'type'    => 'array',
						'default' => array(),
					),
				),
			)
		);
	}

	/**
	 * 权限：可编辑文章。
	 *
	 * @return bool
	 */
	public function can_edit_posts() {
		return current_user_can( 'edit_posts' );
	}

	/**
	 * 权限：可导入文章。
	 *
	 * @return bool
	 */
	public function can_import() {
		return current_user_can( 'edit_posts' );
	}

	/**
	 * 权限：可管理批量操作。
	 *
	 * @return bool
	 */
	public function can_manage() {
		return current_user_can( 'manage_options' );
	}

	/**
	 * 预览。
	 *
	 * @param WP_REST_Request $request 请求。
	 * @return WP_REST_Response
	 */
	public function preview( $request ) {
		$markdown = (string) $request->get_param( 'markdown' );
		$options  = $request->get_param( 'options' );
		$options  = is_array( $options ) ? $options : array();

		$allowed = array( 'allow_html', 'tables', 'tasklists', 'footnotes', 'heading_ids', 'autolink', 'breaks', 'toc_min', 'toc_max' );
		$args    = array();
		foreach ( $allowed as $key ) {
			if ( array_key_exists( $key, $options ) ) {
				$args[ $key ] = $options[ $key ];
			}
		}

		$result = $this->plugin->render( $markdown, $args );

		return rest_ensure_response(
			array(
				'html'     => $result['html'],
				'toc_html' => $result['toc_html'],
				'headings' => $result['headings'],
				'words'    => $result['words'],
			)
		);
	}

	/**
	 * 导入。
	 *
	 * @param WP_REST_Request $request 请求。
	 * @return WP_REST_Response|WP_Error
	 */
	public function import( $request ) {
		$options = array(
			'post_type' => $this->clean_string( $request->get_param( 'post_type' ) ),
			'status'    => $this->clean_string( $request->get_param( 'status' ) ),
			'duplicate' => $this->clean_string( $request->get_param( 'duplicate' ) ),
			'author'    => $request->get_param( 'author' ) ? (int) $request->get_param( 'author' ) : 0,
			'taxonomy'  => $this->parse_taxonomy( $request->get_param( 'taxonomy' ) ),
		);

		$results = array();

		// 1) 上传的文件（支持多文件）。
		$files = $request->get_file_params();

		if ( ! empty( $files['file'] ) && is_array( $files['file'] ) && isset( $files['file']['tmp_name'] ) ) {
			$file = $files['file'];
			if ( is_array( $file['tmp_name'] ) ) {
				$count = count( $file['tmp_name'] );
				for ( $i = 0; $i < $count; $i++ ) {
					$single = array(
						'name'     => $file['name'][ $i ],
						'type'     => $file['type'][ $i ],
						'tmp_name' => $file['tmp_name'][ $i ],
						'error'    => $file['error'][ $i ],
						'size'     => $file['size'][ $i ],
					);
					$results = array_merge( $results, $this->import_upload( $single, $options ) );
				}
			} else {
				$results = array_merge( $results, $this->import_upload( $file, $options ) );
			}
		}

		// 2) 直接提交内容。
		$content = $request->get_param( 'content' );
		if ( is_string( $content ) && '' !== trim( $content ) ) {
			$results[] = $this->plugin->importer->import_file(
				$content,
				$this->clean_string( $request->get_param( 'filename' ) ),
				$options
			);
		}

		if ( empty( $results ) ) {
			return new WP_Error(
				'mdp_nothing_to_import',
				__( '没有收到可导入的内容，请选择 .md 文件或粘贴 Markdown 文本。', 'wp-markdown-publisher' ),
				array( 'status' => 400 )
			);
		}

		return rest_ensure_response( array( 'results' => $results ) );
	}

	/**
	 * 处理上传文件。
	 *
	 * @param array $file    文件数组。
	 * @param array $options 选项。
	 * @return array
	 */
	protected function import_upload( $file, $options ) {
		$name = isset( $file['name'] ) ? sanitize_file_name( $file['name'] ) : '';

		if ( ! empty( $file['error'] ) ) {
			return array(
				array(
					'status'  => 'error',
					'file'    => $name,
					'message' => $this->upload_error_message( (int) $file['error'] ),
				),
			);
		}

		$is_zip = ( 'zip' === strtolower( pathinfo( $name, PATHINFO_EXTENSION ) ) );

		if ( ! Mdp_Importer::is_markdown_file( $name ) && ! $is_zip ) {
			return array(
				array(
					'status'  => 'error',
					'file'    => $name,
					'message' => __( '只支持 .md / .markdown / .txt / .zip 文件。', 'wp-markdown-publisher' ),
				),
			);
		}

		if ( empty( $file['tmp_name'] ) || ! is_uploaded_file( $file['tmp_name'] ) ) {
			return array(
				array(
					'status'  => 'error',
					'file'    => $name,
					'message' => __( '文件上传失败。', 'wp-markdown-publisher' ),
				),
			);
		}

		if ( $is_zip ) {
			return $this->plugin->importer->import_zip( $file['tmp_name'], $options );
		}

		$content = file_get_contents( $file['tmp_name'] ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		if ( false === $content ) {
			return array(
				array(
					'status'  => 'error',
					'file'    => $name,
					'message' => __( '无法读取上传的文件。', 'wp-markdown-publisher' ),
				),
			);
		}

		return array( $this->plugin->importer->import_file( $content, $name, $options ) );
	}

	/**
	 * 上传错误信息。
	 *
	 * @param int $code 错误码。
	 * @return string
	 */
	protected function upload_error_message( $code ) {
		switch ( $code ) {
			case UPLOAD_ERR_INI_SIZE:
			case UPLOAD_ERR_FORM_SIZE:
				return __( '文件超过服务器允许的上传大小。', 'wp-markdown-publisher' );
			case UPLOAD_ERR_PARTIAL:
				return __( '文件只上传了一部分。', 'wp-markdown-publisher' );
			case UPLOAD_ERR_NO_FILE:
				return __( '没有选择文件。', 'wp-markdown-publisher' );
			default:
				return __( '上传出错。', 'wp-markdown-publisher' );
		}
	}

	/**
	 * 批量重新渲染。
	 *
	 * @param WP_REST_Request $request 请求。
	 * @return WP_REST_Response
	 */
	public function rerender( $request ) {
		$types = $request->get_param( 'post_types' );
		$types = is_array( $types ) ? array_filter( array_map( 'sanitize_key', $types ) ) : array();

		if ( empty( $types ) ) {
			$types = $this->plugin->enabled_post_types();
		}

		$result = $this->plugin->editor->rerender_batch(
			array(
				'post_types' => $types,
				'page'       => max( 1, (int) $request->get_param( 'page' ) ),
				'per_page'   => min( 100, max( 1, (int) $request->get_param( 'per_page' ) ) ),
			)
		);

		return rest_ensure_response( $result );
	}

	/**
	 * 清理字符串参数。
	 *
	 * @param mixed $value 值。
	 * @return string
	 */
	protected function clean_string( $value ) {
		return is_string( $value ) ? sanitize_text_field( $value ) : '';
	}

	/**
	 * 解析分类参数。
	 *
	 * @param mixed $value 值。
	 * @return array
	 */
	protected function parse_taxonomy( $value ) {
		if ( ! is_array( $value ) ) {
			return array();
		}
		$out = array();
		foreach ( $value as $taxonomy => $terms ) {
			$taxonomy = sanitize_key( $taxonomy );
			if ( '' === $taxonomy ) {
				continue;
			}
			if ( is_string( $terms ) ) {
				$terms = array_filter( array_map( 'trim', explode( ',', $terms ) ) );
			}
			if ( ! is_array( $terms ) ) {
				continue;
			}
			$out[ $taxonomy ] = array_filter( array_map( 'sanitize_text_field', $terms ) );
		}
		return $out;
	}
}
