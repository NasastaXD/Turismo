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
}
