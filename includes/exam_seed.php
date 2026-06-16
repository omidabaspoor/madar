<?php
/** ساخت آزمون جامع نمونه (قابل استفاده از install یا seeder مستقل) */
declare(strict_types=1);

function seed_sample_exam(PDO $pdo, int $advisorId, array $subjIds = []): int
{
    // اگر آزمونی با همین عنوان هست، دوباره نساز
    $chk = $pdo->prepare('SELECT id FROM exams WHERE title=? LIMIT 1');
    $chk->execute(['آزمون جامع نمونه']);
    if ($chk->fetch()) return 0;

    $pdo->prepare('INSERT INTO exams (advisor_id,title,description,exam_type,timing_mode,duration_min,negative_marking,show_review,status) VALUES (?,?,?,?,?,?,?,?,?)')
        ->execute([$advisorId,'آزمون جامع نمونه','شامل شیمی، ریاضی و ادبیات — با چند سوال تصویری','comprehensive','total',40,1,1,'published']);
    $exId = (int)$pdo->lastInsertId();

    $secIns = $pdo->prepare('INSERT INTO exam_sections (exam_id,subject_id,name,sort_order) VALUES (?,?,?,?)');
    $secIns->execute([$exId, $subjIds['شیمی'] ?? null, 'شیمی', 0]);   $secChem = (int)$pdo->lastInsertId();
    $secIns->execute([$exId, $subjIds['ریاضی'] ?? null, 'ریاضی', 1]); $secMath = (int)$pdo->lastInsertId();
    $secIns->execute([$exId, $subjIds['ادبیات'] ?? null, 'ادبیات', 2]); $secLit = (int)$pdo->lastInsertId();

    $qIns = $pdo->prepare('INSERT INTO exam_questions (exam_id,section_id,q_text,q_image,opt1,opt2,opt3,opt4,correct_opt,explanation,sort_order) VALUES (?,?,?,?,?,?,?,?,?,?,?)');

    // [section, text, image|null, o1,o2,o3,o4, correct, explanation]
    $Q = [
        // ---- شیمی ----
        [$secChem,'عدد اتمی عنصری که در هسته‌ی خود ۱۷ پروتون دارد، کدام است؟',null,'۱۵','۱۶','۱۷','۱۸',3,'عدد اتمی برابر تعداد پروتون‌های هسته است؛ پس ۱۷.'],
        [$secChem,'کدام یک از موارد زیر جزو گازهای نجیب (بی‌اثر) است؟',null,'اکسیژن','نئون','نیتروژن','هیدروژن',2,'نئون در گروه ۱۸ جدول تناوبی و از گازهای نجیب است.'],
        [$secChem,'با توجه به شکل مولکول زیر، زاویه‌ی پیوند در مولکول آب تقریباً چند درجه است؟','uploads/exams/sample_molecule.png','۹۰','۱۰۴٫۵','۱۲۰','۱۸۰',2,'زاویه‌ی پیوند H–O–H در آب حدود ۱۰۴٫۵ درجه است.'],
        [$secChem,'فرمول شیمیایی آب اکسیژنه کدام است؟',null,'H₂O','H₂O₂','HO₂','H₃O',2,'آب اکسیژنه (پراکسید هیدروژن) با فرمول H₂O₂ است.'],
        [$secChem,'تعداد الکترون‌های ظرفیت اکسیژن (Z=۸) چند است؟',null,'۴','۵','۶','۸',3,'آرایش اکسیژن ۲و۶ است؛ لایه‌ی آخر ۶ الکترون دارد.'],

        // ---- ریاضی ----
        [$secMath,'حاصل عبارت ۲ به توان ۵ کدام است؟',null,'۱۶','۳۲','۶۴','۸',2,'۲⁵ = ۳۲'],
        [$secMath,'با توجه به مثلث قائم‌الزاویه‌ی زیر، رابطه‌ی فیثاغورس کدام است؟','uploads/exams/sample_triangle.png','c = a + b','c² = a² + b²','c² = a² − b²','c = a² + b²',2,'در مثلث قائم‌الزاویه، مربع وتر برابر مجموع مربع دو ضلع دیگر است: c²=a²+b²'],
        [$secMath,'مشتق تابع f(x) = x² کدام است؟',null,'x','2x','x²','۲',2,'مشتق x² برابر 2x است.'],
        [$secMath,'با توجه به نمودار میله‌ای زیر، مقدار ستون D کدام است؟','uploads/exams/sample_chart.png','۱۰','۲۰','۳۰','۴۰',4,'طبق نمودار، بلندترین ستون (D) مقدار ۴۰ را نشان می‌دهد.'],
        [$secMath,'اگر ۳x = ۱۲ باشد، مقدار x کدام است؟',null,'۳','۴','۵','۶',2,'x = ۱۲ ÷ ۳ = ۴'],

        // ---- ادبیات ----
        [$secLit,'«مرا در منزل جانان چه امن عیش چون هر دم / جرس فریاد می‌دارد که بربندید محمل‌ها» از کیست؟',null,'سعدی','حافظ','مولوی','فردوسی',2,'این بیت از غزل معروف حافظ شیرازی است.'],
        [$secLit,'آرایه‌ی ادبی به‌کاررفته در «دل می‌رود ز دستم» کدام است؟',null,'تشبیه','کنایه','استعاره','جناس',2,'«دل از دست رفتن» کنایه از بی‌قراری و عاشق‌شدن است.'],
        [$secLit,'کدام واژه هم‌خانواده‌ی «علم» است؟',null,'عالَم','عالِم','اَلَم','عَلَم',2,'«عالِم» (دانا) با «علم» هم‌خانواده است.'],
        [$secLit,'«سپید» متضاد کدام واژه است؟',null,'روشن','سیاه','زرد','شفاف',2,'متضاد سپید (سفید)، سیاه است.'],
    ];

    $so = 0; $n = 0;
    foreach ($Q as $q) {
        $qIns->execute([$exId,$q[0],$q[1],$q[2],$q[3],$q[4],$q[5],$q[6],(int)$q[7],$q[8] ?? null,$so++]);
        $n++;
    }
    return $n;
}
