<?php defined( 'ABSPATH' ) || exit; ?>

<footer class="ek-footer">
	<div class="ek-wrap ek-footer__bar">
		<p class="ek-footer__about">
			<span class="ek-footer__copy">© <?php echo esc_html( ek_copyright_years() ); ?> <?php bloginfo( 'name' ); ?></span>
			<?php if ( $ek_about = ek_opt( 'ek_about' ) ) : ?>
				<span class="ek-footer__text"><?php echo esc_html( $ek_about ); ?></span>
			<?php endif; ?>
		</p>
		<div class="ek-footer__right">
		<div class="ek-footer__prefs" data-ek-prefs>
			<div class="ek-seg" role="group" aria-label="Vzhled">
				<button type="button" class="ek-seg__btn" data-ek-theme-set="system" aria-pressed="false"><?php echo ek_icon( 'gear', 16 ); ?> Systém</button>
				<button type="button" class="ek-seg__btn" data-ek-theme-set="light" aria-pressed="false"><?php echo ek_icon( 'sun', 16 ); ?> Světlý</button>
				<button type="button" class="ek-seg__btn" data-ek-theme-set="dark" aria-pressed="false"><?php echo ek_icon( 'moon', 16 ); ?> Tmavý</button>
			</div>
			<button type="button" class="ek-pill" data-ek-wide-toggle aria-pressed="false"><?php echo ek_icon( 'expand', 16 ); ?> Široká stránka</button>
		</div>
		<nav class="ek-footer__links" aria-label="Odkazy v patičce">
			<?php if ( $ek_contact = get_page_by_path( 'kontakt' ) ) : ?>
				<a href="<?php echo esc_url( get_permalink( $ek_contact ) ); ?>">Kontakt</a>
			<?php endif; ?>
			<a href="<?php echo esc_url( get_feed_link() ); ?>"><?php echo ek_icon( 'rss', 14 ); ?> RSS</a>
			<?php if ( $ek_privacy = get_privacy_policy_url() ) : ?>
				<a href="<?php echo esc_url( $ek_privacy ); ?>">Soukromí</a>
			<?php endif; ?>
			<?php if ( function_exists( 'ek_consent_link' ) ) { ek_consent_link(); } ?>
			<a href="<?php echo esc_url( admin_url() ); ?>" aria-label="Administrace" rel="nofollow"><?php echo ek_icon( 'lock', 14 ); ?></a>
		</nav>
		</div>
	</div>
</footer>

<?php wp_footer(); ?>
</body>
</html>
