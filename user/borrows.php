<?php
require __DIR__ . '/../includes/layout.php';
$u = require_role(['student', 'staff']);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && post('action') === 'cancel') {
    $n = q("DELETE FROM borrow_records WHERE borrow_id=? AND user_id=? AND status='Requested'", [(int)post('id'), $u['user_id']])->rowCount();
    flash($n ? 'Request cancelled.' : 'Only pending requests can be cancelled.', $n ? 'ok' : 'err');
    redirect('user/borrows.php');
}
$rows = all('SELECT b.*, e.equipment_name, e.qr_code FROM borrow_records b JOIN equipment e ON e.equipment_id=b.equipment_id WHERE b.user_id=? ORDER BY b.created_at DESC', [$u['user_id']]);
page_start('My Borrows', 'user', 'borrows');
?>
<section class="card glass"><div class="tbl-wrap"><table>
  <tr><th>Equipment</th><th>Requested</th><th>Released</th><th>Due</th><th>Returned</th><th>Status</th><th></th></tr>
  <?php foreach ($rows as $r): $late = $r['status'] === 'Borrowed' ? days_overdue($r['expected_return_date']) : 0; ?>
    <tr><td><b><?= e($r['equipment_name']) ?></b><br><small><?= e($r['qr_code']) ?> · <?= e($r['notes']) ?></small></td>
    <td><?= dt($r['created_at']) ?></td><td><?= dt($r['borrow_date']) ?></td><td><?= dt($r['expected_return_date']) ?></td><td><?= dt($r['actual_return_date']) ?></td>
    <td><?= badge($r['status']) ?> <?= $late ? badge('Overdue') : '' ?></td>
    <td><?php if ($r['status'] === 'Requested'): ?><form method="post" data-confirm="Cancel this request?"><?= csrf_field() ?><input type="hidden" name="action" value="cancel"><input type="hidden" name="id" value="<?= $r['borrow_id'] ?>"><button class="btn danger sm">Cancel</button></form><?php endif; ?></td></tr>
  <?php endforeach; if (!$rows): ?><tr><td colspan="7" class="empty">You have not borrowed anything yet.</td></tr><?php endif; ?>
</table></div></section>
<?php page_end();
