<?php
require __DIR__ . '/../includes/layout.php';
$u = require_role(['student', 'staff']);
$methods = ['GCash', 'Maya', 'Cash', 'Bank Transfer', 'Card'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && post('action') === 'pay') {
    $m = in_array(post('payment_method'), $methods, true) ? post('payment_method') : 'Cash';
    $ref = post('payment_reference');
    if ($m !== 'Cash' && $ref === '') flash('Please enter the payment reference number.', 'err');
    else {
        $n = q("UPDATE fines SET status='Pending', payment_method=?, payment_reference=? WHERE fine_id=? AND user_id=? AND status='Unpaid'", [$m, nullable($ref), (int)post('id'), $u['user_id']])->rowCount();
        flash($n ? 'Payment submitted. An admin will confirm it.' : 'This fine cannot be paid right now.', $n ? 'ok' : 'err');
    }
    redirect('user/fines.php');
}
$rows = all('SELECT f.*, e.equipment_name FROM fines f LEFT JOIN borrow_records b ON b.borrow_id=f.borrow_id LEFT JOIN equipment e ON e.equipment_id=b.equipment_id WHERE f.user_id=? ORDER BY f.created_at DESC', [$u['user_id']]);
$owed = array_sum(array_map(function ($r) { return $r['status'] === 'Unpaid' ? $r['amount'] : 0; }, $rows));
page_start('My Fines', 'user', 'fines');
?>
<section class="hero glass"><div><h2><?= money($owed) ?> <small>due</small></h2><p>Late returns cost <?= money(FINE_PER_DAY) ?> per day. Pay via GCash, Maya, bank or at the lab office.</p></div></section>
<section class="card glass"><div class="tbl-wrap"><table>
  <tr><th>Reason</th><th>Days late</th><th>Amount</th><th>Status</th><th>Date</th><th></th></tr>
  <?php foreach ($rows as $r): ?>
    <tr><td><b><?= e($r['reason']) ?></b><br><small><?= e($r['equipment_name'] ?? '') ?></small></td><td><?= (int)$r['days_overdue'] ?></td><td><b><?= money($r['amount']) ?></b></td>
    <td><?= badge($r['status']) ?><?= $r['status'] === 'Pending' ? '<br><small>' . e($r['payment_method']) . ' · ' . e($r['payment_reference']) . '</small>' : '' ?></td><td><?= dt($r['created_at']) ?></td>
    <td><?php if ($r['status'] === 'Unpaid'): ?><button class="btn sm" data-dialog="payDlg" data-fill='{"id":"<?= $r['fine_id'] ?>"}'>Pay</button><?php endif; ?></td></tr>
  <?php endforeach; if (!$rows): ?><tr><td colspan="6" class="empty">You have no fines. Keep it up! 🎉</td></tr><?php endif; ?>
</table></div></section>
<dialog id="payDlg"><form method="post"><h3>Submit payment</h3><?= csrf_field() ?><input type="hidden" name="action" value="pay"><input type="hidden" name="id">
  <div class="fg">
    <div class="full"><label>Method</label><select name="payment_method"><?php foreach ($methods as $m): ?><option><?= $m ?></option><?php endforeach; ?></select></div>
    <div class="full"><label>Reference number <small>(not needed for cash)</small></label><input name="payment_reference"></div>
  </div>
  <div class="row end" style="margin-top:16px"><button type="button" class="btn ghost" data-close>Cancel</button><button class="btn">Submit</button></div>
</form></dialog>
<?php page_end();
