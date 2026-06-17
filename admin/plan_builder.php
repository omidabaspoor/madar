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
ensure_special_defaults_for_empty_plan((int)$plan['id']);
$grid = tasks_grid((int)$plan['id']);
$progress = plan_progress((int)$plan['id']);
$subjects = all_subjects();
$copyTargets = array_values(array_filter(advisor_students((int)$u['id'], 'active'), fn($s) => (int)$s['id'] !== $studentId));

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
  <button class="btn btn-ghost btn-sm" id="seedSpecialBtn"><?= icon('sparkles',15) ?> واحد ویژه پیش‌فرض</button>
  <span class="badge" id="copyHint" style="display:none"><?= icon('copy',13) ?> برای کپی، خانه مقصد را بزنید</span>
  <span class="badge <?= $plan['status']==='published'?'badge-sage':'badge-gold' ?>" id="statusBadge">
    <?= $plan['status']==='published' ? icon('check-circle',13).' منتشر شده' : icon('edit',13).' پیش‌نویس' ?>
  </span>
  <button class="btn btn-gold btn-sm" id="publishBtn" data-status="<?= e($plan['status']) ?>" style="margin-right:auto">
    <?= $plan['status']==='published' ? icon('edit',15).' بازگشت به پیش‌نویس' : icon('rocket',15).' انتشار برنامه' ?>
  </button>
</div>

<?php if ($copyTargets): ?>
<div class="copy-plan-panel">
  <div>
    <b><?= icon('copy',16) ?> کپی کل برنامه هفته</b>
    <span class="muted">همه تسک‌های این هفته را برای دانش‌آموز دیگر می‌سازد (در مقصد به حالت پیش‌نویس).</span>
  </div>
  <div class="copy-plan-actions">
    <select class="select" id="copyTargetStudent">
      <option value="">انتخاب دانش‌آموز مقصد…</option>
      <?php foreach ($copyTargets as $cs): ?>
      <option value="<?= (int)$cs['id'] ?>"><?= e($cs['full_name']) ?><?= $cs['field']?' · '.e($cs['field']):'' ?></option>
      <?php endforeach; ?>
    </select>
    <button class="btn btn-sage btn-sm" id="copyToStudentBtn" type="button"><?= icon('copy',15) ?> انتقال برنامه</button>
  </div>
</div>
<?php endif; ?>

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
        <div class="quick-presets" id="quickPresets">
          <button type="button" data-preset="test20">۲۰ تست</button>
          <button type="button" data-preset="test40">۴۰ تست</button>
          <button type="button" data-preset="test60">۶۰ تست</button>
          <button type="button" data-preset="study60">مطالعه ۶۰د</button>
          <button type="button" data-preset="study90">مطالعه ۹۰د</button>
          <button type="button" data-preset="review45">مرور ۴۵د</button>
          <button type="button" data-preset="textbook">کتاب درسی</button>
          <button type="button" data-preset="desc">تشریحی</button>
          <button type="button" data-preset="reading">روزخوانی ۱س</button>
          <button type="button" data-preset="exam">آزمونک ۵۰د</button>
        </div>
      </div>
      <div class="field">
        <label for="f_title">عنوان تسک <span class="muted">(اختیاری)</span></label>
        <input class="input" id="f_title" name="title" placeholder="اگر خالی بماند، خودکار بر اساس نوع/درس پر می‌شود">
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
            <?php foreach (['تست','سوال','صفحه','درسنامه','دقیقه','ساعت','فصل','مبحث'] as $tu): ?>
            <option><?= e($tu) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
      <div class="grid gap-3" style="grid-template-columns:1fr 1fr">
        <div class="field">
          <label for="f_dur">مدت</label>
          <select class="select" id="f_dur" name="duration_min">
            <option value="">اختیاری</option>
            <?php foreach ([30=>'۳۰ دقیقه',45=>'⭐ ۴۵ دقیقه',50=>'۵۰ دقیقه',60=>'⭐ ۶۰ دقیقه / ۱ ساعت',75=>'۷۵ دقیقه',90=>'⭐ ۹۰ دقیقه',120=>'۱۲۰ دقیقه / ۲ ساعت',150=>'۱۵۰ دقیقه',180=>'۱۸۰ دقیقه / ۳ ساعت'] as $dm=>$dl): ?>
            <option value="<?= $dm ?>" <?= in_array($dm,[45,60,90],true)?'class="hot-duration" data-hot="1"':'' ?>><?= e($dl) ?></option>
            <?php endforeach; ?>
          </select>
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

/* ---- seed default special unit once for empty plans ---- */
function ensure_special_defaults_for_empty_plan(int $planId): void {
    $marker = '[special_defaults_seeded]';
    $p = db()->prepare('SELECT * FROM plans WHERE id=? LIMIT 1');
    $p->execute([$planId]);
    $plan = $p->fetch();
    if (!$plan) return;
    if (str_contains((string)($plan['note'] ?? ''), $marker)) return;
    $cnt = db()->prepare('SELECT COUNT(*) FROM tasks WHERE plan_id=?');
    $cnt->execute([$planId]);
    if ((int)$cnt->fetchColumn() > 0) return;
    $ins = db()->prepare('INSERT INTO tasks (plan_id,student_id,title,task_type,day_index,unit_index,target_count,target_unit,duration_min,priority,sort_order) VALUES (?,?,?,?,?,?,?,?,?,?,?)');
    for ($day=0; $day<7; $day++) {
        $ins->execute([$planId,$plan['student_id'],'روزخوانی','reading',$day,8,1,'ساعت',60,'normal',1]);
        $ins->execute([$planId,$plan['student_id'],'آزمونک','exam',$day,8,50,'دقیقه',50,'normal',2]);
    }
    $note = trim((string)($plan['note'] ?? ''));
    $note = $note ? ($note . "\n" . $marker) : $marker;
    db()->prepare('UPDATE plans SET note=? WHERE id=?')->execute([$note,$planId]);
}

/* ---- helper: pill markup (server-side, mirrors JS) ---- */
function builder_task_pill(array $t): string {
    $done = (int)$t['is_done'] ? 'done' : '';
    $meta = [];
    if ($t['target_count']!==null) $meta[] = fa_num($t['target_count']).' '.e($t['target_unit']);
    if ($t['duration_min']) $meta[] = fa_num($t['duration_min']).' دقیقه';
    $metaStr = implode(' · ', $meta);
    $type = TASK_TYPES[$t['task_type']]['label'] ?? $t['task_type'];
    return '<div class="task-pill type-'.e($t['task_type']).' '.$done.'" draggable="true" data-id="'.(int)$t['id'].'"'
        .' data-json="'.e(json_encode([
            'id'=>(int)$t['id'],'title'=>$t['title'],'description'=>$t['description'],'task_type'=>$t['task_type'],
            'day_index'=>(int)$t['day_index'],'unit_index'=>(int)$t['unit_index'],
            'target_count'=>$t['target_count']!==null?(int)$t['target_count']:'','target_unit'=>$t['target_unit'],
            'duration_min'=>$t['duration_min']!==null?(int)$t['duration_min']:'','priority'=>$t['priority'],
            'subject_id'=>$t['subject_id']!==null?(int)$t['subject_id']:'',
        ], JSON_UNESCAPED_UNICODE)).'">'
        .'<button class="tp-copy" data-copy title="کپی">'.icon('copy',12).'</button>'
        .'<button class="tp-del" data-del>'.icon('close',12).'</button>'
        .'<span class="tp-title">'.e($t['title']).'</span>'
        .($metaStr?'<span class="tp-meta">'.$metaStr.'</span>':'')
        .'<span class="tp-type">'.e($type).'</span>'
        .'</div>';
}
