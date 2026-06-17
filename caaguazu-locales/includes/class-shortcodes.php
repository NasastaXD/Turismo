<?php
/**
 * Shortcodes de frontend:
 *   [caaguazu_locales tipo="restaurante"]   directorio de locales
 *   [caaguazu_booking id="123"]             widget de reserva por WhatsApp
 *   [caaguazu_mapa tipo="" alto="480px"]    mapa interactivo de locales
 *   [caaguazu_resenas id="123"]             reseñas (fotos, votos, respuestas)
 *   [caaguazu_cuenta]                       login / registro / solicitud de dueño
 *   [caaguazu_dueno_panel]                  panel de gestión para dueños
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class CGZ_Shortcodes {

	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_shortcode( 'caaguazu_locales', array( $this, 'directory' ) );
		add_shortcode( 'caaguazu_booking', array( $this, 'booking' ) );
		add_shortcode( 'caaguazu_mapa', array( $this, 'map' ) );
		add_shortcode( 'caaguazu_resenas', array( $this, 'reviews' ) );
		add_shortcode( 'caaguazu_cuenta', array( $this, 'account' ) );
		add_shortcode( 'caaguazu_dueno_panel', array( $this, 'owner_panel' ) );
	}

	/* ------------------------------------------------------------------ */
	/* Directorio                                                          */
	/* ------------------------------------------------------------------ */
	public function directory( $atts ) {
		$atts = shortcode_atts( array( 'tipo' => '', 'cantidad' => 24 ), $atts, 'caaguazu_locales' );

		$query_args = array(
			'post_type'      => 'cgz_local',
			'posts_per_page' => (int) $atts['cantidad'],
			'orderby'        => 'title',
			'order'          => 'ASC',
		);
		if ( $atts['tipo'] ) {
			$query_args['meta_query'] = array( array(
				'key'   => '_cgz_tipo',
				'value' => sanitize_key( $atts['tipo'] ),
			) );
		}

		$q = new WP_Query( $query_args );
		if ( ! $q->have_posts() ) {
			return '<p class="cgz-empty">' . esc_html__( 'Todavía no hay locales cargados.', 'caaguazu-locales' ) . '</p>';
		}

		$types = cgz_local_types();
		ob_start();
		echo '<div class="cgz-grid">';
		while ( $q->have_posts() ) {
			$q->the_post();
			$id      = get_the_ID();
			$tipo    = get_post_meta( $id, '_cgz_tipo', true );
			$summary = CGZ_Reviews::get_summary( $id );
			?>
			<a class="cgz-card" href="<?php the_permalink(); ?>">
				<div class="cgz-card__media">
					<?php if ( has_post_thumbnail() ) {
						the_post_thumbnail( 'medium_large', array( 'class' => 'cgz-card__img', 'loading' => 'lazy' ) );
					} else {
						echo '<div class="cgz-card__placeholder" aria-hidden="true"></div>';
					} ?>
				</div>
				<div class="cgz-card__body">
					<?php if ( isset( $types[ $tipo ] ) ) : ?>
						<span class="cgz-card__tag"><?php echo esc_html( $types[ $tipo ] ); ?></span>
					<?php endif; ?>
					<h3 class="cgz-card__title"><?php the_title(); ?></h3>
					<?php if ( has_excerpt() ) : ?>
						<p class="cgz-card__excerpt"><?php echo esc_html( wp_trim_words( get_the_excerpt(), 18 ) ); ?></p>
					<?php endif; ?>
					<div class="cgz-card__meta">
						<?php if ( $summary['count'] > 0 ) : ?>
							<span class="cgz-stars" aria-label="<?php echo esc_attr( sprintf( __( '%s de 5', 'caaguazu-locales' ), $summary['average'] ) ); ?>">
								<?php echo self::stars_html( $summary['average'] ); ?>
							</span>
							<span class="cgz-card__count"><?php echo esc_html( $summary['average'] ); ?> · <?php
								/* translators: %d = cantidad de reseñas */
								printf( esc_html( _n( '%d reseña', '%d reseñas', $summary['count'], 'caaguazu-locales' ) ), (int) $summary['count'] );
							?></span>
						<?php else : ?>
							<span class="cgz-card__count cgz-card__count--empty"><?php esc_html_e( 'Sin reseñas aún', 'caaguazu-locales' ); ?></span>
						<?php endif; ?>
						<?php if ( cgz_local_allows_booking( $id ) ) : ?>
							<span class="cgz-card__wa"><?php esc_html_e( 'Reserva por WhatsApp', 'caaguazu-locales' ); ?></span>
						<?php endif; ?>
					</div>
				</div>
			</a>
			<?php
		}
		echo '</div>';
		wp_reset_postdata();
		return ob_get_clean();
	}

	/* ------------------------------------------------------------------ */
	/* Booking widget                                                      */
	/* ------------------------------------------------------------------ */
	public function booking( $atts ) {
		$atts = shortcode_atts( array( 'id' => 0 ), $atts, 'caaguazu_booking' );
		$id   = (int) $atts['id'];
		if ( ! $id && is_singular( 'cgz_local' ) ) {
			$id = get_the_ID();
		}
		return CGZ_Booking::render_widget( $id );
	}

	/* ------------------------------------------------------------------ */
	/* Mapa interactivo                                                    */
	/* ------------------------------------------------------------------ */
	public function map( $atts ) {
		$atts = shortcode_atts( array( 'tipo' => '', 'alto' => '480px', 'editable' => '' ), $atts, 'caaguazu_mapa' );

		cgz_enqueue_leaflet();
		wp_enqueue_script(
			'cgz-loc-map',
			CGZ_LOC_URI . 'assets/js/frontend-map.js',
			array( 'cgz-leaflet' ),
			CGZ_LOC_VERSION,
			true
		);

		$query_args = array(
			'post_type'      => 'cgz_local',
			'posts_per_page' => -1,
			'meta_query'     => array(
				'relation' => 'AND',
				array( 'key' => '_cgz_lat', 'value' => '', 'compare' => '!=' ),
			),
		);
		if ( $atts['tipo'] ) {
			$query_args['meta_query'][] = array( 'key' => '_cgz_tipo', 'value' => sanitize_key( $atts['tipo'] ) );
		}

		$q      = new WP_Query( $query_args );
		$points = array();
		$types  = cgz_local_types();
		while ( $q->have_posts() ) {
			$q->the_post();
			$id     = get_the_ID();
			$coords = cgz_get_coords( $id );
			if ( ! $coords ) { continue; }
			$tipo     = get_post_meta( $id, '_cgz_tipo', true );
			$points[] = array(
				'id'    => $id,
				'name'  => get_the_title(),
				'type'  => isset( $types[ $tipo ] ) ? $types[ $tipo ] : '',
				'tipo'  => $tipo,
				'lat'   => $coords['lat'],
				'lng'   => $coords['lng'],
				'url'   => get_permalink(),
				'wa'    => cgz_local_allows_booking( $id ),
			);
		}
		wp_reset_postdata();

		$map_id = 'cgz-map-' . wp_unique_id();
		wp_add_inline_script(
			'cgz-loc-map',
			'window.CGZ_MAPS = window.CGZ_MAPS || {}; window.CGZ_MAPS["' . esc_js( $map_id ) . '"] = ' .
			wp_json_encode( array(
				'center' => cgz_map_center(),
				'points' => $points,
			) ) . ';',
			'before'
		);

		return sprintf(
			'<div class="cgz-map" id="%s" data-cgz-map style="height:%s"></div>',
			esc_attr( $map_id ),
			esc_attr( $atts['alto'] )
		);
	}

	/* ------------------------------------------------------------------ */
	/* Reseñas                                                             */
	/* ------------------------------------------------------------------ */
	public function reviews( $atts ) {
		$atts = shortcode_atts( array( 'id' => 0 ), $atts, 'caaguazu_resenas' );
		$id   = (int) $atts['id'];
		if ( ! $id && is_singular( 'cgz_local' ) ) {
			$id = get_the_ID();
		}
		if ( ! $id ) { return ''; }

		$summary    = CGZ_Reviews::get_summary( $id );
		$reviews    = CGZ_Reviews::get_reviews( $id );
		$can_review = is_user_logged_in() && current_user_can( 'cgz_write_review' );
		$current    = get_current_user_id();

		ob_start();
		?>
		<section class="cgz-reviews" data-cgz-reviews data-local="<?php echo esc_attr( $id ); ?>">
			<header class="cgz-reviews__head">
				<div class="cgz-reviews__score">
					<span class="cgz-reviews__avg"><?php echo esc_html( $summary['count'] ? $summary['average'] : '—' ); ?></span>
					<span class="cgz-stars cgz-stars--lg"><?php echo self::stars_html( $summary['average'] ); ?></span>
					<span class="cgz-reviews__count">
						<?php
						if ( $summary['count'] ) {
							printf( esc_html( _n( '%d reseña', '%d reseñas', $summary['count'], 'caaguazu-locales' ) ), (int) $summary['count'] );
						} else {
							esc_html_e( 'Sé el primero en reseñar', 'caaguazu-locales' );
						}
						?>
					</span>
				</div>
			</header>

			<?php if ( $can_review ) : ?>
				<?php echo $this->review_form( $id ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
			<?php else : ?>
				<div class="cgz-login-cta">
					<?php
					printf(
						/* translators: %s = enlace de cuenta */
						esc_html__( 'Para dejar tu reseña, %s.', 'caaguazu-locales' ),
						'<a href="' . esc_url( self::account_url() ) . '">' . esc_html__( 'iniciá sesión o creá una cuenta', 'caaguazu-locales' ) . '</a>'
					);
					?>
				</div>
			<?php endif; ?>

			<div class="cgz-reviews__list" data-cgz-reviews-list>
				<?php
				if ( empty( $reviews ) ) {
					echo '<p class="cgz-empty">' . esc_html__( 'Todavía no hay reseñas.', 'caaguazu-locales' ) . '</p>';
				} else {
					foreach ( $reviews as $review ) {
						echo self::review_card( $review, $current ); // phpcs:ignore WordPress.Security.EscapeOutput
					}
				}
				?>
			</div>
		</section>
		<?php
		return ob_get_clean();
	}

	/**
	 * Formulario para crear/editar reseña.
	 */
	private function review_form( $local_id ) {
		ob_start();
		?>
		<form class="cgz-review-form" data-cgz-review-form>
			<input type="hidden" name="local_id" value="<?php echo esc_attr( $local_id ); ?>">
			<div class="cgz-rating-input" data-cgz-rating>
				<?php for ( $i = 1; $i <= 5; $i++ ) : ?>
					<button type="button" class="cgz-rating-star" data-value="<?php echo esc_attr( $i ); ?>" aria-label="<?php echo esc_attr( sprintf( __( '%d estrellas', 'caaguazu-locales' ), $i ) ); ?>">★</button>
				<?php endfor; ?>
				<input type="hidden" name="rating" value="0">
			</div>
			<input type="text" name="title" class="cgz-input" maxlength="200" placeholder="<?php esc_attr_e( 'Título (opcional)', 'caaguazu-locales' ); ?>">
			<textarea name="content" class="cgz-input" rows="4" required placeholder="<?php esc_attr_e( '¿Cómo fue tu experiencia?', 'caaguazu-locales' ); ?>"></textarea>

			<div class="cgz-photos" data-cgz-photos>
				<label class="cgz-photos__add">
					<input type="file" accept="image/*" multiple data-cgz-photo-input hidden>
					<span>📷 <?php esc_html_e( 'Agregar fotos', 'caaguazu-locales' ); ?></span>
				</label>
				<div class="cgz-photos__preview" data-cgz-photo-preview></div>
				<input type="hidden" name="photos" value="">
			</div>

			<button type="submit" class="cgz-btn cgz-btn--primary"><?php esc_html_e( 'Publicar reseña', 'caaguazu-locales' ); ?></button>
			<span class="cgz-form-msg" data-cgz-form-msg aria-live="polite"></span>
		</form>
		<?php
		return ob_get_clean();
	}

	/**
	 * Markup de una reseña (también usado por AJAX para insertar nuevas).
	 */
	public static function review_card( $review, $current_user = 0 ) {
		$can_moderate = current_user_can( 'cgz_moderate_reviews' );
		$is_author    = $current_user && (int) $current_user === (int) $review['user_id'];
		$user_vote    = CGZ_Reviews::get_user_vote( $review['id'], $current_user );

		ob_start();
		?>
		<article class="cgz-review" data-cgz-review="<?php echo esc_attr( $review['id'] ); ?>">
			<header class="cgz-review__head">
				<img class="cgz-review__avatar" src="<?php echo esc_url( $review['avatar'] ); ?>" alt="" width="40" height="40" loading="lazy">
				<div>
					<span class="cgz-review__author"><?php echo esc_html( $review['author'] ); ?></span>
					<span class="cgz-review__date"><?php echo esc_html( $review['date_human'] ); ?></span>
				</div>
				<span class="cgz-stars"><?php echo self::stars_html( $review['rating'] ); ?></span>
			</header>

			<?php if ( $review['title'] ) : ?>
				<h4 class="cgz-review__title"><?php echo esc_html( $review['title'] ); ?></h4>
			<?php endif; ?>
			<div class="cgz-review__body"><?php echo wpautop( esc_html( $review['content'] ) ); ?></div>

			<?php if ( ! empty( $review['photos'] ) ) : ?>
				<div class="cgz-review__photos">
					<?php foreach ( $review['photos'] as $photo ) : ?>
						<a href="<?php echo esc_url( $photo['url'] ); ?>" target="_blank" rel="noopener" class="cgz-review__photo">
							<img src="<?php echo esc_url( $photo['thumb'] ); ?>" alt="" loading="lazy">
						</a>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>

			<footer class="cgz-review__foot">
				<div class="cgz-vote" data-cgz-vote="<?php echo esc_attr( $review['id'] ); ?>">
					<button type="button" class="cgz-vote__btn<?php echo $user_vote > 0 ? ' is-active' : ''; ?>" data-vote="1" aria-label="<?php esc_attr_e( 'Útil', 'caaguazu-locales' ); ?>">▲</button>
					<span class="cgz-vote__score" data-cgz-vote-score><?php echo esc_html( $review['score'] ); ?></span>
					<button type="button" class="cgz-vote__btn<?php echo $user_vote < 0 ? ' is-active' : ''; ?>" data-vote="-1" aria-label="<?php esc_attr_e( 'No útil', 'caaguazu-locales' ); ?>">▼</button>
				</div>
				<button type="button" class="cgz-link-btn" data-cgz-reply-toggle><?php esc_html_e( 'Responder', 'caaguazu-locales' ); ?></button>
				<?php if ( $can_moderate || $is_author ) : ?>
					<button type="button" class="cgz-link-btn cgz-link-btn--danger" data-cgz-delete-review><?php esc_html_e( 'Eliminar', 'caaguazu-locales' ); ?></button>
				<?php endif; ?>
			</footer>

			<div class="cgz-replies" data-cgz-replies>
				<?php foreach ( $review['replies'] as $reply ) {
					echo self::reply_card( $reply ); // phpcs:ignore WordPress.Security.EscapeOutput
				} ?>
			</div>

			<?php if ( is_user_logged_in() ) : ?>
				<form class="cgz-reply-form" data-cgz-reply-form hidden>
					<textarea name="content" rows="2" class="cgz-input" required placeholder="<?php esc_attr_e( 'Escribí una respuesta…', 'caaguazu-locales' ); ?>"></textarea>
					<button type="submit" class="cgz-btn cgz-btn--small"><?php esc_html_e( 'Comentar', 'caaguazu-locales' ); ?></button>
				</form>
			<?php endif; ?>
		</article>
		<?php
		return ob_get_clean();
	}

	/**
	 * Markup de una respuesta.
	 */
	public static function reply_card( $reply ) {
		ob_start();
		?>
		<div class="cgz-reply<?php echo $reply['is_owner'] ? ' cgz-reply--owner' : ''; ?>" data-cgz-reply="<?php echo esc_attr( $reply['id'] ); ?>">
			<img class="cgz-reply__avatar" src="<?php echo esc_url( $reply['avatar'] ); ?>" alt="" width="28" height="28" loading="lazy">
			<div class="cgz-reply__main">
				<span class="cgz-reply__author">
					<?php echo esc_html( $reply['author'] ); ?>
					<?php if ( $reply['is_owner'] ) : ?>
						<span class="cgz-reply__badge"><?php esc_html_e( 'Dueño', 'caaguazu-locales' ); ?></span>
					<?php endif; ?>
				</span>
				<span class="cgz-reply__date"><?php echo esc_html( $reply['date_human'] ); ?></span>
				<p class="cgz-reply__content"><?php echo esc_html( $reply['content'] ); ?></p>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	/* ------------------------------------------------------------------ */
	/* Cuenta                                                              */
	/* ------------------------------------------------------------------ */
	public function account( $atts ) {
		ob_start();
		echo '<div class="cgz-account">';

		// Notices.
		foreach ( CGZ_Accounts::$notices as $notice ) {
			printf( '<div class="cgz-notice cgz-notice--%s">%s</div>', esc_attr( $notice[0] ), esc_html( $notice[1] ) );
		}

		if ( is_user_logged_in() ) {
			$user = wp_get_current_user();
			?>
			<div class="cgz-account__welcome">
				<p><?php printf( esc_html__( 'Sesión iniciada como %s.', 'caaguazu-locales' ), '<strong>' . esc_html( $user->display_name ) . '</strong>' ); ?></p>
				<ul class="cgz-account__links">
					<?php if ( user_can( $user, 'cgz_manage_own_local' ) ) : ?>
						<li><a href="<?php echo esc_url( get_post_type_archive_link( 'cgz_local' ) ); ?>"><?php esc_html_e( 'Ver locales', 'caaguazu-locales' ); ?></a></li>
					<?php endif; ?>
					<li><a href="<?php echo esc_url( wp_logout_url( get_permalink() ) ); ?>"><?php esc_html_e( 'Cerrar sesión', 'caaguazu-locales' ); ?></a></li>
				</ul>
			</div>
			<?php
			echo $this->owner_request_form(); // phpcs:ignore WordPress.Security.EscapeOutput
		} else {
			?>
			<div class="cgz-account__tabs" data-cgz-tabs>
				<div class="cgz-account__col">
					<h3><?php esc_html_e( 'Iniciar sesión', 'caaguazu-locales' ); ?></h3>
					<form method="post" class="cgz-form">
						<?php wp_nonce_field( 'cgz_login', 'cgz_nonce' ); ?>
						<input type="hidden" name="cgz_account_action" value="login">
						<input type="text" name="username" class="cgz-input" required placeholder="<?php esc_attr_e( 'Usuario o email', 'caaguazu-locales' ); ?>">
						<input type="password" name="password" class="cgz-input" required placeholder="<?php esc_attr_e( 'Contraseña', 'caaguazu-locales' ); ?>">
						<label class="cgz-check"><input type="checkbox" name="remember" value="1"> <?php esc_html_e( 'Recordarme', 'caaguazu-locales' ); ?></label>
						<button type="submit" class="cgz-btn cgz-btn--primary"><?php esc_html_e( 'Entrar', 'caaguazu-locales' ); ?></button>
						<a class="cgz-forgot" href="<?php echo esc_url( wp_lostpassword_url() ); ?>"><?php esc_html_e( '¿Olvidaste tu contraseña?', 'caaguazu-locales' ); ?></a>
					</form>
				</div>
				<div class="cgz-account__col">
					<h3><?php esc_html_e( 'Crear cuenta', 'caaguazu-locales' ); ?></h3>
					<form method="post" class="cgz-form">
						<?php wp_nonce_field( 'cgz_register', 'cgz_nonce' ); ?>
						<input type="hidden" name="cgz_account_action" value="register">
						<input type="text" name="username" class="cgz-input" required placeholder="<?php esc_attr_e( 'Nombre de usuario', 'caaguazu-locales' ); ?>">
						<input type="email" name="email" class="cgz-input" required placeholder="<?php esc_attr_e( 'Email', 'caaguazu-locales' ); ?>">
						<input type="password" name="password" class="cgz-input" required minlength="6" placeholder="<?php esc_attr_e( 'Contraseña (6+ caracteres)', 'caaguazu-locales' ); ?>">
						<button type="submit" class="cgz-btn cgz-btn--primary"><?php esc_html_e( 'Registrarme', 'caaguazu-locales' ); ?></button>
					</form>
				</div>
			</div>
			<?php
			echo $this->owner_request_form(); // phpcs:ignore WordPress.Security.EscapeOutput
		}

		echo '</div>';
		return ob_get_clean();
	}

	/**
	 * Formulario de solicitud de cuenta de dueño.
	 */
	private function owner_request_form() {
		$current = wp_get_current_user();
		ob_start();
		?>
		<div class="cgz-owner-request">
			<h3><?php esc_html_e( '¿Tenés un local en Caaguazú?', 'caaguazu-locales' ); ?></h3>
			<p class="cgz-owner-request__intro">
				<?php
				printf(
					/* translators: %s = email de contacto */
					esc_html__( 'Solicitá una cuenta de dueño para administrar tu local (datos, fotos, reservas y respuestas a reseñas). Tu pedido se envía a %s y el equipo te activa la cuenta.', 'caaguazu-locales' ),
					'<strong>' . esc_html( cgz_contact_email() ) . '</strong>'
				);
				?>
			</p>
			<form method="post" class="cgz-form cgz-form--grid">
				<?php wp_nonce_field( 'cgz_owner_request', 'cgz_nonce' ); ?>
				<input type="hidden" name="cgz_account_action" value="owner_request">
				<input type="text" name="nombre" class="cgz-input" required placeholder="<?php esc_attr_e( 'Tu nombre', 'caaguazu-locales' ); ?>" value="<?php echo esc_attr( $current->display_name ); ?>">
				<input type="email" name="email" class="cgz-input" required placeholder="<?php esc_attr_e( 'Email de contacto', 'caaguazu-locales' ); ?>" value="<?php echo esc_attr( $current->user_email ); ?>">
				<input type="text" name="local" class="cgz-input" required placeholder="<?php esc_attr_e( 'Nombre del local', 'caaguazu-locales' ); ?>">
				<input type="text" name="telefono" class="cgz-input" placeholder="<?php esc_attr_e( 'Teléfono / WhatsApp', 'caaguazu-locales' ); ?>">
				<textarea name="mensaje" class="cgz-input cgz-input--full" rows="3" placeholder="<?php esc_attr_e( 'Contanos sobre tu local (opcional)', 'caaguazu-locales' ); ?>"></textarea>
				<button type="submit" class="cgz-btn cgz-btn--outline"><?php esc_html_e( 'Enviar solicitud', 'caaguazu-locales' ); ?></button>
			</form>
		</div>
		<?php
		return ob_get_clean();
	}

	/* ------------------------------------------------------------------ */
	/* Panel de dueño                                                      */
	/* ------------------------------------------------------------------ */
	public function owner_panel( $atts ) {
		if ( ! is_user_logged_in() ) {
			return '<div class="cgz-login-cta"><a href="' . esc_url( self::account_url() ) . '">' . esc_html__( 'Iniciá sesión para administrar tu local.', 'caaguazu-locales' ) . '</a></div>';
		}

		$user_id = get_current_user_id();
		$locals  = get_posts( array(
			'post_type'      => 'cgz_local',
			'posts_per_page' => -1,
			'meta_key'       => '_cgz_owner',
			'meta_value'     => $user_id,
			'post_status'    => array( 'publish', 'draft', 'pending' ),
		) );

		if ( ! current_user_can( 'manage_options' ) && empty( $locals ) ) {
			return '<div class="cgz-notice cgz-notice--info">' .
				esc_html__( 'Todavía no tenés ningún local asignado. Si solicitaste una cuenta de dueño, esperá la activación del equipo de turismo.', 'caaguazu-locales' ) .
				'</div>';
		}

		cgz_enqueue_leaflet();
		wp_enqueue_script(
			'cgz-loc-map-picker',
			CGZ_LOC_URI . 'assets/js/map-picker.js',
			array( 'cgz-leaflet' ),
			CGZ_LOC_VERSION,
			true
		);

		$types = cgz_local_types();
		ob_start();
		echo '<div class="cgz-owner-panel" data-cgz-owner-panel>';
		echo '<h2 class="cgz-owner-panel__title">' . esc_html__( 'Tus locales', 'caaguazu-locales' ) . '</h2>';

		foreach ( $locals as $local ) {
			$id      = $local->ID;
			$coords  = cgz_get_coords( $id );
			$center  = cgz_map_center();
			$lat     = $coords ? $coords['lat'] : $center['lat'];
			$lng     = $coords ? $coords['lng'] : $center['lng'];
			$tipo    = get_post_meta( $id, '_cgz_tipo', true );
			$options = cgz_get_booking_options( $id );
			$opt_text = '';
			foreach ( $options as $o ) {
				$opt_text .= ( $o['label'] ?? '' ) . ' | ' . str_replace( "\n", '\\n', $o['message'] ?? '' ) . "\n";
			}
			$picker_id = 'cgz-picker-' . $id;
			?>
			<form class="cgz-owner-form" data-cgz-owner-form data-local="<?php echo esc_attr( $id ); ?>">
				<h3 class="cgz-owner-form__name"><?php echo esc_html( get_the_title( $id ) ); ?> <a class="cgz-owner-form__view" href="<?php echo esc_url( get_permalink( $id ) ); ?>" target="_blank" rel="noopener">↗</a></h3>

				<div class="cgz-field">
					<label><?php esc_html_e( 'Tipo de local', 'caaguazu-locales' ); ?></label>
					<select name="tipo" class="cgz-input">
						<?php foreach ( $types as $slug => $label ) : ?>
							<option value="<?php echo esc_attr( $slug ); ?>" <?php selected( $tipo, $slug ); ?>><?php echo esc_html( $label ); ?></option>
						<?php endforeach; ?>
					</select>
				</div>

				<div class="cgz-field">
					<label><?php esc_html_e( 'WhatsApp (con código de país, ej: 595981...)', 'caaguazu-locales' ); ?></label>
					<input type="text" name="whatsapp" class="cgz-input" value="<?php echo esc_attr( get_post_meta( $id, '_cgz_whatsapp', true ) ); ?>">
				</div>

				<div class="cgz-field cgz-field--2">
					<div>
						<label><?php esc_html_e( 'Teléfono', 'caaguazu-locales' ); ?></label>
						<input type="text" name="telefono" class="cgz-input" value="<?php echo esc_attr( get_post_meta( $id, '_cgz_telefono', true ) ); ?>">
					</div>
					<div>
						<label><?php esc_html_e( 'Horario', 'caaguazu-locales' ); ?></label>
						<input type="text" name="horario" class="cgz-input" value="<?php echo esc_attr( get_post_meta( $id, '_cgz_horario', true ) ); ?>">
					</div>
				</div>

				<div class="cgz-field">
					<label><?php esc_html_e( 'Dirección', 'caaguazu-locales' ); ?></label>
					<input type="text" name="direccion" class="cgz-input" value="<?php echo esc_attr( get_post_meta( $id, '_cgz_direccion', true ) ); ?>">
				</div>

				<div class="cgz-field">
					<label><?php esc_html_e( 'Descripción', 'caaguazu-locales' ); ?></label>
					<textarea name="descripcion" class="cgz-input" rows="4"><?php echo esc_textarea( $local->post_content ); ?></textarea>
				</div>

				<div class="cgz-field">
					<label><?php esc_html_e( 'Opciones de reserva (una por línea — formato: Etiqueta | Mensaje)', 'caaguazu-locales' ); ?></label>
					<textarea name="booking_options" class="cgz-input" rows="4"><?php echo esc_textarea( trim( $opt_text ) ); ?></textarea>
					<small class="cgz-hint"><?php esc_html_e( 'Solo se usan en restaurantes y hoteles. Usá \\n para saltos de línea en el mensaje.', 'caaguazu-locales' ); ?></small>
				</div>

				<div class="cgz-field">
					<label><?php esc_html_e( 'Ubicación en el mapa — hacé clic para marcar el punto exacto', 'caaguazu-locales' ); ?></label>
					<div class="cgz-picker" id="<?php echo esc_attr( $picker_id ); ?>"
						data-cgz-picker
						data-lat="<?php echo esc_attr( $lat ); ?>"
						data-lng="<?php echo esc_attr( $lng ); ?>"
						data-has-point="<?php echo $coords ? '1' : '0'; ?>"></div>
					<div class="cgz-coords">
						<input type="text" name="lat" data-cgz-lat class="cgz-input cgz-input--mini" value="<?php echo $coords ? esc_attr( $lat ) : ''; ?>" placeholder="lat" readonly>
						<input type="text" name="lng" data-cgz-lng class="cgz-input cgz-input--mini" value="<?php echo $coords ? esc_attr( $lng ) : ''; ?>" placeholder="lng" readonly>
						<button type="button" class="cgz-link-btn" data-cgz-clear-point><?php esc_html_e( 'Quitar marcador', 'caaguazu-locales' ); ?></button>
					</div>
				</div>

				<button type="submit" class="cgz-btn cgz-btn--primary"><?php esc_html_e( 'Guardar cambios', 'caaguazu-locales' ); ?></button>
				<span class="cgz-form-msg" data-cgz-form-msg aria-live="polite"></span>
			</form>
			<?php
		}

		echo '</div>';
		return ob_get_clean();
	}

	/* ------------------------------------------------------------------ */
	/* Utilidades                                                          */
	/* ------------------------------------------------------------------ */

	/**
	 * HTML de estrellas (llenas/medias/vacías) para un rating 0–5.
	 */
	public static function stars_html( $rating ) {
		$rating = (float) $rating;
		$full   = (int) floor( $rating );
		$half   = ( $rating - $full ) >= 0.5;
		$html   = '';
		for ( $i = 1; $i <= 5; $i++ ) {
			if ( $i <= $full ) {
				$html .= '<span class="cgz-star is-full">★</span>';
			} elseif ( $half && $i === $full + 1 ) {
				$html .= '<span class="cgz-star is-half">★</span>';
			} else {
				$html .= '<span class="cgz-star">★</span>';
			}
		}
		return $html;
	}

	/**
	 * URL de la página de cuenta (filtrable).
	 */
	public static function account_url() {
		$page = get_option( 'cgz_account_page_id' );
		if ( $page ) {
			return get_permalink( $page );
		}
		return apply_filters( 'cgz_account_url', home_url( '/cuenta/' ) );
	}
}
