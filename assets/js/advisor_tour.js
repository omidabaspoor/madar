/* مَدار · Advisor Panel Interactive Tutorial (پکیج آموزش تعاملی پنل مشاور) */
(() => {
  'use strict';
  
  // Spotlight CSS tricks & Tooltip Styles
  const style = document.createElement('style');
  style.innerHTML = `
    .tour-highlight {
      position: relative !important;
      z-index: 100001 !important;
      box-shadow: 0 0 0 9999px rgba(6, 11, 9, 0.85), 0 0 25px rgba(224, 197, 149, 0.4) !important;
      pointer-events: auto !important;
      transition: all 0.3s ease !important;
      border-color: var(--gold) !important;
    }
    .tour-tooltip {
      position: fixed;
      z-index: 100002;
      width: 320px;
      background: linear-gradient(160deg, #15201b, #0c1512);
      border: 2px solid var(--gold);
      border-radius: 18px;
      box-shadow: 0 15px 45px rgba(0,0,0,0.7);
      padding: 18px;
      direction: rtl;
      color: var(--text-1);
      font-family: inherit;
      animation: tourPop 0.25s cubic-bezier(0.16, 1, 0.3, 1);
    }
    @keyframes tourPop {
      from { opacity: 0; transform: scale(0.92) translateY(10px); }
      to { opacity: 1; transform: scale(1) translateY(0); }
    }
    .tour-tooltip h4 {
      font-size: 0.98rem;
      font-weight: 1000;
      color: var(--gold-light);
      margin: 0 0 8px 0;
      display: flex;
      align-items: center;
      gap: 6px;
    }
    .tour-tooltip p {
      font-size: 0.82rem;
      line-height: 1.6;
      color: var(--text-2);
      margin: 0 0 14px 0;
    }
    .tour-steps-indicator {
      font-size: 0.72rem;
      font-weight: 900;
      color: var(--text-3);
    }
    .tour-btn-row {
      display: flex;
      justify-content: space-between;
      align-items: center;
      gap: 8px;
    }
    .tour-btn {
      border: 1px solid var(--border-soft);
      background: rgba(255,255,255,0.03);
      color: var(--text-1);
      border-radius: 10px;
      padding: 6px 12px;
      font-size: 0.75rem;
      font-weight: 900;
      cursor: pointer;
      transition: all 0.2s;
    }
    .tour-btn:hover {
      background: rgba(255,255,255,0.08);
      border-color: var(--border);
    }
    .tour-btn.next {
      background: linear-gradient(135deg, var(--gold-light), var(--gold));
      color: #111;
      border: none;
    }
    .tour-btn.next:hover {
      box-shadow: 0 0 10px rgba(178,148,95,0.3);
    }
    .tour-test-box {
      background: rgba(178,148,95,0.12);
      border: 1px dashed var(--gold);
      border-radius: 10px;
      padding: 10px;
      margin-bottom: 12px;
      display: flex;
      flex-direction: column;
      gap: 6px;
    }
    .tour-test-task {
      background: #1c2823;
      border: 1px solid rgba(255,255,255,0.06);
      border-radius: 8px;
      padding: 6px 10px;
      font-size: 0.75rem;
      font-weight: 900;
      color: var(--text-1);
      cursor: pointer;
      transition: all 0.2s;
      display: flex;
      justify-content: space-between;
      align-items: center;
    }
    .tour-test-task:hover {
      border-color: var(--gold-light);
      background: #203029;
    }
    .tour-test-task.success {
      border-color: var(--success) !important;
      background: rgba(95,174,123,0.1) !important;
      color: var(--success) !important;
    }
  `;
  document.head.appendChild(style);

  let currentStep = 0;
  let tooltipEl = null;
  let highlightedEl = null;
  let testCompleted = false;

  const steps = [
    {
      target: '#sidebar',
      title: 'سایدبار ناوبری مَدار',
      text: 'این بخش سایدبار هوشمند مَدار است. از اینجا به تمام کارهای مدیریتی خود دسترسی دارید: تعریف برنامه‌ها، هماهنگی جلسات، بررسی کارنامه‌ها و ارتباط با دانش‌آموزان.'
    },
    {
      target: '.tb-actions',
      title: 'پیام‌ها و اعلان‌های فوری',
      text: 'در این گوشه، پیام‌های جدیدِ ارسالی دانش‌آموزان و هشدارهای مهم سیستم (مانند گزارش‌های روزانه‌ی قفل‌شده به دلیل اتمام مهلت شبانه) را به صورت زنده رصد و مدیریت می‌کنید.'
    },
    {
      target: '.stat-cards',
      title: 'آمار کلان و پیشرفت زنده',
      text: 'این بخش خلاصه عملکرد کل دانش‌آموزان تحت نظارت شما را نمایش می‌دهد. نرخ تکمیل تسک‌ها و تعداد دانش‌آموزان در انتظار تأیید را از همین‌جا فوراً دنبال کنید.'
    },
    {
      target: '#meetingForm', // Only if on admin/schedule_meeting.php, otherwise falls back to fallback element
      fallback: '.content',
      title: 'تست تعاملی سیستم هوشمند ⚡',
      text: 'بیایید یک آزمون کوچک انجام دهیم! این کادر شبیه‌ساز برنامه‌ریزی هوشمند مَدار است. برای تست تعاملی، لطفاً روی پارت درسی زیر کلیک کنید تا ذخیره‌سازی خودکار و یادگیری سیستم را امتحان کنید:',
      isTest: true
    }
  ];

  function showStep(index) {
    // Clear previous
    closeStep();
    
    if (index < 0 || index >= steps.length) {
      endTour();
      return;
    }

    currentStep = index;
    const step = steps[index];
    let target = document.querySelector(step.target);
    if (!target && step.fallback) {
      target = document.querySelector(step.fallback);
    }
    
    if (!target) {
      // If target is missing, skip to next step
      showStep(index + 1);
      return;
    }

    highlightedEl = target;
    target.classList.add('tour-highlight');

    // Create tooltip
    tooltipEl = document.createElement('div');
    tooltipEl.className = 'tour-tooltip';
    
    let innerHTML = `
      <h4>🎓 ${step.title}</h4>
      <p>${step.text}</p>
    `;

    if (step.isTest) {
      innerHTML += `
        <div class="tour-test-box" id="tourTestBox">
          <div class="tour-test-task" id="tourTestTask">
            <span>📚 مطالعه زیست‌شناسی دوازدهم (فصل ۳)</span>
            <span style="color: var(--gold); font-size: 10px;">کلیک کنید ⚡</span>
          </div>
        </div>
      `;
    }

    innerHTML += `
      <div class="tour-btn-row">
        <span class="tour-steps-indicator">مرحله ${fa(index + 1)} از ${fa(steps.length)}</span>
        <div class="flex gap-1">
          ${index > 0 ? `<button type="button" class="tour-btn" id="tourPrevBtn">قبلی</button>` : ''}
          <button type="button" class="tour-btn ${index === steps.length - 1 ? '' : 'next'}" id="tourNextBtn">
            ${index === steps.length - 1 ? 'پایان آموزش' : 'بعدی'}
          </button>
        </div>
      </div>
    `;

    tooltipEl.innerHTML = innerHTML;
    document.body.appendChild(tooltipEl);

    // Position tooltip (viewport-relative since tooltip has position: fixed)
    const r = target.getBoundingClientRect();
    const tw = tooltipEl.offsetWidth;
    const th = tooltipEl.offsetHeight;
    
    let top = r.bottom + 12;
    let left = r.right - tw;
    
    // Safety boundaries
    if (top + th > window.innerHeight) {
      top = r.top - th - 12;
    }
    if (left < 10) left = 10;
    if (left + tw > window.innerWidth - 10) left = window.innerWidth - tw - 10;

    tooltipEl.style.top = top + 'px';
    tooltipEl.style.left = left + 'px';

    // Bind events
    document.getElementById('tourNextBtn').onclick = () => {
      if (step.isTest && !testCompleted) {
        alert('لطفاً ابتدا تسک شبیه‌ساز را کلیک کنید تا فرآیند یادگیری هوشمند را امتحان کنید! 🎓');
        return;
      }
      showStep(index + 1);
    };
    
    const prev = document.getElementById('tourPrevBtn');
    if (prev) {
      prev.onclick = () => showStep(index - 1);
    }

    if (step.isTest) {
      const task = document.getElementById('tourTestTask');
      task.onclick = () => {
        if (testCompleted) return;
        testCompleted = true;
        task.className = 'tour-test-task success';
        task.innerHTML = '<span>✓ ذخیره‌سازی و آنالیز هوشمند انجام شد!</span>';
        
        // Custom interactive visual effect
        const win = document.createElement('div');
        win.style = 'position: fixed; inset: 0; pointer-events: none; z-index: 100003; display: flex; align-items: center; justify-content: center;';
        win.innerHTML = '<div style="background: rgba(95,174,123,0.9); color: white; padding: 18px 32px; border-radius: 18px; font-weight: bold; font-size: 1.1rem; box-shadow: 0 10px 40px rgba(0,0,0,0.5); transform: scale(0.9); transition: all 0.3s; opacity: 0;" id="successAlert">🎓 آفرین! یادگیری هوشمند مَدار منبع کتاب و ساعت پیشنهادی این درس را یاد گرفت!</div>';
        document.body.appendChild(win);
        
        setTimeout(() => {
          const sa = document.getElementById('successAlert');
          if (sa) { sa.style.transform = 'scale(1)'; sa.style.opacity = '1'; }
        }, 50);

        setTimeout(() => {
          win.remove();
          showStep(index + 1);
        }, 2200);
      };
    }
  }

  function closeStep() {
    if (highlightedEl) {
      highlightedEl.classList.remove('tour-highlight');
      highlightedEl = null;
    }
    if (tooltipEl) {
      tooltipEl.remove();
      tooltipEl = null;
    }
  }

  function startTour() {
    testCompleted = false;
    showStep(0);
  }

  function endTour() {
    closeStep();
    alert('تبریک! آموزش تعاملی پنل مشاور مَدار با موفقیت به پایان رسید. حالا شما یک مشاور حرفه‌ای مَدار هستید! 🎓✨');
  }

  function fa(n) { return String(n).replace(/\d/g, d => FA[d]); }
  const FA = ['۰','۱','۲','۳','۴','۵','۶','۷','۸','۹'];

  document.addEventListener('DOMContentLoaded', () => {
    const btn = document.getElementById('advisorTourBtn');
    if (btn) {
      btn.onclick = () => {
        startTour();
      };
    }
  });

})();
