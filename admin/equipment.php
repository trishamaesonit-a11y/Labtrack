<?php
require __DIR__ . '/../includes/layout.php';
require_role(['admin']);
$statuses = ['Available', 'Borrowed', 'Maintenance', 'Retired'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = post('action'); $id = (int)post('id');
    if ($act === 'save') {
        $status = in_array(post('status'), $statuses, true) ? post('status') : 'Available';
        $f = [post('equipment_name'), nullable(post('category_id')), post('description'), post('serial_number'), $status, post('condition_status', 'Good') ?: 'Good',
              max(1, (int)post('quantity', '1')), post('location'), post('department'), nullable(post('purchase_date'))];
        if ($f[0] === '') flash('Equipment name is required.', 'err');
        elseif ($id) { q('UPDATE equipment SET equipment_name=?,category_id=?,description=?,serial_number=?,status=?,condition_status=?,quantity=?,location=?,department=?,purchase_date=? WHERE equipment_id=?', array_merge($f, [$id])); flash('Equipment updated.'); }
        else {
            try {
                transaction(function () use ($f) {
                    q('INSERT INTO equipment (equipment_name,category_id,description,serial_number,status,condition_status,quantity,location,department,purchase_date) VALUES (?,?,?,?,?,?,?,?,?,?)', $f);
                    $nid = (int)db()->lastInsertId(); q('UPDATE equipment SET qr_code=? WHERE equipment_id=?', [qr_for($nid), $nid]);
                });
                flash('Equipment added.');
            } catch (Throwable $ex) { flash('Could not add the equipment. Nothing was saved.', 'err'); }
        }
    } elseif ($act === 'delete' && $id) { q('DELETE FROM equipment WHERE equipment_id=?', [$id]); flash('Equipment deleted.'); }
    redirect('admin/equipment.php');
}

$qs = trim($_GET['q'] ?? ''); $cat = $_GET['cat'] ?? ''; $stf = $_GET['status'] ?? '';
$where = ['1=1']; $p = [];
if ($qs !== '') { $where[] = '(e.equipment_name LIKE ? OR e.serial_number LIKE ? OR e.qr_code LIKE ?)'; array_push($p, "%$qs%", "%$qs%", "%$qs%"); }
if ($cat !== '') { $where[] = 'e.category_id=?'; $p[] = $cat; }
if (in_array($stf, $statuses, true)) { $where[] = 'e.status=?'; $p[] = $stf; }
$rows = all('SELECT e.*, c.category_name FROM equipment e LEFT JOIN categories c ON c.category_id=e.category_id WHERE ' . implode(' AND ', $where) . ' ORDER BY e.equipment_id DESC', $p);
$cats = all('SELECT * FROM categories ORDER BY category_name');

page_start('Equipment', 'admin', 'equipment');
?>
<section class="card glass">
  <div class="card-head">
    <form class="filters" method="get" style="margin:0">
      <input name="q" placeholder="Search name / serial / QR" value="<?= e($qs) ?>">
      <select name="cat"><option value="">All categories</option><?php foreach ($cats as $c): ?><option value="<?= $c['category_id'] ?>" <?= $cat == $c['category_id'] ? 'selected' : '' ?>><?= e($c['category_name']) ?></option><?php endforeach; ?></select>
      <select name="status"><option value="">All status</option><?php foreach ($statuses as $s): ?><option <?= $stf === $s ? 'selected' : '' ?>><?= $s ?></option><?php endforeach; ?></select>
      <button class="btn ghost">Filter</button>
    </form>
    <button class="btn" data-dialog="eqDlg" data-heading="Add equipment"><?= icon('plus', 16) ?> Add equipment</button>
  </div>
  <div class="tbl-wrap"><table>
    <tr><th>QR</th><th>Equipment</th><th>Category</th><th>Location</th><th>Qty</th><th>Condition</th><th>Status</th><th>Used</th><th></th></tr>
    <?php foreach ($rows as $r): ?>
      <tr><td><?= e($r['qr_code']) ?></td><td><b><?= e($r['equipment_name']) ?></b><br><small><?= e($r['serial_number']) ?></small></td>
      <td><?= e($r['category_name'] ?? '—') ?></td><td><?= e($r['location']) ?><br><small><?= e($r['department']) ?></small></td><td><?= (int)$r['quantity'] ?></td>
      <td><?= badge($r['condition_status']) ?></td><td><?= badge($r['status']) ?></td><td><?= (int)$r['usage_count'] ?>×</td>
      <td><div class="act">
        <a class="btn ghost sm" href="detail.php?type=equipment&id=<?= (int)$r['equipment_id'] ?>">View details</a> 
        <button class="btn ghost sm" data-dialog="eqDlg" data-heading="Edit equipment" data-fill="<?= e(json_encode($r)) ?>"><?= icon('edit', 14) ?></button>
        <form method="post" data-confirm="Delete this equipment and its records?"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $r['equipment_id'] ?>"><button class="btn danger sm"><?= icon('trash', 14) ?></button></form>
      </div></td></tr>
    <?php endforeach; if (!$rows): ?><tr><td colspan="9" class="empty">No equipment found.</td></tr><?php endif; ?>
  </table></div>
</section>

<dialog id="eqDlg"><form method="post"><h3 data-title>Add equipment</h3><?= csrf_field() ?><input type="hidden" name="action" value="save"><input type="hidden" name="id">
  <div class="fg">
    <div class="full"><label>Equipment name *</label><input name="equipment_name" required></div>
    <div><label>Category</label><select name="category_id"><option value="">— none —</option><?php foreach ($cats as $c): ?><option value="<?= $c['category_id'] ?>"><?= e($c['category_name']) ?></option><?php endforeach; ?></select></div>
    <div><label>Serial number</label><input name="serial_number"></div>
    <div><label>Status</label><select name="status"><?php foreach ($statuses as $s): ?><option><?= $s ?></option><?php endforeach; ?></select></div>
    <div><label>Condition</label><select name="condition_status"><option>Good</option><option>Fair</option><option>Poor</option></select></div>
    <div><label>Quantity</label><input type="number" min="1" name="quantity" value="1"></div>
    <div><label>Purchase date</label><input type="date" name="purchase_date"></div>
    <div><label>Location</label><input name="location"></div>
    <div><label>Department</label><input name="department"></div>
    <div class="full"><label>Description</label><textarea name="description"></textarea></div>
  </div>
  <div class="row end" style="margin-top:16px"><button type="button" class="btn ghost" data-close>Cancel</button><button class="btn">Save</button></div>
</form></dialog>
<?php page_end();
