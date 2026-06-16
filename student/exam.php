<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/models.php';
require_once __DIR__ . '/../includes/layout.php';
boot_session();
require_role('student');
$u = current_user();

$examId = (int)($_GET['id'] ?? 0);
$exam = get_exam($examId);
if (!$exam || $exam['status'] !== 'published') { flash('error','آزمون در دسترس نیست'); redirect('student/exams.php'); }
$now = time();
if ($exam['start_at'] && strtotime($exam['start_at']) > $now) { flash('error','آزمون هنوز شروع نشده'); redirect('student/exams.php'); }
if ($exam['end_at'] && strtotime($exam['end_at']) < $now) { flash('error','مهلت آزمون تمام شده'); redirect('student/exams.php'); }

$attempt = get_or_create_attempt($examId, (int)$u['id']);
if ($attempt['status'] === 'submitted') redirect('student/exam_result.php?attempt='.$attempt['id']);

$sections = exam_sections($examId);
$questions = exam_questions($examId);
$answers = attempt_answers((int)$attempt['id']);

// گروه‌بندی سوالات بر اساس بخش، با شماره‌گذاری سراسری
$qBySection = [];
$globalNum = 0;
$flatQ = []; // لیست مسطح برای ناوبری
foreach ($sections as $sec) {
    foreach ($questions as $q) {
        if ((int)$q['section_id'] !== (int)$sec['id']) continue;
        $globalNum++;
        $q['gnum'] = $globalNum;
        $qBySection[(int)$sec['id']][] = $q;
        $flatQ[] = $q;
    }
}
$totalQ = count($flatQ);
$remain = $attempt['deadline_at'] ? max(0, strtotime($attempt['deadline_at']) - $now) : null;

// داده‌ی اولیه برای JS
$initAnswers = [];
foreach ($answers as $qid=>$a) {
    $initAnswers[$qid] = ['s'=>$a['selected_opt']!==null?(int)$a['selected_opt']:null,'f'=>(int)$a['flagged']];
}

page_head('آزمون: ' . $exam['title'], '', ['exam.css']);
?>
<div class="exam-env" id="examEnv" data-attempt="<?= (int)$attempt['id'] ?>" data-total="<?= $totalQ ?>" data-review="<?= 0 ?>">

  <!-- ===== top bar ===== -->
  <header class="exam-bar">
    <div class="exam-bar-info">
      <div class="eb-title"><?= e($exam['title']) ?></div>
      <div class="eb-sub"><span id="answeredCount">۰</span>/<?= fa_num($totalQ) ?> پاسخ‌داده</div>
    </div>
    <?php if($remain !== null): ?>
    <div class="exam-timer" id="examTimer" data-remain="<?= $remain ?>"><?= icon('clock',18) ?> <span id="timerText">--:--</span></div>
    <?php endif; ?>
    <button class="btn btn-gold btn-sm" id="finishBtn"><?= icon('check',16) ?> پایان آزمون</button>
  </header>

  <div class="exam-main">
    <!-- ===== question area ===== -->
    <div class="exam-questions" id="examQuestions">
      <?php $i=0; foreach ($flatQ as $q): $sel = $initAnswers[$q['id']]['s'] ?? null; $fl = $initAnswers[$q['id']]['f'] ?? 0;
        // نام بخش
        $secName=''; foreach($sections as $s){ if((int)$s['id']===(int)$q['section_id']){ $secName=$s['name']; break; } }
      ?>
      <div class="exam-q <?= $i===0?'active':'' ?>" data-q="<?= (int)$q['id'] ?>" data-index="<?= $i ?>" data-section="<?= (int)$q['section_id'] ?>">
        <div class="eq-head">
          <span class="eq-sec"><?= e($secName) ?></span>
          <span class="eq-num">سوال <?= fa_num($q['gnum']) ?> از <?= fa_num($totalQ) ?></span>
          <button class="eq-flag <?= $fl?'on':'' ?>" data-flag data-tip="علامت برای مرور"><?= icon('flag',16) ?></button>
        </div>
        <?php if($q['q_image']):?><div class="eq-image"><img src="<?= url($q['q_image']) ?>" alt="" loading="lazy"></div><?php endif;?>
        <div class="eq-text"><?= nl2br(e($q['q_text'] ?: '—')) ?></div>
        <div class="eq-options">
          <?php for($o=1;$o<=4;$o++): if($q['opt'.$o]===null || $q['opt'.$o]==='') continue; ?>
          <button class="eq-opt <?= $sel===$o?'selected':'' ?>" data-opt="<?= $o ?>">
            <span class="eqo-marker"><?= fa_num($o) ?></span>
            <span class="eqo-text"><?= e($q['opt'.$o]) ?></span>
          </button>
          <?php endfor; ?>
        </div>
        <button class="eq-clear <?= $sel?'':'hidden' ?>" data-clear><?= icon('close',14) ?> پاک‌کردن پاسخ</button>
      </div>
      <?php $i++; endforeach; ?>
    </div>

    <!-- ===== nav buttons ===== -->
    <div class="exam-nav">
      <button class="btn btn-ghost" id="prevBtn"><?= icon('chevron-right',18) ?> قبلی</button>
      <button class="btn btn-ghost btn-icon" id="gridToggle" data-tip="فهرست سوالات"><?= icon('grid',18) ?></button>
      <button class="btn btn-gold" id="nextBtn">بعدی <?= icon('chevron-left',18) ?></button>
    </div>
  </div>

  <!-- ===== question grid (drawer) ===== -->
  <div class="qgrid-overlay" id="qgridOverlay"></div>
  <aside class="qgrid-panel" id="qgridPanel">
    <div class="qgrid-head"><h3><?= icon('grid',18) ?> فهرست سوالات</h3><button class="modal-close" id="gridClose"><?= icon('close',18) ?></button></div>
    <div class="qgrid-legend">
      <span><span class="lg answered"></span> پاسخ‌داده</span>
      <span><span class="lg flagged"></span> علامت‌دار</span>
      <span><span class="lg blank"></span> بی‌پاسخ</span>
    </div>
    <div class="qgrid-scroll">
      <?php foreach ($sections as $sec): if(empty($qBySection[(int)$sec['id']])) continue; ?>
      <div class="qgrid-sec-title"><?= e($sec['name']) ?></div>
      <div class="qgrid">
        <?php foreach ($qBySection[(int)$sec['id']] as $q): $sel=$initAnswers[$q['id']]['s']??null; $fl=$initAnswers[$q['id']]['f']??0; ?>
        <button class="qg-cell <?= $sel?'answered':'' ?> <?= $fl?'flagged':'' ?>" data-goto="<?= $q['gnum']-1 ?>" data-q="<?= (int)$q['id'] ?>"><?= fa_num($q['gnum']) ?></button>
        <?php endforeach; ?>
      </div>
      <?php endforeach; ?>
    </div>
    <button class="btn btn-gold btn-block" id="finishBtn2" style="margin-top:14px"><?= icon('check',16) ?> پایان و ثبت آزمون</button>
  </aside>
</div>

<!-- ===== confirm submit modal ===== -->
<div class="modal-backdrop" id="submitModal">
  <div class="modal" style="max-width:420px">
    <div class="modal-head"><h3><?= icon('check-circle',20) ?> پایان آزمون</h3><button class="modal-close" data-close><?= icon('close',18) ?></button></div>
    <p class="muted" style="margin-bottom:14px">آیا از ثبت نهایی آزمون مطمئنی؟ پس از ثبت، امکان تغییر پاسخ‌ها نیست.</p>
    <div class="submit-summary">
      <div class="ss-item"><span class="v" id="ssAnswered">۰</span><span class="k">پاسخ‌داده</span></div>
      <div class="ss-item"><span class="v" id="ssBlank">۰</span><span class="k">بی‌پاسخ</span></div>
      <div class="ss-item"><span class="v" id="ssFlagged">۰</span><span class="k">علامت‌دار</span></div>
    </div>
    <div class="flex gap-3 mt-4">
      <button class="btn btn-gold" style="flex:1" id="confirmSubmit"><?= icon('check',16) ?> ثبت نهایی</button>
      <button class="btn btn-ghost" data-close>بازگشت</button>
    </div>
  </div>
</div>

<script>
  window.API_EXAM_TAKE = '<?= url('api/exam_take.php') ?>';
  window.EXAM_INIT = <?= json_encode($initAnswers, JSON_UNESCAPED_UNICODE) ?: '{}' ?>;
</script>
<?php page_foot(['exam_take.js']); ?>
