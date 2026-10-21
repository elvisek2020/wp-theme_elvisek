<?php
/**
 * EKT module: markdown
 * Version: 1.0.0
 * Requires: common
 * Replaces: –
 * Assets: js/markdown.js
 * Summary: Článek jako Markdown na adrese /<článek>.md (pro AI nástroje) a tlačítko „Kopírovat pro AI“.
 */

defined( 'ABSPATH' ) || exit;

/*
 * /<permalink>.md → text/markdown: nadpis, řádek se zdrojem (adresa, autor, datum, aktualizace,
 * rubrika), Ve zkratce a obsah převedený z HTML (DOMDocument: nadpisy, seznamy, tabulky, kód
 * s jazykem a názvem souboru, Výstup, rozbalovací sekce). WordPress takovou adresu nezná (404),
 * obslouží ji template_redirect – bez přepisovacích pravidel, funguje i ve WP v podsložce.
 * Hlavičky: Link rel="canonical" na článek (bez noindex), Cache-Control public 1 h (bez
 * Expires/Pragma z 404), X-Content-Type-Options nosniff. V <head> článku je
 * <link rel="alternate" type="text/markdown">. Jen s hezkými adresami (permalink_structure).
 *
 * Veřejné funkce: ekt_markdown_post_types(), ekt_markdown_url( $post ), ekt_markdown_post( $post ),
 * ekt_markdown_from_html( $html ), ekt_markdown_button( $post ) (HTML tlačítka).
 * Filtry: ekt_markdown_post_types( ['post','page'] ), ekt_markdown_max_age( 3600 ),
 * ekt_markdown( $md, $post ), ekt_markdown_texts( $texts ) (prompt s %1$s název webu,
 * %2$s titulek, %3$s adresa .md), ekt_markdown_ai_links( [ id => [ popisek, adresa s %s = prompt ] ] ),
 * ekt_markdown_html( $html, $args ) (override template-parts/ekt/markdown.php; $args: md, links, texts).
 *
 * Markup tlačítka: <div class="ekt-ai" data-ekt-ai data-ekt-ai-md="…/clanek.md">
 *   <button class="ekt-ai__copy" data-ekt-ai-copy>svg<span class="ekt-ai__label" data-ekt-ai-label>Kopírovat pro AI</span></button>
 *   <button class="ekt-ai__more" data-ekt-ai-toggle aria-expanded="false">svg</button>
 *   <div class="ekt-ai__menu" data-ekt-ai-menu hidden><a class="ekt-ai__link">Otevřít v Claude ↗</a>…</div></div>
 * JS (markdown.js) přidá .is-done po zkopírování.
 */

/**
 * Typy obsahu s Markdown verzí.
 *
 * @return string[]
 */
function ekt_markdown_post_types(): array {
	return array_values( array_filter( array_map( 'sanitize_key', (array) apply_filters( 'ekt_markdown_post_types', array( 'post', 'page' ) ) ) ) );
}

/**
 * Má článek Markdown verzi?
 *
 * @param WP_Post|null $post Článek.
 * @return bool
 */
function ekt_markdown_available( $post ): bool {
	return $post instanceof WP_Post
		&& '' !== (string) get_option( 'permalink_structure' )
		&& 'publish' === $post->post_status
		&& in_array( $post->post_type, ekt_markdown_post_types(), true )
		&& ! post_password_required( $post )
		&& (int) get_option( 'page_on_front' ) !== (int) $post->ID;
}

/**
 * Adresa Markdown verze článku.
 *
 * @param int|WP_Post|null $post Článek.
 * @return string '' = článek Markdown verzi nemá.
 */
function ekt_markdown_url( $post = null ): string {
	$post = get_post( $post );
	return ekt_markdown_available( $post ) ? untrailingslashit( (string) get_permalink( $post ) ) . '.md' : '';
}

/**
 * HTML obsahu → Markdown.
 *
 * @param string $html HTML.
 * @return string
 */
function ekt_markdown_from_html( string $html ): string {
	if ( '' === trim( $html ) || ! class_exists( 'DOMDocument' ) ) {
		return '';
	}
	$doc       = new DOMDocument();
	$use_error = libxml_use_internal_errors( true );
	$doc->loadHTML( '<?xml encoding="utf-8"?><body>' . $html . '</body>', LIBXML_NOERROR | LIBXML_NOWARNING );
	libxml_clear_errors();
	libxml_use_internal_errors( $use_error );
	$body = $doc->getElementsByTagName( 'body' )->item( 0 );
	$md   = $body ? ekt_markdown_children( $body, 0 ) : '';
	$md   = (string) preg_replace( "/[ \t]+\n/", "\n", $md );
	$md   = (string) preg_replace( "/\n{3,}/", "\n\n", $md );
	return trim( $md ) . "\n";
}

/**
 * Převod potomků uzlu.
 *
 * @param DOMNode $node  Uzel.
 * @param int     $depth Hloubka seznamu.
 * @return string
 */
function ekt_markdown_children( DOMNode $node, int $depth ): string {
	$out = '';
	foreach ( $node->childNodes as $child ) {
		$out .= ekt_markdown_node( $child, $depth );
	}
	return $out;
}

/**
 * Obsah uzlu na jednom řádku.
 *
 * @param DOMNode $node Uzel.
 * @return string
 */
function ekt_markdown_inline( DOMNode $node ): string {
	return trim( (string) preg_replace( '/[ \t]*\n[ \t]*/', ' ', ekt_markdown_children( $node, 0 ) ) );
}

/**
 * Atribut s fallbackem na starší název (data-ekt-file / data-ek-file).
 *
 * @param DOMElement $el    Prvek.
 * @param string     $name  Atribut bez prefixu (file, output).
 * @return string|null
 */
function ekt_markdown_data( DOMElement $el, string $name ): ?string {
	foreach ( array( 'data-ekt-', 'data-ek-' ) as $prefix ) {
		if ( $el->hasAttribute( $prefix . $name ) ) {
			return (string) $el->getAttribute( $prefix . $name );
		}
	}
	return null;
}

/**
 * Převod jednoho uzlu.
 *
 * @param DOMNode $n     Uzel.
 * @param int     $depth Hloubka seznamu.
 * @return string
 */
function ekt_markdown_node( DOMNode $n, int $depth ): string {
	if ( XML_TEXT_NODE === $n->nodeType ) {
		return (string) preg_replace( '/\s+/u', ' ', (string) $n->nodeValue );
	}
	if ( ! $n instanceof DOMElement ) {
		return '';
	}
	$tag   = strtolower( $n->nodeName );
	$class = ' ' . $n->getAttribute( 'class' ) . ' ';
	switch ( $tag ) {
		case 'script':
		case 'style':
		case 'noscript':
		case 'svg':
		case 'button':
		case 'form':
		case 'iframe':
		case 'template':
			return '';
		case 'h1':
		case 'h2':
		case 'h3':
		case 'h4':
		case 'h5':
		case 'h6':
			return "\n\n" . str_repeat( '#', (int) $tag[1] ) . ' ' . ekt_markdown_inline( $n ) . "\n\n";
		case 'p':
			$t = ekt_markdown_inline( $n );
			return '' === $t ? '' : "\n\n" . $t . "\n\n";
		case 'br':
			return "  \n";
		case 'hr':
			return "\n\n---\n\n";
		case 'strong':
		case 'b':
			$t = ekt_markdown_inline( $n );
			return '' === $t ? '' : '**' . $t . '**';
		case 'em':
		case 'i':
			$t = ekt_markdown_inline( $n );
			return '' === $t ? '' : '*' . $t . '*';
		case 'code':
		case 'kbd':
			return '`' . str_replace( '`', '\\`', $n->textContent ) . '`';
		case 'a':
			$href = (string) $n->getAttribute( 'href' );
			$img  = $n->getElementsByTagName( 'img' )->item( 0 );
			if ( $img instanceof DOMElement && '' === trim( $n->textContent ) ) {
				// Obrázek s odkazem na plnou velikost → jen obrázek, a to v plné velikosti.
				$src = preg_match( '/\.(webp|png|jpe?g|gif|avif)(\?.*)?$/i', $href ) ? $href : (string) $img->getAttribute( 'src' );
				return '' === $src ? '' : '![' . trim( (string) $img->getAttribute( 'alt' ) ) . '](' . $src . ')';
			}
			$t = ekt_markdown_inline( $n );
			if ( '' === $href || str_starts_with( $href, '#' ) || str_starts_with( strtolower( $href ), 'javascript:' ) ) {
				return $t;
			}
			return '[' . ( '' === $t ? $href : $t ) . '](' . $href . ')';
		case 'img':
			$src = (string) $n->getAttribute( 'src' );
			return '' === $src ? '' : '![' . trim( (string) $n->getAttribute( 'alt' ) ) . '](' . $src . ')';
		case 'figure':
			return "\n\n" . trim( ekt_markdown_children( $n, $depth ) ) . "\n\n";
		case 'figcaption':
			$t = ekt_markdown_inline( $n );
			return '' === $t ? '' : "\n\n*" . $t . "*\n\n";
		case 'pre':
			$code  = $n->getElementsByTagName( 'code' )->item( 0 );
			$cls   = $n->getAttribute( 'class' ) . ' ' . ( $code instanceof DOMElement ? $code->getAttribute( 'class' ) : '' );
			$lang  = preg_match( '/lang(?:uage)?-([\w+-]+)/', $cls, $m ) ? $m[1] : '';
			$text  = rtrim( str_replace( "\r", '', $n->textContent ) );
			$fence = str_contains( $text, '```' ) ? '````' : '```';
			$file  = trim( (string) ekt_markdown_data( $n, 'file' ) );
			$file  = str_replace( '`', '', $file );
			$head  = '' === $file ? '' : 'Soubor `' . $file . "`:\n\n";
			if ( null !== ekt_markdown_data( $n, 'output' ) || str_contains( $class, ' is-style-ekt-output ' ) || str_contains( $class, ' is-style-ek-output ' ) ) {
				$head = '' === $file ? "Výstup:\n\n" : 'Výstup (`' . $file . "`):\n\n";
			}
			return "\n\n" . $head . $fence . $lang . "\n" . $text . "\n" . $fence . "\n\n";
		case 'blockquote':
			$t = trim( ekt_markdown_children( $n, $depth ) );
			return "\n\n" . preg_replace( '/^/m', '> ', $t ) . "\n\n";
		case 'ul':
		case 'ol':
			$out = '';
			$i   = (int) ( $n->getAttribute( 'start' ) ? $n->getAttribute( 'start' ) : 1 );
			foreach ( $n->childNodes as $li ) {
				if ( ! $li instanceof DOMElement || 'li' !== strtolower( $li->nodeName ) ) {
					continue;
				}
				$marker = 'ol' === $tag ? ( $i++ ) . '. ' : '- ';
				$text   = '';
				$nested = '';
				foreach ( $li->childNodes as $c ) {
					if ( $c instanceof DOMElement && in_array( strtolower( $c->nodeName ), array( 'ul', 'ol' ), true ) ) {
						$nested .= ekt_markdown_node( $c, $depth + 1 );
					} else {
						$text .= ekt_markdown_node( $c, $depth + 1 );
					}
				}
				$text = trim( (string) preg_replace( '/\n{2,}/', "\n", $text ) );
				$text = str_replace( "\n", "\n" . str_repeat( '  ', $depth + 1 ), $text );
				$out .= str_repeat( '  ', $depth ) . $marker . $text . "\n" . $nested;
			}
			return 0 === $depth ? "\n\n" . $out . "\n" : $out;
		case 'table':
			$rows = array();
			foreach ( $n->getElementsByTagName( 'tr' ) as $tr ) {
				$cells = array();
				foreach ( $tr->childNodes as $cell ) {
					if ( $cell instanceof DOMElement && in_array( strtolower( $cell->nodeName ), array( 'td', 'th' ), true ) ) {
						$cells[] = str_replace( '|', '\\|', ekt_markdown_inline( $cell ) );
					}
				}
				if ( $cells ) {
					$rows[] = $cells;
				}
			}
			if ( ! $rows ) {
				return '';
			}
			$cols = max( array_map( 'count', $rows ) );
			$line = static fn( array $r ): string => '| ' . implode( ' | ', array_pad( $r, $cols, '' ) ) . ' |';
			$out  = $line( $rows[0] ) . "\n|" . str_repeat( ' --- |', $cols ) . "\n";
			foreach ( array_slice( $rows, 1 ) as $r ) {
				$out .= $line( $r ) . "\n";
			}
			return "\n\n" . $out . "\n";
		case 'div':
			// Štítek souboru nad kódem vypíše blok pre.
			if ( str_contains( $class, ' ekt-code-file ' ) || str_contains( $class, ' ek-code-file ' ) ) {
				return '';
			}
			return ekt_markdown_children( $n, $depth );
		case 'details':
			$out = '';
			foreach ( $n->childNodes as $c ) {
				$out .= ( $c instanceof DOMElement && 'summary' === strtolower( $c->nodeName ) )
					? "\n\n**" . ekt_markdown_inline( $c ) . "**\n\n"
					: ekt_markdown_node( $c, $depth );
			}
			return $out;
	}
	return ekt_markdown_children( $n, $depth ); // span, section, mark, … → jen obsah
}

/**
 * Celý článek jako Markdown (hlavička se zdrojem + Ve zkratce + obsah).
 *
 * @param WP_Post $post Článek.
 * @return string
 */
function ekt_markdown_post( WP_Post $post ): string {
	$previous        = $GLOBALS['post'] ?? null;
	$GLOBALS['post'] = $post; // phpcs:ignore WordPress.WP.GlobalVariablesOverride -- vrací se níže
	setup_postdata( $post );
	$html = (string) apply_filters( 'the_content', get_the_content( null, false, $post ) );
	$author = trim( (string) get_the_author_meta( 'display_name', (int) $post->post_author ) );
	$meta   = array_filter(
		array(
			'Zdroj: ' . get_permalink( $post ),
			'Web: ' . wp_specialchars_decode( (string) get_bloginfo( 'name' ), ENT_QUOTES ),
			'' !== $author ? 'Autor: ' . $author : '',
			'Publikováno: ' . get_the_date( 'j. n. Y', $post ),
		)
	);
	$updated = function_exists( 'ekt_updated_date' ) ? (string) ekt_updated_date( $post ) : '';
	if ( '' !== $updated ) {
		$meta[] = 'Aktualizováno: ' . wp_date( 'j. n. Y', (int) strtotime( $updated . ' 12:00:00' ) );
	}
	$cat = 'post' === $post->post_type ? ekt_primary_category( $post ) : null;
	if ( $cat ) {
		$meta[] = 'Rubrika: ' . $cat->name;
	}
	$summary = '';
	$items   = function_exists( 'ekt_summary_items' ) ? (array) ekt_summary_items( $post ) : array();
	if ( $items ) {
		$summary = "**Ve zkratce:**\n\n- " . implode( "\n- ", $items ) . "\n\n";
	}
	wp_reset_postdata();
	$GLOBALS['post'] = $previous; // phpcs:ignore WordPress.WP.GlobalVariablesOverride
	$md = '# ' . ekt_plain_title( $post ) . "\n\n> " . implode( ' · ', $meta ) . "\n\n" . $summary . ekt_markdown_from_html( $html );
	return (string) apply_filters( 'ekt_markdown', $md, $post );
}

/**
 * Obsluha /<článek>.md (WordPress adresu nezná → 404 → tady).
 */
function ekt_markdown_serve(): void {
	if ( ! is_404() || '' === (string) get_option( 'permalink_structure' ) ) {
		return;
	}
	$path = (string) wp_parse_url( (string) ( $_SERVER['REQUEST_URI'] ?? '' ), PHP_URL_PATH ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- jen cesta, porovnává se
	if ( strlen( $path ) < 5 || ! str_ends_with( $path, '.md' ) ) {
		return;
	}
	// Adresa článku = původ webu + cesta bez .md (cesta už obsahuje případnou podsložku).
	$origin = (string) preg_replace( '#^(https?://[^/]+).*$#i', '$1', home_url() );
	$id     = url_to_postid( $origin . substr( $path, 0, -3 ) );
	$post   = $id ? get_post( $id ) : null;
	if ( ! ekt_markdown_available( $post ) ) {
		return;
	}
	$md = ekt_markdown_post( $post );
	status_header( 200 );
	if ( ! headers_sent() ) {
		header_remove( 'Expires' ); // z nocache_headers() u 404
		header_remove( 'Pragma' );
		header_remove( 'Last-Modified' );
		header( 'Content-Type: text/markdown; charset=utf-8' );
		header( 'Cache-Control: public, max-age=' . max( 0, (int) apply_filters( 'ekt_markdown_max_age', HOUR_IN_SECONDS ) ) );
		header( 'X-Content-Type-Options: nosniff' );
		header( 'Link: <' . esc_url_raw( (string) get_permalink( $post ) ) . '>; rel="canonical"' );
	}
	echo $md; // phpcs:ignore WordPress.Security.EscapeOutput -- prostý text (text/markdown)
	exit;
}
add_action( 'template_redirect', 'ekt_markdown_serve', 1 );

/**
 * Odkaz na Markdown verzi v hlavičce článku.
 */
function ekt_markdown_head(): void {
	if ( ! is_singular() ) {
		return;
	}
	$url = ekt_markdown_url( get_queried_object() );
	if ( '' !== $url ) {
		printf( '<link rel="alternate" type="text/markdown" title="Markdown" href="%s">' . "\n", esc_url( $url ) );
	}
}
add_action( 'wp_head', 'ekt_markdown_head', 5 );

/**
 * Tlačítko „Kopírovat pro AI“ s nabídkou Otevřít v Claude / ChatGPT / jako Markdown.
 *
 * @param int|WP_Post|null $post Článek.
 * @return string HTML ('' = článek Markdown verzi nemá).
 */
function ekt_markdown_button( $post = null ): string {
	$post = get_post( $post );
	$md   = ekt_markdown_url( $post );
	if ( '' === $md ) {
		return '';
	}
	$texts  = array_merge(
		array(
			'copy'    => 'Kopírovat pro AI',
			'copying' => 'Kopíruji…',
			'copied'  => 'Zkopírováno',
			'opened'  => 'Otevřeno jako Markdown',
			'title'   => 'Zkopíruje článek jako Markdown – vložte ho do libovolné AI',
			'more'    => 'Další možnosti pro AI',
			'show'    => 'Zobrazit jako Markdown',
			'prompt'  => 'Přečti si tento článek z webu %1$s: „%2$s“ – %3$s. Pak mi s ním pomoz, odpovídej česky.',
		),
		(array) apply_filters( 'ekt_markdown_texts', array() )
	);
	$prompt = sprintf( (string) $texts['prompt'], wp_specialchars_decode( (string) get_bloginfo( 'name' ), ENT_QUOTES ), ekt_plain_title( $post ), $md );
	$links  = array();
	$ai     = (array) apply_filters(
		'ekt_markdown_ai_links',
		array(
			'claude'  => array( 'Otevřít v Claude', 'https://claude.ai/new?q=%s' ),
			'chatgpt' => array( 'Otevřít v ChatGPT', 'https://chatgpt.com/?q=%s' ),
		)
	);
	foreach ( $ai as $id => $link ) {
		if ( is_array( $link ) && 2 === count( $link ) ) {
			$links[ (string) $id ] = array( (string) $link[0], str_replace( '%s', rawurlencode( $prompt ), (string) $link[1] ) );
		}
	}
	ekt_enqueue_script(
		'ekt-markdown',
		'js/markdown.js',
		array(),
		array(
			'object' => 'EKT_MARKDOWN',
			'data'   => array( 'texts' => array_intersect_key( $texts, array_flip( array( 'copy', 'copying', 'copied', 'opened' ) ) ) ),
		)
	);
	return ekt_render(
		'markdown',
		static function ( array $args ): string {
			$t     = $args['texts'];
			$items = '';
			foreach ( $args['links'] as $link ) {
				$items .= sprintf( '<a class="ekt-ai__link" href="%1$s" target="_blank" rel="noopener noreferrer nofollow">%2$s ↗</a>', esc_url( $link[1] ), esc_html( $link[0] ) );
			}
			$items .= sprintf( '<a class="ekt-ai__link" href="%1$s" target="_blank" rel="nofollow">%2$s</a>', esc_url( $args['md'] ), esc_html( $t['show'] ) );
			return sprintf(
				'<div class="ekt-ai" data-ekt-ai data-ekt-ai-md="%1$s"><button type="button" class="ekt-ai__copy" data-ekt-ai-copy title="%2$s">%3$s<span class="ekt-ai__label" data-ekt-ai-label>%4$s</span></button><button type="button" class="ekt-ai__more" data-ekt-ai-toggle aria-expanded="false" aria-haspopup="true" aria-label="%5$s">%6$s</button><div class="ekt-ai__menu" data-ekt-ai-menu hidden>%7$s</div></div>',
				esc_url( $args['md'] ),
				esc_attr( $t['title'] ),
				ekt_icon( 'sparkle', 16 ),
				esc_html( $t['copy'] ),
				esc_attr( $t['more'] ),
				ekt_icon( 'chevron', 16 ),
				$items
			);
		},
		array(
			'md'    => $md,
			'links' => $links,
			'texts' => $texts,
			'post'  => $post,
		)
	);
}
