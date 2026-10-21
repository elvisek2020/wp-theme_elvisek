<?php
/**
 * Detail článku.
 */
defined( 'ABSPATH' ) || exit;

get_header();
?>
<main id="obsah" class="ek-main">
	<?php while ( have_posts() ) : the_post(); ?>
		<?php
		$ek_mode       = (string) ekt_opt( 'site_sidebar_mode' );
		$ek_toc_html   = function_exists( 'ekt_toc_html' ) && ekt_toc_enabled() ? ekt_toc_html() : '';
		$ek_show_recent = (bool) ekt_opt( 'site_sidebar_recent' );
		// Obsah vykreslit předem: až pak víme, jestli má článek obsah (TOC) a jestli boční panel nebude prázdný.
		$ek_content    = str_replace( ']]>', ']]&gt;', apply_filters( 'the_content', get_the_content() ) );
		$ek_has_panel  = 'never' !== $ek_mode && ( '' !== $ek_toc_html || $ek_show_recent );
		?>
		<div class="ek-wrap ek-single ek-single--<?php echo esc_attr( $ek_has_panel ? $ek_mode : 'never' ); ?>">
			<article <?php post_class( 'ek-article' ); ?>>
				<header class="ek-article__head">
					<?php ek_breadcrumbs(); ?>
					<h1 class="ek-article__title"><?php the_title(); ?></h1>
					<div class="ek-article__meta">
						<?php echo ek_category_chips(); ?>
						<span><?php echo ek_date( 'long' ); ?></span>
						<span aria-hidden="true">·</span>
						<span><?php echo (int) ek_reading_time(); ?> min čtení</span>
						<?php echo ekt_updated_badge(); ?>
						<span class="ek-article__actions">
							<?php echo ekt_markdown_button(); // phpcs:ignore WordPress.Security.EscapeOutput -- escapuje modul ?>
							<button type="button" class="ek-linkbtn ek-article__print" data-ek-print><?php echo ekt_icon( 'print', 16 ); ?> Tisk</button>
						</span>
					</div>
				</header>

				<?php if ( has_post_thumbnail() && ! ek_hide_hero() ) : ?>
					<figure class="ek-article__hero ek-thumb<?php echo ek_thumb_is_logo( get_post_thumbnail_id() ) ? ' ek-article__hero--logo' : ''; ?>">
						<?php the_post_thumbnail( 'ek-hero', array( 'alt' => ekt_plain_title(), 'class' => 'ek-thumb__img', 'loading' => 'eager', 'fetchpriority' => 'high', 'sizes' => '(max-width: 900px) 100vw, 860px' ) ); ?>
					</figure>
				<?php endif; ?>

				<?php echo ekt_summary_box(); ?>

				<div class="ek-content entry-content" data-ekt-content>
					<?php echo $ek_content; // phpcs:ignore WordPress.Security.EscapeOutput -- výstup filtru the_content ?>
					<?php wp_link_pages( array( 'before' => '<nav class="ek-pages">Stránky: ', 'after' => '</nav>' ) ); ?>
				</div>

				<?php if ( $ek_tags = get_the_tag_list( '<ul class="ek-tags"><li>', '</li><li>', '</li></ul>' ) ) : ?>
					<?php echo wp_kses_post( $ek_tags ); ?>
				<?php endif; ?>

				<?php
				$ek_prev = ekt_opt( 'site_postnav' ) ? get_previous_post() : null;
				$ek_next = ekt_opt( 'site_postnav' ) ? get_next_post() : null;
				if ( $ek_prev || $ek_next ) :
					?>
					<nav class="ek-postnav" aria-label="Další články">
						<?php if ( $ek_prev ) : ?>
							<a class="ek-postnav__item" href="<?php echo esc_url( get_permalink( $ek_prev ) ); ?>" rel="prev">
								<span class="ek-postnav__dir"><?php echo ekt_icon( 'arrow-l', 14 ); ?> Starší</span>
								<span class="ek-postnav__title"><?php echo esc_html( get_the_title( $ek_prev ) ); ?></span>
							</a>
						<?php else : ?><span></span><?php endif; ?>
						<?php if ( $ek_next ) : ?>
							<a class="ek-postnav__item ek-postnav__item--next" href="<?php echo esc_url( get_permalink( $ek_next ) ); ?>" rel="next">
								<span class="ek-postnav__dir">Novější <?php echo ekt_icon( 'arrow', 14 ); ?></span>
								<span class="ek-postnav__title"><?php echo esc_html( get_the_title( $ek_next ) ); ?></span>
							</a>
						<?php endif; ?>
					</nav>
				<?php endif; ?>

				<?php $ek_related = ek_related_posts( 3 ); ?>
				<?php if ( $ek_related ) : ?>
					<section class="ek-section ek-related" aria-labelledby="ek-related-title">
						<h2 id="ek-related-title" class="ek-section__title ek-related__head">Mohlo by vás zajímat</h2>
						<ul class="ek-related__list">
							<?php foreach ( $ek_related as $ek_r ) : ?>
								<li>
									<a class="ek-related__item" href="<?php echo esc_url( get_permalink( $ek_r ) ); ?>">
										<span class="ek-related__cat"><?php echo esc_html( ekt_primary_category( $ek_r )->name ?? '' ); ?></span>
										<span class="ek-related__title"><?php echo esc_html( get_the_title( $ek_r ) ); ?></span>
									</a>
								</li>
							<?php endforeach; ?>
						</ul>
					</section>
				<?php endif; ?>

				<?php echo ekt_share_links(); // phpcs:ignore WordPress.Security.EscapeOutput -- escapuje modul ?>

				<?php
				if ( comments_open() || get_comments_number() ) {
					comments_template();
				}
				?>
			</article>

			<?php if ( $ek_has_panel ) : ?>
				<aside class="ek-sidebar" aria-label="Postranní panel">
					<div class="ek-sidebar__sticky">
						<?php
						echo $ek_toc_html; // phpcs:ignore WordPress.Security.EscapeOutput -- escapuje modul toc
						if ( $ek_show_recent ) {
							get_template_part( 'template-parts/recent' );
						}
						?>
					</div>
				</aside>
			<?php endif; ?>
			<?php if ( '' !== $ek_toc_html ) : ?>
				<template id="ek-toc-tpl"><?php echo $ek_toc_html; // phpcs:ignore WordPress.Security.EscapeOutput -- escapuje modul toc ?></template>
			<?php endif; ?>
		</div>
	<?php endwhile; ?>
</main>
<?php
get_footer();
