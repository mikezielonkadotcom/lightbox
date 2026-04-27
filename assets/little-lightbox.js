/**
 * This Little Lightbox of Mine v2.2.0 — Enhanced Mode JS
 *
 * Vanilla JS: modal, gallery, swipe, keyboard, animation, focus trap.
 * No dependencies. ES2017+.
 */
(function () {
	'use strict';

	// ── State ────────────────────────────────────────────────────────────
	var config = window.mzvLbConfig || {};
	var modal, modalImg, modalCaption, modalClose, modalPrev, modalNext, modalCounter, modalJump, modalBackdrop;
	var activeGroup = null;
	var activeIndex = 0;
	var lastFocused = null;
	var pendingJump = false;
	var isOpen = false;
	var groups = { content: [], recipe: [] };

	// Swipe tracking.
	var touchStartX = 0;
	var touchStartY = 0;

	// ── Helpers ───────────────────────────────────────────────────────────
	function prefersReducedMotion() {
		return window.matchMedia('(prefers-reduced-motion: reduce)').matches;
	}

	function shouldAnimate() {
		return config.animationsEnabled && !prefersReducedMotion();
	}

	function getDuration() {
		return shouldAnimate() ? (config.animationDurationMs || 200) : 0;
	}

	function trackLightboxOpen(wrapEl) {
		var imageSrc = wrapEl.getAttribute('data-mzv-lb-src') || '';
		var imageAlt = wrapEl.getAttribute('data-mzv-lb-caption') || '';
		var img = wrapEl.querySelector('img');

		if (!imageAlt && img) {
			imageAlt = img.getAttribute('alt') || '';
		}

		if (typeof gtag === 'function') {
			gtag('event', 'lightbox_open', {
				'event_category': 'engagement',
				'event_label': imageSrc || imageAlt || 'unknown',
				'image_url': imageSrc
			});
		}
	}

	// ── Init ─────────────────────────────────────────────────────────────
	function init() {
		var wraps = document.querySelectorAll('.mzv-lb-wrap');
		if (!wraps.length) return;

		// Build gallery groups.
		wraps.forEach(function (wrap) {
			var group = wrap.getAttribute('data-mzv-lb-group') || 'content';
			if (!groups[group]) groups[group] = [];
			groups[group].push(wrap);
		});

		createModal();

		// Attach click/keyboard handlers to triggers.
		wraps.forEach(function (wrap) {
			wrap.addEventListener('click', function (e) {
				e.preventDefault();
				openModal(wrap);
			});
			wrap.addEventListener('keydown', function (e) {
				if (e.key === 'Enter' || e.key === ' ') {
					e.preventDefault();
					openModal(wrap);
				}
			});
		});
	}

	// ── Modal DOM ────────────────────────────────────────────────────────
	function createModal() {
		modal = document.createElement('div');
		modal.id = 'mzv-lb-modal';
		modal.setAttribute('role', 'dialog');
		modal.setAttribute('aria-modal', 'true');
		modal.setAttribute('aria-label', 'Image lightbox');
		modal.setAttribute('aria-hidden', 'true');

		var i18n = config.i18n || {};

		modalBackdrop = document.createElement('div');
		modalBackdrop.className = 'mzv-lb-backdrop';
		modal.appendChild(modalBackdrop);

		modalPrev = document.createElement('button');
		modalPrev.className = 'mzv-lb-prev is-hidden';
		modalPrev.setAttribute('aria-label', i18n.prev || 'Previous image');
		modal.appendChild(modalPrev);

		modalNext = document.createElement('button');
		modalNext.className = 'mzv-lb-next is-hidden';
		modalNext.setAttribute('aria-label', i18n.next || 'Next image');
		modal.appendChild(modalNext);

		var content = document.createElement('div');
		content.className = 'mzv-lb-content';

		modalImg = document.createElement('img');
		modalImg.className = 'mzv-lb-full';
		modalImg.setAttribute('loading', 'lazy');
		modalImg.setAttribute('decoding', 'async');
		content.appendChild(modalImg);

		modalCaption = document.createElement('span');
		modalCaption.className = 'mzv-lb-caption';
		content.appendChild(modalCaption);

		modalJump = document.createElement('a');
		modalJump.className = 'mzv-lb-jump-link is-hidden';
		modalJump.href = '#';
		modalJump.setAttribute('role', 'button');
		modalJump.textContent = i18n.jumpToRecipe || 'Jump to Recipe ↓';
		content.appendChild(modalJump);

		modalCounter = document.createElement('span');
		modalCounter.className = 'mzv-lb-counter is-hidden';
		modalCounter.setAttribute('aria-live', 'polite');
		content.appendChild(modalCounter);

		modal.appendChild(content);

		modalClose = document.createElement('button');
		modalClose.className = 'mzv-lb-close';
		modalClose.setAttribute('aria-label', i18n.close || 'Close image');
		modal.appendChild(modalClose);

		document.body.appendChild(modal);

		// Event listeners.
		modalClose.addEventListener('click', closeModal);
		modalBackdrop.addEventListener('click', closeModal);
		modalPrev.addEventListener('click', function () { navigate(-1); });
		modalNext.addEventListener('click', function () { navigate(1); });
		modalJump.addEventListener('click', function (e) {
			e.preventDefault();
			pendingJump = true;
			closeModal();
		});

		// Touch events on content area.
		modal.addEventListener('touchstart', onTouchStart, { passive: true });
		modal.addEventListener('touchend', onTouchEnd, { passive: true });
	}

	// ── Open / Close ─────────────────────────────────────────────────────
	function openModal(wrapEl) {
		lastFocused = wrapEl;
		pendingJump = false;

		var group = wrapEl.getAttribute('data-mzv-lb-group') || 'content';
		activeGroup = groups[group] || [];
		activeIndex = activeGroup.indexOf(wrapEl);
		if (activeIndex < 0) activeIndex = 0;

		updateModalContent();
		trackLightboxOpen(wrapEl);

		// Show modal.
		modal.style.display = 'flex';
		modal.setAttribute('aria-hidden', 'false');

		// Set animation duration.
		var duration = getDuration();
		modal.style.setProperty('--mzv-lb-duration', duration + 'ms');

		if (shouldAnimate()) {
			// Force reflow before adding class.
			modal.offsetHeight; // eslint-disable-line no-unused-expressions
			modal.classList.add('is-visible');
			requestAnimationFrame(function () {
				modal.classList.add('is-open');
			});
		} else {
			modal.classList.add('is-visible', 'is-open');
		}

		// Lock body scroll.
		document.documentElement.classList.add('mzv-lb-open');

		// Focus close button.
		modalClose.focus();

		// Attach keyboard listeners.
		document.addEventListener('keydown', onKeyDown);
		modal.addEventListener('keydown', trapFocus);

		isOpen = true;
	}

	function closeModal() {
		if (!isOpen) return;
		isOpen = false;

		document.removeEventListener('keydown', onKeyDown);
		modal.removeEventListener('keydown', trapFocus);

		var duration = getDuration();

		if (duration > 0) {
			modal.classList.remove('is-open');

			var fallback = setTimeout(afterClose, duration + 50);
			modal.addEventListener('transitionend', function handler() {
				clearTimeout(fallback);
				modal.removeEventListener('transitionend', handler);
				afterClose();
			});
		} else {
			modal.classList.remove('is-open');
			afterClose();
		}
	}

	function scrollToRecipe() {
		var selector = config.recipeCardSelector || '[id^="wprm-recipe-container-"], .wprm-recipe-container';
		var target = document.querySelector(selector);

		if (target) {
			target.scrollIntoView({ behavior: 'smooth', block: 'start' });
		}
	}

	function afterClose() {
		modal.classList.remove('is-visible');
		modal.style.display = 'none';
		modal.setAttribute('aria-hidden', 'true');

		// Restore scroll.
		document.documentElement.classList.remove('mzv-lb-open');

		// Clear image src.
		modalImg.src = '';
		modalImg.alt = '';

		// Restore focus.
		if (lastFocused) {
			lastFocused.focus();
		}

		// Execute pending jump scroll after the close flow completes.
		if (pendingJump) {
			pendingJump = false;
			setTimeout(scrollToRecipe, 150);
		}
	}

	// ── Update modal content ─────────────────────────────────────────────
	function pageHasRecipeCard() {
		return !!document.querySelector('.wprm-recipe-container');
	}

	function isInsideRecipeCard(wrapEl) {
		return !!(wrapEl && wrapEl.closest('.wprm-recipe-container'));
	}

	function shouldShowJumpLink(wrapEl) {
		return !!(config.wprmJumpEnabled && pageHasRecipeCard() && !isInsideRecipeCard(wrapEl));
	}

	function updateModalContent() {
		var wrapEl = activeGroup[activeIndex];
		if (!wrapEl) return;

		var src = wrapEl.getAttribute('data-mzv-lb-src') || '';
		var caption = wrapEl.getAttribute('data-mzv-lb-caption') || '';

		modalImg.src = src;
		modalImg.alt = caption;

		// Caption.
		modalCaption.textContent = caption;
		modalCaption.classList.toggle('is-empty', !caption);

		// Jump link: only show when this page has a WPRM recipe card and the
		// active image is outside that recipe card.
		if (shouldShowJumpLink(wrapEl)) {
			modalJump.classList.remove('is-hidden');
		} else {
			modalJump.classList.add('is-hidden');
		}

		// Gallery controls.
		var galleryEnabled = config.galleryEnabled && activeGroup.length > 1;
		var i18n = config.i18n || {};

		if (galleryEnabled) {
			modalPrev.classList.remove('is-hidden');
			modalNext.classList.remove('is-hidden');
			modalCounter.classList.remove('is-hidden');

			var counterText = (i18n.counter || '%1$d of %2$d')
				.replace('%1$d', activeIndex + 1)
				.replace('%2$d', activeGroup.length);
			modalCounter.textContent = counterText;
		} else {
			modalPrev.classList.add('is-hidden');
			modalNext.classList.add('is-hidden');
			modalCounter.classList.add('is-hidden');
		}
	}

	// ── Navigation ───────────────────────────────────────────────────────
	function navigate(direction) {
		if (!config.galleryEnabled || activeGroup.length <= 1) return;

		activeIndex = (activeIndex + direction + activeGroup.length) % activeGroup.length;

		// Brief opacity transition for image swap.
		if (shouldAnimate()) {
			modalImg.style.opacity = '0';
			setTimeout(function () {
				updateModalContent();
				modalImg.style.opacity = '1';
			}, 50);
		} else {
			updateModalContent();
		}
	}

	// ── Swipe ────────────────────────────────────────────────────────────
	function onTouchStart(e) {
		if (!e.touches || !e.touches.length) return;
		touchStartX = e.touches[0].clientX;
		touchStartY = e.touches[0].clientY;
	}

	function onTouchEnd(e) {
		if (!e.changedTouches || !e.changedTouches.length) return;
		var dx = e.changedTouches[0].clientX - touchStartX;
		var dy = e.changedTouches[0].clientY - touchStartY;

		// Ignore vertical swipes.
		if (Math.abs(dy) > Math.abs(dx)) return;

		// Require minimum horizontal distance.
		if (Math.abs(dx) < 50) return;

		if (dx < 0) {
			navigate(1);  // Swipe left → next.
		} else {
			navigate(-1); // Swipe right → prev.
		}
	}

	// ── Focus Trap ───────────────────────────────────────────────────────
	function trapFocus(e) {
		if (e.key !== 'Tab') return;

		var focusable = modal.querySelectorAll('button:not(.is-hidden), a:not(.is-hidden), [tabindex]:not([tabindex="-1"])');
		if (!focusable.length) return;

		var first = focusable[0];
		var last = focusable[focusable.length - 1];

		if (e.shiftKey) {
			if (document.activeElement === first) {
				e.preventDefault();
				last.focus();
			}
		} else {
			if (document.activeElement === last) {
				e.preventDefault();
				first.focus();
			}
		}
	}

	// ── Keyboard ─────────────────────────────────────────────────────────
	function onKeyDown(e) {
		switch (e.key) {
			case 'Escape':
				closeModal();
				break;
			case 'ArrowRight':
				navigate(1);
				break;
			case 'ArrowLeft':
				navigate(-1);
				break;
		}
	}

	document.addEventListener('DOMContentLoaded', init);
})();
