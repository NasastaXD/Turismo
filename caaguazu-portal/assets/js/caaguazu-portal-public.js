/**
 * Caaguazú Portal — JS público (visitante): reseñas, consultas, reportes
 * y armador de itinerario "Mi viaje" (sin cuenta, en localStorage).
 */
(function () {
	'use strict';

	var CFG = window.PROMOTUR_PUB || {};
	var i18n = CFG.i18n || {};
	var STORE = 'promotur_viaje';

	function ready(fn) { if (document.readyState !== 'loading') { fn(); } else { document.addEventListener('DOMContentLoaded', fn); } }

	function ajax(action, obj) {
		var body = new FormData();
		Object.keys(obj || {}).forEach(function (k) { body.append(k, obj[k]); });
		body.append('action', 'promotur_' + action);
		body.append('nonce', CFG.nonce);
		return fetch(CFG.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: body }).then(function (r) { return r.json(); });
	}

	function setMsg(el, text, cls) { if (el) { el.textContent = text; el.className = 'promotur-form-msg ' + (cls || ''); } }

	ready(function () {
		initRatingStars();
		initResena();
		initConsulta();
		initReporte();
		initItinerary();
		initQR();
	});

	/* ---------- QR por destino ---------- */
	function initQR() {
		var btn = document.querySelector('[data-qr]');
		var modal = document.querySelector('[data-qr-modal]');
		if (!btn || !modal) { return; }
		var canvas = modal.querySelector('[data-qr-canvas]');
		var close = modal.querySelector('[data-qr-close]');
		var drawn = false;

		btn.addEventListener('click', function () {
			if (!drawn && typeof qrcode !== 'undefined') {
				try {
					var qr = qrcode(0, 'M');
					qr.addData(btn.getAttribute('data-url'));
					qr.make();
					canvas.innerHTML = qr.createSvgTag({ cellSize: 6, margin: 4, scalable: true });
					var svg = canvas.querySelector('svg');
					if (svg) { svg.setAttribute('width', '220'); svg.setAttribute('height', '220'); }
					drawn = true;
				} catch (e) { canvas.textContent = btn.getAttribute('data-url'); }
			}
			modal.hidden = false;
		});
		function hide() { modal.hidden = true; }
		if (close) { close.addEventListener('click', hide); }
		modal.addEventListener('click', function (e) { if (e.target === modal) { hide(); } });
		document.addEventListener('keydown', function (e) { if (e.key === 'Escape') { hide(); } });
	}

	/* ---------- Rating ---------- */
	function initRatingStars() {
		document.querySelectorAll('[data-rating]').forEach(function (wrap) {
			var input = wrap.querySelector('input[name="rating"]');
			var stars = wrap.querySelectorAll('.promotur-rating-star');
			function paint(v) { stars.forEach(function (s) { s.classList.toggle('is-on', parseInt(s.getAttribute('data-value'), 10) <= v); }); }
			stars.forEach(function (s) {
				var v = parseInt(s.getAttribute('data-value'), 10);
				s.addEventListener('mouseenter', function () { paint(v); });
				s.addEventListener('click', function () { input.value = v; paint(v); });
			});
			wrap.addEventListener('mouseleave', function () { paint(parseInt(input.value, 10) || 0); });
			paint(parseInt(input.value, 10) || 0);
		});
	}

	/* ---------- Reseña ---------- */
	function initResena() {
		var form = document.querySelector('[data-resena-form]');
		if (!form) { return; }
		var msg = form.querySelector('[data-form-msg]');
		form.addEventListener('submit', function (e) {
			e.preventDefault();
			var data = { post_id: form.getAttribute('data-post'), rating: val(form, 'rating'), author: val(form, 'author'), email: val(form, 'email'), content: val(form, 'content') };
			setMsg(msg, i18n.sending, '');
			ajax('submit_resena', data).then(function (r) {
				if (!r.success) { setMsg(msg, (r.data && r.data.message) || i18n.error, 'is-error'); return; }
				form.reset();
				setMsg(msg, r.data.message, 'is-success');
			}).catch(function () { setMsg(msg, i18n.error, 'is-error'); });
		});
	}

	/* ---------- Consulta ---------- */
	function initConsulta() {
		document.querySelectorAll('[data-consulta-form]').forEach(function (form) {
			var msg = form.querySelector('[data-form-msg]');
			form.addEventListener('submit', function (e) {
				e.preventDefault();
				setMsg(msg, i18n.sending, '');
				ajax('submit_consulta', { nombre: val(form, 'nombre'), email: val(form, 'email'), mensaje: val(form, 'mensaje'), destino: form.getAttribute('data-destino') }).then(function (r) {
					if (!r.success) { setMsg(msg, (r.data && r.data.message) || i18n.error, 'is-error'); return; }
					form.reset();
					setMsg(msg, r.data.message, 'is-success');
				}).catch(function () { setMsg(msg, i18n.error, 'is-error'); });
			});
		});
	}

	/* ---------- Reporte ---------- */
	function initReporte() {
		var toggle = document.querySelector('[data-report-toggle]');
		var form = document.querySelector('[data-reporte-form]');
		if (toggle && form) { toggle.addEventListener('click', function () { form.hidden = !form.hidden; }); }
		if (!form) { return; }
		var msg = form.querySelector('[data-form-msg]');
		form.addEventListener('submit', function (e) {
			e.preventDefault();
			setMsg(msg, i18n.sending, '');
			ajax('submit_reporte', { post_id: form.getAttribute('data-post'), content: val(form, 'content') }).then(function (r) {
				if (!r.success) { setMsg(msg, (r.data && r.data.message) || i18n.error, 'is-error'); return; }
				form.reset(); form.hidden = true;
				setMsg(msg, r.data.message, 'is-success');
			}).catch(function () { setMsg(msg, i18n.error, 'is-error'); });
		});
	}

	/* ---------- Itinerario ---------- */
	function getTrip() { try { return JSON.parse(localStorage.getItem(STORE)) || []; } catch (e) { return []; } }
	function setTrip(t) { try { localStorage.setItem(STORE, JSON.stringify(t)); } catch (e) {} }

	function initItinerary() {
		// Botones "agregar a mi viaje".
		document.querySelectorAll('[data-itin-add]').forEach(function (btn) {
			btn.addEventListener('click', function () {
				var id = btn.getAttribute('data-id');
				var trip = getTrip();
				if (!trip.some(function (x) { return String(x.id) === String(id); })) {
					trip.push({ id: id, title: btn.getAttribute('data-title'), url: btn.getAttribute('data-url') });
					setTrip(trip);
				}
				var old = btn.textContent;
				btn.textContent = '✓ ' + i18n.added;
				btn.disabled = true;
				setTimeout(function () { btn.textContent = old; btn.disabled = false; }, 1600);
			});
		});

		var page = document.querySelector('[data-itinerario]');
		if (!page) { return; }

		// Importar desde enlace compartido ?v=1,2,3
		var params = new URLSearchParams(location.search);
		if (params.get('v') && window.PROMOTUR_DESTINOS) {
			var trip = getTrip();
			params.get('v').split(',').forEach(function (id) {
				id = id.trim();
				var d = window.PROMOTUR_DESTINOS[id];
				if (d && !trip.some(function (x) { return String(x.id) === String(id); })) {
					trip.push({ id: id, title: d.title, url: d.url });
				}
			});
			setTrip(trip);
		}

		var list = page.querySelector('[data-itin-list]');
		var msg = page.querySelector('[data-itin-msg]');

		function render() {
			var trip = getTrip();
			if (!trip.length) { list.innerHTML = '<p class="promotur-muted">' + i18n.empty + '</p>'; return; }
			list.innerHTML = '';
			trip.forEach(function (item, idx) {
				var row = document.createElement('div');
				row.className = 'promotur-row';
				row.innerHTML =
					'<span class="promotur-row__main"><span class="promotur-row__title">' + (idx + 1) + '. <a href="' + item.url + '">' + escapeHtml(item.title) + '</a></span></span>' +
					'<span class="promotur-itin__ctrls"></span>';
				var ctrls = row.querySelector('.promotur-itin__ctrls');
				ctrls.appendChild(ctrlBtn('▲', function () { move(idx, -1); }));
				ctrls.appendChild(ctrlBtn('▼', function () { move(idx, 1); }));
				ctrls.appendChild(ctrlBtn('✕', function () { remove(idx); }));
				list.appendChild(row);
			});
		}
		function ctrlBtn(label, fn) { var b = document.createElement('button'); b.type = 'button'; b.className = 'promotur-iconbtn'; b.textContent = label; b.addEventListener('click', fn); return b; }
		function move(i, dir) { var t = getTrip(); var j = i + dir; if (j < 0 || j >= t.length) { return; } var tmp = t[i]; t[i] = t[j]; t[j] = tmp; setTrip(t); render(); }
		function remove(i) { var t = getTrip(); t.splice(i, 1); setTrip(t); render(); }

		var shareBtn = page.querySelector('[data-itin-share]');
		if (shareBtn) {
			shareBtn.addEventListener('click', function () {
				var ids = getTrip().map(function (x) { return x.id; }).join(',');
				var url = location.origin + location.pathname + '?v=' + ids;
				if (navigator.clipboard) { navigator.clipboard.writeText(url).then(function () { setMsg(msg, i18n.copied, 'is-success'); }); }
				else { prompt('Copiá el enlace:', url); }
			});
		}
		var clearBtn = page.querySelector('[data-itin-clear]');
		if (clearBtn) { clearBtn.addEventListener('click', function () { setTrip([]); render(); }); }

		render();
	}

	function val(form, name) { var el = form.querySelector('[name="' + name + '"]'); return el ? el.value : ''; }
	function escapeHtml(s) { return String(s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }
})();
