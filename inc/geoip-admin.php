<?php
/**
 * 后台 GeoLite2 数据库管理：上传 / 更新 / 删除 / 状态查看。
 *
 * 上传的库统一存放在 wp-content/uploads/aurora-star-geoip/，
 * 优先级高于主题目录内手动放置的文件，且升级主题不会丢失。
 *
 * @package Aurora Star
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * 上传目录（上传成功前不创建）。
 *
 * @return string 不可用时返回空字符串。
 */
function aurora_star_geoip_upload_dir() {
	$uploads = wp_upload_dir();
	if ( ! empty( $uploads['error'] ) || empty( $uploads['basedir'] ) ) {
		return '';
	}

	return trailingslashit( $uploads['basedir'] ) . 'aurora-star-geoip';
}

/**
 * 当前生效的数据库文件信息。
 *
 * @return array 无可用数据库时 installed 为 false。
 */
function aurora_star_geoip_status() {
	$path = aurora_star_geoip_db_path();

	$status = array(
		'installed'   => false,
		'path'        => $path,
		'is_uploaded' => false,
		'size'        => 0,
		'type'        => '',
		'build_epoch' => 0,
		'node_count'  => 0,
		'ip_version'  => 0,
		'error'       => '',
	);

	if ( '' === $path || ! is_readable( $path ) ) {
		$status['error'] = 'not_found';
		return $status;
	}

	$status['size']        = (int) filesize( $path );
	$status['is_uploaded'] = ( false !== strpos( $path, 'aurora-star-geoip' ) );

	$inspect = aurora_star_geoip_inspect( $path );

	if ( ! $inspect['ok'] ) {
		$status['error'] = $inspect['error'];
		return $status;
	}

	$status['installed']   = true;
	$status['type']        = $inspect['type'];
	$status['build_epoch'] = $inspect['build_epoch'];
	$status['node_count']  = $inspect['node_count'];
	$status['ip_version']  = $inspect['ip_version'];

	return $status;
}

/**
 * 校验一个文件是不是可用的 MaxMind 数据库。
 *
 * @param string $path 文件路径。
 * @return array{ok:bool,error:string,type:string,build_epoch:int,node_count:int,ip_version:int}
 */
function aurora_star_geoip_inspect( $path ) {
	$fail = array(
		'ok'          => false,
		'error'       => '',
		'type'        => '',
		'build_epoch' => 0,
		'node_count'  => 0,
		'ip_version'  => 0,
	);

	if ( ! is_readable( $path ) ) {
		$fail['error'] = 'not_readable';
		return $fail;
	}

	// 1) 尾部的元数据标记（MaxMind 规范要求位于最后 128KB 内）。
	$marker = "\xab\xcd\xefMaxMind.com";
	$handle = @fopen( $path, 'rb' );
	if ( ! $handle ) {
		$fail['error'] = 'not_readable';
		return $fail;
	}

	$size      = (int) filesize( $path );
	$read_size = min( 131072, $size );
	fseek( $handle, -$read_size, SEEK_END );
	$tail = fread( $handle, $read_size );
	fclose( $handle );

	if ( false === strpos( (string) $tail, $marker ) ) {
		$fail['error'] = 'not_maxmind_db';
		return $fail;
	}

	// 2) 真正用 Reader 打开一次，确认可读且能取到元数据。
	aurora_star_load_geoip_reader();

	if ( ! class_exists( '\MaxMind\Db\Reader' ) ) {
		$fail['error'] = 'reader_missing';
		return $fail;
	}

	try {
		$reader   = new \MaxMind\Db\Reader( $path );
		$metadata = $reader->metadata();
		$reader->close();
	} catch ( \Throwable $e ) {
		$fail['error'] = 'unreadable_db';
		return $fail;
	}

	return array(
		'ok'          => true,
		'error'       => '',
		'type'        => (string) $metadata->databaseType,
		'build_epoch' => (int) $metadata->buildEpoch,
		'node_count'  => (int) $metadata->nodeCount,
		'ip_version'  => (int) $metadata->ipVersion,
	);
}

/**
 * 从上传的临时文件安装数据库。
 *
 * 先解压/复制到临时目标并校验通过，再原子替换正式文件，
 * 因此一次错误的上传不会破坏当前可用的数据库。
 *
 * @param array $file $_FILES 中的单条记录。
 * @return string 成功返回空字符串，失败返回错误代码。
 */
function aurora_star_geoip_install( $file ) {
	$tmp = isset( $file['tmp_name'] ) ? $file['tmp_name'] : '';
	if ( '' === $tmp || ! is_uploaded_file( $tmp ) ) {
		return 'err_no_file';
	}

	return aurora_star_geoip_install_from_path( $tmp );
}

/**
 * 从一个本地文件安装数据库（解包 → 校验 → 落盘）。
 *
 * 与上传入口分离，便于单独测试。
 *
 * @param string $source 源文件路径（.mmdb / .gz / .tar.gz / .zip）。
 * @return string 成功返回空字符串，否则返回错误代码。
 */
function aurora_star_geoip_install_from_path( $source ) {
	$upload_dir = aurora_star_geoip_upload_dir();
	if ( '' === $upload_dir ) {
		return 'err_uploads_unavailable';
	}

	if ( ! wp_mkdir_p( $upload_dir ) ) {
		return 'err_mkdir';
	}

	// 大文件解压可能较慢。
	if ( function_exists( 'set_time_limit' ) ) {
		@set_time_limit( 300 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
	}

	$working = $upload_dir . '/.incoming.mmdb';
	$result  = aurora_star_geoip_unpack( $source, $working );

	if ( '' !== $result ) {
		@unlink( $working ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		return $result;
	}

	$inspect = aurora_star_geoip_inspect( $working );
	if ( ! $inspect['ok'] ) {
		@unlink( $working ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		return 'err_' . $inspect['error'];
	}

	// 按数据库类型命名：城市库 / 国家库分别落盘，城市库优先被读取。
	if ( false !== strpos( $inspect['type'], 'City' ) ) {
		$target_name = 'GeoLite2-City.mmdb';
	} elseif ( false !== strpos( $inspect['type'], 'Country' ) ) {
		$target_name = 'GeoLite2-Country.mmdb';
	} else {
		$target_name = 'GeoLite2.mmdb';
	}

	$target = $upload_dir . '/' . $target_name;

	// 同目录内的重命名是原子操作。
	if ( ! @rename( $working, $target ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		// 跨文件系统时退化为复制。
		if ( ! @copy( $working, $target ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			@unlink( $working ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			return 'err_write';
		}
		@unlink( $working ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
	}

	// 避免目录被直接浏览。
	if ( ! file_exists( $upload_dir . '/index.php' ) ) {
		@file_put_contents( $upload_dir . '/index.php', "<?php\n// Silence is golden.\n" ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
	}

	return '';
}

/**
 * 把上传文件解包成裸 .mmdb。
 *
 * 按文件头识别，不依赖扩展名：gzip（含 tar.gz）、zip、或裸 mmdb。
 *
 * @param string $source 上传的临时文件。
 * @param string $dest   输出路径。
 * @return string 成功返回空字符串，否则返回错误代码。
 */
function aurora_star_geoip_unpack( $source, $dest ) {
	$handle = @fopen( $source, 'rb' );
	if ( ! $handle ) {
		return 'err_no_file';
	}
	$magic = (string) fread( $handle, 4 );
	fclose( $handle );

	// gzip：可能是 GeoLite2-City.tar.gz，也可能是单独压缩的 .mmdb。
	if ( 0 === strpos( $magic, "\x1f\x8b" ) ) {
		if ( aurora_star_geoip_extract_targz( $source, $dest ) ) {
			return '';
		}
		if ( aurora_star_geoip_extract_gz( $source, $dest ) ) {
			return '';
		}
		return 'err_extract';
	}

	// zip
	if ( 0 === strpos( $magic, "PK\x03\x04" ) ) {
		if ( aurora_star_geoip_extract_zip( $source, $dest ) ) {
			return '';
		}
		return 'err_extract_zip';
	}

	// 其余一律按裸数据库处理，后续再校验。
	if ( ! @copy( $source, $dest ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		return 'err_write';
	}

	return '';
}

/**
 * 从 tar.gz 中流式提取 .mmdb（不把整个文件读进内存）。
 *
 * @param string $source tar.gz 路径。
 * @param string $dest   输出路径。
 * @return bool
 */
function aurora_star_geoip_extract_targz( $source, $dest ) {
	$gz = @gzopen( $source, 'rb' );
	if ( ! $gz ) {
		return false;
	}

	$found = false;

	while ( ! gzeof( $gz ) ) {
		$header = gzread( $gz, 512 );
		if ( strlen( $header ) < 512 ) {
			break;
		}

		// 归档结尾的连续空块。
		if ( '' === trim( $header, "\0" ) ) {
			continue;
		}

		$name = rtrim( substr( $header, 0, 100 ), "\0" );

		// 大小字段是 12 字节八进制，但不同 tar 实现会用 NUL / 空格填充，
		// 超大文件还会用 base-256 编码，直接 octdec() 会在 PHP 8.1+ 触发 deprecation。
		$raw_size = (string) substr( $header, 124, 12 );
		if ( isset( $raw_size[0] ) && ord( $raw_size[0] ) > 127 ) {
			$size = 0; // base-256：本场景用不到，按跳过处理。
		} elseif ( preg_match( '/[0-7]+/', $raw_size, $size_match ) ) {
			$size = (int) octdec( $size_match[0] );
		} else {
			$size = 0;
		}

		$type = substr( $header, 156, 1 );

		$is_file = ( '' === $type || '0' === $type );
		$pad     = ( 512 - ( $size % 512 ) ) % 512;

		if ( $is_file && '.mmdb' === strtolower( substr( $name, -5 ) ) ) {
			$out = @fopen( $dest, 'wb' );
			if ( ! $out ) {
				gzclose( $gz );
				return false;
			}

			$left = $size;
			while ( $left > 0 ) {
				$chunk = gzread( $gz, (int) min( 262144, $left ) );
				if ( '' === $chunk ) {
					break;
				}
				fwrite( $out, $chunk );
				$left -= strlen( $chunk );
			}
			fclose( $out );

			if ( 0 !== $left ) {
				@unlink( $dest ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
				gzclose( $gz );
				return false;
			}

			$found = true;
			break;
		}

		// 跳过该条目的内容。
		$skip = $size + $pad;
		while ( $skip > 0 ) {
			$chunk = gzread( $gz, (int) min( 262144, $skip ) );
			if ( '' === $chunk ) {
				break;
			}
			$skip -= strlen( $chunk );
		}
	}

	gzclose( $gz );

	return $found;
}

/**
 * 解压单个 .gz。
 *
 * @param string $source .gz 路径。
 * @param string $dest   输出路径。
 * @return bool
 */
function aurora_star_geoip_extract_gz( $source, $dest ) {
	$gz = @gzopen( $source, 'rb' );
	if ( ! $gz ) {
		return false;
	}

	$out = @fopen( $dest, 'wb' );
	if ( ! $out ) {
		gzclose( $gz );
		return false;
	}

	while ( ! gzeof( $gz ) ) {
		$chunk = gzread( $gz, 262144 );
		if ( '' === $chunk ) {
			break;
		}
		fwrite( $out, $chunk );
	}

	fclose( $out );
	gzclose( $gz );

	return true;
}

/**
 * 从 zip 中提取 .mmdb（需要 zip 扩展）。
 *
 * @param string $source zip 路径。
 * @param string $dest   输出路径。
 * @return bool
 */
function aurora_star_geoip_extract_zip( $source, $dest ) {
	if ( ! class_exists( 'ZipArchive' ) ) {
		return false;
	}

	$zip = new ZipArchive();
	if ( true !== $zip->open( $source ) ) {
		return false;
	}

	$found = false;

	for ( $i = 0; $i < $zip->numFiles; $i++ ) {
		$name = (string) $zip->getNameIndex( $i );
		if ( '.mmdb' !== strtolower( substr( $name, -5 ) ) ) {
			continue;
		}

		$stream = $zip->getStream( $name );
		if ( ! $stream ) {
			continue;
		}

		$out = @fopen( $dest, 'wb' );
		if ( $out ) {
			while ( ! feof( $stream ) ) {
				fwrite( $out, fread( $stream, 262144 ) );
			}
			fclose( $out );
			$found = true;
		}

		fclose( $stream );

		if ( $found ) {
			break;
		}
	}

	$zip->close();

	return $found;
}

/**
 * 删除通过后台上传的数据库。
 *
 * @return void
 */
function aurora_star_geoip_delete_uploaded() {
	$upload_dir = aurora_star_geoip_upload_dir();
	if ( '' === $upload_dir ) {
		return;
	}

	foreach ( array( 'GeoLite2-City.mmdb', 'GeoLite2-Country.mmdb', 'GeoLite2.mmdb', '.incoming.mmdb' ) as $name ) {
		$file = $upload_dir . '/' . $name;
		if ( file_exists( $file ) ) {
			@unlink( $file ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		}
	}
}

/* -------------------------------------------------------------------------
 * 表单处理
 * ---------------------------------------------------------------------- */

/**
 * 错误代码 → 提示文案。
 *
 * @return array
 */
function aurora_star_geoip_messages() {
	return array(
		'installed'                => array( 'success', 'IP 数据库已更新。' ),
		'deleted'                  => array( 'success', '已删除后台上传的数据库。' ),
		'err_no_file'              => array( 'error', '没有收到文件，请重新选择。' ),
		'err_too_large'            => array( 'error', '文件超过服务器允许的上传大小（post_max_size），请调大 php.ini 的 upload_max_filesize 与 post_max_size，或改用 FTP 手动放置。' ),
		'err_partial'              => array( 'error', '文件只上传了一部分，请重试。' ),
		'err_uploads_unavailable'  => array( 'error', 'wp-content/uploads 目录不可用或不可写。' ),
		'err_mkdir'                => array( 'error', '无法创建上传目录，请检查 wp-content/uploads 的写入权限。' ),
		'err_write'                => array( 'error', '写入数据库文件失败，请检查磁盘空间与目录权限。' ),
		'err_extract'              => array( 'error', '无法从压缩包中解出 .mmdb，请确认下载完整（GeoLite2 的 .tar.gz 内含 mmdb）。' ),
		'err_extract_zip'          => array( 'error', '解压 zip 失败。若服务器未启用 zip 扩展，请改用 .tar.gz 或直接上传 .mmdb。' ),
		'err_not_maxmind_db'       => array( 'error', '这不是有效的 MaxMind 数据库文件（缺少元数据标记），已放弃替换，原数据库未受影响。' ),
		'err_unreadable_db'        => array( 'error', '数据库可以识别但无法读取，可能已损坏，原数据库未受影响。' ),
		'err_not_readable'         => array( 'error', '文件不可读。' ),
		'err_reader_missing'       => array( 'error', '主题内置的 MaxMind 读取库未找到，请重新安装主题。' ),
	);

}

/**
 * 处理上传 / 删除请求，然后重定向回设置页（PRG 模式）。
 *
 * 之所以挂在 admin_init 而不是 admin-post.php：当上传超过 post_max_size 时
 * $_POST 会被清空，admin-post.php 拿不到 action 参数，无法给出任何提示。
 *
 * @return void
 */
function aurora_star_geoip_maybe_handle_post() {
	if ( 'POST' !== strtoupper( isset( $_SERVER['REQUEST_METHOD'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) : '' ) ) {
		return;
	}

	if ( ! isset( $_GET['page'] ) || 'aurora-star-settings' !== $_GET['page'] ) {
		return;
	}

	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	// 超出 post_max_size 时 $_POST / $_FILES 均为空，但 CONTENT_LENGTH 仍保留。
	if ( empty( $_POST ) ) {
		$length = isset( $_SERVER['CONTENT_LENGTH'] ) ? (int) $_SERVER['CONTENT_LENGTH'] : 0;
		if ( $length > 0 ) {
			aurora_star_geoip_redirect( 'err_too_large' );
		}
		return;
	}

	$action = isset( $_POST['aurora_star_geoip_action'] )
		? sanitize_key( wp_unslash( $_POST['aurora_star_geoip_action'] ) )
		: '';

	if ( ! in_array( $action, array( 'upload', 'delete' ), true ) ) {
		return;
	}

	check_admin_referer( 'aurora_star_geoip_' . $action );

	if ( 'delete' === $action ) {
		aurora_star_geoip_delete_uploaded();
		aurora_star_geoip_redirect( 'deleted' );
	}

	if ( empty( $_FILES['aurora_star_geoip_file'] ) ) {
		aurora_star_geoip_redirect( 'err_no_file' );
	}

	$file = $_FILES['aurora_star_geoip_file']; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- 逐字段处理。

	$error = isset( $file['error'] ) ? (int) $file['error'] : UPLOAD_ERR_NO_FILE;
	if ( UPLOAD_ERR_OK !== $error ) {
		$map = array(
			UPLOAD_ERR_INI_SIZE   => 'err_too_large',
			UPLOAD_ERR_FORM_SIZE  => 'err_too_large',
			UPLOAD_ERR_PARTIAL    => 'err_partial',
			UPLOAD_ERR_NO_FILE    => 'err_no_file',
			UPLOAD_ERR_NO_TMP_DIR => 'err_write',
			UPLOAD_ERR_CANT_WRITE => 'err_write',
			UPLOAD_ERR_EXTENSION  => 'err_write',
		);

		aurora_star_geoip_redirect( isset( $map[ $error ] ) ? $map[ $error ] : 'err_no_file' );
	}

	$result = aurora_star_geoip_install( $file );
	aurora_star_geoip_redirect( '' === $result ? 'installed' : $result );
}

add_action( 'admin_init', 'aurora_star_geoip_maybe_handle_post' );

/**
 * 带提示码重定向回设置页。
 *
 * @param string $code 提示码。
 * @return void
 */
function aurora_star_geoip_redirect( $code ) {
	wp_safe_redirect(
		add_query_arg(
			array(
				'page'                => 'aurora-star-settings',
				'aurora-geoip-notice' => rawurlencode( $code ),
			),
			admin_url( 'admin.php' )
		)
	);
	exit;
}

/* -------------------------------------------------------------------------
 * 后台界面
 * ---------------------------------------------------------------------- */

/**
 * 输出 IP 数据库管理面板。
 *
 * @return void
 */
function aurora_star_render_geoip_panel() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$status   = aurora_star_geoip_status();
	$messages = aurora_star_geoip_messages();

	// 处理结果提示。
	$notice_code = isset( $_GET['aurora-geoip-notice'] ) // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		? sanitize_key( wp_unslash( $_GET['aurora-geoip-notice'] ) ) // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		: '';

	if ( '' !== $notice_code && isset( $messages[ $notice_code ] ) ) {
		printf(
			'<div class="notice notice-%s is-dismissible"><p>%s</p></div>',
			esc_attr( $messages[ $notice_code ][0] ),
			esc_html( $messages[ $notice_code ][1] )
		);
	}
	?>
	<h2 style="margin-top: 32px;"><?php esc_html_e( 'IP 归属地数据库', 'aurora-star' ); ?></h2>

	<table class="widefat striped" style="max-width: 860px;">
		<tbody>
			<tr>
				<th style="width: 140px;"><?php esc_html_e( '状态', 'aurora-star' ); ?></th>
				<td>
					<?php if ( $status['installed'] ) : ?>
						<span style="color: #007017; font-weight: 600;">✔ <?php esc_html_e( '已安装', 'aurora-star' ); ?></span>
						（<?php echo $status['is_uploaded'] ? esc_html__( '由后台上传', 'aurora-star' ) : esc_html__( '主题目录内手动放置', 'aurora-star' ); ?>）
					<?php else : ?>
						<span style="color: #b32d2e; font-weight: 600;">✘ <?php esc_html_e( '未安装', 'aurora-star' ); ?></span>
						— <?php esc_html_e( '评论将不显示国旗与归属地（其余功能不受影响）。', 'aurora-star' ); ?>
					<?php endif; ?>
				</td>
			</tr>
			<tr>
				<th><?php esc_html_e( '文件路径', 'aurora-star' ); ?></th>
				<td><code style="word-break: break-all;"><?php echo esc_html( $status['path'] ); ?></code></td>
			</tr>
			<?php if ( $status['installed'] ) : ?>
				<tr>
					<th><?php esc_html_e( '数据库类型', 'aurora-star' ); ?></th>
					<td>
						<code><?php echo esc_html( $status['type'] ); ?></code>
						<?php if ( false === strpos( $status['type'], 'City' ) ) : ?>
							<span style="color: #996800;">— <?php esc_html_e( '国家库只能定位到国家，无法显示省市。', 'aurora-star' ); ?></span>
						<?php endif; ?>
					</td>
				</tr>
				<tr>
					<th><?php esc_html_e( '构建时间', 'aurora-star' ); ?></th>
					<td>
						<strong><?php echo esc_html( gmdate( 'Y-m-d', $status['build_epoch'] ) ); ?></strong>
						<?php
						$age_days = (int) floor( ( time() - $status['build_epoch'] ) / DAY_IN_SECONDS );
						if ( $age_days > 45 ) {
							printf(
								' <span style="color: #996800;">— %s</span>',
								esc_html(
									sprintf(
										/* translators: %d: 天数。 */
										__( '已 %d 天未更新，建议重新下载（MaxMind 通常每周更新）', 'aurora-star' ),
										$age_days
									)
								)
							);
						}
						?>
					</td>
				</tr>
				<tr>
					<th><?php esc_html_e( '文件大小', 'aurora-star' ); ?></th>
					<td><?php echo esc_html( size_format( $status['size'], 1 ) ); ?></td>
				</tr>
				<tr>
					<th><?php esc_html_e( '节点数 / IP 版本', 'aurora-star' ); ?></th>
					<td><?php echo esc_html( number_format_i18n( $status['node_count'] ) ); ?> / IPv<?php echo (int) $status['ip_version']; ?></td>
				</tr>
			<?php endif; ?>
		</tbody>
	</table>

	<?php if ( ! $status['installed'] ) : ?>
		<div class="notice notice-info inline" style="max-width: 860px; margin: 12px 0;">
			<p>
				<?php
				printf(
					/* translators: %s: 示例过滤器代码。 */
					esc_html__( '到 %1$s 注册免费账号下载 %2$s，然后用下面的表单上传即可。也可以手动放到主题的 assets/geoip/ 目录，或用 %3$s 过滤器指定其他位置。', 'aurora-star' ),
					'<a href="https://www.maxmind.com/en/geolite2/signup" target="_blank" rel="noopener noreferrer">maxmind.com</a>',
					'<code>GeoLite2-City.tar.gz</code>',
					'<code>aurora_star_geoip_db_path</code>'
				);
				?>
			</p>
		</div>
	<?php endif; ?>

	<form method="post" enctype="multipart/form-data" style="margin: 16px 0 8px;">
		<?php wp_nonce_field( 'aurora_star_geoip_upload' ); ?>
		<input type="hidden" name="aurora_star_geoip_action" value="upload" />

		<p>
			<input type="file" name="aurora_star_geoip_file" accept=".mmdb,.gz,.tgz,.zip,application/gzip,application/zip" required />
			<button type="submit" class="button button-primary"><?php esc_html_e( '上传并安装', 'aurora-star' ); ?></button>
		</p>

		<p class="description" style="max-width: 860px;">
			<?php esc_html_e( '支持 MaxMind 官方下载的 .tar.gz，也支持 .gz / .zip / 直接上传 .mmdb。上传后会先校验文件有效性，通过后才替换现有数据库；校验失败会保留原库。', 'aurora-star' ); ?>
		</p>
	</form>

	<?php
	$max_upload = size_format( wp_max_upload_size(), 1 );
	$zip_ok     = class_exists( 'ZipArchive' );
	?>
	<p class="description" style="max-width: 860px;">
		<?php
		printf(
			/* translators: 1: 上传大小上限，2: zip 扩展状态。 */
			esc_html__( '当前服务器上传上限：%1$s；zip 扩展：%2$s。若上传失败，多半是 upload_max_filesize / post_max_size 太小。', 'aurora-star' ),
			'<strong>' . esc_html( $max_upload ) . '</strong>',
			$zip_ok ? esc_html__( '已启用', 'aurora-star' ) : esc_html__( '未启用（请改用 .tar.gz）', 'aurora-star' )
		);
		?>
	</p>

	<?php if ( $status['is_uploaded'] ) : ?>
		<form method="post" onsubmit="return confirm('<?php echo esc_js( __( '确定删除后台上传的数据库？删除后归属地功能将失效。', 'aurora-star' ) ); ?>');">
			<?php wp_nonce_field( 'aurora_star_geoip_delete' ); ?>
			<input type="hidden" name="aurora_star_geoip_action" value="delete" />
			<button type="submit" class="button button-link-delete"><?php esc_html_e( '删除后台上传的数据库', 'aurora-star' ); ?></button>
		</form>
	<?php endif; ?>
	<?php
}
