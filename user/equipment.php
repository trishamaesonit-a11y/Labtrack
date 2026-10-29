<?php
require __DIR__ . '/../includes/layout.php';
$u = require_role(['student', 'staff']);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && post('action') === 'request') {
    $eqId = (int)post('equipment_id'); $due = post('expected_return_date');
    $eq = one('SELECT * FROM equipment WHERE equipment_id=?', [$eqId]);
    $max = date('Y-m-d', strtotime('+30 days'));
    if (!$eq || $eq['status'] !== 'Available') flash('That equipment is no longer available.', 'err');
    elseif ($due < date('Y-m-d') || $due > $max) flash('Choose a return date between today and 30 days from now.', 'err');
    elseif (val("SELECT COUNT(*) FROM borrow_records WHERE user_id=? AND equipment_id=? AND status IN ('Requested','Approved','Borrowed')", [$u['user_id'], $eqId])) flash('You already have an open request or borrow for this item.', 'err');
    else { q("INSERT INTO borrow_records (equipment_id,user_id,status,expected_return_date,notes) VALUES (?,?, 'Requested', ?,?)", [$eqId, $u['user_id'], $due, post('notes')]); flash('Request sent. An admin will review it shortly.'); redirect('user/borrows.php'); }
    redirect('user/equipment.php');
}

$qs = trim($_GET['q'] ?? ''); $cat = $_GET['cat'] ?? '';
$where = ["e.status<>'Retired'"]; $p = [];
if ($qs !== '') { $where[] = '(e.equipment_name LIKE ? OR e.description LIKE ?)'; array_push($p, "%$qs%", "%$qs%"); }
if ($cat !== '') { $where[] = 'e.category_id=?'; $p[] = $cat; }
$rows = all('SELECT e.*, c.category_name FROM equipment e LEFT JOIN categories c ON c.category_id=e.category_id WHERE ' . implode(' AND ', $where) . " ORDER BY (e.status='Available') DESC, e.equipment_name", $p);
$cats = all('SELECT * FROM categories ORDER BY category_name');

page_start('Equipment', 'user', 'equipment');
?>
<form class="filters" method="get">
  <input name="q" placeholder="Search equipment…" value="<?= e($qs) ?>">
  <select name="cat" onchange="this.form.submit()"><option value="">All categories</option><?php foreach ($cats as $c): ?><option value="<?= $c['category_id'] ?>" <?= $cat == $c['category_id'] ? 'selected' : '' ?>><?= e($c['category_name']) ?></option><?php endforeach; ?></select>
  <button class="btn ghost">Search</button>
</form>
<div class="eq-grid">
  <?php foreach ($rows as $r): ?>
    <article class="eq card glass">
      <div class="meta"><span><?= e($r['category_name'] ?? 'General') ?></span><?= badge($r['status']) ?></div>
      <h3><?= e($r['equipment_name']) ?></h3>
      <p><?= e($r['description']) ?></p>
      <div class="meta"><span><?= e($r['location']) ?> · <?= e($r['qr_code']) ?></span><span><?= badge($r['condition_status']) ?></span></div>
      <?php if ($r['status'] === 'Available'): ?>
        <button class="btn" data-dialog="reqDlg" data-fill='{"equipment_id":"<?= $r['equipment_id'] ?>","expected_return_date":"<?= date('Y-m-d', strtotime('+7 days')) ?>"}' data-heading="<?= e($r['equipment_name']) ?>">Request to borrow</button>
      <?php else: ?><button class="btn ghost" disabled style="opacity:.55;cursor:not-allowed">Not available</button><?php endif; ?>
    </article>
  <?php endforeach; ?>
</div>
<?php if (!$rows): ?><div class="card glass empty">No equipment matches your search.</div><?php endif; ?>

<dialog id="reqDlg"><form method="post"><h3>Borrow: <span data-title></span></h3><?= csrf_field() ?><input type="hidden" name="action" value="request"><input type="hidden" name="equipment_id">
  <div class="fg">
    <div class="full"><label>Return date</label><input type="date" name="expected_return_date" required min="<?= date('Y-m-d') ?>" max="<?= date('Y-m-d', strtotime('+30 days')) ?>"></div>
    <div class="full"><label>Purpose / notes</label><textarea name="notes" placeholder="What will you use it for?"></textarea></div>
  </div>
  <small>Late returns are charged <?= money(FINE_PER_DAY) ?> per day.</small>
  <div class="row end" style="margin-top:16px"><button type="button" class="btn ghost" data-close>Cancel</button><button class="btn">Send request</button></div>
</form></dialog>
<?php page_end();
