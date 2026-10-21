<?php
/**
 * Styly a skript šablony (vzhled a chování lišty, menu, komentářů). Žádné externí zdroje, žádné jQuery.
 * Funkční CSS a JS kanonických modulů načítají moduly samy z assets/ekt/.
 */

defined( 'ABSPATH' ) || exit;

function ek_asset_ver( string $rel ): string {
	$file = EKT_DIR . '/' . $rel;
	return file_exists( $file ) ? (string) filemtime( $file ) : EKT_VERSION;
}

add_action( 'wp_enqueue_scripts', function () {
	wp_enqueue_style( 'ek-main', EKT_URI . '/assets/css/main.css', array(), ek_asset_ver( 'assets/css/main.css' ) );
	wp_enqueue_script( 'ek-theme', EKT_URI . '/assets/js/theme.js', array(), ek_asset_ver( 'assets/js/theme.js' ), array( 'in_footer' => true, 'strategy' => 'defer' ) );

	if ( is_singular() && comments_open() && get_option( 'thread_comments' ) ) {
		wp_enqueue_script( 'comment-reply' );
	}
}, 20 );

// Preload hlavních písem (latin), aby se text nepřekresloval.
add_filter( 'ekt_preload_fonts', fn( $fonts ) => array_merge(
	(array) $fonts,
	array( 'assets/fonts/nunito-sans-latin-wght-normal.woff2', 'assets/fonts/space-grotesk-latin-wght-normal.woff2' )
) );
