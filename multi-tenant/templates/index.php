<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/core/tenant.php';
require_once dirname(__DIR__) . '/core/db.php';

$error     = '';
$blogInput = '';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && isset($_POST['create_blog'])) {
	$blogInput = (string) ($_POST['blog_word'] ?? '');
	$password  = (string) ($_POST['admin_password'] ?? '');

	if ($blogInput === '' || $password === '') {
		$error = 'Blog name and password are required.';
	} elseif (!preg_match('/^[A-Za-z0-9_-]+$/', $blogInput)) {
		$error = 'Use letters, numbers, - or _ only.';
	} elseif (mb_strlen($password) < 6) {
		$error = 'Password must be at least 6 characters.';
	} else {
		$result = mt_provision_blog($blogInput, $password);
		if (empty($result['ok'])) {
			$error = (string) ($result['message'] ?? 'Provisioning failed.');
		} else {
			$targetUrl = mt_blog_url((string) ($result['blog'] ?? $blogInput));
			header('Location: ' . $targetUrl);
			exit;
		}
	}
}

$blogs = mt_list_blogs();

// Asset versioning helper (assets live at /fbo/assets/)
function mt_asset_url(string $path): string
{
	$webRoot = dirname(dirname(__DIR__));
	$full    = $webRoot . '/fbo/' . ltrim($path, '/');
	$v       = is_file($full) ? (string) filemtime($full) : '1';
	return htmlspecialchars('/fbo/' . ltrim($path, '/') . '?v=' . rawurlencode($v), ENT_QUOTES, 'UTF-8');
}

$scheme      = (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off') ? 'https' : 'http';
$host        = (string) ($_SERVER['HTTP_HOST'] ?? 'example.com');
$previewWord = $blogInput !== '' ? mt_normalize_blog_word($blogInput) : 'myblog';
$previewUrl  = $scheme . '://' . $host . '/blog/' . rawurlencode($previewWord);
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
	<title>FBO Project - Onboarding</title>
	<link rel="stylesheet" href="<?= mt_asset_url('assets/css/styles.css') ?>">
	<link rel="stylesheet" href="<?= mt_asset_url('assets/css/admin.css') ?>">
	<link rel="stylesheet" href="<?= mt_asset_url('assets/css/onboarding.css') ?>">
	<style>
		.ob-search {
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

		.ob-search::placeholder {
			color: var(--muted);
		}

		.ob-search:focus {
			outline: 2px solid var(--fg);
			outline-offset: -2px;
		}

		.ob-blog-item {
			/* uses existing .subtitle-line inside upload-panel */
		}

		.ob-blog-item[hidden] {
			display: none;
		}

		.ob-no-results {
			display: none;
			font-size: 13px;
			color: var(--muted);
			padding: 4px 0;
		}

		.ob-blog-count {
			font-size: 12px;
			color: var(--muted);
		}
	</style>
</head>
<body class="onboarding-page">
	<main class="onboarding-wrap">
		<section class="onboarding-card">

			<div class="hero onboarding-preview">
				<div class="hero-head onboarding-preview-head">
					<div class="logo" id="onboardingTitlePreview"><?= htmlspecialchars(strtoupper($previewWord), ENT_QUOTES, 'UTF-8') ?></div>
					<a href="/fbo/fbo" class="fbo fbo-link">FBO</a>
				</div>
			</div>

			<p class="subtitle-line onboarding-lead">Create a new blog — it will be available at <code>/blog/&lt;name&gt;</code> immediately.</p>
			<p class="subtitle-line">1–24 chars, letters/numbers/_/-, title always displays in ALL CAPS.</p>
			<p class="subtitle-line">Path preview: <span class="onboarding-url-preview" id="onboardingUrlPreview"><?= htmlspecialchars($previewUrl, ENT_QUOTES, 'UTF-8') ?></span></p>

			<?php if ($error !== ''): ?>
				<div class="subtitle-line onboarding-error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
			<?php endif; ?>

			<form method="post" class="upload-panel onboarding-form" id="onboardingForm">
				<input
					id="onboardingBlogWord"
					class="upload-auth-input"
					type="text"
					name="blog_word"
					maxlength="24"
					pattern="[A-Za-z0-9_-]+"
					value="<?= htmlspecialchars($blogInput, ENT_QUOTES, 'UTF-8') ?>"
					placeholder="Blog name (also the URL path)"
					required
					autocomplete="off"
				>
				<input class="upload-auth-input" type="password" name="admin_password" minlength="6" maxlength="120" placeholder="Admin password (min 6 chars)" required>
				<div class="hero-actions">
					<button type="submit" name="create_blog" value="1" class="ui-btn">Create blog &rarr;</button>
				</div>
			</form>

			<?php if ($blogs !== []): ?>
				<div class="subtitle-line">
					<strong>Existing blogs</strong>
					<span class="ob-blog-count" id="obCount">(<?= count($blogs) ?>)</span>
				</div>

				<input
					type="search"
					class="ob-search"
					id="obSearch"
					placeholder="Search by name or URL…"
					autocomplete="off"
					spellcheck="false"
				>

				<div class="upload-panel" id="obBlogList">
					<?php foreach ($blogs as $blog):
						$word    = (string) ($blog['blog_word'] ?? '');
						$date    = (string) ($blog['created_at'] ?? '');
						$url     = '/blog/' . rawurlencode($word);
						$fullUrl = $scheme . '://' . $host . $url;
						$safeWord    = htmlspecialchars($word, ENT_QUOTES, 'UTF-8');
						$safeUrl     = htmlspecialchars($url, ENT_QUOTES, 'UTF-8');
						$safeFullUrl = htmlspecialchars($fullUrl, ENT_QUOTES, 'UTF-8');
						$safeDate    = htmlspecialchars($date, ENT_QUOTES, 'UTF-8');
					?>
						<div class="subtitle-line ob-blog-item"
							data-word="<?= $safeWord ?>"
							data-url="<?= $safeUrl ?>"
							data-fullurl="<?= $safeFullUrl ?>">
							<a href="<?= $safeUrl ?>" class="text-link">/blog/<?= $safeWord ?></a>
							<span class="upload-note">&mdash; <?= $safeDate ?></span>
						</div>
					<?php endforeach; ?>
				</div>

				<p class="ob-no-results" id="obNoResults">No blogs match your search.</p>

				<div id="obLoadMoreWrap" style="display:none; padding-top: 6px;">
					<button class="ui-btn" id="obLoadMore">Load more</button>
				</div>
			<?php endif; ?>

		</section>
	</main>

	<script src="<?= mt_asset_url('assets/js/onboarding.js') ?>" defer></script>
	<script>
	(function () {
		var PAGE_SIZE = 20;

		var search    = document.getElementById('obSearch');
		var list      = document.getElementById('obBlogList');
		var noResult  = document.getElementById('obNoResults');
		var countEl   = document.getElementById('obCount');
		var loadWrap  = document.getElementById('obLoadMoreWrap');
		var loadBtn   = document.getElementById('obLoadMore');

		if (!list) return;

		var allItems   = Array.from(list.querySelectorAll('.ob-blog-item'));
		var visibleEnd = PAGE_SIZE;

		function applyState() {
			var q = search ? search.value.toLowerCase().trim() : '';
			var matched = [];

			allItems.forEach(function (item) {
				var word    = (item.dataset.word    || '').toLowerCase();
				var url     = (item.dataset.url     || '').toLowerCase();
				var fullUrl = (item.dataset.fullurl || '').toLowerCase();
				var fits = q === '' || word.indexOf(q) !== -1 || url.indexOf(q) !== -1 || fullUrl.indexOf(q) !== -1;
				if (fits) matched.push(item);
			});

			allItems.forEach(function (item) {
				if (!item._matched) { item.hidden = true; return; }
				var idx = matched.indexOf(item);
				// When searching — show all matches. When browsing — paginate.
				item.hidden = (q === '') ? idx >= visibleEnd : false;
			});

			// mark matched for the hidden logic above
			allItems.forEach(function (item) { item._matched = false; });
			matched.forEach(function (item) { item._matched = true; });

			// re-apply hidden now that _matched is set
			allItems.forEach(function (item) {
				if (!item._matched) { item.hidden = true; return; }
				var idx = matched.indexOf(item);
				item.hidden = (q === '') ? idx >= visibleEnd : false;
			});

			if (noResult) {
				noResult.style.display = (q !== '' && matched.length === 0) ? '' : 'none';
			}

			if (loadWrap) {
				loadWrap.style.display = (q === '' && matched.length > visibleEnd) ? '' : 'none';
			}

			if (countEl) {
				var shown = matched.filter(function(i){ return !i.hidden; }).length;
				countEl.textContent = q !== '' ? '(' + shown + ' of ' + allItems.length + ')' : '(' + allItems.length + ')';
			}
		}

		if (search) {
			search.addEventListener('input', function () {
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

		applyState();
	})();
	</script>
</body>
</html>