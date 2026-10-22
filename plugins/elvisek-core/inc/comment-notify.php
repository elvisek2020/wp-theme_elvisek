<?php
/**
 * EKC module: comment-notify
 * Version: 1.0.0
 * Requires: common
 * Replaces: subscribe-to-comments-reloaded, comment-reply-email-notification
 * Summary: Upozornění e-mailem na odpověď na komentář: zaškrtávátko ve formuláři, e-mail po schválení odpovědi, odhlášení jedním odkazem.
 */

defined( 'ABSPATH' ) || exit;

/*
 * - Formulář komentáře dostane před tlačítkem zaškrtávátko (filtr comment_form_submit_field):
 *     p.comment-form-ekc-notify > label > input[type=checkbox][name=ekc_notify]
 *   Funguje s jakoukoli šablonou; vzhled řeší šablona (stejně jako .comment-form-cookies-consent).
 * - Zaškrtnutý komentář dostane comment meta _ekc_notify. Když je odpověď na něj schválená
 *   (hned, nebo později po moderaci), odejde autorovi rodičovského komentáře e-mail s úryvkem
 *   odpovědi a odkazem. Neposílá se: odpověď autora sám sobě (stejný e-mail), pingback, odpověď,
 *   která už upozornění odeslala (meta _ekc_notified), a když je volba v nastavení vypnutá.
 * - Odhlášení: odkaz v e-mailu (a hlavička List-Unsubscribe) s podpisem wp_hash; zruší odběr
 *   u všech komentářů se stejným e-mailem. Stránka odhlášení je noindex.
 * - Bez cizích služeb, e-mail přes wp_mail() (SMTP řeší web, jako u ostatních e-mailů WordPressu).
 *
 * Veřejné funkce: ekc_comment_notify_send( $reply ), ekc_comment_notify_unsubscribe_url( $comment ),
 * ekc_comment_notify_count(): int.
 * Filtry: ekc_comment_notify_texts( $texts ), ekc_comment_notify_mail( $mail, $reply, $parent ).
 */

ekc_settings_register(
	'comment_notify',
	array(
		'type'    => 'bool',
		'default' => true,
		'label'   => 'Upozornění na odpověď',
		'help'    => 'Ve formuláři komentáře zaškrtávátko „Pošlete mi e-mail, až někdo odpoví“; e-mail odejde po schválení odpovědi.',
		'section' => 'Komentáře',
	)
);

/**
 * Texty (filtr ekc_comment_notify_texts).
 *
 * @return array<string,string>
 */
function ekc_comment_notify_texts(): array {
	$texts = array(
		'label'        => 'Pošlete mi e-mail, až někdo odpoví',
		'subject'      => 'Nová odpověď na váš komentář: %s',
		'intro'        => 'na váš komentář u článku „%1$s“ odpověděl(a) %2$s:',
		'link'         => 'Celá odpověď',
		'unsubscribe'  => 'Upozornění na odpovědi už nechcete? Odhlásíte je tady',
		'done_title'   => 'Odběr zrušen',
		'done'         => 'Upozornění na odpovědi na vaše komentáře jsou zrušená.',
		'invalid'      => 'Odkaz pro odhlášení je neplatný nebo už byl použit.',
		'back'         => 'Zpět na web',
	);
	return array_map( 'strval', array_merge( $texts, (array) apply_filters( 'ekc_comment_notify_texts', $texts ) ) );
}

/**
 * Je odběr zapnutý?
 *
 * @return bool
 */
function ekc_comment_notify_enabled(): bool {
	return (bool) ekc_opt( 'comment_notify' );
}

/* -------------------------------------------------------------------------
 * Formulář a uložení volby
 * ---------------------------------------------------------------------- */

add_filter(
	'comment_form_submit_field',
	static function ( $field ) {
		if ( ! ekc_comment_notify_enabled() ) {
			return $field;
		}
		$t = ekc_comment_notify_texts();
		return '<p class="comment-form-ekc-notify"><input id="ekc-notify" name="ekc_notify" type="checkbox" value="1"> <label for="ekc-notify">' . esc_html( $t['label'] ) . '</label></p>' . $field;
	}
);

add_action(
	'comment_post',
	static function ( $comment_id, $approved ) {
		$comment = get_comment( (int) $comment_id );
		if ( ! $comment instanceof WP_Comment ) {
			return;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- formulář komentáře nonce nemá (stránky v cache)
		if ( ekc_comment_notify_enabled() && ! empty( $_POST['ekc_notify'] ) && is_email( $comment->comment_author_email ) ) {
			update_comment_meta( $comment->comment_ID, '_ekc_notify', 1 );
		}
		if ( 1 === (int) $approved ) {
			ekc_comment_notify_send( $comment );
		}
	},
	20,
	2
);

add_action(
	'transition_comment_status',
	static function ( $new_status, $old_status, $comment ) {
		if ( 'approved' === $new_status && 'approved' !== $old_status && $comment instanceof WP_Comment ) {
			ekc_comment_notify_send( $comment );
		}
	},
	10,
	3
);

/* -------------------------------------------------------------------------
 * Odeslání
 * ---------------------------------------------------------------------- */

/**
 * Pošle autorovi rodičovského komentáře e-mail o odpovědi (jednou na odpověď).
 *
 * @param WP_Comment|int $reply Odpověď.
 * @return bool Odesláno.
 */
function ekc_comment_notify_send( $reply ): bool {
	$reply = get_comment( $reply );
	if ( ! $reply instanceof WP_Comment || ! ekc_comment_notify_enabled() || '1' !== (string) $reply->comment_approved ) {
		return false;
	}
	if ( ! in_array( (string) $reply->comment_type, array( '', 'comment' ), true ) || ! (int) $reply->comment_parent ) {
		return false;
	}
	if ( get_comment_meta( $reply->comment_ID, '_ekc_notified', true ) ) {
		return false;
	}
	$parent = get_comment( (int) $reply->comment_parent );
	if ( ! $parent instanceof WP_Comment || '1' !== (string) $parent->comment_approved || ! get_comment_meta( $parent->comment_ID, '_ekc_notify', true ) ) {
		return false;
	}
	$to = (string) $parent->comment_author_email;
	if ( ! is_email( $to ) || 0 === strcasecmp( $to, (string) $reply->comment_author_email ) ) {
		return false;
	}
	update_comment_meta( $reply->comment_ID, '_ekc_notified', time() );

	$t       = ekc_comment_notify_texts();
	$site    = wp_specialchars_decode( (string) get_bloginfo( 'name' ), ENT_QUOTES );
	$title   = wp_specialchars_decode( (string) get_the_title( (int) $reply->comment_post_ID ), ENT_QUOTES );
	$excerpt = wp_trim_words( wp_strip_all_tags( (string) $reply->comment_content ), 80, '…' );
	$unsub   = ekc_comment_notify_unsubscribe_url( $parent );
	$body    = 'Dobrý den,' . "\n\n"
		. sprintf( $t['intro'], $title, (string) $reply->comment_author ) . "\n\n"
		. $excerpt . "\n\n"
		. $t['link'] . ': ' . get_comment_link( $reply ) . "\n\n"
		. "-- \n"
		. $site . ' · ' . home_url( '/' ) . "\n"
		. $t['unsubscribe'] . ': ' . $unsub . "\n";
	$mail = array(
		'to'      => $to,
		'subject' => '[' . $site . '] ' . sprintf( $t['subject'], $title ),
		'body'    => $body,
		'headers' => array(
			'Content-Type: text/plain; charset=UTF-8',
			'List-Unsubscribe: <' . $unsub . '>',
		),
	);
	$mail = (array) apply_filters( 'ekc_comment_notify_mail', $mail, $reply, $parent );
	return (bool) wp_mail( $mail['to'], $mail['subject'], $mail['body'], $mail['headers'] );
}

/* -------------------------------------------------------------------------
 * Odhlášení
 * ---------------------------------------------------------------------- */

/**
 * Podpis odkazu pro odhlášení.
 *
 * @param int    $comment_id ID komentáře.
 * @param string $email      E-mail autora.
 * @return string
 */
function ekc_comment_notify_token( int $comment_id, string $email ): string {
	return substr( wp_hash( 'ekc_comment_notify|' . $comment_id . '|' . strtolower( $email ) ), 0, 24 );
}

/**
 * Adresa pro zrušení odběru.
 *
 * @param WP_Comment|int $comment Komentář s odběrem.
 * @return string
 */
function ekc_comment_notify_unsubscribe_url( $comment ): string {
	$comment = get_comment( $comment );
	if ( ! $comment instanceof WP_Comment ) {
		return home_url( '/' );
	}
	return add_query_arg(
		array(
			'ekc_unsub' => (int) $comment->comment_ID,
			'ekc_t'     => ekc_comment_notify_token( (int) $comment->comment_ID, (string) $comment->comment_author_email ),
		),
		home_url( '/' )
	);
}

add_action(
	'template_redirect',
	static function () {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- odkaz z e-mailu, ověřený podpisem
		if ( ! isset( $_GET['ekc_unsub'], $_GET['ekc_t'] ) ) {
			return;
		}
		$id    = absint( $_GET['ekc_unsub'] );
		$token = sanitize_text_field( wp_unslash( $_GET['ekc_t'] ) );
		// phpcs:enable
		$t       = ekc_comment_notify_texts();
		$comment = $id ? get_comment( $id ) : null;
		$valid   = $comment instanceof WP_Comment && hash_equals( ekc_comment_notify_token( $id, (string) $comment->comment_author_email ), $token );
		if ( $valid ) {
			global $wpdb;
			$ids = $wpdb->get_col( $wpdb->prepare( "SELECT comment_ID FROM {$wpdb->comments} WHERE comment_author_email = %s", (string) $comment->comment_author_email ) );
			foreach ( $ids as $cid ) {
				delete_comment_meta( (int) $cid, '_ekc_notify' );
			}
		}
		nocache_headers();
		header( 'X-Robots-Tag: noindex, nofollow' );
		wp_die(
			'<p>' . esc_html( $valid ? $t['done'] : $t['invalid'] ) . '</p><p><a href="' . esc_url( home_url( '/' ) ) . '">' . esc_html( $t['back'] ) . '</a></p>',
			esc_html( $t['done_title'] ),
			array( 'response' => $valid ? 200 : 400 )
		);
	},
	1
);

/* -------------------------------------------------------------------------
 * Zdraví webu
 * ---------------------------------------------------------------------- */

/**
 * Počet komentářů s aktivním odběrem odpovědí.
 *
 * @return int
 */
function ekc_comment_notify_count(): int {
	global $wpdb;
	return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->commentmeta} WHERE meta_key = %s", '_ekc_notify' ) );
}

add_filter(
	'ekc_health_rows',
	static function ( array $rows ): array {
		if ( ekc_comment_notify_enabled() ) {
			$rows['Obsah']['Odběry odpovědí na komentáře'] = (string) ekc_comment_notify_count();
		}
		return $rows;
	}
);
