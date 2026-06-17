<?php
require_once __DIR__ . '/../includes/auth.php';
require_admin();

$msg = ''; $err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check()) {
        $err = t('csrf_invalid');
    } else {
        $action = $_POST['action'] ?? '';
        $id = (int)($_POST['id'] ?? 0);

        if ($action === 'delete' && $id) {
            db()->execute("DELETE FROM tariffs WHERE id=?", [$id]);
            audit('tariff_deleted', "Tariff #$id", 'warning');
            $msg = t('deleted_success');
        }

        if ($action === 'add' || ($action === 'edit' && $id)) {
            $name_lat = Security::clean($_POST['name_latin'] ?? '', 100);
            $name_cyr = Security::clean($_POST['name_cyrillic'] ?? '', 100);
            if (!$name_cyr && $name_lat) $name_cyr = uz_latin_to_cyrillic($name_lat);

            $desc_lat = Security::clean($_POST['description_latin'] ?? '', 500);
            $desc_cyr = Security::clean($_POST['description_cyrillic'] ?? '', 500);
            if (!$desc_cyr && $desc_lat) $desc_cyr = uz_latin_to_cyrillic($desc_lat);

            $features_lat = Security::clean($_POST['features_latin'] ?? '', 1000);
            $features_cyr = Security::clean($_POST['features_cyrillic'] ?? '', 1000);
            if (!$features_cyr && $features_lat) $features_cyr = uz_latin_to_cyrillic($features_lat);

            $data = [
                $name_lat, $name_cyr, $desc_lat, $desc_cyr,
                (float)($_POST['price'] ?? 0),
                max(1, (int)($_POST['duration_days'] ?? 30)),
                $features_lat, $features_cyr,
                max(0, (int)($_POST['tests_per_day'] ?? 0)),
                !empty($_POST['is_popular']) ? 1 : 0,
                in_array($_POST['status'] ?? 'active', ['active','inactive']) ? $_POST['status'] : 'active',
                (int)($_POST['sort_order'] ?? 0),
            ];

            if (!$name_lat) {
                $err = t('fill_required');
            } elseif ($action === 'add') {
                db()->execute(
                    "INSERT INTO tariffs (name_latin, name_cyrillic, description_latin, description_cyrillic,
                    price, duration_days, features_latin, features_cyrillic, tests_per_day, is_popular, status, sort_order)
                    VALUES (?,?,?,?,?,?,?,?,?,?,?,?)", $data);
                audit('tariff_added', "Tariff: $name_lat");
                $msg = t('saved_success');
            } else {
                $data[] = $id;
                db()->execute(
                    "UPDATE tariffs SET name_latin=?, name_cyrillic=?, description_latin=?, description_cyrillic=?,
                    price=?, duration_days=?, features_latin=?, features_cyrillic=?, tests_per_day=?, is_popular=?, status=?, sort_order=?
                    WHERE id=?", $data);
                audit('tariff_updated', "Tariff #$id");
                $msg = t('updated_success');
            }
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
    <div class="page-title"><?= icon('gem', 28) ?> <?= t('tariffs') ?></div>
    <button class="btn btn-primary" onclick='openTariffModal({})'>
      <?= icon('plus', 16) ?> <?= t('add') ?>
    </button>
  </div>

  <?php if ($msg): ?><div class="alert alert-success"><?= icon('check-circle', 18) ?> <?= e($msg) ?></div><?php endif; ?>
  <?php if ($err): ?><div class="alert alert-danger"><?= icon('x-circle', 18) ?> <?= e($err) ?></div><?php endif; ?>

  <!-- Tariffs grid -->
  <?php if (empty($tariffs)): ?>
    <div class="card empty-state">
      <?= icon('gem', 64) ?>
      <h3 class="mt-2"><?= lang()==='uz_cyrillic' ? "Тарифлар йўқ" : "Tariflar yo'q" ?></h3>
    </div>
  <?php else: ?>
  <div class="grid-3 stagger">
    <?php foreach ($tariffs as $t):
      $features = explode('|', $t['features_'.$lang_field] ?? '');
    ?>
    <div class="card pricing-card <?= $t['is_popular']?'popular':'' ?> <?= $t['status']==='inactive'?'is-inactive':'' ?>" style="<?= $t['status']==='inactive'?'opacity:.6':'' ?>">
      <?php if ($t['is_popular']): ?>
        <div class="pricing-badge"><?= t('popular') ?></div>
      <?php endif; ?>
      <span class="badge badge-<?= $t['status']==='active'?'success':'mute' ?>" style="position:absolute;top:14px;right:14px">
        <?= e(t($t['status'])) ?>
      </span>
      <h3><?= e($t['name_'.$lang_field]) ?></h3>
      <p class="pricing-desc"><?= e($t['description_'.$lang_field]) ?></p>
      <div class="pricing-price">
        <?php if ($t['price']==0): ?><?= t('free') ?>
        <?php else: ?><?= money($t['price']) ?> <small><?= t('soum') ?></small><?php endif; ?>
      </div>
      <div class="text-mute" style="font-size:13px;margin-bottom:10px">
        <?= $t['duration_days'] ?> <?= t('days') ?> · <?= $t['tests_per_day']>=999?'∞':$t['tests_per_day'] ?> test/kun
      </div>
      <ul class="pricing-features" style="font-size:13px">
        <?php foreach (array_slice($features, 0, 4) as $f): if (trim($f)) echo '<li>'.e(trim($f)).'</li>'; endforeach; ?>
      </ul>
      <div class="flex gap-1 mt-3">
        <button class="btn btn-light btn-sm flex-1" onclick='openTariffModal(<?= json_encode($t, JSON_HEX_APOS|JSON_HEX_QUOT|JSON_UNESCAPED_UNICODE) ?>)'>
          <?= icon('edit', 12) ?> <?= t('edit') ?>
        </button>
        <form method="post" style="display:inline" onsubmit="return confirm('<?= t('confirm_delete') ?>')">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="delete">
          <input type="hidden" name="id" value="<?= $t['id'] ?>">
          <button class="btn btn-light btn-sm" style="color:var(--danger)"><?= icon('trash', 12) ?></button>
        </form>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</main>
</div>

<!-- Tariff modal -->
<div id="tariffModal" class="modal-backdrop">
  <div class="modal modal-lg">
    <div class="modal-header">
      <h3 class="modal-title" id="tariffModalTitle"><?= t('add') ?></h3>
      <button class="modal-close" data-modal-close>&times;</button>
    </div>
    <form method="post">
      <?= csrf_field() ?>
      <input type="hidden" name="action" id="t_action" value="add">
      <input type="hidden" name="id" id="t_id">

      <div class="modal-body">
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Nom (Lotin) <span style="color:var(--danger)">*</span></label>
            <input type="text" name="name_latin" id="t_nl" class="form-control" required maxlength="100">
          </div>
          <div class="form-group">
            <label class="form-label">Ном (Кирилл) <small class="text-mute">— bo'sh = avto</small></label>
            <input type="text" name="name_cyrillic" id="t_nc" class="form-control" maxlength="100">
          </div>
        </div>

        <div class="form-group">
          <label class="form-label">Tavsif (Lotin)</label>
          <textarea name="description_latin" id="t_dl" class="form-control" rows="2" maxlength="500"></textarea>
        </div>
        <div class="form-group">
          <label class="form-label">Тавсиф (Кирилл) <small class="text-mute">— bo'sh = avto</small></label>
          <textarea name="description_cyrillic" id="t_dc" class="form-control" rows="2" maxlength="500"></textarea>
        </div>

        <div class="form-row" style="grid-template-columns:1fr 1fr 1fr;gap:12px">
          <div class="form-group">
            <label class="form-label">Narx (so'm)</label>
            <input type="number" name="price" id="t_p" class="form-control" value="0" min="0" step="1000">
          </div>
          <div class="form-group">
            <label class="form-label">Muddat (kun)</label>
            <input type="number" name="duration_days" id="t_dd" class="form-control" value="30" min="1" max="365">
          </div>
          <div class="form-group">
            <label class="form-label">Test/kun</label>
            <input type="number" name="tests_per_day" id="t_tpd" class="form-control" value="999" min="0" max="999">
          </div>
        </div>

        <div class="form-group">
          <label class="form-label">Xususiyatlar (Lotin) <small class="text-mute">— | bilan ajrating</small></label>
          <textarea name="features_latin" id="t_fl" class="form-control" rows="3" maxlength="1000" placeholder="Cheksiz testlar|Barcha biletlar|Statistika"></textarea>
        </div>
        <div class="form-group">
          <label class="form-label">Хусусиятлар (Кирилл) <small class="text-mute">— bo'sh = avto</small></label>
          <textarea name="features_cyrillic" id="t_fc" class="form-control" rows="3" maxlength="1000"></textarea>
        </div>

        <div class="form-row" style="grid-template-columns:auto 1fr 1fr;gap:14px;align-items:end">
          <div class="form-group" style="margin-bottom:0">
            <label class="form-check" style="margin-top:18px">
              <input type="checkbox" name="is_popular" id="t_ip" value="1">
              <span>⭐ Popular</span>
            </label>
          </div>
          <div class="form-group" style="margin-bottom:0">
            <label class="form-label">Status</label>
            <select name="status" id="t_st" class="form-control">
              <option value="active">✓ Faol</option>
              <option value="inactive">⏸ Nofaol</option>
            </select>
          </div>
          <div class="form-group" style="margin-bottom:0">
            <label class="form-label">Tartib</label>
            <input type="number" name="sort_order" id="t_so" class="form-control" value="0">
          </div>
        </div>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-light" data-modal-close><?= t('cancel') ?></button>
        <button type="submit" class="btn btn-primary"><?= t('save') ?></button>
      </div>
    </form>
  </div>
</div>

<script>
function openTariffModal(t){
  const isEdit = !!t.id;
  document.getElementById('t_action').value = isEdit ? 'edit' : 'add';
  document.getElementById('t_id').value = t.id || '';
  document.getElementById('t_nl').value = t.name_latin || '';
  document.getElementById('t_nc').value = t.name_cyrillic || '';
  document.getElementById('t_dl').value = t.description_latin || '';
  document.getElementById('t_dc').value = t.description_cyrillic || '';
  document.getElementById('t_p').value = t.price || 0;
  document.getElementById('t_dd').value = t.duration_days || 30;
  document.getElementById('t_tpd').value = t.tests_per_day || 999;
  document.getElementById('t_fl').value = t.features_latin || '';
  document.getElementById('t_fc').value = t.features_cyrillic || '';
  document.getElementById('t_ip').checked = !!parseInt(t.is_popular || 0);
  document.getElementById('t_st').value = t.status || 'active';
  document.getElementById('t_so').value = t.sort_order || 0;
  document.getElementById('tariffModalTitle').textContent = isEdit ? '<?= t('edit') ?>' : '<?= t('add') ?>';
  openModal('tariffModal');
}
</script>
</body></html>
