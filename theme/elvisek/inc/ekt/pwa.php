<?php
/**
 * EKT module: pwa
 * Version: 1.0.0
 * Requires: common
 * Replaces: super-progressive-web-apps, pwa
 * Assets: js/pwa.js, js/sw.js
 * Summary: Instalovatelný web: manifest, service worker (přečtené stránky offline) a stránka „Jste offline“.
 */

defined( 'ABSPATH' ) || exit;

/*
 * Adresy (vždy od home_url, takže fungují i pro WP v podsložce):
 *   <home>/manifest.webmanifest – název, barvy, ikony (application/manifest+json),
 *   <home>/sw.js                – service worker ze souboru assets/ekt/js/sw.js s doplněnými cestami
 *                                 a verzí (application/javascript, no-cache, Service-Worker-Allowed),
 *   <home>/offline/             – stránka „Jste offline“ (template-parts/offline.php šablony, když
 *                                 existuje, jinak minimální markup), noindex.
 * WordPress tyto adresy nezná (404) a obslouží je template_redirect – bez přepisovacích pravidel.
 *
 * Service worker: stránky nejdřív ze sítě (bez připojení uložená verze, jinak offline stránka),
 * soubory z wp-content/wp-includes z mezipaměti s obnovou na pozadí. Neukládá administraci,
 * přihlášení, REST, hledání, náhledy ani stránky přihlášeného uživatele. Cache ekt-<verze>-pages
 * a ekt-<verze>-assets; při aktivaci smaže starší ekt-* i caches staré šablony ek-*.
 * Registraci (js/pwa.js) dostanou jen nepřihlášení návštěvníci na HTTPS (nebo localhost).
 * Na offline stránce js/pwa.js vypíše uložené články do prvku [data-ekt-offline-list].
 *
 * Veřejné funkce: ekt_pwa_path( $rel ), ekt_pwa_is_offline(), ekt_pwa_manifest_data().
 * Filtry: ekt_pwa_manifest( $data ) (barvy, ikony, název…; výchozí ikony = Ikona webu z Přizpůsobit),
 * ekt_pwa_theme_colors( ['light'=>…,'dark'=>…] ) (meta theme-color; modul color-scheme ji vypisuje
 * sám, pak ji pwa vynechá), ekt_pwa_precache( [cesty] ), ekt_pwa_texts( $texts ).
 */

/**
 * Cesta k adrese webu (s podsložkou), např. /blog/sw.js.
 *
 * @param string $rel Relativní adresa (/sw.js).
 * @return string
 */
function ekt_pwa_path( string $rel ): string {
	$path = (string) wp_parse_url( home_url( $rel ), PHP_URL_PATH );
	return '' === $path ? '/' : $path;
}

/**
 * Běží PWA? (ne s původním pluginem).
 *
 * @return bool
 */
function ekt_pwa_active(): bool {
	return ! ekt_legacy_active( 'pwa' );
}

/**
 * Je aktuální stránka offline stránka PWA? (modul seo podle toho dává noindex).
 *
 * @param bool|null $set Interní: nastavit stav.
 * @return bool
 */
function ekt_pwa_is_offline( ?bool $set = null ): bool {
	static $offline = false;
	if ( null !== $set ) {
		$offline = $set;
	}
	return $offline;
}

/**
 * Texty modulu.
 *
 * @return array<string,string>
 */
function ekt_pwa_texts(): array {
	return array_merge(
		array(
			'title'   => 'Jste offline',
			'text'    => 'Stránka se nedá načíst, protože zařízení není připojené k internetu. Zkuste to znovu, až bude připojení zpět.',
			'retry'   => 'Zkusit znovu',
			'saved'   => 'Uložené stránky',
			'offline' => 'Jste offline',
		),
		(array) apply_filters( 'ekt_pwa_texts', array() )
	);
}

/**
 * Data manifestu.
 *
 * @return array
 */
function ekt_pwa_manifest_data(): array {
	$name  = wp_specialchars_decode( (string) get_bloginfo( 'name' ), ENT_QUOTES );
	$icons = array();
	if ( has_site_icon() ) {
		foreach ( array( 192, 512 ) as $size ) {
			$icons[] = array(
				'src'   => get_site_icon_url( $size ),
				'sizes' => $size . 'x' . $size,
				'type'  => (string) ( wp_check_filetype( (string) get_site_icon_url( $size ) )['type'] ?? '' ) ?: 'image/png',
			);
		}
	}
	$data = array(
		'name'             => $name,
		'short_name'       => $name,
		'description'      => wp_specialchars_decode( (string) get_bloginfo( 'description' ), ENT_QUOTES ),
		'lang'             => (string) get_bloginfo( 'language' ),
		'dir'              => is_rtl() ? 'rtl' : 'ltr',
		'start_url'        => home_url( '/' ),
		'scope'            => home_url( '/' ),
		'display'          => 'standalone',
		'background_color' => '#ffffff',
		'theme_color'      => '#ffffff',
		'icons'            => $icons,
	);
	return (array) apply_filters( 'ekt_pwa_manifest', $data );
}

/**
 * Odkaz na manifest a theme-color v <head>.
 */
function ekt_pwa_head(): void {
	if ( ! ekt_pwa_active() ) {
		return;
	}
	printf( '<link rel="manifest" href="%s">' . "\n", esc_url( home_url( '/manifest.webmanifest' ) ) );
	if ( function_exists( 'ekt_color_scheme_switch' ) ) {
		return; // theme-color světlé/tmavé vypisuje modul color-scheme
	}
	$manifest = ekt_pwa_manifest_data();
	$colors   = (array) apply_filters(
		'ekt_pwa_theme_colors',
		array(
			'light' => (string) ( $manifest['theme_color'] ?? '' ),
			'dark'  => '',
		)
	);
	foreach ( array( 'light', 'dark' ) as $scheme ) {
		$color = sanitize_hex_color( (string) ( $colors[ $scheme ] ?? '' ) );
		if ( $color ) {
			printf( '<meta name="theme-color" content="%1$s" media="(prefers-color-scheme: %2$s)">' . "\n", esc_attr( $color ), esc_attr( $scheme ) );
		}
	}
}
add_action( 'wp_head', 'ekt_pwa_head', 6 );

/**
 * Hlavičky odpovědi, která není stránka (bez Expires/Pragma z 404).
 *
 * @param string $type          Content-Type.
 * @param string $cache_control Cache-Control.
 */
function ekt_pwa_headers( string $type, string $cache_control ): void {
	status_header( 200 );
	if ( headers_sent() ) {
		return;
	}
	header_remove( 'Expires' );
	header_remove( 'Pragma' );
	header_remove( 'Last-Modified' );
	header( 'Content-Type: ' . $type );
	header( 'Cache-Control: ' . $cache_control );
	header( 'X-Content-Type-Options: nosniff' );
}

/**
 * Obsluha /manifest.webmanifest, /sw.js a /offline/ (WordPress je nezná → 404 → tady).
 */
function ekt_pwa_route(): void {
	if ( ! is_404() || ! ekt_pwa_active() ) {
		return;
	}
	$path = untrailingslashit( (string) wp_parse_url( (string) ( $_SERVER['REQUEST_URI'] ?? '' ), PHP_URL_PATH ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- jen cesta, porovnává se
	switch ( $path ) {
		case untrailingslashit( ekt_pwa_path( '/manifest.webmanifest' ) ):
			ekt_pwa_headers( 'application/manifest+json; charset=utf-8', 'public, max-age=86400' );
			echo wp_json_encode( ekt_pwa_manifest_data(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT ); // phpcs:ignore WordPress.Security.EscapeOutput -- JSON
			exit;
		case untrailingslashit( ekt_pwa_path( '/sw.js' ) ):
			ekt_pwa_sw();
			exit;
		case untrailingslashit( ekt_pwa_path( '/offline/' ) ):
			ekt_pwa_offline();
			exit;
	}
}
add_action( 'template_redirect', 'ekt_pwa_route', 1 );

/**
 * Service worker s doplněnou verzí a cestami.
 */
function ekt_pwa_sw(): void {
	$file = ekt_asset_path( 'js/sw.js' );
	$js   = is_file( $file ) ? (string) file_get_contents( $file ) : ''; // phpcs:ignore WordPress.WP.AlternativeFunctions -- lokální soubor šablony
	if ( '' === $js ) {
		status_header( 404 );
		exit;
	}
	$base     = trailingslashit( ekt_pwa_path( '/' ) );
	$precache = array_values( array_unique( array_map( 'strval', (array) apply_filters( 'ekt_pwa_precache', array( ekt_pwa_path( '/offline/' ) ) ) ) ) );
	$flags    = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG;
	$js       = strtr(
		$js,
		array(
			'"__EKT_VERSION__"'  => (string) wp_json_encode( EKT_VERSION . '-' . ekt_asset_ver( 'js/sw.js' ), $flags ),
			'"__EKT_BASE__"'     => (string) wp_json_encode( $base, $flags ),
			'"__EKT_OFFLINE__"'  => (string) wp_json_encode( ekt_pwa_path( '/offline/' ), $flags ),
			'"__EKT_PRECACHE__"' => (string) wp_json_encode( $precache, $flags ),
		)
	);
	ekt_pwa_headers( 'application/javascript; charset=utf-8', 'no-cache, no-store, must-revalidate, max-age=0' );
	if ( ! headers_sent() ) {
		header( 'Service-Worker-Allowed: ' . $base );
	}
	echo $js; // phpcs:ignore WordPress.Security.EscapeOutput -- vlastní JS ze šablony, hodnoty jako JSON
}

/**
 * Stránka „Jste offline“ (template-parts/offline.php šablony, jinak minimální markup).
 */
function ekt_pwa_offline(): void {
	ekt_pwa_is_offline( true );
	status_header( 200 );
	nocache_headers();
	if ( ! headers_sent() ) {
		header( 'X-Robots-Tag: noindex, follow' );
	}
	$texts = ekt_pwa_texts();
	add_filter(
		'wp_robots',
		static function ( array $robots ): array {
			unset( $robots['index'], $robots['nofollow'] );
			return array_merge(
				$robots,
				array(
					'noindex' => true,
					'follow'  => true,
				)
			);
		}
	);
	add_filter( 'pre_get_document_title', static fn() => $texts['title'] . ' – ' . get_bloginfo( 'name' ), 99 );
	add_filter( 'body_class', static fn( $classes ) => array_merge( array_diff( (array) $classes, array( 'error404' ) ), array( 'ekt-offline-page' ) ) );
	get_header();
	if ( locate_template( 'template-parts/offline.php' ) ) {
		get_template_part( 'template-parts/offline', null, array( 'texts' => $texts ) );
	} else {
		printf(
			'<section class="ekt-offline"><h1 class="ekt-offline__title">%1$s</h1><p class="ekt-offline__text">%2$s</p><p><a class="ekt-offline__retry" href="%3$s">%4$s</a></p><section class="ekt-offline__saved" data-ekt-offline hidden><h2 class="ekt-offline__subtitle">%5$s</h2><ul class="ekt-offline__list" data-ekt-offline-list></ul></section></section>',
			esc_html( $texts['title'] ),
			esc_html( $texts['text'] ),
			esc_url( home_url( '/' ) ),
			esc_html( $texts['retry'] ),
			esc_html( $texts['saved'] )
		);
	}
	get_footer();
}

/**
 * Registrace service workeru (jen nepřihlášení; přihlášený vidí stránky s lištou administrace).
 */
function ekt_pwa_enqueue(): void {
	if ( ! ekt_pwa_active() || is_user_logged_in() ) {
		return;
	}
	ekt_enqueue_script(
		'ekt-pwa',
		'js/pwa.js',
		array(),
		array(
			'object' => 'EKT_PWA',
			'data'   => array(
				'sw'    => ekt_pwa_path( '/sw.js' ),
				'scope' => trailingslashit( ekt_pwa_path( '/' ) ),
				'index' => trailingslashit( ekt_pwa_path( '/' ) ) . '__ekt_index',
			),
		)
	);
}
add_action( 'wp_enqueue_scripts', 'ekt_pwa_enqueue' );
