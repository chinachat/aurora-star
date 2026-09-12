<?php
/**
 * 零依赖 Markdown 解析器（GFM 风格）。
 *
 * 支持：ATX/Setext 标题、围栏与缩进代码块、引用、有序/无序列表（可嵌套、紧/松列表）、
 * 任务列表、表格、分隔线、链接引用定义、脚注、行内 HTML（可选）、自动链接、
 * 强调/加粗/删除线、代码段、标题锚点与目录(TOC)。
 *
 * 该文件不依赖 WordPress，可独立测试。
 *
 * @package Mdp
 */

defined( 'ABSPATH' ) || exit;

if ( class_exists( 'Mdp_Markdown', false ) ) {
	return;
}

/**
 * Markdown 解析器。
 */
class Mdp_Markdown {

	/** 解析器版本（用于判断文章是否需要重新渲染）。 */
	const VERSION = '1.0.1';

	/** 行内占位符分隔符。 */
	const PH = "\x1A";

	/** 延迟解析（先收集块结构、后解析行内语法）占位符分隔符。 */
	const DEF = "\x1B";

	/**
	 * 默认选项。
	 *
	 * @var array
	 */
	protected static $defaults = array(
		'allow_html'    => true,   // 允许原始 HTML。
		'sanitize_urls' => true,   // 过滤 javascript:/data: 等危险链接协议。
		'tables'        => true,   // 表格。
		'tasklists'     => true,   // 任务列表。
		'footnotes'     => true,   // 脚注。
		'heading_ids'   => true,   // 标题自动加锚点 id。
		'autolink'      => true,   // 裸 URL 自动转链接。
		'breaks'        => false,  // 单个换行即 <br>。
		'toc_min'       => 1,      // 目录包含的最小标题级别。
		'toc_max'       => 4,      // 目录包含的最大标题级别。
		'toc_title'     => '目录',
	);

	/**
	 * 选项。
	 *
	 * @var array
	 */
	protected $options = array();

	/**
	 * 链接引用定义：key => array( url, title )。
	 *
	 * @var array
	 */
	protected $refs = array();

	/**
	 * 脚注定义：id => HTML。
	 *
	 * @var array
	 */
	protected $footnotes = array();

	/**
	 * 目录条目。
	 *
	 * @var array
	 */
	protected $toc = array();

	/**
	 * 已使用的锚点 id（去重用）。
	 *
	 * @var array
	 */
	protected $used_ids = array();

	/**
	 * 延迟解析的行内文本。
	 *
	 * @var array
	 */
	protected $deferred = array();

	/**
	 * 是否为多行行内文本。
	 *
	 * @var array
	 */
	protected $deferred_block = array();

	/**
	 * 构造。
	 *
	 * @param array $options 选项。
	 */
	public function __construct( $options = array() ) {
		$this->options = array_merge( self::$defaults, is_array( $options ) ? $options : array() );
	}

	/**
	 * 解析 Markdown 并返回结构化结果。
	 *
	 * @param string $markdown Markdown 原文。
	 * @return array { html, toc, toc_html, headings, words, parser }
	 */
	public function parse( $markdown ) {
		$this->reset();
		$markdown = $this->normalize( (string) $markdown );
		$lines    = explode( "\n", $markdown );

		$html = $this->parseBlocks( $lines );

		// 先解析正文中的延迟行内内容，再据此组装脚注区（脚注正文单独解析）。
		$html = $this->resolveDeferred( $html );
		$html = $this->assembleFootnotes( $html );
		$html = $this->resolveDeferred( $html );
		$html = $this->replaceTocPlaceholder( $html );
		$html = $this->cleanup( $html );

		return array(
			'html'     => $html,
			'toc'      => $this->toc,
			'toc_html' => $this->buildTocHtml(),
			'headings' => count( $this->toc ),
			'words'    => $this->wordCount( $markdown ),
			'parser'   => self::VERSION,
		);
	}

	/**
	 * 只取 HTML。
	 *
	 * @param string $markdown Markdown 原文。
	 * @return string
	 */
	public function toHtml( $markdown ) {
		$result = $this->parse( $markdown );
		return $result['html'];
	}

	/**
	 * 最近一次解析得到的目录。
	 *
	 * @return array
	 */
	public function getToc() {
		return $this->toc;
	}

	/**
	 * 读取选项。
	 *
	 * @param string $key     键。
	 * @param mixed  $default 默认值。
	 * @return mixed
	 */
	public function getOption( $key, $default = null ) {
		return isset( $this->options[ $key ] ) ? $this->options[ $key ] : $default;
	}

	/**
	 * 设置选项。
	 *
	 * @param string $key   键。
	 * @param mixed  $value 值。
	 * @return void
	 */
	public function setOption( $key, $value ) {
		$this->options[ $key ] = $value;
	}

	/**
	 * 重置内部状态。
	 *
	 * @return void
	 */
	protected function reset() {
		$this->refs       = array();
		$this->footnotes  = array();
		$this->toc        = array();
		$this->used_ids   = array();
		$this->deferred   = array();
	}

	// ---------------------------------------------------------------------
	// 预处理。
	// ---------------------------------------------------------------------

	/**
	 * 规范化输入。
	 *
	 * @param string $text 原文。
	 * @return string
	 */
	protected function normalize( $text ) {
		$text = self::ensure_utf8( $text );
		$text = str_replace( array( "\r\n", "\r" ), "\n", $text );
		// 去掉 BOM 与我们占位符使用的控制字符。
		$text = str_replace( array( "\xEF\xBB\xBF", self::PH, self::DEF, "\x00" ), '', $text );
		// 制表符展开为 4 空格。
		$text = str_replace( "\t", '    ', $text );
		return $text;
	}

	/**
	 * 保证文本是 UTF-8（GBK 等编码的文件常见于中文环境）。
	 *
	 * @param string $text 文本。
	 * @return string
	 */
	public static function ensure_utf8( $text ) {
		$text = (string) $text;

		if ( ! function_exists( 'mb_check_encoding' ) ) {
			return $text;
		}
		if ( mb_check_encoding( $text, 'UTF-8' ) ) {
			return $text;
		}

		if ( function_exists( 'mb_detect_encoding' ) ) {
			$encoding = mb_detect_encoding( $text, array( 'GB18030', 'BIG-5', 'SJIS', 'EUC-KR', 'ISO-8859-1' ), true );
			if ( $encoding ) {
				$converted = @mb_convert_encoding( $text, 'UTF-8', $encoding ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
				if ( is_string( $converted ) && '' !== $converted ) {
					return $converted;
				}
			}
		}

		if ( function_exists( 'iconv' ) ) {
			$converted = @iconv( 'UTF-8', 'UTF-8//IGNORE', $text ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			if ( is_string( $converted ) ) {
				return $converted;
			}
		}

		return $text;
	}

	/**
	 * 判断空行。
	 *
	 * @param string $line 行。
	 * @return bool
	 */
	protected function isBlank( $line ) {
		return trim( $line ) === '';
	}

	/**
	 * 行首空格数。
	 *
	 * @param string $line 行。
	 * @return int
	 */
	protected function indentOf( $line ) {
		return strspn( $line, ' ' );
	}

	// ---------------------------------------------------------------------
	// 块级解析。
	// ---------------------------------------------------------------------

	/**
	 * 解析块级结构。
	 *
	 * @param array $lines 行数组。
	 * @return string
	 */
	protected function parseBlocks( array $lines ) {
		$html  = '';
		$count = count( $lines );
		$i     = 0;

		while ( $i < $count ) {
			$line = $lines[ $i ];

			if ( $this->isBlank( $line ) ) {
				$i++;
				continue;
			}

			// 脚注定义。
			if ( $this->options['footnotes'] && preg_match( '/^ {0,3}\[\^([^\]\s]+)\]:[ \t]*(.*)$/', $line, $m ) ) {
				$i = $this->consumeFootnote( $lines, $i, $m[1], $m[2] );
				continue;
			}

			// 链接引用定义。
			if ( preg_match( '/^ {0,3}\[([^\]^][^\]]*)\]:[ \t]*(.+)$/', $line, $m ) ) {
				$consumed = $this->consumeLinkRef( $lines, $i, $m[1], $m[2] );
				if ( $consumed > 0 ) {
					$i += $consumed;
					continue;
				}
			}

			// 围栏代码块。
			if ( preg_match( '/^( {0,3})(`{3,}|~{3,})[ \t]*(.*)$/', $line, $m ) ) {
				$i     = $this->parseFenced( $lines, $i, $m[2], $m[3], $html );
				continue;
			}

			// ATX 标题。
			if ( preg_match( '/^ {0,3}(#{1,6})(?:[ \t]+(.*?)|[ \t]*)$/', $line, $m ) ) {
				$text = isset( $m[2] ) ? $m[2] : '';
				$text = preg_replace( '/[ \t]+#+[ \t]*$/', '', $text );
				$html .= $this->makeHeading( strlen( $m[1] ), $text );
				$i++;
				continue;
			}

			// 分隔线。
			if ( preg_match( '/^ {0,3}(?:(?:\*[ \t]*){3,}|(?:-[ \t]*){3,}|(?:_[ \t]*){3,})$/', $line ) ) {
				$html .= "<hr />\n";
				$i++;
				continue;
			}

			// 引用块。
			if ( preg_match( '/^ {0,3}>/', $line ) ) {
				$i     = $this->parseBlockquote( $lines, $i, $html );
				continue;
			}

			// 列表。
			if ( preg_match( '/^( {0,3})([-+*]|\d{1,9}[.)])(?:[ \t]+(.*)|[ \t]*)$/', $line ) ) {
				$i     = $this->parseList( $lines, $i, $html );
				continue;
			}
			// 表格。
			if ( $this->options['tables'] && $i + 1 < $count
				&& strpos( $line, '|' ) !== false
				&& $this->isTableDelimiter( $lines[ $i + 1 ] ) ) {
				$i     = $this->parseTable( $lines, $i, $html );
				continue;
			}

			// 缩进代码块。
			if ( preg_match( '/^ {4}/', $line ) ) {
				$i     = $this->parseIndentedCode( $lines, $i, $html );
				continue;
			}

			// HTML 块。
			if ( $this->isHtmlBlockStart( $line ) ) {
				$i     = $this->parseHtmlBlock( $lines, $i, $html );
				continue;
			}

			// [TOC] 标记。
			if ( preg_match( '/^ {0,3}\[toc\][ \t]*$/i', $line ) ) {
				$html .= "<!--mdp-toc-->\n";
				$i++;
				continue;
			}

			// 段落（含 Setext 标题）。
			$i     = $this->parseParagraph( $lines, $i, $html );
		}

		return $html;
	}

	/**
	 * 段落。
	 *
	 * @param array  $lines 行。
	 * @param int    $i     当前位置。
	 * @param string $html  输出（引用）。
	 * @return int 新位置。
	 */
	protected function parseParagraph( array $lines, $i, &$html ) {
		$count = count( $lines );
		$buf   = array();
		$level = 0;

		while ( $i < $count ) {
			$line = $lines[ $i ];

			if ( $this->isBlank( $line ) ) {
				break;
			}

			if ( ! empty( $buf ) && preg_match( '/^ {0,3}(=+|-+)[ \t]*$/', $line, $m ) ) {
				$level = ( '=' === substr( $m[1], 0, 1 ) ) ? 1 : 2;
				$i++;
				break;
			}

			if ( ! empty( $buf ) && $this->interruptsParagraph( $line ) ) {
				break;
			}

			$buf[] = ltrim( $line, ' ' );
			$i++;
		}

		$text = implode( "\n", $buf );

		if ( $level > 0 ) {
			$html .= $this->makeHeading( $level, $text );
		} else {
			$html .= '<p>' . $this->defer( $text ) . "</p>\n";
		}

		return $i;
	}

	/**
	 * 该行是否会打断段落。
	 *
	 * @param string $line 行。
	 * @return bool
	 */
	protected function interruptsParagraph( $line ) {
		if ( preg_match( '/^ {0,3}(?:#{1,6})(?:[ \t]+|$)/', $line ) ) {
			return true;
		}
		if ( preg_match( '/^ {0,3}(?:`{3,}|~{3,})/', $line ) ) {
			return true;
		}
		if ( preg_match( '/^ {0,3}(?:(?:\*[ \t]*){3,}|(?:-[ \t]*){3,}|(?:_[ \t]*){3,})$/', $line ) ) {
			return true;
		}
		if ( preg_match( '/^ {0,3}>/', $line ) ) {
			return true;
		}
		if ( preg_match( '/^ {0,3}[-+*](?:[ \t]+|$)/', $line ) ) {
			return true;
		}
		// 有序列表仅当以 1 开头时才能打断段落。
		if ( preg_match( '/^ {0,3}1[.)](?:[ \t]+|$)/', $line ) ) {
			return true;
		}
		if ( $this->isHtmlBlockStart( $line ) ) {
			return true;
		}
		return false;
	}

	/**
	 * 是否为 HTML 块起始行（自动链接除外）。
	 *
	 * @param string $line 行。
	 * @return bool
	 */
	protected function isHtmlBlockStart( $line ) {
		if ( ! $this->options['allow_html'] ) {
			return false;
		}
		// <https://...> 与 <a@b.com> 是自动链接，不是 HTML 块。
		if ( preg_match( '/^ {0,3}<[a-zA-Z][a-zA-Z0-9+.\-]*:[^<>\s]*>[ \t]*$/', $line ) ) {
			return false;
		}
		if ( preg_match( '/^ {0,3}<[^\s<>@]+@[^\s<>@]+\.[^\s<>@]+>[ \t]*$/', $line ) ) {
			return false;
		}
		return (bool) preg_match( '/^ {0,3}<(?:\/?(?:[a-zA-Z][a-zA-Z0-9-]*)|!--|\?|!\[CDATA\[)/', $line );
	}

	/**
	 * 生成标题。
	 *
	 * @param int    $level 级别。
	 * @param string $text  原文。
	 * @return string
	 */
	protected function makeHeading( $level, $text ) {
		$level = max( 1, min( 6, (int) $level ) );
		$text  = trim( $text );

		// 支持 {#custom-id} 语法。
		$custom = '';
		if ( preg_match( '/(?:^|\s)\{#([^}\s]+)\}[ \t]*$/', $text, $m ) ) {
			$custom = $m[1];
			$text   = trim( preg_replace( '/(?:^|\s)\{#[^}\s]+\}[ \t]*$/', '', $text ) );
		}

		$plain = $this->plainText( $text );
		$id    = $custom !== '' ? $this->uniqueId( $custom ) : ( $this->options['heading_ids'] ? $this->uniqueId( $plain ) : '' );

		$this->toc[] = array(
			'level' => $level,
			'id'    => $id,
			'text'  => $plain,
		);

		$attr = ( $id !== '' ) ? ' id="' . $this->esc( $id ) . '"' : '';

		return '<h' . $level . $attr . '>' . $this->defer( $text ) . '</h' . $level . ">\n";
	}

	/**
	 * 生成唯一锚点。
	 *
	 * @param string $text 文本。
	 * @return string
	 */
	protected function uniqueId( $text ) {
		$id = $this->slugify( $text );
		if ( '' === $id ) {
			$id = 'section';
		}
		$base = $id;
		$n    = 1;
		while ( isset( $this->used_ids[ $id ] ) ) {
			$n++;
			$id = $base . '-' . $n;
		}
		$this->used_ids[ $id ] = true;
		return $id;
	}

	/**
	 * 生成 slug（保留中日韩字符）。
	 *
	 * 刻意对齐 GitHub 的锚点算法：**空白逐个变成 `-`，且不折叠连续的 `-`**。
	 * 文档里的目录通常是手写的 GitHub 风格锚点（例如「暗黑 / 明亮模式」写作
	 * `#4-暗黑--明亮模式`，斜杠两侧各留一个 `-`），一旦折叠就对不上了。
	 *
	 * @param string $text 文本。
	 * @return string
	 */
	protected function slugify( $text ) {
		$text = $this->plainText( $text );
		$text = strtolower( trim( $text ) );
		$text = preg_replace( '/[\s_]+/u', '-', $text );
		$text = preg_replace( '/[^\p{L}\p{N}\-]+/u', '', $text );
		return trim( $text, '-' );
	}

	/**
	 * 围栏代码块。
	 *
	 * @param array  $lines 行。
	 * @param int    $i     位置。
	 * @param string $fence 围栏串。
	 * @param string $info  信息串。
	 * @param string $html  输出（引用）。
	 * @return int
	 */
	protected function parseFenced( array $lines, $i, $fence, $info, &$html ) {
		$count     = count( $lines );
		$char      = substr( $fence, 0, 1 );
		$len       = strlen( $fence );
		$code      = array();
		$i++;

		while ( $i < $count ) {
			if ( preg_match( '/^ {0,3}' . preg_quote( $char, '/' ) . '{' . $len . ',}[ \t]*$/', $lines[ $i ] ) ) {
				$i++;
				break;
			}
			$code[] = $lines[ $i ];
			$i++;
		}

		$html .= $this->renderCode( implode( "\n", $code ), $info );

		return $i;
	}

	/**
	 * 缩进代码块。
	 *
	 * @param array  $lines 行。
	 * @param int    $i     位置。
	 * @param string $html  输出（引用）。
	 * @return int
	 */
	protected function parseIndentedCode( array $lines, $i, &$html ) {
		$count = count( $lines );
		$code  = array();
		$blank = array();

		while ( $i < $count ) {
			$line = $lines[ $i ];
			if ( $this->isBlank( $line ) ) {
				$blank[] = '';
				$i++;
				continue;
			}
			if ( preg_match( '/^ {4}(.*)$/', $line, $m ) ) {
				while ( $blank ) {
					$code[] = array_shift( $blank );
				}
				$code[] = $m[1];
				$i++;
				continue;
			}
			// 非缩进行：若之前有代码，段落打断则结束。
			if ( $this->interruptsParagraph( $line ) || ! empty( $code ) ) {
				break;
			}
			break;
		}

		$html .= $this->renderCode( implode( "\n", $code ), '' );

		return $i;
	}

	/**
	 * 渲染代码块。
	 *
	 * @param string $code 代码。
	 * @param string $info 信息串。
	 * @return string
	 */
	protected function renderCode( $code, $info ) {
		$code = rtrim( $code, "\n" );
		$lang = '';
		if ( '' !== trim( $info ) ) {
			$parts = preg_split( '/[ \t]+/', trim( $info ) );
			$lang  = isset( $parts[0] ) ? $parts[0] : '';
		}
		$class  = 'mdp-code';
		$attr   = '';
		if ( '' !== $lang ) {
			$class .= ' language-' . $this->slugClass( $lang );
			$attr   = ' data-lang="' . $this->esc( $lang ) . '"';
		}
		if ( '' !== trim( $info ) ) {
			$attr .= ' data-info="' . $this->esc( trim( $info ) ) . '"';
		}
		return '<pre class="mdp-pre"><code class="' . $this->esc( $class ) . '"' . $attr . '>' . $this->esc( $code ) . "</code></pre>\n";
	}

	/**
	 * 清理 class 名。
	 *
	 * @param string $text 文本。
	 * @return string
	 */
	protected function slugClass( $text ) {
		$text = strtolower( $text );
		$text = preg_replace( '/[^a-z0-9_+#.-]+/', '-', $text );
		return trim( $text, '-' );
	}

	/**
	 * 引用块。
	 *
	 * @param array  $lines 行。
	 * @param int    $i     位置。
	 * @param string $html  输出（引用）。
	 * @return int
	 */
	protected function parseBlockquote( array $lines, $i, &$html ) {
		$count = count( $lines );
		$buf   = array();

		while ( $i < $count ) {
			$line = $lines[ $i ];
			if ( preg_match( '/^ {0,3}>[ \t]?(.*)$/', $line, $m ) ) {
				$buf[] = $m[1];
				$i++;
				continue;
			}
			if ( $this->isBlank( $line ) ) {
				// 空行后若仍是引用行则继续。
				if ( $i + 1 < $count && preg_match( '/^ {0,3}>/', $lines[ $i + 1 ] ) ) {
					$buf[] = '';
					$i++;
					continue;
				}
				break;
			}
			if ( $this->interruptsParagraph( $line ) ) {
				break;
			}
			// 懒惰续行。
			$buf[] = $line;
			$i++;
		}

		$html .= "<blockquote>\n" . $this->parseBlocks( $buf ) . "</blockquote>\n";

		return $i;
	}

	/**
	 * 列表。
	 *
	 * @param array  $lines 行。
	 * @param int    $i     位置。
	 * @param string $html  输出（引用）。
	 * @return int
	 */
	protected function parseList( array $lines, $i, &$html ) {
		$count   = count( $lines );
		$type    = null;
		$start   = 1;
		$items   = array();
		$loose   = false;

		while ( $i < $count ) {
			$line = $lines[ $i ];

			if ( ! preg_match( '/^( {0,3})([-+*]|\d{1,9}[.)])(?:[ \t]+(.*)|[ \t]*)$/', $line, $m ) ) {
				break;
			}

			$marker     = $m[2];
			$itemType   = ctype_digit( substr( $marker, 0, 1 ) ) ? 'ol' : 'ul';
			$markerPos  = strlen( $m[1] );
			$spaces     = strlen( $line ) - $markerPos - strlen( $marker );
			$firstText  = isset( $m[3] ) ? $m[3] : '';
			$spaces     = strlen( $line ) - $markerPos - strlen( $marker );
			$afterMark  = substr( $line, $markerPos + strlen( $marker ) );
			$leadSpace  = strspn( $afterMark, ' ' );
			$contentCol = $markerPos + strlen( $marker ) + min( $leadSpace, 4 );
			if ( $leadSpace === 0 ) {
				$contentCol = $markerPos + strlen( $marker ) + 1;
			}

			if ( null === $type ) {
				$type = $itemType;
				if ( 'ol' === $type ) {
					$start = (int) $marker;
				}
			} elseif ( $itemType !== $type ) {
				break;
			}

			$raw         = array( $firstText );
			$internal    = false;
			$blankRun    = 0;
			$i++;

			while ( $i < $count ) {
				$l = $lines[ $i ];

				if ( $this->isBlank( $l ) ) {
					$raw[] = '';
					$blankRun++;
					$i++;
					continue;
				}

				$indent = $this->indentOf( $l );

				if ( $indent >= $contentCol ) {
					if ( $blankRun > 0 ) {
						$internal = true;
					}
					$blankRun = 0;
					$raw[]    = substr( $l, $contentCol );
					$i++;
					continue;
				}

				// 同级新条目。
				if ( preg_match( '/^ {0,3}([-+*]|\d{1,9}[.)])(?:[ \t]+|$)/', $l, $nm ) ) {
					if ( $blankRun > 0 ) {
						$nextType = ctype_digit( substr( $nm[1], 0, 1 ) ) ? 'ol' : 'ul';
						// 只有同类型列表之间的空行才让列表变“松”。
						if ( $nextType === $type ) {
							$loose = true;
						}
					}
					break;
				}

				if ( $blankRun > 0 ) {
					// 空行之后的非缩进行：本条目结束，列表也结束。
					break;
				}

				if ( $this->interruptsParagraph( $l ) ) {
					break;
				}

				// 懒惰续行。
				$raw[] = ltrim( $l );
				$i++;
			}

			// 去掉尾部空行。
			while ( ! empty( $raw ) && '' === trim( end( $raw ) ) ) {
				array_pop( $raw );
			}

			$items[] = array(
				'raw'      => $raw,
				'internal' => $internal,
			);
		}

		if ( empty( $items ) ) {
			return $i;
		}

		foreach ( $items as $item ) {
			if ( ! empty( $item['internal'] ) ) {
				$loose = true;
			}
		}

		$tag   = ( 'ol' === $type ) ? 'ol' : 'ul';
		$attr  = ( 'ol' === $type && 1 !== $start ) ? ' start="' . (int) $start . '"' : '';
		$out   = '<' . $tag . $attr . ">\n";

		foreach ( $items as $item ) {
			$raw     = $item['raw'];
			$task    = '';
			if ( $this->options['tasklists'] && ! empty( $raw ) && preg_match( '/^\[([ xX])\][ \t]+/', $raw[0], $tm ) ) {
				$checked = ( 'x' === strtolower( $tm[1] ) );
				$raw[0]  = preg_replace( '/^\[[ xX]\][ \t]+/', '', $raw[0] );
				$task    = '<input type="checkbox" class="mdp-task" disabled="disabled"' . ( $checked ? ' checked="checked"' : '' ) . ' /> ';
			}

			$content = $this->parseBlocks( $raw );

			if ( ! $loose ) {
				$content = preg_replace( '/^<p>(.*?)<\/p>\n?/s', '$1', $content, 1 );
				$content = preg_replace( '/\n<p>(.*?)<\/p>$/', "\n" . '$1', $content, 1 );
			}

			$liClass = ( '' !== $task ) ? ' class="mdp-task-item"' : '';

			$out .= '<li' . $liClass . '>' . $task . trim( $content ) . "</li>\n";
		}

		$out  .= '</' . $tag . ">\n";
		$html .= $out;

		return $i;
	}

	/**
	 * 判断是否为表格分隔行。
	 *
	 * @param string $line 行。
	 * @return bool
	 */
	protected function isTableDelimiter( $line ) {
		$line = trim( $line );
		if ( '' === $line ) {
			return false;
		}
		if ( strpos( $line, '-' ) === false ) {
			return false;
		}
		$cells = $this->splitTableRow( $line );
		if ( empty( $cells ) ) {
			return false;
		}
		foreach ( $cells as $cell ) {
			if ( ! preg_match( '/^:?-+:?$/', trim( $cell ) ) ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * 拆分表格行。
	 *
	 * @param string $line 行。
	 * @return array
	 */
	protected function splitTableRow( $line ) {
		$line = trim( $line );
		$line = preg_replace( '/^\|/', '', $line );
		$line = preg_replace( '/\|[ \t]*$/', '', $line );

		$cells = array();
		$buf   = '';
		$len   = strlen( $line );
		for ( $i = 0; $i < $len; $i++ ) {
			$ch = $line[ $i ];
			if ( '\\' === $ch && $i + 1 < $len && '|' === $line[ $i + 1 ] ) {
				$buf .= '|';
				$i++;
				continue;
			}
			if ( '|' === $ch ) {
				$cells[] = $buf;
				$buf     = '';
				continue;
			}
			$buf .= $ch;
		}
		$cells[] = $buf;

		return $cells;
	}

	/**
	 * 表格。
	 *
	 * @param array  $lines 行。
	 * @param int    $i     位置。
	 * @param string $html  输出（引用）。
	 * @return int
	 */
	protected function parseTable( array $lines, $i, &$html ) {
		$count = count( $lines );
		$head  = $this->splitTableRow( $lines[ $i ] );
		$delim = $this->splitTableRow( $lines[ $i + 1 ] );
		$i    += 2;

		$align = array();
		foreach ( $delim as $cell ) {
			$cell    = trim( $cell );
			$left    = ( 0 === strpos( $cell, ':' ) );
			$right   = ( substr( $cell, -1 ) === ':' );
			if ( $left && $right ) {
				$align[] = 'center';
			} elseif ( $right ) {
				$align[] = 'right';
			} elseif ( $left ) {
				$align[] = 'left';
			} else {
				$align[] = '';
			}
		}

		$cols = max( count( $head ), count( $align ) );

		$out = "<div class=\"mdp-table-wrap\">\n<table>\n<thead>\n<tr>";
		for ( $c = 0; $c < $cols; $c++ ) {
			$text  = isset( $head[ $c ] ) ? trim( $head[ $c ] ) : '';
			$a     = isset( $align[ $c ] ) ? $align[ $c ] : '';
			$attr  = ( '' !== $a ) ? ' style="text-align:' . $a . '"' : '';
			$out  .= '<th' . $attr . '>' . $this->defer( $text ) . '</th>';
		}
		$out .= "</tr>\n</thead>\n<tbody>\n";

		$rows = 0;
		while ( $i < $count ) {
			$line = $lines[ $i ];
			if ( $this->isBlank( $line ) || strpos( $line, '|' ) === false ) {
				break;
			}
			if ( $this->interruptsParagraph( $line ) ) {
				break;
			}
			$cells = $this->splitTableRow( $line );
			$out  .= '<tr>';
			for ( $c = 0; $c < $cols; $c++ ) {
				$text  = isset( $cells[ $c ] ) ? trim( $cells[ $c ] ) : '';
				$a     = isset( $align[ $c ] ) ? $align[ $c ] : '';
				$attr  = ( '' !== $a ) ? ' style="text-align:' . $a . '"' : '';
				$out  .= '<td' . $attr . '>' . $this->defer( $text ) . '</td>';
			}
			$out .= "</tr>\n";
			$rows++;
			$i++;
		}

		$out .= "</tbody>\n</table>\n</div>\n";

		if ( $rows > 0 ) {
			$html .= $out;
		}

		return $i;
	}

	/**
	 * HTML 块。
	 *
	 * @param array  $lines 行。
	 * @param int    $i     位置。
	 * @param string $html  输出（引用）。
	 * @return int
	 */
	protected function parseHtmlBlock( array $lines, $i, &$html ) {
		$count = count( $lines );
		$buf   = array();

		while ( $i < $count ) {
			if ( $this->isBlank( $lines[ $i ] ) ) {
				break;
			}
			$buf[] = $lines[ $i ];
			$i++;
		}

		$html .= implode( "\n", $buf ) . "\n";

		return $i;
	}

	/**
	 * 收集链接引用定义。
	 *
	 * @param array  $lines 行。
	 * @param int    $i     位置。
	 * @param string $label 标签。
	 * @param string $rest  剩余内容。
	 * @return int 消费的行数，0 表示不是定义。
	 */
	protected function consumeLinkRef( array $lines, $i, $label, $rest ) {
		$count  = count( $lines );
		$rest   = trim( $rest );
		$url    = '';
		$title  = '';
		$used   = 1;

		if ( preg_match( '/^<([^>]*)>(.*)$/s', $rest, $m ) ) {
			$url = $m[1];
			$rest = trim( $m[2] );
		} elseif ( preg_match( '/^(\S+)(.*)$/s', $rest, $m ) ) {
			$url  = $m[1];
			$rest = trim( $m[2] );
		} else {
			return 0;
		}

		$rest = trim( $rest );
		if ( '' !== $rest ) {
			$q = substr( $rest, 0, 1 );
			if ( ( '"' === $q || "'" === $q ) && substr( $rest, -1 ) === $q && strlen( $rest ) >= 2 ) {
				$title = substr( $rest, 1, -1 );
			} elseif ( '(' === $q && substr( $rest, -1 ) === ')' ) {
				$title = substr( $rest, 1, -1 );
			} else {
				// 标题可能位于下一行。
				if ( $i + 1 < $count ) {
					$next = trim( $lines[ $i + 1 ] );
					$q2   = substr( $next, 0, 1 );
					if ( ( '"' === $q2 || "'" === $q2 ) && substr( $next, -1 ) === $q2 && strlen( $next ) >= 2 ) {
						$title = substr( $next, 1, -1 );
						$used++;
					} elseif ( '(' === $q2 && substr( $next, -1 ) === ')' ) {
						$title = substr( $next, 1, -1 );
						$used++;
					}
				}
			}
		}

		$key = $this->refKey( $label );
		if ( '' !== $key && ! isset( $this->refs[ $key ] ) ) {
			$this->refs[ $key ] = array(
				'url'   => $this->unescapeUrl( $url ),
				'title' => $title,
			);
		}

		return $used;
	}

	/**
	 * 收集脚注定义。
	 *
	 * @param array  $lines 行。
	 * @param int    $i     位置。
	 * @param string $id    脚注 id。
	 * @param string $first 首行剩余内容。
	 * @return int 新位置。
	 */
	protected function consumeFootnote( array $lines, $i, $id, $first ) {
		$count = count( $lines );
		$buf   = array( $first );
		$i++;

		while ( $i < $count ) {
			$line = $lines[ $i ];
			if ( $this->isBlank( $line ) ) {
				// 空行后若继续缩进则属于脚注。
				if ( $i + 1 < $count && preg_match( '/^ {2,}\S/', $lines[ $i + 1 ] ) ) {
					$buf[] = '';
					$i++;
					continue;
				}
				break;
			}
			if ( preg_match( '/^ {2,}(.*)$/', $line, $m ) ) {
				$buf[] = $m[1];
				$i++;
				continue;
			}
			break;
		}

		$key = strtolower( trim( $id ) );
		if ( ! isset( $this->footnotes[ $key ] ) ) {
			$this->footnotes[ $key ] = $this->parseBlocks( $buf );
		}

		return $i;
	}

	// ---------------------------------------------------------------------
	// 延迟行内解析。
	// ---------------------------------------------------------------------

	/**
	 * 登记一段稍后解析的行内文本。
	 *
	 * @param string $text 文本。
	 * @return string 占位符。
	 */
	protected function defer( $text ) {
		$index                   = count( $this->deferred );
		$this->deferred[ $index ] = $text;
		return self::DEF . $index . self::DEF;
	}

	/**
	 * 解析所有延迟的行内文本。
	 *
	 * @param string $html HTML。
	 * @return string
	 */
	protected function resolveDeferred( $html ) {
		if ( strpos( $html, self::DEF ) === false ) {
			return $html;
		}
		$out = preg_replace_callback(
			'/' . self::DEF . '(\d+)' . self::DEF . '/',
			array( $this, 'deferredCallback' ),
			$html
		);
		return ( null === $out ) ? str_replace( self::DEF, '', $html ) : $out;
	}

	/**
	 * 延迟解析回调。
	 *
	 * @param array $m 匹配。
	 * @return string
	 */
	public function deferredCallback( $m ) {
		$index = (int) $m[1];
		if ( ! isset( $this->deferred[ $index ] ) ) {
			return '';
		}
		return $this->parseInline( $this->deferred[ $index ] );
	}

	// ---------------------------------------------------------------------
	// 行内解析。
	// ---------------------------------------------------------------------

	/**
	 * 解析行内语法。
	 *
	 * @param string $text  文本。
	 * @param int    $depth 递归深度。
	 * @return string
	 */
	protected function parseInline( $text, $depth = 0 ) {
		if ( '' === $text ) {
			return '';
		}

		$ph   = array();
		$text = $this->protectCodeSpans( $text, $ph );
		$text = $this->protectEscapes( $text, $ph );
		$text = $this->protectRawHtml( $text, $ph, $depth );
		$text = $this->protectFootnoteRefs( $text, $ph );
		$text = $this->protectLinks( $text, $ph, $depth );
		$text = $this->escapeText( $text );
		$text = $this->parseEmphasis( $text );
		$text = $this->applyBreaks( $text );

		return $this->restore( $text, $ph );
	}

	/**
	 * 保存一段 HTML，返回占位符。
	 *
	 * @param string $html HTML。
	 * @param array  $ph   占位符表（引用）。
	 * @return string
	 */
	protected function placeholder( $html, &$ph ) {
		$index      = count( $ph );
		$ph[ $index ] = $html;
		return self::PH . $index . self::PH;
	}

	/**
	 * 还原占位符。
	 *
	 * @param string $text 文本。
	 * @param array  $ph   占位符表。
	 * @return string
	 */
	protected function restore( $text, $ph ) {
		if ( empty( $ph ) ) {
			return $text;
		}
		$out = preg_replace_callback(
			'/' . self::PH . '(\d+)' . self::PH . '/',
			function ( $m ) use ( $ph ) {
				$i = (int) $m[1];
				return isset( $ph[ $i ] ) ? $ph[ $i ] : '';
			},
			$text
		);
		return ( null === $out ) ? $text : $out;
	}

	/**
	 * 代码段。
	 *
	 * @param string $text 文本。
	 * @param array  $ph   占位符表（引用）。
	 * @return string
	 */
	protected function protectCodeSpans( $text, &$ph ) {
		if ( strpos( $text, '`' ) === false ) {
			return $text;
		}

		$out = '';
		$len = strlen( $text );
		$i   = 0;

		while ( $i < $len ) {
			$pos = strpos( $text, '`', $i );
			if ( false === $pos ) {
				$out .= substr( $text, $i );
				break;
			}
			// 被转义的反引号留给转义处理。
			if ( $pos > 0 && '\\' === $text[ $pos - 1 ] ) {
				$out .= substr( $text, $i, $pos - $i + 1 );
				$i    = $pos + 1;
				continue;
			}

			$runLen = strspn( $text, '`', $pos );
			$needle = str_repeat( '`', $runLen );
			$search = $pos + $runLen;
			$end    = false;

			while ( false !== ( $p = strpos( $text, $needle, $search ) ) ) {
				$before = ( $p > 0 ) ? $text[ $p - 1 ] : '';
				$after  = ( $p + $runLen < $len ) ? $text[ $p + $runLen ] : '';
				if ( '`' !== $before && '`' !== $after ) {
					$end = $p;
					break;
				}
				$search = $p + 1;
			}

			if ( false === $end ) {
				$out .= substr( $text, $i, $pos - $i ) . $needle;
				$i    = $pos + $runLen;
				continue;
			}

			$code = substr( $text, $pos + $runLen, $end - $pos - $runLen );
			$code = str_replace( "\n", ' ', $code );
			if ( strlen( $code ) > 2 && ' ' === $code[0] && ' ' === substr( $code, -1 ) && trim( $code ) !== '' ) {
				$code = substr( $code, 1, -1 );
			}

			$out .= substr( $text, $i, $pos - $i );
			$out .= $this->placeholder( '<code>' . $this->esc( $code ) . '</code>', $ph );
			$i    = $end + $runLen;
		}

		return $out;
	}

	/**
	 * 反斜杠转义。
	 *
	 * @param string $text 文本。
	 * @param array  $ph   占位符表（引用）。
	 * @return string
	 */
	protected function protectEscapes( $text, &$ph ) {
		if ( strpos( $text, '\\' ) === false ) {
			return $text;
		}
		return preg_replace_callback(
			'/\\\\([!"#$%&\'()*+,\-.\/:;<=>?@\[\\\\\]^_`{|}~])/',
			function ( $m ) use ( &$ph ) {
				return $this->placeholder( $this->esc( $m[1] ), $ph );
			},
			$text
		);
	}

	/**
	 * 行内 HTML 与自动链接。
	 *
	 * @param string $text  文本。
	 * @param array  $ph    占位符表（引用）。
	 * @param int    $depth 深度。
	 * @return string
	 */
	protected function protectRawHtml( $text, &$ph, $depth ) {
		if ( strpos( $text, '<' ) === false ) {
			return $text;
		}

		// 自动链接：<scheme:...>。
		$text = preg_replace_callback(
			'/<([a-zA-Z][a-zA-Z0-9+.\-]{1,31}:[^<>\s]*)>/',
			function ( $m ) use ( &$ph ) {
				$url = $this->sanitizeUrl( $m[1] );
				return $this->placeholder( '<a href="' . $this->esc( $url ) . '">' . $this->esc( $m[1] ) . '</a>', $ph );
			},
			$text
		);

		// 邮箱自动链接。
		$text = preg_replace_callback(
			'/<([^\s<>@]+@[^\s<>@]+\.[^\s<>@]+)>/',
			function ( $m ) use ( &$ph ) {
				return $this->placeholder( '<a href="mailto:' . $this->esc( $m[1] ) . '">' . $this->esc( $m[1] ) . '</a>', $ph );
			},
			$text
		);

		if ( ! $this->options['allow_html'] ) {
			return $text;
		}

		// 注释、处理指令、CDATA、标签。
		$text = preg_replace_callback(
			'/<!--.*?-->|<\?.*?\?>|<!\[CDATA\[.*?\]\]>|<\/?[a-zA-Z][a-zA-Z0-9:\-]*(?:\s[^<>]*)?\/?>/s',
			function ( $m ) use ( &$ph ) {
				return $this->placeholder( $m[0], $ph );
			},
			$text
		);

		return $text;
	}

	/**
	 * 脚注引用。
	 *
	 * @param string $text 文本。
	 * @param array  $ph   占位符表（引用）。
	 * @return string
	 */
	protected function protectFootnoteRefs( $text, &$ph ) {
		if ( ! $this->options['footnotes'] || strpos( $text, '[^' ) === false ) {
			return $text;
		}
		return preg_replace_callback(
			'/\[\^([^\]\s]+)\]/',
			function ( $m ) use ( &$ph ) {
				return $this->placeholder( '<sup class="mdp-fn-ref" data-id="' . $this->esc( $m[1] ) . '"></sup>', $ph );
			},
			$text
		);
	}

	/**
	 * 链接与图片。
	 *
	 * @param string $text  文本。
	 * @param array  $ph    占位符表（引用）。
	 * @param int    $depth 深度。
	 * @return string
	 */
	protected function protectLinks( $text, &$ph, $depth ) {
		if ( strpos( $text, '[' ) === false ) {
			return $this->protectBareLinks( $text, $ph );
		}

		$out = '';
		$len = strlen( $text );
		$i   = 0;

		while ( $i < $len ) {
			$ch = $text[ $i ];

			$isImage = false;
			$start   = $i;

			if ( '!' === $ch && $i + 1 < $len && '[' === $text[ $i + 1 ] ) {
				$isImage = true;
				$start   = $i + 1;
			} elseif ( '[' !== $ch ) {
				$out .= $ch;
				$i++;
				continue;
			}

			$labelEnd = $this->findClosingBracket( $text, $start );
			if ( false === $labelEnd ) {
				$out .= substr( $text, $i, $start - $i + 1 );
				$i    = $start + 1;
				continue;
			}

			$label = substr( $text, $start + 1, $labelEnd - $start - 1 );
			$after = $labelEnd + 1;
			$done  = false;

			// 行内链接：](url "title")
			if ( $after < $len && '(' === $text[ $after ] ) {
				$close = $this->findClosingParen( $text, $after );
				if ( false !== $close ) {
					$inside          = substr( $text, $after + 1, $close - $after - 1 );
					$dest            = $this->splitDestination( $inside );
					$out            .= $this->placeholder( $this->makeLink( $dest[0], $dest[1], $label, $isImage, $depth ), $ph );
					$i               = $close + 1;
					$done            = true;
				}
			}

			if ( ! $done ) {
				// 引用式：[label][ref] / [label][] / [label]
				$ref  = $label;
				$next = $after;
				if ( $after < $len && '[' === $text[ $after ] ) {
					$refEnd = $this->findClosingBracket( $text, $after );
					if ( false !== $refEnd ) {
						$inner = substr( $text, $after + 1, $refEnd - $after - 1 );
						$ref   = ( '' === $inner ) ? $label : $inner;
						$next  = $refEnd + 1;
					}
				}
				$key = $this->refKey( $ref );
				if ( isset( $this->refs[ $key ] ) ) {
					$def  = $this->refs[ $key ];
					$out .= $this->placeholder( $this->makeLink( $def['url'], $def['title'], $label, $isImage, $depth ), $ph );
					$i    = $next;
					$done = true;
				}
			}

			if ( ! $done ) {
				$out .= substr( $text, $i, $labelEnd - $i + 1 );
				$i    = $labelEnd + 1;
			}
		}

		return $this->protectBareLinks( $out, $ph );
	}

	/**
	 * 裸 URL 自动链接。
	 *
	 * @param string $text 文本。
	 * @param array  $ph   占位符表（引用）。
	 * @return string
	 */
	protected function protectBareLinks( $text, &$ph ) {
		if ( ! $this->options['autolink'] || strpos( $text, '://' ) === false && strpos( $text, 'www.' ) === false ) {
			return $text;
		}
		return preg_replace_callback(
			'~(?<![\w/:\-])((?:https?://|www\.)[^\s<>"' . "\x1A" . ']+)~u',
			function ( $m ) use ( &$ph ) {
				$url   = $m[1];
				$trail = '';
				while ( '' !== $url && false !== strpos( '.,;:!?、。，；：！？', substr( $url, -1 ) ) ) {
					$trail = substr( $url, -1 ) . $trail;
					$url   = substr( $url, 0, -1 );
				}
				// 去掉多余右括号。
				while ( substr( $url, -1 ) === ')' && substr_count( $url, ')' ) > substr_count( $url, '(' ) ) {
					$trail = ')' . $trail;
					$url   = substr( $url, 0, -1 );
				}
				if ( '' === $url ) {
					return $m[0];
				}
				$href = ( 0 === strpos( $url, 'www.' ) ) ? 'http://' . $url : $url;
				$href = $this->sanitizeUrl( $href );
				return $this->placeholder( '<a href="' . $this->esc( $href ) . '">' . $this->esc( $url ) . '</a>', $ph ) . $trail;
			},
			$text
		);
	}

	/**
	 * 查找配对的 ]。
	 *
	 * @param string $text 文本。
	 * @param int    $open [ 的位置。
	 * @return int|false
	 */
	protected function findClosingBracket( $text, $open ) {
		$len   = strlen( $text );
		$depth = 0;
		for ( $i = $open; $i < $len; $i++ ) {
			$ch = $text[ $i ];
			if ( '[' === $ch ) {
				$depth++;
			} elseif ( ']' === $ch ) {
				$depth--;
				if ( 0 === $depth ) {
					return $i;
				}
			} elseif ( "\n" === $ch && $i + 1 < $len && "\n" === $text[ $i + 1 ] ) {
				return false;
			}
		}
		return false;
	}

	/**
	 * 查找配对的 )。
	 *
	 * @param string $text 文本。
	 * @param int    $open ( 的位置。
	 * @return int|false
	 */
	protected function findClosingParen( $text, $open ) {
		$len   = strlen( $text );
		$depth = 0;
		for ( $i = $open; $i < $len; $i++ ) {
			$ch = $text[ $i ];
			if ( '(' === $ch ) {
				$depth++;
			} elseif ( ')' === $ch ) {
				$depth--;
				if ( 0 === $depth ) {
					return $i;
				}
			} elseif ( "\n" === $ch && $i + 1 < $len && "\n" === $text[ $i + 1 ] ) {
				return false;
			}
		}
		return false;
	}

	/**
	 * 拆分链接目标与标题。
	 *
	 * @param string $inside 括号中的内容。
	 * @return array array( url, title )
	 */
	protected function splitDestination( $inside ) {
		$inside = trim( $inside );
		$url    = '';
		$rest   = '';

		if ( preg_match( '/^<([^>]*)>\s*(.*)$/s', $inside, $m ) ) {
			$url  = $m[1];
			$rest = trim( $m[2] );
		} elseif ( preg_match( '/^(\S+)\s*(.*)$/s', $inside, $m ) ) {
			$url  = $m[1];
			$rest = trim( $m[2] );
		}

		$title = '';
		if ( '' !== $rest ) {
			$q = substr( $rest, 0, 1 );
			if ( ( '"' === $q || "'" === $q ) && substr( $rest, -1 ) === $q && strlen( $rest ) >= 2 ) {
				$title = substr( $rest, 1, -1 );
			} elseif ( '(' === $q && substr( $rest, -1 ) === ')' && strlen( $rest ) >= 2 ) {
				$title = substr( $rest, 1, -1 );
			}
		}

		return array( $this->unescapeUrl( $url ), $title );
	}

	/**
	 * 生成链接/图片 HTML。
	 *
	 * @param string $url     地址。
	 * @param string $title   标题。
	 * @param string $label   文本。
	 * @param bool   $isImage 是否图片。
	 * @param int    $depth   深度。
	 * @return string
	 */
	protected function makeLink( $url, $title, $label, $isImage, $depth ) {
		$url   = $this->sanitizeUrl( $url );
		$title = trim( (string) $title );
		$attr  = ( '' !== $title ) ? ' title="' . $this->esc( $title ) . '"' : '';

		if ( $isImage ) {
			$alt = $this->plainText( $label );
			return '<img src="' . $this->esc( $url ) . '" alt="' . $this->esc( $alt ) . '"' . $attr . ' />';
		}

		// 链接内不允许再嵌套链接。
		$inner = ( $depth > 0 ) ? $this->escapeText( $label ) : $this->parseInline( $label, $depth + 1 );

		return '<a href="' . $this->esc( $url ) . '"' . $attr . '>' . $inner . '</a>';
	}

	/**
	 * 强调/加粗/删除线。
	 *
	 * @param string $text 文本。
	 * @return string
	 */
	protected function parseEmphasis( $text ) {
		if ( ! preg_match( '/[*_~]/', $text ) ) {
			return $text;
		}

		$nodes  = array();
		$offset = 0;
		$len    = strlen( $text );

		if ( preg_match_all( '/([*_~]+)/', $text, $matches, PREG_OFFSET_CAPTURE ) ) {
			foreach ( $matches[1] as $hit ) {
				$run = $hit[0];
				$pos = $hit[1];

				if ( $pos > $offset ) {
					$nodes[] = array( 't' => 'text', 'v' => substr( $text, $offset, $pos - $offset ) );
				}

				$prevChar = ( $pos > 0 ) ? $text[ $pos - 1 ] : "\n";
				$nextPos  = $pos + strlen( $run );
				$nextChar = ( $nextPos < $len ) ? $text[ $nextPos ] : "\n";
				$char     = $run[0];

				$prevWs    = ( '' === trim( $prevChar ) );
				$nextWs    = ( '' === trim( $nextChar ) );
				$prevPunct = $this->isPunct( $prevChar );
				$nextPunct = $this->isPunct( $nextChar );

				$leftFlank  = ( ! $nextWs ) && ( ! $nextPunct || $prevWs || $prevPunct );
				$rightFlank = ( ! $prevWs ) && ( ! $prevPunct || $nextWs || $nextPunct );

				if ( '_' === $char ) {
					$canOpen  = $leftFlank && ( ! $rightFlank || $prevPunct );
					$canClose = $rightFlank && ( ! $leftFlank || $nextPunct );
				} else {
					$canOpen  = $leftFlank;
					$canClose = $rightFlank;
				}

				if ( '~' === $char && 2 !== strlen( $run ) ) {
					$nodes[] = array( 't' => 'text', 'v' => $run );
					$offset  = $nextPos;
					continue;
				}

				$nodes[] = array(
					't' => 'd',
					'c' => $char,
					'n' => strlen( $run ),
					'o' => $canOpen,
					'x' => $canClose,
					'b' => '',
					'a' => '',
				);

				$offset = $nextPos;
			}
		}

		if ( $offset < $len ) {
			$nodes[] = array( 't' => 'text', 'v' => substr( $text, $offset ) );
		}
		if ( empty( $nodes ) ) {
			return $text;
		}

		$stack = array();
		$total = count( $nodes );

		for ( $i = 0; $i < $total; $i++ ) {
			if ( 'd' !== $nodes[ $i ]['t'] ) {
				continue;
			}

			if ( $nodes[ $i ]['x'] ) {
				$guard = 0;
				while ( $nodes[ $i ]['n'] > 0 && $guard < 64 ) {
					$guard++;
					$found = -1;
					for ( $k = count( $stack ) - 1; $k >= 0; $k-- ) {
						$oi = $stack[ $k ];
						if ( $nodes[ $oi ]['n'] <= 0 ) {
							array_splice( $stack, $k, 1 );
							continue;
						}
						if ( $nodes[ $oi ]['c'] === $nodes[ $i ]['c'] ) {
							$found = $k;
							break;
						}
					}
					if ( $found < 0 ) {
						break;
					}

					$oi  = $stack[ $found ];
					$use = ( $nodes[ $oi ]['n'] >= 2 && $nodes[ $i ]['n'] >= 2 ) ? 2 : 1;
					$tag = ( '~' === $nodes[ $i ]['c'] ) ? 'del' : ( ( 2 === $use ) ? 'strong' : 'em' );
					if ( '~' === $nodes[ $i ]['c'] && ( $nodes[ $oi ]['n'] < 2 || $nodes[ $i ]['n'] < 2 ) ) {
						break;
					}

					$nodes[ $oi ]['n'] -= $use;
					$nodes[ $oi ]['a']  = '<' . $tag . '>' . $nodes[ $oi ]['a'];
					$nodes[ $i ]['n']  -= $use;
					$nodes[ $i ]['b']  .= '</' . $tag . '>';

					if ( $nodes[ $oi ]['n'] <= 0 ) {
						array_splice( $stack, $found, 1 );
					}
				}
			}

			if ( $nodes[ $i ]['o'] && $nodes[ $i ]['n'] > 0 ) {
				$stack[] = $i;
			}
		}

		$out = '';
		foreach ( $nodes as $node ) {
			if ( 'text' === $node['t'] ) {
				$out .= $node['v'];
				continue;
			}
			$out .= $node['b'] . str_repeat( $node['c'], max( 0, $node['n'] ) ) . $node['a'];
		}

		return $out;
	}

	/**
	 * 是否标点。
	 *
	 * @param string $char 字符。
	 * @return bool
	 */
	protected function isPunct( $char ) {
		if ( '' === $char || "\n" === $char ) {
			return false;
		}
		return (bool) preg_match( '/[\p{P}\p{S}]/u', $char );
	}

	/**
	 * 换行处理。
	 *
	 * @param string $text 文本。
	 * @return string
	 */
	protected function applyBreaks( $text ) {
		if ( $this->options['breaks'] ) {
			return str_replace( "\n", "<br />\n", $text );
		}
		// 行尾两个以上空格或反斜杠 => 硬换行。
		$text = preg_replace( '/ {2,}\n/', "<br />\n", $text );
		$text = preg_replace( '/\\\\\n/', "<br />\n", $text );
		return $text;
	}

	/**
	 * 转义文本（保留占位符）。
	 *
	 * @param string $text 文本。
	 * @return string
	 */
	protected function escapeText( $text ) {
		$text = preg_replace( '/&(?!#?[A-Za-z0-9]{1,32};)/', '&amp;', $text );
		$text = str_replace( array( '<', '>' ), array( '&lt;', '&gt;' ), $text );
		return $text;
	}

	/**
	 * HTML 转义。
	 *
	 * @param string $text 文本。
	 * @return string
	 */
	protected function esc( $text ) {
		return htmlspecialchars( (string) $text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' );
	}

	/**
	 * 链接协议过滤。
	 *
	 * @param string $url 地址。
	 * @return string
	 */
	protected function sanitizeUrl( $url ) {
		$url = trim( (string) $url );
		if ( ! $this->options['sanitize_urls'] ) {
			return $url;
		}
		$probe = preg_replace( '/[\x00-\x20]+/', '', strtolower( $url ) );
		if ( preg_match( '/^(?:javascript|vbscript|file):/', $probe ) ) {
			return '#';
		}
		if ( 0 === strpos( $probe, 'data:' ) && ! preg_match( '#^data:image/(?:png|jpe?g|gif|webp|svg\+xml);#', $probe ) ) {
			return '#';
		}
		return $url;
	}

	/**
	 * 还原 URL 中的反斜杠转义。
	 *
	 * @param string $url 地址。
	 * @return string
	 */
	protected function unescapeUrl( $url ) {
		return preg_replace( '/\\\\([\\\\()<> ])/', '$1', (string) $url );
	}

	/**
	 * 引用定义 key 规范化。
	 *
	 * @param string $label 标签。
	 * @return string
	 */
	protected function refKey( $label ) {
		$label = preg_replace( '/\s+/u', ' ', (string) $label );
		return strtolower( trim( $label ) );
	}

	// ---------------------------------------------------------------------
	// 脚注与目录。
	// ---------------------------------------------------------------------

	/**
	 * 组装脚注区。
	 *
	 * @param string $html 正文 HTML。
	 * @return string
	 */
	protected function assembleFootnotes( $html ) {
		if ( ! $this->options['footnotes'] || empty( $this->footnotes ) ) {
			return $html;
		}

		// 按出现顺序收集被引用的脚注。
		$order = array();
		if ( preg_match_all( '/<sup class="mdp-fn-ref" data-id="([^"]*)"><\/sup>/', $html, $m ) ) {
			foreach ( $m[1] as $id ) {
				$key = strtolower( html_entity_decode( $id, ENT_QUOTES, 'UTF-8' ) );
				if ( isset( $this->footnotes[ $key ] ) && ! isset( $order[ $key ] ) ) {
					$order[ $key ] = true;
				}
			}
		}

		if ( empty( $order ) ) {
			return $html;
		}

		$list = '';
		foreach ( array_keys( $order ) as $key ) {
			$body = $this->resolveDeferred( $this->footnotes[ $key ] );
			$list .= '<li id="fn:' . $this->esc( $key ) . '" class="mdp-fn-item">'
				. '<span class="mdp-fn-num" data-id="' . $this->esc( $key ) . '"></span> '
				. $body
				. ' <a class="mdp-fn-back" href="#fnref:' . $this->esc( $key ) . '">&#8617;</a></li>' . "\n";
		}

		$html .= "\n" . '<div class="mdp-footnotes"><hr class="mdp-fn-sep" /><ol class="mdp-fn-list">' . "\n" . $list . "</ol></div>\n";

		return $html;
	}

	/**
	 * 为脚注引用与条目编号。
	 *
	 * @param string $html HTML。
	 * @return string 由 cleanup 阶段调用。
	 */
	protected function numberFootnotes( $html ) {
		if ( strpos( $html, 'mdp-fn-ref' ) === false && strpos( $html, 'mdp-fn-num' ) === false ) {
			return $html;
		}

		$numbers = array();
		$counts  = array();
		$next    = 1;

		$html = preg_replace_callback(
			'/<sup class="mdp-fn-ref" data-id="([^"]*)"><\/sup>/',
			function ( $m ) use ( &$numbers, &$counts, &$next ) {
				$id  = html_entity_decode( $m[1], ENT_QUOTES, 'UTF-8' );
				$key = strtolower( $id );
				if ( ! isset( $this->footnotes[ $key ] ) ) {
					return '[' . '^' . $id . ']';
				}
				if ( ! isset( $numbers[ $key ] ) ) {
					$numbers[ $key ] = $next;
					$counts[ $key ]  = 0;
					$next++;
				}
				$counts[ $key ]++;
				$refId = ( 1 === $counts[ $key ] ) ? 'fnref:' . $key : 'fnref:' . $key . '-' . $counts[ $key ];
				return '<sup class="mdp-fn-ref"><a id="' . $this->esc( $refId ) . '" href="#fn:' . $this->esc( $key ) . '">' . $numbers[ $key ] . '</a></sup>';
			},
			$html
		);

		$html = preg_replace_callback(
			'/<span class="mdp-fn-num" data-id="([^"]*)"><\/span>/',
			function ( $m ) use ( &$numbers ) {
				$key = strtolower( html_entity_decode( $m[1], ENT_QUOTES, 'UTF-8' ) );
				return isset( $numbers[ $key ] ) ? '[' . $numbers[ $key ] . ']' : '';
			},
			$html
		);

		return $html;
	}

	/**
	 * 生成目录 HTML。
	 *
	 * @return string
	 */
	protected function buildTocHtml() {
		if ( empty( $this->toc ) ) {
			return '';
		}

		$min   = max( 1, (int) $this->options['toc_min'] );
		$max   = max( $min, (int) $this->options['toc_max'] );
		$items = array();
		foreach ( $this->toc as $entry ) {
			if ( $entry['level'] < $min || $entry['level'] > $max || '' === $entry['id'] ) {
				continue;
			}
			$items[] = $entry;
		}

		if ( empty( $items ) ) {
			return '';
		}

		$out   = '<nav class="mdp-toc" aria-label="' . $this->esc( $this->options['toc_title'] ) . '">' . "\n";
		$out  .= '<div class="mdp-toc-title">' . $this->esc( $this->options['toc_title'] ) . "</div>\n";

		// 先按级别构造树，再递归输出，避免跨级时标签闭合错误。
		$root          = new stdClass();
		$root->level   = 0;
		$root->id      = '';
		$root->text    = '';
		$root->children = array();
		$stack         = array( $root );

		foreach ( $items as $entry ) {
			while ( count( $stack ) > 1 && $stack[ count( $stack ) - 1 ]->level >= $entry['level'] ) {
				array_pop( $stack );
			}
			$node           = new stdClass();
			$node->level    = $entry['level'];
			$node->id       = $entry['id'];
			$node->text     = $entry['text'];
			$node->children = array();
			$stack[ count( $stack ) - 1 ]->children[] = $node;
			$stack[] = $node;
		}

		$out .= $this->renderTocNodes( $root->children, 1 );
		$out .= "</nav>\n";

		return $out;
	}

	/**
	 * 递归输出目录节点。
	 *
	 * @param array $nodes 节点。
	 * @param int   $depth 深度。
	 * @return string
	 */
	protected function renderTocNodes( array $nodes, $depth ) {
		if ( empty( $nodes ) ) {
			return '';
		}
		$out = '<ol class="mdp-toc-list mdp-toc-depth-' . (int) $depth . "\">\n";
		foreach ( $nodes as $node ) {
			$out .= '<li class="mdp-toc-item mdp-toc-h' . (int) $node->level . '">'
				. '<a href="#' . $this->esc( $node->id ) . '">' . $this->esc( $node->text ) . '</a>';
			if ( ! empty( $node->children ) ) {
				$out .= "\n" . $this->renderTocNodes( $node->children, $depth + 1 );
			} else {
				$out .= "\n";
			}
			$out .= "</li>\n";
		}
		$out .= "</ol>\n";
		return $out;
	}

	/**
	 * 替换 [TOC] 占位。
	 *
	 * @param string $html HTML。
	 * @return string
	 */
	protected function replaceTocPlaceholder( $html ) {
		if ( false === strpos( $html, '<!--mdp-toc-->' ) ) {
			return $html;
		}
		$toc = $this->buildTocHtml();
		return str_replace( '<!--mdp-toc-->', $toc, $html );
	}

	/**
	 * 收尾清理。
	 *
	 * @param string $html HTML。
	 * @return string
	 */
	protected function cleanup( $html ) {
		$html = str_replace( array( self::PH, self::DEF ), '', $html );
		$html = $this->numberFootnotes( $html );
		return trim( $html ) . "\n";
	}

	// ---------------------------------------------------------------------
	// 工具。
	// ---------------------------------------------------------------------

	/**
	 * 去掉 Markdown 标记，得到纯文本。
	 *
	 * @param string $text 文本。
	 * @return string
	 */
	public function plainText( $text ) {
		$text = (string) $text;
		$text = preg_replace( '/!\[([^\]]*)\]\([^)]*\)/', '$1', $text );
		$text = preg_replace( '/\[([^\]]*)\]\([^)]*\)/', '$1', $text );
		$text = preg_replace( '/\[([^\]]*)\]\[[^\]]*\]/', '$1', $text );
		$text = preg_replace( '/`+/', '', $text );
		$text = preg_replace( '/[*_~]{1,3}/', '', $text );
		$text = preg_replace( '/<[^>]+>/', '', $text );
		$text = preg_replace( '/\s+/u', ' ', $text );
		return trim( $text );
	}

	/**
	 * 统计字数（中英混排）。
	 *
	 * @param string $text 文本。
	 * @return int
	 */
	public function wordCount( $text ) {
		$text  = $this->plainText( $text );
		$cjk   = preg_match_all( '/[\x{4e00}-\x{9fff}\x{3040}-\x{30ff}\x{ac00}-\x{d7af}]/u', $text );
		$latin = preg_match_all( '/[A-Za-z0-9_\'\-]+/', $text );
		return (int) $cjk + (int) $latin;
	}
}
