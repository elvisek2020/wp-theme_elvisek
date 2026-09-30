/* ElvisEK — lightbox pro obrázky v článku (nahrazuje wp-jquery-lightbox). */
(() => {
	'use strict';
	const IMG_RE = /\.(jpe?g|png|gif|webp|avif)(\?.*)?$/i;
	const links = [...document.querySelectorAll('.ek-content a')].filter(
		(a) => IMG_RE.test(a.getAttribute('href') || '') && a.querySelector('img')
	);
	if (!links.length || typeof HTMLDialogElement !== 'function') return;

	const icon = (d) => `<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="${d}"/></svg>`;
	const dlg = document.createElement('dialog');
	dlg.className = 'ek-lightbox';
	dlg.setAttribute('aria-label', 'Náhled obrázku');
	dlg.innerHTML = `
		<div class="ek-lightbox__stage"><img class="ek-lightbox__img" alt=""></div>
		<button type="button" class="ek-lightbox__btn ek-lightbox__close" aria-label="Zavřít">${icon('M6 6l12 12M18 6 6 18')}</button>
		<button type="button" class="ek-lightbox__btn ek-lightbox__prev" aria-label="Předchozí obrázek">${icon('M15 6l-6 6 6 6')}</button>
		<button type="button" class="ek-lightbox__btn ek-lightbox__next" aria-label="Další obrázek">${icon('M9 6l6 6-6 6')}</button>
		<p class="ek-lightbox__caption"></p>`;
	document.body.appendChild(dlg);

	const img = dlg.querySelector('.ek-lightbox__img');
	const cap = dlg.querySelector('.ek-lightbox__caption');
	const prev = dlg.querySelector('.ek-lightbox__prev');
	const next = dlg.querySelector('.ek-lightbox__next');
	let index = 0;

	const show = (i) => {
		index = (i + links.length) % links.length;
		const a = links[index];
		const thumb = a.querySelector('img');
		img.src = a.href;
		img.alt = thumb.alt || '';
		const figcap = a.closest('figure')?.querySelector('figcaption')?.textContent;
		cap.textContent = [figcap || thumb.alt || '', links.length > 1 ? `${index + 1} / ${links.length}` : ''].filter(Boolean).join(' · ');
	};

	prev.hidden = next.hidden = links.length < 2;
	links.forEach((a, i) => a.addEventListener('click', (e) => {
		if (e.metaKey || e.ctrlKey || e.shiftKey) return;
		e.preventDefault();
		show(i);
		dlg.showModal();
	}));
	dlg.querySelector('.ek-lightbox__close').addEventListener('click', () => dlg.close());
	prev.addEventListener('click', () => show(index - 1));
	next.addEventListener('click', () => show(index + 1));
	dlg.addEventListener('click', (e) => { if (e.target === dlg || e.target.classList.contains('ek-lightbox__stage')) dlg.close(); });
	dlg.addEventListener('keydown', (e) => {
		if (e.key === 'ArrowLeft') show(index - 1);
		if (e.key === 'ArrowRight') show(index + 1);
	});
	dlg.addEventListener('close', () => { img.removeAttribute('src'); links[index]?.focus(); });
})();
