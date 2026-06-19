<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/reporting.php';
require_once __DIR__ . '/../includes/panel_layout.php';
boot_session();
require_role('advisor','admin');
$u=current_user();
$studentId=(int)($_GET['student']??0);
$type=in_array($_GET['type']??'daily',['daily','weekly','monthly'],true)?$_GET['type']:'daily';
if(!$studentId){
  $students=advisor_students((int)$u['id'],'active');
  panel_start('گزارش حرفه‌ای','انتخاب دانش‌آموز', 'admin','student_reports',['student.css']); ?>
  <div class="student-grid">
    <?php foreach($students as $s): ?>
    <a class="panel student-card" href="?student=<?= (int)$s['id'] ?>&type=daily" style="text-decoration:none;color:inherit">
      <div class="sc-top"><span class="u-ava"><?= e(avatar_letters($s['full_name'])) ?></span><div><b><?= e($s['full_name']) ?></b><div class="muted">گزارش‌های روزانه، هفتگی و ماهانه</div></div></div>
      <div class="sc-meta"><span class="badge">× <?= fa_num($s['missed_tasks']??0) ?> قرمز</span><span class="badge badge-sage">✓ <?= fa_num($s['full_tasks']??0) ?> کامل</span></div>
    </a>
    <?php endforeach; ?>
  </div>
  <?php panel_end(); exit;
}
$student=get_user($studentId);
if(!$student || $student['role']!=='student'){ flash('error','دانش‌آموز یافت نشد'); redirect('admin/student_reports.php'); }
$reports=reports_for_student($studentId,$type,40);
$showInsight = advisor_feature_enabled((int)$u['id'], 'insight_enabled');
panel_start('گزارش حرفه‌ای', $student['full_name'].' · '.report_type_label($type), 'admin','student_reports',['student.css']);
?>
<div class="between mb-4 wrap gap-3">
  <div class="builder-student flex gap-3" style="align-items:center">
    <a href="<?= url('admin/student_reports.php') ?>" class="btn btn-ghost btn-icon"><?= icon('arrow-right',18) ?></a>
    <span class="u-ava"><?= e(avatar_letters($student['full_name'])) ?></span>
    <div><div style="font-weight:900"><?= e($student['full_name']) ?></div><div class="muted"><?= e($student['field']?:'') ?> · گزارش‌های ثبت‌شده</div></div>
  </div>
  <div class="report-tabs">
    <?php foreach(['daily'=>'روزانه','weekly'=>'هفتگی','monthly'=>'ماهانه'] as $k=>$lbl): ?><a class="chip <?= $type===$k?'active':'' ?>" href="?student=<?= $studentId ?>&type=<?= $k ?>"><?= e($lbl) ?></a><?php endforeach; ?>
  </div>
</div>

<?php if(!$reports): ?>
<div class="panel"><div class="empty-state"><div class="es-ico"><?= icon('chart',30) ?></div>هنوز گزارشی برای این بخش ثبت نشده</div></div>
<?php else: foreach($reports as $r): $s=$r['snapshot']; $a=$r['advanced']; $an=$showInsight?report_build_analysis($studentId,(string)$r['report_type'],(string)$r['period_start'],(string)$r['period_end'],$s,$a):null; ?>
<div class="panel report-admin-card mb-4 <?= $r['status']==='submitted'?'submitted':'draft' ?>">
  <div class="panel-head">
    <h3><?= e(report_type_label($r['report_type'])) ?> · <?= jalali_date($r['period_start']) ?><?= $r['period_start']!==$r['period_end']?' تا '.jalali_date($r['period_end']):'' ?></h3>
    <span class="badge <?= $r['status']==='submitted'?'badge-sage':'badge-gold' ?>"><?= $r['status']==='submitted'?'ارسال شده':'تکمیل نشده' ?></span>
  </div>
  <div class="stat-cards compact-stats">
    <div class="panel stat"><span class="icon-tile sage">٪</span><div><div class="v"><?= fa_num($s['progress_percent']??0) ?>٪</div><div class="k">پیشرفت</div></div></div>
    <div class="panel stat"><span class="icon-tile sage"><?= icon('clock',20) ?></span><div><div class="v"><?= fa_num($s['study_hours']??0) ?></div><div class="k">ساعت مؤثر</div></div></div>
    <div class="panel stat"><span class="icon-tile"><?= icon('check',20) ?></span><div><div class="v"><?= fa_num($s['tests_done']??0) ?></div><div class="k">تست</div></div></div>
    <div class="panel stat"><span class="icon-tile" style="background:rgba(217,116,116,.16);color:#ff9a9a">×</span><div><div class="v"><?= fa_num($s['missed']??0) ?></div><div class="k">قرمز</div></div></div>
  </div>

  <?php if($showInsight && $an): ?>
  <div class="insight-closed admin-insight-closed">
    <div><b><?= icon('sparkles',18) ?> تحلیل هوشمند مَدار <span class="beta-pill">بتا</span></b><span><?= e($an['summary']) ?></span></div>
    <button class="btn btn-gold btn-sm" type="button" data-modal="insightModal<?= (int)$r['id'] ?>">مشاهده</button>
  </div>
  <div class="modal-backdrop" id="insightModal<?= (int)$r['id'] ?>">
    <div class="modal insight-modal">
      <div class="modal-head"><h3><?= icon('sparkles',20) ?> تحلیل هوشمند مَدار <span class="beta-pill">بتا</span></h3><button class="modal-close" data-close><?= icon('close',18) ?></button></div>
      <div class="insight-score-head"><b><?= fa_num($an['overall']) ?>٪</b><span><?= e($an['overall_label']) ?></span></div>
      <p class="insight-summary"><?= e($an['summary']) ?></p>
      <div class="insight-mini-row"><?php foreach(['execution'=>'اجرا','consistency'=>'ثبات','tests'=>'تست','recovery'=>'خواب/انرژی','subject_balance'=>'تعادل','burnout_risk'=>'ریسک افت'] as $k=>$lbl): ?><span><?= e($lbl) ?>: <b><?= fa_num($an['scores'][$k]??0) ?>٪</b></span><?php endforeach; ?></div>
      <?php if($an['alerts']): ?><div class="insight-alerts compact"><?php foreach($an['alerts'] as $al): ?><div class="ia <?= e($al['level']) ?>"><b><?= e($al['title']) ?></b><span><?= e($al['text']) ?></span></div><?php endforeach; ?></div><?php endif; ?>
      <div class="insight-recs"><b>پیشنهادها</b><ul><?php foreach($an['recommendations'] as $rec): ?><li><?= e($rec) ?></li><?php endforeach; ?></ul></div>
    </div>
  </div>
  <?php endif; ?>
  <div class="report-review-grid">
    <div class="rr-box"><b>خواب</b><span><?= isset($a['sleep_hours'])?fa_num($a['sleep_hours']).' ساعت':'—' ?> · کیفیت <?= isset($a['sleep_quality'])?fa_num($a['sleep_quality']).'/۵':'—' ?></span></div>
    <div class="rr-box"><b>تمرکز / انرژی / استرس</b><span><?= fa_num($a['focus_score']??'—') ?> / <?= fa_num($a['energy_score']??'—') ?> / <?= fa_num($a['stress_score']??'—') ?></span></div>
    <div class="rr-box"><b>موبایل و اتلاف وقت</b><span><?= fa_num($a['phone_minutes']??0) ?> دقیقه · اتلاف <?= fa_num($a['wasted_minutes']??0) ?> دقیقه</span></div>
    <div class="rr-box"><b>خودارزیابی</b><span><?= isset($a['self_score'])?fa_num($a['self_score']).' از ۲۰':'—' ?></span></div>
    <?php if(!empty($a['main_reason']) || !empty($a['plan_fit']) || !empty($a['monthly_mindset'])): ?><div class="rr-box"><b>جمع‌بندی بازه</b><span><?= e($a['main_reason'] ?? $a['plan_fit'] ?? $a['monthly_mindset'] ?? '') ?></span></div><?php endif; ?>
  </div>
  <div class="report-notes">
    <?php foreach(['best_win'=>'برد/نقطه قوت','main_challenge'=>'چالش','challenge_reason'=>'علت احتمالی','solution'=>'راهکار دانش‌آموز','next_priority'=>'اولویت بعدی','advisor_question'=>'سؤال از مشاور'] as $k=>$lbl): if(!empty($a[$k])): ?>
      <div><b><?= e($lbl) ?></b><p><?= nl2br(e($a[$k])) ?></p></div>
    <?php endif; endforeach; ?>
  </div>
  <?php if(!empty($s['by_subject'])): ?><div class="report-subjects mt-4"><?php foreach($s['by_subject'] as $name=>$x): ?><div class="report-subj"><b><?= e($name) ?></b><span><?= fa_num($x['tests']??0) ?> تست · <?= fa_num(round(($x['minutes']??0)/60,1)) ?> ساعت · قرمز <?= fa_num($x['missed']??0) ?></span></div><?php endforeach; ?></div><?php endif; ?>
</div>
<?php endforeach; endif; ?>
<?php panel_end(); ?>
