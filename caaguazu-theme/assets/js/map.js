/**
 * Mapa de Caaguazú con Leaflet.
 * Reemplaza el componente React MapDialog del sitio original.
 */
(function () {
	'use strict';

	const ready = (fn) => {
		if (document.readyState !== 'loading') { fn(); }
		else { document.addEventListener('DOMContentLoaded', fn); }
	};

	ready(() => {
		const container = document.querySelector('[data-caaguazu-map]') ||
			document.getElementById('map') ||
			document.querySelector('.caaguazu-map-container');

		if (!container || typeof L === 'undefined') { return; }

		// Coordenadas aproximadas de Caaguazú (centro).
		const CAAGUAZU = [-25.4646, -56.0173];

		const map = L.map(container, {
			center: CAAGUAZU,
			zoom: 14,
			scrollWheelZoom: false,
		});

		L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
			maxZoom: 19,
			attribution: '© OpenStreetMap contributors',
		}).addTo(map);

		// Puntos de interés (editable).
		const POIS = [
			{ name: 'Ykua La Patria', coords: [-25.4639, -56.0153], type: 'Sitio fundacional' },
			{ name: 'Iglesia Inmaculada Concepción', coords: [-25.4670, -56.0177], type: 'Patrimonio religioso' },
			{ name: 'Mercado de Abasto', coords: [-25.4633, -56.0190], type: 'Vida local' },
			{ name: 'Parque Techapyrã', coords: [-25.4720, -56.0250], type: 'Familiar' },
		];

		POIS.forEach((poi) => {
			const icon = L.divIcon({
				className: 'map-pin',
				html: '<span></span>',
				iconSize: [22, 28],
				iconAnchor: [11, 28],
			});
			L.marker(poi.coords, { icon })
				.addTo(map)
				.bindPopup(
					'<strong>' + poi.name + '</strong><br>' +
					'<span style="font-size:12px;color:#666">' + poi.type + '</span>'
				);
		});

		// Habilita el scroll-zoom solo al hacer click (UX más amigable en páginas largas).
		container.addEventListener('click', () => map.scrollWheelZoom.enable());
		container.addEventListener('mouseleave', () => map.scrollWheelZoom.disable());
	});

})();
