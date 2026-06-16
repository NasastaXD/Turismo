/**
 * Caaguazú Locales — interacción de frontend.
 * Reservas (WhatsApp), reseñas (rating, fotos, votos, respuestas) y panel de dueño.
 */
(function () {
	'use strict';

	var CFG = window.CGZ_LOC || {};
	var i18n = CFG.i18n || {};

	function ready(fn) {
		if (document.readyState !== 'loading') { fn(); }
		else { document.addEventListener('DOMContentLoaded', fn); }
	}

	/** POST a admin-ajax con FormData. */
	function ajax(action, data) {
		var body = new FormData();
		body.append('action', 'cgz_' + action);
		body.append('nonce', CFG.nonce);
		if (data instanceof FormData) {
			data.forEach(function (v, k) { body.append(k, v); });
		} else {
			Object.keys(data || {}).forEach(function (k) { body.append(k, data[k]); });
		}
		return fetch(CFG.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: body })
			.then(function (r) { return r.json(); });
	}

	ready(function () {
		initBooking();
		initRatingInputs();
		initPhotoUpload();
		initReviewForms();
		initReviewList(document);
		initOwnerPanel();
	});

	/* ------------------------------------------------------------------ */
	/* 1) Reservas por WhatsApp                                            */
	/* ------------------------------------------------------------------ */
	function initBooking() {
		document.querySelectorAll('[data-cgz-booking]').forEach(function (widget) {
			var phone = widget.getAttribute('data-phone');
			var textarea = widget.querySelector('[data-cgz-message]');
			var sendBtn = widget.querySelector('[data-cgz-send]');
			var options = widget.querySelectorAll('[data-cgz-option]');

			options.forEach(function (opt) {
				opt.addEventListener('click', function () {
					options.forEach(function (o) {
						o.classList.remove('is-active');
						o.setAttribute('aria-pressed', 'false');
					});
					opt.classList.add('is-active');
					opt.setAttribute('aria-pressed', 'true');
					if (textarea) { textarea.value = opt.getAttribute('data-message') || ''; }
				});
			});

			function buildUrl() {
				var msg = textarea ? textarea.value : '';
				return 'https://wa.me/' + phone + '?text=' + encodeURIComponent(msg);
			}
			if (sendBtn) {
				sendBtn.addEventListener('click', function (e) {
					e.preventDefault();
					window.open(buildUrl(), '_blank', 'noopener');
				});
			}
		});
	}

	/* ------------------------------------------------------------------ */
	/* 2) Estrellas de rating                                              */
	/* ------------------------------------------------------------------ */
	function initRatingInputs() {
		document.querySelectorAll('[data-cgz-rating]').forEach(function (wrap) {
			var input = wrap.querySelector('input[name="rating"]');
			var stars = wrap.querySelectorAll('.cgz-rating-star');

			function paint(value) {
				stars.forEach(function (s) {
					s.classList.toggle('is-on', parseInt(s.getAttribute('data-value'), 10) <= value);
				});
			}
			stars.forEach(function (star) {
				var val = parseInt(star.getAttribute('data-value'), 10);
				star.addEventListener('mouseenter', function () { paint(val); });
				star.addEventListener('click', function () {
					input.value = val;
					paint(val);
				});
			});
			wrap.addEventListener('mouseleave', function () { paint(parseInt(input.value, 10) || 0); });
		});
	}

	/* ------------------------------------------------------------------ */
	/* 3) Subida de fotos                                                  */
	/* ------------------------------------------------------------------ */
	function initPhotoUpload() {
		document.querySelectorAll('[data-cgz-photos]').forEach(function (box) {
			var input = box.querySelector('[data-cgz-photo-input]');
			var preview = box.querySelector('[data-cgz-photo-preview]');
			var hidden = box.querySelector('input[name="photos"]');
			var ids = [];

			if (!input) { return; }
			input.addEventListener('change', function () {
				Array.prototype.forEach.call(input.files, function (file) {
					if (ids.length >= 6) { return; }
					var fd = new FormData();
					fd.append('photo', file);
					var ph = document.createElement('div');
					ph.className = 'cgz-photos__item is-loading';
					preview.appendChild(ph);

					ajax('upload_photo', fd).then(function (res) {
						if (res.success) {
							ids.push(res.data.id);
							hidden.value = ids.join(',');
							ph.classList.remove('is-loading');
							ph.style.backgroundImage = 'url(' + res.data.thumb + ')';
							var rm = document.createElement('button');
							rm.type = 'button';
							rm.className = 'cgz-photos__remove';
							rm.textContent = '×';
							rm.addEventListener('click', function () {
								ids = ids.filter(function (x) { return x !== res.data.id; });
								hidden.value = ids.join(',');
								ph.remove();
							});
							ph.appendChild(rm);
						} else {
							ph.remove();
							alert((res.data && res.data.message) || i18n.error);
						}
					}).catch(function () { ph.remove(); });
				});
				input.value = '';
			});
		});
	}

	/* ------------------------------------------------------------------ */
	/* 4) Envío de reseña                                                  */
	/* ------------------------------------------------------------------ */
	function initReviewForms() {
		document.querySelectorAll('[data-cgz-review-form]').forEach(function (form) {
			form.addEventListener('submit', function (e) {
				e.preventDefault();
				var msg = form.querySelector('[data-cgz-form-msg]');
				var submitBtn = form.querySelector('button[type="submit"]');
				var fd = new FormData(form);

				if (submitBtn) { submitBtn.disabled = true; }
				if (msg) { msg.textContent = i18n.sending; msg.className = 'cgz-form-msg'; }

				ajax('submit_review', fd).then(function (res) {
					if (submitBtn) { submitBtn.disabled = false; }
					if (!res.success) {
						if (msg) { msg.textContent = (res.data && res.data.message) || i18n.error; msg.classList.add('is-error'); }
						return;
					}
					var section = form.closest('[data-cgz-reviews]');
					var list = section.querySelector('[data-cgz-reviews-list]');
					var empty = list.querySelector('.cgz-empty');
					if (empty) { empty.remove(); }

					var wrapper = document.createElement('div');
					wrapper.innerHTML = res.data.html;
					var node = wrapper.firstElementChild;
					// Reemplaza si el usuario ya tenía reseña.
					var existing = list.querySelector('[data-cgz-review="' + node.getAttribute('data-cgz-review') + '"]');
					if (existing) { existing.replaceWith(node); }
					else { list.insertBefore(node, list.firstChild); }

					initReviewList(node.parentNode);
					updateSummary(section, res.data.summary);
					form.reset();
					form.querySelectorAll('.cgz-rating-star').forEach(function (s) { s.classList.remove('is-on'); });
					var pv = form.querySelector('[data-cgz-photo-preview]');
					if (pv) { pv.innerHTML = ''; }
					if (msg) { msg.textContent = res.data.message; msg.classList.add('is-success'); }
				}).catch(function () {
					if (submitBtn) { submitBtn.disabled = false; }
					if (msg) { msg.textContent = i18n.error; msg.classList.add('is-error'); }
				});
			});
		});
	}

	function updateSummary(section, summary) {
		if (!summary) { return; }
		var avg = section.querySelector('.cgz-reviews__avg');
		var count = section.querySelector('.cgz-reviews__count');
		if (avg) { avg.textContent = summary.count ? summary.average : '—'; }
		if (count) {
			count.textContent = summary.count + (summary.count === 1 ? ' reseña' : ' reseñas');
		}
	}

	/* ------------------------------------------------------------------ */
	/* 5) Lista de reseñas: votos, respuestas, borrado                     */
	/* ------------------------------------------------------------------ */
	function initReviewList(scope) {
		// Votos.
		scope.querySelectorAll('[data-cgz-vote]').forEach(function (box) {
			if (box.dataset.bound) { return; }
			box.dataset.bound = '1';
			var reviewId = box.getAttribute('data-cgz-vote');
			box.querySelectorAll('.cgz-vote__btn').forEach(function (btn) {
				btn.addEventListener('click', function () {
					if (!CFG.isUser) { alert(i18n.loginNeeded); return; }
					ajax('vote_review', { review_id: reviewId, vote: btn.getAttribute('data-vote') }).then(function (res) {
						if (!res.success) { return; }
						box.querySelector('[data-cgz-vote-score]').textContent = res.data.score;
						box.querySelectorAll('.cgz-vote__btn').forEach(function (b) { b.classList.remove('is-active'); });
						if (res.data.vote > 0) { box.querySelector('[data-vote="1"]').classList.add('is-active'); }
						else if (res.data.vote < 0) { box.querySelector('[data-vote="-1"]').classList.add('is-active'); }
					});
				});
			});
		});

		// Toggle de formulario de respuesta.
		scope.querySelectorAll('[data-cgz-reply-toggle]').forEach(function (btn) {
			if (btn.dataset.bound) { return; }
			btn.dataset.bound = '1';
			btn.addEventListener('click', function () {
				if (!CFG.isUser) { alert(i18n.loginNeeded); return; }
				var review = btn.closest('[data-cgz-review]');
				var form = review.querySelector('[data-cgz-reply-form]');
				if (form) { form.hidden = !form.hidden; }
			});
		});

		// Envío de respuesta.
		scope.querySelectorAll('[data-cgz-reply-form]').forEach(function (form) {
			if (form.dataset.bound) { return; }
			form.dataset.bound = '1';
			form.addEventListener('submit', function (e) {
				e.preventDefault();
				var review = form.closest('[data-cgz-review]');
				var reviewId = review.getAttribute('data-cgz-review');
				var content = form.querySelector('textarea').value;
				ajax('submit_reply', { review_id: reviewId, content: content }).then(function (res) {
					if (!res.success) { alert((res.data && res.data.message) || i18n.error); return; }
					var replies = review.querySelector('[data-cgz-replies]');
					var wrapper = document.createElement('div');
					wrapper.innerHTML = res.data.html;
					replies.appendChild(wrapper.firstElementChild);
					form.reset();
					form.hidden = true;
				});
			});
		});

		// Borrado de reseña.
		scope.querySelectorAll('[data-cgz-delete-review]').forEach(function (btn) {
			if (btn.dataset.bound) { return; }
			btn.dataset.bound = '1';
			btn.addEventListener('click', function () {
				if (!confirm(i18n.confirmDelete)) { return; }
				var review = btn.closest('[data-cgz-review]');
				var section = btn.closest('[data-cgz-reviews]');
				ajax('delete_review', { review_id: review.getAttribute('data-cgz-review') }).then(function (res) {
					if (!res.success) { alert((res.data && res.data.message) || i18n.error); return; }
					review.remove();
					updateSummary(section, res.data.summary);
				});
			});
		});
	}

	/* ------------------------------------------------------------------ */
	/* 6) Panel de dueño                                                   */
	/* ------------------------------------------------------------------ */
	function initOwnerPanel() {
		document.querySelectorAll('[data-cgz-owner-form]').forEach(function (form) {
			form.addEventListener('submit', function (e) {
				e.preventDefault();
				var msg = form.querySelector('[data-cgz-form-msg]');
				var submitBtn = form.querySelector('button[type="submit"]');
				var fd = new FormData(form);
				fd.append('local_id', form.getAttribute('data-local'));

				if (submitBtn) { submitBtn.disabled = true; }
				if (msg) { msg.textContent = i18n.sending; msg.className = 'cgz-form-msg'; }

				ajax('save_local', fd).then(function (res) {
					if (submitBtn) { submitBtn.disabled = false; }
					if (msg) {
						msg.textContent = res.success ? res.data.message : ((res.data && res.data.message) || i18n.error);
						msg.classList.add(res.success ? 'is-success' : 'is-error');
					}
				}).catch(function () {
					if (submitBtn) { submitBtn.disabled = false; }
					if (msg) { msg.textContent = i18n.error; msg.classList.add('is-error'); }
				});
			});
		});
	}
})();
