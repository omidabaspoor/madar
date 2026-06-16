<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/models.php';
require_once __DIR__ . '/../includes/panel_layout.php';
require_once __DIR__ . '/../includes/result_view.php';
boot_session();
require_role('student');
$u = current_user();

$attemptId = (int)($_GET['attempt'] ?? 0);
$rep = attempt_report($attemptId);
if (!$rep || (int)$rep['attempt']['student_id'] !== (int)$u['id']) { flash('error','کارنامه یافت نشد'); redirect('student/exams.php'); }
if ($rep['attempt']['status'] !== 'submitted') redirect('student/exam.php?id='.$rep['attempt']['exam_id']);

$showAnswers = (int)$rep['exam']['show_review'] === 1;

panel_start('کارنامه آزمون', $rep['exam']['title'], 'student', 'exams', ['student.css','exam.css','result.css']);
?>
<div class="mb-4"><a href="<?= url('student/exams.php') ?>" class="btn btn-ghost btn-sm"><?= icon('arrow-right',16) ?> بازگشت به آزمون‌ها</a></div>
<?php render_result($rep, $showAnswers); ?>
<?php if(!$showAnswers): ?>
<div class="panel" style="margin-top:18px"><div class="empty-state"><div class="es-ico"><?= icon('lock',28) ?></div>پاسخنامه‌ی این آزمون توسط مشاور غیرفعال شده است.</div></div>
<?php endif; ?>
<?php panel_end(); ?>
