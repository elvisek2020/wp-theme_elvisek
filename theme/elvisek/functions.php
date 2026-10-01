<?php
/**
 * ElvisEK — šablona pro www.elvisek.cz
 *
 * Každý modul v inc/ jde vypnout zakomentováním řádku níže.
 */

defined( 'ABSPATH' ) || exit;

define( 'EK_VERSION', (string) wp_get_theme( get_template() )->get( 'Version' ) );
define( 'EK_DIR', get_template_directory() );
define( 'EK_URI', get_template_directory_uri() );

$ek_modules = array(
	'setup',             // podpora šablony, menu, velikosti obrázků, úklid <head>
	'customizer',        // motto, obrázek hlavičky, text do patičky, GA4 ID
	'assets',            // CSS/JS, podmíněné načítání
	'template-tags',     // pomocné funkce pro šablony (meta, ikony, karty)
	'category-image',    // obrázek rubriky (náhled článků bez vlastního / místo vlastního)
	'content',           // externí odkazy, kotvy nadpisů, obsah článku (TOC)
	'seo',               // meta description, Open Graph, JSON-LD
	'comments-antispam', // honeypot + časová kontrola
	'analytics-consent', // GA4 + cookie lišta (jen když je vyplněné ID)
	'updater',           // aktualizace z GitHub Releases
	'updated',           // štítek „Aktualizováno“ u návodů
);

foreach ( $ek_modules as $ek_module ) {
	require EK_DIR . '/inc/' . $ek_module . '.php';
}
