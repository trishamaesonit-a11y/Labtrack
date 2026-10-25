<?php
require __DIR__ . '/includes/layout.php';
if ($u = me()) redirect(home_for($u['role']));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = post('full_name'); $user = post('username'); $mail = post('email');
    $role = in_array(post('role'), ['student', 'staff'], true) ? post('role') : 'student';   // admins are created by admins only
    $pass = $_POST['password'] ?? '';
    if ($name === '' || $user === '' || !filter_var($mail, FILTER_VALIDATE_EMAIL)) flash('Please fill in all fields with a valid email.', 'err');
    elseif (strlen($pass) < 6) flash('Password must be at least 6 characters.', 'err');
    elseif (val('SELECT COUNT(*) FROM users WHERE username=? OR email=?', [$user, $mail])) flash('Username or email is already taken.', 'err');
    else {
        q('INSERT INTO users (username,password_hash,full_name,email,role,department) VALUES (?,?,?,?,?,?)',
          [$user, password_hash($pass, PASSWORD_DEFAULT), $name, $mail, $role, post('department')]);
        flash('Account created. You can sign in now.');
        redirect('login.php');
    }
}
head('Create account');
?>
<body class="auth">
<div class="tt"><?= theme_toggle() ?></div>
<form class="box glass" method="post">
  <div class="brand"><span class="logo"><?= icon('flask', 20) ?></span><span>LAB<b>TRACK</b></span></div>
  <h2>Create account</h2><p class="sub">For students and staff</p>
  <?= flash_html() ?><?= csrf_field() ?>
  <div class="stack">
    <div><label>Full name</label><input name="full_name" required value="<?= e(post('full_name')) ?>"></div>
    <div class="fg">
      <div><label>Username</label><input name="username" required value="<?= e(post('username')) ?>"></div>
      <div><label>I am a</label><select name="role"><option value="student">Student</option><option value="staff">Staff</option></select></div>
    </div>
    <div><label>Email</label><input type="email" name="email" required value="<?= e(post('email')) ?>"></div>
    <div class="fg">
      <div><label>Department</label><input name="department" value="<?= e(post('department')) ?>"></div>
      <div><label>Password</label><div class="password-field"><input type="password" name="password" autocomplete="new-password" required minlength="6">
        <button class="password-toggle" type="button" aria-label="Show password" aria-pressed="false"><svg class="eye-on" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/></svg><svg class="eye-off" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m3 3 18 18M10.6 10.6a2 2 0 0 0 2.8 2.8"/><path d="M9.9 5.2A10.8 10.8 0 0 1 12 5c6.4 0 10 7 10 7a17 17 0 0 1-3.1 4.1M6.2 6.2C3.5 8.1 2 12 2 12s3.6 7 10 7a10 10 0 0 0 3-.5"/></svg></button>
      </div></div>
    </div>
    <button class="btn" style="width:100%;justify-content:center">Sign up</button>
  </div>
  <p class="demo">Already registered? <a href="<?= url('login.php') ?>" style="color:var(--accent);font-weight:700">Sign in</a></p>
</form>
<script src="<?= asset_url('assets/js/app.js') ?>"></script></body></html>
