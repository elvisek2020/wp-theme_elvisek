<?php
// Lokální vývoj elvisek.cz — NENASAZOVAT na produkci.
define('DB_NAME', 'elvisek');
define('DB_USER', 'wp');
define('DB_PASSWORD', 'wp');
define('DB_HOST', 'db');
define('DB_CHARSET', 'utf8mb4');
define('DB_COLLATE', '');
$table_prefix = 'wp_';

define('AUTH_KEY', ')nlr73|VMEswp+1YG(hpQb.EN9m;RQ>HPEOZ=o(>mZ}U;![[=zFj9}aO@TyD^26&');
define('SECURE_AUTH_KEY', '(Lz[YL8o&D4_=z8}&Lu7LhN]5d3Tn]5AD2db6j)TlH]O.w,_z^y0^b2OJeIw|LY3');
define('LOGGED_IN_KEY', 'aWfJrXK)]k?TH-}{WiMS9*UV4g#i@EeEHfcu#%h~;[sR+tE|eJu[H#:6<-mw^#@@');
define('NONCE_KEY', 'Bul1fl@nAgYXSo8v;L+;Lh0F05,efb5@rg.iiw^&:7[SKsi:xuho+.K1yO[L,PM>');
define('AUTH_SALT', 'Km6BbcA*MSWE-F&f,YDqZL&Kuyo+:G59,F|HKBs1PPyb)m,leM,2zIR4*cSRNyn3');
define('SECURE_AUTH_SALT', 'T?_?D:2_of7?WBxD.0!qYMv{y%wo;7R?9BE1#T#.hNs!S:ZbkEne(1dWtGpnKBY<');
define('LOGGED_IN_SALT', 'vWRkU+E?:0d*.PxQ[Rw]~-[DDd0l_b^&irt3T3Y+K+yW-LOU>ee2A8+pJib<jkl%');
define('NONCE_SALT', 'Y7@%?p%Gfv-1Y~IUpE26O*VuD,E_Vu^V2b-^*mW?!M@wJg|AvIgox]oSPEwuU2|]');

// Adresa podle toho, odkud přistupuješ (localhost i IP Macu v síti).
$ek_allowed_hosts = array( 'localhost:8080', 'localhost', '127.0.0.1:8080', '127.0.0.1', '10.5.11.173', '10.5.11.173:8080' );
$ek_host = strtolower( $_SERVER['HTTP_HOST'] ?? 'localhost:8080' );
if ( ! in_array( $ek_host, $ek_allowed_hosts, true ) ) {
	$ek_host = 'localhost:8080';
}
define('EK_DEV_HOST', $ek_host);
define('WP_HOME', 'http://' . $ek_host);
define('WP_SITEURL', 'http://' . $ek_host);
define('WP_ENVIRONMENT_TYPE', 'local');
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);
define('SCRIPT_DEBUG', false);
define('DISALLOW_FILE_EDIT', true);
define('AUTOMATIC_UPDATER_DISABLED', true);
define('WP_AUTO_UPDATE_CORE', false);

if ( ! defined('ABSPATH') ) {
	define('ABSPATH', __DIR__ . '/');
}
require_once ABSPATH . 'wp-settings.php';
