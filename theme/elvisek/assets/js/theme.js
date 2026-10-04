/* ElvisEK — chování šablony (bez závislostí). */
(() => {
	'use strict';
	const root = document.documentElement;

	/* Vzhled v patičce: Systém / Světlý / Tmavý + Široká stránka (uloženo v localStorage) */
	const store = (k, v) => { try { v === null ? localStorage.removeItem(k) : localStorage.setItem(k, v); } catch (e) { /* soukromé okno */ } };
	const systemDark = () => window.matchMedia('(prefers-color-scheme: dark)').matches;
	const currentTheme = () => root.dataset.theme || (systemDark() ? 'dark' : 'light');
	const syncHero = () => {
		const heroLight = document.querySelector('[data-ek-hero-light]');
		if (!heroLight) return;
		heroLight.media = root.dataset.theme ? (root.dataset.theme === 'light' ? 'all' : 'not all') : '(prefers-color-scheme: light)';
	};
	const themeBtns = document.querySelectorAll('[data-ek-theme-set]');
	const syncTheme = () => {
		const mode = root.dataset.theme || 'system';
		themeBtns.forEach((b) => b.setAttribute('aria-pressed', String(b.dataset.ekThemeSet === mode)));
	};
	themeBtns.forEach((b) => b.addEventListener('click', () => {
		const mode = b.dataset.ekThemeSet;
		if (mode === 'system') { delete root.dataset.theme; store('ek-theme', null); }
		else { root.dataset.theme = mode; store('ek-theme', mode); }
		syncTheme(); syncHero();
	}));
	syncTheme();
	const wideBtn = document.querySelector('[data-ek-wide-toggle]');
	if (wideBtn) {
		// Výchozí je široká stránka; „0“ = užší (3 sloupce)
		const syncWide = () => wideBtn.setAttribute('aria-pressed', String(root.dataset.wide !== '0'));
		wideBtn.addEventListener('click', () => {
			if (root.dataset.wide === '0') { delete root.dataset.wide; store('ek-wide', null); }
			else { root.dataset.wide = '0'; store('ek-wide', '0'); }
			syncWide();
			window.dispatchEvent(new Event('resize'));
		});
		syncWide();
	}

	/* Hamburger: když se témata do lišty nevejdou, schovají se a nabídnou se v rozbalovacím menu */
	let closeMenu = () => {};
	const menuBar = document.querySelector('[data-ek-bar]');
	const menuBtn = menuBar?.querySelector('[data-ek-menu-toggle]');
	const menu = menuBar?.querySelector('[data-ek-menu]');
	const menuNav = menuBar?.querySelector('[data-ek-chipsnav]');
	if (menuBar && menuBtn && menu && menuNav) {
		const setMenu = (open) => {
			menu.hidden = !open;
			menuBar.classList.toggle('is-menu-open', open);
			menuBtn.setAttribute('aria-expanded', String(open));
			menuBtn.setAttribute('aria-label', open ? 'Zavřít témata' : 'Témata');
		};
		closeMenu = () => { if (!menu.hidden) setMenu(false); };
		const syncMode = () => {
			const compact = menuBar.classList.contains('is-menu');
			// V režimu menu zabírá hamburger místo navíc – přičíst ho, ať se lišta nepřepíná tam a zpět.
			const extra = compact ? menuBtn.offsetWidth + 20 : 0;
			const fits = menuNav.scrollWidth <= menuNav.clientWidth + extra + 1;
			if (fits === !compact) return;
			menuBar.classList.toggle('is-menu', !fits);
			if (fits) closeMenu();
		};
		syncMode();
		if ('ResizeObserver' in window) { const ro = new ResizeObserver(syncMode); ro.observe(menuBar); if (menuNav.firstElementChild) ro.observe(menuNav.firstElementChild); }
		else window.addEventListener('resize', syncMode);
		menuBtn.addEventListener('click', () => setMenu(menu.hidden));
		document.addEventListener('keydown', (e) => {
			if (e.key === 'Escape' && !menu.hidden) { setMenu(false); menuBtn.focus(); }
		});
		document.addEventListener('click', (e) => {
			if (!menu.hidden && !menu.contains(e.target) && !menuBtn.contains(e.target)) setMenu(false);
		});
	}

	/* Titulka: plynulý přechod dlaždice → lišta řízený rolováním.
	   --ek-p (0–1) = jak moc je velká hlavička odrolovaná; dlaždice podle něj blednou a zmenšují se,
	   lišta současně sjíždí shora. Nahoře p = 0 (jen dlaždice), po odrolování hlavičky p = 1 (jen lišta). */
	const heroHead = document.querySelector('[data-ek-hero-head]');
	const bar = document.querySelector('[data-ek-bar]');
	if (heroHead && bar) {
		let ticking = false;
		let shown = null;
		const update = () => {
			ticking = false;
			const range = Math.max(1, heroHead.offsetHeight - 16);
			const p = Math.min(1, Math.max(0, window.scrollY / range));
			root.style.setProperty('--ek-p', p.toFixed(3));
			// Lišta vyjíždí rychleji než mizí dlaždice (celá venku už v ~55 % cesty).
			root.style.setProperty('--ek-q', Math.min(1, p * 1.8).toFixed(3));
			const show = p > 0.3;
			if (show !== shown) {
				shown = show;
				bar.classList.toggle('is-shown', show);
				bar.toggleAttribute('inert', !show);
				bar.setAttribute('aria-hidden', String(!show));
				if (!show) closeMenu();
			}
		};
		window.addEventListener('scroll', () => {
			if (!ticking) { ticking = true; requestAnimationFrame(update); }
		}, { passive: true });
		window.addEventListener('resize', update);
		update();
	}

	/* Vyhledávání: pole vyjede vedle lupy. Lupa s textem = hledat, prázdná = zavřít. */
	document.querySelectorAll('[data-ek-qs]').forEach((qs) => {
		const btn = qs.querySelector('[data-ek-search-toggle]');
		const form = qs.querySelector('form');
		const input = qs.querySelector('input[type="search"]');
		if (!btn || !form || !input) return;
		const setOpen = (open) => {
			qs.classList.toggle('is-open', open);
			btn.setAttribute('aria-expanded', String(open));
			btn.setAttribute('aria-label', open ? 'Hledat (prázdné pole zavře)' : 'Hledat');
			input.tabIndex = open ? 0 : -1;
			if (open) input.focus();
		};
		btn.addEventListener('click', () => {
			if (!qs.classList.contains('is-open')) { setOpen(true); return; }
			if (input.value.trim()) { form.requestSubmit ? form.requestSubmit() : form.submit(); } else { setOpen(false); }
		});
		form.addEventListener('submit', (e) => { if (!input.value.trim()) { e.preventDefault(); } });
		qs.addEventListener('keydown', (e) => {
			if (e.key === 'Escape' && qs.classList.contains('is-open')) { setOpen(false); btn.focus(); }
		});
		document.addEventListener('click', (e) => {
			if (qs.classList.contains('is-open') && !qs.contains(e.target) && !input.value.trim()) setOpen(false);
		});
	});

	/* Rychlé hledání během psaní: až 6 výsledků pod polem (REST ek/v1/search) */
	const escHtml = (s) => String(s).replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
	document.querySelectorAll('[data-ek-qs][data-ek-search-api]').forEach((qs, n) => {
		const api = qs.dataset.ekSearchApi;
		const form = qs.querySelector('form');
		const input = qs.querySelector('input[type="search"]');
		if (!api || !form || !input) return;
		const box = document.createElement('div');
		box.className = 'ek-qs__results';
		box.id = 'ek-qs-results-' + n;
		box.hidden = true;
		box.setAttribute('role', 'listbox');
		qs.appendChild(box);
		input.setAttribute('role', 'combobox');
		input.setAttribute('aria-autocomplete', 'list');
		input.setAttribute('aria-controls', box.id);
		input.setAttribute('aria-expanded', 'false');
		let timer = 0;
		let ctrl = null;
		let active = -1;
		const cache = new Map();
		const items = () => [...box.querySelectorAll('.ek-qs__item')];
		const close = () => { box.hidden = true; input.setAttribute('aria-expanded', 'false'); active = -1; };
		const mark = (text, q) => {
			const safe = escHtml(text);
			const words = q.split(/\s+/).filter((w) => w.length > 1).map((w) => escHtml(w).replace(/[.*+?^${}()|[\]\\]/g, '\\$&'));
			return words.length ? safe.replace(new RegExp('(' + words.join('|') + ')', 'gi'), '<mark>$1</mark>') : safe;
		};
		const render = (q, rows) => {
			const all = `<a class="ek-qs__all" href="${escHtml(form.action)}?s=${encodeURIComponent(q)}">Všechny výsledky pro „${escHtml(q)}“ →</a>`;
			box.innerHTML = rows.length
				? rows.map((r, i) => `<a class="ek-qs__item" role="option" id="${box.id}-${i}" href="${escHtml(r.u)}"><span class="ek-qs__title">${mark(r.t, q)}</span><span class="ek-qs__meta">${escHtml([r.c, r.d].filter(Boolean).join(' · '))}</span></a>`).join('') + all
				: `<p class="ek-qs__empty">Nic jsme nenašli. Zkuste jiné slovo.</p>`;
			box.hidden = false;
			// na úzkém displeji nesmí přetéct přes levý okraj (vpravo od lupy může být ještě hamburger)
			box.style.right = '0px';
			const left = box.getBoundingClientRect().left;
			if (left < 16) box.style.right = (left - 16) + 'px';
			input.setAttribute('aria-expanded', 'true');
			active = -1;
		};
		const search = async (q) => {
			if (cache.has(q)) { render(q, cache.get(q)); return; }
			ctrl?.abort();
			ctrl = new AbortController();
			try {
				const res = await fetch(api + (api.includes('?') ? '&' : '?') + 'q=' + encodeURIComponent(q), { signal: ctrl.signal, headers: { Accept: 'application/json' } });
				if (!res.ok) return;
				const rows = await res.json();
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
			const list = items();
			if (box.hidden || !list.length) return;
			if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
				e.preventDefault();
				active = (active + (e.key === 'ArrowDown' ? 1 : -1) + list.length + 1) % (list.length + 1) - 0;
				if (active === list.length) active = -1;
				list.forEach((a, i) => a.classList.toggle('is-active', i === active));
				input.setAttribute('aria-activedescendant', active >= 0 ? list[active].id : '');
			} else if (e.key === 'Enter' && active >= 0) {
				e.preventDefault();
				location.href = list[active].href;
			} else if (e.key === 'Escape') {
				close();
			}
		});
		document.addEventListener('click', (e) => { if (!qs.contains(e.target)) close(); });
		// Když se pole hledání zavře (lupa / Esc), zavřít i výsledky
		new MutationObserver(() => { if (!qs.classList.contains('is-open')) close(); }).observe(qs, { attributes: true, attributeFilter: ['class'] });
	});

	/* Kopírovat pro AI: článek jako Markdown do schránky + nabídka Otevřít v Claude / ChatGPT */
	document.querySelectorAll('[data-ek-ai]').forEach((wrap) => {
		const copy = wrap.querySelector('[data-ek-ai-copy]');
		const label = wrap.querySelector('[data-ek-ai-label]');
		const toggle = wrap.querySelector('[data-ek-ai-toggle]');
		const menu = wrap.querySelector('[data-ek-ai-menu]');
		const md = wrap.dataset.md;
		const getMd = () => fetch(md, { headers: { Accept: 'text/markdown' } }).then((r) => { if (!r.ok) throw new Error(r.status); return r.text(); });
		const done = (text) => {
			label.textContent = text;
			copy.classList.toggle('is-done', text === 'Zkopírováno');
			setTimeout(() => { label.textContent = 'Kopírovat pro AI'; copy.classList.remove('is-done'); }, 2200);
		};
		copy?.addEventListener('click', async () => {
			label.textContent = 'Kopíruji…';
			try {
				// ClipboardItem s Promise: funguje i v Safari, kde by se po await fetch ztratilo oprávnění ke schránce
				if (window.ClipboardItem && navigator.clipboard?.write) {
					await navigator.clipboard.write([new ClipboardItem({ 'text/plain': getMd().then((t) => new Blob([t], { type: 'text/plain' })) })]);
				} else {
					await navigator.clipboard.writeText(await getMd());
				}
				done('Zkopírováno');
			} catch (e) {
				try { await navigator.clipboard.writeText(await getMd()); done('Zkopírováno'); }
				catch (e2) { window.open(md, '_blank', 'noopener'); done('Otevřeno jako Markdown'); }
			}
		});
		const setMenu = (open) => { menu.hidden = !open; toggle.setAttribute('aria-expanded', String(open)); };
		toggle?.addEventListener('click', () => setMenu(menu.hidden));
		document.addEventListener('click', (e) => { if (!menu.hidden && !wrap.contains(e.target)) setMenu(false); });
		wrap.addEventListener('keydown', (e) => { if (e.key === 'Escape' && !menu.hidden) { setMenu(false); toggle.focus(); } });
		menu?.addEventListener('click', (e) => { if (e.target.closest('a')) setMenu(false); });
	});

	/* Sdílení článku: na mobilu systémové sdílení, jinak zkopírovat odkaz */
	document.querySelectorAll('[data-ek-share]').forEach((btn) => {
		const label = btn.querySelector('[data-ek-share-label]');
		const iconWrap = btn.querySelector('.ek-share__icon');
		const iconLink = iconWrap?.innerHTML || '';
		const iconOk = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 6 9 17l-5-5"/></svg>';
		const touch = window.matchMedia('(pointer: coarse)').matches && typeof navigator.share === 'function';
		if (touch && label) label.textContent = 'Sdílet';
		btn.addEventListener('click', async () => {
			const url = btn.dataset.url;
			if (touch) {
				try { await navigator.share({ title: btn.dataset.title, url }); } catch (e) { /* zrušeno */ }
				return;
			}
			try {
				await navigator.clipboard.writeText(url);
				if (label) label.textContent = 'Zkopírováno';
				if (iconWrap) iconWrap.innerHTML = iconOk;
				btn.classList.add('is-done');
				setTimeout(() => { if (label) label.textContent = 'Kopírovat odkaz'; if (iconWrap) iconWrap.innerHTML = iconLink; btn.classList.remove('is-done'); }, 2000);
			} catch (e) {
				window.prompt('Odkaz na článek:', url);
			}
		});
	});

	/* Aktuální téma v liště vyrolovat do viditelné části (mobil) */
	document.querySelector('.ek-chipsnav .is-current')?.scrollIntoView({ block: 'nearest', inline: 'center' });
	/* Maska na okraji lišty jen při přetečení */
	const chipsnav = document.querySelector('[data-ek-chipsnav]');
	if (chipsnav) {
		const syncOverflow = () => chipsnav.classList.toggle('is-overflowing', chipsnav.scrollWidth > chipsnav.clientWidth + 1);
		syncOverflow();
		if ('ResizeObserver' in window) new ResizeObserver(syncOverflow).observe(chipsnav);
		else window.addEventListener('resize', syncOverflow);
	}

	/* Tlačítko Nahoru: objeví se po odrolování zhruba jedné obrazovky */
	const toTop = document.querySelector('[data-ek-totop]');
	if (toTop) {
		toTop.hidden = false;
		let topShown = null;
		let topTick = false;
		const syncTop = () => {
			topTick = false;
			const show = window.scrollY > Math.max(600, window.innerHeight);
			if (show === topShown) return;
			topShown = show;
			toTop.classList.toggle('is-shown', show);
			toTop.tabIndex = show ? 0 : -1;
			toTop.setAttribute('aria-hidden', String(!show));
		};
		window.addEventListener('scroll', () => { if (!topTick) { topTick = true; requestAnimationFrame(syncTop); } }, { passive: true });
		syncTop();
		toTop.addEventListener('click', () => {
			const reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
			window.scrollTo({ top: 0, behavior: reduce ? 'auto' : 'smooth' });
		});
	}

	/* Komentáře: sbalené; rozbalit při odkazu na komentář (#comment-123, #komentare) */
	const commentsBox = document.querySelector('[data-ek-comments]');
	if (commentsBox) {
		const openForHash = () => {
			const h = location.hash;
			if (!/^#(comment-\d+|komentare)$/.test(h)) return;
			commentsBox.open = true;
			if (h !== '#komentare') document.querySelector(h)?.scrollIntoView({ block: 'start' });
		};
		openForHash();
		window.addEventListener('hashchange', openForHash);
	}

	/* Tisk */
	document.querySelectorAll('[data-ek-print]').forEach((b) => b.addEventListener('click', () => window.print()));

	/* Tlačítko Kopírovat u bloků kódu */
	document.querySelectorAll('.ek-content pre').forEach((pre) => {
		if (pre.closest('.ek-code-wrap')) return;
		const wrap = document.createElement('div');
		wrap.className = 'ek-code-wrap';
		const label = pre.previousElementSibling?.classList.contains('ek-code-file') ? pre.previousElementSibling : null;
		pre.parentNode.insertBefore(wrap, label || pre);
		if (label) { wrap.classList.add('has-file'); wrap.appendChild(label); }
		wrap.appendChild(pre);
		if (pre.dataset.ekOutput) wrap.classList.add('is-output');
		if (pre.dataset.ekOutput || !navigator.clipboard) return;
		const btn = document.createElement('button');
		btn.type = 'button';
		btn.className = 'ek-copy';
		btn.textContent = 'Kopírovat';
		btn.addEventListener('click', async () => {
			try {
				const clone = pre.cloneNode(true);
				clone.querySelectorAll('.line-numbers-rows').forEach((n) => n.remove());
				await navigator.clipboard.writeText(clone.textContent.replace(/\n$/, ''));
				btn.textContent = 'Zkopírováno ✓';
				btn.classList.add('is-done');
			} catch (e) {
				btn.textContent = 'Nelze kopírovat';
			}
			setTimeout(() => { btn.textContent = 'Kopírovat'; btn.classList.remove('is-done'); }, 1800);
		});
		wrap.appendChild(btn);
	});

	/* Dlouhý kód (víc než 30 řádků): zobrazit začátek a tlačítko „Zobrazit celé“. Kopírovat bere vždy celý kód. */
	document.querySelectorAll('.ek-content .ek-code-wrap > pre').forEach((pre) => {
		const lines = pre.textContent.replace(/\n$/, '').split('\n').length;
		if (lines <= 30) return;
		const wrap = pre.parentElement;
		wrap.classList.add('is-collapsible', 'is-collapsed');
		const more = document.createElement('button');
		more.type = 'button';
		more.className = 'ek-code-more';
		const sync = () => {
			const closed = wrap.classList.contains('is-collapsed');
			more.textContent = closed ? `Zobrazit celé (${lines} řádků)` : 'Sbalit';
			more.setAttribute('aria-expanded', String(!closed));
		};
		more.addEventListener('click', () => {
			wrap.classList.toggle('is-collapsed');
			if (wrap.classList.contains('is-collapsed')) wrap.scrollIntoView({ block: 'nearest' });
			sync();
		});
		sync();
		wrap.appendChild(more);
	});

	/* Instalovatelný web: service worker (jen pro nepřihlášené – přihlášený vidí stránky s lištou administrace). */
	if ('serviceWorker' in navigator && !document.body.classList.contains('logged-in') && window.isSecureContext) {
		window.addEventListener('load', () => { navigator.serviceWorker.register('/sw.js').catch(() => {}); });
	}

	/* Stránka „Jsi offline“: seznam uložených článků. */
	const offlineBox = document.querySelector('[data-ek-offline]');
	if (offlineBox && navigator.serviceWorker?.controller) {
		fetch('/__ek_index').then((r) => r.json()).then((idx) => {
			const items = Object.entries(idx).sort((a, b) => b[1].d - a[1].d).slice(0, 30);
			if (!items.length) return;
			const list = offlineBox.querySelector('[data-ek-offline-list]');
			items.forEach(([url, it]) => {
				const li = document.createElement('li');
				const a = document.createElement('a');
				a.href = url;
				a.textContent = it.t || url;
				li.appendChild(a);
				list.appendChild(li);
			});
			offlineBox.hidden = false;
		}).catch(() => {});
	}

	/* Další články: načítají se samy při dorolování (max. 3×, pak tlačítko, ať jde dojet k patičce).
	   Dávky jsou po 12 = plné řádky při 1, 2, 3 i 4 sloupcích. Bez JS funguje jako odkaz. */
	const moreLink = document.querySelector('[data-ek-more]');
	const grid = document.querySelector('[data-ek-grid]');
	if (moreLink && grid) {
		const box = moreLink.parentElement;
		let busy = false;
		let auto = 3;
		const loadMore = async (byUser) => {
			if (busy) return;
			busy = true;
			box.classList.add('is-loading');
			try {
				const res = await fetch(moreLink.href, { credentials: 'same-origin' });
				if (!res.ok) throw new Error(res.status);
				const doc = new DOMParser().parseFromString(await res.text(), 'text/html');
				const items = doc.querySelectorAll('[data-ek-grid] > *');
				items.forEach((el) => grid.appendChild(document.importNode(el, true)));
				const next = doc.querySelector('[data-ek-more]');
				if (next) { moreLink.href = next.href; } else { box.remove(); io?.disconnect(); }
				if (byUser) items[0]?.querySelector('a:not([tabindex])')?.focus({ preventScroll: true });
			} catch (err) {
				if (byUser) window.location.href = moreLink.href;
			} finally {
				box.classList.remove('is-loading');
				busy = false;
			}
		};
		moreLink.addEventListener('click', (e) => { e.preventDefault(); loadMore(true); });
		const io = 'IntersectionObserver' in window ? new IntersectionObserver((entries) => {
			if (!entries.some((en) => en.isIntersecting) || auto <= 0) return;
			auto -= 1;
			if (auto <= 0) io.disconnect();
			loadMore(false);
		}, { rootMargin: '0px 0px 600px 0px' }) : null;
		io?.observe(box);
	}

	/* Komentáře: kompaktní formulář – rozbalí se po kliknutí do pole nebo na „Odpovědět“ */
	const compose = document.querySelector('[data-ek-compose]');
	if (compose) {
		const ta = compose.querySelector('textarea');
		const open = () => compose.classList.remove('is-collapsed');
		if (ta && !ta.value && !/#respond|replytocom/.test(location.href) && !compose.querySelector('.comment-form-author input[aria-invalid="true"]')) {
			compose.classList.add('is-collapsed');
		}
		ta?.addEventListener('focus', open);
		document.addEventListener('click', (e) => { if (e.target.closest('.comment-reply-link')) open(); });
	}

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
