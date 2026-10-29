<?php
// Shared profile/settings screen. Expects $area ('admin' or 'user') and $active to be set by the caller.
$u = me();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (post('action') === 'remove_profile_photo') {
        $photoPath = $u['profile_photo'] ?? '';
        $photoParts = parse_url($photoPath);
        $isRemotePhoto = filter_var($photoPath, FILTER_VALIDATE_URL)
            && is_array($photoParts)
            && isset($photoParts['scheme'])
            && in_array(strtolower($photoParts['scheme']), ['http', 'https'], true);
        $isLocalPhoto = preg_match(
            '~^uploads/profile_photos/user_' . (int)$u['user_id'] . '_[a-f0-9]{10}\.(jpg|png|webp)$~',
            $photoPath
        );
        if ($photoPath === '') {
            flash('There is no profile photo to remove.', 'err');
        } elseif (!$isRemotePhoto && !$isLocalPhoto) {
            flash('Could not safely identify your uploaded profile photo.', 'err');
        } else {
            q('UPDATE users SET profile_photo=NULL WHERE user_id=?', [$u['user_id']]);
            $file = __DIR__ . '/../' . $photoPath;
            if ($isLocalPhoto && is_file($file) && !unlink($file)) {
                flash('The profile photo was removed from your account, but its file could not be deleted.', 'err');
            } else {
                flash('Profile photo removed.');
            }
        }
        redirect(($area === 'admin' ? 'admin' : 'user') . '/profile.php');
    }

    $name = post('full_name'); $mail = post('email'); $new = $_POST['new_password'] ?? '';
    $photoPath = $u['profile_photo'] ?? null;
    $oldPhotoPath = $photoPath;
    if ($name === '' || !filter_var($mail, FILTER_VALIDATE_EMAIL)) flash('Name and a valid email are required.', 'err');
    elseif (val('SELECT COUNT(*) FROM users WHERE email=? AND user_id<>?', [$mail, $u['user_id']])) flash('That email is already used by another account.', 'err');
    elseif ($new !== '' && strlen($new) < 6) flash('New password must be at least 6 characters.', 'err');
    elseif ($new !== '' && !password_verify($_POST['current_password'] ?? '', val('SELECT password_hash FROM users WHERE user_id=?', [$u['user_id']]))) flash('Current password is incorrect.', 'err');
    else {
        $photoUrl = trim(post('profile_photo_url'));
        $uploadError = (int)($_FILES['profile_photo']['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($photoUrl !== '' && $uploadError !== UPLOAD_ERR_NO_FILE) {
            flash('Choose either an image file or an image URL, not both.', 'err');
            redirect(($area === 'admin' ? 'admin' : 'user') . '/profile.php');
        }

        if ($photoUrl !== '') {
            $photoParts = parse_url($photoUrl);
            if (
                strlen($photoUrl) > 255
                || !filter_var($photoUrl, FILTER_VALIDATE_URL)
                || !is_array($photoParts)
                || !isset($photoParts['scheme'])
                || !in_array(strtolower($photoParts['scheme']), ['http', 'https'], true)
            ) {
                flash('Enter a valid HTTP or HTTPS image URL (up to 255 characters).', 'err');
                redirect(($area === 'admin' ? 'admin' : 'user') . '/profile.php');
            }
            $photoPath = $photoUrl;
        } elseif ($uploadError !== UPLOAD_ERR_NO_FILE) {
            if ($uploadError !== UPLOAD_ERR_OK) {
                flash($uploadError === UPLOAD_ERR_INI_SIZE || $uploadError === UPLOAD_ERR_FORM_SIZE
                    ? 'Profile picture exceeds the upload size limit.'
                    : 'The profile picture could not be uploaded. Please try again.', 'err');
                redirect(($area === 'admin' ? 'admin' : 'user') . '/profile.php');
            }

            $tmp = $_FILES['profile_photo']['tmp_name'];
            $size = is_uploaded_file($tmp) ? filesize($tmp) : false;
            $info = $size !== false ? getimagesize($tmp) : false;
            $mime = $info['mime'] ?? '';
            $exts = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
            if (!$info || !isset($exts[$mime]) || $size > 3 * 1024 * 1024) {
                flash('Profile picture must be JPG, PNG, or WEBP and no larger than 3 MB.', 'err');
                redirect(($area === 'admin' ? 'admin' : 'user') . '/profile.php');
            }

            $dir = __DIR__ . '/../uploads/profile_photos';
            if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
                flash('Could not create the profile picture folder.', 'err');
                redirect(($area === 'admin' ? 'admin' : 'user') . '/profile.php');
            }
            $file = 'user_' . (int)$u['user_id'] . '_' . bin2hex(random_bytes(5)) . '.' . $exts[$mime];
            if (!move_uploaded_file($tmp, $dir . '/' . $file)) {
                flash('Could not save the uploaded picture.', 'err');
                redirect(($area === 'admin' ? 'admin' : 'user') . '/profile.php');
            }
            $photoPath = 'uploads/profile_photos/' . $file;
        }

        q('UPDATE users SET full_name=?, email=?, phone=?, department=?, bio=?, profile_photo=? WHERE user_id=?',
            [$name, $mail, post('phone'), post('department'), post('bio'), $photoPath, $u['user_id']]);
        if ($new !== '') q('UPDATE users SET password_hash=? WHERE user_id=?', [password_hash($new, PASSWORD_DEFAULT), $u['user_id']]);
        if (
            $oldPhotoPath !== $photoPath
            && preg_match('~^uploads/profile_photos/user_' . (int)$u['user_id'] . '_[a-f0-9]{10}\.(jpg|png|webp)$~', (string)$oldPhotoPath)
        ) {
            $oldFile = __DIR__ . '/../' . $oldPhotoPath;
            if (is_file($oldFile) && !unlink($oldFile)) {
                flash('Profile saved, but the previous picture file could not be deleted.', 'err');
                redirect(($area === 'admin' ? 'admin' : 'user') . '/profile.php');
            }
        }
        flash('Profile saved.', 'ok');
    }
    redirect(($area === 'admin' ? 'admin' : 'user') . '/profile.php');
}
page_start($area === 'admin' ? 'Settings' : 'My Profile', $area, $active);
?>
<section class="card glass" style="max-width:760px">
  <div style="margin-bottom:18px" class="row"><div><?= user_avatar($u) ?></div><div><h3 style="margin:0"><?= e($u['full_name']) ?></h3><small>@<?= e($u['username']) ?> · <?= badge($u['role']) ?> · joined <?= dt($u['created_at']) ?></small></div></div>
  <form method="post" enctype="multipart/form-data"><?= csrf_field() ?>
    <div class="fg">
      <div><label>Full name</label><input name="full_name" required value="<?= e($u['full_name']) ?>"></div>
      <div><label>Email</label><input type="email" name="email" required value="<?= e($u['email']) ?>"></div>
      <div><label>Phone</label><input name="phone" value="<?= e($u['phone']) ?>"></div>
      <div><label>Department</label><input name="department" value="<?= e($u['department']) ?>"></div>
      <div class="full"><label>Upload profile picture (JPG, PNG, WEBP; max 3 MB)</label><input type="file" name="profile_photo" accept="image/jpeg,image/png,image/webp"><small>The original image is saved and center-cropped to fit the 34 × 34 circular avatar. The saved path is recorded in your account.</small></div>
      <div class="full"><label>Or use an image URL</label><input type="url" name="profile_photo_url" maxlength="255" placeholder="https://example.com/profile-photo.jpg"><small>Choose either a file or URL, not both.</small>
        <?php if (!empty($u['profile_photo'])): ?>
          <div class="row" style="margin-top:10px"><button class="btn danger sm" type="submit" name="action" value="remove_profile_photo" onclick="return confirm('Remove your uploaded profile photo?')">Remove profile photo</button></div>
        <?php endif; ?>
      </div>
      <div class="full"><label>About</label><textarea name="bio"><?= e($u['bio']) ?></textarea></div>
      <div><label>Current password</label><div class="password-field"><input type="password" name="current_password" autocomplete="current-password"><button class="password-toggle" type="button" aria-label="Show password" aria-pressed="false"><svg class="eye-on" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/></svg><svg class="eye-off" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m3 3 18 18M10.6 10.6a2 2 0 0 0 2.8 2.8"/><path d="M9.9 5.2A10.8 10.8 0 0 1 12 5c6.4 0 10 7 10 7a17 17 0 0 1-3.1 4.1M6.2 6.2C3.5 8.1 2 12 2 12s3.6 7 10 7a10 10 0 0 0 3-.5"/></svg></button></div></div>
      <div><label>New password</label><div class="password-field"><input type="password" name="new_password" autocomplete="new-password" placeholder="Leave blank to keep"><button class="password-toggle" type="button" aria-label="Show password" aria-pressed="false"><svg class="eye-on" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/></svg><svg class="eye-off" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m3 3 18 18M10.6 10.6a2 2 0 0 0 2.8 2.8"/><path d="M9.9 5.2A10.8 10.8 0 0 1 12 5c6.4 0 10 7 10 7a17 17 0 0 1-3.1 4.1M6.2 6.2C3.5 8.1 2 12 2 12s3.6 7 10 7a10 10 0 0 0 3-.5"/></svg></button></div></div>
    </div>
    <div class="row end" style="margin-top:16px"><button class="btn">Save changes</button></div>
  </form>
</section>
<?php page_end();
