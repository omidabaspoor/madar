<?php
/** قالب یک بخش/درس + سوالاتش (در exam_builder استفاده می‌شود) */
$secQs = $qBySection[(int)$sec['id']] ?? [];
?>
<div class="exam-section" data-section="<?= (int)$sec['id'] ?>">
  <div class="section-head">
    <div class="flex gap-2" style="align-items:center;flex:1">
      <span class="sec-handle"><?= icon('book',18) ?></span>
      <input class="sec-name-input" data-sec-name value="<?= e($sec['name']) ?>" placeholder="نام درس (مثلاً شیمی)">
      <input class="sec-dur-input timing-section" data-sec-dur type="number" min="1" value="<?= e($sec['duration_min'] ?? '') ?>" placeholder="دقیقه">
    </div>
    <div class="flex gap-2">
      <span class="badge sec-count"><?= fa_num(count($secQs)) ?> سوال</span>
      <button class="btn btn-ghost btn-sm btn-icon" data-del-section data-tip="حذف درس" style="color:var(--danger)"><?= icon('trash',15) ?></button>
    </div>
  </div>

  <div class="questions-wrap">
    <?php foreach ($secQs as $idx=>$q): ?>
    <div class="q-card" data-question="<?= (int)$q['id'] ?>">
      <div class="q-top">
        <span class="q-num"><?= fa_num($idx+1) ?></span>
        <textarea class="q-text" data-q-text rows="1" placeholder="متن سوال را بنویسید…"><?= e($q['q_text']) ?></textarea>
        <div class="q-tools">
          <label class="q-img-btn" data-tip="افزودن عکس"><?= icon('paperclip',16) ?><input type="file" accept="image/*" data-q-img hidden></label>
          <button class="btn btn-ghost btn-sm btn-icon" data-del-question data-tip="حذف سوال" style="color:var(--danger)"><?= icon('trash',15) ?></button>
        </div>
      </div>
      <div class="q-img-preview <?= $q['q_image']?'':'hidden' ?>" data-q-img-preview>
        <?php if($q['q_image']):?><img src="<?= url($q['q_image']) ?>" alt=""><?php endif;?>
        <button class="q-img-remove" data-q-img-remove><?= icon('close',14) ?></button>
      </div>
      <div class="q-options">
        <?php for($o=1;$o<=4;$o++): ?>
        <label class="q-opt <?= (int)$q['correct_opt']===$o?'correct':'' ?>" data-opt="<?= $o ?>">
          <input type="radio" name="correct_<?= (int)$q['id'] ?>" value="<?= $o ?>" data-correct <?= (int)$q['correct_opt']===$o?'checked':'' ?>>
          <span class="opt-marker"><?= fa_num($o) ?></span>
          <input class="opt-input" data-opt-text="<?= $o ?>" value="<?= e($q['opt'.$o]) ?>" placeholder="گزینه <?= fa_num($o) ?>">
          <span class="opt-correct-badge"><?= icon('check',13) ?></span>
        </label>
        <?php endfor; ?>
      </div>
      <details class="q-exp">
        <summary><?= icon('note',14) ?> پاسخ تشریحی (اختیاری)</summary>
        <textarea class="input" data-q-exp rows="2" placeholder="توضیح پاسخ صحیح…"><?= e($q['explanation']) ?></textarea>
      </details>
    </div>
    <?php endforeach; ?>
  </div>

  <button class="add-q-btn" data-add-question><?= icon('plus',16) ?> افزودن سوال به این درس</button>
</div>
