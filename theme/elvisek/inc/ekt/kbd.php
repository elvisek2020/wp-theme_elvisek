<?php
/**
 * EKT module: kbd
 * Version: 1.0.0
 * Requires: common
 * Replaces: –
 * Assets: js/kbd-editor.js
 * Summary: Formát „Klávesa“ v editoru: označený text jako <kbd class="ekt-kbd">.
 */

defined( 'ABSPATH' ) || exit;

/*
 * Editor (js/kbd-editor.js): formát ekt/kbd → <kbd class="ekt-kbd">Ctrl</kbd> s tlačítkem
 * v liště. Starší formát elvisek/kbd (<kbd class="ek-kbd">) je zaregistrovaný bez tlačítka,
 * aby uložený obsah zůstal v editoru beze změny.
 * Web: <kbd class="ek-kbd"> dostane při výpisu i třídu ekt-kbd (WP_HTML_Tag_Processor), takže
 * šablona styluje jen .ekt-kbd. Obsah článků se nepřepisuje.
 *
 * Markup: <kbd class="ekt-kbd">…</kbd> (vzhled klávesy je v CSS šablony).
 */

/**
 * Editor: formát Klávesa.
 */
function ekt_kbd_editor_assets(): void {
	ekt_enqueue_script( 'ekt-kbd-editor', 'js/kbd-editor.js', array( 'wp-rich-text', 'wp-element', 'wp-block-editor', 'wp-data' ), array( 'strategy' => '' ) );
}
add_action( 'enqueue_block_editor_assets', 'ekt_kbd_editor_assets' );

/**
 * Starý formát <kbd class="ek-kbd"> → i třída ekt-kbd (jen ve výpisu).
 *
 * @param string $html Obsah.
 * @return string
 */
function ekt_kbd_content( string $html ): string {
	if ( is_admin() || ! str_contains( $html, 'ek-kbd' ) ) {
		return $html;
	}
	$p = new WP_HTML_Tag_Processor( $html );
	while ( $p->next_tag( array( 'tag_name' => 'kbd', 'class_name' => 'ek-kbd' ) ) ) {
		$p->add_class( 'ekt-kbd' );
	}
	return $p->get_updated_html();
}
add_filter( 'the_content', 'ekt_kbd_content', 20 );
