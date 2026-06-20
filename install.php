<?php
/**
 * نصب‌کننده و همگام‌ساز هوشمند مَدار — جداول را می‌سازد، به‌روزرسانی می‌کند و داده‌ی نمونه می‌ریزد.
 * برای ارتقای پایگاه داده در هر زمان، کافی است این فایل را در مرورگر باز کرده و اجرا کنید.
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

/** اجرای فایل SQL چنددستوری؛ برای seedهای نصب/ارتقا */
function execute_sql_file(PDO $pdo, string $path): int {
    if (!is_file($path)) return 0;
    $sql = file_get_contents($path);
    if ($sql === false || trim($sql) === '') return 0;

    // حذف کامنت‌های خطی تا split روی ; تمیزتر انجام شود.
    $sql = preg_replace('/^\s*--.*$/m', '', $sql) ?? $sql;
    $statements = array_filter(array_map('trim', explode(';', $sql)));
    $done = 0;
    foreach ($statements as $stmt) {
        if ($stmt === '') continue;
        $pdo->exec($stmt);
        $done++;
    }
    return $done;
}

function synchronize_database_schema(PDO $pdo, array &$messages): void {
    // 1. Create any missing tables or execute schema.sql in chunks
    $schemaSql = file_get_contents(__DIR__ . '/sql/schema.sql');
    $statements = array_filter(array_map('trim', explode(';', $schemaSql)));
    foreach ($statements as $stmt) {
        if ($stmt === '') continue;
        try {
            $pdo->exec($stmt);
        } catch (Throwable $e) {
            // Ignore minor execution notices
        }
    }
    $messages[] = 'ساختار جداول اصلی دیتابیس ایجاد/بررسی شد.';

    // 2a. Ensure chapters table exists (if upgrading from older install)
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS chapters (
          id INT UNSIGNED NOT NULL AUTO_INCREMENT,
          subject_name VARCHAR(80) NOT NULL,
          grade INT UNSIGNED NOT NULL,
          field VARCHAR(30) NOT NULL,
          book_name VARCHAR(120) NOT NULL,
          chapter_name VARCHAR(200) NOT NULL,
          sort_order INT UNSIGNED NOT NULL DEFAULT 0,
          is_system TINYINT(1) NOT NULL DEFAULT 1,
          advisor_id INT UNSIGNED DEFAULT NULL,
          is_active TINYINT(1) NOT NULL DEFAULT 1,
          created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
          updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          PRIMARY KEY (id),
          KEY idx_chap_subject (subject_name, grade, field, is_active),
          KEY idx_chap_advisor (advisor_id),
          KEY idx_chap_book (book_name, sort_order)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    } catch (Throwable $e) {}

    // 2. Ensure all columns exist in existing tables (Self-Healing Synchronization)
    $tableColumns = [
        'tasks' => [
            'source'            => "VARCHAR(120) DEFAULT NULL AFTER description",
            'completion_status' => "ENUM('pending','full','partial','missed') NOT NULL DEFAULT 'pending' AFTER is_done",
            'course_percent'    => "TINYINT UNSIGNED DEFAULT NULL AFTER completion_status",
            'student_feeling'   => "VARCHAR(30) DEFAULT NULL AFTER course_percent",
            'status_updated_at' => "DATETIME DEFAULT NULL AFTER completed_at",
        ],
        'users' => [
            'mood'            => "VARCHAR(20) DEFAULT NULL AFTER status",
            'mood_date'       => "DATE DEFAULT NULL AFTER mood",
            'streak'          => "INT UNSIGNED NOT NULL DEFAULT 0 AFTER mood_date",
            'last_active'     => "DATE DEFAULT NULL AFTER streak",
            'remember_token'  => "VARCHAR(64) DEFAULT NULL AFTER last_active",
        ],
        'subjects' => [
            'advisor_id' => "INT UNSIGNED DEFAULT NULL AFTER id",
            'icon'       => "VARCHAR(30) DEFAULT 'book' AFTER color",
        ],
        'messages' => [
            'attachment_type' => "VARCHAR(20) NOT NULL DEFAULT 'none' AFTER body",
            'attachment_path' => "VARCHAR(255) DEFAULT NULL AFTER attachment_type",
            'attachment_name' => "VARCHAR(190) DEFAULT NULL AFTER attachment_path",
            'attachment_mime' => "VARCHAR(80) DEFAULT NULL AFTER attachment_name",
            'attachment_size' => "INT UNSIGNED DEFAULT NULL AFTER attachment_mime",
        ],
        'planner_memory' => [
            'source' => "VARCHAR(120) DEFAULT NULL AFTER priority",
        ],
        'review_reminders' => [
            'student_id'       => "INT UNSIGNED NOT NULL DEFAULT 0 AFTER id",
            'source_task_id'   => "INT UNSIGNED NOT NULL DEFAULT 0 AFTER student_id",
            'subject_id'       => "INT UNSIGNED DEFAULT NULL AFTER source_task_id",
            'topic_title'      => "VARCHAR(180) NOT NULL DEFAULT '' AFTER subject_id",
            'source'           => "VARCHAR(160) DEFAULT NULL AFTER topic_title",
            'first_studied_at' => "DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER source",
            'interval_days'    => "INT UNSIGNED NOT NULL DEFAULT 1 AFTER first_studied_at",
            'review_no'        => "TINYINT UNSIGNED NOT NULL DEFAULT 1 AFTER interval_days",
            'profile_key'      => "VARCHAR(40) DEFAULT NULL AFTER review_no",
            'profile_label'    => "VARCHAR(80) DEFAULT NULL AFTER profile_key",
            'suggested_minutes'=> "INT UNSIGNED DEFAULT 15 AFTER profile_label",
            'due_date'         => "DATE NOT NULL DEFAULT '1970-01-01' AFTER suggested_minutes",
            'status'           => "ENUM('pending','done','dismissed') NOT NULL DEFAULT 'pending' AFTER due_date",
            'notified_at'      => "DATETIME DEFAULT NULL AFTER status",
            'completed_at'     => "DATETIME DEFAULT NULL AFTER notified_at",
            'quality'          => "ENUM('hard','good','easy') DEFAULT NULL AFTER completed_at",
        ],
        'exams' => [
            'creation_mode'    => "VARCHAR(30) NOT NULL DEFAULT 'standard' AFTER description",
            'sheet_path'       => "VARCHAR(255) DEFAULT NULL AFTER creation_mode",
            'sheet_paths_json' => "TEXT DEFAULT NULL AFTER sheet_path",
            'answer_key'       => "VARCHAR(500) DEFAULT NULL AFTER sheet_paths_json",
        ],
        'exam_answers' => [
            'diagnostic_reason'   => "VARCHAR(60) DEFAULT NULL AFTER flagged",
            'diagnostic_takeaway' => "VARCHAR(500) DEFAULT NULL AFTER diagnostic_reason",
        ],
    ];

    foreach ($tableColumns as $table => $cols) {
        try {
            $existing = [];
            $stmt = $pdo->query("SHOW COLUMNS FROM `$table`");
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $existing[$row['Field']] = true;
            }
            foreach ($cols as $col => $def) {
                if (empty($existing[$col])) {
                    try {
                        $pdo->exec("ALTER TABLE `$table` ADD COLUMN `$col` $def");
                    } catch (Throwable $e) {}
                }
            }
        } catch (Throwable $e) {}
    }

    // 3. Ensure task_type ENUM is fully up to date
    try {
        $pdo->exec("ALTER TABLE tasks MODIFY task_type ENUM('test','study','review','textbook','descriptive','exam','reading','custom','analysis','special','mock') NOT NULL DEFAULT 'study'");
    } catch (Throwable $e) {}

    // 4. Update legacy completion_status if needed
    try {
        $pdo->exec("UPDATE tasks SET completion_status=IF(is_done=1,'full','pending') WHERE completion_status IS NULL OR completion_status='' ");
    } catch (Throwable $e) {}

    $messages[] = 'همگام‌سازی و به‌روزرسانی فیلدهای پایگاه داده با موفقیت انجام شد.';
}

$run = ($_SERVER['REQUEST_METHOD'] === 'POST') || (PHP_SAPI === 'cli') || isset($_GET['update']);
if ($run) {
  try {
    // 1) ساخت دیتابیس در صورت نبود
    $root = pdo_root();
    $root->exec('CREATE DATABASE IF NOT EXISTS `'.DB_NAME.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    $messages[] = 'پایگاه داده بررسی شد: ' . DB_NAME;

    $pdo = new PDO(sprintf('mysql:host=%s;dbname=%s;charset=%s', DB_HOST, DB_NAME, DB_CHARSET), DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

    // 2) همگام‌سازی و ارتقای ساختار جداول (بدون از دست رفتن داده‌ها)
    synchronize_database_schema($pdo, $messages);

    // 2b) seed دقیق فصل‌های درسی از فایل SQL استخراج‌شده از HTML ارسالی
    $chapterSeedSql = __DIR__ . '/sql/seed_chapters_curriculum.sql';
    if (is_file($chapterSeedSql)) {
        execute_sql_file($pdo, $chapterSeedSql);
        $messages[] = 'فصل‌های درسی پایه‌های ۱۰ تا ۱۲ از فایل SQL در دیتابیس همگام شد.';
    }

    // 2c) اضافه کردن درس «ریاضی جامع» + فصل‌ها (هر بار که install.php اجرا شود)
    $riaziJameSql = __DIR__ . '/sql/upgrade_riazi_jame_chapters.sql';
    if (is_file($riaziJameSql)) {
        execute_sql_file($pdo, $riaziJameSql);
        $messages[] = 'درس و فصل‌های «ریاضی جامع» (از تصویر) به دیتابیس اضافه/به‌روزرسانی شد.';
    }

    // 2d) آپگرید سیستم چندمشاوری + لاگ فعالیت (جدید)
    $multiAdvisorSql = __DIR__ . '/sql/upgrade_multi_advisor_logs.sql';
    if (is_file($multiAdvisorSql)) {
        execute_sql_file($pdo, $multiAdvisorSql);
        $messages[] = 'سیستم چندمشاوری + لاگ فعالیت فعال شد.';
    }

    // 2e) آپگرید کنترل دسترسی مشاوران (جدید)
    $accessSql = __DIR__ . '/sql/upgrade_advisor_access.sql';
    if (is_file($accessSql)) {
        execute_sql_file($pdo, $accessSql);
        $messages[] = 'سیستم کنترل دسترسی مشاوران فعال شد.';
    }

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
            // درس جدید درخواست‌شده
            ['ریاضی جامع','#2E5A8C','target'],
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

        $doneAli = (int)$pdo->query('SELECT COALESCE(SUM(is_done),0) FROM tasks WHERE student_id='.$aliId)->fetchColumn();
        $aliStreak = (int)$pdo->query('SELECT streak FROM users WHERE id='.$aliId)->fetchColumn();
        foreach ($pdo->query('SELECT * FROM achievements WHERE is_active=1')->fetchAll() as $a) {
            $ok = ($a['condition_type']==='tasks_done' && $doneAli>=$a['threshold']) || ($a['condition_type']==='streak' && $aliStreak>=$a['threshold']);
            if ($ok) $pdo->prepare('INSERT IGNORE INTO student_achievements (student_id,achievement_id) VALUES (?,?)')->execute([$aliId,$a['id']]);
        }

        // آزمون جامع نمونه
        require_once __DIR__ . '/includes/exam_seed.php';
        seed_sample_exam($pdo, $advisorId, $subjIds);
        $messages[] = 'آزمون جامع نمونه ساخته شد.';
    }

    // فصل‌های درسی: منبع اصلی فایل SQL است؛ fallback فقط برای حالتی است که فایل SQL روی هاست نباشد.
    if (!is_file($chapterSeedSql)) {
        require_once __DIR__ . '/includes/models.php';
        require_once __DIR__ . '/includes/chapter_data.php';
        $seeded = seed_system_chapters();
        $messages[] = 'فصل‌های درسی PHP-seed بررسی شد؛ '.fa_num($seeded).' مورد تکمیلی اضافه شد.';
    }

    $messages[] = '✅ نصب و همگام‌سازی با موفقیت کامل شد!';
    $messages[] = 'ورود مشاور →  نام‌کاربری: <b>sajjad</b>  |  گذرواژه: <b>madar@1404</b>';
    $messages[] = 'ورود دانش‌آموز →  نام‌کاربری: <b>ali_rezaei</b>  |  گذرواژه: <b>student123</b>';
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
page_head('نصب و به‌روزرسانی مَدار');
?>
<div style="min-height:100vh;display:grid;place-items:center;padding:24px">
  <div class="card" style="max-width:560px;width:100%">
    <div class="brand" style="justify-content:center;margin-bottom:18px"><?= logo_svg(48) ?></div>
    <h1 class="text-c" style="font-size:1.6rem;margin-bottom:8px">نصب و ارتقای <span class="gradient-text"><?= e(APP_NAME) ?></span></h1>
    <p class="text-c muted" style="margin-bottom:24px">ایجاد پایگاه داده، به‌روزرسانی خودکار جداول و همگام‌سازی</p>
    <?php if ($err): ?>
      <div class="alert alert-error" style="margin-bottom:16px"><?= icon('info',18) ?><span><?= e($err) ?></span></div>
    <?php endif; ?>
    <?php if ($messages): ?>
      <?php foreach ($messages as $m): ?>
      <div class="alert alert-success" style="margin-bottom:10px"><?= icon('check',18) ?><span><?= $m ?></span></div>
      <?php endforeach; ?>
      <div class="alert alert-info" style="margin:16px 0"><?= icon('info',18) ?><span>برای امنیت بیشتر در محیط واقعی، فایل <b>install.php</b> را تغییرنام یا حذف کنید.</span></div>
      <a href="<?= url('') ?>" class="btn btn-gold btn-block btn-lg"><?= icon('rocket',18) ?> ورود به سامانه</a>
    <?php else: ?>
      <div class="alert alert-info" style="margin-bottom:16px"><?= icon('info',18) ?><span>این اسکریپت ساختار پایگاه داده را به‌صورت کامل و هوشمند (بدون حذف داده‌های قبلی) به‌روزرسانی می‌کند.</span></div>
      <form method="post"><button class="btn btn-gold btn-block btn-lg"><?= icon('zap',18) ?> شروع نصب و همگام‌سازی</button></form>
    <?php endif; ?>
  </div>
</div>
<?php page_foot(); ?>
