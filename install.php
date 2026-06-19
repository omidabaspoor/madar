<?php
/**
 * نصب‌کننده‌ی مَدار — جداول را می‌سازد و داده‌ی نمونه می‌ریزد.
 * پس از اجرا، این فایل را حذف یا تغییرنام بدهید.
 */
declare(strict_types=1);
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/helpers.php';

$messages = [];
$err = null;

function pdo_root(): PDO {
    return new PDO(sprintf('mysql:host=%s;charset=%s', DB_HOST, DB_CHARSET), DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
}

$run = ($_SERVER['REQUEST_METHOD'] === 'POST') || (PHP_SAPI === 'cli');
if ($run) {
  try {
    // 1) ساخت دیتابیس
    $root = pdo_root();
    $root->exec('CREATE DATABASE IF NOT EXISTS `'.DB_NAME.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    $messages[] = 'پایگاه داده ساخته شد: ' . DB_NAME;

    $pdo = new PDO(sprintf('mysql:host=%s;dbname=%s;charset=%s', DB_HOST, DB_NAME, DB_CHARSET), DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

    // 2) اجرای schema
    $sql = file_get_contents(__DIR__ . '/sql/schema.sql');
    $pdo->exec($sql);
    $messages[] = 'جداول اصلی ایجاد/به‌روزرسانی شدند.';

    // 2b) اجرای همه‌ی مهاجرت‌ها (idempotent) — حتی روی نصب‌های قبلی
    //     این کار باعث می‌شود با باز کردن install.php دیتابیس کامل به‌روز شود.
    $migrations = [
        'upgrade_task_types.sql'       => 'نوع‌های جدید تسک (کتاب درسی، تشریحی)',
        'upgrade_task_types2.sql'      => 'نوع‌های تسک (تحلیل آزمون، واحد ویژه، آزمون) + ستون منبع',
        'upgrade_subject_palette.sql'  => 'پالت رنگ درس‌ها',
        'upgrade_messages_media.sql'   => 'پیوست رسانه در پیام‌ها',
        'upgrade_achievements.sql'     => 'سیستم دستاوردها',
        'upgrade_exams.sql'            => 'سیستم آزمون',
        'upgrade_planner_settings.sql' => 'پیش‌فرض‌های برنامه‌ریز + حافظه‌ی هوشمند',
        'upgrade_task_status.sql'      => 'وضعیت سه‌حالته تسک، درصد کورس و حس دانش‌آموز',
        'upgrade_mood_date.sql'       => 'تاریخ روزانه حال دانش‌آموز',
        'upgrade_student_reports.sql' => 'سیستم گزارش‌دهی پیشرفته دانش‌آموز',
        'upgrade_review_reminders.sql' => 'یادآور مرورهای فاصله‌دار',
    ];
    foreach ($migrations as $file => $label) {
        $path = __DIR__ . '/sql/' . $file;
        if (!is_file($path)) continue;
        try {
            $pdo->exec(file_get_contents($path));
            $messages[] = 'مهاجرت اعمال شد: ' . $label;
        } catch (Throwable $mEx) {
            // برخی مهاجرت‌ها ممکن است قبلاً اعمال شده باشند؛ نادیده بگیر
            $messages[] = 'مهاجرت «' . $label . '» رد شد (احتمالاً از قبل اعمال شده).';
        }
    }

    // 2b-2) اطمینان قطعی از نوع‌های تسک و ستون منبع (idempotent، حتی اگر مهاجرت ناقص اجرا شد)
    try {
        $pdo->exec("ALTER TABLE tasks MODIFY task_type ENUM('test','study','review','textbook','descriptive','exam','reading','custom','analysis','special','mock') NOT NULL DEFAULT 'study'");
        $messages[] = 'نوع‌های تسک به‌روزرسانی شدند.';
    } catch (Throwable $e) { /* احتمالاً از قبل */ }
    try {
        $col = $pdo->query("SHOW COLUMNS FROM tasks LIKE 'source'")->fetch();
        if (!$col) { $pdo->exec("ALTER TABLE tasks ADD COLUMN source VARCHAR(120) DEFAULT NULL AFTER description"); }
        $messages[] = 'ستون «منبع» آماده است.';
    } catch (Throwable $e) { /* ignore */ }


    try {
        $cols = [];
        foreach ($pdo->query('SHOW COLUMNS FROM tasks')->fetchAll() as $cc) { $cols[$cc['Field']] = true; }
        if (empty($cols['completion_status'])) $pdo->exec("ALTER TABLE tasks ADD COLUMN completion_status ENUM('pending','full','partial','missed') NOT NULL DEFAULT 'pending' AFTER is_done");
        if (empty($cols['course_percent'])) $pdo->exec("ALTER TABLE tasks ADD COLUMN course_percent TINYINT UNSIGNED DEFAULT NULL AFTER completion_status");
        if (empty($cols['student_feeling'])) $pdo->exec("ALTER TABLE tasks ADD COLUMN student_feeling VARCHAR(30) DEFAULT NULL AFTER course_percent");
        if (empty($cols['status_updated_at'])) $pdo->exec("ALTER TABLE tasks ADD COLUMN status_updated_at DATETIME DEFAULT NULL AFTER completed_at");
        $pdo->exec("UPDATE tasks SET completion_status=IF(is_done=1,'full','pending') WHERE completion_status IS NULL OR completion_status=''");
        $messages[] = 'سیستم وضعیت سه‌حالته تسک آماده است.';
    } catch (Throwable $e) { /* ignore */ }


    try {
        $mc = $pdo->query("SHOW COLUMNS FROM users LIKE 'mood_date'")->fetch();
        if (!$mc) $pdo->exec("ALTER TABLE users ADD COLUMN mood_date DATE DEFAULT NULL AFTER mood");
        $messages[] = 'تاریخ روزانه حال دانش‌آموز آماده است.';
    } catch (Throwable $e) { /* ignore */ }

    // 2c) اطمینان از وجود ستون‌ها/جداول کلیدیِ تنظیمات (پشتیبان در صورت نبود فایل مهاجرت)
    $pdo->exec("CREATE TABLE IF NOT EXISTS advisor_settings (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        advisor_id INT UNSIGNED NOT NULL,
        skey VARCHAR(60) NOT NULL,
        svalue VARCHAR(255) DEFAULT NULL,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id), UNIQUE KEY uq_advisor_key (advisor_id, skey), KEY idx_setting_advisor (advisor_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $pdo->exec("CREATE TABLE IF NOT EXISTS planner_memory (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        advisor_id INT UNSIGNED NOT NULL,
        scope VARCHAR(20) NOT NULL DEFAULT 'global',
        ctx_key VARCHAR(60) NOT NULL DEFAULT '*',
        task_type VARCHAR(20) DEFAULT NULL,
        subject_id INT UNSIGNED DEFAULT NULL,
        target_count INT DEFAULT NULL,
        target_unit VARCHAR(20) DEFAULT NULL,
        duration_min INT DEFAULT NULL,
        priority VARCHAR(10) DEFAULT NULL,
        source VARCHAR(120) DEFAULT NULL,
        hits INT UNSIGNED NOT NULL DEFAULT 1,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id), UNIQUE KEY uq_mem (advisor_id, scope, ctx_key), KEY idx_mem_advisor (advisor_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    try {
        $c = $pdo->query("SHOW COLUMNS FROM planner_memory LIKE 'source'")->fetch();
        if (!$c) { $pdo->exec("ALTER TABLE planner_memory ADD COLUMN source VARCHAR(120) DEFAULT NULL AFTER priority"); }
    } catch (Throwable $e) { /* ignore */ }
    $messages[] = 'جدول‌های تنظیمات و حافظه‌ی هوشمند آماده‌اند.';

    // 3) داده‌ی نمونه (فقط اگر کاربری نیست)
    $exists = (int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
    if ($exists === 0) {
        $hash = fn($p) => password_hash($p, PASSWORD_BCRYPT, ['cost' => BCRYPT_COST]);

        // مشاور
        $pdo->prepare('INSERT INTO users (role,full_name,username,password_hash,status,field) VALUES ("admin",?,?,?,"active","مشاور کنکور")')
            ->execute([APP_OWNER, 'sajjad', $hash('madar@1404')]);
        $advisorId = (int)$pdo->lastInsertId();

        // درس‌ها
        $subjects = [
            // اختصاصی تجربی
            ['ریاضی','#6E5B9A','target'], ['شیمی','#B58A45','book'], ['فیزیک','#3F7F9F','zap'], ['زیست‌شناسی','#3B8B5B','book'],
            // اختصاصی ریاضی
            ['حسابان','#6E5B9A','target'], ['هندسه','#4F8C86','target'], ['گسسته','#8A6A52','target'],
            // عمومی‌ها
            ['هویت','#6F6F78','user'], ['سلامت','#C06C84','heart'], ['عربی','#A0754C','book'], ['دینی','#7A5AA6','heart'], ['ادبیات','#9A5A8A','book'], ['زبان انگلیسی','#5578A6','globe'],
        ];
        $subjIds = [];
        $sIns = $pdo->prepare('INSERT INTO subjects (advisor_id,name,color,icon) VALUES (?,?,?,?)');
        foreach ($subjects as $s) { $sIns->execute([$advisorId,$s[0],$s[1],$s[2]]); $subjIds[$s[0]] = (int)$pdo->lastInsertId(); }

        // دانش‌آموزان
        $students = [
            ['علی رضایی','ali_rezaei','تجربی','کنکوری','active',12],
            ['مرادعلی یعقوبی','moradali','تجربی','دوازدهم','active',7],
            ['محمد فرزانی','m_farzani','ریاضی','کنکوری','active',4],
            ['غفور النجی','ghafoor','تجربی','یازدهم','active',9],
            ['سارا کریمی','sara_k','تجربی','کنکوری','active',15],
            ['نگار محمدی','negar_m','انسانی','دوازدهم','pending',0],
            ['حسین قاسمی','hossein_q','ریاضی','کنکوری','pending',0],
        ];
        $stIns = $pdo->prepare('INSERT INTO users (role,full_name,username,password_hash,status,field,grade,advisor_id,streak,last_active) VALUES ("student",?,?,?,?,?,?,?,?,?)');
        $studentIds = [];
        foreach ($students as $i=>$st) {
            $stIns->execute([$st[0],$st[1],$hash('student123'),$st[4],$st[2],$st[3],$advisorId,$st[5],$st[4]==='active'?date('Y-m-d'):null]);
            $studentIds[] = (int)$pdo->lastInsertId();
        }
        $aliId = $studentIds[0];

        // برنامه‌ی هفته‌ی جاری برای علی — برگرفته از اسکرین‌شات دکتر
        $weekStart = week_saturday();
        $pdo->prepare('INSERT INTO plans (student_id,advisor_id,week_start,title,status) VALUES (?,?,?,?,"published")')
            ->execute([$aliId,$advisorId,$weekStart,'برنامه هفتگی - تجربی']);
        $planId = (int)$pdo->lastInsertId();

        $tIns = $pdo->prepare('INSERT INTO tasks (plan_id,student_id,subject_id,title,task_type,day_index,unit_index,target_count,target_unit,is_done,done_count,completed_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)');
        // [day, unit, subjectName, title, type, target, unit]
        $plan = [
          // شنبه (day 0)
          [0,1,'زیست‌شناسی','زیست ف۴','test',40,'تست'],
          [0,2,'زیست‌شناسی','زیست ف۴','test',40,'تست'],
          [0,3,'شیمی','شیمی استوکیومتری','test',30,'تست'],
          [0,4,'فیزیک','فیزیک جبرانی ف۱','study',0,'درسنامه'],
          [0,5,'فیزیک','فیزیک ف۲ + ۳۰ تست','test',30,'تست'],
          [0,6,'فیزیک','فیزیک ف۲','study',0,'درسنامه'],
          [0,8,'','روزخوانی','reading',60,'دقیقه'],
          [0,8,'','آزمونک','exam',30,'دقیقه'],
          // یکشنبه (day 1)
          [1,1,'زیست‌شناسی','زیست ف۴','test',40,'تست'],
          [1,2,'زیست‌شناسی','زیست ف۴','test',40,'تست'],
          [1,3,'شیمی','شیمی استوکیومتری','test',30,'تست'],
          [1,4,'شیمی','شیمی ساختار لوویس','study',30,'تست'],
          [1,5,'فیزیک','فیزیک ف۲ + ۳۰ تست','test',30,'تست'],
          [1,8,'','روزخوانی','reading',60,'دقیقه'],
          // دوشنبه (day 2)
          [2,1,'زیست‌شناسی','زیست ف۴','test',40,'تست'],
          [2,2,'زیست‌شناسی','زیست ف۷ کتاب درسی','study',0,'فصل'],
          [2,3,'شیمی','شیمی استوکیومتری','test',30,'تست'],
          [2,4,'شیمی','شیمی ساختار لوویس','test',30,'تست'],
          [2,5,'فیزیک','فیزیک ف۲ + ۳۰ تست','test',30,'تست'],
          [2,6,'ریاضی','ریاضی ف۲','test',35,'تست'],
          [2,8,'','روزخوانی','reading',60,'دقیقه'],
          // سه‌شنبه (day 3)
          [3,1,'زیست‌شناسی','زیست ف۷','test',40,'تست'],
          [3,2,'زیست‌شناسی','زیست ف۷','test',40,'تست'],
          [3,3,'شیمی','شیمی استوکیومتری','test',30,'تست'],
          [3,4,'ریاضی','ریاضی ف۳ + ۳۵ تست','test',35,'تست'],
          [3,5,'فیزیک','فیزیک ف۴ + ۳۰ تست','test',30,'تست'],
          [3,8,'','روزخوانی','reading',60,'دقیقه'],
          // چهارشنبه (day 4)
          [4,1,'زیست‌شناسی','زیست ف۷','test',40,'تست'],
          [4,2,'شیمی','شیمی استوکیومتری','test',30,'تست'],
          [4,3,'شیمی','شیمی غلط‌نامه','review',0,'مبحث'],
          [4,4,'شیمی','شیمی غلط‌نامه','review',0,'مبحث'],
          [4,5,'ریاضی','ریاضی ف۲ + ۳۵ تست','test',35,'تست'],
          [4,6,'ریاضی','ریاضی ف۳','test',35,'تست'],
          [4,8,'','روزخوانی','reading',60,'دقیقه'],
          // پنجشنبه (day 5)
          [5,1,'زیست‌شناسی','زیست ف۷','test',40,'تست'],
          [5,2,'شیمی','شیمی استوکیومتری','test',30,'تست'],
          [5,3,'شیمی','شیمی غلط‌نامه','review',0,'مبحث'],
          [5,4,'ریاضی','ریاضی ف۲ + ۳۵ تست','test',35,'تست'],
          [5,5,'ریاضی','ریاضی ف۳ + ۳۵ تست','test',35,'تست'],
          [5,8,'','روزخوانی','reading',60,'دقیقه'],
          // جمعه (day 6)
          [6,1,'شیمی','شیمی استوکیومتری','test',30,'تست'],
          [6,2,'ریاضی','ریاضی ف۲ + ۳۵ تست','test',35,'تست'],
          [6,3,'ریاضی','ریاضی ف','test',35,'تست'],
          [6,4,'ریاضی','ریاضی ف۳ + ۳۵ تست','test',35,'تست'],
          [6,5,'فیزیک','فیزیک ف۴ + ۳۰ تست','test',30,'تست'],
          [6,6,'فیزیک','فیزیک ف۴','test',30,'تست'],
          [6,8,'','روزخوانی','reading',60,'دقیقه'],
        ];
        $today = persian_day_index(date('Y-m-d'));
        foreach ($plan as $p) {
            $sid = $p[2] && isset($subjIds[$p[2]]) ? $subjIds[$p[2]] : null;
            // برخی تسک‌های روزهای گذشته را انجام‌شده فرض کن (برای دموی واقعی)
            $done = ($p[0] < $today) ? (rand(0,10) > 2 ? 1 : 0) : 0;
            $dc = $done ? ($p[5] ?: 1) : 0;
            $tIns->execute([$planId,$aliId,$sid,$p[3],$p[4],$p[0],$p[1],$p[5]?:null,$p[6],$done,$dc,$done?date('Y-m-d H:i:s'):null]);
        }
        $messages[] = 'برنامه‌ی نمونه‌ی هفتگی برای «علی رضایی» ساخته شد (' . count($plan) . ' تسک).';

        // برنامه‌های ساده برای چند دانش‌آموز دیگر
        foreach ([1,2,3,4] as $idx) {
            $sid = $studentIds[$idx];
            $pdo->prepare('INSERT INTO plans (student_id,advisor_id,week_start,status) VALUES (?,?,?,"published")')->execute([$sid,$advisorId,$weekStart]);
            $pid = (int)$pdo->lastInsertId();
            for ($d=0;$d<7;$d++) for ($cnt=0;$cnt<rand(2,4);$cnt++){
                $names = ['زیست ف۳','شیمی فصل ۲','فیزیک نوسان','ریاضی مشتق','ادبیات آرایه'];
                $nm = $names[array_rand($names)];
                $target = rand(20,40);
                $done = ($d < $today) ? (rand(0,10)>3?1:0) : 0;
                $dc = $done ? $target : 0;
                // ستون‌ها: plan_id,student_id,subject_id,title,task_type,day_index,unit_index,target_count,target_unit,is_done,done_count,completed_at
                $tIns->execute([$pid,$sid,null,$nm,'test',$d,(($cnt%7)+1),$target,'تست',$done,$dc,$done?date('Y-m-d H:i:s'):null]);
            }
        }
        $messages[] = 'برنامه‌های نمونه برای سایر دانش‌آموزان ساخته شد.';

        // چند پیام و اعلان
        $pdo->prepare('INSERT INTO messages (sender_id,receiver_id,body) VALUES (?,?,?)')->execute([$advisorId,$aliId,'سلام علی جان، برنامه‌ی این هفته رو گذاشتم. روزخوانی‌ها رو جدی بگیر 🙏']);
        $pdo->prepare('INSERT INTO messages (sender_id,receiver_id,body) VALUES (?,?,?)')->execute([$aliId,$advisorId,'سلام استاد، چشم حتماً. ممنونم 🌹']);
        $pdo->prepare('INSERT INTO notifications (user_id,title,body,type,link) VALUES (?,?,?,?,?)')->execute([$aliId,'برنامه‌ی جدید شما آماده شد 📅','برنامه‌ی این هفته منتشر شد.','calendar','student/plan.php']);
        $pdo->prepare('INSERT INTO notifications (user_id,title,body,type,link) VALUES (?,?,?,?,?)')->execute([$advisorId,'۲ درخواست عضویت جدید','منتظر تأیید شما هستند.','user','admin/students.php']);

        // دستاوردهای پیش‌فرض
        $achs = [
            ['شروع‌کننده','اولین تسکت را انجام دادی','rocket','tasks_done',1],
            ['استمرار','۳ روز پیاپی فعالیت','fire','streak',3],
            ['جنگجوی هفته','۷ روز استریک','fire','streak',7],
            ['نیم‌قرن','۵۰ تسک انجام‌شده','target','tasks_done',50],
            ['صدتایی','۱۰۰ تسک انجام‌شده','trophy','tasks_done',100],
            ['حرفه‌ای','۲۵۰ تسک انجام‌شده','star','tasks_done',250],
            ['وفادار','۳۰ روز استریک','heart','streak',30],
            ['منتخب مشاور','نشان ویژه از طرف مشاور','sparkles','manual',0],
        ];
        $aIns = $pdo->prepare('INSERT INTO achievements (advisor_id,title,description,icon,condition_type,threshold,sort_order) VALUES (?,?,?,?,?,?,?)');
        $so=0; foreach ($achs as $a) { $aIns->execute([$advisorId,$a[0],$a[1],$a[2],$a[3],$a[4],$so++]); }
        // ارزیابی برای علی (که تسک‌های انجام‌شده دارد)
        $doneAli = (int)$pdo->query('SELECT COALESCE(SUM(is_done),0) FROM tasks WHERE student_id='.$aliId)->fetchColumn();
        $aliStreak = (int)$pdo->query('SELECT streak FROM users WHERE id='.$aliId)->fetchColumn();
        foreach ($pdo->query('SELECT * FROM achievements WHERE is_active=1')->fetchAll() as $a) {
            $ok = ($a['condition_type']==='tasks_done' && $doneAli>=$a['threshold']) || ($a['condition_type']==='streak' && $aliStreak>=$a['threshold']);
            if ($ok) $pdo->prepare('INSERT IGNORE INTO student_achievements (student_id,achievement_id) VALUES (?,?)')->execute([$aliId,$a['id']]);
        }

        // آزمون جامع نمونه (شیمی + ریاضی + ادبیات) با چند سوال عکس‌دار
        require_once __DIR__ . '/includes/exam_seed.php';
        $seeded = seed_sample_exam($pdo, $advisorId, $subjIds);
        $messages[] = 'آزمون جامع نمونه ساخته شد (' . $seeded . ' سوال، ۳ درس، شامل سوالات عکس‌دار).';

        $messages[] = '✅ نصب کامل شد!';
        $messages[] = 'ورود مشاور →  نام‌کاربری: <b>sajjad</b>  |  گذرواژه: <b>madar@1404</b>';
        $messages[] = 'ورود دانش‌آموز →  نام‌کاربری: <b>ali_rezaei</b>  |  گذرواژه: <b>student123</b>';
    } else {
        $messages[] = 'کاربران از قبل وجود دارند؛ داده‌ی نمونه رد شد.';
    }
  } catch (Throwable $e) {
    $err = $e->getMessage();
  }
}

if (PHP_SAPI === 'cli') {
    foreach ($messages as $m) echo strip_tags($m) . "\n";
    if ($err) { echo "ERROR: $err\n"; exit(1); }
    exit(0);
}

require_once __DIR__ . '/includes/layout.php';
page_head('نصب مَدار');
?>
<div style="min-height:100vh;display:grid;place-items:center;padding:24px">
  <div class="card" style="max-width:560px;width:100%">
    <div class="brand" style="justify-content:center;margin-bottom:18px"><?= logo_svg(48) ?></div>
    <h1 class="text-c" style="font-size:1.6rem;margin-bottom:8px">نصب <span class="gradient-text"><?= e(APP_NAME) ?></span></h1>
    <p class="text-c muted" style="margin-bottom:24px">ساخت پایگاه داده و داده‌ی نمونه</p>
    <?php if ($err): ?>
      <div class="alert alert-error" style="margin-bottom:16px"><?= icon('info',18) ?><span><?= e($err) ?></span></div>
    <?php endif; ?>
    <?php if ($messages): ?>
      <?php foreach ($messages as $m): ?>
      <div class="alert alert-success" style="margin-bottom:10px"><?= icon('check',18) ?><span><?= $m ?></span></div>
      <?php endforeach; ?>
      <div class="alert alert-info" style="margin:16px 0"><?= icon('info',18) ?><span>برای امنیت، فایل <b>install.php</b> را حذف کنید.</span></div>
      <a href="<?= url('') ?>" class="btn btn-gold btn-block btn-lg"><?= icon('rocket',18) ?> ورود به سامانه</a>
    <?php else: ?>
      <div class="alert alert-info" style="margin-bottom:16px"><?= icon('info',18) ?><span>تنظیمات اتصال در <code>config/config.php</code> را بررسی کنید، سپس نصب را آغاز کنید.</span></div>
      <form method="post"><button class="btn btn-gold btn-block btn-lg"><?= icon('zap',18) ?> شروع نصب</button></form>
    <?php endif; ?>
  </div>
</div>
<?php page_foot(); ?>
