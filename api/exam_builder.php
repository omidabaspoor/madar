<?php
/**
 * API ساخت آزمون (مشاور) — با ذخیره‌ی خودکار
 * actions: save_meta, add_section, update_section, delete_section,
 *          add_question, save_question, delete_question, autosave, publish, set_status
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/models.php';
boot_session();
require_role('advisor','admin');
require_csrf();
$u = current_user();
$me = (int)$u['id'];
$in = array_merge($_POST, body_json());
$action = (string)($in['action'] ?? '');

function own_exam(int $examId, int $me, string $role): ?array {
    $e = get_exam($examId);
    if (!$e) return null;
    if ($role !== 'admin' && (int)$e['advisor_id'] !== $me) return null;
    return $e;
}

try {
switch ($action) {

case 'save_meta': {
    $id = (int)($in['id'] ?? 0);
    $title = trim((string)($in['title'] ?? '')) ?: 'آزمون بدون عنوان';
    $desc = trim((string)($in['description'] ?? '')) ?: null;
    $etype = in_array($in['exam_type'] ?? '', ['single','comprehensive'], true) ? $in['exam_type'] : 'single';
    $timing = in_array($in['timing_mode'] ?? '', ['total','per_section'], true) ? $in['timing_mode'] : 'total';
    $dur = max(1, (int)($in['duration_min'] ?? 60));
    $neg = isset($in['negative_marking']) ? (int)((string)$in['negative_marking']==='1') : 1;
    $rev = isset($in['show_review']) ? (int)((string)$in['show_review']==='1') : 1;
    $shuf = isset($in['shuffle_questions']) ? (int)((string)$in['shuffle_questions']==='1') : 0;
    $start = trim((string)($in['start_at'] ?? '')) ?: null;
    $end = trim((string)($in['end_at'] ?? '')) ?: null;

    if ($id) {
        if (!own_exam($id,$me,$u['role'])) json_out(['ok'=>false,'error'=>'آزمون یافت نشد'],404);
        db()->prepare('UPDATE exams SET title=?,description=?,exam_type=?,timing_mode=?,duration_min=?,negative_marking=?,show_review=?,shuffle_questions=?,start_at=?,end_at=? WHERE id=?')
            ->execute([$title,$desc,$etype,$timing,$dur,$neg,$rev,$shuf,$start,$end,$id]);
    } else {
        db()->prepare('INSERT INTO exams (advisor_id,title,description,exam_type,timing_mode,duration_min,negative_marking,show_review,shuffle_questions,start_at,end_at) VALUES (?,?,?,?,?,?,?,?,?,?,?)')
            ->execute([$me,$title,$desc,$etype,$timing,$dur,$neg,$rev,$shuf,$start,$end]);
        $id = (int)db()->lastInsertId();
    }
    json_out(['ok'=>true,'id'=>$id]);
}

case 'add_section': {
    $examId = (int)($in['exam_id'] ?? 0);
    if (!own_exam($examId,$me,$u['role'])) json_out(['ok'=>false,'error'=>'آزمون یافت نشد'],404);
    $name = trim((string)($in['name'] ?? '')) ?: 'بخش جدید';
    $subj = isset($in['subject_id']) && $in['subject_id'] ? (int)$in['subject_id'] : null;
    $dur = isset($in['duration_min']) && $in['duration_min']!=='' ? max(1,(int)$in['duration_min']) : null;
    $so = (int)db()->query('SELECT COALESCE(MAX(sort_order),0)+1 FROM exam_sections WHERE exam_id='.$examId)->fetchColumn();
    $ins = db()->prepare('INSERT INTO exam_sections (exam_id,subject_id,name,duration_min,sort_order) VALUES (?,?,?,?,?)');
    $ins->execute([$examId,$subj,$name,$dur,$so]);
    json_out(['ok'=>true,'id'=>(int)db()->lastInsertId()]);
}

case 'update_section': {
    $id = (int)($in['id'] ?? 0);
    $sec = db()->prepare('SELECT s.*, e.advisor_id FROM exam_sections s JOIN exams e ON e.id=s.exam_id WHERE s.id=?');
    $sec->execute([$id]); $s = $sec->fetch();
    if (!$s || ($u['role']!=='admin' && (int)$s['advisor_id']!==$me)) json_out(['ok'=>false,'error'=>'بخش یافت نشد'],404);
    $name = trim((string)($in['name'] ?? $s['name'])) ?: $s['name'];
    $subj = isset($in['subject_id']) && $in['subject_id'] ? (int)$in['subject_id'] : null;
    $dur = isset($in['duration_min']) && $in['duration_min']!=='' ? max(1,(int)$in['duration_min']) : null;
    db()->prepare('UPDATE exam_sections SET name=?,subject_id=?,duration_min=? WHERE id=?')->execute([$name,$subj,$dur,$id]);
    json_out(['ok'=>true]);
}

case 'delete_section': {
    $id = (int)($in['id'] ?? 0);
    $sec = db()->prepare('SELECT s.exam_id, e.advisor_id FROM exam_sections s JOIN exams e ON e.id=s.exam_id WHERE s.id=?');
    $sec->execute([$id]); $s = $sec->fetch();
    if (!$s || ($u['role']!=='admin' && (int)$s['advisor_id']!==$me)) json_out(['ok'=>false,'error'=>'یافت نشد'],404);
    db()->prepare('DELETE FROM exam_sections WHERE id=?')->execute([$id]);
    json_out(['ok'=>true]);
}

case 'add_question': {
    $examId = (int)($in['exam_id'] ?? 0);
    $secId  = (int)($in['section_id'] ?? 0);
    if (!own_exam($examId,$me,$u['role'])) json_out(['ok'=>false,'error'=>'آزمون یافت نشد'],404);
    $so = (int)db()->query('SELECT COALESCE(MAX(sort_order),0)+1 FROM exam_questions WHERE section_id='.$secId)->fetchColumn();
    $ins = db()->prepare('INSERT INTO exam_questions (exam_id,section_id,correct_opt,sort_order) VALUES (?,?,1,?)');
    $ins->execute([$examId,$secId,$so]);
    json_out(['ok'=>true,'id'=>(int)db()->lastInsertId()]);
}

case 'save_question': {
    $id = (int)($in['id'] ?? 0);
    $q = db()->prepare('SELECT q.*, e.advisor_id FROM exam_questions q JOIN exams e ON e.id=q.exam_id WHERE q.id=?');
    $q->execute([$id]); $row = $q->fetch();
    if (!$row || ($u['role']!=='admin' && (int)$row['advisor_id']!==$me)) json_out(['ok'=>false,'error'=>'سوال یافت نشد'],404);
    $txt = trim((string)($in['q_text'] ?? ''));
    $o1 = trim((string)($in['opt1'] ?? '')); $o2 = trim((string)($in['opt2'] ?? ''));
    $o3 = trim((string)($in['opt3'] ?? '')); $o4 = trim((string)($in['opt4'] ?? ''));
    $cor = max(1,min(4,(int)($in['correct_opt'] ?? 1)));
    $exp = trim((string)($in['explanation'] ?? '')) ?: null;
    db()->prepare('UPDATE exam_questions SET q_text=?,opt1=?,opt2=?,opt3=?,opt4=?,correct_opt=?,explanation=? WHERE id=?')
        ->execute([$txt ?: null,$o1 ?: null,$o2 ?: null,$o3 ?: null,$o4 ?: null,$cor,$exp,$id]);
    json_out(['ok'=>true]);
}

/* ---- ذخیره‌ی خودکار دسته‌ای (هر ۵ ثانیه) ---- */
case 'autosave': {
    $examId = (int)($in['exam_id'] ?? 0);
    if (!own_exam($examId,$me,$u['role'])) json_out(['ok'=>false,'error'=>'آزمون یافت نشد'],404);
    $questions = $in['questions'] ?? [];
    if (!is_array($questions)) $questions = [];
    $upd = db()->prepare('UPDATE exam_questions SET q_text=?,opt1=?,opt2=?,opt3=?,opt4=?,correct_opt=?,explanation=? WHERE id=? AND exam_id=?');
    $saved = 0;
    db()->beginTransaction();
    try {
        foreach ($questions as $q) {
            $qid = (int)($q['id'] ?? 0); if (!$qid) continue;
            $cor = max(1,min(4,(int)($q['correct_opt'] ?? 1)));
            $upd->execute([
                trim((string)($q['q_text'] ?? '')) ?: null,
                trim((string)($q['opt1'] ?? '')) ?: null,
                trim((string)($q['opt2'] ?? '')) ?: null,
                trim((string)($q['opt3'] ?? '')) ?: null,
                trim((string)($q['opt4'] ?? '')) ?: null,
                $cor,
                trim((string)($q['explanation'] ?? '')) ?: null,
                $qid, $examId,
            ]);
            $saved++;
        }
        db()->commit();
    } catch (Throwable $ex) { db()->rollBack(); throw $ex; }
    json_out(['ok'=>true,'saved'=>$saved,'time'=>date('H:i:s')]);
}

case 'delete_question': {
    $id = (int)($in['id'] ?? 0);
    $q = db()->prepare('SELECT q.id, e.advisor_id FROM exam_questions q JOIN exams e ON e.id=q.exam_id WHERE q.id=?');
    $q->execute([$id]); $row = $q->fetch();
    if (!$row || ($u['role']!=='admin' && (int)$row['advisor_id']!==$me)) json_out(['ok'=>false,'error'=>'یافت نشد'],404);
    db()->prepare('DELETE FROM exam_questions WHERE id=?')->execute([$id]);
    json_out(['ok'=>true]);
}

case 'set_status': {
    $examId = (int)($in['exam_id'] ?? 0);
    $e = own_exam($examId,$me,$u['role']);
    if (!$e) json_out(['ok'=>false,'error'=>'آزمون یافت نشد'],404);
    $status = in_array($in['status'] ?? '', ['draft','published','closed'], true) ? $in['status'] : 'draft';
    if ($status === 'published') {
        // اعتبارسنجی: حداقل یک بخش و یک سوال کامل
        if (exam_question_count($examId) === 0) json_out(['ok'=>false,'error'=>'آزمون حداقل باید یک سوال داشته باشد'],422);
    }
    db()->prepare('UPDATE exams SET status=? WHERE id=?')->execute([$status,$examId]);
    if ($status==='published') {
        // اعلان به دانش‌آموزان فعال
        $studs = db()->query("SELECT id FROM users WHERE role='student' AND status='active'")->fetchAll();
        foreach ($studs as $s) notify((int)$s['id'],'آزمون جدید منتشر شد 📝', $e['title'], 'clipboard', 'student/exams.php');
    }
    json_out(['ok'=>true,'status'=>$status]);
}

case 'upload_image': {
    $examId = (int)($in['exam_id'] ?? 0);
    $qid = (int)($in['question_id'] ?? 0);
    if (!own_exam($examId,$me,$u['role'])) json_out(['ok'=>false,'error'=>'آزمون یافت نشد'],404);
    if (empty($_FILES['image'])) json_out(['ok'=>false,'error'=>'فایلی ارسال نشد'],422);
    $f = $_FILES['image'];
    if ($f['size'] > MAX_UPLOAD) json_out(['ok'=>false,'error'=>'حجم عکس بیش از حد مجاز است'],422);
    $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg','jpeg','png','webp','gif'], true)) json_out(['ok'=>false,'error'=>'فرمت عکس مجاز نیست'],422);
    $info = @getimagesize($f['tmp_name']);
    if (!$info) json_out(['ok'=>false,'error'=>'فایل عکس معتبر نیست'],422);
    if (!is_dir(UPLOAD_DIR.'/exams')) @mkdir(UPLOAD_DIR.'/exams', 0775, true);
    $name = 'q'.$qid.'_'.bin2hex(random_bytes(5)).'.'.$ext;
    $dest = UPLOAD_DIR.'/exams/'.$name;
    if (!move_uploaded_file($f['tmp_name'], $dest)) json_out(['ok'=>false,'error'=>'خطا در ذخیره عکس'],500);
    $rel = 'uploads/exams/'.$name;
    db()->prepare('UPDATE exam_questions SET q_image=? WHERE id=?')->execute([$rel,$qid]);
    json_out(['ok'=>true,'url'=>url($rel)]);
}

case 'remove_image': {
    $id = (int)($in['id'] ?? 0);
    $q = db()->prepare('SELECT q.q_image, e.advisor_id FROM exam_questions q JOIN exams e ON e.id=q.exam_id WHERE q.id=?');
    $q->execute([$id]); $row=$q->fetch();
    if (!$row || ($u['role']!=='admin' && (int)$row['advisor_id']!==$me)) json_out(['ok'=>false,'error'=>'یافت نشد'],404);
    if ($row['q_image']) @unlink(__DIR__.'/../'.$row['q_image']);
    db()->prepare('UPDATE exam_questions SET q_image=NULL WHERE id=?')->execute([$id]);
    json_out(['ok'=>true]);
}

case 'delete_exam': {
    $examId = (int)($in['exam_id'] ?? 0);
    if (!own_exam($examId,$me,$u['role'])) json_out(['ok'=>false,'error'=>'یافت نشد'],404);
    db()->prepare('DELETE FROM exams WHERE id=?')->execute([$examId]);
    json_out(['ok'=>true]);
}

default: json_out(['ok'=>false,'error'=>'عملیات نامعتبر'],400);
}
} catch (Throwable $e) {
    json_out(['ok'=>false,'error'=> APP_ENV==='development' ? $e->getMessage() : 'خطای سرور'],500);
}
