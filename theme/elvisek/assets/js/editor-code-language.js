/* ElvisEK — výběr jazyka v bloku Kód (nastaví třídu language-xxx pro zvýraznění Prism). */
( function ( wp ) {
	'use strict';
	const { addFilter } = wp.hooks;
	const { createHigherOrderComponent } = wp.compose;
	const { createElement: el, Fragment } = wp.element;
	const { InspectorControls } = wp.blockEditor;
	const { PanelBody, SelectControl } = wp.components;

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
				el( PanelBody, { title: 'Jazyk kódu', initialOpen: true },
					el( SelectControl, {
						label: 'Zvýraznění syntaxe',
						value: current,
						options: LANGS.map( ( [ value, label ] ) => ( { value, label } ) ),
						onChange: setLang,
						__nextHasNoMarginBottom: true,
					} )
				)
			)
		);
	}, 'ekWithCodeLanguage' );

	addFilter( 'editor.BlockEdit', 'elvisek/code-language', withLanguage );
} )( window.wp );
