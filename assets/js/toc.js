/**
 * Aurora Star 浮动目录（TOC）
 * - 滚动监听高亮当前阅读章节
 * - 阅读进度条
 * - 大类默认折叠，读到相应位置自动展开子类
 * - 点击箭头手动展开/折叠
 * - 移动端悬浮按钮触发
 */
(function () {
	'use strict';

	var toc = document.querySelector('[data-toc]');
	if (!toc) {
		return;
	}

	var links = Array.prototype.slice.call(toc.querySelectorAll('.aurora-star-toc-link'));
	var items = Array.prototype.slice.call(toc.querySelectorAll('.aurora-star-toc-item'));
	var carets = Array.prototype.slice.call(toc.querySelectorAll('.aurora-star-toc-caret'));
	var progressBar = toc.querySelector('.aurora-star-toc-progress-bar');
	var toggleBtn = toc.querySelector('.aurora-star-toc-toggle');

	var headings = [];
	links.forEach(function (link) {
		var id = link.getAttribute('href').replace(/^#/, '');
		var target = document.getElementById(id);
		if (target) {
			headings.push({
				link: link,
				item: link.closest('.aurora-star-toc-item'),
				el: target
			});
		}
	});

	if (headings.length === 0) {
		toc.remove();
		return;
	}

	// ---------- 折叠/展开 ----------
	function isCollapsed(item) {
		return item.classList.contains('is-collapsed');
	}

	function setCollapsed(item, collapsed) {
		item.classList.toggle('is-collapsed', collapsed);
		var caret = item.querySelector(':scope > .aurora-star-toc-caret');
		if (caret) {
			caret.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
		}
	}

	// 初始化：含子项的目录项默认折叠（含顶级大类）。
	function initDepthState() {
		items.forEach(function (item) {
			var subList = item.querySelector(':scope > .aurora-star-toc-list');
			if (subList) {
				setCollapsed(item, true);
			}
		});
	}

	// 展开某标题的祖先链。
	function expandAncestors(heading) {
		var item = heading.item;
		var chain = [];
		while (item) {
			chain.unshift(item);
			item = item.closest('.aurora-star-toc-list') ? item.closest('.aurora-star-toc-list').closest('.aurora-star-toc-item') : null;
		}
		chain.forEach(function (li) {
			if (li.classList.contains('is-collapsed')) {
				// 只展开祖先链，不改变手动折叠状态（user 手动收起的保持）。
				if (!li.dataset.userCollapsed) {
					setCollapsed(li, false);
				}
			}
		});
	}

	function markActive(id) {
		links.forEach(function (link) {
			link.classList.remove('is-active');
		});
		items.forEach(function (item) {
			item.classList.remove('is-parent');
		});

		var active = null;
		headings.forEach(function (h) {
			if (h.el.id === id) {
				active = h;
			}
		});
		if (!active) {
			return;
		}

		active.link.classList.add('is-active');
		expandAncestors(active);

		// 标记祖先为 is-parent。
		var item = active.item.closest('.aurora-star-toc-list') ? active.item.closest('.aurora-star-toc-list').closest('.aurora-star-toc-item') : null;
		while (item) {
			item.classList.add('is-parent');
			item = item.closest('.aurora-star-toc-list') ? item.closest('.aurora-star-toc-list').closest('.aurora-star-toc-item') : null;
		}
	}

	// ---------- 滚动定位 ----------
	function getActiveHeading() {
		var scrollY = window.scrollY;
		var headerH = 80;
		var current = null;
		var currentTop = -Infinity;

		headings.forEach(function (h) {
			var top = h.el.getBoundingClientRect().top + scrollY;
			if (top <= scrollY + headerH && top > currentTop) {
				current = h;
				currentTop = top;
			}
		});

		if (!current && headings.length > 0) {
			var first = headings[0].el.getBoundingClientRect().top + scrollY;
			if (first > scrollY + headerH) {
				current = headings[0];
			}
		}

		return current;
	}

	function updateProgress() {
		if (!progressBar) {
			return;
		}
		var doc = document.documentElement;
		var total = doc.scrollHeight - window.innerHeight;
		if (total <= 0) {
			progressBar.style.width = '0%';
			return;
		}
		var pct = (window.scrollY / total) * 100;
		progressBar.style.width = pct + '%';
	}

	function onScroll() {
		updateProgress();
		var active = getActiveHeading();
		if (active) {
			markActive(active.el.id);
		}
	}

	function scrollToId(id) {
		var el = document.getElementById(id);
		if (!el) {
			return;
		}
		var top = el.getBoundingClientRect().top + window.scrollY - 70;
		window.scrollTo({ top: top, behavior: 'smooth' });
		history.replaceState(null, '', '#' + id);
	}

	// ---------- 事件绑定 ----------
	links.forEach(function (link) {
		link.addEventListener('click', function (e) {
			e.preventDefault();
			var id = link.getAttribute('href').replace('#', '');
			scrollToId(id);
		});
	});

	// 手动折叠/展开：用户操作后标记，滚动不再强制展开。
	carets.forEach(function (caret) {
		caret.addEventListener('click', function (e) {
			e.stopPropagation();
			var item = caret.closest('.aurora-star-toc-item');
			if (!item) {
				return;
			}
			var willCollapse = !isCollapsed(item);
			// 用户手动操作：若展开则清除标记，若折叠则记录。
			if (!willCollapse) {
				item.dataset.userCollapsed = '1';
			} else {
				delete item.dataset.userCollapsed;
			}
			setCollapsed(item, willCollapse);
		});
	});

	if (toggleBtn) {
		toggleBtn.addEventListener('click', function () {
			var collapsed = toc.classList.toggle('is-collapsed');
			toggleBtn.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
		});
	}

	var mobileBtn = document.querySelector('[data-toc-mobile-btn]');
	if (mobileBtn) {
		mobileBtn.addEventListener('click', function () {
			toc.classList.toggle('is-visible');
		});
	}

	function visibility() {
		if (window.innerWidth >= 1280) {
			toc.classList.add('is-visible');
		} else {
			toc.classList.remove('is-visible');
		}
	}

	window.addEventListener('scroll', onScroll, { passive: true });
	window.addEventListener('resize', visibility, { passive: true });

	var observer = new MutationObserver(function () {
		updateProgress();
	});
	observer.observe(document.body, { childList: true, subtree: true });

	initDepthState();
	visibility();
	onScroll();
})();
