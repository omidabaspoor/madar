<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/models.php';
require_once __DIR__ . '/../includes/panel_layout.php';
boot_session();
require_role('student');
$u = current_user();

$weekStart = isset($_GET['week']) ? week_saturday($_GET['week']) : week_saturday();
$st = db()->prepare('SELECT * FROM plans WHERE student_id=? AND week_start=? AND status="published" LIMIT 1');
$st->execute([$u['id'], $weekStart]);
$plan = $st->fetch();

$prevWeek = date('Y-m-d', strtotime($weekStart.' -7 day'));
$nextWeek = date('Y-m-d', strtotime($weekStart.' +7 day'));
$todayIdx = persian_day_index(date('Y-m-d'));

$tasksByDay = [];
if ($plan) {
    $rows = db()->prepare('SELECT t.*, s.color subj_color, s.name subj_name FROM tasks t LEFT JOIN subjects s ON s.id=t.subject_id WHERE t.plan_id=? ORDER BY t.day_index, t.unit_index, t.sort_order, t.id');
    $rows->execute([$plan['id']]);
    foreach ($rows->fetchAll() as $t) { $tasksByDay[(int)$t['day_index']][] = $t; }
}

panel_start('برنامه من', $plan ? (jalali_date($weekStart) . ' تا ' . jalali_date(date('Y-m-d', strtotime($weekStart.' +6 day')))) : '', 'student', 'plan', ['student.css']);
?>
<div class="between mb-4 wrap gap-3">
  <div class="week-nav flex gap-2" style="align-items:center">
    <a href="?week=<?= $prevWeek ?>" class="btn btn-ghost btn-icon" data-tip="هفته قبل"><?= icon('chevron-right',18) ?></a>
    <span class="fw-700"><?= jalali_date($weekStart) ?></span>
    <a href="?week=<?= $nextWeek ?>" class="btn btn-ghost btn-icon" data-tip="هفته بعد"><?= icon('chevron-left',18) ?></a>
  </div>
  <?php if ($plan): $pp = plan_progress((int)$plan['id']); ?>
  <div class="flex gap-2 wrap">
    <a href="<?= url('student/plan_pdf.php?week='.$weekStart) ?>" target="_blank" class="btn btn-gold btn-sm"><?= icon('paperclip',14) ?> چاپ / ذخیره PDF</a>
    <span class="badge badge-sage"><?= icon('target',14) ?> <?= fa_num($pp['percent']) ?>٪ تکمیل (<?= fa_num($pp['done']) ?>/<?= fa_num($pp['total']) ?>)</span>
  </div>
  <?php endif; ?>
</div>

<?php if (!$plan): ?>
  <div class="panel"><div class="empty-state"><div class="es-ico"><?= icon('calendar',34) ?></div><p>برای این هفته برنامه‌ای منتشر نشده</p><p class="muted" style="font-size:.85rem">مشاورت به‌زودی برنامه‌ات را آماده می‌کند 🌱</p></div></div>
<?php else: ?>

<!-- day tabs -->
<div class="day-tabs">
  <?php foreach (DAY_NAMES as $di=>$dn):
    $cnt = count($tasksByDay[$di] ?? []);
    $isToday = $di===$todayIdx; ?>
  <button class="day-tab <?= $isToday?'active today':'' ?>" data-day="<?= $di ?>">
    <?= e($dn) ?>
    <span class="dt-num"><?= $cnt?fa_num($cnt).' تسک':'—' ?><?= $isToday?' · امروز':'' ?></span>
  </button>
  <?php endforeach; ?>
</div>

<?php foreach (DAY_NAMES as $di=>$dn): ?>
<div data-day-panel="<?= $di ?>" class="<?= $di===$todayIdx?'':'hidden' ?>">
  <?php $dayTasks = $tasksByDay[$di] ?? []; if (!$dayTasks): ?>
    <div class="panel"><div class="empty-state" style="padding:36px"><div class="es-ico"><?= icon('inbox',28) ?></div>برای <?= e($dn) ?> تسکی نیست</div></div>
  <?php else: ?>
  <div class="task-list">
    <?php foreach ($dayTasks as $t):
      $done=(int)$t['is_done']; $color=$t['subj_color']??'#6b8872';
      $typeLabel=TASK_TYPES[$t['task_type']]['label']??$t['task_type']; ?>
    <div class="s-task <?= $done?'done':'' ?>" data-id="<?= (int)$t['id'] ?>" data-target="<?= $t['target_count']!==null?(int)$t['target_count']:'' ?>" data-done="<?= (int)$t['done_count'] ?>">
      <label class="checkbox st-check"><input type="checkbox" <?= $done?'checked':'' ?> data-toggle-task><span class="box"><?= icon('check',14) ?></span></label>
      <div class="st-body">
        <div class="st-title"><?= e($t['title']) ?>
          <?php if($t['subj_name']):?><span class="st-subj" style="background:<?= e($color) ?>22;color:<?= e($color) ?>"><?= e($t['subj_name']) ?></span><?php endif;?>
        </div>
        <div class="st-meta">
          <span class="badge" style="font-size:.7rem;padding:2px 8px"><?= e($typeLabel) ?></span>
          <?php if($t['target_count']!==null):?><span class="st-prog st-prog-count" data-unit="<?= e($t['target_unit']) ?>"><?= fa_num($t['done_count']) ?>/<?= fa_num($t['target_count']) ?> <?= e($t['target_unit']) ?></span>
          <?php elseif($t['duration_min']):?><span class="st-prog"><?= fa_num($t['duration_min']) ?> دقیقه</span><?php endif;?>
          <?php if(!empty($t['source'])):?><span class="st-src"><?= icon('book',12) ?> <?= e($t['source']) ?></span><?php endif;?>
        </div>
        <?php if($t['student_note']):?>
          <div class="st-note-box"><span class="st-note-text" data-raw="<?= e($t['student_note']) ?>"><?= icon('note',13) ?> <?= e($t['student_note']) ?></span> <button class="st-note-edit" data-note="<?= (int)$t['id'] ?>">ویرایش</button></div>
        <?php else:?>
          <button class="st-note-btn" data-note="<?= (int)$t['id'] ?>"><?= icon('note',14) ?> افزودن یادداشت</button>
        <?php endif;?>
        <?php if($t['advisor_feedback']):?><div class="st-feedback"><span class="ico"><?= icon('message',15) ?></span><span><b>بازخورد مشاور:</b> <?= e($t['advisor_feedback']) ?></span></div><?php endif;?>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>
<?php endforeach; ?>
<?php endif; ?>

<?php require __DIR__ . '/_modals.php'; ?>
<script>
  window.API_TASKS='<?= url('api/tasks.php') ?>';
  window.NOTIF_URL='<?= url('api/notifications.php') ?>';
  window.NOTIF_READ_URL='<?= url('api/notifications.php?read=1') ?>';
</script>
<?php panel_end(['student.js']); ?>
