/**
 * Selector de ubicación por clic (Leaflet).
 * Reemplaza el marcado automático: el usuario hace clic donde quiere el punto
 * y puede arrastrar el marcador para ajustarlo con precisión.
 *
 * Usado tanto en el metabox de admin como en el panel de dueño.
 */
(function () {
	'use strict';

	function ready(fn) {
		if (document.readyState !== 'loading') { fn(); }
		else { document.addEventListener('DOMContentLoaded', fn); }
	}

	ready(function () {
		if (typeof L === 'undefined') { return; }

		document.querySelectorAll('[data-cgz-picker]').forEach(function (el) {
			var scope = el.closest('form') || el.closest('[data-cgz-owner-form]') || document;
			var latInput = scope.querySelector('[data-cgz-lat]') || document.querySelector('[data-cgz-lat]');
			var lngInput = scope.querySelector('[data-cgz-lng]') || document.querySelector('[data-cgz-lng]');
			var clearBtn = scope.querySelector('[data-cgz-clear-point]') || document.querySelector('[data-cgz-clear-point]');

			var startLat = parseFloat(el.getAttribute('data-lat'));
			var startLng = parseFloat(el.getAttribute('data-lng'));
			var hasPoint = el.getAttribute('data-has-point') === '1';

			var center = window.CGZ_MAP_CENTER || { lat: -25.4646, lng: -56.0173, zoom: 14 };
			if (isNaN(startLat)) { startLat = center.lat; }
			if (isNaN(startLng)) { startLng = center.lng; }

			var map = L.map(el, { scrollWheelZoom: true }).setView([startLat, startLng], center.zoom || 14);
			L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
				maxZoom: 19,
				attribution: '© OpenStreetMap'
			}).addTo(map);

			var marker = null;

			function setPoint(lat, lng) {
				if (!marker) {
					marker = L.marker([lat, lng], { draggable: true }).addTo(map);
					marker.on('dragend', function () {
						var p = marker.getLatLng();
						writeInputs(p.lat, p.lng);
					});
				} else {
					marker.setLatLng([lat, lng]);
				}
				writeInputs(lat, lng);
			}

			function writeInputs(lat, lng) {
				if (latInput) { latInput.value = lat.toFixed(6); }
				if (lngInput) { lngInput.value = lng.toFixed(6); }
			}

			if (hasPoint) { setPoint(startLat, startLng); }

			map.on('click', function (e) {
				setPoint(e.latlng.lat, e.latlng.lng);
			});

			if (clearBtn) {
				clearBtn.addEventListener('click', function () {
					if (marker) { map.removeLayer(marker); marker = null; }
					if (latInput) { latInput.value = ''; }
					if (lngInput) { lngInput.value = ''; }
				});
			}

			// Recalcula el tamaño cuando el contenedor ya es visible (metaboxes).
			setTimeout(function () { map.invalidateSize(); }, 200);
		});
	});
})();
