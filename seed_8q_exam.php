<?php
/**
 * ساخت «آزمون ۸ سواله تصویرمحور نمونه» جهت تست دموی سامورایی
 * با باز کردن این اسکریپت در مرورگر، آزمون نمونه به‌صورت خودکار ساخته می‌شود.
 */
declare(strict_types=1);
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/models.php';

function seed_samurai_8q_exam(): array {
    $pdo = db();
    
    // پیدا کردن مشاور اصلی
    $adv = $pdo->query('SELECT id FROM users WHERE username="sajjad" OR role="admin" ORDER BY id LIMIT 1')->fetch();
    $advisorId = $adv ? (int)$adv['id'] : 1;

    // بررسی وجود آزمون نمونه قبلی
    $chk = $pdo->prepare('SELECT id FROM exams WHERE advisor_id=? AND title LIKE "%۸ سواله%" LIMIT 1');
    $chk->execute([$advisorId]);
    if ($ex = $chk->fetch()) {
        return ['ok'=>true, 'id'=>(int)$ex['id'], 'msg'=>'آزمون ۸ سواله از قبل در سیستم وجود دارد.'];
    }

    $title    = 'آزمون تصویرمحور کنکور (۸ سواله ویژه دمو)';
    $desc     = 'دموی تعاملی محیط دوپنله (دفترچه‌ی سوالات آپلودشده در کنار پاسخ‌برگ حبابی تعاملی)';
    $sheetPath= 'assets/img/logo.png'; // تصویر نمونه دفترچه
    $answerKey= '42311423';            // کلید ۸ سوال

    $pdo->beginTransaction();
    try {
        $ins = $pdo->prepare('INSERT INTO exams (advisor_id, title, description, creation_mode, sheet_path, answer_key, exam_type, timing_mode, duration_min, status, assign_all) VALUES (?, ?, ?, "quick_sheet", ?, ?, "single", "total", 30, "published", 1)');
        $ins->execute([$advisorId, $title, $desc, $sheetPath, $answerKey]);
        $examId = (int)$pdo->lastInsertId();

        $pdo->prepare('INSERT INTO exam_sections (exam_id, name, sort_order) VALUES (?, "سوالات دفترچه", 1)')->execute([$examId]);
        $secId = (int)$pdo->lastInsertId();

        $qIns = $pdo->prepare('INSERT INTO exam_questions (exam_id, section_id, q_text, q_image, correct_opt, sort_order) VALUES (?, ?, ?, ?, ?, ?)');
        for ($i=0; $i<8; $i++) {
            $cor = (int)$answerKey[$i];
            $qIns->execute([$examId, $secId, 'سوال ' . fa_num($i+1), $sheetPath, $cor, $i+1]);
        }

        // تخصیص یک attempt نمونه برای علی رضایی جهت مشاهده آنی
        $stu = $pdo->query('SELECT id FROM users WHERE username="ali_rezaei" LIMIT 1')->fetch();
        if ($stu) {
            $stuId = (int)$stu['id'];
            $pdo->prepare('INSERT IGNORE INTO exam_attempts (exam_id, student_id, deadline_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 30 MINUTE))')->execute([$examId, $stuId]);
        }

        $pdo->commit();
        return ['ok'=>true, 'id'=>$examId, 'msg'=>'آزمون ۸ سواله تصویرمحور با موفقیت ساخته شد و منتشر گردید! 🎉'];
    } catch(Throwable $e) {
        $pdo->rollBack();
        return ['ok'=>false, 'error'=>$e->getMessage()];
    }
}

$res = seed_samurai_8q_exam();
require_once __DIR__ . '/includes/layout.php';
page_head('ساخت آزمون نمونه ۸ سواله');
?>
<div style="min-height:100vh;display:grid;place-items:center;padding:24px;background:var(--bg)">
  <div class="card text-c panel" style="max-width:520px;width:100%;padding:40px;background:var(--surface-1);border:1px solid var(--gold);border-radius:24px">
    <span style="font-size:3.5rem;color:var(--gold);display:block;margin-bottom:16px"><?= icon('image',56) ?></span>
    <h1 style="font-size:1.6rem;font-weight:900;color:var(--text-1);margin-bottom:12px">ساخت آزمون تصویرمحور (۸ سواله)</h1>
    
    <?php if($res['ok']): ?>
      <div class="alert alert-success mt-3 mb-4" style="text-align:right"><?= icon('check',18) ?> <span><?= e($res['msg']) ?></span></div>
      <div class="grid gap-3" style="grid-template-columns:1fr 1fr">
        <a href="<?= url('admin/exams.php') ?>" class="btn btn-gold btn-lg">👨‍⚕️ مشاهده در پنل مشاور</a>
        <a href="<?= url('student/exams.php') ?>" class="btn btn-sage btn-lg">🎓 شرکت به‌عنوان دانش‌آموز</a>
      </div>
    <?php else: ?>
      <div class="alert alert-error mt-3 mb-4"><?= icon('close',18) ?> <span>خطا: <?= e($res['error']) ?></span></div>
      <a href="<?= url('admin/exams.php') ?>" class="btn btn-ghost">بازگشت</a>
    <?php endif; ?>
  </div>
</div>
<?php page_foot(); ?>
