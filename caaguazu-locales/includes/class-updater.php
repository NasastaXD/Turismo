<?php
/**
 * Autoactualización vía WordPress para plugin y tema.
 *
 * Consulta un manifiesto JSON remoto y engancha los transients de actualización
 * nativos de WordPress, de modo que las actualizaciones aparezcan en
 * Escritorio → Actualizaciones igual que cualquier plugin/tema del repositorio.
 *
 * Formato del manifiesto JSON:
 * {
 *   "version": "1.1.0",
 *   "download_url": "https://.../caaguazu-locales-1.1.0.zip",
 *   "requires": "6.0",
 *   "tested": "6.5",
 *   "requires_php": "7.4",
 *   "last_updated": "2026-06-16",
 *   "sections": { "description": "…", "changelog": "…" }
 * }
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class CGZ_Updater {

	/** @var array config */
	private $cfg;

	/**
	 * @param array $cfg { type: plugin|theme, slug, basename(plugin), version, metadata_url }
	 */
	public function __construct( array $cfg ) {
		$this->cfg = wp_parse_args( $cfg, array(
			'type'         => 'plugin',
			'slug'         => '',
			'basename'     => '',
			'version'      => '0',
			'metadata_url' => '',
		) );

		if ( empty( $this->cfg['metadata_url'] ) || empty( $this->cfg['slug'] ) ) {
			return;
		}

		if ( 'theme' === $this->cfg['type'] ) {
			add_filter( 'pre_set_site_transient_update_themes', array( $this, 'check_theme' ) );
		} else {
			add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'check_plugin' ) );
			add_filter( 'plugins_api', array( $this, 'plugin_info' ), 20, 3 );
		}

		// Limpia la caché del manifiesto al pedir comprobación manual.
		add_action( 'upgrader_process_complete', array( $this, 'flush_cache' ), 10, 0 );
	}

	/**
	 * Descarga (con caché de 6 h) el manifiesto remoto.
	 *
	 * @return array|null
	 */
	private function get_remote() {
		$cache_key = 'cgz_update_' . md5( $this->cfg['metadata_url'] );
		$cached    = get_transient( $cache_key );
		if ( false !== $cached ) {
			return is_array( $cached ) ? $cached : null;
		}

		$response = wp_remote_get( $this->cfg['metadata_url'], array(
			'timeout' => 15,
			'headers' => array( 'Accept' => 'application/json' ),
		) );

		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			set_transient( $cache_key, 'none', HOUR_IN_SECONDS );
			return null;
		}

		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $data ) || empty( $data['version'] ) ) {
			set_transient( $cache_key, 'none', HOUR_IN_SECONDS );
			return null;
		}

		set_transient( $cache_key, $data, 6 * HOUR_IN_SECONDS );
		return $data;
	}

	public function flush_cache() {
		delete_transient( 'cgz_update_' . md5( $this->cfg['metadata_url'] ) );
	}

	/* ----- Plugin ----- */

	public function check_plugin( $transient ) {
		if ( empty( $transient->checked ) ) {
			return $transient;
		}
		$remote = $this->get_remote();
		if ( ! $remote ) {
			return $transient;
		}

		if ( version_compare( $remote['version'], $this->cfg['version'], '>' ) ) {
			$transient->response[ $this->cfg['basename'] ] = (object) array(
				'slug'        => $this->cfg['slug'],
				'plugin'      => $this->cfg['basename'],
				'new_version' => $remote['version'],
				'package'     => $remote['download_url'] ?? '',
				'url'         => $remote['homepage'] ?? '',
				'tested'      => $remote['tested'] ?? '',
				'requires'    => $remote['requires'] ?? '',
				'requires_php'=> $remote['requires_php'] ?? '',
			);
		} else {
			$transient->no_update[ $this->cfg['basename'] ] = (object) array(
				'slug'        => $this->cfg['slug'],
				'plugin'      => $this->cfg['basename'],
				'new_version' => $this->cfg['version'],
			);
		}
		return $transient;
	}

	/**
	 * Información del plugin para la ventana modal "Ver detalles".
	 */
	public function plugin_info( $result, $action, $args ) {
		if ( 'plugin_information' !== $action || empty( $args->slug ) || $args->slug !== $this->cfg['slug'] ) {
			return $result;
		}
		$remote = $this->get_remote();
		if ( ! $remote ) {
			return $result;
		}

		return (object) array(
			'name'          => $remote['name'] ?? 'Caaguazú Locales',
			'slug'          => $this->cfg['slug'],
			'version'       => $remote['version'],
			'author'        => $remote['author'] ?? 'Municipalidad de Caaguazú',
			'homepage'      => $remote['homepage'] ?? '',
			'requires'      => $remote['requires'] ?? '',
			'tested'        => $remote['tested'] ?? '',
			'requires_php'  => $remote['requires_php'] ?? '',
			'last_updated'  => $remote['last_updated'] ?? '',
			'download_link' => $remote['download_url'] ?? '',
			'sections'      => $remote['sections'] ?? array( 'description' => '' ),
		);
	}

	/* ----- Tema ----- */

	public function check_theme( $transient ) {
		if ( empty( $transient->checked ) ) {
			return $transient;
		}
		$remote = $this->get_remote();
		if ( ! $remote ) {
			return $transient;
		}

		if ( version_compare( $remote['version'], $this->cfg['version'], '>' ) ) {
			$transient->response[ $this->cfg['slug'] ] = array(
				'theme'       => $this->cfg['slug'],
				'new_version' => $remote['version'],
				'package'     => $remote['download_url'] ?? '',
				'url'         => $remote['homepage'] ?? '',
				'requires'    => $remote['requires'] ?? '',
				'requires_php'=> $remote['requires_php'] ?? '',
			);
		}
		return $transient;
	}
}
