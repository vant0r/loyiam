<?php
require_once __DIR__ . '/../includes/auth.php';
require_admin();

$msg = flash('msg');
$err = flash('err');
$lang_field = lang() === 'uz_cyrillic' ? 'cyrillic' : 'latin';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $redir = $_SERVER['REQUEST_URI'];
    if (!csrf_check()) { flash('err', 'CSRF xatosi'); header("Location: $redir"); exit; }

    $action = $_POST['action'] ?? '';
    $id = (int)($_POST['id'] ?? 0);

    try {
        if ($action === 'delete' && $id) {
            db()->execute("DELETE FROM blog_posts WHERE id=?", [$id]);
            flash('msg', t('deleted_success'));
        }
        elseif ($action === 'publish' && $id) {
            db()->execute("UPDATE blog_posts SET status='published' WHERE id=?", [$id]);
            flash('msg', 'Chop etildi');
        }
        elseif ($action === 'draft' && $id) {
            db()->execute("UPDATE blog_posts SET status='draft' WHERE id=?", [$id]);
            flash('msg', 'Draft\'ga olindi');
        }
        elseif ($action === 'add' || ($action === 'edit' && $id)) {
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

            $image = $_POST['old_image'] ?? null;
            if (!empty($_FILES['image']['name'])) {
                $up = Security::upload_image($_FILES['image'], 'blog');
                if ($up['ok']) { $image = $up['url']; @chmod(BASE_PATH . $up['url'], 0644); }
                else throw new Exception('Rasm: ' . $up['error']);
            }

            if ($action === 'add') {
                db()->execute(
                    "INSERT INTO blog_posts (title_latin, title_cyrillic, slug, excerpt_latin, excerpt_cyrillic,
                     content_latin, content_cyrillic, image, category, status)
                     VALUES (?,?,?,?,?,?,?,?,?,?)",
                    [$title_lat, $title_cyr, $slug, $excerpt_lat, $excerpt_cyr,
                     $content_lat, $content_cyr, $image, $category, $status]);
                flash('msg', t('saved_success'));
            } else {
                db()->execute(
                    "UPDATE blog_posts SET title_latin=?, title_cyrillic=?, slug=?, excerpt_latin=?, excerpt_cyrillic=?,
                     content_latin=?, content_cyrillic=?, image=?, category=?, status=? WHERE id=?",
                    [$title_lat, $title_cyr, $slug, $excerpt_lat, $excerpt_cyr,
                     $content_lat, $content_cyr, $image, $category, $status, $id]);
                flash('msg', t('updated_success'));
            }
        }
        else { throw new Exception('Notog\'ri amal'); }
    } catch (Throwable $e) {
        flash('err', 'Xatolik: ' . $e->getMessage());
    }
    header("Location: $redir"); exit;
}

$search = trim($_GET['q'] ?? '');
$where = "1=1"; $params = [];
if ($search) {
    $where .= " AND (title_latin LIKE ? OR title_cyrillic LIKE ?)";
    $params[] = "%$search%"; $params[] = "%$search%";
}
$posts = db()->fetchAll("SELECT * FROM blog_posts WHERE $where ORDER BY created_at DESC LIMIT 100", $params);

render_head(t('blog'));
?>
<div class="layout">
<?php render_sidebar('admin','blog'); ?>
<main class="main">
  <div class="page-header">
    <div class="page-title"><?= icon('edit', 28) ?> <?= t('blog') ?></div>
    <a href="/admin/blog-form.php" class="btn btn-primary">
      <?= icon('plus', 16) ?> <?= t('add') ?>
    </a>
  </div>

  <?php if ($msg): ?><div class="alert alert-success"><?= icon('check-circle', 18) ?> <?= e($msg) ?></div><?php endif; ?>
  <?php if (!empty($err)): ?><div class="alert alert-danger"><?= icon('x-circle', 18) ?> <?= e($err) ?></div><?php endif; ?>

  <form method="get" class="card mb-3" style="display:flex;gap:12px;align-items:end" data-no-loading>
    <div class="form-group flex-1" style="margin-bottom:0">
      <input type="text" name="q" class="form-control" value="<?= e($search) ?>" placeholder="<?= t('search') ?>...">
    </div>
    <button class="btn btn-primary"><?= icon('search', 14) ?></button>
  </form>

  <?php if (empty($posts)): ?>
    <div class="card empty-state"><?= icon('document', 64) ?><h3 class="mt-2">Postlar yo'q</h3></div>
  <?php else: ?>
  <div class="grid-3">
    <?php foreach ($posts as $p): ?>
    <div class="card" style="padding:0;overflow:hidden">
      <div style="aspect-ratio:16/9;background:linear-gradient(135deg,var(--primary-light),#fff);position:relative;display:flex;align-items:center;justify-content:center;font-size:48px;color:var(--primary)">
        <?php if (!empty($p['image'])): ?>
          <img src="<?= e($p['image']) ?>" style="width:100%;height:100%;object-fit:cover">
        <?php else: ?>📰<?php endif; ?>
        <span class="badge badge-<?= $p['status']==='published'?'success':'warning' ?>" style="position:absolute;top:10px;left:10px">
          <?= $p['status']==='published' ? '✓ Published' : '✎ Draft' ?>
        </span>
      </div>
      <div style="padding:18px">
        <?php if ($p['category']): ?><span class="badge badge-info"><?= e($p['category']) ?></span><?php endif; ?>
        <h4 style="font-size:16px;font-weight:700;margin:8px 0;line-height:1.4"><?= e($p['title_'.$lang_field]) ?></h4>
        <div class="text-mute" style="font-size:12px;margin-bottom:12px">
          <?= date('d.m.Y', strtotime($p['created_at'])) ?> · 👁 <?= (int)$p['views'] ?>
        </div>
        <div class="flex gap-1 flex-wrap">
          <button type="button" class="btn btn-light btn-sm" onclick='openEditModal(<?= json_encode($p, JSON_UNESCAPED_UNICODE | JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'>
            <?= icon('edit', 12) ?>
          </button>
          <a href="/blog-post.php?slug=<?= e($p['slug']) ?>" target="_blank" class="btn btn-light btn-sm"><?= icon('eye', 12) ?></a>
          <?php if ($p['status'] === 'draft'): ?>
            <form method="post" style="display:inline">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="publish">
              <input type="hidden" name="id" value="<?= $p['id'] ?>">
              <button class="btn btn-success btn-sm"><?= icon('check', 12) ?></button>
            </form>
          <?php else: ?>
            <form method="post" style="display:inline">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="draft">
              <input type="hidden" name="id" value="<?= $p['id'] ?>">
              <button class="btn btn-light btn-sm" style="color:var(--warning-dark)"><?= icon('eye-off', 12) ?></button>
            </form>
          <?php endif; ?>
          <form method="post" style="display:inline" onsubmit="return confirm('<?= t('confirm_delete') ?>')">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" value="<?= $p['id'] ?>">
            <button class="btn btn-light btn-sm" style="color:var(--danger)"><?= icon('trash', 12) ?></button>
          </form>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</main>
</div>

<div id="editModal" class="modal-backdrop">
  <div class="modal modal-xl">
    <div class="modal-header">
      <h3 class="modal-title" id="modalTitle"><?= t('add') ?></h3>
      <button type="button" class="modal-close" data-modal-close>&times;</button>
    </div>
    <form method="post" enctype="multipart/form-data" action="">
      <?= csrf_field() ?>
      <input type="hidden" name="action" id="m_action" value="add">
      <input type="hidden" name="id" id="m_id">
      <input type="hidden" name="old_image" id="m_old_image">

      <div class="modal-body">
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Title (Latin) *</label>
            <input type="text" name="title_latin" id="m_tl" class="form-control" required>
          </div>
          <div class="form-group">
            <label class="form-label">Title (Кирилл)</label>
            <input type="text" name="title_cyrillic" id="m_tc" class="form-control">
          </div>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Slug</label>
            <input type="text" name="slug" id="m_slug" class="form-control" pattern="[a-z0-9-]+" placeholder="avto">
          </div>
          <div class="form-group">
            <label class="form-label"><?= t('category') ?></label>
            <input type="text" name="category" id="m_cat" class="form-control">
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Excerpt (Latin)</label>
          <textarea name="excerpt_latin" id="m_el" class="form-control" rows="2" maxlength="500"></textarea>
        </div>
        <div class="form-group">
          <label class="form-label">Excerpt (Кирилл)</label>
          <textarea name="excerpt_cyrillic" id="m_ec" class="form-control" rows="2" maxlength="500"></textarea>
        </div>
        <div class="form-group">
          <label class="form-label">Content (Latin)</label>
          <textarea name="content_latin" id="m_cl" class="form-control" rows="6"></textarea>
        </div>
        <div class="form-group">
          <label class="form-label">Content (Кирилл)</label>
          <textarea name="content_cyrillic" id="m_cc" class="form-control" rows="6"></textarea>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Cover image</label>
            <input type="file" name="image" class="form-control" accept="image/*">
          </div>
          <div class="form-group">
            <label class="form-label">Status</label>
            <select name="status" id="m_st" class="form-control">
              <option value="draft">📝 Draft</option>
              <option value="published" selected>✓ Published</option>
            </select>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-light" data-modal-close><?= t('cancel') ?></button>
        <button type="submit" class="btn btn-primary"><?= icon('check', 14) ?> <?= t('save') ?></button>
      </div>
    </form>
  </div>
</div>

<script>
function openEditModal(p){
  document.getElementById('m_action').value = p.id ? 'edit' : 'add';
  document.getElementById('m_id').value     = p.id || '';
  document.getElementById('m_tl').value     = p.title_latin || '';
  document.getElementById('m_tc').value     = p.title_cyrillic || '';
  document.getElementById('m_slug').value   = p.slug || '';
  document.getElementById('m_cat').value    = p.category || '';
  document.getElementById('m_el').value     = p.excerpt_latin || '';
  document.getElementById('m_ec').value     = p.excerpt_cyrillic || '';
  document.getElementById('m_cl').value     = p.content_latin || '';
  document.getElementById('m_cc').value     = p.content_cyrillic || '';
  document.getElementById('m_st').value     = p.status || 'published';
  document.getElementById('m_old_image').value = p.image || '';
  document.getElementById('modalTitle').textContent = p.id ? '<?= t('edit') ?>' : '<?= t('add') ?>';
  openModal('editModal');
}
</script>
</body></html>
