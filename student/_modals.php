<?php /** مودال‌های مشترک پنل دانش‌آموز (یادداشت + مقدار تکمیل) */ ?>
<!-- amount modal: how many did you do? -->
<div class="modal-backdrop" id="amountModal">
  <div class="modal" style="max-width:400px">
    <div class="modal-head"><h3><?= icon('check-circle',18) ?> ثبت تکمیل تسک</h3><button class="modal-close" data-close><?= icon('close',18) ?></button></div>
    <p class="muted" style="margin-bottom:6px;font-size:.92rem"><span id="amTitle" style="color:var(--text);font-weight:700"></span></p>
    <p class="muted" style="margin-bottom:18px;font-size:.86rem">چند مورد از <b class="gold" id="amTarget">۰</b> را انجام دادی؟ <br><span style="font-size:.8rem">(نگران نباش، با هر مقداری تسک تکمیل می‌شود ✅)</span></p>
    <div class="amount-control">
      <input type="range" id="amRange" min="0" value="0" step="1" class="am-range">
      <input type="number" id="amCount" min="0" value="0" inputmode="numeric" class="input am-num">
    </div>
    <div class="flex gap-3 mt-4">
      <button class="btn btn-gold" style="flex:1" id="amConfirm"><?= icon('check',16) ?> تکمیل شد</button>
      <button class="btn btn-ghost" id="amFull"><?= icon('target',15) ?> همه را زدم</button>
    </div>
  </div>
</div>

<!-- note modal -->
<div class="modal-backdrop" id="noteModal">
  <div class="modal" style="max-width:440px">
    <div class="modal-head"><h3><?= icon('note',18) ?> یادداشت تسک</h3><button class="modal-close" data-close><?= icon('close',18) ?></button></div>
    <p class="muted" style="font-size:.84rem;margin-bottom:12px">می‌توانی نکته، سؤال یا مشکلت روی این تسک را بنویسی؛ مشاورت آن را می‌بیند.</p>
    <input type="hidden" id="noteTaskId">
    <textarea class="input" id="noteText" rows="4" placeholder="مثلاً: تست‌های ترکیبی این مبحث سخت بود…"></textarea>
    <button class="btn btn-gold btn-block mt-4" id="saveNoteBtn"><?= icon('check',16) ?> ذخیره یادداشت</button>
  </div>
</div>
