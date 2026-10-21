/**
 * EKT share — tlačítko [data-ekt-share]: na dotykovém zařízení systémové sdílení (navigator.share),
 * jinak zkopíruje odkaz do schránky (popisek a třída is-done na 2 s). Texty z data-ekt-share-*.
 */
(function () {
	'use strict';

	var check = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M20 6 9 17l-5-5"/></svg>';
	var native = typeof navigator.share === 'function' && window.matchMedia && window.matchMedia('(pointer: coarse)').matches;

	document.querySelectorAll('[data-ekt-share]').forEach(function (btn) {
		var label = btn.querySelector('[data-ekt-share-label]');
		var icon = btn.querySelector('.ekt-share__icon');
		var original = label ? label.textContent : '';
		var originalIcon = icon ? icon.innerHTML : '';
		var url = btn.getAttribute('data-ekt-share-url') || window.location.href;
		var title = btn.getAttribute('data-ekt-share-title') || document.title;
		var timer = null;

		if (native && label && btn.getAttribute('data-ekt-share-native')) {
			label.textContent = btn.getAttribute('data-ekt-share-native');
			original = label.textContent;
		}

		function done() {
			if (label) {
				label.textContent = btn.getAttribute('data-ekt-share-copied') || original;
			}
			if (icon) {
				icon.innerHTML = check;
			}
			btn.classList.add('is-done');
			window.clearTimeout(timer);
			timer = window.setTimeout(function () {
				if (label) {
					label.textContent = original;
				}
				if (icon) {
					icon.innerHTML = originalIcon;
				}
				btn.classList.remove('is-done');
			}, 2000);
		}

		function fallback() {
			window.prompt(btn.getAttribute('data-ekt-share-prompt') || '', url);
		}

		btn.addEventListener('click', function () {
			if (native) {
				navigator.share({ title: title, url: url }).catch(function () { /* zrušeno */ });
				return;
			}
			if (navigator.clipboard && window.isSecureContext) {
				navigator.clipboard.writeText(url).then(done, fallback);
			} else {
				fallback();
			}
		});
	});
})();
