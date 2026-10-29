<?php
require __DIR__ . '/../includes/layout.php';
$admin = require_role(['admin']);
$statuses = ['Unpaid', 'Pending', 'Paid', 'Waived'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = post('action'); $id = (int)post('id');
    $f = $id ? one('SELECT * FROM fines WHERE fine_id=?', [$id]) : null;
    if ($act === 'paid' && $f && in_array($f['status'], ['Unpaid', 'Pending'], true)) {
        $m = $f['payment_method'] === 'None' ? 'Cash' : $f['payment_method'];
        q("UPDATE fines SET status='Paid', payment_method=?, paid_at=NOW(), approved_by=? WHERE fine_id=?", [$m, $admin['user_id'], $id]); flash('Fine marked as paid.');
    } elseif ($act === 'decline' && $f && $f['status'] === 'Pending') {
        q("UPDATE fines SET status='Unpaid', payment_method='None', payment_reference=NULL WHERE fine_id=?", [$id]); flash('Payment declined; fine is unpaid again.');
    } elseif ($act === 'waive' && $f && in_array($f['status'], ['Unpaid', 'Pending'], true)) {
        q("UPDATE fines SET status='Waived', approved_by=? WHERE fine_id=?", [$admin['user_id'], $id]); flash('Fine waived.');
    } elseif ($act === 'create') {
        $uid = (int)post('user_id'); $amt = (float)post('amount');
        if ($uid && $amt > 0) { q('INSERT INTO fines (user_id, amount, reason) VALUES (?,?,?)', [$uid, $amt, post('reason') ?: 'Penalty']); flash('Penalty recorded.'); }
        else flash('Choose a user and an amount greater than zero.', 'err');
    } elseif ($act === 'delete' && $f) { q('DELETE FROM fines WHERE fine_id=?', [$id]); flash('Fine record deleted.'); }
    redirect('admin/fines.php');
}

$sf = $_GET['status'] ?? '';
$rows = all('SELECT f.*, u.full_name, e.equipment_name FROM fines f JOIN users u ON u.user_id=f.user_id LEFT JOIN borrow_records b ON b.borrow_id=f.borrow_id LEFT JOIN equipment e ON e.equipment_id=b.equipment_id'
    . (in_array($sf, $statuses, true) ? ' WHERE f.status=?' : '') . ' ORDER BY f.created_at DESC', in_array($sf, $statuses, true) ? [$sf] : []);
$sum = function ($s) { return (float)val('SELECT COALESCE(SUM(amount),0) FROM fines WHERE status=?', [$s]); };
$usr = all("SELECT user_id, full_name, role FROM users WHERE role<>'admin' ORDER BY full_name");

page_start('Fines & Penalties', 'admin', 'fines');
?>
<div class="kpis">
  <div class="kpi glass card"><div class="t"><?= icon('dollar', 15) ?> Unpaid</div><div class="n" style="color:var(--warn)"><?= money($sum('Unpaid')) ?></div></div>
  <div class="kpi glass card"><div class="t"><?= icon('dollar', 15) ?> Awaiting approval</div><div class="n" style="color:var(--info)"><?= money($sum('Pending')) ?></div></div>
  <div class="kpi glass card"><div class="t"><?= icon('dollar', 15) ?> Collected</div><div class="n" style="color:var(--accent)"><?= money($sum('Paid')) ?></div></div>
  <div class="kpi glass card"><div class="t"><?= icon('dollar', 15) ?> Waived</div><div class="n"><?= money($sum('Waived')) ?></div></div>
</div>
<section class="card glass">
  <div class="card-head">
    <form class="filters" method="get" style="margin:0"><select name="status" onchange="this.form.submit()"><option value="">All status</option><?php foreach ($statuses as $s): ?><option <?= $sf === $s ? 'selected' : '' ?>><?= $s ?></option><?php endforeach; ?></select></form>
    <button class="btn" data-dialog="fDlg"><?= icon('plus', 16) ?> Add penalty</button>
  </div>
  <div class="tbl-wrap"><table>
    <tr><th>#</th><th>User</th><th>Reason</th><th>Days late</th><th>Amount</th><th>Payment</th><th>Status</th><th>Created</th><th></th></tr>
    <?php foreach ($rows as $r): ?>
      <tr><td><?= $r['fine_id'] ?></td><td><b><?= e($r['full_name']) ?></b></td><td><?= e($r['reason']) ?><br><small><?= e($r['equipment_name'] ?? '') ?></small></td><td><?= (int)$r['days_overdue'] ?></td><td><b><?= money($r['amount']) ?></b></td>
      <td><?= $r['payment_method'] === 'None' ? '—' : e($r['payment_method']) . '<br><small>' . e($r['payment_reference']) . '</small>' ?></td><td><?= badge($r['status']) ?></td><td><?= dt($r['created_at']) ?></td>
      <td><div class="act">
        <a class="btn ghost sm" href="detail.php?type=fine&id=<?= (int)$r['fine_id'] ?>">View details</a> 
        <?php if (in_array($r['status'], ['Unpaid', 'Pending'], true)): ?>
          <form method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $r['fine_id'] ?>">
            <button class="btn sm" name="action" value="paid"><?= $r['status'] === 'Pending' ? 'Approve' : 'Mark paid' ?></button>
            <?php if ($r['status'] === 'Pending'): ?><button class="btn danger sm" name="action" value="decline">Decline</button><?php endif; ?>
            <button class="btn ghost sm" name="action" value="waive">Waive</button></form>
        <?php endif; ?>
        <form method="post" data-confirm="Delete this fine record?"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $r['fine_id'] ?>"><button class="btn danger sm"><?= icon('trash', 14) ?></button></form>
      </div></td></tr>
    <?php endforeach; if (!$rows): ?><tr><td colspan="9" class="empty">No fines recorded.</td></tr><?php endif; ?>
  </table></div>
</section>
<dialog id="fDlg"><form method="post"><h3>Add manual penalty</h3><?= csrf_field() ?><input type="hidden" name="action" value="create">
  <div class="fg">
    <div class="full"><label>User</label><select name="user_id" required><option value="">Select…</option><?php foreach ($usr as $x): ?><option value="<?= $x['user_id'] ?>"><?= e($x['full_name']) ?> (<?= $x['role'] ?>)</option><?php endforeach; ?></select></div>
    <div><label>Amount (₱)</label><input type="number" step="0.01" min="1" name="amount" required></div>
    <div><label>Reason</label><input name="reason" placeholder="e.g. Damaged item"></div>
  </div>
  <div class="row end" style="margin-top:16px"><button type="button" class="btn ghost" data-close>Cancel</button><button class="btn">Save</button></div>
</form></dialog>
<?php page_end();
