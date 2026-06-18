<?php
/**
 * API تسک‌ها — CRUD + toggle
 * actions: list, create, update, delete, move, seed_special, toggle, feedback, publish, copy_week, clear_day
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/models.php';
boot_session();
require_login();
require_csrf();

/** نوع‌هایی که در ENUM پایه‌ی قدیمی نبوده‌اند و نیاز به مهاجرت دارند */
const EXTENDED_TASK_TYPES = ['textbook','descriptive','analysis','special','mock'];

$u = current_user();
$me = (int)$u['id'];
$role = $u['role'];
$json = str_contains($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') ? body_json() : [];
$action = (string)(input('action') ?: ($json['action'] ?? ''));
$in = array_merge($_POST, $json);

/* ---- مجوز: مشاور صاحب برنامه؟ یا دانش‌آموز صاحب تسک؟ ---- */
function plan_owned_by_advisor(int $planId, int $advisorId, string $role): bool {
    $st = db()->prepare('SELECT advisor_id FROM plans WHERE id=?');
    $st->execute([$planId]);
    $p = $st->fetch();
    if (!$p) return false;
    return $role === 'admin' || (int)$p['advisor_id'] === $advisorId;
}
function task_row(int $id): ?array {
    $st = db()->prepare('SELECT t.*, p.advisor_id, p.status plan_status FROM tasks t JOIN plans p ON p.id=t.plan_id WHERE t.id=?');
    $st->execute([$id]);
    return $st->fetch() ?: null;
}
function default_task_title(string $type, ?int $subjectId = null): string {
    if ($type === 'reading') return 'روزخوانی';
    if ($type === 'exam') return 'آزمونک';
    if ($subjectId) {
        $st = db()->prepare('SELECT name FROM subjects WHERE id=?');
        $st->execute([$subjectId]);
        $name = trim((string)$st->fetchColumn());
        if ($name !== '') {
            return match ($type) {
                'test' => $name . ' تست',
                'study' => $name . ' مطالعه',
                'review' => $name . ' مرور',
                'textbook' => $name . ' کتاب درسی',
                'descriptive' => $name . ' سوال تشریحی',
                'analysis' => $name . ' تحلیل آزمون',
                'mock' => $name . ' آزمون',
                'special' => $name,
                default => $name,
            };
        }
    }
    return TASK_TYPES[$type]['label'] ?? 'تسک';
}
function seed_special_tasks(int $planId, int $readingMin = 60, int $examMin = 50): int {
    $st = db()->prepare('SELECT * FROM plans WHERE id=? LIMIT 1');
    $st->execute([$planId]);
    $plan = $st->fetch();
    if (!$plan) return 0;
    $readingMin = max(0, min(600, $readingMin));
    $examMin    = max(0, min(600, $examMin));
    $ins = db()->prepare('INSERT INTO tasks (plan_id,student_id,title,task_type,day_index,unit_index,target_count,target_unit,duration_min,priority,sort_order) VALUES (?,?,?,?,?,?,?,?,?,?,?)');
    $added = 0;
    for ($day=0; $day<7; $day++) {
        $chk = db()->prepare('SELECT title, task_type FROM tasks WHERE plan_id=? AND day_index=? AND unit_index=8');
        $chk->execute([$planId,$day]);
        $hasReading = false; $hasExam = false;
        foreach ($chk->fetchAll() as $r) {
            if ($r['task_type']==='reading' || trim((string)$r['title'])==='روزخوانی') $hasReading = true;
            if ($r['task_type']==='exam' || trim((string)$r['title'])==='آزمونک') $hasExam = true;
        }
        $sortq = db()->prepare('SELECT COALESCE(MAX(sort_order),0)+1 FROM tasks WHERE plan_id=? AND day_index=? AND unit_index=8');
        $sortq->execute([$planId,$day]);
        $sort = (int)$sortq->fetchColumn();
        if (!$hasReading) { $ins->execute([$planId,$plan['student_id'],'روزخوانی','reading',$day,8,1,'ساعت',$readingMin,'normal',$sort++]); $added++; }
        if (!$hasExam)    { $ins->execute([$planId,$plan['student_id'],'آزمونک','exam',$day,8,50,'دقیقه',$examMin,'normal',$sort++]); $added++; }
    }
    return $added;
}

function ensure_extended_task_types(): bool {
    static $checked = null;
    if ($checked !== null) return $checked;
    try {
        $st = db()->query("SHOW COLUMNS FROM tasks LIKE 'task_type'");
        $col = $st->fetch();
        $type = (string)($col['Type'] ?? '');
        $hasAll = str_contains($type, "'textbook'") && str_contains($type, "'descriptive'")
               && str_contains($type, "'analysis'") && str_contains($type, "'special'") && str_contains($type, "'mock'");
        if (!$hasAll) {
            db()->exec("ALTER TABLE tasks MODIFY task_type ENUM('test','study','review','textbook','descriptive','exam','reading','custom','analysis','special','mock') NOT NULL DEFAULT 'study'");
        }
        return $checked = true;
    } catch (Throwable $e) {
        return $checked = false;
    }
}

/** اطمینان از وجود ستون «منبع» (source) */
function ensure_source_column(): bool {
    static $ok = null;
    if ($ok !== null) return $ok;
    try {
        $st = db()->query("SHOW COLUMNS FROM tasks LIKE 'source'");
        if ($st->fetch()) return $ok = true;
        db()->exec("ALTER TABLE tasks ADD COLUMN source VARCHAR(120) DEFAULT NULL AFTER description");
        return $ok = true;
    } catch (Throwable $e) {
        return $ok = false;
    }
}

try {
switch ($action) {

/* ============ ساخت تسک (مشاور) ============ */
case 'create': {
    if (!in_array($role, ['advisor','admin'], true)) json_out(['ok'=>false,'error'=>'دسترسی ندارید'],403);
    $planId  = (int)($in['plan_id'] ?? 0);
    if (!plan_owned_by_advisor($planId, $me, $role)) json_out(['ok'=>false,'error'=>'برنامه نامعتبر'],403);
    $st = db()->prepare('SELECT student_id FROM plans WHERE id=?'); $st->execute([$planId]);
    $studentId = (int)$st->fetchColumn();

    $day  = clamp_int($in['day_index'] ?? 0, 0, 6);
    $unit = clamp_int($in['unit_index'] ?? 1, 1, 8);
    $type = in_array($in['task_type'] ?? '', array_keys(TASK_TYPES), true) ? $in['task_type'] : 'study';
    if (in_array($type, EXTENDED_TASK_TYPES, true) && !ensure_extended_task_types()) json_out(['ok'=>false,'error'=>'برای نوع‌های جدید، فایل sql/upgrade_task_types2.sql را یک‌بار اجرا کنید (یا install.php را باز کنید).'],500);
    $hasSource = ensure_source_column();
    $target = isset($in['target_count']) && $in['target_count']!=='' ? max(0,(int)$in['target_count']) : null;
    $tunit  = trim((string)($in['target_unit'] ?? 'تست')) ?: 'تست';
    $dur    = isset($in['duration_min']) && $in['duration_min']!=='' ? max(0,(int)$in['duration_min']) : null;
    $subj   = isset($in['subject_id']) && $in['subject_id'] ? (int)$in['subject_id'] : null;
    $title = trim((string)($in['title'] ?? ''));
    if ($title === '') $title = default_task_title($type, $subj);
    $desc   = trim((string)($in['description'] ?? '')) ?: null;
    $source = trim((string)($in['source'] ?? '')) ?: null;
    $prio   = in_array($in['priority'] ?? '', ['low','normal','high'], true) ? $in['priority'] : 'normal';

    $sortq = db()->prepare('SELECT COALESCE(MAX(sort_order),0)+1 FROM tasks WHERE plan_id=? AND day_index=? AND unit_index=?');
    $sortq->execute([$planId,$day,$unit]); $sort=(int)$sortq->fetchColumn();

    if ($hasSource) {
        $ins = db()->prepare('INSERT INTO tasks (plan_id,student_id,subject_id,title,description,source,task_type,day_index,unit_index,target_count,target_unit,duration_min,priority,sort_order) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
        $ins->execute([$planId,$studentId,$subj,$title,$desc,$source,$type,$day,$unit,$target,$tunit,$dur,$prio,$sort]);
    } else {
        $ins = db()->prepare('INSERT INTO tasks (plan_id,student_id,subject_id,title,description,task_type,day_index,unit_index,target_count,target_unit,duration_min,priority,sort_order) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)');
        $ins->execute([$planId,$studentId,$subj,$title,$desc,$type,$day,$unit,$target,$tunit,$dur,$prio,$sort]);
    }
    $id = (int)db()->lastInsertId();
    // یادگیری انتخاب برای پرکردن خودکار هوشمند (فقط برای ساخت دستی، نه کپی)
    if (empty($in['_no_learn'])) {
        remember_task_choice($me, ['task_type'=>$type,'unit_index'=>$unit,'subject_id'=>$subj,
            'target_count'=>$target,'target_unit'=>$tunit,'duration_min'=>$dur,'priority'=>$prio,'source'=>$source]);
    }
    json_out(['ok'=>true,'task'=>render_task(task_row($id))]);
}

/* ============ ویرایش تسک (مشاور) ============ */
case 'update': {
    if (!in_array($role, ['advisor','admin'], true)) json_out(['ok'=>false,'error'=>'دسترسی ندارید'],403);
    $id = (int)($in['id'] ?? 0); $t = task_row($id);
    if (!$t || !plan_owned_by_advisor((int)$t['plan_id'],$me,$role)) json_out(['ok'=>false,'error'=>'تسک یافت نشد'],404);
    $type = in_array($in['task_type'] ?? '', array_keys(TASK_TYPES), true) ? $in['task_type'] : $t['task_type'];
    if (in_array($type, EXTENDED_TASK_TYPES, true) && !ensure_extended_task_types()) json_out(['ok'=>false,'error'=>'برای نوع‌های جدید، فایل sql/upgrade_task_types2.sql را یک‌بار اجرا کنید (یا install.php را باز کنید).'],500);
    $hasSource = ensure_source_column();
    $target = ($in['target_count'] ?? '')!=='' ? max(0,(int)$in['target_count']) : null;
    $tunit  = trim((string)($in['target_unit'] ?? $t['target_unit'])) ?: 'تست';
    $dur    = ($in['duration_min'] ?? '')!=='' ? max(0,(int)$in['duration_min']) : null;
    $subj   = isset($in['subject_id']) && $in['subject_id'] ? (int)$in['subject_id'] : null;
    $title = trim((string)($in['title'] ?? $t['title']));
    if ($title === '') $title = default_task_title($type, $subj);
    $desc   = trim((string)($in['description'] ?? '')) ?: null;
    $source = isset($in['source']) ? (trim((string)$in['source']) ?: null) : ($t['source'] ?? null);
    $prio   = in_array($in['priority'] ?? '', ['low','normal','high'], true) ? $in['priority'] : $t['priority'];
    if ($hasSource) {
        $up = db()->prepare('UPDATE tasks SET title=?,description=?,source=?,task_type=?,target_count=?,target_unit=?,duration_min=?,subject_id=?,priority=? WHERE id=?');
        $up->execute([$title,$desc,$source,$type,$target,$tunit,$dur,$subj,$prio,$id]);
    } else {
        $up = db()->prepare('UPDATE tasks SET title=?,description=?,task_type=?,target_count=?,target_unit=?,duration_min=?,subject_id=?,priority=? WHERE id=?');
        $up->execute([$title,$desc,$type,$target,$tunit,$dur,$subj,$prio,$id]);
    }
    if (empty($in['_no_learn'])) {
        remember_task_choice($me, ['task_type'=>$type,'unit_index'=>(int)$t['unit_index'],'subject_id'=>$subj,
            'target_count'=>$target,'target_unit'=>$tunit,'duration_min'=>$dur,'priority'=>$prio,'source'=>$source]);
    }
    json_out(['ok'=>true,'task'=>render_task(task_row($id))]);
}

/* ============ حذف تسک ============ */
case 'delete': {
    if (!in_array($role, ['advisor','admin'], true)) json_out(['ok'=>false,'error'=>'دسترسی ندارید'],403);
    $id = (int)($in['id'] ?? 0); $t = task_row($id);
    if (!$t || !plan_owned_by_advisor((int)$t['plan_id'],$me,$role)) json_out(['ok'=>false,'error'=>'تسک یافت نشد'],404);
    db()->prepare('DELETE FROM tasks WHERE id=?')->execute([$id]);
    json_out(['ok'=>true]);
}

/* ============ پاک‌کردن یک روز ============ */
case 'clear_day': {
    if (!in_array($role, ['advisor','admin'], true)) json_out(['ok'=>false,'error'=>'دسترسی ندارید'],403);
    $planId=(int)($in['plan_id']??0); $day=clamp_int($in['day_index']??0,0,6);
    if (!plan_owned_by_advisor($planId,$me,$role)) json_out(['ok'=>false,'error'=>'برنامه نامعتبر'],403);
    db()->prepare('DELETE FROM tasks WHERE plan_id=? AND day_index=?')->execute([$planId,$day]);
    json_out(['ok'=>true]);
}

/* ============ پاک‌کردن یک واحد در کل هفته ============ */
case 'clear_unit': {
    if (!in_array($role, ['advisor','admin'], true)) json_out(['ok'=>false,'error'=>'دسترسی ندارید'],403);
    $planId=(int)($in['plan_id']??0); $unit=clamp_int($in['unit_index']??1,1,8);
    if (!plan_owned_by_advisor($planId,$me,$role)) json_out(['ok'=>false,'error'=>'برنامه نامعتبر'],403);
    $del = db()->prepare('DELETE FROM tasks WHERE plan_id=? AND unit_index=?');
    $del->execute([$planId,$unit]);
    json_out(['ok'=>true,'removed'=>$del->rowCount()]);
}

/* ============ پیشنهاد هوشمند برای پرکردن خودکار خانه ============ */
case 'suggest': {
    if (!in_array($role, ['advisor','admin'], true)) json_out(['ok'=>false,'error'=>'دسترسی ندارید'],403);
    $unit = isset($in['unit_index']) && $in['unit_index']!=='' ? (int)$in['unit_index'] : null;
    $subj = isset($in['subject_id']) && $in['subject_id']!=='' ? (int)$in['subject_id'] : null;
    json_out(['ok'=>true,'suggestion'=>suggest_task_defaults($me, $unit, $subj)]);
}

/* ============ انتشار / پیش‌نویس برنامه ============ */
case 'publish': {
    if (!in_array($role, ['advisor','admin'], true)) json_out(['ok'=>false,'error'=>'دسترسی ندارید'],403);
    $planId=(int)($in['plan_id']??0); $status = ($in['status']??'published')==='draft'?'draft':'published';
    if (!plan_owned_by_advisor($planId,$me,$role)) json_out(['ok'=>false,'error'=>'برنامه نامعتبر'],403);
    db()->prepare('UPDATE plans SET status=? WHERE id=?')->execute([$status,$planId]);
    if ($status==='published') {
        $st=db()->prepare('SELECT student_id FROM plans WHERE id=?'); $st->execute([$planId]); $sid=(int)$st->fetchColumn();
        notify($sid,'برنامه‌ی جدید شما آماده شد 📅','مشاور برنامه‌ی این هفته را منتشر کرد.','calendar','student/plan.php');
    }
    json_out(['ok'=>true,'status'=>$status]);
}

/* ============ کپی از هفته قبل ============ */
case 'copy_week': {
    if (!in_array($role, ['advisor','admin'], true)) json_out(['ok'=>false,'error'=>'دسترسی ندارید'],403);
    $planId=(int)($in['plan_id']??0);
    if (!plan_owned_by_advisor($planId,$me,$role)) json_out(['ok'=>false,'error'=>'برنامه نامعتبر'],403);
    $p = db()->prepare('SELECT * FROM plans WHERE id=?'); $p->execute([$planId]); $plan=$p->fetch();
    $prevWeek = date('Y-m-d', strtotime($plan['week_start'].' -7 day'));
    $prev = db()->prepare('SELECT id FROM plans WHERE student_id=? AND week_start=?');
    $prev->execute([$plan['student_id'],$prevWeek]); $prevId=(int)$prev->fetchColumn();
    if (!$prevId) json_out(['ok'=>false,'error'=>'برنامه‌ی هفته‌ی قبل یافت نشد'],404);
    db()->prepare('DELETE FROM tasks WHERE plan_id=?')->execute([$planId]);
    $rows = plan_tasks($prevId); $n=0;
    $hasSource = ensure_source_column();
    if ($hasSource) {
        $ins = db()->prepare('INSERT INTO tasks (plan_id,student_id,subject_id,title,description,source,task_type,day_index,unit_index,target_count,target_unit,duration_min,priority,sort_order) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
        foreach ($rows as $r) { $ins->execute([$planId,$plan['student_id'],$r['subject_id'],$r['title'],$r['description'],$r['source']??null,$r['task_type'],$r['day_index'],$r['unit_index'],$r['target_count'],$r['target_unit'],$r['duration_min'],$r['priority'],$r['sort_order']]); $n++; }
    } else {
        $ins = db()->prepare('INSERT INTO tasks (plan_id,student_id,subject_id,title,description,task_type,day_index,unit_index,target_count,target_unit,duration_min,priority,sort_order) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)');
        foreach ($rows as $r) { $ins->execute([$planId,$plan['student_id'],$r['subject_id'],$r['title'],$r['description'],$r['task_type'],$r['day_index'],$r['unit_index'],$r['target_count'],$r['target_unit'],$r['duration_min'],$r['priority'],$r['sort_order']]); $n++; }
    }
    json_out(['ok'=>true,'copied'=>$n]);
}

/* ============ کپی کل برنامه به دانش‌آموز دیگر ============ */
case 'copy_to_student': {
    if (!in_array($role, ['advisor','admin'], true)) json_out(['ok'=>false,'error'=>'دسترسی ندارید'],403);
    $planId=(int)($in['plan_id']??0); $targetStudent=(int)($in['student_id']??0);
    if (!plan_owned_by_advisor($planId,$me,$role)) json_out(['ok'=>false,'error'=>'برنامه مبدا نامعتبر است'],403);
    $target = get_user($targetStudent);
    if (!$target || $target['role']!=='student') json_out(['ok'=>false,'error'=>'دانش‌آموز مقصد یافت نشد'],404);
    if ($role !== 'admin' && !empty($target['advisor_id']) && (int)$target['advisor_id'] !== $me) json_out(['ok'=>false,'error'=>'این دانش‌آموز متعلق به شما نیست'],403);
    $p = db()->prepare('SELECT * FROM plans WHERE id=?'); $p->execute([$planId]); $src=$p->fetch();
    if (!$src) json_out(['ok'=>false,'error'=>'برنامه مبدا یافت نشد'],404);
    $targetPlan = find_or_create_plan($targetStudent, (int)$src['advisor_id'], $src['week_start']);
    db()->beginTransaction();
    try {
        db()->prepare('DELETE FROM tasks WHERE plan_id=?')->execute([$targetPlan['id']]);
        $rows = plan_tasks($planId); $n=0;
        $hasSource = ensure_source_column();
        if ($hasSource) {
            $ins = db()->prepare('INSERT INTO tasks (plan_id,student_id,subject_id,title,description,source,task_type,day_index,unit_index,target_count,target_unit,duration_min,priority,sort_order) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
            foreach ($rows as $r) {
                $ins->execute([$targetPlan['id'],$targetStudent,$r['subject_id'],$r['title'],$r['description'],$r['source']??null,$r['task_type'],$r['day_index'],$r['unit_index'],$r['target_count'],$r['target_unit'],$r['duration_min'],$r['priority'],$r['sort_order']]);
                $n++;
            }
        } else {
            $ins = db()->prepare('INSERT INTO tasks (plan_id,student_id,subject_id,title,description,task_type,day_index,unit_index,target_count,target_unit,duration_min,priority,sort_order) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)');
            foreach ($rows as $r) {
                $ins->execute([$targetPlan['id'],$targetStudent,$r['subject_id'],$r['title'],$r['description'],$r['task_type'],$r['day_index'],$r['unit_index'],$r['target_count'],$r['target_unit'],$r['duration_min'],$r['priority'],$r['sort_order']]);
                $n++;
            }
        }
        db()->prepare('UPDATE plans SET title=?, status="draft", note=? WHERE id=?')->execute([$src['title'], $src['note'], $targetPlan['id']]);
        db()->commit();
    } catch (Throwable $e) { db()->rollBack(); throw $e; }
    json_out(['ok'=>true,'copied'=>$n,'target_plan'=>(int)$targetPlan['id']]);
}

/* ============ جابه‌جایی تسک با Drag & Drop (مشاور) ============ */
case 'move': {
    if (!in_array($role, ['advisor','admin'], true)) json_out(['ok'=>false,'error'=>'دسترسی ندارید'],403);
    $id = (int)($in['id'] ?? 0); $t = task_row($id);
    if (!$t || !plan_owned_by_advisor((int)$t['plan_id'],$me,$role)) json_out(['ok'=>false,'error'=>'تسک یافت نشد'],404);
    $day = clamp_int($in['day_index'] ?? $t['day_index'], 0, 6);
    $unit = clamp_int($in['unit_index'] ?? $t['unit_index'], 1, 8);
    $sortq = db()->prepare('SELECT COALESCE(MAX(sort_order),0)+1 FROM tasks WHERE plan_id=? AND day_index=? AND unit_index=?');
    $sortq->execute([$t['plan_id'],$day,$unit]); $sort=(int)$sortq->fetchColumn();
    db()->prepare('UPDATE tasks SET day_index=?, unit_index=?, sort_order=? WHERE id=?')->execute([$day,$unit,$sort,$id]);
    json_out(['ok'=>true,'task'=>render_task(task_row($id))]);
}

/* ============ افزودن/ترمیم واحد ویژه پیش‌فرض ============ */
case 'seed_special': {
    if (!in_array($role, ['advisor','admin'], true)) json_out(['ok'=>false,'error'=>'دسترسی ندارید'],403);
    $planId=(int)($in['plan_id']??0);
    if (!plan_owned_by_advisor($planId,$me,$role)) json_out(['ok'=>false,'error'=>'برنامه نامعتبر'],403);
    $cfg = advisor_settings($me);
    $added = seed_special_tasks($planId, (int)$cfg['special_reading_min'], (int)$cfg['special_exam_min']);
    json_out(['ok'=>true,'added'=>$added]);
}

/* ============ تکمیل تسک توسط دانش‌آموز ============ */
case 'toggle': {
    if ($role!=='student') json_out(['ok'=>false,'error'=>'دسترسی ندارید'],403);
    $id=(int)($in['id']??0); $t=task_row($id);
    if (!$t || (int)$t['student_id']!==$me) json_out(['ok'=>false,'error'=>'تسک یافت نشد'],404);
    if ($t['plan_status']!=='published') json_out(['ok'=>false,'error'=>'این برنامه هنوز منتشر نشده'],403);

    // مبنای تکمیل = خواست دانش‌آموز (تیک)، نه مقایسه با هدف.
    // اگر done صریحاً ارسال شود همان ملاک است؛ وگرنه وضعیت فعلی برعکس می‌شود.
    if (isset($in['done']) && $in['done'] !== '') {
        $done = (int)((string)$in['done'] === '1' || $in['done'] === true || $in['done'] === 1);
    } else {
        $done = (int)($t['is_done']) ? 0 : 1;
    }

    // مقدار انجام‌شده فقط برای آمار است و تأثیری در تکمیل ندارد
    if (isset($in['done_count']) && $in['done_count']!=='') {
        $doneCount = max(0,(int)$in['done_count']);
        if ($t['target_count']!==null) $doneCount = min($doneCount, (int)$t['target_count']);
    } else {
        // اگر مقدار نفرستاد: تکمیل کامل=هدف، لغو=۰
        $doneCount = $done ? ((int)($t['target_count'] ?? 0) ?: (int)$t['done_count'] ?: 1) : 0;
    }

    $note = isset($in['student_note']) ? trim((string)$in['student_note']) : $t['student_note'];
    $up=db()->prepare('UPDATE tasks SET is_done=?,done_count=?,student_note=?,completed_at=? WHERE id=?');
    $up->execute([$done,$doneCount,$note ?: null,$done?date('Y-m-d H:i:s'):null,$id]);
    if ($done) { touch_streak($me);
        notify((int)$t['advisor_id'],'تسک تکمیل شد ✅', ($u['full_name'].' «'.$t['title'].'» را انجام داد.'),'check','admin/reports.php?student='.$me);
        evaluate_achievements($me); // اعطای خودکار دستاوردهای واجد شرایط
    }
    $week = student_week_stats($me);
    json_out(['ok'=>true,'is_done'=>$done,'done_count'=>$doneCount,'target'=>$t['target_count']!==null?(int)$t['target_count']:null,'week'=>$week,'streak'=>get_user($me)['streak']]);
}

/* ============ یادداشت دانش‌آموز ============ */
case 'note': {
    if ($role!=='student') json_out(['ok'=>false,'error'=>'دسترسی ندارید'],403);
    $id=(int)($in['id']??0); $t=task_row($id);
    if (!$t || (int)$t['student_id']!==$me) json_out(['ok'=>false,'error'=>'تسک یافت نشد'],404);
    $note=trim((string)($in['student_note']??''));
    db()->prepare('UPDATE tasks SET student_note=? WHERE id=?')->execute([$note ?: null,$id]);
    json_out(['ok'=>true]);
}

/* ============ بازخورد مشاور ============ */
case 'feedback': {
    if (!in_array($role, ['advisor','admin'], true)) json_out(['ok'=>false,'error'=>'دسترسی ندارید'],403);
    $id=(int)($in['id']??0); $t=task_row($id);
    if (!$t || !plan_owned_by_advisor((int)$t['plan_id'],$me,$role)) json_out(['ok'=>false,'error'=>'تسک یافت نشد'],404);
    $fb=trim((string)($in['feedback']??''));
    db()->prepare('UPDATE tasks SET advisor_feedback=?, feedback_at=? WHERE id=?')->execute([$fb ?: null,$fb?date('Y-m-d H:i:s'):null,$id]);
    if ($fb) notify((int)$t['student_id'],'بازخورد جدید 💬','مشاور روی «'.$t['title'].'» نظر گذاشت.','message','student/plan.php');
    json_out(['ok'=>true]);
}

default:
    json_out(['ok'=>false,'error'=>'عملیات نامعتبر'],400);
}
} catch (Throwable $ex) {
    json_out(['ok'=>false,'error'=> APP_ENV==='development' ? $ex->getMessage() : 'خطای داخلی سرور'],500);
}

/* ---- رندر JSON یک تسک برای فرانت ---- */
function render_task(array $t): array {
    return [
        'id'=>(int)$t['id'],'title'=>$t['title'],'description'=>$t['description'],'source'=>$t['source']??null,
        'task_type'=>$t['task_type'],'type_label'=>TASK_TYPES[$t['task_type']]['label']??$t['task_type'],
        'day_index'=>(int)$t['day_index'],'unit_index'=>(int)$t['unit_index'],
        'target_count'=>$t['target_count']!==null?(int)$t['target_count']:null,'target_unit'=>$t['target_unit'],
        'duration_min'=>$t['duration_min']!==null?(int)$t['duration_min']:null,'priority'=>$t['priority'],
        'is_done'=>(int)$t['is_done'],'done_count'=>(int)$t['done_count'],
        'subject_id'=>$t['subject_id']!==null?(int)$t['subject_id']:null,
    ];
}
