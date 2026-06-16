<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/models.php';
require_once __DIR__ . '/../includes/panel_layout.php';
boot_session();
require_role('advisor','admin');
$u = current_user();

$studentId = (int)($_GET['student'] ?? 0);

// اگر دانش‌آموز مشخص نشده: لیست رتبه‌بندی
if (!$studentId) {
    $students = advisor_students((int)$u['id'], 'active');
    usort($students, fn($a,$b)=> ($b['done_tasks']<=>$a['done_tasks']));
    panel_start('گزارش‌ها', 'رتبه‌بندی و تحلیل', 'admin', 'reports', ['student.css']);
    ?>
    <div class="panel">
      <div class="panel-head"><h3><?= icon('trophy',20) ?> رتبه‌بندی دانش‌آموزان</h3></div>
      <?php if(!$students):?><div class="empty-state"><div class="es-ico"><?= icon('users',30) ?></div>دانش‌آموز فعالی نیست</div>
      <?php else:?>
      <table class="tbl">
        <thead><tr><th>#</th><th>دانش‌آموز</th><th>تسک انجام‌شده</th><th>استریک</th><th>پیشرفت</th><th></th></tr></thead>
        <tbody>
        <?php foreach($students as $i=>$s): $pct=$s['total_tasks']?round($s['done_tasks']/$s['total_tasks']*100):0; ?>
          <tr>
            <td><span class="badge <?= $i<3?'badge-gold':'' ?>"><?= fa_num($i+1) ?></span></td>
            <td><div class="u-row"><span class="u-ava <?= $i==0?'gold':'' ?>"><?= e(avatar_letters($s['full_name'])) ?></span><span style="font-weight:700"><?= e($s['full_name']) ?></span></div></td>
            <td><?= fa_num($s['done_tasks']) ?> / <?= fa_num($s['total_tasks']) ?></td>
            <td><span class="badge badge-gold"><?= icon('fire',13) ?> <?= fa_num($s['streak']) ?></span></td>
            <td style="min-width:150px"><div class="between" style="gap:10px"><div class="progress" style="flex:1"><span data-w="<?= $pct ?>" style="width:0"></span></div><span style="font-size:.82rem;font-weight:700"><?= fa_num($pct) ?>٪</span></div></td>
            <td><a href="?student=<?= (int)$s['id'] ?>" class="btn btn-ghost btn-sm">جزئیات <?= icon('arrow-left',14) ?></a></td>
          </tr>
        <?php endforeach;?>
        </tbody>
      </table>
      <?php endif;?>
    </div>
    <?php
    panel_end();
    exit;
}

// گزارش یک دانش‌آموز
$student = get_user($studentId);
if (!$student || $student['role']!=='student') { flash('error','دانش‌آموز یافت نشد'); redirect('admin/reports.php'); }

$weekStart = isset($_GET['week']) ? week_saturday($_GET['week']) : week_saturday();
$prevWeek = date('Y-m-d', strtotime($weekStart.' -7 day'));
$nextWeek = date('Y-m-d', strtotime($weekStart.' +7 day'));

$p = db()->prepare('SELECT * FROM plans WHERE student_id=? AND week_start=? LIMIT 1');
$p->execute([$studentId,$weekStart]); $plan=$p->fetch();

$tasksByDay = [];
if ($plan) {
    $rows = db()->prepare('SELECT t.*, s.name subj_name, s.color subj_color FROM tasks t LEFT JOIN subjects s ON s.id=t.subject_id WHERE t.plan_id=? ORDER BY t.day_index,t.unit_index,t.id');
    $rows->execute([$plan['id']]);
    foreach ($rows->fetchAll() as $t) $tasksByDay[(int)$t['day_index']][] = $t;
}
$chart = student_week_chart($studentId);
$maxBar = max(1, max(array_map(fn($c)=>$c['total'],$chart)));

panel_start('گزارش دانش‌آموز', $student['full_name'], 'admin', 'reports', ['student.css']);
?>
<div class="between mb-4 wrap gap-3">
  <div class="builder-student flex gap-3" style="align-items:center">
    <a href="<?= url('admin/reports.php') ?>" class="btn btn-ghost btn-icon"><?= icon('arrow-right',18) ?></a>
    <span class="u-ava"><?= e(avatar_letters($student['full_name'])) ?></span>
    <div><div style="font-weight:800"><?= e($student['full_name']) ?></div>
      <div class="muted" style="font-size:.78rem"><?= e($student['field'] ?: '') ?> · <?= icon('fire',12) ?> <?= fa_num($student['streak']) ?> روز
      <?php $m = mood_info($student['mood'] ?? null); if($m): ?> · حال امروز: <span style="color:<?= e($m['color']) ?>"><?= $m['emoji'] ?> <?= e($m['label']) ?></span><?php endif; ?>
      </div></div>
  </div>
  <div class="week-nav flex gap-2" style="align-items:center">
    <a href="?student=<?= $studentId ?>&week=<?= $prevWeek ?>" class="btn btn-ghost btn-icon"><?= icon('chevron-right',18) ?></a>
    <span class="fw-700"><?= jalali_date($weekStart) ?></span>
    <a href="?student=<?= $studentId ?>&week=<?= $nextWeek ?>" class="btn btn-ghost btn-icon"><?= icon('chevron-left',18) ?></a>
  </div>
</div>

<div class="panel reveal mb-4">
  <div class="panel-head"><h3><?= icon('bar',20) ?> فعالیت هفته</h3>
    <a href="<?= url('admin/plan_builder.php?student='.$studentId.'&week='.$weekStart) ?>" class="btn btn-gold btn-sm"><?= icon('edit',15) ?> ویرایش برنامه</a></div>
  <div class="barchart">
    <?php foreach($chart as $c): $hh=round($c['done']/$maxBar*100); ?>
    <div class="bcol"><div class="bar gold" data-h="<?= $hh ?>" style="height:0" data-tip="<?= fa_num($c['done']) ?>/<?= fa_num($c['total']) ?>"></div><span class="blbl"><?= mb_substr($c['day'],0,3) ?></span></div>
    <?php endforeach;?>
  </div>
</div>

<?php if(!$plan):?>
  <div class="panel"><div class="empty-state"><div class="es-ico"><?= icon('calendar',30) ?></div>برنامه‌ای برای این هفته نیست</div></div>
<?php else: foreach(DAY_NAMES as $di=>$dn): $dt=$tasksByDay[$di]??[]; if(!$dt) continue; ?>
<div class="panel reveal mb-4">
  <div class="panel-head"><h3><?= e($dn) ?></h3><span class="badge"><?= fa_num(count(array_filter($dt,fn($t)=>$t['is_done']))) ?>/<?= fa_num(count($dt)) ?></span></div>
  <div class="task-list">
    <?php foreach($dt as $t): $done=(int)$t['is_done']; ?>
    <div class="s-task <?= $done?'done':'' ?>">
      <span class="st-check icon-tile <?= $done?'sage':'' ?>" style="width:34px;height:34px;border-radius:10px"><?= icon($done?'check':'clock',16) ?></span>
      <div class="st-body">
        <div class="st-title"><?= e($t['title']) ?>
          <?php if($t['subj_name']):?><span class="st-subj" style="background:<?= e($t['subj_color']) ?>22;color:<?= e($t['subj_color']) ?>"><?= e($t['subj_name']) ?></span><?php endif;?>
        </div>
        <div class="st-meta">
          <span class="badge" style="font-size:.7rem"><?= e(TASK_TYPES[$t['task_type']]['label']??'') ?></span>
          <?php if($t['target_count']!==null):?><span class="st-prog"><?= fa_num($t['done_count']) ?>/<?= fa_num($t['target_count']) ?> <?= e($t['target_unit']) ?></span><?php endif;?>
          <?php if($done && $t['completed_at']):?><span class="st-prog"><?= icon('check',12) ?> <?= time_ago($t['completed_at']) ?></span><?php endif;?>
        </div>
        <?php if($t['student_note']):?><div class="st-prog" style="margin-top:5px">📝 <?= e($t['student_note']) ?></div><?php endif;?>
        <?php if($t['advisor_feedback']):?>
          <div class="st-feedback"><span class="ico"><?= icon('message',15) ?></span><span><b>بازخورد شما:</b> <?= e($t['advisor_feedback']) ?></span></div>
        <?php endif;?>
        <button class="st-note-btn" data-feedback="<?= (int)$t['id'] ?>" data-current="<?= e($t['advisor_feedback'] ?? '') ?>"><?= icon('message',14) ?> <?= $t['advisor_feedback']?'ویرایش بازخورد':'ثبت بازخورد' ?></button>
      </div>
    </div>
    <?php endforeach;?>
  </div>
</div>
<?php endforeach; endif; ?>

<!-- feedback modal -->
<div class="modal-backdrop" id="fbModal">
  <div class="modal" style="max-width:440px">
    <div class="modal-head"><h3><?= icon('message',18) ?> بازخورد مشاور</h3><button class="modal-close" data-close><?= icon('close',18) ?></button></div>
    <input type="hidden" id="fbTaskId">
    <textarea class="input" id="fbText" rows="4" placeholder="بازخوردت روی این تسک…"></textarea>
    <button class="btn btn-gold btn-block mt-4" id="saveFbBtn"><?= icon('check',16) ?> ارسال بازخورد</button>
  </div>
</div>
<script>
  window.API_TASKS='<?= url('api/tasks.php') ?>';
  window.NOTIF_URL='<?= url('api/notifications.php') ?>';
  window.NOTIF_READ_URL='<?= url('api/notifications.php?read=1') ?>';
  document.addEventListener('click',e=>{
    const b=e.target.closest('[data-feedback]'); if(!b)return;
    document.getElementById('fbTaskId').value=b.dataset.feedback;
    document.getElementById('fbText').value=b.dataset.current||'';
    openModal('fbModal');
  });
  document.getElementById('saveFbBtn')?.addEventListener('click', async function(){
    const id=document.getElementById('fbTaskId').value, fb=document.getElementById('fbText').value;
    try{ await api(window.API_TASKS,{method:'POST',body:{action:'feedback',id,feedback:fb}});
      closeModal('fbModal'); toast('بازخورد ثبت شد','success'); setTimeout(()=>location.reload(),700);
    }catch(e){ toast(e.error||'خطا','error'); }
  });
</script>
<?php panel_end(); ?>
