/**
 * EKT color-scheme — ovladače světlého a tmavého režimu a široké stránky.
 *
 * Počáteční stav nastaví inline skript v <head> (inc/ekt/color-scheme.php). Tady:
 *  [data-ekt-theme-set="system|light|dark"]  trojstavový přepínač (aria-pressed)
 *  [data-ekt-theme-toggle]                   světlý ↔ tmavý
 *  [data-ekt-wide-toggle]                    široká / užší stránka (aria-pressed = široká)
 * Stav: <html data-theme="light|dark"> (bez atributu = systém), <html data-wide="0">.
 * localStorage: ekt-theme, ekt-wide (staré ek-theme, iv-theme, ek-wide převezme head skript).
 * Událost: document 'ekt:color-scheme' (detail: { mode, theme }).
 */
(function () {
	'use strict';

	var root = document.documentElement;
	var media = window.matchMedia ? window.matchMedia('(prefers-color-scheme: dark)') : null;

	function store(key, value) {
		try {
			if (value === null) {
				window.localStorage.removeItem(key);
			} else {
				window.localStorage.setItem(key, value);
			}
		} catch (e) { /* soukromé okno */ }
	}

	function mode() {
		var t = root.getAttribute('data-theme');
		return t === 'light' || t === 'dark' ? t : 'system';
	}

	function effective() {
		var m = mode();
		if (m !== 'system') {
			return m;
		}
		return media && media.matches ? 'dark' : 'light';
	}

	// theme-color podle skutečného režimu (meta s data-ekt-theme-color mají původní barvy v data-ekt-color).
	function syncThemeColor() {
		var metas = document.querySelectorAll('meta[data-ekt-theme-color]');
		if (!metas.length) {
			return;
		}
		var colors = {};
		metas.forEach(function (meta) {
			if (!meta.hasAttribute('data-ekt-color')) {
				meta.setAttribute('data-ekt-color', meta.content);
			}
			colors[meta.getAttribute('data-ekt-theme-color')] = meta.getAttribute('data-ekt-color');
		});
		var m = mode();
		metas.forEach(function (meta) {
			meta.content = m === 'system' ? meta.getAttribute('data-ekt-color') : (colors[m] || meta.getAttribute('data-ekt-color'));
		});
	}

	function sync() {
		var m = mode();
		document.querySelectorAll('[data-ekt-theme-set]').forEach(function (btn) {
			btn.setAttribute('aria-pressed', String(btn.getAttribute('data-ekt-theme-set') === m));
		});
		document.querySelectorAll('[data-ekt-theme-toggle]').forEach(function (btn) {
			btn.setAttribute('aria-pressed', String(effective() === 'dark'));
		});
		syncThemeColor();
	}

	function set(m) {
		if (m === 'light' || m === 'dark') {
			root.setAttribute('data-theme', m);
			store('ekt-theme', m);
		} else {
			root.removeAttribute('data-theme');
			store('ekt-theme', null);
		}
		sync();
		document.dispatchEvent(new CustomEvent('ekt:color-scheme', { detail: { mode: mode(), theme: effective() } }));
	}

	document.addEventListener('click', function (event) {
		var btn = event.target.closest('[data-ekt-theme-set], [data-ekt-theme-toggle], [data-ekt-wide-toggle]');
		if (!btn) {
			return;
		}
		if (btn.hasAttribute('data-ekt-theme-set')) {
			set(btn.getAttribute('data-ekt-theme-set'));
		} else if (btn.hasAttribute('data-ekt-theme-toggle')) {
			set(effective() === 'dark' ? 'light' : 'dark');
		} else {
			if (root.getAttribute('data-wide') === '0') {
				root.removeAttribute('data-wide');
				store('ekt-wide', null);
			} else {
				root.setAttribute('data-wide', '0');
				store('ekt-wide', '0');
			}
			syncWide();
			window.dispatchEvent(new Event('resize'));
		}
	});

	function syncWide() {
		document.querySelectorAll('[data-ekt-wide-toggle]').forEach(function (btn) {
			btn.setAttribute('aria-pressed', String(root.getAttribute('data-wide') !== '0'));
		});
	}

	if (media) {
		var onChange = function () {
			if (mode() === 'system') {
				sync();
			}
		};
		if (media.addEventListener) {
			media.addEventListener('change', onChange);
		} else if (media.addListener) {
			media.addListener(onChange);
		}
	}

	sync();
	syncWide();
})();
