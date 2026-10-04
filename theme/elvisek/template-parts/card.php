<?php
/**
 * Karta článku do mřížky.
 */
defined( 'ABSPATH' ) || exit;
?>
<article <?php post_class( 'ek-card' ); ?><?php echo ek_cat_stripe_style(); ?>>
	<a class="ek-card__thumb ek-thumb" href="<?php the_permalink(); ?>" tabindex="-1" aria-hidden="true">
		<?php ek_thumbnail( 'ek-card', array( 'loading' => 'lazy', 'sizes' => '(max-width: 700px) 100vw, 400px' ) ); ?>
	</a>
	<div class="ek-card__body">
		<div class="ek-card__chips"><?php echo ek_category_chips(); ?><?php echo ek_updated_chip(); ?></div>
		<h3 class="ek-card__title"><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h3>
		<p class="ek-card__excerpt"><?php echo esc_html( wp_trim_words( get_the_excerpt(), 34, '…' ) ); ?></p>
		<div class="ek-card__meta">
			<span><?php echo ek_date(); ?> · <?php echo (int) ek_reading_time(); ?> min</span>
			<a class="ek-card__more" href="<?php the_permalink(); ?>" aria-label="Číst: <?php the_title_attribute(); ?>">Číst →</a>
		</div>
	</div>
</article>
