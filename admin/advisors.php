<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/models.php';
require_once __DIR__ . '/../includes/panel_layout.php';
require_once __DIR__ . '/../includes/log.php';

boot_session();
require_role('admin');

$u = current_user();
$adminId = (int)$u['id'];

$action = $_POST['action'] ?? '';
$msg = null;
$err = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    // Create Advisor
    if ($action === 'create_advisor') {
        $full_name = trim($_POST['full_name'] ?? '');
        $username  = trim($_POST['username'] ?? '');
        $password  = $_POST['password'] ?? '';

        if (!$full_name || !$username || !$password) {
            $err = 'تمام فیلدها الزامی است.';
        } elseif (strlen($password) < 6) {
            $err = 'رمز عبور حداقل ۶ کاراکتر.';
        } else {
            $chk = db()->prepare("SELECT id FROM users WHERE username = ?");
            $chk->execute([$username]);
            if ($chk->fetch()) {
                $err = 'نام کاربری تکراری است.';
            } else {
                $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => BCRYPT_COST]);
                db()->prepare("INSERT INTO users (role, full_name, username, password_hash, status, field, access_mode) 
                               VALUES ('advisor', ?, ?, ?, 'active', 'مشاور کنکور', 'all')")
                    ->execute([$full_name, $username, $hash]);
                $newId = (int)db()->lastInsertId();
                log_activity($adminId, 'advisor_created', 'user', $newId, ['full_name' => $full_name]);
                $msg = 'مشاور جدید با موفقیت ایجاد شد.';
            }
        }
    }

    // Toggle Status
    if ($action === 'toggle_status') {
        $id = (int)($_POST['id'] ?? 0);
        $status = $_POST['status'] ?? 'active';
        if ($id && in_array($status, ['active','suspended'])) {
            db()->prepare("UPDATE users SET status = ? WHERE id = ?")->execute([$status, $id]);
            log_activity($adminId, 'advisor_status_changed', 'user', $id, ['status' => $status]);
            $msg = 'وضعیت تغییر کرد.';
        }
    }

    // Toggle Access Mode
    if ($action === 'toggle_access') {
        $id = (int)($_POST['id'] ?? 0);
        $mode = $_POST['mode'] ?? 'all';
        if ($id && in_array($mode, ['all','restricted'])) {
            db()->prepare("UPDATE users SET access_mode = ? WHERE id = ?")->execute([$mode, $id]);
            log_activity($adminId, 'advisor_access_changed', 'user', $id, ['mode' => $mode]);
            $msg = 'نوع دسترسی تغییر کرد.';
        }
    }

    // Delete Advisor
    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id && $id !== $adminId) {
            db()->prepare("UPDATE users SET advisor_id = NULL WHERE advisor_id = ?")->execute([$id]);
            db()->prepare("DELETE FROM users WHERE id = ?")->execute([$id]);
            log_activity($adminId, 'advisor_deleted', 'user', $id);
            $msg = 'مشاور حذف شد.';
        }
    }
}

// Get data
$advisors = db()->query("
    SELECT id, full_name, username, status, access_mode, created_at,
           (SELECT COUNT(*) FROM users WHERE advisor_id = u.id AND role = 'student') AS student_count
    FROM users u 
    WHERE role = 'advisor' 
    ORDER BY created_at DESC
")->fetchAll();

$total = count($advisors);
$active = count(array_filter($advisors, fn($a) => $a['status'] === 'active'));
$restrictedCount = count(array_filter($advisors, fn($a) => $a['access_mode'] === 'restricted'));

panel_start('مدیریت مشاوران', '', 'admin', 'advisors');
?>

<div class="max-w-[1180px] mx-auto px-4">
    <!-- Header -->
    <div class="flex justify-between items-end mb-8">
        <div>
            <h1 class="text-3xl font-bold tracking-tight">مدیریت مشاوران</h1>
            <p class="text-slate-500 mt-1">کنترل کامل وضعیت و دسترسی مشاوران</p>
        </div>
        <button onclick="document.getElementById('createModal').showModal()" 
                class="btn btn-gold px-6 py-2.5 flex items-center gap-2 text-base shadow-sm">
            <?= icon('user-plus', 18) ?>
            <span>مشاور جدید</span>
        </button>
    </div>

    <!-- Stats -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-8">
        <div class="card p-6 flex items-center gap-4">
            <div class="w-12 h-12 rounded-2xl bg-blue-100 flex items-center justify-center text-blue-600">
                <?= icon('users', 24) ?>
            </div>
            <div>
                <div class="text-4xl font-semibold"><?= fa_num($total) ?></div>
                <div class="text-sm text-slate-500">کل مشاوران</div>
            </div>
        </div>
        <div class="card p-6 flex items-center gap-4">
            <div class="w-12 h-12 rounded-2xl bg-emerald-100 flex items-center justify-center text-emerald-600">
                <?= icon('check-circle', 24) ?>
            </div>
            <div>
                <div class="text-4xl font-semibold"><?= fa_num($active) ?></div>
                <div class="text-sm text-slate-500">مشاور فعال</div>
            </div>
        </div>
        <div class="card p-6 flex items-center gap-4">
            <div class="w-12 h-12 rounded-2xl bg-orange-100 flex items-center justify-center text-orange-600">
                <?= icon('shield', 24) ?>
            </div>
            <div>
                <div class="text-4xl font-semibold"><?= fa_num($restrictedCount) ?></div>
                <div class="text-sm text-slate-500">دسترسی محدود</div>
            </div>
        </div>
    </div>

    <!-- Messages -->
    <?php if ($msg): ?><div class="alert alert-success mb-6"><?= e($msg) ?></div><?php endif; ?>
    <?php if ($err): ?><div class="alert alert-error mb-6"><?= e($err) ?></div><?php endif; ?>

    <!-- Advisors Table -->
    <div class="card p-0 overflow-hidden border border-slate-200 shadow-sm">
        <table class="w-full">
            <thead>
                <tr class="bg-slate-50 border-b">
                    <th class="px-6 py-4 text-right font-semibold text-slate-600">مشاور</th>
                    <th class="px-6 py-4 text-right font-semibold text-slate-600">نام کاربری</th>
                    <th class="px-6 py-4 text-center font-semibold text-slate-600">دانش‌آموزان</th>
                    <th class="px-6 py-4 text-center font-semibold text-slate-600">وضعیت</th>
                    <th class="px-6 py-4 text-center font-semibold text-slate-600">دسترسی</th>
                    <th class="px-6 py-4 text-right font-semibold text-slate-600">تاریخ ثبت</th>
                    <th class="px-6 py-4 w-56 text-center font-semibold text-slate-600">عملیات</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <?php if (empty($advisors)): ?>
                    <tr>
                        <td colspan="7" class="px-6 py-20 text-center">
                            <div class="text-slate-400">
                                <?= icon('users', 56) ?>
                                <p class="mt-4 text-lg">هنوز هیچ مشاوری ثبت نشده است</p>
                            </div>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($advisors as $adv): ?>
                    <tr class="hover:bg-slate-50 transition-colors">
                        <td class="px-6 py-5">
                            <div class="flex items-center gap-3">
                                <div class="w-9 h-9 rounded-full bg-blue-100 flex items-center justify-center text-blue-700 font-bold text-sm flex-shrink-0">
                                    <?= e(avatar_letters($adv['full_name'])) ?>
                                </div>
                                <div class="font-medium"><?= e($adv['full_name']) ?></div>
                            </div>
                        </td>
                        <td class="px-6 py-5">
                            <code class="bg-slate-100 px-3 py-1 rounded text-sm"><?= e($adv['username']) ?></code>
                        </td>
                        <td class="px-6 py-5 text-center">
                            <span class="inline-block px-3 py-0.5 text-xs bg-emerald-100 text-emerald-700 rounded-full">
                                <?= fa_num($adv['student_count']) ?> نفر
                            </span>
                        </td>
                        <td class="px-6 py-5 text-center">
                            <?php if ($adv['status'] === 'active'): ?>
                                <span class="inline-block px-3 py-0.5 text-xs bg-emerald-100 text-emerald-700 rounded-full">فعال</span>
                            <?php else: ?>
                                <span class="inline-block px-3 py-0.5 text-xs bg-amber-100 text-amber-700 rounded-full">مسدود</span>
                            <?php endif; ?>
                        </td>
                        <td class="px-6 py-5 text-center">
                            <?php if ($adv['access_mode'] === 'all'): ?>
                                <span class="inline-block px-3 py-0.5 text-xs bg-blue-100 text-blue-700 rounded-full">همه دانش‌آموزان</span>
                            <?php else: ?>
                                <span class="inline-block px-3 py-0.5 text-xs bg-orange-100 text-orange-700 rounded-full">محدود</span>
                            <?php endif; ?>
                        </td>
                        <td class="px-6 py-5 text-sm text-slate-500">
                            <?= jalali_date($adv['created_at']) ?>
                        </td>
                        <td class="px-6 py-5">
                            <div class="flex justify-center gap-1.5">
                                <!-- Status Toggle -->
                                <form method="post" class="inline">
                                    <input type="hidden" name="action" value="toggle_status">
                                    <input type="hidden" name="id" value="<?= $adv['id'] ?>">
                                    <input type="hidden" name="status" value="<?= $adv['status']==='active' ? 'suspended' : 'active' ?>">
                                    <?= csrf_field(); ?>
                                    <button class="btn btn-sm btn-ghost px-2 py-1.5" title="تغییر وضعیت">
                                        <?= $adv['status']==='active' ? icon('lock', 14) : icon('unlock', 14) ?>
                                    </button>
                                </form>

                                <!-- Access Toggle -->
                                <form method="post" class="inline">
                                    <input type="hidden" name="action" value="toggle_access">
                                    <input type="hidden" name="id" value="<?= $adv['id'] ?>">
                                    <input type="hidden" name="mode" value="<?= $adv['access_mode']==='all' ? 'restricted' : 'all' ?>">
                                    <?= csrf_field(); ?>
                                    <button class="btn btn-sm btn-ghost px-2 py-1.5" title="تغییر دسترسی">
                                        <?= $adv['access_mode']==='all' ? icon('shield', 14) : icon('unlock', 14) ?>
                                    </button>
                                </form>

                                <!-- View Students -->
                                <a href="students.php?advisor=<?= $adv['id'] ?>" class="btn btn-sm btn-ghost px-2 py-1.5" title="دانش‌آموزان">
                                    <?= icon('users', 14) ?>
                                </a>

                                <!-- Delete -->
                                <?php if ($adv['id'] != $adminId): ?>
                                <form method="post" class="inline" onsubmit="return confirm('آیا از حذف این مشاور مطمئن هستید؟')">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= $adv['id'] ?>">
                                    <?= csrf_field(); ?>
                                    <button class="btn btn-sm btn-ghost px-2 py-1.5 text-red-500" title="حذف">
                                        <?= icon('trash-2', 14) ?>
                                    </button>
                                </form>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Create Advisor Modal -->
<dialog id="createModal" class="modal max-w-md">
    <div class="modal-head px-6 pt-6">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 bg-blue-100 rounded-2xl flex items-center justify-center text-blue-600">
                <?= icon('user-plus', 20) ?>
            </div>
            <div>
                <h3 class="font-semibold text-xl">افزودن مشاور جدید</h3>
                <p class="text-sm text-slate-500">دسترسی پیش‌فرض: همه دانش‌آموزان</p>
            </div>
        </div>
        <button onclick="document.getElementById('createModal').close()" class="modal-close">×</button>
    </div>

    <form method="post" class="p-6">
        <input type="hidden" name="action" value="create_advisor">
        <?= csrf_field(); ?>

        <div class="space-y-5">
            <div>
                <label class="block text-sm font-medium mb-1.5">نام و نام خانوادگی</label>
                <input type="text" name="full_name" class="input w-full" placeholder="دکتر رضا احمدی" required>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium mb-1.5">نام کاربری</label>
                    <input type="text" name="username" class="input w-full" placeholder="reza_ahmadi" required>
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1.5">رمز عبور</label>
                    <input type="password" name="password" class="input w-full" placeholder="حداقل ۶ کاراکتر" minlength="6" required>
                </div>
            </div>
        </div>

        <div class="modal-footer mt-7">
            <button type="button" onclick="document.getElementById('createModal').close()" class="btn btn-ghost px-6">انصراف</button>
            <button type="submit" class="btn btn-gold px-7">ایجاد مشاور</button>
        </div>
    </form>
</dialog>

<?php panel_end(); ?>