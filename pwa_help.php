<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/layout.php';
boot_session();
page_head('راهنمای نصب وب‌اپ', '', ['student.css']);
?>
<main class="pwa-page">
  <section class="pwa-hero panel">
    <div class="pwa-brand"><?= logo_svg(72) ?><div><span class="muted">WEB APP</span><h1>مَدار را مثل اپلیکیشن نصب کن</h1><p>دسترسی سریع، تجربه تمام‌صفحه و امکان دریافت یادآوری مرورها روی گوشی.</p></div></div>
    <div class="pwa-actions"><button class="btn btn-gold btn-lg" type="button" data-pwa-install>نصب مستقیم وب‌اپ</button><button class="btn btn-ghost" type="button" data-notif-enable>فعال‌سازی اعلان‌ها</button></div>
  </section>
  <section class="pwa-steps">
    <div class="panel pwa-card"><span>Android</span><h3>Chrome / Samsung Internet</h3><ol><li>سایت را باز کن.</li><li>اگر دکمه نصب بالا فعال بود، همان را بزن.</li><li>اگر نبود: منوی سه‌نقطه → Add to Home screen / Install app.</li><li>بعد از نصب، اعلان‌ها را فعال کن.</li></ol></div>
    <div class="panel pwa-card"><span>iPhone</span><h3>Safari</h3><ol><li>سایت را در Safari باز کن.</li><li>دکمه Share را بزن.</li><li>Add to Home Screen را انتخاب کن.</li><li>روی Add بزن.</li></ol><p class="muted">روی iOS نصب مستقیم با دکمه مرورگر محدود است؛ مسیر Share مطمئن‌ترین روش است.</p></div>
    <div class="panel pwa-card"><span>Desktop</span><h3>Chrome / Edge</h3><ol><li>سایت را باز کن.</li><li>آیکن نصب کنار نوار آدرس را بزن.</li><li>یا از منوی مرورگر گزینه Install app را انتخاب کن.</li></ol></div>
    <div class="panel pwa-card"><span>Notifications</span><h3>اعلان مرورها</h3><p>برای اینکه مرورهای مهم را از دست ندهی، اجازه اعلان را فعال کن. اگر قبلاً رد کرده‌ای، از تنظیمات مرورگر بخش Notifications اجازه سایت مَدار را فعال کن.</p></div>
  </section>
  <a class="btn btn-ghost" href="<?= url(is_logged_in() ? (user_role()==='student'?'student/dashboard.php':'admin/dashboard.php') : '') ?>">بازگشت</a>
</main>
<style>
.pwa-page{max-width:1050px;margin:0 auto;padding:26px}.pwa-hero{display:flex;align-items:center;justify-content:space-between;gap:20px;background:radial-gradient(circle at 8% 0,rgba(203,172,128,.18),transparent 35%),linear-gradient(135deg,rgba(107,136,114,.12),var(--card));margin-bottom:18px}.pwa-brand{display:flex;gap:16px;align-items:center}.pwa-brand h1{margin:2px 0 8px;font-size:1.8rem}.pwa-brand p{margin:0;color:var(--text-3)}.pwa-actions{display:flex;gap:10px;flex-wrap:wrap}.pwa-steps{display:grid;grid-template-columns:repeat(auto-fit,minmax(230px,1fr));gap:14px;margin-bottom:18px}.pwa-card span{display:inline-block;color:var(--gold-light);font-weight:900;font-size:.78rem;margin-bottom:7px}.pwa-card h3{margin:0 0 10px}.pwa-card li{margin-bottom:7px;color:var(--text-2)}[data-pwa-install]:not(.ready)::after{content:' (اگر پشتیبانی شود)';font-size:.72rem;opacity:.75}@media(max-width:700px){.pwa-hero{flex-direction:column;align-items:stretch}.pwa-brand{align-items:flex-start}.pwa-actions .btn{width:100%;text-align:center}}
</style>
<?php page_foot(); ?>
