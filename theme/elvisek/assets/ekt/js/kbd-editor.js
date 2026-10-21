/* EKT kbd — editor: formát „Klávesa“ (<kbd class="ekt-kbd">). Starší elvisek/kbd (<kbd class="ek-kbd">)
 * je zaregistrovaný bez tlačítka, aby uložený obsah zůstal beze změny. */
(function (wp) {
	'use strict';
	if (!wp || !wp.richText) return;
	const { registerFormatType, toggleFormat } = wp.richText;
	const { createElement: el } = wp.element;
	const { RichTextToolbarButton } = wp.blockEditor;
	const store = wp.data && wp.data.select('core/rich-text');
	const has = (name) => !!(store && store.getFormatType(name));

	const icon = el('svg', { width: 24, height: 24, viewBox: '0 0 24 24', fill: 'none', stroke: 'currentColor', strokeWidth: 1.6 },
		el('rect', { x: 3, y: 6, width: 18, height: 12, rx: 2 }),
		el('path', { d: 'M7 10h.01M11 10h.01M15 10h.01M8 14h8' })
	);

	if (!has('ekt/kbd')) {
		registerFormatType('ekt/kbd', {
			title: 'Klávesa',
			tagName: 'kbd',
			className: 'ekt-kbd',
			edit: ({ isActive, value, onChange }) => el(RichTextToolbarButton, {
				icon,
				title: 'Klávesa',
				isActive,
				onClick: () => onChange(toggleFormat(value, { type: 'ekt/kbd' })),
			}),
		});
	}
	if (!has('elvisek/kbd')) {
		registerFormatType('elvisek/kbd', {
			title: 'Klávesa (starší)',
			tagName: 'kbd',
			className: 'ek-kbd',
			edit: () => null,
		});
	}
})(window.wp);
