<?php
require_once __DIR__ . '/../includes/auth.php';
require_login();

$u = current_user();
$lang_field = lang() === 'uz_cyrillic' ? 'cyrillic' : 'latin';

// Davom etayotganlar
$inprogress = db()->fetchAll(
    "SELECT a.*, t.title_$lang_field title, t.questions_count, t.time_minutes
     FROM test_attempts a LEFT JOIN tickets t ON a.ticket_id=t.id
     WHERE a.user_id=? AND a.status='in_progress' ORDER BY a.started_at DESC",
    [$u['id']]);

// Tugallangan testlar
$completed = db()->fetchAll(
    "SELECT a.*, t.title_$lang_field title FROM test_attempts a
     LEFT JOIN tickets t ON a.ticket_id=t.id
     WHERE a.user_id=? AND a.status='completed'
     ORDER BY a.finished_at DESC LIMIT 30", [$u['id']]);

// Mavjud biletlar
$tickets = db()->fetchAll("SELECT * FROM tickets WHERE status='active' ORDER BY ticket_number ASC");

render_head(t('tests'));
?>
<div class="layout">
<?php render_sidebar('user', 'tests'); ?>
<main class="main">
  <div class="page-header">
    <div>
      <div class="page-title"><?= icon('document', 28) ?> <?= t('tests') ?></div>
      <div class="page-subtitle"><?= lang()==='uz_cyrillic' ? 'Билет танлаб тестни бошланг' : 'Bilet tanlab testni boshlang' ?></div>
    </div>
  </div>

  <!-- Davom etayotganlar -->
  <?php if (!empty($inprogress)): ?>
  <div class="card mb-3" style="border-left:4px solid var(--warning)">
    <h3 style="font-size:18px;font-weight:700;margin-bottom:14px;display:flex;align-items:center;gap:10px">
      <?= icon('clock', 22) ?> <?= lang()==='uz_cyrillic' ? "Давом этаётган тестлар" : "Davom etayotgan testlar" ?>
    </h3>
    <div class="grid-3">
      <?php foreach ($inprogress as $r):
        $elapsed = time() - strtotime($r['started_at']);
        $remaining = max(0, ($r['time_minutes'] * 60) - $elapsed);
        $isExpired = $remaining <= 0;
      ?>
      <div class="card card-hover" style="background:var(--bg-soft);padding:18px">
        <div class="flex justify-between items-start mb-2">
          <strong><?= e($r['title'] ?? '—') ?></strong>
          <span class="badge badge-<?= $isExpired?'danger':'warning' ?>">
            <?= $isExpired ? t('time_up') : t('in_progress') ?>
          </span>
        </div>
        <div style="font-size:13px;color:var(--text-soft);margin-bottom:12px">
          <?= date('d.m.Y H:i', strtotime($r['started_at'])) ?>
        </div>
        <a href="/user/test.php?attempt=<?= $r['id'] ?>" class="btn btn-primary btn-sm btn-block">
          <?= icon('play', 14) ?> <?= t('continue_test') ?>
        </a>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>

  <!-- Yangi test boshlash -->
  <div class="card mb-3">
    <h3 style="font-size:18px;font-weight:700;margin-bottom:14px;display:flex;align-items:center;gap:10px">
      <?= icon('plus', 22) ?> <?= t('start_test') ?>
    </h3>
    <?php if (empty($tickets)): ?>
      <div class="empty-state">
        <?= icon('ticket', 64) ?>
        <h3><?= lang()==='uz_cyrillic' ? 'Билетлар йўқ' : 'Biletlar yo\'q' ?></h3>
        <p><?= lang()==='uz_cyrillic' ? 'Админ кейинроқ билетларни қўшади' : 'Admin keyinroq biletlarni qo\'shadi' ?></p>
      </div>
    <?php else: ?>
    <div class="grid-4 stagger">
      <?php foreach ($tickets as $tk): ?>
      <a href="/user/test.php?ticket=<?= $tk['id'] ?>" class="card card-hover" style="text-align:center;padding:24px;text-decoration:none;color:inherit;display:block">
        <div style="width:56px;height:56px;background:linear-gradient(135deg,var(--primary),var(--secondary));color:#fff;
                    border-radius:14px;display:flex;align-items:center;justify-content:center;margin:0 auto 14px">
          <?= icon('ticket', 28) ?>
        </div>
        <div style="font-weight:700;font-size:16px;margin-bottom:4px"><?= e($tk['title_'.$lang_field]) ?></div>
        <div style="color:var(--text-soft);font-size:12px">
          <?= $tk['questions_count'] ?> <?= lang()==='uz_cyrillic' ? 'савол' : 'savol' ?>
          ·
          <?= $tk['time_minutes'] ?> <?= t('minutes') ?>
        </div>
      </a>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>

  <!-- Tugallanganlar -->
  <div class="card" style="padding:0">
    <div style="padding:20px 24px;border-bottom:1px solid var(--border);display:flex;justify-content:space-between;align-items:center">
      <h3 style="font-size:18px;font-weight:700;display:flex;align-items:center;gap:10px">
        <?= icon('check-circle', 22) ?> <?= lang()==='uz_cyrillic' ? "Тугалланган тестлар" : "Tugallangan testlar" ?>
      </h3>
      <span class="badge badge-info"><?= count($completed) ?></span>
    </div>
    <?php if (empty($completed)): ?>
      <div class="empty-state">
        <?= icon('document', 64) ?>
        <h3><?= t('no_tests') ?></h3>
        <p><?= t('first_test') ?></p>
      </div>
    <?php else: ?>
    <div class="table-wrap table-flat">
      <table>
        <thead>
          <tr>
            <th>#</th>
            <th><?= lang()==='uz_cyrillic' ? 'Билет' : 'Bilet' ?></th>
            <th><?= t('date') ?></th>
            <th><?= lang()==='uz_cyrillic' ? 'Балл' : 'Ball' ?></th>
            <th>%</th>
            <th><?= t('actions') ?></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($completed as $i => $r): ?>
          <tr>
            <td><?= $i+1 ?></td>
            <td><strong><?= e($r['title'] ?? '—') ?></strong></td>
            <td><?= date('d.m.Y H:i', strtotime($r['finished_at'] ?? $r['started_at'])) ?></td>
            <td><strong><?= $r['correct_answers'] ?>/<?= $r['total_questions'] ?></strong></td>
            <td>
              <?php
                $p = (float)$r['score_percent'];
                $cls = $p>=80?'success':($p>=50?'warning':'danger');
              ?>
              <span class="badge badge-<?= $cls ?>"><?= $p ?>%</span>
            </td>
            <td>
              <a href="/user/test-result.php?attempt=<?= $r['id'] ?>" class="btn btn-light btn-sm">
                <?= icon('eye', 14) ?> <?= t('view_details') ?>
              </a>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
  </div>
</main>
</div>
</body></html>
