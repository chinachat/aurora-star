/**
 * Aurora Star 暗黑/明亮模式切换
 * - localStorage 记忆用户选择
 * - 默认跟随系统（prefers-color-scheme）
 * - 可选夜间时间段自动切换
 */
(function () {
	'use strict';

	var STORAGE_KEY = 'aurora-star-theme';
	var darkDefault = (typeof auroraData !== 'undefined' && auroraData.darkDefault) || 'system';
	var darkByTime = typeof auroraData !== 'undefined' && auroraData.darkByTime;

	function applyTheme(theme) {
		document.documentElement.setAttribute('data-theme', theme);
	}

	function systemPrefersDark() {
		return window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
	}

	function resolveTheme(pref) {
		if (pref === 'light' || pref === 'dark') {
			return pref;
		}
		if (pref === 'system') {
			return systemPrefersDark() ? 'dark' : 'light';
		}
		return systemPrefersDark() ? 'dark' : 'light';
	}

	function init() {
		// 防止闪烁：在 DOM 渲染前应用主题。
		var stored = null;
		try {
			stored = localStorage.getItem(STORAGE_KEY);
		} catch (e) { /* 忽略隐私模式异常 */ }

		var theme;
		if (stored) {
			theme = stored;
		} else if (darkByTime) {
			var hour = new Date().getHours();
			theme = (hour >= 20 || hour < 6) ? 'dark' : resolveTheme(darkDefault);
		} else {
			theme = resolveTheme(darkDefault);
		}
		applyTheme(theme);
	}

	function toggle() {
		var current = document.documentElement.getAttribute('data-theme');
		var next = (current === 'dark') ? 'light' : 'dark';
		applyTheme(next);
		try {
			localStorage.setItem(STORAGE_KEY, next);
		} catch (e) { /* 忽略 */ }
		return next;
	}

	function bindToggle() {
		var btn = document.querySelector('[data-dark-toggle]');
		if (btn) {
			btn.addEventListener('click', function (e) {
				e.preventDefault();
				toggle();
			});
		}
	}

	// 系统主题变化时，若用户未手动选择则跟随。
	if (window.matchMedia) {
		var mq = window.matchMedia('(prefers-color-scheme: dark)');
		var listener = function () {
			try {
				if (!localStorage.getItem(STORAGE_KEY)) {
					applyTheme(resolveTheme('system'));
				}
			} catch (e) { /* 忽略 */ }
		};
		if (mq.addEventListener) {
			mq.addEventListener('change', listener);
		} else if (mq.addListener) {
			mq.addListener(listener); // 旧浏览器
		}
	}

	document.addEventListener('DOMContentLoaded', function () {
		init();
		bindToggle();
	});

	// 立即初始化，避免首屏闪烁。
	init();
})();
