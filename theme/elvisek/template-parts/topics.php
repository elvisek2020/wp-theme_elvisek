<?php
/**
 * Dlaždice témat (kategorie s nejvíce články).
 */
defined( 'ABSPATH' ) || exit;

$ek_topics = ek_topics( (int) ek_opt( 'ek_topics_count' ) ?: 8 );
if ( ! $ek_topics ) {
	return;
}
?>
<section class="ek-section" aria-labelledby="ek-topics-title">
	<div class="ek-section__head">
		<h2 id="ek-topics-title" class="ek-section__title">Témata</h2>
	</div>
	<ul class="ek-topics">
		<?php foreach ( $ek_topics as $cat ) : ?>
			<li>
				<a class="ek-topic" href="<?php echo esc_url( get_category_link( $cat ) ); ?>">
					<span class="ek-topic__icon"><?php echo ek_icon( ek_category_icon_name( $cat->slug ), 20 ); ?></span>
					<span class="ek-topic__name"><?php echo esc_html( $cat->name ); ?></span>
					<span class="ek-topic__count"><?php echo esc_html( ek_plural( (int) $cat->count, 'článek', 'články', 'článků' ) ); ?></span>
				</a>
			</li>
		<?php endforeach; ?>
	</ul>
</section>
