<?php
/**
 * EKT module: github
 * Version: 1.0.1
 * Requires: common
 * Replaces: github-embed, simple-github-card
 * Assets: js/github-editor.js, css/github.css
 * Summary: Blok „GitHub repozitář“: karta s popisem, hvězdičkami, posledním vydáním a licencí; data stahuje server s cache.
 */

defined( 'ABSPATH' ) || exit;

/*
 * Blok ekt/github (atribut repo „owner/repo“, stačí i celá adresa) vykresluje server. Starší
 * blok elvisek/github v obsahu je alias se stejným vykreslením (skrytý v inserteru), obsah článků
 * se nepřepisuje. Návštěvník se s GitHubem nespojuje (žádné cizí skripty ani obrázky).
 *
 * Data: GitHub API (User-Agent ze slugu šablony, s EKT_GITHUB_TOKEN vyšší limit), transient
 * ekt_gh_<md5> 12 h, záložní poslední úspěšná data v option ekt_gh_<md5>_last (bez autoloadu).
 * Když GitHub neodpoví, použije se záloha a nový pokus za hodinu; bez zálohy prostý odkaz.
 * Zálohy repozitářů, které se 30 dní nezobrazily, maže denní cron ekt_github_cleanup.
 *
 * Veřejné funkce: ekt_github_repo( $value ), ekt_github_data( $repo ), ekt_github_render( $attrs ).
 * Filtry: ekt_github_ttl( 12 h ), ekt_github_keep_days( 30 ), ekt_github_request_args( $args, $url ),
 * ekt_github_texts( $texts ), ekt_github_html( $html, $args ) (override template-parts/ekt/github.php;
 * $args: repo, data|null, facts = HTML položek).
 *
 * Markup: <div class="ekt-gh"><div class="ekt-gh__icon">svg</div><div class="ekt-gh__body">
 *           <p class="ekt-gh__name"><a><span class="ekt-gh__owner">owner /</span> <strong class="ekt-gh__repo">repo</strong></a></p>
 *           <p class="ekt-gh__desc">…</p><ul class="ekt-gh__facts"><li class="ekt-gh__fact">svg<span>…</span></li></ul>
 *         </div><a class="ekt-gh__btn">Poslední vydání ↗</a></div>
 *         bez dat: <p class="ekt-gh ekt-gh--plain"><a>github.com/owner/repo</a></p>
 */

/**
 * Bloky ekt/github a alias elvisek/github.
 */
function ekt_github_register(): void {
	if ( is_file( ekt_asset_path( 'js/github-editor.js' ) ) ) {
		wp_register_script(
			'ekt-github-editor',
			ekt_asset_url( 'js/github-editor.js' ),
			array( 'wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-server-side-render' ),
			ekt_asset_ver( 'js/github-editor.js' ),
			true
		);
	}
	$args = array(
		'api_version'     => 3,
		'title'           => 'GitHub repozitář',
		'category'        => 'embed',
		'icon'            => 'editor-code',
		'description'     => 'Karta repozitáře z GitHubu: popis, hvězdičky, poslední vydání, licence.',
		'attributes'      => array(
			'repo' => array(
				'type'    => 'string',
				'default' => '',
			),
		),
		'supports'        => array( 'html' => false ),
		'render_callback' => 'ekt_github_render',
		'editor_script'   => 'ekt-github-editor',
	);
	register_block_type( 'ekt/github', $args );
	$args['supports']['inserter'] = false; // starší název v obsahu článků
	register_block_type( 'elvisek/github', $args );
}
add_action( 'init', 'ekt_github_register' );

/**
 * Repozitář ve tvaru owner/repo (i z celé adresy), jinak ''.
 *
 * @param string $value Zadaná hodnota.
 * @return string
 */
function ekt_github_repo( string $value ): string {
	$repo = (string) preg_replace( '#^https?://(www\.)?github\.com/#i', '', trim( $value ) );
	$repo = trim( (string) preg_replace( '#(\.git)?/*$#', '', $repo ), '/' );
	return preg_match( '#^[A-Za-z0-9_.-]+/[A-Za-z0-9_.-]+$#', $repo ) ? $repo : '';
}

/**
 * Klíč cache repozitáře.
 *
 * @param string $repo owner/repo.
 * @return string ekt_gh_<md5>.
 */
function ekt_github_key( string $repo ): string {
	return 'ekt_gh_' . md5( strtolower( $repo ) );
}

/**
 * Požadavek na GitHub API.
 *
 * @param string $url Adresa API.
 * @return array|null Dekódovaná odpověď 200, jinak null.
 */
function ekt_github_get( string $url ): ?array {
	$headers = array(
		'Accept'     => 'application/vnd.github+json',
		'User-Agent' => EKT_SLUG . '/' . EKT_VERSION . ' (+' . home_url( '/' ) . ')',
	);
	$token   = defined( 'EKT_GITHUB_TOKEN' ) ? (string) constant( 'EKT_GITHUB_TOKEN' ) : '';
	if ( '' !== $token ) {
		$headers['Authorization'] = 'Bearer ' . $token;
	}
	$args = (array) apply_filters(
		'ekt_github_request_args',
		array(
			'timeout' => 6,
			'headers' => $headers,
		),
		$url
	);
	$res  = wp_remote_get( $url, $args );
	if ( is_wp_error( $res ) || 200 !== (int) wp_remote_retrieve_response_code( $res ) ) {
		return null;
	}
	$data = json_decode( (string) wp_remote_retrieve_body( $res ), true );
	return is_array( $data ) ? $data : null;
}

/**
 * Data repozitáře. Cache 12 h, při výpadku GitHubu poslední úspěšná data.
 *
 * @param string $repo owner/repo.
 * @return array|null name, url, description, stars, forks, language, license, pushed, release, released, release_url.
 */
function ekt_github_data( string $repo ): ?array {
	$repo = ekt_github_repo( $repo );
	if ( '' === $repo ) {
		return null;
	}
	$key  = ekt_github_key( $repo );
	$data = get_transient( $key );
	if ( is_array( $data ) ) {
		return $data;
	}
	if ( 'none' === $data ) {
		return null; // nedávný neúspěšný pokus bez zálohy
	}
	$last = get_option( $key . '_last' );
	$r    = ekt_github_get( 'https://api.github.com/repos/' . $repo );
	if ( null === $r ) {
		// GitHub neodpověděl (limit, výpadek, bez sítě) → záloha, nový pokus za hodinu.
		if ( is_array( $last ) && isset( $last['data'] ) && is_array( $last['data'] ) ) {
			set_transient( $key, $last['data'], HOUR_IN_SECONDS );
			update_option(
				$key . '_last',
				array(
					'data' => $last['data'],
					'used' => time(),
				),
				false
			);
			return $last['data'];
		}
		set_transient( $key, 'none', 15 * MINUTE_IN_SECONDS );
		return null;
	}
	$data = array(
		'name'        => (string) ( $r['full_name'] ?? $repo ),
		'url'         => (string) ( $r['html_url'] ?? 'https://github.com/' . $repo ),
		'description' => (string) ( $r['description'] ?? '' ),
		'stars'       => (int) ( $r['stargazers_count'] ?? 0 ),
		'forks'       => (int) ( $r['forks_count'] ?? 0 ),
		'language'    => (string) ( $r['language'] ?? '' ),
		'license'     => (string) ( $r['license']['spdx_id'] ?? '' ),
		'pushed'      => (string) ( $r['pushed_at'] ?? '' ),
		'release'     => '',
		'released'    => '',
		'release_url' => '',
	);
	if ( 'NOASSERTION' === $data['license'] ) {
		$data['license'] = '';
	}
	$rel = ekt_github_get( 'https://api.github.com/repos/' . $repo . '/releases/latest' );
	if ( null !== $rel ) {
		$data['release']     = (string) ( $rel['tag_name'] ?? '' );
		$data['released']    = (string) ( $rel['published_at'] ?? '' );
		$data['release_url'] = (string) ( $rel['html_url'] ?? '' );
	}
	set_transient( $key, $data, (int) apply_filters( 'ekt_github_ttl', 12 * HOUR_IN_SECONDS ) );
	update_option(
		$key . '_last',
		array(
			'data' => $data,
			'used' => time(),
		),
		false
	);
	return $data;
}

/**
 * Vykreslení bloku (ekt/github i elvisek/github).
 *
 * @param array $attrs Atributy bloku.
 * @return string HTML.
 */
function ekt_github_render( $attrs ): string {
	$repo  = ekt_github_repo( (string) ( $attrs['repo'] ?? '' ) );
	$texts = array_merge(
		array(
			'empty'   => 'Zadejte repozitář ve tvaru <code>uživatel/repozitář</code>.',
			'release' => 'Poslední vydání',
			'repo'    => 'Na GitHubu',
		),
		(array) apply_filters( 'ekt_github_texts', array() )
	);
	if ( '' === $repo ) {
		$preview = function_exists( 'wp_is_serving_rest_request' ) ? wp_is_serving_rest_request() : ( defined( 'REST_REQUEST' ) && REST_REQUEST );
		return ( $preview && current_user_can( 'edit_posts' ) ) ? '<p class="ekt-gh-empty">' . wp_kses( $texts['empty'], array( 'code' => array() ) ) . '</p>' : '';
	}
	ekt_enqueue_style( 'ekt-github', 'css/github.css' );
	$data  = ekt_github_data( $repo );
	$facts = array();
	if ( $data ) {
		$facts[] = ekt_icon( 'star', 15 ) . '<span>' . esc_html( number_format_i18n( (int) $data['stars'] ) ) . '</span>';
		if ( '' !== $data['release'] ) {
			$facts[] = ekt_icon( 'tag', 15 ) . '<span>' . esc_html( $data['release'] ) . ( '' !== $data['released'] ? ' · ' . esc_html( wp_date( 'j. n. Y', (int) strtotime( $data['released'] ) ) ) : '' ) . '</span>';
		}
		if ( '' !== $data['language'] ) {
			$facts[] = ekt_icon( 'code', 15 ) . '<span>' . esc_html( $data['language'] ) . '</span>';
		}
		if ( '' !== $data['license'] ) {
			$facts[] = ekt_icon( 'scale', 15 ) . '<span>' . esc_html( $data['license'] ) . '</span>';
		}
	}
	return ekt_render(
		'github',
		static function ( array $args ) use ( $texts ): string {
			$d = $args['data'];
			if ( ! $d ) {
				return sprintf( '<p class="ekt-gh ekt-gh--plain"><a href="%1$s">github.com/%2$s</a></p>', esc_url( 'https://github.com/' . $args['repo'] ), esc_html( $args['repo'] ) );
			}
			list( $owner, $name ) = array_pad( explode( '/', (string) $d['name'], 2 ), 2, '' );
			$html  = '<div class="ekt-gh"><div class="ekt-gh__icon" aria-hidden="true">' . ekt_icon( 'github', 26 ) . '</div><div class="ekt-gh__body">';
			$html .= sprintf(
				'<p class="ekt-gh__name"><a href="%1$s"><span class="ekt-gh__owner">%2$s /</span> <strong class="ekt-gh__repo">%3$s</strong></a></p>',
				esc_url( $d['url'] ),
				esc_html( $owner ),
				esc_html( $name )
			);
			if ( '' !== $d['description'] ) {
				$html .= '<p class="ekt-gh__desc">' . esc_html( $d['description'] ) . '</p>';
			}
			if ( $args['facts'] ) {
				$html .= '<ul class="ekt-gh__facts"><li class="ekt-gh__fact">' . implode( '</li><li class="ekt-gh__fact">', $args['facts'] ) . '</li></ul>';
			}
			$html .= sprintf(
				'</div><a class="ekt-gh__btn" href="%1$s">%2$s ↗</a></div>',
				esc_url( '' !== $d['release_url'] ? $d['release_url'] : $d['url'] ),
				esc_html( '' !== $d['release_url'] ? $texts['release'] : $texts['repo'] )
			);
			return $html;
		},
		array(
			'repo'  => $repo,
			'data'  => $data,
			'facts' => $facts,
		)
	);
}

/**
 * Styl karty v hlavičce u článku s blokem (jinak se načte z bloku v patičce).
 */
function ekt_github_enqueue(): void {
	if ( is_singular() && ( has_block( 'ekt/github', get_queried_object() ) || has_block( 'elvisek/github', get_queried_object() ) ) ) {
		ekt_enqueue_style( 'ekt-github', 'css/github.css' );
	}
}
add_action( 'wp_enqueue_scripts', 'ekt_github_enqueue' );

/**
 * Úklid záložních dat repozitářů, které se dlouho nezobrazily (denní cron).
 */
function ekt_github_cleanup(): void {
	global $wpdb;
	$limit = time() - DAY_IN_SECONDS * max( 1, (int) apply_filters( 'ekt_github_keep_days', 30 ) );
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- jednorázový výpis názvů options
	$names = $wpdb->get_col( $wpdb->prepare( "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s", $wpdb->esc_like( 'ekt_gh_' ) . '%' . $wpdb->esc_like( '_last' ) ) );
	foreach ( $names as $name ) {
		$last = get_option( $name );
		if ( ! is_array( $last ) || (int) ( $last['used'] ?? 0 ) < $limit ) {
			delete_option( $name );
		}
	}
}
add_action( 'ekt_github_cleanup', 'ekt_github_cleanup' );

/**
 * Naplánování úklidu (jen v administraci, ne při každém zobrazení webu).
 */
function ekt_github_schedule(): void {
	if ( ! wp_next_scheduled( 'ekt_github_cleanup' ) ) {
		wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'ekt_github_cleanup' );
	}
}
add_action( 'admin_init', 'ekt_github_schedule' );

/*
 * Migrace (A elvisek): záložní option ek_gh_<md5>_last → ekt_gh_<md5>_last (stejné md5), staré
 * transienty ek_gh_* smazat.
 */
add_filter(
	'ekt_migrations',
	static function ( array $steps ): array {
		$steps['github/cache'] = 'ekt_github_migrate';
		return $steps;
	}
);

/**
 * Krok migrace: převzít zálohy, smazat staré cache.
 *
 * @return bool
 */
function ekt_github_migrate(): bool {
	global $wpdb;
	foreach ( ekt_legacy_prefixes() as $p ) {
		$like = $wpdb->esc_like( $p . '_gh_' ) . '%';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- výpis starých options
		$names = $wpdb->get_col( $wpdb->prepare( "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s", $like ) );
		foreach ( $names as $name ) {
			if ( preg_match( '/^' . preg_quote( $p, '/' ) . '_gh_([a-f0-9]{32})_last$/', $name, $m ) ) {
				$old = get_option( $name );
				$new = 'ekt_gh_' . $m[1] . '_last';
				if ( is_array( $old ) && false === get_option( $new ) ) {
					update_option(
						$new,
						array(
							'data' => $old,
							'used' => time(),
						),
						false
					);
					if ( ! is_array( get_option( $new ) ) ) {
						return false; // zápis se nepovedl, starou zálohu nechat
					}
				}
			}
			delete_option( $name );
		}
		// Transienty {p}_gh_<md5> (a jejich timeout).
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- úklid starých transientů
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
				$wpdb->esc_like( '_transient_' . $p . '_gh_' ) . '%',
				$wpdb->esc_like( '_transient_timeout_' . $p . '_gh_' ) . '%'
			)
		);
	}
	wp_cache_delete( 'alloptions', 'options' );
	return true;
}
