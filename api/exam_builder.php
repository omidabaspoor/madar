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

case 'reset_attempt': {
    $examId    = (int)($in['exam_id'] ?? 0);
    $attemptId = (int)($in['attempt_id'] ?? 0);
    if (!own_exam($examId, $me, $u['role'])) json_out(['ok'=>false,'error'=>'آزمون یافت نشد'], 404);
    
    db()->prepare('DELETE FROM exam_attempts WHERE id=? AND exam_id=?')->execute([$attemptId, $examId]);
    json_out(['ok'=>true]);
}

case 'upload_sheet': {
    $examId = (int)($in['exam_id'] ?? 0);
    if (!own_exam($examId, $me, $u['role'])) json_out(['ok'=>false,'error'=>'آزمون یافت نشد'], 404);
    if (empty($_FILES['sheet'])) json_out(['ok'=>false,'error'=>'فایلی ارسال نشد'], 422);
    
    // مدیریت آپلود فایل‌های چندتایی (Multi-Image Quick Sheet Mode)
    $files = [];
    if (is_array($_FILES['sheet']['name'])) {
        $cnt = count($_FILES['sheet']['name']);
        for ($fi=0; $fi<$cnt; $fi++) {
            $files[] = [
                'name'     => $_FILES['sheet']['name'][$fi],
                'type'     => $_FILES['sheet']['type'][$fi],
                'tmp_name' => $_FILES['sheet']['tmp_name'][$fi],
                'error'    => $_FILES['sheet']['error'][$fi],
                'size'     => $_FILES['sheet']['size'][$fi],
            ];
        }
    } else {
        $files[] = $_FILES['sheet'];
    }

    if (!is_dir(UPLOAD_DIR.'/exams')) @mkdir(UPLOAD_DIR.'/exams', 0775, true);

    // استخراج صفحات قبلی
    $stE = db()->prepare('SELECT sheet_path, sheet_paths_json FROM exams WHERE id=?');
    $stE->execute([$examId]);
    $exObj = $stE->fetch();
    $existingArr = $exObj['sheet_paths_json'] ? (json_decode($exObj['sheet_paths_json'], true) ?: []) : [];
    if ($exObj['sheet_path'] && !in_array($exObj['sheet_path'], $existingArr, true)) {
        array_unshift($existingArr, $exObj['sheet_path']);
    }

    $newUrls = [];
    foreach ($files as $f) {
        if ($f['error'] !== UPLOAD_ERR_OK || empty($f['tmp_name'])) continue;
        if ($f['size'] > MAX_UPLOAD * 4) json_out(['ok'=>false,'error'=>'حجم یکی از عکس‌ها بیش از حد مجاز است'], 422);
        $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['jpg','jpeg','png','webp'], true)) json_out(['ok'=>false,'error'=>'فرمت عکس مجاز نیست (فقط JPG, PNG, WEBP)'], 422);

        $name = 'sheet_'.$examId.'_'.bin2hex(random_bytes(5)).'.'.$ext;
        $dest = UPLOAD_DIR.'/exams/'.$name;
        if (!move_uploaded_file($f['tmp_name'], $dest)) json_out(['ok'=>false,'error'=>'خطا در ذخیره عکس صفحه'], 500);
        $rel = 'uploads/exams/'.$name;
        $existingArr[] = $rel;
        $newUrls[] = url($rel);
    }
    
    $existingArr = array_values(array_unique($existingArr));
    $firstPath   = $existingArr[0] ?? null;
    $jsonArrStr  = json_encode($existingArr, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    db()->prepare('UPDATE exams SET sheet_path=?, sheet_paths_json=? WHERE id=?')->execute([$firstPath, $jsonArrStr, $examId]);
    json_out(['ok'=>true, 'url'=>url((string)$firstPath), 'rel'=>$firstPath, 'sheets'=>$existingArr, 'urls'=>$newUrls]);
}

case 'remove_sheet_item': {
    $examId = (int)($in['exam_id'] ?? 0);
    $pathToRemove = trim((string)($in['sheet_path'] ?? ''));
    if (!own_exam($examId, $me, $u['role'])) json_out(['ok'=>false,'error'=>'یافت نشد'], 404);

    $stE = db()->prepare('SELECT sheet_path, sheet_paths_json FROM exams WHERE id=?');
    $stE->execute([$examId]);
    $exObj = $stE->fetch();
    $existingArr = $exObj['sheet_paths_json'] ? (json_decode($exObj['sheet_paths_json'], true) ?: []) : [];
    
    // حذف آیتم
    $existingArr = array_values(array_filter($existingArr, fn($p) => $p !== $pathToRemove));
    if ($pathToRemove) @unlink(__DIR__.'/../'.$pathToRemove);

    $firstPath  = $existingArr[0] ?? null;
    $jsonArrStr = $existingArr ? json_encode($existingArr, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null;

    db()->prepare('UPDATE exams SET sheet_path=?, sheet_paths_json=? WHERE id=?')->execute([$firstPath, $jsonArrStr, $examId]);
    json_out(['ok'=>true, 'sheets'=>$existingArr, 'first'=>$firstPath]);
}

case 'quick_sheet_generate': {
    $examId = (int)($in['exam_id'] ?? 0);
    $e = own_exam($examId, $me, $u['role']);
    if (!$e) json_out(['ok'=>false,'error'=>'آزمون یافت نشد'], 404);
    
    $sheetPath = trim((string)($in['sheet_path'] ?? ''));
    $answerKey = trim((string)($in['answer_key'] ?? ''));
    
    $cleanKey = preg_replace('/[^1-4]/', '', $answerKey);
    $qCount = mb_strlen($cleanKey);
    if ($qCount === 0) json_out(['ok'=>false, 'error'=>'کلید پاسخنامه معتبر نیست (باید شامل اعداد ۱ تا ۴ باشد)'], 422);

    db()->beginTransaction();
    try {
        db()->prepare('UPDATE exams SET creation_mode="quick_sheet", answer_key=? WHERE id=?')->execute([$answerKey, $examId]);
        if ($sheetPath && !$e['sheet_path']) {
            db()->prepare('UPDATE exams SET sheet_path=? WHERE id=?')->execute([$sheetPath, $examId]);
        }
        
        db()->prepare('DELETE FROM exam_sections WHERE exam_id=?')->execute([$examId]);
        
        db()->prepare('INSERT INTO exam_sections (exam_id, name, sort_order) VALUES (?, "سوالات دفترچه کنکور", 1)')->execute([$examId]);
        $secId = (int)db()->lastInsertId();
        
        $ins = db()->prepare('INSERT INTO exam_questions (exam_id, section_id, q_text, q_image, correct_opt, sort_order) VALUES (?, ?, ?, ?, ?, ?)');
        for ($i = 0; $i < $qCount; $i++) {
            $cor = (int)$cleanKey[$i];
            $ins->execute([$examId, $secId, 'سوال ' . fa_num($i + 1), $sheetPath ?: null, $cor, $i + 1]);
        }
        db()->commit();
    } catch (Throwable $ex) {
        db()->rollBack();
        throw $ex;
    }
    json_out(['ok'=>true, 'q_count'=>$qCount]);
}

case 'ai_bulk_generate': {
    $examId = (int)($in['exam_id'] ?? 0);
    $e = own_exam($examId, $me, $u['role']);
    if (!$e) json_out(['ok'=>false, 'error'=>'آزمون یافت نشد'], 404);

    $bulkText = trim((string)($in['bulk_text'] ?? ''));
    if (!$bulkText) json_out(['ok'=>false, 'error'=>'متن سوالات ارسال نشده است'], 422);

    // پارس کردن سوالات از متن
    $questions = [];
    $rawQuestions = preg_split('/(?=\n\s*\d+[\.\-\)]|\n\s*سوال\s*\d+)/u', $bulkText);
    
    foreach ($rawQuestions as $rq) {
        $rq = trim($rq);
        if (!$rq) continue;
        
        $lines = array_values(array_filter(array_map('trim', explode("\n", $rq))));
        if (count($lines) < 2) continue;

        $qText = $lines[0];
        $o1 = ''; $o2 = ''; $o3 = ''; $o4 = '';
        $cor = 1;

        $rest = implode('  ', array_slice($lines, 1));
        
        $opts = preg_split('/(?:\s+|\b)(?:1|2|3|4|الف|ب|ج|د)[\)\.\-]/u', $rest);
        $opts = array_values(array_filter(array_map('trim', $opts)));

        if (isset($opts[0])) $o1 = $opts[0];
        if (isset($opts[1])) $o2 = $opts[1];
        if (isset($opts[2])) $o3 = $opts[2];
        if (isset($opts[3])) $o4 = $opts[3];

        if (str_contains($o1, '*') || str_contains($o1, '#')) { $cor = 1; $o1 = str_replace(['*','#'], '', $o1); }
        if (str_contains($o2, '*') || str_contains($o2, '#')) { $cor = 2; $o2 = str_replace(['*','#'], '', $o2); }
        if (str_contains($o3, '*') || str_contains($o3, '#')) { $cor = 3; $o3 = str_replace(['*','#'], '', $o3); }
        if (str_contains($o4, '*') || str_contains($o4, '#')) { $cor = 4; $o4 = str_replace(['*','#'], '', $o4); }

        $questions[] = [
            'qText' => $qText, 'o1' => trim($o1), 'o2' => trim($o2), 'o3' => trim($o3), 'o4' => trim($o4), 'cor' => $cor
        ];
    }

    if (!$questions) json_out(['ok'=>false, 'error'=>'هیچ سوال استانداردی در متن یافت نشد. لطفاً ساختار شماره‌گذاری را بررسی کنید.'], 422);

    db()->beginTransaction();
    try {
        db()->prepare('UPDATE exams SET creation_mode="ai_bulk" WHERE id=?')->execute([$examId]);
        db()->prepare('DELETE FROM exam_sections WHERE exam_id=?')->execute([$examId]);
        
        db()->prepare('INSERT INTO exam_sections (exam_id, name, sort_order) VALUES (?, "سوالات پردازش‌شده", 1)')->execute([$examId]);
        $secId = (int)db()->lastInsertId();

        $ins = db()->prepare('INSERT INTO exam_questions (exam_id, section_id, q_text, opt1, opt2, opt3, opt4, correct_opt, sort_order) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
        foreach ($questions as $idx => $q) {
            $ins->execute([$examId, $secId, $q['qText'], $q['o1'], $q['o2'], $q['o3'], $q['o4'], $q['cor'], $idx + 1]);
        }
        db()->commit();
    } catch (Throwable $ex) {
        db()->rollBack();
        throw $ex;
    }
    json_out(['ok'=>true, 'q_count'=>count($questions)]);
}

default: json_out(['ok'=>false,'error'=>'عملیات نامعتبر'],400);
}
} catch (Throwable $e) {
    json_out(['ok'=>false,'error'=> APP_ENV==='development' ? $e->getMessage() : 'خطای سرور'],500);
}
