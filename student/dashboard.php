<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/models.php';
require_once __DIR__ . '/../includes/panel_layout.php';
boot_session();
require_role('student');
$u = current_user();

$todayTasks = student_today_tasks((int)$u['id']);
$week = student_week_stats((int)$u['id']);
$chart = student_week_chart((int)$u['id']);
$todayIdx = persian_day_index(date('Y-m-d'));
$todayDone = count(array_filter($todayTasks, fn($t)=>$t['is_done']));
$todayTotal = count($todayTasks);
$todayPct = $todayTotal ? round($todayDone/$todayTotal*100) : 0;

$hour = (int)date('H');
$greet = $hour<12 ? 'صبح بخیر' : ($hour<17 ? 'ظهر بخیر' : ($hour<20 ? 'عصر بخیر' : 'شب بخیر'));
$first = explode(' ', (string)$u['full_name'])[0];

$maxBar = max(1, max(array_map(fn($c)=>$c['total'],$chart)));

panel_start('خانه', jalali_date('now'), 'student', 'dashboard', ['student.css']);
?>
<!-- greeting -->
<div class="greet-card reveal in">
  <div class="between" style="align-items:flex-start">
    <div>
      <div class="gc-sub"><?= e($greet) ?> 👋</div>
      <h2><?= e($first) ?> عزیز</h2>
    </div>
    <span class="badge" style="background:rgba(12,21,18,.25);color:#0c1512;border:none"><?= icon('fire',15) ?> <?= fa_num($u['streak']) ?> روز پیاپی</span>
  </div>
  <div class="greet-progress">
    <div class="between" style="font-size:.85rem;font-weight:700;margin-bottom:6px"><span>پیشرفت امروز</span><span><?= fa_num($todayPct) ?>٪ · <?= fa_num($todayDone) ?>/<?= fa_num($todayTotal) ?></span></div>
    <div class="progress"><span data-w="<?= $todayPct ?>" style="width:0"></span></div>
  </div>
  <div class="mood-wrap">
    <span class="mood-label">حال امروزت چطوره؟</span>
    <div class="mood-row" id="moodRow">
      <?php foreach (['😄'=>'happy','🙂'=>'ok','😐'=>'meh','😴'=>'tired','😣'=>'stressed'] as $emo=>$key): ?>
      <button class="mood-btn <?= $u['mood']===$key?'active':'' ?>" data-mood="<?= $key ?>" type="button"><?= $emo ?></button>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<!-- ==== TODAY'S TASKS (most important — first) ==== -->
<div class="panel reveal" data-d="1" style="margin-bottom:18px">
  <div class="panel-head">
    <h3><?= icon('calendar',20) ?> تسک‌های امروز · <?= e(DAY_NAMES[$todayIdx]) ?></h3>
    <a href="<?= url('student/plan.php') ?>" class="btn btn-ghost btn-sm">هفته کامل <?= icon('arrow-left',15) ?></a>
  </div>
  <?php if (!$todayTasks): ?>
    <div class="empty-state"><div class="es-ico"><?= icon('inbox',30) ?></div><p>برای امروز تسکی ثبت نشده 🌱</p><p class="muted" style="font-size:.84rem">از یک روز استراحت لذت ببر یا برنامه‌ی کامل هفته را ببین.</p></div>
  <?php else: ?>
    <?php if ($todayDone < $todayTotal): ?>
    <div class="hint-bar"><?= icon('info',16) ?><span>روی <b>مربع کنار هر تسک</b> بزن تا تکمیلش کنی. <?= fa_num($todayTotal-$todayDone) ?> تسک مونده!</span></div>
    <?php else: ?>
    <div class="hint-bar done"><?= icon('check-circle',16) ?><span>عالیه! همه‌ی تسک‌های امروز رو زدی 🎉</span></div>
    <?php endif; ?>
    <div class="task-list" id="todayTasks">
      <?php foreach ($todayTasks as $t): ?>
        <?= student_task_card($t) ?>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<!-- ==== compact stats ==== -->
<div class="stat-cards">
  <div class="panel stat reveal" data-d="2"><span class="icon-tile"><?= icon('check-circle',24) ?></span><div><div class="v"><?= fa_num($week['done']) ?>/<?= fa_num($week['total']) ?></div><div class="k">تسک‌های هفته</div></div></div>
  <div class="panel stat reveal" data-d="3"><span class="icon-tile sage"><?= icon('target',24) ?></span><div><div class="v"><?= fa_num($week['percent']) ?>٪</div><div class="k">پیشرفت هفته</div></div></div>
  <div class="panel stat reveal" data-d="4"><span class="icon-tile" style="background:rgba(217,178,95,.14);color:var(--warn)"><?= icon('fire',24) ?></span><div><div class="v"><?= fa_num($u['streak']) ?></div><div class="k">استریک</div></div></div>
  <div class="panel stat reveal" data-d="5"><span class="icon-tile sage"><?= icon('trophy',24) ?></span><div><div class="v"><?= fa_num($todayPct) ?>٪</div><div class="k">امروز</div></div></div>
</div>

<!-- ==== week chart ==== -->
<div class="panel reveal" data-d="3">
  <div class="panel-head"><h3><?= icon('bar',20) ?> نمودار هفته</h3>
    <a href="<?= url('student/progress.php') ?>" class="btn btn-ghost btn-sm">گزارش <?= icon('arrow-left',15) ?></a></div>
  <div class="barchart">
    <?php foreach ($chart as $i=>$c): $h = round($c['done']/$maxBar*100); ?>
    <div class="bcol">
      <div class="bar <?= $i===$todayIdx?'gold':'' ?>" data-h="<?= $h ?>" style="height:0" data-tip="<?= fa_num($c['done']) ?>/<?= fa_num($c['total']) ?>"></div>
      <span class="blbl"><?= mb_substr($c['day'],0,3) ?></span>
    </div>
    <?php endforeach; ?>
  </div>
</div>

<?php require __DIR__ . '/_modals.php'; ?>

<script>
  window.API_TASKS = '<?= url('api/tasks.php') ?>';
  window.NOTIF_URL = '<?= url('api/notifications.php') ?>';
  window.NOTIF_READ_URL = '<?= url('api/notifications.php?read=1') ?>';
  window.API_MOOD = '<?= url('api/mood.php') ?>';
</script>
<?php
panel_end(['student.js']);

/* ---- student task card markup ---- */
function student_task_card(array $t): string {
    $done = (int)$t['is_done'];
    $color = $t['subj_color'] ?? '#6b8872';
    $meta = [];
    if ($t['target_count']!==null) {
        $dc = (int)$t['done_count']; $tc=(int)$t['target_count'];
        $meta[] = '<span class="st-prog st-prog-count" data-unit="'.e($t['target_unit']).'">'.fa_num($dc).'/'.fa_num($tc).' '.e($t['target_unit']).'</span>';
    } elseif ($t['duration_min']) {
        $meta[] = '<span class="st-prog">'.fa_num($t['duration_min']).' دقیقه</span>';
    }
    $typeLabel = TASK_TYPES[$t['task_type']]['label'] ?? $t['task_type'];
    $subjTag = $t['subj_name'] ? '<span class="st-subj" style="background:'.e($color).'22;color:'.e($color).'">'.e($t['subj_name']).'</span>' : '';
    $fb = $t['advisor_feedback'] ? '<div class="st-feedback"><span class="ico">'.icon('message',15).'</span><span><b>بازخورد مشاور:</b> '.e($t['advisor_feedback']).'</span></div>' : '';
    $noteBlock = $t['student_note']
      ? '<div class="st-note-box"><span class="st-note-text" data-raw="'.e($t['student_note']).'">'.icon('note',13).' '.e($t['student_note']).'</span> <button class="st-note-edit" data-note="'.(int)$t['id'].'">ویرایش</button></div>'
      : '<button class="st-note-btn" data-note="'.(int)$t['id'].'">'.icon('note',14).' افزودن یادداشت</button>';
    return '<div class="s-task '.($done?'done':'').'" data-id="'.(int)$t['id'].'" data-target="'.($t['target_count']!==null?(int)$t['target_count']:'').'" data-done="'.(int)$t['done_count'].'">'
      .'<label class="checkbox st-check"><input type="checkbox" '.($done?'checked':'').' data-toggle-task><span class="box">'.icon('check',14).'</span></label>'
      .'<div class="st-body">'
        .'<div class="st-title">'.e($t['title']).' '.$subjTag.'</div>'
        .'<div class="st-meta"><span class="badge" style="font-size:.7rem;padding:2px 8px">'.e($typeLabel).'</span>'.implode('',$meta).'</div>'
        .$noteBlock
        .$fb
      .'</div>'
    .'</div>';
}
