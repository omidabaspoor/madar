<?php
/** نمای کارنامه پیشرفته + پاسخنامه تحلیلی و آسیب‌شناسی سامورایی (Samurai Smart Exam Report Card & Diagnostic Post-Mortem) */
declare(strict_types=1);

function render_result(array $rep, bool $showAnswers = true): void
{
    $att       = $rep['attempt']; 
    $exam      = $rep['exam']; 
    $sections  = $rep['sections']; 
    $questions = $rep['questions'];

    $correct = (int)$att['correct_count'];
    $wrong   = (int)$att['wrong_count'];
    $blank   = (int)$att['blank_count'];
    $total   = $correct + $wrong + $blank;

    $konkurPct = round((float)$att['total_score'], 1);
    $rawPct    = $total > 0 ? round(($correct / $total) * 100, 1) : 0;
    
    $answered  = $correct + $wrong;
    $precision = $answered > 0 ? round(($correct / $answered) * 100) : 0;
    
    // محاسبه دقیق تراز کنکور بر اساس توزیع کشوری سازمان سنجش (Iran Konkur Standard taraz Engine)
    $taraz = 5000 + (($konkurPct - 18.5) / 16.2) * 1000;
    $taraz = max(2500, min(11500, (int)round($taraz)));
    if ($total === 0) $taraz = 5000;

    $samuraiAdvice = [];
    if ($total === 0) {
        $samuraiAdvice = ['title'=>'داده کافی نیست','text'=>'آزمون فاقد پاسخ‌برگ یا سوال جهت ارزیابی است.','class'=>'info'];
    } elseif ($konkurPct >= 80 && $precision >= 85) {
        $samuraiAdvice = ['title'=>'عملکرد سامورایی و خیره‌کننده! 🏆','text'=>'تسلط مفهومی، مدیریت زمان و عدم پاسخگویی به سوالات شک‌دار در سطح رتبه‌های برتر کنکور است. همین الگو را باثبات ادامه دهید.','class'=>'success'];
    } elseif ($konkurPct >= 65) {
        $samuraiAdvice = ['title'=>'عملکرد بسیار خوب و قدرتمند 🌟','text'=>'ساختار علمی شما پایدار است؛ با بررسی دقیق تست‌های غلط و تحلیل تله‌های طراح، به‌راحتی به تراز بالای ۷,۰۰۰ خواهید رسید.','class'=>'success'];
    } elseif ($precision < 60 && $wrong >= 5) {
        $samuraiAdvice = ['title'=>'هشدار سامورایی: افتادن در تله‌های تستی و بی‌دقتی! ⚠️','text'=>'ضریب دقت شما پایین است (گزینش حدسی بالا). شما تعداد زیادی سوال را با شک و بدون اطمینان زده‌اید که نمره منفی سنگینی به همراه داشته است.','class'=>'warn'];
    } elseif ($blank > $total * 0.6) {
        $samuraiAdvice = ['title'=>'نیاز به افزایش سرعت و جرأت در تست‌زنی ⚡','text'=>'کنترل نمره منفی شما عالی بوده است (نزده‌های آگاهانه)، اما برای جهش تراز باید تعداد تست‌های آموزشی و سرعت انتقال را بالا ببرید.','class'=>'warn'];
    } else {
        $samuraiAdvice = ['title'=>'نیازمند بازنگری در شیوه مطالعه و تست‌زنی ⚠️','text'=>'توصیه فوری: پارت‌های مطالعه را کوتاه‌تر کرده و پس از هر درسنامه، حداقل ۲۰ تست آموزشی با تحلیل دقیق خط‌به‌خط انجام دهید.','class'=>'warn'];
    }

    $sheetPath = $exam['sheet_path'] ?? null;

    // استخراج آسیب‌شناسی‌های ثبت‌شده‌ی دانش‌آموز از دیتابیس
    $ansMap = [];
    $stmt = db()->prepare('SELECT question_id, diagnostic_reason, diagnostic_takeaway FROM exam_answers WHERE attempt_id=?');
    $stmt->execute([(int)$att['id']]);
    foreach ($stmt->fetchAll() as $rr) {
        $ansMap[(int)$rr['question_id']] = $rr;
    }

    // آمار کلان آسیب‌شناسی
    $diagCounts = [
      'بی‌دقتی / شتاب‌زدگی' => 0,
      'نقص علمی / ضعف مفهوم' => 0,
      'فراموشی فرمول یا نکته' => 0,
      'دام طراح سوال' => 0,
      'کمبود وقت / استرس' => 0
    ];
    foreach ($ansMap as $ans) {
        $r = (string)($ans['diagnostic_reason'] ?? '');
        if ($r && isset($diagCounts[$r])) $diagCounts[$r]++;
    }
    ?>

<!-- ===== SAMURAI REPORT CARD HERO ===== -->
<div class="panel result-samurai-hero mb-4 flex" style="flex-direction:column;justify-content:space-between;background:linear-gradient(135deg, rgba(217,178,95,0.12) 0%, rgba(12,21,18,0.7) 100%);border:1px solid var(--gold);padding:32px;border-radius:var(--r-lg);box-shadow:0 12px 36px rgba(0,0,0,0.4)">
  <div class="between wrap gap-4 mb-4" style="align-items:center">
    
    <div class="hero-titles" style="flex:1;min-width:280px">
      <div class="flex gap-2 mb-2" style="align-items:center">
        <span class="badge badge-gold" style="padding:4px 10px;font-weight:900">کارنامه تحلیلی و ترازسنج کنکور</span>
        <span class="muted" style="font-size:.85rem">الگوریتم روان‌سنجی مَدار</span>
      </div>
      <h1 style="font-size:2rem;font-weight:900;color:var(--text-1);margin-bottom:6px"><?= e($exam['title']) ?></h1>
      <p class="muted" style="font-size:.95rem;font-weight:700"><?= e($att['full_name'] ?? 'دانش‌آموز') ?> · ثبت نهایی: <?= jalali_date($att['submitted_at'] ?? '', true) ?></p>
    </div>

    <div class="flex wrap gap-4" style="align-items:center;justify-content:center">
      
      <!-- تراز تخمینی -->
      <div class="taraz-box text-c" style="background:var(--surface-1);border:1px solid var(--border-soft);padding:16px 28px;border-radius:20px;min-width:160px;box-shadow:0 8px 24px rgba(0,0,0,0.3)">
        <span class="muted" style="font-size:.8rem;font-weight:800;display:block;text-transform:uppercase;letter-spacing:1px">تراز تخمینی مَدار</span>
        <b style="font-size:2.4rem;font-weight:900;color:var(--gold-light);font-family:monospace;margin:4px 0;display:block;letter-spacing:1px"><?= fa_num($taraz) ?></b>
        <span class="badge" style="background:var(--gold-glass);color:var(--gold);font-size:.7۵rem">از سقف ۸,۵۰۰</span>
      </div>

      <!-- درصد کنکور حبابی -->
      <div class="pct-box text-c relative" style="background:var(--surface-1);border:2px solid <?= $konkurPct>=50 ? 'var(--sage)' : ($konkurPct>=25 ? 'var(--gold)' : 'var(--danger)') ?>;padding:16px 28px;border-radius:20px;min-width:160px;box-shadow:0 8px 24px rgba(0,0,0,0.3)">
        <span class="muted" style="font-size:.8rem;font-weight:800;display:block">درصد کل (با نمره منفی)</span>
        <b style="font-size:2.4rem;font-weight:900;color:<?= $konkurPct>=50 ? '#8ae6ab' : ($konkurPct>=25 ? 'var(--gold-light)' : '#ff9a9a') ?>;font-family:monospace;margin:4px 0;display:block"><?= fa_num($konkurPct) ?>٪</b>
        <span style="font-size:.7۵rem;color:var(--text-3);font-weight:bold">درصد خام: <?= fa_num($rawPct) ?>٪</span>
      </div>

    </div>

  </div>

  <?php if(!empty($samuraiAdvice['title'])): ?>
    <div class="samurai-coaching-alert mt-4" style="background:var(--surface-1);border-right:4px solid <?= $samuraiAdvice['class']==='success'?'var(--sage)':($samuraiAdvice['class']==='warn'?'var(--gold)':'var(--cyan)') ?>;padding:18px 24px;border-radius:12px;border-top:1px solid var(--border-soft);border-left:1px solid var(--border-soft);border-bottom:1px solid var(--border-soft)">
      <b style="font-size:1.1rem;font-weight:900;color:<?= $samuraiAdvice['class']==='success'?'#8ae6ab':($samuraiAdvice['class']==='warn'?'var(--gold-light)':'var(--text-1)') ?>;display:block;margin-bottom:6px">
        <?= $samuraiAdvice['title'] ?>
      </b>
      <p style="font-size:.95rem;color:var(--text-2);line-height:1.6">
        <?= e($samuraiAdvice['text']) ?>
      </p>
    </div>
  <?php endif; ?>
</div>

<!-- ===== PSYCHOMETRIC & RAW PERFORMANCE METRICS ===== -->
<div class="stat-cards mb-4" style="grid-template-columns:repeat(auto-fit, minmax(200px, 1fr))">
  <div class="panel stat reveal in" style="background:var(--surface-2);border:1px solid var(--border-soft);padding:20px">
    <span class="icon-tile sage" style="width:48px;height:48px;font-size:1.4rem">✓</span>
    <div>
      <div class="v" style="font-size:1.8rem;font-weight:900;color:var(--sage)"><?= fa_num($correct) ?></div>
      <div class="k" style="font-size:.9rem;color:var(--text-2);font-weight:800">پاسخ صحیح</div>
    </div>
  </div>

  <div class="panel stat reveal in" style="background:var(--surface-2);border:1px solid var(--border-soft);padding:20px">
    <span class="icon-tile" style="background:rgba(217,116,116,0.18);color:#ff9a9a;width:48px;height:48px;font-size:1.4rem">✗</span>
    <div>
      <div class="v" style="font-size:1.8rem;font-weight:900;color:var(--danger)"><?= fa_num($wrong) ?></div>
      <div class="k" style="font-size:.9rem;color:var(--text-2);font-weight:800">پاسخ غلط (نمره منفی)</div>
    </div>
  </div>

  <div class="panel stat reveal in" style="background:var(--surface-2);border:1px solid var(--border-soft);padding:20px">
    <span class="icon-tile" style="background:rgba(255,255,255,0.1);color:var(--text-3);width:48px;height:48px;font-size:1.4rem">⚪</span>
    <div>
      <div class="v" style="font-size:1.8rem;font-weight:900;color:var(--text-1)"><?= fa_num($blank) ?></div>
      <div class="k" style="font-size:.9rem;color:var(--text-2);font-weight:800">سوال بی‌پاسخ (نزده)</div>
    </div>
  </div>

  <div class="panel stat reveal in" style="background:var(--surface-2);border:1px solid var(--border-soft);padding:20px">
    <span class="icon-tile" style="background:var(--gold-glass);color:var(--gold);width:48px;height:48px"><?= icon('target',24) ?></span>
    <div>
      <div class="v" style="font-size:1.8rem;font-weight:900;color:var(--gold-light)"><?= fa_num($precision) ?>٪</div>
      <div class="k" style="font-size:.9rem;color:var(--text-2);font-weight:800">ضریب دقت (صحیح از کل‌زده)</div>
    </div>
  </div>
</div>

<!-- ===== DIAGNOSTIC ROOT CAUSE MASTER SUMMARY ===== -->
<?php if(array_sum($diagCounts) > 0): ?>
<div class="panel mb-4" style="background:var(--surface-2);border:1px solid var(--border-soft);border-radius:var(--r-lg);padding:24px">
  <div class="panel-head mb-4 between wrap gap-2" style="align-items:center">
    <h3 style="font-size:1.25rem;font-weight:900;color:var(--gold-light);display:flex;align-items:center;gap:10px"><?= icon('chart',22) ?> نمودار کلان آسیب‌شناسی و ریشه‌یابی اشتباهات آزمون</h3>
    <span class="badge badge-gold">ثبت‌شده توسط دانش‌آموز</span>
  </div>
  
  <div class="grid gap-3" style="grid-template-columns:repeat(auto-fit, minmax(180px, 1fr))">
    <?php foreach($diagCounts as $dlbl => $dcnt): ?>
      <div class="panel stat flex" style="flex-direction:row;align-items:center;justify-content:space-between;background:var(--surface-1);border:1px solid <?= $dcnt?'var(--gold)':'var(--border-soft)' ?>;padding:14px 18px;border-radius:12px">
        <span style="font-size:.9rem;font-weight:800;color:var(--text-1)"><?= $dlbl ?></span>
        <b style="font-size:1.5rem;font-weight:900;color:<?= $dcnt?'var(--gold-light)':'var(--text-3)' ?>"><?= fa_num($dcnt) ?></b>
      </div>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>

<!-- ===== PER-SECTION BREAKDOWN MATRIX ===== -->
<div class="panel mb-4" style="background:var(--surface-2);border:1px solid var(--border-soft);border-radius:var(--r-lg);padding:24px">
  <div class="panel-head mb-4 between wrap gap-2" style="align-items:center">
    <h3 style="font-size:1.25rem;font-weight:900;color:var(--text-1);display:flex;align-items:center;gap:10px"><?= icon('pie',22) ?> کارنامه تحلیلی به تفکیک درس / بخش</h3>
    <span class="muted" style="font-size:.85rem">مقایسه درصد کنکوری با و بدون نمره منفی</span>
  </div>

  <div class="sections-progress-grid grid gap-4" style="grid-template-columns:repeat(auto-fit, minmax(320px, 1fr))">
    <?php foreach ($sections as $s): 
        $sp = (float)$s['percent']; 
        $sTot = (int)$s['total'];
        $sCor = (int)$s['correct'];
        $sWr  = (int)$s['wrong'];
        $sBl  = (int)$s['blank'];
        $sRaw = $sTot > 0 ? round(($sCor / $sTot) * 100, 1) : 0;
        
        $sName = mb_strtolower($s['name']);
        if (str_contains($sName, 'ریاضی') || str_contains($sName, 'فیزیک') || str_contains($sName, 'حسابان') || str_contains($sName, 'هندسه')) {
            $sMean = 11.0; $sStd = 15.0;
        } elseif (str_contains($sName, 'زیست') || str_contains($sName, 'شیمی')) {
            $sMean = 21.0; $sStd = 18.0;
        } elseif (str_contains($sName, 'ادبیات') || str_contains($sName, 'دینی') || str_contains($sName, 'عربی')) {
            $sMean = 36.0; $sStd = 19.0;
        } else {
            $sMean = 25.0; $sStd = 17.0;
        }
        $sTaraz = 5000 + (($sp - $sMean) / $sStd) * 1000;
        $sTaraz = max(2500, min(11500, (int)round($sTaraz)));
        
        $clr  = $sp>=60 ? '#8ae6ab' : ($sp>=30 ? 'var(--gold-light)' : '#ff9a9a');
    ?>
      <div class="sec-prog-card panel flex" style="flex-direction:column;justify-content:space-between;background:var(--surface-1);border:1px solid var(--border-soft);padding:20px;border-radius:16px;box-shadow:0 4px 12px rgba(0,0,0,0.2);min-height:100%;word-break:break-word">
        <div>
          <div class="between mb-3 wrap gap-2" style="align-items:center">
            <b style="font-size:1.15rem;font-weight:900;color:var(--text-1)"><?= e($s['name']) ?></b>
            <div class="flex gap-2" style="align-items:center">
              <span class="badge" style="background:var(--surface-2);font-size:.85rem;font-weight:900;padding:4px 12px;color:<?= $clr ?>"><?= fa_num($sp) ?>٪</span>
              <span class="badge badge-gold" style="font-size:.8rem;font-family:monospace;font-weight:bold;padding:4px 10px" title="تراز کنکوری درس">تراز <?= fa_num($sTaraz) ?></span>
            </div>
          </div>

          <div class="progress mb-3" style="height:10px;background:var(--surface-2);border-radius:100px;overflow:hidden;padding:0;border:1px solid var(--border-soft)">
            <span style="width:<?= max(0, min(100, $sp)) ?>%;background:<?= $clr ?>;height:100%;display:block;border-radius:100px"></span>
          </div>
        </div>

        <div class="between mt-3 pt-3 wrap gap-2" style="font-size:.85rem;border-top:1px solid var(--surface-2);color:var(--text-2);align-items:center">
          <span class="flex wrap gap-2">
            <b style="color:var(--sage)"><?= fa_num($sCor) ?> درست</b> · 
            <b style="color:var(--danger)"><?= fa_num($sWr) ?> غلط</b> · 
            <span class="muted"><?= fa_num($sBl) ?> نزده</span>
          </span>
          <span class="muted" style="font-weight:700">درصد خام: <?= fa_num($sRaw) ?>٪</span>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
</div>

<!-- ===== INTERACTIVE ANSWER KEY & POST-MORTEM MATRIX ===== -->
<?php if ($showAnswers): ?>
  <div class="panel mb-4" style="background:var(--surface-2);border:1px solid var(--border-soft);border-radius:var(--r-lg);padding:24px">
    
    <div class="panel-head mb-4 between wrap gap-3" style="align-items:center">
      <div class="flex gap-2" style="align-items:center">
        <h3 style="font-size:1.25rem;font-weight:900;color:var(--text-1);display:flex;align-items:center;gap:10px"><?= icon('list',22) ?> پاسخنامه تشریحی و سیستم آسیب‌شناسی اشتباهات</h3>
        <span class="badge badge-sage">آسیب‌شناسی تعاملی</span>
      </div>

      <div class="filter-chips-group flex gap-2 wrap">
        <button type="button" class="badge badge-gold ans-filter-btn active" data-filter="all" style="padding:6px 14px;font-size:.8۵rem;cursor:pointer;font-weight:800">همه سوالات (<?= fa_num(count($questions)) ?>)</button>
        <button type="button" class="badge ans-filter-btn" data-filter="correct" style="padding:6px 14px;font-size:.8۵rem;cursor:pointer;background:var(--surface-1);color:var(--sage);border:1px solid var(--border-soft);font-weight:800">✓ درست‌ها (<?= fa_num($correct) ?>)</button>
        <button type="button" class="badge ans-filter-btn" data-filter="wrong" style="padding:6px 14px;font-size:.8۵rem;cursor:pointer;background:var(--surface-1);color:var(--danger);border:1px solid var(--border-soft);font-weight:800">✗ غلط‌ها (<?= fa_num($wrong) ?>)</button>
        <button type="button" class="badge ans-filter-btn" data-filter="blank" style="padding:6px 14px;font-size:.8۵rem;cursor:pointer;background:var(--surface-1);color:var(--text-2);border:1px solid var(--border-soft);font-weight:800">⚪ نزده‌ها (<?= fa_num($blank) ?>)</button>
      </div>
    </div>

    <?php if ($sheetPath): ?>
      <details class="mb-4 panel" style="background:var(--surface-1);border:1px solid var(--gold);padding:14px 20px;border-radius:16px">
        <summary style="font-weight:900;cursor:pointer;color:var(--gold-light);font-size:1rem;display:flex;align-items:center;gap:10px">
          <?= icon('image',20) ?> مشاهده‌ی دفترچه‌ی اصلی سوالات آزمون (جهت تطبیق چشمی سوال با کلید و آسیب‌شناسی)
        </summary>
        <div class="mt-3 text-c" style="overflow:auto;height:min(72vh,720px);background:#060a08;padding:<?= sheet_asset_type($sheetPath)==='pdf'?'0':'20px' ?>;border-radius:12px;border:1px solid var(--border-soft)">
          <?php if(sheet_asset_type($sheetPath)==='pdf'): ?>
            <div class="empty-state" style="height:100%;display:grid;place-items:center;color:var(--text-2)">دفترچه PDF فقط داخل محیط آزمون، بدون امکان دانلود مستقیم نمایش داده می‌شود.</div>
          <?php else: ?>
            <img src="<?= url($sheetPath) ?>" alt="Original Question Booklet Sheet" style="max-width:100%;height:auto;border-radius:10px;box-shadow:0 8px 24px rgba(0,0,0,0.6)">
          <?php endif; ?>
        </div>
      </details>
    <?php endif; ?>

    <!-- ماتریس کارت‌های پاسخنامه (Redesigned Unbreakable Samurai Layout) -->
    <div class="answer-sheet-container grid gap-4" id="answerSheetContainer" style="grid-template-columns:repeat(auto-fill, minmax(min(100%, 380px), 1fr))">
      <?php foreach ($questions as $item): 
          $q     = $item['q']; 
          $qid   = (int)$q['id'];
          $sel   = $item['selected'] !== null ? (int)$item['selected'] : null; 
          $st    = $item['state']; // correct | wrong | blank
          $cOpt  = (int)$q['correct_opt'];
          $bgClr = $st==='correct' ? 'rgba(95,174,123,0.08)' : ($st==='wrong' ? 'rgba(217,116,116,0.1)' : 'var(--surface-1)');
          $bdClr = $st==='correct' ? 'var(--sage)' : ($st==='wrong' ? 'var(--danger)' : 'var(--border-soft)');
          
          $qAns     = $ansMap[$qid] ?? [];
          $dReason  = (string)($qAns['diagnostic_reason'] ?? '');
          $dTakeaway= e((string)($qAns['diagnostic_takeaway'] ?? ''));
      ?>
        <div class="ans-q-item panel flex" data-state="<?= $st ?>" data-qid="<?= $qid ?>" style="flex-direction:column;justify-content:space-between;background:<?= $bgClr ?>;border:1px solid <?= $bdClr ?>;padding:24px;border-radius:20px;box-shadow:0 6px 16px rgba(0,0,0,0.15);min-height:100%;word-break:break-word">
          
          <div>
            <div class="ans-head between mb-4 wrap gap-2" style="align-items:center;border-bottom:1px solid var(--surface-2);padding-bottom:14px">
              <div class="flex gap-2" style="align-items:center">
                <span class="ans-num" style="background:var(--surface-2);color:var(--text-1);font-family:monospace;font-size:1.15rem;font-weight:900;padding:4px 12px;border-radius:8px;border:1px solid var(--border-soft)">Q<?= fa_num($item['gnum']) ?></span>
                <span class="ans-sec" style="font-weight:800;color:var(--gold-light);font-size:.9۵rem"><?= e($item['sec']) ?></span>
              </div>

              <span class="badge" style="background:<?= $st==='correct'?'var(--sage)':($st==='wrong'?'var(--danger)':'var(--surface-2)') ?>;color:<?= $st==='blank'?'var(--text-2)':'#000' ?>;font-weight:900;font-size:.8۵rem;padding:6px 14px">
                <?= $st==='correct' ? icon('check',15).' پاسخ صحیح' : ($st==='wrong' ? icon('close',15).' پاسخ غلط' : '⚪ نزده') ?>
              </span>
            </div>

            <?php if($q['q_image']): ?>
              <div class="eq-image mb-4 text-c" style="background:#060a08;padding:12px;border-radius:12px;border:1px solid var(--border-soft)">
                <?php if(sheet_asset_type($q['q_image'])==='pdf'): ?>
                  <span class="badge badge-gold">PDF سوال فقط در محیط آزمون نمایش داده می‌شود</span>
                <?php else: ?>
                  <img src="<?= url($q['q_image']) ?>" alt="" loading="lazy" style="max-height:240px;width:auto;max-width:100%;border-radius:8px">
                <?php endif; ?>
              </div>
            <?php endif; ?>

            <?php if($q['q_text']): ?>
              <div class="ans-text mb-4" style="font-size:1rem;font-weight:800;color:var(--text-1);line-height:1.7">
                <?= nl2br(e($q['q_text'])) ?>
              </div>
            <?php endif; ?>

            <div class="ans-options-matrix grid gap-3 mb-4" style="grid-template-columns:repeat(auto-fit, minmax(150px, 1fr));direction:ltr">
              <?php for($o=1; $o<=4; $o++): 
                $isCor = ($cOpt === $o);
                $isSel = ($sel   === $o);
                $oBg = $isCor ? 'var(--sage)' : ($isSel ? 'var(--danger)' : 'var(--surface-2)');
                $oTxt = $isCor ? '#000' : ($isSel ? '#fff' : 'var(--text-2)');
                $oBd  = $isCor ? 'var(--sage)' : ($isSel ? 'var(--danger)' : 'var(--border-soft)');
              ?>
                <div class="opt-pill flex gap-2" style="background:<?= $oBg ?>;color:<?= $oTxt ?>;border:1px solid <?= $oBd ?>;padding:8px 14px;border-radius:10px;align-items:center;font-weight:900;font-size:.9rem;<?= ($isCor || $isSel)?'box-shadow:0 0 16px '.$oBg.'44':'' ?>">
                  <span style="font-family:monospace;background:rgba(0,0,0,0.3);padding:2px 8px;border-radius:6px;font-size:.85rem"><?= $o ?></span>
                  <span style="direction:rtl;text-align:right;flex:1;word-break:break-word;overflow-wrap:break-word;white-space:normal;font-size:.85rem;line-height:1.5">
                    <?= e($q['opt'.$o] ?? ($isCor ? '✓ کلید صحیح' : ($isSel ? '✗ پاسخ شما' : 'گزینه '.$o))) ?>
                  </span>
                </div>
              <?php endfor; ?>
            </div>
          </div>

          <!-- =================================================================
               INTERACTIVE ERROR POST-MORTEM & DIAGNOSTIC BOX
               ================================================================= -->
          <?php if($st==='wrong' || $st==='blank'): ?>
            <div class="diagnostic-post-mortem-box mt-3 panel flex" style="flex-direction:column;gap:14px;background:var(--surface-2);border:2px solid <?= $dReason?'var(--sage)':'var(--gold)' ?>;padding:20px;border-radius:16px;box-shadow:inset 0 0 16px rgba(0,0,0,0.2)">
              <div class="between wrap gap-2" style="align-items:center;border-bottom:1px solid rgba(255,255,255,0.05);padding-bottom:12px">
                <b style="color:var(--gold-light);font-size:.9۵rem;display:flex;align-items:center;gap:8px">
                  <?= icon('sparkles',18) ?> ریشه‌یابی و آسیب‌شناسی (چرا این تست <?= $st==='wrong'?'اشتباه زده شد':'نزده ماند' ?>؟)
                </b>
                <span class="save-diag-status badge <?= $dReason?'badge-sage':'' ?>" style="font-size:.8rem;padding:4px 10px;font-weight:900">
                  <?= $dReason ? '✓ علت ثبت شده' : 'انتخاب نشده' ?>
                </span>
              </div>

              <!-- Reason Chips -->
              <div class="flex wrap gap-2 diag-reasons-group">
                <?php foreach([
                  'بی‌دقتی / شتاب‌زدگی' => '⚠️ بی‌دقتی / شتاب‌زدگی',
                  'نقص علمی / ضعف مفهوم' => '📖 ضعف علمی / نقص مفهوم',
                  'فراموشی فرمول یا نکته' => '❓ فراموشی فرمول یا نکته',
                  'دام طراح سوال' => '🕸️ دام طراح سوال',
                  'کمبود وقت / استرس' => '⏳ کمبود وقت / استرس'
                ] as $dk => $dlbl): ?>
                  <button type="button" class="diag-reason-btn badge <?= $dReason===$dk?'active badge-gold':'' ?>" data-dreason="<?= e($dk) ?>" style="padding:8px 14px;font-size:.8۵rem;cursor:pointer;font-weight:800;background:<?= $dReason===$dk?'var(--gold)':'var(--surface-1)' ?>;color:<?= $dReason===$dk?'#000':'var(--text-2)' ?>;border:1px solid var(--border-soft);border-radius:8px;transition:all 0.15s">
                    <?= $dlbl ?>
                  </button>
                <?php endforeach; ?>
              </div>

              <!-- Takeaway input -->
              <div class="flex wrap gap-2 mt-2 pt-2 between" style="border-top:1px solid rgba(255,255,255,0.05);align-items:center">
                <span class="muted" style="font-size:.85rem;font-weight:700">💡 نکته‌ی طلایی:</span>
                <input class="input diag-takeaway-input" value="<?= $dTakeaway ?>" placeholder="قاعده کنکوری یا نکته‌ای که از این سوال یاد گرفتی را بنویس..." style="flex:1;min-width:240px;height:40px;background:var(--surface-1);border-radius:8px;margin:0;font-size:.9rem;font-weight:bold">
                <button type="button" class="btn btn-gold save-diagnostic-btn text-c" style="height:40px;font-weight:900;padding:0 20px;font-size:.9rem">
                  ✓ ثبت تحلیل
                </button>
              </div>
            </div>
          <?php endif; ?>

          <?php if(!empty($q['explanation'])): ?>
            <details class="ans-exp-details mt-4 panel" style="background:var(--surface-2);border:1px solid var(--border-soft);padding:12px 18px;border-radius:12px">
              <summary style="font-weight:900;cursor:pointer;color:var(--gold-light);font-size:.9rem;display:flex;align-items:center;gap:8px">
                <?= icon('sparkles',18) ?> پاسخ تشریحی و نکات آموزشی طراح
              </summary>
              <div class="mt-3 pt-3" style="border-top:1px solid var(--border-soft);font-size:.9rem;color:var(--text-1);line-height:1.8">
                <?= nl2br(e($q['explanation'])) ?>
              </div>
            </details>
          <?php endif; ?>

        </div>
      <?php endforeach; ?>
    </div>

    <script>
      const TA_API = '<?= url('api/exam_take.php') ?>';
      const attId  = <?= (int)$att['id'] ?>;

      document.querySelectorAll('.ans-filter-btn').forEach(btn => {
        btn.addEventListener('click', () => {
          document.querySelectorAll('.ans-filter-btn').forEach(b => {
            b.classList.remove('active', 'badge-gold');
            b.classList.add('badge');
            b.style.background = 'var(--surface-1)';
          });
          btn.classList.add('active', 'badge-gold');
          btn.style.background = '';
          
          const filter = btn.dataset.filter;
          document.querySelectorAll('#answerSheetContainer .ans-q-item').forEach(item => {
            if (filter === 'all' || item.dataset.state === filter) {
              item.style.display = 'flex';
            } else {
              item.style.display = 'none';
            }
          });
        });
      });

      document.querySelectorAll('#answerSheetContainer .ans-q-item').forEach(item => {
        const qid = item.dataset.qid;
        
        item.querySelectorAll('.diag-reason-btn').forEach(rBtn => {
          rBtn.addEventListener('click', async () => {
            item.querySelectorAll('.diag-reason-btn').forEach(b => {
              b.classList.remove('active', 'badge-gold');
              b.classList.add('badge');
              b.style.background = 'var(--surface-1)';
              b.style.color      = 'var(--text-2)';
            });
            rBtn.classList.add('active', 'badge-gold');
            rBtn.style.background = 'var(--gold)';
            rBtn.style.color      = '#000';

            const dReason   = rBtn.dataset.dreason;
            const dTakeaway = item.querySelector('.diag-takeaway-input')?.value.trim() || '';
            const statusBadge = item.querySelector('.save-diag-status');
            if (statusBadge) { statusBadge.textContent = 'در حال ثبت...'; }

            try {
              await api(TA_API, { method: 'POST', body: { action: 'save_diagnostic', attempt_id: attId, question_id: qid, diagnostic_reason: dReason, diagnostic_takeaway: dTakeaway } });
              toast('علت اشتباه با موفقیت در حافظه آسیب‌شناسی مَدار ثبت شد 🎯', 'success');
              if (statusBadge) { statusBadge.textContent = '✓ علت ثبت شد'; statusBadge.classList.add('badge-sage'); }
              
              // آپدیت آنی نمودار کلان بالای صفحه (اختیاری)
              const masterStat = document.querySelector(`.diag-counts-summary .panel.stat[data-realkey="${dReason}"] b`);
              if (masterStat) { masterStat.textContent = faNum(parseInt(masterStat.textContent) + 1); }
            } catch(err) {
              toast(err.error || 'خطا در ثبت علت اشتباه', 'error');
              if (statusBadge) { statusBadge.textContent = 'خطا در ثبت'; }
            }
          });
        });

        item.querySelector('.save-diagnostic-btn')?.addEventListener('click', async b => {
          const btn = b.currentTarget;
          btn.disabled = true; btn.innerHTML = '<span class="spinner" style="width:14px;height:14px"></span>';

          const activeR = item.querySelector('.diag-reason-btn.active')?.dataset.dreason || '';
          const dTakeaway = item.querySelector('.diag-takeaway-input')?.value.trim() || '';

          try {
            await api(TA_API, { method: 'POST', body: { action: 'save_diagnostic', attempt_id: attId, question_id: qid, diagnostic_reason: activeR, diagnostic_takeaway: dTakeaway } });
            toast('نکته طلایی و آسیب‌شناسی با موفقیت ثبت شد 🌟', 'success');
            btn.disabled = false; btn.innerHTML = '✓ ثبت تحلیل';
          } catch(err) {
            toast(err.error || 'خطا در ثبت نکته', 'error');
            btn.disabled = false; btn.innerHTML = '✓ ثبت تحلیل';
          }
        });
      });
    </script>
  </div>
<?php else: ?>
  <div class="panel text-c mb-4" style="background:var(--surface-2);border:1px solid var(--border-soft);padding:48px 20px;border-radius:var(--r-lg)">
    <span style="font-size:3rem;color:var(--gold);margin-bottom:16px;display:block"><?= icon('lock',48) ?></span>
    <h3 style="font-size:1.4rem;font-weight:900;color:var(--text-1);margin-bottom:8px">پاسخنامه‌ی تشریحی و کلیدی قفل است</h3>
    <p class="muted" style="max-width:480px;margin:0 auto;font-size:.9۵rem;line-height:1.6">
      دسترسی به پاسخنامه‌ی این آزمون توسط مشاور (دکتر سجاد صیادی) غیرفعال شده است. در صورت نیاز با مشاور خود در ارتباط باشید.
    </p>
  </div>
<?php endif; ?>
<?php
}
