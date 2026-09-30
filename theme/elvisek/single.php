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
		$ek_mode       = (string) ek_opt( 'ek_sidebar_mode' );
		$ek_show_toc   = (bool) ek_opt( 'ek_sidebar_toc' );
		$ek_show_recent = (bool) ek_opt( 'ek_sidebar_recent' );
		$ek_has_panel  = 'never' !== $ek_mode && ( $ek_show_toc || $ek_show_recent );
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
						<?php if ( get_the_modified_date( 'Y-m-d' ) > get_the_date( 'Y-m-d' ) ) : ?>
							<span class="ek-muted">(aktualizováno <?php echo esc_html( get_the_modified_date( 'j. n. Y' ) ); ?>)</span>
						<?php endif; ?>
						<button type="button" class="ek-linkbtn ek-article__print" data-ek-print><?php echo ek_icon( 'print', 16 ); ?> Tisk</button>
					</div>
				</header>

				<?php if ( has_post_thumbnail() ) : ?>
					<figure class="ek-article__hero ek-thumb<?php echo ek_thumb_is_logo( get_post_thumbnail_id() ) ? ' ek-article__hero--logo' : ''; ?>">
						<?php the_post_thumbnail( 'ek-hero', array( 'class' => 'ek-thumb__img', 'loading' => 'eager', 'fetchpriority' => 'high', 'sizes' => '(max-width: 900px) 100vw, 860px' ) ); ?>
					</figure>
				<?php endif; ?>

				<div class="ek-content entry-content" data-ek-content>
					<?php the_content(); ?>
					<?php wp_link_pages( array( 'before' => '<nav class="ek-pages">Stránky: ', 'after' => '</nav>' ) ); ?>
				</div>

				<?php if ( $ek_tags = get_the_tag_list( '<ul class="ek-tags"><li>', '</li><li>', '</li></ul>' ) ) : ?>
					<?php echo wp_kses_post( $ek_tags ); ?>
				<?php endif; ?>

				<?php
				$ek_prev = get_previous_post();
				$ek_next = get_next_post();
				if ( $ek_prev || $ek_next ) :
					?>
					<nav class="ek-postnav" aria-label="Další články">
						<?php if ( $ek_prev ) : ?>
							<a class="ek-postnav__item" href="<?php echo esc_url( get_permalink( $ek_prev ) ); ?>" rel="prev">
								<span class="ek-postnav__dir"><?php echo ek_icon( 'arrow-l', 14 ); ?> Starší</span>
								<span class="ek-postnav__title"><?php echo esc_html( get_the_title( $ek_prev ) ); ?></span>
							</a>
						<?php else : ?><span></span><?php endif; ?>
						<?php if ( $ek_next ) : ?>
							<a class="ek-postnav__item ek-postnav__item--next" href="<?php echo esc_url( get_permalink( $ek_next ) ); ?>" rel="next">
								<span class="ek-postnav__dir">Novější <?php echo ek_icon( 'arrow', 14 ); ?></span>
								<span class="ek-postnav__title"><?php echo esc_html( get_the_title( $ek_next ) ); ?></span>
							</a>
						<?php endif; ?>
					</nav>
				<?php endif; ?>

				<?php $ek_related = ek_related_posts( 3 ); ?>
				<?php if ( $ek_related ) : ?>
					<section class="ek-section ek-related" aria-labelledby="ek-related-title">
						<h2 id="ek-related-title" class="ek-section__title">Mohlo by vás zajímat</h2>
						<ul class="ek-related__list">
							<?php foreach ( $ek_related as $ek_r ) : ?>
								<li>
									<a class="ek-related__item" href="<?php echo esc_url( get_permalink( $ek_r ) ); ?>">
										<span class="ek-related__cat"><?php echo esc_html( ek_primary_category( $ek_r )->name ?? '' ); ?></span>
										<span class="ek-related__title"><?php echo esc_html( get_the_title( $ek_r ) ); ?></span>
									</a>
								</li>
							<?php endforeach; ?>
						</ul>
					</section>
				<?php endif; ?>

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
						if ( $ek_show_toc ) {
							ek_the_toc();
						}
						if ( $ek_show_recent ) {
							get_template_part( 'template-parts/recent' );
						}
						?>
					</div>
				</aside>
			<?php endif; ?>
			<template id="ek-toc-tpl"><?php ek_the_toc(); ?></template>
		</div>
	<?php endwhile; ?>
</main>
<?php
get_footer();
