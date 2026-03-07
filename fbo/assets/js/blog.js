(() => {
	const textarea = document.getElementById('textPostContent');
	const count = document.getElementById('textPostCount');
	const epoch = document.getElementById('textPostClientEpoch');
	const form = document.getElementById('textPostForm');
	if (!textarea || !count || !epoch || !form) return;

	const limit = Number(document.body?.dataset?.maxTextPostLength || '280');
	const refreshCount = () => {
		const len = textarea.value.length;
		count.textContent = `${len} / ${limit}`;
	};

	textarea.addEventListener('input', refreshCount);
	form.addEventListener('submit', () => {
		epoch.value = String(Date.now());
	});
	refreshCount();
})();

(() => {
	const input = document.getElementById('inlineUploadFiles');
	const epochInput = document.getElementById('uploadClientEpoch');
	const form = document.getElementById('inlineUploadForm');
	const preview = document.getElementById('inlineUploadPreview');
	const empty = document.getElementById('inlineUploadEmpty');
	const cancelBtn = document.getElementById('cancelInlineUpload');
	if (!input || !preview || !empty || !cancelBtn || !epochInput || !form) return;

	const imageExt = new Set(['jpg', 'jpeg', 'png', 'webp', 'gif']);
	const videoExt = new Set(['mp4', 'mov', 'webm', 'm4v']);

	const clearPreview = () => {
		preview.innerHTML = '';
		empty.style.display = '';
	};

	const render = () => {
		epochInput.value = String(Date.now());
		preview.innerHTML = '';
		const files = Array.from(input.files || []);
		if (files.length === 0) {
			empty.style.display = '';
			return;
		}
		empty.style.display = 'none';

		for (const file of files) {
			const card = document.createElement('article');
			card.className = 'item';

			const mediaWrap = document.createElement('div');
			mediaWrap.className = 'media-wrap';
			const ext = (file.name.split('.').pop() || '').toLowerCase();
			const url = URL.createObjectURL(file);

			if (videoExt.has(ext)) {
				const video = document.createElement('video');
				video.src = url;
				video.preload = 'metadata';
				video.playsInline = true;
				mediaWrap.appendChild(video);
			} else if (imageExt.has(ext)) {
				const img = document.createElement('img');
				img.src = url;
				img.alt = file.name;
				mediaWrap.appendChild(img);
			} else {
				const fallback = document.createElement('div');
				fallback.className = 'stamp';
				fallback.textContent = file.name;
				mediaWrap.appendChild(fallback);
			}

			const stamp = document.createElement('div');
			stamp.className = 'stamp';
			stamp.textContent = file.name;

			card.appendChild(mediaWrap);
			card.appendChild(stamp);
			preview.appendChild(card);
		}
	};

	cancelBtn.addEventListener('click', () => {
		input.value = '';
		epochInput.value = '';
		clearPreview();
	});

	form.addEventListener('submit', () => {
		if (!epochInput.value) {
			epochInput.value = String(Date.now());
		}
	});

	input.addEventListener('change', render);
})();

(() => {
	const titleInput = document.getElementById('editBlogWord');
	const titlePreview = document.getElementById('editTitlePreview');
	const urlPreview = document.getElementById('editUrlPreview');
	const siteTitleDisplay = document.getElementById('siteTitleDisplay');
	if (!titleInput || !titlePreview) return;

	const normalizeBlogWord = (value) => {
		const cleaned = String(value || '').trim().replace(/[^A-Za-z0-9_-]/g, '');
		return cleaned.slice(0, 24) || 'fbo';
	};

	const rootFromCurrentHost = () => {
		const host = window.location.host.replace(/:\d+$/, '');
		if (!host) return 'example.com';
		if (/^\d{1,3}(?:\.\d{1,3}){3}$/.test(host) || host === 'localhost') {
			return host;
		}
		const parts = host.split('.').filter(Boolean);
		if (parts.length >= 2) {
			return `${parts[parts.length - 2]}.${parts[parts.length - 1]}`;
		}
		return host;
	};

	const updatePreview = () => {
		const safe = normalizeBlogWord(titleInput.value);
		titlePreview.textContent = safe.toUpperCase();
		if (siteTitleDisplay) {
			siteTitleDisplay.textContent = safe.toUpperCase();
		}
		if (urlPreview) {
			const protocol = window.location.protocol || 'https:';
			urlPreview.textContent = `${protocol}//${safe.toLowerCase()}.${rootFromCurrentHost()}`;
		}
	};

	['input', 'keyup', 'change', 'blur'].forEach((eventName) => {
		titleInput.addEventListener(eventName, updatePreview);
	});

	window.addEventListener('pageshow', updatePreview);
	updatePreview();
})();

(() => {
	const markButtons = Array.from(document.querySelectorAll('.mark-delete-btn[data-post-id]'));
	const form = document.getElementById('pendingDeleteForm');
	const inputs = document.getElementById('pendingDeleteInputs');
	const countEl = document.getElementById('pendingDeleteCount');
	const cancelBtn = document.getElementById('cancelDeleteBtn');
	const saveDeleteBtn = document.getElementById('saveDeleteBtn');
	const saveCloseBtn = document.getElementById('saveCloseUploadBtn');
	const closeAfterSaveInput = document.getElementById('closeAfterSaveInput');

	if (!markButtons.length || !form || !inputs || !countEl || !cancelBtn) {
		if (saveCloseBtn) {
			saveCloseBtn.addEventListener('click', () => {
				const closeUrl = saveCloseBtn.getAttribute('data-close-url');
				if (closeUrl) window.location.href = closeUrl;
			});
		}
		return;
	}

	const selected = new Set();

	const refreshState = () => {
		inputs.innerHTML = '';
		selected.forEach((postId) => {
			const input = document.createElement('input');
			input.type = 'hidden';
			input.name = 'page_post_ids[]';
			input.value = postId;
			inputs.appendChild(input);
		});

		const count = selected.size;
		countEl.textContent = `${count} selected for delete.`;
		form.classList.toggle('active', count > 0);
	};

	markButtons.forEach((button) => {
		button.addEventListener('click', () => {
			const postId = button.getAttribute('data-post-id') || '';
			if (!postId) return;

			const article = button.closest('.item');
			if (selected.has(postId)) {
				selected.delete(postId);
				button.textContent = 'Delete';
				if (article) article.classList.remove('marked-delete');
			} else {
				selected.add(postId);
				button.textContent = 'Undo';
				if (article) article.classList.add('marked-delete');
			}

			refreshState();
		});
	});

	cancelBtn.addEventListener('click', () => {
		selected.clear();
		markButtons.forEach((button) => {
			button.textContent = 'Delete';
			const article = button.closest('.item');
			if (article) article.classList.remove('marked-delete');
		});
		if (closeAfterSaveInput) closeAfterSaveInput.value = '0';
		refreshState();
	});

	if (saveDeleteBtn) {
		saveDeleteBtn.addEventListener('click', (event) => {
			if (selected.size <= 0) {
				event.preventDefault();
				return;
			}
			if (!window.confirm(`Delete ${selected.size} selected post(s)?`)) {
				event.preventDefault();
			}
		});
	}

	if (saveCloseBtn) {
		saveCloseBtn.addEventListener('click', () => {
			const closeUrl = saveCloseBtn.getAttribute('data-close-url');
			if (selected.size > 0) {
				if (closeAfterSaveInput) closeAfterSaveInput.value = '1';
				form.submit();
				return;
			}
			if (closeUrl) window.location.href = closeUrl;
		});
	}

	refreshState();
})();
