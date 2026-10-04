<?php
/**
 * Článek jako Markdown pro AI.
 *
 *  - https://www.elvisek.cz/2017/11/clanek.md → text/markdown (nadpis, zdroj, obsah, kód s jazykem)
 *  - v hlavičce článku <link rel="alternate" type="text/markdown">
 *  - tlačítko „Kopírovat pro AI“ s nabídkou Otevřít v Claude / ChatGPT (ek_ai_button)
 */

defined( 'ABSPATH' ) || exit;

/** Adresa Markdown verze článku. */
function ek_md_url( $post = null ): string {
	return untrailingslashit( get_permalink( $post ) ) . '.md';
}

/** Převod HTML obsahu článku na Markdown. */
function ek_html_to_md( string $html ): string {
	if ( '' === trim( $html ) ) {
		return '';
	}
	$doc = new DOMDocument();
	libxml_use_internal_errors( true );
	$doc->loadHTML( '<?xml encoding="utf-8"?><body>' . $html . '</body>', LIBXML_NOERROR | LIBXML_NOWARNING );
	libxml_clear_errors();
	$body = $doc->getElementsByTagName( 'body' )->item( 0 );
	$md   = $body ? ek_md_children( $body, 0 ) : '';
	$md   = preg_replace( "/[ \t]+\n/", "\n", $md );
	$md   = preg_replace( "/\n{3,}/", "\n\n", $md );
	return trim( $md ) . "\n";
}

function ek_md_children( DOMNode $node, int $depth ): string {
	$out = '';
	foreach ( $node->childNodes as $child ) {
		$out .= ek_md_node( $child, $depth );
	}
	return $out;
}

function ek_md_inline( DOMNode $node ): string {
	return trim( preg_replace( '/[ \t]*\n[ \t]*/', ' ', ek_md_children( $node, 0 ) ) );
}

function ek_md_node( DOMNode $n, int $depth ): string {
	if ( XML_TEXT_NODE === $n->nodeType ) {
		return preg_replace( '/\s+/u', ' ', $n->nodeValue );
	}
	if ( XML_ELEMENT_NODE !== $n->nodeType ) {
		return '';
	}
	/** @var DOMElement $n */
	$tag = strtolower( $n->nodeName );
	switch ( $tag ) {
		case 'script':
		case 'style':
		case 'noscript':
		case 'svg':
		case 'button':
		case 'form':
		case 'iframe':
			return '';
		case 'h1': case 'h2': case 'h3': case 'h4': case 'h5': case 'h6':
			return "\n\n" . str_repeat( '#', min( 6, (int) $tag[1] ) ) . ' ' . ek_md_inline( $n ) . "\n\n";
		case 'p':
			$t = ek_md_inline( $n );
			return '' === $t ? '' : "\n\n" . $t . "\n\n";
		case 'br':
			return "  \n";
		case 'hr':
			return "\n\n---\n\n";
		case 'strong':
		case 'b':
			$t = ek_md_inline( $n );
			return '' === $t ? '' : '**' . $t . '**';
		case 'em':
		case 'i':
			$t = ek_md_inline( $n );
			return '' === $t ? '' : '*' . $t . '*';
		case 'code':
		case 'kbd':
			return '`' . str_replace( '`', '\\`', $n->textContent ) . '`';
		case 'mark':
		case 'span':
		case 'u':
		case 'small':
		case 'sup':
		case 'sub':
			return ek_md_children( $n, $depth );
		case 'a':
			$href = (string) $n->getAttribute( 'href' );
			$img  = $n->getElementsByTagName( 'img' )->item( 0 );
			if ( $img && '' === trim( $n->textContent ) ) {
				// obrázek s odkazem na plnou velikost → jen obrázek, a to v plné velikosti
				$src = preg_match( '/\.(webp|png|jpe?g|gif|avif)(\?.*)?$/i', $href ) ? $href : (string) $img->getAttribute( 'src' );
				return '' === $src ? '' : '![' . trim( (string) $img->getAttribute( 'alt' ) ) . '](' . $src . ')';
			}
			$t = ek_md_inline( $n );
			if ( '' === $href || str_starts_with( $href, '#' ) || str_starts_with( $href, 'javascript:' ) ) {
				return $t;
			}
			return '[' . ( '' === $t ? $href : $t ) . '](' . $href . ')';
		case 'img':
			$src = (string) $n->getAttribute( 'src' );
			return '' === $src ? '' : '![' . trim( (string) $n->getAttribute( 'alt' ) ) . '](' . $src . ')';
		case 'figure':
			return "\n\n" . trim( ek_md_children( $n, $depth ) ) . "\n\n";
		case 'figcaption':
			$t = ek_md_inline( $n );
			return '' === $t ? '' : "\n\n*" . $t . "*\n\n";
		case 'pre':
			$code = $n->getElementsByTagName( 'code' )->item( 0 );
			$cls  = $n->getAttribute( 'class' ) . ' ' . ( $code instanceof DOMElement ? $code->getAttribute( 'class' ) : '' );
			$lang = preg_match( '/language-([\w+-]+)/', $cls, $m ) ? $m[1] : '';
			$text = rtrim( str_replace( "\r", '', $n->textContent ) );
			$fence = str_contains( $text, '```' ) ? '````' : '```';
			$file  = trim( (string) $n->getAttribute( 'data-ek-file' ) );
			$head  = '' === $file ? '' : 'Soubor `' . str_replace( '`', '', $file ) . "`:\n\n";
			if ( $n->hasAttribute( 'data-ek-output' ) ) {
				$head = '' === $file ? "Výstup:\n\n" : 'Výstup (`' . str_replace( '`', '', $file ) . "`):\n\n";
			}
			return "\n\n" . $head . $fence . $lang . "\n" . $text . "\n" . $fence . "\n\n";
		case 'blockquote':
			$t = trim( ek_md_children( $n, $depth ) );
			return "\n\n" . preg_replace( '/^/m', '> ', $t ) . "\n\n";
		case 'ul':
		case 'ol':
			$out = '';
			$i   = (int) ( $n->getAttribute( 'start' ) ?: 1 );
			foreach ( $n->childNodes as $li ) {
				if ( ! $li instanceof DOMElement || 'li' !== strtolower( $li->nodeName ) ) {
					continue;
				}
				$marker = 'ol' === $tag ? ( $i++ ) . '. ' : '- ';
				$text   = '';
				$nested = '';
				foreach ( $li->childNodes as $c ) {
					if ( $c instanceof DOMElement && in_array( strtolower( $c->nodeName ), array( 'ul', 'ol' ), true ) ) {
						$nested .= ek_md_node( $c, $depth + 1 );
					} else {
						$text .= ek_md_node( $c, $depth + 1 );
					}
				}
				$text = trim( preg_replace( '/\n{2,}/', "\n", $text ) );
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
						$cells[] = str_replace( '|', '\\|', ek_md_inline( $cell ) );
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
			$line = fn( $r ) => '| ' . implode( ' | ', array_pad( $r, $cols, '' ) ) . ' |';
			$out  = $line( $rows[0] ) . "\n|" . str_repeat( ' --- |', $cols ) . "\n";
			foreach ( array_slice( $rows, 1 ) as $r ) {
				$out .= $line( $r ) . "\n";
			}
			return "\n\n" . $out . "\n";
		case 'div':
			// štítek s názvem souboru nad kódem – v Markdownu ho vypíše blok pre
			if ( str_contains( ' ' . $n->getAttribute( 'class' ) . ' ', ' ek-code-file ' ) ) {
				return '';
			}
			return ek_md_children( $n, $depth );
		case 'details':
			$out = '';
			foreach ( $n->childNodes as $c ) {
				$out .= ( $c instanceof DOMElement && 'summary' === strtolower( $c->nodeName ) )
					? "\n\n**" . ek_md_inline( $c ) . "**\n\n"
					: ek_md_node( $c, $depth );
			}
			return $out;
		default:
			// div, section, li mimo seznam, … → jen obsah
			return ek_md_children( $n, $depth );
	}
}

/** Celý článek jako Markdown (hlavička se zdrojem + obsah). */
function ek_post_markdown( WP_Post $post ): string {
	$GLOBALS['post'] = $post;
	setup_postdata( $post );
	$html  = apply_filters( 'the_content', get_the_content( null, false, $post ) );
	$title = html_entity_decode( wp_strip_all_tags( get_the_title( $post ) ), ENT_QUOTES, 'UTF-8' );
	$meta  = array( 'Zdroj: ' . get_permalink( $post ), 'Autor: ' . get_the_author_meta( 'display_name', $post->post_author ), 'Publikováno: ' . get_the_date( 'j. n. Y', $post ) );
	if ( function_exists( 'ek_updated_date' ) && ( $u = ek_updated_date( $post ) ) ) {
		$meta[] = 'Aktualizováno: ' . wp_date( 'j. n. Y', strtotime( $u . ' 12:00:00' ) );
	}
	if ( 'post' === $post->post_type && ( $cat = ek_primary_category( $post ) ) ) {
		$meta[] = 'Rubrika: ' . $cat->name;
	}
	$summary = '';
	if ( function_exists( 'ek_summary_items' ) && ( $items = ek_summary_items( $post ) ) ) {
		$summary = "**Ve zkratce:**\n\n- " . implode( "\n- ", $items ) . "\n\n";
	}
	wp_reset_postdata();
	return '# ' . $title . "\n\n> " . implode( ' · ', $meta ) . "\n\n" . $summary . ek_html_to_md( $html );
}

// /clanek.md → Markdown (WordPress takovou adresu nezná → 404 → tady ji obsloužíme)
add_action( 'template_redirect', function () {
	if ( ! is_404() ) {
		return;
	}
	$path = (string) wp_parse_url( $_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH );
	if ( ! str_ends_with( $path, '.md' ) ) {
		return;
	}
	$id   = url_to_postid( home_url( substr( $path, 0, -3 ) . '/' ) );
	$post = $id ? get_post( $id ) : null;
	if ( ! $post || 'publish' !== $post->post_status || ! in_array( $post->post_type, array( 'post', 'page' ), true ) || post_password_required( $post ) ) {
		return;
	}
	status_header( 200 );
	nocache_headers();
	header( 'Content-Type: text/markdown; charset=utf-8' );
	header( 'X-Robots-Tag: noindex' );
	header( 'Cache-Control: public, max-age=3600' );
	header( 'Link: <' . get_permalink( $post ) . '>; rel="canonical"' );
	echo ek_post_markdown( $post ); // phpcs:ignore WordPress.Security.EscapeOutput -- prostý text
	exit;
}, 1 );

// Odkaz na Markdown verzi v hlavičce článku (pro AI nástroje a crawlery)
add_action( 'wp_head', function () {
	if ( is_singular( array( 'post', 'page' ) ) && ! post_password_required() ) {
		printf( '<link rel="alternate" type="text/markdown" title="Markdown" href="%s">' . "\n", esc_url( ek_md_url() ) );
	}
}, 5 );

/**
 * Tlačítko „Kopírovat pro AI“ + nabídka.
 */
function ek_ai_button(): void {
	$md     = ek_md_url();
	$title  = html_entity_decode( wp_strip_all_tags( get_the_title() ), ENT_QUOTES, 'UTF-8' );
	$prompt = sprintf( 'Přečti si tento návod z webu elvisek.cz: „%s“ – %s. Pak mi s ním pomoz, odpovídej česky.', $title, $md );
	$open   = array(
		'claude'  => array( 'Otevřít v Claude', 'https://claude.ai/new?q=' . rawurlencode( $prompt ) ),
		'chatgpt' => array( 'Otevřít v ChatGPT', 'https://chatgpt.com/?q=' . rawurlencode( $prompt ) ),
	);
	?>
	<div class="ek-ai" data-ek-ai data-md="<?php echo esc_url( $md ); ?>">
		<button type="button" class="ek-ai__copy" data-ek-ai-copy title="Zkopíruje článek jako Markdown – vložte ho do libovolné AI">
			<?php echo ek_icon( 'sparkle', 16 ); ?><span data-ek-ai-label>Kopírovat pro AI</span>
		</button>
		<button type="button" class="ek-ai__more" data-ek-ai-toggle aria-expanded="false" aria-haspopup="true" aria-label="Další možnosti pro AI"><?php echo ek_icon( 'chevron', 16 ); ?></button>
		<div class="ek-ai__menu" data-ek-ai-menu hidden>
			<?php foreach ( $open as [ $label, $href ] ) : ?>
				<a href="<?php echo esc_url( $href ); ?>" target="_blank" rel="noopener noreferrer nofollow"><?php echo esc_html( $label ); ?> ↗</a>
			<?php endforeach; ?>
			<a href="<?php echo esc_url( $md ); ?>" target="_blank" rel="nofollow">Zobrazit jako Markdown</a>
		</div>
	</div>
	<?php
}
