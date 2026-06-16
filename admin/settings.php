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
    }
    redirect('admin/settings.php');
}
$subjects = all_subjects();

panel_start('تنظیمات', 'پیکربندی حساب و درس‌ها', 'admin', 'settings', ['student.css']);
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
<?php panel_end(); ?>
