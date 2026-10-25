<?php
require __DIR__ . '/../includes/layout.php';
require_role(['admin']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = post('action'); $id = (int)post('id');
    if ($act === 'save') {
        $f = [(int)post('equipment_id'), post('maintenance_date'), post('description'), (float)post('cost', '0'), post('technician'), nullable(post('next_maintenance_date')), post('trigger_type') === 'usage' ? 'usage' : 'time'];
        if (!$f[0] || $f[1] === '') flash('Equipment and date are required.', 'err');
        else {
            try {
                transaction(function () use ($id, $f) {
                    if ($id) q('UPDATE maintenance_logs SET equipment_id=?,maintenance_date=?,description=?,cost=?,technician=?,next_maintenance_date=?,trigger_type=? WHERE log_id=?', array_merge($f, [$id]));
                    else q('INSERT INTO maintenance_logs (equipment_id,maintenance_date,description,cost,technician,next_maintenance_date,trigger_type) VALUES (?,?,?,?,?,?,?)', $f);
                    q('UPDATE equipment SET last_maintenance=(SELECT MAX(maintenance_date) FROM maintenance_logs WHERE equipment_id=?) WHERE equipment_id=?', [$f[0], $f[0]]);
                });
                flash('Maintenance log saved.');
            } catch (Throwable $ex) { flash('Could not save the maintenance log. Nothing was changed.', 'err'); }
        }
    } elseif ($act === 'delete' && $id) { q('DELETE FROM maintenance_logs WHERE log_id=?', [$id]); flash('Log deleted.'); }
    redirect('admin/maintenance.php');
}
$rows = all('SELECT m.*, e.equipment_name FROM maintenance_logs m JOIN equipment e ON e.equipment_id=m.equipment_id ORDER BY m.maintenance_date DESC, m.log_id DESC');
$eqs = all('SELECT equipment_id, equipment_name FROM equipment ORDER BY equipment_name');
$due = all("SELECT equipment_name, next_due FROM (SELECT e.equipment_name, MAX(m.next_maintenance_date) next_due FROM equipment e JOIN maintenance_logs m ON m.equipment_id=e.equipment_id GROUP BY e.equipment_id) t WHERE next_due <= DATE_ADD(CURDATE(), INTERVAL 14 DAY) ORDER BY next_due");
page_start('Maintenance Log', 'admin', 'maintenance');
?>
<?php if ($due): ?><section class="card glass" style="margin-bottom:16px"><h3>Maintenance due or overdue</h3><div class="row"><?php foreach ($due as $d): ?><span class="badge b-maintenance"><?= e($d['equipment_name']) ?> · <?= dt($d['next_due']) ?></span><?php endforeach; ?></div></section><?php endif; ?>
<section class="card glass">
  <div class="card-head"><h3>All maintenance records</h3><button class="btn" data-dialog="mDlg" data-heading="Add maintenance log"><?= icon('plus', 16) ?> Add log</button></div>
  <div class="tbl-wrap"><table>
    <tr><th>Date</th><th>Equipment</th><th>Description</th><th>Technician</th><th>Cost</th><th>Next due</th><th>Trigger</th><th></th></tr>
    <?php foreach ($rows as $r): ?>
      <tr><td><?= dt($r['maintenance_date']) ?></td><td><b><?= e($r['equipment_name']) ?></b></td><td><?= e($r['description']) ?></td><td><?= e($r['technician']) ?></td><td><?= money($r['cost']) ?></td><td><?= dt($r['next_maintenance_date']) ?></td><td><?= badge(ucfirst($r['trigger_type'])) ?></td>
      <td><div class="act">
        <a class="btn ghost sm" href="detail.php?type=maintenance&id=<?= (int)$r['log_id'] ?>">View details</a> 
        <button class="btn ghost sm" data-dialog="mDlg" data-heading="Edit maintenance log" data-fill="<?= e(json_encode($r)) ?>"><?= icon('edit', 14) ?></button>
        <form method="post" data-confirm="Delete this log?"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $r['log_id'] ?>"><button class="btn danger sm"><?= icon('trash', 14) ?></button></form>
      </div></td></tr>
    <?php endforeach; if (!$rows): ?><tr><td colspan="8" class="empty">No maintenance records yet.</td></tr><?php endif; ?>
  </table></div>
</section>
<dialog id="mDlg"><form method="post"><h3 data-title>Add maintenance log</h3><?= csrf_field() ?><input type="hidden" name="action" value="save"><input type="hidden" name="id">
  <div class="fg">
    <div class="full"><label>Equipment *</label><select name="equipment_id" required><option value="">Select…</option><?php foreach ($eqs as $x): ?><option value="<?= $x['equipment_id'] ?>"><?= e($x['equipment_name']) ?></option><?php endforeach; ?></select></div>
    <div><label>Date *</label><input type="date" name="maintenance_date" required value="<?= date('Y-m-d') ?>"></div>
    <div><label>Next maintenance</label><input type="date" name="next_maintenance_date"></div>
    <div><label>Technician</label><input name="technician"></div>
    <div><label>Cost (₱)</label><input type="number" step="0.01" min="0" name="cost" value="0"></div>
    <div><label>Trigger</label><select name="trigger_type"><option value="time">Time-based</option><option value="usage">Usage-based</option></select></div>
    <div class="full"><label>Description</label><input name="description"></div>
  </div>
  <div class="row end" style="margin-top:16px"><button type="button" class="btn ghost" data-close>Cancel</button><button class="btn">Save</button></div>
</form></dialog>
<?php page_end();
