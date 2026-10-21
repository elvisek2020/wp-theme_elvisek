<?php defined( 'ABSPATH' ) || exit; ?>

<footer class="ek-footer">
	<div class="ek-wrap ek-footer__bar">
		<p class="ek-footer__about">
			<span class="ek-footer__copy">© <?php echo esc_html( ekt_copyright_years() ); ?> <?php bloginfo( 'name' ); ?></span>
			<?php if ( $ek_about = ekt_opt( 'footer_text' ) ) : ?>
				<span class="ek-footer__text"><?php echo esc_html( $ek_about ); ?></span>
			<?php endif; ?>
		</p>
		<div class="ek-footer__prefs">
			<?php echo ekt_color_scheme_switch(); // phpcs:ignore WordPress.Security.EscapeOutput -- escapuje modul ?>
			<button type="button" class="ek-pill" data-ekt-wide-toggle aria-pressed="false"><?php echo ekt_icon( 'expand', 16 ); ?> Široká stránka</button>
		</div>
		<nav class="ek-footer__links" aria-label="Odkazy v patičce">
			<?php echo ekt_social_links( 'ek-social' ); // phpcs:ignore WordPress.Security.EscapeOutput -- escapuje modul ?>
			<?php if ( $ek_contact = get_page_by_path( 'kontakt' ) ) : ?>
				<a href="<?php echo esc_url( get_permalink( $ek_contact ) ); ?>">Kontakt</a>
			<?php endif; ?>
			<a href="<?php echo esc_url( get_feed_link() ); ?>"><?php echo ekt_icon( 'rss', 14 ); ?> RSS</a>
			<?php if ( $ek_privacy = get_privacy_policy_url() ) : ?>
				<a href="<?php echo esc_url( $ek_privacy ); ?>">Soukromí</a>
			<?php endif; ?>
			<?php echo ekt_consent_link(); // phpcs:ignore WordPress.Security.EscapeOutput -- escapuje modul ?>
			<a href="<?php echo esc_url( admin_url() ); ?>" aria-label="Administrace" rel="nofollow"><?php echo ekt_icon( 'lock', 14 ); ?></a>
		</nav>
	</div>
</footer>

<?php echo ekt_totop_button(); // phpcs:ignore WordPress.Security.EscapeOutput -- escapuje modul ?>

<?php wp_footer(); ?>
</body>
</html>
