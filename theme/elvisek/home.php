<?php
/**
 * Titulka (výpis článků): nadpis s mottem, hlavní článek, témata, mřížka karet.
 * Od 2. stránky jen mřížka.
 */
defined( 'ABSPATH' ) || exit;

get_header();

$ek_paged = max( 1, (int) get_query_var( 'paged' ) );
$ek_hero  = (int) ek_opt( 'ek_hero_image' );
$ek_hero_l = $ek_hero ? (int) ek_opt( 'ek_hero_image_light' ) : 0;
?>
<main id="obsah" class="ek-main">
	<?php if ( 1 === $ek_paged && ! ek_opt( 'ek_show_masthead' ) ) : ?>
		<h1 class="screen-reader-text"><?php bloginfo( 'name' ); ?></h1>
		<div class="ek-home-gap"></div>
	<?php elseif ( 1 === $ek_paged ) : ?>
		<section class="ek-masthead<?php echo $ek_hero ? ' ek-masthead--image' : ''; ?><?php echo $ek_hero_l ? ' ek-masthead--dual' : ''; ?>">
			<?php if ( $ek_hero_l ) : ?>
				<picture class="ek-masthead__pic">
					<source media="(prefers-color-scheme: light)" data-ek-hero-light
						srcset="<?php echo esc_attr( wp_get_attachment_image_srcset( $ek_hero_l, 'ek-hero' ) ?: wp_get_attachment_image_url( $ek_hero_l, 'ek-hero' ) ); ?>"
						sizes="100vw">
					<?php echo wp_get_attachment_image( $ek_hero, 'ek-hero', false, array( 'class' => 'ek-masthead__bg', 'alt' => (string) get_post_meta( $ek_hero, '_wp_attachment_image_alt', true ), 'loading' => 'eager', 'fetchpriority' => 'high', 'sizes' => '100vw' ) ); ?>
				</picture>
				<script>(function(){var t=document.documentElement.dataset.theme,s=document.querySelector('[data-ek-hero-light]');if(s&&t){s.media=t==='light'?'all':'not all';}})();</script>
			<?php elseif ( $ek_hero ) : ?>
				<?php echo wp_get_attachment_image( $ek_hero, 'ek-hero', false, array( 'class' => 'ek-masthead__bg', 'alt' => (string) get_post_meta( $ek_hero, '_wp_attachment_image_alt', true ), 'loading' => 'eager', 'fetchpriority' => 'high', 'sizes' => '100vw' ) ); ?>
			<?php endif; ?>
			<div class="ek-wrap ek-masthead__inner">
				<h1 class="ek-masthead__title"><a href="<?php echo esc_url( home_url( '/' ) ); ?>" rel="home"><?php bloginfo( 'name' ); ?></a></h1>
				<?php if ( $ek_motto = ek_opt( 'ek_motto' ) ) : ?>
					<p class="ek-masthead__motto"><?php echo esc_html( $ek_motto ); ?></p>
				<?php endif; ?>
			</div>
		</section>
	<?php else : ?>
		<header class="ek-wrap ek-pagehead">
			<h1 class="ek-pagehead__title">Články <span class="ek-muted">– strana <?php echo (int) $ek_paged; ?></span></h1>
		</header>
	<?php endif; ?>

	<div class="ek-wrap ek-stack">
		<?php if ( have_posts() ) : ?>
			<?php
			if ( 1 === $ek_paged ) {
				the_post();
				get_template_part( 'template-parts/card', 'featured' );
			}
			?>
			<section class="ek-section" aria-labelledby="ek-latest-title">
				<?php if ( 1 === $ek_paged ) : ?>
					<div class="ek-section__head"><h2 id="ek-latest-title" class="ek-section__title">Další články</h2></div>
				<?php endif; ?>
				<div class="ek-grid" data-ek-grid>
					<?php
					while ( have_posts() ) {
						the_post();
						get_template_part( 'template-parts/card' );
					}
					?>
				</div>
				<?php ek_load_more(); ?>
			</section>
		<?php else : ?>
			<p>Zatím tu nic není.</p>
		<?php endif; ?>
	</div>
</main>
<?php
get_footer();
