<?php
require __DIR__ . '/../includes/layout.php';
require_role(['admin']);

/** last $n months of an aggregate, oldest first => [labels, values] */
function monthly($table, $col, $agg = 'COUNT(*)', $where = '1=1', $n = 12) {
    $rows = all("SELECT DATE_FORMAT($col,'%Y-%m') m, $agg v FROM $table WHERE $where AND $col >= DATE_SUB(DATE_FORMAT(CURDATE(),'%Y-%m-01'), INTERVAL " . ($n - 1) . " MONTH) GROUP BY m");
    $map = []; foreach ($rows as $r) $map[$r['m']] = (float)$r['v'];
    $labels = []; $vals = [];
    for ($i = $n - 1; $i >= 0; $i--) {
        $t = strtotime("first day of -$i month");
        $labels[] = date('M', $t); $vals[] = $map[date('Y-m', $t)] ?? 0;
    }
    return [$labels, $vals];
}
function delta($vals) {
    $last = end($vals); $prev = prev($vals);
    $p = $prev > 0 ? round(($last - $prev) / $prev * 100) : ($last > 0 ? 100 : 0);
    return '<span class="delta ' . ($p >= 0 ? 'up' : 'down') . '">' . ($p >= 0 ? '↑ +' : '↓ ') . $p . '%</span> <small>vs last month</small>';
}

[$ml, $req]  = monthly('borrow_records', 'created_at');
[, $ret]     = monthly('borrow_records', 'actual_return_date', 'COUNT(*)', "status='Returned'");
[, $rel]     = monthly('borrow_records', 'borrow_date');
[, $fineAmt] = monthly('fines', 'created_at', 'COALESCE(SUM(amount),0)');
[, $mCost]   = monthly('maintenance_logs', 'maintenance_date', 'COALESCE(SUM(cost),0)');

$totalEq  = (int)val('SELECT COUNT(*) FROM equipment');
$available= (int)val("SELECT COUNT(*) FROM equipment WHERE status='Available'");
$borrowed = (int)val("SELECT COUNT(*) FROM borrow_records WHERE status='Borrowed'");
$pending  = (int)val("SELECT COUNT(*) FROM borrow_records WHERE status='Requested'");
$overdue  = (int)val("SELECT COUNT(*) FROM borrow_records WHERE status='Borrowed' AND expected_return_date < CURDATE()");
$totalBr  = (int)val('SELECT COUNT(*) FROM borrow_records');
$unpaid   = (float)val("SELECT COALESCE(SUM(amount),0) FROM fines WHERE status IN ('Unpaid','Pending')");
$mYear    = (float)val('SELECT COALESCE(SUM(cost),0) FROM maintenance_logs WHERE YEAR(maintenance_date)=YEAR(CURDATE())');
$thisMonth= (int)end($req);
$totalUsers = (int)val('SELECT COUNT(*) FROM users');
$activeUsers = (int)val("SELECT COUNT(*) FROM users WHERE status='active'");
$registeredThisMonth = (int)val("SELECT COUNT(*) FROM users WHERE created_at >= DATE_FORMAT(CURDATE(), '%Y-%m-01')");
$usersByRole = all('SELECT role, COUNT(*) total FROM users GROUP BY role ORDER BY role');

$st = all('SELECT status, COUNT(*) c FROM equipment GROUP BY status');
$stMap = array_column($st, 'c', 'status');
$stLabels = ['Available', 'Borrowed', 'Maintenance', 'Retired'];
$stVals = array_map(function ($s) use ($stMap) { return (int)($stMap[$s] ?? 0); }, $stLabels);
$stCols = ['#22c55e', '#38bdf8', '#f59e0b', '#ef4444'];

$cats = all('SELECT c.category_name n, COUNT(e.equipment_id) c FROM categories c LEFT JOIN equipment e ON e.category_id=c.category_id GROUP BY c.category_id ORDER BY c.category_name');
$catCols = ['#22c55e', '#a3e635', '#2dd4bf', '#38bdf8', '#f59e0b', '#a78bfa', '#fb7185', '#facc15'];
$catTotal = max(1, array_sum(array_column($cats, 'c')));

$top = all('SELECT equipment_name, usage_count FROM equipment ORDER BY usage_count DESC LIMIT 8');
$topMax = max(1, (int)($top[0]['usage_count'] ?? 1));
$recent = all("SELECT b.*, e.equipment_name, u.full_name FROM borrow_records b JOIN equipment e ON e.equipment_id=b.equipment_id JOIN users u ON u.user_id=b.user_id ORDER BY b.created_at DESC LIMIT 6");

function rt($label, $v, $max) {
    $w = $max > 0 ? min(100, round($v / $max * 100)) : 0;
    return '<div class="rt-item"><div class="l"><span>' . $label . '</span><span class="mx">max ' . $max . '</span></div><b>' . $v . '</b><div class="bar"><i style="width:' . $w . '%"></i></div></div>';
}

page_start('Dashboard', 'admin', 'dashboard');
?>
<div class="dash">
  <section class="card glass stat">
    <div class="card-head"><h3>Borrow requests</h3><small>This month</small></div>
    <div class="big"><?= $thisMonth ?></div><?= delta($req) ?>
    <div class="spark" data-values="<?= e(json_encode($req)) ?>" data-color="#fb923c"></div>
  </section>
  <section class="card glass stat">
    <div class="card-head"><h3>Active borrows</h3><small>Now</small></div>
    <div class="big"><?= $borrowed ?></div><?= delta($rel) ?>
    <div class="spark" data-values="<?= e(json_encode($rel)) ?>" data-color="#22c55e"></div>
  </section>
  <section class="card glass stat">
    <div class="card-head"><h3>Maintenance spend</h3><small>This year</small></div>
    <div class="big"><?= money($mYear) ?></div><?= delta($mCost) ?>
    <div class="spark" data-values="<?= e(json_encode($mCost)) ?>" data-color="#38bdf8"></div>
  </section>

  <section class="card glass devices">
    <div class="card-head"><h3>Equipment status</h3><small>Today</small></div>
    <div class="donut-wrap">
      <div class="chart donut" data-values="<?= e(json_encode($stVals)) ?>" data-labels="<?= e(json_encode($stLabels)) ?>" data-colors="<?= e(json_encode($stCols)) ?>" data-sub="Total"></div>
      <div class="legend">
        <?php foreach ($stLabels as $i => $l): ?>
          <div><i style="background:<?= $stCols[$i] ?>"></i><?= $l ?><span><?= $stVals[$i] ?> / <?= $totalEq ? round($stVals[$i] / $totalEq * 100) : 0 ?>%</span></div>
        <?php endforeach; ?>
      </div>
    </div>
  </section>

  <section class="card glass realtime">
    <div class="card-head"><h3>Real-time data</h3></div>
    <?= rt('Available equipment', $available, $totalEq) ?>
    <?= rt('Borrowed now', $borrowed, $totalEq) ?>
    <?= rt('Pending requests', $pending, max($totalBr, 1)) ?>
    <?= rt('Overdue returns', $overdue, max($borrowed, 1)) ?>
  </section>

  <section class="card glass traffic">
    <div class="card-head"><h3>Borrow activity</h3>
      <div class="leg-inline"><span><i style="background:#a855f7"></i>Requests</span><span><i style="background:#22d3ee"></i>Returns</span></div></div>
    <div class="chart linechart" data-labels="<?= e(json_encode($ml)) ?>" data-series="<?= e(json_encode([['name' => 'Requests', 'color' => '#a855f7', 'values' => $req], ['name' => 'Returns', 'color' => '#22d3ee', 'values' => $ret]])) ?>"></div>
  </section>

  <section class="card glass social">
    <div class="card-head"><h3>Equipment by category</h3></div>
    <div class="donut-wrap">
      <div class="chart donut" data-values="<?= e(json_encode(array_map('intval', array_column($cats, 'c')))) ?>" data-labels="<?= e(json_encode(array_column($cats, 'n'))) ?>" data-colors="<?= e(json_encode($catCols)) ?>" data-sub="Items"></div>
      <div class="legend">
        <?php foreach ($cats as $i => $c): ?>
          <div><i style="background:<?= $catCols[$i % 8] ?>"></i><?= e($c['n']) ?><span><?= (int)$c['c'] ?> / <?= round($c['c'] / $catTotal * 100) ?>%</span></div>
        <?php endforeach; ?>
      </div>
    </div>
  </section>

  <section class="card glass bars">
    <div class="card-head"><h3>Fines issued per month</h3><small>Unpaid now: <b style="color:var(--warn)"><?= money($unpaid) ?></b></small></div>
    <div class="chart barchart" data-values="<?= e(json_encode($fineAmt)) ?>" data-labels="<?= e(json_encode($ml)) ?>" data-unit=" ₱"></div>
  </section>

  <section class="card glass pages">
    <div class="card-head"><h3>Most borrowed</h3><small>Times</small></div>
    <?php foreach ($top as $t): ?>
      <div class="pg"><div class="l"><span><?= e($t['equipment_name']) ?></span><span><?= (int)$t['usage_count'] ?></span></div>
      <div class="bar red"><i style="width:<?= round($t['usage_count'] / $topMax * 100) ?>%"></i></div></div>
    <?php endforeach; ?>
  </section>

  <section class="card glass recent">
    <div class="card-head"><h3>Recent borrow activity</h3><a class="btn ghost sm" href="<?= url('admin/borrows.php') ?>">View all</a></div>
    <div class="tbl-wrap"><table>
      <tr><th>Equipment</th><th>Borrower</th><th>Requested</th><th>Due</th><th>Status</th></tr>
      <?php foreach ($recent as $r): $od = $r['status'] === 'Borrowed' && days_overdue($r['expected_return_date']); ?>
        <tr><td><?= e($r['equipment_name']) ?></td><td><?= e($r['full_name']) ?></td><td><?= dt($r['created_at']) ?></td><td><?= dt($r['expected_return_date']) ?></td>
        <td><?= badge($r['status']) ?> <?= $od ? badge('Overdue') : '' ?></td></tr>
      <?php endforeach; ?>
    </table></div>
  </section>

  <section class="card glass user-analytics">
    <div class="card-head"><div><h3>User analytics</h3><small>Account registrations and active users</small></div><a class="btn ghost sm" href="<?= url('admin/users.php') ?>">Manage users</a></div>
    <div class="stats">
      <div class="user-count"><small>Total registered users</small><strong><?= $totalUsers ?></strong></div>
      <div class="user-count"><small>Active accounts</small><strong><?= $activeUsers ?></strong></div>
      <div class="user-count"><small>Registered this month</small><strong><?= $registeredThisMonth ?></strong></div>
      <?php foreach ($usersByRole as $roleCount): ?>
        <div class="user-count"><small><?= e(ucfirst($roleCount['role'])) ?> accounts</small><strong><?= (int)$roleCount['total'] ?></strong></div>
      <?php endforeach; ?>
    </div>
  </section>
</div>
<?php page_end();
