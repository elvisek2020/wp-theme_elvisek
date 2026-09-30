<?php defined( 'ABSPATH' ) || exit; ?>

<footer class="ek-footer">
	<div class="ek-wrap ek-footer__grid">
		<div class="ek-footer__about">
			<div class="ek-footer__name"><?php bloginfo( 'name' ); ?></div>
			<p><?php echo esc_html( ek_opt( 'ek_about' ) ); ?></p>
		</div>
		<div>
			<h2 class="ek-footer__title">Témata</h2>
			<ul class="ek-footer__list">
				<?php foreach ( ek_topics( 5 ) as $cat ) : ?>
					<li><a href="<?php echo esc_url( get_category_link( $cat ) ); ?>"><?php echo esc_html( $cat->name ); ?></a></li>
				<?php endforeach; ?>
			</ul>
		</div>
		<div>
			<h2 class="ek-footer__title">Odkazy</h2>
			<?php
			if ( has_nav_menu( 'footer' ) ) {
				wp_nav_menu( array( 'theme_location' => 'footer', 'container' => false, 'menu_class' => 'ek-footer__list', 'depth' => 1 ) );
			} else {
				echo '<ul class="ek-footer__list">';
				wp_list_pages( array( 'title_li' => '', 'depth' => 1, 'exclude' => (int) get_option( 'page_on_front' ) ) );
				printf( '<li><a href="%s">RSS</a></li>', esc_url( get_feed_link() ) );
				echo '</ul>';
			}
			?>
		</div>
	</div>
	<div class="ek-wrap ek-footer__bottom">
		<span>© <?php echo esc_html( ek_copyright_years() ); ?> <?php bloginfo( 'name' ); ?> · Všechna práva vyhrazena</span>
		<span class="ek-footer__meta">
			<?php if ( function_exists( 'ek_consent_link' ) ) { ek_consent_link(); } ?>
			<a href="<?php echo esc_url( admin_url() ); ?>" aria-label="Administrace" rel="nofollow"><?php echo ek_icon( 'lock', 14 ); ?></a>
		</span>
	</div>
</footer>

<?php wp_footer(); ?>
</body>
</html>
