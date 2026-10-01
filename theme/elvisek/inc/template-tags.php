<?php
/**
 * Pomocné funkce pro šablony.
 */

defined( 'ABSPATH' ) || exit;

/**
 * Inline SVG ikona (stroke, barva = currentColor).
 */
function ek_icon( string $name, int $size = 18 ): string {
	static $paths = array(
		'search'   => '<circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/>',
		'moon'     => '<path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8z"/>',
		'sun'      => '<circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/>',
		'menu'     => '<path d="M4 6h16M4 12h16M4 18h16"/>',
		'close'    => '<path d="M6 6l12 12M18 6 6 18"/>',
		'print'    => '<path d="M6 9V3h12v6M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="7"/>',
		'chevron'  => '<path d="m6 9 6 6 6-6"/>',
		'arrow-up' => '<path d="M12 19V5M6 11l6-6 6 6"/>',
		'refresh'  => '<path d="M20 11a8 8 0 0 0-14.6-4.5L4 8M4 4v4h4M4 13a8 8 0 0 0 14.6 4.5L20 16M20 20v-4h-4"/>',
		'arrow'    => '<path d="M5 12h14M13 6l6 6-6 6"/>',
		'arrow-l'  => '<path d="M19 12H5M11 6l-6 6 6 6"/>',
		'copy'     => '<rect x="9" y="9" width="11" height="11" rx="2"/><path d="M5 15V5a2 2 0 0 1 2-2h10"/>',
		'gear'     => '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 0 0 .3 1.8l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.7 1.7 0 0 0-1.8-.3 1.7 1.7 0 0 0-1 1.5V21a2 2 0 1 1-4 0v-.1a1.7 1.7 0 0 0-1.1-1.5 1.7 1.7 0 0 0-1.8.3l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1.7 1.7 0 0 0 .3-1.8 1.7 1.7 0 0 0-1.5-1H3a2 2 0 1 1 0-4h.1a1.7 1.7 0 0 0 1.5-1.1 1.7 1.7 0 0 0-.3-1.8l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1.7 1.7 0 0 0 1.8.3H9a1.7 1.7 0 0 0 1-1.5V3a2 2 0 1 1 4 0v.1a1.7 1.7 0 0 0 1 1.5 1.7 1.7 0 0 0 1.8-.3l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.7 1.7 0 0 0-.3 1.8V9a1.7 1.7 0 0 0 1.5 1H21a2 2 0 1 1 0 4h-.1a1.7 1.7 0 0 0-1.5 1z"/>',
		'expand'   => '<path d="M4 9V5a1 1 0 0 1 1-1h4M15 4h4a1 1 0 0 1 1 1v4M20 15v4a1 1 0 0 1-1 1h-4M9 20H5a1 1 0 0 1-1-1v-4"/>',
		'lock'     => '<rect x="4" y="11" width="16" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/>',
		'rss'      => '<path d="M4 11a9 9 0 0 1 9 9M4 4a16 16 0 0 1 16 16"/><circle cx="5" cy="19" r="1"/>',
		'folder'   => '<path d="M3 6a1 1 0 0 1 1-1h5l2 2h9a1 1 0 0 1 1 1v10a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1z"/>',
		// Ikony kategorií.
		'chip'     => '<path d="M9 3v2M15 3v2M9 19v2M15 19v2M3 9h2M3 15h2M19 9h2M19 15h2"/><rect x="5" y="5" width="14" height="14" rx="1"/><path d="M9 9h6v6H9z"/>',
		'laptop'   => '<path d="M4 5h16a1 1 0 0 1 1 1v10H3V6a1 1 0 0 1 1-1zM1 19h22"/>',
		'terminal' => '<path d="m4 17 6-5-6-5M12 19h8"/>',
		'phone'    => '<rect x="6" y="2" width="12" height="20" rx="2"/><path d="M11 18h2"/>',
		'home'     => '<path d="m3 11 9-7 9 7M5 10v10h14V10M10 20v-6h4v6"/>',
		'smarthome' => '<path d="M3 10.5 12 3l9 7.5V20a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1z"/><circle cx="12" cy="14.5" r="1.8"/><path d="M12 12.7V10M10.4 15.4l-2.2 1.3M13.6 15.4l2.2 1.3"/>',
		'sparkle'   => '<path d="M12 3.5l1.9 5.1 5.1 1.9-5.1 1.9L12 17.5l-1.9-5.1L5 10.5l5.1-1.9z"/><path d="M18.5 15.5l.8 2 2 .8-2 .8-.8 2-.8-2-2-.8 2-.8z"/>',
		'server'   => '<rect x="3" y="4" width="18" height="7" rx="1"/><rect x="3" y="13" width="18" height="7" rx="1"/><path d="M7 7.5h.01M7 16.5h.01"/>',
		'globe'    => '<circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3a14 14 0 0 1 0 18M12 3a14 14 0 0 0 0 18"/>',
		'wifi'     => '<path d="M12 20h.01M8.5 16.5a5 5 0 0 1 7 0M5 13a10 10 0 0 1 14 0M2 9.5a15 15 0 0 1 20 0"/>',
		'car'      => '<path d="M5 17h14M5 17a2 2 0 1 1-4 0v-4l2-5h18l2 5v4a2 2 0 1 1-4 0"/><circle cx="7" cy="17" r="2"/><circle cx="17" cy="17" r="2"/>',
		'bag'      => '<path d="M6 7h12l1 13H5zM9 7a3 3 0 0 1 6 0"/>',
	);
	$inner = $paths[ $name ] ?? $paths['folder'];
	return sprintf(
		'<svg class="ek-icon" width="%1$d" height="%1$d" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">%2$s</svg>',
		$size,
		$inner
	);
}

/**
 * Ikona podle slugu kategorie.
 */
function ek_category_icon_name( string $slug ): string {
	$map = array(
		'raspberry-pi' => 'chip',
		'osx'          => 'laptop',
		'debian'       => 'terminal',
		'ios'          => 'phone',
		'loxone'       => 'home',
		'synology'     => 'server',
		'webportfolio' => 'globe',
		'zigbee'       => 'wifi',
		'car'          => 'car',
		'aliexpress'   => 'bag',
		'homeassistant' => 'smarthome',
		'ai-tools'     => 'sparkle',
	);
	return $map[ $slug ] ?? 'folder';
}

/**
 * Hlavní kategorie článku (mimo „Nezařazené“).
 */
function ek_primary_category( $post = null ): ?WP_Term {
	$cats = get_the_category( $post ? get_post( $post )->ID : 0 );
	foreach ( $cats as $cat ) {
		if ( (int) $cat->term_id !== (int) get_option( 'default_category' ) ) {
			return $cat;
		}
	}
	return $cats[0] ?? null;
}

/**
 * Doba čtení v minutách (200 slov/min, obrázek ~ 10 s).
 */
function ek_reading_time( $post = null ): int {
	$post    = get_post( $post );
	$content = (string) $post->post_content;
	$words   = str_word_count( wp_strip_all_tags( strip_shortcodes( $content ) ), 0, 'áčďéěíňóřšťúůýžÁČĎÉĚÍŇÓŘŠŤÚŮÝŽ' );
	$images  = substr_count( $content, '<img' );
	return max( 1, (int) round( ( $words / 200 ) + ( $images * 10 / 60 ) ) );
}

/**
 * Štítek kategorie.
 */
function ek_category_chip( $post = null, string $class = 'ek-chip' ): string {
	$cat = ek_primary_category( $post );
	if ( ! $cat ) {
		return '';
	}
	return sprintf( '<a class="%s" href="%s">%s</a>', esc_attr( $class ), esc_url( get_category_link( $cat ) ), esc_html( $cat->name ) );
}

/**
 * Štítky všech rubrik článku (bez „Nezařazené“). Na stránce rubriky je ta aktuální první.
 */
function ek_category_chips( $post = null, string $class = 'ek-chip' ): string {
	$default = (int) get_option( 'default_category' );
	$cats    = array_filter( get_the_category( $post ? get_post( $post )->ID : 0 ), fn( $c ) => (int) $c->term_id !== $default );
	if ( ! $cats ) {
		return ek_category_chip( $post, $class );
	}
	if ( is_category() ) {
		$current = get_queried_object_id();
		usort( $cats, fn( $a, $b ) => ( (int) $b->term_id === $current ) <=> ( (int) $a->term_id === $current ) );
	}
	$out = '';
	foreach ( $cats as $cat ) {
		$out .= sprintf( '<a class="%s" href="%s">%s</a>', esc_attr( $class ), esc_url( get_category_link( $cat ) ), esc_html( $cat->name ) );
	}
	return '<span class="ek-chips">' . $out . '</span>';
}

/**
 * Náhled článku — obrázek nebo zástupná plocha s ikonou kategorie.
 */
function ek_thumbnail( string $size = 'ek-card', array $attr = array() ): void {
	if ( has_post_thumbnail() ) {
		$class = 'ek-thumb__img' . ( ek_thumb_is_logo( get_post_thumbnail_id() ) ? ' ek-thumb__img--contain' : '' );
		the_post_thumbnail( $size, array_merge( array( 'alt' => trim( html_entity_decode( wp_strip_all_tags( get_the_title() ), ENT_QUOTES, 'UTF-8' ) ) ), $attr, array( 'class' => $class ) ) );
		return;
	}
	$cat = ek_primary_category();
	echo '<span class="ek-thumb__placeholder">' . ek_icon( ek_category_icon_name( $cat ? $cat->slug : '' ), 40 ) . '</span>';
}

/**
 * Je obrázek spíš logo/ikona (malý nebo čtvercový)? Pak se nezvětšuje ani neořezává.
 */
function ek_thumb_is_logo( int $attachment_id ): bool {
	$meta = wp_get_attachment_metadata( $attachment_id );
	$w    = (int) ( $meta['width'] ?? 0 );
	$h    = (int) ( $meta['height'] ?? 0 );
	if ( ! $w || ! $h ) {
		return false;
	}
	return $w < 700 || abs( ( $w / $h ) - 1 ) < 0.2 || str_ends_with( strtolower( (string) ( $meta['file'] ?? '' ) ), '.png' ) && $w < 1000;
}

/**
 * Datum pro karty (15. 11. 2023) a pro článek (15. listopadu 2023).
 */
function ek_date( string $format = 'short' ): string {
	$fmt = 'short' === $format ? 'j. n. Y' : 'j. F Y';
	return sprintf( '<time datetime="%s">%s</time>', esc_attr( get_the_date( 'c' ) ), esc_html( get_the_date( $fmt ) ) );
}

/**
 * Témata na titulce — nejpočetnější kategorie.
 */
function ek_topics( int $limit = 8 ): array {
	// Ruční výběr a pořadí z Přizpůsobit → Úvodní stránka → Témata v hlavičce (prázdné = automaticky podle počtu článků).
	$ids = array_filter( array_map( 'absint', explode( ',', (string) get_theme_mod( 'ek_topics', '' ) ) ) );
	if ( $ids ) {
		$terms = get_categories( array( 'include' => $ids, 'orderby' => 'include', 'hide_empty' => true ) );
		if ( $terms ) {
			return $terms;
		}
	}
	return get_categories( array(
		'orderby'    => 'count',
		'order'      => 'DESC',
		'hide_empty' => true,
		'exclude'    => array( (int) get_option( 'default_category' ) ),
		'number'     => $limit,
	) );
}

/**
 * Tlačítko „Načíst další“ (funguje i bez JS jako odkaz na další stránku).
 */
function ek_load_more( string $label = 'Načíst další články' ): void {
	global $wp_query;
	$paged = max( 1, (int) get_query_var( 'paged' ) );
	if ( $paged >= (int) $wp_query->max_num_pages ) {
		return;
	}
	printf(
		'<div class="ek-more"><a class="ek-btn ek-btn--ghost" data-ek-more href="%s">%s</a></div>',
		esc_url( get_pagenum_link( $paged + 1 ) ),
		esc_html( $label )
	);
}

/**
 * Drobečková navigace pro článek.
 */
function ek_breadcrumbs(): void {
	$items = array( sprintf( '<a href="%s">Úvod</a>', esc_url( home_url( '/' ) ) ) );
	if ( is_single() ) {
		$cat = ek_primary_category();
		if ( $cat ) {
			$items[] = sprintf( '<a href="%s">%s</a>', esc_url( get_category_link( $cat ) ), esc_html( $cat->name ) );
		}
	}
	echo '<nav class="ek-crumbs" aria-label="Drobečková navigace">' . implode( '<span aria-hidden="true">›</span>', $items ) . '</nav>';
}

/**
 * Související články ze stejné kategorie.
 */
function ek_related_posts( int $count = 3 ): array {
	$cat = ek_primary_category();
	if ( ! $cat ) {
		return array();
	}
	return get_posts( array(
		'category__in'        => array( $cat->term_id ),
		'post__not_in'        => array( get_the_ID() ),
		'posts_per_page'      => $count,
		'ignore_sticky_posts' => true,
		'no_found_rows'       => true,
	) );
}

/**
 * Rozsah let pro copyright.
 */
function ek_copyright_years(): string {
	$since = (int) ek_opt( 'ek_since' );
	$now   = (int) wp_date( 'Y' );
	return ( $since && $since < $now ) ? $since . '–' . $now : (string) $now;
}

/**
 * Logo: monogram EK s kurzorem + „ElvisEK“ (EK v akcentu). $compact = jen monogram.
 */
function ek_logo( bool $compact = false ): void {
	$name = get_bloginfo( 'name' );
	printf( '<a class="ek-logo%s" href="%s" aria-label="%s – úvod">', $compact ? ' ek-logo--compact' : '', esc_url( home_url( '/' ) ), esc_attr( $name ) );
	if ( has_custom_logo() ) {
		echo wp_get_attachment_image( get_theme_mod( 'custom_logo' ), 'thumbnail', false, array( 'class' => 'ek-logo__img', 'alt' => '' ) );
	} else {
		echo '<span class="ek-logo__mark" aria-hidden="true">EK<span class="ek-logo__cursor"></span></span>';
	}
	// „ElvisEK“ → Elvis + EK v akcentu (když název webu končí na EK). V kompaktní liště menší, na mobilu skrytý (CSS).
	$html = str_ends_with( $name, 'EK' ) ? esc_html( substr( $name, 0, -2 ) ) . '<span>EK</span>' : esc_html( $name );
	echo '<span class="ek-logo__name">' . $html . '</span>';
	echo '</a>';
}

/**
 * Tlačítka hledání a přepnutí režimu.
 */
function ek_header_tools( string $search_id ): void {
	?>
	<div class="ek-tools">
		<div class="ek-qs" data-ek-qs>
			<form role="search" method="get" class="ek-qs__form" id="<?php echo esc_attr( $search_id ); ?>" action="<?php echo esc_url( home_url( '/' ) ); ?>">
				<label class="screen-reader-text" for="<?php echo esc_attr( $search_id ); ?>-input">Hledat</label>
				<input id="<?php echo esc_attr( $search_id ); ?>-input" class="ek-qs__input" type="search" name="s" value="<?php echo esc_attr( get_search_query() ); ?>" placeholder="Hledat návody…" autocomplete="off" tabindex="-1">
			</form>
			<button type="button" class="ek-iconbtn" data-ek-search-toggle aria-expanded="false" aria-controls="<?php echo esc_attr( $search_id ); ?>" aria-label="Hledat">
				<?php echo ek_icon( 'search' ); ?>
			</button>
		</div>
	</div>
	<?php
}

/**
 * Témata v navigaci (kategorie podle počtu článků).
 */
function ek_nav_topics(): array {
	static $topics = null;
	if ( null === $topics ) {
		$topics = ek_topics( (int) ek_opt( 'ek_topics_count' ) ?: 8 );
	}
	return $topics;
}

/**
 * Vyvážený počet sloupců dlaždic: nejméně řádků při daném maximu, řádky co nejplnější.
 * (10 → 10 | 5 | 5, 12 → 6 | 6 | 4, 8 → 8 | 4 | 4 pro max 10 / 6 / 5)
 */
function ek_tile_cols( int $n, int $max ): int {
	if ( $n < 1 ) {
		return 1;
	}
	$rows = (int) ceil( $n / $max );
	return (int) ceil( $n / $rows );
}

/**
 * Inline CSS proměnné pro mřížku dlaždic (desktop / tablet / mobil).
 */
function ek_tiles_style( int $n ): string {
	// Málo témat: dlaždice si drží běžnou šířku (mřížka má aspoň 8 / 4 / 4 sloupce) a řadí se zleva, neroztahují se přes celou šířku.
	$d = $n < 8 ? 8 : ek_tile_cols( $n, 10 );
	$t = $n < 4 ? 4 : ek_tile_cols( $n, 6 );
	$m = $n < 4 ? 4 : ek_tile_cols( $n, 5 );
	return sprintf( '--ek-cols-d:%d;--ek-cols-t:%d;--ek-cols-m:%d', $d, $t, $m );
}

/**
 * Je téma aktuální (archiv rubriky nebo článek v ní)?
 */
function ek_is_current_topic( WP_Term $cat ): bool {
	if ( is_category( $cat->term_id ) ) {
		return true;
	}
	return is_single() && in_category( $cat->term_id, get_queried_object_id() );
}

/**
 * České skloňování podle čísla: 1 článek, 2–4 články, 5+ článků.
 */
function ek_plural( int $n, string $one, string $few, string $many ): string {
	$form = 1 === $n ? $one : ( ( $n >= 2 && $n <= 4 ) ? $few : $many );
	return $n . ' ' . $form;
}
