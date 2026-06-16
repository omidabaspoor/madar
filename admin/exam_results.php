<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/models.php';
require_once __DIR__ . '/../includes/panel_layout.php';
require_once __DIR__ . '/../includes/result_view.php';
boot_session();
require_role('advisor','admin');
$u = current_user();

$examId = (int)($_GET['id'] ?? 0);
$exam = get_exam($examId);
if (!$exam || ($exam['advisor_id'] != $u['id'] && $u['role']!=='admin')) { flash('error','آزمون یافت نشد'); redirect('admin/exams.php'); }

// نمایش کارنامه‌ی یک دانش‌آموز خاص
$viewAttempt = (int)($_GET['attempt'] ?? 0);
if ($viewAttempt) {
    $rep = attempt_report($viewAttempt);
    if (!$rep || (int)$rep['attempt']['exam_id'] !== $examId) { flash('error','کارنامه یافت نشد'); redirect('admin/exam_results.php?id='.$examId); }
    panel_start('کارنامه دانش‌آموز', $rep['attempt']['full_name'].' · '.$exam['title'], 'admin', 'exams', ['student.css','exam.css','result.css']);
    echo '<div class="mb-4"><a href="'.url('admin/exam_results.php?id='.$examId).'" class="btn btn-ghost btn-sm">'.icon('arrow-right',16).' بازگشت به نتایج</a></div>';
    render_result($rep, true);
    panel_end();
    exit;
}

$results = exam_results($examId);
$qCount = exam_question_count($examId);
// میانگین
$avg = $results ? round(array_sum(array_map(fn($r)=>(float)$r['total_score'],$results))/count($results),1) : 0;

panel_start('نتایج آزمون', $exam['title'], 'admin', 'exams', ['student.css','result.css']);
?>
<div class="mb-4 flex gap-2 wrap" style="align-items:center">
  <a href="<?= url('admin/exams.php') ?>" class="btn btn-ghost btn-sm"><?= icon('arrow-right',16) ?> آزمون‌ها</a>
  <a href="<?= url('admin/exam_builder.php?id='.$examId) ?>" class="btn btn-ghost btn-sm"><?= icon('edit',15) ?> ویرایش آزمون</a>
</div>

<div class="stat-cards">
  <div class="panel stat reveal"><span class="icon-tile sage"><?= icon('users',24) ?></span><div><div class="v"><?= fa_num(count($results)) ?></div><div class="k">شرکت‌کننده</div></div></div>
  <div class="panel stat reveal" data-d="1"><span class="icon-tile"><?= icon('target',24) ?></span><div><div class="v"><?= fa_num($avg) ?>٪</div><div class="k">میانگین درصد</div></div></div>
  <div class="panel stat reveal" data-d="2"><span class="icon-tile sage"><?= icon('list',24) ?></span><div><div class="v"><?= fa_num($qCount) ?></div><div class="k">تعداد سوال</div></div></div>
  <div class="panel stat reveal" data-d="3"><span class="icon-tile" style="background:rgba(217,178,95,.14);color:var(--warn)"><?= icon('trophy',24) ?></span><div><div class="v"><?= $results?fa_num(round($results[0]['total_score'])).'٪':'—' ?></div><div class="k">بالاترین درصد</div></div></div>
</div>

<div class="panel reveal" data-d="2">
  <div class="panel-head"><h3><?= icon('trophy',20) ?> رتبه‌بندی</h3></div>
  <?php if(!$results): ?>
    <div class="empty-state"><div class="es-ico"><?= icon('users',30) ?></div>هنوز کسی این آزمون را نداده</div>
  <?php else: ?>
  <table class="tbl">
    <thead><tr><th>#</th><th>دانش‌آموز</th><th>درست</th><th>غلط</th><th>نزده</th><th>درصد</th><th></th></tr></thead>
    <tbody>
    <?php foreach($results as $i=>$r): ?>
      <tr>
        <td><span class="badge <?= $i<3?'badge-gold':'' ?>"><?= fa_num($i+1) ?></span></td>
        <td><div class="u-row"><span class="u-ava <?= $i==0?'gold':'' ?>"><?= e(avatar_letters($r['full_name'])) ?></span><span style="font-weight:700"><?= e($r['full_name']) ?></span></div></td>
        <td style="color:var(--success);font-weight:700"><?= fa_num($r['correct_count']) ?></td>
        <td style="color:var(--danger);font-weight:700"><?= fa_num($r['wrong_count']) ?></td>
        <td class="muted"><?= fa_num($r['blank_count']) ?></td>
        <td><span class="fw-800 gold"><?= fa_num(round($r['total_score'])) ?>٪</span></td>
        <td><a href="?id=<?= $examId ?>&attempt=<?= (int)$r['id'] ?>" class="btn btn-ghost btn-sm"><?= icon('eye',15) ?> کارنامه</a></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>
</div>
<?php panel_end(); ?>
