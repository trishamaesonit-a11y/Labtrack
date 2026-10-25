<?php
require __DIR__ . '/includes/layout.php';
if ($u = me()) redirect(home_for($u['role']));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = post('username');
    $row = one('SELECT * FROM users WHERE username=? OR email=?', [$id, $id]);
    if ($row && $row['status'] === 'active' && password_verify($_POST['password'] ?? '', $row['password_hash'])) {
        session_regenerate_id(true);
        $_SESSION['uid'] = (int)$row['user_id'];
        redirect(home_for($row['role']));
    }
    flash('Invalid username or password.', 'err');
}
head('Sign in');
$firebaseConfig = [
    'apiKey' => (string)(getenv('LABTRACK_FIREBASE_API_KEY') ?: 'AIzaSyAmbd3ucYvR-qsmh-8moJ8OVm6WvHkMaSE'),
    'authDomain' => (string)(getenv('LABTRACK_FIREBASE_AUTH_DOMAIN') ?: 'project1-303d9.firebaseapp.com'),
    'projectId' => (string)(getenv('LABTRACK_FIREBASE_PROJECT_ID') ?: 'project1-303d9'),
    'storageBucket' => (string)(getenv('LABTRACK_FIREBASE_STORAGE_BUCKET') ?: 'project1-303d9.firebasestorage.app'),
    'messagingSenderId' => (string)(getenv('LABTRACK_FIREBASE_MESSAGING_SENDER_ID') ?: '105299749739'),
    'appId' => (string)(getenv('LABTRACK_FIREBASE_APP_ID') ?: '1:105299749739:web:0121fd9c00dfe6a67a3a2e'),
    'measurementId' => (string)(getenv('LABTRACK_FIREBASE_MEASUREMENT_ID') ?: 'G-68QRC8HRQF'),
];
$firebaseConfigured = $firebaseConfig['apiKey'] !== ''
    && $firebaseConfig['authDomain'] !== ''
    && $firebaseConfig['projectId'] !== ''
    && $firebaseConfig['appId'] !== '';
?>
<body class="auth">
<div class="tt"><?= theme_toggle() ?></div>
<form class="box glass" id="loginForm" method="post">
  <div class="brand"><span class="logo"><?= icon('flask', 20) ?></span><span>LAB<b>TRACK</b></span></div>
  <h2>Welcome back</h2><p class="sub">Sign in to manage and borrow lab equipment</p>
  <?= flash_html() ?><?= csrf_field() ?>
  <div class="stack">
    <div><label>Username or email</label><input name="username" required autofocus></div>
    <div><label>Password</label><div class="password-field"><input type="password" name="password" autocomplete="current-password" required>
      <button class="password-toggle" type="button" aria-label="Show password" aria-pressed="false"><svg class="eye-on" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/></svg><svg class="eye-off" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m3 3 18 18M10.6 10.6a2 2 0 0 0 2.8 2.8"/><path d="M9.9 5.2A10.8 10.8 0 0 1 12 5c6.4 0 10 7 10 7a17 17 0 0 1-3.1 4.1M6.2 6.2C3.5 8.1 2 12 2 12s3.6 7 10 7a10 10 0 0 0 3-.5"/></svg></button>
    </div></div>
    <button class="btn" style="width:100%;justify-content:center">Sign in</button>
    <button class="google-btn" id="googleLogin" type="button" <?= $firebaseConfigured ? '' : 'disabled' ?>><span class="google-mark" aria-hidden="true">G</span>Continue with Google</button>
    <div class="auth-message" id="googleLoginMessage" role="status" aria-live="polite"></div>
  </div>
  <p class="demo">No account yet? <a href="<?= url('register.php') ?>" style="color:var(--accent);font-weight:700">Create one</a><br>Demo: <b>admin</b> / <b>msantos</b> / <b>jdelacruz</b> — password <b>password</b></p>
</form>
<script>window.LABTRACK_FIREBASE_CONFIG = <?= json_encode($firebaseConfig, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;</script>
<script src="https://www.gstatic.com/firebasejs/10.14.1/firebase-app-compat.js"></script>
<script src="https://www.gstatic.com/firebasejs/10.14.1/firebase-auth-compat.js"></script>
<script src="https://www.gstatic.com/firebasejs/10.14.1/firebase-analytics-compat.js"></script>
<script src="<?= asset_url('assets/js/firebase-login.js') ?>"></script>
<script src="<?= asset_url('assets/js/app.js') ?>"></script></body></html>
