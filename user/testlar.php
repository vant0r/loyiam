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
<?php
// Color rotation for tickets (visible variety)
$ticket_palettes = ['', 'violet', 'cyan', 'amber', 'emerald', 'rose', 'indigo'];
?>
<div class="layout">
<?php render_sidebar('user', 'tests'); ?>
<main class="main">

  <div class="page-header-modern">
    <div>
      <div class="page-eyebrow"><?= icon('document', 12) ?> <?= lang()==='uz_cyrillic' ? "Имтиҳон тайёрлов" : "Imtihon tayyorlov" ?></div>
      <h1><?= t('tests') ?></h1>
      <div class="page-subtitle"><?= lang()==='uz_cyrillic' ? "Билет танлаб тестни бошланг" : "Bilet tanlab testni boshlang" ?></div>
    </div>
    <a href="/user/natijalar.php" class="btn btn-light btn-sm"><?= icon('chart', 14) ?> <?= t('results') ?></a>
  </div>

  <!-- In progress -->
  <?php if (!empty($inprogress)): ?>
  <div class="section-card mb-3" style="border-left:3px solid var(--warning)">
    <div class="section-card-head">
      <div class="section-card-title"><?= icon('clock', 16) ?> <?= lang()==='uz_cyrillic' ? "Давом этаётган тестлар" : "Davom etayotgan testlar" ?> <span class="count-pill"><?= count($inprogress) ?></span></div>
    </div>
    <div class="section-card-body" style="padding:14px">
      <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(min(260px, 100%), 1fr));gap:12px">
        <?php foreach ($inprogress as $r):
          $elapsed = time() - strtotime($r['started_at']);
          $remaining = max(0, ($r['time_minutes'] * 60) - $elapsed);
          $isExpired = $remaining <= 0;
          $rem_min = floor($remaining/60); $rem_sec = $remaining%60;
        ?>
          <div class="ticket-card amber" style="border-left:none">
            <div class="ticket-card-head">
              <div class="ticket-card-icon"><?= icon('clock', 18) ?></div>
              <span class="badge-soft <?= $isExpired?'danger':'warning' ?>"><?= $isExpired ? t('time_up') : t('in_progress') ?></span>
            </div>
            <div>
              <h3 class="ticket-card-title"><?= e($r['title'] ?? '—') ?></h3>
              <div class="ticket-card-meta">
                <span><?= icon('calendar', 12) ?> <?= date('d.m H:i', strtotime($r['started_at'])) ?></span>
                <?php if (!$isExpired): ?>
                  <span><?= icon('clock', 12) ?> <?= $rem_min ?>:<?= str_pad($rem_sec,2,'0',STR_PAD_LEFT) ?></span>
                <?php endif; ?>
              </div>
            </div>
            <a href="/user/test.php?attempt=<?= $r['id'] ?>" class="btn btn-primary btn-sm btn-block"><?= icon('play', 14) ?> <?= t('continue_test') ?></a>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
  <?php endif; ?>

  <!-- Available tickets -->
  <div class="section-card mb-3">
    <div class="section-card-head">
      <div class="section-card-title"><?= icon('ticket', 16) ?> <?= lang()==='uz_cyrillic' ? "Мавжуд билетлар" : "Mavjud biletlar" ?> <span class="count-pill"><?= count($tickets) ?></span></div>
      <span class="text-mute" style="font-size:12px"><?= lang()==='uz_cyrillic' ? "Билет устида босинг" : "Bilet ustida bosing" ?></span>
    </div>
    <div class="section-card-body" style="padding:16px">
      <?php if (empty($tickets)): ?>
        <div class="empty-state-v2">
          <div class="empty-state-v2-icon"><?= icon('ticket', 32) ?></div>
          <h3><?= lang()==='uz_cyrillic' ? "Билетлар йўқ" : "Biletlar yo'q" ?></h3>
          <p><?= lang()==='uz_cyrillic' ? "Админ кейинроқ билетларни қўшади" : "Admin keyinroq biletlarni qo'shadi" ?></p>
        </div>
      <?php else: ?>
        <div class="stagger" style="display:grid;grid-template-columns:repeat(auto-fill, minmax(min(220px, 100%), 1fr));gap:14px">
          <?php foreach ($tickets as $i => $tk): $palette = $ticket_palettes[$i % count($ticket_palettes)]; ?>
            <a href="/user/test.php?ticket=<?= $tk['id'] ?>" class="ticket-card <?= $palette ?>">
              <div class="ticket-card-head">
                <div class="ticket-card-icon"><?= icon('ticket', 22) ?></div>
                <span class="text-mute" style="font-size:11px;font-weight:700">#<?= $tk['ticket_number'] ?></span>
              </div>
              <h3 class="ticket-card-title"><?= e($tk['title_'.$lang_field]) ?></h3>
              <div class="ticket-card-meta">
                <span><?= icon('help', 12) ?> <?= $tk['questions_count'] ?> <?= lang()==='uz_cyrillic' ? "савол" : "savol" ?></span>
                <span><?= icon('clock', 12) ?> <?= $tk['time_minutes'] ?> <?= t('minutes') ?></span>
              </div>
              <div class="ticket-card-cta">
                <span><?= icon('play', 14) ?> <?= t('start_test') ?></span>
                <?= icon('arrow-right', 14) ?>
              </div>
            </a>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- Completed -->
  <div class="section-card">
    <div class="section-card-head">
      <div class="section-card-title"><?= icon('check-circle', 16) ?> <?= lang()==='uz_cyrillic' ? "Тугалланган тестлар" : "Tugallangan testlar" ?> <span class="count-pill"><?= count($completed) ?></span></div>
      <a href="/user/natijalar.php" class="chip"><?= t('view_all') ?> →</a>
    </div>
    <div class="section-card-body" style="padding:14px">
      <?php if (empty($completed)): ?>
        <div class="empty-state-v2">
          <div class="empty-state-v2-icon"><?= icon('document', 32) ?></div>
          <h3><?= t('no_tests') ?></h3>
          <p><?= t('first_test') ?></p>
        </div>
      <?php else: foreach (array_slice($completed, 0, 10) as $r):
        $p = (float)$r['score_percent'];
        $cls = $p>=80?'success':($p>=50?'warning':'danger');
        $circ = 2 * M_PI * 20;
      ?>
        <a href="/user/test-result.php?attempt=<?= $r['id'] ?>" class="result-card-modern">
          <div class="progress-circle" style="--pc-pct:<?= $p/100 ?>;--pc-color:var(--<?= $cls ?>);--pc-circ:<?= round($circ,2) ?>;width:48px;height:48px">
            <svg viewBox="0 0 48 48">
              <circle class="pc-track" cx="24" cy="24" r="20"/>
              <circle class="pc-fill" cx="24" cy="24" r="20"/>
            </svg>
            <div class="pc-text"><?= round($p) ?></div>
          </div>
          <div class="result-body-modern">
            <div class="result-title-modern">
              <?= e($r['title'] ?? '—') ?>
              <span class="badge-soft <?= $cls ?>"><?= round($p,1) ?>%</span>
            </div>
            <div class="result-meta-modern">
              <span><?= icon('calendar', 12) ?> <?= date('d.m.Y H:i', strtotime($r['finished_at'] ?? $r['started_at'])) ?></span>
              <span class="activity-meta-dot"></span>
              <span><?= $r['correct_answers'] ?>/<?= $r['total_questions'] ?></span>
            </div>
          </div>
          <span class="data-cell-actions">
            <span class="btn btn-light btn-sm btn-icon"><?= icon('arrow-right', 14) ?></span>
          </span>
        </a>
      <?php endforeach; endif; ?>
    </div>
  </div>

</main>
</div>
</body></html>
