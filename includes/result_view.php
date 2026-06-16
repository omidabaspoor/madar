<?php
/** نمای کارنامه + پاسخنامه (مشترک دانش‌آموز/مشاور) */
declare(strict_types=1);

function render_result(array $rep, bool $showAnswers = true): void
{
    $att = $rep['attempt']; $exam = $rep['exam']; $sections = $rep['sections']; $questions = $rep['questions'];
    $total = (int)$att['correct_count'] + (int)$att['wrong_count'] + (int)$att['blank_count'];
    $pct = round((float)$att['total_score']);
    ?>
<!-- score hero -->
<div class="panel result-hero reveal in">
  <div class="rh-ring">
    <div class="ring" data-p="<?= max(0,$pct) ?>" style="--p:0;--size:120px"><span style="font-size:1.4rem"><?= fa_num($pct) ?>٪</span></div>
  </div>
  <div class="rh-info">
    <h2 style="font-size:1.3rem"><?= e($exam['title']) ?></h2>
    <p class="muted" style="font-size:.85rem"><?= e($att['full_name']) ?> · <?= jalali_date($att['submitted_at'],true) ?></p>
    <div class="rh-stats">
      <span class="rh-stat ok"><?= icon('check',14) ?> <?= fa_num($att['correct_count']) ?> درست</span>
      <span class="rh-stat bad"><?= icon('close',14) ?> <?= fa_num($att['wrong_count']) ?> غلط</span>
      <span class="rh-stat blank"><?= icon('info',14) ?> <?= fa_num($att['blank_count']) ?> نزده</span>
    </div>
  </div>
</div>

<!-- per-section -->
<div class="panel reveal" data-d="1" style="margin-top:18px">
  <div class="panel-head"><h3><?= icon('pie',20) ?> درصد هر درس</h3></div>
  <?php foreach ($sections as $s): $p=(float)$s['percent']; $clr = $p>=50?'var(--success)':($p>=0?'var(--gold)':'var(--danger)'); ?>
  <div style="margin-bottom:14px">
    <div class="between" style="font-size:.88rem;margin-bottom:5px">
      <span class="fw-700"><?= e($s['name']) ?></span>
      <span class="flex gap-2" style="font-size:.78rem;color:var(--text-3)">
        <span style="color:var(--success)"><?= fa_num($s['correct']) ?>✓</span>
        <span style="color:var(--danger)"><?= fa_num($s['wrong']) ?>✗</span>
        <span><?= fa_num($s['blank']) ?>—</span>
        <span class="fw-800" style="color:<?= $clr ?>"><?= fa_num($p) ?>٪</span>
      </span>
    </div>
    <div class="progress"><span data-w="<?= max(0,$p) ?>" style="width:0;background:<?= $clr ?>"></span></div>
  </div>
  <?php endforeach; ?>
</div>

<?php if ($showAnswers): ?>
<!-- answer sheet -->
<div class="panel reveal" data-d="2" style="margin-top:18px">
  <div class="panel-head"><h3><?= icon('list',20) ?> پاسخنامه و تحلیل</h3>
    <div class="flex gap-2">
      <button class="chip active" data-filter="all">همه</button>
      <button class="chip" data-filter="wrong">غلط‌ها</button>
      <button class="chip" data-filter="blank">نزده‌ها</button>
    </div>
  </div>
  <div class="answer-sheet" id="answerSheet">
    <?php foreach ($questions as $item): $q=$item['q']; $sel=$item['selected']; $st=$item['state']; ?>
    <div class="ans-q <?= $st ?>" data-state="<?= $st ?>">
      <div class="ans-head">
        <span class="ans-num <?= $st ?>"><?= fa_num($item['gnum']) ?></span>
        <span class="ans-sec"><?= e($item['sec']) ?></span>
        <span class="ans-badge <?= $st ?>">
          <?= $st==='correct'?icon('check',13).' درست':($st==='wrong'?icon('close',13).' غلط':icon('info',13).' نزده') ?>
        </span>
      </div>
      <?php if($q['q_image']):?><div class="eq-image" style="margin:8px 0"><img src="<?= url($q['q_image']) ?>" alt="" loading="lazy" style="max-height:220px"></div><?php endif;?>
      <?php if($q['q_text']):?><div class="ans-text"><?= nl2br(e($q['q_text'])) ?></div><?php endif;?>
      <div class="ans-options">
        <?php for($o=1;$o<=4;$o++): if($q['opt'.$o]===null||$q['opt'.$o]==='') continue;
          $isCorrect=(int)$q['correct_opt']===$o; $isSel=$sel===$o;
          $cls=$isCorrect?'correct':($isSel?'wrong-sel':''); ?>
        <div class="ans-opt <?= $cls ?>">
          <span class="ao-marker"><?= fa_num($o) ?></span>
          <span class="ao-text"><?= e($q['opt'.$o]) ?></span>
          <?php if($isCorrect):?><span class="ao-tag ok"><?= icon('check',12) ?> پاسخ صحیح</span>
          <?php elseif($isSel):?><span class="ao-tag bad">پاسخ شما</span><?php endif;?>
        </div>
        <?php endfor; ?>
      </div>
      <?php if($q['explanation']):?>
      <details class="ans-exp"><summary><?= icon('sparkles',14) ?> پاسخ تشریحی</summary><div><?= nl2br(e($q['explanation'])) ?></div></details>
      <?php endif;?>
    </div>
    <?php endforeach; ?>
  </div>
</div>
<script>
  document.querySelectorAll('[data-filter]').forEach(b=>b.addEventListener('click',()=>{
    document.querySelectorAll('[data-filter]').forEach(x=>x.classList.remove('active'));
    b.classList.add('active');
    const f=b.dataset.filter;
    document.querySelectorAll('.ans-q').forEach(q=>{
      q.style.display=(f==='all'||q.dataset.state===f)?'':'none';
    });
  }));
</script>
<?php endif; ?>
<?php
}
