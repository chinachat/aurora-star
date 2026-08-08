/**
 * Aurora Star 主脚本
 * - 移动端导航
 * - 全局浮动导航（返回顶部 + 分享）
 */
(function () {
	'use strict';

	// 移动端导航
	function initNav() {
		var toggle = document.querySelector('[data-nav-toggle]');
		var nav = document.querySelector('.site-nav');
		var overlay = document.querySelector('[data-nav-overlay]');

		if (!toggle || !nav) {
			return;
		}

		function setOpen(open) {
			nav.classList.toggle('is-open', open);
			toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
			if (overlay) {
				overlay.classList.toggle('is-open', open);
			}
			document.body.style.overflow = open ? 'hidden' : '';
		}

		toggle.addEventListener('click', function () {
			setOpen(!nav.classList.contains('is-open'));
		});

		if (overlay) {
			overlay.addEventListener('click', function () {
				setOpen(false);
			});
		}

		// 移动端子菜单展开。
		nav.querySelectorAll('.menu-item-has-children > a').forEach(function (link) {
			link.addEventListener('click', function (e) {
				if (window.innerWidth <= 991) {
					e.preventDefault();
					var li = link.closest('li');
					if (li) {
						var expanded = li.classList.contains('is-expanded');
						li.classList.toggle('is-expanded', !expanded);
						link.setAttribute('aria-expanded', String(!expanded));
					}
				}
			});
		});
	}

	// 全局浮动导航：返回顶部 + 分享。
	function initFloatingNav() {
		var container = document.querySelector('[data-floating-nav]');
		var backTop = document.querySelector('[data-back-top]');
		var shareBtn = document.querySelector('[data-share-btn]');
		var popover = document.querySelector('[data-share-popover]');
		var shareClose = document.querySelector('[data-share-close]');
		var toast = document.querySelector('[data-share-toast]');

		if (!container) {
			return;
		}

		var toastTimer = null;

		function showToast(text) {
			if (!toast) {
				return;
			}
			if (toastTimer) {
				clearTimeout(toastTimer);
			}
			if (text) {
				toast.textContent = text;
			}
			toast.classList.add('is-visible');
			toastTimer = setTimeout(function () {
				toast.classList.remove('is-visible');
			}, 2000);
		}

		// 返回顶部。
		if (backTop) {
			var onScroll = function () {
				if (window.scrollY > 400) {
					container.classList.add('is-scrolled');
				} else {
					container.classList.remove('is-scrolled');
				}
			};
			window.addEventListener('scroll', onScroll, { passive: true });
			onScroll();

			backTop.addEventListener('click', function () {
				window.scrollTo({ top: 0, behavior: 'smooth' });
			});
		}

		// 分享面板开合。
		function setPopover(open) {
			if (!popover) {
				return;
			}
			popover.classList.toggle('is-open', open);
			popover.setAttribute('aria-hidden', open ? 'false' : 'true');
		}

		if (shareBtn && popover) {
			shareBtn.addEventListener('click', function (e) {
				e.stopPropagation();
				setPopover(!popover.classList.contains('is-open'));
			});
		}

		if (shareClose) {
			shareClose.addEventListener('click', function () {
				setPopover(false);
			});
		}

		document.addEventListener('click', function (e) {
			if (popover && popover.classList.contains('is-open') &&
				!popover.contains(e.target) && e.target !== shareBtn) {
				setPopover(false);
			}
		});

		var url = window.location.href;
		var title = document.title;
		var desc = (function () {
			var meta = document.querySelector('meta[name="description"]');
			return meta ? meta.getAttribute('content') || title : title;
		})();

		function shareUrl() {
			// 读取最新地址（如点击目录锚点后变化）。
			return window.location.href.split('#')[0];
		}

		// 微信：生成二维码（调用 qrcode 或跳转微信 API）。此处使用微信 JS 兼容的转码接口。
		var wechat = document.querySelector('[data-share-wechat]');
		if (wechat) {
			wechat.addEventListener('click', function (e) {
				e.preventDefault();
				showToast('微信内请使用右上角 ··· 分享');
			});
		}

		var weibo = document.querySelector('[data-share-weibo]');
		if (weibo) {
			weibo.href = 'https://service.weibo.com/share/share.php?url=' + encodeURIComponent(shareUrl()) +
				'&title=' + encodeURIComponent(title);
			weibo.addEventListener('click', function (e) {
				e.preventDefault();
				window.open(weibo.href, '_blank', 'noopener,width=640,height=480');
			});
		}

		var qq = document.querySelector('[data-share-qq]');
		if (qq) {
			qq.href = 'https://connect.qq.com/widget/shareqq/index.html?url=' + encodeURIComponent(shareUrl()) +
				'&title=' + encodeURIComponent(title) +
				'&summary=' + encodeURIComponent(desc) +
				'&desc=' + encodeURIComponent(desc);
			qq.addEventListener('click', function (e) {
				e.preventDefault();
				window.open(qq.href, '_blank', 'noopener,width=680,height=480');
			});
		}

		var copyBtn = document.querySelector('[data-share-copy]');
		if (copyBtn) {
			copyBtn.addEventListener('click', function () {
				var text = shareUrl();
				if (navigator.clipboard && navigator.clipboard.writeText) {
					navigator.clipboard.writeText(text).then(function () {
						showToast();
					}).catch(function () {
						fallbackCopy(text);
					});
				} else {
					fallbackCopy(text);
				}
				setPopover(false);
			});
		}

		function fallbackCopy(text) {
			var ta = document.createElement('textarea');
			ta.value = text;
			ta.style.position = 'fixed';
			ta.style.opacity = '0';
			document.body.appendChild(ta);
			ta.select();
			try {
				document.execCommand('copy');
				showToast();
			} catch (err) {
				showToast('复制失败，请手动复制地址');
			}
			document.body.removeChild(ta);
		}
	}

	// 页头搜索按钮与搜索面板。
	function initHeaderSearch() {
		var toggle = document.querySelector('[data-search-toggle]');
		var panel = document.querySelector('[data-header-search]');
		var closeBtn = document.querySelector('[data-search-close]');
		var input = document.querySelector('.header-search__input');

		if (!toggle || !panel) {
			return;
		}

		function setOpen(open) {
			panel.classList.toggle('is-open', open);
			toggle.classList.toggle('is-active', open);
			toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
			panel.setAttribute('aria-hidden', open ? 'false' : 'true');
			if (open && input) {
				setTimeout(function () { input.focus(); }, 60);
			}
		}

		toggle.addEventListener('click', function (e) {
			e.stopPropagation();
			setOpen(!panel.classList.contains('is-open'));
		});

		if (closeBtn) {
			closeBtn.addEventListener('click', function () {
				setOpen(false);
			});
		}

		// 点击面板外部关闭。
		document.addEventListener('click', function (e) {
			if (panel.classList.contains('is-open') &&
				!panel.contains(e.target) && e.target !== toggle) {
				setOpen(false);
			}
		});

		// ESC 关闭。
		document.addEventListener('keydown', function (e) {
			if (e.key === 'Escape' && panel.classList.contains('is-open')) {
				setOpen(false);
				toggle.focus();
			}
		});
	}

	document.addEventListener('DOMContentLoaded', function () {
		initNav();
		initFloatingNav();
		initHeaderSearch();
	});
})();
