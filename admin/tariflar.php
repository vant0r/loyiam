<?php
require_once __DIR__ . '/../includes/functions.php';
require_admin();

$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $id = (int)($_POST['id'] ?? 0);

    if ($action === 'delete' && $id) {
        db()->execute("DELETE FROM tariffs WHERE id=?", [$id]);
        $msg = 'O\'chirildi';
    }
    if ($action === 'add' || $action === 'edit') {
        $data = [
            trim($_POST['name_latin']),
            trim($_POST['name_cyrillic']) ?: trim($_POST['name_latin']),
            trim($_POST['description_latin']),
            trim($_POST['description_cyrillic']),
            (float)$_POST['price'],
            (int)$_POST['duration_days'],
            trim($_POST['features_latin']),
            trim($_POST['features_cyrillic']),
            (int)($_POST['tests_per_day'] ?? 0),
            !empty($_POST['is_popular']) ? 1 : 0,
            $_POST['status'] ?? 'active',
            (int)($_POST['sort_order'] ?? 0),
        ];
        if ($action === 'add') {
            db()->execute("INSERT INTO tariffs (name_latin, name_cyrillic, description_latin, description_cyrillic,
                price, duration_days, features_latin, features_cyrillic, tests_per_day, is_popular, status, sort_order)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?)", $data);
            $msg = 'Qo\'shildi';
        } else {
            $data[] = $id;
            db()->execute("UPDATE tariffs SET name_latin=?, name_cyrillic=?, description_latin=?, description_cyrillic=?,
                price=?, duration_days=?, features_latin=?, features_cyrillic=?, tests_per_day=?, is_popular=?, status=?, sort_order=? WHERE id=?", $data);
            $msg = 'Yangilandi';
        }
    }
}

$tariffs = db()->fetchAll("SELECT * FROM tariffs ORDER BY sort_order, id");
$lang_field = lang() === 'uz_cyrillic' ? 'cyrillic' : 'latin';

render_head(t('tariffs'));
?>
<div class="layout">
<?php render_sidebar('admin','tariffs'); ?>
<main class="main">
  <div class="page-header">
    <div class="page-title">💎 <?= t('tariffs') ?></div>
    <button class="btn btn-primary" onclick='openModal({})'>+ <?= t('add') ?></button>
  </div>

  <?php if ($msg): ?><div class="alert alert-success"><?= e($msg) ?></div><?php endif; ?>

  <div class="card" style="padding:0">
    <div class="table-wrap" style="border:none;box-shadow:none">
      <table>
        <thead><tr><th>#</th><th><?= lang()==='uz_cyrillic' ? 'Номи' : 'Nomi' ?></th><th><?= lang()==='uz_cyrillic' ? 'Нархи' : 'Narxi' ?></th><th><?= lang()==='uz_cyrillic' ? 'Муддат' : 'Muddat' ?></th><th><?= lang()==='uz_cyrillic' ? 'Маҳсул' : 'Mash.' ?></th><th><?= t('status') ?></th><th><?= t('actions') ?></th></tr></thead>
        <tbody>
          <?php foreach ($tariffs as $t): ?>
          <tr>
            <td>#<?= $t['id'] ?></td>
            <td><strong><?= e($t['name_'.$lang_field]) ?></strong>
              <div style="font-size:12px;color:var(--text-mute)"><?= e(mb_substr($t['description_'.$lang_field],0,60)) ?></div>
            </td>
            <td><strong><?= $t['price']==0?t('free'):money($t['price']) ?></strong> <?= $t['price']>0?t('soum'):'' ?></td>
            <td><?= $t['duration_days'] ?> <?= lang()==='uz_cyrillic' ? 'кун' : 'kun' ?></td>
            <td><?= $t['is_popular']?'⭐':'—' ?></td>
            <td><span class="badge badge-<?= $t['status']==='active'?'success':'mute' ?>"><?= e($t['status']) ?></span></td>
            <td>
              <div class="flex" style="gap:4px">
                <button class="btn btn-light btn-sm" onclick='openModal(<?= json_encode($t) ?>)'>✎</button>
                <form method="post" style="display:inline" onsubmit="return confirm('Ochirilsinmi?')">
                  <input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $t['id'] ?>">
                  <button class="btn btn-light btn-sm" style="color:var(--danger)">🗑</button>
                </form>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</main>
</div>

<div id="modal" style="display:none;position:fixed;inset:0;background:rgba(15,23,42,.6);z-index:300;align-items:center;justify-content:center;padding:20px">
  <div class="card" style="max-width:680px;width:100%;max-height:92vh;overflow-y:auto">
    <div class="flex justify-between items-center mb-3"><h3 style="font-size:18px;font-weight:700" id="modalTitle"><?= t('add') ?></h3>
      <button onclick="document.getElementById('modal').style.display='none'" style="font-size:24px">×</button></div>
    <form method="post">
      <input type="hidden" name="action" id="m_action" value="add">
      <input type="hidden" name="id" id="m_id">
      <div class="grid-2" style="gap:14px">
        <div class="form-group"><label class="form-label">Name (Lotin) *</label><input type="text" name="name_latin" id="m_nl" class="form-control" required></div>
        <div class="form-group"><label class="form-label">Name (Кирилл)</label><input type="text" name="name_cyrillic" id="m_nc" class="form-control"></div>
      </div>
      <div class="form-group"><label class="form-label">Desc (Lotin)</label><textarea name="description_latin" id="m_dl" class="form-control" rows="2"></textarea></div>
      <div class="form-group"><label class="form-label">Desc (Кирилл)</label><textarea name="description_cyrillic" id="m_dc" class="form-control" rows="2"></textarea></div>
      <div class="grid-3" style="gap:10px">
        <div class="form-group"><label class="form-label">Price</label><input type="number" name="price" id="m_p" class="form-control" value="0"></div>
        <div class="form-group"><label class="form-label">Days</label><input type="number" name="duration_days" id="m_dd" class="form-control" value="30"></div>
        <div class="form-group"><label class="form-label">Tests/day</label><input type="number" name="tests_per_day" id="m_tpd" class="form-control" value="999"></div>
      </div>
      <div class="form-group"><label class="form-label">Features Lotin (| bilan ajrating)</label><textarea name="features_latin" id="m_fl" class="form-control" rows="2"></textarea></div>
      <div class="form-group"><label class="form-label">Features Кирилл (| билан ажратинг)</label><textarea name="features_cyrillic" id="m_fc" class="form-control" rows="2"></textarea></div>
      <div class="grid-3" style="gap:10px">
        <div class="form-group flex items-center" style="gap:8px;margin-top:24px">
          <input type="checkbox" name="is_popular" id="m_ip"><label for="m_ip">⭐ Popular</label>
        </div>
        <div class="form-group"><label class="form-label">Status</label>
          <select name="status" id="m_st" class="form-control"><option value="active">Active</option><option value="inactive">Inactive</option></select>
        </div>
        <div class="form-group"><label class="form-label">Order</label><input type="number" name="sort_order" id="m_so" class="form-control" value="0"></div>
      </div>
      <button class="btn btn-primary btn-block"><?= t('save') ?></button>
    </form>
  </div>
</div>

<script>
function openModal(t){
  document.getElementById('m_action').value = t.id ? 'edit' : 'add';
  document.getElementById('m_id').value = t.id || '';
  document.getElementById('m_nl').value = t.name_latin || '';
  document.getElementById('m_nc').value = t.name_cyrillic || '';
  document.getElementById('m_dl').value = t.description_latin || '';
  document.getElementById('m_dc').value = t.description_cyrillic || '';
  document.getElementById('m_p').value = t.price || 0;
  document.getElementById('m_dd').value = t.duration_days || 30;
  document.getElementById('m_tpd').value = t.tests_per_day || 999;
  document.getElementById('m_fl').value = t.features_latin || '';
  document.getElementById('m_fc').value = t.features_cyrillic || '';
  document.getElementById('m_ip').checked = !!parseInt(t.is_popular || 0);
  document.getElementById('m_st').value = t.status || 'active';
  document.getElementById('m_so').value = t.sort_order || 0;
  document.getElementById('modal').style.display = 'flex';
}
document.getElementById('modal').addEventListener('click', e => { if (e.target.id === 'modal') e.target.style.display = 'none'; });
</script>
</body></html>
