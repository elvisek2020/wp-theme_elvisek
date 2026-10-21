<?php
/**
 * EKT module: lightbox
 * Version: 1.0.1
 * Requires: common
 * Replaces: wp-jquery-lightbox, simple-lightbox, responsive-lightbox
 * Assets: js/lightbox.js, css/lightbox.css
 * Summary: Zvětšení obrázků v obsahu článku v nativním <dialog> (galerie, popisky, šipky, přejetí prstem).
 */

defined( 'ABSPATH' ) || exit;

/*
 * Server (filtr the_content, priorita 15 – před content-links) označí odkazy na soubor obrázku
 * atributem data-ekt-lightbox="<skupina>" (výchozí „post“; obrázky jedné skupiny se listují spolu).
 * Klasické galerie [gallery] bez parametru link dostanou link="file", aby vedly na obrázek.
 * Volitelně (filtr ekt_lightbox_unlinked, výchozí false) i obrázky bez odkazu v bloku Obrázek
 * dostanou data-ekt-lightbox na <img> (zvětší se největší verze ze srcset).
 *
 * JS (assets/ekt/js/lightbox.js) hledá jen [data-ekt-lightbox] uvnitř [data-ekt-content]
 * (šablona obalí obsah článku: <div data-ekt-content>…</div>) a vytvoří si dialog sám:
 *   dialog.ekt-lightbox > div.ekt-lightbox__stage > img.ekt-lightbox__img
 *                       button.ekt-lightbox__btn.ekt-lightbox__close | __prev | __next
 *                       p.ekt-lightbox__caption
 * Popisek z figcaption (nebo alt), počítadlo „2 / 5“, šipky, Esc, přejetí prstem, Cmd/Ctrl-klik
 * otevře obrázek normálně. Skript a styl se načtou jen na stránce, kde je co zvětšit.
 * Dokud běží původní plugin, nedělá nic (ekt_legacy_active( 'lightbox' )).
 *
 * Veřejné funkce: ekt_lightbox_enabled(), ekt_lightbox_is_image_url( $url ), ekt_lightbox_mark( $html ).
 * Filtry: ekt_lightbox_unlinked (false), ekt_lightbox_texts( $texts ), ekt_lightbox_group( $group ).
 */

/**
 * Dělá modul něco? Ne, dokud běží původní plugin.
 *
 * @return bool
 */
function ekt_lightbox_enabled(): bool {
	return ! ekt_legacy_active( 'lightbox' );
}

/**
 * Vede adresa na soubor obrázku?
 *
 * @param string $url Adresa.
 * @return bool
 */
function ekt_lightbox_is_image_url( string $url ): bool {
	return (bool) preg_match( '/\.(jpe?g|png|webp|gif|avif)(\?[^#]*)?(#.*)?$/i', trim( $url ) );
}

/**
 * Označí obrázky k zvětšení v HTML obsahu.
 *
 * @param string $html HTML.
 * @return string
 */
function ekt_lightbox_mark( $html ) {
	if ( ! is_string( $html ) || false === stripos( $html, '<img' ) || ! ekt_lightbox_enabled() || is_feed() || is_admin() ) {
		return $html;
	}
	$group    = sanitize_key( (string) apply_filters( 'ekt_lightbox_group', 'post' ) );
	$unlinked = (bool) apply_filters( 'ekt_lightbox_unlinked', false );
	$p        = new WP_HTML_Tag_Processor( $html );
	$marked   = 0;
	$in_link  = false;
	$in_image = 0; // hloubka figure.wp-block-image
	while ( $p->next_tag( array( 'tag_closers' => 'visit' ) ) ) {
		$tag = $p->get_tag();
		if ( 'A' === $tag ) {
			$in_link = ! $p->is_tag_closer();
			if ( $in_link ) {
				$href = $p->get_attribute( 'href' );
				if ( is_string( $href ) && ekt_lightbox_is_image_url( $href ) && null === $p->get_attribute( 'data-ekt-lightbox' ) ) {
					$p->set_attribute( 'data-ekt-lightbox', $group );
					++$marked;
				}
			}
		} elseif ( 'FIGURE' === $tag ) {
			if ( $p->is_tag_closer() ) {
				$in_image = max( 0, $in_image - 1 );
			} elseif ( $in_image || $p->has_class( 'wp-block-image' ) ) {
				++$in_image;
			}
		} elseif ( 'IMG' === $tag && $unlinked && ! $in_link && $in_image && null === $p->get_attribute( 'data-ekt-lightbox' ) ) {
			$p->set_attribute( 'data-ekt-lightbox', $group );
			++$marked;
		}
	}
	if ( $marked && is_singular() && in_the_loop() ) {
		ekt_lightbox_enqueue();
	}
	return $marked ? $p->get_updated_html() : $html;
}
add_filter( 'the_content', 'ekt_lightbox_mark', 15 );

/**
 * Skript a funkční styl (jen na stránce s obrázky k zvětšení; skript v patičce).
 */
function ekt_lightbox_enqueue(): void {
	$texts = array(
		'dialog' => 'Náhled obrázku',
		'close'  => 'Zavřít',
		'prev'   => 'Předchozí obrázek',
		'next'   => 'Další obrázek',
		'zoom'   => 'Zvětšit obrázek',
	);
	$texts = array_map( 'strval', array_merge( $texts, (array) apply_filters( 'ekt_lightbox_texts', $texts ) ) );
	ekt_enqueue_style( 'ekt-lightbox', 'css/lightbox.css' );
	ekt_enqueue_script(
		'ekt-lightbox',
		'js/lightbox.js',
		array(),
		array(
			'object' => 'ektLightbox',
			'data'   => array( 'texts' => $texts ),
		)
	);
}

/**
 * Klasické galerie [gallery] bez parametru link: odkaz na soubor (lightbox), ne na stránku přílohy.
 *
 * @param array $out Parametry galerie.
 * @return array
 */
function ekt_lightbox_gallery_atts( $out ) {
	if ( is_array( $out ) && ekt_lightbox_enabled() && empty( $out['link'] ) ) {
		$out['link'] = 'file';
	}
	return $out;
}
add_filter( 'shortcode_atts_gallery', 'ekt_lightbox_gallery_atts' );
