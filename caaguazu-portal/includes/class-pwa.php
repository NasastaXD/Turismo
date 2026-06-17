<?php
/**
 * PWA: manifest, service worker, íconos y página offline.
 * Cada recurso se sirve con su propio MIME desde el enrutador (sin pasar por el theme).
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class PROMOTUR_PWA {

	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {}

	/**
	 * Color de marca (hex aproximado del verde del sitio) para metas que no aceptan oklch.
	 */
	private function brand_hex() {
		return apply_filters( 'promotur_brand_hex', '#155c33' );
	}

	/**
	 * manifest.webmanifest
	 */
	public function render_manifest() {
		header( 'Content-Type: application/manifest+json; charset=utf-8' );
		$data = array(
			'name'             => get_bloginfo( 'name' ) . ' — Promotores',
			'short_name'       => 'Promotores',
			'start_url'        => '/panel',
			'scope'            => '/',
			'display'          => 'standalone',
			'orientation'      => 'portrait',
			'background_color' => '#0f1713',
			'theme_color'      => $this->brand_hex(),
			'lang'             => 'es',
			'icons'            => array(
				array( 'src' => home_url( '/promotur-icon-192.png' ), 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any' ),
				array( 'src' => home_url( '/promotur-icon-512.png' ), 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any' ),
				array( 'src' => home_url( '/promotur-icon-512.png' ), 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable' ),
			),
		);
		echo wp_json_encode( $data );
	}

	/**
	 * Service worker (network-first con fallback offline).
	 */
	public function render_sw() {
		header( 'Content-Type: application/javascript; charset=utf-8' );
		header( 'Service-Worker-Allowed: /' );

		$version  = PROMOTUR_VERSION;
		$offline  = home_url( '/promotur-offline' );
		$css      = promotur_asset( 'css/caaguazu-portal.css' );
		$js       = promotur_asset( 'js/caaguazu-portal.js' );
		$icon     = home_url( '/promotur-icon-192.png' );

		$precache = wp_json_encode( array( $offline, $css, $js, $icon ) );

		echo "/* Promotores Turísticos SW v{$version} */\n";
		?>
const CACHE = 'promotur-v<?php echo esc_js( $version ); ?>';
const PRECACHE = <?php echo $precache; // phpcs:ignore ?>;
const OFFLINE = <?php echo wp_json_encode( $offline ); ?>;

self.addEventListener('install', (e) => {
  e.waitUntil(caches.open(CACHE).then((c) => c.addAll(PRECACHE)).then(() => self.skipWaiting()));
});

self.addEventListener('activate', (e) => {
  e.waitUntil(
    caches.keys().then((keys) => Promise.all(keys.filter((k) => k !== CACHE).map((k) => caches.delete(k))))
      .then(() => self.clients.claim())
  );
});

self.addEventListener('fetch', (e) => {
  const req = e.request;
  if (req.method !== 'GET') { return; }

  // Network-first; cae a caché y, si es navegación, a la página offline.
  e.respondWith(
    fetch(req).then((res) => {
      const copy = res.clone();
      caches.open(CACHE).then((c) => c.put(req, copy)).catch(() => {});
      return res;
    }).catch(() =>
      caches.match(req).then((hit) => hit || (req.mode === 'navigate' ? caches.match(OFFLINE) : Response.error()))
    )
  );
});
		<?php
	}

	/**
	 * Ícono PNG por tamaño. Usa archivo exacto si existe; si no, redimensiona el de 512 con GD.
	 *
	 * @param int $size
	 */
	public function render_icon( $size ) {
		$size = max( 16, min( 1024, (int) $size ) );
		header( 'Content-Type: image/png' );
		header( 'Cache-Control: public, max-age=2592000' );

		$exact = PROMOTUR_DIR . 'assets/icons/icon-' . $size . '.png';
		if ( file_exists( $exact ) ) {
			readfile( $exact );
			return;
		}

		$base = PROMOTUR_DIR . 'assets/icons/icon-512.png';
		if ( file_exists( $base ) && function_exists( 'imagecreatefrompng' ) ) {
			$src = imagecreatefrompng( $base );
			$dst = imagecreatetruecolor( $size, $size );
			imagealphablending( $dst, false );
			imagesavealpha( $dst, true );
			imagecopyresampled( $dst, $src, 0, 0, 0, 0, $size, $size, imagesx( $src ), imagesy( $src ) );
			imagepng( $dst );
			imagedestroy( $src );
			imagedestroy( $dst );
			return;
		}

		// Último recurso: servir el de 512 tal cual o un PNG vacío.
		if ( file_exists( $base ) ) {
			readfile( $base );
		}
	}

	/**
	 * Página offline (auto-contenida; se cachea en el SW).
	 */
	public function render_offline() {
		$css = promotur_asset( 'css/caaguazu-portal.css' );
		?>
<!DOCTYPE html>
<html lang="es">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title><?php echo esc_html__( 'Sin conexión', 'caaguazu-portal' ); ?></title>
	<link rel="stylesheet" href="<?php echo esc_url( $css ); ?>">
	<meta name="theme-color" content="<?php echo esc_attr( $this->brand_hex() ); ?>">
</head>
<body class="promotur-app promotur-offline-page">
	<div class="promotur-offline-box">
		<div class="promotur-eyebrow"><?php esc_html_e( 'Promotores Turísticos', 'caaguazu-portal' ); ?></div>
		<h1><?php esc_html_e( 'Estás sin conexión', 'caaguazu-portal' ); ?></h1>
		<p><?php esc_html_e( 'No pudimos cargar esta pantalla. Revisá tu conexión e intentá de nuevo.', 'caaguazu-portal' ); ?></p>
		<button class="promotur-btn promotur-btn--primary" onclick="location.reload()"><?php esc_html_e( 'Reintentar', 'caaguazu-portal' ); ?></button>
	</div>
</body>
</html>
		<?php
	}
}
