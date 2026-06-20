<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/models.php';
require_once __DIR__ . '/../includes/panel_layout.php';
require_once __DIR__ . '/../includes/log.php';

boot_session();
require_role('admin');

$logs = get_recent_logs(100);

panel_start('لاگ فعالیت‌ها', '', 'admin', 'logs');
?>

<div class="card">
    <h2 style="margin-bottom:20px">لاگ فعالیت‌های سامانه (۱۰۰ مورد آخر)</h2>
    
    <table class="table">
        <thead>
            <tr>
                <th>زمان</th>
                <th>کاربر</th>
                <th>عملیات</th>
                <th>جزئیات</th>
                <th>IP</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($logs as $log): ?>
            <tr>
                <td style="white-space:nowrap"><?= jalali_date($log['created_at'], true) ?></td>
                <td>
                    <strong><?= e($log['user_name'] ?? 'ناشناس') ?></strong><br>
                    <small class="muted"><?= e($log['user_role'] ?? '') ?></small>
                </td>
                <td>
                    <span class="badge badge-sage"><?= e($log['action']) ?></span>
                </td>
                <td>
                    <?php 
                    $details = $log['details'] ? json_decode($log['details'], true) : null;
                    if ($details) {
                        echo '<code>' . e(json_encode($details, JSON_UNESCAPED_UNICODE)) . '</code>';
                    } else {
                        echo '-';
                    }
                    ?>
                </td>
                <td><small><?= e($log['ip_address'] ?? '-') ?></small></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php panel_end(); ?>