/* ElvisEK — blok Kód: výběr jazyka (třída language-xxx pro Prism) a volitelný název souboru (atribut ekFile). */
( function ( wp ) {
	'use strict';
	const { addFilter } = wp.hooks;
	const { createHigherOrderComponent } = wp.compose;
	const { createElement: el, Fragment } = wp.element;
	const { InspectorControls } = wp.blockEditor;
	const { PanelBody, SelectControl, TextControl } = wp.components;

	const LANGS = [
		[ '', '— bez zvýraznění —' ],
		[ 'bash', 'Bash / shell' ],
		[ 'powershell', 'PowerShell' ],
		[ 'python', 'Python' ],
		[ 'php', 'PHP' ],
		[ 'javascript', 'JavaScript' ],
		[ 'json', 'JSON' ],
		[ 'yaml', 'YAML' ],
		[ 'markup', 'HTML / XML' ],
		[ 'css', 'CSS' ],
		[ 'sql', 'SQL' ],
		[ 'ini', 'INI / conf' ],
		[ 'docker', 'Dockerfile' ],
		[ 'nginx', 'nginx' ],
		[ 'apacheconf', 'Apache (.htaccess)' ],
		[ 'diff', 'Diff' ],
	];
	const RE = /(^|\s)language-[\w-]+/g;

	const withLanguage = createHigherOrderComponent( ( BlockEdit ) => ( props ) => {
		if ( props.name !== 'core/code' ) {
			return el( BlockEdit, props );
		}
		const cls = props.attributes.className || '';
		const m = cls.match( /(?:^|\s)language-([\w-]+)/ );
		const current = m ? m[ 1 ] : '';
		const setLang = ( lang ) => {
			const rest = cls.replace( RE, '' ).trim();
			const next = [ rest, lang ? 'language-' + lang : '' ].filter( Boolean ).join( ' ' );
			props.setAttributes( { className: next || undefined } );
		};
		return el( Fragment, null,
			el( BlockEdit, props ),
			el( InspectorControls, null,
				el( PanelBody, { title: 'Kód', initialOpen: true },
					el( SelectControl, {
						label: 'Zvýraznění syntaxe',
						value: current,
						options: LANGS.map( ( [ value, label ] ) => ( { value, label } ) ),
						onChange: setLang,
						__nextHasNoMarginBottom: true,
					} ),
					el( 'div', { style: { height: 16 } } ),
					el( TextControl, {
						label: 'Název souboru',
						help: 'Zobrazí se jako štítek nad kódem, např. docker-compose.yml nebo /etc/fstab. Nechte prázdné, pokud nejde o soubor.',
						value: props.attributes.ekFile || '',
						onChange: ( v ) => props.setAttributes( { ekFile: v || undefined } ),
						__nextHasNoMarginBottom: true,
					} )
				)
			)
		);
	}, 'ekWithCodeLanguage' );

	addFilter( 'editor.BlockEdit', 'elvisek/code-language', withLanguage );

	// Atribut ekFile u bloku Kód (ukládá se jen do komentáře bloku, HTML bloku se nemění).
	addFilter( 'blocks.registerBlockType', 'elvisek/code-file-attr', ( settings, name ) => {
		if ( name !== 'core/code' ) {
			return settings;
		}
		return { ...settings, attributes: { ...settings.attributes, ekFile: { type: 'string' } } };
	} );

	// Štítek s názvem souboru i v editoru (přes data atribut obalu bloku a CSS).
	addFilter( 'editor.BlockListBlock', 'elvisek/code-file-label', createHigherOrderComponent( ( BlockListBlock ) => ( props ) => {
		if ( props.name !== 'core/code' || ! props.attributes.ekFile ) {
			return el( BlockListBlock, props );
		}
		const wrapperProps = { ...( props.wrapperProps || {} ), 'data-ek-file': props.attributes.ekFile };
		return el( BlockListBlock, { ...props, wrapperProps } );
	}, 'ekWithCodeFileLabel' ) );
} )( window.wp );
