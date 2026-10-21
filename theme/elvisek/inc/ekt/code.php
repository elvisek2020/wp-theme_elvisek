<?php
/**
 * EKT module: code
 * Version: 1.0.1
 * Requires: common
 * Replaces: syntaxhighlighter, code-block-pro, prismatic, highlighting-code-block
 * Assets: js/code.js, js/code-editor.js, js/prism.js, js/prism-LICENSE.txt, css/code.css
 * Summary: Blok Kód: jazyk a název souboru v editoru, štítek souboru, styl Výstup, čísla řádků, úzký kód, Kopírovat, sbalení a Prism lokálně.
 */

defined( 'ABSPATH' ) || exit;

/*
 * Editor (js/code-editor.js): v bloku Kód výběr jazyka (třída language-xxx pro Prism) a název
 * souboru (atribut bloku ektFile; starý ekFile se čte dál a přepíše se, až autor pole změní).
 * Blokový styl „Výstup“ = is-style-ekt-output (starý is-style-ek-output funguje stejně).
 *
 * Web:
 *  - render_block_core/code: <pre data-ekt-file="…" data-ekt-output="1"> a nad ním štítek
 *    <div class="ekt-code-file [ekt-code-file--output]"><span class="ekt-code-file__name">…</span></div>;
 *  - the_content (WP_HTML_Tag_Processor): každý <pre> dostane .ekt-code, s jazykem a zapnutými
 *    čísly řádků .line-numbers (ne u Výstupu), krátký kód (nejdelší řádek do 64 znaků)
 *    .ekt-code--narrow;
 *  - js/code.js jen uvnitř [data-ekt-content]: obal .ekt-code-wrap (.has-file, .is-output),
 *    tlačítko .ekt-code-copy (Kopírovat, ne u Výstupu), u kódu nad 30 řádků .is-collapsible
 *    .is-collapsed a tlačítko .ekt-code-more;
 *  - Prism (js/prism.js, MIT, licence v js/prism-LICENSE.txt) jen u článku s blokem core/code.
 *    Barvy tokenů (.token.*) a vzhled kódu patří do CSS šablony, css/code.css je jen funkční.
 *
 * Veřejné funkce: ekt_code_texts(), ekt_code_is_output( $classes ).
 * Přizpůsobit: ekt_posts → code_lines (čísla řádků, výchozí zapnuto).
 * Filtry: ekt_code_texts( $texts ), ekt_code_collapse_lines( 30 ), ekt_code_narrow_chars( 64 ),
 * ekt_code_prism( $load, $post ) (např. true pro klasický obsah s <pre class="language-…">),
 * ekt_code_html( $html, $args ) – štítek souboru (override template-parts/ekt/code.php;
 * $args: label, file, output).
 * JS: wp.hooks filtr 'ekt.code.languages' (seznam jazyků v editoru).
 */

ekt_setting_register(
	'code_lines',
	array(
		'type'     => 'checkbox',
		'default'  => 1,
		'label'    => 'Čísla řádků u kódu',
		'section'  => 'posts',
		'priority' => 30,
	)
);

/**
 * Texty modulu (štítek Výstup, tlačítka).
 *
 * @return array<string,string>
 */
function ekt_code_texts(): array {
	return array_merge(
		array(
			'output' => 'Výstup',
			'copy'   => 'Kopírovat',
			'copied' => 'Zkopírováno',
			'failed' => 'Nelze kopírovat',
			'more'   => 'Zobrazit celé (%d řádků)',
			'less'   => 'Sbalit',
		),
		(array) apply_filters( 'ekt_code_texts', array() )
	);
}

/**
 * Je to blok ve stylu Výstup (nový i starý název třídy)?
 *
 * @param string $classes Třídy.
 * @return bool
 */
function ekt_code_is_output( string $classes ): bool {
	$classes = ' ' . $classes . ' ';
	return str_contains( $classes, ' is-style-ekt-output ' ) || str_contains( $classes, ' is-style-ek-output ' );
}

/**
 * Styl Výstup a atributy bloku Kód na serveru.
 */
function ekt_code_init(): void {
	register_block_style(
		'core/code',
		array(
			'name'  => 'ekt-output',
			'label' => 'Výstup',
		)
	);
}
add_action( 'init', 'ekt_code_init' );

/**
 * Atributy ektFile a ekFile (alias) u core/code, ať je zná i REST a serverové vykreslení.
 *
 * @param array  $args Argumenty typu bloku.
 * @param string $name Název bloku.
 * @return array
 */
function ekt_code_block_args( array $args, string $name ): array {
	if ( 'core/code' === $name ) {
		$args['attributes']           = (array) ( $args['attributes'] ?? array() );
		$args['attributes']['ektFile'] = array( 'type' => 'string' );
		$args['attributes']['ekFile']  = array( 'type' => 'string' );
	}
	return $args;
}
add_filter( 'register_block_type_args', 'ekt_code_block_args', 10, 2 );

/**
 * Blok Kód: data-ekt-file, data-ekt-output a štítek nad kódem.
 *
 * @param string $html  HTML bloku.
 * @param array  $block Blok.
 * @return string
 */
function ekt_code_render_block( string $html, array $block ): string {
	$attrs = (array) ( $block['attrs'] ?? array() );
	$file  = trim( (string) ( $attrs['ektFile'] ?? $attrs['ekFile'] ?? '' ) );
	$p     = new WP_HTML_Tag_Processor( $html );
	if ( ! $p->next_tag( 'pre' ) ) {
		return $html;
	}
	$output = ekt_code_is_output( (string) ( $attrs['className'] ?? '' ) . ' ' . (string) $p->get_attribute( 'class' ) );
	if ( '' === $file && ! $output ) {
		return $html;
	}
	if ( '' !== $file ) {
		$p->set_attribute( 'data-ekt-file', $file );
	}
	if ( $output ) {
		$p->set_attribute( 'data-ekt-output', '1' );
	}
	$html  = $p->get_updated_html();
	$texts = ekt_code_texts();
	$label = $output ? ( '' !== $file ? $texts['output'] . ' · ' . $file : $texts['output'] ) : $file;
	return ekt_render(
		'code',
		static fn( array $args ): string => sprintf(
			'<div class="ekt-code-file%1$s"><span class="ekt-code-file__name">%2$s</span></div>',
			$args['output'] ? ' ekt-code-file--output' : '',
			esc_html( $args['label'] )
		),
		array(
			'label'  => $label,
			'file'   => $file,
			'output' => $output,
		)
	) . $html;
}
add_filter( 'render_block_core/code', 'ekt_code_render_block', 10, 2 );

/**
 * Bloky <pre> v obsahu: .ekt-code, čísla řádků, úzký kód (dva průchody WP_HTML_Tag_Processor:
 * první spočítá nejdelší řádek, druhý zapíše třídy).
 *
 * @param string $html Obsah.
 * @return string
 */
function ekt_code_content( string $html ): string {
	if ( is_admin() || is_feed() || ! str_contains( $html, '<pre' ) || ! method_exists( 'WP_HTML_Tag_Processor', 'next_token' ) ) {
		return $html;
	}
	$narrow = (int) apply_filters( 'ekt_code_narrow_chars', 64 );
	$widths = array();
	$text   = null;
	$p      = new WP_HTML_Tag_Processor( $html );
	while ( $p->next_token() ) {
		$type = $p->get_token_type();
		if ( '#text' === $type && null !== $text ) {
			$text .= $p->get_modifiable_text();
		} elseif ( '#tag' === $type ) {
			$tag = $p->get_tag();
			if ( 'PRE' === $tag && ! $p->is_tag_closer() ) {
				$text = '';
			} elseif ( 'BR' === $tag && null !== $text ) {
				$text .= "\n";
			} elseif ( 'PRE' === $tag && null !== $text ) {
				$max = 0;
				foreach ( explode( "\n", str_replace( "\t", '    ', $text ) ) as $line ) {
					$max = max( $max, mb_strlen( rtrim( $line ) ) );
				}
				$widths[] = $max;
				$text     = null;
			}
		}
	}
	$lines = (bool) ekt_opt( 'code_lines' );
	$i     = 0;
	$p     = new WP_HTML_Tag_Processor( $html );
	while ( $p->next_tag( 'pre' ) ) {
		$classes = (string) $p->get_attribute( 'class' );
		$p->add_class( 'ekt-code' );
		$output = null !== $p->get_attribute( 'data-ekt-output' ) || ekt_code_is_output( $classes );
		if ( $lines && ! $output && preg_match( '/(?:^|\s)lang(?:uage)?-[\w-]+/', $classes ) ) {
			$p->add_class( 'line-numbers' );
		}
		if ( isset( $widths[ $i ] ) && $widths[ $i ] <= $narrow ) {
			$p->add_class( 'ekt-code--narrow' );
		}
		++$i;
	}
	return $p->get_updated_html();
}
add_filter( 'the_content', 'ekt_code_content', 20 );

/**
 * Skripty a styly jen u článku s kódem; Prism jen s blokem core/code.
 */
function ekt_code_enqueue(): void {
	if ( ! is_singular() ) {
		return;
	}
	$post = get_queried_object();
	if ( ! $post instanceof WP_Post || post_password_required( $post ) ) {
		return;
	}
	$has_block = has_block( 'core/code', $post );
	if ( ! $has_block && ! str_contains( $post->post_content, '<pre' ) ) {
		return;
	}
	$texts = ekt_code_texts();
	ekt_enqueue_style( 'ekt-code', 'css/code.css' );
	ekt_enqueue_script(
		'ekt-code',
		'js/code.js',
		array(),
		array(
			'object' => 'EKT_CODE',
			'data'   => array(
				'collapse' => (int) apply_filters( 'ekt_code_collapse_lines', 30 ),
				'texts'    => $texts,
			),
		)
	);
	if ( apply_filters( 'ekt_code_prism', $has_block, $post ) ) {
		ekt_enqueue_script( 'ekt-prism', 'js/prism.js' );
	}
}
add_action( 'wp_enqueue_scripts', 'ekt_code_enqueue' );

/**
 * Editor: výběr jazyka a název souboru v bloku Kód.
 */
function ekt_code_editor_assets(): void {
	ekt_enqueue_script( 'ekt-code-editor', 'js/code-editor.js', array( 'wp-hooks', 'wp-compose', 'wp-element', 'wp-block-editor', 'wp-components' ), array( 'strategy' => '' ) );
}
add_action( 'enqueue_block_editor_assets', 'ekt_code_editor_assets' );

/**
 * Editor: štítek s názvem souboru nad blokem (i v iframe editoru).
 */
function ekt_code_editor_style(): void {
	if ( ! is_admin() ) {
		return;
	}
	wp_register_style( 'ekt-code-editor', false, array(), EKT_VERSION );
	wp_enqueue_style( 'ekt-code-editor' );
	wp_add_inline_style( 'ekt-code-editor', '.wp-block-code[data-ekt-file]::before{content:attr(data-ekt-file);display:block;margin:0 0 .6em;font-size:12px;font-weight:600;opacity:.7}' );
}
add_action( 'enqueue_block_assets', 'ekt_code_editor_style' );
