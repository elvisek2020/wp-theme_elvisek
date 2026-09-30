<?php
/**
 * Box „Novinky“ — poslední články (nahrazuje widget z Graphene).
 */
defined( 'ABSPATH' ) || exit;

$ek_recent = get_posts( array(
	'posts_per_page'      => 5,
	'post__not_in'        => is_single() ? array( get_the_ID() ) : array(),
	'ignore_sticky_posts' => true,
	'no_found_rows'       => true,
) );
if ( ! $ek_recent ) {
	return;
}
?>
<nav class="ek-box ek-box--tint" aria-label="Novinky">
	<h2 class="ek-box__title">Novinky</h2>
	<ul class="ek-box__list">
		<?php foreach ( $ek_recent as $ek_p ) : ?>
			<li><a href="<?php echo esc_url( get_permalink( $ek_p ) ); ?>"><?php echo esc_html( get_the_title( $ek_p ) ); ?></a></li>
		<?php endforeach; ?>
	</ul>
</nav>
