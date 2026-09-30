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
		'ek_about'       => 'Memo blog – poznámky co, jak, kde a proč ze světa jedniček a nul. Jablíčka, Linux, chytrá domácnost a sem tam web.',
		'ek_since'       => '2016',
		'ek_meta_desc'   => 'ElvisEK – Linux, Apple, macOS, Synology, NAS, Raspberry, LibreELEC – instalace, konfigurace, debugging. Loxone, Zigbee, weby.',
		'ek_ga_id'       => '',
		'ek_topics_count'=> 8,
		'ek_sidebar_mode'=> 'wide',
		'ek_sidebar_toc' => true,
		'ek_sidebar_recent' => true,
	);
}

function ek_opt( string $key ) {
	$defaults = ek_defaults();
	return get_theme_mod( $key, $defaults[ $key ] ?? '' );
}

add_action( 'customize_register', function ( WP_Customize_Manager $wpc ) {
	$d = ek_defaults();

	$wpc->add_section( 'ek_theme', array(
		'title'    => 'ElvisEK',
		'priority' => 30,
	) );

	$fields = array(
		'ek_motto'     => array( 'Motto pod nadpisem', 'textarea', 'sanitize_textarea_field' ),
		'ek_about'     => array( 'Text „O webu“ v patičce', 'textarea', 'sanitize_textarea_field' ),
		'ek_since'     => array( 'Rok založení (copyright)', 'text', 'absint' ),
		'ek_meta_desc' => array( 'Meta description titulky', 'textarea', 'sanitize_textarea_field' ),
		'ek_ga_id'     => array( 'Google Analytics 4 ID (G-…). Prázdné = žádná analytika ani cookie lišta.', 'text', 'ek_sanitize_ga_id' ),
		'ek_topics_count' => array( 'Počet témat na titulce', 'number', 'absint' ),
	);

	foreach ( $fields as $id => [ $label, $type, $sanitize ] ) {
		$wpc->add_setting( $id, array(
			'default'           => $d[ $id ],
			'sanitize_callback' => $sanitize,
		) );
		$wpc->add_control( $id, array(
			'label'   => $label,
			'section' => 'ek_theme',
			'type'    => $type,
		) );
	}

	$wpc->add_setting( 'ek_sidebar_mode', array( 'default' => $d['ek_sidebar_mode'], 'sanitize_callback' => fn( $v ) => in_array( $v, array( 'wide', 'always', 'never' ), true ) ? $v : 'wide' ) );
	$wpc->add_control( 'ek_sidebar_mode', array(
		'label'   => 'Boční panel u článku',
		'section' => 'ek_theme',
		'type'    => 'select',
		'choices' => array(
			'wide'   => 'Jen na širokých obrazovkách (od 1400 px)',
			'always' => 'Vždy (od 1080 px)',
			'never'  => 'Nikdy — jen článek',
		),
	) );
	foreach ( array( 'ek_sidebar_toc' => 'Panel: obsah článku', 'ek_sidebar_recent' => 'Panel: novinky' ) as $id => $label ) {
		$wpc->add_setting( $id, array( 'default' => $d[ $id ], 'sanitize_callback' => 'rest_sanitize_boolean' ) );
		$wpc->add_control( $id, array( 'label' => $label, 'section' => 'ek_theme', 'type' => 'checkbox' ) );
	}

	$wpc->add_setting( 'ek_hero_image', array(
		'default'           => '',
		'sanitize_callback' => 'absint',
	) );
	$wpc->add_control( new WP_Customize_Media_Control( $wpc, 'ek_hero_image', array(
		'label'     => 'Obrázek na pozadí hlavičky (volitelné)',
		'section'   => 'ek_theme',
		'mime_type' => 'image',
	) ) );
} );

function ek_sanitize_ga_id( $value ): string {
	$value = strtoupper( trim( (string) $value ) );
	return preg_match( '/^G-[A-Z0-9]{4,}$/', $value ) ? $value : '';
}
