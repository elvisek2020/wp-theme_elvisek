<?php
/**
 * Údržba webu — Nástroje → Údržba webu.
 *
 *  1) Sjednotit obrázky na WebP: starší PNG/JPG natrvalo převede na .webp (soubory i odkazy v obsahu),
 *     staré adresy přesměruje. Sekce je vidět jen dokud nějaké PNG/JPG zbývají.
 *  2) Databáze: velikost tabulek, autoload, optimalizace
 *  3) Média a odkazy: největší soubory, nepoužité obrázky (hromadné smazání vybraných), rozbité interní odkazy
 *  4) Úklid obsahu: revize, koncepty a koš, transienty
 *
 * Nově nahrané obrázky převádí na WebP rovnou při nahrání inc/core.php.
 */

defined( 'ABSPATH' ) || exit;

const EK_MAINT_SLUG = 'ek-udrzba';

/* -------------------------------------------------------------------------
 * Jednorázový úklid po odebraných nástrojích
 * ---------------------------------------------------------------------- */
add_action( 'admin_init', function () {
	$v = get_option( 'ek_maint_v' );
	if ( '3' === $v ) {
		return;
	}
	if ( '2' !== $v ) {
		delete_option( 'ek_mig_deactivated' );
		delete_post_meta_by_key( '_ek_premigration' );
	}
	// v3: odebrané nástroje Výkon, Bloky kódu a WebP kopie – jejich nastavení a zálohy už nejsou potřeba.
	// (Zálohy bloků kódu by po sjednocení editoru vrátily starý obsah, proto pryč.)
	delete_option( 'ek_webp_serve' );
	delete_post_meta_by_key( '_ek_code_backup' );
	update_option( 'ek_maint_v', '3', false );
} );

// Staré záložky na „Přechod ElvisEK“ → nová stránka (skrytá stránka, jinak WP ohlásí „nemáte oprávnění“).
add_action( 'admin_menu', function () {
	$hook = add_submenu_page( '', 'Údržba webu', '', 'manage_options', 'ek-migrace', '__return_null' );
	add_action( 'load-' . $hook, function () {
		wp_safe_redirect( admin_url( 'tools.php?page=' . EK_MAINT_SLUG ) );
		exit;
	} );
} );

add_action( 'admin_menu', function () {
	add_management_page( 'Údržba webu', 'Údržba webu', 'manage_options', EK_MAINT_SLUG, 'ek_maint_page' );
} );

/* =========================================================================
 * Soubory přílohy
 * ====================================================================== */

/** Všechny soubory přílohy na disku (originál, případný nezmenšený originál, zmenšeniny). */
function ek_attachment_files( int $id ): array {
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
	return array_values( array_unique( array_filter( $files, 'is_file' ) ) );
}

// Při smazání přílohy smazat i případné staré WebP kopie (soubor.png.webp).
add_action( 'delete_attachment', function ( $id ) {
	foreach ( ek_attachment_files( (int) $id ) as $file ) {
		if ( is_file( $file . '.webp' ) ) {
			wp_delete_file( $file . '.webp' );
		}
	}
} );

/* =========================================================================
 * Sjednotit obrázky na WebP (natrvalo)
 * ====================================================================== */

/** ID příloh, které jsou ještě PNG/JPG. */
function ek_webp_pending(): array {
	global $wpdb;
	return array_map( 'intval', $wpdb->get_col(
		"SELECT p.ID FROM {$wpdb->posts} p LEFT JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key = '_ek_webp_fail'
		 WHERE p.post_type = 'attachment' AND p.post_mime_type IN ('image/png','image/jpeg') AND m.meta_id IS NULL ORDER BY p.ID DESC"
	) );
}

/**
 * Převede jednu přílohu natrvalo na WebP: všechny velikosti, metadata, odkazy v obsahu.
 * Při chybě nechá přílohu beze změny. Vrací ušetřené bajty nebo WP_Error.
 */
function ek_webp_unify( int $id ) {
	global $wpdb;
	$main = get_attached_file( $id );
	$rel  = (string) get_post_meta( $id, '_wp_attached_file', true );
	if ( ! $main || ! is_file( $main ) || '' === $rel ) {
		return new WP_Error( 'missing', 'Soubor chybí' );
	}
	$dir     = dirname( $main );
	$subdir  = trim( dirname( $rel ), './' );
	$meta    = wp_get_attachment_metadata( $id ) ?: array();
	$names   = array( basename( $main ) );
	if ( ! empty( $meta['original_image'] ) ) {
		$names[] = $meta['original_image'];
	}
	foreach ( (array) ( $meta['sizes'] ?? array() ) as $size ) {
		if ( ! empty( $size['file'] ) ) {
			$names[] = $size['file'];
		}
	}
	// Hlavní soubor už je WebP (nahráno s převodem zmenšenin) a PNG/JPG je jen záložní originál → ten stačí smazat.
	if ( ! empty( $meta['original_image'] ) && preg_match( '/\.webp$/i', $main ) ) {
		$orig  = $dir . '/' . $meta['original_image'];
		$freed = is_file( $orig ) ? (int) filesize( $orig ) : 0;
		unset( $meta['original_image'] );
		$names = array_values( array_diff( $names, array( basename( $orig ) ) ) );
		wp_update_attachment_metadata( $id, $meta );
		if ( is_file( $orig ) ) {
			wp_delete_file( $orig );
		}
		if ( is_file( $orig . '.webp' ) ) {
			wp_delete_file( $orig . '.webp' );
		}
	}
	$map     = array(); // starý název => nový název
	$created = array();
	$before  = 0;
	$after   = 0;
	foreach ( array_unique( $names ) as $name ) {
		if ( ! preg_match( '/\.(png|jpe?g)$/i', $name ) ) {
			continue; // zmenšenina už je WebP
		}
		$old = $dir . '/' . $name;
		if ( ! is_file( $old ) ) {
			continue;
		}
		$new_name = preg_replace( '/\.(png|jpe?g)$/i', '.webp', $name );
		if ( is_file( $dir . '/' . $new_name ) ) {
			$new_name = wp_unique_filename( $dir, $new_name );
		}
		$new = $dir . '/' . $new_name;
		if ( is_file( $old . '.webp' ) ) {
			copy( $old . '.webp', $new ); // hotová kopie z dřívějška
		} else {
			$editor = wp_get_image_editor( $old );
			if ( is_wp_error( $editor ) ) {
				$editor = null;
			} else {
				$editor->set_quality( 82 );
				if ( method_exists( $editor, 'maybe_exif_rotate' ) ) {
					$editor->maybe_exif_rotate(); // WebP nenese EXIF – otočit podle něj už teď
				}
				$saved = $editor->save( $new, 'image/webp' );
				if ( is_wp_error( $saved ) ) {
					$editor = null;
				}
			}
			if ( ! $editor || ! is_file( $new ) ) {
				array_map( 'wp_delete_file', $created );
				return new WP_Error( 'convert', 'Převod se nepovedl: ' . $name );
			}
		}
		$created[]    = $new;
		$map[ $name ] = $new_name;
		$before      += (int) filesize( $old );
		$after       += (int) filesize( $new );
	}
	if ( ! $map ) {
		$wpdb->update( $wpdb->posts, array( 'post_mime_type' => 'image/webp' ), array( 'ID' => $id ) );
		delete_post_meta( $id, '_ek_webp' );
		clean_post_cache( $id );
		return $freed ?? 0;
	}

	// Metadata přílohy
	$main_new = $dir . '/' . ( $map[ basename( $main ) ] ?? basename( $main ) );
	if ( ! empty( $meta['file'] ) ) {
		$meta['file'] = ( $subdir ? $subdir . '/' : '' ) . basename( $main_new );
	}
	if ( ! empty( $meta['original_image'] ) && isset( $map[ $meta['original_image'] ] ) ) {
		$meta['original_image'] = $map[ $meta['original_image'] ];
	}
	foreach ( (array) ( $meta['sizes'] ?? array() ) as $k => $size ) {
		if ( isset( $map[ $size['file'] ] ) ) {
			$meta['sizes'][ $k ]['file']      = $map[ $size['file'] ];
			$meta['sizes'][ $k ]['mime-type'] = 'image/webp';
			$meta['sizes'][ $k ]['filesize']  = (int) filesize( $dir . '/' . $map[ $size['file'] ] );
		}
	}
	$meta['filesize'] = (int) filesize( $main_new );
	update_attached_file( $id, $main_new );
	wp_update_attachment_metadata( $id, $meta );
	$wpdb->update(
		$wpdb->posts,
		array( 'post_mime_type' => 'image/webp', 'guid' => str_replace( array_keys( $map ), array_values( $map ), (string) get_post_field( 'guid', $id ) ) ),
		array( 'ID' => $id )
	);

	// Odkazy v obsahu (články, stránky, bloky; revize ne – staré adresy stejně přesměrujeme)
	$prefix = '/' . ( $subdir ? $subdir . '/' : '' );
	foreach ( $map as $from => $to ) {
		$wpdb->query( $wpdb->prepare(
			"UPDATE {$wpdb->posts} SET post_content = REPLACE(post_content, %s, %s) WHERE post_type NOT IN ('revision','attachment') AND post_content LIKE %s",
			$prefix . $from, $prefix . $to, '%' . $wpdb->esc_like( $prefix . $from ) . '%'
		) );
	}

	// Staré soubory pryč (i se starými kopiemi soubor.png.webp)
	foreach ( array_keys( $map ) as $from ) {
		wp_delete_file( $dir . '/' . $from );
		if ( is_file( $dir . '/' . $from . '.webp' ) ) {
			wp_delete_file( $dir . '/' . $from . '.webp' );
		}
	}
	delete_post_meta( $id, '_ek_webp' );
	clean_post_cache( $id );
	return max( 0, $before - $after ) + ( $freed ?? 0 );
}

/** Dávka převodů (časově omezená, aby nespadl PHP limit). */
function ek_webp_unify_batch(): string {
	@set_time_limit( 120 );
	$start = time();
	$done  = 0;
	$saved = 0;
	$fail  = array();
	foreach ( ek_webp_pending() as $id ) {
		$r = ek_webp_unify( $id );
		if ( is_wp_error( $r ) ) {
			$fail[] = $id;
			update_post_meta( $id, '_ek_webp_fail', $r->get_error_message() ); // další dávka ji přeskočí
		} else {
			$done++;
			$saved += $r;
		}
		if ( time() - $start > 40 ) {
			break;
		}
	}
	$left = count( ek_webp_pending() );
	return sprintf( 'Převedeno %d obrázků, ušetřeno %s.%s%s', $done, ek_kb( $saved ), $left ? sprintf( ' Zbývá %d – klikněte znovu.', $left ) : ' Hotovo, všechny obrázky jsou WebP.', $fail ? ' Nepovedlo se: ID ' . implode( ', ', $fail ) . '.' : '' );
}

// Staré adresy PNG/JPG (odkazy zvenku, Google Obrázky) → 301 na WebP verzi.
add_action( 'template_redirect', function () {
	if ( ! is_404() ) {
		return;
	}
	$path = (string) wp_parse_url( $_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH );
	$up   = wp_get_upload_dir();
	$base = (string) wp_parse_url( $up['baseurl'], PHP_URL_PATH );
	if ( ! str_starts_with( $path, $base . '/' ) || ! preg_match( '/^(.+)\.(png|jpe?g)$/i', rawurldecode( substr( $path, strlen( $base ) ) ), $m ) || str_contains( $m[1], '..' ) ) {
		return;
	}
	if ( is_file( $up['basedir'] . $m[1] . '.webp' ) ) {
		wp_redirect( $up['baseurl'] . $m[1] . '.webp', 301 );
		exit;
	}
} );

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
 * Média a odkazy
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

/** Velikost všech souborů přílohy na disku (originál, zmenšeniny, WebP kopie). */
function ek_media_bytes( int $id ): int {
	$bytes = 0;
	foreach ( ek_attachment_files( $id ) as $file ) {
		$bytes += (int) filesize( $file );
		if ( is_file( $file . '.webp' ) ) {
			$bytes += (int) filesize( $file . '.webp' ); // stará WebP kopie (soubor.png.webp)
		}
	}
	return $bytes;
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
			if ( str_contains( $path, '/wp-content/' ) || preg_match( '#\.(?!html?$|php$)[a-z0-9]{2,5}$#i', $path ) ) {
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
		case 'webp-unify':
			return ek_webp_unify_batch();
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
		case 'media-delete':
			// Maže jen to, co je i teď opravdu nepoužité (kontrola znovu na serveru).
			$want   = array_map( 'intval', (array) ( $_POST['ek_ids'] ?? array() ) );
			$unused = array_flip( array_map( fn( $r ) => $r[0], ek_media_unused() ) );
			$done   = 0;
			$bytes  = 0;
			@set_time_limit( 120 );
			foreach ( array_unique( $want ) as $id ) {
				if ( ! isset( $unused[ $id ] ) || ! current_user_can( 'delete_post', $id ) ) {
					continue;
				}
				$size = ek_media_bytes( $id );
				if ( wp_delete_attachment( $id, true ) ) {
					$done++;
					$bytes += $size;
				}
			}
			return $done ? sprintf( 'Smazáno %d obrázků, uvolněno %s.', $done, ek_kb( $bytes ) ) : 'Nic nebylo smazáno.';
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

	/* 1) Sjednotit obrázky na WebP – jen dokud nějaké PNG/JPG zbývají */
	$pending = ek_webp_pending();
	$failed  = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = '_ek_webp_fail'" );
	if ( $pending ) {
		echo '<h2>Sjednotit obrázky na WebP</h2>';
		if ( ! wp_image_editor_supports( array( 'mime_type' => 'image/webp' ) ) ) {
			echo '<p>⚪ Server WebP neumí vytvořit.</p>';
		} else {
			printf( '<p style="max-width:900px">Na webu je ještě <strong>%d</strong> obrázků ve formátu PNG/JPG. Převod je natrvalo: vytvoří <code>.webp</code> (všechny velikosti), přepíše odkazy v článcích a stránkách a původní soubory smaže. Staré adresy obrázků se automaticky přesměrují na WebP. <strong>Před převodem si udělejte zálohu</strong> – nejde vrátit.</p><p>', count( $pending ) );
			$btn( 'webp-unify', sprintf( 'Převést na WebP (%d)', count( $pending ) ), 'button button-primary', true );
			echo '</p>';
		}
	}
	if ( $failed ) {
		printf( '<p>⚠️ %d obrázků se nepodařilo převést (poškozený nebo chybějící soubor) – zkontrolujte je v Médiích.</p>', $failed );
	}

	/* 2) Databáze */
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

	/* 3) Média a odkazy */
	echo '<h2>Média a odkazy</h2><p class="description">Kontrola projde celý web, může chvíli trvat. Smazat jde jen to, co v přehledu nepoužitých obrázků sami vyberete.</p><p>';
	$btn( 'largest', 'Největší soubory', 'button', false, 'ek_report' );
	$btn( 'unused', 'Nepoužité obrázky', 'button', false, 'ek_report' );
	$btn( 'links', 'Rozbité interní odkazy', 'button', false, 'ek_report' );
	echo '</p>';
	if ( 'largest' === $report ) {
		echo '<table class="widefat striped" style="max-width:900px"><thead><tr><th>Soubor</th><th>Velikost</th></tr></thead><tbody>';
		foreach ( ek_media_largest() as [ $id, $size, $name ] ) {
			printf( '<tr><td><a href="%s">%s</a></td><td>%s</td></tr>', esc_url( get_edit_post_link( $id ) ), esc_html( $name ), esc_html( ek_kb( $size ) ) );
		}
		echo '</tbody></table>';
	} elseif ( 'unused' === $report ) {
		$list = ek_media_unused();
		$sizes = array();
		foreach ( $list as [ $id ] ) {
			$sizes[ $id ] = ek_media_bytes( $id );
		}
		printf( '<p>Nalezeno <strong>%d</strong> obrázků (%s), které nejsou použité v žádném článku, stránce, náhledu, rubrice ani nastavení šablony. Před smazáním je zkontrolujte – mohou být odkazované zvenku.</p>', count( $list ), esc_html( ek_kb( array_sum( $sizes ) ) ) );
		if ( $list ) {
			echo '<form method="post" id="ek-unused" onsubmit="var n=this.querySelectorAll(\'input[name=&quot;ek_ids[]&quot;]:checked\').length;return n>0&&confirm(\'Trvale smazat \'+n+\' obrázků i se všemi velikostmi? Nejde vrátit bez zálohy.\');">';
			wp_nonce_field( 'ek_maint' );
			echo '<input type="hidden" name="ek_do" value="media-delete"><input type="hidden" name="ek_report" value="unused">';
			echo '<p style="display:flex;gap:8px;align-items:center;flex-wrap:wrap"><button type="button" class="button" data-ek-all="1">Vybrat vše</button><button type="button" class="button" data-ek-all="0">Zrušit výběr</button><span data-ek-count style="color:#646970">Vybráno 0</span><button class="button button-primary" style="background:#b32d2e;border-color:#b32d2e">Smazat vybrané</button></p>';
			echo '<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(160px,1fr));gap:10px;max-width:1100px">';
			foreach ( $list as [ $id, $rel ] ) {
				printf(
					'<label style="display:block;font-size:11px;word-break:break-all;border:1px solid #dcdcde;border-radius:6px;padding:6px;background:#fff;cursor:pointer"><span style="display:flex;justify-content:space-between;align-items:center;margin-bottom:4px"><input type="checkbox" name="ek_ids[]" value="%1$d"> <span style="color:#646970">%4$s</span></span>%2$s<br>%3$s <a href="%5$s" target="_blank">upravit</a></label>',
					$id,
					wp_get_attachment_image( $id, 'thumbnail', false, array( 'style' => 'width:100%;height:90px;object-fit:cover;border-radius:4px' ) ),
					esc_html( $rel ),
					esc_html( ek_kb( $sizes[ $id ] ) ),
					esc_url( get_edit_post_link( $id ) )
				);
			}
			echo '</div></form>';
			echo "<script>(()=>{const f=document.getElementById('ek-unused');if(!f)return;const boxes=[...f.querySelectorAll('input[type=checkbox]')];const c=f.querySelector('[data-ek-count]');const sync=()=>{c.textContent='Vybráno '+boxes.filter(b=>b.checked).length+' z '+boxes.length;};f.addEventListener('change',sync);f.querySelectorAll('[data-ek-all]').forEach(b=>b.addEventListener('click',()=>{boxes.forEach(x=>x.checked=b.dataset.ekAll==='1');sync();}));sync();})();</script>";
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

	/* 4) Úklid obsahu */
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
