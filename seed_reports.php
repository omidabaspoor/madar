<?php
/**
 * ساخت گزارش‌های پیشرفته نمونه برای تست پنل گزارش‌دهی
 * بعد از استفاده در محیط واقعی حذف شود.
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/reporting.php';
require_once __DIR__ . '/includes/panel_layout.php';
boot_session();
require_role('advisor','admin');
$u = current_user();
report_schema_ready();

function sample_report_payload(string $type, int $i = 0): array
{
    $moods = ['آرام و متمرکز', 'خوب ولی کمی خسته', 'پر انرژی', 'متوسط؛ نیاز به خواب بهتر', 'پراسترس اما قابل کنترل'];
    $wins = [
        'تسک‌های اصلی را کامل‌تر و با تمرکز بیشتری انجام دادم.',
        'تعداد تست‌ها نسبت به روزهای قبل بهتر شد و خطاها را تحلیل کردم.',
        'در درس‌های اختصاصی پیوستگی بهتری داشتم.',
        'با وجود خستگی، برنامه را رها نکردم و بخش مهمی را جلو بردم.',
    ];
    $challenges = [
        'شروع مطالعه کمی دیر شد و باعث فشار انتهای روز شد.',
        'در بعضی تست‌ها زمان‌دار کار نکردم و سرعت پایین بود.',
        'حواس‌پرتی با موبایل و پیام‌ها بخشی از زمان را گرفت.',
        'مرور بعد از مطالعه کافی نبود و بعضی نکات فراموش شد.',
    ];
    $solutions = [
        'فردا شروع اولین واحد را زودتر می‌گذارم و موبایل را دورتر می‌گذارم.',
        'برای تست‌ها زمان‌سنج می‌گذارم و بعد از هر بسته تست تحلیل کوتاه می‌نویسم.',
        'بین واحدها استراحت کوتاه و کنترل‌شده می‌گذارم تا افت تمرکز کمتر شود.',
        'آخر شب ۲۰ دقیقه مرور خلاصه و غلط‌نامه اضافه می‌کنم.',
    ];
    $weak = ['فیزیک', 'شیمی', 'ریاضی', 'زیست‌شناسی', 'ادبیات'];
    $best = ['زیست‌شناسی', 'شیمی', 'دینی', 'ریاضی', 'زبان انگلیسی'];
    $payload = [
        'sleep_hours' => [6.5, 7, 7.5, 6, 8][$i % 5],
        'sleep_quality' => [3, 4, 4, 2, 5][$i % 5],
        'focus_score' => [7, 8, 6, 7, 9][$i % 5],
        'energy_score' => [7, 6, 8, 6, 9][$i % 5],
        'stress_score' => [5, 4, 6, 7, 3][$i % 5],
        'phone_minutes' => [55, 40, 75, 90, 35][$i % 5],
        'wasted_minutes' => [35, 25, 50, 60, 20][$i % 5],
        'mood' => $moods[$i % count($moods)],
        'best_win' => $wins[$i % count($wins)],
        'main_challenge' => $challenges[$i % count($challenges)],
        'challenge_reason' => 'علت احتمالی: مدیریت شروع روز، انرژی و حواس‌پرتی‌ها نیاز به کنترل دقیق‌تر دارد.',
        'solution' => $solutions[$i % count($solutions)],
        'advisor_question' => 'برای بهتر شدن تحلیل تست‌ها و اولویت‌بندی فردا چه پیشنهادی دارید؟',
        'next_priority' => 'اولویت بعدی: جبران تسک‌های قرمز، مرور غلط‌نامه و افزایش تست زمان‌دار.',
        'self_score' => [14, 16, 13, 15, 17][$i % 5],
        'main_reason' => ['کمبود وقت','خستگی','حواس‌پرتی/موبایل','سختی مبحث','مدرسه/کلاس'][$i % 5],
        'week_rating' => ['خوب','متوسط','خوب','ضعیف','عالی'][$i % 5],
        'plan_fit' => ['مناسب','سنگین','مناسب','نامتعادل','سبک'][$i % 5],
        'advisor_followup' => ['خیر','بله معمولی','خیر','بله فوری','خیر'][$i % 5],
        'month_satisfaction' => [7,6,8,5,9][$i % 5],
        'monthly_mindset' => ['پایدار','خسته','رو به رشد','پراسترس','رو به رشد'][$i % 5],
        'next_month_goal_type' => ['افزایش تست','جبران عقب‌ماندگی','تثبیت','افزایش ساعت','آزمون‌محور شدن'][$i % 5],
    ];
    if ($type !== 'daily') {
        $payload += [
            'best_subject' => $best[$i % count($best)],
            'weak_subject' => $weak[$i % count($weak)],
            'exam_count' => [1, 2, 1, 3, 2][$i % 5],
            'exam_analysis_quality' => [3, 4, 3, 5, 4][$i % 5],
            'catchup_hours' => [1.5, 2, 0.5, 3, 1][$i % 5],
            'next_period_goal' => 'هدف بازه بعد: افزایش پیوستگی اجرا، کاهش زمان تلف‌شده و تحلیل کامل‌تر آزمون‌ها.',
        ];
    }
    return $payload;
}

$done = [];
$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $targetMode = $_POST['target'] ?? 'first';
    $students = advisor_students((int)$u['id'], 'active');
    if ($targetMode === 'first') $students = array_slice($students, 0, 1);
    if (!$students) $err = 'دانش‌آموز فعالی پیدا نشد.';
    else {
        foreach ($students as $si => $s) {
            $sid = (int)$s['id'];
            // ۷ گزارش روزانه اخیر
            for ($i=0; $i<7; $i++) {
                $date = date('Y-m-d', strtotime("-$i day"));
                $r = report_submit($sid, 'daily', $date, sample_report_payload('daily', $i + $si));
                $done[] = $s['full_name'] . ' · روزانه · ' . jalali_date($r['period_start']);
            }
            // ۳ گزارش هفتگی اخیر
            for ($i=0; $i<3; $i++) {
                $date = date('Y-m-d', strtotime("-$i week"));
                $r = report_submit($sid, 'weekly', $date, sample_report_payload('weekly', $i + $si));
                $done[] = $s['full_name'] . ' · هفتگی · ' . jalali_date($r['period_start']);
            }
            // ۲ گزارش ماهانه اخیر
            for ($i=0; $i<2; $i++) {
                $date = date('Y-m-d', strtotime("-$i month"));
                $r = report_submit($sid, 'monthly', $date, sample_report_payload('monthly', $i + $si));
                $done[] = $s['full_name'] . ' · ماهانه · ' . jalali_date($r['period_start']);
            }
        }
    }
}

page_head('ساخت گزارش نمونه');
?>
<div class="auth-shell" style="min-height:100vh;display:grid;place-items:center;padding:24px">
  <div class="panel" style="max-width:720px;width:100%">
    <div class="brand" style="justify-content:center;margin-bottom:18px"><?= logo_svg(56) ?></div>
    <h2 style="text-align:center;margin-bottom:8px">ساخت گزارش‌های پیشرفته نمونه</h2>
    <p class="muted" style="text-align:center;margin-bottom:20px">برای تست صفحه گزارش‌دهی دانش‌آموز و گزارش حرفه‌ای مشاور، چند گزارش روزانه/هفتگی/ماهانه ساخته می‌شود.</p>
    <?php if($err): ?><div class="alert alert-error"><?= e($err) ?></div><?php endif; ?>
    <?php if($done): ?>
      <div class="alert alert-success">✅ <?= fa_num(count($done)) ?> گزارش نمونه ساخته شد.</div>
      <div style="max-height:260px;overflow:auto;margin:14px 0;border:1px solid var(--border-soft);border-radius:16px;padding:10px">
        <?php foreach($done as $d): ?><div class="badge" style="margin:3px"><?= e($d) ?></div><?php endforeach; ?>
      </div>
      <div class="flex gap-2 wrap">
        <a class="btn btn-gold" href="<?= url('admin/student_reports.php') ?>">مشاهده گزارش حرفه‌ای</a>
        <a class="btn btn-ghost" href="<?= url('student/reports.php') ?>">مشاهده گزارش دانش‌آموز</a>
      </div>
    <?php else: ?>
      <form method="post">
        <?= csrf_field() ?>
        <div class="field" style="margin-bottom:16px">
          <label style="display:block;font-weight:800;margin-bottom:8px">برای چه کسانی ساخته شود؟</label>
          <select class="input" name="target">
            <option value="first">فقط اولین دانش‌آموز فعال</option>
            <option value="all">همه دانش‌آموزان فعال</option>
          </select>
        </div>
        <button class="btn btn-gold btn-block" type="submit">ساخت گزارش‌های نمونه</button>
      </form>
    <?php endif; ?>
    <p class="muted" style="font-size:.8rem;margin-top:18px">نکته امنیتی: بعد از تست روی هاست واقعی، بهتر است فایل <code>seed_reports.php</code> را حذف کنید.</p>
  </div>
</div>
<?php page_foot(); ?>
