<?php
/**
 * Blok „GitHub repozitář“ (elvisek/github): karta s popisem, hvězdičkami, posledním vydáním a licencí.
 * Data stahuje server z GitHub API a drží je 12 h. Když GitHub neodpoví, použije se poslední úspěšná verze.
 * Návštěvník se s GitHubem nespojuje (žádné externí skripty ani obrázky).
 */

defined( 'ABSPATH' ) || exit;

const EK_GH_TTL = 12 * HOUR_IN_SECONDS;

add_action( 'init', function () {
	register_block_type( 'elvisek/github', array(
		'api_version'     => 3,
		'title'           => 'GitHub repozitář',
		'category'        => 'embed',
		'icon'            => 'editor-code',
		'description'     => 'Karta repozitáře z GitHubu: popis, hvězdičky, poslední vydání, licence.',
		'attributes'      => array(
			'repo' => array( 'type' => 'string', 'default' => '' ),
		),
		'supports'        => array( 'html' => false ),
		'render_callback' => 'ek_github_render',
		'editor_script'   => 'ek-editor-extras',
	) );
} );

function ek_github_repo_sanitize( string $repo ): string {
	$repo = trim( $repo );
	$repo = preg_replace( '#^https?://(www\.)?github\.com/#i', '', $repo );
	$repo = trim( preg_replace( '#(\.git)?/*$#', '', $repo ), '/' );
	return preg_match( '#^[A-Za-z0-9_.-]+/[A-Za-z0-9_.-]+$#', $repo ) ? $repo : '';
}

/**
 * Data repozitáře (pole) nebo null. Cache 12 h, při výpadku poslední úspěšná data.
 */
function ek_github_data( string $repo ): ?array {
	$key  = 'ek_gh_' . md5( strtolower( $repo ) );
	$data = get_transient( $key );
	if ( is_array( $data ) ) {
		return $data;
	}
	$args = array(
		'timeout' => 6,
		'headers' => array( 'Accept' => 'application/vnd.github+json', 'User-Agent' => 'elvisek.cz' ),
	);
	$res = wp_remote_get( 'https://api.github.com/repos/' . $repo, $args );
	if ( 200 === wp_remote_retrieve_response_code( $res ) ) {
		$r    = json_decode( wp_remote_retrieve_body( $res ), true );
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
		$rel = wp_remote_get( 'https://api.github.com/repos/' . $repo . '/releases/latest', $args );
		if ( 200 === wp_remote_retrieve_response_code( $rel ) ) {
			$l = json_decode( wp_remote_retrieve_body( $rel ), true );
			$data['release']     = (string) ( $l['tag_name'] ?? '' );
			$data['released']    = (string) ( $l['published_at'] ?? '' );
			$data['release_url'] = (string) ( $l['html_url'] ?? '' );
		}
		set_transient( $key, $data, EK_GH_TTL );
		update_option( $key . '_last', $data, false );
		return $data;
	}
	// GitHub neodpověděl (limit, výpadek) → poslední úspěšná data, zkusit znovu za hodinu.
	$last = get_option( $key . '_last' );
	if ( is_array( $last ) ) {
		set_transient( $key, $last, HOUR_IN_SECONDS );
		return $last;
	}
	return null;
}

function ek_github_render( array $attrs ): string {
	$repo = ek_github_repo_sanitize( (string) ( $attrs['repo'] ?? '' ) );
	if ( '' === $repo ) {
		return ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ? '<p class="ek-gh-empty">Zadejte repozitář ve tvaru <code>uživatel/repozitář</code>.</p>' : '';
	}
	$d = ek_github_data( $repo );
	if ( ! $d ) {
		return sprintf( '<p class="ek-gh ek-gh--plain"><a href="%1$s">github.com/%2$s</a></p>', esc_url( 'https://github.com/' . $repo ), esc_html( $repo ) );
	}
	[ $owner, $name ] = array_pad( explode( '/', $d['name'], 2 ), 2, '' );

	$facts = array();
	$facts[] = ek_icon( 'star', 15 ) . '<span>' . esc_html( number_format_i18n( $d['stars'] ) ) . '</span>';
	if ( $d['release'] ) {
		$facts[] = ek_icon( 'tag', 15 ) . '<span>' . esc_html( $d['release'] ) . ( $d['released'] ? ' · ' . esc_html( wp_date( 'j. n. Y', strtotime( $d['released'] ) ) ) : '' ) . '</span>';
	}
	if ( $d['language'] ) {
		$facts[] = ek_icon( 'code', 15 ) . '<span>' . esc_html( $d['language'] ) . '</span>';
	}
	if ( $d['license'] ) {
		$facts[] = ek_icon( 'scale', 15 ) . '<span>' . esc_html( $d['license'] ) . '</span>';
	}

	ob_start();
	?>
	<div class="ek-gh">
		<div class="ek-gh__icon" aria-hidden="true"><?php echo ek_icon( 'github', 26 ); ?></div>
		<div class="ek-gh__body">
			<p class="ek-gh__name"><a href="<?php echo esc_url( $d['url'] ); ?>"><span class="ek-gh__owner"><?php echo esc_html( $owner ); ?> /</span> <strong><?php echo esc_html( $name ); ?></strong></a></p>
			<?php if ( $d['description'] ) : ?>
				<p class="ek-gh__desc"><?php echo esc_html( $d['description'] ); ?></p>
			<?php endif; ?>
			<ul class="ek-gh__facts">
				<?php foreach ( $facts as $f ) : ?>
					<li><?php echo $f; // phpcs:ignore WordPress.Security.EscapeOutput -- ikona + escapovaný text ?></li>
				<?php endforeach; ?>
			</ul>
		</div>
		<a class="ek-btn ek-btn--ghost ek-gh__btn" href="<?php echo esc_url( $d['release_url'] ?: $d['url'] ); ?>"><?php echo $d['release_url'] ? 'Poslední vydání' : 'Na GitHubu'; ?> ↗</a>
	</div>
	<?php
	return (string) ob_get_clean();
}
