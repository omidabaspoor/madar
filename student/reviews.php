<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/review_scheduler.php';
require_once __DIR__ . '/../includes/panel_layout.php';
boot_session();
require_role('student');
$u = current_user();
$webNotifEnabled = advisor_feature_enabled((int)($u['advisor_id'] ?? 0), 'web_notifications');
$reviewsEnabled = advisor_feature_enabled((int)($u['advisor_id'] ?? 0), 'review_enabled');
$scope = in_array($_GET['scope'] ?? 'due', ['due','upcoming','done'], true) ? $_GET['scope'] : 'due';
$reviewError = '';
try {
    if (!$reviewsEnabled) { throw new RuntimeException('DISABLED_REVIEWS'); }
    review_schema_ready();
    review_due_notifications((int)$u['id']);
    $counts = review_counts((int)$u['id']);
    $items = review_items((int)$u['id'], $scope);
} catch (Throwable $e) {
    error_log('Madar reviews page error: '.$e->getMessage());
    $reviewError = $e->getMessage()==='DISABLED_REVIEWS' ? 'مرورهای هوشمند فعلاً توسط مشاور غیرفعال شده است.' : (APP_ENV === 'development' ? $e->getMessage() : 'مرورها فعلاً آماده نیستند. لطفاً کمی بعد دوباره تلاش کن.');
    $counts = ['due'=>0,'upcoming'=>0,'done'=>0];
    $items = [];
}
panel_start('مرورهای هوشمند', 'مرورهای برنامه‌ریزی‌شده برای تثبیت مطالب', 'student', 'reviews', ['student.css']);
?>

<?php if($webNotifEnabled): ?>
<div class="panel notif-permission mb-4" style="display:none">
  <div><b>🔔 اعلان مرورها را فعال کن</b><span>برای اینکه وب‌اپ زمان مرورهای مهم را روی گوشی یادآوری کند، اجازه اعلان را فعال کن.</span></div>
  <button class="btn btn-gold btn-sm" type="button" data-notif-enable>فعال‌سازی اعلان</button>
</div>
<script>if('Notification' in window && Notification.permission==='default') document.currentScript.previousElementSibling.style.display='flex';</script>
<?php endif; ?>

<?php if($reviewError): ?><div class="alert alert-error mb-4"><?= e($reviewError) ?></div><?php endif; ?>
<div class="review-hero panel mb-4">
  <div><span class="muted">جلوگیری از فراموشی</span><h2>مرور فاصله‌دار</h2><p>هر مبحث خواندنی که کامل یا ناقص ثبت شود، در زمان‌های مناسب دوباره برای مرور یادآوری می‌شود.</p></div>
  <div class="review-count"><b><?= fa_num($counts['due']) ?></b><span>مرور امروز</span></div>
</div>
<div class="report-tabs mb-4">
  <a class="chip <?= $scope==='due'?'active':'' ?>" href="?scope=due">موعد امروز/عقب‌افتاده · <?= fa_num($counts['due']) ?></a>
  <a class="chip <?= $scope==='upcoming'?'active':'' ?>" href="?scope=upcoming">آینده · <?= fa_num($counts['upcoming']) ?></a>
  <a class="chip <?= $scope==='done'?'active':'' ?>" href="?scope=done">انجام‌شده · <?= fa_num($counts['done']) ?></a>
</div>
<?php if(!$items): ?>
<div class="panel"><div class="empty-state"><div class="es-ico">🔁</div>موردی برای نمایش نیست</div></div>
<?php else: ?>
<div class="review-list">
<?php foreach($items as $r): $late = max(0, (int)floor((time()-strtotime($r['due_date']))/86400)); ?>
  <div class="panel review-card <?= $scope==='due'?'due':'' ?>" data-review="<?= (int)$r['id'] ?>">
    <div class="review-main">
      <span class="review-step"><?= fa_num($r['interval_days']) ?> روز</span>
      <div><h3><?= e($r['topic_title']) ?></h3><p><?= $r['subject_name']?e($r['subject_name']).' · ':'' ?><?= $r['profile_label']?e($r['profile_label']).' · ':'' ?><?= fa_num((int)($r['suggested_minutes']??15)) ?> دقیقه · موعد: <?= jalali_date($r['due_date']) ?><?= $late>0?' · '.fa_num($late).' روز تأخیر':'' ?></p></div>
    </div>
    <?php if($scope!=='done'): ?><div class="review-actions"><button class="btn btn-ghost btn-sm" data-review-done data-quality="hard">سخت بود</button><button class="btn btn-sage btn-sm" data-review-done data-quality="good">مرور کردم</button><button class="btn btn-gold btn-sm" data-review-done data-quality="easy">آسون بود</button><button class="btn btn-ghost btn-sm" data-review-dismiss>بعداً</button></div><?php endif; ?>
  </div>
<?php endforeach; ?>
</div>
<?php endif; ?>
<script>
window.API_REVIEWS='<?= url('api/reviews.php') ?>';
document.addEventListener('click', async e=>{
  const card=e.target.closest('[data-review]'); if(!card) return;
  const action=e.target.closest('[data-review-done]')?'done':(e.target.closest('[data-review-dismiss]')?'dismiss':'');
  if(!action) return;
  const quality=e.target.closest('[data-review-done]')?.dataset.quality||'good';
  try{ await api(window.API_REVIEWS,{method:'POST',body:{action,id:card.dataset.review,quality}}); toast(action==='done'?(quality==='hard'?'مرور ثبت شد؛ یک مرور تقویتی هم اضافه شد':'مرور ثبت شد'):'از لیست امروز کنار گذاشته شد','success'); card.remove(); }
  catch(err){ toast(err.error||'خطا','error'); }
});
</script>
<?php panel_end(); ?>
