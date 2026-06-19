<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/models.php';
require_once __DIR__ . '/../includes/review_scheduler.php';
require_once __DIR__ . '/../includes/panel_layout.php';
boot_session();
require_role('advisor','admin');
$u = current_user();
$studentId = (int)($_GET['student'] ?? 0);
$scope = in_array($_GET['scope'] ?? 'due', ['due','upcoming','done'], true) ? $_GET['scope'] : 'due';

if (!review_schema_ready()) {
    panel_start('مرورهای دانش‌آموزان','خطا در آماده‌سازی', 'admin', 'reviews', ['student.css']);
    echo '<div class="alert alert-error">خطا در آماده‌سازی مرورها. فایل migration مرورها را دوباره اجرا کنید یا install.php را یک‌بار باز کنید.</div>';
    panel_end(); exit;
}

if (!$studentId) {
    $students = advisor_students((int)$u['id'], 'active');
    panel_start('مرورهای دانش‌آموزان','نمای جداگانه مرورهای فاصله‌دار هر دانش‌آموز', 'admin', 'reviews', ['student.css']);
    ?>
    <div class="student-grid">
      <?php foreach($students as $s): review_backfill_for_student((int)$s['id']); $c=review_counts((int)$s['id']); ?>
      <a class="panel student-card" href="?student=<?= (int)$s['id'] ?>&scope=due" style="text-decoration:none;color:inherit">
        <div class="sc-top"><span class="u-ava"><?= e(avatar_letters($s['full_name'])) ?></span><div><b><?= e($s['full_name']) ?></b><div class="muted">برنامه مرور اختصاصی</div></div></div>
        <div class="sc-meta"><span class="badge badge-gold">موعد: <?= fa_num($c['due']) ?></span><span class="badge">آینده: <?= fa_num($c['upcoming']) ?></span><span class="badge badge-sage">انجام‌شده: <?= fa_num($c['done']) ?></span></div>
      </a>
      <?php endforeach; ?>
    </div>
    <?php panel_end(); exit;
}

$student = get_user($studentId);
if (!$student || $student['role'] !== 'student') { flash('error','دانش‌آموز یافت نشد'); redirect('admin/reviews.php'); }
if ($u['role'] !== 'admin' && (int)($student['advisor_id'] ?? 0) !== (int)$u['id']) { http_response_code(403); require __DIR__ . '/../403.php'; exit; }
review_backfill_for_student($studentId);
$counts = review_counts($studentId);
$items = review_items_for_advisor($studentId, $scope);
panel_start('مرورهای دانش‌آموز', $student['full_name'], 'admin', 'reviews', ['student.css']);
?>
<div class="between mb-4 wrap gap-3">
  <div class="builder-student flex gap-3" style="align-items:center">
    <a href="<?= url('admin/reviews.php') ?>" class="btn btn-ghost btn-icon"><?= icon('arrow-right',18) ?></a>
    <span class="u-ava"><?= e(avatar_letters($student['full_name'])) ?></span>
    <div><div style="font-weight:900"><?= e($student['full_name']) ?></div><div class="muted">مرورهای ساخته‌شده از تسک‌های مطالعه/کتاب/روزخوانی کامل یا ناقص</div></div>
  </div>
  <a class="btn btn-gold btn-sm" href="<?= url('admin/plan_builder.php?student='.$studentId) ?>"><?= icon('calendar',15) ?> برنامه‌ریز</a>
</div>
<div class="report-tabs mb-4">
  <a class="chip <?= $scope==='due'?'active':'' ?>" href="?student=<?= $studentId ?>&scope=due">موعد امروز/عقب‌افتاده · <?= fa_num($counts['due']) ?></a>
  <a class="chip <?= $scope==='upcoming'?'active':'' ?>" href="?student=<?= $studentId ?>&scope=upcoming">آینده · <?= fa_num($counts['upcoming']) ?></a>
  <a class="chip <?= $scope==='done'?'active':'' ?>" href="?student=<?= $studentId ?>&scope=done">انجام‌شده · <?= fa_num($counts['done']) ?></a>
</div>
<?php if(!$items): ?>
<div class="panel"><div class="empty-state"><div class="es-ico">🔁</div>موردی برای نمایش نیست</div></div>
<?php else: ?>
<div class="review-list admin-review-list">
<?php foreach($items as $r): $late = $scope==='due' ? max(0, (int)floor((time()-strtotime($r['due_date']))/86400)) : 0; ?>
  <div class="panel review-card <?= $scope==='due'?'due':'' ?>">
    <div class="review-main">
      <span class="review-step"><?= (int)$r['interval_days']===0?'تقویتی':fa_num($r['interval_days']).' روز' ?></span>
      <div><h3><?= e($r['topic_title']) ?></h3><p><?= $r['subject_name']?e($r['subject_name']).' · ':'' ?><?= $r['profile_label']?e($r['profile_label']).' · ':'' ?><?= $r['source']?'منبع: '.e($r['source']).' · ':'' ?><?= fa_num((int)($r['suggested_minutes']??15)) ?> دقیقه · موعد: <?= jalali_date($r['due_date']) ?><?= $late>0?' · '.fa_num($late).' روز تأخیر':'' ?></p></div>
    </div>
    <span class="badge <?= $r['status']==='done'?'badge-sage':'badge-gold' ?>"><?= e($r['status']==='done'?'انجام‌شده':($scope==='upcoming'?'آینده':'در انتظار')) ?></span>
  </div>
<?php endforeach; ?>
</div>
<?php endif; ?>
<?php panel_end(); ?>
