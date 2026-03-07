// ── Live onboarding preview ───────────────────────────────────────────────
(() => {
	const titleInput = document.getElementById('onboardingBlogWord');
	const titlePreview = document.getElementById('onboardingTitlePreview');
	const urlPreview = document.getElementById('onboardingUrlPreview');

	if (!titleInput || !titlePreview) {
		return;
	}

	const updatePreview = () => {
		const raw = titleInput.value.trim();
		const safe = raw !== '' ? raw : 'yourname';
		titlePreview.textContent = safe.toUpperCase();
		if (urlPreview) {
			const protocol = window.location.protocol || 'https:';
			const host = window.location.host || 'example.com';
			urlPreview.textContent = `${protocol}//${host}/blog/${safe.toLowerCase()}`;
		}
	};

	['input', 'keyup', 'change', 'blur'].forEach((eventName) => {
		titleInput.addEventListener(eventName, updatePreview);
	});

	window.addEventListener('pageshow', updatePreview);
	updatePreview();
})();

// ── Confirm dialogs via data-confirm attribute ────────────────────────────
(() => {
	document.addEventListener('submit', (event) => {
		const form = event.target;
		const btn = form.querySelector('[data-confirm]');
		if (!btn) return;
		const message = btn.getAttribute('data-confirm') || 'Are you sure?';
		if (!window.confirm(message)) {
			event.preventDefault();
		}
	});
})();

// ── SVG icon thumbnails → PNG ─────────────────────────────────────────────
(() => {
	const svgNodes = Array.from(document.querySelectorAll('.icon-thumb svg'));
	if (!svgNodes.length) return;
	const previewSize = 120;

	const toPng = (svg) => {
		try {
			const serializer = new XMLSerializer();
			const svgMarkup = serializer.serializeToString(svg);
			const svgData = `data:image/svg+xml;charset=utf-8,${encodeURIComponent(svgMarkup)}`;
			const image = new Image();
			image.onload = () => {
				const canvas = document.createElement('canvas');
				canvas.width = previewSize;
				canvas.height = previewSize;
				const ctx = canvas.getContext('2d');
				if (!ctx) return;
				ctx.clearRect(0, 0, previewSize, previewSize);
				ctx.drawImage(image, 0, 0, previewSize, previewSize);
				const pngUrl = canvas.toDataURL('image/png');
				const img = document.createElement('img');
				img.src = pngUrl;
				img.alt = 'Icon preview';
				img.width = previewSize;
				img.height = previewSize;
				svg.replaceWith(img);
			};
			image.src = svgData;
		} catch {
			// silently ignore
		}
	};

	svgNodes.forEach(toPng);
})();