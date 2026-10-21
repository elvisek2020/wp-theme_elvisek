/**
 * EKT totop — tlačítko [data-ekt-totop] se ukáže po odrolování zhruba jedné obrazovky.
 * Ukázané: bez hidden, s třídou is-shown. Klik = nahoru (plynule jen bez prefers-reduced-motion).
 */
(function () {
	'use strict';

	var buttons = document.querySelectorAll('[data-ekt-totop]');
	if (!buttons.length) {
		return;
	}

	var shown = null;
	var ticking = false;

	function sync() {
		ticking = false;
		var show = window.scrollY > Math.max(600, window.innerHeight);
		if (show === shown) {
			return;
		}
		shown = show;
		buttons.forEach(function (btn) {
			btn.hidden = !show;
			btn.classList.toggle('is-shown', show);
		});
	}

	window.addEventListener('scroll', function () {
		if (!ticking) {
			ticking = true;
			window.requestAnimationFrame(sync);
		}
	}, { passive: true });

	buttons.forEach(function (btn) {
		btn.addEventListener('click', function () {
			var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
			window.scrollTo({ top: 0, behavior: reduce ? 'auto' : 'smooth' });
			var target = document.getElementById('main') || document.body;
			if (target && target.focus) {
				if (!target.hasAttribute('tabindex')) {
					target.setAttribute('tabindex', '-1');
				}
				target.focus({ preventScroll: true });
			}
		});
	});

	sync();
})();
