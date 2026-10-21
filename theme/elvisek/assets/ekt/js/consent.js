/**
 * EKT analytics — cookie lišta a Google Analytics 4 (Consent Mode v2).
 *
 * Výchozí stav „denied“ nastavuje inline skript v <head> (inc/ekt/analytics.php).
 * Po souhlasu: consent update → js → config → teprve pak se stáhne gtag.js, takže se změří už
 * první zobrazení. Bez souhlasu se ke Googlu nepošle nic. Volba platí data-ekt-consent-days dní
 * (výchozí 365), pak se lišta zeptá znovu. Po odmítnutí se smažou cookies _ga*.
 * localStorage: ekt-consent = {"v":"granted|denied","t":ms}; převezme ek-consent, iv-consent, mndConsent.
 */
(function () {
	'use strict';

	var bar = document.querySelector('[data-ekt-consent-bar]');
	if (!bar || typeof window.gtag !== 'function') {
		return;
	}

	var KEY = 'ekt-consent';
	var id = bar.getAttribute('data-ekt-ga') || '';
	var days = parseInt(bar.getAttribute('data-ekt-consent-days'), 10) || 365;
	var maxAge = days * 24 * 60 * 60 * 1000;
	var loaded = false;

	function store(key, value) {
		try {
			if (value === null) {
				window.localStorage.removeItem(key);
			} else {
				window.localStorage.setItem(key, value);
			}
		} catch (e) { /* soukromé okno */ }
	}

	function get(key) {
		try {
			return window.localStorage.getItem(key);
		} catch (e) {
			return null;
		}
	}

	function save(value) {
		store(KEY, JSON.stringify({ v: value, t: Date.now() }));
	}

	// Převzetí volby ze starších šablon (bez času = platí od teď), staré klíče pryč.
	function migrate() {
		if (get(KEY) !== null) {
			return;
		}
		var legacy = { 'ek-consent': { grant: 'granted', deny: 'denied' }, 'iv-consent': { yes: 'granted', no: 'denied' } };
		var value = null;
		var time = Date.now();
		Object.keys(legacy).forEach(function (key) {
			var old = get(key);
			if (old !== null) {
				if (!value && legacy[key][old]) {
					value = legacy[key][old];
				}
				store(key, null);
			}
		});
		var mnd = get('mndConsent');
		if (mnd !== null) {
			try {
				var parsed = JSON.parse(mnd);
				if (!value && parsed && (parsed.v === 'granted' || parsed.v === 'denied')) {
					value = parsed.v;
					time = parseInt(parsed.t, 10) || time;
				}
			} catch (e) { /* neplatná hodnota */ }
			store('mndConsent', null);
		}
		if (value) {
			store(KEY, JSON.stringify({ v: value, t: time }));
		}
	}

	function read() {
		try {
			var value = JSON.parse(get(KEY));
			if (value && value.t > Date.now() - maxAge && (value.v === 'granted' || value.v === 'denied')) {
				return value.v;
			}
		} catch (e) { /* neplatná hodnota */ }
		return null;
	}

	function load() {
		if (loaded || !id) {
			return;
		}
		loaded = true;
		window.gtag('consent', 'update', { analytics_storage: 'granted' });
		window.gtag('js', new Date());
		window.gtag('config', id);
		var script = document.createElement('script');
		script.async = true;
		script.src = 'https://www.googletagmanager.com/gtag/js?id=' + encodeURIComponent(id);
		document.head.appendChild(script);
	}

	// Smaže cookies _ga a _ga_XXXX na všech úrovních domény (www.example.com, .example.com…).
	function clearCookies() {
		var parts = window.location.hostname.split('.');
		document.cookie.split(';').forEach(function (cookie) {
			var name = cookie.split('=')[0].trim();
			if (!/^_ga(_|$)/.test(name)) {
				return;
			}
			for (var i = 0; i < parts.length; i++) {
				document.cookie = name + '=; Max-Age=0; path=/; domain=' + parts.slice(i).join('.');
			}
			document.cookie = name + '=; Max-Age=0; path=/';
		});
	}

	function show(focus) {
		bar.hidden = false;
		if (focus) {
			var first = bar.querySelector('[data-ekt-consent]');
			if (first) {
				first.focus();
			}
		}
	}

	function decide(value) {
		save(value);
		bar.hidden = true;
		if (value === 'granted') {
			load();
			return;
		}
		if (loaded) {
			window.gtag('consent', 'update', { analytics_storage: 'denied' });
		}
		clearCookies();
	}

	bar.addEventListener('click', function (event) {
		var button = event.target.closest('[data-ekt-consent]');
		if (button) {
			decide(button.getAttribute('data-ekt-consent') === 'granted' ? 'granted' : 'denied');
		}
	});

	document.addEventListener('click', function (event) {
		var opener = event.target.closest('[data-ekt-consent-open]');
		if (opener) {
			event.preventDefault();
			show(true);
		}
	});

	migrate();
	var choice = read();
	if (choice === 'granted') {
		load();
	} else if (choice === null) {
		show(false);
	}
})();
