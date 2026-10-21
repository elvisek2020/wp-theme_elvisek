/* EKT github — editor: blok „GitHub repozitář“ (ekt/github) s náhledem ze serveru. Starší blok
 * elvisek/github se registruje se stejným editorem (skrytý v inserteru) a jde převést na nový. */
(function (wp) {
	'use strict';
	if (!wp || !wp.blocks) return;
	const { registerBlockType, getBlockType, createBlock } = wp.blocks;
	const { createElement: el, Fragment } = wp.element;
	const { InspectorControls, useBlockProps } = wp.blockEditor;
	const { PanelBody, TextControl, Placeholder } = wp.components;
	const ServerSideRender = wp.serverSideRender;

	const edit = (name) => ({ attributes, setAttributes }) => {
		const blockProps = useBlockProps();
		const repo = attributes.repo || '';
		const field = el(TextControl, {
			label: 'Repozitář',
			help: 'Ve tvaru uživatel/repozitář (stačí vložit i celou adresu z GitHubu).',
			value: repo,
			onChange: (v) => setAttributes({ repo: v.trim() }),
			__nextHasNoMarginBottom: true,
		});
		return el(Fragment, null,
			el(InspectorControls, null, el(PanelBody, { title: 'GitHub', initialOpen: true }, field)),
			el('div', blockProps,
				repo
					? el(ServerSideRender, { block: name, attributes: { repo } })
					: el(Placeholder, {
						icon: 'editor-code',
						label: 'GitHub repozitář',
						instructions: 'Karta s popisem, hvězdičkami a posledním vydáním. Data stahuje server a obnovuje je jednou za 12 hodin.',
					}, field)
			)
		);
	};
	const settings = (name, extra) => Object.assign({
		apiVersion: 3,
		title: 'GitHub repozitář',
		category: 'embed',
		icon: 'editor-code',
		attributes: { repo: { type: 'string', default: '' } },
		supports: { html: false },
		edit: edit(name),
		save: () => null,
	}, extra || {});

	if (!getBlockType('ekt/github')) registerBlockType('ekt/github', settings('ekt/github'));
	if (!getBlockType('elvisek/github')) {
		registerBlockType('elvisek/github', settings('elvisek/github', {
			supports: { html: false, inserter: false },
			transforms: { to: [{ type: 'block', blocks: ['ekt/github'], transform: (a) => createBlock('ekt/github', { repo: a.repo || '' }) }] },
		}));
	}
})(window.wp);
