(() => {
	const body = document.body;
	const overlay = document.getElementById('introOverlay');
	const text = document.getElementById('introFboText');
	if (!body.classList.contains('intro-loading') || !overlay || !text) return;

	const frames = ['F', 'FB', 'FBO'];
	let frame = 0;
	let cycles = 0;

	const timer = window.setInterval(() => {
		text.textContent = frames[frame];
		frame += 1;
		if (frame >= frames.length) {
			frame = 0;
			cycles += 1;
			if (cycles >= 2) {
				window.clearInterval(timer);
				overlay.classList.add('done');
				window.setTimeout(() => {
					body.classList.remove('intro-loading');
					overlay.remove();
				}, 180);
			}
		}
	}, 130);
})();

(() => {
	const label = document.getElementById('pageInfoLabel');
	const select = document.getElementById('pageJumpSelect');
	if (!label || !select) return;

	const close = () => {
		select.classList.remove('open');
		label.setAttribute('aria-expanded', 'false');
	};

	label.addEventListener('click', (event) => {
		event.stopPropagation();
		const nextState = !select.classList.contains('open');
		select.classList.toggle('open', nextState);
		label.setAttribute('aria-expanded', nextState ? 'true' : 'false');
		if (nextState) {
			select.focus();
		}
	});

	select.addEventListener('change', () => {
		const selectedPage = Number(select.value || '1');
		if (!Number.isFinite(selectedPage) || selectedPage < 1) return;

		const url = new URL(window.location.href);
		url.searchParams.set('page', String(selectedPage));
		window.location.href = url.toString();
	});

	document.addEventListener('click', (event) => {
		if (event.target === label || event.target === select) return;
		if (!select.classList.contains('open')) return;
		close();
	});

	select.addEventListener('blur', () => {
		window.setTimeout(() => {
			if (document.activeElement !== select) {
				close();
			}
		}, 80);
	});
})();

(() => {
	const formatLocalEu = (tsSec) => {
		const d = new Date(tsSec * 1000);
		if (Number.isNaN(d.getTime())) return '';
		const pad = (v) => String(v).padStart(2, '0');
		return `${pad(d.getDate())}.${pad(d.getMonth() + 1)}.${d.getFullYear()} ${pad(d.getHours())}:${pad(d.getMinutes())}`;
	};

	document.querySelectorAll('.stamp[data-ts]').forEach((el) => {
		const ts = Number(el.getAttribute('data-ts'));
		if (!Number.isFinite(ts) || ts <= 0) return;
		const formatted = formatLocalEu(ts);
		if (formatted) {
			el.textContent = formatted;
		}
	});
})();

(() => {
	const storageKey = 'template-theme';
	const stored = localStorage.getItem(storageKey);
	if (stored === 'dark') {
		document.body.classList.add('dark');
	}
	const btn = document.getElementById('themeToggle');
	const refreshToggleLabel = () => {
		if (!btn) return;
		btn.textContent = document.body.classList.contains('dark') ? 'light mode' : 'dark mode';
	};

	refreshToggleLabel();
	if (btn) {
		btn.addEventListener('click', () => {
			document.body.classList.toggle('dark');
			localStorage.setItem(storageKey, document.body.classList.contains('dark') ? 'dark' : 'light');
			refreshToggleLabel();
		});
	}
})();

(() => {
	const body = document.body;
	const heroHead = document.querySelector('.hero .hero-head');
	if (!body || !heroHead) return;

	let lastY = window.scrollY || 0;
	let ticking = false;

	const applyState = () => {
		const currentY = window.scrollY || 0;
		const atTop = currentY <= 16;

		if (atTop) {
			body.classList.remove('header-peek');
			lastY = currentY;
			ticking = false;
			return;
		}

		const delta = currentY - lastY;
		if (delta <= -8 && currentY > 88) {
			body.classList.add('header-peek');
		} else if (delta >= 8) {
			body.classList.remove('header-peek');
		}

		lastY = currentY;
		ticking = false;
	};

	window.addEventListener('scroll', () => {
		if (ticking) return;
		ticking = true;
		window.requestAnimationFrame(applyState);
	}, { passive: true });
})();

(() => {
	const grid = document.querySelector('.archive.grid');
	if (!grid) return;

	const composeMode = document.body?.dataset?.composeMode === '1';
	if (composeMode) return;

	const articleItems = Array.from(grid.querySelectorAll('.item[data-post-id]'));
	if (!articleItems.length) return;

	articleItems.forEach((item) => {
		item.addEventListener('click', (event) => {
			if (event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
				return;
			}

			const target = event.target;
			if (!(target instanceof Element)) return;
			if (target.closest('a, button, input, textarea, select, label, form')) {
				return;
			}

			event.preventDefault();
			const postId = item.getAttribute('data-post-id') || '';
			if (!postId) return;

			const url = new URL(window.location.href);
			const currentPage = Number(url.searchParams.get('page') || '1');
			url.searchParams.set('view', 'single');
			url.searchParams.set('post_id', postId);
			url.searchParams.set('from_page', String(Number.isFinite(currentPage) && currentPage > 0 ? currentPage : 1));
			url.searchParams.delete('compose');
			window.location.href = url.toString();
		});
	});
})();
