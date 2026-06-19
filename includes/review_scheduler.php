<?php
/** مرورهای فاصله‌دار برای جلوگیری از فراموشی */
declare(strict_types=1);
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/models.php';

const REVIEW_INTERVALS = [1, 3, 7, 14, 30, 60];

function review_norm(string $s): string
{
    $s = str_replace(['ي','ك','‌'], ['ی','ک',' '], trim($s));
    return preg_replace('/\s+/u', ' ', $s) ?: '';
}
function review_profile_for_subject(?string $subjectName, string $taskType='study'): array
{
    $n = review_norm((string)$subjectName);
    // مبنا: مرور کوتاه همان روز داخل خود برنامه/واحد ویژه انجام می‌شود؛ یادآورهای سیستمی از روزهای بعد شروع می‌شوند.
    // برای کنکور، بازه‌ها نباید آن‌قدر فشرده باشند که فرصت مطالعه درس‌های دیگر را بگیرند.
    if ($n !== '' && mb_strpos($n, 'زیست') !== false) {
        return ['key'=>'bio','label'=>'زیست/فرّار','intervals'=>[1,3,7,14,30,60], 'minutes'=>15];
    }
    foreach (['دینی','دین','ادبیات','عربی'] as $k) {
        if ($n !== '' && mb_strpos($n, $k) !== false) {
            return ['key'=>'memorization','label'=>'حفظی کنکوری','intervals'=>[1,7,14,30,60,90], 'minutes'=>12];
        }
    }
    foreach (['زبان','لغت'] as $k) {
        if ($n !== '' && mb_strpos($n, $k) !== false) {
            return ['key'=>'vocabulary','label'=>'لغت/زبان','intervals'=>[1,3,7,14,30,60], 'minutes'=>10];
        }
    }
    foreach (['هویت','سلامت'] as $k) {
        if ($n !== '' && mb_strpos($n, $k) !== false) {
            return ['key'=>'light_memory','label'=>'حفظی سبک','intervals'=>[1,7,14,30,60], 'minutes'=>10];
        }
    }
    if ($n !== '' && mb_strpos($n, 'شیمی') !== false) {
        return ['key'=>'mixed','label'=>'ترکیبی','intervals'=>[1,7,14,30,60], 'minutes'=>15];
    }
    foreach (['ریاضی','حسابان','هندسه','گسسته','فیزیک'] as $k) {
        if ($n !== '' && mb_strpos($n, $k) !== false) {
            return ['key'=>'problem_solving','label'=>'تمرینی/محاسباتی','intervals'=>[3,7,14,30,60], 'minutes'=>20];
        }
    }
    if ($taskType === 'textbook' || $taskType === 'reading') {
        return ['key'=>'reading','label'=>'خواندنی','intervals'=>[1,7,14,30,60], 'minutes'=>12];
    }
    return ['key'=>'standard','label'=>'استاندارد','intervals'=>[1,7,14,30,60], 'minutes'=>12];
}
function review_profile_for_task(array $t): array
{
    $subject = $t['subj_name'] ?? '';
    if (!$subject && !empty($t['subject_id'])) {
        try { $q=db()->prepare('SELECT name FROM subjects WHERE id=?'); $q->execute([(int)$t['subject_id']]); $subject=(string)$q->fetchColumn(); } catch(Throwable $e) {}
    }
    return review_profile_for_subject($subject, (string)($t['task_type'] ?? 'study'));
}

function review_schema_ready(): bool
{
    static $ok = null;
    if ($ok !== null) return $ok;
    try {
        db()->exec("CREATE TABLE IF NOT EXISTS review_reminders (
          id INT UNSIGNED NOT NULL AUTO_INCREMENT,
          student_id INT UNSIGNED NOT NULL,
          source_task_id INT UNSIGNED NOT NULL,
          subject_id INT UNSIGNED DEFAULT NULL,
          topic_title VARCHAR(180) NOT NULL,
          source VARCHAR(160) DEFAULT NULL,
          first_studied_at DATETIME NOT NULL,
          interval_days INT UNSIGNED NOT NULL,
          review_no TINYINT UNSIGNED NOT NULL DEFAULT 1,
          profile_key VARCHAR(40) DEFAULT NULL,
          profile_label VARCHAR(80) DEFAULT NULL,
          suggested_minutes INT UNSIGNED DEFAULT 15,
          due_date DATE NOT NULL,
          status ENUM('pending','done','dismissed') NOT NULL DEFAULT 'pending',
          notified_at DATETIME DEFAULT NULL,
          completed_at DATETIME DEFAULT NULL,
          quality ENUM('hard','good','easy') DEFAULT NULL,
          created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
          updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          PRIMARY KEY (id),
          UNIQUE KEY uq_review_step (source_task_id, interval_days),
          KEY idx_review_student_due (student_id, status, due_date),
          KEY idx_review_source (source_task_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $cols=[]; foreach(db()->query('SHOW COLUMNS FROM review_reminders')->fetchAll() as $c) $cols[$c['Field']]=true;
        $adds=[
          'review_no'=>'ALTER TABLE review_reminders ADD COLUMN review_no TINYINT UNSIGNED NOT NULL DEFAULT 1 AFTER interval_days',
          'profile_key'=>'ALTER TABLE review_reminders ADD COLUMN profile_key VARCHAR(40) DEFAULT NULL AFTER review_no',
          'profile_label'=>'ALTER TABLE review_reminders ADD COLUMN profile_label VARCHAR(80) DEFAULT NULL AFTER profile_key',
          'suggested_minutes'=>'ALTER TABLE review_reminders ADD COLUMN suggested_minutes INT UNSIGNED DEFAULT 15 AFTER profile_label',
          'quality'=>"ALTER TABLE review_reminders ADD COLUMN quality ENUM('hard','good','easy') DEFAULT NULL AFTER completed_at",
        ];
        foreach($adds as $col=>$sql) {
            if(empty($cols[$col])) {
                try { db()->exec($sql); } catch (Throwable $alterError) { error_log('Madar review schema alter '.$col.': '.$alterError->getMessage()); }
            }
        }
        return $ok = true;
    } catch (Throwable $e) { error_log('Madar review schema error: '.$e->getMessage()); return $ok = false; }
}

function review_is_eligible_task(array $t): bool
{
    $type = (string)($t['task_type'] ?? '');
    if (!in_array($type, ['study','textbook','reading','custom'], true)) return false;
    $title = trim((string)($t['title'] ?? ''));
    if ($title === '') return false;
    // تسک‌های تستی/آزمونی را با عنوان هم حذف کن تا مرورهای اضافی ساخته نشود.
    $bad = ['تست','آزمون','آزمونک','تحلیل'];
    foreach ($bad as $b) if (mb_strpos($title, $b) !== false && $type !== 'textbook') return false;
    return true;
}

function review_create_for_task(int $taskId): int
{
    if (!review_schema_ready()) return 0;
    $st = db()->prepare('SELECT t.*, p.status plan_status, s.name subj_name FROM tasks t JOIN plans p ON p.id=t.plan_id LEFT JOIN subjects s ON s.id=t.subject_id WHERE t.id=? LIMIT 1');
    $st->execute([$taskId]);
    $t = $st->fetch();
    if (!$t || !review_is_eligible_task($t)) return 0;
    $advisorId = (int)($t['advisor_id'] ?? 0);
    if ($advisorId && function_exists('advisor_feature_enabled') && !advisor_feature_enabled($advisorId, 'review_enabled')) return 0;
    $status = task_status($t);
    if (!in_array($status, ['full','partial'], true)) return 0;
    $first = $t['completed_at'] ?: date('Y-m-d H:i:s');
    $baseDate = date('Y-m-d', strtotime($first));
    $profile = review_profile_for_task($t);
    $ins = db()->prepare('INSERT IGNORE INTO review_reminders (student_id,source_task_id,subject_id,topic_title,source,first_studied_at,interval_days,review_no,profile_key,profile_label,suggested_minutes,due_date) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)');
    $n = 0; $i = 1;
    foreach ($profile['intervals'] as $days) {
        $due = date('Y-m-d', strtotime($baseDate.' +'.$days.' day'));
        $ins->execute([(int)$t['student_id'], (int)$t['id'], $t['subject_id'] ?: null, (string)$t['title'], $t['source'] ?? null, $first, $days, $i++, $profile['key'], $profile['label'], (int)$profile['minutes'], $due]);
        $n += $ins->rowCount();
    }
    return $n;
}

function review_due_notifications(int $studentId): int
{
    $stu = get_user($studentId);
    $advisorId = (int)($stu['advisor_id'] ?? 0);
    if ($advisorId && function_exists('advisor_feature_enabled') && !advisor_feature_enabled($advisorId, 'review_enabled')) return 0;
    if (!review_schema_ready()) return 0;
    $st = db()->prepare("SELECT * FROM review_reminders WHERE student_id=? AND status='pending' AND due_date<=CURDATE() AND notified_at IS NULL ORDER BY due_date, interval_days LIMIT 20");
    $st->execute([$studentId]);
    $rows = $st->fetchAll();
    $up = db()->prepare('UPDATE review_reminders SET notified_at=NOW() WHERE id=?');
    $n = 0;
    foreach ($rows as $r) {
        $days = (int)$r['interval_days'];
        $msg = $days===1 ? 'یک روز از مطالعه این مبحث گذشته؛ مرور کوتاه کمک می‌کند تثبیت شود.' : ($days===7 ? 'یک هفته از مطالعه این مبحث گذشته؛ وقت مرور فاصله‌دار است.' : ($days===10 ? 'ده روز از مطالعه این مبحث گذشته؛ مرور الان جلوی فراموشی را می‌گیرد.' : 'سی روز از مطالعه این مبحث گذشته؛ مرور جمع‌بندی انجام بده.'));
        notify($studentId, 'وقت مرور «'.$r['topic_title'].'» 🔁', $msg, 'review', 'student/reviews.php');
        $up->execute([$r['id']]);
        $n++;
    }
    return $n;
}

function review_counts(int $studentId): array
{
    if (!review_schema_ready()) return ['due'=>0,'upcoming'=>0,'done'=>0];
    $st = db()->prepare("SELECT
      SUM(status='pending' AND due_date<=CURDATE()) due_count,
      SUM(status='pending' AND due_date>CURDATE()) upcoming_count,
      SUM(status='done') done_count
      FROM review_reminders WHERE student_id=?");
    $st->execute([$studentId]);
    $r = $st->fetch() ?: [];
    return ['due'=>(int)($r['due_count']??0),'upcoming'=>(int)($r['upcoming_count']??0),'done'=>(int)($r['done_count']??0)];
}

function review_items(int $studentId, string $scope='due'): array
{
    if (!review_schema_ready()) return [];
    if ($scope === 'upcoming') $where = "status='pending' AND due_date>CURDATE()";
    elseif ($scope === 'done') $where = "status='done'";
    else $where = "status='pending' AND due_date<=CURDATE()";
    $st = db()->prepare("SELECT rr.*, s.name subject_name, s.color subject_color FROM review_reminders rr LEFT JOIN subjects s ON s.id=rr.subject_id WHERE rr.student_id=? AND $where ORDER BY rr.due_date ASC, rr.interval_days ASC LIMIT 80");
    $st->execute([$studentId]);
    return $st->fetchAll();
}


function review_complete_item(int $reviewId, int $studentId, string $quality='good'): bool
{
    if (!review_schema_ready()) return false;
    $quality = in_array($quality, ['hard','good','easy'], true) ? $quality : 'good';
    $st = db()->prepare('SELECT * FROM review_reminders WHERE id=? AND student_id=? LIMIT 1');
    $st->execute([$reviewId,$studentId]);
    $r = $st->fetch();
    if (!$r) return false;
    db()->prepare("UPDATE review_reminders SET status='done', completed_at=NOW(), quality=? WHERE id=?")->execute([$quality,$reviewId]);
    // اگر مرور سخت بود، یک یادآور تقویتی کوتاه برای فردا بساز.
    if ($quality === 'hard') {
        $due = date('Y-m-d', strtotime('+1 day'));
        $ins = db()->prepare('INSERT IGNORE INTO review_reminders (student_id,source_task_id,subject_id,topic_title,source,first_studied_at,interval_days,review_no,profile_key,profile_label,suggested_minutes,due_date) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)');
        $ins->execute([$studentId,(int)$r['source_task_id'],$r['subject_id'] ?: null,$r['topic_title'],$r['source'] ?? null,$r['first_studied_at'],0,99,$r['profile_key'] ?: 'reinforce','مرور تقویتی',10,$due]);
    }
    return true;
}
