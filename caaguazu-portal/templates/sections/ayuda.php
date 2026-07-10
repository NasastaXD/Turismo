<?php
/** Ayuda / Acerca de: explica qué hace cada parte del portal. */
if ( ! defined( 'ABSPATH' ) ) { exit; }

$page_title = __( 'Ayuda', 'caaguazu-portal' );
$body = function () {
	// [ icono, título, descripción, cap-requerida (o '' para todos) ]
	$bloques = array(
		array( 'home',   __( 'Inicio', 'caaguazu-portal' ), __( 'Tu “pulso” del día: cuántas fichas esperan revisión, cuáles necesitan tu corrección y accesos rápidos según tu rol.', 'caaguazu-portal' ), '' ),
		array( 'edit',   __( 'Nueva ficha', 'caaguazu-portal' ), __( 'El editor guiado de destinos: campos estructurados + un checklist de mínimos que se valida en vivo y bloquea el envío hasta completarlo.', 'caaguazu-portal' ), 'promotur_edit_destino' ),
		array( 'image',  __( 'Salida de campo', 'caaguazu-portal' ), __( 'Capturá foto, nota y ubicación GPS en el lugar, incluso sin señal. Se guarda en tu teléfono y lo sincronizás como borrador cuando vuelve la conexión.', 'caaguazu-portal' ), 'promotur_create_draft' ),
		array( 'doc',    __( 'Mis contenidos', 'caaguazu-portal' ), __( 'Todas tus fichas agrupadas por estado: borrador, enviado, en revisión, necesita cambios, publicado.', 'caaguazu-portal' ), 'promotur_create_draft' ),
		array( 'inbox',  __( 'Cola de revisión', 'caaguazu-portal' ), __( 'Para Promotores: revisá lo enviado, asignátelo, aprobá y publicá, o devolvé con feedback (con comentarios rápidos).', 'caaguazu-portal' ), 'promotur_review_content' ),
		array( 'tasks',  __( 'Tareas', 'caaguazu-portal' ), __( 'Asignaciones con fecha límite y el tablero “lo que falta cubrir”: huecos que los Mini Promotores pueden reclamar.', 'caaguazu-portal' ), 'promotur_view_own_tasks' ),
		array( 'star',   __( 'Curaduría', 'caaguazu-portal' ), __( 'Elegí los destacados de la portada y un banner de temporada con vigencia. La home pública se arma sin tocar código.', 'caaguazu-portal' ), 'promotur_curate_featured' ),
		array( 'shield', __( 'Moderación', 'caaguazu-portal' ), __( 'Aprobá o descartá reseñas, respondé/derivá consultas de visitantes y resolvé reportes de “info desactualizada”.', 'caaguazu-portal' ), 'promotur_moderate' ),
		array( 'team',   __( 'Equipo', 'caaguazu-portal' ), __( 'Gestioná a los Mini Promotores: producción, nivel de confianza y enlaces de invitación.', 'caaguazu-portal' ), 'promotur_manage_team' ),
		array( 'chart',  __( 'Reportes', 'caaguazu-portal' ), __( 'Producción por autor, lo más visto, búsquedas sin resultado (huecos de contenido) y salud del contenido.', 'caaguazu-portal' ), 'promotur_view_reports' ),
		array( 'user',   __( 'Mi perfil', 'caaguazu-portal' ), __( 'Tu portafolio público, vistas totales y tu progreso de nivel de confianza.', 'caaguazu-portal' ), '' ),
	);
	?>
	<div class="promotur-eyebrow"><?php esc_html_e( 'Cómo funciona', 'caaguazu-portal' ); ?></div>
	<h2 class="promotur-h2"><?php esc_html_e( '¿Qué hace cada cosa?', 'caaguazu-portal' ); ?></h2>
	<p class="promotur-muted" style="max-width:60ch">
		<?php esc_html_e( 'Este es el portal de los Promotores Turísticos: una web turística pública con un taller editorial detrás. Los Mini Promotores producen las fichas de destino y los Promotores las revisan y publican.', 'caaguazu-portal' ); ?>
	</p>

	<h3 class="promotur-h3"><?php esc_html_e( 'El flujo editorial', 'caaguazu-portal' ); ?></h3>
	<div class="promotur-card">
		<p style="margin:0">
			<span class="promotur-pill is-draft"><?php esc_html_e( 'Borrador', 'caaguazu-portal' ); ?></span> →
			<span class="promotur-pill is-sent"><?php esc_html_e( 'Enviado', 'caaguazu-portal' ); ?></span> →
			<span class="promotur-pill is-review"><?php esc_html_e( 'En revisión', 'caaguazu-portal' ); ?></span> →
			<span class="promotur-pill is-changes"><?php esc_html_e( 'Necesita cambios', 'caaguazu-portal' ); ?></span> /
			<span class="promotur-pill is-published"><?php esc_html_e( 'Publicado', 'caaguazu-portal' ); ?></span>
		</p>
		<p class="promotur-muted" style="margin:.6rem 0 0">
			<?php esc_html_e( 'Solo lo que un Promotor aprueba sale al público. La confianza se gana: con más aprobaciones subís de nivel (Aprendiz → Promotor Jr → De confianza) y desbloqueás editar publicadas sin re-revisión y, luego, publicar directo.', 'caaguazu-portal' ); ?>
		</p>
	</div>

	<h3 class="promotur-h3"><?php esc_html_e( 'Las secciones', 'caaguazu-portal' ); ?></h3>
	<div class="promotur-grid promotur-grid--2">
		<?php foreach ( $bloques as $b ) :
			if ( $b[3] && ! promotur_can( $b[3] ) ) { continue; } ?>
			<div class="promotur-card">
				<div class="promotur-quick" style="border:0;padding:0;margin-bottom:.4rem">
					<?php echo promotur_icon( $b[0] ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
					<strong><?php echo esc_html( $b[1] ); ?></strong>
				</div>
				<p class="promotur-muted" style="margin:0"><?php echo esc_html( $b[2] ); ?></p>
			</div>
		<?php endforeach; ?>
	</div>

	<h3 class="promotur-h3"><?php esc_html_e( 'Extras', 'caaguazu-portal' ); ?></h3>
	<ul class="promotur-muted">
		<li><?php esc_html_e( 'Instalable como app (PWA) con lectura offline desde el menú lateral.', 'caaguazu-portal' ); ?></li>
		<li><?php esc_html_e( 'Cada ficha pública tiene reseñas, “cómo llegar”, código QR para imprimir y botón para sumarla a “Mi viaje” (itinerario).', 'caaguazu-portal' ); ?></li>
		<li><?php esc_html_e( 'Modo claro/oscuro e idioma (ES/EN/GN) desde la barra superior.', 'caaguazu-portal' ); ?></li>
		<li><?php esc_html_e( 'El acceso es solo por invitación. Pedí tu enlace al equipo de Turismo.', 'caaguazu-portal' ); ?></li>
	</ul>
	<?php
};

include PROMOTUR_DIR . 'templates/shell.php';
