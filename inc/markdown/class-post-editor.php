<?php
/**
 * 文章编辑器：Markdown 元框、保存时渲染、编辑器接管、批量重新渲染。
 *
 * @package Mdp
 */

defined( 'ABSPATH' ) || exit;

if ( class_exists( 'Mdp_Post_Editor', false ) ) {
	return;
}

/**
 * 文章编辑模块。
 */
class Mdp_Post_Editor {

	/**
	 * 插件实例。
	 *
	 * @var Mdp_Plugin
	 */
	protected $plugin;

	/**
	 * 本次请求中已经渲染好的结果。
	 *
	 * @var array|null
	 */
	protected $pending = null;

	/**
	 * 是否需要在本次保存中放行 kses。
	 *
	 * @var bool
	 */
	protected $suppress_kses = false;

	/**
	 * 是否已把核心 kses 过滤器临时移到优先级 11。
	 *
	 * @var bool
	 */
	protected $kses_moved = false;

	/**
	 * 是否已接管正文编辑器。
	 *
	 * @var bool
	 */
	protected $took_over = false;

	/**
	 * 构造。
	 *
	 * @param Mdp_Plugin $plugin 插件实例。
	 */
	public function __construct( $plugin ) {
		$this->plugin = $plugin;

		add_action( 'admin_init', array( $this, 'maybe_take_over_editor' ) );
		add_filter( 'use_block_editor_for_post', array( $this, 'filter_block_editor' ), 10, 2 );
		add_action( 'add_meta_boxes', array( $this, 'add_meta_box' ), 10, 2 );
		add_filter( 'wp_insert_post_data', array( $this, 'filter_post_data' ), 10, 2 );
		add_action( 'save_post', array( $this, 'save_post' ), 10, 3 );
		add_filter( 'content_save_pre', array( $this, 'maybe_suppress_kses' ), 9 );
		add_filter( 'wp_post_revision_meta_keys', array( $this, 'revision_meta_keys' ) );
		add_filter( 'plugin_action_links_' . MDP_BASENAME, array( $this, 'action_links' ) );

		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			$this->register_cli();
		}
	}

	/**
	 * 插件列表操作链接。
	 *
	 * @param array $links 链接。
	 * @return array
	 */
	public function action_links( $links ) {
		$extra = array(
			'<a href="' . esc_url( admin_url( 'admin.php?page=mdp-import' ) ) . '">' . esc_html__( '导入 Markdown', 'wp-markdown-publisher' ) . '</a>',
			'<a href="' . esc_url( admin_url( 'admin.php?page=mdp-settings' ) ) . '">' . esc_html__( '设置', 'wp-markdown-publisher' ) . '</a>',
		);
		return array_merge( $extra, $links );
	}

	// ---------------------------------------------------------------------
	// 编辑器接管。
	// ---------------------------------------------------------------------

	/**
	 * 当文章使用 Markdown 时移除正文编辑器（同时禁用区块编辑器）。
	 *
	 * @return void
	 */
	public function maybe_take_over_editor() {
		if ( ! is_admin() ) {
			return;
		}

		global $pagenow;
		if ( ! in_array( (string) $pagenow, array( 'post.php', 'post-new.php' ), true ) ) {
			return;
		}

		$post_id   = $this->current_post_id();
		$post_type = '';

		if ( $post_id ) {
			$post_type = (string) get_post_type( $post_id );
		} elseif ( isset( $_GET['post_type'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$post_type = sanitize_key( wp_unslash( $_GET['post_type'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		} else {
			$post_type = 'post';
		}

		if ( '' === $post_type || ! $this->plugin->is_enabled_type( $post_type ) ) {
			return;
		}

		if ( $post_id ) {
			if ( ! $this->plugin->is_markdown_post( $post_id ) ) {
				return;
			}
		} elseif ( ! $this->plugin->is_enabled_type( $post_type ) ) {
			return;
		}

		remove_post_type_support( $post_type, 'editor' );
		$this->took_over = true;
	}

	/**
	 * 当前请求编辑的文章 ID。
	 *
	 * @return int
	 */
	protected function current_post_id() {
		if ( isset( $_GET['post'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return (int) $_GET['post']; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		}
		if ( isset( $_POST['post_ID'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			return (int) $_POST['post_ID']; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		}
		return 0;
	}

	/**
	 * 关闭区块编辑器。
	 *
	 * @param bool    $use_block_editor 是否使用。
	 * @param WP_Post $post            文章。
	 * @return bool
	 */
	public function filter_block_editor( $use_block_editor, $post ) {
		if ( $post instanceof WP_Post && $this->plugin->is_markdown_post( $post ) ) {
			return false;
		}
		return $use_block_editor;
	}

	// ---------------------------------------------------------------------
	// 元框。
	// ---------------------------------------------------------------------

	/**
	 * 注册元框。
	 *
	 * @param string  $post_type 类型。
	 * @param WP_Post $post      文章。
	 * @return void
	 */
	public function add_meta_box( $post_type, $post ) {
		if ( ! $this->plugin->is_enabled_type( $post_type ) ) {
			return;
		}
		if ( $post instanceof WP_Post && ! current_user_can( 'edit_post', $post->ID ) ) {
			return;
		}

		add_meta_box(
			'mdp-editor',
			__( 'Markdown 编辑器', 'wp-markdown-publisher' ),
			array( $this, 'render_meta_box' ),
			$post_type,
			'normal',
			'high',
			array( 'post' => $post )
		);
	}

	/**
	 * 输出元框。
	 *
	 * @param WP_Post $post 文章。
	 * @return void
	 */
	public function render_meta_box( $post ) {
		$source  = $this->plugin->get_source( $post->ID );
		$flag    = get_post_meta( $post->ID, MDP_META_ENABLED, true );
		$enabled = $this->plugin->is_markdown_post( $post );

		$original = '';
		if ( '' === $source && '' !== trim( (string) $post->post_content ) ) {
			$original = (string) $post->post_content;
			if ( ! $enabled ) {
				$source = $original;
			}
		}

		$rendered_at = (int) get_post_meta( $post->ID, MDP_META_RENDERED, true );
		$parser      = (string) get_post_meta( $post->ID, MDP_META_PARSER, true );
		$stale       = ( $enabled && '' !== $source && $parser !== Mdp_Markdown::VERSION );

		$words = $this->plugin->parser()->wordCount( $source );
		?>
		<div class="mdp-editor" id="mdp-editor" data-enabled="<?php echo $enabled ? '1' : '0'; ?>">
			<?php wp_nonce_field( 'mdp_save', 'mdp_nonce' ); ?>
			<input type="hidden" name="mdp_original" value="<?php echo esc_attr( $original ); ?>" />

			<div class="mdp-editor__bar">
				<label class="mdp-switch">
					<input type="checkbox" name="mdp_enabled" id="mdp-enabled" value="1" <?php checked( $enabled ); ?> />
					<span><?php esc_html_e( '用 Markdown 编辑此文章', 'wp-markdown-publisher' ); ?></span>
				</label>

				<span class="mdp-editor__spacer"></span>

				<span class="mdp-editor__stat" id="mdp-stats">
					<?php
					printf(
						/* translators: 1: 字数 2: 标题数 */
						esc_html__( '约 %1$s 字', 'wp-markdown-publisher' ),
						esc_html( number_format_i18n( $words ) )
					);
					?>
				</span>
				<button type="button" class="button button-small" id="mdp-toggle-preview"><?php esc_html_e( '预览', 'wp-markdown-publisher' ); ?></button>
				<button type="button" class="button button-small" id="mdp-toggle-fullscreen"><?php esc_html_e( '全屏', 'wp-markdown-publisher' ); ?></button>
			</div>

			<div class="mdp-toolbar" id="mdp-toolbar">
				<button type="button" class="button button-small" data-mdp-action="h1" title="H1">H1</button>
				<button type="button" class="button button-small" data-mdp-action="h2" title="H2">H2</button>
				<button type="button" class="button button-small" data-mdp-action="h3" title="H3">H3</button>
				<span class="mdp-toolbar__sep"></span>
				<button type="button" class="button button-small" data-mdp-action="bold" title="加粗"><strong>B</strong></button>
				<button type="button" class="button button-small" data-mdp-action="italic" title="斜体"><em>I</em></button>
				<button type="button" class="button button-small" data-mdp-action="strike" title="删除线"><del>S</del></button>
				<button type="button" class="button button-small" data-mdp-action="code" title="行内代码">&lt;/&gt;</button>
				<span class="mdp-toolbar__sep"></span>
				<button type="button" class="button button-small" data-mdp-action="link" title="链接"><?php esc_html_e( '链接', 'wp-markdown-publisher' ); ?></button>
				<button type="button" class="button button-small" data-mdp-action="image" title="图片"><?php esc_html_e( '图片', 'wp-markdown-publisher' ); ?></button>
				<button type="button" class="button button-small" data-mdp-action="quote" title="引用"><?php esc_html_e( '引用', 'wp-markdown-publisher' ); ?></button>
				<span class="mdp-toolbar__sep"></span>
				<button type="button" class="button button-small" data-mdp-action="ul" title="无序列表"><?php esc_html_e( '列表', 'wp-markdown-publisher' ); ?></button>
				<button type="button" class="button button-small" data-mdp-action="ol" title="有序列表">1.</button>
				<button type="button" class="button button-small" data-mdp-action="task" title="任务列表">☑</button>
				<span class="mdp-toolbar__sep"></span>
				<button type="button" class="button button-small" data-mdp-action="table" title="表格"><?php esc_html_e( '表格', 'wp-markdown-publisher' ); ?></button>
				<button type="button" class="button button-small" data-mdp-action="codeblock" title="代码块">{ }</button>
				<button type="button" class="button button-small" data-mdp-action="hr" title="分隔线">—</button>
				<button type="button" class="button button-small" data-mdp-action="toc" title="目录">TOC</button>
				<button type="button" class="button button-small" data-mdp-action="footnote" title="脚注">[^]</button>
			</div>

			<?php if ( $this->took_over ) : ?>
				<input type="hidden" name="content" value="" />
			<?php endif; ?>

			<textarea id="mdp-source" name="mdp_markdown" class="mdp-source" rows="18" spellcheck="false"
				placeholder="<?php esc_attr_e( '在这里用 Markdown 撰写正文，或把 .md 文件拖进来…', 'wp-markdown-publisher' ); ?>"
				<?php echo $enabled ? '' : 'disabled="disabled"'; ?>><?php echo esc_textarea( $source ); ?></textarea>

			<div class="mdp-preview" id="mdp-preview" hidden>
				<div class="mdp-preview__head"><?php esc_html_e( '实时预览', 'wp-markdown-publisher' ); ?></div>
				<div class="mdp-preview__body" id="mdp-preview-body"></div>
			</div>

			<div class="mdp-editor__foot">
				<span class="mdp-hint">
					<?php esc_html_e( '支持拖放 .md 文件；Ctrl/⌘ + B 加粗、Ctrl/⌘ + I 斜体、Tab 缩进。', 'wp-markdown-publisher' ); ?>
				</span>
				<span class="mdp-editor__state">
					<?php if ( $rendered_at ) : ?>
						<?php
						printf(
							/* translators: %s: 时间 */
							esc_html__( '上次渲染：%s', 'wp-markdown-publisher' ),
							esc_html( date_i18n( 'Y-m-d H:i', $rendered_at ) )
						);
						?>
					<?php endif; ?>
					<?php if ( $stale ) : ?>
						<em class="mdp-stale"><?php esc_html_e( '解析器已更新，建议重新保存或重新渲染此文章。', 'wp-markdown-publisher' ); ?></em>
					<?php endif; ?>
				</span>
			</div>
		</div>
		<?php
	}

	// ---------------------------------------------------------------------
	// 保存。
	// ---------------------------------------------------------------------

	/**
	 * 是否来自本插件的表单提交。
	 *
	 * @return bool
	 */
	protected function is_our_save() {
		if ( empty( $_POST['mdp_nonce'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			return false;
		}
		$nonce = sanitize_text_field( wp_unslash( $_POST['mdp_nonce'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		return (bool) wp_verify_nonce( $nonce, 'mdp_save' );
	}

	/**
	 * 保存前把 Markdown 渲染进 post_content。
	 *
	 * @param array $data    文章数据。
	 * @param array $postarr 原始数据。
	 * @return array
	 */
	public function filter_post_data( $data, $postarr ) {
		$this->pending = null;

		if ( ! $this->is_our_save() || ! isset( $_POST['mdp_markdown'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			return $data;
		}

		$post_type   = isset( $data['post_type'] ) ? (string) $data['post_type'] : 'post';
		$is_revision = ( 'revision' === $post_type );
		$post_id     = isset( $postarr['ID'] ) ? (int) $postarr['ID'] : 0;

		if ( $is_revision ) {
			$parent = isset( $postarr['post_parent'] ) ? (int) $postarr['post_parent'] : 0;
			$post_id = 0;
			if ( $parent ) {
				$post_type = (string) get_post_type( $parent );
			}
		}

		if ( ! $this->plugin->is_enabled_type( $post_type ) ) {
			return $data;
		}

		if ( $post_id ) {
			if ( ! current_user_can( 'edit_post', $post_id ) ) {
				return $data;
			}
		} else {
			$pto = get_post_type_object( $post_type );
			if ( $pto && ! current_user_can( $pto->cap->edit_posts ) ) {
				return $data;
			}
		}

		$raw     = $this->plugin->sanitize_source( wp_unslash( $_POST['mdp_markdown'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$enabled = ! empty( $_POST['mdp_enabled'] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing

		if ( ! $enabled ) {
			// 用户关闭了 Markdown 模式：不触碰正文。
			$this->pending = array(
				'raw'    => $raw,
				'enabled' => false,
			);
			return $data;
		}

		$current_content = isset( $data['post_content'] ) ? (string) $data['post_content'] : '';

		if ( '' === trim( $raw ) && '' !== trim( $current_content ) && ! $is_revision ) {
			// 原文为空且已有正文：为避免误删正文，保持不变。
			$this->pending = array(
				'raw'     => $raw,
				'enabled' => true,
				'skip'    => true,
			);
			return $data;
		}

		$info   = $this->extract_title( $raw, isset( $data['post_title'] ) ? (string) $data['post_title'] : '' );
		$source = $info['source'];
		if ( '' !== $info['title'] ) {
			$data['post_title'] = $info['title'];
		}
		$result = $this->plugin->render( $source );
		$this->pending = array(
			'raw'     => $raw,
			'source'  => $source,
			'result'  => $result,
			'enabled' => true,
		);

		$data['post_content'] = $this->sanitize_for_save( $result['html'] );

		if ( ! empty( $this->plugin->option( 'auto_excerpt' ) ) && '' === trim( (string) $data['post_excerpt'] ) ) {
			$excerpt = $this->excerpt_from_html( $result['html'] );
			if ( '' !== $excerpt ) {
				$data['post_excerpt'] = $excerpt;
			}
		}

		return $data;
	}

	/**
	 * 保存元数据。
	 *
	 * @param int     $post_id 文章 ID。
	 * @param WP_Post $post    文章。
	 * @param bool    $update  是否更新。
	 * @return void
	 */
	public function save_post( $post_id, $post, $update ) {
		if ( ! $this->is_our_save() || ! isset( $_POST['mdp_markdown'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			return;
		}
		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}
		if ( ! $post instanceof WP_Post || ! $this->plugin->is_enabled_type( $post->post_type ) ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$raw  = $this->plugin->sanitize_source( wp_unslash( $_POST['mdp_markdown'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$flag = ! empty( $_POST['mdp_enabled'] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing

		if ( ! $flag ) {
			update_post_meta( $post_id, MDP_META_ENABLED, '0' );
			return;
		}

		if ( is_array( $this->pending ) && isset( $this->pending['raw'] ) && $this->pending['raw'] === $raw ) {
			if ( ! empty( $this->pending['skip'] ) ) {
				$this->suppress_kses = false;
				return;
			}
			update_post_meta( $post_id, MDP_META_ENABLED, '1' );
			if ( isset( $this->pending['source'], $this->pending['result'] ) ) {
				$this->store_result( $post_id, $this->pending['source'], $this->pending['result'] );
			}
			$this->suppress_kses = false;
			return;
		}

		$info   = $this->extract_title( $raw, $post->post_title );
		$source = $info['source'];
		$result = $this->plugin->render( $source );
		update_post_meta( $post_id, MDP_META_ENABLED, '1' );
		$this->store_result( $post_id, $source, $result );
	}

	/**
	 * 写入渲染结果相关元数据。
	 *
	 * @param int    $post_id 文章 ID。
	 * @param string $source  原文。
	 * @param array  $result  渲染结果。
	 * @return void
	 */
	protected function store_result( $post_id, $source, $result ) {
		update_post_meta( $post_id, MDP_META_SOURCE, $source );
		update_post_meta( $post_id, MDP_META_TOC, isset( $result['toc_html'] ) ? $result['toc_html'] : '' );
		update_post_meta( $post_id, MDP_META_RENDERED, time() );
		update_post_meta( $post_id, MDP_META_PARSER, Mdp_Markdown::VERSION );
	}

	/**
	 * 把 Markdown 渲染并写入文章（导入、批量重渲染使用）。
	 *
	 * @param int    $post_id 文章 ID。
	 * @param string $source  原文。
	 * @param array  $args    { update_content: bool }
	 * @return array
	 */
	public function render_post( $post_id, $source, $args = array() ) {
		$args   = array_merge( array( 'update_content' => true ), $args );
		$result = $this->plugin->render( $source );

		if ( ! empty( $args['update_content'] ) ) {
			$content = $this->sanitize_for_save( $result['html'] );
			$post    = get_post( $post_id );

			if ( $post ) {
				remove_filter( 'wp_insert_post_data', array( $this, 'filter_post_data' ), 10 );
				wp_update_post(
					array(
						'ID'           => $post_id,
						'post_content' => $content,
					)
				);
				add_filter( 'wp_insert_post_data', array( $this, 'filter_post_data' ), 10, 2 );

				if ( ! empty( $this->plugin->option( 'auto_excerpt' ) ) && '' === trim( (string) $post->post_excerpt ) ) {
					$excerpt = $this->excerpt_from_html( $result['html'] );
					if ( '' !== $excerpt ) {
						remove_filter( 'wp_insert_post_data', array( $this, 'filter_post_data' ), 10 );
						wp_update_post(
							array(
								'ID'           => $post_id,
								'post_excerpt' => $excerpt,
							)
						);
						add_filter( 'wp_insert_post_data', array( $this, 'filter_post_data' ), 10, 2 );
					}
				}
			}
		}

		$this->store_result( $post_id, $source, $result );

		return $result;
	}

	/**
	 * 需要放入修订版本的元键。
	 *
	 * @param array $keys 键。
	 * @return array
	 */
	public function revision_meta_keys( $keys ) {
		$keys[] = MDP_META_SOURCE;
		$keys[] = MDP_META_ENABLED;
		return $keys;
	}

	/**
	 * kses 放行控制。
	 *
	 * 注意：这里只是把核心的 wp_filter_post_kses 从优先级 10 **挪到 11**，
	 * 并没有移除它——两个优先级的过滤器在同一次 content_save_pre 里都会执行，
	 * 核心 kses 始终生效。此处**不是**放行开关，真正的过滤发生在
	 * sanitize_for_save() 里（无 unfiltered_html 权限时走 $plugin->kses()）。
	 * 保留它只是为了兼容历史行为，改动前请先确认这一点。
	 *
	 * @param string $content 内容。
	 * @return string
	 */
	public function maybe_suppress_kses( $content ) {
		if ( ! function_exists( 'wp_filter_post_kses' ) ) {
			return $content;
		}

		if ( $this->suppress_kses ) {
			// 我们已经用扩展白名单过滤过，本次保存不再让核心 kses 再过滤一遍。
			remove_filter( 'content_save_pre', 'wp_filter_post_kses', 10 );
			add_filter( 'content_save_pre', 'wp_filter_post_kses', 11 );
			$this->kses_moved = true;
		} elseif ( $this->kses_moved ) {
			remove_filter( 'content_save_pre', 'wp_filter_post_kses', 11 );
			if ( ! has_filter( 'content_save_pre', 'wp_filter_post_kses' ) ) {
				add_filter( 'content_save_pre', 'wp_filter_post_kses', 10 );
			}
			$this->kses_moved = false;
		}

		return $content;
	}

	/**
	 * 按当前用户权限过滤/标记 HTML。
	 *
	 * @param string $html HTML。
	 * @return string
	 */
	protected function sanitize_for_save( $html ) {
		if ( current_user_can( 'unfiltered_html' ) && empty( $this->plugin->option( 'kses_output' ) ) ) {
			return $html;
		}
		$this->suppress_kses = true;
		return $this->plugin->kses( $html );
	}

	/**
	 * 自动提取标题，并可移除首个 H1。
	 *
	 * @param string $source      原文。
	 * @param string $current_title 当前标题。
	 * @return array { title, source }
	 */
	protected function extract_title( $source, $current_title = '' ) {
		$out = array(
			'title'  => '',
			'source' => $source,
		);

		if ( empty( $this->plugin->option( 'auto_title' ) ) ) {
			return $out;
		}

		if ( '' !== trim( (string) $current_title ) ) {
			return $out;
		}

		$trimmed = ltrim( $source, "\n" );
		if ( ! preg_match( '/^ {0,3}#[ \t]+(.+?)[ \t]*#*[ \t]*\n/', $trimmed . "\n", $m ) ) {
			return $out;
		}

		$out['title'] = $this->plugin->parser()->plainText( $m[1] );

		if ( ! empty( $this->plugin->option( 'strip_first_h1' ) ) ) {
			$out['source'] = ltrim( (string) preg_replace( '/^[ \t]*#[ \t]+.+?[ \t]*#*[ \t]*\n/', '', $trimmed, 1 ), "\n" );
		}

		return $out;
	}

	/**
	 * 从 HTML 生成摘要。
	 *
	 * @param string $html HTML。
	 * @return string
	 */
	protected function excerpt_from_html( $html ) {
		$text = wp_strip_all_tags( (string) $html );
		$text = preg_replace( '/\s+/u', ' ', $text );
		$text = trim( (string) $text );
		if ( '' === $text ) {
			return '';
		}
		return wp_trim_words( $text, 80, '…' );
	}

	// ---------------------------------------------------------------------
	// 批量重新渲染。
	// ---------------------------------------------------------------------

	/**
	 * 分批重新渲染。
	 *
	 * @param array $args { post_types, page, per_page }
	 * @return array { processed, total, done, next_page }
	 */
	public function rerender_batch( $args = array() ) {
		$args = array_merge(
			array(
				'post_types' => $this->plugin->enabled_post_types(),
				'page'       => 1,
				'per_page'   => 20,
			),
			$args
		);

		$query = new WP_Query(
			array(
				'post_type'      => $args['post_types'],
				'post_status'    => array( 'publish', 'draft', 'pending', 'future', 'private' ),
				'posts_per_page' => max( 1, (int) $args['per_page'] ),
				'paged'          => max( 1, (int) $args['page'] ),
				'fields'         => 'ids',
				'orderby'        => 'ID',
				'order'          => 'ASC',
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					array(
						'key'     => MDP_META_SOURCE,
						'compare' => 'EXISTS',
					),
				),
			)
		);

		$processed = 0;
		foreach ( $query->posts as $post_id ) {
			$source = $this->plugin->get_source( $post_id );
			if ( '' === trim( $source ) ) {
				continue;
			}
			$this->render_post( $post_id, $source );
			$processed++;
		}

		$max = (int) $query->max_num_pages;

		return array(
			'processed' => $processed,
			'total'     => (int) $query->found_posts,
			'page'      => (int) $args['page'],
			'max_pages' => $max,
			'done'      => ( (int) $args['page'] >= $max ),
		);
	}

	/**
	 * 注册 WP-CLI 命令。
	 *
	 * @return void
	 */
	protected function register_cli() {
		WP_CLI::add_command(
			'mdp rerender',
			function ( $args, $assoc ) {
				$types = isset( $assoc['post_type'] ) ? explode( ',', $assoc['post_type'] ) : $this->plugin->enabled_post_types();
				$page  = 1;
				$sum   = 0;

				do {
					$result = $this->rerender_batch(
						array(
							'post_types' => $types,
							'page'       => $page,
							'per_page'   => 50,
						)
					);
					$sum  += $result['processed'];
					WP_CLI::log( sprintf( '第 %d 批：渲染 %d 篇，共 %d 篇', $page, $result['processed'], $result['total'] ) );
					$page++;
				} while ( ! $result['done'] && $page <= 200 );

				WP_CLI::success( sprintf( '完成，共重新渲染 %d 篇文章。', $sum ) );
			}
		);

		WP_CLI::add_command(
			'mdp import',
			function ( $args, $assoc ) {
				$path = isset( $args[0] ) ? $args[0] : '';
				if ( '' === $path || ! file_exists( $path ) ) {
					WP_CLI::error( '请指定存在的 .md 文件或包含 .md 的目录。' );
				}
				$options = array(
					'post_type' => isset( $assoc['post_type'] ) ? $assoc['post_type'] : null,
					'status'    => isset( $assoc['status'] ) ? $assoc['status'] : null,
				);
				$results = $this->plugin->importer->import_path( $path, $options );
				foreach ( $results as $row ) {
					WP_CLI::log( sprintf( '[%s] %s', $row['status'], $row['message'] ) );
				}
				WP_CLI::success( sprintf( '共处理 %d 个文件。', count( $results ) ) );
			}
		);
	}
}
