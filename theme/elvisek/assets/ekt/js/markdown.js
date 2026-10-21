/* EKT markdown — tlačítko „Kopírovat pro AI“ ([data-ekt-ai]): stáhne Markdown verzi článku
 * a zkopíruje ji do schránky; nabídka Otevřít v Claude / ChatGPT. Texty: window.EKT_MARKDOWN.texts. */
(() => {
	'use strict';
	const t = Object.assign({
		copy: 'Kopírovat pro AI', copying: 'Kopíruji…', copied: 'Zkopírováno', opened: 'Otevřeno jako Markdown',
	}, (window.EKT_MARKDOWN || {}).texts || {});

	document.querySelectorAll('[data-ekt-ai]').forEach((wrap) => {
		const md = wrap.dataset.ektAiMd;
		const copy = wrap.querySelector('[data-ekt-ai-copy]');
		const label = wrap.querySelector('[data-ekt-ai-label]');
		const toggle = wrap.querySelector('[data-ekt-ai-toggle]');
		const menu = wrap.querySelector('[data-ekt-ai-menu]');
		if (!md || !copy) return;
		const getMd = () => fetch(md, { headers: { Accept: 'text/markdown' }, credentials: 'same-origin' })
			.then((r) => { if (!r.ok) throw new Error(String(r.status)); return r.text(); });
		const say = (text) => { if (label) label.textContent = text; };
		let timer = 0;
		const done = (text, ok) => {
			say(text);
			copy.classList.toggle('is-done', !!ok);
			clearTimeout(timer);
			timer = setTimeout(() => { say(t.copy); copy.classList.remove('is-done'); }, 2200);
		};
		copy.addEventListener('click', async () => {
			say(t.copying);
			try {
				// ClipboardItem s Promise: funguje i v Safari, kde by se po await fetch ztratilo oprávnění ke schránce.
				if (window.ClipboardItem && navigator.clipboard && navigator.clipboard.write) {
					await navigator.clipboard.write([new ClipboardItem({ 'text/plain': getMd().then((s) => new Blob([s], { type: 'text/plain' })) })]);
				} else {
					await navigator.clipboard.writeText(await getMd());
				}
				done(t.copied, true);
			} catch (e) {
				try { await navigator.clipboard.writeText(await getMd()); done(t.copied, true); }
				catch (e2) { window.open(md, '_blank', 'noopener'); done(t.opened, false); }
			}
		});
		if (!toggle || !menu) return;
		const setMenu = (open) => { menu.hidden = !open; toggle.setAttribute('aria-expanded', String(open)); };
		toggle.addEventListener('click', () => setMenu(menu.hidden));
		document.addEventListener('click', (e) => { if (!menu.hidden && !wrap.contains(e.target)) setMenu(false); });
		wrap.addEventListener('keydown', (e) => { if (e.key === 'Escape' && !menu.hidden) { setMenu(false); toggle.focus(); } });
		menu.addEventListener('click', (e) => { if (e.target.closest('a')) setMenu(false); });
	});
})();
