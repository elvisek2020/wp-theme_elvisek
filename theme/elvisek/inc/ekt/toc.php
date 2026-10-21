<?php
/**
 * EKT module: toc
 * Version: 1.0.0
 * Requires: common
 * Replaces: easy-table-of-contents, luckywp-table-of-contents, table-of-contents-plus
 * Assets: js/toc.js
 * Summary: Obsah článku z nadpisů H2/H3 a rozbalovacích sekcí: kotvy v obsahu, seznam se zvýrazněním aktuální sekce.
 */

defined( 'ABSPATH' ) || exit;

/*
 * Kotvy: filtr the_content (priorita 20) doplní id nadpisům H2, H3 a rozbalovacím sekcím
 * <details> nejvyšší úrovně (text ze <summary>) přes WP_HTML_Tag_Processor; existující id zůstává.
 * Seznam: ekt_toc_items() ho spočítá stejným algoritmem z obsahu článku (bloky + typografie)
 * nezávisle na tom, jestli už the_content proběhl – šablona může volat ekt_the_toc() před obsahem
 * i po něm, klidně dvakrát (boční panel + vložené nad text).
 *
 * Veřejné funkce: ekt_toc_enabled( $post ), ekt_toc_items( $post ), ekt_the_toc( $post ),
 * ekt_toc_html( $post ), ekt_toc_scan( $html ).
 * Přizpůsobit: ekt_posts → toc (výchozí zapnuto). Nastavení článku: toc (''/show/hide).
 * Filtry: ekt_toc_post_types( ['post'] ), ekt_toc_tags( ['h2'=>2,'h3'=>3,'details'=>2] ),
 * ekt_toc_min_items( 2 ), ekt_toc_items( $items, $post ), ekt_toc_texts( ['title'=>…] ),
 * ekt_toc_html( $html, $args ) (override template-parts/ekt/toc.php, $args['items'], $args['title']).
 *
 * Markup: <nav class="ekt-toc" data-ekt-toc aria-label>
 *           <p class="ekt-toc__title">Obsah článku</p>
 *           <ol class="ekt-toc__list"><li class="ekt-toc__item ekt-toc__l2|ekt-toc__l3">
 *             <a class="ekt-toc__link" href="#id">…</a></li></ol></nav>
 * JS (toc.js) přidá aktuálnímu odkazu .is-active a aria-current="location"; odkaz na zavřenou
 * sekci <details> ji otevře. Hledá jen uvnitř [data-ekt-content].
 * Kotvy vyžadují WP 6.5+ (WP_HTML_Tag_Processor::next_token), na starším WP modul nic nedělá.
 */

ekt_setting_register(
	'toc',
	array(
		'type'        => 'checkbox',
		'default'     => 1,
		'label'       => 'Obsah článku (z nadpisů)',
		'description' => 'Výchozí stav; u článku jde přepnout v boxu Nastavení článku.',
		'section'     => 'posts',
		'priority'    => 20,
	)
);

ekt_post_setting_register(
	'toc',
	array(
		'type'     => 'select',
		'label'    => 'Obsah článku',
		'choices'  => array(
			''     => 'Podle nastavení šablony',
			'show' => 'Zobrazit',
			'hide' => 'Skrýt',
		),
		'priority' => 10,
		'sanitize' => 'ekt_toc_sanitize_setting',
	)
);

/**
 * Očištění volby Obsah článku u článku.
 *
 * @param mixed $value Hodnota.
 * @return string '', show, hide.
 */
function ekt_toc_sanitize_setting( $value ): string {
	$value = is_scalar( $value ) ? (string) $value : '';
	return in_array( $value, array( 'show', 'hide' ), true ) ? $value : '';
}

/**
 * Zobrazit u článku obsah? Volba článku má přednost před šablonou; dokud běží TOC plugin, ne.
 *
 * @param int|WP_Post|null $post Článek.
 * @return bool
 */
function ekt_toc_enabled( $post = null ): bool {
	$post = get_post( $post );
	if ( ! $post || ekt_legacy_active( 'toc' ) || post_password_required( $post ) ) {
		return false;
	}
	if ( ! in_array( $post->post_type, (array) apply_filters( 'ekt_toc_post_types', array( 'post' ) ), true ) ) {
		return false;
	}
	$own = (string) ekt_post_setting( 'toc', $post );
	if ( 'show' === $own || 'hide' === $own ) {
		return 'show' === $own;
	}
	return (bool) ekt_opt( 'toc' );
}

/**
 * Sledované značky a jejich úroveň v seznamu.
 *
 * @return array<string,int> malými písmeny: h2 => 2, h3 => 3, details => 2.
 */
function ekt_toc_tags(): array {
	$tags = (array) apply_filters(
		'ekt_toc_tags',
		array(
			'h2'      => 2,
			'h3'      => 3,
			'details' => 2,
		)
	);
	return array_change_key_case( array_map( 'intval', $tags ), CASE_LOWER );
}

/**
 * Kotva z textu nadpisu: bez diakritiky, malá písmena, jen a–z, 0–9 a pomlčky. Necitlivé na
 * typografické úpravy (uvozovky, pomlčky, tři tečky), takže seznam i obsah dají stejné id.
 *
 * @param string $text Text nadpisu.
 * @return string
 */
function ekt_toc_slug( string $text ): string {
	$text = strtolower( remove_accents( html_entity_decode( $text, ENT_QUOTES | ENT_HTML5, 'UTF-8' ) ) );
	$text = trim( (string) preg_replace( '/[^a-z0-9]+/', '-', $text ), '-' );
	return '' === $text ? 'sekce' : substr( $text, 0, 80 );
}

/**
 * Projde HTML, spočítá položky obsahu a doplní id (dva průchody WP_HTML_Tag_Processor:
 * první sbírá texty, druhý ve stejném pořadí zapisuje id).
 *
 * @param string $html HTML obsahu.
 * @return array{html:string,items:array<int,array{level:int,id:string,text:string}>}
 */
function ekt_toc_scan( string $html ): array {
	$result = array(
		'html'  => $html,
		'items' => array(),
	);
	if ( '' === trim( $html ) || ! method_exists( 'WP_HTML_Tag_Processor', 'next_token' ) ) {
		return $result;
	}
	$tags = ekt_toc_tags();
	if ( ! $tags ) {
		return $result;
	}

	// 1) Texty a existující id sledovaných značek v pořadí dokumentu.
	$found   = array(); // [ tag, level, id, text ] pro každý otvírací tag (i ty, které se nepoužijí)
	$open    = null;    // index rozpracované položky
	$depth   = 0;       // vnoření stejné značky v rozpracované položce
	$details = 0;       // hloubka <details>
	$summary = false;   // uvnitř <summary> rozpracovaného <details>
	$p       = new WP_HTML_Tag_Processor( $html );
	while ( $p->next_token() ) {
		$type = $p->get_token_type();
		if ( '#text' === $type ) {
			if ( null !== $open && ( 'details' !== $found[ $open ]['tag'] || $summary ) ) {
				$found[ $open ]['text'] .= $p->get_modifiable_text();
			}
			continue;
		}
		if ( '#tag' !== $type ) {
			continue;
		}
		$tag    = strtolower( (string) $p->get_tag() );
		$closer = $p->is_tag_closer();
		if ( 'details' === $tag ) {
			$details += $closer ? -1 : 1;
		}

		// Každý otvírací sledovaný tag dostane záznam (pořadí = pořadí zápisu id v kroku 3).
		if ( ! $closer && isset( $tags[ $tag ] ) ) {
			$skip    = null !== $open || ( 'details' === $tag && 1 !== $details ); // uvnitř jiné položky, vnořené <details>
			$found[] = array(
				'tag'   => $tag,
				'level' => $tags[ $tag ],
				'id'    => trim( (string) $p->get_attribute( 'id' ) ),
				'text'  => '',
				'skip'  => $skip,
			);
			if ( ! $skip ) {
				$open    = array_key_last( $found );
				$depth   = 1;
				$summary = false;
			} elseif ( null !== $open && $tag === $found[ $open ]['tag'] && 'details' !== $tag ) {
				++$depth;
			}
			continue;
		}
		if ( null === $open ) {
			continue;
		}
		$current = $found[ $open ]['tag'];
		if ( 'details' === $current ) {
			if ( 'summary' === $tag && ! $closer && 1 === $details ) {
				$summary = true;
			} elseif ( 'summary' === $tag && $closer && $summary ) {
				$summary = false;
				$open    = null; // text sekce = první <summary>
			} elseif ( 'details' === $tag && $closer && 0 === $details ) {
				$open = null; // <details> bez <summary>
			}
			continue;
		}
		if ( $closer && $tag === $current && --$depth <= 0 ) {
			$open = null;
		}
	}

	// 2) Id: existující se nechá, ostatní ze slugu, unikátní v rámci obsahu.
	$used = array();
	foreach ( $found as $item ) {
		if ( '' !== $item['id'] ) {
			$used[ $item['id'] ] = true;
		}
	}
	$assign = array();
	foreach ( $found as $i => $item ) {
		$text = trim( (string) preg_replace( '/\s+/u', ' ', $item['text'] ) );
		if ( $item['skip'] || '' === $text ) {
			continue;
		}
		$id = $item['id'];
		if ( '' === $id ) {
			$base = ekt_toc_slug( $text );
			$id   = $base;
			$n    = 2;
			while ( isset( $used[ $id ] ) ) {
				$id = $base . '-' . $n++;
			}
			$used[ $id ]  = true;
			$assign[ $i ] = $id;
		}
		$result['items'][] = array(
			'level' => (int) $item['level'],
			'id'    => $id,
			'text'  => $text,
		);
	}
	if ( ! $assign ) {
		return $result;
	}

	// 3) Zápis id ve stejném pořadí otvíracích tagů.
	$i = -1;
	$p = new WP_HTML_Tag_Processor( $html );
	while ( $p->next_tag() ) {
		if ( ! isset( $tags[ strtolower( (string) $p->get_tag() ) ] ) ) {
			continue;
		}
		++$i;
		if ( isset( $assign[ $i ] ) ) {
			$p->set_attribute( 'id', $assign[ $i ] );
		}
	}
	$result['html'] = $p->get_updated_html();
	return $result;
}

/**
 * Kotvy v obsahu hlavního článku stránky.
 *
 * @param string $html Obsah.
 * @return string
 */
function ekt_toc_content( string $html ): string {
	if ( is_admin() || is_feed() || ! is_singular() || (int) get_the_ID() !== (int) get_queried_object_id() || ekt_legacy_active( 'toc' ) ) {
		return $html;
	}
	return ekt_toc_scan( $html )['html'];
}
add_filter( 'the_content', 'ekt_toc_content', 20 );

/**
 * Položky obsahu článku (spočítané z obsahu, nezávisle na pořadí volání v šabloně).
 *
 * @param int|WP_Post|null $post Článek.
 * @return array<int,array{level:int,id:string,text:string}>
 */
function ekt_toc_items( $post = null ): array {
	static $cache = array();
	$post = get_post( $post );
	if ( ! $post || post_password_required( $post ) ) {
		return array();
	}
	$key = $post->ID . ':' . md5( $post->post_content );
	if ( ! isset( $cache[ $key ] ) ) {
		// Stejné kroky the_content, které mění text nadpisů (bloky, shortcody, typografie).
		$html          = wptexturize( do_shortcode( do_blocks( $post->post_content ) ) );
		$cache[ $key ] = (array) apply_filters( 'ekt_toc_items', ekt_toc_scan( $html )['items'], $post );
	}
	return $cache[ $key ];
}

/**
 * HTML obsahu článku ('' = obsah vypnutý nebo málo položek).
 *
 * @param int|WP_Post|null $post Článek.
 * @return string
 */
function ekt_toc_html( $post = null ): string {
	$post = get_post( $post );
	if ( ! $post || ! ekt_toc_enabled( $post ) ) {
		return '';
	}
	$items = ekt_toc_items( $post );
	if ( count( $items ) < (int) apply_filters( 'ekt_toc_min_items', 2 ) ) {
		return '';
	}
	$texts = (array) apply_filters( 'ekt_toc_texts', array( 'title' => 'Obsah článku' ) );
	ekt_enqueue_script( 'ekt-toc', 'js/toc.js' );
	return ekt_render(
		'toc',
		static function ( array $args ): string {
			$li = '';
			foreach ( $args['items'] as $item ) {
				$li .= sprintf(
					'<li class="ekt-toc__item ekt-toc__l%1$d"><a class="ekt-toc__link" href="#%2$s">%3$s</a></li>',
					(int) $item['level'],
					esc_attr( $item['id'] ),
					esc_html( $item['text'] )
				);
			}
			return sprintf(
				'<nav class="ekt-toc" data-ekt-toc aria-label="%1$s"><p class="ekt-toc__title">%2$s</p><ol class="ekt-toc__list">%3$s</ol></nav>',
				esc_attr( $args['title'] ),
				esc_html( $args['title'] ),
				$li
			);
		},
		array(
			'items' => $items,
			'title' => (string) ( $texts['title'] ?? 'Obsah článku' ),
			'post'  => $post,
		)
	);
}

/**
 * Vypíše obsah článku (volá kostra v single.php).
 *
 * @param int|WP_Post|null $post Článek.
 */
function ekt_the_toc( $post = null ): void {
	echo ekt_toc_html( $post ); // phpcs:ignore WordPress.Security.EscapeOutput -- escapováno v ekt_toc_html()
}

/*
 * Migrace: post meta ek_toc → ekt_toc (A elvisek). Theme_mod ek_sidebar_toc → ekt_toc převádí common.
 */
add_filter(
	'ekt_migrations',
	static function ( array $steps ): array {
		$steps['toc/post-meta'] = 'ekt_toc_migrate';
		return $steps;
	}
);

/**
 * Krok migrace: {p}_toc → ekt_toc.
 *
 * @return bool
 */
function ekt_toc_migrate(): bool {
	$ok = true;
	foreach ( ekt_legacy_prefixes() as $p ) {
		$ok = ekt_migrate_meta_key( 'post', $p . '_toc', 'ekt_toc' ) && $ok;
	}
	return $ok;
}
