<?php if ($adminAuthed): ?>
    <div class="subtitle-line">Post media and text to your blog in #composemode</div>

<form method="post" enctype="multipart/form-data" class="upload-panel" id="inlineUploadForm">
    <input type="file" id="inlineUploadFiles" class="upload-auth-input" name="files[]" accept="image/*,video/*,audio/*,.mp3,.wav,.flac" multiple required>
    <input type="hidden" id="uploadClientEpoch" name="upload_client_epoch" value="">
    <div class="hero-actions">
        <button type="button" class="ui-btn" id="cancelInlineUpload">Cancel selection</button>
    </div>
    <div class="hero-actions hero-actions-primary">
        <button type="submit" name="upload_media" value="1" class="ui-btn ui-btn-strong">Post media</button>
    </div>
    <div id="inlineUploadPreview" class="upload-preview"></div>
    <div id="inlineUploadEmpty" class="upload-empty">No files selected yet.</div>
    <div class="upload-note">Files are previewed as post cards before upload (max <?= MAX_UPLOAD_FILES_PER_REQUEST ?> files, <?= (int)(MAX_UPLOAD_FILE_SIZE_BYTES / 1048576) ?>MB each).</div>
</form>

<form method="post" class="upload-panel" id="textPostForm">
    <textarea id="textPostContent" name="text_post_content" maxlength="<?= MAX_TEXT_POST_LENGTH ?>"
        placeholder="Write a text post (max <?= MAX_TEXT_POST_LENGTH ?> chars)" required></textarea>
    <input type="hidden" id="textPostClientEpoch" name="text_post_client_epoch" value="">
    <div class="hero-actions">
        <button type="submit" name="create_text_post" value="1" class="ui-btn ui-btn-strong">Post text</button>
        <span class="upload-note upload-note-right" id="textPostCount">0 / <?= MAX_TEXT_POST_LENGTH ?></span>
    </div>
</form>

<form method="post" class="upload-panel pending-delete-actions" id="pendingDeleteForm">
    <input type="hidden" name="delete_page_posts" value="1">
    <input type="hidden" name="close_after_save" id="closeAfterSaveInput" value="0">
    <div class="hero-actions">
        <button type="submit" class="ui-btn ui-btn-strong" id="saveDeleteBtn">Save delete</button>
        <button type="button" class="ui-btn" id="cancelDeleteBtn">Cancel delete</button>
    </div>
    <div class="upload-note" id="pendingDeleteCount">0 selected for delete.</div>
    <div id="pendingDeleteInputs"></div>
</form>

<div class="hero-actions">
    <button type="button" class="ui-btn ui-btn-strong" id="saveCloseUploadBtn"
        data-close-url="?<?= $blogQ ?>view=<?= $view ?>&page=<?= $page ?>">Close</button>
</div>
<?php else: ?>
    <?php if ($authError !== ''): ?>
        <div class="subtitle-line auth-error"><?= htmlspecialchars($authError, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>
    <?php if ($flashMessage !== ''): ?>
        <div class="subtitle-line"><?= htmlspecialchars($flashMessage, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>

    <?php $hasActiveOtp = (function_exists('load_otp') && load_otp() !== null); ?>

    <?php if ($hasActiveOtp): ?>
        <?php if (!empty($otpLoginError)): ?>
            <div class="subtitle-line auth-error"><?= htmlspecialchars($otpLoginError, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>
        <div class="subtitle-line">Einmalpasswort aus deiner E-Mail eingeben.</div>
        <form method="post" class="subtitle-form">
            <input type="text" class="upload-auth-input" name="otp_password" maxlength="32"
                placeholder="Einmalpasswort" autocomplete="off" spellcheck="false" inputmode="text" required>
            <button type="submit" name="otp_login" value="1" class="ui-btn ui-btn-strong">Weiter</button>
            <a href="?<?= $blogQ ?>compose=1" class="ui-btn">Zurück</a>
        </form>
    <?php else: ?>
        <div class="subtitle-line">Enter admin password once to unlock compose and edit.</div>
        <form method="post" class="subtitle-form">
            <input type="hidden" name="login_target" value="compose">
            <input type="password" class="upload-auth-input" name="admin_login_password" maxlength="120" placeholder="Password" required>
            <button type="submit" class="ui-btn ui-btn-strong">Unlock</button>
        </form>
        <?php if ($recoveryEmail !== ''): ?>
            <form method="post" class="subtitle-form">
                <button type="submit" name="generate_otp" value="1" class="ui-btn">Passwort vergessen</button>
            </form>
        <?php else: ?>
            <div class="subtitle-line"><a href="?<?= $blogQ ?>edit=1">Im Edit-Bereich einloggen</a> um eine Wiederherstellungs-E-Mail zu hinterlegen.</div>
        <?php endif; ?>
    <?php endif; ?>
<?php endif; ?>