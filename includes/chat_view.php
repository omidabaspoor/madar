<?php
/** نمای چت مشترک — برای پنل مدیر و دانش‌آموز */
declare(strict_types=1);

function render_chat(string $role): void
{
    $u = current_user();
    $with = (int)($_GET['with'] ?? 0);
    ?>
<div class="chat-wrap">
  <aside class="chat-list" id="chatList">
    <div class="empty-state" style="padding:30px"><span class="spinner"></span></div>
  </aside>
  <section class="chat-main">
    <div class="chat-head" id="chatHead">
      <span class="u-ava" id="chatAva">—</span>
      <div><div class="nm" id="chatName">یک گفتگو را انتخاب کنید</div><div class="muted" style="font-size:.76rem" id="chatSub"></div></div>
    </div>
    <div class="chat-body" id="chatBody">
      <div class="empty-state" style="margin:auto"><div class="es-ico"><?= icon('message',30) ?></div>برای شروع، از لیست کناری انتخاب کن</div>
    </div>
    <form class="chat-input" id="chatForm" style="display:none">
      <input class="input" id="chatText" placeholder="پیامت را بنویس…" autocomplete="off" maxlength="2000">
      <button class="btn btn-gold btn-icon" type="submit"><?= icon('send',18) ?></button>
    </form>
  </section>
</div>
<script>
  window.API_MSG = '<?= url('api/messages.php') ?>';
  window.NOTIF_URL='<?= url('api/notifications.php') ?>';
  window.NOTIF_READ_URL='<?= url('api/notifications.php?read=1') ?>';
  window.INIT_WITH = <?= $with ?: 'null' ?>;
  window.MY_ROLE = '<?= e($role) ?>';
</script>
<?php
}
