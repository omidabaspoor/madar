<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/layout.php';
boot_session();

// اگر وارد شده، به داشبورد مربوطه برو
if (is_logged_in()) {
    $r = user_role();
    if ($r === 'student') redirect('student/dashboard.php');
    if (in_array($r, ['advisor','admin'], true)) redirect('admin/dashboard.php');
}

page_head('', '', ['landing.css']);
?>
<!-- ============ NAV ============ -->
<nav class="site-nav">
  <div class="container inner">
    <?= brand_block() ?>
    <ul class="nav-links">
      <li><a href="#features"><?= icon('grid',16) ?> امکانات</a></li>
      <li><a href="#flow">مسیر کاربری</a></li>
      <li><a href="#panels">پنل‌ها</a></li>
      <li><a href="#tech">امنیت</a></li>
    </ul>
    <div class="flex gap-2" style="align-items:center">
      <a href="<?= url('auth/login.php') ?>" class="btn btn-ghost btn-sm"><?= icon('login',16) ?> ورود</a>
      <a href="<?= url('auth/register.php') ?>" class="btn btn-gold btn-sm">ثبت‌نام دانش‌آموز</a>
      <button class="nav-toggle btn btn-icon btn-ghost" aria-label="منو"><?= icon('menu') ?></button>
    </div>
  </div>
</nav>

<!-- ============ HERO ============ -->
<header class="hero">
  <div class="hero-blob b1"></div>
  <div class="hero-blob b2"></div>
  <div class="container hero-grid">
    <div class="reveal in">
      <span class="badge badge-gold hero-badge"><?= icon('sparkles',14) ?> سامانه هوشمند برنامه‌ریزی کنکور</span>
      <h1 class="display">برنامه‌ریزی دقیق،<br><span class="gradient-text">پیشرفت واقعی.</span></h1>
      <p class="lead">مَدار، فضای اختصاصی دانش‌آموز و مشاور است. <?= e(APP_OWNER) ?> از پنل خود برای هر دانش‌آموز برنامه می‌چیند و دانش‌آموز تسک‌ها را گام‌به‌گام تکمیل می‌کند — با گزارش‌گیری زنده و انگیزه‌بخش.</p>
      <div class="hero-cta">
        <a href="<?= url('auth/register.php') ?>" class="btn btn-gold btn-lg"><?= icon('rocket',18) ?> شروع کنید</a>
        <a href="#flow" class="btn btn-ghost btn-lg"><?= icon('play',16) ?> ببینید چطور کار می‌کند</a>
      </div>
      <div class="hero-stats">
        <div class="hero-stat"><div class="num"><span data-count="100" data-suffix="+">۰</span></div><div class="lbl">دانش‌آموز هم‌زمان</div></div>
        <div class="hero-stat"><div class="num"><span data-count="85" data-suffix="٪">۰</span></div><div class="lbl">افزایش پایبندی</div></div>
        <div class="hero-stat"><div class="num"><span data-count="70" data-suffix="٪">۰</span></div><div class="lbl">کاهش زمان مدیریت</div></div>
      </div>
    </div>
    <div class="hero-visual reveal" data-d="2">
      <img src="<?= asset('img/hero-mockup.png') ?>" alt="نمایی از اپلیکیشن مَدار" class="float">
      <div class="float-card fc1"><span class="icon-tile sage" style="width:30px;height:30px;border-radius:9px"><?= icon('fire',16) ?></span> استریک ۱۲ روزه</div>
      <div class="float-card fc2"><span class="icon-tile" style="width:30px;height:30px;border-radius:9px"><?= icon('check-circle',16) ?></span> ۲۵٪ تسک‌های امروز</div>
    </div>
  </div>
</header>

<!-- ============ FEATURES ============ -->
<section class="section" id="features">
  <div class="container">
    <div class="section-head">
      <span class="eyebrow">قابلیت‌ها و امکانات</span>
      <h2 class="section-title">هر چیزی که برای یک سال موفق لازم است</h2>
      <p>از برنامه‌ریزی هوشمند تا گزارش‌دهی دقیق و ارتباط مستقیم — همه در یک سامانه‌ی یکپارچه.</p>
    </div>
    <div class="feat-grid">
      <?php
      $feats = [
        ['calendar','برنامه هفتگی روز×واحد','جدول کامل روزها و واحدهای مطالعاتی؛ درست مثل فرم برنامه‌ریزی کاغذی اما هوشمند و زنده.'],
        ['tasks','سه نوع تسک','مطالعه، تست، روزخوانی و آزمونک — با مقدار هدف و درصد پیشرفت دقیق برای هر تسک.'],
        ['fire','استریک و دستاورد','هر روز فعالیت = یک شعله؛ سیستم انگیزشی که دانش‌آموز را پای کار نگه می‌دارد.'],
        ['message','پیام‌رسانی مستقیم','گفتگوی دوطرفه‌ی دکتر و دانش‌آموز، بازخورد روی هر تسک و اعلان‌های هوشمند.'],
        ['chart','گزارش‌های پیشرفت','نمودار روزانه، پیشرفت هر درس و رتبه‌بندی دانش‌آموزان برای مشاور.'],
        ['shield','امن و سریع','رمزنگاری bcrypt، محافظت CSRF، نشست امن و طراحی موبایل‌محور (PWA).'],
      ];
      $i=0; foreach ($feats as [$ic,$t,$d]): $i++; ?>
      <div class="card card-glow hover-lift feat-card reveal" data-d="<?= min($i,6) ?>">
        <span class="icon-tile <?= $i%2? 'sage':'' ?>"><?= icon($ic,24) ?></span>
        <h3><?= e($t) ?></h3>
        <p><?= e($d) ?></p>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- ============ USER FLOW ============ -->
<section class="section" id="flow" style="background:linear-gradient(180deg,transparent,var(--bg-2),transparent)">
  <div class="container">
    <div class="section-head">
      <span class="eyebrow">تجربه کاربری دانش‌آموز و مشاور</span>
      <h2 class="section-title">یک مسیر روشن، دو نقش هماهنگ</h2>
    </div>
    <div class="flow-cols">
      <div class="flow-col student">
        <h3><?= icon('user',20) ?> دانش‌آموز</h3>
        <?php foreach ([
          ['ثبت‌نام و تأیید','حساب می‌سازید و منتظر تأیید مشاور می‌مانید'],
          ['دریافت برنامه','برنامه‌ی هفتگی شما آماده می‌شود'],
          ['تکمیل تسک‌ها','هر روز تسک‌ها را انجام و ثبت می‌کنید'],
          ['دریافت بازخورد','نظر و راهنمایی دکتر را می‌بینید'],
          ['پیشرفت و دستاورد','نمودار رشد و دستاوردهایتان را دنبال می‌کنید'],
        ] as $n=>$s): ?>
        <div class="flow-step reveal" data-d="<?= min($n+1,6) ?>"><span class="n"><?= fa_num($n+1) ?></span><div><div class="t"><?= e($s[0]) ?></div><div class="d"><?= e($s[1]) ?></div></div></div>
        <?php endforeach; ?>
      </div>
      <div class="flow-mid"><div class="conn"><?= icon('repeat',24) ?></div></div>
      <div class="flow-col advisor">
        <h3><?= icon('graduation',20) ?> مشاور / دکتر</h3>
        <?php foreach ([
          ['ورود به پنل','به پنل مدیریت اختصاصی وارد می‌شوید'],
          ['تعریف دانش‌آموز','دانش‌آموزان را تأیید و مدیریت می‌کنید'],
          ['ساخت برنامه','جدول هفتگی را با چند کلیک می‌سازید'],
          ['رصد پیشرفت','عملکرد همه را زنده می‌بینید'],
          ['ارتباط با دانش‌آموز','بازخورد و پیام مستقیم می‌فرستید'],
        ] as $n=>$s): ?>
        <div class="flow-step reveal" data-d="<?= min($n+1,6) ?>"><span class="n"><?= fa_num($n+1) ?></span><div><div class="t"><?= e($s[0]) ?></div><div class="d"><?= e($s[1]) ?></div></div></div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</section>

<!-- ============ PANELS PREVIEW ============ -->
<section class="section" id="panels">
  <div class="container">
    <div class="section-head">
      <span class="eyebrow">نمایی از سامانه</span>
      <h2 class="section-title">طراحی تمیز، حرفه‌ای و موبایل‌محور</h2>
      <p>دارک‌مود زیبا، فونت فارسی وزیرمتن و ریسپانسیو کامل روی هر دستگاه.</p>
    </div>
    <div class="card glass reveal" style="padding:14px;border-radius:var(--r-xl)">
      <div class="grid" style="grid-template-columns:1fr 1fr;gap:14px" id="panels-grid">
        <div class="preview-shot"><img src="<?= asset('img/preview-student.png') ?>" alt="پنل دانش‌آموز" loading="lazy"></div>
        <div class="preview-shot"><img src="<?= asset('img/preview-admin.png') ?>" alt="پنل مدیر" loading="lazy"></div>
      </div>
    </div>
    <div class="flex gap-3 wrap mt-6" style="justify-content:center">
      <span class="tech-tag"><?= icon('moon',16) ?> دارک‌مود زیبا</span>
      <span class="tech-tag"><?= icon('desktop',16) ?> ریسپانسیو کامل</span>
      <span class="tech-tag"><?= icon('lang',16) ?> فونت فارسی Vazirmatn</span>
      <span class="tech-tag"><?= icon('pwa',16) ?> PWA Ready</span>
    </div>
  </div>
</section>

<!-- ============ STATS BAND ============ -->
<section class="section">
  <div class="container">
    <div class="stats-band">
      <div class="card hover-lift stat-pill reveal" data-d="1"><div class="big gradient-text"><span data-count="100" data-suffix="+">۰</span></div><div class="lbl">پشتیبانی دانش‌آموز هم‌زمان</div></div>
      <div class="card hover-lift stat-pill reveal" data-d="2"><div class="big sage"><span data-count="85" data-suffix="٪">۰</span></div><div class="lbl">افزایش پایبندی دانش‌آموز</div></div>
      <div class="card hover-lift stat-pill reveal" data-d="3"><div class="big gold"><span data-count="70" data-suffix="٪">۰</span></div><div class="lbl">کاهش زمان مدیریت</div></div>
    </div>
  </div>
</section>

<!-- ============ SECURITY / TECH ============ -->
<section class="section" id="tech">
  <div class="container">
    <div class="section-head">
      <span class="eyebrow">امنیت و فناوری</span>
      <h2 class="section-title">ساخته‌شده تا قابل اعتماد باشد</h2>
    </div>
    <div class="tech-grid">
      <?php foreach ([
        ['lock','رمزنگاری bcrypt','گذرواژه‌ها هرگز فاش نمی‌شوند'],
        ['shield','محافظت CSRF','هر فرم با توکن امن'],
        ['clock','محدودیت نرخ','جلوگیری از حملات تکراری'],
        ['globe','نشست امن','کوکی httpOnly و SameSite'],
      ] as $n=>$t): ?>
      <div class="card hover-lift tech-item reveal" data-d="<?= $n+1 ?>">
        <span class="icon-tile <?= $n%2?'sage':'' ?>"><?= icon($t[0],24) ?></span>
        <div class="t"><?= e($t[1]) ?></div>
        <div class="d"><?= e($t[2]) ?></div>
      </div>
      <?php endforeach; ?>
    </div>
    <div class="text-c mt-6"><span class="tech-tag" style="font-size:.95rem;padding:10px 22px">PHP 8 + MySQL + PWA</span></div>
  </div>
</section>

<!-- ============ CTA ============ -->
<section class="section">
  <div class="container">
    <div class="cta-band reveal">
      <h2>آماده‌اید مسیر کنکورتان را <span class="gradient-text">منظم</span> کنید؟</h2>
      <p class="lead" style="margin:0 auto 26px;max-width:520px">همین حالا حساب دانش‌آموزی بسازید و منتظر تأیید مشاورتان بمانید.</p>
      <div class="flex gap-3 wrap" style="justify-content:center">
        <a href="<?= url('auth/register.php') ?>" class="btn btn-gold btn-lg"><?= icon('user',18) ?> ثبت‌نام دانش‌آموز</a>
        <a href="<?= url('auth/login.php') ?>" class="btn btn-ghost btn-lg"><?= icon('login',18) ?> ورود به حساب</a>
      </div>
    </div>
  </div>
</section>

<!-- ============ FOOTER ============ -->
<footer class="site-footer">
  <div class="container">
    <div class="footer-grid">
      <div>
        <?= brand_block() ?>
        <p class="muted mt-4" style="max-width:320px"><?= e(APP_TAGLINE) ?>. طراحی‌شده برای دانش‌آموزان کنکور زیر نظر <?= e(APP_OWNER) ?>.</p>
      </div>
      <div>
        <h4>دسترسی سریع</h4>
        <a href="#features">امکانات</a>
        <a href="#flow">مسیر کاربری</a>
        <a href="#panels">پنل‌ها</a>
        <a href="#tech">امنیت</a>
      </div>
      <div>
        <h4>حساب کاربری</h4>
        <a href="<?= url('auth/login.php') ?>">ورود</a>
        <a href="<?= url('auth/register.php') ?>">ثبت‌نام دانش‌آموز</a>
      </div>
    </div>
    <div class="footer-bottom">
      <span>© <?= fa_num(date('Y')) ?> · <?= e(APP_OWNER) ?> — همه حقوق محفوظ است.</span>
      <span class="flex gap-2"><span class="tech-tag" style="padding:5px 12px;font-size:.74rem">نسخه <?= fa_num(APP_VERSION) ?></span></span>
    </div>
  </div>
</footer>
<?php page_foot(); ?>
