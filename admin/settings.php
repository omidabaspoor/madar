<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/models.php';
require_once __DIR__ . '/../includes/panel_layout.php';
boot_session();
require_role('advisor','admin');
$u = current_user();

// مدیریت درس‌ها
if ($_SERVER['REQUEST_METHOD']==='POST') {
    require_csrf();
    $act = (string)input('s_action');
    if ($act==='add_subject') {
        $name=trim((string)input('name')); $color=trim((string)input('color')) ?: '#6b8872';
        if ($name!=='') { db()->prepare('INSERT INTO subjects (advisor_id,name,color) VALUES (?,?,?)')->execute([$u['id'],$name,$color]); flash('success','درس اضافه شد'); }
    } elseif ($act==='del_subject') {
        db()->prepare('DELETE FROM subjects WHERE id=?')->execute([(int)input('id')]); flash('success','درس حذف شد');
    } elseif ($act==='planner_defaults') {
        if (!settings_table_ready()) {
            flash('error','جدول تنظیمات ساخته نشد. لطفاً یک‌بار install.php را باز کنید یا دسترسی CREATE TABLE دیتابیس را بررسی کنید.');
        } else {
            save_planner_settings((int)$u['id'], $_POST);
            $saved = advisor_settings((int)$u['id']);
            flash('success','پیش‌فرض‌ها ذخیره شد · مدت پیش‌فرض اکنون '.fa_num($saved['default_duration']).' دقیقه است.');
        }
    }
    redirect('admin/settings.php');
}
$subjects = all_subjects();
$pset = advisor_settings((int)$u['id']);
$durOptions = [30=>'۳۰ دقیقه',45=>'۴۵ دقیقه',50=>'۵۰ دقیقه',60=>'۶۰ دقیقه (۱ ساعت)',75=>'۷۵ دقیقه',90=>'۹۰ دقیقه',120=>'۱۲۰ دقیقه (۲ ساعت)',150=>'۱۵۰ دقیقه',180=>'۱۸۰ دقیقه (۳ ساعت)'];

panel_start('تنظیمات', 'پیکربندی حساب و درس‌ها', 'admin', 'settings', ['student.css','builder.css']);
?>
<div class="panel-grid cols-2">
  <div class="panel reveal in">
    <div class="panel-head"><h3><?= icon('user',20) ?> اطلاعات حساب</h3></div>
    <form method="post" action="<?= url('api/profile.php') ?>">
      <?= csrf_field() ?><input type="hidden" name="action" value="profile">
      <div class="field"><label>نام نمایشی</label><input class="input" name="full_name" value="<?= e($u['full_name']) ?>" required></div>
      <div class="field"><label>تخصص / عنوان</label><input class="input" name="field" value="<?= e($u['field']) ?>" placeholder="مشاور کنکور"></div>
      <div class="field"><label>موبایل</label><input class="input" name="phone" dir="ltr" value="<?= e($u['phone']) ?>"></div>
      <button class="btn btn-gold"><?= icon('check',16) ?> ذخیره</button>
    </form>
    <div class="divider"></div>
    <h3 style="font-size:1rem;margin-bottom:12px"><?= icon('lock',18) ?> تغییر گذرواژه</h3>
    <form method="post" action="<?= url('api/profile.php') ?>">
      <?= csrf_field() ?><input type="hidden" name="action" value="password">
      <div class="grid gap-3" style="grid-template-columns:1fr 1fr 1fr">
        <div class="field"><label>فعلی</label><input class="input" name="current" type="password" required></div>
        <div class="field"><label>جدید</label><input class="input" name="new" type="password" required></div>
        <div class="field"><label>تکرار</label><input class="input" name="new2" type="password" required></div>
      </div>
      <button class="btn btn-ghost"><?= icon('lock',16) ?> تغییر گذرواژه</button>
    </form>
  </div>

  <div class="panel reveal" data-d="1">
    <div class="panel-head"><h3><?= icon('book',20) ?> درس‌ها</h3></div>
    <form method="post" class="flex gap-2 mb-4" style="align-items:flex-end">
      <?= csrf_field() ?><input type="hidden" name="s_action" value="add_subject">
      <div class="field" style="flex:1;margin:0"><label>نام درس</label><input class="input" name="name" placeholder="مثلاً زمین‌شناسی" required></div>
      <div class="field" style="margin:0;width:60px"><label>رنگ</label><input class="input" type="color" name="color" value="#6b8872" style="padding:4px;height:44px"></div>
      <button class="btn btn-gold btn-icon" type="submit"><?= icon('plus',18) ?></button>
    </form>
    <div class="flex gap-2 wrap">
      <?php foreach($subjects as $s):?>
      <span class="badge" style="padding:6px 10px"><span class="subj-dot" style="background:<?= e($s['color']) ?>"></span><?= e($s['name']) ?>
        <form method="post" style="display:inline"><?= csrf_field() ?><input type="hidden" name="s_action" value="del_subject"><input type="hidden" name="id" value="<?= (int)$s['id'] ?>"><button style="background:none;border:none;color:var(--text-3);cursor:pointer;padding:0;margin-right:4px" onclick="return confirm('حذف شود؟')"><?= icon('close',12) ?></button></form>
      </span>
      <?php endforeach;?>
      <?php if(!$subjects):?><span class="muted">درسی ثبت نشده</span><?php endif;?>
    </div>
  </div>
</div>

<!-- ===== پیش‌فرض‌های برنامه‌ریز ===== -->
<div class="panel reveal" data-d="2" style="margin-top:18px">
  <div class="panel-head">
    <h3><?= icon('settings',20) ?> پیش‌فرض‌های برنامه‌ریز</h3>
    <span class="muted" style="font-size:.82rem">این مقادیر هنگام افزودن تسک به‌صورت خودکار اعمال می‌شوند تا برنامه‌ریزی سریع‌تر و دقیق‌تر شود.</span>
  </div>
  <form method="post" class="settings-defaults">
    <?= csrf_field() ?><input type="hidden" name="s_action" value="planner_defaults">
    <div class="grid gap-3 defaults-grid">

      <div class="field">
        <label for="d_dur"><?= icon('clock',15) ?> مدت پیش‌فرض تسک</label>
        <select class="select" id="d_dur" name="default_duration">
          <?php foreach ($durOptions as $m=>$lbl): ?>
          <option value="<?= $m ?>" <?= (int)$pset['default_duration']===$m?'selected':'' ?>><?= e($lbl) ?></option>
          <?php endforeach; ?>
          <?php if (!array_key_exists((int)$pset['default_duration'], $durOptions)): ?>
          <option value="<?= (int)$pset['default_duration'] ?>" selected><?= fa_num($pset['default_duration']) ?> دقیقه</option>
          <?php endif; ?>
        </select>
        <span class="hint">مثلاً اگر روی ۶۰ بگذارید، همه‌جای برنامه‌ریز مدت پیش‌فرض ۶۰ دقیقه می‌شود.</span>
      </div>

      <div class="field">
        <label for="d_test"><?= icon('check',15) ?> تعداد تست پیش‌فرض</label>
        <input class="input" id="d_test" name="default_test_count" type="number" min="0" max="600" inputmode="numeric" value="<?= e($pset['default_test_count']) ?>">
        <span class="hint">وقتی نوع «تست» را انتخاب می‌کنید، این عدد خودکار پر می‌شود.</span>
      </div>

      <div class="field">
        <label for="d_prio"><?= icon('flag',15) ?> اولویت پیش‌فرض</label>
        <select class="select" id="d_prio" name="default_priority">
          <option value="normal" <?= $pset['default_priority']==='normal'?'selected':'' ?>>عادی</option>
          <option value="high"   <?= $pset['default_priority']==='high'?'selected':'' ?>>مهم</option>
          <option value="low"    <?= $pset['default_priority']==='low'?'selected':'' ?>>کم‌اهمیت</option>
        </select>
      </div>

      <div class="field">
        <label for="d_grid"><?= icon('grid',15) ?> تراکم جدول برنامه</label>
        <select class="select" id="d_grid" name="grid_density">
          <option value="comfortable" <?= $pset['grid_density']==='comfortable'?'selected':'' ?>>راحت و مرتب (پیشنهادی)</option>
          <option value="compact"     <?= $pset['grid_density']==='compact'?'selected':'' ?>>فشرده (تسک بیشتر در دید)</option>
        </select>
      </div>

      <div class="field">
        <label for="d_reading"><?= icon('glasses',15) ?> مدت روزخوانی (واحد ویژه)</label>
        <input class="input" id="d_reading" name="special_reading_min" type="number" min="0" max="600" inputmode="numeric" value="<?= e($pset['special_reading_min']) ?>">
      </div>

      <div class="field">
        <label for="d_exam"><?= icon('clipboard',15) ?> مدت آزمونک (واحد ویژه)</label>
        <input class="input" id="d_exam" name="special_exam_min" type="number" min="0" max="600" inputmode="numeric" value="<?= e($pset['special_exam_min']) ?>">
      </div>
    </div>

    <div class="divider"></div>

    <div class="field" style="max-width:560px">
      <label><?= icon('copy',15) ?> رفتار کپی تسک</label>
      <div class="radio-cards">
        <label class="radio-card <?= $pset['paste_mode']==='single'?'active':'' ?>">
          <input type="radio" name="paste_mode" value="single" <?= $pset['paste_mode']==='single'?'checked':'' ?>>
          <span class="rc-title"><?= icon('check-circle',16) ?> یک‌بار پیست</span>
          <span class="rc-desc">کپی می‌کنید، یک‌جا پیست می‌کنید و کپی تمام می‌شود.</span>
        </label>
        <label class="radio-card <?= $pset['paste_mode']==='sticky'?'active':'' ?>">
          <input type="radio" name="paste_mode" value="sticky" <?= $pset['paste_mode']==='sticky'?'checked':'' ?>>
          <span class="rc-title"><?= icon('copy',16) ?> پیست چندباره (چسبان)</span>
          <span class="rc-desc">یک‌بار کپی، چندین خانه پشت‌سرهم پیست؛ با Esc یا دکمه پایان می‌دهید.</span>
        </label>
      </div>
    </div>

    <div class="toggle-row" style="max-width:560px;margin-top:6px">
      <div>
        <div style="font-weight:700"><?= icon('sparkles',15) ?> پرکردن خودکار هوشمند</div>
        <div class="muted" style="font-size:.8rem">خانه‌های بعدی بر اساس آخرین انتخاب شما (در همان واحد/درس) خودکار پر می‌شوند.</div>
      </div>
      <label class="switch">
        <input type="checkbox" name="smart_autofill" value="1" <?= $pset['smart_autofill']==='1'?'checked':'' ?>>
        <span class="slider"></span>
      </label>
    </div>

    <button class="btn btn-gold mt-2"><?= icon('check',16) ?> ذخیره پیش‌فرض‌ها</button>
  </form>
</div>

<style>
  .defaults-grid { grid-template-columns:repeat(auto-fit,minmax(220px,1fr)); }
  .settings-defaults .field label { display:flex; align-items:center; gap:6px; }
  .settings-defaults .hint { display:block; font-size:.74rem; color:var(--text-faint); margin-top:5px; line-height:1.6; }
  .radio-cards { display:grid; grid-template-columns:1fr 1fr; gap:10px; }
  .radio-card { display:flex; flex-direction:column; gap:4px; padding:13px 14px; border:1.5px solid var(--border); border-radius:var(--r-md); background:var(--surface); cursor:pointer; transition:.18s; }
  .radio-card input { position:absolute; opacity:0; }
  .radio-card:hover { border-color:var(--sage); }
  .radio-card.active { border-color:var(--gold); background:var(--gold-glass); }
  .radio-card .rc-title { font-weight:800; display:flex; align-items:center; gap:6px; }
  .radio-card .rc-desc { font-size:.78rem; color:var(--text-3); line-height:1.6; }
  @media (max-width:600px){ .radio-cards { grid-template-columns:1fr; } }
</style>
<script>
  document.querySelectorAll('.radio-card input').forEach(r=>{
    r.addEventListener('change', ()=>{
      document.querySelectorAll('.radio-card').forEach(c=>c.classList.remove('active'));
      r.closest('.radio-card').classList.add('active');
    });
  });
</script>
<?php panel_end(); ?>
