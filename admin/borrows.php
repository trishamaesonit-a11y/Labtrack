<?php
require __DIR__ . '/../includes/layout.php';
$admin = require_role(['admin']);
$statuses = ['Requested', 'Approved', 'Borrowed', 'Returned', 'Rejected'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = post('action'); $id = (int)post('id');
    $b = $id ? one('SELECT * FROM borrow_records WHERE borrow_id=?', [$id]) : null;

    if ($act === 'approve' && $b && $b['status'] === 'Requested') {
        q("UPDATE borrow_records SET status='Approved', approved_by=? WHERE borrow_id=?", [$admin['user_id'], $id]); flash('Request approved.');
    } elseif ($act === 'reject' && $b && $b['status'] === 'Requested') {
        q("UPDATE borrow_records SET status='Rejected', approved_by=? WHERE borrow_id=?", [$admin['user_id'], $id]); flash('Request rejected.');
    } elseif ($act === 'release' && $b && $b['status'] === 'Approved') {
        $eq = one('SELECT * FROM equipment WHERE equipment_id=?', [$b['equipment_id']]);
        if ($eq && $eq['status'] === 'Available') {
            try {
                transaction(function () use ($eq, $id) {
                    q("UPDATE borrow_records SET status='Borrowed', borrow_date=NOW(), condition_at_borrow=? WHERE borrow_id=?", [$eq['condition_status'], $id]);
                    q("UPDATE equipment SET status='Borrowed', usage_count=usage_count+1 WHERE equipment_id=?", [$eq['equipment_id']]);
                });
                flash('Equipment released to borrower.');
            } catch (Throwable $ex) { flash('Could not release the equipment. Nothing was changed.', 'err'); }
        } else flash('That equipment is not available right now.', 'err');
    } elseif ($act === 'return' && $b && $b['status'] === 'Borrowed') {
        $cond = in_array(post('condition'), ['Good', 'Fair', 'Poor'], true) ? post('condition') : 'Good';
        $late = days_overdue($b['expected_return_date']);
        try {
            transaction(function () use ($cond, $id, $b, $late) {
                q("UPDATE borrow_records SET status='Returned', actual_return_date=NOW(), condition_at_return=? WHERE borrow_id=?", [$cond, $id]);
                q("UPDATE equipment SET status=?, condition_status=? WHERE equipment_id=?", [$cond === 'Poor' ? 'Maintenance' : 'Available', $cond, $b['equipment_id']]);
                if ($late > 0) {
                    q("INSERT INTO fines (borrow_id,user_id,days_overdue,amount,reason) VALUES (?,?,?,?, 'Late return')", [$id, $b['user_id'], $late, $late * FINE_PER_DAY]);
                }
            });
            if ($late > 0) flash("Returned {$late} day(s) late. Fine of " . money($late * FINE_PER_DAY) . ' was recorded.');
            else flash('Equipment returned on time.');
        } catch (Throwable $ex) { flash('Could not record the return. Nothing was changed.', 'err'); }
    } elseif ($act === 'create') {
        $eqId = (int)post('equipment_id'); $uId = (int)post('user_id'); $due = post('expected_return_date');
        if ($eqId && $uId && $due) { q("INSERT INTO borrow_records (equipment_id,user_id,status,expected_return_date,notes,approved_by) VALUES (?,?, 'Approved', ?,?,?)", [$eqId, $uId, $due, post('notes'), $admin['user_id']]); flash('Borrow record created (Approved). Release it when handed over.'); }
        else flash('Choose a user, equipment and due date.', 'err');
    } elseif ($act === 'delete' && $b) { q('DELETE FROM borrow_records WHERE borrow_id=?', [$id]); flash('Record deleted.'); }
    redirect('admin/borrows.php');
}

$sf = $_GET['status'] ?? ''; $qs = trim($_GET['q'] ?? '');
$where = ['1=1']; $p = [];
if (in_array($sf, $statuses, true)) { $where[] = 'b.status=?'; $p[] = $sf; }
if ($qs !== '') { $where[] = '(u.full_name LIKE ? OR e.equipment_name LIKE ?)'; array_push($p, "%$qs%", "%$qs%"); }
$rows = all('SELECT b.*, e.equipment_name, u.full_name, u.role FROM borrow_records b JOIN equipment e ON e.equipment_id=b.equipment_id JOIN users u ON u.user_id=b.user_id WHERE ' . implode(' AND ', $where) . ' ORDER BY b.created_at DESC', $p);
$eqs = all("SELECT equipment_id, equipment_name FROM equipment WHERE status='Available' ORDER BY equipment_name");
$usr = all("SELECT user_id, full_name, role FROM users WHERE role<>'admin' AND status='active' ORDER BY full_name");

page_start('Borrow Records', 'admin', 'borrows');
?>
<section class="card glass">
  <div class="card-head">
    <form class="filters" method="get" style="margin:0">
      <input name="q" placeholder="Search borrower / equipment" value="<?= e($qs) ?>">
      <select name="status"><option value="">All status</option><?php foreach ($statuses as $s): ?><option <?= $sf === $s ? 'selected' : '' ?>><?= $s ?></option><?php endforeach; ?></select>
      <button class="btn ghost">Filter</button>
    </form>
    <button class="btn" data-dialog="newDlg"><?= icon('plus', 16) ?> New record</button>
  </div>
  <div class="tbl-wrap"><table>
    <tr><th>#</th><th>Equipment</th><th>Borrower</th><th>Requested</th><th>Released</th><th>Due</th><th>Returned</th><th>Status</th><th></th></tr>
    <?php foreach ($rows as $r): $od = $r['status'] === 'Borrowed' && days_overdue($r['expected_return_date']); ?>
      <tr><td><?= $r['borrow_id'] ?></td><td><b><?= e($r['equipment_name']) ?></b><br><small><?= e($r['notes']) ?></small></td>
      <td><?= e($r['full_name']) ?> <?= badge($r['role']) ?></td><td><?= dt($r['created_at']) ?></td><td><?= dt($r['borrow_date']) ?></td><td><?= dt($r['expected_return_date']) ?></td><td><?= dt($r['actual_return_date']) ?></td>
      <td><?= badge($r['status']) ?> <?= $od ? badge('Overdue') : '' ?></td>
      <td><div class="act">
        <a class="btn ghost sm" href="detail.php?type=borrow&id=<?= (int)$r['borrow_id'] ?>">View details</a> 
        <?php if ($r['status'] === 'Requested'): ?>
          <form method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $r['borrow_id'] ?>"><button class="btn sm" name="action" value="approve">Approve</button> <button class="btn danger sm" name="action" value="reject">Reject</button></form>
        <?php elseif ($r['status'] === 'Approved'): ?>
          <form method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $r['borrow_id'] ?>"><button class="btn sm" name="action" value="release">Release</button></form>
        <?php elseif ($r['status'] === 'Borrowed'): ?>
          <button class="btn warn sm" data-dialog="retDlg" data-fill='{"id":"<?= $r['borrow_id'] ?>"}'>Return</button>
        <?php endif; ?>
        <form method="post" data-confirm="Delete this record?"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $r['borrow_id'] ?>"><button class="btn danger sm"><?= icon('trash', 14) ?></button></form>
      </div></td></tr>
    <?php endforeach; if (!$rows): ?><tr><td colspan="9" class="empty">No borrow records.</td></tr><?php endif; ?>
  </table></div>
  <small>Fine rule: <?= money(FINE_PER_DAY) ?> per day overdue — recorded automatically when equipment is returned late.</small>
</section>

<dialog id="retDlg"><form method="post"><h3>Return equipment</h3><?= csrf_field() ?><input type="hidden" name="action" value="return"><input type="hidden" name="id">
  <label>Condition on return</label><select name="condition"><option>Good</option><option>Fair</option><option>Poor</option></select>
  <small>"Poor" sends the item to Maintenance automatically.</small>
  <div class="row end" style="margin-top:16px"><button type="button" class="btn ghost" data-close>Cancel</button><button class="btn">Confirm return</button></div>
</form></dialog>

<dialog id="newDlg"><form method="post"><h3>New borrow record</h3><?= csrf_field() ?><input type="hidden" name="action" value="create">
  <div class="fg">
    <div><label>Borrower</label><select name="user_id" required><option value="">Select…</option><?php foreach ($usr as $x): ?><option value="<?= $x['user_id'] ?>"><?= e($x['full_name']) ?> (<?= $x['role'] ?>)</option><?php endforeach; ?></select></div>
    <div><label>Equipment (available)</label><select name="equipment_id" required><option value="">Select…</option><?php foreach ($eqs as $x): ?><option value="<?= $x['equipment_id'] ?>"><?= e($x['equipment_name']) ?></option><?php endforeach; ?></select></div>
    <div><label>Expected return</label><input type="date" name="expected_return_date" required min="<?= date('Y-m-d') ?>"></div>
    <div><label>Notes</label><input name="notes"></div>
  </div>
  <div class="row end" style="margin-top:16px"><button type="button" class="btn ghost" data-close>Cancel</button><button class="btn">Create</button></div>
</form></dialog>
<?php page_end();
