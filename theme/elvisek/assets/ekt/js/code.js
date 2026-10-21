/* EKT code — bloky kódu v obsahu článku ([data-ekt-content] pre): obal .ekt-code-wrap se štítkem
 * souboru, tlačítko Kopírovat (ne u Výstupu) a sbalení dlouhého kódu. Kopíruje se vždy celý kód
 * bez čísel řádků. Konfigurace: window.EKT_CODE = { collapse, texts }. */
(() => {
	'use strict';
	const cfg = window.EKT_CODE || {};
	const t = Object.assign({
		copy: 'Kopírovat', copied: 'Zkopírováno', failed: 'Nelze kopírovat',
		more: 'Zobrazit celé (%d řádků)', less: 'Sbalit',
	}, cfg.texts || {});
	const limit = Number(cfg.collapse) || 30;
	const isOutput = (pre) => pre.hasAttribute('data-ekt-output') || pre.classList.contains('is-style-ekt-output') || pre.classList.contains('is-style-ek-output');
	const codeText = (pre) => {
		const clone = pre.cloneNode(true);
		clone.querySelectorAll('.line-numbers-rows').forEach((n) => n.remove());
		return clone.textContent.replace(/\n$/, '');
	};
	let n = 0;

	document.querySelectorAll('[data-ekt-content] pre').forEach((pre) => {
		if (pre.closest('.ekt-code-wrap')) return;
		n += 1;
		const wrap = document.createElement('div');
		wrap.className = 'ekt-code-wrap';
		const prev = pre.previousElementSibling;
		const label = prev && prev.classList.contains('ekt-code-file') ? prev : null;
		pre.parentNode.insertBefore(wrap, label || pre);
		if (label) { wrap.classList.add('has-file'); wrap.appendChild(label); }
		wrap.appendChild(pre);
		const output = isOutput(pre);
		if (output) wrap.classList.add('is-output');

		/* Kopírovat */
		if (!output && navigator.clipboard && window.isSecureContext) {
			const btn = document.createElement('button');
			btn.type = 'button';
			btn.className = 'ekt-code-copy';
			btn.textContent = t.copy;
			btn.setAttribute('aria-live', 'polite');
			let timer = 0;
			btn.addEventListener('click', async () => {
				clearTimeout(timer);
				try {
					await navigator.clipboard.writeText(codeText(pre));
					btn.textContent = t.copied;
					btn.classList.add('is-done');
				} catch (e) {
					btn.textContent = t.failed;
				}
				timer = setTimeout(() => { btn.textContent = t.copy; btn.classList.remove('is-done'); }, 1800);
			});
			wrap.appendChild(btn);
		}

		/* Sbalení dlouhého kódu */
		const lines = codeText(pre).split('\n').length;
		if (lines <= limit) return;
		if (!pre.id) pre.id = 'ekt-code-' + n;
		wrap.classList.add('is-collapsible', 'is-collapsed');
		const more = document.createElement('button');
		more.type = 'button';
		more.className = 'ekt-code-more';
		more.setAttribute('aria-controls', pre.id);
		const sync = () => {
			const closed = wrap.classList.contains('is-collapsed');
			more.textContent = closed ? t.more.replace('%d', String(lines)) : t.less;
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
})();
