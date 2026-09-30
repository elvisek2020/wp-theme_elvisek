<?php
defined( 'ABSPATH' ) || exit;
$ek_sid = 'ek-s-' . wp_unique_id();
?>
<form role="search" method="get" class="ek-search" action="<?php echo esc_url( home_url( '/' ) ); ?>">
	<label class="screen-reader-text" for="<?php echo esc_attr( $ek_sid ); ?>">Hledat</label>
	<span class="ek-search__icon"><?php echo ek_icon( 'search' ); ?></span>
	<input id="<?php echo esc_attr( $ek_sid ); ?>" class="ek-search__input" type="search" name="s" value="<?php echo esc_attr( get_search_query() ); ?>" placeholder="Hledat návody…" autocomplete="off">
	<button type="submit" class="ek-btn">Hledat</button>
</form>
