<?php
/**
 * API تسک‌ها — CRUD + toggle
 * actions: list, create, update, delete, toggle, feedback, publish, copy_week, clear_day
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/models.php';
boot_session();
require_login();
require_csrf();

$u = current_user();
$me = (int)$u['id'];
$role = $u['role'];
$action = (string)(input('action') ?: (body_json()['action'] ?? ''));
$in = array_merge($_POST, body_json());

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

try {
switch ($action) {

/* ============ ساخت تسک (مشاور) ============ */
case 'create': {
    if (!in_array($role, ['advisor','admin'], true)) json_out(['ok'=>false,'error'=>'دسترسی ندارید'],403);
    $planId  = (int)($in['plan_id'] ?? 0);
    if (!plan_owned_by_advisor($planId, $me, $role)) json_out(['ok'=>false,'error'=>'برنامه نامعتبر'],403);
    $st = db()->prepare('SELECT student_id FROM plans WHERE id=?'); $st->execute([$planId]);
    $studentId = (int)$st->fetchColumn();

    $title = trim((string)($in['title'] ?? ''));
    if ($title === '') json_out(['ok'=>false,'error'=>'عنوان تسک الزامی است'],422);
    $day  = clamp_int($in['day_index'] ?? 0, 0, 6);
    $unit = clamp_int($in['unit_index'] ?? 1, 1, 8);
    $type = in_array($in['task_type'] ?? '', array_keys(TASK_TYPES), true) ? $in['task_type'] : 'study';
    $target = isset($in['target_count']) && $in['target_count']!=='' ? max(0,(int)$in['target_count']) : null;
    $tunit  = trim((string)($in['target_unit'] ?? 'تست')) ?: 'تست';
    $dur    = isset($in['duration_min']) && $in['duration_min']!=='' ? max(0,(int)$in['duration_min']) : null;
    $subj   = isset($in['subject_id']) && $in['subject_id'] ? (int)$in['subject_id'] : null;
    $desc   = trim((string)($in['description'] ?? '')) ?: null;
    $prio   = in_array($in['priority'] ?? '', ['low','normal','high'], true) ? $in['priority'] : 'normal';

    $sortq = db()->prepare('SELECT COALESCE(MAX(sort_order),0)+1 FROM tasks WHERE plan_id=? AND day_index=? AND unit_index=?');
    $sortq->execute([$planId,$day,$unit]); $sort=(int)$sortq->fetchColumn();

    $ins = db()->prepare('INSERT INTO tasks (plan_id,student_id,subject_id,title,description,task_type,day_index,unit_index,target_count,target_unit,duration_min,priority,sort_order) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)');
    $ins->execute([$planId,$studentId,$subj,$title,$desc,$type,$day,$unit,$target,$tunit,$dur,$prio,$sort]);
    $id = (int)db()->lastInsertId();
    json_out(['ok'=>true,'task'=>render_task(task_row($id))]);
}

/* ============ ویرایش تسک (مشاور) ============ */
case 'update': {
    if (!in_array($role, ['advisor','admin'], true)) json_out(['ok'=>false,'error'=>'دسترسی ندارید'],403);
    $id = (int)($in['id'] ?? 0); $t = task_row($id);
    if (!$t || !plan_owned_by_advisor((int)$t['plan_id'],$me,$role)) json_out(['ok'=>false,'error'=>'تسک یافت نشد'],404);
    $title = trim((string)($in['title'] ?? $t['title']));
    if ($title==='') json_out(['ok'=>false,'error'=>'عنوان الزامی است'],422);
    $type = in_array($in['task_type'] ?? '', array_keys(TASK_TYPES), true) ? $in['task_type'] : $t['task_type'];
    $target = ($in['target_count'] ?? '')!=='' ? max(0,(int)$in['target_count']) : null;
    $tunit  = trim((string)($in['target_unit'] ?? $t['target_unit'])) ?: 'تست';
    $dur    = ($in['duration_min'] ?? '')!=='' ? max(0,(int)$in['duration_min']) : null;
    $subj   = isset($in['subject_id']) && $in['subject_id'] ? (int)$in['subject_id'] : null;
    $desc   = trim((string)($in['description'] ?? '')) ?: null;
    $prio   = in_array($in['priority'] ?? '', ['low','normal','high'], true) ? $in['priority'] : $t['priority'];
    $up = db()->prepare('UPDATE tasks SET title=?,description=?,task_type=?,target_count=?,target_unit=?,duration_min=?,subject_id=?,priority=? WHERE id=?');
    $up->execute([$title,$desc,$type,$target,$tunit,$dur,$subj,$prio,$id]);
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
    $ins = db()->prepare('INSERT INTO tasks (plan_id,student_id,subject_id,title,description,task_type,day_index,unit_index,target_count,target_unit,duration_min,priority,sort_order) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)');
    foreach ($rows as $r) { $ins->execute([$planId,$plan['student_id'],$r['subject_id'],$r['title'],$r['description'],$r['task_type'],$r['day_index'],$r['unit_index'],$r['target_count'],$r['target_unit'],$r['duration_min'],$r['priority'],$r['sort_order']]); $n++; }
    json_out(['ok'=>true,'copied'=>$n]);
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
        'id'=>(int)$t['id'],'title'=>$t['title'],'description'=>$t['description'],
        'task_type'=>$t['task_type'],'type_label'=>TASK_TYPES[$t['task_type']]['label']??$t['task_type'],
        'day_index'=>(int)$t['day_index'],'unit_index'=>(int)$t['unit_index'],
        'target_count'=>$t['target_count']!==null?(int)$t['target_count']:null,'target_unit'=>$t['target_unit'],
        'duration_min'=>$t['duration_min']!==null?(int)$t['duration_min']:null,'priority'=>$t['priority'],
        'is_done'=>(int)$t['is_done'],'done_count'=>(int)$t['done_count'],
        'subject_id'=>$t['subject_id']!==null?(int)$t['subject_id']:null,
    ];
}
