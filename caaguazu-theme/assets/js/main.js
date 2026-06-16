/**
 * Caaguazú Turismo — JS de interacción.
 * Reemplaza la lógica de React/TanStack del sitio estático original
 * con vanilla JS, manteniendo los mismos efectos visuales.
 */
(function () {
	'use strict';

	const ready = (fn) => {
		if (document.readyState !== 'loading') { fn(); }
		else { document.addEventListener('DOMContentLoaded', fn); }
	};

	const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

	ready(() => {

		// 1) MOBILE MENU TOGGLE
		const toggle = document.querySelector('[data-mobile-toggle]');
		const menu = document.getElementById('mobile-menu');
		if (toggle && menu) {
			toggle.addEventListener('click', () => {
				const isOpen = !menu.classList.contains('hidden');
				menu.classList.toggle('hidden');
				menu.setAttribute('aria-hidden', isOpen ? 'true' : 'false');
				toggle.setAttribute('aria-expanded', isOpen ? 'false' : 'true');
			});
		}

		// 2) REVEAL-ON-SCROLL
		// El HTML original viene con inline styles "opacity:0; transform:translateY(...)".
		// Animamos a estado natural cuando entran al viewport.
		const revealTargets = document.querySelectorAll('main [style*="opacity:0"], main [style*="opacity: 0"]');

		if (reduceMotion || !('IntersectionObserver' in window)) {
			// Sin animación: simplemente mostrarlos.
			revealTargets.forEach((el) => {
				el.style.opacity = '';
				el.style.transform = '';
			});
		} else {
			const io = new IntersectionObserver((entries) => {
				entries.forEach((entry) => {
					if (!entry.isIntersecting) { return; }
					const el = entry.target;
					el.style.transition = 'opacity 700ms cubic-bezier(.4,0,.2,1), transform 700ms cubic-bezier(.4,0,.2,1)';
					el.style.opacity = '1';
					el.style.transform = 'translateY(0)';
					io.unobserve(el);
				});
			}, { rootMargin: '0px 0px -10% 0px', threshold: 0.05 });

			revealTargets.forEach((el) => io.observe(el));
		}

		// 3) ANIMATED COUNTERS
		// Los <span class="tabular-nums">0</span> dentro del bloque de estadísticas
		// se animan hacia el valor final. Definimos los targets por orden.
		const counterSection = document.querySelector('.grid.grid-cols-2.md\\:grid-cols-4');
		if (counterSection) {
			const counterEls = counterSection.querySelectorAll('.tabular-nums');
			const targets = [181, 90, 5000, 4500];
			counterEls.forEach((el, idx) => {
				if (typeof targets[idx] === 'undefined') { return; }
				el.dataset.target = String(targets[idx]);
			});

			const animateCount = (el) => {
				const target = parseInt(el.dataset.target, 10);
				if (isNaN(target)) { return; }
				if (reduceMotion) { el.textContent = target.toLocaleString('es'); return; }

				const duration = 1400;
				const start = performance.now();
				const ease = (t) => 1 - Math.pow(1 - t, 3);

				const step = (now) => {
					const elapsed = now - start;
					const progress = Math.min(elapsed / duration, 1);
					const value = Math.floor(ease(progress) * target);
					el.textContent = value.toLocaleString('es');
					if (progress < 1) { requestAnimationFrame(step); }
					else { el.textContent = target.toLocaleString('es'); }
				};

				requestAnimationFrame(step);
			};

			if ('IntersectionObserver' in window) {
				const countObs = new IntersectionObserver((entries) => {
					entries.forEach((entry) => {
						if (!entry.isIntersecting) { return; }
						counterEls.forEach(animateCount);
						countObs.disconnect();
					});
				}, { threshold: 0.3 });
				countObs.observe(counterSection);
			} else {
				counterEls.forEach(animateCount);
			}
		}

		// 4) GLOSSARY TOOLTIPS (palabras guaraníes)
		// Los botones marcados con aria-label dentro del párrafo abren un tooltip al click.
		document.querySelectorAll('button[aria-label][data-state="closed"]').forEach((btn) => {
			const label = btn.getAttribute('aria-label');
			if (!label) { return; }
			btn.addEventListener('click', (e) => {
				e.preventDefault();
				closeAllTooltips();
				const tip = document.createElement('span');
				tip.className = 'caaguazu-tooltip';
				tip.textContent = label;
				tip.style.cssText = `
					position: absolute;
					z-index: 100;
					background: var(--color-ink, #16201a);
					color: var(--color-snow, #fff);
					padding: 8px 12px;
					border-radius: 8px;
					font-size: 0.85rem;
					line-height: 1.4;
					max-width: 320px;
					box-shadow: 0 8px 24px rgba(0,0,0,0.18);
					font-family: var(--font-display, system-ui), system-ui, sans-serif;
				`;
				const rect = btn.getBoundingClientRect();
				tip.style.top = (window.scrollY + rect.bottom + 6) + 'px';
				tip.style.left = (window.scrollX + rect.left) + 'px';
				document.body.appendChild(tip);
				btn.setAttribute('data-state', 'open');

				setTimeout(() => {
					document.addEventListener('click', closeAllTooltips, { once: true });
				}, 0);
			});
		});

		function closeAllTooltips() {
			document.querySelectorAll('.caaguazu-tooltip').forEach((el) => el.remove());
			document.querySelectorAll('button[aria-label][data-state="open"]').forEach((b) => b.setAttribute('data-state', 'closed'));
		}

		// 5) HOVER LIFT EN TARJETAS (efecto de gap-2.5 al hover)
		// CSS-only; no se necesita JS.

	});

})();
