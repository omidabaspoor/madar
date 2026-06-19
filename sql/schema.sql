-- =============================================================
--  مَدار · Madar Study OS — Database Schema
--  Konkur Task Manager — Dr. Sajjad Sayyadi
--  MySQL 5.7+/8.0  | utf8mb4
-- =============================================================
SET NAMES utf8mb4;
SET time_zone = '+00:00';
SET FOREIGN_KEY_CHECKS = 0;

-- ----------------------------------------------------------
--  users : both advisors (doctors) and students
-- ----------------------------------------------------------
CREATE TABLE IF NOT EXISTS users (
  id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  role            ENUM('admin','advisor','student') NOT NULL DEFAULT 'student',
  full_name       VARCHAR(120) NOT NULL,
  email           VARCHAR(190) DEFAULT NULL,
  phone           VARCHAR(20)  DEFAULT NULL,
  username        VARCHAR(60)  NOT NULL,
  password_hash   VARCHAR(255) NOT NULL,
  avatar          VARCHAR(255) DEFAULT NULL,
  field           VARCHAR(60)  DEFAULT NULL,            -- رشته: تجربی/ریاضی/انسانی
  grade           VARCHAR(40)  DEFAULT NULL,            -- پایه
  advisor_id      INT UNSIGNED DEFAULT NULL,            -- مشاور این دانش‌آموز
  status          ENUM('pending','active','suspended') NOT NULL DEFAULT 'pending',
  mood            VARCHAR(20)  DEFAULT NULL,            -- حال امروز
  mood_date       DATE DEFAULT NULL,                       -- تاریخ ثبت حال روزانه
  streak          INT UNSIGNED NOT NULL DEFAULT 0,
  last_active      DATE DEFAULT NULL,
  remember_token  VARCHAR(64) DEFAULT NULL,
  created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_username (username),
  KEY idx_role (role),
  KEY idx_advisor (advisor_id),
  CONSTRAINT fk_user_advisor FOREIGN KEY (advisor_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------
--  subjects : درس‌ها (با رنگ برای UI)
-- ----------------------------------------------------------
CREATE TABLE IF NOT EXISTS subjects (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  advisor_id  INT UNSIGNED DEFAULT NULL,
  name        VARCHAR(80) NOT NULL,
  color       VARCHAR(9)  NOT NULL DEFAULT '#6b8872',
  icon        VARCHAR(30) DEFAULT 'book',
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_subj_advisor (advisor_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------
--  plans : هر هفته برنامه برای یک دانش‌آموز
-- ----------------------------------------------------------
CREATE TABLE IF NOT EXISTS plans (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  student_id  INT UNSIGNED NOT NULL,
  advisor_id  INT UNSIGNED NOT NULL,
  title       VARCHAR(140) NOT NULL DEFAULT 'برنامه هفتگی',
  week_start  DATE NOT NULL,                 -- شنبه آن هفته (میلادی معادل)
  note        TEXT DEFAULT NULL,
  status      ENUM('draft','published','archived') NOT NULL DEFAULT 'draft',
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_plan_student (student_id),
  KEY idx_plan_advisor (advisor_id),
  KEY idx_plan_week (week_start),
  CONSTRAINT fk_plan_student FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_plan_advisor FOREIGN KEY (advisor_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------
--  tasks : هر تسک داخل یک برنامه
--  day_index 0=شنبه ... 6=جمعه ، unit_index 1..7 ، 8=ویژه
-- ----------------------------------------------------------
CREATE TABLE IF NOT EXISTS tasks (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  plan_id       INT UNSIGNED NOT NULL,
  student_id    INT UNSIGNED NOT NULL,
  subject_id    INT UNSIGNED DEFAULT NULL,
  title         VARCHAR(160) NOT NULL,           -- مثلا «زیست ف۴»
  description   VARCHAR(255) DEFAULT NULL,
  source        VARCHAR(120) DEFAULT NULL,        -- منبع آزاد (کتاب، آزمون ماز، …)
  task_type     ENUM('test','study','review','textbook','descriptive','exam','reading','custom','analysis','special','mock') NOT NULL DEFAULT 'study',
  day_index     TINYINT UNSIGNED NOT NULL,       -- 0..6
  unit_index    TINYINT UNSIGNED NOT NULL DEFAULT 1, -- 1..8 (8=ویژه)
  target_count  INT UNSIGNED DEFAULT NULL,       -- مثلا 40 تست
  target_unit   VARCHAR(20) DEFAULT 'تست',        -- تست/صفحه/دقیقه/ساعت
  duration_min  INT UNSIGNED DEFAULT NULL,       -- مدت پیشنهادی به دقیقه
  priority      ENUM('low','normal','high') NOT NULL DEFAULT 'normal',
  sort_order    INT UNSIGNED NOT NULL DEFAULT 0,
  -- وضعیت تکمیل توسط دانش‌آموز
  done_count    INT UNSIGNED NOT NULL DEFAULT 0,
  is_done       TINYINT(1) NOT NULL DEFAULT 0,
  completion_status ENUM('pending','full','partial','missed') NOT NULL DEFAULT 'pending', -- وضعیت سه‌حالته/قرمز
  course_percent TINYINT UNSIGNED DEFAULT NULL,       -- درصد پوشش کورس توسط دانش‌آموز
  student_feeling VARCHAR(30) DEFAULT NULL,           -- حس دانش‌آموز برای تسک‌های خواندنی
  student_note  VARCHAR(500) DEFAULT NULL,
  completed_at  DATETIME DEFAULT NULL,
  status_updated_at DATETIME DEFAULT NULL,
  -- بازخورد مشاور
  advisor_feedback VARCHAR(500) DEFAULT NULL,
  feedback_at   DATETIME DEFAULT NULL,
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_task_plan (plan_id),
  KEY idx_task_student (student_id),
  KEY idx_task_day (plan_id, day_index, unit_index),
  CONSTRAINT fk_task_plan FOREIGN KEY (plan_id) REFERENCES plans(id) ON DELETE CASCADE,
  CONSTRAINT fk_task_student FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_task_subject FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------
--  messages : چت دوطرفه دکتر ↔ دانش‌آموز
-- ----------------------------------------------------------
CREATE TABLE IF NOT EXISTS messages (
  id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  sender_id       INT UNSIGNED NOT NULL,
  receiver_id     INT UNSIGNED NOT NULL,
  body            TEXT NOT NULL,
  attachment_type VARCHAR(20) NOT NULL DEFAULT 'none', -- none | image | audio | pdf | file
  attachment_path VARCHAR(255) DEFAULT NULL,
  attachment_name VARCHAR(190) DEFAULT NULL,
  attachment_mime VARCHAR(80) DEFAULT NULL,
  attachment_size INT UNSIGNED DEFAULT NULL,
  is_read         TINYINT(1) NOT NULL DEFAULT 0,
  created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_msg_pair (sender_id, receiver_id),
  KEY idx_msg_recv (receiver_id, is_read)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------
--  notifications
-- ----------------------------------------------------------
CREATE TABLE IF NOT EXISTS notifications (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id     INT UNSIGNED NOT NULL,
  title       VARCHAR(140) NOT NULL,
  body        VARCHAR(255) DEFAULT NULL,
  type        VARCHAR(30) NOT NULL DEFAULT 'info',
  link        VARCHAR(190) DEFAULT NULL,
  is_read     TINYINT(1) NOT NULL DEFAULT 0,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_notif_user (user_id, is_read)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------
--  daily_logs : لاگ روزانه برای استریک و نمودار
-- ----------------------------------------------------------
CREATE TABLE IF NOT EXISTS daily_logs (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  student_id    INT UNSIGNED NOT NULL,
  log_date      DATE NOT NULL,
  tasks_total   INT UNSIGNED NOT NULL DEFAULT 0,
  tasks_done    INT UNSIGNED NOT NULL DEFAULT 0,
  study_minutes INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  UNIQUE KEY uq_log (student_id, log_date),
  CONSTRAINT fk_log_student FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------
--  achievements : دستاوردهای قابل تعریف توسط مشاور
--  condition_type: tasks_done | streak | manual
-- ----------------------------------------------------------
CREATE TABLE IF NOT EXISTS achievements (
  id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  advisor_id      INT UNSIGNED DEFAULT NULL,
  title           VARCHAR(100) NOT NULL,
  description     VARCHAR(190) DEFAULT NULL,
  icon            VARCHAR(30) NOT NULL DEFAULT 'trophy',
  condition_type  ENUM('tasks_done','streak','manual') NOT NULL DEFAULT 'manual',
  threshold       INT UNSIGNED NOT NULL DEFAULT 0,
  sort_order      INT UNSIGNED NOT NULL DEFAULT 0,
  is_active       TINYINT(1) NOT NULL DEFAULT 1,
  created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_ach_advisor (advisor_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------
--  student_achievements : دستاوردهای کسب‌شده توسط هر دانش‌آموز
-- ----------------------------------------------------------
CREATE TABLE IF NOT EXISTS student_achievements (
  id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  student_id      INT UNSIGNED NOT NULL,
  achievement_id  INT UNSIGNED NOT NULL,
  awarded_by      INT UNSIGNED DEFAULT NULL,    -- مشاور (در حالت دستی) یا NULL برای خودکار
  earned_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_student_ach (student_id, achievement_id),
  KEY idx_sa_student (student_id),
  CONSTRAINT fk_sa_student FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_sa_ach FOREIGN KEY (achievement_id) REFERENCES achievements(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------
--  exams : آزمون (تکی یا جامع)
-- ----------------------------------------------------------
CREATE TABLE IF NOT EXISTS exams (
  id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  advisor_id      INT UNSIGNED NOT NULL,
  title           VARCHAR(160) NOT NULL,
  description     VARCHAR(255) DEFAULT NULL,
  exam_type       ENUM('single','comprehensive') NOT NULL DEFAULT 'single',
  timing_mode     ENUM('total','per_section') NOT NULL DEFAULT 'total',
  duration_min    INT UNSIGNED NOT NULL DEFAULT 60,    -- زمان کل (وقتی timing_mode=total)
  negative_marking TINYINT(1) NOT NULL DEFAULT 1,      -- نمره منفی کنکوری (هر ۳ غلط = ۱ درست)
  show_review     TINYINT(1) NOT NULL DEFAULT 1,       -- نمایش پاسخنامه بعد از آزمون
  shuffle_questions TINYINT(1) NOT NULL DEFAULT 0,
  start_at        DATETIME DEFAULT NULL,
  end_at          DATETIME DEFAULT NULL,
  status          ENUM('draft','published','closed') NOT NULL DEFAULT 'draft',
  assign_all      TINYINT(1) NOT NULL DEFAULT 1,       -- برای همه‌ی دانش‌آموزان
  created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_exam_advisor (advisor_id),
  KEY idx_exam_status (status),
  CONSTRAINT fk_exam_advisor FOREIGN KEY (advisor_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------
--  exam_sections : بخش‌های آزمون (هر بخش = یک درس)
-- ----------------------------------------------------------
CREATE TABLE IF NOT EXISTS exam_sections (
  id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  exam_id         INT UNSIGNED NOT NULL,
  subject_id      INT UNSIGNED DEFAULT NULL,
  name            VARCHAR(80) NOT NULL,                -- نام درس/بخش
  duration_min    INT UNSIGNED DEFAULT NULL,           -- زمان این بخش (وقتی per_section)
  sort_order      INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  KEY idx_sec_exam (exam_id),
  CONSTRAINT fk_sec_exam FOREIGN KEY (exam_id) REFERENCES exams(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------
--  exam_questions : سوالات چهارگزینه‌ای
-- ----------------------------------------------------------
CREATE TABLE IF NOT EXISTS exam_questions (
  id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  exam_id         INT UNSIGNED NOT NULL,
  section_id      INT UNSIGNED NOT NULL,
  q_text          TEXT DEFAULT NULL,
  q_image         VARCHAR(255) DEFAULT NULL,
  opt1            VARCHAR(500) DEFAULT NULL,
  opt2            VARCHAR(500) DEFAULT NULL,
  opt3            VARCHAR(500) DEFAULT NULL,
  opt4            VARCHAR(500) DEFAULT NULL,
  correct_opt     TINYINT UNSIGNED NOT NULL DEFAULT 1, -- 1..4
  explanation     TEXT DEFAULT NULL,                   -- پاسخ تشریحی
  sort_order      INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  KEY idx_q_exam (exam_id),
  KEY idx_q_section (section_id, sort_order),
  CONSTRAINT fk_q_exam FOREIGN KEY (exam_id) REFERENCES exams(id) ON DELETE CASCADE,
  CONSTRAINT fk_q_section FOREIGN KEY (section_id) REFERENCES exam_sections(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------
--  exam_attempts : شرکت دانش‌آموز در آزمون
-- ----------------------------------------------------------
CREATE TABLE IF NOT EXISTS exam_attempts (
  id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  exam_id         INT UNSIGNED NOT NULL,
  student_id      INT UNSIGNED NOT NULL,
  status          ENUM('in_progress','submitted') NOT NULL DEFAULT 'in_progress',
  started_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  submitted_at    DATETIME DEFAULT NULL,
  deadline_at     DATETIME DEFAULT NULL,               -- زمان پایان مجاز (برای تایمر امن سمت سرور)
  total_score     DECIMAL(6,2) DEFAULT NULL,           -- درصد کل
  correct_count   INT UNSIGNED DEFAULT 0,
  wrong_count     INT UNSIGNED DEFAULT 0,
  blank_count     INT UNSIGNED DEFAULT 0,
  PRIMARY KEY (id),
  UNIQUE KEY uq_attempt (exam_id, student_id),
  KEY idx_att_student (student_id),
  CONSTRAINT fk_att_exam FOREIGN KEY (exam_id) REFERENCES exams(id) ON DELETE CASCADE,
  CONSTRAINT fk_att_student FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------
--  exam_answers : پاسخ دانش‌آموز به هر سوال
-- ----------------------------------------------------------
CREATE TABLE IF NOT EXISTS exam_answers (
  id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  attempt_id      INT UNSIGNED NOT NULL,
  question_id     INT UNSIGNED NOT NULL,
  selected_opt    TINYINT UNSIGNED DEFAULT NULL,       -- 1..4 یا NULL (نزده)
  flagged         TINYINT(1) NOT NULL DEFAULT 0,       -- علامت‌گذاری برای مرور
  updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_answer (attempt_id, question_id),
  KEY idx_ans_attempt (attempt_id),
  CONSTRAINT fk_ans_attempt FOREIGN KEY (attempt_id) REFERENCES exam_attempts(id) ON DELETE CASCADE,
  CONSTRAINT fk_ans_question FOREIGN KEY (question_id) REFERENCES exam_questions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------
--  student_reports : گزارش‌های خودکار و پیشرفته دانش‌آموز
-- ----------------------------------------------------------
-- Upgrade: advanced student reporting system
CREATE TABLE IF NOT EXISTS student_reports (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  student_id INT UNSIGNED NOT NULL,
  report_type ENUM('daily','weekly','monthly') NOT NULL DEFAULT 'daily',
  period_start DATE NOT NULL,
  period_end DATE NOT NULL,
  auto_snapshot_json LONGTEXT NULL,
  advanced_json LONGTEXT NULL,
  status ENUM('draft','submitted') NOT NULL DEFAULT 'draft',
  submitted_at DATETIME DEFAULT NULL,
  advisor_note TEXT NULL,
  reviewed_by INT UNSIGNED DEFAULT NULL,
  reviewed_at DATETIME DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_student_report (student_id, report_type, period_start),
  KEY idx_report_student (student_id, report_type, period_start),
  KEY idx_report_status (status, submitted_at),
  CONSTRAINT fk_report_student FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ----------------------------------------------------------
--  review_reminders : مرورهای فاصله‌دار
-- ----------------------------------------------------------
-- Upgrade: spaced repetition review reminders
CREATE TABLE IF NOT EXISTS review_reminders (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  student_id INT UNSIGNED NOT NULL,
  source_task_id INT UNSIGNED NOT NULL,
  subject_id INT UNSIGNED DEFAULT NULL,
  topic_title VARCHAR(180) NOT NULL,
  source VARCHAR(160) DEFAULT NULL,
  first_studied_at DATETIME NOT NULL,
  interval_days INT UNSIGNED NOT NULL,
  review_no TINYINT UNSIGNED NOT NULL DEFAULT 1,
  profile_key VARCHAR(40) DEFAULT NULL,
  profile_label VARCHAR(80) DEFAULT NULL,
  suggested_minutes INT UNSIGNED DEFAULT 15,
  due_date DATE NOT NULL,
  status ENUM('pending','done','dismissed') NOT NULL DEFAULT 'pending',
  notified_at DATETIME DEFAULT NULL,
  completed_at DATETIME DEFAULT NULL,
  quality ENUM('hard','good','easy') DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_review_step (source_task_id, interval_days),
  KEY idx_review_student_due (student_id, status, due_date),
  KEY idx_review_source (source_task_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
