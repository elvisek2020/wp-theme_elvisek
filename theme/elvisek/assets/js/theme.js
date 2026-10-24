/* ElvisEK — chování šablony (bez závislostí). */
(() => {
	'use strict';
	const root = document.documentElement;

	/* Titulka: světlá varianta obrázku hlavičky podle ručně zvoleného režimu (přepínač je kanon color-scheme) */
	const syncHero = () => {
		const heroLight = document.querySelector('[data-ek-hero-light]');
		if (!heroLight) return;
		heroLight.media = root.dataset.theme ? (root.dataset.theme === 'light' ? 'all' : 'not all') : '(prefers-color-scheme: light)';
	};
	document.addEventListener('ekt:color-scheme', syncHero);

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
			// V režimu menu zabírá hamburger místo navíc (tlačítko + skutečná mezera v liště) – přičíst ho.
			// Zpět na lištu až s rezervou 8 px: na hraně se šířky liší zaokrouhlením a lišta by přepínala dokola.
			const gap = parseFloat(getComputedStyle(menuNav.parentElement).columnGap) || 0;
			const fits = compact
				? menuNav.scrollWidth <= menuNav.clientWidth + menuBtn.offsetWidth + gap - 8
				: menuNav.scrollWidth <= menuNav.clientWidth + 1;
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
	document.querySelectorAll('.ek-qs').forEach((qs) => {
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
	const content = document.querySelector('[data-ekt-content]');
	if (tocTpl && content && tocTpl.content.querySelector('[data-ekt-toc]')) {
		const det = document.createElement('details');
		det.className = 'ek-toc-inline';
		const sum = document.createElement('summary');
		sum.textContent = 'Obsah článku';
		det.appendChild(sum);
		det.appendChild(tocTpl.content.cloneNode(true));
		det.addEventListener('click', (e) => { if (e.target.closest('a')) det.open = false; });
		content.parentNode.insertBefore(det, content);
	}

})();
