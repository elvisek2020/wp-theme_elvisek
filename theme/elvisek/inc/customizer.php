<?php
/**
 * Nastavení šablony v Přizpůsobení (Vzhled → Přizpůsobit → ElvisEK).
 */

defined( 'ABSPATH' ) || exit;

/**
 * Výchozí hodnoty nastavení.
 */
function ek_defaults(): array {
	return array(
		'ek_motto'       => get_bloginfo( 'description' ),
		'ek_hero_image'  => '',
		'ek_about'       => 'Memo blog – poznámky co, jak, kde a proč ze světa jedniček a nul.',
		'ek_since'       => '2016',
		'ek_meta_desc'   => 'ElvisEK – Linux, Apple, macOS, Synology, NAS, Raspberry, LibreELEC – instalace, konfigurace, debugging. Loxone, Zigbee, weby.',
		'ek_ga_id'       => '',
		'ek_topics_count'=> 8,
		'ek_sidebar_mode'=> 'wide',
		'ek_sidebar_toc' => true,
		'ek_sidebar_recent' => true,
		'ek_code_lines'  => true,
	);
}

function ek_opt( string $key ) {
	$defaults = ek_defaults();
	return get_theme_mod( $key, $defaults[ $key ] ?? '' );
}

add_action( 'customize_register', function ( WP_Customize_Manager $wpc ) {
	$d = ek_defaults();

	// Sekce jako samostatné položky menu v Přizpůsobit.
	$sections = array(
		'ek_home'    => array( 'Úvodní stránka', 24 ),
		'ek_article' => array( 'Články', 25 ),
		'ek_footer'  => array( 'Patička', 26 ),
		'ek_seo'     => array( 'SEO a analytika', 27 ),
	);
	foreach ( $sections as $id => [ $title, $prio ] ) {
		$wpc->add_section( $id, array( 'title' => $title, 'priority' => $prio ) );
	}

	$add = function ( string $id, string $section, string $label, string $type, $sanitize, array $extra = array() ) use ( $wpc, $d ) {
		$wpc->add_setting( $id, array( 'default' => $d[ $id ] ?? '', 'sanitize_callback' => $sanitize ) );
		$wpc->add_control( $id, array_merge( array( 'label' => $label, 'section' => $section, 'type' => $type ), $extra ) );
	};

	// Úvodní stránka
	$add( 'ek_motto', 'ek_home', 'Motto pod nadpisem', 'textarea', 'sanitize_textarea_field' );
	$wpc->add_setting( 'ek_hero_image', array( 'default' => '', 'sanitize_callback' => 'absint' ) );
	$wpc->add_control( new WP_Customize_Media_Control( $wpc, 'ek_hero_image', array(
		'label'     => 'Obrázek na pozadí nadpisu (volitelné)',
		'section'   => 'ek_home',
		'mime_type' => 'image',
	) ) );
	$add( 'ek_topics_count', 'ek_home', 'Počet témat', 'number', 'absint', array( 'input_attrs' => array( 'min' => 0, 'max' => 12 ) ) );

	// Články
	$add( 'ek_sidebar_mode', 'ek_article', 'Boční panel', 'select',
		fn( $v ) => in_array( $v, array( 'wide', 'always', 'never' ), true ) ? $v : 'wide',
		array( 'choices' => array(
			'wide'   => 'Jen na širokých obrazovkách (od 1400 px)',
			'always' => 'Vždy (od 1080 px)',
			'never'  => 'Nikdy — jen článek',
		) )
	);
	$add( 'ek_sidebar_toc', 'ek_article', 'Boční panel: obsah článku', 'checkbox', 'rest_sanitize_boolean' );
	$add( 'ek_sidebar_recent', 'ek_article', 'Boční panel: novinky', 'checkbox', 'rest_sanitize_boolean' );
	$add( 'ek_code_lines', 'ek_article', 'Čísla řádků u kódu', 'checkbox', 'rest_sanitize_boolean' );

	// Patička
	$add( 'ek_about', 'ek_footer', 'Krátký text o webu', 'textarea', 'sanitize_textarea_field' );
	$add( 'ek_since', 'ek_footer', 'Rok založení (copyright)', 'number', 'absint' );

	// SEO a analytika
	$add( 'ek_meta_desc', 'ek_seo', 'Meta description titulky', 'textarea', 'sanitize_textarea_field' );
	$add( 'ek_ga_id', 'ek_seo', 'Google Analytics 4 ID (G-…)', 'text', 'ek_sanitize_ga_id',
		array( 'description' => 'Prázdné = žádná analytika ani cookie lišta. Přihlášeným se GA nenačítá.' ) );
} );

function ek_sanitize_ga_id( $value ): string {
	$value = strtoupper( trim( (string) $value ) );
	return preg_match( '/^G-[A-Z0-9]{4,}$/', $value ) ? $value : '';
}
