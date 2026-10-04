<?php
/**
 * Úpravy obsahu článků: externí odkazy, kotvy nadpisů, obsah článku (TOC),
 * sjednocení bloků kódu. Nahrazuje pluginy open-external-links a část syntaxhighlighter.
 */

defined( 'ABSPATH' ) || exit;

/** Položky obsahu článku posbírané při zpracování the_content. */
$GLOBALS['ek_toc'] = array();

add_filter( 'the_content', 'ek_process_content', 20 );

/**
 * Blok Kód s názvem souboru (atribut ekFile z editoru): štítek nad kódem
 * a data-ek-file na <pre> (pro Markdown verzi článku).
 */
add_filter( 'render_block_core/code', function ( string $html, array $block ): string {
	$file = trim( (string) ( $block['attrs']['ekFile'] ?? '' ) );
	if ( '' === $file ) {
		return $html;
	}
	$p = new WP_HTML_Tag_Processor( $html );
	if ( $p->next_tag( 'pre' ) ) {
		$p->set_attribute( 'data-ek-file', $file );
		$p->add_class( 'has-ek-file' );
		$html = $p->get_updated_html();
	}
	return '<div class="ek-code-file"><span>' . esc_html( $file ) . '</span></div>' . $html;
}, 10, 2 );

function ek_process_content( string $html ): string {
	if ( is_admin() || ! in_the_loop() || ! is_main_query() || '' === trim( $html ) ) {
		return $html;
	}

	$home_host = wp_parse_url( home_url(), PHP_URL_HOST );
	$lines     = (bool) ek_opt( 'ek_code_lines' );
	$toc       = array();
	$used_ids  = array();
	$p         = new WP_HTML_Tag_Processor( $html );

	while ( $p->next_tag() ) {
		$tag = $p->get_tag();

		// Externí odkazy do nového okna.
		if ( 'A' === $tag ) {
			$href = (string) $p->get_attribute( 'href' );
			$host = wp_parse_url( $href, PHP_URL_HOST );
			if ( $host && $host !== $home_host && preg_match( '#^https?://#i', $href ) ) {
				$p->set_attribute( 'target', '_blank' );
				$p->set_attribute( 'rel', 'noopener' );
			}
			continue;
		}

		// Kódové bloky: jednotná třída, aby šly stylovat a kopírovat.
		if ( 'PRE' === $tag ) {
			$p->add_class( 'ek-code' );
			if ( $lines && str_contains( (string) $p->get_attribute( 'class' ), 'language-' ) ) {
				$p->add_class( 'line-numbers' );
			}
		}
	}
	$html = $p->get_updated_html();

	// Bloky kódu jen ve dvou šířkách: krátké (nejdelší řádek do ~64 znaků) úzké 800 px, ostatní přes celý sloupec.
	$html = preg_replace_callback(
		'#<pre\b([^>]*\bclass="[^"]*\bek-code\b[^"]*")([^>]*)>(.*?)</pre>#is',
		function ( $m ) {
			$text = html_entity_decode( wp_strip_all_tags( preg_replace( '#<br\s*/?>#i', "\n", $m[3] ) ), ENT_QUOTES, 'UTF-8' );
			$max  = 0;
			foreach ( explode( "\n", str_replace( "\t", '    ', $text ) ) as $line ) {
				$max = max( $max, mb_strlen( rtrim( $line ) ) );
			}
			if ( $max > 64 ) {
				return $m[0];
			}
			$attrs = preg_replace( '#\bek-code\b#', 'ek-code ek-code--narrow', $m[1], 1 );
			return '<pre' . $attrs . $m[2] . '>' . $m[3] . '</pre>';
		},
		$html
	);

	// Kotvy pro nadpisy H2/H3 a rozbalovací sekce + položky obsahu článku.
	$html = preg_replace_callback(
		'#<(h2|h3|details)(\s[^>]*)?>(.*?)</\1>#is',
		function ( $m ) use ( &$toc, &$used_ids ) {
			$tag   = strtolower( $m[1] );
			$attrs = $m[2] ?? '';
			$inner = $m[3];

			if ( 'details' === $tag ) {
				if ( ! preg_match( '#<summary[^>]*>(.*?)</summary>#is', $inner, $s ) ) {
					return $m[0];
				}
				$text = trim( wp_strip_all_tags( $s[1] ) );
			} else {
				$text = trim( wp_strip_all_tags( $inner ) );
			}
			if ( '' === $text ) {
				return $m[0];
			}

			if ( preg_match( '#\sid=["\']([^"\']+)["\']#i', $attrs, $idm ) ) {
				$id = $idm[1];
			} else {
				$base = sanitize_title( $text ) ?: 'sekce';
				$id   = $base;
				$i    = 2;
				while ( isset( $used_ids[ $id ] ) ) {
					$id = $base . '-' . $i++;
				}
				$attrs .= ' id="' . esc_attr( $id ) . '"';
			}
			$used_ids[ $id ] = true;
			$toc[]           = array( 'level' => 'h3' === $tag ? 3 : 2, 'id' => $id, 'text' => $text );

			return '<' . $tag . $attrs . '>' . $inner . '</' . $tag . '>';
		},
		$html
	);

	$GLOBALS['ek_toc'] = $toc;
	return $html;
}

/**
 * Vypíše obsah článku, pokud má aspoň 2 položky.
 */
function ek_the_toc(): void {
	$toc = $GLOBALS['ek_toc'] ?? array();
	if ( count( $toc ) < 2 ) {
		return;
	}
	echo '<nav class="ek-box ek-toc" aria-label="Obsah článku"><h2 class="ek-box__title">Obsah článku</h2><ol>';
	foreach ( $toc as $item ) {
		printf(
			'<li class="ek-toc__l%d"><a href="#%s">%s</a></li>',
			(int) $item['level'],
			esc_attr( $item['id'] ),
			esc_html( $item['text'] )
		);
	}
	echo '</ol></nav>';
}

// Staré třídy z Graphene/SyntaxHighlighter v obsahu nechat projít, ale bez inline fontů (Arial).
add_filter( 'the_content', function ( string $html ): string {
	return preg_replace( '#\sstyle="font-family:\s*arial,\s*helvetica,\s*sans-serif;?"#i', '', $html );
}, 5 );

/**
 * Obrázky v článku: jednotná šířka (max. 800 px, zarovnané vlevo s textem).
 * Malé ikonky a obrázky v galeriích nechává být. Prohlížeči řekne, že se obrázek
 * zobrazí až v 800 px, aby si ze srcset vzal dost velkou verzi (ne rozmazaný náhled 300 px).
 */
add_filter( 'wp_content_img_tag', function ( string $img, string $context ): string {
	if ( 'the_content' !== $context || ! is_singular() || str_contains( $img, 'wp-smiley' ) ) {
		return $img;
	}
	$w = preg_match( '/\swidth="(\d+)"/', $img, $m ) ? (int) $m[1] : 0;
	$h = preg_match( '/\sheight="(\d+)"/', $img, $m ) ? (int) $m[1] : 0;
	if ( $w && $w < 160 ) {
		return $img; // ikonka / logo – necháme v původní velikosti
	}
	$class = 'ek-img' . ( $w && $h > $w * 1.15 ? ' ek-img--tall' : '' );
	$img   = preg_match( '/\sclass="/', $img )
		? preg_replace( '/\sclass="/', ' class="' . $class . ' ', $img, 1 )
		: preg_replace( '/^<img/', '<img class="' . $class . '"', $img );
	if ( str_contains( $img, ' srcset=' ) ) {
		$sizes = '(max-width: 860px) 100vw, 800px';
		$img   = preg_match( '/\ssizes="[^"]*"/', $img )
			? preg_replace( '/\ssizes="[^"]*"/', ' sizes="' . $sizes . '"', $img, 1 )
			: str_replace( ' srcset=', ' sizes="' . $sizes . '" srcset=', $img );
	}
	return $img;
}, 10, 2 );
