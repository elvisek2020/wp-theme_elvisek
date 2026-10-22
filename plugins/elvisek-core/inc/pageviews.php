<?php
/**
 * EKC module: pageviews
 * Version: 1.0.0
 * Requires: common
 * Replaces: post-views-counter, wp-statistics, jetpack-stats
 * Summary: Počítadlo zobrazení článků a stránek bez cookies a osobních údajů: denní součty, nejčtenější články ve Zdraví webu a sloupec v přehledu.
 */

defined( 'ABSPATH' ) || exit;

/*
 * - Počítá se v prohlížeči: na článku (stránce) vloží plugin do patičky malý inline skript, který
 *   jednou za relaci karty (sessionStorage, ne cookie) pošle navigator.sendBeacon na
 *   POST /wp-json/ekc/v1/hit s ID. Funguje i za cache hostingu, protože se nepočítá při
 *   generování stránky. Prohlížeč řízený automatem (navigator.webdriver) nepočítá.
 * - Server: jen publikovaný obsah povolených typů (filtr ekc_pageviews_post_types, výchozí post
 *   a page), ne přihlášení (cookie se ověří), ne roboti podle User-Agent, nejvýš
 *   EKC_PAGEVIEWS_LIMIT hitů za 10 minut z jedné IP (IP jen jako krátkodobý hash v transientu,
 *   neukládá se). Nic dalšího o návštěvníkovi se neukládá → nepotřebuje souhlas s cookies.
 * - Data: tabulka {prefix}ekc_pageviews (post_id, day, views), jeden řádek na článek a den,
 *   atomické INSERT … ON DUPLICATE KEY UPDATE. Vytvoří ji krok migrace pageviews/table
 *   (a pojistka při prvním zápisu). Odinstalace ji smaže, Údržba ji chrání (vzor ekc_%).
 * - Výstupy: řádky ve widgetu Zdraví webu (dnes, 7 a 30 dní, nejčtenější za 30 dní), sloupec
 *   Zobrazení v přehledu článků a stránek (30 dní / celkem).
 *
 * Veřejné funkce: ekc_pageviews_count( $post_id, $days = 0 ), ekc_pageviews_sum( $days ),
 * ekc_pageviews_top( $days = 30, $limit = 5, $post_type = 'post' ) – pro box „Nejčtenější“ v šabloně.
 * Filtry: ekc_pageviews_post_types( ['post','page'] ), ekc_pageviews_count_hit( true, $post_id ),
 * ekc_pageviews_bot_pattern( $regex ).
 */

ekc_settings_register(
	'pageviews',
	array(
		'type'    => 'bool',
		'default' => true,
		'label'   => 'Počítadlo zobrazení',
		'help'    => 'Počet zobrazení článků a stránek bez cookies a bez osobních údajů (Zdraví webu, sloupec v přehledu článků).',
		'section' => 'Obsah',
	)
);

const EKC_PAGEVIEWS_LIMIT = 60; // hitů z jedné IP za 10 minut

/**
 * Název tabulky.
 *
 * @return string
 */
function ekc_pageviews_table(): string {
	global $wpdb;
	return $wpdb->prefix . 'ekc_pageviews';
}

/**
 * Počítat?
 *
 * @return bool
 */
function ekc_pageviews_enabled(): bool {
	return (bool) ekc_opt( 'pageviews' );
}

/**
 * Typy obsahu, které se počítají.
 *
 * @return string[]
 */
function ekc_pageviews_post_types(): array {
	return array_values( array_filter( array_map( 'strval', (array) apply_filters( 'ekc_pageviews_post_types', array( 'post', 'page' ) ) ), 'post_type_exists' ) );
}

/**
 * Vytvoří tabulku (idempotentní).
 *
 * @return bool
 */
function ekc_pageviews_install(): bool {
	global $wpdb;
	$table   = ekc_pageviews_table();
	$charset = $wpdb->get_charset_collate();
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- název tabulky z prefixu
	$wpdb->query( "CREATE TABLE IF NOT EXISTS {$table} ( post_id BIGINT UNSIGNED NOT NULL, day DATE NOT NULL, views INT UNSIGNED NOT NULL DEFAULT 0, PRIMARY KEY  (post_id, day), KEY day (day) ) {$charset}" );
	$ok = $table === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) );
	if ( $ok ) {
		update_option( 'ekc_pageviews_db', 1, false );
	}
	return $ok;
}

add_filter(
	'ekc_migrations',
	static function ( array $steps ): array {
		$steps['pageviews/table'] = 'ekc_pageviews_install';
		return $steps;
	}
);

/* -------------------------------------------------------------------------
 * Měření
 * ---------------------------------------------------------------------- */

add_action(
	'wp_footer',
	static function () {
		if ( ! ekc_pageviews_enabled() || ! is_singular( ekc_pageviews_post_types() ) || is_preview() || is_user_logged_in() ) {
			return;
		}
		$id = (int) get_queried_object_id();
		if ( ! $id ) {
			return;
		}
		$js = sprintf(
			'(function(){var k="ekc-pv-%1$d";try{if(sessionStorage.getItem(k))return;sessionStorage.setItem(k,"1")}catch(e){}if(navigator.webdriver||!navigator.sendBeacon)return;navigator.sendBeacon(%2$s,new URLSearchParams({id:"%1$d"}))})();',
			$id,
			wp_json_encode( rest_url( 'ekc/v1/hit' ), JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP )
		);
		wp_print_inline_script_tag( $js );
	},
	99
);

add_action(
	'rest_api_init',
	static function () {
		register_rest_route(
			'ekc/v1',
			'/hit',
			array(
				'methods'             => 'POST',
				'callback'            => 'ekc_pageviews_hit',
				'permission_callback' => '__return_true',
				'args'                => array(
					'id' => array(
						'type'     => 'integer',
						'required' => true,
						'minimum'  => 1,
					),
				),
			)
		);
	}
);

/**
 * Zapíše jedno zobrazení. Odpověď je vždy 204 (bez informace, jestli se započítalo).
 *
 * @param WP_REST_Request $request Požadavek.
 * @return WP_REST_Response
 */
function ekc_pageviews_hit( WP_REST_Request $request ): WP_REST_Response {
	$response = new WP_REST_Response( null, 204 );
	$response->header( 'Cache-Control', 'no-store' );
	$id   = (int) $request->get_param( 'id' );
	$post = get_post( $id );
	if ( ! ekc_pageviews_enabled() || ! $post || 'publish' !== $post->post_status || ! in_array( $post->post_type, ekc_pageviews_post_types(), true ) || ! empty( $post->post_password ) ) {
		return $response;
	}
	// Přihlášený uživatel (REST bez nonce ho jinak nepozná).
	if ( wp_validate_auth_cookie( '', 'logged_in' ) ) {
		return $response;
	}
	$ua  = (string) $request->get_header( 'user_agent' );
	$bot = (string) apply_filters( 'ekc_pageviews_bot_pattern', '/bot|crawl|spider|slurp|preview|fetch|curl|wget|python|headless|lighthouse|monitor/i' );
	if ( '' === $ua || preg_match( $bot, $ua ) ) {
		return $response;
	}
	$key   = 'ekc_pv_' . md5( ekc_client_ip() . '|' . wp_date( 'Y-m-d' ) . '|' . wp_salt( 'nonce' ) );
	$count = (int) get_transient( $key );
	if ( $count >= EKC_PAGEVIEWS_LIMIT ) {
		return $response;
	}
	set_transient( $key, $count + 1, 10 * MINUTE_IN_SECONDS );
	if ( ! apply_filters( 'ekc_pageviews_count_hit', true, $id ) ) {
		return $response;
	}
	if ( ! get_option( 'ekc_pageviews_db' ) && ! ekc_pageviews_install() ) {
		return $response;
	}
	global $wpdb;
	$table = ekc_pageviews_table();
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- název tabulky z prefixu
	$wpdb->query( $wpdb->prepare( "INSERT INTO {$table} (post_id, day, views) VALUES (%d, %s, 1) ON DUPLICATE KEY UPDATE views = views + 1", $id, wp_date( 'Y-m-d' ) ) );
	return $response;
}

/* -------------------------------------------------------------------------
 * Čtení
 * ---------------------------------------------------------------------- */

/**
 * Den, od kterého se počítá („posledních N dní“ včetně dneška), nebo '' = celkem.
 *
 * @param int $days Počet dní (0 = celkem).
 * @return string Y-m-d nebo ''.
 */
function ekc_pageviews_since( int $days ): string {
	return $days > 0 ? wp_date( 'Y-m-d', time() - ( $days - 1 ) * DAY_IN_SECONDS ) : '';
}

/**
 * Zobrazení jednoho článku.
 *
 * @param int $post_id ID.
 * @param int $days    Posledních N dní (0 = celkem).
 * @return int
 */
function ekc_pageviews_count( int $post_id, int $days = 0 ): int {
	if ( ! get_option( 'ekc_pageviews_db' ) ) {
		return 0;
	}
	global $wpdb;
	$table = ekc_pageviews_table();
	$since = ekc_pageviews_since( $days );
	// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- název tabulky z prefixu
	return (int) ( '' === $since
		? $wpdb->get_var( $wpdb->prepare( "SELECT SUM(views) FROM {$table} WHERE post_id = %d", $post_id ) )
		: $wpdb->get_var( $wpdb->prepare( "SELECT SUM(views) FROM {$table} WHERE post_id = %d AND day >= %s", $post_id, $since ) ) );
	// phpcs:enable
}

/**
 * Součet zobrazení celého webu.
 *
 * @param int $days Posledních N dní (1 = dnes, 0 = celkem).
 * @return int
 */
function ekc_pageviews_sum( int $days ): int {
	if ( ! get_option( 'ekc_pageviews_db' ) ) {
		return 0;
	}
	global $wpdb;
	$table = ekc_pageviews_table();
	$since = ekc_pageviews_since( $days );
	// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- název tabulky z prefixu
	return (int) ( '' === $since
		? $wpdb->get_var( "SELECT SUM(views) FROM {$table}" )
		: $wpdb->get_var( $wpdb->prepare( "SELECT SUM(views) FROM {$table} WHERE day >= %s", $since ) ) );
	// phpcs:enable
}

/**
 * Nejčtenější publikovaný obsah.
 *
 * @param int    $days      Posledních N dní (0 = celkem).
 * @param int    $limit     Počet.
 * @param string $post_type Typ obsahu ('' = všechny počítané).
 * @return array<int,int> post_id => zobrazení, sestupně
 */
function ekc_pageviews_top( int $days = 30, int $limit = 5, string $post_type = 'post' ): array {
	if ( ! get_option( 'ekc_pageviews_db' ) ) {
		return array();
	}
	global $wpdb;
	$table = ekc_pageviews_table();
	$since = ekc_pageviews_since( $days );
	$types = '' === $post_type ? ekc_pageviews_post_types() : array( $post_type );
	if ( ! $types ) {
		return array();
	}
	$in   = implode( ',', array_fill( 0, count( $types ), '%s' ) );
	$args = array_merge( $types, array( '' === $since ? '1000-01-01' : $since, max( 1, $limit ) ) );
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- tabulka z prefixu, placeholdery sestavené výše
	$rows = $wpdb->get_results( $wpdb->prepare( "SELECT v.post_id, SUM(v.views) AS n FROM {$table} v JOIN {$wpdb->posts} p ON p.ID = v.post_id WHERE p.post_status = 'publish' AND p.post_type IN ({$in}) AND v.day >= %s GROUP BY v.post_id ORDER BY n DESC LIMIT %d", $args ) );
	$out  = array();
	foreach ( (array) $rows as $row ) {
		$out[ (int) $row->post_id ] = (int) $row->n;
	}
	return $out;
}

/* -------------------------------------------------------------------------
 * Administrace: sloupec v přehledu a Zdraví webu
 * ---------------------------------------------------------------------- */

add_action(
	'admin_init',
	static function () {
		if ( ! ekc_pageviews_enabled() ) {
			return;
		}
		foreach ( ekc_pageviews_post_types() as $type ) {
			add_filter(
				"manage_{$type}_posts_columns",
				static function ( array $cols ): array {
					$cols['ekc_views'] = 'Zobrazení';
					return $cols;
				}
			);
			add_action(
				"manage_{$type}_posts_custom_column",
				static function ( $col, $post_id ) {
					if ( 'ekc_views' === $col ) {
						printf(
							'<span title="%1$s">%2$s</span> <span style="color:#646970">/ %3$s</span>',
							esc_attr( 'Za 30 dní / celkem' ),
							esc_html( number_format_i18n( ekc_pageviews_count( (int) $post_id, 30 ) ) ),
							esc_html( number_format_i18n( ekc_pageviews_count( (int) $post_id ) ) )
						);
					}
				},
				10,
				2
			);
		}
	}
);

add_filter(
	'ekc_health_rows',
	static function ( array $rows ): array {
		if ( ! ekc_pageviews_enabled() ) {
			return $rows;
		}
		$rows['Návštěvnost']['Zobrazení dnes / 7 / 30 dní'] = esc_html(
			implode( ' / ', array_map( static fn( $d ) => number_format_i18n( ekc_pageviews_sum( $d ) ), array( 1, 7, 30 ) ) )
		);
		$top = ekc_pageviews_top( 30, 5 );
		if ( $top ) {
			$items = array();
			foreach ( $top as $id => $n ) {
				$items[] = '<a href="' . esc_url( (string) get_permalink( $id ) ) . '">' . esc_html( wp_trim_words( (string) get_the_title( $id ), 6, '…' ) ) . '</a> (' . esc_html( number_format_i18n( $n ) ) . ')';
			}
			$rows['Návštěvnost']['Nejčtenější za 30 dní'] = implode( '<br>', $items );
		}
		return $rows;
	}
);
