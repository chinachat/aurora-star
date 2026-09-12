/**
 * Markdown 发布器：导入页面与批量渲染页面。
 */
( function () {
	'use strict';

	var config = window.mdpAdmin || {};

	if ( ! config.restUrl ) {
		return;
	}

	var i18n = config.i18n || {};

	/**
	 * 请求封装。
	 *
	 * @param {string} path    路径。
	 * @param {Object} options fetch 选项。
	 * @return {Promise<Object>} 结果。
	 */
	function api( path, options ) {
		var opts = options || {};
		var headers = opts.headers || {};

		if ( ! ( opts.body instanceof FormData ) ) {
			headers[ 'Content-Type' ] = 'application/json';
			if ( opts.body && typeof opts.body !== 'string' ) {
				opts.body = JSON.stringify( opts.body );
			}
		}

		headers[ 'X-WP-Nonce' ] = config.nonce;
		opts.headers = headers;
		opts.credentials = 'same-origin';

		return window.fetch( config.restUrl + path, opts ).then( function ( response ) {
			return response.json().then( function ( data ) {
				if ( ! response.ok ) {
					var message = ( data && data.message ) ? data.message : response.statusText;
					throw new Error( message );
				}
				return data;
			} );
		} );
	}

	/**
	 * 转义 HTML。
	 *
	 * @param {string} text 文本。
	 * @return {string} 结果。
	 */
	function escapeHtml( text ) {
		return String( text === undefined || text === null ? '' : text )
			.replace( /&/g, '&amp;' )
			.replace( /</g, '&lt;' )
			.replace( />/g, '&gt;' )
			.replace( /"/g, '&quot;' );
	}

	/**
	 * 渲染结果表。
	 *
	 * @param {Array}  results 结果。
	 * @param {string} target  容器 ID。
	 */
	function renderResults( results, target ) {
		var container = document.getElementById( target || 'mdp-import-results' );
		if ( ! container ) {
			return;
		}

		var labels = {
			created: '已创建',
			updated: '已更新',
			skipped: '已跳过',
			error: '失败'
		};

		var rows = results.map( function ( row ) {
			var status = row.status || 'error';
			var title = row.title ? escapeHtml( row.title ) : '';
			var links = '';

			if ( row.post_id ) {
				if ( row.edit ) {
					links += '<a href="' + escapeHtml( row.edit ) + '">编辑</a>';
				}
				if ( row.view ) {
					links += ( links ? ' · ' : '' ) + '<a href="' + escapeHtml( row.view ) + '" target="_blank" rel="noopener">查看</a>';
				}
			}

			return '<tr>' +
				'<td>' + escapeHtml( row.file ) + '</td>' +
				'<td><span class="mdp-status mdp-status--' + escapeHtml( status ) + '">' + escapeHtml( labels[ status ] || status ) + '</span></td>' +
				'<td>' + title + '</td>' +
				'<td>' + escapeHtml( row.message ) + '</td>' +
				'<td>' + links + '</td>' +
				'</tr>';
		} ).join( '' );

		container.innerHTML = '<table class="widefat striped mdp-table"><thead><tr>' +
			'<th>文件</th><th>结果</th><th>标题</th><th>说明</th><th>操作</th>' +
			'</tr></thead><tbody>' + rows + '</tbody></table>';
	}

	// ---------------------------------------------------------------
	// 导入页面
	// ---------------------------------------------------------------
	var form = document.getElementById( 'mdp-import-form' );

	if ( form ) {
		var input = document.getElementById( 'mdp-files' );
		var dropzone = document.getElementById( 'mdp-dropzone' );
		var filelist = document.getElementById( 'mdp-filelist' );
		var submit = document.getElementById( 'mdp-import-submit' );
		var progress = document.getElementById( 'mdp-progress' );
		var progressFill = document.getElementById( 'mdp-progress-fill' );
		var progressText = document.getElementById( 'mdp-progress-text' );
		var picked = [];

		/**
		 * 刷新文件列表。
		 */
		function refreshList() {
			if ( ! filelist ) {
				return;
			}
			filelist.innerHTML = '';
			picked.forEach( function ( file, index ) {
				var li = document.createElement( 'li' );
				var name = document.createElement( 'span' );
				name.textContent = file.name + ' (' + Math.max( 1, Math.round( file.size / 1024 ) ) + ' KB)';

				var remove = document.createElement( 'button' );
				remove.type = 'button';
				remove.textContent = '移除';
				remove.addEventListener( 'click', function () {
					picked.splice( index, 1 );
					refreshList();
				} );

				li.appendChild( name );
				li.appendChild( remove );
				filelist.appendChild( li );
			} );
		}

		/**
		 * 读取选项。
		 *
		 * @return {Object} 选项。
		 */
		function options() {
			var categories = document.getElementById( 'mdp-categories' );
			var tags = document.getElementById( 'mdp-tags' );
			var taxonomy = {};

			if ( categories && categories.value.trim() ) {
				taxonomy.category = categories.value.split( ',' ).map( function ( item ) {
					return item.trim();
				} ).filter( Boolean );
			}
			if ( tags && tags.value.trim() ) {
				taxonomy.post_tag = tags.value.split( ',' ).map( function ( item ) {
					return item.trim();
				} ).filter( Boolean );
			}

			return {
				post_type: ( document.getElementById( 'mdp-post-type' ) || {} ).value || '',
				status: ( document.getElementById( 'mdp-status' ) || {} ).value || '',
				duplicate: ( document.getElementById( 'mdp-duplicate' ) || {} ).value || '',
				author: ( document.getElementById( 'mdp-author' ) || {} ).value || 0,
				taxonomy: taxonomy
			};
		}

		if ( input ) {
			input.addEventListener( 'change', function () {
				picked = picked.concat( Array.prototype.slice.call( input.files ) );
				input.value = '';
				refreshList();
			} );
		}

		if ( dropzone ) {
			[ 'dragenter', 'dragover' ].forEach( function ( name ) {
				dropzone.addEventListener( name, function ( event ) {
					event.preventDefault();
					dropzone.classList.add( 'is-dragover' );
				} );
			} );
			[ 'dragleave', 'drop' ].forEach( function ( name ) {
				dropzone.addEventListener( name, function ( event ) {
					event.preventDefault();
					dropzone.classList.remove( 'is-dragover' );
				} );
			} );
			dropzone.addEventListener( 'drop', function ( event ) {
				var files = event.dataTransfer && event.dataTransfer.files;
				if ( files && files.length ) {
					picked = picked.concat( Array.prototype.slice.call( files ) );
					refreshList();
				}
			} );
		}

		/**
		 * 设置进度。
		 *
		 * @param {number} percent 百分比。
		 * @param {string} text    文本。
		 */
		function setProgress( percent, text ) {
			if ( progress ) {
				progress.hidden = false;
			}
			if ( progressFill ) {
				progressFill.style.width = percent + '%';
			}
			if ( progressText ) {
				progressText.textContent = text;
			}
		}

		/**
		 * 逐个上传文件。
		 *
		 * @param {Array} files 文件。
		 * @return {Promise<Array>} 全部结果。
		 */
		function uploadAll( files ) {
			var results = [];
			var index = 0;
			var opts = options();

			function next() {
				if ( index >= files.length ) {
					return Promise.resolve( results );
				}

				var file = files[ index ];
				index++;

				setProgress(
					Math.round( ( ( index - 1 ) / files.length ) * 100 ),
					( i18n.importing || '正在导入…' ) + ' ' + file.name + ' (' + index + '/' + files.length + ')'
				);

				var body = new FormData();
				body.append( 'file', file );
				body.append( 'post_type', opts.post_type );
				body.append( 'status', opts.status );
				body.append( 'duplicate', opts.duplicate );
				body.append( 'author', opts.author );

				Object.keys( opts.taxonomy ).forEach( function ( taxonomy ) {
					opts.taxonomy[ taxonomy ].forEach( function ( term ) {
						body.append( 'taxonomy[' + taxonomy + '][]', term );
					} );
				} );

				return api( '/import', { method: 'POST', body: body } ).then( function ( data ) {
					results = results.concat( data.results || [] );
					renderResults( results );
					return next();
				} ).catch( function ( error ) {
					results.push( { status: 'error', file: file.name, message: error.message } );
					renderResults( results );
					return next();
				} );
			}

			return next();
		}

		form.addEventListener( 'submit', function ( event ) {
			event.preventDefault();

			if ( ! picked.length ) {
				window.alert( i18n.noFile || '请选择文件。' );
				return;
			}

			if ( submit ) {
				submit.disabled = true;
			}

			uploadAll( picked ).then( function ( results ) {
				if ( submit ) {
					submit.disabled = false;
				}
				setProgress( 100, i18n.done || '导入完成。' );
				renderResults( results );
				picked = [];
				refreshList();
			} );
		} );

		// 粘贴文本导入。
		var pasteSubmit = document.getElementById( 'mdp-paste-submit' );
		if ( pasteSubmit ) {
			pasteSubmit.addEventListener( 'click', function () {
				var titleField = document.getElementById( 'mdp-paste-title' );
				var contentField = document.getElementById( 'mdp-paste-content' );
				var content = contentField ? contentField.value : '';

				if ( ! content.trim() ) {
					window.alert( '请先粘贴 Markdown 内容。' );
					return;
				}

				pasteSubmit.disabled = true;
				setProgress( 30, i18n.importing || '正在导入…' );

				var payload = options();
				payload.content = content;
				payload.filename = ( titleField && titleField.value ? titleField.value : 'paste' ) + '.md';

				api( '/import', { method: 'POST', body: payload } ).then( function ( data ) {
					pasteSubmit.disabled = false;
					setProgress( 100, i18n.done || '导入完成。' );
					renderResults( data.results || [] );
					if ( contentField ) {
						contentField.value = '';
					}
					if ( titleField ) {
						titleField.value = '';
					}
				} ).catch( function ( error ) {
					pasteSubmit.disabled = false;
					renderResults( [ { status: 'error', file: 'paste', message: error.message } ] );
				} );
			} );
		}
	}

	// ---------------------------------------------------------------
	// 批量渲染页面
	// ---------------------------------------------------------------
	var startButton = document.getElementById( 'mdp-rerender-start' );

	if ( startButton ) {
		var spinner = document.getElementById( 'mdp-rerender-spinner' );
		var log = document.getElementById( 'mdp-rerender-log' );
		var bar = document.getElementById( 'mdp-progress' );
		var fill = document.getElementById( 'mdp-progress-fill' );
		var text = document.getElementById( 'mdp-progress-text' );
		var perPageField = document.getElementById( 'mdp-rerender-per-page' );

		/**
		 * 追加日志。
		 *
		 * @param {string} line 文本。
		 */
		function appendLog( line ) {
			if ( ! log ) {
				return;
			}
			log.classList.add( 'is-visible' );
			log.textContent += line + '\n';
			log.scrollTop = log.scrollHeight;
		}

		startButton.addEventListener( 'click', function () {
			var perPage = perPageField ? parseInt( perPageField.value, 10 ) || 20 : 20;
			var page = 1;
			var processed = 0;
			var total = 0;

			startButton.disabled = true;
			if ( spinner ) {
				spinner.classList.add( 'is-active' );
			}
			if ( bar ) {
				bar.hidden = false;
			}
			if ( log ) {
				log.textContent = '';
			}

			function step() {
				api( '/rerender', {
					method: 'POST',
					body: { page: page, per_page: perPage }
				} ).then( function ( data ) {
					processed += data.processed || 0;
					total = data.total || 0;
					appendLog( '第 ' + page + ' 批：渲染 ' + ( data.processed || 0 ) + ' 篇（合计 ' + processed + ' / ' + total + '）' );

					var percent = total ? Math.min( 100, Math.round( processed / total * 100 ) ) : 100;
					if ( fill ) {
						fill.style.width = percent + '%';
					}
					if ( text ) {
						text.textContent = ( i18n.rendering || '正在渲染…' ) + ' ' + percent + '%';
					}

					if ( ! data.done && page < 500 ) {
						page++;
						window.setTimeout( step, 120 );
						return;
					}

					startButton.disabled = false;
					if ( spinner ) {
						spinner.classList.remove( 'is-active' );
					}
					appendLog( i18n.renderDone || '渲染完成。' );
				} ).catch( function ( error ) {
					startButton.disabled = false;
					if ( spinner ) {
						spinner.classList.remove( 'is-active' );
					}
					appendLog( '出错：' + error.message );
				} );
			}

			step();
		} );
	}
}() );
