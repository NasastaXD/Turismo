/**
 * Caaguazú Portal — interacción del panel.
 * Chrome (tema, drawer, dropdowns), PWA, editor (checklist en vivo, guardar, subir, geo)
 * y acciones de revisión.
 */
(function () {
	'use strict';

	var CFG = window.PROMOTUR || {};
	var i18n = CFG.i18n || {};

	function ready(fn) {
		if (document.readyState !== 'loading') { fn(); }
		else { document.addEventListener('DOMContentLoaded', fn); }
	}

	/** POST a admin-ajax. data: FormData u objeto plano. */
	function ajax(action, data) {
		var body;
		if (data instanceof FormData) { body = data; }
		else { body = new FormData(); Object.keys(data || {}).forEach(function (k) { body.append(k, data[k]); }); }
		body.append('action', 'promotur_' + action);
		body.append('nonce', CFG.nonce);
		return fetch(CFG.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: body }).then(function (r) { return r.json(); });
	}

	ready(function () {
		// Globales (una sola vez, viven fuera del área de contenido).
		initSplash();
		initTheme();
		initDrawer();
		initDropdowns();
		initInstall();
		initServiceWorker();
		// Específicos del contenido (se re-ejecutan tras cada navegación in-place).
		initContent();
		// Navegación tipo app (sin recargar toda la página).
		initPjax();
	});

	/** Inicializadores que dependen del HTML del área de contenido. */
	function initContent() {
		initEditor();
		initReview();
		initModeration();
		initGestion();
		initCaptura();
	}

	/* ---------- Navegación in-place (PJAX) ---------- */
	function initPjax() {
		var main = document.getElementById('promotur-content');
		if (!main || !window.history || !window.fetch || !window.DOMParser) { return; }

		var panelBase = (CFG.urls && CFG.urls.panel) || '';
		var busy = false;

		function samePanel(href) {
			if (!href || !panelBase) { return false; }
			try {
				var u = new URL(href, location.origin);
				if (u.origin !== location.origin) { return false; }
				var base = new URL(panelBase, location.origin);
				// Solo rutas bajo /panel (no login, salir, fichas públicas, etc.).
				return u.pathname === base.pathname || u.pathname.indexOf(base.pathname.replace(/\/$/, '') + '/') === 0 || u.pathname.indexOf(base.pathname) === 0;
			} catch (e) { return false; }
		}

		function eligible(a) {
			if (!a || a.target === '_blank' || a.hasAttribute('download')) { return false; }
			if (a.classList.contains('promotur-pjax-skip')) { return false; }
			var href = a.getAttribute('href');
			if (!href || href.charAt(0) === '#' || /^(mailto:|tel:|javascript:)/i.test(href)) { return false; }
			return samePanel(a.href);
		}

		function setActive(path) {
			var clean = path.replace(/\/$/, '');
			document.querySelectorAll('.promotur-nav__item, .promotur-bottomnav__item').forEach(function (a) {
				if (!a.getAttribute || !a.getAttribute('href')) { return; }
				var ap;
				try { ap = new URL(a.href).pathname.replace(/\/$/, ''); } catch (e) { return; }
				var isHome = /\/panel$/.test(ap);
				var active = isHome ? /\/panel$/.test(clean) : (clean === ap || clean.indexOf(ap + '/') === 0);
				a.classList.toggle('is-active', active);
			});
		}

		function swap(html, url) {
			var doc = new DOMParser().parseFromString(html, 'text/html');
			var next = doc.getElementById('promotur-content');
			if (!next) { window.location.href = url; return; }
			main.innerHTML = next.innerHTML;
			// Título del documento y de la topbar.
			if (doc.title) { document.title = doc.title; }
			var newTitle = doc.querySelector('.promotur-topbar__title');
			var curTitle = document.querySelector('.promotur-topbar__title');
			if (newTitle && curTitle) { curTitle.textContent = newTitle.textContent; }
			setActive(new URL(url, location.origin).pathname);
			main.scrollTop = 0;
			window.scrollTo(0, 0);
			if (typeof main.focus === 'function') { main.focus(); }
			initContent();
		}

		function go(url, push) {
			if (busy) { return; }
			busy = true;
			document.body.classList.add('promotur-loading');
			fetch(url, { credentials: 'same-origin', headers: { 'X-Requested-With': 'promotur-pjax' } })
				.then(function (r) {
					if (!r.ok) { throw new Error('http'); }
					return r.text();
				})
				.then(function (html) {
					if (push) { history.pushState({ promotur: 1 }, '', url); }
					swap(html, url);
				})
				.catch(function () { window.location.href = url; })
				.then(function () { busy = false; document.body.classList.remove('promotur-loading'); });
		}

		document.addEventListener('click', function (e) {
			if (e.defaultPrevented || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) { return; }
			var a = e.target.closest ? e.target.closest('a') : null;
			if (!a || !eligible(a)) { return; }
			e.preventDefault();
			if (a.href === location.href) { return; }
			// En móvil, cerrar el drawer al navegar.
			document.body.classList.remove('promotur-nav-open');
			var bd = document.querySelector('[data-drawer-backdrop]');
			if (bd) { bd.hidden = true; }
			go(a.href, true);
		});

		window.addEventListener('popstate', function () {
			if (samePanel(location.href)) { go(location.href, false); }
		});
	}

	/* ---------- Salida de campo (captura offline) ---------- */
	function initCaptura() {
		var root = document.querySelector('[data-captura]');
		var list = document.querySelector('[data-captura-list]');
		if (!root || !list) { return; }
		var KEY = 'promotur_capturas';
		var form = root.querySelector('[data-captura-form]');
		var msg = form.querySelector('[data-form-msg]');
		var photoInput = form.querySelector('[data-captura-photo]');
		var geoBtn = form.querySelector('[data-captura-geo]');
		var countEl = document.querySelector('[data-captura-count]');
		var syncBtn = document.querySelector('[data-captura-sync]');
		var photoData = null;

		function getQ() { try { return JSON.parse(localStorage.getItem(KEY)) || []; } catch (e) { return []; } }
		function setQ(q) { try { localStorage.setItem(KEY, JSON.stringify(q)); } catch (e) { alert('No hay espacio para guardar la foto offline.'); } }

		if (photoInput) {
			photoInput.addEventListener('change', function () {
				var f = photoInput.files && photoInput.files[0];
				if (!f) { photoData = null; return; }
				var r = new FileReader();
				r.onload = function () { photoData = r.result; };
				r.readAsDataURL(f);
			});
		}
		if (geoBtn && navigator.geolocation) {
			geoBtn.addEventListener('click', function () {
				geoBtn.disabled = true;
				navigator.geolocation.getCurrentPosition(function (p) {
					form.querySelector('[data-captura-lat]').value = p.coords.latitude.toFixed(6);
					form.querySelector('[data-captura-lng]').value = p.coords.longitude.toFixed(6);
					geoBtn.disabled = false;
				}, function () { geoBtn.disabled = false; }, { enableHighAccuracy: true, timeout: 8000 });
			});
		}

		form.addEventListener('submit', function (e) {
			e.preventDefault();
			var q = getQ();
			q.push({
				id: Date.now(),
				titulo: form.querySelector('[name="titulo"]').value,
				nota: form.querySelector('[name="nota"]').value,
				lat: form.querySelector('[data-captura-lat]').value,
				lng: form.querySelector('[data-captura-lng]').value,
				photo: photoData
			});
			setQ(q);
			form.reset();
			photoData = null;
			render();
			if (msg) { msg.textContent = '✓ ' + (navigator.onLine ? 'Guardada' : 'Guardada offline'); msg.className = 'promotur-form-msg is-success'; }
		});

		function render() {
			var q = getQ();
			if (countEl) { countEl.textContent = '(' + q.length + ')'; }
			if (!q.length) { list.innerHTML = '<p class="promotur-muted">Sin capturas pendientes.</p>'; return; }
			list.innerHTML = '';
			q.forEach(function (item) {
				var row = document.createElement('div');
				row.className = 'promotur-row';
				row.innerHTML = '<span class="promotur-row__main"><span class="promotur-row__title">' + escapeHtml(item.titulo || '(sin nombre)') + '</span>' +
					'<span class="promotur-row__meta">' + (item.lat ? '📍 ' + item.lat + ', ' + item.lng : 'sin GPS') + (item.photo ? ' · 📷' : '') + '</span></span>';
				var rm = document.createElement('button');
				rm.type = 'button'; rm.className = 'promotur-iconbtn'; rm.textContent = '✕';
				rm.addEventListener('click', function () { setQ(getQ().filter(function (x) { return x.id !== item.id; })); render(); });
				row.appendChild(rm);
				list.appendChild(row);
			});
		}

		if (syncBtn) {
			syncBtn.addEventListener('click', function () {
				if (!navigator.onLine) { alert('Necesitás conexión para sincronizar.'); return; }
				var q = getQ();
				if (!q.length) { return; }
				syncBtn.disabled = true;
				syncOne(q, 0);
			});
		}

		function syncOne(q, i) {
			if (i >= q.length) { syncBtn.disabled = false; render(); if (msg) { msg.textContent = '✓ Sincronizado'; msg.className = 'promotur-form-msg is-success'; } return; }
			var item = q[i];
			var afterPhoto = function (attachmentId) {
				var fd = new FormData();
				fd.append('post_id', '0');
				fd.append('titulo', item.titulo || '(sin título)');
				fd.append('descripcion', item.nota || '');
				if (item.lat) { fd.append('meta[_promotur_lat]', item.lat); }
				if (item.lng) { fd.append('meta[_promotur_lng]', item.lng); }
				if (attachmentId) { fd.append('meta[_promotur_portada]', attachmentId); }
				ajax('save_destino', fd).then(function (r) {
					if (r.success) { setQ(getQ().filter(function (x) { return x.id !== item.id; })); }
					syncOne(q, i + 1);
				}).catch(function () { syncOne(q, i + 1); });
			};
			if (item.photo) {
				fetch(item.photo).then(function (res) { return res.blob(); }).then(function (blob) {
					var pf = new FormData();
					pf.append('file', blob, 'captura-' + item.id + '.jpg');
					ajax('upload_media', pf).then(function (r) { afterPhoto(r.success ? r.data.id : 0); }).catch(function () { afterPhoto(0); });
				}).catch(function () { afterPhoto(0); });
			} else { afterPhoto(0); }
		}

		function escapeHtml(s) { return String(s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }
		render();
	}

	/* ---------- Gestión (tareas, nivel) ---------- */
	function initGestion() {
		// Crear tarea.
		var tform = document.querySelector('[data-tarea-form]');
		if (tform) {
			tform.addEventListener('submit', function (e) {
				e.preventDefault();
				var msg = tform.querySelector('[data-form-msg]');
				var fd = new FormData(tform);
				if (msg) { msg.textContent = i18n.sending; msg.className = 'promotur-form-msg'; }
				ajax('create_tarea', fd).then(function (r) {
					if (!r.success) { if (msg) { msg.textContent = (r.data && r.data.message) || i18n.error; msg.className = 'promotur-form-msg is-error'; } return; }
					window.location.reload();
				}).catch(function () { if (msg) { msg.textContent = i18n.error; msg.className = 'promotur-form-msg is-error'; } });
			});
		}
		// Reclamar / completar tareas.
		document.querySelectorAll('[data-tarea]').forEach(function (card) {
			var id = card.getAttribute('data-tarea');
			card.querySelectorAll('[data-op]').forEach(function (btn) {
				btn.addEventListener('click', function () {
					var map = { claim: 'claim_tarea', complete: 'complete_tarea' };
					var action = map[btn.getAttribute('data-op')];
					if (!action) { return; }
					btn.disabled = true;
					ajax(action, { id: id }).then(function (r) {
						if (r.success) { window.location.reload(); }
						else { btn.disabled = false; alert((r.data && r.data.message) || i18n.error); }
					}).catch(function () { btn.disabled = false; });
				});
			});
		});
		// Guardar nivel de confianza.
		document.querySelectorAll('[data-user]').forEach(function (card) {
			var save = card.querySelector('[data-nivel-save]');
			if (!save) { return; }
			save.addEventListener('click', function () {
				var sel = card.querySelector('[data-nivel-select]');
				var msg = card.querySelector('[data-form-msg]');
				ajax('set_nivel', { user_id: card.getAttribute('data-user'), level: sel ? sel.value : '' }).then(function (r) {
					if (msg) { msg.textContent = r.success ? (r.data.message || i18n.saved) : ((r.data && r.data.message) || i18n.error); msg.className = 'promotur-form-msg ' + (r.success ? 'is-success' : 'is-error'); }
				});
			});
		});
	}

	/* ---------- Moderación (panel) ---------- */
	function initModeration() {
		// Reseñas: aprobar / descartar.
		document.querySelectorAll('[data-mod-resena]').forEach(function (card) {
			var id = card.getAttribute('data-mod-resena');
			card.querySelectorAll('[data-op]').forEach(function (btn) {
				btn.addEventListener('click', function () {
					if (btn.getAttribute('data-op') === 'trash' && !confirm(i18n.confirm)) { return; }
					ajax('moderate_resena', { comment_id: id, op: btn.getAttribute('data-op') }).then(function (r) {
						if (r.success) { card.remove(); }
					});
				});
			});
		});
		// Consultas: derivar / resolver.
		document.querySelectorAll('[data-consulta]').forEach(function (card) {
			var id = card.getAttribute('data-consulta');
			card.querySelectorAll('[data-op]').forEach(function (btn) {
				btn.addEventListener('click', function () {
					var op = btn.getAttribute('data-op');
					if (op === 'assign') {
						var sel = card.querySelector('[data-consulta-user]');
						var uid = sel ? sel.value : '';
						if (!uid) { return; }
						ajax('assign_consulta', { id: id, user_id: uid }).then(function (r) { if (r.success) { flashCard(card); } });
					} else if (op === 'resolve') {
						ajax('resolve_consulta', { id: id }).then(function (r) { if (r.success) { card.remove(); } });
					}
				});
			});
		});
		// Reportes: resolver.
		document.querySelectorAll('[data-reporte]').forEach(function (card) {
			var id = card.getAttribute('data-reporte');
			var btn = card.querySelector('[data-op="resolve"]');
			if (btn) { btn.addEventListener('click', function () { ajax('resolve_reporte', { comment_id: id }).then(function (r) { if (r.success) { card.remove(); } }); }); }
		});
		function flashCard(card) { card.style.outline = '2px solid var(--promotur-brand)'; setTimeout(function () { card.style.outline = ''; }, 1200); }
	}

	/* ---------- Splash ---------- */
	function initSplash() {
		var splash = document.querySelector('[data-splash]');
		if (!splash) { return; }
		if (document.documentElement.classList.contains('promotur-no-splash')) { splash.classList.add('is-hidden'); return; }
		setTimeout(function () { splash.classList.add('is-hidden'); }, 1700);
	}

	/* ---------- Tema claro/oscuro ---------- */
	function initTheme() {
		document.querySelectorAll('[data-theme-toggle]').forEach(function (btn) {
			btn.addEventListener('click', function () {
				var dark = document.documentElement.getAttribute('data-theme') === 'dark';
				document.documentElement.setAttribute('data-theme', dark ? '' : 'dark');
				try { localStorage.setItem('promotur-theme', dark ? 'light' : 'dark'); } catch (e) {}
			});
		});
	}

	/* ---------- Drawer móvil ---------- */
	function initDrawer() {
		var toggle = document.querySelector('[data-drawer-toggle]');
		var backdrop = document.querySelector('[data-drawer-backdrop]');
		function open() { document.body.classList.add('promotur-nav-open'); if (backdrop) { backdrop.hidden = false; } if (toggle) { toggle.setAttribute('aria-expanded', 'true'); } }
		function close() { document.body.classList.remove('promotur-nav-open'); if (backdrop) { backdrop.hidden = true; } if (toggle) { toggle.setAttribute('aria-expanded', 'false'); } }
		if (toggle) {
			toggle.addEventListener('click', function () {
				document.body.classList.contains('promotur-nav-open') ? close() : open();
			});
		}
		if (backdrop) { backdrop.addEventListener('click', close); }
		document.addEventListener('keydown', function (e) { if (e.key === 'Escape') { close(); } });
		window.addEventListener('resize', function () { if (window.innerWidth >= 900) { close(); } });
	}

	/* ---------- Dropdowns ---------- */
	function initDropdowns() {
		var groups = document.querySelectorAll('[data-dropdown]');
		groups.forEach(function (group) {
			var toggle = group.querySelector('[data-dropdown-toggle]');
			var panel = group.querySelector('[data-dropdown-panel]');
			if (!toggle || !panel) { return; }
			toggle.addEventListener('click', function (e) {
				e.stopPropagation();
				var open = !panel.hidden;
				closeAll();
				panel.hidden = open;
				toggle.setAttribute('aria-expanded', open ? 'false' : 'true');
			});
		});
		function closeAll() {
			groups.forEach(function (g) {
				var p = g.querySelector('[data-dropdown-panel]');
				var t = g.querySelector('[data-dropdown-toggle]');
				if (p) { p.hidden = true; }
				if (t) { t.setAttribute('aria-expanded', 'false'); }
			});
		}
		document.addEventListener('click', closeAll);
		document.addEventListener('keydown', function (e) { if (e.key === 'Escape') { closeAll(); } });
	}

	/* ---------- Instalar app (PWA) ---------- */
	var deferredPrompt = null;
	function initInstall() {
		var btn = document.querySelector('[data-install-app]');
		window.addEventListener('beforeinstallprompt', function (e) {
			e.preventDefault();
			deferredPrompt = e;
			if (btn) { btn.hidden = false; }
		});
		if (btn) {
			btn.addEventListener('click', function () {
				if (!deferredPrompt) { return; }
				deferredPrompt.prompt();
				deferredPrompt.userChoice.finally(function () { deferredPrompt = null; btn.hidden = true; });
			});
		}
	}

	function initServiceWorker() {
		if ('serviceWorker' in navigator && CFG.swUrl) {
			window.addEventListener('load', function () {
				navigator.serviceWorker.register(CFG.swUrl, { scope: '/' }).catch(function () {});
			});
		}
	}

	/* ---------- Editor ---------- */
	function initEditor() {
		var form = document.querySelector('[data-editor-form]');
		if (!form) { return; }
		var msg = form.querySelector('[data-form-msg]');

		// Checklist en vivo.
		function refreshChecklist() {
			document.querySelectorAll('[data-checklist-key]').forEach(function (li) {
				var key = li.getAttribute('data-checklist-key');
				var field = form.querySelector('[data-check="' + cssEscape(key) + '"]');
				var done = field && String(field.value || '').trim() !== '';
				li.classList.toggle('is-done', !!done);
			});
		}
		form.addEventListener('input', refreshChecklist);
		refreshChecklist();

		// Subida de imágenes.
		form.querySelectorAll('[data-upload]').forEach(function (box) {
			var input = box.querySelector('[data-upload-input]');
			var value = box.querySelector('[data-upload-value]');
			var preview = box.querySelector('[data-upload-preview]');
			if (!input) { return; }
			input.addEventListener('change', function () {
				if (!input.files || !input.files[0]) { return; }
				var fd = new FormData();
				fd.append('file', input.files[0]);
				setMsg(i18n.sending, '');
				ajax('upload_media', fd).then(function (res) {
					if (!res.success) { setMsg((res.data && res.data.message) || i18n.error, 'is-error'); return; }
					value.value = res.data.id;
					if (preview && res.data.thumb) { preview.style.backgroundImage = 'url(' + res.data.thumb + ')'; }
					setMsg('', '');
					refreshChecklist();
				}).catch(function () { setMsg(i18n.error, 'is-error'); });
			});
		});

		// Geolocalización.
		var geoBtn = form.querySelector('[data-geolocate]');
		if (geoBtn && navigator.geolocation) {
			geoBtn.addEventListener('click', function () {
				geoBtn.disabled = true;
				navigator.geolocation.getCurrentPosition(function (pos) {
					var lat = form.querySelector('[data-coord="lat"]');
					var lng = form.querySelector('[data-coord="lng"]');
					if (lat) { lat.value = pos.coords.latitude.toFixed(6); }
					if (lng) { lng.value = pos.coords.longitude.toFixed(6); }
					geoBtn.disabled = false;
					refreshChecklist();
				}, function () { geoBtn.disabled = false; setMsg(i18n.error, 'is-error'); });
			});
		}

		function save() {
			var fd = new FormData(form);
			setMsg(i18n.sending, '');
			return ajax('save_destino', fd).then(function (res) {
				if (!res.success) { setMsg((res.data && res.data.message) || i18n.error, 'is-error'); return res; }
				var pid = form.querySelector('[name="post_id"]');
				if (pid && res.data.post_id) { pid.value = res.data.post_id; }
				renderChecklist(res.data.checklist);
				return res;
			});
		}

		function renderChecklist(list) {
			if (!list) { return; }
			list.forEach(function (item) {
				var li = document.querySelector('[data-checklist-key="' + cssEscape(item.key) + '"]');
				if (li) { li.classList.toggle('is-done', !!item.done); }
			});
		}

		form.querySelectorAll('[data-action]').forEach(function (btn) {
			btn.addEventListener('click', function () {
				var action = btn.getAttribute('data-action');
				setBusy(true);
				save().then(function (res) {
					if (!res || !res.success) { setBusy(false); return; }
					if (action === 'save') { setMsg(res.data.message || i18n.saved, 'is-success'); setBusy(false); return; }
					// submit
					var pid = form.querySelector('[name="post_id"]').value;
					ajax('submit_destino', { post_id: pid }).then(function (r2) {
						setBusy(false);
						if (!r2.success) { setMsg((r2.data && r2.data.message) || i18n.missing, 'is-error'); if (r2.data && r2.data.checklist) { renderChecklist(r2.data.checklist); } return; }
						setMsg(r2.data.message, 'is-success');
						if (r2.data.redirect) { window.location.href = r2.data.redirect; }
					}).catch(function () { setBusy(false); setMsg(i18n.error, 'is-error'); });
				}).catch(function () { setBusy(false); setMsg(i18n.error, 'is-error'); });
			});
		});

		function setBusy(b) { form.querySelectorAll('[data-action]').forEach(function (x) { x.disabled = b; }); }
		function setMsg(text, cls) { if (msg) { msg.textContent = text; msg.className = 'promotur-form-msg ' + (cls || ''); } }
	}

	/* ---------- Revisión ---------- */
	function initReview() {
		var box = document.querySelector('[data-review]');
		if (!box) { return; }
		var postId = box.getAttribute('data-review');
		var comment = box.querySelector('[data-review-comment]');
		var msg = box.querySelector('[data-form-msg]');

		box.querySelectorAll('[data-quickfb]').forEach(function (chip) {
			chip.addEventListener('click', function () {
				if (!comment) { return; }
				comment.value = (comment.value ? comment.value + '\n' : '') + chip.getAttribute('data-quickfb');
			});
		});

		box.querySelectorAll('[data-review-action]').forEach(function (btn) {
			btn.addEventListener('click', function () {
				var action = btn.getAttribute('data-review-action');
				var map = { assign: 'assign_review', approve: 'approve', return: 'return_changes' };
				if (action === 'return' && comment && !comment.value.trim()) {
					setMsg(i18n.missing, 'is-error'); return;
				}
				if (action === 'approve' && !confirm(i18n.confirm)) { return; }
				setBusy(true);
				ajax(map[action], { post_id: postId, comment: comment ? comment.value : '' }).then(function (res) {
					setBusy(false);
					if (!res.success) { setMsg((res.data && res.data.message) || i18n.error, 'is-error'); return; }
					setMsg(res.data.message, 'is-success');
					if (res.data.redirect) { window.location.href = res.data.redirect; }
					else { window.location.reload(); }
				}).catch(function () { setBusy(false); setMsg(i18n.error, 'is-error'); });
			});
		});

		function setBusy(b) { box.querySelectorAll('[data-review-action]').forEach(function (x) { x.disabled = b; }); }
		function setMsg(text, cls) { if (msg) { msg.textContent = text; msg.className = 'promotur-form-msg ' + (cls || ''); } }
	}

	/* CSS.escape con fallback (keys de meta tienen guiones bajos, seguro). */
	function cssEscape(s) {
		if (window.CSS && CSS.escape) { return CSS.escape(s); }
		return String(s).replace(/[^a-zA-Z0-9_-]/g, '\\$&');
	}
})();
