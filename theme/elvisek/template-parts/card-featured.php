<?php
/**
 * Hlavní (nejnovější) článek na titulce.
 */
defined( 'ABSPATH' ) || exit;
?>
<article <?php post_class( 'ek-featured' ); ?>>
	<a class="ek-featured__thumb ek-thumb" href="<?php the_permalink(); ?>" tabindex="-1" aria-hidden="true">
		<?php ek_thumbnail( 'ek-featured', array( 'loading' => 'eager', 'fetchpriority' => 'high', 'sizes' => '(max-width: 900px) 100vw, 700px' ) ); ?>
	</a>
	<div class="ek-featured__body">
		<div class="ek-featured__chips">
			<span class="ek-chip ek-chip--solid">Nejnovější</span>
			<?php echo ek_category_chips(); ?>
			<?php echo ek_updated_chip(); ?>
		</div>
		<h2 class="ek-featured__title"><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2>
		<p class="ek-featured__excerpt"><?php echo esc_html( wp_trim_words( get_the_excerpt(), 60, '…' ) ); ?></p>
		<div class="ek-featured__meta">
			<span><?php echo ek_date( 'long' ); ?> · <?php echo (int) ek_reading_time(); ?> min čtení</span>
			<a class="ek-btn" href="<?php the_permalink(); ?>">Číst článek</a>
		</div>
	</div>
</article>
