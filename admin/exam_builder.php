<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/models.php';
require_once __DIR__ . '/../includes/panel_layout.php';
boot_session();
require_role('advisor','admin');
$u = current_user();

$examId = (int)($_GET['id'] ?? 0);
$exam = $examId ? get_exam($examId) : null;
if ($exam && $exam['advisor_id'] != $u['id'] && $u['role']!=='admin') { flash('error','آزمون یافت نشد'); redirect('admin/exams.php'); }

$sections = $exam ? exam_sections($examId) : [];
$questions = $exam ? exam_questions($examId) : [];
$subjects = all_subjects();

$qBySection = [];
foreach ($questions as $q) $qBySection[(int)$q['section_id']][] = $q;

// مرحله‌ی شروع: اگر آزمون سوال دارد، مستقیم مرحله ۲
$startStep = ($exam && count($questions) > 0) ? 2 : 1;

panel_start($exam ? 'ویرایش آزمون' : 'آزمون جدید', '', 'admin', 'exams', ['builder.css','student.css']);
?>
<div class="exam-builder" id="examBuilder" data-exam="<?= $examId ?>" data-step="<?= $startStep ?>">

  <!-- ===== header: back + stepper + save status ===== -->
  <div class="between wrap gap-3 mb-4">
    <a href="<?= url('admin/exams.php') ?>" class="btn btn-ghost btn-sm"><?= icon('arrow-right',16) ?> آزمون‌ها</a>
    <div class="stepper">
      <button class="step" data-step-to="1"><span class="step-n">۱</span><span class="step-lbl">تنظیمات</span></button>
      <span class="step-line"></span>
      <button class="step" data-step-to="2"><span class="step-n">۲</span><span class="step-lbl">سوالات</span></button>
    </div>
    <span class="save-status saved" id="saveStatus"><?= icon('check-circle',16) ?> ذخیره خودکار</span>
  </div>

  <!-- =========================================================
       STEP 1 — settings
       ========================================================= -->
  <div class="builder-step" data-step="1">
    <div class="panel">
      <div class="step-intro"><span class="icon-tile"><?= icon('clipboard',22) ?></span>
        <div><h3 style="font-size:1.15rem">گام ۱ · تنظیمات آزمون</h3><p class="muted" style="font-size:.85rem">عنوان و نوع آزمون را مشخص کن. بقیه تنظیمات حالت پیش‌فرض دارند.</p></div>
      </div>

      <form id="metaForm">
        <div class="field"><label>عنوان آزمون *</label><input class="input input-lg" name="title" id="m_title" value="<?= e($exam['title'] ?? '') ?>" placeholder="مثلاً آزمون جامع شماره ۱"></div>

        <div class="field"><label>نوع آزمون</label>
          <div class="type-cards">
            <label class="type-card <?= ($exam['exam_type']??'single')==='single'?'active':'' ?>">
              <input type="radio" name="exam_type" value="single" <?= ($exam['exam_type']??'single')==='single'?'checked':'' ?>>
              <span class="tc-ico"><?= icon('book',22) ?></span>
              <span class="tc-title">تکی</span>
              <span class="tc-desc">فقط یک درس</span>
            </label>
            <label class="type-card <?= ($exam['exam_type']??'')==='comprehensive'?'active':'' ?>">
              <input type="radio" name="exam_type" value="comprehensive" <?= ($exam['exam_type']??'')==='comprehensive'?'checked':'' ?>>
              <span class="tc-ico"><?= icon('grid',22) ?></span>
              <span class="tc-title">جامع</span>
              <span class="tc-desc">چند درس پشت سر هم</span>
            </label>
          </div>
        </div>

        <div class="field"><label>توضیحات <span class="muted">(اختیاری)</span></label><input class="input" name="description" id="m_desc" value="<?= e($exam['description'] ?? '') ?>" placeholder="مثلاً ویژه‌ی فصل‌های اول"></div>

        <div class="grid gap-3" style="grid-template-columns:1fr 1fr">
          <div class="field"><label>نحوه‌ی زمان‌بندی</label>
            <select class="select" name="timing_mode" id="m_timing">
              <option value="total" <?= ($exam['timing_mode']??'')==='total'?'selected':'' ?>>یک زمان برای کل آزمون</option>
              <option value="per_section" <?= ($exam['timing_mode']??'')==='per_section'?'selected':'' ?>>زمان جداگانه برای هر درس</option>
            </select>
          </div>
          <div class="field" id="totalDurField"><label>زمان کل (دقیقه)</label><input class="input" type="number" min="1" name="duration_min" id="m_dur" value="<?= e($exam['duration_min'] ?? 60) ?>"></div>
        </div>

        <!-- advanced (collapsible) -->
        <details class="adv-settings">
          <summary><?= icon('settings',16) ?> تنظیمات پیشرفته (اختیاری)</summary>
          <div class="adv-body">
            <div class="field" style="max-width:320px"><label>بازه‌ی برگزاری <span class="muted">(اختیاری)</span></label>
              <input class="input" type="datetime-local" name="start_at" id="m_start" value="<?= $exam && $exam['start_at'] ? date('Y-m-d\TH:i', strtotime($exam['start_at'])) : '' ?>">
              <p class="help">اگر خالی باشد، آزمون بلافاصله در دسترس است.</p>
            </div>
            <label class="toggle-row"><span><b>نمره منفی کنکوری</b><br><span class="muted" style="font-size:.78rem">هر ۳ غلط، یک درست را خنثی می‌کند</span></span>
              <label class="switch"><input type="checkbox" name="negative_marking" id="m_neg" value="1" <?= ($exam['negative_marking']??1)?'checked':'' ?>><span class="slider"></span></label>
            </label>
            <label class="toggle-row"><span><b>نمایش پاسخنامه به دانش‌آموز</b><br><span class="muted" style="font-size:.78rem">بعد از پایان آزمون، پاسخ صحیح و تحلیل را ببیند</span></span>
              <label class="switch"><input type="checkbox" name="show_review" id="m_rev" value="1" <?= ($exam['show_review']??1)?'checked':'' ?>><span class="slider"></span></label>
            </label>
            <label class="toggle-row"><span><b>ترتیب تصادفی سوالات</b><br><span class="muted" style="font-size:.78rem">هر دانش‌آموز سوالات را با ترتیب متفاوت ببیند</span></span>
              <label class="switch"><input type="checkbox" name="shuffle_questions" id="m_shuf" value="1" <?= ($exam['shuffle_questions']??0)?'checked':'' ?>><span class="slider"></span></label>
            </label>
          </div>
        </details>
      </form>

      <div class="step-actions">
        <button class="btn btn-gold btn-lg" id="toStep2Btn">ادامه: افزودن سوالات <?= icon('arrow-left',18) ?></button>
      </div>
    </div>
  </div>

  <!-- =========================================================
       STEP 2 — questions
       ========================================================= -->
  <div class="builder-step <?= $startStep===2?'':'hidden' ?>" data-step="2">
    <div class="step-intro panel" style="padding:16px 18px;margin-bottom:16px">
      <span class="icon-tile sage"><?= icon('list',22) ?></span>
      <div style="flex:1">
        <h3 style="font-size:1.05rem"><?= icon('sparkles',16) ?> گام ۲ · افزودن سوالات</h3>
        <p class="muted" style="font-size:.83rem">برای هر درس یک «بخش» بساز، بعد سوال اضافه کن. <b>نکته:</b> بعد از نوشتن گزینه‌ها، با زدن Enter سوال بعدی خودکار ساخته می‌شود.</p>
      </div>
      <button class="btn btn-ghost btn-sm" id="backToStep1"><?= icon('settings',15) ?> تنظیمات</button>
    </div>

    <div class="between wrap gap-3 mb-4">
      <div class="exam-summary">
        <span class="ps-item"><span class="v" id="totalQ"><?= fa_num(count($questions)) ?></span><span class="k">سوال</span></span>
        <span class="ps-item"><span class="v" id="totalSec"><?= fa_num(count($sections)) ?></span><span class="k">درس</span></span>
      </div>
      <div class="flex gap-2 wrap">
        <button class="btn btn-ghost" id="addSectionBtn"><?= icon('plus',16) ?> افزودن درس</button>
        <button class="btn btn-sage" id="publishBtn" data-status="<?= e($exam['status'] ?? 'draft') ?>">
          <?= ($exam['status']??'')==='published' ? icon('edit',16).' بازگشت به پیش‌نویس' : icon('rocket',16).' انتشار آزمون' ?>
        </button>
      </div>
    </div>

    <div id="sectionsWrap">
      <?php foreach ($sections as $sec): ?>
      <?php require __DIR__ . '/_section_tpl.php'; ?>
      <?php endforeach; ?>
    </div>

    <?php if (!$sections): ?>
    <div class="panel" id="emptySections"><div class="empty-state"><div class="es-ico"><?= icon('book',30) ?></div><p>هنوز درسی اضافه نکرده‌اید</p><p class="muted" style="font-size:.84rem">با دکمه‌ی «افزودن درس» شروع کنید (مثلاً شیمی، ریاضی، ادبیات).</p>
      <button class="btn btn-gold mt-4" id="addSectionBtn2"><?= icon('plus',16) ?> افزودن اولین درس</button></div></div>
    <?php endif; ?>
  </div>
</div>

<script>
  window.API_EXAM = '<?= url('api/exam_builder.php') ?>';
  window.SUBJECTS = <?= json_encode(array_map(fn($s)=>['id'=>(int)$s['id'],'name'=>$s['name']], $subjects), JSON_UNESCAPED_UNICODE) ?>;
</script>
<?php panel_end(['exam_builder.js']); ?>
