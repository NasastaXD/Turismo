<?php
/**
 * Autoactualización del tema vía WordPress.
 *
 * Consulta un manifiesto JSON remoto y engancha el transient de actualización
 * de temas, para que las actualizaciones del tema aparezcan en
 * Escritorio → Actualizaciones como cualquier tema del repositorio.
 *
 * Manifiesto JSON esperado:
 * { "version": "1.1.0", "download_url": "https://…/caaguazu-theme-1.1.0.zip",
 *   "requires": "6.0", "requires_php": "7.4", "url": "https://…" }
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class Caaguazu_Theme_Updater {

	private $slug;
	private $version;
	private $manifest_url;

	public function __construct( $slug, $version, $manifest_url ) {
		$this->slug         = $slug;
		$this->version      = $version;
		$this->manifest_url = $manifest_url;

		add_filter( 'pre_set_site_transient_update_themes', array( $this, 'check' ) );
	}

	private function get_remote() {
		$key    = 'caaguazu_theme_update_' . md5( $this->manifest_url );
		$cached = get_transient( $key );
		if ( false !== $cached ) {
			return is_array( $cached ) ? $cached : null;
		}

		$response = wp_remote_get( $this->manifest_url, array( 'timeout' => 15 ) );
		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			set_transient( $key, 'none', HOUR_IN_SECONDS );
			return null;
		}

		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $data ) || empty( $data['version'] ) ) {
			set_transient( $key, 'none', HOUR_IN_SECONDS );
			return null;
		}

		set_transient( $key, $data, 6 * HOUR_IN_SECONDS );
		return $data;
	}

	public function check( $transient ) {
		if ( empty( $transient->checked ) ) {
			return $transient;
		}
		$remote = $this->get_remote();
		if ( ! $remote ) {
			return $transient;
		}

		if ( version_compare( $remote['version'], $this->version, '>' ) ) {
			$transient->response[ $this->slug ] = array(
				'theme'        => $this->slug,
				'new_version'  => $remote['version'],
				'package'      => isset( $remote['download_url'] ) ? $remote['download_url'] : '',
				'url'          => isset( $remote['url'] ) ? $remote['url'] : '',
				'requires'     => isset( $remote['requires'] ) ? $remote['requires'] : '',
				'requires_php' => isset( $remote['requires_php'] ) ? $remote['requires_php'] : '',
			);
		}
		return $transient;
	}
}
