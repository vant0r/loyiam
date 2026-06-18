<?php
require_once __DIR__ . '/../includes/bootstrap.php';
auth_class();
require_admin();

$id = (int)($_GET['id'] ?? 0);
$isEdit = $id > 0;
$post = null;

if ($isEdit) {
    $post = db()->fetch("SELECT * FROM blog_posts WHERE id = ?", [$id]);
    if (!$post) { flash('err', 'Topilmadi'); header('Location: /admin/blog.php'); exit; }
}

$msg = flash('msg');
$err = flash('err');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $back = $isEdit ? "/admin/blog-form.php?id=$id" : "/admin/blog-form.php";
    if (!csrf_check()) { flash('err', 'CSRF xatosi'); header("Location: $back"); exit; }

    try {
        $title_lat = Security::clean($_POST['title_latin'] ?? '', 250);
        $title_cyr = Security::clean($_POST['title_cyrillic'] ?? '', 250);
        if (!$title_cyr && $title_lat) $title_cyr = uz_latin_to_cyrillic($title_lat);
        if (!$title_lat) throw new Exception('Sarlavha kerak');

        $excerpt_lat = Security::clean($_POST['excerpt_latin'] ?? '', 500);
        $excerpt_cyr = Security::clean($_POST['excerpt_cyrillic'] ?? '', 500);
        if (!$excerpt_cyr && $excerpt_lat) $excerpt_cyr = uz_latin_to_cyrillic($excerpt_lat);

        $content_lat = $_POST['content_latin'] ?? '';
        $content_cyr = $_POST['content_cyrillic'] ?? '';
        if (!$content_cyr && $content_lat) $content_cyr = uz_latin_to_cyrillic($content_lat);

        $slug    = Security::clean($_POST['slug'] ?? '', 250) ?: strtolower(preg_replace('/[^a-z0-9]+/i', '-', $title_lat));
        $category = Security::clean($_POST['category'] ?? '', 100);
        $status   = in_array($_POST['status'] ?? '', ['draft','published']) ? $_POST['status'] : 'draft';

        $image = $post['image'] ?? null;
        if (!empty($_FILES['image']['name'])) {
            $up = Security::upload_image($_FILES['image'], 'blog');
            if ($up['ok']) { $image = $up['url']; @chmod(BASE_PATH . $up['url'], 0644); }
            else throw new Exception('Rasm: ' . $up['error']);
        }

        if ($isEdit) {
            db()->execute(
                "UPDATE blog_posts SET title_latin=?, title_cyrillic=?, slug=?, excerpt_latin=?, excerpt_cyrillic=?,
                 content_latin=?, content_cyrillic=?, image=?, category=?, status=? WHERE id=?",
                [$title_lat, $title_cyr, $slug, $excerpt_lat, $excerpt_cyr,
                 $content_lat, $content_cyr, $image, $category, $status, $id]);
            flash('msg', t('updated_success'));
        } else {
            db()->execute(
                "INSERT INTO blog_posts (title_latin, title_cyrillic, slug, excerpt_latin, excerpt_cyrillic,
                 content_latin, content_cyrillic, image, category, status)
                 VALUES (?,?,?,?,?,?,?,?,?,?)",
                [$title_lat, $title_cyr, $slug, $excerpt_lat, $excerpt_cyr,
                 $content_lat, $content_cyr, $image, $category, $status]);
            flash('msg', t('saved_success'));
        }
        header("Location: /admin/blog.php"); exit;
    } catch (Throwable $e) {
        flash('err', 'Xatolik: ' . $e->getMessage());
        header("Location: $back"); exit;
    }
}

render_head($isEdit ? t('edit') : t('add'));
?>
<div class="layout">
<?= panel_sidebar('admin', 'blog') ?>
<main class="main">
  <div class="page-header">
    <div>
      <a href="/admin/blog.php" class="text-soft" style="font-size:13px;display:inline-flex;align-items:center;gap:4px;text-decoration:none">
        <?= icon('arrow-left', 14) ?> <?= t('blog') ?>
      </a>
      <div class="page-title mt-1"><?= icon('edit', 28) ?> <?= $isEdit ? t('edit') : t('add') ?> <?= t('blog') ?></div>
    </div>
  </div>

  <?php if ($err): ?><div class="alert alert-danger"><?= icon('x-circle',18) ?> <?= e($err) ?></div><?php endif; ?>

  <form method="post" enctype="multipart/form-data" action="">
    <?= csrf_field() ?>

    <div class="card">
      <h3 style="font-size:16px;font-weight:700;margin-bottom:16px">🖼️ Cover image</h3>
      <div class="image-up" id="imageUp">
        <?php if (!empty($post['image'])): ?>
          <img id="imagePrev" src="<?= e($post['image']) ?>">
        <?php else: ?>
          <div id="imagePrev" class="img-placeholder">📰 Rasm tanlang</div>
        <?php endif; ?>
        <div class="image-overlay"><?= icon('upload', 32) ?> <strong>Tanlash</strong></div>
        <input type="file" name="image" accept="image/*" id="imageInput" hidden>
      </div>
    </div>

    <div class="card mt-3">
      <h3 style="font-size:16px;font-weight:700;margin-bottom:16px">📝 Asosiy</h3>
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Title (Lotin) <span style="color:var(--danger)">*</span></label>
          <input type="text" name="title_latin" class="form-control" required maxlength="250"
                 value="<?= e($post['title_latin'] ?? '') ?>">
        </div>
        <div class="form-group">
          <label class="form-label">Title (Кирилл)</label>
          <input type="text" name="title_cyrillic" class="form-control" maxlength="250"
                 value="<?= e($post['title_cyrillic'] ?? '') ?>">
        </div>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Slug</label>
          <input type="text" name="slug" class="form-control" pattern="[a-z0-9-]+" placeholder="avto-sample"
                 value="<?= e($post['slug'] ?? '') ?>">
        </div>
        <div class="form-group">
          <label class="form-label">Kategoriya</label>
          <input type="text" name="category" class="form-control" maxlength="100"
                 value="<?= e($post['category'] ?? '') ?>">
        </div>
      </div>
    </div>

    <div class="card mt-3">
      <h3 style="font-size:16px;font-weight:700;margin-bottom:16px">📄 Excerpt (qisqacha)</h3>
      <div class="form-group">
        <label class="form-label">Excerpt (Lotin)</label>
        <textarea name="excerpt_latin" class="form-control" rows="2" maxlength="500"><?= e($post['excerpt_latin'] ?? '') ?></textarea>
      </div>
      <div class="form-group">
        <label class="form-label">Excerpt (Кирилл)</label>
        <textarea name="excerpt_cyrillic" class="form-control" rows="2" maxlength="500"><?= e($post['excerpt_cyrillic'] ?? '') ?></textarea>
      </div>
    </div>

    <div class="card mt-3">
      <h3 style="font-size:16px;font-weight:700;margin-bottom:16px">📚 Asosiy matn</h3>
      <div class="form-group">
        <label class="form-label">Content (Lotin)</label>
        <textarea name="content_latin" class="form-control" rows="10"><?= e($post['content_latin'] ?? '') ?></textarea>
      </div>
      <div class="form-group">
        <label class="form-label">Content (Кирилл)</label>
        <textarea name="content_cyrillic" class="form-control" rows="10"><?= e($post['content_cyrillic'] ?? '') ?></textarea>
      </div>
    </div>

    <div class="card mt-3">
      <div class="form-group">
        <label class="form-label">Status</label>
        <select name="status" class="form-control">
          <option value="draft"     <?= ($post['status']??'')==='draft'?'selected':'' ?>>📝 Draft</option>
          <option value="published" <?= ($post['status']??'published')==='published'?'selected':'' ?>>✓ Published</option>
        </select>
      </div>
    </div>

    <div class="form-actions">
      <a href="/admin/blog.php" class="btn btn-light"><?= t('cancel') ?></a>
      <button type="submit" class="btn btn-primary"><?= icon('check', 16) ?> <?= t('save') ?></button>
    </div>
  </form>
</main>
</div>

<style>
.form-actions{display:flex;justify-content:flex-end;gap:10px;margin-top:24px;padding:18px 0}
@media(max-width:640px){.form-actions{flex-direction:column-reverse}.form-actions .btn{width:100%}}

.image-up{position:relative;aspect-ratio:16/9;border:2px dashed var(--border);border-radius:var(--r-lg);
  overflow:hidden;cursor:pointer;background:var(--bg-soft)}
.image-up:hover{border-color:var(--primary)}
.image-up img{width:100%;height:100%;object-fit:cover}
.img-placeholder{display:flex;align-items:center;justify-content:center;height:100%;font-size:24px;color:var(--text-mute)}
.image-overlay{position:absolute;inset:0;background:rgba(15,23,42,.5);color:#fff;display:flex;flex-direction:column;
  align-items:center;justify-content:center;gap:6px;opacity:0;transition:opacity .25s}
.image-up:hover .image-overlay{opacity:1}
</style>
<script>
(function(){
  const up = document.getElementById('imageUp');
  const inp = document.getElementById('imageInput');
  const prev = document.getElementById('imagePrev');
  if (!up) return;
  up.addEventListener('click', () => inp.click());
  inp.addEventListener('change', () => {
    if (inp.files[0]) {
      const r = new FileReader();
      r.onload = e => {
        if (prev.tagName === 'IMG') prev.src = e.target.result;
        else {
          const img = document.createElement('img');
          img.id = 'imagePrev';
          img.src = e.target.result;
          prev.replaceWith(img);
        }
      };
      r.readAsDataURL(inp.files[0]);
    }
  });
})();
</script>
<script><?= panel_js() ?></script>
</body></html>
