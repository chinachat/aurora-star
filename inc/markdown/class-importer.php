<?php
/**
 * Markdown 文件导入器。
 *
 * @package Mdp
 */

defined( 'ABSPATH' ) || exit;

if ( class_exists( 'Mdp_Importer', false ) ) {
	return;
}

/**
 * 导入单个/批量 Markdown 文件并生成文章。
 */
class Mdp_Importer {

	/**
	 * 插件实例。
	 *
	 * @var Mdp_Plugin
	 */
	protected $plugin;

	/**
	 * 允许的扩展名。
	 *
	 * @var array
	 */
	protected static $extensions = array( 'md', 'markdown', 'mdown', 'mkd', 'txt' );

	/**
	 * 单文件大小上限（字节）。
	 *
	 * @var int
	 */
	protected $max_size = 5242880;

	/**
	 * 构造。
	 *
	 * @param Mdp_Plugin $plugin 插件实例。
	 */
	public function __construct( $plugin ) {
		$this->plugin = $plugin;
	}

	/**
	 * 允许的扩展名。
	 *
	 * @return array
	 */
	public static function extensions() {
		return apply_filters( 'mdp_import_extensions', self::$extensions );
	}

	/**
	 * 判断文件名是否为 Markdown 文件。
	 *
	 * @param string $filename 文件名。
	 * @return bool
	 */
	public static function is_markdown_file( $filename ) {
		$ext = strtolower( pathinfo( $filename, PATHINFO_EXTENSION ) );
		return in_array( $ext, self::extensions(), true );
	}

	/**
	 * 导入磁盘上的路径（文件、目录或 zip）。
	 *
	 * @param string $path    路径。
	 * @param array  $options 选项。
	 * @return array 结果列表。
	 */
	public function import_path( $path, $options = array() ) {
		$path    = (string) $path;
		$results = array();

		if ( is_dir( $path ) ) {
			$files = array();
			foreach ( self::extensions() as $ext ) {
				$found = glob( rtrim( $path, '/\\' ) . DIRECTORY_SEPARATOR . '*.' . $ext );
				if ( $found ) {
					$files = array_merge( $files, $found );
				}
			}
			sort( $files );
			foreach ( $files as $file ) {
				$results[] = $this->import_file( file_get_contents( $file ), basename( $file ), $options );
			}
			return $results;
		}

		if ( ! is_file( $path ) ) {
			return array(
				array(
					'status'  => 'error',
					'file'    => $path,
					'message' => __( '文件不存在。', 'wp-markdown-publisher' ),
				),
			);
		}

		if ( 'zip' === strtolower( pathinfo( $path, PATHINFO_EXTENSION ) ) ) {
			return $this->import_zip( $path, $options );
		}

		return array( $this->import_file( file_get_contents( $path ), basename( $path ), $options ) );
	}

	/**
	 * 导入 ZIP。
	 *
	 * @param string $path    zip 路径。
	 * @param array  $options 选项。
	 * @return array
	 */
	public function import_zip( $path, $options = array() ) {
		$results = array();

		if ( ! class_exists( 'ZipArchive' ) ) {
			return array(
				array(
					'status'  => 'error',
					'file'    => basename( $path ),
					'message' => __( '服务器未启用 ZipArchive 扩展，无法解压 zip。', 'wp-markdown-publisher' ),
				),
			);
		}

		$zip = new ZipArchive();
		if ( true !== $zip->open( $path ) ) {
			return array(
				array(
					'status'  => 'error',
					'file'    => basename( $path ),
					'message' => __( '无法打开 zip 文件。', 'wp-markdown-publisher' ),
				),
			);
		}

		for ( $i = 0; $i < $zip->numFiles; $i++ ) {
			$name = $zip->getNameIndex( $i );
			if ( false === $name ) {
				continue;
			}
			$name = $this->fix_encoding( $name );

			if ( '/' === substr( $name, -1 ) || false !== strpos( $name, '__MACOSX' ) ) {
				continue;
			}
			if ( ! self::is_markdown_file( $name ) ) {
				continue;
			}

			$content = $zip->getFromIndex( $i );
			if ( false === $content ) {
				$results[] = array(
					'status'  => 'error',
					'file'    => $name,
					'message' => __( '读取压缩包内文件失败。', 'wp-markdown-publisher' ),
				);
				continue;
			}

			$results[] = $this->import_file( $content, basename( $name ), $options );
		}

		$zip->close();

		if ( empty( $results ) ) {
			$results[] = array(
				'status'  => 'error',
				'file'    => basename( $path ),
				'message' => __( '压缩包内没有找到 Markdown 文件。', 'wp-markdown-publisher' ),
			);
		}

		return $results;
	}

	/**
	 * 修正非 UTF-8 文件名（常见于 Windows 压缩的中文 zip）。
	 *
	 * @param string $name 文件名。
	 * @return string
	 */
	protected function fix_encoding( $name ) {
		if ( function_exists( 'mb_check_encoding' ) && ! mb_check_encoding( $name, 'UTF-8' ) ) {
			$converted = @mb_convert_encoding( $name, 'UTF-8', 'GBK' ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			if ( is_string( $converted ) && '' !== $converted ) {
				return $converted;
			}
		}
		return $name;
	}

	/**
	 * 导入一段 Markdown 文本。
	 *
	 * @param string $content 内容。
	 * @param string $filename 文件名。
	 * @param array  $options 选项。
	 * @return array 结果。
	 */
	public function import_file( $content, $filename = '', $options = array() ) {
		$options = array_merge(
			array(
				'post_type'  => null,
				'status'     => null,
				'author'     => null,
				'duplicate'  => null,
				'taxonomy'   => null,
				'markdown'   => true,
			),
			$options
		);

		$content = (string) $content;

		if ( strlen( $content ) > $this->max_size ) {
			return $this->error( $filename, __( '文件过大（超过 5MB）。', 'wp-markdown-publisher' ) );
		}

		if ( '' === trim( $content ) ) {
			return $this->error( $filename, __( '文件内容为空。', 'wp-markdown-publisher' ) );
		}

		// GBK 等非 UTF-8 编码的文件统一转换，避免标题与正文乱码。
		$content = Mdp_Markdown::ensure_utf8( $content );
		$filename = Mdp_Markdown::ensure_utf8( $filename );

		$split = Mdp_Front_Matter::split( $content );
		$meta  = $split['meta'];
		$body  = $split['body'];

		if ( '' === trim( $body ) ) {
			$body = $content;
		}

		// 文章类型。
		$post_type = $this->pick_post_type( $meta, $options );

		// 状态。
		$status = $this->pick_status( $meta, $options );

		// 标题。
		$title = $this->pick_title( $meta, $body, $filename );

		// slug。
		$slug = '';
		foreach ( array( 'slug', 'permalink', 'url_name' ) as $key ) {
			if ( ! empty( $meta[ $key ] ) && is_string( $meta[ $key ] ) ) {
				$slug = sanitize_title( $meta[ $key ] );
				break;
			}
		}

		// 日期。
		$date = '';
		if ( ! empty( $this->plugin->option( 'import_date' ) ) ) {
			foreach ( array( 'date', 'published_at', 'updated', 'created' ) as $key ) {
				if ( ! empty( $meta[ $key ] ) && is_string( $meta[ $key ] ) ) {
					$ts = strtotime( $meta[ $key ] );
					if ( $ts ) {
						$date = gmdate( 'Y-m-d H:i:s', $ts + ( (int) get_option( 'gmt_offset' ) * HOUR_IN_SECONDS ) );
						break;
					}
				}
			}
		}

		// 摘要。
		$excerpt = '';
		if ( ! empty( $this->plugin->option( 'import_excerpt' ) ) ) {
			foreach ( array( 'excerpt', 'description', 'summary', 'abstract' ) as $key ) {
				if ( ! empty( $meta[ $key ] ) && is_string( $meta[ $key ] ) ) {
					$excerpt = $meta[ $key ];
					break;
				}
			}
		}

		// 作者。
		$author = $this->pick_author( $meta, $options );

		// 重复处理。
		$duplicate = $options['duplicate'] ? $options['duplicate'] : $this->plugin->option( 'import_duplicate', 'skip' );
		$existing  = null;
		if ( '' === $slug ) {
			$slug = sanitize_title( $title );
		}
		if ( '' !== $slug && 'new' !== $duplicate ) {
			$existing = get_page_by_path( $slug, OBJECT, $post_type );
			if ( $existing && ! $existing instanceof WP_Post ) {
				$existing = null;
			}
		}

		if ( $existing && 'skip' === $duplicate ) {
			return array(
				'status'  => 'skipped',
				'file'    => $filename,
				'title'   => $existing->post_title,
				'post_id' => $existing->ID,
				'message' => sprintf(
					/* translators: %s: 文章标题 */
					__( '已存在同名文章「%s」，已跳过。', 'wp-markdown-publisher' ),
					$existing->post_title
				),
			);
		}

		$data = array(
			'post_title'   => $title,
			'post_content' => '',
			'post_status'  => $status,
			'post_type'    => $post_type,
			'post_excerpt' => $excerpt,
		);

		if ( '' !== $slug ) {
			$data['post_name'] = $slug;
		}
		if ( '' !== $date ) {
			$data['post_date'] = $date;
		}
		if ( $author ) {
			$data['post_author'] = $author;
		}

		$updated = false;

		if ( $existing && 'update' === $duplicate ) {
			$data['ID'] = $existing->ID;
			$post_id    = wp_update_post( wp_slash( $data ), true );
			$updated    = true;
		} else {
			$post_id = wp_insert_post( wp_slash( $data ), true );
		}

		if ( is_wp_error( $post_id ) ) {
			return $this->error( $filename, $post_id->get_error_message() );
		}

		$post_id = (int) $post_id;

		// 分类/标签。
		if ( $options['taxonomy'] ) {
			$this->apply_terms( $post_id, $options['taxonomy'] );
		} else {
			$this->apply_front_matter_terms( $post_id, $meta, $post_type );
		}

		// 特色图片。
		if ( ! empty( $meta['featured_image'] ) && is_string( $meta['featured_image'] ) ) {
			$this->apply_featured_image( $post_id, $meta['featured_image'] );
		} elseif ( ! empty( $meta['image'] ) && is_string( $meta['image'] ) && preg_match( '#^https?://#i', $meta['image'] ) ) {
			$this->apply_featured_image( $post_id, $meta['image'] );
		}

		// 渲染并写入正文。
		if ( ! empty( $options['markdown'] ) ) {
			update_post_meta( $post_id, MDP_META_ENABLED, '1' );
			$this->plugin->editor->render_post( $post_id, $body );
		}

		// 自定义字段（front matter 中的 meta: 以 meta_xxx 形式给出）。
		foreach ( $meta as $key => $value ) {
			if ( 0 === strpos( $key, 'meta_' ) && is_scalar( $value ) ) {
				update_post_meta( $post_id, substr( $key, 5 ), $value );
			}
		}

		$message = $updated
			? __( '已更新文章。', 'wp-markdown-publisher' )
			: __( '已创建文章。', 'wp-markdown-publisher' );

		return array(
			'status'  => $updated ? 'updated' : 'created',
			'file'    => $filename,
			'title'   => get_the_title( $post_id ),
			'post_id' => $post_id,
			'message' => $message,
			'edit'    => get_edit_post_link( $post_id, 'raw' ),
			'view'    => get_permalink( $post_id ),
		);
	}

	/**
	 * 构造错误结果。
	 *
	 * @param string $filename 文件。
	 * @param string $message  信息。
	 * @return array
	 */
	protected function error( $filename, $message ) {
		return array(
			'status'  => 'error',
			'file'    => $filename,
			'message' => $message,
		);
	}

	/**
	 * 决定文章类型。
	 *
	 * @param array $meta    front matter。
	 * @param array $options 选项。
	 * @return string
	 */
	protected function pick_post_type( $meta, $options ) {
		$candidates = array();

		if ( ! empty( $options['post_type'] ) ) {
			$candidates[] = $options['post_type'];
		}
		foreach ( array( 'post_type', 'type' ) as $key ) {
			if ( ! empty( $meta[ $key ] ) && is_string( $meta[ $key ] ) ) {
				$candidates[] = $meta[ $key ];
			}
		}
		$candidates[] = $this->plugin->option( 'import_post_type', 'post' );

		foreach ( $candidates as $candidate ) {
			$candidate = sanitize_key( $candidate );
			$object    = get_post_type_object( $candidate );
			if ( $object && ! empty( $object->public ) ) {
				return $candidate;
			}
		}

		return 'post';
	}

	/**
	 * 决定文章状态。
	 *
	 * @param array $meta    front matter。
	 * @param array $options 选项。
	 * @return string
	 */
	protected function pick_status( $meta, $options ) {
		$map = array(
			'publish'   => 'publish',
			'published' => 'publish',
			'public'    => 'publish',
			'draft'     => 'draft',
			'pending'   => 'pending',
			'review'    => 'pending',
			'private'   => 'private',
			'future'    => 'future',
			'scheduled' => 'future',
		);

		// draft: true 之类。
		if ( isset( $meta['draft'] ) && true === $meta['draft'] ) {
			return 'draft';
		}
		if ( isset( $meta['published'] ) && false === $meta['published'] ) {
			return 'draft';
		}

		$status = '';
		foreach ( array( 'status', 'state' ) as $key ) {
			if ( ! empty( $meta[ $key ] ) && is_string( $meta[ $key ] ) ) {
				$status = strtolower( trim( $meta[ $key ] ) );
				break;
			}
		}

		if ( '' !== $status && isset( $map[ $status ] ) ) {
			$requested = $map[ $status ];
		} elseif ( ! empty( $options['status'] ) ) {
			$requested = sanitize_key( $options['status'] );
		} else {
			$requested = sanitize_key( (string) $this->plugin->option( 'import_status', 'draft' ) );
		}

		if ( ! in_array( $requested, array( 'publish', 'draft', 'pending', 'private', 'future' ), true ) ) {
			$requested = 'draft';
		}

		// 没有发布权限时降级为草稿。
		if ( 'publish' === $requested || 'private' === $requested || 'future' === $requested ) {
			if ( ! current_user_can( 'publish_posts' ) && ! current_user_can( 'publish_pages' ) ) {
				$requested = 'draft';
			}
		}

		return $requested;
	}

	/**
	 * 决定标题。
	 *
	 * @param array  $meta     front matter。
	 * @param string $body     正文。
	 * @param string $filename 文件名。
	 * @return string
	 */
	protected function pick_title( $meta, $body, $filename ) {
		foreach ( array( 'title', 'subject', 'name' ) as $key ) {
			if ( ! empty( $meta[ $key ] ) && is_string( $meta[ $key ] ) ) {
				$title = trim( $meta[ $key ] );
				if ( '' !== $title ) {
					return $title;
				}
			}
		}

		// 第一个 H1。
		if ( preg_match( '/^ {0,3}#[ \t]+(.+?)[ \t]*#*[ \t]*$/m', $body, $m ) ) {
			$title = trim( $m[1] );
			if ( '' !== $title ) {
				return $title;
			}
		}

		// 第一段非空文本。
		foreach ( preg_split( '/\n/', $body ) as $line ) {
			$line = trim( $line );
			if ( '' === $line || 0 === strpos( $line, '#' ) || 0 === strpos( $line, '>' ) ) {
				continue;
			}
			$line = preg_replace( '/[*_`\[\]()]/', '', $line );
			$line = trim( (string) $line );
			if ( '' !== $line ) {
				return mb_substr( $line, 0, 60 );
			}
		}

		$name = pathinfo( (string) $filename, PATHINFO_FILENAME );
		return ( '' !== $name ) ? $name : __( '未命名文章', 'wp-markdown-publisher' );
	}

	/**
	 * 决定作者。
	 *
	 * @param array $meta    front matter。
	 * @param array $options 选项。
	 * @return int
	 */
	protected function pick_author( $meta, $options ) {
		if ( ! empty( $options['author'] ) ) {
			return (int) $options['author'];
		}

		foreach ( array( 'author', 'author_id' ) as $key ) {
			if ( empty( $meta[ $key ] ) ) {
				continue;
			}
			$value = $meta[ $key ];
			if ( is_numeric( $value ) ) {
				$user = get_user_by( 'id', (int) $value );
				if ( $user ) {
					return (int) $user->ID;
				}
			} elseif ( is_string( $value ) ) {
				$user = get_user_by( 'login', $value );
				if ( ! $user ) {
					$user = get_user_by( 'email', $value );
				}
				if ( $user ) {
					return (int) $user->ID;
				}
			}
		}

		$default = (int) $this->plugin->option( 'import_author', 0 );
		if ( $default ) {
			return $default;
		}

		return get_current_user_id();
	}

	/**
	 * 按 front matter 设置分类/标签。
	 *
	 * @param int    $post_id   文章 ID。
	 * @param array  $meta      front matter。
	 * @param string $post_type 类型。
	 * @return void
	 */
	protected function apply_front_matter_terms( $post_id, $meta, $post_type ) {
		if ( empty( $this->plugin->option( 'import_taxonomy' ) ) ) {
			return;
		}

		$groups = array(
			array( 'keys' => array( 'categories', 'category', 'cats', 'section' ), 'taxonomy' => 'category' ),
			array( 'keys' => array( 'tags', 'tag', 'keywords' ), 'taxonomy' => 'post_tag' ),
		);

		foreach ( $groups as $group ) {
			$terms = array();
			foreach ( $group['keys'] as $key ) {
				if ( ! empty( $meta[ $key ] ) ) {
					$terms = $meta[ $key ];
					break;
				}
			}
			if ( empty( $terms ) ) {
				continue;
			}
			if ( ! taxonomy_exists( $group['taxonomy'] ) || ! is_object_in_taxonomy( $post_type, $group['taxonomy'] ) ) {
				continue;
			}
			if ( ! current_user_can( get_taxonomy( $group['taxonomy'] )->cap->assign_terms ) ) {
				continue;
			}

			$terms = is_array( $terms ) ? $terms : array( $terms );
			$terms = array_filter( array_map( 'strval', $terms ) );
			if ( empty( $terms ) ) {
				continue;
			}

			wp_set_object_terms( $post_id, $terms, $group['taxonomy'], false );
		}

		// 其它自定义分类：terms: { taxonomy: [..] } 形式由 REST 层传入。
	}

	/**
	 * 应用 REST 传入的分类设置。
	 *
	 * @param int   $post_id 文章 ID。
	 * @param array $terms   taxonomy => terms。
	 * @return void
	 */
	protected function apply_terms( $post_id, $terms ) {
		if ( ! is_array( $terms ) ) {
			return;
		}
		foreach ( $terms as $taxonomy => $list ) {
			$taxonomy = sanitize_key( $taxonomy );
			if ( ! taxonomy_exists( $taxonomy ) ) {
				continue;
			}
			$list = is_array( $list ) ? $list : explode( ',', (string) $list );
			$list = array_filter( array_map( 'trim', array_map( 'strval', $list ) ) );
			if ( empty( $list ) ) {
				continue;
			}
			if ( ! current_user_can( get_taxonomy( $taxonomy )->cap->assign_terms ) ) {
				continue;
			}
			wp_set_object_terms( $post_id, $list, $taxonomy, false );
		}
	}

	/**
	 * 设置特色图片。
	 *
	 * @param int    $post_id 文章 ID。
	 * @param string $url     图片地址。
	 * @return void
	 */
	protected function apply_featured_image( $post_id, $url ) {
		if ( ! current_user_can( 'upload_files' ) ) {
			return;
		}
		if ( has_post_thumbnail( $post_id ) ) {
			return;
		}
		if ( ! preg_match( '#^https?://#i', $url ) ) {
			return;
		}

		if ( ! function_exists( 'media_sideload_image' ) ) {
			require_once ABSPATH . 'wp-admin/includes/media.php';
			require_once ABSPATH . 'wp-admin/includes/file.php';
			require_once ABSPATH . 'wp-admin/includes/image.php';
		}

		$attachment_id = media_sideload_image( $url, $post_id, null, 'id' );
		if ( ! is_wp_error( $attachment_id ) ) {
			set_post_thumbnail( $post_id, $attachment_id );
		}
	}
}
