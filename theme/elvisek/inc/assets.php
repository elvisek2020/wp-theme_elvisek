<?php
/**
 * Styly a skripty. Žádné externí zdroje, žádné jQuery.
 */

defined( 'ABSPATH' ) || exit;

function ek_asset_ver( string $rel ): string {
	$file = EK_DIR . '/' . $rel;
	return file_exists( $file ) ? (string) filemtime( $file ) : EK_VERSION;
}

/**
 * Obsahuje aktuální stránka blok kódu?
 */
function ek_has_code(): bool {
	if ( ! is_singular() ) {
		return false;
	}
	$content = (string) get_post_field( 'post_content', get_queried_object_id() );
	return str_contains( $content, '<pre' ) || str_contains( $content, '<code' );
}

add_action( 'wp_enqueue_scripts', function () {
	wp_enqueue_style( 'ek-main', EK_URI . '/assets/css/main.css', array(), ek_asset_ver( 'assets/css/main.css' ) );
	wp_enqueue_style( 'ek-print', EK_URI . '/assets/css/print.css', array( 'ek-main' ), ek_asset_ver( 'assets/css/print.css' ), 'print' );

	wp_enqueue_script( 'ek-theme', EK_URI . '/assets/js/theme.js', array(), ek_asset_ver( 'assets/js/theme.js' ), array( 'in_footer' => true, 'strategy' => 'defer' ) );

	if ( is_singular() ) {
		wp_enqueue_script( 'ek-lightbox', EK_URI . '/assets/js/lightbox.js', array(), ek_asset_ver( 'assets/js/lightbox.js' ), array( 'in_footer' => true, 'strategy' => 'defer' ) );
		if ( comments_open() && get_option( 'thread_comments' ) ) {
			wp_enqueue_script( 'comment-reply' );
		}
	}

	if ( ek_has_code() ) {
		wp_enqueue_script( 'ek-prism', EK_URI . '/assets/js/prism.js', array(), ek_asset_ver( 'assets/js/prism.js' ), array( 'in_footer' => true, 'strategy' => 'defer' ) );
	}

	// Nepotřebné pro návštěvníky.
	wp_dequeue_style( 'classic-theme-styles' );
	if ( ! is_user_logged_in() ) {
		wp_dequeue_style( 'dashicons' );
	}
}, 20 );

// Preload hlavních fontů (latin), aby se text nepřekresloval.
add_action( 'wp_head', function () {
	foreach ( array( 'nunito-sans-latin-wght-normal', 'space-grotesk-latin-wght-normal' ) as $font ) {
		printf(
			'<link rel="preload" href="%s" as="font" type="font/woff2" crossorigin>' . "\n",
			esc_url( EK_URI . '/assets/fonts/' . $font . '.woff2' )
		);
	}
}, 1 );

// Režim světlý/tmavý nastavit ještě před vykreslením (bez probliknutí).
add_action( 'wp_head', function () {
	echo "<script>(function(){try{var t=localStorage.getItem('ek-theme');if(t==='dark'||t==='light'){document.documentElement.dataset.theme=t;}}catch(e){}})();</script>\n";
	echo '<meta name="color-scheme" content="light dark">' . "\n";
}, 0 );

// Editor: výběr jazyka v bloku Kód.
add_action( 'enqueue_block_editor_assets', function () {
	wp_enqueue_script(
		'ek-editor-code-language',
		EK_URI . '/assets/js/editor-code-language.js',
		array( 'wp-hooks', 'wp-compose', 'wp-element', 'wp-block-editor', 'wp-components' ),
		ek_asset_ver( 'assets/js/editor-code-language.js' ),
		true
	);
} );
