<?php
require __DIR__ . '/../includes/layout.php';
$u = require_role(['student', 'staff']);
$id = $u['user_id'];

$active  = (int)val("SELECT COUNT(*) FROM borrow_records WHERE user_id=? AND status='Borrowed'", [$id]);
$pending = (int)val("SELECT COUNT(*) FROM borrow_records WHERE user_id=? AND status IN ('Requested','Approved')", [$id]);
$soon    = (int)val("SELECT COUNT(*) FROM borrow_records WHERE user_id=? AND status='Borrowed' AND expected_return_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 3 DAY)", [$id]);
$owed    = (float)val("SELECT COALESCE(SUM(amount),0) FROM fines WHERE user_id=? AND status IN ('Unpaid','Pending')", [$id]);

$current = all("SELECT b.*, e.equipment_name FROM borrow_records b JOIN equipment e ON e.equipment_id=b.equipment_id WHERE b.user_id=? AND b.status IN ('Borrowed','Approved','Requested') ORDER BY b.expected_return_date", [$id]);
$msgs = all("SELECT m.*, s.full_name sname, s.role srole FROM messages m JOIN users s ON s.user_id=m.sender_id WHERE m.group_id IS NULL AND (m.sender_id=? OR m.recipient_id=? OR (m.recipient_id IS NULL AND s.role='admin')) ORDER BY m.created_at DESC LIMIT 4", [$id, $id]);
$avail = all("SELECT e.equipment_name, e.location, c.category_name FROM equipment e LEFT JOIN categories c ON c.category_id=e.category_id WHERE e.status='Available' ORDER BY e.usage_count DESC LIMIT 5");

page_start('Dashboard', 'user', 'dashboard');
?>
<section class="hero glass">
  <div><h2>Hello, <?= e(explode(' ', $u['full_name'])[0]) ?> 👋</h2><p>Borrow lab equipment, track your returns and settle fines in one place.</p></div>
  <a class="btn" href="<?= url('user/equipment.php') ?>"><?= icon('box', 16) ?> Browse equipment</a>
</section>
<div class="kpis">
  <div class="kpi card glass"><div class="t"><?= icon('repeat', 15) ?> Borrowed now</div><div class="n"><?= $active ?></div></div>
  <div class="kpi card glass"><div class="t"><?= icon('bell', 15) ?> Open requests</div><div class="n" style="color:var(--info)"><?= $pending ?></div></div>
  <div class="kpi card glass"><div class="t"><?= icon('tool', 15) ?> Due in 3 days</div><div class="n" style="color:var(--warn)"><?= $soon ?></div></div>
  <div class="kpi card glass"><div class="t"><?= icon('dollar', 15) ?> Fines to pay</div><div class="n" style="color:<?= $owed > 0 ? 'var(--danger)' : 'var(--accent)' ?>"><?= money($owed) ?></div></div>
</div>
<div class="cols">
  <section class="card glass">
    <div class="card-head"><h3>My current borrows &amp; requests</h3><a class="btn ghost sm" href="<?= url('user/borrows.php') ?>">History</a></div>
    <div class="tbl-wrap"><table>
      <tr><th>Equipment</th><th>Due</th><th>Status</th></tr>
      <?php foreach ($current as $r): $late = $r['status'] === 'Borrowed' ? days_overdue($r['expected_return_date']) : 0; ?>
        <tr><td><b><?= e($r['equipment_name']) ?></b></td><td><?= dt($r['expected_return_date']) ?><?= $late ? '<br><small style="color:var(--danger)">' . $late . ' day(s) late · ' . money($late * FINE_PER_DAY) . ' so far</small>' : '' ?></td><td><?= badge($r['status']) ?></td></tr>
      <?php endforeach; if (!$current): ?><tr><td colspan="3" class="empty">Nothing borrowed right now.</td></tr><?php endif; ?>
    </table></div>
  </section>
  <div style="display:grid;gap:16px;align-content:start">
    <section class="card glass"><div class="card-head"><h3>Popular &amp; available</h3></div>
      <?php foreach ($avail as $a): ?><div class="row" style="justify-content:space-between;padding:7px 0;border-top:1px solid var(--track)"><span><b><?= e($a['equipment_name']) ?></b><br><small><?= e($a['category_name'] ?? '') ?> · <?= e($a['location']) ?></small></span><?= badge('Available') ?></div><?php endforeach; ?>
      <?php if (!$avail): ?><div class="empty">Nothing available at the moment.</div><?php endif; ?>
    </section>
    <section class="card glass"><div class="card-head"><h3>Latest messages</h3><a class="btn ghost sm" href="<?= url('user/messages.php') ?>">Open</a></div>
      <?php foreach ($msgs as $m): ?><div class="msg"><small><b><?= (int)$m['sender_id'] === (int)$id ? 'You' : e($m['sname']) ?> (<?= e(ucfirst($m['srole'])) ?>)</b> · <?= dt($m['created_at'], 'M j, g:i A') ?></small><?= e(mb_strimwidth($m['body'], 0, 90, '…')) ?></div><?php endforeach; ?>
      <?php if (!$msgs): ?><div class="empty">No messages yet.</div><?php endif; ?>
    </section>
  </div>
</div>
<?php page_end();
