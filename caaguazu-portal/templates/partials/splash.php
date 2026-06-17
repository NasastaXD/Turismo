<?php
/**
 * Splash animado de marca, mostrado una sola vez por sesión.
 * El <head> agrega .promotur-no-splash al <html> si ya se vio; el JS lo retira tras animar.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }
?>
<div class="promotur-splash" data-splash aria-hidden="true">
	<div class="promotur-splash__mark">
		<svg viewBox="0 0 24 24" width="56" height="56" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
			<path d="M12 21V11"/><path d="M12 11c0-4 2-6 6-7-1 4-3 6-6 7Z"/><path d="M12 11C12 7 10 5 4 4c1 4 3 6 8 7Z"/>
		</svg>
	</div>
	<div class="promotur-splash__name"><?php bloginfo( 'name' ); ?></div>
</div>
