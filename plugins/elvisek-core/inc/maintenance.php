<?php
/**
 * Údržba webu — Nástroje → Údržba webu.
 *
 *  1) Výkon: náhledy pro šablonu, cache hlavičky v .htaccess
 *  2) WebP pro starší obrázky: vedle PNG/JPG vytvoří soubor.png.webp a web ho posílá místo originálu
 *     (nic se nepřepisuje v databázi, jde vypnout jedním tlačítkem)
 *  3) Databáze: velikost tabulek, autoload, optimalizace
 *  4) Média a odkazy: největší soubory, nepoužité obrázky, rozbité interní odkazy (jen přehled, nic nemaže)
 *  5) Úklid obsahu: revize, koncepty a koš, transienty
 */

defined( 'ABSPATH' ) || exit;

const EK_HTACCESS_MARKER = 'ElvisEK cache';
const EK_WEBP_META       = '_ek_webp';      // [ 'orig' => bytes, 'webp' => bytes, 'n' => files ] nebo 'skip'
const EK_WEBP_OPTION     = 'ek_webp_serve'; // doručovat WebP kopie
const EK_MAINT_SLUG      = 'ek-udrzba';

/* -------------------------------------------------------------------------
 * Jednorázový úklid po odebraných nástrojích (Přechod ElvisEK)
 * ---------------------------------------------------------------------- */
add_action( 'admin_init', function () {
	if ( get_option( 'ek_maint_v' ) === '2' ) {
		return;
	}
	delete_option( 'ek_mig_deactivated' );
	delete_post_meta_by_key( '_ek_premigration' );
	update_option( 'ek_maint_v', '2', false );
} );

// Staré záložky na „Přechod ElvisEK“ → nová stránka.
add_action( 'admin_init', function () {
	if ( ( $_GET['page'] ?? '' ) === 'ek-migrace' ) {
		wp_safe_redirect( admin_url( 'tools.php?page=' . EK_MAINT_SLUG ) );
		exit;
	}
} );

add_action( 'admin_menu', function () {
	add_management_page( 'Údržba webu', 'Údržba webu', 'manage_options', EK_MAINT_SLUG, 'ek_maint_page' );
} );

/* =========================================================================
 * WebP pro starší obrázky — doručování
 * ====================================================================== */

/** Převede URL z uploads na cestu na disku (nebo null, když to není soubor z uploads). */
function ek_upload_path_from_url( string $url ): ?string {
	static $up = null;
	$up  = $up ?? wp_get_upload_dir();
	$url = preg_replace( '#^https?:#', '', $url );
	$base = preg_replace( '#^https?:#', '', $up['baseurl'] );
	if ( ! str_starts_with( $url, $base . '/' ) ) {
		// stejný web bez/s www
		$alt = str_replace( '//www.', '//', $base );
		$url2 = str_replace( '//www.', '//', $url );
		if ( ! str_starts_with( $url2, $alt . '/' ) ) {
			return null;
		}
		$url = $base . substr( $url2, strlen( $alt ) );
	}
	$rel = rawurldecode( substr( strtok( $url, '?#' ), strlen( $base ) ) );
	return str_contains( $rel, '..' ) ? null : $up['basedir'] . $rel;
}

/** Vrátí URL WebP kopie, pokud existuje; jinak původní URL. */
function ek_webp_url( string $url ): string {
	static $cache = array();
	if ( isset( $cache[ $url ] ) ) {
		return $cache[ $url ];
	}
	$out = $url;
	if ( preg_match( '#\.(png|jpe?g)(\?.*)?$#i', $url ) ) {
		$path = ek_upload_path_from_url( $url );
		if ( $path && is_file( $path . '.webp' ) ) {
			$out = preg_replace( '#(\.(png|jpe?g))(\?.*)?$#i', '$1.webp$3', $url );
		}
	}
	return $cache[ $url ] = $out;
}

function ek_webp_filter_html( string $html ): string {
	if ( ! str_contains( $html, '/uploads/' ) ) {
		return $html;
	}
	// src, href (lightbox) i všechny položky srcset
	return preg_replace_callback(
		'#(https?:)?//[^\s"\'<>,]+/uploads/[^\s"\'<>,]+\.(?:png|jpe?g)(?=[\s"\'<>,])#i',
		fn( $m ) => ek_webp_url( $m[0] ),
		$html
	);
}

if ( ! is_admin() && get_option( EK_WEBP_OPTION ) ) {
	add_filter( 'the_content', 'ek_webp_filter_html', 99 );
	add_filter( 'wp_get_attachment_image', 'ek_webp_filter_html', 99 );
	add_filter( 'post_thumbnail_html', 'ek_webp_filter_html', 99 );
	add_filter( 'wp_get_attachment_image_src', function ( $img ) {
		if ( is_array( $img ) && ! empty( $img[0] ) ) {
			$img[0] = ek_webp_url( $img[0] );
		}
		return $img;
	}, 99 );
}

// Při smazání přílohy smazat i její WebP kopie.
add_action( 'delete_attachment', function ( $id ) {
	foreach ( ek_webp_files( (int) $id ) as $file ) {
		if ( is_file( $file . '.webp' ) ) {
			wp_delete_file( $file . '.webp' );
		}
	}
} );

/** Všechny soubory přílohy (originál, případný nezmenšený originál, zmenšeniny) ve formátu PNG/JPG. */
function ek_webp_files( int $id ): array {
	$main = get_attached_file( $id );
	if ( ! $main ) {
		return array();
	}
	$dir   = dirname( $main );
	$meta  = wp_get_attachment_metadata( $id ) ?: array();
	$files = array( $main );
	if ( ! empty( $meta['original_image'] ) ) {
		$files[] = $dir . '/' . $meta['original_image'];
	}
	foreach ( (array) ( $meta['sizes'] ?? array() ) as $size ) {
		if ( ! empty( $size['file'] ) ) {
			$files[] = $dir . '/' . $size['file'];
		}
	}
	return array_values( array_unique( array_filter( $files, fn( $f ) => preg_match( '#\.(png|jpe?g)$#i', $f ) && is_file( $f ) ) ) );
}

function ek_webp_candidates( bool $only_pending ): array {
	global $wpdb;
	$sql = "SELECT p.ID FROM {$wpdb->posts} p
		LEFT JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key = %s
		WHERE p.post_type = 'attachment' AND p.post_mime_type IN ('image/png','image/jpeg')";
	if ( $only_pending ) {
		$sql .= ' AND m.meta_id IS NULL';
	}
	return array_map( 'intval', $wpdb->get_col( $wpdb->prepare( $sql . ' ORDER BY p.ID DESC', EK_WEBP_META ) ) );
}

/** Převede jednu přílohu; vrací [ orig, webp, n ] nebo 'skip'. */
function ek_webp_convert( int $id ) {
	$orig = 0;
	$webp = 0;
	$n    = 0;
	foreach ( ek_webp_files( $id ) as $file ) {
		$target = $file . '.webp';
		if ( ! is_file( $target ) ) {
			$editor = wp_get_image_editor( $file );
			if ( is_wp_error( $editor ) ) {
				continue;
			}
			$editor->set_quality( 80 );
			$saved = $editor->save( $target, 'image/webp' );
			if ( is_wp_error( $saved ) || ! is_file( $target ) ) {
				continue;
			}
		}
		$o = (int) filesize( $file );
		$w = (int) filesize( $target );
		if ( $w >= $o ) {
			wp_delete_file( $target ); // WebP by nepomohl (typicky malé ikony)
			continue;
		}
		$orig += $o;
		$webp += $w;
		$n++;
	}
	$result = $n ? array( 'orig' => $orig, 'webp' => $webp, 'n' => $n ) : 'skip';
	update_post_meta( $id, EK_WEBP_META, $result );
	return $result;
}

function ek_webp_stats(): array {
	global $wpdb;
	$all  = count( ek_webp_candidates( false ) );
	$todo = count( ek_webp_candidates( true ) );
	$orig = 0;
	$webp = 0;
	$conv = 0;
	foreach ( $wpdb->get_col( $wpdb->prepare( "SELECT meta_value FROM {$wpdb->postmeta} WHERE meta_key = %s", EK_WEBP_META ) ) as $v ) {
		$v = maybe_unserialize( $v );
		if ( is_array( $v ) ) {
			$orig += (int) $v['orig'];
			$webp += (int) $v['webp'];
			$conv++;
		}
	}
	return compact( 'all', 'todo', 'orig', 'webp', 'conv' );
}

/* =========================================================================
 * Výkon
 * ====================================================================== */

function ek_maint_htaccess_rules(): array {
	return array(
		'<IfModule mod_expires.c>',
		'ExpiresActive On',
		'ExpiresByType text/css "access plus 1 year"',
		'ExpiresByType application/javascript "access plus 1 year"',
		'ExpiresByType text/javascript "access plus 1 year"',
		'ExpiresByType font/woff2 "access plus 1 year"',
		'ExpiresByType image/webp "access plus 1 year"',
		'ExpiresByType image/avif "access plus 1 year"',
		'ExpiresByType image/jpeg "access plus 1 year"',
		'ExpiresByType image/png "access plus 1 year"',
		'ExpiresByType image/gif "access plus 1 year"',
		'ExpiresByType image/svg+xml "access plus 1 year"',
		'ExpiresByType image/x-icon "access plus 1 year"',
		'</IfModule>',
		'<IfModule mod_headers.c>',
		'<FilesMatch "\.(css|js|woff2|webp|avif|jpe?g|png|gif|svg|ico)$">',
		'Header set Cache-Control "public, max-age=31536000"',
		'</FilesMatch>',
		'</IfModule>',
		'<IfModule mod_deflate.c>',
		'AddOutputFilterByType DEFLATE text/html text/css text/plain text/xml application/javascript application/json application/xml image/svg+xml',
		'</IfModule>',
	);
}

function ek_maint_thumb_ids(): array {
	global $wpdb;
	$ids = $wpdb->get_col(
		"SELECT DISTINCT pm.meta_value FROM {$wpdb->postmeta} pm JOIN {$wpdb->posts} p ON p.ID = pm.post_id
		 WHERE pm.meta_key = '_thumbnail_id' AND p.post_status = 'publish' AND pm.meta_value > 0"
	);
	// obrázky rubrik (šablona)
	$ids = array_merge( $ids, $wpdb->get_col( "SELECT meta_value FROM {$wpdb->termmeta} WHERE meta_key = 'ek_image' AND meta_value > 0" ) );
	return array_values( array_unique( array_map( 'intval', $ids ) ) );
}

function ek_maint_missing_sizes( int $id ): array {
	$meta  = wp_get_attachment_metadata( $id );
	$sizes = array_keys( (array) ( $meta['sizes'] ?? array() ) );
	$w     = (int) ( $meta['width'] ?? 0 );
	$h     = (int) ( $meta['height'] ?? 0 );
	$dims  = array( 'ek-card' => array( 640, 360 ), 'ek-featured' => array( 1100, 720 ), 'ek-hero' => array( 1520, 640 ) );
	return array_values( array_filter( array_keys( $dims ), fn( $s ) => ! in_array( $s, $sizes, true ) && ( $w > $dims[ $s ][0] || $h > $dims[ $s ][1] ) ) );
}

/* =========================================================================
 * Databáze
 * ====================================================================== */

function ek_db_tables(): array {
	global $wpdb;
	return $wpdb->get_results( $wpdb->prepare(
		"SELECT table_name AS t, engine AS e, table_rows AS r, data_length AS d, index_length AS i, data_free AS f
		 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name LIKE %s ORDER BY (data_length + index_length) DESC",
		$wpdb->esc_like( $wpdb->prefix ) . '%'
	) ) ?: array();
}

function ek_db_autoload(): array {
	global $wpdb;
	$where = "autoload IN ('yes','on','auto','auto-on')";
	return array(
		'total' => (int) $wpdb->get_var( "SELECT SUM(LENGTH(option_value)) FROM {$wpdb->options} WHERE $where" ),
		'count' => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->options} WHERE $where" ),
		'top'   => $wpdb->get_results( "SELECT option_name AS n, LENGTH(option_value) AS b FROM {$wpdb->options} WHERE $where ORDER BY b DESC LIMIT 10" ),
	);
}

/* =========================================================================
 * Média a odkazy (jen přehled)
 * ====================================================================== */

function ek_media_largest( int $limit = 15 ): array {
	global $wpdb;
	$rows = array();
	foreach ( $wpdb->get_col( "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'attachment'" ) as $id ) {
		$file = get_attached_file( (int) $id );
		$meta = wp_get_attachment_metadata( (int) $id ) ?: array();
		$size = (int) ( $meta['filesize'] ?? ( $file && is_file( $file ) ? filesize( $file ) : 0 ) );
		if ( ! empty( $meta['original_image'] ) && $file ) {
			$o = dirname( $file ) . '/' . $meta['original_image'];
			$size = max( $size, is_file( $o ) ? (int) filesize( $o ) : 0 );
		}
		$rows[] = array( (int) $id, $size, $file ? basename( $file ) : '?' );
	}
	usort( $rows, fn( $a, $b ) => $b[1] <=> $a[1] );
	return array_slice( $rows, 0, $limit );
}

/** Obrázky, které nejsou nikde použité (náhled, obrázek rubriky, nastavení šablony, obsah). */
function ek_media_unused(): array {
	global $wpdb;
	$used = array_map( 'intval', $wpdb->get_col( "SELECT meta_value FROM {$wpdb->postmeta} WHERE meta_key = '_thumbnail_id'" ) );
	$used = array_merge( $used, array_map( 'intval', $wpdb->get_col( "SELECT meta_value FROM {$wpdb->termmeta} WHERE meta_key = 'ek_image'" ) ) );
	foreach ( (array) get_theme_mods() as $v ) {
		if ( is_numeric( $v ) ) {
			$used[] = (int) $v;
		}
	}
	$used[]  = (int) get_option( 'site_icon' );
	$used    = array_flip( array_filter( $used ) );
	$content = implode( "\n", $wpdb->get_col( "SELECT post_content FROM {$wpdb->posts} WHERE post_type NOT IN ('attachment','revision') AND post_status NOT IN ('trash','auto-draft')" ) );
	$out     = array();
	foreach ( $wpdb->get_results( "SELECT ID, guid FROM {$wpdb->posts} WHERE post_type = 'attachment' AND post_mime_type LIKE 'image/%'" ) as $a ) {
		if ( isset( $used[ (int) $a->ID ] ) ) {
			continue;
		}
		$rel  = (string) get_post_meta( $a->ID, '_wp_attached_file', true );          // 2023/11/obrazek.png
		$stem = preg_replace( '#(-scaled)?\.[a-z0-9]+$#i', '', $rel );                 // 2023/11/obrazek
		if ( $stem && ( str_contains( $content, $stem ) || str_contains( $content, 'wp-image-' . $a->ID . '"' ) || str_contains( $content, '"id":' . $a->ID . ',' ) || str_contains( $content, '"id":' . $a->ID . '}' ) ) ) {
			continue;
		}
		$out[] = array( (int) $a->ID, $rel );
	}
	return $out;
}

/** Interní odkazy a obrázky v obsahu, které nikam nevedou. */
function ek_links_broken(): array {
	global $wpdb;
	$host  = preg_replace( '#^www\.#', '', (string) wp_parse_url( home_url(), PHP_URL_HOST ) );
	$terms = array();
	foreach ( get_terms( array( 'taxonomy' => array( 'category', 'post_tag' ), 'hide_empty' => false ) ) as $t ) {
		$terms[ untrailingslashit( (string) wp_parse_url( get_term_link( $t ), PHP_URL_PATH ) ) ] = true;
	}
	$out  = array();
	$seen = array();
	$rows = $wpdb->get_results( "SELECT ID, post_title, post_content FROM {$wpdb->posts} WHERE post_type IN ('post','page') AND post_status = 'publish'" );
	foreach ( $rows as $row ) {
		if ( ! preg_match_all( '#(?:href|src)=["\']([^"\']+)["\']#i', $row->post_content, $m ) ) {
			continue;
		}
		foreach ( array_unique( $m[1] ) as $url ) {
			$u = wp_parse_url( html_entity_decode( $url ) );
			$h = preg_replace( '#^www\.#', '', (string) ( $u['host'] ?? '' ) );
			if ( '' === $h && str_starts_with( (string) ( $u['path'] ?? '' ), '/' ) ) {
				$h = $host; // relativní odkaz /kontakt/
			}
			if ( $h !== $host || empty( $u['path'] ) || '/' === $u['path'] ) {
				continue;
			}
			$path = untrailingslashit( $u['path'] );
			$key  = $row->ID . $path;
			if ( isset( $seen[ $key ] ) ) {
				continue;
			}
			$seen[ $key ] = true;
			if ( str_contains( $path, '/wp-content/' ) ) {
				$ok = is_file( untrailingslashit( ABSPATH ) . $path );
			} elseif ( isset( $terms[ $path ] ) || preg_match( '#/(feed|page/\d+|wp-admin|wp-login\.php)#', $path ) ) {
				$ok = true;
			} else {
				$ok = url_to_postid( home_url( $path . '/' ) ) > 0 || get_page_by_path( ltrim( $path, '/' ) ) || url_to_postid( home_url( $path ) ) > 0;
			}
			if ( ! $ok ) {
				$out[] = array( (int) $row->ID, $row->post_title, $url );
			}
		}
	}
	return $out;
}

/* =========================================================================
 * Akce
 * ====================================================================== */

function ek_maint_handle( string $do ): string {
	global $wpdb;
	switch ( $do ) {
		case 'htaccess-on':
			require_once ABSPATH . 'wp-admin/includes/misc.php';
			return insert_with_markers( ABSPATH . '.htaccess', EK_HTACCESS_MARKER, ek_maint_htaccess_rules() )
				? 'Cache hlavičky přidány do .htaccess.' : 'Zápis do .htaccess se nepovedl (práva souboru).';
		case 'htaccess-off':
			require_once ABSPATH . 'wp-admin/includes/misc.php';
			insert_with_markers( ABSPATH . '.htaccess', EK_HTACCESS_MARKER, array() );
			return 'Cache hlavičky z .htaccess odebrány.';
		case 'regen':
			@set_time_limit( 120 );
			require_once ABSPATH . 'wp-admin/includes/image.php';
			$done  = 0;
			$start = time();
			foreach ( ek_maint_thumb_ids() as $id ) {
				if ( ! ek_maint_missing_sizes( $id ) ) {
					continue;
				}
				wp_update_image_subsizes( $id );
				$done++;
				if ( time() - $start > 40 ) {
					break;
				}
			}
			return sprintf( 'Náhledy vytvořeny u %d obrázků. Pokud nějaké zbývají, klikněte znovu.', $done );
		case 'webp-convert':
			@set_time_limit( 120 );
			$start = time();
			$done  = 0;
			foreach ( ek_webp_candidates( true ) as $id ) {
				ek_webp_convert( $id );
				$done++;
				if ( time() - $start > 25 ) {
					break;
				}
			}
			$left = count( ek_webp_candidates( true ) );
			return $left ? sprintf( 'Převedeno %d příloh, zbývá %d – klikněte znovu.', $done, $left ) : sprintf( 'Hotovo, převedeno %d příloh.', $done );
		case 'webp-on':
			update_option( EK_WEBP_OPTION, 1 );
			return 'Web teď posílá WebP kopie starších obrázků.';
		case 'webp-off':
			update_option( EK_WEBP_OPTION, 0 );
			return 'Doručování WebP kopií vypnuto – web posílá původní PNG/JPG.';
		case 'webp-delete':
			$n = 0;
			foreach ( ek_webp_candidates( false ) as $id ) {
				foreach ( ek_webp_files( $id ) as $file ) {
					if ( is_file( $file . '.webp' ) ) {
						wp_delete_file( $file . '.webp' );
						$n++;
					}
				}
			}
			delete_post_meta_by_key( EK_WEBP_META );
			update_option( EK_WEBP_OPTION, 0 );
			return sprintf( 'Smazáno WebP kopií: %d. Originály zůstaly.', $n );
		case 'db-optimize':
			$n = 0;
			foreach ( ek_db_tables() as $t ) {
				$wpdb->query( 'OPTIMIZE TABLE `' . esc_sql( $t->t ) . '`' );
				$n++;
			}
			return sprintf( 'Optimalizováno tabulek: %d.', $n );
		case 'revisions':
			$ids = $wpdb->get_col( "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'revision'" );
			foreach ( $ids as $id ) {
				wp_delete_post_revision( (int) $id );
			}
			return sprintf( 'Smazáno revizí: %d.', count( $ids ) );
		case 'drafts':
			$ids = $wpdb->get_col( "SELECT ID FROM {$wpdb->posts} WHERE post_status IN ('auto-draft','trash')" );
			foreach ( $ids as $id ) {
				wp_delete_post( (int) $id, true );
			}
			return sprintf( 'Smazáno automatických konceptů a položek z koše: %d.', count( $ids ) );
		case 'transients':
			delete_expired_transients( true );
			return 'Expirované transienty smazány.';
	}
	return '';
}

/* =========================================================================
 * Stránka
 * ====================================================================== */

function ek_kb( $bytes ): string {
	$bytes = (float) $bytes;
	return $bytes >= 1048576 ? number_format_i18n( $bytes / 1048576, 1 ) . ' MB' : number_format_i18n( $bytes / 1024, 0 ) . ' kB';
}

function ek_maint_page(): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	global $wpdb;
	$notice = '';
	$report = '';
	if ( 'POST' === $_SERVER['REQUEST_METHOD'] && check_admin_referer( 'ek_maint' ) ) {
		$report = sanitize_key( $_POST['ek_report'] ?? '' );
		$do     = sanitize_key( $_POST['ek_do'] ?? '' );
		if ( $do ) {
			$notice = ek_maint_handle( $do );
		}
	}
	$confirm = "return confirm('Opravdu? Tohle nejde vrátit bez zálohy.');";
	$btn     = function ( string $do, string $label, string $class = 'button', bool $danger = false, string $field = 'ek_do' ) use ( $confirm ) {
		printf( '<form method="post" style="display:inline-block;margin:0 8px 8px 0"%s>', $danger ? ' onsubmit="' . esc_attr( $confirm ) . '"' : '' );
		wp_nonce_field( 'ek_maint' );
		printf( '<input type="hidden" name="%s" value="%s"><button class="%s">%s</button></form>', esc_attr( $field ), esc_attr( $do ), esc_attr( $class ), esc_html( $label ) );
	};
	$table = function ( array $rows ) {
		echo '<table class="widefat striped" style="max-width:900px"><tbody>';
		foreach ( $rows as [ $a, $b ] ) {
			printf( '<tr><td>%s</td><td>%s</td></tr>', $a, $b );
		}
		echo '</tbody></table>';
	};

	echo '<div class="wrap"><h1>Údržba webu</h1>';
	if ( $notice ) {
		printf( '<div class="notice notice-success"><p>%s</p></div>', esc_html( $notice ) );
	}

	/* 1) Výkon */
	$ids     = ek_maint_thumb_ids();
	$missing = count( array_filter( $ids, fn( $id ) => (bool) ek_maint_missing_sizes( $id ) ) );
	$ht      = file_exists( ABSPATH . '.htaccess' ) ? (string) file_get_contents( ABSPATH . '.htaccess' ) : '';
	$has_ht  = str_contains( $ht, '# BEGIN ' . EK_HTACCESS_MARKER );
	$webp_ok = wp_image_editor_supports( array( 'mime_type' => 'image/webp' ) );
	echo '<h2>Výkon</h2>';
	$table( array(
		array( 'Náhledy pro šablonu (karty, hlavní článek, úvodní obrázek)', $missing ? sprintf( '⚠️ chybí u %d z %d obrázků', $missing, count( $ids ) ) : '✅ hotovo' ),
		array( 'Nové zmenšeniny ve formátu WebP', $webp_ok ? '✅ server podporuje' : '⚪ server WebP neumí' ),
		array( 'Cache hlavičky v .htaccess', $has_ht ? '✅ nastaveno' : '⚪ nenastaveno' ),
	) );
	echo '<p>';
	if ( $missing ) {
		$btn( 'regen', 'Vytvořit chybějící náhledy', 'button button-primary' );
	}
	$has_ht ? $btn( 'htaccess-off', 'Odebrat cache hlavičky' ) : $btn( 'htaccess-on', 'Přidat cache hlavičky', 'button button-primary' );
	echo '</p>';

	/* 2) WebP pro starší obrázky */
	$ws    = ek_webp_stats();
	$serve = (bool) get_option( EK_WEBP_OPTION );
	echo '<h2>WebP pro starší obrázky</h2>';
	echo '<p class="description" style="max-width:900px">Ke starším PNG/JPG vytvoří vedle originálu soubor <code>.webp</code> a web ho posílá místo originálu (v článcích, náhledech i v lightboxu). V databázi se nic nemění, originály zůstávají a doručování jde kdykoli vypnout.</p>';
	if ( ! $webp_ok ) {
		echo '<p>⚪ Server WebP neumí vytvořit.</p>';
	} else {
		$table( array(
			array( 'Příloh PNG/JPG', (string) $ws['all'] ),
			array( 'Převedeno', sprintf( '%d%s', $ws['conv'], $ws['todo'] ? sprintf( ' (zbývá %d)', $ws['todo'] ) : ' ✅' ) ),
			array( 'Úspora', $ws['orig'] ? sprintf( '%s → %s (−%d %%)', ek_kb( $ws['orig'] ), ek_kb( $ws['webp'] ), round( 100 - 100 * $ws['webp'] / $ws['orig'] ) ) : '–' ),
			array( 'Doručování WebP na webu', $serve ? '✅ zapnuto' : '⚪ vypnuto' ),
		) );
		echo '<p>';
		if ( $ws['todo'] ) {
			$btn( 'webp-convert', sprintf( 'Převést (%d zbývá)', $ws['todo'] ), 'button button-primary' );
		}
		if ( $ws['conv'] ) {
			$serve ? $btn( 'webp-off', 'Vypnout doručování WebP' ) : $btn( 'webp-on', 'Zapnout doručování WebP', 'button button-primary' );
			$btn( 'webp-delete', 'Smazat WebP kopie', 'button', true );
		}
		echo '</p>';
	}

	/* 3) Databáze */
	$tables = ek_db_tables();
	$al     = ek_db_autoload();
	$sum    = array_sum( array_map( fn( $t ) => $t->d + $t->i, $tables ) );
	$free   = array_sum( array_map( fn( $t ) => $t->f, $tables ) );
	echo '<h2>Databáze</h2>';
	printf( '<p>%d tabulek, celkem <strong>%s</strong>%s. Autoload (načítá se při každém požadavku): <strong>%s</strong> v %d volbách.</p>',
		count( $tables ), esc_html( ek_kb( $sum ) ), $free > 1048576 ? ', nevyužité místo ' . esc_html( ek_kb( $free ) ) : '', esc_html( ek_kb( $al['total'] ) ), $al['count'] );
	echo '<details style="max-width:900px"><summary style="cursor:pointer">Tabulky a největší autoload volby</summary><div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-top:8px">';
	echo '<table class="widefat striped"><thead><tr><th>Tabulka</th><th>Řádků</th><th>Velikost</th></tr></thead><tbody>';
	foreach ( $tables as $t ) {
		printf( '<tr><td><code>%s</code></td><td>%s</td><td>%s</td></tr>', esc_html( $t->t ), esc_html( number_format_i18n( (int) $t->r ) ), esc_html( ek_kb( $t->d + $t->i ) ) );
	}
	echo '</tbody></table><table class="widefat striped"><thead><tr><th>Volba (autoload)</th><th>Velikost</th></tr></thead><tbody>';
	foreach ( $al['top'] as $o ) {
		printf( '<tr><td><code>%s</code></td><td>%s</td></tr>', esc_html( $o->n ), esc_html( ek_kb( $o->b ) ) );
	}
	echo '</tbody></table></div></details><p>';
	$btn( 'db-optimize', 'Optimalizovat tabulky' );
	echo '</p>';

	/* 4) Média a odkazy */
	echo '<h2>Média a odkazy</h2><p class="description">Jen přehled – nic se nemaže. Kontrola projde celý web, může chvíli trvat.</p><p>';
	$btn( 'largest', 'Největší soubory', 'button', false, 'ek_report' );
	$btn( 'unused', 'Nepoužité obrázky', 'button', false, 'ek_report' );
	$btn( 'links', 'Rozbité interní odkazy', 'button', false, 'ek_report' );
	echo '</p>';
	if ( 'largest' === $report ) {
		echo '<table class="widefat striped" style="max-width:900px"><thead><tr><th>Soubor</th><th>Velikost</th><th>WebP</th></tr></thead><tbody>';
		foreach ( ek_media_largest() as [ $id, $size, $name ] ) {
			$w = get_post_meta( $id, EK_WEBP_META, true );
			printf( '<tr><td><a href="%s">%s</a></td><td>%s</td><td>%s</td></tr>', esc_url( get_edit_post_link( $id ) ), esc_html( $name ), esc_html( ek_kb( $size ) ), is_array( $w ) ? '✅ ' . esc_html( ek_kb( $w['webp'] ) ) . ' (všechny velikosti)' : '–' );
		}
		echo '</tbody></table>';
	} elseif ( 'unused' === $report ) {
		$list = ek_media_unused();
		printf( '<p>Nalezeno <strong>%d</strong> obrázků, které nejsou použité v žádném článku, stránce, náhledu, rubrice ani nastavení šablony. Před smazáním je zkontrolujte (mohou být odkazované zvenku).</p>', count( $list ) );
		if ( $list ) {
			echo '<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(150px,1fr));gap:10px;max-width:1100px">';
			foreach ( $list as [ $id, $rel ] ) {
				printf( '<a href="%s" style="text-decoration:none;font-size:11px;word-break:break-all">%s<br>%s</a>', esc_url( get_edit_post_link( $id ) ), wp_get_attachment_image( $id, 'thumbnail', false, array( 'style' => 'width:100%;height:90px;object-fit:cover;border-radius:4px' ) ), esc_html( $rel ) );
			}
			echo '</div>';
		}
	} elseif ( 'links' === $report ) {
		$list = ek_links_broken();
		if ( ! $list ) {
			echo '<p>✅ Žádné rozbité interní odkazy ani obrázky.</p>';
		} else {
			echo '<table class="widefat striped" style="max-width:1100px"><thead><tr><th>Článek</th><th>Odkaz</th></tr></thead><tbody>';
			foreach ( $list as [ $id, $title, $url ] ) {
				printf( '<tr><td><a href="%s">%s</a></td><td><code style="word-break:break-all">%s</code></td></tr>', esc_url( get_edit_post_link( $id ) ), esc_html( $title ), esc_html( $url ) );
			}
			echo '</tbody></table>';
		}
	}

	/* 5) Úklid obsahu */
	$revisions = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'revision'" );
	$drafts    = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_status IN ('auto-draft','trash')" );
	echo '<h2>Úklid obsahu</h2>';
	$table( array(
		array( 'Revize článků', (string) $revisions ),
		array( 'Automatické koncepty a koš', (string) $drafts ),
	) );
	echo '<p>';
	if ( $revisions ) {
		$btn( 'revisions', 'Smazat revize', 'button', true );
	}
	if ( $drafts ) {
		$btn( 'drafts', 'Vysypat koncepty a koš', 'button', true );
	}
	$btn( 'transients', 'Smazat expirované transienty' );
	echo '</p></div>';
}
