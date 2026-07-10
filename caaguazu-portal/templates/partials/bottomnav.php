<?php
/**
 * Barra de navegación inferior para teléfonos (oculta en desktop por CSS).
 * Muestra hasta 5 accesos gateados por capability.
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

$current = promotur_current_route();
$candidatos = array(
	array( 'icon' => 'home',  'route' => 'panel',                'label' => __( 'Inicio', 'caaguazu-portal' ),     'cap' => 'promotur_view_panel' ),
	array( 'icon' => 'doc',   'route' => 'panel/mis-contenidos', 'label' => __( 'Contenidos', 'caaguazu-portal' ), 'cap' => 'promotur_create_draft' ),
	array( 'icon' => 'image', 'route' => 'panel/captura',        'label' => __( 'Campo', 'caaguazu-portal' ),      'cap' => 'promotur_create_draft' ),
	array( 'icon' => 'inbox', 'route' => 'panel/revision',       'label' => __( 'Revisar', 'caaguazu-portal' ),    'cap' => 'promotur_review_content' ),
	array( 'icon' => 'user',  'route' => 'panel/perfil',         'label' => __( 'Perfil', 'caaguazu-portal' ),     'cap' => 'promotur_edit_profile' ),
);
$items = array_values( array_filter( $candidatos, function ( $i ) { return promotur_can( $i['cap'] ); } ) );
$items = array_slice( $items, 0, 5 );
if ( empty( $items ) ) { return; }
?>
<nav class="promotur-bottomnav" aria-label="<?php esc_attr_e( 'Navegación rápida', 'caaguazu-portal' ); ?>">
	<?php foreach ( $items as $it ) :
		$seg    = ( 'panel' === $it['route'] ) ? 'home' : substr( $it['route'], strlen( 'panel/' ) );
		$active = ( 'home' === $seg ) ? ( 'home' === $current ) : ( '' !== $current && 0 === strpos( $current, $seg ) );
		?>
		<a class="promotur-bottomnav__item<?php echo $active ? ' is-active' : ''; ?>" href="<?php echo esc_url( promotur_url( $it['route'] ) ); ?>"<?php echo $active ? ' aria-current="page"' : ''; ?>>
			<?php echo promotur_icon( $it['icon'] ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
			<span><?php echo esc_html( $it['label'] ); ?></span>
		</a>
	<?php endforeach; ?>
</nav>
