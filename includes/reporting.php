<?php
/** سیستم گزارش‌دهی پیشرفته دانش‌آموز */
declare(strict_types=1);
require_once __DIR__ . '/models.php';

function report_schema_ready(): bool
{
    static $ok = null;
    if ($ok !== null) return $ok;
    try {
        db()->exec("CREATE TABLE IF NOT EXISTS student_reports (
          id INT UNSIGNED NOT NULL AUTO_INCREMENT,
          student_id INT UNSIGNED NOT NULL,
          report_type ENUM('daily','weekly','monthly') NOT NULL DEFAULT 'daily',
          period_start DATE NOT NULL,
          period_end DATE NOT NULL,
          auto_snapshot_json LONGTEXT NULL,
          advanced_json LONGTEXT NULL,
          status ENUM('draft','submitted') NOT NULL DEFAULT 'draft',
          submitted_at DATETIME DEFAULT NULL,
          advisor_note TEXT NULL,
          reviewed_by INT UNSIGNED DEFAULT NULL,
          reviewed_at DATETIME DEFAULT NULL,
          created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
          updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          PRIMARY KEY (id),
          UNIQUE KEY uq_student_report (student_id, report_type, period_start),
          KEY idx_report_student (student_id, report_type, period_start),
          KEY idx_report_status (status, submitted_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        // برای نصب‌هایی که جدول را با نسخه قدیمی/ناقص ساخته‌اند، ستون‌های ضروری را ترمیم کن.
        $cols = [];
        foreach (db()->query('SHOW COLUMNS FROM student_reports')->fetchAll() as $c) $cols[$c['Field']] = true;
        $adds = [
            'auto_snapshot_json' => 'ALTER TABLE student_reports ADD COLUMN auto_snapshot_json LONGTEXT NULL AFTER period_end',
            'advanced_json' => 'ALTER TABLE student_reports ADD COLUMN advanced_json LONGTEXT NULL AFTER auto_snapshot_json',
            'status' => "ALTER TABLE student_reports ADD COLUMN status ENUM('draft','submitted') NOT NULL DEFAULT 'draft' AFTER advanced_json",
            'submitted_at' => 'ALTER TABLE student_reports ADD COLUMN submitted_at DATETIME NULL AFTER status',
            'advisor_note' => 'ALTER TABLE student_reports ADD COLUMN advisor_note TEXT NULL AFTER submitted_at',
            'reviewed_by' => 'ALTER TABLE student_reports ADD COLUMN reviewed_by INT UNSIGNED NULL AFTER advisor_note',
            'reviewed_at' => 'ALTER TABLE student_reports ADD COLUMN reviewed_at DATETIME NULL AFTER reviewed_by',
        ];
        foreach ($adds as $col=>$sql) if (empty($cols[$col])) db()->exec($sql);
        return $ok = true;
    } catch (Throwable $e) { return $ok = false; }
}

function report_period(string $type, string $date = 'now'): array
{
    $ts = $date === 'now' ? time() : strtotime($date);
    if ($ts === false) $ts = time();
    if ($type === 'weekly') {
        $s = week_saturday(date('Y-m-d', $ts));
        return [$s, date('Y-m-d', strtotime($s.' +6 day'))];
    }
    if ($type === 'monthly') {
        return [date('Y-m-01', $ts), date('Y-m-t', $ts)];
    }
    return [date('Y-m-d', $ts), date('Y-m-d', $ts)];
}
function report_type_label(string $type): string
{
    return ['daily'=>'روزانه','weekly'=>'هفتگی','monthly'=>'ماهانه'][$type] ?? $type;
}

function report_auto_snapshot(int $studentId, string $start, string $end): array
{
    task_status_schema_ready();
    auto_mark_missed_tasks($studentId);
    $st = db()->prepare("SELECT t.*, s.name subj_name, s.color subj_color, DATE_ADD(p.week_start, INTERVAL t.day_index DAY) task_date
        FROM tasks t JOIN plans p ON p.id=t.plan_id
        LEFT JOIN subjects s ON s.id=t.subject_id
        WHERE t.student_id=? AND p.status='published'
          AND DATE_ADD(p.week_start, INTERVAL t.day_index DAY) BETWEEN ? AND ?
        ORDER BY task_date, t.unit_index, t.sort_order, t.id");
    $st->execute([$studentId,$start,$end]);
    $rows = $st->fetchAll();
    $out = [
        'generated_at'=>date('Y-m-d H:i:s'), 'period_start'=>$start, 'period_end'=>$end,
        'total_tasks'=>0, 'full'=>0, 'partial'=>0, 'missed'=>0, 'pending'=>0,
        'score'=>0.0, 'progress_percent'=>0, 'tests_done'=>0, 'planned_minutes'=>0, 'effective_minutes'=>0,
        'study_hours'=>0, 'course_avg'=>null, 'target_tests'=>0, 'extra_tests'=>0, 'by_subject'=>[], 'by_day'=>[], 'by_type'=>[], 'red_tasks'=>[],
    ];
    $courses = [];
    foreach ($rows as $t) {
        $status = task_status($t);
        $score = task_score($t);
        $date = (string)$t['task_date'];
        $subj = trim((string)($t['subj_name'] ?: $t['title'] ?: 'بدون درس'));
        $dur = (int)($t['duration_min'] ?? 0);
        $doneCount = (int)($t['done_count'] ?? 0);
        $typeKey = (string)($t['task_type'] ?? 'custom');
        $isTestish = str_contains((string)($t['target_unit'] ?? ''), 'تست') || in_array($typeKey, ['test','exam','mock'], true);
        if (!isset($out['by_type'][$typeKey])) $out['by_type'][$typeKey] = ['tasks'=>0,'score'=>0,'tests'=>0,'target_tests'=>0,'minutes'=>0];
        $out['by_type'][$typeKey]['tasks']++;
        $out['by_type'][$typeKey]['score'] += $score;
        $out['by_type'][$typeKey]['minutes'] += (int)round($dur * min($score, 1));
        $out['total_tasks']++;
        if (isset($out[$status])) $out[$status]++;
        $out['score'] += $score;
        $out['planned_minutes'] += $dur;
        $out['effective_minutes'] += (int)round($dur * min($score, 1));
        if ($isTestish) {
            $out['tests_done'] += $doneCount;
            $targetCnt = (int)($t['target_count'] ?? 0);
            $out['target_tests'] += $targetCnt;
            $out['extra_tests'] += max(0, $doneCount - $targetCnt);
            $out['by_type'][$typeKey]['tests'] += $doneCount;
            $out['by_type'][$typeKey]['target_tests'] += $targetCnt;
        }
        if ($t['course_percent'] !== null) $courses[] = (int)$t['course_percent'];
        if (!isset($out['by_subject'][$subj])) $out['by_subject'][$subj] = ['tasks'=>0,'score'=>0,'tests'=>0,'minutes'=>0,'full'=>0,'partial'=>0,'missed'=>0];
        $out['by_subject'][$subj]['tasks']++;
        $out['by_subject'][$subj]['score'] += $score;
        $out['by_subject'][$subj]['minutes'] += (int)round($dur * min($score, 1));
        if ($isTestish) $out['by_subject'][$subj]['tests'] += $doneCount;
        if (isset($out['by_subject'][$subj][$status])) $out['by_subject'][$subj][$status]++;
        if (!isset($out['by_day'][$date])) $out['by_day'][$date] = ['tasks'=>0,'score'=>0,'tests'=>0,'minutes'=>0,'full'=>0,'partial'=>0,'missed'=>0];
        $out['by_day'][$date]['tasks']++;
        $out['by_day'][$date]['score'] += $score;
        $out['by_day'][$date]['minutes'] += (int)round($dur * min($score, 1));
        if ($isTestish) $out['by_day'][$date]['tests'] += $doneCount;
        if (isset($out['by_day'][$date][$status])) $out['by_day'][$date][$status]++;
        if ($status === 'missed') $out['red_tasks'][] = ['date'=>$date,'title'=>$t['title'],'subject'=>$t['subj_name'],'type'=>$t['task_type']];
    }
    $out['progress_percent'] = $out['total_tasks'] ? round($out['score']/$out['total_tasks']*100) : 0;
    $out['study_hours'] = round($out['effective_minutes']/60, 1);
    $out['course_avg'] = $courses ? round(array_sum($courses)/count($courses)) : null;
    return $out;
}

function report_get_or_create(int $studentId, string $type, string $date = 'now'): array
{
    report_schema_ready();
    [$start,$end] = report_period($type,$date);
    $snap = report_auto_snapshot($studentId,$start,$end);
    $st = db()->prepare('SELECT * FROM student_reports WHERE student_id=? AND report_type=? AND period_start=? LIMIT 1');
    $st->execute([$studentId,$type,$start]);
    $r = $st->fetch();
    $json = json_encode($snap, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    if (!$r) {
        db()->prepare('INSERT INTO student_reports (student_id,report_type,period_start,period_end,auto_snapshot_json) VALUES (?,?,?,?,?)')
            ->execute([$studentId,$type,$start,$end,$json]);
        $st->execute([$studentId,$type,$start]);
        $r = $st->fetch();
    } else {
        db()->prepare('UPDATE student_reports SET period_end=?, auto_snapshot_json=? WHERE id=?')->execute([$end,$json,$r['id']]);
        $r['auto_snapshot_json'] = $json; $r['period_end']=$end;
    }
    $r['snapshot'] = $snap;
    $r['advanced'] = $r['advanced_json'] ? (json_decode($r['advanced_json'], true) ?: []) : [];
    return $r;
}

function report_submit(int $studentId, string $type, string $date, array $advanced): array
{
    $r = report_get_or_create($studentId,$type,$date);
    $clean = report_clean_advanced($type,$advanced);
    db()->prepare('UPDATE student_reports SET advanced_json=?, status="submitted", submitted_at=COALESCE(submitted_at, NOW()) WHERE id=?')
        ->execute([json_encode($clean, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES), $r['id']]);
    return report_get_or_create($studentId,$type,$date);
}

function report_clean_advanced(string $type, array $a): array
{
    $num = fn($k,$min,$max,$def=null) => isset($a[$k]) && $a[$k] !== '' ? max($min, min($max, (float)$a[$k])) : $def;
    $txt = fn($k,$max=800) => mb_substr(trim((string)($a[$k] ?? '')),0,$max);
    $base = [
        'sleep_hours'=>$num('sleep_hours',0,16), 'sleep_quality'=>$num('sleep_quality',1,5),
        'focus_score'=>$num('focus_score',1,10), 'energy_score'=>$num('energy_score',1,10), 'stress_score'=>$num('stress_score',1,10),
        'phone_minutes'=>$num('phone_minutes',0,1440,0), 'wasted_minutes'=>$num('wasted_minutes',0,1440,0),
        'mood'=>$txt('mood',60), 'best_win'=>$txt('best_win'), 'main_challenge'=>$txt('main_challenge'),
        'challenge_reason'=>$txt('challenge_reason'), 'solution'=>$txt('solution'), 'advisor_question'=>$txt('advisor_question'),
        'next_priority'=>$txt('next_priority'), 'self_score'=>$num('self_score',1,20),
        'main_reason'=>$txt('main_reason',120), 'week_rating'=>$txt('week_rating',60), 'plan_fit'=>$txt('plan_fit',60),
        'advisor_followup'=>$txt('advisor_followup',60), 'month_satisfaction'=>$num('month_satisfaction',1,10),
        'monthly_mindset'=>$txt('monthly_mindset',80), 'next_month_goal_type'=>$txt('next_month_goal_type',120),
    ];
    if ($type !== 'daily') {
        $base += [
            'best_subject'=>$txt('best_subject',120), 'weak_subject'=>$txt('weak_subject',120),
            'exam_count'=>$num('exam_count',0,30,0), 'exam_analysis_quality'=>$num('exam_analysis_quality',1,5),
            'catchup_hours'=>$num('catchup_hours',0,80,0), 'next_period_goal'=>$txt('next_period_goal'),
        ];
    }
    return $base;
}


function report_period_has_tasks(int $studentId, string $start, string $end): bool
{
    $st = db()->prepare("SELECT COUNT(*) FROM tasks t JOIN plans p ON p.id=t.plan_id WHERE t.student_id=? AND p.status='published' AND DATE_ADD(p.week_start, INTERVAL t.day_index DAY) BETWEEN ? AND ?");
    $st->execute([$studentId,$start,$end]);
    return (int)$st->fetchColumn() > 0;
}
function report_period_is_closed(int $studentId, string $start, string $end): bool
{
    // بسته یعنی همه تسک‌های بازه وضعیت گرفته‌اند: کامل، ناقص یا قرمز. pending یعنی هنوز زود است.
    $st = db()->prepare("SELECT COUNT(*) FROM tasks t JOIN plans p ON p.id=t.plan_id WHERE t.student_id=? AND p.status='published' AND DATE_ADD(p.week_start, INTERVAL t.day_index DAY) BETWEEN ? AND ? AND COALESCE(t.completion_status,'pending')='pending'");
    $st->execute([$studentId,$start,$end]);
    return (int)$st->fetchColumn() === 0;
}
function report_is_submitted(int $studentId, string $type, string $start): bool
{
    report_schema_ready();
    $st = db()->prepare('SELECT status FROM student_reports WHERE student_id=? AND report_type=? AND period_start=? LIMIT 1');
    $st->execute([$studentId,$type,$start]);
    return (($st->fetchColumn() ?: 'draft') === 'submitted');
}

function report_due_daily_after_tasks(int $studentId, ?string $date = null): bool
{
    $date = $date ?: date('Y-m-d');
    [$start,$end] = report_period('daily',$date);
    if (!report_period_has_tasks($studentId,$start,$end)) return false;
    if (!report_period_is_closed($studentId,$start,$end)) return false;
    return !report_is_submitted($studentId,'daily',$start);
}

function report_pending_items(int $studentId): array
{
    $items = [];
    // روزانه: فقط وقتی روز تمام شده باشد (دیروز و چند روز اخیر) یا همه تسک‌های امروز بسته شده باشند.
    for ($i=0; $i<=3; $i++) {
        $date = date('Y-m-d', strtotime("-$i day"));
        [$s,$e] = report_period('daily',$date);
        if (!report_period_has_tasks($studentId,$s,$e) || report_is_submitted($studentId,'daily',$s)) continue;
        $isPast = $s < date('Y-m-d');
        if ($isPast || report_period_is_closed($studentId,$s,$e)) {
            report_get_or_create($studentId,'daily',$s);
            $items[] = ['type'=>'daily','label'=>'روزانه','start'=>$s,'end'=>$e,'url'=>url('student/reports.php?type=daily&date='.$s)];
        }
    }
    // هفتگی: هفته قبل به بعد، یا اگر هفته جاری همه تسک‌هایش بسته شد.
    foreach ([date('Y-m-d'), date('Y-m-d', strtotime('-7 day')), date('Y-m-d', strtotime('-14 day'))] as $date) {
        [$s,$e] = report_period('weekly',$date);
        if (!report_period_has_tasks($studentId,$s,$e) || report_is_submitted($studentId,'weekly',$s)) continue;
        $isEnded = $e < date('Y-m-d');
        if ($isEnded || report_period_is_closed($studentId,$s,$e)) {
            report_get_or_create($studentId,'weekly',$s);
            $items[] = ['type'=>'weekly','label'=>'هفتگی','start'=>$s,'end'=>$e,'url'=>url('student/reports.php?type=weekly&date='.$s)];
        }
    }
    // ماهانه: ماه قبل به بعد، یا اگر ماه جاری کاملاً بسته شد.
    foreach ([date('Y-m-d'), date('Y-m-d', strtotime('first day of last month')), date('Y-m-d', strtotime('first day of -2 month'))] as $date) {
        [$s,$e] = report_period('monthly',$date);
        if (!report_period_has_tasks($studentId,$s,$e) || report_is_submitted($studentId,'monthly',$s)) continue;
        $isEnded = $e < date('Y-m-d');
        if ($isEnded || report_period_is_closed($studentId,$s,$e)) {
            report_get_or_create($studentId,'monthly',$s);
            $items[] = ['type'=>'monthly','label'=>'ماهانه','start'=>$s,'end'=>$e,'url'=>url('student/reports.php?type=monthly&date='.$s)];
        }
    }
    // حذف تکراری‌ها
    $seen = []; $out = [];
    foreach ($items as $it) { $k=$it['type'].'-'.$it['start']; if(isset($seen[$k])) continue; $seen[$k]=1; $out[]=$it; }
    return $out;
}

function reports_for_student(int $studentId, string $type='daily', int $limit=30): array
{
    report_schema_ready();
    $st = db()->prepare('SELECT * FROM student_reports WHERE student_id=? AND report_type=? ORDER BY period_start DESC LIMIT ?');
    $st->bindValue(1,$studentId,PDO::PARAM_INT); $st->bindValue(2,$type); $st->bindValue(3,$limit,PDO::PARAM_INT);
    $st->execute();
    $rows = $st->fetchAll();
    foreach ($rows as &$r) {
        $r['snapshot'] = $r['auto_snapshot_json'] ? (json_decode($r['auto_snapshot_json'], true) ?: []) : [];
        $r['advanced'] = $r['advanced_json'] ? (json_decode($r['advanced_json'], true) ?: []) : [];
    }
    return $rows;
}

function report_clamp(float $v, float $min=0, float $max=100): float { return max($min, min($max, $v)); }
function report_score_label(float $score): string
{
    if ($score >= 85) return 'عالی';
    if ($score >= 70) return 'خوب';
    if ($score >= 50) return 'متوسط';
    return 'نیازمند پیگیری';
}
function report_risk_label(float $risk): string
{
    if ($risk >= 70) return 'زیاد';
    if ($risk >= 45) return 'متوسط';
    return 'کم';
}
function report_avg(array $vals, ?float $default=null): ?float
{
    $vals = array_values(array_filter($vals, fn($v)=>$v !== null && $v !== '' && is_numeric($v)));
    return $vals ? array_sum(array_map('floatval',$vals))/count($vals) : $default;
}
function report_previous_period(string $type, string $start): array
{
    if ($type === 'monthly') return report_period('monthly', date('Y-m-d', strtotime($start.' -1 month')));
    if ($type === 'weekly') return report_period('weekly', date('Y-m-d', strtotime($start.' -7 day')));
    return report_period('daily', date('Y-m-d', strtotime($start.' -1 day')));
}
function report_advanced_aggregate(int $studentId, string $type, string $start, string $end, array $currentAdvanced=[]): array
{
    $rows = [];
    if ($type === 'daily') {
        $rows[] = $currentAdvanced;
    } else {
        report_schema_ready();
        $st = db()->prepare("SELECT advanced_json FROM student_reports WHERE student_id=? AND report_type='daily' AND period_start BETWEEN ? AND ? AND status='submitted'");
        $st->execute([$studentId,$start,$end]);
        foreach ($st->fetchAll() as $r) {
            $a = $r['advanced_json'] ? (json_decode($r['advanced_json'], true) ?: []) : [];
            if ($a) $rows[] = $a;
        }
        if ($currentAdvanced) $rows[] = $currentAdvanced;
    }
    $get = fn($key)=>array_map(fn($r)=>$r[$key] ?? null, $rows);
    return [
        'sleep_hours'=>report_avg($get('sleep_hours')),
        'sleep_quality'=>report_avg($get('sleep_quality')),
        'focus_score'=>report_avg($get('focus_score')),
        'energy_score'=>report_avg($get('energy_score')),
        'stress_score'=>report_avg($get('stress_score')),
        'phone_minutes'=>report_avg($get('phone_minutes'),0),
        'wasted_minutes'=>report_avg($get('wasted_minutes'),0),
        'self_score'=>report_avg($get('self_score')),
        'count'=>count($rows),
    ];
}
function report_trend_label(float $cur, float $prev): string
{
    if ($cur <= 0 && $prev <= 0) return 'بدون اجرای مؤثر در دو بازه';
    if ($cur <= 0 && $prev > 0) return 'افت کامل نسبت به بازه قبل';
    if ($cur > 0 && $prev <= 0) return 'شروع دوباره نسبت به بازه قبل';
    $d = $cur - $prev;
    if ($d >= 8) return 'رو به رشد';
    if ($d <= -8) return 'رو به افت';
    return 'تقریباً پایدار';
}
function report_build_analysis(int $studentId, string $type, string $start, string $end, array $snapshot, array $advanced=[]): array
{
    $prev = report_previous_period($type, $start);
    $prevSnap = report_auto_snapshot($studentId, $prev[0], $prev[1]);
    $agg = report_advanced_aggregate($studentId,$type,$start,$end,$advanced);

    $totalReal = (int)($snapshot['total_tasks'] ?? 0);
    $total = max(1, $totalReal);
    $progress = (float)($snapshot['progress_percent'] ?? 0);
    $prevProgress = (float)($prevSnap['progress_percent'] ?? 0);
    $full = (int)($snapshot['full'] ?? 0);
    $partial = (int)($snapshot['partial'] ?? 0);
    $missed = (int)($snapshot['missed'] ?? 0);
    $pending = (int)($snapshot['pending'] ?? max(0, $totalReal-$full-$partial-$missed));
    $missedRate = ($missed / $total) * 100;
    $partialRate = ($partial / $total) * 100;
    $pendingRate = ($pending / $total) * 100;
    $targetTests = (int)($snapshot['target_tests'] ?? 0);
    $testsDone = (int)($snapshot['tests_done'] ?? 0);
    $testRatio = $targetTests > 0 ? min(140, $testsDone / max(1,$targetTests) * 100) : ($testsDone > 0 ? 70 : 0);

    // اگر اصلاً داده اجرایی نداریم، تحلیل باید صادقانه بگوید داده کافی نیست؛ امتیاز مصنوعی ندهد.
    if ($totalReal === 0) {
        return [
            'beta'=>true, 'overall'=>0, 'overall_label'=>'بدون داده کافی', 'trend'=>'بدون داده کافی', 'prev_progress'=>round($prevProgress),
            'scores'=>['execution'=>0,'consistency'=>0,'tests'=>0,'study_quality'=>0,'recovery'=>0,'subject_balance'=>0,'distraction_control'=>0,'exam_analysis'=>0,'burnout_risk'=>0],
            'weak_subjects'=>[], 'strong_subjects'=>[],
            'alerts'=>[['level'=>'warn','title'=>'داده کافی وجود ندارد','text'=>'برای این بازه هنوز تسک یا اجرای قابل تحلیل ثبت نشده است.']],
            'recommendations'=>['ابتدا برنامه یا تسک‌های این بازه را ثبت و اجرا کنید تا تحلیل قابل اعتماد ساخته شود.'],
            'summary'=>'برای این بازه هنوز داده کافی برای تحلیل وجود ندارد.',
        ];
    }

    $dayPcts = [];
    foreach (($snapshot['by_day'] ?? []) as $d=>$r) $dayPcts[] = !empty($r['tasks']) ? (($r['score'] ?? 0)/max(1,$r['tasks'])*100) : 0;
    if (!$dayPcts) $dayPcts = [$progress];
    $activeDays = count(array_filter($dayPcts, fn($p)=>$p >= 35));
    $plannedDays = max(1, count($dayPcts));
    $avgDay = array_sum($dayPcts)/count($dayPcts);
    $variance = array_sum(array_map(fn($p)=>($p-$avgDay)**2, $dayPcts))/count($dayPcts);
    $std = sqrt($variance);
    if ($progress <= 0) $consistency = 0;
    elseif ($type === 'daily') $consistency = $progress;
    else $consistency = report_clamp(($activeDays/$plannedDays)*55 + ($avgDay*.30) + max(0, 100-$std*2)*.15);

    $sleep = $agg['sleep_hours']; $sleepQ = $agg['sleep_quality']; $energy = $agg['energy_score']; $focus = $agg['focus_score']; $stress = $agg['stress_score'];
    $hasRecoveryData = ($sleep !== null || $sleepQ !== null || $energy !== null || $stress !== null);
    $recovery = $hasRecoveryData ? 55 : 0;
    if ($sleep !== null) $recovery += ($sleep >= 7 ? 18 : ($sleep >= 6 ? 6 : -18));
    if ($sleepQ !== null) $recovery += (($sleepQ-3)*5);
    if ($energy !== null) $recovery += (($energy-6)*4);
    if ($stress !== null) $recovery -= max(0, $stress-5)*6;
    $recovery = report_clamp($recovery);

    $hasDistractionData = (($agg['wasted_minutes'] ?? null) !== null || ($agg['phone_minutes'] ?? null) !== null);
    $waste = (float)($agg['wasted_minutes'] ?? 0) + (float)($agg['phone_minutes'] ?? 0) * .55;
    $distractionScore = $hasDistractionData ? report_clamp(100 - min(100, $waste / 2.0)) : 0;

    $subjects = $snapshot['by_subject'] ?? [];
    $weakSubjects = [];
    $strongSubjects = [];
    foreach ($subjects as $name=>$r) {
        $p = !empty($r['tasks']) ? (($r['score'] ?? 0)/max(1,$r['tasks'])*100) : 0;
        if ($p < 60 || !empty($r['missed'])) $weakSubjects[] = ['name'=>$name,'score'=>round($p),'missed'=>(int)($r['missed']??0)];
        if ($p >= 85 && ($r['tasks']??0) >= 1) $strongSubjects[] = ['name'=>$name,'score'=>round($p)];
    }
    usort($weakSubjects, fn($a,$b)=>($b['missed']<=>$a['missed']) ?: ($a['score']<=>$b['score']));
    usort($strongSubjects, fn($a,$b)=>$b['score']<=>$a['score']);
    $balance = ($progress <= 0 || empty($subjects)) ? 0 : report_clamp(100 - min(65, count($weakSubjects)*14) - ($missedRate*.45));

    $byType = $snapshot['by_type'] ?? [];
    $examLike = (int)(($byType['exam']['tasks'] ?? 0) + ($byType['mock']['tasks'] ?? 0));
    $analysisTasks = (int)($byType['analysis']['tasks'] ?? 0);
    $analysisQuality = $advanced['exam_analysis_quality'] ?? null;
    $examScore = ($examLike || $analysisTasks || $analysisQuality) ? report_clamp(($examLike ? 40 : 10) + min(35,$analysisTasks*18) + ($analysisQuality ? (((float)$analysisQuality-3)*10+25) : 0)) : 0;

    $studyQuality = ($progress <= 0) ? 0 : report_clamp(($progress*.50)+(($focus??5)*5)+(($snapshot['course_avg']??60)*.20));

    // ریسک افت عملکرد/فرسودگی: صفر بودن اجرا خودش هشدار جدی است، حتی اگر داده خواب خوب باشد.
    $burnout = 15;
    if ($progress <= 0 && $pendingRate >= 80) $burnout += 45;
    if ($progress <= 0 && $missedRate >= 50) $burnout += 65;
    if ($sleep !== null && $sleep < 6) $burnout += 20;
    if ($stress !== null && $stress >= 7) $burnout += 22;
    if ($energy !== null && $energy <= 4) $burnout += 18;
    if ($missedRate >= 25) $burnout += 20;
    if ($progress < $prevProgress - 10) $burnout += 14;
    if ($partialRate >= 35) $burnout += 8;
    $burnout = report_clamp($burnout);

    $scores = [
        'execution'=>round(report_clamp($progress)),
        'consistency'=>round($consistency),
        'tests'=>round(report_clamp($testRatio)),
        'study_quality'=>round($studyQuality),
        'recovery'=>round($recovery),
        'subject_balance'=>round($balance),
        'distraction_control'=>round($distractionScore),
        'exam_analysis'=>round($examScore),
        'burnout_risk'=>round($burnout),
    ];

    // امتیاز کل نباید با خواب/تعادل مصنوعی بالا برود وقتی اجرا صفر است.
    $overall = round(($scores['execution']*.42)+($scores['consistency']*.20)+($scores['tests']*.14)+($scores['study_quality']*.12)+($scores['subject_balance']*.06)+($scores['recovery']*.04)+($scores['distraction_control']*.02));
    if ($progress <= 0) $overall = min($overall, $missedRate >= 50 ? 8 : 12);
    elseif ($progress < 30) $overall = min($overall, 35);

    $alerts = [];
    if ($progress <= 0 && $pendingRate >= 80) $alerts[] = ['level'=>'danger','title'=>'هیچ اجرای مؤثری ثبت نشده','text'=>'برای این بازه تسک وجود دارد، اما هنوز اجرای کامل یا ناقص قابل قبول ثبت نشده است.'];
    if ($progress <= 0 && $missedRate >= 50) $alerts[] = ['level'=>'danger','title'=>'برنامه عملاً اجرا نشده','text'=>'بیشتر تسک‌های این بازه قرمز یا بدون امتیاز هستند و نیاز به اقدام فوری دارد.'];
    if ($burnout >= 70) $alerts[] = ['level'=>'danger','title'=>'ریسک افت جدی','text'=>'ترکیب اجرای پایین، تسک قرمز، فشار یا افت نسبت به قبل نیاز به پیگیری سریع دارد.'];
    elseif ($burnout >= 45) $alerts[] = ['level'=>'warn','title'=>'ریسک افت متوسط','text'=>'بهتر است فشار برنامه، خواب و علت اجرا نشدن بررسی شود.'];
    if ($missedRate >= 25) $alerts[] = ['level'=>'danger','title'=>'تسک قرمز زیاد','text'=>'بخش قابل توجهی از برنامه اجرا نشده و باید اولویت‌بندی مجدد شود.'];
    if ($targetTests > 0 && $testsDone < $targetTests*.7) $alerts[] = ['level'=>'warn','title'=>'تست کمتر از هدف','text'=>'تعداد تست‌ها نسبت به هدف برنامه پایین‌تر است.'];
    if ($hasDistractionData && $waste >= 120) $alerts[] = ['level'=>'warn','title'=>'اتلاف وقت قابل توجه','text'=>'زمان موبایل یا حاشیه روی بازده مطالعه اثر گذاشته است.'];
    if ($progress < $prevProgress - 10) $alerts[] = ['level'=>'warn','title'=>'افت نسبت به بازه قبل','text'=>'عملکرد این بازه نسبت به بازه قبل کاهش محسوسی داشته است.'];
    if (!$hasRecoveryData) $alerts[] = ['level'=>'warn','title'=>'داده خواب و انرژی ثبت نشده','text'=>'برای تحلیل دقیق‌تر، خواب، انرژی، تمرکز و استرس این بازه باید ثبت شود.'];

    $longTerm = ['label'=>null,'avg'=>null,'months'=>0];
    if ($type === 'monthly') {
        try {
            $stLt = db()->prepare("SELECT auto_snapshot_json FROM student_reports WHERE student_id=? AND report_type='monthly' AND period_start < ? ORDER BY period_start DESC LIMIT 3");
            $stLt->execute([$studentId,$start]);
            $vals = [];
            foreach ($stLt->fetchAll() as $rr) {
                $ss = $rr['auto_snapshot_json'] ? (json_decode($rr['auto_snapshot_json'], true) ?: []) : [];
                if (isset($ss['progress_percent'])) $vals[] = (float)$ss['progress_percent'];
            }
            if ($vals) {
                $avgLt = array_sum($vals)/count($vals);
                $longTerm = ['label'=>report_trend_label($progress,$avgLt),'avg'=>round($avgLt),'months'=>count($vals)];
                if ($progress < $avgLt - 12) $alerts[] = ['level'=>'danger','title'=>'افت ماهانه نسبت به روند قبلی','text'=>'عملکرد این ماه نسبت به میانگین ماه‌های اخیر کاهش محسوسی دارد.'];
                elseif ($progress > $avgLt + 12) $alerts[] = ['level'=>'warn','title'=>'رشد خوب ماهانه','text'=>'این ماه نسبت به روند ماه‌های اخیر رشد قابل توجهی دیده می‌شود؛ علت‌های موفقیت را حفظ کنید.'];
            }
        } catch (Throwable $e) { /* تحلیل بلندمدت اختیاری است */ }
    }

    $recommendations = [];
    if ($progress <= 0) {
        if ($pendingRate >= 80) $recommendations[] = 'اولین اقدام: وضعیت واقعی تسک‌های این بازه را ثبت کن؛ اگر انجام نشده‌اند، علت اصلی را مشخص کن.';
        if ($missedRate >= 50) $recommendations[] = 'برای تسک‌های قرمز، یک برنامه جبرانی کوتاه با حداکثر ۲ تا ۳ اولویت اصلی بچینید.';
        $recommendations[] = 'حجم برنامه بعدی را موقتاً سبک‌تر و قابل اجرا کنید تا دوباره جریان اجرا شروع شود.';
        $recommendations[] = 'یک هدف کوچک فوری تعریف شود: فقط یک واحد کامل + یک بسته تست کوتاه.';
    } else {
        if ($weakSubjects) $recommendations[] = 'برای '. $weakSubjects[0]['name'] .' یک برنامه جبرانی کوتاه و پیوسته بچینید.';
        if ($targetTests > 0 && $testsDone < $targetTests) $recommendations[] = 'تعداد تست را مرحله‌ای بالا ببرید و تست‌های زمان‌دار را جداگانه ثبت کنید.';
        if ($analysisTasks < $examLike) $recommendations[] = 'بعد از آزمون/آزمونک، یک تسک تحلیل آزمون اجباری اضافه شود.';
        if ($burnout >= 55) $recommendations[] = 'حجم برنامه بعدی کمی متعادل‌تر شود و خواب/استراحت جدی‌تر پیگیری شود.';
        if ($hasDistractionData && $distractionScore < 55) $recommendations[] = 'برای موبایل و حاشیه، بازه‌های بدون گوشی تعریف شود.';
    }
    if ($type === 'monthly' && !empty($longTerm['avg'])) {
        if ($progress < $longTerm['avg'] - 12) $recommendations[] = 'برای ماه بعد، هدف را روی بازگشت به میانگین قبلی بگذارید نه جهش سنگین؛ برنامه باید قابل اجرا و پایدار باشد.';
        elseif ($progress > $longTerm['avg'] + 12) $recommendations[] = 'عوامل رشد این ماه را مشخص کنید و همان الگو را در ماه بعد تکرار کنید.';
        $recommendations[] = 'در پایان هر هفته ماه بعد، یک اصلاح کوچک برنامه انجام شود تا افت ماهانه دیر تشخیص داده نشود.';
    }
    if (!$recommendations) $recommendations[] = 'مسیر کلی قابل قبول است؛ برای رشد بیشتر روی کیفیت تحلیل و استمرار تمرکز شود.';

    $summaryBits = [];
    if ($progress <= 0) {
        $summaryBits[] = 'در این بازه اجرای مؤثر ثبت نشده و وضعیت نیازمند اقدام فوری است.';
        if ($pendingRate >= 80) $summaryBits[] = 'بخش اصلی تسک‌ها هنوز تعیین وضعیت نشده‌اند.';
        if ($missedRate >= 50) $summaryBits[] = 'تعداد تسک‌های قرمز بالاست.';
    } else {
        $summaryBits[] = 'اجرای برنامه '.report_score_label($scores['execution']).' بوده است.';
        $summaryBits[] = 'روند نسبت به بازه قبل '.report_trend_label($progress,$prevProgress).' است.';
    }
    if ($type === 'monthly' && !empty($longTerm['avg'])) $summaryBits[] = 'نسبت به میانگین '.fa_num($longTerm['months']).' ماه اخیر: '.$longTerm['label'].'.';
    if ($weakSubjects) $summaryBits[] = 'نیازمند توجه اصلی: '.$weakSubjects[0]['name'].'.';
    if ($strongSubjects && $progress > 0) $summaryBits[] = 'نقطه قوت: '.$strongSubjects[0]['name'].'.';
    $summaryBits[] = 'ریسک افت '.report_risk_label($burnout).' ارزیابی می‌شود.';

    return [
        'beta'=>true,
        'overall'=>$overall,
        'overall_label'=>report_score_label($overall),
        'trend'=>report_trend_label($progress,$prevProgress),
        'prev_progress'=>round($prevProgress),
        'scores'=>$scores,
        'weak_subjects'=>array_slice($weakSubjects,0,4),
        'strong_subjects'=>array_slice($strongSubjects,0,3),
        'alerts'=>$alerts,
        'recommendations'=>array_slice(array_values(array_unique($recommendations)),0,6),
        'summary'=>implode(' ', $summaryBits),
    ];
}
