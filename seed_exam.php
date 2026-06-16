<?php
/**
 * افزودن «آزمون جامع نمونه» به نصب موجود.
 * فقط مشاور/مدیر می‌تواند اجرا کند. پس از استفاده می‌توانید حذفش کنید.
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/models.php';
require_once __DIR__ . '/includes/exam_seed.php';
require_once __DIR__ . '/includes/layout.php';
boot_session();
require_role('advisor','admin');
$u = current_user();

$done = false; $count = 0; $err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    try {
        $subs = [];
        foreach (all_subjects() as $s) $subs[$s['name']] = (int)$s['id'];
        $count = seed_sample_exam(db(), (int)$u['id'], $subs);
        $done = true;
    } catch (Throwable $e) {
        $err = APP_ENV==='development' ? $e->getMessage() : 'خطا در ساخت آزمون نمونه';
    }
}

page_head('افزودن آزمون نمونه');
?>
<div style="min-height:100vh;display:grid;place-items:center;padding:24px">
  <div class="card" style="max-width:480px;width:100%">
    <div class="brand" style="justify-content:center;margin-bottom:16px"><?= logo_svg(46) ?></div>
    <h1 class="text-c" style="font-size:1.4rem;margin-bottom:8px">آزمون جامع نمونه</h1>
    <?php if ($done): ?>
      <div class="alert alert-success" style="margin:14px 0"><?= icon('check',18) ?><span><?= $count>0 ? ('آزمون نمونه با '.fa_num($count).' سوال (شامل سوالات عکس‌دار) ساخته شد.') : 'آزمون نمونه از قبل وجود دارد.' ?></span></div>
      <a href="<?= url('admin/exams.php') ?>" class="btn btn-gold btn-block"><?= icon('clipboard',18) ?> رفتن به آزمون‌ها</a>
    <?php else: ?>
      <?php if($err):?><div class="alert alert-error" style="margin:12px 0"><?= icon('info',18) ?><span><?= e($err) ?></span></div><?php endif;?>
      <p class="muted text-c" style="margin-bottom:18px">یک آزمون جامع نمونه شامل سه درس (شیمی، ریاضی، ادبیات) و چند سوال تصویری ساخته می‌شود تا با محیط آزمون آشنا شوید.</p>
      <form method="post"><?= csrf_field() ?><button class="btn btn-gold btn-block btn-lg"><?= icon('plus',18) ?> ساخت آزمون نمونه</button></form>
    <?php endif; ?>
    <div class="text-c mt-4"><a href="<?= url('admin/exams.php') ?>" class="muted" style="font-size:.85rem">انصراف</a></div>
  </div>
</div>
<?php page_foot(); ?>
