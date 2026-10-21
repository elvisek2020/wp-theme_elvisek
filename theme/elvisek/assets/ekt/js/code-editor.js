/* EKT code — editor: v bloku Kód výběr jazyka (třída language-xxx pro Prism) a název souboru
 * (atribut ektFile; starší ekFile se čte a při změně pole nahradí). HTML bloku se nemění. */
(function (wp) {
	'use strict';
	if (!wp || !wp.hooks || window.EKT_CODE_EDITOR) return;
	window.EKT_CODE_EDITOR = true;
	const { addFilter, applyFilters } = wp.hooks;
	const { createHigherOrderComponent } = wp.compose;
	const { createElement: el, Fragment } = wp.element;
	const { InspectorControls } = wp.blockEditor;
	const { PanelBody, SelectControl, TextControl } = wp.components;

	const LANGS = applyFilters('ekt.code.languages', [
		['', '— bez zvýraznění —'],
		['bash', 'Bash / shell'],
		['powershell', 'PowerShell'],
		['python', 'Python'],
		['php', 'PHP'],
		['javascript', 'JavaScript'],
		['json', 'JSON'],
		['yaml', 'YAML'],
		['markup', 'HTML / XML'],
		['css', 'CSS'],
		['sql', 'SQL'],
		['ini', 'INI / conf'],
		['docker', 'Dockerfile'],
		['nginx', 'nginx'],
		['apacheconf', 'Apache (.htaccess)'],
		['diff', 'Diff'],
	]);
	const RE = /(^|\s)lang(?:uage)?-[\w-]+/g;
	const fileOf = (attrs) => attrs.ektFile || attrs.ekFile || '';

	/* Atributy: ektFile (nový) a ekFile (alias ze starších článků). */
	addFilter('blocks.registerBlockType', 'ekt/code-file-attr', (settings, name) => {
		if (name !== 'core/code') return settings;
		return Object.assign({}, settings, {
			attributes: Object.assign({}, settings.attributes, { ektFile: { type: 'string' }, ekFile: { type: 'string' } }),
		});
	});

	addFilter('editor.BlockEdit', 'ekt/code-language', createHigherOrderComponent((BlockEdit) => (props) => {
		if (props.name !== 'core/code') return el(BlockEdit, props);
		const cls = props.attributes.className || '';
		const m = cls.match(/(?:^|\s)lang(?:uage)?-([\w-]+)/);
		const current = m ? m[1] : '';
		const setLang = (lang) => {
			const rest = cls.replace(RE, ' ').replace(/\s+/g, ' ').trim();
			const next = [rest, lang ? 'language-' + lang : ''].filter(Boolean).join(' ');
			props.setAttributes({ className: next || undefined });
		};
		const options = LANGS.map(([value, label]) => ({ value, label }));
		if (current && !options.some((o) => o.value === current)) options.push({ value: current, label: current });
		return el(Fragment, null,
			el(BlockEdit, props),
			el(InspectorControls, null,
				el(PanelBody, { title: 'Kód', initialOpen: true },
					el(SelectControl, {
						label: 'Zvýraznění syntaxe',
						value: current,
						options,
						onChange: setLang,
						__nextHasNoMarginBottom: true,
					}),
					el('div', { style: { height: 16 } }),
					el(TextControl, {
						label: 'Název souboru',
						help: 'Štítek nad kódem, např. docker-compose.yml nebo /etc/fstab. Prázdné = nejde o soubor.',
						value: fileOf(props.attributes),
						onChange: (v) => props.setAttributes({ ektFile: v || undefined, ekFile: undefined }),
						__nextHasNoMarginBottom: true,
					})
				)
			)
		);
	}, 'ektWithCodeLanguage'));

	/* Štítek s názvem souboru i v editoru (data atribut obalu bloku, styl dodá modul). */
	addFilter('editor.BlockListBlock', 'ekt/code-file-label', createHigherOrderComponent((BlockListBlock) => (props) => {
		const file = props.name === 'core/code' ? fileOf(props.attributes) : '';
		if (!file) return el(BlockListBlock, props);
		const wrapperProps = Object.assign({}, props.wrapperProps || {}, { 'data-ekt-file': file });
		return el(BlockListBlock, Object.assign({}, props, { wrapperProps }));
	}, 'ektWithCodeFileLabel'));
})(window.wp);
