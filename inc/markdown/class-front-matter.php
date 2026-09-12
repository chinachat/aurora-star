<?php
/**
 * Markdown 文件头部（front matter）解析。
 *
 * 支持常见的 YAML 子集：
 *
 *     ---
 *     title: 标题
 *     tags: [a, b]
 *     categories:
 *       - 教程
 *     ---
 *
 * @package Mdp
 */

defined( 'ABSPATH' ) || exit;

if ( class_exists( 'Mdp_Front_Matter', false ) ) {
	return;
}

/**
 * Front matter 工具类。
 */
class Mdp_Front_Matter {

	/**
	 * 拆分 front matter 与正文。
	 *
	 * @param string $raw 原文。
	 * @return array { has, meta, body }
	 */
	public static function split( $raw ) {
		$raw   = str_replace( array( "\r\n", "\r" ), "\n", (string) $raw );
		$raw   = preg_replace( '/^\xEF\xBB\xBF/', '', $raw );
		$lines = explode( "\n", $raw );

		$first = isset( $lines[0] ) ? trim( $lines[0] ) : '';
		if ( '---' !== $first && '+++' !== $first ) {
			return array(
				'has'  => false,
				'meta' => array(),
				'body' => $raw,
			);
		}

		$total = count( $lines );
		for ( $i = 1; $i < $total; $i++ ) {
			$t = trim( $lines[ $i ] );
			if ( '---' === $t || '...' === $t ) {
				$yaml = implode( "\n", array_slice( $lines, 1, $i - 1 ) );
				$body = implode( "\n", array_slice( $lines, $i + 1 ) );
				return array(
					'has'  => true,
					'meta' => self::parse_yaml( $yaml ),
					'body' => ltrim( $body, "\n" ),
				);
			}
		}

		return array(
			'has'  => false,
			'meta' => array(),
			'body' => $raw,
		);
	}

	/**
	 * 解析 YAML 子集。
	 *
	 * @param string $yaml 文本。
	 * @return array
	 */
	public static function parse_yaml( $yaml ) {
		$out   = array();
		$lines = explode( "\n", $yaml );
		$total = count( $lines );
		$i     = 0;

		while ( $i < $total ) {
			$line = $lines[ $i ];

			if ( '' === trim( $line ) || 0 === strpos( trim( $line ), '#' ) ) {
				$i++;
				continue;
			}

			if ( ! preg_match( '/^([A-Za-z0-9_.\-]+)[ \t]*:[ \t]*(.*)$/', $line, $m ) ) {
				$i++;
				continue;
			}

			$key = strtolower( $m[1] );
			$val = trim( $m[2] );

			// 块状标量。
			if ( '|' === $val || '>' === $val || '|-' === $val || '>-' === $val ) {
				$fold   = ( 0 === strpos( $val, '>' ) );
				$chunk  = array();
				$j      = $i + 1;
				while ( $j < $total && ( '' === trim( $lines[ $j ] ) || preg_match( '/^[ \t]+\S/', $lines[ $j ] ) ) ) {
					$chunk[] = preg_replace( '/^[ \t]{1,4}/', '', $lines[ $j ] );
					$j++;
				}
				$out[ $key ] = $fold ? trim( implode( ' ', $chunk ) ) : rtrim( implode( "\n", $chunk ) );
				$i           = $j;
				continue;
			}

			// 空值：可能是块状列表。
			if ( '' === $val ) {
				$items = array();
				$j     = $i + 1;
				while ( $j < $total && preg_match( '/^[ \t]*-[ \t]*(.*)$/', $lines[ $j ], $mm ) ) {
					$items[] = self::scalar( $mm[1] );
					$j++;
				}
				if ( ! empty( $items ) ) {
					$out[ $key ] = $items;
					$i           = $j;
					continue;
				}
				$out[ $key ] = '';
				$i++;
				continue;
			}

			// 行内列表。
			if ( preg_match( '/^\[(.*)\]$/', $val, $mm ) ) {
				$parts = array();
				foreach ( explode( ',', $mm[1] ) as $part ) {
					$part = trim( $part );
					if ( '' !== $part ) {
						$parts[] = self::scalar( $part );
					}
				}
				$out[ $key ] = $parts;
				$i++;
				continue;
			}

			// 逗号分隔（tags: a, b）。
			if ( preg_match( '/^[^\'"\[\]]+,[^\'"\[\]]+$/', $val ) && ! preg_match( '#^https?://#i', $val ) ) {
				$parts = array();
				foreach ( explode( ',', $val ) as $part ) {
					$part = trim( $part );
					if ( '' !== $part ) {
						$parts[] = self::scalar( $part );
					}
				}
				$out[ $key ] = $parts;
				$i++;
				continue;
			}

			$out[ $key ] = self::scalar( $val );
			$i++;
		}

		return $out;
	}

	/**
	 * 标量转换。
	 *
	 * @param string $value 值。
	 * @return mixed
	 */
	public static function scalar( $value ) {
		$value = trim( (string) $value );

		if ( strlen( $value ) >= 2 ) {
			$first = substr( $value, 0, 1 );
			$last  = substr( $value, -1 );
			if ( ( '"' === $first && '"' === $last ) || ( "'" === $first && "'" === $last ) ) {
				return substr( $value, 1, -1 );
			}
		}

		$lower = strtolower( $value );
		if ( in_array( $lower, array( 'true', 'yes', 'on' ), true ) ) {
			return true;
		}
		if ( in_array( $lower, array( 'false', 'no', 'off' ), true ) ) {
			return false;
		}
		if ( 'null' === $lower || '~' === $lower ) {
			return '';
		}

		return $value;
	}
}
