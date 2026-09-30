/* ElvisEK — chování šablony (bez závislostí). */
(() => {
	'use strict';
	const root = document.documentElement;

	/* Světlý / tmavý režim */
	const systemDark = () => window.matchMedia('(prefers-color-scheme: dark)').matches;
	const currentTheme = () => root.dataset.theme || (systemDark() ? 'dark' : 'light');
	document.querySelectorAll('[data-ek-theme-toggle]').forEach((btn) => {
		const sync = () => btn.setAttribute('aria-pressed', String(currentTheme() === 'dark'));
		sync();
		btn.addEventListener('click', () => {
			const next = currentTheme() === 'dark' ? 'light' : 'dark';
			root.dataset.theme = next;
			try { localStorage.setItem('ek-theme', next); } catch (e) { /* soukromé okno */ }
			sync();
		});
	});

	/* Mobilní menu */
	const navToggle = document.querySelector('.ek-nav-toggle');
	const nav = document.getElementById('ek-nav');
	if (navToggle && nav) {
		navToggle.addEventListener('click', () => {
			const open = nav.classList.toggle('is-open');
			navToggle.setAttribute('aria-expanded', String(open));
		});
	}

	/* Vyhledávání v hlavičce */
	const searchToggle = document.querySelector('[data-ek-search-toggle]');
	const searchBar = document.getElementById('ek-search');
	if (searchToggle && searchBar) {
		searchToggle.addEventListener('click', () => {
			const open = searchBar.hidden;
			searchBar.hidden = !open;
			searchToggle.setAttribute('aria-expanded', String(open));
			if (open) searchBar.querySelector('input[type="search"]')?.focus();
		});
		document.addEventListener('keydown', (e) => {
			if (e.key === 'Escape' && !searchBar.hidden) {
				searchBar.hidden = true;
				searchToggle.setAttribute('aria-expanded', 'false');
				searchToggle.focus();
			}
		});
	}

	/* Tisk */
	document.querySelectorAll('[data-ek-print]').forEach((b) => b.addEventListener('click', () => window.print()));

	/* Tlačítko Kopírovat u bloků kódu */
	document.querySelectorAll('.ek-content pre').forEach((pre) => {
		if (pre.closest('.ek-code-wrap')) return;
		const wrap = document.createElement('div');
		wrap.className = 'ek-code-wrap';
		pre.parentNode.insertBefore(wrap, pre);
		wrap.appendChild(pre);
		if (!navigator.clipboard) return;
		const btn = document.createElement('button');
		btn.type = 'button';
		btn.className = 'ek-copy';
		btn.textContent = 'Kopírovat';
		btn.addEventListener('click', async () => {
			try {
				await navigator.clipboard.writeText(pre.innerText.replace(/\n$/, ''));
				btn.textContent = 'Zkopírováno ✓';
				btn.classList.add('is-done');
			} catch (e) {
				btn.textContent = 'Nelze kopírovat';
			}
			setTimeout(() => { btn.textContent = 'Kopírovat'; btn.classList.remove('is-done'); }, 1800);
		});
		wrap.appendChild(btn);
	});

	/* Načíst další články (bez JS funguje jako odkaz na další stránku) */
	document.addEventListener('click', async (e) => {
		const link = e.target.closest('[data-ek-more]');
		if (!link) return;
		const grid = document.querySelector('[data-ek-grid]');
		if (!grid) return;
		e.preventDefault();
		const box = link.parentElement;
		box.classList.add('is-loading');
		try {
			const res = await fetch(link.href, { credentials: 'same-origin' });
			if (!res.ok) throw new Error(res.status);
			const doc = new DOMParser().parseFromString(await res.text(), 'text/html');
			const items = doc.querySelectorAll('[data-ek-grid] > *');
			items.forEach((el) => grid.appendChild(document.importNode(el, true)));
			const next = doc.querySelector('[data-ek-more]');
			if (next) { link.href = next.href; } else { box.remove(); }
			items[0]?.querySelector('a:not([tabindex])')?.focus({ preventScroll: true });
		} catch (err) {
			window.location.href = link.href;
		} finally {
			box.classList.remove('is-loading');
		}
	});


	/* Obsah článku nad textem (zobrazí se, když není vidět boční panel — řeší CSS) */
	const tocTpl = document.getElementById('ek-toc-tpl');
	const content = document.querySelector('[data-ek-content]');
	if (tocTpl && content && tocTpl.content.querySelector('.ek-toc')) {
		const det = document.createElement('details');
		det.className = 'ek-toc-inline';
		const sum = document.createElement('summary');
		sum.textContent = 'Obsah článku';
		det.appendChild(sum);
		det.appendChild(tocTpl.content.cloneNode(true));
		det.addEventListener('click', (e) => { if (e.target.closest('a')) det.open = false; });
		content.parentNode.insertBefore(det, content);
	}

	/* Zvýraznění aktuální položky v obsahu článku */
	const tocLinks = document.querySelectorAll('.ek-sidebar .ek-toc a[href^="#"]');
	if (tocLinks.length && 'IntersectionObserver' in window) {
		const map = new Map();
		tocLinks.forEach((a) => {
			const el = document.getElementById(decodeURIComponent(a.hash.slice(1)));
			if (el) map.set(el, a);
		});
		const io = new IntersectionObserver((entries) => {
			entries.forEach((en) => {
				if (en.isIntersecting) {
					tocLinks.forEach((a) => a.classList.remove('is-active'));
					map.get(en.target)?.classList.add('is-active');
				}
			});
		}, { rootMargin: '-90px 0px -70% 0px' });
		map.forEach((_, el) => io.observe(el));
	}

	/* Odkaz z obsahu na zavřenou sekci ji otevře */
	const openTarget = () => {
		const el = location.hash && document.getElementById(decodeURIComponent(location.hash.slice(1)));
		if (el && el.tagName === 'DETAILS') el.open = true;
	};
	window.addEventListener('hashchange', openTarget);
	openTarget();
})();
