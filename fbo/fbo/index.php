<?php
declare(strict_types=1);

function local_asset_url(string $relativePath): string
{
	$cleanPath = ltrim($relativePath, '/');
	$fullPath  = dirname(__DIR__) . '/' . $cleanPath;
	$version   = is_file($fullPath) ? (string) filemtime($fullPath) : '1';
	return htmlspecialchars('../' . $cleanPath . '?v=' . rawurlencode($version), ENT_QUOTES, 'UTF-8');
}

$blogs = [];
$dbFile = dirname(dirname(__DIR__)) . '/multi-tenant/core/db.php';
if (is_file($dbFile)) {
	try {
		require_once $dbFile;
		$blogs = mt_list_blogs();
	} catch (Throwable $e) {}
}

$scheme = (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off') ? 'https' : 'http';
$host   = (string) ($_SERVER['HTTP_HOST'] ?? 'example.com');
?>
<!--
 __  __       ____  
|  \/  |     / ___| 
| \  / |    | | __  
| |\/| |    | |(  | 
| |  | |  _ | |_) |  _ 
(_)  (_) (_) \____| (_)
moritzgauss.com©
-->
<!doctype html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title>FBO Project — Fuck Being Online</title>
	<link rel="stylesheet" href="<?= local_asset_url('assets/css/styles.css') ?>">
	<style>
		/* header height measured by JS and stored here */
		:root { --fbo-header-h: 80px; }

		/* ── two-col wrapper fills remaining viewport ── */
		.fbo-page-wrap {
			display: grid;
			grid-template-columns: 280px 1fr;
			height: calc(100vh - var(--fbo-header-h));
		}

		/* ── left col: independently scrollable ── */
		.fbo-index-col {
			border-right: 2px solid var(--line);
			padding: 14px 12px;
			display: flex;
			flex-direction: column;
			gap: 10px;
			overflow-y: auto;
		}

		/* ── right col: independently scrollable ── */
		.fbo-info-col {
			padding: 14px;
			overflow-y: auto;
		}

		.fbo-info-col .archive.single {
			max-width: 100%;
			margin: 0;
			padding: 0;
			border: none;
		}

		/* ── mobile: stack, no fixed height ── */
		@media (max-width: 700px) {
			.fbo-page-wrap {
				grid-template-columns: 1fr;
				height: auto;
			}
			.fbo-index-col {
				border-right: none;
				border-bottom: 2px solid var(--line);
				overflow-y: visible;
			}
			.fbo-info-col {
				overflow-y: visible;
			}
		}

		/* ── blog index internals ── */
		.fbo-index-heading {
			font-size: 11px;
			font-weight: 700;
			letter-spacing: 0.07em;
			text-transform: uppercase;
			color: var(--muted);
			margin: 0;
			flex-shrink: 0;
		}

		.fbo-search {
			flex-shrink: 0;
			width: 100%;
			border: 2px solid var(--line);
			background: var(--bg);
			color: var(--fg);
			padding: 6px 10px;
			font-size: 13px;
			font-family: inherit;
			outline: none;
			border-radius: 0;
			-webkit-appearance: none;
		}
		.fbo-search::placeholder { color: var(--muted); }
		.fbo-search:focus { outline: 2px solid var(--fg); outline-offset: -2px; }

		.fbo-blog-list {
			display: flex;
			flex-direction: column;
			gap: 0;
		}

		.fbo-blog-item {
			display: flex;
			flex-direction: column;
			gap: 2px;
			padding: 7px 0;
			border-bottom: 1px solid var(--line);
		}
		.fbo-blog-item:last-child { border-bottom: none; }
		.fbo-blog-item[hidden]    { display: none; }

		.fbo-blog-item a {
			color: var(--fg);
			text-decoration: none;
			font-size: 13px;
			font-weight: 600;
		}
		.fbo-blog-item a:hover { text-decoration: underline; }

		.fbo-blog-date { font-size: 11px; color: var(--muted); }

		.fbo-no-results {
			font-size: 13px;
			color: var(--muted);
			display: none;
		}

		.fbo-load-more-wrap {
			display: none;
			padding-top: 4px;
			flex-shrink: 0;
		}

		.fbo-count { font-size: 11px; color: var(--muted); }
	</style>
</head>
<body class="intro-loading">
	<div class="intro-overlay" id="introOverlay" aria-hidden="true">
		<div class="intro-fbo" id="introFboText">F</div>
	</div>

	<header class="hero" id="fboHeader">
		<div class="hero-head">
			<a href="#" onclick="history.back(); return false;" class="logo logo-link">FBO Project</a>
		</div>
		<div class="subtitle-line">FBO Project stands for Fuck Being Online.</div>
	</header>

	<div class="fbo-page-wrap" id="fboPageWrap">

		<aside class="fbo-index-col">
			<p class="fbo-index-heading">Blogs <?php if ($blogs !== []): ?><span class="fbo-count" id="fboCount">(<?= count($blogs) ?>)</span><?php endif; ?></p>

			<input
				type="search"
				class="fbo-search"
				id="fboSearch"
				placeholder="Search by name or URL…"
				autocomplete="off"
				spellcheck="false"
			>

			<?php if ($blogs === []): ?>
				<p class="fbo-count">No blogs yet.</p>
			<?php else: ?>
				<div class="fbo-blog-list" id="fboBlogList">
					<?php foreach ($blogs as $blog):
						$word    = (string) ($blog['blog_word'] ?? '');
						$date    = (string) ($blog['created_at'] ?? '');
						$url     = '/blog/' . rawurlencode($word);
						$fullUrl = $scheme . '://' . $host . $url;
					?>
						<div class="fbo-blog-item"
							data-word="<?= htmlspecialchars($word, ENT_QUOTES, 'UTF-8') ?>"
							data-url="<?= htmlspecialchars($url, ENT_QUOTES, 'UTF-8') ?>"
							data-fullurl="<?= htmlspecialchars($fullUrl, ENT_QUOTES, 'UTF-8') ?>">
							<a href="<?= htmlspecialchars($url, ENT_QUOTES, 'UTF-8') ?>">/blog/<?= htmlspecialchars($word, ENT_QUOTES, 'UTF-8') ?></a>
							<?php if ($date !== ''): ?>
								<span class="fbo-blog-date"><?= htmlspecialchars($date, ENT_QUOTES, 'UTF-8') ?></span>
							<?php endif; ?>
						</div>
					<?php endforeach; ?>
				</div>

				<p class="fbo-no-results" id="fboNoResults">No blogs match your search.</p>

				<div class="fbo-load-more-wrap" id="fboLoadMoreWrap">
					<button class="ui-btn" id="fboLoadMore">Load more</button>
				</div>
			<?php endif; ?>
		</aside>

		<div class="fbo-info-col">
			<main class="archive single">
				<article class="item">
					<div class="text-post-body">FBO is a stripped-down blog template focused on speed, clarity, and easy customization. It includes first-run onboarding to set one-word blog name and admin password, plus text-post publishing/editing without heavy media/archive processing.</div>
					<div class="stamp">Core ideas</div>
				</article>
				<article class="item">
					<div class="text-post-body">Structure highlights: split CSS (<code>styles.css</code> + <code>admin.css</code>), split JS (<code>script.js</code> + <code>blog.js</code>), reusable snippets, and backend JSON placeholders. The goal is to give you a clean foundation you can ship or extend quickly.</div>
					<div class="stamp">Template architecture</div>
</article>
				
<article class="item">
    <div class="text-post-body">
No bloated databases or invasive tracking. Your data stays in lean JSON files—private, portable, and entirely under your control.
    </div>
    <div class="stamp">Data privacy</div>
</article>
	

			</main>
		</div>

	</div>

	<script src="<?= local_asset_url('assets/js/script.js') ?>" defer></script>
	<script>
	(function () {
		var MOBILE_BREAKPOINT = 700;
		var PAGE_SIZE = 20;

		// ── measure header, set CSS var so columns fill the rest ──
		function setHeaderVar() {
			var h = document.getElementById('fboHeader');
			var wrap = document.getElementById('fboPageWrap');
			if (h && wrap) {
				document.documentElement.style.setProperty('--fbo-header-h', h.offsetHeight + 'px');
			}
		}
		setHeaderVar();
		window.addEventListener('resize', setHeaderVar);

		// ── blog list logic ──
		var search   = document.getElementById('fboSearch');
		var list     = document.getElementById('fboBlogList');
		var noResult = document.getElementById('fboNoResults');
		var loadWrap = document.getElementById('fboLoadMoreWrap');
		var loadBtn  = document.getElementById('fboLoadMore');
		var countEl  = document.getElementById('fboCount');

		if (!list) return;

		var allItems     = Array.from(list.querySelectorAll('.fbo-blog-item'));
		var visibleEnd   = PAGE_SIZE;
		var currentQuery = '';

		function isMobile() { return window.innerWidth <= MOBILE_BREAKPOINT; }

		function applyState() {
			var q = currentQuery.toLowerCase().trim();
			var matched = [];

			allItems.forEach(function (item) {
				var fits =
					q === '' ||
					(item.dataset.word    || '').toLowerCase().indexOf(q) !== -1 ||
					(item.dataset.url     || '').toLowerCase().indexOf(q) !== -1 ||
					(item.dataset.fullurl || '').toLowerCase().indexOf(q) !== -1;
				if (fits) matched.push(item);
			});

			allItems.forEach(function (item) {
				var idx = matched.indexOf(item);
				if (idx === -1) { item.hidden = true; return; }
				// desktop: show all. mobile + no search query: paginate.
				item.hidden = (!isMobile() || q !== '') ? false : idx >= visibleEnd;
			});

			if (noResult) noResult.style.display = (q !== '' && matched.length === 0) ? '' : 'none';

			// load more: only on mobile, only when not searching
			if (loadWrap) loadWrap.style.display = (isMobile() && q === '' && matched.length > visibleEnd) ? '' : 'none';

			if (countEl) {
				var shown = matched.filter(function (i) { return !i.hidden; }).length;
				countEl.textContent = q !== '' ? '(' + shown + ' of ' + allItems.length + ')' : '(' + allItems.length + ')';
			}
		}

		if (search) {
			search.addEventListener('input', function () {
				currentQuery = search.value;
				visibleEnd = PAGE_SIZE;
				applyState();
			});
		}

		if (loadBtn) {
			loadBtn.addEventListener('click', function () {
				visibleEnd += PAGE_SIZE;
				applyState();
			});
		}

		window.addEventListener('resize', applyState);
		applyState();
	})();
	</script>
</body>
</html>