(() => {
	const MOBILE_BREAKPOINT = 700;
	const PAGE_SIZE = 20;

	function setHeaderVar() {
		const h = document.getElementById('fboHeader');
		const wrap = document.getElementById('fboPageWrap');
		if (h && wrap) {
			document.documentElement.style.setProperty('--fbo-header-h', h.offsetHeight + 'px');
		}
	}
	setHeaderVar();
	window.addEventListener('resize', setHeaderVar);

	const search = document.getElementById('fboSearch');
	const list = document.getElementById('fboBlogList');
	const noResult = document.getElementById('fboNoResults');
	const loadWrap = document.getElementById('fboLoadMoreWrap');
	const loadBtn = document.getElementById('fboLoadMore');
	const countEl = document.getElementById('fboCount');

	if (!list) return;

	const allItems = Array.from(list.querySelectorAll('.fbo-blog-item'));
	let visibleEnd = PAGE_SIZE;
	let currentQuery = '';

	const isMobile = () => window.innerWidth <= MOBILE_BREAKPOINT;

	function applyState() {
		const q = currentQuery.toLowerCase().trim();
		const matched = allItems.filter(item =>
			q === '' ||
			(item.dataset.word || '').toLowerCase().includes(q) ||
			(item.dataset.url || '').toLowerCase().includes(q) ||
			(item.dataset.fullurl || '').toLowerCase().includes(q)
		);

		allItems.forEach(item => {
			const idx = matched.indexOf(item);
			if (idx === -1) { item.hidden = true; return; }
			item.hidden = (!isMobile() || q !== '') ? false : idx >= visibleEnd;
		});

		if (noResult) noResult.style.display = (q !== '' && matched.length === 0) ? '' : 'none';
		if (loadWrap) loadWrap.style.display = (isMobile() && q === '' && matched.length > visibleEnd) ? '' : 'none';

		if (countEl) {
			const shown = matched.filter(i => !i.hidden).length;
			countEl.textContent = q !== '' ? '(' + shown + ' of ' + allItems.length + ')' : '(' + allItems.length + ')';
		}
	}

	if (search) {
		search.addEventListener('input', () => {
			currentQuery = search.value;
			visibleEnd = PAGE_SIZE;
			applyState();
		});
	}

	if (loadBtn) {
		loadBtn.addEventListener('click', () => {
			visibleEnd += PAGE_SIZE;
			applyState();
		});
	}

	window.addEventListener('resize', applyState);
	applyState();
})();
