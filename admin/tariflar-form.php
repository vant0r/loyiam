<?php
require_once __DIR__ . '/../includes/bootstrap.php';
auth_class();
require_admin();

$id = (int)($_GET['id'] ?? 0);
$isEdit = $id > 0;
$tariff = null;
$lang_field = lang() === 'uz_cyrillic' ? 'cyrillic' : 'latin';

if ($isEdit) {
    $tariff = db()->fetch("SELECT * FROM tariffs WHERE id = ?", [$id]);
    if (!$tariff) { flash('err', 'Topilmadi'); header('Location: /admin/tariflar.php'); exit; }
}

$msg = flash('msg');
$err = flash('err');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $back = $isEdit ? "/admin/tariflar-form.php?id=$id" : "/admin/tariflar-form.php";
    if (!csrf_check()) { flash('err', 'CSRF xatosi'); header("Location: $back"); exit; }

    try {
        $name_lat = Security::clean($_POST['name_latin'] ?? '', 100);
        $name_cyr = Security::clean($_POST['name_cyrillic'] ?? '', 100);
        if (!$name_cyr && $name_lat) $name_cyr = uz_latin_to_cyrillic($name_lat);
        if (!$name_lat) throw new Exception('Tarif nomi kerak');

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

        if ($isEdit) {
            $data[] = $id;
            db()->execute(
                "UPDATE tariffs SET name_latin=?, name_cyrillic=?, description_latin=?, description_cyrillic=?,
                price=?, duration_days=?, features_latin=?, features_cyrillic=?, tests_per_day=?, is_popular=?, status=?, sort_order=?
                WHERE id=?", $data);
            flash('msg', t('updated_success'));
        } else {
            db()->execute(
                "INSERT INTO tariffs (name_latin, name_cyrillic, description_latin, description_cyrillic,
                price, duration_days, features_latin, features_cyrillic, tests_per_day, is_popular, status, sort_order)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?)", $data);
            flash('msg', t('saved_success'));
        }
        header("Location: /admin/tariflar.php"); exit;
    } catch (Throwable $e) {
        flash('err', 'Xatolik: ' . $e->getMessage());
        header("Location: $back"); exit;
    }
}

render_head($isEdit ? t('edit') : t('add'));
?>
<div class="layout">
<?= panel_sidebar('admin', 'tariffs') ?>
<main class="main">
  <div class="page-header">
    <div>
      <a href="/admin/tariflar.php" class="text-soft" style="font-size:13px;display:inline-flex;align-items:center;gap:4px;text-decoration:none">
        <?= icon('arrow-left', 14) ?> <?= t('tariffs') ?>
      </a>
      <div class="page-title mt-1"><?= icon('gem', 28) ?> <?= $isEdit ? t('edit') : t('add') ?> <?= t('tariffs') ?></div>
    </div>
  </div>

  <?php if ($err): ?><div class="alert alert-danger"><?= icon('x-circle',18) ?> <?= e($err) ?></div><?php endif; ?>

  <form method="post" action="">
    <?= csrf_field() ?>

    <div class="card">
      <h3 style="font-size:16px;font-weight:700;margin-bottom:16px">📝 Asosiy ma'lumotlar</h3>
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Nom (Lotin) <span style="color:var(--danger)">*</span></label>
          <input type="text" name="name_latin" class="form-control" required maxlength="100"
                 value="<?= e($_POST['name_latin'] ?? $tariff['name_latin'] ?? '') ?>">
        </div>
        <div class="form-group">
          <label class="form-label">Ном (Кирилл) <small class="text-mute">— bo'sh = avto</small></label>
          <input type="text" name="name_cyrillic" class="form-control" maxlength="100"
                 value="<?= e($_POST['name_cyrillic'] ?? $tariff['name_cyrillic'] ?? '') ?>">
        </div>
      </div>
      <div class="form-group">
        <label class="form-label">Tavsif (Lotin)</label>
        <textarea name="description_latin" class="form-control" rows="2" maxlength="500"><?= e($_POST['description_latin'] ?? $tariff['description_latin'] ?? '') ?></textarea>
      </div>
      <div class="form-group">
        <label class="form-label">Тавсиф (Кирилл)</label>
        <textarea name="description_cyrillic" class="form-control" rows="2" maxlength="500"><?= e($_POST['description_cyrillic'] ?? $tariff['description_cyrillic'] ?? '') ?></textarea>
      </div>
    </div>

    <div class="card mt-3">
      <h3 style="font-size:16px;font-weight:700;margin-bottom:16px">💰 Narx va cheklov</h3>
      <div class="form-row" style="grid-template-columns:1fr 1fr 1fr;gap:12px">
        <div class="form-group">
          <label class="form-label">Narx (so'm)</label>
          <input type="number" name="price" class="form-control" min="0" step="1000"
                 value="<?= e($_POST['price'] ?? $tariff['price'] ?? '0') ?>">
        </div>
        <div class="form-group">
          <label class="form-label">Muddat (kun)</label>
          <input type="number" name="duration_days" class="form-control" min="1" max="365"
                 value="<?= e($_POST['duration_days'] ?? $tariff['duration_days'] ?? '30') ?>">
        </div>
        <div class="form-group">
          <label class="form-label">Test/kun</label>
          <input type="number" name="tests_per_day" class="form-control" min="0" max="999"
                 value="<?= e($_POST['tests_per_day'] ?? $tariff['tests_per_day'] ?? '999') ?>">
        </div>
      </div>
    </div>

    <div class="card mt-3">
      <h3 style="font-size:16px;font-weight:700;margin-bottom:16px">⭐ Xususiyatlar</h3>
      <div class="form-group">
        <label class="form-label">Xususiyatlar (Lotin) <small class="text-mute">— | bilan ajrating</small></label>
        <textarea name="features_latin" class="form-control" rows="3" maxlength="1000"
                  placeholder="Cheksiz testlar|Barcha biletlar|Statistika"><?= e($_POST['features_latin'] ?? $tariff['features_latin'] ?? '') ?></textarea>
      </div>
      <div class="form-group">
        <label class="form-label">Хусусиятлар (Кирилл)</label>
        <textarea name="features_cyrillic" class="form-control" rows="3" maxlength="1000"><?= e($_POST['features_cyrillic'] ?? $tariff['features_cyrillic'] ?? '') ?></textarea>
      </div>
    </div>

    <div class="card mt-3">
      <h3 style="font-size:16px;font-weight:700;margin-bottom:16px">⚙️ Sozlamalar</h3>
      <div class="form-row" style="grid-template-columns:auto 1fr 1fr;gap:14px;align-items:end">
        <div class="form-group" style="margin-bottom:0">
          <label class="form-check" style="margin-top:18px">
            <input type="checkbox" name="is_popular" value="1" <?= !empty($tariff['is_popular'])?'checked':'' ?>>
            <span>⭐ Popular</span>
          </label>
        </div>
        <div class="form-group" style="margin-bottom:0">
          <label class="form-label">Status</label>
          <select name="status" class="form-control">
            <option value="active"   <?= ($tariff['status']??'active')==='active'?'selected':'' ?>>✓ Faol</option>
            <option value="inactive" <?= ($tariff['status']??'')==='inactive'?'selected':'' ?>>⏸ Nofaol</option>
          </select>
        </div>
        <div class="form-group" style="margin-bottom:0">
          <label class="form-label">Tartib</label>
          <input type="number" name="sort_order" class="form-control" value="<?= e($tariff['sort_order'] ?? '0') ?>">
        </div>
      </div>
    </div>

    <div class="form-actions">
      <a href="/admin/tariflar.php" class="btn btn-light"><?= t('cancel') ?></a>
      <button type="submit" class="btn btn-primary"><?= icon('check', 16) ?> <?= t('save') ?></button>
    </div>
  </form>
</main>
</div>

<style>
.form-actions{display:flex;justify-content:flex-end;gap:10px;margin-top:24px;padding:18px 0}
@media(max-width:640px){.form-actions{flex-direction:column-reverse}.form-actions .btn{width:100%}}
</style>
<script><?= panel_js() ?></script>
</body></html>
