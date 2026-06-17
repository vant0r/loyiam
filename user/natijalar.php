<?php
require_once __DIR__ . '/../includes/functions.php';
require_login();

$u = current_user();
$lang_field = lang() === 'uz_cyrillic' ? 'cyrillic' : 'latin';

$from = $_GET['from'] ?? '';
$to   = $_GET['to']   ?? '';
$tid  = (int)($_GET['ticket'] ?? 0);

$where = "WHERE a.user_id = ?";
$params = [$u['id']];
if ($from) { $where .= " AND DATE(a.started_at) >= ?"; $params[] = $from; }
if ($to)   { $where .= " AND DATE(a.started_at) <= ?"; $params[] = $to; }
if ($tid)  { $where .= " AND a.ticket_id = ?"; $params[] = $tid; }

$results = db()->fetchAll(
    "SELECT a.*, t.title_$lang_field title
     FROM test_attempts a LEFT JOIN tickets t ON a.ticket_id=t.id
     $where ORDER BY a.started_at DESC LIMIT 100", $params);

$tickets = db()->fetchAll("SELECT * FROM tickets ORDER BY ticket_number");

// Umumiy
$total_attempts = count($results);
$total_correct = array_sum(array_column($results, 'correct_answers'));
$total_questions = array_sum(array_column($results, 'total_questions'));
$avg_percent = $total_questions ? round($total_correct / $total_questions * 100, 1) : 0;

render_head(t('results'));
?>
<div class="layout">
<?php render_sidebar('user', 'results'); ?>
<main class="main">
  <div class="page-header">
    <div class="page-title"><?= t('results') ?></div>
  </div>

  <!-- Umumiy stat -->
  <div class="grid-4 mb-3">
    <div class="stat-card"><div class="icon">📝</div><div class="value"><?= $total_attempts ?></div><div class="label"><?= lang()==='uz_cyrillic' ? 'Жами уринишлар' : 'Jami urinishlar' ?></div></div>
    <div class="stat-card"><div class="icon" style="background:#D1FAE5;color:#065F46">✓</div><div class="value"><?= $total_correct ?></div><div class="label"><?= lang()==='uz_cyrillic' ? 'Тўғри' : 'To\'g\'ri' ?></div></div>
    <div class="stat-card"><div class="icon" style="background:#FEE2E2;color:#991B1B">✕</div><div class="value"><?= $total_questions - $total_correct ?></div><div class="label"><?= lang()==='uz_cyrillic' ? 'Хато' : 'Xato' ?></div></div>
    <div class="stat-card"><div class="icon" style="background:#FEF3C7;color:#92400E">%</div><div class="value"><?= $avg_percent ?>%</div><div class="label"><?= lang()==='uz_cyrillic' ? 'Ўртача' : 'O\'rtacha' ?></div></div>
  </div>

  <!-- Filter -->
  <form method="get" class="card mb-3" style="display:flex;gap:12px;flex-wrap:wrap;align-items:end">
    <div class="form-group" style="margin-bottom:0">
      <label class="form-label"><?= lang()==='uz_cyrillic' ? 'Дан' : 'Dan' ?></label>
      <input type="date" name="from" value="<?= e($from) ?>" class="form-control">
    </div>
    <div class="form-group" style="margin-bottom:0">
      <label class="form-label"><?= lang()==='uz_cyrillic' ? 'Гача' : 'Gacha' ?></label>
      <input type="date" name="to" value="<?= e($to) ?>" class="form-control">
    </div>
    <div class="form-group" style="margin-bottom:0;min-width:180px">
      <label class="form-label"><?= lang()==='uz_cyrillic' ? 'Билет' : 'Bilet' ?></label>
      <select name="ticket" class="form-control">
        <option value="">— <?= lang()==='uz_cyrillic' ? 'Барчаси' : 'Barchasi' ?></option>
        <?php foreach ($tickets as $t): ?>
          <option value="<?= $t['id'] ?>" <?= $tid==$t['id']?'selected':'' ?>><?= e($t['title_'.$lang_field]) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <button class="btn btn-primary"><?= lang()==='uz_cyrillic' ? 'Филтрлаш' : 'Filtrlash' ?></button>
  </form>

  <!-- Ro'yxat -->
  <div class="card" style="padding:0">
    <div class="table-wrap" style="border:none;box-shadow:none">
      <table>
        <thead><tr><th>#</th><th><?= lang()==='uz_cyrillic' ? 'Билет' : 'Bilet' ?></th><th><?= t('date') ?></th><th><?= lang()==='uz_cyrillic' ? 'Балл' : 'Ball' ?></th><th>%</th><th><?= t('status') ?></th><th></th></tr></thead>
        <tbody>
          <?php if (empty($results)): ?>
            <tr><td colspan="7" class="text-center" style="padding:40px;color:var(--text-soft)"><?= lang()==='uz_cyrillic' ? 'Натижалар топилмади' : 'Natijalar topilmadi' ?></td></tr>
          <?php else: foreach ($results as $i => $r): ?>
          <tr>
            <td><?= $i+1 ?></td>
            <td><?= e($r['title'] ?? '—') ?></td>
            <td><?= date('d.m.Y H:i', strtotime($r['started_at'])) ?></td>
            <td><strong><?= $r['correct_answers'] ?>/<?= $r['total_questions'] ?></strong></td>
            <td>
              <?php
                $p = (float)$r['score_percent'];
                $cls = $p>=80?'success':($p>=50?'warning':'danger');
              ?>
              <span class="badge badge-<?= $cls ?>"><?= $p ?>%</span>
            </td>
            <td>
              <?= $r['status']==='completed' ? '<span class="badge badge-success">'.t('completed').'</span>' : '<span class="badge badge-warning">'.t('in_progress').'</span>' ?>
            </td>
            <td><a href="#" class="btn btn-light btn-sm"><?= lang()==='uz_cyrillic' ? 'Батафсил' : 'Batafsil' ?></a></td>
          </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</main>
</div>
</body></html>
