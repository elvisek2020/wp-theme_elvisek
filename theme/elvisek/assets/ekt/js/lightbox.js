/**
 * EKT lightbox — zvětšení obrázků v obsahu článku v nativním <dialog>.
 *
 * Jen prvky [data-ekt-lightbox] uvnitř [data-ekt-content] (označí je inc/ekt/lightbox.php):
 * <a href="…jpg"> otevře href, <img> bez odkazu největší verzi ze srcset. Skupina = hodnota
 * atributu (obrázky jedné skupiny se listují spolu). Dialog vytvoří skript sám (třídy ekt-lightbox__*).
 * Texty: window.ektLightbox.texts.
 */
(function () {
	'use strict';

	if (typeof HTMLDialogElement !== 'function') {
		return; // starý prohlížeč: odkazy fungují normálně
	}

	var cfg = window.ektLightbox || {};
	var t = Object.assign({ dialog: 'Náhled obrázku', close: 'Zavřít', prev: 'Předchozí obrázek', next: 'Další obrázek', zoom: 'Zvětšit obrázek' }, cfg.texts || {});

	var items = [];
	document.querySelectorAll('[data-ekt-content] [data-ekt-lightbox]').forEach(function (el) {
		if (el.tagName === 'A' || (el.tagName === 'IMG' && !el.closest('a'))) {
			items.push(el);
		}
	});
	if (!items.length) {
		return;
	}

	function largest(img) {
		var best = null;
		var bestW = 0;
		(img.getAttribute('srcset') || '').split(',').forEach(function (part) {
			var bits = part.trim().split(/\s+/);
			var w = parseInt(bits[1], 10) || 0;
			if (bits[0] && w >= bestW) {
				best = bits[0];
				bestW = w;
			}
		});
		return best || img.currentSrc || img.src;
	}

	function source(el) {
		return el.tagName === 'IMG' ? largest(el) : el.getAttribute('href');
	}

	function thumb(el) {
		return el.tagName === 'IMG' ? el : el.querySelector('img');
	}

	function caption(el) {
		var fig = el.closest('figure, .gallery-item, .wp-caption');
		var cap = fig && fig.querySelector('figcaption, .wp-caption-text, .gallery-caption');
		var img = thumb(el);
		return (cap && cap.textContent.trim()) || (img && img.getAttribute('alt')) || '';
	}

	var svg = function (d) {
		return '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="' + d + '"/></svg>';
	};

	var dlg = document.createElement('dialog');
	dlg.className = 'ekt-lightbox';
	dlg.setAttribute('aria-label', t.dialog);
	dlg.innerHTML =
		'<div class="ekt-lightbox__stage"><img class="ekt-lightbox__img" alt=""></div>' +
		'<button type="button" class="ekt-lightbox__btn ekt-lightbox__close"></button>' +
		'<button type="button" class="ekt-lightbox__btn ekt-lightbox__prev"></button>' +
		'<button type="button" class="ekt-lightbox__btn ekt-lightbox__next"></button>' +
		'<p class="ekt-lightbox__caption" aria-live="polite"></p>';
	document.body.appendChild(dlg);

	var img = dlg.querySelector('.ekt-lightbox__img');
	var cap = dlg.querySelector('.ekt-lightbox__caption');
	var btnClose = dlg.querySelector('.ekt-lightbox__close');
	var btnPrev = dlg.querySelector('.ekt-lightbox__prev');
	var btnNext = dlg.querySelector('.ekt-lightbox__next');
	btnClose.setAttribute('aria-label', t.close);
	btnClose.innerHTML = svg('M6 6l12 12M18 6 6 18');
	btnPrev.setAttribute('aria-label', t.prev);
	btnPrev.innerHTML = svg('M15 6l-6 6 6 6');
	btnNext.setAttribute('aria-label', t.next);
	btnNext.innerHTML = svg('M9 6l6 6-6 6');

	var set = [];
	var index = 0;
	var opener = null;

	function show(i) {
		index = (i + set.length) % set.length;
		var el = set[index];
		var text = caption(el);
		img.src = source(el);
		img.alt = (thumb(el) && thumb(el).getAttribute('alt')) || text;
		cap.textContent = [text, set.length > 1 ? (index + 1) + ' / ' + set.length : ''].filter(Boolean).join(' · ');
		cap.hidden = cap.textContent === '';
	}

	function open(el) {
		var group = el.getAttribute('data-ekt-lightbox');
		set = items.filter(function (item) {
			return item.getAttribute('data-ekt-lightbox') === group;
		});
		opener = el;
		btnPrev.hidden = btnNext.hidden = set.length < 2;
		show(set.indexOf(el));
		dlg.showModal();
	}

	items.forEach(function (el) {
		if (el.tagName === 'IMG') {
			el.tabIndex = 0;
			el.setAttribute('role', 'button');
			el.setAttribute('aria-label', t.zoom + (el.alt ? ': ' + el.alt : ''));
			el.addEventListener('keydown', function (e) {
				if (e.key === 'Enter' || e.key === ' ') {
					e.preventDefault();
					open(el);
				}
			});
		}
		el.addEventListener('click', function (e) {
			if (e.metaKey || e.ctrlKey || e.shiftKey || e.altKey || e.button !== 0) {
				return; // nový panel / okno
			}
			e.preventDefault();
			open(el);
		});
	});

	btnClose.addEventListener('click', function () { dlg.close(); });
	btnPrev.addEventListener('click', function () { show(index - 1); });
	btnNext.addEventListener('click', function () { show(index + 1); });
	dlg.addEventListener('click', function (e) {
		if (e.target === dlg || e.target.classList.contains('ekt-lightbox__stage')) {
			dlg.close();
		}
	});
	dlg.addEventListener('keydown', function (e) {
		if (set.length < 2) {
			return;
		}
		if (e.key === 'ArrowLeft') {
			show(index - 1);
		} else if (e.key === 'ArrowRight') {
			show(index + 1);
		}
	});
	dlg.addEventListener('close', function () {
		if (dlg.open) {
			return; // událost close dorazila až po novém otevření
		}
		img.removeAttribute('src');
		if (opener) {
			opener.focus();
		}
	});

	// Přejetí prstem na mobilu.
	var startX = null;
	dlg.addEventListener('touchstart', function (e) {
		startX = e.touches.length === 1 ? e.touches[0].clientX : null;
	}, { passive: true });
	dlg.addEventListener('touchend', function (e) {
		if (startX === null || set.length < 2) {
			return;
		}
		var dx = e.changedTouches[0].clientX - startX;
		if (Math.abs(dx) > 50) {
			show(index + (dx < 0 ? 1 : -1));
		}
		startX = null;
	});
})();
