/* EKT quick-search — našeptávač pod každým form[data-ekt-qs] s input[type=search] (REST ekt/v1/search).
 * Adresa API: hodnota data-ekt-qs, jinak window.EKT_QS.api. Ovládání šipkami, Enter, Esc. */
(() => {
	'use strict';
	const cfg = window.EKT_QS || {};
	const t = Object.assign({ all: 'Všechny výsledky pro „%s“', empty: 'Nic jsme nenašli. Zkuste jiné slovo.' }, cfg.texts || {});
	const esc = (s) => String(s).replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
	const reEsc = (s) => s.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');

	document.querySelectorAll('form[data-ekt-qs]').forEach((form, n) => {
		const input = form.querySelector('input[type="search"]');
		const api = form.dataset.ektQs || cfg.api;
		if (!input || !api) return;

		const box = document.createElement('div');
		box.className = 'ekt-qs';
		box.hidden = true;
		const list = document.createElement('ul');
		list.className = 'ekt-qs__list';
		list.id = 'ekt-qs-' + n;
		list.setAttribute('role', 'listbox');
		box.appendChild(list);
		form.appendChild(box);
		input.setAttribute('role', 'combobox');
		input.setAttribute('aria-autocomplete', 'list');
		input.setAttribute('aria-controls', list.id);
		input.setAttribute('aria-expanded', 'false');
		input.setAttribute('autocomplete', 'off');

		let timer = 0;
		let ctrl = null;
		let active = -1;
		const cache = new Map();
		const items = () => [...list.querySelectorAll('.ekt-qs__item')];
		const setActive = (i) => {
			const all = items();
			active = i;
			all.forEach((li, k) => { li.classList.toggle('is-active', k === i); li.setAttribute('aria-selected', String(k === i)); });
			if (i >= 0 && all[i]) { input.setAttribute('aria-activedescendant', all[i].id); all[i].scrollIntoView({ block: 'nearest' }); }
			else input.removeAttribute('aria-activedescendant');
		};
		const close = () => {
			box.hidden = true;
			form.classList.remove('is-qs-open');
			input.setAttribute('aria-expanded', 'false');
			setActive(-1);
		};
		const mark = (text, q) => {
			const words = q.split(/\s+/).filter((w) => w.length > 1).map((w) => reEsc(esc(w)));
			const safe = esc(text);
			return words.length ? safe.replace(new RegExp('(' + words.join('|') + ')', 'gi'), '<mark>$1</mark>') : safe;
		};
		const allUrl = (q) => {
			try { const u = new URL(form.getAttribute('action') || location.href, location.href); u.search = ''; u.searchParams.set(input.name || 's', q); return u.href; }
			catch (e) { return '?s=' + encodeURIComponent(q); }
		};
		const render = (q, rows) => {
			box.querySelectorAll('.ekt-qs__all, .ekt-qs__empty').forEach((el) => el.remove());
			list.innerHTML = rows.map((r, i) => `<li class="ekt-qs__item" role="option" id="${list.id}-${i}" aria-selected="false"><a class="ekt-qs__link" href="${esc(r.u)}" tabindex="-1"><span class="ekt-qs__title">${mark(r.t || '', q)}</span><span class="ekt-qs__meta">${esc([r.c, r.d].filter(Boolean).join(' · '))}</span></a></li>`).join('');
			if (rows.length) {
				const all = document.createElement('a');
				all.className = 'ekt-qs__all';
				all.href = allUrl(q);
				all.textContent = t.all.replace('%s', q);
				box.appendChild(all);
			} else {
				const empty = document.createElement('p');
				empty.className = 'ekt-qs__empty';
				empty.textContent = t.empty;
				box.appendChild(empty);
			}
			box.hidden = false;
			form.classList.add('is-qs-open');
			input.setAttribute('aria-expanded', 'true');
			setActive(-1);
		};
		const search = async (q) => {
			if (cache.has(q)) { render(q, cache.get(q)); return; }
			if (ctrl) ctrl.abort();
			ctrl = new AbortController();
			try {
				const res = await fetch(api + (api.includes('?') ? '&' : '?') + 'q=' + encodeURIComponent(q), { signal: ctrl.signal, headers: { Accept: 'application/json' } });
				if (!res.ok) return;
				const rows = await res.json();
				if (!Array.isArray(rows)) return;
				cache.set(q, rows);
				if (input.value.trim() === q) render(q, rows);
			} catch (e) { /* zrušeno nebo offline */ }
		};

		input.addEventListener('input', () => {
			clearTimeout(timer);
			const q = input.value.trim();
			if (q.length < 2) { close(); return; }
			timer = setTimeout(() => search(q), 200);
		});
		input.addEventListener('keydown', (e) => {
			if (e.key === 'Escape') { if (!box.hidden) { e.preventDefault(); close(); } return; }
			const all = items();
			if (box.hidden || !all.length) return;
			if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
				e.preventDefault();
				const step = e.key === 'ArrowDown' ? 1 : -1;
				// -1 = zpět v poli; cyklí: pole → 0 … poslední → pole.
				let next = active + step;
				if (next >= all.length) next = -1;
				if (next < -1) next = all.length - 1;
				setActive(next);
			} else if (e.key === 'Enter' && active >= 0) {
				e.preventDefault();
				const a = all[active].querySelector('a');
				if (a) location.href = a.href;
			}
		});
		input.addEventListener('focus', () => { if (list.children.length && input.value.trim().length >= 2) { box.hidden = false; form.classList.add('is-qs-open'); input.setAttribute('aria-expanded', 'true'); } });
		document.addEventListener('click', (e) => { if (!form.contains(e.target)) close(); });
		form.addEventListener('focusout', (e) => { if (e.relatedTarget && !form.contains(e.relatedTarget)) close(); });
	});
})();
