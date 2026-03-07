<?php if ($adminAuthed): ?>
	<?php if ($flashMessage !== ''): ?>
		<div class="subtitle-line"><?= htmlspecialchars($flashMessage, ENT_QUOTES, 'UTF-8') ?></div>
	<?php endif; ?>

	<form method="post" enctype="multipart/form-data" class="upload-panel" id="inlineUploadForm">
		<input type="file" id="inlineUploadFiles" class="upload-auth-input" name="files[]" accept="image/*,video/*" multiple required>
		<input type="hidden" id="uploadClientEpoch" name="upload_client_epoch" value="">
		<div class="hero-actions">
			<button type="submit" name="upload_media" value="1" class="ui-btn">Upload media</button>
			<button type="button" class="ui-btn" id="cancelInlineUpload">Cancel selection</button>
		</div>
		<div id="inlineUploadPreview" class="upload-preview"></div>
		<div id="inlineUploadEmpty" class="upload-empty">No files selected yet.</div>
		<div class="upload-note">Files are previewed as post cards before upload (max 100MB each).</div>
	</form>

	<form method="post" class="upload-panel" id="textPostForm">
		<textarea id="textPostContent" name="text_post_content" maxlength="<?= MAX_TEXT_POST_LENGTH ?>" placeholder="Write a text post (max <?= MAX_TEXT_POST_LENGTH ?> chars)" required></textarea>
		<input type="hidden" id="textPostClientEpoch" name="text_post_client_epoch" value="">
		<div class="hero-actions">
			<button type="submit" name="create_text_post" value="1" class="ui-btn">Publish post</button>
			<span class="upload-note upload-note-right" id="textPostCount">0 / <?= MAX_TEXT_POST_LENGTH ?></span>
		</div>
	</form>

	<form method="post" class="upload-panel pending-delete-actions" id="pendingDeleteForm">
		<input type="hidden" name="delete_page_posts" value="1">
		<input type="hidden" name="close_after_save" id="closeAfterSaveInput" value="0">
		<div class="hero-actions">
			<button type="submit" class="ui-btn" id="saveDeleteBtn">Save delete</button>
			<button type="button" class="ui-btn" id="cancelDeleteBtn">Cancel delete</button>
		</div>
		<div class="upload-note" id="pendingDeleteCount">0 selected for delete.</div>
		<div id="pendingDeleteInputs"></div>
	</form>
<?php else: ?>
	<?php if ($authError !== ''): ?>
		<div class="subtitle-line auth-error"><?= htmlspecialchars($authError, ENT_QUOTES, 'UTF-8') ?></div>
	<?php endif; ?>

	<?php if ($otpDisplay !== ''): ?>
		<div class="otp-display">
			<div class="otp-display-label">Your one-time password (valid 15 min):</div>
			<div class="otp-display-code" id="otpCode"><?= htmlspecialchars($otpDisplay, ENT_QUOTES, 'UTF-8') ?></div>
			<div class="otp-display-hint">Copy this code — it will not be shown again.</div>
		</div>
	<?php endif; ?>

	<?php $showOtpForm = isset($otpLoginError) || $otpDisplay !== ''; ?>

	<?php if ($showOtpForm): ?>
		<?php if (!empty($otpLoginError)): ?>
			<div class="subtitle-line auth-error"><?= htmlspecialchars($otpLoginError, ENT_QUOTES, 'UTF-8') ?></div>
		<?php endif; ?>
		<form method="post" class="subtitle-form">
			<input type="password" class="upload-auth-input" name="otp_password" maxlength="32" placeholder="Enter one-time password" autocomplete="off" required>
			<button type="submit" name="otp_login" value="1" class="ui-btn ui-btn-strong">Continue</button>
			<a href="?compose=1" class="ui-btn">Back</a>
		</form>
	<?php else: ?>
		<div class="subtitle-line">Enter admin password once to unlock compose and edit.</div>
		<form method="post" class="subtitle-form">
			<input type="hidden" name="login_target" value="compose">
			<input type="password" class="upload-auth-input" name="admin_login_password" maxlength="120" placeholder="Password" required>
			<button type="submit" class="ui-btn">Unlock compose + edit</button>
		</form>
		<form method="post" class="subtitle-form">
			<button type="submit" name="generate_otp" value="1" class="ui-btn">Forgot password</button>
		</form>
	<?php endif; ?>
<?php endif; ?>