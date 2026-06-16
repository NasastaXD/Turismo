<?php
/**
 * Search form template.
 */
?>
<form role="search" method="get" class="flex items-center gap-2 max-w-xl" action="<?php echo esc_url( home_url( '/' ) ); ?>">
	<label class="sr-only" for="caaguazu-s"><?php esc_html_e( 'Buscar', 'caaguazu' ); ?></label>
	<input
		type="search"
		id="caaguazu-s"
		name="s"
		placeholder="<?php esc_attr_e( 'Buscar en el portal…', 'caaguazu' ); ?>"
		value="<?php echo esc_attr( get_search_query() ); ?>"
		class="flex-1 px-4 py-3 rounded-full border border-border bg-snow text-text-primary focus:outline-none focus:ring-2 focus:ring-forest-deep"
	>
	<button type="submit" class="pill-link">
		<?php esc_html_e( 'Buscar', 'caaguazu' ); ?>
	</button>
</form>
