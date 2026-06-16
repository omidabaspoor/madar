<?php
/** API پیام‌ها: list, send, contacts */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/models.php';
boot_session();
require_login();
$u = current_user();
$me = (int)$u['id'];
$action = (string)(input('action') ?: (body_json()['action'] ?? 'list'));
$in = array_merge($_GET, $_POST, body_json());

/** بررسی مجاز بودن گفتگو (مشاور↔دانش‌آموزِ خودش) */
function can_chat(array $u, int $other): bool {
    $o = get_user($other);
    if (!$o) return false;
    if (in_array($u['role'], ['advisor','admin'], true)) return $o['role'] === 'student';
    // دانش‌آموز فقط با مشاور خودش یا هر مشاور/ادمین
    return in_array($o['role'], ['advisor','admin'], true);
}

try {
switch ($action) {

case 'contacts': {
    if (in_array($u['role'], ['advisor','admin'], true)) {
        $rows = db()->query('SELECT id, full_name, field, status FROM users WHERE role="student" AND status="active" ORDER BY full_name')->fetchAll();
    } else {
        $rows = db()->query('SELECT id, full_name, field, status FROM users WHERE role IN ("advisor","admin") ORDER BY id')->fetchAll();
    }
    // آخرین پیام + تعداد نخوانده
    foreach ($rows as &$r) {
        $last = db()->prepare('SELECT body, created_at FROM messages WHERE (sender_id=? AND receiver_id=?) OR (sender_id=? AND receiver_id=?) ORDER BY created_at DESC LIMIT 1');
        $last->execute([$me,$r['id'],$r['id'],$me]);
        $lm = $last->fetch();
        $r['last'] = $lm['body'] ?? '';
        $r['last_ago'] = $lm ? time_ago($lm['created_at']) : '';
        $unr = db()->prepare('SELECT COUNT(*) FROM messages WHERE sender_id=? AND receiver_id=? AND is_read=0');
        $unr->execute([$r['id'],$me]);
        $r['unread'] = (int)$unr->fetchColumn();
    }
    json_out(['ok'=>true,'items'=>$rows]);
}

case 'list': {
    $other = (int)($in['with'] ?? 0);
    if (!can_chat($u,$other)) json_out(['ok'=>false,'error'=>'دسترسی ندارید'],403);
    $msgs = conversation($me,$other);
    // علامت‌گذاری خوانده‌شده
    db()->prepare('UPDATE messages SET is_read=1 WHERE sender_id=? AND receiver_id=?')->execute([$other,$me]);
    $out = array_map(fn($m)=>[
        'id'=>(int)$m['id'],'mine'=>(int)$m['sender_id']===$me,
        'body'=>$m['body'],'time'=>fa_num(date('H:i',strtotime($m['created_at']))),'date'=>jalali_date($m['created_at']),
    ], $msgs);
    json_out(['ok'=>true,'items'=>$out]);
}

case 'send': {
    require_csrf();
    $other = (int)($in['with'] ?? 0);
    $body = trim((string)($in['body'] ?? ''));
    if (!can_chat($u,$other)) json_out(['ok'=>false,'error'=>'دسترسی ندارید'],403);
    if ($body==='') json_out(['ok'=>false,'error'=>'پیام خالی است'],422);
    if (mb_strlen($body) > 2000) $body = mb_substr($body,0,2000);
    db()->prepare('INSERT INTO messages (sender_id,receiver_id,body) VALUES (?,?,?)')->execute([$me,$other,$body]);
    notify($other, 'پیام جدید 💬', mb_substr($body,0,60), 'message', $u['role']==='student'?'admin/messages.php?with='.$me:'student/messages.php');
    json_out(['ok'=>true,'time'=>fa_num(date('H:i'))]);
}

default: json_out(['ok'=>false,'error'=>'عملیات نامعتبر'],400);
}
} catch (Throwable $e) {
    json_out(['ok'=>false,'error'=> APP_ENV==='development' ? $e->getMessage() : 'خطای سرور'],500);
}
