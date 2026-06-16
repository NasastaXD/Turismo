/**
 * Mapa interactivo de locales (solo lectura) con Leaflet.
 * Lee window.CGZ_MAPS[mapId] = { center, points }.
 */
(function () {
	'use strict';

	function ready(fn) {
		if (document.readyState !== 'loading') { fn(); }
		else { document.addEventListener('DOMContentLoaded', fn); }
	}

	ready(function () {
		if (typeof L === 'undefined' || !window.CGZ_MAPS) { return; }

		document.querySelectorAll('[data-cgz-map]').forEach(function (el) {
			var data = window.CGZ_MAPS[el.id];
			if (!data) { return; }

			var center = data.center || { lat: -25.4646, lng: -56.0173, zoom: 14 };
			var map = L.map(el, { scrollWheelZoom: false }).setView([center.lat, center.lng], center.zoom || 14);
			L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
				maxZoom: 19,
				attribution: '© OpenStreetMap'
			}).addTo(map);

			var bounds = [];
			(data.points || []).forEach(function (p) {
				var marker = L.marker([p.lat, p.lng]).addTo(map);
				var html = '<strong>' + escapeHtml(p.name) + '</strong>';
				if (p.type) { html += '<br><span style="font-size:12px;color:#666">' + escapeHtml(p.type) + '</span>'; }
				if (p.wa) { html += '<br><span style="font-size:12px;color:#16873e">✓ Reserva por WhatsApp</span>'; }
				if (p.url) { html += '<br><a href="' + p.url + '">Ver local →</a>'; }
				marker.bindPopup(html);
				bounds.push([p.lat, p.lng]);
			});

			if (bounds.length > 1) {
				map.fitBounds(bounds, { padding: [40, 40], maxZoom: 16 });
			}

			// Zoom con rueda solo tras clic (UX en páginas largas).
			el.addEventListener('click', function () { map.scrollWheelZoom.enable(); });
			el.addEventListener('mouseleave', function () { map.scrollWheelZoom.disable(); });
		});

		function escapeHtml(s) {
			return String(s).replace(/[&<>"']/g, function (c) {
				return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
			});
		}
	});
})();
