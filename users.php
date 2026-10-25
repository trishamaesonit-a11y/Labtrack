<?php
require __DIR__ . '/../includes/layout.php';
$admin = require_role(['admin']);
$roles = ['admin', 'staff', 'student'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = post('action'); $id = (int)post('id');
    if ($act === 'save') {
        $role = in_array(post('role'), $roles, true) ? post('role') : 'student';
        $status = post('status') === 'disabled' ? 'disabled' : 'active';
        $name = post('full_name'); $user = post('username'); $mail = post('email'); $pass = $_POST['password'] ?? '';
        if ($id === (int)$admin['user_id']) { $role = 'admin'; $status = 'active'; }   // don't lock yourself out
        if ($name === '' || $user === '' || !filter_var($mail, FILTER_VALIDATE_EMAIL)) flash('Name, username and a valid email are required.', 'err');
        elseif (val('SELECT COUNT(*) FROM users WHERE (username=? OR email=?) AND user_id<>?', [$user, $mail, $id])) flash('Username or email already in use.', 'err');
        elseif (!$id && strlen($pass) < 6) flash('Password must be at least 6 characters.', 'err');
        elseif ($pass !== '' && strlen($pass) < 6) flash('New password must be at least 6 characters.', 'err');
        else {
            $f = [$user, $name, $mail, $role, post('department'), post('phone'), $status];
            if ($id) {
                try {
                    transaction(function () use ($f, $id, $pass) {
                        q('UPDATE users SET username=?,full_name=?,email=?,role=?,department=?,phone=?,status=? WHERE user_id=?', array_merge($f, [$id]));
                        if ($pass !== '') q('UPDATE users SET password_hash=? WHERE user_id=?', [password_hash($pass, PASSWORD_DEFAULT), $id]);
                    });
                    flash('User updated.');
                } catch (Throwable $ex) { flash('Could not update the user. Nothing was changed.', 'err'); }
            } else {
                q('INSERT INTO users (username,full_name,email,role,department,phone,status,password_hash) VALUES (?,?,?,?,?,?,?,?)', array_merge($f, [password_hash($pass, PASSWORD_DEFAULT)]));
                flash('User created.');
            }
        }
    } elseif ($act === 'delete' && $id) {
        if ($id === (int)$admin['user_id']) flash('You cannot delete your own account.', 'err');
        else { q('DELETE FROM users WHERE user_id=?', [$id]); flash('User deleted.'); }
    }
    redirect('admin/users.php');
}
$rf = $_GET['role'] ?? ''; $qs = trim($_GET['q'] ?? '');
$where = ['1=1']; $p = [];
if (in_array($rf, $roles, true)) { $where[] = 'role=?'; $p[] = $rf; }
if ($qs !== '') { $where[] = '(full_name LIKE ? OR username LIKE ? OR email LIKE ?)'; array_push($p, "%$qs%", "%$qs%", "%$qs%"); }
$rows = all('SELECT * FROM users WHERE ' . implode(' AND ', $where) . ' ORDER BY created_at DESC', $p);
page_start('Users', 'admin', 'users');
?>
<section class="card glass">
  <div class="card-head">
    <form class="filters" method="get" style="margin:0">
      <input name="q" placeholder="Search users" value="<?= e($qs) ?>">
      <select name="role"><option value="">All roles</option><?php foreach ($roles as $r): ?><option <?= $rf === $r ? 'selected' : '' ?>><?= $r ?></option><?php endforeach; ?></select>
      <button class="btn ghost">Filter</button>
    </form>
    <button class="btn" data-dialog="uDlg" data-heading="Add user"><?= icon('plus', 16) ?> Add user</button>
  </div>
  <div class="tbl-wrap"><table>
    <tr><th>User</th><th>Email</th><th>Department</th><th>Role</th><th>Status</th><th>Created</th><th></th></tr>
    <?php foreach ($rows as $r): ?>
      <tr><td><div class="row"><span class="av"><?= e(initials($r['full_name'])) ?></span><div><b><?= e($r['full_name']) ?></b><br><small>@<?= e($r['username']) ?></small></div></div></td>
      <td><?= e($r['email']) ?></td><td><?= e($r['department']) ?></td><td><?= badge($r['role']) ?></td><td><?= badge($r['status']) ?></td><td><?= dt($r['created_at'], 'M j, Y g:i A') ?></td>
      <td><div class="act">
        <button class="btn ghost sm" data-dialog="uDlg" data-heading="Edit user" data-fill="<?= e(json_encode(array_diff_key($r, ['password_hash' => 1]))) ?>"><?= icon('edit', 14) ?></button>
        <?php if ((int)$r['user_id'] !== (int)$admin['user_id']): ?>
        <form method="post" data-confirm="Delete this user and all their records?"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $r['user_id'] ?>"><button class="btn danger sm"><?= icon('trash', 14) ?></button></form>
        <?php endif; ?>
      </div></td></tr>
    <?php endforeach; ?>
  </table></div>
</section>
<dialog id="uDlg"><form method="post"><h3 data-title>Add user</h3><?= csrf_field() ?><input type="hidden" name="action" value="save"><input type="hidden" name="id">
  <div class="fg">
    <div class="full"><label>Full name *</label><input name="full_name" required></div>
    <div><label>Username *</label><input name="username" required></div>
    <div><label>Email *</label><input type="email" name="email" required></div>
    <div><label>Role</label><select name="role"><option value="student">Student</option><option value="staff">Staff</option><option value="admin">Admin</option></select></div>
    <div><label>Status</label><select name="status"><option value="active">Active</option><option value="disabled">Disabled</option></select></div>
    <div><label>Department</label><input name="department"></div>
    <div><label>Phone</label><input name="phone"></div>
    <div class="full"><label>Password <small>(leave blank to keep current when editing)</small></label><div class="password-field"><input type="password" name="password" autocomplete="new-password"><button class="password-toggle" type="button" aria-label="Show password" aria-pressed="false"><svg class="eye-on" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/></svg><svg class="eye-off" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m3 3 18 18M10.6 10.6a2 2 0 0 0 2.8 2.8"/><path d="M9.9 5.2A10.8 10.8 0 0 1 12 5c6.4 0 10 7 10 7a17 17 0 0 1-3.1 4.1M6.2 6.2C3.5 8.1 2 12 2 12s3.6 7 10 7a10 10 0 0 0 3-.5"/></svg></button></div></div>
  </div>
  <div class="row end" style="margin-top:16px"><button type="button" class="btn ghost" data-close>Cancel</button><button class="btn">Save</button></div>
</form></dialog>
<?php page_end();
