/* ElvisEK — souhlas s cookies pro GA4 (Consent Mode v2). gtag.js se stáhne až po souhlasu. */
(() => {
	'use strict';
	const KEY = 'ek-consent';
	const bar = document.querySelector('[data-ek-consent]');
	if (!bar || !window.EK_GA_ID) return;

	let loaded = false;
	const loadGtag = () => {
		if (loaded) return;
		loaded = true;
		const s = document.createElement('script');
		s.async = true;
		s.src = 'https://www.googletagmanager.com/gtag/js?id=' + encodeURIComponent(window.EK_GA_ID);
		document.head.appendChild(s);
	};
	const apply = (choice) => {
		const v = choice === 'grant' ? 'granted' : 'denied';
		window.gtag && window.gtag('consent', 'update', { analytics_storage: v, ad_storage: 'denied', ad_user_data: 'denied', ad_personalization: 'denied' });
		if (choice === 'grant') loadGtag();
	};

	let saved = null;
	try { saved = localStorage.getItem(KEY); } catch (e) { /* ignore */ }
	if (saved) { apply(saved); } else { bar.hidden = false; }

	bar.addEventListener('click', (e) => {
		const btn = e.target.closest('[data-ek-consent-choice]');
		if (!btn) return;
		const choice = btn.dataset.ekConsentChoice;
		try { localStorage.setItem(KEY, choice); } catch (err) { /* ignore */ }
		apply(choice);
		bar.hidden = true;
	});
	document.querySelectorAll('[data-ek-consent-open]').forEach((b) => b.addEventListener('click', () => { bar.hidden = false; }));
})();
