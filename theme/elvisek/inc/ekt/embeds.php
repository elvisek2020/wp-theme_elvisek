<?php
/**
 * EKT module: embeds
 * Version: 1.1.1
 * Requires: common
 * Replaces: youtube-embed-plus, embedpress, lazy-load-for-videos
 * Assets: css/embeds.css
 * Summary: Responzivní vložená videa (YouTube, Vimeo…) s lazy načítáním a titulkem, YouTube playlist bez oEmbed.
 */

defined( 'ABSPATH' ) || exit;

/*
 * - Filtr the_content (priorita 20, WP_HTML_Tag_Processor): iframe z poskytovatele videa dostane
 *   třídu ekt-embed, přijde o width, height a frameborder, doplní se loading="lazy" a title
 *   („Video“, když chybí). Poměr stran drží CSS (aspect-ratio, výchozí 16 : 9, proměnná
 *   --ekt-embed-ratio). Poskytovatelé: filtr ekt_embeds_hosts( $hosts ).
 * - YouTube odkaz s playlistem (…watch?v=X&list=Y, /playlist?list=Y) → přehrávač celého playlistu
 *   z youtube-nocookie.com bez dotazu na oEmbed (pre_oembed_result). Když první video playlistu
 *   zmizí, oEmbed vrátí 404 a místo videa by zůstal jen odkaz.
 * - Styl assets/ekt/css/embeds.css se načte jen na stránce s videem.
 *
 * Markup: <iframe class="ekt-embed" …> (YouTube playlist i ekt-embed--playlist).
 * Veřejné funkce: ekt_embeds_is_video( $src ), ekt_embeds_process( $html ).
 * Filtry: ekt_embeds_hosts( $hosts ), ekt_embeds_title (Video).
 */

/**
 * Hostitelé vložených videí (regulární výraz bez oddělovačů).
 *
 * @return string[]
 */
function ekt_embeds_hosts(): array {
	return (array) apply_filters(
		'ekt_embeds_hosts',
		array( 'youtube\.com', 'youtube-nocookie\.com', 'youtu\.be', 'vimeo\.com', 'dailymotion\.com', 'facebook\.com/plugins/video', 'tiktok\.com/embed', 'loom\.com/embed' )
	);
}

/**
 * Je iframe vložené video?
 *
 * @param string $src Adresa iframe.
 * @return bool
 */
function ekt_embeds_is_video( string $src ): bool {
	$hosts = array_map( static fn( $h ) => str_replace( '#', '\#', (string) $h ), ekt_embeds_hosts() );
	return '' !== $src && $hosts && (bool) preg_match( '#^(https?:)?//([a-z0-9-]+\.)*(' . implode( '|', $hosts ) . ')#i', trim( $src ) );
}

/**
 * Responzivní a líně načítaná videa v HTML.
 *
 * @param string $html HTML.
 * @return string
 */
function ekt_embeds_process( $html ) {
	if ( ! is_string( $html ) || false === stripos( $html, '<iframe' ) || is_feed() ) {
		return $html;
	}
	$p     = new WP_HTML_Tag_Processor( $html );
	$found = 0;
	while ( $p->next_tag( 'IFRAME' ) ) {
		$src = $p->get_attribute( 'src' );
		if ( ! is_string( $src ) || ! ekt_embeds_is_video( $src ) ) {
			continue;
		}
		$p->add_class( 'ekt-embed' );
		$p->remove_attribute( 'width' );
		$p->remove_attribute( 'height' );
		$p->remove_attribute( 'frameborder' );
		if ( ! $p->get_attribute( 'loading' ) ) {
			$p->set_attribute( 'loading', 'lazy' );
		}
		$title = $p->get_attribute( 'title' );
		if ( ! is_string( $title ) || '' === trim( $title ) ) {
			$p->set_attribute( 'title', (string) apply_filters( 'ekt_embeds_title', 'Video' ) );
		}
		++$found;
	}
	if ( ! $found ) {
		return $html;
	}
	if ( ! is_admin() ) {
		ekt_enqueue_style( 'ekt-embeds', 'css/embeds.css' );
	}
	return $p->get_updated_html();
}
add_filter( 'the_content', 'ekt_embeds_process', 20 );

/**
 * YouTube odkaz s playlistem → přehrávač playlistu bez oEmbed.
 *
 * @param mixed  $result Výsledek (null = pokračovat oEmbedem).
 * @param string $url    Vložená adresa.
 * @return mixed
 */
function ekt_embeds_youtube_playlist( $result, $url ) {
	if ( null !== $result || ! preg_match( '#^https?://(www\.|m\.)?youtube\.com/(watch|playlist)\?#i', (string) $url ) ) {
		return $result;
	}
	parse_str( (string) wp_parse_url( html_entity_decode( (string) $url, ENT_QUOTES | ENT_HTML5, 'UTF-8' ), PHP_URL_QUERY ), $query );
	$list = isset( $query['list'] ) && is_string( $query['list'] ) ? $query['list'] : '';
	if ( ! preg_match( '/^[A-Za-z0-9_-]{10,64}$/', $list ) ) {
		return $result;
	}
	return sprintf(
		'<iframe class="ekt-embed ekt-embed--playlist" src="%s" title="YouTube playlist" loading="lazy" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" referrerpolicy="strict-origin-when-cross-origin" allowfullscreen></iframe>',
		esc_url( 'https://www.youtube-nocookie.com/embed/videoseries?list=' . $list )
	);
}
add_filter( 'pre_oembed_result', 'ekt_embeds_youtube_playlist', 10, 2 );

/**
 * Blokový styl „Úzké“ u videa (core/video i core/embed). Šířku drží CSS proměnná
 * --ekt-narrow-width (výchozí 800 px). Starší obsah se třídou is-style-ek-narrow se
 * rozpozná stejně (alias, obsah se nepřepisuje).
 */
function ekt_embeds_block_styles(): void {
	foreach ( array( 'core/video', 'core/embed' ) as $block ) {
		register_block_style(
			$block,
			array(
				'name'  => 'ekt-narrow',
				'label' => 'Úzké',
			)
		);
	}
}
add_action( 'init', 'ekt_embeds_block_styles' );
