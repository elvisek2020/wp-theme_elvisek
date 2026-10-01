<?php
/**
 * Sdílení článku, profily v patičce a rychlé hledání během psaní.
 *
 * Sdílení i profily jsou obyčejné odkazy – žádné skripty sociálních sítí, žádné sledování.
 */

defined( 'ABSPATH' ) || exit;

/**
 * Tlačítka pro sdílení pod článkem.
 */
function ek_share_buttons(): void {
	if ( ! ek_opt( 'ek_share' ) ) {
		return;
	}
	$url   = get_permalink();
	$title = wp_strip_all_tags( get_the_title() );
	$links = array(
		'linkedin' => array( 'LinkedIn', 'https://www.linkedin.com/sharing/share-offsite/?url=' . rawurlencode( $url ) ),
		'facebook' => array( 'Facebook', 'https://www.facebook.com/sharer/sharer.php?u=' . rawurlencode( $url ) ),
	);
	?>
	<div class="ek-share">
		<span class="ek-share__label">Sdílet článek</span>
		<button type="button" class="ek-share__btn" data-ek-share data-url="<?php echo esc_url( $url ); ?>" data-title="<?php echo esc_attr( $title ); ?>">
			<span class="ek-share__icon"><?php echo ek_icon( 'link', 16 ); ?></span><span data-ek-share-label>Kopírovat odkaz</span>
		</button>
		<?php foreach ( $links as $key => [ $label, $href ] ) : ?>
			<a class="ek-share__btn" href="<?php echo esc_url( $href ); ?>" target="_blank" rel="noopener noreferrer nofollow">
				<span class="ek-share__icon"><?php echo ek_icon( $key, 16 ); ?></span><?php echo esc_html( $label ); ?>
			</a>
		<?php endforeach; ?>
	</div>
	<?php
}

/**
 * Ikony profilů (e-mail, GitHub, LinkedIn) v patičce.
 */
function ek_social_profiles(): void {
	$items = array();
	if ( $mail = sanitize_email( (string) ek_opt( 'ek_social_email' ) ) ) {
		$items[] = array( 'mailto:' . antispambot( $mail ), 'E-mail', 'mail' );
	}
	if ( $gh = (string) ek_opt( 'ek_social_github' ) ) {
		$items[] = array( $gh, 'GitHub', 'github' );
	}
	if ( $li = (string) ek_opt( 'ek_social_linkedin' ) ) {
		$items[] = array( $li, 'LinkedIn', 'linkedin' );
	}
	if ( ! $items ) {
		return;
	}
	echo '<span class="ek-social">';
	foreach ( $items as [ $href, $label, $icon ] ) {
		printf(
			'<a class="ek-social__link" href="%s" aria-label="%s" title="%s"%s>%s</a>',
			'mail' === $icon ? $href : esc_url( $href ), // mailto už je zakódovaný přes antispambot()
			esc_attr( $label ),
			esc_attr( $label ),
			'mail' === $icon ? '' : ' target="_blank" rel="me noopener"',
			ek_icon( $icon, 16 )
		);
	}
	echo '</span>';
}

/**
 * Rychlé hledání: GET /wp-json/ek/v1/search?q=… → až 6 článků/stránek (název, adresa, rubrika, datum).
 */
add_action( 'rest_api_init', function () {
	register_rest_route( 'ek/v1', '/search', array(
		'methods'             => 'GET',
		'permission_callback' => '__return_true',
		'args'                => array(
			'q' => array(
				'required'          => true,
				'sanitize_callback' => fn( $v ) => mb_substr( trim( sanitize_text_field( (string) $v ) ), 0, 80 ),
			),
		),
		'callback'            => function ( WP_REST_Request $req ) {
			$q = (string) $req['q'];
			if ( mb_strlen( $q ) < 2 ) {
				return array();
			}
			$query = new WP_Query( array(
				's'                   => $q,
				'post_type'           => array( 'post', 'page' ),
				'post_status'         => 'publish',
				'posts_per_page'      => 6,
				'no_found_rows'       => true,
				'ignore_sticky_posts' => true,
			) );
			$out = array();
			foreach ( $query->posts as $p ) {
				$cat   = 'post' === $p->post_type ? ( ek_primary_category( $p )->name ?? '' ) : 'Stránka';
				$out[] = array(
					't' => html_entity_decode( wp_strip_all_tags( get_the_title( $p ) ), ENT_QUOTES, 'UTF-8' ),
					'u' => get_permalink( $p ),
					'c' => $cat,
					'd' => 'post' === $p->post_type ? get_the_date( 'j. n. Y', $p ) : '',
				);
			}
			$res = rest_ensure_response( $out );
			$res->header( 'Cache-Control', 'public, max-age=300' );
			return $res;
		},
	) );
} );
