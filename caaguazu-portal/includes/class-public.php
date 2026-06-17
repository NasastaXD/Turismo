<?php
/**
 * Vitrina pública mínima: shortcodes de listado y mapa de destinos publicados.
 *   [promotur_destinos categoria="" cantidad="24"]
 *   [promotur_mapa alto="480px"]
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class PROMOTUR_Public {

	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_shortcode( 'promotur_destinos', array( $this, 'sc_destinos' ) );
		add_shortcode( 'promotur_mapa', array( $this, 'sc_mapa' ) );
		add_shortcode( 'promotur_explorar', array( $this, 'sc_explorar' ) );
		add_shortcode( 'promotur_itinerario', array( $this, 'sc_itinerario' ) );
		add_shortcode( 'promotur_contacto', array( $this, 'sc_contacto' ) );
		add_shortcode( 'promotur_home', array( $this, 'sc_home' ) );
		add_shortcode( 'promotur_destacados', array( $this, 'sc_destacados' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'register_public' ) );
	}

	/**
	 * Registra los assets públicos y los encola en la ficha de destino.
	 */
	public function register_public() {
		wp_register_style( 'promotur', promotur_asset( 'css/caaguazu-portal.css' ), array(), PROMOTUR_VERSION );
		wp_register_script( 'promotur-public', promotur_asset( 'js/caaguazu-portal-public.js' ), array(), PROMOTUR_VERSION, true );
		wp_localize_script( 'promotur-public', 'PROMOTUR_PUB', array(
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'promotur_pub' ),
			'i18n'    => array(
				'sending' => __( 'Enviando…', 'caaguazu-portal' ),
				'error'   => __( 'Ocurrió un error. Probá de nuevo.', 'caaguazu-portal' ),
				'added'   => __( 'Agregado a tu viaje', 'caaguazu-portal' ),
				'copied'  => __( 'Enlace copiado', 'caaguazu-portal' ),
				'empty'   => __( 'Tu viaje está vacío. Agregá destinos desde sus fichas.', 'caaguazu-portal' ),
			),
		) );
		wp_register_script( 'promotur-qrcode', promotur_asset( 'js/qrcode.js' ), array(), '1.4.4', true );
		if ( is_singular( PROMOTUR_Destinos::CPT ) ) {
			self::ensure_assets();
			wp_enqueue_script( 'promotur-qrcode' );
		}
	}

	/** Encola el bundle público (CSS + JS). Útil desde shortcodes. */
	public static function ensure_assets() {
		wp_enqueue_style( 'promotur', promotur_asset( 'css/caaguazu-portal.css' ), array(), PROMOTUR_VERSION );
		wp_enqueue_script( 'promotur-public' );
	}

	/**
	 * Query de destinos publicados.
	 */
	private function query( $atts ) {
		$args = array(
			'post_type'      => PROMOTUR_Destinos::CPT,
			'post_status'    => 'publish',
			'posts_per_page' => (int) ( $atts['cantidad'] ?? 24 ),
		);
		if ( ! empty( $atts['categoria'] ) ) {
			$args['tax_query'] = array( array(
				'taxonomy' => 'promotur_categoria',
				'field'    => 'slug',
				'terms'    => sanitize_title( $atts['categoria'] ),
			) );
		}
		return new WP_Query( $args );
	}

	/**
	 * Grid de fichas.
	 */
	public function sc_destinos( $atts ) {
		$atts = shortcode_atts( array( 'categoria' => '', 'cantidad' => 24 ), $atts, 'promotur_destinos' );
		$q    = $this->query( $atts );
		if ( ! $q->have_posts() ) {
			return '<p>' . esc_html__( 'Todavía no hay destinos publicados.', 'caaguazu-portal' ) . '</p>';
		}

		wp_enqueue_style( 'promotur', promotur_asset( 'css/caaguazu-portal.css' ), array(), PROMOTUR_VERSION );

		ob_start();
		echo '<div class="promotur-grid promotur-grid--3 promotur-vitrina">';
		while ( $q->have_posts() ) {
			$q->the_post();
			$id      = get_the_ID();
			$portada = get_post_meta( $id, '_promotur_portada', true );
			$gancho  = get_post_meta( $id, '_promotur_gancho', true );
			$img     = $portada ? wp_get_attachment_image_url( (int) $portada, 'medium_large' ) : get_the_post_thumbnail_url( $id, 'medium_large' );
			?>
			<a class="promotur-card promotur-card--link promotur-vitrina__card" href="<?php the_permalink(); ?>">
				<span class="promotur-vitrina__media"<?php echo $img ? ' style="background-image:url(' . esc_url( $img ) . ')"' : ''; ?>></span>
				<span class="promotur-vitrina__body">
					<span class="promotur-row__title"><?php the_title(); ?></span>
					<?php if ( $gancho ) : ?><span class="promotur-muted"><?php echo esc_html( $gancho ); ?></span><?php endif; ?>
				</span>
			</a>
			<?php
		}
		echo '</div>';
		wp_reset_postdata();
		return ob_get_clean();
	}

	/**
	 * Mapa Leaflet de destinos con coordenadas.
	 */
	public function sc_mapa( $atts ) {
		$atts = shortcode_atts( array( 'alto' => '480px', 'categoria' => '', 'cantidad' => -1 ), $atts, 'promotur_mapa' );

		wp_enqueue_style( 'promotur-leaflet', 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css', array(), '1.9.4' );
		wp_enqueue_script( 'promotur-leaflet', 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js', array(), '1.9.4', true );
		wp_enqueue_style( 'promotur', promotur_asset( 'css/caaguazu-portal.css' ), array(), PROMOTUR_VERSION );

		$q      = $this->query( $atts );
		$points = array();
		while ( $q->have_posts() ) {
			$q->the_post();
			$id  = get_the_ID();
			$lat = get_post_meta( $id, '_promotur_lat', true );
			$lng = get_post_meta( $id, '_promotur_lng', true );
			if ( '' === trim( (string) $lat ) || '' === trim( (string) $lng ) ) { continue; }
			$points[] = array(
				'name' => get_the_title(),
				'lat'  => (float) $lat,
				'lng'  => (float) $lng,
				'url'  => get_permalink(),
			);
		}
		wp_reset_postdata();

		$map_id = 'promotur-map-' . wp_unique_id();
		$data   = wp_json_encode( array(
			'center' => apply_filters( 'promotur_map_center', array( 'lat' => -25.4646, 'lng' => -56.0173, 'zoom' => 12 ) ),
			'points' => $points,
		) );

		$script = <<<JS
(function(){function init(){if(typeof L==='undefined'){return setTimeout(init,150);}var el=document.getElementById('{$map_id}');if(!el||el.dataset.done)return;el.dataset.done=1;var d={$data};var m=L.map(el,{scrollWheelZoom:false}).setView([d.center.lat,d.center.lng],d.center.zoom);L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',{maxZoom:19,attribution:'© OpenStreetMap'}).addTo(m);var b=[];(d.points||[]).forEach(function(p){var mk=L.marker([p.lat,p.lng]).addTo(m);mk.bindPopup('<strong>'+p.name+'</strong><br><a href="'+p.url+'">Ver ficha →</a>');b.push([p.lat,p.lng]);});if(b.length>1){m.fitBounds(b,{padding:[40,40],maxZoom:15});}el.addEventListener('click',function(){m.scrollWheelZoom.enable();});}init();})();
JS;
		wp_add_inline_script( 'promotur-leaflet', $script );

		return sprintf(
			'<div class="promotur-publicmap" id="%s" style="height:%s"></div>',
			esc_attr( $map_id ),
			esc_attr( $atts['alto'] )
		);
	}

	/**
	 * Explorar con filtros (GET) + grid. Filtros: categoria, zona, etiqueta, q, gratis.
	 */
	public function sc_explorar( $atts ) {
		self::ensure_assets();

		$cat    = isset( $_GET['cat'] ) ? sanitize_title( wp_unslash( $_GET['cat'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		$zona   = isset( $_GET['zona'] ) ? sanitize_title( wp_unslash( $_GET['zona'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		$etq    = isset( $_GET['etq'] ) ? sanitize_title( wp_unslash( $_GET['etq'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		$q      = isset( $_GET['q'] ) ? sanitize_text_field( wp_unslash( $_GET['q'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification

		$args = array(
			'post_type'      => PROMOTUR_Destinos::CPT,
			'post_status'    => 'publish',
			'posts_per_page' => 36,
			's'              => $q,
		);
		$tax = array();
		if ( $cat )  { $tax[] = array( 'taxonomy' => 'promotur_categoria', 'field' => 'slug', 'terms' => $cat ); }
		if ( $zona ) { $tax[] = array( 'taxonomy' => 'promotur_zona', 'field' => 'slug', 'terms' => $zona ); }
		if ( $etq )  { $tax[] = array( 'taxonomy' => 'promotur_etiqueta', 'field' => 'slug', 'terms' => $etq ); }
		if ( $tax )  { $args['tax_query'] = array_merge( array( 'relation' => 'AND' ), $tax ); }

		$query = new WP_Query( $args );

		// Registrar búsquedas sin resultado (huecos de contenido).
		if ( '' !== $q && ! $query->have_posts() && class_exists( 'PROMOTUR_Stats' ) ) {
			PROMOTUR_Stats::log_empty_search( $q );
		}

		ob_start();
		?>
		<div class="promotur-explorar">
			<form class="promotur-explorar__filters" method="get">
				<input type="search" name="q" value="<?php echo esc_attr( $q ); ?>" placeholder="<?php esc_attr_e( 'Buscar destinos…', 'caaguazu-portal' ); ?>">
				<?php
				$this->filter_select( 'cat', 'promotur_categoria', $cat, __( 'Categoría', 'caaguazu-portal' ) );
				$this->filter_select( 'zona', 'promotur_zona', $zona, __( 'Zona', 'caaguazu-portal' ) );
				$this->filter_select( 'etq', 'promotur_etiqueta', $etq, __( 'Etiqueta', 'caaguazu-portal' ) );
				?>
				<button type="submit" class="promotur-btn promotur-btn--primary"><?php esc_html_e( 'Filtrar', 'caaguazu-portal' ); ?></button>
				<a class="promotur-btn promotur-btn--ghost" href="<?php echo esc_url( strtok( $_SERVER['REQUEST_URI'] ?? '', '?' ) ); ?>"><?php esc_html_e( 'Limpiar', 'caaguazu-portal' ); ?></a>
			</form>

			<?php if ( ! $query->have_posts() ) : ?>
				<p class="promotur-muted"><?php esc_html_e( 'No encontramos destinos con esos filtros.', 'caaguazu-portal' ); ?></p>
			<?php else : ?>
				<div class="promotur-grid promotur-grid--3 promotur-vitrina">
					<?php while ( $query->have_posts() ) : $query->the_post();
						$id  = get_the_ID();
						$g   = get_post_meta( $id, '_promotur_gancho', true );
						$pid = get_post_meta( $id, '_promotur_portada', true );
						$img = $pid ? wp_get_attachment_image_url( (int) $pid, 'medium_large' ) : get_the_post_thumbnail_url( $id, 'medium_large' );
						?>
						<a class="promotur-card promotur-card--link promotur-vitrina__card" href="<?php the_permalink(); ?>">
							<span class="promotur-vitrina__media"<?php echo $img ? ' style="background-image:url(' . esc_url( $img ) . ')"' : ''; ?>></span>
							<span class="promotur-vitrina__body">
								<span class="promotur-row__title"><?php the_title(); ?></span>
								<?php if ( $g ) : ?><span class="promotur-muted"><?php echo esc_html( $g ); ?></span><?php endif; ?>
							</span>
						</a>
					<?php endwhile; ?>
				</div>
			<?php endif; ?>
			<?php wp_reset_postdata(); ?>
		</div>
		<?php
		return ob_get_clean();
	}

	/** Select de filtro por taxonomía. */
	private function filter_select( $param, $taxonomy, $current, $label ) {
		$terms = get_terms( array( 'taxonomy' => $taxonomy, 'hide_empty' => true ) );
		if ( is_wp_error( $terms ) || empty( $terms ) ) { return; }
		echo '<select name="' . esc_attr( $param ) . '">';
		echo '<option value="">' . esc_html( $label ) . '</option>';
		foreach ( $terms as $t ) {
			printf( '<option value="%s" %s>%s</option>', esc_attr( $t->slug ), selected( $current, $t->slug, false ), esc_html( $t->name ) );
		}
		echo '</select>';
	}

	/**
	 * Armador de itinerario (sin cuenta). El estado vive en localStorage; el JS
	 * resuelve los IDs contra este mapa de destinos publicados.
	 */
	public function sc_itinerario( $atts ) {
		self::ensure_assets();

		$map = array();
		$q   = new WP_Query( array( 'post_type' => PROMOTUR_Destinos::CPT, 'post_status' => 'publish', 'posts_per_page' => -1 ) );
		while ( $q->have_posts() ) {
			$q->the_post();
			$id  = get_the_ID();
			$lat = get_post_meta( $id, '_promotur_lat', true );
			$lng = get_post_meta( $id, '_promotur_lng', true );
			$map[ $id ] = array(
				'title' => get_the_title(),
				'url'   => get_permalink(),
				'lat'   => $lat !== '' ? (float) $lat : null,
				'lng'   => $lng !== '' ? (float) $lng : null,
			);
		}
		wp_reset_postdata();
		wp_add_inline_script( 'promotur-public', 'window.PROMOTUR_DESTINOS=' . wp_json_encode( $map ) . ';', 'before' );

		ob_start();
		?>
		<div class="promotur-itinerario" data-itinerario>
			<div class="promotur-pagehead">
				<h2 class="text-h3"><?php esc_html_e( 'Mi viaje', 'caaguazu-portal' ); ?></h2>
				<div class="promotur-inline-form">
					<button type="button" class="promotur-btn promotur-btn--ghost promotur-btn--small" data-itin-share><?php esc_html_e( 'Compartir', 'caaguazu-portal' ); ?></button>
					<button type="button" class="promotur-btn promotur-btn--ghost promotur-btn--small" onclick="window.print()"><?php esc_html_e( 'Imprimir / PDF', 'caaguazu-portal' ); ?></button>
					<button type="button" class="promotur-btn promotur-btn--ghost promotur-btn--small" data-itin-clear><?php esc_html_e( 'Vaciar', 'caaguazu-portal' ); ?></button>
				</div>
			</div>
			<div class="promotur-itinerario__list" data-itin-list></div>
			<span class="promotur-form-msg" data-itin-msg aria-live="polite"></span>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Formulario de contacto/consulta general.
	 */
	public function sc_contacto( $atts ) {
		$atts = shortcode_atts( array( 'destino' => 0 ), $atts, 'promotur_contacto' );
		self::ensure_assets();
		return self::contact_form( (int) $atts['destino'] );
	}

	/**
	 * Markup del formulario de consulta (reutilizable desde el single).
	 */
	public static function contact_form( $destino = 0 ) {
		$user = wp_get_current_user();
		ob_start();
		?>
		<form class="promotur-form promotur-contacto-form" data-consulta-form data-destino="<?php echo esc_attr( $destino ); ?>">
			<h3 class="text-h3"><?php esc_html_e( '¿Tenés una consulta?', 'caaguazu-portal' ); ?></h3>
			<div class="promotur-grid promotur-grid--2">
				<label class="promotur-field"><span><?php esc_html_e( 'Nombre', 'caaguazu-portal' ); ?></span><input type="text" name="nombre" value="<?php echo esc_attr( $user->exists() ? $user->display_name : '' ); ?>" required></label>
				<label class="promotur-field"><span><?php esc_html_e( 'Email', 'caaguazu-portal' ); ?></span><input type="email" name="email" value="<?php echo esc_attr( $user->exists() ? $user->user_email : '' ); ?>" required></label>
			</div>
			<label class="promotur-field"><span><?php esc_html_e( 'Mensaje', 'caaguazu-portal' ); ?></span><textarea name="mensaje" rows="3" required></textarea></label>
			<button type="submit" class="promotur-btn promotur-btn--primary"><?php esc_html_e( 'Enviar consulta', 'caaguazu-portal' ); ?></button>
			<span class="promotur-form-msg" data-form-msg aria-live="polite"></span>
		</form>
		<?php
		return ob_get_clean();
	}

	/**
	 * Tarjeta de destino reutilizable.
	 */
	private static function card( $id ) {
		$pid = get_post_meta( $id, '_promotur_portada', true );
		$img = $pid ? wp_get_attachment_image_url( (int) $pid, 'medium_large' ) : get_the_post_thumbnail_url( $id, 'medium_large' );
		$g   = get_post_meta( $id, '_promotur_gancho', true );
		ob_start();
		?>
		<a class="promotur-card promotur-card--link promotur-vitrina__card" href="<?php echo esc_url( get_permalink( $id ) ); ?>">
			<span class="promotur-vitrina__media"<?php echo $img ? ' style="background-image:url(' . esc_url( $img ) . ')"' : ''; ?>></span>
			<span class="promotur-vitrina__body">
				<span class="promotur-row__title"><?php echo esc_html( get_the_title( $id ) ); ?></span>
				<?php if ( $g ) : ?><span class="promotur-muted"><?php echo esc_html( $g ); ?></span><?php endif; ?>
			</span>
		</a>
		<?php
		return ob_get_clean();
	}

	/**
	 * Grid de destacados (cae a "recién publicado" si no hay curaduría).
	 */
	public function sc_destacados( $atts ) {
		self::ensure_assets();
		$ids = PROMOTUR_Curaduria::destacados();
		if ( empty( $ids ) ) {
			$ids = get_posts( array( 'post_type' => PROMOTUR_Destinos::CPT, 'post_status' => 'publish', 'posts_per_page' => 6, 'fields' => 'ids' ) );
		}
		if ( empty( $ids ) ) { return ''; }
		$out = '<div class="promotur-grid promotur-grid--3 promotur-vitrina">';
		foreach ( $ids as $id ) {
			if ( 'publish' === get_post_status( $id ) ) { $out .= self::card( $id ); }
		}
		return $out . '</div>';
	}

	/**
	 * Home pública curada: banner + hero + destacados + recién publicado + mapa.
	 */
	public function sc_home( $atts ) {
		self::ensure_assets();
		$destacados = array_filter( PROMOTUR_Curaduria::destacados(), function ( $id ) { return 'publish' === get_post_status( $id ); } );

		ob_start();
		echo '<div class="promotur-homeblocks">';

		// Banner de temporada.
		if ( PROMOTUR_Curaduria::banner_visible() ) {
			$b = PROMOTUR_Curaduria::banner();
			$tag = $b['url'] ? 'a' : 'div';
			printf(
				'<%1$s class="promotur-banner"%2$s><strong>%3$s</strong>%4$s</%1$s>',
				$tag,
				$b['url'] ? ' href="' . esc_url( $b['url'] ) . '"' : '',
				esc_html( $b['title'] ),
				$b['text'] ? ' <span>' . esc_html( $b['text'] ) . '</span>' : ''
			);
		}

		// Hero = primer destacado.
		if ( ! empty( $destacados ) ) {
			$hero = array_shift( $destacados );
			$pid  = get_post_meta( $hero, '_promotur_portada', true );
			$img  = $pid ? wp_get_attachment_image_url( (int) $pid, 'large' ) : get_the_post_thumbnail_url( $hero, 'large' );
			?>
			<a class="promotur-hero" href="<?php echo esc_url( get_permalink( $hero ) ); ?>"<?php echo $img ? ' style="background-image:linear-gradient(0deg,rgba(0,0,0,.55),rgba(0,0,0,.1)),url(' . esc_url( $img ) . ')"' : ''; ?>>
				<span class="promotur-hero__eyebrow"><?php esc_html_e( 'Destacado', 'caaguazu-portal' ); ?></span>
				<span class="promotur-hero__title"><?php echo esc_html( get_the_title( $hero ) ); ?></span>
				<span class="promotur-hero__gancho"><?php echo esc_html( get_post_meta( $hero, '_promotur_gancho', true ) ); ?></span>
			</a>
			<?php
		}

		// Resto de destacados.
		if ( ! empty( $destacados ) ) {
			echo '<h2 class="text-h3">' . esc_html__( 'Qué no te podés perder', 'caaguazu-portal' ) . '</h2>';
			echo '<div class="promotur-grid promotur-grid--3 promotur-vitrina">';
			foreach ( $destacados as $id ) { echo self::card( $id ); } // phpcs:ignore WordPress.Security.EscapeOutput
			echo '</div>';
		}

		// Recién publicado.
		$recientes = get_posts( array( 'post_type' => PROMOTUR_Destinos::CPT, 'post_status' => 'publish', 'posts_per_page' => 3, 'fields' => 'ids' ) );
		if ( $recientes ) {
			echo '<h2 class="text-h3">' . esc_html__( 'Recién publicado', 'caaguazu-portal' ) . '</h2>';
			echo '<div class="promotur-grid promotur-grid--3 promotur-vitrina">';
			foreach ( $recientes as $id ) { echo self::card( $id ); } // phpcs:ignore WordPress.Security.EscapeOutput
			echo '</div>';
		}

		// Mapa.
		echo '<h2 class="text-h3">' . esc_html__( 'En el mapa', 'caaguazu-portal' ) . '</h2>';
		echo do_shortcode( '[promotur_mapa alto="420px"]' );

		echo '</div>';
		return ob_get_clean();
	}
}
