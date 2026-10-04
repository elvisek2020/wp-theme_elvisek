<?php
/**
 * Instalovatelný web (PWA): manifest, service worker a stránka „Jsi offline“.
 *
 * /manifest.webmanifest – název, barvy a ikony pro přidání na plochu
 * /sw.js                – ukládá přečtené články a soubory šablony, offline je ukáže z mezipaměti
 * /offline/             – stránka pro chvíli, kdy není připojení a článek uložený není
 *
 * Adresy WordPress nezná (404), obslouží se tady – stejně jako Markdown verze článků.
 * Administrace, přihlášení, REST API, hledání a náhledy se nikdy neukládají.
 */

defined( 'ABSPATH' ) || exit;

function ek_pwa_path( string $rel ): string {
	return (string) wp_parse_url( home_url( $rel ), PHP_URL_PATH );
}

add_action( 'wp_head', function () {
	printf( '<link rel="manifest" href="%s">' . "\n", esc_url( home_url( '/manifest.webmanifest' ) ) );
	echo '<meta name="theme-color" content="#f5f7fa" media="(prefers-color-scheme: light)">' . "\n";
	echo '<meta name="theme-color" content="#121926" media="(prefers-color-scheme: dark)">' . "\n";
}, 6 );

add_action( 'template_redirect', function () {
	if ( ! is_404() ) {
		return;
	}
	$path = untrailingslashit( (string) wp_parse_url( $_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH ) );

	if ( untrailingslashit( ek_pwa_path( '/manifest.webmanifest' ) ) === $path ) {
		ek_pwa_manifest();
	}
	if ( untrailingslashit( ek_pwa_path( '/sw.js' ) ) === $path ) {
		ek_pwa_sw();
	}
	if ( untrailingslashit( ek_pwa_path( '/offline/' ) ) === $path ) {
		ek_pwa_offline();
	}
}, 1 );

function ek_pwa_manifest(): void {
	$icons = EK_URI . '/assets/icons/';
	$data  = array(
		'name'             => get_bloginfo( 'name' ),
		'short_name'       => get_bloginfo( 'name' ),
		'description'      => (string) ( ek_opt( 'ek_motto' ) ?: get_bloginfo( 'description' ) ),
		'lang'             => 'cs',
		'start_url'        => home_url( '/' ),
		'scope'            => home_url( '/' ),
		'display'          => 'standalone',
		'background_color' => '#f5f7fa',
		'theme_color'      => '#1d5fc4',
		'icons'            => array(
			array( 'src' => $icons . 'icon-192.png', 'sizes' => '192x192', 'type' => 'image/png' ),
			array( 'src' => $icons . 'icon-512.png', 'sizes' => '512x512', 'type' => 'image/png' ),
			array( 'src' => $icons . 'icon-maskable-512.png', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable' ),
		),
	);
	status_header( 200 );
	header( 'Content-Type: application/manifest+json; charset=utf-8' );
	header( 'Cache-Control: public, max-age=86400' );
	echo wp_json_encode( $data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT );
	exit;
}

function ek_pwa_sw(): void {
	$js = (string) file_get_contents( EK_DIR . '/assets/js/sw.js' );
	$js = strtr( $js, array(
		'__EK_VERSION__' => EK_VERSION,
		'__EK_OFFLINE__' => ek_pwa_path( '/offline/' ),
		'__EK_PRECACHE__' => wp_json_encode( array(
			ek_pwa_path( '/offline/' ),
			(string) wp_parse_url( EK_URI . '/assets/css/main.css', PHP_URL_PATH ) . '?ver=' . ek_asset_ver( 'assets/css/main.css' ),
			(string) wp_parse_url( EK_URI . '/assets/js/theme.js', PHP_URL_PATH ) . '?ver=' . ek_asset_ver( 'assets/js/theme.js' ),
		), JSON_UNESCAPED_SLASHES ),
	) );
	status_header( 200 );
	header( 'Content-Type: application/javascript; charset=utf-8' );
	header( 'Cache-Control: no-cache, no-store, must-revalidate, max-age=0' );
	header( 'Service-Worker-Allowed: ' . ek_pwa_path( '/' ) );
	echo $js; // phpcs:ignore WordPress.Security.EscapeOutput -- vlastní JS ze šablony
	exit;
}

function ek_pwa_offline(): void {
	status_header( 200 );
	nocache_headers();
	header( 'X-Robots-Tag: noindex' );
	add_filter( 'pre_get_document_title', fn() => 'Jsi offline – ' . get_bloginfo( 'name' ) );
	add_filter( 'body_class', fn( $c ) => array_merge( array_diff( $c, array( 'error404' ) ), array( 'ek-offline-page' ) ) );
	get_header();
	?>
	<main id="obsah" class="ek-main">
		<div class="ek-wrap"><section class="ek-404 ek-offline">
			<p class="ek-404__code"><?php echo ek_icon( 'offline', 56 ); ?></p>
			<h1 class="ek-pagehead__title">Jsi offline</h1>
			<p class="ek-muted">Tahle stránka se nestihla uložit. Až bude připojení zpátky, stačí ji obnovit.</p>
			<section class="ek-offline__saved" data-ek-offline hidden>
				<h2 class="ek-section__title">Uložené články</h2>
				<p class="ek-muted">Tyhle jsi už četl a jdou otevřít i bez internetu:</p>
				<ul class="ek-offline__list" data-ek-offline-list></ul>
			</section>
			<p><a class="ek-btn" href="<?php echo esc_url( home_url( '/' ) ); ?>">Zkusit úvod</a></p>
		</section></div>
	</main>
	<?php
	get_footer();
	exit;
}
