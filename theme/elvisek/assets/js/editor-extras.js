/* ElvisEK — doplňky editoru: formát „Klávesa“ (<kbd>) a blok „GitHub repozitář“. */
( function ( wp ) {
	'use strict';
	// Skript se může načíst dvakrát (editor_script bloku + editor) – registrovat jen jednou.
	if ( wp.data.select( 'core/rich-text' ).getFormatType( 'elvisek/kbd' ) ) {
		return;
	}
	const { createElement: el, Fragment } = wp.element;
	const { registerFormatType, toggleFormat } = wp.richText;
	const { RichTextToolbarButton, InspectorControls, useBlockProps } = wp.blockEditor;
	const { PanelBody, TextControl, Placeholder } = wp.components;
	const ServerSideRender = wp.serverSideRender;

	/* Klávesa: označený text → <kbd>Ctrl</kbd> */
	const kbdIcon = el( 'svg', { width: 24, height: 24, viewBox: '0 0 24 24', fill: 'none', stroke: 'currentColor', strokeWidth: 1.6 },
		el( 'rect', { x: 3, y: 6, width: 18, height: 12, rx: 2 } ),
		el( 'path', { d: 'M7 10h.01M11 10h.01M15 10h.01M8 14h8' } )
	);
	registerFormatType( 'elvisek/kbd', {
		title: 'Klávesa',
		tagName: 'kbd',
		className: 'ek-kbd',
		edit: ( { isActive, value, onChange } ) => el( RichTextToolbarButton, {
			icon: kbdIcon,
			title: 'Klávesa',
			isActive,
			onClick: () => onChange( toggleFormat( value, { type: 'elvisek/kbd' } ) ),
		} ),
	} );

	/* Blok GitHub repozitář (vykresluje server, v editoru náhled) */
	wp.blocks.registerBlockType( 'elvisek/github', {
		edit: ( { attributes, setAttributes } ) => {
			const blockProps = useBlockProps();
			const repo = attributes.repo || '';
			const field = el( TextControl, {
				label: 'Repozitář',
				help: 'Ve tvaru uživatel/repozitář, např. elvisek2020/fve-flow-card (stačí vložit i celou adresu z GitHubu).',
				value: repo,
				onChange: ( v ) => setAttributes( { repo: v.trim() } ),
				__nextHasNoMarginBottom: true,
			} );
			return el( Fragment, null,
				el( InspectorControls, null, el( PanelBody, { title: 'GitHub', initialOpen: true }, field ) ),
				el( 'div', blockProps,
					repo
						? el( ServerSideRender, { block: 'elvisek/github', attributes: { repo } } )
						: el( Placeholder, { icon: 'editor-code', label: 'GitHub repozitář', instructions: 'Karta s popisem, hvězdičkami a posledním vydáním. Data se stahují na serveru a obnovují se jednou za 12 hodin.' }, field )
				)
			);
		},
		save: () => null,
	} );
} )( window.wp );
