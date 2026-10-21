/* EKT pwa — registrace service workeru (cesty z window.EKT_PWA, funguje i ve WP v podsložce)
 * a na offline stránce seznam uložených stránek v [data-ekt-offline-list]. */
(() => {
	'use strict';
	const cfg = window.EKT_PWA || {};
	if (!('serviceWorker' in navigator) || !window.isSecureContext || !cfg.sw) return;
	if (document.body && document.body.classList.contains('logged-in')) return;

	window.addEventListener('load', () => {
		navigator.serviceWorker.register(cfg.sw, { scope: cfg.scope || '/' }).catch(() => {});
	});

	const list = document.querySelector('[data-ekt-offline-list]');
	if (!list || !cfg.index || !navigator.serviceWorker.controller) return;
	fetch(cfg.index).then((r) => r.json()).then((idx) => {
		const items = Object.entries(idx || {}).sort((a, b) => b[1].d - a[1].d).slice(0, 30);
		if (!items.length) return;
		const ul = list.tagName === 'UL' || list.tagName === 'OL' ? list : list.appendChild(document.createElement('ul'));
		if (ul !== list) ul.className = 'ekt-offline__list';
		items.forEach(([url, it]) => {
			const li = document.createElement('li');
			const a = document.createElement('a');
			a.href = url;
			a.textContent = (it && it.t) || url;
			li.appendChild(a);
			ul.appendChild(li);
		});
		const box = list.closest('[data-ekt-offline]');
		if (box) box.hidden = false;
		list.hidden = false;
	}).catch(() => {});
})();
