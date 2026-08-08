/**
 * Aurora Star 代码高亮触发
 * - 高亮所有 <pre><code> 代码块
 * - Gutenberg 代码块（无 language 类）自动分配语言
 * - 通过短码 [code] 或编辑器语法高亮生成
 */
(function () {
	'use strict';

	// 关闭 Prism 自动高亮，由本脚本统一控制时机（避免与手动高亮竞态导致行号丢失）。
	if (window.Prism) {
		Prism.manual = true;
	}

	function detectLanguage(code) {
		var text = code.textContent || '';
		var trimmed = text.replace(/^\s+/, '');

		if (/<\?php|<\?=/.test(text)) return 'php';
		if (/^<\?xml/.test(trimmed)) return 'xml';
		if (/<(!DOCTYPE|html|div|span|body|head|script|style)\b/i.test(trimmed)) return 'markup';
		if (/^(def |class |import |from |print\(|if __name__)/m.test(text)) return 'python';
		if (/^(function|const|let|var|console\.log|=>|async|await)\b/m.test(text)) return 'javascript';
		if (/^(#include|#define|int main|printf\(|std::)/m.test(text)) return 'cpp';
		if (/^(import java\.|public class|System\.out)/.test(text)) return 'java';
		if (/^(package main|func |fmt\.Println|go func)/m.test(text)) return 'go';
		if (/^(#!\/bin\/bash|export |echo |apt-get |sudo )/m.test(text)) return 'bash';
		if (/^(SELECT|INSERT|UPDATE|DELETE|CREATE TABLE)\s/mi.test(text)) return 'sql';
		if (/^([{[\[])/m.test(trimmed)) return 'json';
		if (/^\s*(---|\.\.\.)\s*$/.test(text.split('\n')[0]) || /^[\w-]+:\s/.test(text)) return 'yaml';
		return 'markup';
	}

	function init() {
		// 读取主题设置（由 wp_localize_script 注入）。
		var settings = (typeof auroraStarHighlight !== 'undefined') ? auroraStarHighlight : {};
		var lineNumbers = settings.lineNumbers !== false;   // 默认开启
		var wrap = !!settings.wrap;                          // 默认关闭

		// 为无语言类的代码块分配默认语言，确保 Prism 能高亮。
		var codes = document.querySelectorAll('pre code:not([class*="language-"])');
		codes.forEach(function (code) {
			var pre = code.closest('pre');
			code.className = (code.className ? code.className + ' ' : '') + 'language-' + detectLanguage(code);
		});

		// 行号：短码显式 line="true"/line="false"（line-numbers / no-line-numbers 类）优先；
		// 否则按全局设置决定是否添加。
		document.querySelectorAll('pre[class*="language-"]').forEach(function (pre) {
			if (pre.classList.contains('no-line-numbers')) {
				pre.classList.remove('line-numbers');
				pre.classList.remove('no-line-numbers');
				return;
			}
			if (lineNumbers && !pre.classList.contains('line-numbers')) {
				pre.classList.add('line-numbers');
			}
		});

		// 自动换行：按全局设置，为代码容器添加类（CSS 控制）。
		document.querySelectorAll('pre[class*="language-"]').forEach(function (pre) {
			pre.classList.toggle('is-wrap', wrap);
		});

		// 代码块：Prism 自动高亮。
		if (window.Prism) {
			Prism.highlightAll();
		}

		// 为代码块附加语言标签（若无 toolbar 插件处理）。
		var pres = document.querySelectorAll('pre[class*="language-"]');
		pres.forEach(function (pre) {
			var cls = pre.className.match(/language-([\w-]+)/);
			if (cls && !pre.querySelector('.aurora-star-lang-tag')) {
				var lang = cls[1];
				var isToolbarApplied = pre.closest('.code-toolbar');
				if (!isToolbarApplied) {
					var tag = document.createElement('span');
					tag.className = 'aurora-star-lang-tag';
					tag.textContent = lang;
					tag.style.cssText = 'position:absolute;top:8px;right:10px;font-size:0.75em;' +
						'color:rgba(255,255,255,0.7);background:rgba(0,0,0,0.35);' +
						'padding:4px 10px;border-radius:4px;text-transform:uppercase;';
					pre.style.paddingTop = '18px';
					pre.appendChild(tag);
				}
			}
		});
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();
