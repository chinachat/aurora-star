/**
 * Markdown 发布器：文章编辑页的 Markdown 编辑器。
 */
( function () {
	'use strict';

	var config = window.mdpEditor || {};
	var root = document.getElementById( 'mdp-editor' );

	if ( ! root || ! config.restUrl ) {
		return;
	}

	var textarea = document.getElementById( 'mdp-source' );
	var toggle = document.getElementById( 'mdp-enabled' );
	var preview = document.getElementById( 'mdp-preview' );
	var previewBody = document.getElementById( 'mdp-preview-body' );
	var stats = document.getElementById( 'mdp-stats' );
	var toolbar = document.getElementById( 'mdp-toolbar' );
	var originalField = root.querySelector( 'input[name="mdp_original"]' );
	var previewBtn = document.getElementById( 'mdp-toggle-preview' );
	var fullscreenBtn = document.getElementById( 'mdp-toggle-fullscreen' );

	if ( ! textarea ) {
		return;
	}

	var i18n = config.i18n || {};
	var previewTimer = null;
	var lastPreview = '';

	/**
	 * 统计字数（中英混排）。
	 *
	 * @param {string} text 文本。
	 * @return {number} 字数。
	 */
	function countWords( text ) {
		var cjk = text.match( /[\u4e00-\u9fff\u3040-\u30ff\uac00-\ud7af]/g );
		var latin = text.match( /[A-Za-z0-9_'\-]+/g );
		return ( cjk ? cjk.length : 0 ) + ( latin ? latin.length : 0 );
	}

	/**
	 * 更新统计信息。
	 */
	function updateStats() {
		if ( ! stats ) {
			return;
		}
		var value = countWords( textarea.value );
		var headings = ( textarea.value.match( /^ {0,3}#{1,6}\s+/gm ) || [] ).length;
		stats.textContent = '约 ' + value.toLocaleString() + ' 字 · ' + headings + ' 个标题';
	}

	/**
	 * 请求预览。
	 */
	function requestPreview() {
		var markdown = textarea.value;

		if ( markdown === lastPreview ) {
			return;
		}
		lastPreview = markdown;

		if ( ! markdown.trim() ) {
			if ( previewBody ) {
				previewBody.innerHTML = '';
			}
			return;
		}

		if ( previewBody ) {
			previewBody.classList.add( 'is-loading' );
		}

		window.fetch( config.restUrl + '/preview', {
			method: 'POST',
			credentials: 'same-origin',
			headers: {
				'Content-Type': 'application/json',
				'X-WP-Nonce': config.nonce
			},
			body: JSON.stringify( { markdown: markdown } )
		} ).then( function ( response ) {
			return response.json();
		} ).then( function ( data ) {
			if ( previewBody ) {
				previewBody.classList.remove( 'is-loading' );
				previewBody.innerHTML = data && data.html ? data.html : '';
			}
		} ).catch( function ( error ) {
			lastPreview = null;
			if ( previewBody ) {
				previewBody.classList.remove( 'is-loading' );
				previewBody.textContent = ( i18n.previewFailed || '预览失败：' ) + error.message;
			}
		} );
	}

	/**
	 * 延迟预览。
	 */
	function schedulePreview() {
		if ( ! preview || preview.hidden || ! previewBody ) {
			return;
		}
		window.clearTimeout( previewTimer );
		previewTimer = window.setTimeout( requestPreview, 450 );
	}

	/**
	 * 在选区外包一层标记。
	 *
	 * @param {string} before 前置。
	 * @param {string} after  后置。
	 * @param {string} empty  无选中时的占位。
	 */
	function wrap( before, after, empty ) {
		var start = textarea.selectionStart;
		var end = textarea.selectionEnd;
		var value = textarea.value;
		var selected = value.substring( start, end );

		if ( ! selected ) {
			selected = empty || '';
		}

		var replacement = before + selected + after;
		textarea.value = value.substring( 0, start ) + replacement + value.substring( end );
		textarea.focus();

		if ( textarea.setSelectionRange ) {
			if ( start === end && ! empty ) {
				textarea.setSelectionRange( start + before.length, start + before.length );
			} else {
				textarea.setSelectionRange( start + before.length, start + before.length + selected.length );
			}
		}

		onChange();
	}

	/**
	 * 给选中的每一行加前缀。
	 *
	 * @param {string} prefix 前缀。
	 * @param {string} first  首行前缀（默认同 prefix）。
	 */
	function prefixLines( prefix, first ) {
		var start = textarea.selectionStart;
		var end = textarea.selectionEnd;
		var value = textarea.value;

		var lineStart = value.lastIndexOf( '\n', start - 1 ) + 1;
		var lineEnd = value.indexOf( '\n', end );
		if ( lineEnd === -1 ) {
			lineEnd = value.length;
		}

		var block = value.substring( lineStart, lineEnd );
		var lines = block.split( '\n' );

		if ( lines.length === 1 && lines[ 0 ] === '' ) {
			lines = [ '' ];
		}

		var output = lines.map( function ( line, index ) {
			var marker = ( index === 0 && typeof first === 'string' ) ? first : prefix;
			if ( line.replace( /\s/g, '' ) === '' ) {
				return marker.replace( /\s+$/, '' );
			}
			return marker + line;
		} ).join( '\n' );

		textarea.value = value.substring( 0, lineStart ) + output + value.substring( lineEnd );
		textarea.focus();
		textarea.setSelectionRange( lineStart, lineStart + output.length );
		onChange();
	}

	/**
	 * 插入文本。
	 *
	 * @param {string} text 文本。
	 */
	function insert( text ) {
		var start = textarea.selectionStart;
		var value = textarea.value;
		textarea.value = value.substring( 0, start ) + text + value.substring( textarea.selectionEnd );
		textarea.focus();
		textarea.setSelectionRange( start + text.length, start + text.length );
		onChange();
	}

	/**
	 * 工具栏动作。
	 *
	 * @param {string} action 动作。
	 */
	function runAction( action ) {
		switch ( action ) {
			case 'h1':
				prefixLines( '# ' );
				break;
			case 'h2':
				prefixLines( '## ' );
				break;
			case 'h3':
				prefixLines( '### ' );
				break;
			case 'bold':
				wrap( '**', '**', '加粗文字' );
				break;
			case 'italic':
				wrap( '*', '*', '斜体文字' );
				break;
			case 'strike':
				wrap( '~~', '~~', '删除线' );
				break;
			case 'code':
				wrap( '`', '`', 'code' );
				break;
			case 'link':
				wrap( '[', '](https://)', '链接文字' );
				break;
			case 'image':
				wrap( '![', '](https://)', '图片说明' );
				break;
			case 'quote':
				prefixLines( '> ' );
				break;
			case 'ul':
				prefixLines( '- ' );
				break;
			case 'ol':
				prefixLines( '1. ' );
				break;
			case 'task':
				prefixLines( '- [ ] ' );
				break;
			case 'codeblock':
				wrap( '```\n', '\n```', 'code' );
				break;
			case 'table':
				insert( '\n| 列 1 | 列 2 |\n| --- | --- |\n| 内容 | 内容 |\n' );
				break;
			case 'hr':
				insert( '\n\n---\n\n' );
				break;
			case 'toc':
				insert( '\n[TOC]\n' );
				break;
			case 'footnote':
				insert( '[^1]\n\n[^1]: 脚注内容\n' );
				break;
			default:
				break;
		}
	}

	/**
	 * 内容变化处理。
	 */
	function onChange() {
		updateStats();
		schedulePreview();
	}

	// 工具栏。
	if ( toolbar ) {
		toolbar.addEventListener( 'click', function ( event ) {
			var button = event.target.closest( 'button[data-mdp-action]' );
			if ( ! button ) {
				return;
			}
			event.preventDefault();
			runAction( button.getAttribute( 'data-mdp-action' ) );
		} );
	}

	// 预览开关。
	if ( previewBtn && preview ) {
		previewBtn.addEventListener( 'click', function () {
			preview.hidden = ! preview.hidden;
			if ( ! preview.hidden ) {
				lastPreview = '';
				requestPreview();
			}
		} );
	}

	// 全屏。
	if ( fullscreenBtn ) {
		fullscreenBtn.addEventListener( 'click', function () {
			root.classList.toggle( 'is-fullscreen' );
			document.body.classList.toggle( 'mdp-fullscreen-open' );
			fullscreenBtn.textContent = root.classList.contains( 'is-fullscreen' ) ? '退出全屏' : '全屏';
		} );
	}

	// 输入。
	textarea.addEventListener( 'input', onChange );

	// Tab 缩进。
	textarea.addEventListener( 'keydown', function ( event ) {
		var meta = event.ctrlKey || event.metaKey;

		if ( meta && ! event.shiftKey && ! event.altKey ) {
			var key = ( event.key || '' ).toLowerCase();
			if ( 'b' === key ) {
				event.preventDefault();
				runAction( 'bold' );
				return;
			}
			if ( 'i' === key ) {
				event.preventDefault();
				runAction( 'italic' );
				return;
			}
			if ( 'k' === key ) {
				event.preventDefault();
				runAction( 'link' );
				return;
			}
		}

		if ( 'Tab' === event.key ) {
			event.preventDefault();
			insert( '  ' );
		}
	} );

	// 拖放 .md 文件。
	function readFiles( files ) {
		if ( ! files || ! files.length ) {
			return;
		}

		var file = files[ 0 ];
		if ( file.name && ! /\.(md|markdown|mdown|mkd|txt)$/i.test( file.name ) ) {
			return;
		}

		if ( textarea.value.trim() && originalField && ! window.confirm( i18n.confirmReplace ) ) {
			return;
		}

		var reader = new FileReader();
		reader.onload = function () {
			var text = String( reader.result || '' );
			textarea.value = textarea.value.trim() ? textarea.value.replace( /\s*$/, '\n\n' ) + text : text;

			// 文件头部有 # 标题且文章标题为空时，顺手填上标题。
			var titleField = document.getElementById( 'title' );
			if ( titleField && ! titleField.value ) {
				var match = text.match( /^ {0,3}#[ \t]+(.+)$/m );
				if ( match ) {
					titleField.value = match[ 1 ].replace( /[ \t]*#+[ \t]*$/, '' ).trim();
					if ( window.tinymce ) {
						// 无需处理，标题是普通输入框。
					}
				}
			}

			textarea.dispatchEvent( new Event( 'input' ) );
			textarea.focus();
		};
		reader.readAsText( file );

		// 只处理第一个文件，其余追加。
		for ( var i = 1; i < files.length; i++ ) {
			( function ( extra ) {
				var extraReader = new FileReader();
				extraReader.onload = function () {
					textarea.value = textarea.value.replace( /\s*$/, '\n\n' ) + String( extraReader.result || '' );
					textarea.dispatchEvent( new Event( 'input' ) );
				};
				extraReader.readAsText( extra );
			}( files[ i ] ) );
		}
	}

	[ 'dragenter', 'dragover' ].forEach( function ( name ) {
		textarea.addEventListener( name, function ( event ) {
			event.preventDefault();
			textarea.classList.add( 'is-dragover' );
		} );
	} );

	[ 'dragleave', 'drop' ].forEach( function ( name ) {
		textarea.addEventListener( name, function ( event ) {
			event.preventDefault();
			textarea.classList.remove( 'is-dragover' );
		} );
	} );

	textarea.addEventListener( 'drop', function ( event ) {
		var dt = event.dataTransfer;
		if ( dt && dt.files && dt.files.length ) {
			readFiles( dt.files );
		}
	} );

	// 启用/关闭 Markdown 模式。
	if ( toggle ) {
		toggle.addEventListener( 'change', function () {
			if ( toggle.checked ) {
				if ( ! textarea.value.trim() && originalField && originalField.value ) {
					if ( ! window.confirm( i18n.confirmEnable ) ) {
						toggle.checked = false;
						return;
					}
					textarea.value = originalField.value;
				}
				textarea.disabled = false;
				textarea.focus();
			} else {
				textarea.disabled = true;
				if ( preview ) {
					preview.hidden = true;
				}
			}
			updateStats();
		} );
	}

	// 表单提交前把内容同步（防止某些插件直接读 content 字段）。
	var form = textarea.form;
	if ( form ) {
		form.addEventListener( 'submit', function () {
			textarea.disabled = false;
		} );
	}

	updateStats();

	if ( preview && ! preview.hidden ) {
		requestPreview();
	}
}() );
