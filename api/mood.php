<?php
require_once __DIR__ . '/../includes/auth.php';
boot_session();
require_role('student');
require_csrf();
$u = current_user();
$mood = (string)(input('mood') ?: (body_json()['mood'] ?? ''));
$allowed = ['happy','ok','meh','tired','stressed'];
if (!in_array($mood, $allowed, true)) json_out(['ok'=>false,'error'=>'مقدار نامعتبر'],422);
db()->prepare('UPDATE users SET mood=? WHERE id=?')->execute([$mood, $u['id']]);
json_out(['ok'=>true]);
