/* EKT load-more — další články do [data-ekt-grid] z odkazu [data-ekt-more]: prvních N dávek
 * (window.EKT_LOAD_MORE.auto, výchozí 3) při dorolování samo, pak po kliknutí. Bez JS obyčejný odkaz. */
(() => {
	'use strict';
	const link = document.querySelector('[data-ekt-more]');
	const grid = document.querySelector('[data-ekt-grid]');
	if (!link || !grid || !window.fetch || !window.DOMParser) return;
	const box = link.closest('[data-ekt-more-box]') || link.parentElement;
	const cfg = window.EKT_LOAD_MORE || {};
	let auto = Number.isFinite(Number(cfg.auto)) ? Number(cfg.auto) : 3;
	let busy = false;
	let io = null;

	const finish = () => { if (io) io.disconnect(); box.remove(); };
	const load = async (byUser) => {
		if (busy) return;
		busy = true;
		box.classList.add('is-loading');
		box.setAttribute('aria-busy', 'true');
		try {
			const res = await fetch(link.href, { credentials: 'same-origin', headers: { Accept: 'text/html' } });
			if (!res.ok) throw new Error(String(res.status));
			const doc = new DOMParser().parseFromString(await res.text(), 'text/html');
			const nextGrid = doc.querySelector('[data-ekt-grid]');
			const items = nextGrid ? [...nextGrid.children] : [];
			items.forEach((el) => grid.appendChild(document.importNode(el, true)));
			const next = doc.querySelector('[data-ekt-more]');
			if (next && next.getAttribute('href')) link.href = new URL(next.getAttribute('href'), link.href).href;
			else finish();
			if (byUser && items[0]) {
				const first = grid.children[grid.children.length - items.length];
				const focusable = first && first.querySelector('a:not([tabindex="-1"])');
				if (focusable) focusable.focus({ preventScroll: true });
			}
		} catch (err) {
			if (byUser) window.location.href = link.href;
		} finally {
			box.classList.remove('is-loading');
			box.removeAttribute('aria-busy');
			busy = false;
			// Odkaz je po načtení pořád v dosahu → znovu pozorovat (observer hlásí jen změnu).
			if (io && auto > 0 && box.isConnected) { io.unobserve(box); io.observe(box); }
		}
	};

	link.addEventListener('click', (e) => {
		if (e.metaKey || e.ctrlKey || e.shiftKey || e.button === 1) return;
		e.preventDefault();
		load(true);
	});
	if (auto > 0 && 'IntersectionObserver' in window) {
		io = new IntersectionObserver((entries) => {
			if (!entries.some((en) => en.isIntersecting) || auto <= 0 || busy) return;
			auto -= 1;
			if (auto <= 0) io.disconnect();
			load(false);
		}, { rootMargin: '0px 0px 600px 0px' });
		io.observe(box);
	}
})();
