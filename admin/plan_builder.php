<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/models.php';
require_once __DIR__ . '/../includes/panel_layout.php';
boot_session();
require_role('advisor','admin');
$u = current_user();

$studentId = (int)($_GET['student'] ?? 0);
$student = get_user($studentId);
if (!$student || $student['role'] !== 'student') { flash('error','دانش‌آموز یافت نشد'); redirect('admin/students.php'); }

// هفته (شنبه)
$weekStart = isset($_GET['week']) ? week_saturday($_GET['week']) : week_saturday();
$plan = find_or_create_plan($studentId, (int)$u['id'], $weekStart);
$grid = tasks_grid((int)$plan['id']);
$progress = plan_progress((int)$plan['id']);
$subjects = all_subjects();

$prevWeek = date('Y-m-d', strtotime($weekStart.' -7 day'));
$nextWeek = date('Y-m-d', strtotime($weekStart.' +7 day'));
$weekEnd  = date('Y-m-d', strtotime($weekStart.' +6 day'));

panel_start('برنامه‌ریز هفتگی', '', 'admin', 'plans', ['builder.css']);
?>
<div class="builder-top">
  <div class="builder-student">
    <a href="<?= url('admin/students.php') ?>" class="btn btn-ghost btn-icon" data-tip="بازگشت"><?= icon('arrow-right',18) ?></a>
    <span class="u-ava"><?= e(avatar_letters($student['full_name'])) ?></span>
    <div>
      <div style="font-weight:800;font-size:1.05rem"><?= e($student['full_name']) ?></div>
      <div class="muted" style="font-size:.8rem"><?= e($student['field'] ?: '') ?> <?= $student['grade']?'· '.e($student['grade']):'' ?></div>
    </div>
  </div>
  <div class="builder-toolbar">
    <span class="save-status" id="saveStatus"><?= icon('check-circle',16) ?> ذخیره خودکار فعال</span>
    <div class="week-nav">
      <a href="?student=<?= $studentId ?>&week=<?= $prevWeek ?>" class="btn btn-ghost btn-icon" data-tip="هفته قبل"><?= icon('chevron-right',18) ?></a>
      <span class="wk"><?= jalali_date($weekStart) ?> تا <?= jalali_date($weekEnd) ?></span>
      <a href="?student=<?= $studentId ?>&week=<?= $nextWeek ?>" class="btn btn-ghost btn-icon" data-tip="هفته بعد"><?= icon('chevron-left',18) ?></a>
    </div>
  </div>
</div>

<div class="flex gap-2 wrap mb-4">
  <button class="btn btn-ghost btn-sm" id="copyWeekBtn"><?= icon('copy',15) ?> کپی از هفته قبل</button>
  <span class="badge <?= $plan['status']==='published'?'badge-sage':'badge-gold' ?>" id="statusBadge">
    <?= $plan['status']==='published' ? icon('check-circle',13).' منتشر شده' : icon('edit',13).' پیش‌نویس' ?>
  </span>
  <button class="btn btn-gold btn-sm" id="publishBtn" data-status="<?= e($plan['status']) ?>" style="margin-right:auto">
    <?= $plan['status']==='published' ? icon('edit',15).' بازگشت به پیش‌نویس' : icon('rocket',15).' انتشار برنامه' ?>
  </button>
</div>

<!-- grid -->
<div class="plan-wrap">
  <table class="plan-grid" id="planGrid" data-plan="<?= (int)$plan['id'] ?>">
    <thead>
      <tr>
        <th class="day-col">روز / واحد</th>
        <?php foreach (UNIT_NAMES as $ui=>$un): ?>
        <th class="<?= $ui===8?'special':'' ?>"><?= e($un) ?></th>
        <?php endforeach; ?>
      </tr>
    </thead>
    <tbody>
      <?php foreach (DAY_NAMES as $di=>$dn): ?>
      <tr>
        <td class="day-cell">
          <?= e($dn) ?>
          <span class="dnum"><?= jalali_date(date('Y-m-d', strtotime($weekStart." +$di day"))) ?></span>
          <button class="btn-icon btn btn-ghost btn-sm" style="margin-top:6px;width:26px;height:26px" data-clear-day="<?= $di ?>" data-tip="پاک‌کردن روز"><?= icon('trash',13) ?></button>
        </td>
        <?php foreach (UNIT_NAMES as $ui=>$un): ?>
        <td class="cell <?= $ui===8?'special':'' ?>" data-day="<?= $di ?>" data-unit="<?= $ui ?>">
          <div class="cell-tasks">
          <?php foreach (($grid[$di][$ui] ?? []) as $t): ?>
            <?= builder_task_pill($t) ?>
          <?php endforeach; ?>
          </div>
          <div class="cell-add"><?= icon('plus',18) ?></div>
        </td>
        <?php endforeach; ?>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<div class="plan-summary">
  <div class="ps-item"><span class="icon-tile sage" style="width:38px;height:38px"><?= icon('list',18) ?></span><div><div class="v" id="sumTotal"><?= fa_num($progress['total']) ?></div><div class="k">کل تسک‌ها</div></div></div>
  <div class="ps-item"><span class="icon-tile" style="width:38px;height:38px"><?= icon('check-circle',18) ?></span><div><div class="v" id="sumDone"><?= fa_num($progress['done']) ?></div><div class="k">انجام‌شده</div></div></div>
  <div class="ps-item" style="flex:1;min-width:200px"><div style="flex:1;width:100%"><div class="between" style="font-size:.78rem;margin-bottom:5px"><span class="k">پیشرفت دانش‌آموز</span><span class="v" id="sumPct" style="font-size:.95rem"><?= fa_num($progress['percent']) ?>٪</span></div><div class="progress"><span id="sumBar" style="width:<?= $progress['percent'] ?>%"></span></div></div></div>
</div>

<!-- ===== task editor modal ===== -->
<div class="modal-backdrop" id="taskModal">
  <div class="modal">
    <div class="modal-head"><h3 id="taskModalTitle">افزودن تسک</h3><button class="modal-close" data-close><?= icon('close',18) ?></button></div>
    <form id="taskForm">
      <input type="hidden" name="id" id="taskId">
      <input type="hidden" name="day_index" id="taskDay">
      <input type="hidden" name="unit_index" id="taskUnit">
      <input type="hidden" name="task_type" id="taskType" value="study">
      <div class="field">
        <label>نوع تسک</label>
        <div class="type-grid" id="typeGrid">
          <?php foreach (TASK_TYPES as $k=>$tt): ?>
          <div class="type-opt <?= $k==='study'?'active':'' ?>" data-type="<?= $k ?>">
            <span class="icon-tile <?= in_array($k,['test','exam'])?'':'sage' ?>"><?= icon($tt['icon'],18) ?></span>
            <div class="t"><?= e($tt['label']) ?></div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
      <div class="field">
        <label for="f_title">عنوان تسک *</label>
        <input class="input" id="f_title" name="title" placeholder="مثلاً زیست‌شناسی فصل ۴" required>
      </div>
      <div class="field">
        <label for="f_subject">درس (اختیاری)</label>
        <select class="select" id="f_subject" name="subject_id">
          <option value="">— بدون درس —</option>
          <?php foreach ($subjects as $s): ?>
          <option value="<?= (int)$s['id'] ?>"><?= e($s['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="grid gap-3" style="grid-template-columns:1.4fr 1fr">
        <div class="field">
          <label for="f_target">مقدار هدف</label>
          <input class="input" id="f_target" name="target_count" type="number" min="0" inputmode="numeric" placeholder="مثلاً ۴۰">
        </div>
        <div class="field">
          <label for="f_unit">واحد</label>
          <select class="select" id="f_unit" name="target_unit">
            <?php foreach (['تست','صفحه','درسنامه','دقیقه','ساعت','فصل','مبحث'] as $tu): ?>
            <option><?= e($tu) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
      <div class="grid gap-3" style="grid-template-columns:1fr 1fr">
        <div class="field">
          <label for="f_dur">مدت (دقیقه)</label>
          <input class="input" id="f_dur" name="duration_min" type="number" min="0" inputmode="numeric" placeholder="اختیاری">
        </div>
        <div class="field">
          <label for="f_prio">اولویت</label>
          <select class="select" id="f_prio" name="priority">
            <option value="normal">عادی</option>
            <option value="high">مهم</option>
            <option value="low">کم‌اهمیت</option>
          </select>
        </div>
      </div>
      <div class="field">
        <label for="f_desc">توضیحات (اختیاری)</label>
        <textarea class="input" id="f_desc" name="description" rows="2" placeholder="توضیح کوتاه…"></textarea>
      </div>
      <div class="flex gap-3 mt-2">
        <button type="submit" class="btn btn-gold" style="flex:1"><?= icon('check',16) ?> ذخیره تسک</button>
        <button type="button" class="btn btn-ghost" id="deleteTaskBtn" style="display:none;color:var(--danger)"><?= icon('trash',16) ?></button>
      </div>
    </form>
  </div>
</div>

<script>
  window.API_TASKS = '<?= url('api/tasks.php') ?>';
  window.NOTIF_URL = '<?= url('api/notifications.php') ?>';
  window.NOTIF_READ_URL = '<?= url('api/notifications.php?read=1') ?>';
  window.TASK_TYPES = <?= json_encode(array_map(fn($k)=>['label'=>TASK_TYPES[$k]['label']], array_combine(array_keys(TASK_TYPES),array_keys(TASK_TYPES))), JSON_UNESCAPED_UNICODE) ?>;
</script>
<?php
panel_end(['builder.js']);

/* ---- helper: pill markup (server-side, mirrors JS) ---- */
function builder_task_pill(array $t): string {
    $done = (int)$t['is_done'] ? 'done' : '';
    $meta = [];
    if ($t['target_count']!==null) $meta[] = fa_num($t['target_count']).' '.e($t['target_unit']);
    if ($t['duration_min']) $meta[] = fa_num($t['duration_min']).' دقیقه';
    $metaStr = implode(' · ', $meta);
    $type = TASK_TYPES[$t['task_type']]['label'] ?? $t['task_type'];
    return '<div class="task-pill '.$done.'" data-id="'.(int)$t['id'].'"'
        .' data-json="'.e(json_encode([
            'id'=>(int)$t['id'],'title'=>$t['title'],'description'=>$t['description'],'task_type'=>$t['task_type'],
            'target_count'=>$t['target_count']!==null?(int)$t['target_count']:'','target_unit'=>$t['target_unit'],
            'duration_min'=>$t['duration_min']!==null?(int)$t['duration_min']:'','priority'=>$t['priority'],
            'subject_id'=>$t['subject_id']!==null?(int)$t['subject_id']:'',
        ], JSON_UNESCAPED_UNICODE)).'">'
        .'<button class="tp-del" data-del>'.icon('close',12).'</button>'
        .'<span class="tp-title">'.e($t['title']).'</span>'
        .($metaStr?'<span class="tp-meta">'.$metaStr.'</span>':'')
        .'<span class="tp-type">'.e($type).'</span>'
        .'</div>';
}
