<?php
/**
 * Pomocné funkce pro šablony.
 */

defined( 'ABSPATH' ) || exit;

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
	$cat = ekt_primary_category( $post );
	if ( ! $cat ) {
		return '';
	}
	return sprintf( '<a class="%s" href="%s"%s>%s</a>', esc_attr( $class . ' ek-chip--cat' ), esc_url( get_category_link( $cat ) ), ekt_term_color_style( $cat ), esc_html( $cat->name ) );
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
		$out .= sprintf( '<a class="%s" href="%s"%s>%s</a>', esc_attr( $class . ' ek-chip--cat' ), esc_url( get_category_link( $cat ) ), ekt_term_color_style( $cat ), esc_html( $cat->name ) );
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
	$cat = ekt_primary_category();
	echo '<span class="ek-thumb__placeholder">' . ekt_icon( ek_category_icon_name( $cat ? $cat->slug : '' ), 40 ) . '</span>';
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
	$ids = array_filter( array_map( 'absint', explode( ',', (string) ekt_opt( 'site_topics' ) ) ) );
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
 * Drobečková navigace pro článek.
 */
function ek_breadcrumbs(): void {
	$items = array( sprintf( '<a href="%s">Úvod</a>', esc_url( home_url( '/' ) ) ) );
	if ( is_single() ) {
		$cat = ekt_primary_category();
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
	$cat = ekt_primary_category();
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
		<div class="ek-qs">
			<form role="search" method="get" class="ek-qs__form" data-ekt-qs id="<?php echo esc_attr( $search_id ); ?>" action="<?php echo esc_url( home_url( '/' ) ); ?>">
				<label class="screen-reader-text" for="<?php echo esc_attr( $search_id ); ?>-input">Hledat</label>
				<input id="<?php echo esc_attr( $search_id ); ?>-input" class="ek-qs__input" type="search" name="s" value="<?php echo esc_attr( get_search_query() ); ?>" placeholder="Hledat návody…" autocomplete="off" tabindex="-1">
			</form>
			<button type="button" class="ek-iconbtn" data-ek-search-toggle aria-expanded="false" aria-controls="<?php echo esc_attr( $search_id ); ?>" aria-label="Hledat">
				<?php echo ekt_icon( 'search', 18 ); ?>
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
		$topics = ek_topics( (int) ekt_opt( 'site_topics_count' ) ?: 8 );
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
