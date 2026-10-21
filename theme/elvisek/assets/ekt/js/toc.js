/* EKT toc — zvýraznění aktuální sekce v obsahu článku ([data-ekt-toc]) a otevření zavřené
 * sekce <details> při odkazu na ni. Cíle hledá jen uvnitř [data-ekt-content]. */
(() => {
	'use strict';
	const content = document.querySelector('[data-ekt-content]');
	if (!content) return;

	const byHash = (hash) => {
		if (!hash || hash.length < 2) return null;
		let id = hash.slice(1);
		try { id = decodeURIComponent(id); } catch (e) { /* neplatné kódování */ }
		const el = document.getElementById(id);
		return el && content.contains(el) ? el : null;
	};

	/* Odkaz na zavřenou sekci <details> (i vnořenou) ji otevře. */
	const openTarget = () => {
		const el = byHash(location.hash);
		if (!el) return;
		for (let node = el; node && node !== content; node = node.parentElement) {
			if (node.tagName === 'DETAILS') node.open = true;
		}
	};
	window.addEventListener('hashchange', openTarget);
	openTarget();

	/* Zvýraznění: poslední sekce, jejíž začátek je nad horní třetinou okna. */
	const links = [...document.querySelectorAll('[data-ekt-toc] a[href^="#"]')];
	if (!links.length || !('IntersectionObserver' in window)) return;
	const targets = new Map();
	links.forEach((a) => {
		const el = byHash(a.hash);
		if (!el) return;
		if (!targets.has(el)) targets.set(el, []);
		targets.get(el).push(a);
	});
	if (!targets.size) return;
	const order = [...targets.keys()];
	const visible = new Set();
	const setActive = (el) => {
		links.forEach((a) => { a.classList.remove('is-active'); a.removeAttribute('aria-current'); });
		(targets.get(el) || []).forEach((a) => { a.classList.add('is-active'); a.setAttribute('aria-current', 'location'); });
	};
	const io = new IntersectionObserver((entries) => {
		entries.forEach((en) => { if (en.isIntersecting) visible.add(en.target); else visible.delete(en.target); });
		const first = order.find((el) => visible.has(el));
		if (first) { setActive(first); return; }
		// Nic v pásmu: poslední sekce nad ním.
		const above = order.filter((el) => el.getBoundingClientRect().top < 0).pop();
		if (above) setActive(above);
	}, { rootMargin: '-80px 0px -65% 0px' });
	order.forEach((el) => io.observe(el));
})();
