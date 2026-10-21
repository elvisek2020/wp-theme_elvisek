<?php
/**
 * EKT module: share
 * Version: 1.0.0
 * Requires: common
 * Replaces: add-to-any, shared-counts, social-warfare, supsystic-social-share
 * Assets: js/share.js
 * Summary: Sdílení článku obyčejnými odkazy bez skriptů sociálních sítí, kopírování odkazu a systémové sdílení na mobilu.
 */

defined( 'ABSPATH' ) || exit;

/*
 * Zapíná se v Přizpůsobit → Články → Sdílení pod článkem (theme_mod ekt_share, výchozí zapnuto;
 * staré ek_share převádí common). Dokud běží AddToAny nebo Shared Counts, nic nevypíše
 * (ekt_legacy_active( 'share' )).
 *
 * Výstup ekt_share_links( $post ) (single.php kostry; override template-parts/ekt/share.php
 * s $args['url'], ['title'], ['networks'], ['texts']; filtr ekt_share_html):
 *   <div class="ekt-share">
 *     <span class="ekt-share__label">Sdílet článek</span>
 *     <button type="button" class="ekt-share__btn ekt-share__btn--copy" data-ekt-share
 *             data-ekt-share-url data-ekt-share-title data-ekt-share-copied data-ekt-share-native>
 *       <span class="ekt-share__icon">svg link</span><span class="ekt-share__text" data-ekt-share-label>Kopírovat odkaz</span>
 *     </button>
 *     <a class="ekt-share__btn ekt-share__btn--linkedin" href target="_blank" rel="noopener noreferrer nofollow">
 *       <span class="ekt-share__icon">svg</span><span class="ekt-share__text">LinkedIn</span></a> …
 *   </div>
 * JS (assets/ekt/js/share.js): na dotykovém zařízení s navigator.share systémové sdílení
 * (popisek „Sdílet“), jinak zkopíruje odkaz (popisek „Zkopírováno“, třída is-done na 2 s).
 *
 * Sítě: filtr ekt_share_networks( $networks, $url, $title ) – klíč => [label, icon, href]
 * (výchozí linkedin, facebook, x, email). Texty: filtr ekt_share_texts( $texts ).
 */

ekt_setting_register(
	'share',
	array(
		'type'        => 'checkbox',
		'default'     => 1,
		'label'       => 'Sdílení pod článkem',
		'description' => 'Odkazy na sociální sítě a kopírování odkazu, bez skriptů třetích stran.',
		'section'     => 'posts',
		'priority'    => 40,
	)
);

/**
 * Texty sdílení.
 *
 * @return array<string,string>
 */
function ekt_share_texts(): array {
	$texts = array(
		'label'  => 'Sdílet článek',
		'copy'   => 'Kopírovat odkaz',
		'copied' => 'Zkopírováno',
		'native' => 'Sdílet',
		'prompt' => 'Odkaz na článek:',
	);
	return array_map( 'strval', array_merge( $texts, (array) apply_filters( 'ekt_share_texts', $texts ) ) );
}

/**
 * Sítě ke sdílení článku.
 *
 * @param string $url   Adresa článku.
 * @param string $title Titulek (prostý text).
 * @return array<string,array{label:string,icon:string,href:string}>
 */
function ekt_share_networks( string $url, string $title ): array {
	$networks = array(
		'linkedin' => array(
			'label' => 'LinkedIn',
			'icon'  => 'linkedin',
			'href'  => 'https://www.linkedin.com/sharing/share-offsite/?url=' . rawurlencode( $url ),
		),
		'facebook' => array(
			'label' => 'Facebook',
			'icon'  => 'facebook',
			'href'  => 'https://www.facebook.com/sharer/sharer.php?u=' . rawurlencode( $url ),
		),
		'x'        => array(
			'label' => 'X',
			'icon'  => 'x',
			'href'  => 'https://x.com/intent/post?url=' . rawurlencode( $url ) . '&text=' . rawurlencode( $title ),
		),
		'email'    => array(
			'label' => 'E-mail',
			'icon'  => 'mail',
			'href'  => 'mailto:?subject=' . rawurlencode( $title ) . '&body=' . rawurlencode( $url ),
		),
	);
	return (array) apply_filters( 'ekt_share_networks', $networks, $url, $title );
}

/**
 * Sdílení článku. Vypnuté, nebo běží původní plugin = ''.
 *
 * @param int|WP_Post|null $post Článek.
 * @return string HTML.
 */
function ekt_share_links( $post = null ): string {
	$post = get_post( $post );
	if ( ! $post || ! ekt_opt( 'share' ) || ekt_legacy_active( 'share' ) || 'publish' !== get_post_status( $post ) ) {
		return '';
	}
	$url   = (string) get_permalink( $post );
	$title = ekt_plain_title( $post );
	ekt_enqueue_script( 'ekt-share', 'js/share.js' );
	return ekt_render(
		'share',
		static function ( array $args ): string {
			$t    = $args['texts'];
			$html = '<div class="ekt-share"><span class="ekt-share__label">' . esc_html( $t['label'] ) . '</span>';
			$html .= sprintf(
				'<button type="button" class="ekt-share__btn ekt-share__btn--copy" data-ekt-share data-ekt-share-url="%1$s" data-ekt-share-title="%2$s" data-ekt-share-copied="%3$s" data-ekt-share-native="%4$s" data-ekt-share-prompt="%5$s"><span class="ekt-share__icon">%6$s</span><span class="ekt-share__text" data-ekt-share-label>%7$s</span></button>',
				esc_url( $args['url'] ),
				esc_attr( $args['title'] ),
				esc_attr( $t['copied'] ),
				esc_attr( $t['native'] ),
				esc_attr( $t['prompt'] ),
				ekt_icon( 'link', 16 ),
				esc_html( $t['copy'] )
			);
			foreach ( $args['networks'] as $key => $net ) {
				$mail  = str_starts_with( (string) $net['href'], 'mailto:' );
				$html .= sprintf(
					'<a class="ekt-share__btn ekt-share__btn--%1$s" href="%2$s"%3$s><span class="ekt-share__icon">%4$s</span><span class="ekt-share__text">%5$s</span></a>',
					esc_attr( sanitize_html_class( (string) $key ) ),
					esc_url( (string) $net['href'], $mail ? array( 'mailto' ) : array( 'https', 'http' ) ),
					$mail ? '' : ' target="_blank" rel="noopener noreferrer nofollow"',
					ekt_icon( (string) ( $net['icon'] ?? $key ), 16 ),
					esc_html( (string) ( $net['label'] ?? $key ) )
				);
			}
			return $html . '</div>';
		},
		array(
			'url'      => $url,
			'title'    => $title,
			'networks' => ekt_share_networks( $url, $title ),
			'texts'    => ekt_share_texts(),
		)
	);
}
