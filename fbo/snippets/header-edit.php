<?php if ($adminAuthed): ?>
	<form method="post" class="subtitle-form">
		<input id="editBlogWord" type="text" class="upload-auth-input" name="site_name" maxlength="<?= BLOG_WORD_MAX_LENGTH ?>" value="<?= htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8') ?>" placeholder="Blog word (title)">
		<input type="text" class="upload-auth-input" name="hero_subtitle" maxlength="180" value="<?= htmlspecialchars($heroSubtitle, ENT_QUOTES, 'UTF-8') ?>" placeholder="Subtitle">
		<button type="submit" name="save_settings" value="1" class="ui-btn">Save</button>
	</form>
	<div class="subtitle-line">Title preview: <span id="editTitlePreview"><?= htmlspecialchars(strtoupper($siteName), ENT_QUOTES, 'UTF-8') ?></span></div>
	<div class="subtitle-line">Path preview: <span id="editUrlPreview"><?= htmlspecialchars(blog_path_preview_url($siteName), ENT_QUOTES, 'UTF-8') ?></span></div>
	<div class="hero-actions">
		<a href="?<?= $blogQ ?>download_backup=1" class="ui-btn">Download your whole blog! All imaes and data as a .zip file.</a>
	</div>

	<form method="post" class="upload-panel" id="deleteBlogForm">
		<input type="hidden" name="delete_blog" value="1">
		<input type="hidden" name="delete_blog_confirm_compose" id="deleteBlogConfirmCompose" value="0">
		<input type="hidden" name="delete_blog_confirm_irreversible" id="deleteBlogConfirmIrreversible" value="0">
		<div class="subtitle-line danger-note">Danger zone: permanently delete this blog, all media files, and all backend data.</div>
		<input type="password" class="upload-auth-input" name="delete_blog_password" maxlength="120" placeholder="Type your current admin password" autocomplete="off" required>
		<div class="hero-actions">
			<button type="submit" class="ui-btn danger-btn">Delete blog permanently</button>
		</div>
	</form>
	<div class="subtitle-line danger-note">This cannot be undone and cannot be restored.</div>
	<?php if ($flashMessage !== ''): ?>
		<div class="subtitle-line"><?= htmlspecialchars($flashMessage, ENT_QUOTES, 'UTF-8') ?></div>
	<?php endif; ?>

	<form method="post" enctype="multipart/form-data" class="upload-panel" id="inlineUploadForm">
		<?php if (!empty($uploadMessage)): ?>
			<div class="subtitle-line"><?= htmlspecialchars($uploadMessage, ENT_QUOTES, 'UTF-8') ?></div>
		<?php endif; ?>
		<input type="file" id="inlineUploadFiles" class="upload-auth-input" name="files[]" accept="image/*,video/*,audio/*,.mp3,.wav,.flac" multiple required>
		<input type="hidden" id="uploadClientEpoch" name="upload_client_epoch" value="">
		<div class="hero-actions">
			<button type="submit" name="upload_media" value="1" class="ui-btn ui-btn-strong">Upload now</button>
			<button type="button" class="ui-btn" id="cancelInlineUpload">Cancel selection</button>
		</div>
		<div id="inlineUploadPreview" class="upload-preview"></div>
		<div id="inlineUploadEmpty" class="upload-empty">No files selected yet.</div>
		<div class="upload-note">Files are previewed as post cards before upload (max <?= MAX_UPLOAD_FILES_PER_REQUEST ?> files, <?= (int) (MAX_UPLOAD_FILE_SIZE_BYTES / 1048576) ?>MB each).</div>
	</form>

	<form method="post" class="upload-panel" id="textPostForm">
		<textarea id="textPostContent" name="text_post_content" maxlength="<?= MAX_TEXT_POST_LENGTH ?>" placeholder="Write text post (max <?= MAX_TEXT_POST_LENGTH ?> chars)" required></textarea>
		<input type="hidden" id="textPostClientEpoch" name="text_post_client_epoch" value="">
		<div class="hero-actions">
			<button type="submit" name="create_text_post" value="1" class="ui-btn">Post text</button>
			<span class="upload-note" id="textPostCount">0 / <?= MAX_TEXT_POST_LENGTH ?></span>
		</div>
	</form>

	<div class="hero-actions">
		<button type="button" class="ui-btn ui-btn-strong" id="saveCloseUploadBtn" data-close-url="?<?= $blogQ ?>view=<?= $view ?>&page=<?= $page ?>">Save &amp; close</button>
	</div>
	<form method="post" class="upload-panel pending-delete-actions" id="pendingDeleteForm">
		<input type="hidden" name="delete_page_media" value="1">
		<input type="hidden" name="close_after_save" id="closeAfterSaveInput" value="0">
		<div class="hero-actions">
			<button type="submit" class="ui-btn ui-btn-strong" id="saveDeleteBtn">Save delete</button>
			<button type="button" class="ui-btn" id="cancelDeleteBtn">Cancel delete</button>
		</div>
		<div class="upload-note" id="pendingDeleteCount">0 selected for delete.</div>
		<div id="pendingDeleteInputs"></div>
	</form>

<?php else: ?>
	<?php if (!empty($authError)): ?>
		<div class="subtitle-line auth-error"><?= htmlspecialchars($authError, ENT_QUOTES, 'UTF-8') ?></div>
	<?php endif; ?>

	<?php if ($otpDisplay !== ''): ?>
		<div class="otp-display">
			<div class="otp-display-label">Your one-time password (valid 15 min):</div>
			<div class="otp-display-code" id="otpCode"><?= htmlspecialchars($otpDisplay, ENT_QUOTES, 'UTF-8') ?></div>
			<div class="otp-display-hint">Copy this code — it will not be shown again.</div>
		</div>
	<?php endif; ?>

	<?php
	$showOtpForm = isset($otpLoginError) || $otpDisplay !== '';
	?>

	<?php if ($showOtpForm): ?>
		<?php if (!empty($otpLoginError)): ?>
			<div class="subtitle-line auth-error"><?= htmlspecialchars($otpLoginError, ENT_QUOTES, 'UTF-8') ?></div>
		<?php endif; ?>
		<form method="post" class="subtitle-form">
			<input type="password" class="upload-auth-input" name="otp_password" maxlength="32" placeholder="Enter one-time password" autocomplete="off" required>
			<button type="submit" name="otp_login" value="1" class="ui-btn ui-btn-strong">Continue</button>
			<a href="?<?= $blogQ ?>edit=1" class="ui-btn">Back</a>
		</form>
	<?php else: ?>
		<form method="post" class="subtitle-form">
			<input type="hidden" name="login_target" value="edit">
			<input type="password" class="upload-auth-input" name="admin_login_password" maxlength="120" placeholder="Password" required>
			<button type="submit" class="ui-btn ui-btn-strong">Unlock edit</button>
		</form>
		<form method="post" class="subtitle-form">
			<button type="submit" name="generate_otp" value="1" class="ui-btn">Forgot password</button>
		</form>
	<?php endif; ?>
<?php endif; ?>