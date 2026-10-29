<?php
require __DIR__ . '/../includes/layout.php';
require_role(['admin']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = post('action'); $id = (int)post('id'); $name = post('category_name');
    if ($act === 'save') {
        if ($name === '') flash('Category name is required.', 'err');
        elseif (val('SELECT COUNT(*) FROM categories WHERE category_name=? AND category_id<>?', [$name, $id])) flash('That category already exists.', 'err');
        elseif ($id) { q('UPDATE categories SET category_name=?, description=? WHERE category_id=?', [$name, post('description'), $id]); flash('Category updated.'); }
        else { q('INSERT INTO categories (category_name, description) VALUES (?,?)', [$name, post('description')]); flash('Category added.'); }
    } elseif ($act === 'delete' && $id) { q('DELETE FROM categories WHERE category_id=?', [$id]); flash('Category deleted (its equipment is now uncategorised).'); }
    redirect('admin/categories.php');
}
$rows = all('SELECT c.*, COUNT(e.equipment_id) items FROM categories c LEFT JOIN equipment e ON e.category_id=c.category_id GROUP BY c.category_id ORDER BY c.category_name');
page_start('Categories', 'admin', 'categories');
?>
<section class="card glass">
  <div class="card-head"><h3>All categories</h3><button class="btn" data-dialog="catDlg" data-heading="Add category"><?= icon('plus', 16) ?> Add category</button></div>
  <div class="tbl-wrap"><table>
    <tr><th>Name</th><th>Description</th><th>Items</th><th>Created</th><th></th></tr>
    <?php foreach ($rows as $r): ?>
      <tr><td><b><?= e($r['category_name']) ?></b></td><td><?= e($r['description']) ?></td><td><?= (int)$r['items'] ?></td><td><?= dt($r['created_at']) ?></td>
      <td><div class="act">
        <a class="btn ghost sm" href="detail.php?type=category&id=<?= (int)$r['category_id'] ?>">View details</a> 
        <button class="btn ghost sm" data-dialog="catDlg" data-heading="Edit category" data-fill="<?= e(json_encode($r)) ?>"><?= icon('edit', 14) ?></button>
        <form method="post" data-confirm="Delete this category?"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $r['category_id'] ?>"><button class="btn danger sm"><?= icon('trash', 14) ?></button></form>
      </div></td></tr>
    <?php endforeach; ?>
  </table></div>
</section>
<dialog id="catDlg"><form method="post"><h3 data-title>Add category</h3><?= csrf_field() ?><input type="hidden" name="action" value="save"><input type="hidden" name="id">
  <div class="fg"><div class="full"><label>Name *</label><input name="category_name" required></div><div class="full"><label>Description</label><input name="description"></div></div>
  <div class="row end" style="margin-top:16px"><button type="button" class="btn ghost" data-close>Cancel</button><button class="btn">Save</button></div>
</form></dialog>
<?php page_end();
