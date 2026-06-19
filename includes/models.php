<?php
/** توابع داده‌ای (کوئری‌های پرکاربرد) */
declare(strict_types=1);
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/planner_settings.php';


/* ---------- سیستم سه‌حالته وضعیت تسک ---------- */
const TASK_STATUS_LABELS = [
    'pending' => 'در انتظار',
    'full'    => 'اجرای کامل',
    'partial' => 'اجرای ناقص',
    'missed'  => 'عدم اجرا',
];
const TASK_FEELINGS = [
    'great' => ['emoji'=>'😄','label'=>'عالی'],
    'good'  => ['emoji'=>'🙂','label'=>'خوب'],
    'hard'  => ['emoji'=>'😵‍💫','label'=>'سخت بود'],
    'tired' => ['emoji'=>'😴','label'=>'خسته‌کننده'],
    'bad'   => ['emoji'=>'😣','label'=>'بد/نامفهوم'],
];
function task_status_schema_ready(): bool
{
    static $ready = null;
    if ($ready !== null) return $ready;
    try {
        $cols = [];
        foreach (db()->query('SHOW COLUMNS FROM tasks')->fetchAll() as $c) $cols[$c['Field']] = true;
        if (empty($cols['completion_status'])) db()->exec("ALTER TABLE tasks ADD COLUMN completion_status ENUM('pending','full','partial','missed') NOT NULL DEFAULT 'pending' AFTER is_done");
        if (empty($cols['course_percent'])) db()->exec("ALTER TABLE tasks ADD COLUMN course_percent TINYINT UNSIGNED DEFAULT NULL AFTER completion_status");
        if (empty($cols['student_feeling'])) db()->exec("ALTER TABLE tasks ADD COLUMN student_feeling VARCHAR(30) DEFAULT NULL AFTER course_percent");
        if (empty($cols['status_updated_at'])) db()->exec("ALTER TABLE tasks ADD COLUMN status_updated_at DATETIME DEFAULT NULL AFTER completed_at");
        // تبدیل داده‌های قدیمی: تیک‌های قبلی = کامل، بقیه = در انتظار
        db()->exec("UPDATE tasks SET completion_status=IF(is_done=1,'full','pending') WHERE completion_status IS NULL OR completion_status='' ");
        return $ready = true;
    } catch (Throwable $e) { return $ready = false; }
}
function task_status(array $t): string
{
    $s = (string)($t['completion_status'] ?? '');
    if (isset(TASK_STATUS_LABELS[$s])) return $s;
    return !empty($t['is_done']) ? 'full' : 'pending';
}

function score_display($v): string
{
    $f = (float)$v;
    if (abs($f - round($f)) < 0.001) return (string)(int)round($f);
    return rtrim(rtrim(number_format($f, 1, '.', ''), '0'), '.');
}

function task_score_sql(string $alias='t'): string
{
    // کامل = ۱ امتیاز؛ اگر تست/مقدار بیشتر از هدف زده شده باشد تا سقف ۰.۲۵ امتیاز تشویقی می‌گیرد.
    // ناقص = ۰.۵ امتیاز ثابت؛ قرمز/درانتظار = ۰
    return "CASE
        WHEN {$alias}.completion_status='full' OR ({$alias}.completion_status IS NULL AND {$alias}.is_done=1) THEN
            CASE WHEN {$alias}.target_count IS NOT NULL AND {$alias}.target_count > 0 AND {$alias}.done_count > {$alias}.target_count
                 THEN 1 + LEAST(0.25, (({$alias}.done_count - {$alias}.target_count) / {$alias}.target_count) * 0.25)
                 ELSE 1 END
        WHEN {$alias}.completion_status='partial' THEN 0.5
        ELSE 0 END";
}
function task_score(array $t): float
{
    $status = task_status($t);
    if ($status === 'partial') return 0.5;
    if ($status !== 'full') return 0.0;
    $target = isset($t['target_count']) ? (int)$t['target_count'] : 0;
    $done = isset($t['done_count']) ? (int)$t['done_count'] : 0;
    if ($target > 0 && $done > $target) return 1.0 + min(0.25, (($done - $target) / $target) * 0.25);
    return 1.0;
}
function is_feeling_task(string $type): bool
{
    return in_array($type, ['study','review','textbook','reading','analysis','custom'], true);
}
function feeling_info(?string $key): ?array
{
    return $key && isset(TASK_FEELINGS[$key]) ? TASK_FEELINGS[$key] : null;
}
/** تسک‌های روزهای گذشته که هنوز ثبت نشده‌اند، خودکار قرمز می‌شوند. */
function auto_mark_missed_tasks(?int $studentId = null): int
{
    if (!task_status_schema_ready()) return 0;
    $sql = "UPDATE tasks t JOIN plans p ON p.id=t.plan_id
            SET t.completion_status='missed', t.is_done=0, t.done_count=0, t.course_percent=0,
                t.completed_at=NULL, t.status_updated_at=NOW()
            WHERE p.status='published' AND t.completion_status='pending'
              AND DATE_ADD(p.week_start, INTERVAL t.day_index DAY) < CURDATE()";
    $params = [];
    if ($studentId !== null) { $sql .= ' AND t.student_id=?'; $params[] = $studentId; }
    $st = db()->prepare($sql); $st->execute($params);
    return $st->rowCount();
}
function task_status_badge(array $t): string
{
    $s = task_status($t);
    $label = TASK_STATUS_LABELS[$s] ?? $s;
    $icon = ['full'=>'✓','partial'=>'●','missed'=>'×','pending'=>'…'][$s] ?? '…';
    return '<span class="task-status-badge ts-'.$s.'"><b>'.$icon.'</b> '.e($label).'</span>';
}

/* ---------- دانش‌آموزان یک مشاور ---------- */
function advisor_students(int $advisorId, ?string $status = null, string $q = ''): array
{
    task_status_schema_ready();
    $score = task_score_sql('t');
    $sql = 'SELECT u.*,
            (SELECT COUNT(*) FROM tasks t WHERE t.student_id=u.id) AS total_tasks,
            (SELECT COALESCE(SUM('.$score.'),0) FROM tasks t WHERE t.student_id=u.id) AS done_tasks,
            (SELECT COUNT(*) FROM tasks t WHERE t.student_id=u.id AND t.completion_status="full") AS full_tasks,
            (SELECT COUNT(*) FROM tasks t WHERE t.student_id=u.id AND t.completion_status="partial") AS partial_tasks,
            (SELECT COUNT(*) FROM tasks t WHERE t.student_id=u.id AND t.completion_status="missed") AS missed_tasks
            FROM users u WHERE u.role="student" AND (u.advisor_id=? OR ? IN (SELECT id FROM users WHERE role="admin"))';
    $params = [$advisorId, $advisorId];
    if ($status) { $sql .= ' AND u.status=?'; $params[] = $status; }
    if ($q !== '') { $sql .= ' AND (u.full_name LIKE ? OR u.username LIKE ?)'; $params[] = "%$q%"; $params[] = "%$q%"; }
    $sql .= ' ORDER BY FIELD(u.status,"pending","active","suspended"), u.created_at DESC';
    $st = db()->prepare($sql);
    $st->execute($params);
    return $st->fetchAll();
}

function get_user(int $id): ?array
{
    $st = db()->prepare('SELECT * FROM users WHERE id=? LIMIT 1');
    $st->execute([$id]);
    return $st->fetch() ?: null;
}

/* ---------- آمار مشاور ---------- */
function advisor_stats(int $advisorId): array
{
    $pdo = db();
    $total = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role='student'")->fetchColumn();
    $active = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role='student' AND status='active'")->fetchColumn();
    $pending = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role='student' AND status='pending'")->fetchColumn();
    $tasksDone = (int)$pdo->query("SELECT COUNT(*) FROM tasks WHERE is_done=1")->fetchColumn();
    $tasksTotal = (int)$pdo->query("SELECT COUNT(*) FROM tasks")->fetchColumn();
    $rate = $tasksTotal ? round($tasksDone / $tasksTotal * 100) : 0;
    return compact('total','active','pending','tasksDone','tasksTotal','rate');
}

/* ---------- برنامه‌ها ---------- */
function find_or_create_plan(int $studentId, int $advisorId, string $weekStart): array
{
    $st = db()->prepare('SELECT * FROM plans WHERE student_id=? AND week_start=? LIMIT 1');
    $st->execute([$studentId, $weekStart]);
    $plan = $st->fetch();
    if ($plan) return $plan;
    $ins = db()->prepare('INSERT INTO plans (student_id,advisor_id,week_start,status) VALUES (?,?,?,"draft")');
    $ins->execute([$studentId, $advisorId, $weekStart]);
    return find_or_create_plan($studentId, $advisorId, $weekStart);
}

function plan_tasks(int $planId): array
{
    $st = db()->prepare('SELECT * FROM tasks WHERE plan_id=? ORDER BY day_index, unit_index, sort_order, id');
    $st->execute([$planId]);
    return $st->fetchAll();
}

/** تسک‌ها به‌صورت گرید [day][unit] = [tasks] */
function tasks_grid(int $planId): array
{
    $grid = [];
    foreach (plan_tasks($planId) as $t) {
        $grid[(int)$t['day_index']][(int)$t['unit_index']][] = $t;
    }
    return $grid;
}

function plan_progress(int $planId): array
{
    task_status_schema_ready();
    $score = task_score_sql('t');
    $st = db()->prepare("SELECT COUNT(*) total, COALESCE(SUM($score),0) score,
        SUM(t.completion_status='full') full_count,
        SUM(t.completion_status='partial') partial_count,
        SUM(t.completion_status='missed') missed_count
        FROM tasks t WHERE t.plan_id=?");
    $st->execute([$planId]);
    $r = $st->fetch();
    $total = (int)$r['total']; $scoreVal = (float)$r['score'];
    return [
        'total'=>$total,
        'done'=>$scoreVal,
        'done_display'=>score_display($scoreVal),
        'full'=>(int)$r['full_count'], 'partial'=>(int)$r['partial_count'], 'missed'=>(int)$r['missed_count'],
        'percent'=>$total?round($scoreVal/$total*100):0
    ];
}

/* ---------- درس‌ها ---------- */
function all_subjects(): array
{
    return db()->query('SELECT * FROM subjects ORDER BY id')->fetchAll();
}

/* ---------- آمار دانش‌آموز ---------- */
function student_today_tasks(int $studentId): array
{
    task_status_schema_ready();
    auto_mark_missed_tasks($studentId);
    $weekStart = week_saturday();
    $todayIdx = persian_day_index(date('Y-m-d'));
    $st = db()->prepare('SELECT t.*, s.color subj_color, s.name subj_name FROM tasks t
        LEFT JOIN subjects s ON s.id=t.subject_id
        LEFT JOIN plans p ON p.id=t.plan_id
        WHERE t.student_id=? AND p.week_start=? AND t.day_index=? AND p.status="published"
        ORDER BY t.unit_index, t.sort_order, t.id');
    $st->execute([$studentId, $weekStart, $todayIdx]);
    return $st->fetchAll();
}

function student_week_stats(int $studentId): array
{
    task_status_schema_ready();
    auto_mark_missed_tasks($studentId);
    $weekStart = week_saturday();
    $score = task_score_sql('t');
    $st = db()->prepare("SELECT COUNT(*) total, COALESCE(SUM($score),0) score,
        SUM(t.completion_status='full') full_count,
        SUM(t.completion_status='partial') partial_count,
        SUM(t.completion_status='missed') missed_count
        FROM tasks t JOIN plans p ON p.id=t.plan_id
        WHERE t.student_id=? AND p.week_start=? AND p.status=\"published\"");
    $st->execute([$studentId, $weekStart]);
    $r = $st->fetch();
    $total=(int)$r['total']; $scoreVal=(float)$r['score'];
    return [
        'total'=>$total,'done'=>$scoreVal,
        'done_display'=>score_display($scoreVal),
        'full'=>(int)$r['full_count'], 'partial'=>(int)$r['partial_count'], 'missed'=>(int)$r['missed_count'],
        'percent'=>$total?round($scoreVal/$total*100):0
    ];
}

/** نمودار ۷ روز اخیر دانش‌آموز */
function student_week_chart(int $studentId): array
{
    task_status_schema_ready();
    auto_mark_missed_tasks($studentId);
    $out = [];
    $weekStart = week_saturday();
    $score = task_score_sql('t');
    for ($i=0; $i<7; $i++) {
        $st = db()->prepare("SELECT COUNT(*) total, COALESCE(SUM($score),0) score,
            SUM(t.completion_status='full') full_count,
            SUM(t.completion_status='partial') partial_count,
            SUM(t.completion_status='missed') missed_count
            FROM tasks t JOIN plans p ON p.id=t.plan_id
            WHERE t.student_id=? AND p.week_start=? AND t.day_index=? AND p.status=\"published\"");
        $st->execute([$studentId, $weekStart, $i]);
        $r = $st->fetch();
        $total=(int)$r['total']; $scoreVal=(float)$r['score'];
        $out[] = ['day'=>DAY_NAMES[$i],'total'=>$total,'done'=>$scoreVal,
            'done_display'=>score_display($scoreVal),
            'full'=>(int)$r['full_count'], 'partial'=>(int)$r['partial_count'], 'missed'=>(int)$r['missed_count'],
            'pct'=>$total?round($scoreVal/$total*100):0];
    }
    return $out;
}

/** پیشرفت به تفکیک درس */
function student_subject_progress(int $studentId): array
{
    task_status_schema_ready();
    $score = task_score_sql('t');
    $st = db()->prepare("SELECT COALESCE(s.name,t.title) name, COALESCE(s.color,\"#6b8872\") color,
        COUNT(*) total, COALESCE(SUM($score),0) done
        FROM tasks t LEFT JOIN subjects s ON s.id=t.subject_id
        WHERE t.student_id=? GROUP BY COALESCE(s.id,t.title) ORDER BY total DESC LIMIT 8");
    $st->execute([$studentId]);
    $rows = $st->fetchAll();
    foreach ($rows as &$r) { $r['pct'] = (int)$r['total'] ? round(((float)$r['done'])/(int)$r['total']*100) : 0; }
    return $rows;
}

/* ---------- به‌روزرسانی استریک ---------- */
function touch_streak(int $studentId): void
{
    $u = get_user($studentId);
    if (!$u) return;
    $today = date('Y-m-d');
    if ($u['last_active'] === $today) return;
    $yesterday = date('Y-m-d', strtotime('-1 day'));
    $streak = ($u['last_active'] === $yesterday) ? ((int)$u['streak'] + 1) : 1;
    $st = db()->prepare('UPDATE users SET streak=?, last_active=? WHERE id=?');
    $st->execute([$streak, $today, $studentId]);
}

/* ---------- پیام‌ها ---------- */
function conversation(int $a, int $b, int $limit = 200): array
{
    $st = db()->prepare('SELECT * FROM messages WHERE (sender_id=? AND receiver_id=?) OR (sender_id=? AND receiver_id=?) ORDER BY created_at ASC LIMIT ?');
    $st->bindValue(1,$a,PDO::PARAM_INT); $st->bindValue(2,$b,PDO::PARAM_INT);
    $st->bindValue(3,$b,PDO::PARAM_INT); $st->bindValue(4,$a,PDO::PARAM_INT);
    $st->bindValue(5,$limit,PDO::PARAM_INT);
    $st->execute();
    return $st->fetchAll();
}

function user_mood_schema_ready(): bool
{
    static $ready = null;
    if ($ready !== null) return $ready;
    try {
        $st = db()->query("SHOW COLUMNS FROM users LIKE 'mood_date'");
        if (!$st->fetch()) db()->exec("ALTER TABLE users ADD COLUMN mood_date DATE DEFAULT NULL AFTER mood");
        return $ready = true;
    } catch (Throwable $e) { return $ready = false; }
}

/* ======================= mood ======================= */
const MOODS = [
  'happy'    => ['emoji'=>'😄','label'=>'عالی','color'=>'#5fae7b'],
  'ok'       => ['emoji'=>'🙂','label'=>'خوب','color'=>'#8aa791'],
  'meh'      => ['emoji'=>'😐','label'=>'معمولی','color'=>'#cbac80'],
  'tired'    => ['emoji'=>'😴','label'=>'خسته','color'=>'#d9b25f'],
  'stressed' => ['emoji'=>'😣','label'=>'پراسترس','color'=>'#d97474'],
];
function mood_info(?string $key): ?array
{
    return $key && isset(MOODS[$key]) ? MOODS[$key] : null;
}
function current_mood_info(array $user): ?array
{
    user_mood_schema_ready();
    $date = (string)($user['mood_date'] ?? '');
    if ($date !== date('Y-m-d')) return null;
    return mood_info($user['mood'] ?? null);
}
function current_mood_key(array $user): ?string
{
    user_mood_schema_ready();
    return ((string)($user['mood_date'] ?? '') === date('Y-m-d')) ? ($user['mood'] ?? null) : null;
}

/* ======================= achievements ======================= */
/** همه‌ی دستاوردهای تعریف‌شده */
function all_achievements(bool $activeOnly = false): array
{
    $sql = 'SELECT * FROM achievements';
    if ($activeOnly) $sql .= ' WHERE is_active=1';
    $sql .= ' ORDER BY sort_order, id';
    return db()->query($sql)->fetchAll();
}

/** دستاوردهای کسب‌شده توسط یک دانش‌آموز (آرایه‌ی achievement_id => earned_at) */
function student_earned_ids(int $studentId): array
{
    $st = db()->prepare('SELECT achievement_id, earned_at FROM student_achievements WHERE student_id=?');
    $st->execute([$studentId]);
    $out = [];
    foreach ($st->fetchAll() as $r) $out[(int)$r['achievement_id']] = $r['earned_at'];
    return $out;
}

/** اعطای دستاورد به دانش‌آموز (در صورت نداشتن). برمی‌گرداند true اگر جدید اعطا شد */
function award_achievement(int $studentId, int $achId, ?int $by = null): bool
{
    $chk = db()->prepare('SELECT id FROM student_achievements WHERE student_id=? AND achievement_id=?');
    $chk->execute([$studentId, $achId]);
    if ($chk->fetch()) return false;
    $ins = db()->prepare('INSERT INTO student_achievements (student_id,achievement_id,awarded_by) VALUES (?,?,?)');
    $ins->execute([$studentId, $achId, $by]);
    $a = db()->prepare('SELECT title FROM achievements WHERE id=?'); $a->execute([$achId]);
    $title = (string)$a->fetchColumn();
    notify($studentId, 'دستاورد جدید! 🏆', 'نشان «' . $title . '» را کسب کردی.', 'trophy', 'student/achievements.php');
    return true;
}

/** ارزیابی دستاوردهای خودکار برای یک دانش‌آموز و اعطای موارد واجد شرایط */
function evaluate_achievements(int $studentId): int
{
    $u = get_user($studentId);
    if (!$u) return 0;
    $st = db()->prepare('SELECT COUNT(*) total, COALESCE(SUM(is_done),0) done FROM tasks WHERE student_id=?');
    $st->execute([$studentId]);
    $done = (int)$st->fetch()['done'];
    $streak = (int)$u['streak'];
    $earned = student_earned_ids($studentId);
    $count = 0;
    foreach (all_achievements(true) as $a) {
        if (isset($earned[(int)$a['id']])) continue;
        $ok = false;
        if ($a['condition_type'] === 'tasks_done') $ok = $done >= (int)$a['threshold'];
        elseif ($a['condition_type'] === 'streak')  $ok = $streak >= (int)$a['threshold'];
        // manual => اعطای دستی، خودکار نمی‌شود
        if ($ok && award_achievement($studentId, (int)$a['id'])) $count++;
    }
    return $count;
}

/** آمار: چند دانش‌آموز هر دستاورد را گرفته‌اند */
function achievement_award_counts(): array
{
    $rows = db()->query('SELECT achievement_id, COUNT(*) c FROM student_achievements GROUP BY achievement_id')->fetchAll();
    $out = [];
    foreach ($rows as $r) $out[(int)$r['achievement_id']] = (int)$r['c'];
    return $out;
}

/** دانش‌آموزانی که یک دستاورد خاص را گرفته‌اند */
function achievement_recipients(int $achId): array
{
    $st = db()->prepare('SELECT u.id,u.full_name,u.field,sa.earned_at,sa.awarded_by FROM student_achievements sa JOIN users u ON u.id=sa.student_id WHERE sa.achievement_id=? ORDER BY sa.earned_at DESC');
    $st->execute([$achId]);
    return $st->fetchAll();
}

/* ======================= exams ======================= */
function get_exam(int $id): ?array {
    $st = db()->prepare('SELECT * FROM exams WHERE id=? LIMIT 1');
    $st->execute([$id]);
    return $st->fetch() ?: null;
}
function exam_sections(int $examId): array {
    $st = db()->prepare('SELECT * FROM exam_sections WHERE exam_id=? ORDER BY sort_order, id');
    $st->execute([$examId]);
    return $st->fetchAll();
}
function exam_questions(int $examId): array {
    $st = db()->prepare('SELECT * FROM exam_questions WHERE exam_id=? ORDER BY section_id, sort_order, id');
    $st->execute([$examId]);
    return $st->fetchAll();
}
function section_question_count(int $sectionId): int {
    $st = db()->prepare('SELECT COUNT(*) FROM exam_questions WHERE section_id=?');
    $st->execute([$sectionId]);
    return (int)$st->fetchColumn();
}
function exam_question_count(int $examId): int {
    $st = db()->prepare('SELECT COUNT(*) FROM exam_questions WHERE exam_id=?');
    $st->execute([$examId]);
    return (int)$st->fetchColumn();
}

/** آزمون‌های قابل مشاهده برای یک دانش‌آموز (منتشرشده و در بازه) */
function student_exams(int $studentId): array {
    $sql = "SELECT e.*,
        (SELECT COUNT(*) FROM exam_questions q WHERE q.exam_id=e.id) AS q_count,
        a.id AS attempt_id, a.status AS attempt_status, a.total_score
        FROM exams e
        JOIN users su ON su.id=?
        LEFT JOIN exam_attempts a ON a.exam_id=e.id AND a.student_id=su.id
        WHERE e.status='published'
          AND (su.advisor_id IS NULL OR e.advisor_id=su.advisor_id OR e.advisor_id IN (SELECT id FROM users WHERE role='admin'))
        ORDER BY e.created_at DESC";
    $st = db()->prepare($sql);
    $st->execute([$studentId]);
    return $st->fetchAll();
}

/** ساخت/گرفتن attempt یک دانش‌آموز */
function get_or_create_attempt(int $examId, int $studentId): array {
    $st = db()->prepare('SELECT * FROM exam_attempts WHERE exam_id=? AND student_id=? LIMIT 1');
    $st->execute([$examId, $studentId]);
    $a = $st->fetch();
    if ($a) return $a;
    $exam = get_exam($examId);
    $deadline = null;
    if ($exam['timing_mode'] === 'total' && $exam['duration_min']) {
        $deadline = date('Y-m-d H:i:s', time() + (int)$exam['duration_min']*60);
    } elseif ($exam['timing_mode'] === 'per_section') {
        $secs = exam_sections($examId);
        $mins = 0;
        foreach ($secs as $sec) $mins += (int)($sec['duration_min'] ?? 0);
        if ($mins <= 0) $mins = (int)($exam['duration_min'] ?? 60);
        $deadline = date('Y-m-d H:i:s', time() + max(1, $mins)*60);
    }
    $ins = db()->prepare('INSERT INTO exam_attempts (exam_id,student_id,deadline_at) VALUES (?,?,?)');
    $ins->execute([$examId, $studentId, $deadline]);
    return get_or_create_attempt($examId, $studentId);
}

/** پاسخ‌های یک attempt به‌صورت [question_id => row] */
function attempt_answers(int $attemptId): array {
    $st = db()->prepare('SELECT * FROM exam_answers WHERE attempt_id=?');
    $st->execute([$attemptId]);
    $out = [];
    foreach ($st->fetchAll() as $r) $out[(int)$r['question_id']] = $r;
    return $out;
}

/** محاسبه و ثبت نمره (سبک کنکور) */
function grade_attempt(int $attemptId): array {
    $att = db()->prepare('SELECT * FROM exam_attempts WHERE id=?'); $att->execute([$attemptId]); $a = $att->fetch();
    if (!$a) return [];
    $exam = get_exam((int)$a['exam_id']);
    $questions = exam_questions((int)$a['exam_id']);
    $answers = attempt_answers($attemptId);
    $neg = (int)$exam['negative_marking'];

    $secStats = []; // section_id => [correct,wrong,blank,total]
    $correct=$wrong=$blank=0;
    foreach ($questions as $q) {
        $sid = (int)$q['section_id'];
        $secStats[$sid] ??= ['correct'=>0,'wrong'=>0,'blank'=>0,'total'=>0];
        $secStats[$sid]['total']++;
        $sel = $answers[(int)$q['id']]['selected_opt'] ?? null;
        if ($sel === null || $sel === '') { $blank++; $secStats[$sid]['blank']++; }
        elseif ((int)$sel === (int)$q['correct_opt']) { $correct++; $secStats[$sid]['correct']++; }
        else { $wrong++; $secStats[$sid]['wrong']++; }
    }
    // درصد کنکوری هر بخش: (3*درست - غلط) / (3*کل) * 100
    foreach ($secStats as $sid=>&$s) {
        $den = 3 * $s['total'];
        $num = $neg ? (3*$s['correct'] - $s['wrong']) : (3*$s['correct']);
        $s['percent'] = $den>0 ? round(max(-100, $num/$den*100), 2) : 0;
    }
    unset($s);
    $totalQ = count($questions);
    $denAll = 3*$totalQ;
    $numAll = $neg ? (3*$correct - $wrong) : (3*$correct);
    $totalPct = $denAll>0 ? round(max(-100,$numAll/$denAll*100),2) : 0;

    $up = db()->prepare('UPDATE exam_attempts SET status="submitted", submitted_at=NOW(), total_score=?, correct_count=?, wrong_count=?, blank_count=? WHERE id=?');
    $up->execute([$totalPct,$correct,$wrong,$blank,$attemptId]);

    return ['total'=>$totalPct,'correct'=>$correct,'wrong'=>$wrong,'blank'=>$blank,'sections'=>$secStats];
}

/** نتایج یک آزمون برای رتبه‌بندی (مشاور) */
function exam_results(int $examId): array {
    $st = db()->prepare('SELECT a.*, u.full_name, u.field FROM exam_attempts a JOIN users u ON u.id=a.student_id WHERE a.exam_id=? AND a.status="submitted" ORDER BY a.total_score DESC');
    $st->execute([$examId]);
    return $st->fetchAll();
}
function advisor_exams(int $advisorId): array {
    $st = db()->prepare("SELECT e.*,
        (SELECT COUNT(*) FROM exam_questions q WHERE q.exam_id=e.id) AS q_count,
        (SELECT COUNT(*) FROM exam_attempts a WHERE a.exam_id=e.id AND a.status='submitted') AS taken_count
        FROM exams e WHERE e.advisor_id=? ORDER BY e.created_at DESC");
    $st->execute([$advisorId]);
    return $st->fetchAll();
}

/** ساخت کارنامه‌ی کامل یک attempt (برای دانش‌آموز و مشاور) */
function attempt_report(int $attemptId): ?array {
    $st = db()->prepare('SELECT a.*, u.full_name FROM exam_attempts a JOIN users u ON u.id=a.student_id WHERE a.id=?');
    $st->execute([$attemptId]);
    $att = $st->fetch();
    if (!$att) return null;
    $exam = get_exam((int)$att['exam_id']);
    $sections = exam_sections((int)$att['exam_id']);
    $questions = exam_questions((int)$att['exam_id']);
    $answers = attempt_answers($attemptId);
    $neg = (int)$exam['negative_marking'];

    $secMap = []; foreach ($sections as $s) $secMap[(int)$s['id']] = $s['name'];
    $secStats = []; $byList = [];
    $gnum = 0;
    foreach ($sections as $sec) {
        foreach ($questions as $q) {
            if ((int)$q['section_id'] !== (int)$sec['id']) continue;
            $gnum++;
            $sid = (int)$sec['id'];
            $secStats[$sid] ??= ['name'=>$sec['name'],'correct'=>0,'wrong'=>0,'blank'=>0,'total'=>0];
            $secStats[$sid]['total']++;
            $sel = $answers[(int)$q['id']]['selected_opt'] ?? null;
            $sel = ($sel===null||$sel==='') ? null : (int)$sel;
            $st2 = $sel===null ? 'blank' : ((int)$sel===(int)$q['correct_opt'] ? 'correct' : 'wrong');
            $secStats[$sid][$st2]++;
            $byList[] = ['q'=>$q,'gnum'=>$gnum,'sec'=>$sec['name'],'selected'=>$sel,'state'=>$st2];
        }
    }
    foreach ($secStats as &$s) {
        $den = 3*$s['total'];
        $num = $neg ? (3*$s['correct'] - $s['wrong']) : (3*$s['correct']);
        $s['percent'] = $den>0 ? round(max(-100,$num/$den*100),1) : 0;
    }
    unset($s);
    return ['attempt'=>$att,'exam'=>$exam,'sections'=>$secStats,'questions'=>$byList];
}
