<?php
/**
 * EKT module: seo
 * Version: 1.0.1
 * Requires: common
 * Replaces: wordpress-seo, seo-by-rank-math, wp-seopress, all-in-one-seo-pack, autodescription
 * Assets: –
 * Summary: Meta description, Open Graph, Twitter karta, JSON-LD, canonical všude a noindex (hledání, datové archivy, offline, feedy); mlčí, dokud běží SEO plugin.
 */

defined( 'ABSPATH' ) || exit;

/*
 * Pravidla: references/seo-indexing.md („jedna stránka = jeden signál“).
 *  - canonical: článek a stránka (core rel_canonical), titulka, stránka příspěvků, rubriky, štítky,
 *    taxonomie, archivy typů obsahu, autoři a stránkování (/page/2/ na sebe) – doplňuje modul;
 *  - noindex, follow: hledání, datové archivy, offline stránka PWA (bez canonical);
 *  - feedy: hlavička X-Robots-Tag: noindex, follow;
 *  - JSON-LD: @graph (WebSite + SearchAction na titulce, BlogPosting u článku, vydavatel),
 *    vždy s JSON_HEX_TAG|AMP|APOS|QUOT – text „</script>“ z titulku nemůže ukončit skript.
 * Dokud běží Yoast, Rank Math, SEOPress, AIOSEO nebo The SEO Framework, modul nedělá nic
 * (ekt_legacy_active( 'seo' )).
 *
 * Veřejné funkce: ekt_seo_active(), ekt_seo_data(), ekt_seo_canonical(), ekt_seo_noindex(),
 * ekt_seo_publisher(), ekt_seo_json_ld().
 * Filtry: ekt_seo_data( $data ), ekt_seo_json_ld( $graph ), ekt_seo_canonical( $url ),
 * ekt_seo_noindex( $noindex ), ekt_seo_publisher( $entity ), ekt_seo_default_image( $image ),
 * ekt_seo_description_length (160).
 * Přizpůsobit: ekt_seo → meta_desc (popis titulky a výpisů bez vlastního popisu).
 * Záložní obrázek pro sdílení: assets/img/og-image.png šablony (1200 × 630), jinak ikona webu,
 * jinak logo; jiný obrázek filtrem ekt_seo_default_image.
 */

ekt_setting_register(
	'meta_desc',
	array(
		'type'        => 'textarea',
		'label'       => 'Popis webu pro vyhledávače',
		'description' => 'Meta description titulky a výpisů bez vlastního popisu. Prázdné = popis webu z Nastavení → Obecné.',
		'section'     => 'seo',
		'priority'    => 20,
	)
);

/**
 * Vypisuje modul SEO značky? Ne, dokud běží SEO plugin (filtr ekt_legacy_active).
 *
 * @return bool
 */
function ekt_seo_active(): bool {
	return ! ekt_legacy_active( 'seo' );
}

/**
 * Stručný výpis i u stránek – slouží jako meta description.
 */
function ekt_seo_page_excerpt(): void {
	add_post_type_support( 'page', 'excerpt' );
}
add_action( 'init', 'ekt_seo_page_excerpt' );

/**
 * Text bez HTML, bloků a shortcodů, zkrácený na celé slovo.
 *
 * @param string $text   Text nebo HTML.
 * @param int    $length Maximální délka ve znacích (0 = výchozí z filtru ekt_seo_description_length).
 * @return string
 */
function ekt_seo_clean( string $text, int $length = 0 ): string {
	$length = $length > 0 ? $length : (int) apply_filters( 'ekt_seo_description_length', 160 );
	$text   = strip_shortcodes( excerpt_remove_blocks( $text ) );
	// Mezera u blokových značek a zalomení, ať se odrážky a řádky po odstranění HTML neslepí.
	$text   = (string) preg_replace( '#<(?:br|/?(?:p|li|ul|ol|h[1-6]|div|td|th|tr|dt|dd|figcaption|blockquote|section|table))\b[^>]*>#i', ' $0 ', $text );
	$text   = wp_strip_all_tags( $text );
	$text   = trim( (string) preg_replace( '/\s+/u', ' ', html_entity_decode( $text, ENT_QUOTES | ENT_HTML5, 'UTF-8' ) ) );
	if ( mb_strlen( $text ) > $length ) {
		$text = rtrim( (string) preg_replace( '/\s+\S*$/u', '', mb_substr( $text, 0, $length - 1 ) ), ' ,.;:–-' ) . '…';
	}
	return $text;
}

/**
 * Locale aktuálního jazyka (cs_CZ, en_GB…), s Polylangem podle jazyka stránky.
 *
 * @return string
 */
function ekt_seo_locale(): string {
	$locale = function_exists( 'pll_current_language' ) ? (string) pll_current_language( 'locale' ) : '';
	return '' !== $locale ? $locale : get_locale();
}

/**
 * Canonical adresa aktuální stránky, kterou doplňuje modul ('' = žádná: články a stránky řeší
 * core rel_canonical, hledání, datové archivy, offline a 404 canonical nemají – noindex).
 *
 * @return string
 */
function ekt_seo_canonical(): string {
	if ( is_singular() || is_404() || is_search() || is_date() || ekt_seo_noindex() ) {
		return '';
	}
	$url = '';
	if ( is_front_page() ) {
		$url = home_url( '/' );
	} elseif ( is_home() ) {
		$page = (int) get_option( 'page_for_posts' );
		$url  = $page ? (string) get_permalink( $page ) : home_url( '/' );
	} elseif ( is_category() || is_tag() || is_tax() ) {
		$term = get_queried_object();
		$link = $term instanceof WP_Term ? get_term_link( $term ) : '';
		$url  = is_string( $link ) ? $link : '';
	} elseif ( is_post_type_archive() ) {
		$type = get_query_var( 'post_type' );
		$link = get_post_type_archive_link( is_array( $type ) ? (string) reset( $type ) : (string) $type );
		$url  = $link ? $link : '';
	} elseif ( is_author() ) {
		$url = get_author_posts_url( get_queried_object_id() );
	}
	$paged = max( 1, (int) get_query_var( 'paged' ) );
	if ( '' !== $url && $paged > 1 ) {
		global $wp_rewrite;
		$url = $wp_rewrite instanceof WP_Rewrite && $wp_rewrite->using_permalinks()
			? trailingslashit( $url ) . user_trailingslashit( $wp_rewrite->pagination_base . '/' . $paged, 'paged' )
			: add_query_arg( 'paged', $paged, $url );
	}
	return (string) apply_filters( 'ekt_seo_canonical', $url );
}

/**
 * Nemá být aktuální stránka v indexu? Hledání, datové archivy, offline stránka PWA
 * (modul pwa: ekt_pwa_is_offline()). Filtr ekt_seo_noindex.
 *
 * @return bool
 */
function ekt_seo_noindex(): bool {
	$noindex = is_search() || is_date() || ( function_exists( 'ekt_pwa_is_offline' ) && ekt_pwa_is_offline() );
	return (bool) apply_filters( 'ekt_seo_noindex', $noindex );
}

/**
 * Záložní obrázek pro sdílení (bez náhledového obrázku článku).
 *
 * @return array{url:string,width:int,height:int} url '' = žádný.
 */
function ekt_seo_default_image(): array {
	$image = array(
		'url'    => '',
		'width'  => 0,
		'height' => 0,
	);
	if ( is_file( EKT_DIR . '/assets/img/og-image.png' ) ) {
		$size  = wp_getimagesize( EKT_DIR . '/assets/img/og-image.png' );
		$image = array(
			'url'    => EKT_URI . '/assets/img/og-image.png',
			'width'  => $size ? (int) $size[0] : 1200,
			'height' => $size ? (int) $size[1] : 630,
		);
	} else {
		$id  = (int) get_option( 'site_icon' );
		$id  = $id ? $id : (int) get_theme_mod( 'custom_logo' );
		$src = $id ? ekt_seo_image( $id, 'full' ) : array();
		if ( $src ) {
			$image = $src;
		}
	}
	$image = (array) apply_filters( 'ekt_seo_default_image', $image );
	return array(
		'url'    => (string) ( $image['url'] ?? '' ),
		'width'  => (int) ( $image['width'] ?? 0 ),
		'height' => (int) ( $image['height'] ?? 0 ),
	);
}

/**
 * Adresa a skutečné rozměry obrázku přílohy (wp_get_attachment_image_src zmenší rozměry
 * velikosti large podle $content_width, adresa ale vede na větší soubor).
 *
 * @param int    $id   ID přílohy.
 * @param string $size Velikost.
 * @return array{url:string,width:int,height:int}|array{}
 */
function ekt_seo_image( int $id, string $size = 'large' ): array {
	$meta = wp_get_attachment_metadata( $id );
	$part = image_get_intermediate_size( $id, $size );
	if ( is_array( $part ) && ! empty( $part['url'] ) ) {
		return array(
			'url'    => (string) $part['url'],
			'width'  => (int) ( $part['width'] ?? 0 ),
			'height' => (int) ( $part['height'] ?? 0 ),
		);
	}
	$url = wp_get_attachment_url( $id );
	if ( ! $url ) {
		return array();
	}
	return array(
		'url'    => (string) $url,
		'width'  => is_array( $meta ) ? (int) ( $meta['width'] ?? 0 ) : 0,
		'height' => is_array( $meta ) ? (int) ( $meta['height'] ?? 0 ) : 0,
	);
}

/**
 * Datum poslední úpravy článku pro meta a JSON-LD: ruční datum Aktualizováno (modul updated),
 * bez modulu updated datum úpravy v databázi.
 *
 * @param WP_Post $post Článek.
 * @return string DATE_W3C.
 */
function ekt_seo_modified( WP_Post $post ): string {
	$updated = ekt_post_setting( 'updated', $post );
	if ( is_string( $updated ) && '' !== $updated ) {
		$date = date_create_immutable( $updated . ' 12:00:00', wp_timezone() );
		if ( $date ) {
			return $date->format( DATE_W3C );
		}
	}
	if ( function_exists( 'ekt_updated_badge' ) ) {
		return (string) get_the_date( DATE_W3C, $post ); // ruční datum nevyplněné = beze změny obsahu
	}
	return (string) get_the_modified_date( DATE_W3C, $post );
}

/**
 * Data pro meta značky aktuální stránky.
 *
 * @return array{title:string,description:string,url:string,type:string,image:array,has_image:bool,locale:string,alternate:string[],published:string,modified:string}
 */
function ekt_seo_data(): array {
	$object = get_queried_object();
	$title  = html_entity_decode( wp_get_document_title(), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
	$text   = '';
	$url    = ekt_seo_canonical();
	$type   = 'website';
	$image  = array();

	if ( is_singular() && $object instanceof WP_Post ) {
		$title = ekt_plain_title( $object );
		$text  = has_excerpt( $object ) ? $object->post_excerpt : $object->post_content;
		$url   = (string) wp_get_canonical_url( $object );
		$type  = 'post' === $object->post_type ? 'article' : 'website';
		if ( has_post_thumbnail( $object ) ) {
			$image = ekt_seo_image( (int) get_post_thumbnail_id( $object ), 'large' );
		}
	} elseif ( is_front_page() ) {
		$title = html_entity_decode( get_bloginfo( 'name' ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
	} elseif ( is_category() || is_tag() || is_tax() ) {
		$text = (string) term_description();
	} elseif ( is_post_type_archive() ) {
		$text = (string) get_the_post_type_description();
	} elseif ( is_author() ) {
		$text = (string) get_the_author_meta( 'description', get_queried_object_id() );
	}
	if ( '' === ekt_seo_clean( $text ) ) {
		$text = (string) ekt_opt( 'meta_desc' );
	}
	if ( '' === ekt_seo_clean( $text ) ) {
		$text = (string) get_bloginfo( 'description' );
	}
	if ( '' === $url ) {
		$url = home_url( '/' . ltrim( user_trailingslashit( (string) ( $GLOBALS['wp']->request ?? '' ) ), '/' ) );
	}

	$has_image = (bool) $image;
	if ( ! $image ) {
		$image     = ekt_seo_default_image();
		$has_image = '' !== $image['url'];
	}

	$locale    = ekt_seo_locale();
	$alternate = function_exists( 'pll_languages_list' ) ? array_diff( (array) pll_languages_list( array( 'fields' => 'locale' ) ), array( $locale ) ) : array();

	$data = array(
		'title'       => $title,
		'description' => ekt_seo_clean( $text ),
		'url'         => $url,
		'type'        => $type,
		'image'       => $image,
		'has_image'   => $has_image,
		'locale'      => $locale,
		'alternate'   => array_values( array_map( 'strval', $alternate ) ),
		'published'   => 'article' === $type ? (string) get_the_date( DATE_W3C, $object ) : '',
		'modified'    => 'article' === $type ? ekt_seo_modified( $object ) : '',
	);
	return (array) apply_filters( 'ekt_seo_data', $data );
}

/**
 * Canonical pro titulku, archivy a stránkování (články a stránky vypisuje core).
 */
function ekt_seo_canonical_tag(): void {
	if ( ! ekt_seo_active() ) {
		return;
	}
	$url = ekt_seo_canonical();
	if ( '' !== $url ) {
		printf( '<link rel="canonical" href="%s">' . "\n", esc_url( $url ) );
	}
}
add_action( 'wp_head', 'ekt_seo_canonical_tag', 1 );

/**
 * Meta description, Open Graph a Twitter karta.
 */
function ekt_seo_meta(): void {
	if ( ! ekt_seo_active() || is_404() || ekt_seo_noindex() ) {
		return;
	}
	$d    = ekt_seo_data();
	$meta = array();
	if ( '' !== $d['description'] ) {
		$meta[] = array( 'name', 'description', $d['description'] );
	}
	$meta[] = array( 'property', 'og:type', $d['type'] );
	$meta[] = array( 'property', 'og:site_name', html_entity_decode( get_bloginfo( 'name' ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );
	$meta[] = array( 'property', 'og:title', $d['title'] );
	if ( '' !== $d['description'] ) {
		$meta[] = array( 'property', 'og:description', $d['description'] );
	}
	$meta[] = array( 'property', 'og:url', $d['url'] );
	$meta[] = array( 'property', 'og:locale', $d['locale'] );
	foreach ( $d['alternate'] as $alternate ) {
		$meta[] = array( 'property', 'og:locale:alternate', $alternate );
	}
	if ( $d['has_image'] ) {
		$meta[] = array( 'property', 'og:image', $d['image']['url'] );
		if ( $d['image']['width'] && $d['image']['height'] ) {
			$meta[] = array( 'property', 'og:image:width', $d['image']['width'] );
			$meta[] = array( 'property', 'og:image:height', $d['image']['height'] );
		}
	}
	if ( 'article' === $d['type'] ) {
		$meta[] = array( 'property', 'article:published_time', $d['published'] );
		$meta[] = array( 'property', 'article:modified_time', $d['modified'] );
	}
	// Titulek, popis a obrázek si X vezme z Open Graph; velká karta jen s obrázkem.
	$meta[] = array( 'name', 'twitter:card', $d['has_image'] ? 'summary_large_image' : 'summary' );

	foreach ( $meta as $tag ) {
		if ( '' === (string) $tag[2] ) {
			continue;
		}
		printf( '<meta %s="%s" content="%s">' . "\n", 'name' === $tag[0] ? 'name' : 'property', esc_attr( $tag[1] ), esc_attr( (string) $tag[2] ) );
	}
}
add_action( 'wp_head', 'ekt_seo_meta', 3 );

/**
 * Vydavatel webu pro JSON-LD (Organization s logem, nebo Person). Filtr ekt_seo_publisher:
 * add_filter( 'ekt_seo_publisher', fn( $e ) => array_merge( $e, array( '@type' => 'Person', 'name' => 'Jméno' ) ) );
 *
 * @return array
 */
function ekt_seo_publisher(): array {
	$root   = trailingslashit( (string) get_option( 'home' ) ); // kořen webu – jeden vydavatel pro všechny jazyky
	$entity = array(
		'@type' => 'Organization',
		'@id'   => $root . '#publisher',
		'name'  => html_entity_decode( get_bloginfo( 'name' ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ),
		'url'   => $root,
	);
	$logo   = (int) get_theme_mod( 'custom_logo' );
	$src    = $logo ? ekt_seo_image( $logo, 'full' ) : array();
	if ( $src ) {
		$entity['logo'] = array_merge( array( '@type' => 'ImageObject' ), $src );
	}
	$entity = (array) apply_filters( 'ekt_seo_publisher', $entity );
	if ( 'Person' === ( $entity['@type'] ?? '' ) && isset( $entity['logo'] ) ) {
		$entity['image'] = $entity['logo']; // Person nemá logo
		unset( $entity['logo'] );
	}
	return $entity;
}

/**
 * Graf strukturovaných dat aktuální stránky (před filtrem výstupu).
 *
 * @return array
 */
function ekt_seo_json_ld(): array {
	$home      = home_url( '/' );
	$language  = str_replace( '_', '-', ekt_seo_locale() );
	$publisher = ekt_seo_publisher();
	$graph     = array();

	if ( is_front_page() ) {
		$graph[] = $publisher;
		$graph[] = array(
			'@type'           => 'WebSite',
			'@id'             => $home . '#website',
			'url'             => $home,
			'name'            => html_entity_decode( get_bloginfo( 'name' ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ),
			'inLanguage'      => $language,
			'publisher'       => array( '@id' => $publisher['@id'] ?? '' ),
			'potentialAction' => array(
				'@type'       => 'SearchAction',
				'target'      => array(
					'@type'       => 'EntryPoint',
					'urlTemplate' => add_query_arg( 's', '{search_term_string}', $home ),
				),
				'query-input' => 'required name=search_term_string',
			),
		);
	} elseif ( is_singular( 'post' ) ) {
		$d       = ekt_seo_data();
		$post    = get_queried_object();
		$author  = $post instanceof WP_Post ? (int) $post->post_author : 0;
		$name    = $author ? (string) get_the_author_meta( 'display_name', $author ) : '';
		$article = array(
			'@type'            => 'BlogPosting',
			'headline'         => $d['title'],
			'description'      => $d['description'],
			'url'              => $d['url'],
			'mainEntityOfPage' => $d['url'],
			'datePublished'    => $d['published'],
			'dateModified'     => $d['modified'],
			'inLanguage'       => $language,
			'author'           => '' !== $name ? array(
				'@type' => 'Person',
				'name'  => $name,
				'url'   => (string) ( get_the_author_meta( 'user_url', $author ) ? get_the_author_meta( 'user_url', $author ) : $home ),
			) : array( '@id' => $publisher['@id'] ?? '' ), // článek bez autora = vydavatel
			'publisher'        => $publisher,
		);
		if ( $d['has_image'] ) {
			$article['image'] = $d['image']['url'];
		}
		$graph[] = $article;
	}
	return $graph;
}

/**
 * Výpis JSON-LD. JSON_HEX_* zakóduje < > & ' " jako <…, takže text z obsahu
 * („</script>“ v titulku) nemůže ukončit blok skriptu.
 */
function ekt_seo_json_ld_tag(): void {
	if ( ! ekt_seo_active() || is_404() || ekt_seo_noindex() ) {
		return;
	}
	$graph = (array) apply_filters( 'ekt_seo_json_ld', ekt_seo_json_ld() );
	if ( ! $graph ) {
		return;
	}
	$json = wp_json_encode(
		array(
			'@context' => 'https://schema.org',
			'@graph'   => array_values( $graph ),
		),
		JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
	);
	if ( $json ) {
		echo '<script type="application/ld+json">' . $json . "</script>\n"; // phpcs:ignore WordPress.Security.EscapeOutput -- JSON s JSON_HEX_*
	}
}
add_action( 'wp_head', 'ekt_seo_json_ld_tag', 4 );

/**
 * Robots: noindex, follow pro hledání, datové archivy a offline stránku (jedno místo, filtr wp_robots).
 *
 * @param array $robots Direktivy.
 * @return array
 */
function ekt_seo_robots( array $robots ): array {
	if ( ekt_seo_active() && ekt_seo_noindex() ) {
		$robots['noindex'] = true;
		$robots['follow']  = true;
		unset( $robots['index'], $robots['nofollow'] );
	}
	return $robots;
}
add_filter( 'wp_robots', 'ekt_seo_robots' );

/**
 * Feedy: XML není stránka, meta tag do něj dát nejde → hlavička X-Robots-Tag.
 */
function ekt_seo_feed_header(): void {
	if ( ekt_seo_active() && is_feed() && ! headers_sent() ) {
		header( 'X-Robots-Tag: noindex, follow', true );
	}
}
add_action( 'template_redirect', 'ekt_seo_feed_header', 1 );
