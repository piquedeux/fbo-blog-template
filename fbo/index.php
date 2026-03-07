<?php
declare(strict_types=1);

session_start();

const ADMIN_SESSION_KEY        = 'fbo_admin_auth';
const MAX_TEXT_POST_LENGTH     = 280;
const MAX_UPLOAD_FILE_SIZE_BYTES = 104857600;
const FLASH_MESSAGE_SESSION_KEY  = 'fbo_flash_message';
const OTP_DISPLAY_SESSION_KEY    = 'fbo_otp_once';
const OTP_RESET_SESSION_KEY      = 'fbo_otp_reset';
const MEDIA_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp', 'gif', 'mp4', 'mov', 'webm', 'm4v', 'mp3', 'wav', 'flac', 'ogg', 'm4a'];
const BLOG_WORD_MAX_LENGTH = 24;

function blog_root(): string
{
	if (defined('BLOG_ROOT') && is_string(BLOG_ROOT) && BLOG_ROOT !== '') {
		return rtrim((string) BLOG_ROOT, '/');
	}

	$cwd = getcwd();
	if ($cwd !== false && $cwd !== '') {
		return rtrim($cwd, '/');
	}

	return rtrim(__DIR__, '/');
}

function backend_dir_path(): string
{
	$path = blog_root() . '/backend';
	if (!is_dir($path)) {
		mkdir($path, 0775, true);
	}
	return $path;
}

function settings_path(): string
{
	return backend_dir_path() . '/settings.json';
}

function auth_path(): string
{
	return backend_dir_path() . '/.auth.json';
}

function otp_path(): string
{
	return backend_dir_path() . '/.otp.json';
}

function posts_path(): string
{
	return backend_dir_path() . '/posts.json';
}

function media_dir_path(): string
{
	$path = blog_root() . '/media';
	if (!is_dir($path)) {
		mkdir($path, 0775, true);
	}
	return $path;
}

function asset_url(string $path): string
{
	if ($path === '' || preg_match('#^https?://#i', $path)) {
		return $path;
	}

	if (defined('MEDIA_WEB_ROOT') && is_string(MEDIA_WEB_ROOT) && MEDIA_WEB_ROOT !== '') {
		if (str_starts_with(ltrim($path, '/'), 'media/')) {
			$filename = substr(ltrim($path, '/'), strlen('media/'));
			return rtrim((string) MEDIA_WEB_ROOT, '/') . '/' . rawurlencode($filename);
		}
	}

	$segments = explode('/', $path);
	$encodedSegments = array_map(static function (string $segment): string {
		return rawurlencode($segment);
	}, $segments);

	$encoded = implode('/', $encodedSegments);
	if ($path[0] === '/' && ($encoded === '' || $encoded[0] !== '/')) {
		$encoded = '/' . $encoded;
	}

	return $encoded;
}

function local_asset_url(string $relativePath): string
{
	$cleanPath = ltrim($relativePath, '/');

	if (defined('ASSET_BASE_URL') && is_string(ASSET_BASE_URL) && ASSET_BASE_URL !== '') {
		$fullPath = __DIR__ . '/' . $cleanPath;
		$version  = is_file($fullPath) ? (string) filemtime($fullPath) : '1';
		return htmlspecialchars(
			rtrim((string) ASSET_BASE_URL, '/') . '/' . $cleanPath . '?v=' . rawurlencode($version),
			ENT_QUOTES, 'UTF-8'
		);
	}

	$fullPath = blog_root() . '/' . $cleanPath;
	$version  = is_file($fullPath) ? (string) filemtime($fullPath) : '1';
	return htmlspecialchars($cleanPath . '?v=' . rawurlencode($version), ENT_QUOTES, 'UTF-8');
}

function normalize_blog_word(string $value): string
{
	$word = trim($value);
	$word = preg_replace('/[^A-Za-z0-9_-]/', '', $word) ?? '';
	$word = mb_substr($word, 0, BLOG_WORD_MAX_LENGTH);
	return $word === '' ? 'fbo' : $word;
}

function request_scheme(): string
{
	$https = (string) ($_SERVER['HTTPS'] ?? '');
	if ($https !== '' && strtolower($https) !== 'off') {
		return 'https';
	}

	$forwardedProto = strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''));
	if ($forwardedProto === 'https') {
		return 'https';
	}

	return ((string) ($_SERVER['SERVER_PORT'] ?? '') === '443') ? 'https' : 'http';
}

function blog_self_url(): string
{
	$scheme = request_scheme();
	$host   = (string) ($_SERVER['HTTP_HOST'] ?? 'example.com');
	// In multi-tenant mode the blog word arrives via $_GET['blog']
	$raw = trim((string) ($_GET['blog'] ?? ''));
	if ($raw !== '') {
		$word = preg_replace('/[^A-Za-z0-9_-]/', '', $raw) ?? '';
		$word = strtolower(mb_substr($word, 0, 24));
		if ($word !== '') {
			return $scheme . '://' . $host . '/blog/' . rawurlencode($word);
		}
	}
	// Fallback: strip query string from current URI
	$uri  = (string) ($_SERVER['REQUEST_URI'] ?? '/');
	$path = (string) (parse_url($uri, PHP_URL_PATH) ?? '/');
	return $scheme . '://' . $host . $path;
}


function blog_path_preview_url(string $blogWord): string
{
	$scheme = request_scheme();
	$host   = (string) ($_SERVER['HTTP_HOST'] ?? 'example.com');
	$word   = normalize_blog_word($blogWord);
	return $scheme . '://' . $host . '/blog/' . rawurlencode(strtolower($word));
}

function load_settings(): array
{
	$default = [
		'site_name'    => 'fbo',
		'hero_subtitle' => '',
	];

	$path = settings_path();
	if (!is_file($path)) {
		return $default;
	}

	$decoded = json_decode((string) file_get_contents($path), true);
	if (!is_array($decoded)) {
		return $default;
	}

	$siteName = normalize_blog_word((string) ($decoded['site_name'] ?? $default['site_name']));
	$subtitle = trim((string) ($decoded['hero_subtitle'] ?? $default['hero_subtitle']));

	return [
		'site_name'    => $siteName,
		'hero_subtitle' => mb_substr($subtitle, 0, 180),
	];
}

function save_settings(array $settings): void
{
	$payload = [
		'site_name'    => normalize_blog_word((string) ($settings['site_name'] ?? 'fbo')),
		'hero_subtitle' => mb_substr(trim((string) ($settings['hero_subtitle'] ?? '')), 0, 180),
	];

	file_put_contents(settings_path(), json_encode($payload, JSON_UNESCAPED_SLASHES));
}

function onboarding_required(): bool
{
	if (!empty($_SESSION[OTP_RESET_SESSION_KEY])) {
		return true;
	}
	return !is_file(settings_path()) || !is_file(auth_path());
}

function clear_media_files(): void
{
	$mediaPath = blog_root() . '/media';
	if (!is_dir($mediaPath)) {
		return;
	}

	$files = scandir($mediaPath);
	if (!is_array($files)) {
		return;
	}

	foreach ($files as $file) {
		if ($file === '.' || $file === '..') {
			continue;
		}
		$target = $mediaPath . '/' . $file;
		if (is_file($target)) {
			@unlink($target);
		}
	}
}

function save_password_hash(string $password): void
{
	$hash = password_hash($password, PASSWORD_BCRYPT);
	file_put_contents(auth_path(), json_encode(['password_hash' => $hash], JSON_UNESCAPED_SLASHES));
}

function is_valid_password_hash(string $hash): bool
{
	$info = password_get_info($hash);
	return is_array($info) && ((int) ($info['algo'] ?? 0)) !== 0;
}

function load_password_hash(): string
{
	$path = auth_path();
	if (!is_file($path)) {
		return '';
	}

	$decoded = json_decode((string) file_get_contents($path), true);
	if (!is_array($decoded)) {
		return '';
	}

	$hash = (string) ($decoded['password_hash'] ?? '');
	return is_valid_password_hash($hash) ? $hash : '';
}

function load_otp(): ?array
{
	$path = otp_path();
	if (!is_file($path)) {
		return null;
	}

	$decoded = json_decode((string) file_get_contents($path), true);
	if (!is_array($decoded)) {
		return null;
	}

	if ((int) ($decoded['expires'] ?? 0) < time()) {
		@unlink($path);
		return null;
	}

	return $decoded;
}

function normalize_post_entry(array $item): ?array
{
	$id        = trim((string) ($item['id'] ?? ''));
	$timestamp = (int) ($item['timestamp'] ?? 0);
	$type      = trim((string) ($item['type'] ?? 'text'));
	$pinned    = !empty($item['pinned']);

	if ($id === '' || $timestamp <= 0 || !in_array($type, ['text', 'image', 'video'], true)) {
		return null;
	}

	if ($type === 'text') {
		$text = trim((string) ($item['text'] ?? ''));
		if ($text === '') {
			return null;
		}

		return [
			'id'        => $id,
			'type'      => 'text',
			'text'      => mb_substr($text, 0, MAX_TEXT_POST_LENGTH),
			'pinned'    => $pinned,
			'timestamp' => $timestamp,
		];
	}

	$path = trim((string) ($item['path'] ?? ''));
	if ($path === '') {
		return null;
	}

	return [
		'id'        => $id,
		'type'      => $type,
		'path'      => $path,
		'pinned'    => $pinned,
		'timestamp' => $timestamp,
	];
}

function resolve_local_media_path_for_delete(string $relativePath): ?string
{
	$relativePath = trim($relativePath);
	if ($relativePath === '' || !str_starts_with($relativePath, 'media/')) {
		return null;
	}

	$target    = blog_root() . '/' . ltrim($relativePath, '/');
	$mediaRoot = realpath(blog_root() . '/media');
	$realTarget = realpath($target);

	if ($mediaRoot === false || $realTarget === false) {
		return null;
	}

	if (!str_starts_with($realTarget, $mediaRoot . DIRECTORY_SEPARATOR)) {
		return null;
	}

	if (!is_file($realTarget)) {
		return null;
	}

	return $realTarget;
}

function load_posts(): array
{
	$path = posts_path();
	if (!is_file($path)) {
		return [];
	}

	$decoded = json_decode((string) file_get_contents($path), true);
	if (!is_array($decoded)) {
		return [];
	}

	$items  = isset($decoded['items']) && is_array($decoded['items']) ? $decoded['items'] : (array_is_list($decoded) ? $decoded : []);
	$result = [];
	foreach ($items as $item) {
		if (!is_array($item)) {
			continue;
		}
		$normalized = normalize_post_entry($item);
		if ($normalized !== null) {
			$result[] = $normalized;
		}
	}

	usort($result, static function (array $a, array $b): int {
		$pinnedCompare = ((int) !empty($b['pinned'])) <=> ((int) !empty($a['pinned']));
		if ($pinnedCompare !== 0) {
			return $pinnedCompare;
		}
		return ((int) ($b['timestamp'] ?? 0)) <=> ((int) ($a['timestamp'] ?? 0));
	});
	return $result;
}

function save_posts(array $posts): void
{
	$payload = ['items' => array_values($posts)];
	file_put_contents(posts_path(), json_encode($payload, JSON_UNESCAPED_SLASHES));
}

function linkify_text_post_content(string $text): string
{
	$escaped = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
	$linked  = preg_replace_callback(
		'~((?:https?://|www\.)[^\s<]+)~iu',
		static function (array $matches): string {
			$display = (string) ($matches[1] ?? '');
			if ($display === '') {
				return '';
			}

			$trimmedDisplay = rtrim($display, '.,!?;:)]}');
			$suffix         = substr($display, strlen($trimmedDisplay));
			if ($trimmedDisplay === '') {
				return $display;
			}

			$hrefRaw = html_entity_decode($trimmedDisplay, ENT_QUOTES, 'UTF-8');
			if (!preg_match('~^https?://~i', $hrefRaw)) {
				$hrefRaw = 'https://' . $hrefRaw;
			}

			$href = htmlspecialchars($hrefRaw, ENT_QUOTES, 'UTF-8');
			return '<a class="text-link" href="' . $href . '" target="_blank" rel="noopener noreferrer">' . $trimmedDisplay . '</a>' . $suffix;
		},
		$escaped
	);

	if (!is_string($linked)) {
		return nl2br($escaped);
	}

	return nl2br($linked);
}

function set_flash_message(string $message): void
{
	$_SESSION[FLASH_MESSAGE_SESSION_KEY] = $message;
}

function pop_flash_message(): string
{
	$message = (string) ($_SESSION[FLASH_MESSAGE_SESSION_KEY] ?? '');
	unset($_SESSION[FLASH_MESSAGE_SESSION_KEY]);
	return $message;
}

function pop_otp_display(): string
{
	$otp = (string) ($_SESSION[OTP_DISPLAY_SESSION_KEY] ?? '');
	unset($_SESSION[OTP_DISPLAY_SESSION_KEY]);
	return $otp;
}

// ── Determine OTP-reset mode before onboarding check ─────────────────────
$isOtpReset      = !empty($_SESSION[OTP_RESET_SESSION_KEY]);
$onboardingError = '';
$flashMessage    = pop_flash_message();
$otpDisplay      = pop_otp_display();

// ── OTP generation ────────────────────────────────────────────────────────
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && isset($_POST['generate_otp'])) {
	$otp  = strtoupper(bin2hex(random_bytes(4))); // 8 hex chars
	$hash = password_hash($otp, PASSWORD_BCRYPT);
	file_put_contents(
		otp_path(),
		json_encode(['hash' => $hash, 'expires' => time() + 900], JSON_UNESCAPED_SLASHES)
	);
	$_SESSION[OTP_DISPLAY_SESSION_KEY] = $otp;
	header('Location: ?edit=1');
	exit;
}

// ── OTP login ─────────────────────────────────────────────────────────────
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && isset($_POST['otp_login'])) {
	$inputOtp = strtoupper(trim((string) ($_POST['otp_password'] ?? '')));
	$otpData  = load_otp();
	if ($otpData !== null && $inputOtp !== '' && password_verify($inputOtp, (string) ($otpData['hash'] ?? ''))) {
		@unlink(otp_path());
		unset($_SESSION[ADMIN_SESSION_KEY]);
		$_SESSION[OTP_RESET_SESSION_KEY] = true;
		header('Location: ' . blog_self_url());
		exit;
	}
	$otpLoginError = 'Invalid or expired one-time password.';
}

if (onboarding_required()) {
	if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && isset($_POST['complete_onboarding'])) {
		$password = (string) ($_POST['admin_password'] ?? '');
		if (mb_strlen($password) < 6) {
			$onboardingError = 'Password must be at least 6 characters.';
		} else {
			save_password_hash($password);
			unset($_SESSION[OTP_RESET_SESSION_KEY]);
			$_SESSION[ADMIN_SESSION_KEY] = true;
			header('Location: ' . blog_self_url());
			exit;
		}
	}

	if (!$isOtpReset) {
		header('Location: /');
		exit;
	}
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
		<title>Reset Password</title>
		<link rel="stylesheet" href="<?= local_asset_url('assets/css/styles.css') ?>">
		<link rel="stylesheet" href="<?= local_asset_url('assets/css/admin.css') ?>">
		<link rel="stylesheet" href="<?= local_asset_url('assets/css/onboarding.css') ?>">
	</head>
	<body class="onboarding-page">
		<main class="onboarding-wrap">
			<section class="onboarding-card">
				<p class="subtitle-line onboarding-lead">Set a new admin password. Posts and media stay untouched.</p>
				<?php if ($onboardingError !== ''): ?>
					<div class="subtitle-line onboarding-error"><?= htmlspecialchars($onboardingError, ENT_QUOTES, 'UTF-8') ?></div>
				<?php endif; ?>
				<form method="post" class="upload-panel onboarding-form">
					<input class="upload-auth-input" type="password" name="admin_password" maxlength="120" placeholder="New password (min 6 chars)" required>
					<div class="hero-actions">
						<button type="submit" name="complete_onboarding" value="1" class="ui-btn">Set new password</button>
					</div>
				</form>
			</section>
		</main>
	</body>
	</html><?php
	exit;
}

$settings       = load_settings();
$siteName       = (string) ($settings['site_name'] ?? 'fbo');
$siteNameDisplay = strtoupper($siteName);
$heroSubtitle   = (string) ($settings['hero_subtitle'] ?? '');
$passwordHash   = load_password_hash();

$view = isset($_GET['view']) && in_array($_GET['view'], ['grid', 'single'], true)
	? $_GET['view']
	: (isset($_SERVER['HTTP_USER_AGENT']) && preg_match('/Mobile|Android|iPhone|iPad|iPod|Opera Mini|IEMobile|WPDesktop/i', $_SERVER['HTTP_USER_AGENT']) ? 'single' : 'grid');
$editMode    = isset($_GET['edit']) && $_GET['edit'] === '1';
$composeMode = isset($_GET['compose']) && $_GET['compose'] === '1';
if ($composeMode) {
	$view = 'grid';
}
$page             = max(1, (int) ($_GET['page'] ?? 1));
$fromPage         = max(1, (int) ($_GET['from_page'] ?? $page));
$requestedPostId  = trim((string) ($_GET['post_id'] ?? ''));
$showIntroAnimation = (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') && empty($_GET);
$adminAuthed      = !empty($_SESSION[ADMIN_SESSION_KEY]);
$authError        = '';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && isset($_POST['admin_logout'])) {
	unset($_SESSION[ADMIN_SESSION_KEY]);
	header('Location: ' . blog_self_url());
	exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && isset($_POST['admin_login_password'])) {
	$inputPassword    = (string) ($_POST['admin_login_password'] ?? '');
	$loginTarget      = (string) ($_POST['login_target'] ?? 'edit');
	$redirectAfterLogin = ($loginTarget === 'compose') ? '?compose=1' : '?edit=1';
	if ($passwordHash !== '' && password_verify($inputPassword, $passwordHash)) {
		$_SESSION[ADMIN_SESSION_KEY] = true;
		header('Location: ' . $redirectAfterLogin);
		exit;
	}
	$authError = 'Wrong password.';
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && isset($_POST['restart_onboarding']) && $adminAuthed) {
	@unlink(settings_path());
	@unlink(auth_path());
	unset($_SESSION[FLASH_MESSAGE_SESSION_KEY]);
	unset($_SESSION[ADMIN_SESSION_KEY]);
	session_regenerate_id(true);
	header('Location: ' . blog_self_url());
	exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && isset($_POST['save_settings']) && $adminAuthed) {
	save_settings([
		'site_name'    => (string) ($_POST['site_name'] ?? $siteName),
		'hero_subtitle' => (string) ($_POST['hero_subtitle'] ?? $heroSubtitle),
	]);
	set_flash_message('Settings saved.');
	header('Location: ?edit=1');
	exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && isset($_POST['create_text_post']) && $adminAuthed) {
	$text = mb_substr(trim((string) ($_POST['text_post_content'] ?? '')), 0, MAX_TEXT_POST_LENGTH);
	if ($text === '') {
		set_flash_message('Post is empty.');
		header('Location: ?compose=1');
		exit;
	}

	$posts = load_posts();
	array_unshift($posts, [
		'id'        => 'post_' . time() . '_' . bin2hex(random_bytes(3)),
		'type'      => 'text',
		'text'      => $text,
		'pinned'    => false,
		'timestamp' => time(),
	]);
	save_posts($posts);
	set_flash_message('Post created.');
	header('Location: ?compose=1');
	exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && isset($_POST['upload_media']) && $adminAuthed) {
	$files     = $_FILES['files'] ?? null;
	$saved     = 0;
	$failed    = 0;
	$newPosts  = [];
	$uploadDir = media_dir_path();
	$clientUploadTimestamp = (int) ($_POST['upload_client_epoch'] ?? 0);
	if ($clientUploadTimestamp > 1000000000000) {
		$clientUploadTimestamp = (int) floor($clientUploadTimestamp / 1000);
	}
	if ($clientUploadTimestamp <= 0) {
		$clientUploadTimestamp = time();
	}

	if (is_array($files) && isset($files['name'], $files['tmp_name'], $files['error']) && is_array($files['name'])) {
		$count = count($files['name']);
		for ($index = 0; $index < $count; $index++) {
			$name = (string) ($files['name'][$index] ?? '');
			$tmp  = (string) ($files['tmp_name'][$index] ?? '');
			$err  = (int) ($files['error'][$index] ?? UPLOAD_ERR_NO_FILE);
			$size = (int) ($files['size'][$index] ?? 0);

			if ($err !== UPLOAD_ERR_OK || $name === '' || $tmp === '') {
				$failed++;
				continue;
			}

			if ($size <= 0 || $size > MAX_UPLOAD_FILE_SIZE_BYTES) {
				$failed++;
				continue;
			}

			$extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));
			if (!in_array($extension, MEDIA_EXTENSIONS, true)) {
				$failed++;
				continue;
			}

			$targetName = sprintf('%s_%s.%s', date('Ymd_His'), bin2hex(random_bytes(4)), $extension);
			$targetPath = $uploadDir . '/' . $targetName;

			if (!move_uploaded_file($tmp, $targetPath)) {
				$failed++;
				continue;
			}

			@touch($targetPath, $clientUploadTimestamp, $clientUploadTimestamp);

			$type       = in_array($extension, ['mp4', 'mov', 'webm', 'm4v'], true) ? 'video' : 'image';
			$newPosts[] = [
				'id'        => 'media_' . $clientUploadTimestamp . '_' . bin2hex(random_bytes(3)),
				'type'      => $type,
				'path'      => 'media/' . $targetName,
				'pinned'    => false,
				'timestamp' => $clientUploadTimestamp,
			];
			$saved++;
		}
	}

	if ($saved > 0) {
		$posts = load_posts();
		save_posts(array_merge($newPosts, $posts));
	}

	$message = 'Uploaded: ' . $saved;
	if ($failed > 0) {
		$message .= ' | Failed: ' . $failed . ' (limit: 100MB per file)';
	}
	set_flash_message($message);
	header('Location: ?compose=1');
	exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && (isset($_POST['delete_page_posts']) || isset($_POST['delete_page_media'])) && $adminAuthed) {
	$ids            = $_POST['page_post_ids'] ?? [];
	$closeAfterSave = isset($_POST['close_after_save']) && (string) $_POST['close_after_save'] === '1';
	$selectedIds    = [];
	if (is_array($ids)) {
		foreach ($ids as $id) {
			$id = trim((string) $id);
			if ($id !== '') {
				$selectedIds[$id] = true;
			}
		}
	}

	$deleted   = 0;
	$failed    = 0;
	$posts     = load_posts();
	$nextPosts = [];

	foreach ($posts as $post) {
		$postId = (string) ($post['id'] ?? '');
		if ($postId === '' || !isset($selectedIds[$postId])) {
			$nextPosts[] = $post;
			continue;
		}

		$postType = (string) ($post['type'] ?? 'text');
		if (in_array($postType, ['image', 'video'], true)) {
			$target = resolve_local_media_path_for_delete((string) ($post['path'] ?? ''));
			if ($target === null || !@unlink($target)) {
				$failed++;
				$nextPosts[] = $post;
				continue;
			}
		}

		$deleted++;
	}

	save_posts($nextPosts);

	$message = 'Deleted: ' . $deleted;
	if ($failed > 0) {
		$message .= ' | Failed: ' . $failed;
	}
	set_flash_message($message);

	if ($closeAfterSave) {
		header('Location: ?view=' . rawurlencode($view) . '&page=' . rawurlencode((string) $page));
		exit;
	}

	header('Location: ?compose=1&view=' . rawurlencode($view) . '&page=' . rawurlencode((string) $page));
	exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && isset($_POST['delete_post']) && $adminAuthed) {
	$postId          = trim((string) ($_POST['post_id'] ?? ''));
	$posts           = load_posts();
	$deleteMediaPath = '';
	foreach ($posts as $post) {
		if ((string) ($post['id'] ?? '') !== $postId) {
			continue;
		}
		if (in_array((string) ($post['type'] ?? ''), ['image', 'video'], true)) {
			$deleteMediaPath = (string) ($post['path'] ?? '');
		}
		break;
	}
	$posts = array_values(array_filter($posts, static fn(array $p): bool => (string) ($p['id'] ?? '') !== $postId));
	save_posts($posts);
	if ($deleteMediaPath !== '') {
		$target = resolve_local_media_path_for_delete($deleteMediaPath);
		if ($target !== null) {
			@unlink($target);
		}
	}
	set_flash_message('Post deleted.');
	header('Location: ?compose=1');
	exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && isset($_POST['pin_post']) && $adminAuthed) {
	$postId  = trim((string) ($_POST['post_id'] ?? ''));
	$posts   = load_posts();
	$updated = false;
	foreach ($posts as &$post) {
		if ((string) ($post['id'] ?? '') !== $postId) {
			continue;
		}
		$post['pinned'] = true;
		$updated        = true;
		break;
	}
	unset($post);
	if ($updated) {
		save_posts($posts);
		set_flash_message('Post pinned.');
	}
	header('Location: ?compose=1');
	exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && isset($_POST['unpin_post']) && $adminAuthed) {
	$postId  = trim((string) ($_POST['post_id'] ?? ''));
	$posts   = load_posts();
	$updated = false;
	foreach ($posts as &$post) {
		if ((string) ($post['id'] ?? '') !== $postId) {
			continue;
		}
		$post['pinned'] = false;
		$updated        = true;
		break;
	}
	unset($post);
	if ($updated) {
		save_posts($posts);
		set_flash_message('Post unpinned.');
	}
	header('Location: ?compose=1');
	exit;
}

$posts          = load_posts();
$singlePostMode = false;
if ($requestedPostId !== '' && !$composeMode) {
	foreach ($posts as $post) {
		if ((string) ($post['id'] ?? '') !== $requestedPostId) {
			continue;
		}

		$singlePostMode = true;
		$view           = 'single';
		$posts          = [$post];
		$page           = 1;
		break;
	}
}
$perPage      = $view === 'grid' ? 180 : 60;
$totalItems   = count($posts);
$totalPages   = max(1, (int) ceil($totalItems / max(1, $perPage)));
$page         = min($page, $totalPages);
$postsOnPage  = array_slice($posts, ($page - 1) * $perPage, $perPage);
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
	<title><?= htmlspecialchars($siteNameDisplay, ENT_QUOTES, 'UTF-8') ?></title>
	<link rel="stylesheet" href="<?= local_asset_url('assets/css/styles.css') ?>">
	<link rel="stylesheet" href="<?= local_asset_url('assets/css/upload.css') ?>">
	<link rel="stylesheet" href="<?= local_asset_url('assets/css/audio-player.css') ?>">
	<?php if ($editMode || $composeMode): ?>
	<link rel="stylesheet" href="<?= local_asset_url('assets/css/admin.css') ?>">
	<?php endif; ?>
</head>
<body class="<?= $showIntroAnimation ? 'intro-loading' : '' ?>" data-max-text-post-length="<?= MAX_TEXT_POST_LENGTH ?>" data-compose-mode="<?= $composeMode ? '1' : '0' ?>">
	<div class="intro-overlay" id="introOverlay" aria-hidden="true">
		<div class="intro-fbo" id="introFboText">F</div>
	</div>
	<?php include __DIR__ . '/snippets/header.php'; ?>

	<nav class="topbar<?= $composeMode ? ' topbar-compose' : '' ?>">
		<div class="topbar-left">
			<?php if (!$composeMode): ?>
				   <a href="?view=grid" class="ui-btn <?= $view === 'grid' ? 'active' : '' ?>" id="gridViewBtn">grid</a>
				   <a href="?view=single" class="ui-btn <?= $view === 'single' ? 'active' : '' ?>" id="listViewBtn">list</a>
			<?php endif; ?>
			<button type="button" class="ui-btn" id="themeToggle">dark mode</button>
		</div>
		<?php if (!$composeMode && !$singlePostMode): ?>
			<div class="topbar-right">
				<span class="meta" id="pageInfoLabel" aria-haspopup="listbox" aria-expanded="false"><?= count($postsOnPage) ?> / <?= $totalItems ?> posts (page <?= $page ?>/<?= $totalPages ?>)</span>
				<select id="pageJumpSelect" class="page-jump" aria-label="Jump to page">
					<?php for ($p = 1; $p <= $totalPages; $p++): ?>
						<option value="<?= $p ?>" <?= $p === $page ? 'selected' : '' ?>>page <?= $p ?></option>
					<?php endfor; ?>
				</select>
			</div>
		<?php else: ?>
			<div class="topbar-right">
				<button type="button" class="ui-btn" id="saveCloseUploadBtn" data-close-url="?view=<?= $view ?>&page=<?= $page ?>">Save &amp; close</button>
			</div>
		<?php endif; ?>
	</nav>

	<?php if (!$postsOnPage): ?>
		<main class="archive <?= $view ?>">
			<article class="item">
				<div class="text-post-body">No posts yet. Use compose mode to create your first text post.</div>
				<div class="stamp">Placeholder content</div>
			</article>
		</main>
	<?php else: ?>
		<main class="archive <?= $view ?><?= $singlePostMode ? ' single-post-mode' : '' ?>">
			<?php foreach ($postsOnPage as $post): ?>
				<?php $isPinned = !empty($post['pinned']); ?>
				<article class="item<?= $isPinned ? ' is-pinned' : '' ?>" data-post-id="<?= htmlspecialchars((string) $post['id'], ENT_QUOTES, 'UTF-8') ?>">
					<?php if ($adminAuthed && $composeMode): ?>
						<form method="post" class="pin-form">
							<input type="hidden" name="post_id" value="<?= htmlspecialchars((string) $post['id'], ENT_QUOTES, 'UTF-8') ?>">
							<button type="submit" name="<?= $isPinned ? 'unpin_post' : 'pin_post' ?>" value="1" class="ui-btn delete-btn pin-btn"><?= $isPinned ? 'Unpin' : 'Pin' ?></button>
						</form>
						<button type="button" class="ui-btn delete-btn mark-delete-btn" data-post-id="<?= htmlspecialchars((string) $post['id'], ENT_QUOTES, 'UTF-8') ?>">Delete</button>
					<?php endif; ?>
					<?php if ((string) ($post['type'] ?? 'text') === 'text'): ?>
						<div class="text-post-body"><?= linkify_text_post_content((string) ($post['text'] ?? '')) ?></div>
					<?php else: ?>
						<?php $mediaUrl = htmlspecialchars(asset_url((string) ($post['path'] ?? '')), ENT_QUOTES, 'UTF-8'); ?>
						<div class="media-wrap">
							<?php if ((string) ($post['type'] ?? '') === 'video'): ?>
								<video src="<?= $mediaUrl ?>" preload="metadata" controls playsinline></video>
								<?php if ($view === 'grid'): ?>
									<div class="grid-video-overlay" aria-hidden="true">
										<svg class="grid-play-icon" width="36px" height="36px" stroke-width="1" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" color="#ffffff"><path d="M6.90588 4.53682C6.50592 4.2998 6 4.58808 6 5.05299V18.947C6 19.4119 6.50592 19.7002 6.90588 19.4632L18.629 12.5162C19.0211 12.2838 19.0211 11.7162 18.629 11.4838L6.90588 4.53682Z" stroke="#ffffff" stroke-width="1" stroke-linecap="round" stroke-linejoin="round"></path></svg>
									</div>
								<?php endif; ?>
							<?php else: ?>
								<img src="<?= $mediaUrl ?>" alt="Uploaded media" loading="lazy">
							<?php endif; ?>
						</div>
					<?php endif; ?>
					<?php if ($isPinned): ?>
						<div class="pinned-badge<?= ($composeMode && $adminAuthed) ? ' with-delete' : '' ?>">Pinned</div>
					<?php endif; ?>
					<div class="stamp" data-ts="<?= (int) ($post['timestamp'] ?? 0) ?>"><?= date('d.m.Y H:i', (int) ($post['timestamp'] ?? 0)) ?></div>
				</article>
			<?php endforeach; ?>
		</main>
	<?php endif; ?>

	<nav class="topbar topbar-pagination">
		<?php if ($singlePostMode): ?>
			<a class="ui-btn" href="?view=grid&page=<?= $fromPage ?>">back to grid</a>
		<?php endif; ?>
		<?php if ($page > 1): ?>
			<a class="ui-btn" href="?view=<?= $view ?>&page=<?= $page - 1 ?><?= $editMode ? '&edit=1' : '' ?><?= $composeMode ? '&compose=1' : '' ?>">newer</a>
		<?php endif; ?>
		<?php if ($page < $totalPages): ?>
			<a class="ui-btn" href="?view=<?= $view ?>&page=<?= $page + 1 ?><?= $editMode ? '&edit=1' : '' ?><?= $composeMode ? '&compose=1' : '' ?>">older</a>
		<?php endif; ?>
	</nav>

	<script src="<?= local_asset_url('assets/js/script.js') ?>" defer></script>
	<script src="<?= local_asset_url('assets/js/blog.js') ?>" defer></script>
	<script src="<?= local_asset_url('assets/js/instant-capture.js') ?>" defer></script>
	<script src="<?= local_asset_url('assets/js/audio-player.js') ?>" defer></script>
</body>
<script>
if (!window.location.search.match(/[?&]view=/)) {
	if (window.matchMedia('(max-width: 700px)').matches) {
		var listBtn = document.getElementById('listViewBtn');
		if (listBtn) listBtn.click();
	}
}
</script>
</html>