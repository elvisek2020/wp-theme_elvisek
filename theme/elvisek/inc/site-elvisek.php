<?php
/**
 * Projektové nastavení webu elvisek.cz nad kanonickými moduly (jen přes jejich filtry a registry).
 */

defined( 'ABSPATH' ) || exit;

/* =========================================================================
 * Výchozí hodnoty a převod starých theme_mods (ek_*)
 * ====================================================================== */

add_filter( 'ekt_defaults', function ( array $d ): array {
	return array_merge(
		$d,
		array(
			'meta_desc'       => 'ElvisEK – Linux, Apple, macOS, Synology, NAS, Raspberry, LibreELEC – instalace, konfigurace, debugging. Loxone, Zigbee, weby.',
			'copyright_since' => 2016,
			'footer_text'     => 'Memo blog – poznámky co, jak, kde a proč ze světa jedniček a nul.',
			'social_email'    => 'elvisek@elvisek.cz',
			'social_github'   => 'https://github.com/elvisek2020',
			'social_linkedin' => 'https://www.linkedin.com/in/kralzdenek/',
			'site_motto'      => get_bloginfo( 'description' ),
		)
	);
} );

// Projektové klíče staré šablony → ekt_site_* (kanonické klíče převádí common sám).
add_filter( 'ekt_legacy_mod_map', function ( array $map, string $p ): array {
	if ( 'ek' !== $p ) {
		return $map;
	}
	return array_merge(
		$map,
		array(
			'ek_about'            => 'ekt_footer_text',
			'ek_motto'            => 'ekt_site_motto',
			'ek_show_masthead'    => 'ekt_site_show_masthead',
			'ek_hero_image'       => 'ekt_site_hero_image',
			'ek_hero_image_light' => 'ekt_site_hero_image_light',
			'ek_topics'           => 'ekt_site_topics',
			'ek_topics_count'     => 'ekt_site_topics_count',
			'ek_sidebar_mode'     => 'ekt_site_sidebar_mode',
			'ek_sidebar_recent'   => 'ekt_site_sidebar_recent',
			'ek_postnav'          => 'ekt_site_postnav',
		)
	);
}, 10, 2 );

/* =========================================================================
 * Přizpůsobit: projektová pole (sekce Úvodní stránka a Články)
 * ====================================================================== */

ekt_customizer_section( 'home', 'Úvodní stránka', 24 );

ekt_setting_register( 'site_show_masthead', array( 'type' => 'checkbox', 'default' => true, 'label' => 'Zobrazit nadpis webu s mottem', 'section' => 'home', 'priority' => 10 ) );
ekt_setting_register( 'site_motto', array( 'type' => 'textarea', 'default' => '', 'label' => 'Motto pod nadpisem', 'section' => 'home', 'priority' => 20 ) );
ekt_setting_register( 'site_hero_image', array(
	'type'        => 'image',
	'default'     => 0,
	'label'       => 'Obrázek na pozadí nadpisu (volitelné)',
	'description' => 'Tmavý obrázek, bílý text. Použije se v tmavém režimu a ve světlém, pokud níže není světlá varianta. Ideálně 2400 × 1000 px, motiv ve středovém pruhu.',
	'section'     => 'home',
	'priority'    => 30,
) );
ekt_setting_register( 'site_hero_image_light', array(
	'type'        => 'image',
	'default'     => 0,
	'label'       => 'Světlá varianta obrázku (volitelné)',
	'description' => 'Světlý obrázek pro světlý režim – text bude tmavý. Stejný rozměr a kompozice jako tmavý.',
	'section'     => 'home',
	'priority'    => 40,
) );
ekt_setting_register( 'site_topics', array(
	'type'        => 'sortable_terms',
	'default'     => '',
	'label'       => 'Témata v hlavičce',
	'description' => 'Zaškrtni témata a přetáhni je do požadovaného pořadí. Když nic nezaškrtneš, zobrazí se automaticky nejpočetnější (podle „Počtu témat“ níže). Prázdná témata se nezobrazují.',
	'section'     => 'home',
	'priority'    => 50,
	'taxonomy'    => 'category',
) );
ekt_setting_register( 'site_topics_count', array( 'type' => 'number', 'default' => 8, 'label' => 'Počet témat', 'section' => 'home', 'priority' => 60, 'input_attrs' => array( 'min' => 0, 'max' => 12 ) ) );

ekt_setting_register( 'site_sidebar_mode', array(
	'type'     => 'select',
	'default'  => 'wide',
	'label'    => 'Boční panel',
	'section'  => 'posts',
	'priority' => 5,
	'choices'  => array(
		'wide'   => 'Jen na širokých obrazovkách (od 1400 px)',
		'always' => 'Vždy (od 1080 px)',
		'never'  => 'Nikdy — jen článek',
	),
) );
ekt_setting_register( 'site_sidebar_recent', array( 'type' => 'checkbox', 'default' => true, 'label' => 'Boční panel: novinky', 'section' => 'posts', 'priority' => 15 ) );
ekt_setting_register( 'site_postnav', array( 'type' => 'checkbox', 'default' => false, 'label' => 'Pod článkem: odkazy Starší / Novější', 'section' => 'posts', 'priority' => 50 ) );

/* =========================================================================
 * Nastavení článku: skrytý úvodní obrázek (0.11.7). Vlastní náhled místo obrázku rubriky
 * (0.11.6) je od term-image 1.1 v kanonu (post setting own_thumb).
 * ====================================================================== */

ekt_post_setting_register( 'site_hide_hero', array(
	'type'       => 'checkbox',
	'label'      => 'Skrýt úvodní obrázek v článku',
	'help'       => 'Detail článku začne rovnou textem; karty a náhled při sdílení zůstanou.',
	'post_types' => array( 'post' ),
	'priority'   => 70,
) );

add_filter( 'ekt_migrations', function ( array $steps ): array {
	$steps['site-elvisek/post-meta'] = 'ek_site_migrate_post_meta';
	$steps['site-elvisek/kanon-1-1'] = 'ek_site_migrate_canon_1_1';
	return $steps;
} );

function ek_site_migrate_post_meta(): bool {
	return ekt_migrate_meta_key( 'post', 'ek_hide_hero', 'ekt_site_hide_hero' );
}

/**
 * Přechod na term-image 1.1 a lightbox 1.1: vlastní náhled byl na webu pod projektovým klíčem
 * (ekt_site_own_thumb), lightbox i pro obrázky bez odkazu zapínal filtr. Obojí teď drží kanon.
 */
function ek_site_migrate_canon_1_1(): bool {
	ekt_opt_set( 'lightbox_unlinked', true );
	return ekt_migrate_meta_key( 'post', 'ekt_site_own_thumb', 'ekt_own_thumb' );
}

/** Detail článku bez úvodního obrázku. */
function ek_hide_hero( $post = null ): bool {
	return (bool) ekt_post_setting( 'site_hide_hero', $post );
}

/* =========================================================================
 * Nastavení kanonických modulů pro tento web
 * ====================================================================== */

// Výpis: titulka 1 hlavní článek + 12 karet, archivy a hledání po 12 (plné řádky při 1–4 sloupcích).
add_filter( 'ekt_load_more_per_page', fn() => 12 );
add_filter( 'ekt_load_more_home_offset', fn() => 1 );
add_filter( 'ekt_load_more_texts', fn( $t ) => array_merge( (array) $t, array( 'more' => is_search() ? 'Další výsledky' : 'Načíst další články' ) ) );

// Patička: přepínač Systém / Světlý / Tmavý a Široká stránka.
add_filter( 'ekt_color_scheme_wide', '__return_true' );
add_filter( 'ekt_color_scheme_theme_color', fn() => array( 'light' => '#f5f7fa', 'dark' => '#121926' ) );

add_filter( 'ekt_pwa_manifest', fn( $m ) => array_merge( (array) $m, array( 'background_color' => '#f5f7fa', 'theme_color' => '#1d5fc4' ) ) );

// Osobní web: vydavatel v JSON-LD je osoba.
add_filter( 'ekt_seo_publisher', fn( $e ) => array_merge( (array) $e, array( '@type' => 'Person' ) ) );

// Texty a sítě jako dosud.
add_filter( 'ekt_consent_texts', fn( $t ) => array_merge( (array) $t, array(
	'text'    => 'Web používá Google Analytics, abych věděl, které návody vás zajímají. Cookies se uloží jen s vaším souhlasem.',
	'privacy' => 'Více informací',
	'accept'  => 'Souhlasím',
) ) );
add_filter( 'ekt_share_networks', fn( $n ) => array_intersect_key( (array) $n, array_flip( array( 'linkedin', 'facebook' ) ) ) );

// Barvy rubrik, pokud u rubriky není vlastní.
add_filter( 'ekt_term_color_default', function ( $color, $term ) {
	$map = array(
		'raspberry-pi'  => '#c51a4a',
		'osx'           => '#64748b',
		'debian'        => '#a80030',
		'ios'           => '#2f7fe0',
		'loxone'        => '#5fae3e',
		'synology'      => '#2a5fa8',
		'webportfolio'  => '#7c4dce',
		'zigbee'        => '#d39a00',
		'car'           => '#2f7d6d',
		'aliexpress'    => '#e4572e',
		'homeassistant' => '#18a6dd',
		'ai-tools'      => '#d4704f',
		'nezarazene'    => '#8a94a3',
	);
	return ( $term instanceof WP_Term && isset( $map[ $term->slug ] ) ) ? $map[ $term->slug ] : $color;
}, 10, 2 );

/* =========================================================================
 * Obsah článku: jednotná šířka obrázků a úklid starých inline fontů
 * ====================================================================== */

/**
 * Obrázky v článku: třída ek-img (max. 800 px, zarovnané vlevo s textem), vysoké ek-img--tall.
 * Malé ikonky nechává být. Prohlížeči řekne, že se obrázek zobrazí v 800 px, aby si ze srcset
 * vzal dost velkou verzi.
 */
add_filter( 'wp_content_img_tag', function ( string $img, string $context ): string {
	if ( 'the_content' !== $context || ! is_singular() || str_contains( $img, 'wp-smiley' ) ) {
		return $img;
	}
	$p = new WP_HTML_Tag_Processor( $img );
	if ( ! $p->next_tag( 'img' ) ) {
		return $img;
	}
	$w = (int) $p->get_attribute( 'width' );
	$h = (int) $p->get_attribute( 'height' );
	if ( $w && $w < 160 ) {
		return $img;
	}
	$p->add_class( 'ek-img' );
	if ( $w && $h > $w * 1.15 ) {
		$p->add_class( 'ek-img--tall' );
	}
	if ( null !== $p->get_attribute( 'srcset' ) ) {
		$p->set_attribute( 'sizes', '(max-width: 860px) 100vw, 800px' );
	}
	return $p->get_updated_html();
}, 10, 2 );

// Staré třídy z Graphene/SyntaxHighlighter v obsahu nechat projít, ale bez inline fontů (Arial).
add_filter( 'the_content', function ( string $html ): string {
	return (string) preg_replace( '#\sstyle="font-family:\s*arial,\s*helvetica,\s*sans-serif;?"#i', '', $html );
}, 5 );

/* =========================================================================
 * Ikony rubrik (doplněk sady kanonu)
 * ====================================================================== */

add_filter( 'ekt_icons', fn( $icons ) => array_merge(
	(array) $icons,
	array(
		'chip'      => '<path d="M9 3v2M15 3v2M9 19v2M15 19v2M3 9h2M3 15h2M19 9h2M19 15h2"/><rect x="5" y="5" width="14" height="14" rx="1"/><path d="M9 9h6v6H9z"/>',
		'laptop'    => '<path d="M4 5h16a1 1 0 0 1 1 1v10H3V6a1 1 0 0 1 1-1zM1 19h22"/>',
		'terminal'  => '<path d="m4 17 6-5-6-5M12 19h8"/>',
		'phone'     => '<rect x="6" y="2" width="12" height="20" rx="2"/><path d="M11 18h2"/>',
		'home'      => '<path d="m3 11 9-7 9 7M5 10v10h14V10M10 20v-6h4v6"/>',
		'smarthome' => '<path d="M3 10.5 12 3l9 7.5V20a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1z"/><circle cx="12" cy="14.5" r="1.8"/><path d="M12 12.7V10M10.4 15.4l-2.2 1.3M13.6 15.4l2.2 1.3"/>',
		'server'    => '<rect x="3" y="4" width="18" height="7" rx="1"/><rect x="3" y="13" width="18" height="7" rx="1"/><path d="M7 7.5h.01M7 16.5h.01"/>',
		'wifi'      => '<path d="M12 20h.01M8.5 16.5a5 5 0 0 1 7 0M5 13a10 10 0 0 1 14 0M2 9.5a15 15 0 0 1 20 0"/>',
		'car'       => '<path d="M5 17h14M5 17a2 2 0 1 1-4 0v-4l2-5h18l2 5v4a2 2 0 1 1-4 0"/><circle cx="7" cy="17" r="2"/><circle cx="17" cy="17" r="2"/>',
		'bag'       => '<path d="M6 7h12l1 13H5zM9 7a3 3 0 0 1 6 0"/>',
	)
) );
