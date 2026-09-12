/**
 * Aurora Star 图片灯箱
 * - 点击正文图片放大
 * - 支持滚轮/按钮缩放、拖动平移、旋转
 * - 键盘操作、上一张/下一张
 */
(function () {
	'use strict';

	// 由 wp_localize_script 注入；缺失时回退到内置文案。
	function l10n(key, fallback) {
		return (typeof auroraStarLightboxL10n !== 'undefined' && auroraStarLightboxL10n[key])
			? auroraStarLightboxL10n[key]
			: fallback;
	}

	function attrEscape(str) {
		return String(str)
			.replace(/&/g, '&amp;')
			.replace(/"/g, '&quot;')
			.replace(/</g, '&lt;')
			.replace(/>/g, '&gt;');
	}

	var lightbox = null;
	var stage = null;
	var imgEl = null;
	var captionEl = null;
	var zoomLevel = null;
	var currentIndex = 0;
	var images = [];
	var scale = 1;
	var rot = 0;
	var isDragging = false;
	var dragStart = { x: 0, y: 0 };
	var translate = { x: 0, y: 0 };
	var lastFocused = null;

	function build() {
		lightbox = document.createElement('div');
		lightbox.className = 'aurora-star-lightbox';
		lightbox.setAttribute('role', 'dialog');
		lightbox.setAttribute('aria-modal', 'true');
		lightbox.setAttribute('aria-label', l10n('openImage', '图片预览'));
		lightbox.innerHTML =
			'<button type="button" class="aurora-star-lightbox__close" data-lb-close aria-label="' + attrEscape(l10n('close', '关闭')) + '">' +
			'<i class="fa-solid fa-xmark" aria-hidden="true"></i></button>' +
			'<div class="aurora-star-lightbox__stage">' +
			'<img class="aurora-star-lightbox__img" alt="" />' +
			'<div class="aurora-star-lightbox__caption"></div>' +
			'</div>' +
			'<div class="aurora-star-lightbox__toolbar">' +
			'<button type="button" class="aurora-star-lightbox__btn" data-lb-zoom-out aria-label="' + attrEscape(l10n('zoomOut', '缩小')) + '">' +
			'<i class="fa-solid fa-minus" aria-hidden="true"></i></button>' +
			'<span class="aurora-star-lightbox__zoom-level">100%</span>' +
			'<button type="button" class="aurora-star-lightbox__btn" data-lb-zoom-in aria-label="' + attrEscape(l10n('zoomIn', '放大')) + '">' +
			'<i class="fa-solid fa-plus" aria-hidden="true"></i></button>' +
			'<button type="button" class="aurora-star-lightbox__btn" data-lb-reset aria-label="' + attrEscape(l10n('reset', '重置')) + '">' +
			'<i class="fa-solid fa-rotate" aria-hidden="true"></i></button>' +
			'<button type="button" class="aurora-star-lightbox__btn" data-lb-rotate aria-label="' + attrEscape(l10n('rotate', '旋转')) + '">' +
			'<i class="fa-solid fa-rotate-right" aria-hidden="true"></i></button>' +
			'<button type="button" class="aurora-star-lightbox__btn" data-lb-prev aria-label="' + attrEscape(l10n('prev', '上一张')) + '">' +
			'<i class="fa-solid fa-chevron-left" aria-hidden="true"></i></button>' +
			'<button type="button" class="aurora-star-lightbox__btn" data-lb-next aria-label="' + attrEscape(l10n('next', '下一张')) + '">' +
			'<i class="fa-solid fa-chevron-right" aria-hidden="true"></i></button>' +
			'</div>' +
			'<div class="aurora-star-lightbox__hint">' + attrEscape(l10n('hint', '滚动或按钮缩放 · 拖动平移 · ESC 关闭')) + '</div>';

		document.body.appendChild(lightbox);
		stage = lightbox.querySelector('.aurora-star-lightbox__stage');
		imgEl = lightbox.querySelector('.aurora-star-lightbox__img');
		captionEl = lightbox.querySelector('.aurora-star-lightbox__caption');
		zoomLevel = lightbox.querySelector('.aurora-star-lightbox__zoom-level');

		// 事件绑定。
		lightbox.querySelector('[data-lb-close]').addEventListener('click', close);
		lightbox.querySelector('[data-lb-zoom-in]').addEventListener('click', function () { zoomBy(0.25); });
		lightbox.querySelector('[data-lb-zoom-out]').addEventListener('click', function () { zoomBy(-0.25); });
		lightbox.querySelector('[data-lb-reset]').addEventListener('click', reset);
		lightbox.querySelector('[data-lb-rotate]').addEventListener('click', function () {
			rot = (rot + 90) % 360;
			applyTransform();
		});
		lightbox.querySelector('[data-lb-prev]').addEventListener('click', prev);
		lightbox.querySelector('[data-lb-next]').addEventListener('click', next);

		// 点击遮罩关闭（点击图片本身不关闭）。
		lightbox.addEventListener('click', function (e) {
			if (e.target === lightbox || e.target === stage) {
				close();
			}
		});

		// 滚轮缩放。
		stage.addEventListener('wheel', function (e) {
			e.preventDefault();
			zoomBy(e.deltaY < 0 ? 0.1 : -0.1);
		}, { passive: false });

		// 拖动平移。
		imgEl.addEventListener('mousedown', startDrag);
		window.addEventListener('mousemove', onDrag);
		window.addEventListener('mouseup', endDrag);

		imgEl.addEventListener('touchstart', startTouch, { passive: true });
		// onTouch 内部会调用 preventDefault()，必须使用非 passive 监听，否则手势与页面滚动会同时生效。
		imgEl.addEventListener('touchmove', onTouch, { passive: false });
		imgEl.addEventListener('touchend', endTouch);

		// 键盘。
		document.addEventListener('keydown', function (e) {
			if (!lightbox.classList.contains('is-open')) {
				return;
			}
			switch (e.key) {
				case 'Escape':
					close();
					break;
				case 'ArrowLeft':
					prev();
					break;
				case 'ArrowRight':
					next();
					break;
				case '+':
				case '=':
					zoomBy(0.1);
					break;
				case '-':
					zoomBy(-0.1);
					break;
				case '0':
					reset();
					break;
				case 'r':
					rot = (rot + 90) % 360;
					applyTransform();
					break;
			}
		});
	}

	function startDrag(e) {
		if (scale <= 1) {
			return;
		}
		isDragging = true;
		dragStart.x = e.clientX - translate.x;
		dragStart.y = e.clientY - translate.y;
		imgEl.classList.add('is-dragging');
	}

	function onDrag(e) {
		if (!isDragging) {
			return;
		}
		translate.x = e.clientX - dragStart.x;
		translate.y = e.clientY - dragStart.y;
		applyTransform();
	}

	function endDrag() {
		isDragging = false;
		imgEl.classList.remove('is-dragging');
	}

	var touchDist = 0;

	function startTouch(e) {
		if (e.touches.length === 1) {
			startDrag({ clientX: e.touches[0].clientX, clientY: e.touches[0].clientY });
		} else if (e.touches.length === 2) {
			touchDist = pinchDistance(e.touches);
		}
	}

	function onTouch(e) {
		e.preventDefault();
		if (e.touches.length === 2) {
			var d = pinchDistance(e.touches);
			if (touchDist > 0) {
				var factor = d / touchDist;
				scale = Math.min(6, Math.max(0.5, scale * factor));
				touchDist = d;
				applyTransform();
			}
		} else if (e.touches.length === 1 && isDragging) {
			translate.x = e.touches[0].clientX - dragStart.x;
			translate.y = e.touches[0].clientY - dragStart.y;
			applyTransform();
		}
	}

	function endTouch() {
		isDragging = false;
		imgEl.classList.remove('is-dragging');
		touchDist = 0;
	}

	function pinchDistance(touches) {
		var dx = touches[0].clientX - touches[1].clientX;
		var dy = touches[0].clientY - touches[1].clientY;
		return Math.sqrt(dx * dx + dy * dy);
	}

	function applyTransform() {
		imgEl.style.transform =
			'translate(' + translate.x + 'px,' + translate.y + 'px) ' +
			'scale(' + scale + ') ' +
			'rotate(' + rot + 'deg)';
	}

	function zoomBy(delta) {
		scale = Math.min(6, Math.max(0.5, scale + delta));
		applyTransform();
		updateZoomLabel();
	}

	function reset() {
		scale = 1;
		rot = 0;
		translate.x = 0;
		translate.y = 0;
		applyTransform();
		updateZoomLabel();
	}

	function updateZoomLabel() {
		if (zoomLevel) {
			zoomLevel.textContent = Math.round(scale * 100) + '%';
		}
	}

	function load(index) {
		if (!images.length) {
			return;
		}
		index = (index + images.length) % images.length;
		currentIndex = index;
		var src = images[index];
		reset();
		imgEl.src = src.src;
		imgEl.alt = src.alt || '';
		captionEl.textContent = src.caption || '';
	}

	function open(index) {
		lastFocused = document.activeElement;
		load(index);
		lightbox.classList.add('is-open');
		document.body.style.overflow = 'hidden';
		var closeBtn = lightbox.querySelector('[data-lb-close]');
		if (closeBtn) {
			closeBtn.focus();
		}
	}

	function close() {
		lightbox.classList.remove('is-open');
		document.body.style.overflow = '';
		reset();
		// 关闭后把焦点还给触发元素，避免键盘用户丢失位置。
		if (lastFocused && typeof lastFocused.focus === 'function') {
			lastFocused.focus();
		}
		lastFocused = null;
	}

	function prev() { load(currentIndex - 1); }
	function next() { load(currentIndex + 1); }

	// 收集图片。
	function collectImages() {
		images = [];
		var nodes = document.querySelectorAll('.entry-content img[data-lightbox]');
		nodes.forEach(function (img) {
			var src = img.getAttribute('data-full') || img.currentSrc || img.src;
			images.push({
				src: src,
				alt: img.alt || '',
				caption: (img.parentElement && img.parentElement.classList.contains('wp-caption'))
					? (img.parentElement.querySelector('.wp-caption-text') || {}).textContent || ''
					: ''
			});
			if (!img.dataset.lbBound) {
				img.dataset.lbBound = '1';
				// 让键盘用户也能打开灯箱。
				img.setAttribute('tabindex', '0');
				img.setAttribute('role', 'button');
				if (!img.getAttribute('aria-label')) {
					img.setAttribute('aria-label', l10n('openImage', '放大图片'));
				}

				var handler = function () {
					var idx = 0;
					for (var i = 0; i < images.length; i++) {
						if (images[i].src === src) {
							idx = i;
							break;
						}
					}
					open(idx);
				};

				img.addEventListener('click', handler);
				img.addEventListener('keydown', function (e) {
					if (e.key === 'Enter' || e.key === ' ') {
						e.preventDefault();
						handler();
					}
				});
			}
		});
	}

	function init() {
		build();
		collectImages();

		// 只观察正文区域（而不是整个 body），并对回调做防抖：
		// 翻图时 load() 会写入 caption，全量观察会造成无意义的重复收集。
		var timer = null;
		var observer = new MutationObserver(function () {
			if (lightbox.classList.contains('is-open')) {
				return;
			}
			if (timer) {
				clearTimeout(timer);
			}
			timer = setTimeout(collectImages, 200);
		});
		var scope = document.querySelector('.entry-content') || document.body;
		observer.observe(scope, { childList: true, subtree: true });
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();
