-- =========================================================
-- VatanParvar Yaypan Avtomaktab — Ma'lumotlar bazasi
-- MySQL 5.7+ / MariaDB 10+ uchun
-- =========================================================

CREATE DATABASE IF NOT EXISTS `vatanparvar_yaypan`
  DEFAULT CHARACTER SET utf8mb4
  DEFAULT COLLATE utf8mb4_unicode_ci;

USE `vatanparvar_yaypan`;

-- ---------------------------------------------------------
-- Foydalanuvchilar
-- ---------------------------------------------------------
CREATE TABLE IF NOT EXISTS `users` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `first_name` VARCHAR(100) NOT NULL,
  `last_name`  VARCHAR(100) NOT NULL,
  `email`      VARCHAR(150) UNIQUE,
  `phone`      VARCHAR(20)  UNIQUE,
  `password`   VARCHAR(255) NOT NULL,
  `avatar`     VARCHAR(255) DEFAULT NULL,
  `role`       ENUM('user','admin','developer') NOT NULL DEFAULT 'user',
  `status`     ENUM('active','blocked','pending') NOT NULL DEFAULT 'active',
  `language`   ENUM('uz_latin','uz_cyrillic') NOT NULL DEFAULT 'uz_latin',
  `tariff_id`  INT UNSIGNED DEFAULT NULL,
  `tariff_expires_at` DATETIME DEFAULT NULL,
  `referral_code`     VARCHAR(20) UNIQUE,
  `referred_by`       INT UNSIGNED DEFAULT NULL,
  `telegram_id`       BIGINT DEFAULT NULL,
  `telegram_phone`    VARCHAR(20) DEFAULT NULL,
  `last_login`        DATETIME DEFAULT NULL,
  `created_at`        TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at`        TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_role` (`role`),
  INDEX `idx_status` (`status`),
  INDEX `idx_referred_by` (`referred_by`)
) ENGINE=InnoDB;

-- ---------------------------------------------------------
-- Tariflar
-- ---------------------------------------------------------
CREATE TABLE IF NOT EXISTS `tariffs` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `name_latin`    VARCHAR(100) NOT NULL,
  `name_cyrillic` VARCHAR(100) NOT NULL,
  `description_latin`    TEXT,
  `description_cyrillic` TEXT,
  `price`         DECIMAL(12,2) NOT NULL DEFAULT 0,
  `duration_days` INT NOT NULL DEFAULT 30,
  `features_latin`    TEXT,
  `features_cyrillic` TEXT,
  `tests_per_day` INT DEFAULT 0,
  `is_popular`    TINYINT(1) DEFAULT 0,
  `status`        ENUM('active','inactive') NOT NULL DEFAULT 'active',
  `sort_order`    INT DEFAULT 0,
  `created_at`    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ---------------------------------------------------------
-- Biletlar
-- ---------------------------------------------------------
CREATE TABLE IF NOT EXISTS `tickets` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `title_latin`    VARCHAR(150) NOT NULL,
  `title_cyrillic` VARCHAR(150) NOT NULL,
  `ticket_number`  INT NOT NULL,
  `questions_count` INT NOT NULL DEFAULT 20,
  `time_minutes`   INT NOT NULL DEFAULT 25,
  `status`         ENUM('active','inactive') DEFAULT 'active',
  `created_at`     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `uniq_number` (`ticket_number`)
) ENGINE=InnoDB;

-- ---------------------------------------------------------
-- Savollar
-- ---------------------------------------------------------
CREATE TABLE IF NOT EXISTS `questions` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `ticket_id`        INT UNSIGNED DEFAULT NULL,
  `question_latin`    TEXT NOT NULL,
  `question_cyrillic` TEXT NOT NULL,
  `image`            VARCHAR(255) DEFAULT NULL,
  `explanation_latin`    TEXT,
  `explanation_cyrillic` TEXT,
  `category`         VARCHAR(100) DEFAULT NULL,
  `difficulty`       ENUM('easy','medium','hard') DEFAULT 'medium',
  `status`           ENUM('active','inactive') DEFAULT 'active',
  `created_at`       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_ticket` (`ticket_id`),
  CONSTRAINT `fk_q_ticket` FOREIGN KEY (`ticket_id`) REFERENCES `tickets`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ---------------------------------------------------------
-- Javob variantlari
-- ---------------------------------------------------------
CREATE TABLE IF NOT EXISTS `answers` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `question_id`      INT UNSIGNED NOT NULL,
  `answer_latin`    TEXT NOT NULL,
  `answer_cyrillic` TEXT NOT NULL,
  `is_correct`       TINYINT(1) NOT NULL DEFAULT 0,
  `sort_order`       INT DEFAULT 0,
  CONSTRAINT `fk_a_question` FOREIGN KEY (`question_id`) REFERENCES `questions`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------
-- Foydalanuvchi testlari (urinishlar)
-- ---------------------------------------------------------
CREATE TABLE IF NOT EXISTS `test_attempts` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `user_id`        INT UNSIGNED NOT NULL,
  `ticket_id`      INT UNSIGNED DEFAULT NULL,
  `total_questions` INT NOT NULL DEFAULT 0,
  `correct_answers` INT NOT NULL DEFAULT 0,
  `wrong_answers`   INT NOT NULL DEFAULT 0,
  `score_percent`   DECIMAL(5,2) NOT NULL DEFAULT 0,
  `time_spent`      INT DEFAULT 0,
  `status`          ENUM('in_progress','completed','expired') DEFAULT 'in_progress',
  `started_at`      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `finished_at`     TIMESTAMP NULL DEFAULT NULL,
  INDEX `idx_user` (`user_id`),
  INDEX `idx_status` (`status`),
  CONSTRAINT `fk_ta_user`   FOREIGN KEY (`user_id`)   REFERENCES `users`(`id`)   ON DELETE CASCADE,
  CONSTRAINT `fk_ta_ticket` FOREIGN KEY (`ticket_id`) REFERENCES `tickets`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ---------------------------------------------------------
-- Test javoblar tarixi
-- ---------------------------------------------------------
CREATE TABLE IF NOT EXISTS `test_answers` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `attempt_id`  INT UNSIGNED NOT NULL,
  `question_id` INT UNSIGNED NOT NULL,
  `answer_id`   INT UNSIGNED DEFAULT NULL,
  `is_correct`  TINYINT(1) DEFAULT 0,
  `created_at`  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_taw_attempt`  FOREIGN KEY (`attempt_id`)  REFERENCES `test_attempts`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_taw_question` FOREIGN KEY (`question_id`) REFERENCES `questions`(`id`)     ON DELETE CASCADE,
  CONSTRAINT `fk_taw_answer`   FOREIGN KEY (`answer_id`)   REFERENCES `answers`(`id`)       ON DELETE SET NULL
) ENGINE=InnoDB;

-- ---------------------------------------------------------
-- To'lovlar
-- ---------------------------------------------------------
CREATE TABLE IF NOT EXISTS `payments` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `user_id`     INT UNSIGNED NOT NULL,
  `tariff_id`   INT UNSIGNED DEFAULT NULL,
  `amount`      DECIMAL(12,2) NOT NULL,
  `method`      ENUM('click','payme','manual','telegram') NOT NULL DEFAULT 'manual',
  `transaction_id` VARCHAR(100) DEFAULT NULL,
  `screenshot`  VARCHAR(255) DEFAULT NULL,
  `status`      ENUM('pending','approved','rejected','refunded') DEFAULT 'pending',
  `note`        TEXT,
  `created_at`  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_user`   (`user_id`),
  INDEX `idx_status` (`status`),
  CONSTRAINT `fk_pay_user`   FOREIGN KEY (`user_id`)   REFERENCES `users`(`id`)   ON DELETE CASCADE,
  CONSTRAINT `fk_pay_tariff` FOREIGN KEY (`tariff_id`) REFERENCES `tariffs`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ---------------------------------------------------------
-- Bloglar
-- ---------------------------------------------------------
CREATE TABLE IF NOT EXISTS `blog_posts` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `title_latin`    VARCHAR(255) NOT NULL,
  `title_cyrillic` VARCHAR(255) NOT NULL,
  `slug`           VARCHAR(255) UNIQUE,
  `excerpt_latin`    TEXT,
  `excerpt_cyrillic` TEXT,
  `content_latin`    LONGTEXT,
  `content_cyrillic` LONGTEXT,
  `image`         VARCHAR(255) DEFAULT NULL,
  `category`      VARCHAR(100) DEFAULT NULL,
  `views`         INT DEFAULT 0,
  `status`        ENUM('draft','published') DEFAULT 'published',
  `created_at`    TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ---------------------------------------------------------
-- Sharhlar (testimonials/feedback)
-- ---------------------------------------------------------
CREATE TABLE IF NOT EXISTS `reviews` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `user_id`  INT UNSIGNED DEFAULT NULL,
  `name`     VARCHAR(150),
  `text`     TEXT NOT NULL,
  `rating`   TINYINT DEFAULT 5,
  `status`   ENUM('pending','approved','rejected') DEFAULT 'pending',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_review_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ---------------------------------------------------------
-- Aloqa formasi xabarlari
-- ---------------------------------------------------------
CREATE TABLE IF NOT EXISTS `contact_messages` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `name`    VARCHAR(150) NOT NULL,
  `email`   VARCHAR(150),
  `phone`   VARCHAR(30),
  `message` TEXT NOT NULL,
  `is_read` TINYINT(1) DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ---------------------------------------------------------
-- Referallar
-- ---------------------------------------------------------
CREATE TABLE IF NOT EXISTS `referrals` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `referrer_id`  INT UNSIGNED NOT NULL,
  `referred_id`  INT UNSIGNED NOT NULL,
  `bonus_amount` DECIMAL(12,2) DEFAULT 0,
  `status`       ENUM('pending','paid') DEFAULT 'pending',
  `created_at`   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_ref_referrer` FOREIGN KEY (`referrer_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ref_referred` FOREIGN KEY (`referred_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------
-- Sayt sozlamalari
-- ---------------------------------------------------------
CREATE TABLE IF NOT EXISTS `settings` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `setting_key`   VARCHAR(100) NOT NULL UNIQUE,
  `setting_value` TEXT,
  `setting_type`  ENUM('text','image','number','json','boolean') DEFAULT 'text',
  `setting_group` VARCHAR(50) DEFAULT 'general',
  `updated_at`    TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ---------------------------------------------------------
-- Tizim loglari
-- ---------------------------------------------------------
CREATE TABLE IF NOT EXISTS `logs` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `user_id`    INT UNSIGNED DEFAULT NULL,
  `action`     VARCHAR(100) NOT NULL,
  `description` TEXT,
  `ip_address` VARCHAR(45),
  `user_agent` VARCHAR(255),
  `level`      ENUM('info','warning','error','critical') DEFAULT 'info',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_user` (`user_id`),
  INDEX `idx_level` (`level`)
) ENGINE=InnoDB;

-- =========================================================
-- BOSHLANG'ICH MA'LUMOTLAR (SEED)
-- =========================================================

-- Standart admin foydalanuvchi: parol = admin123
INSERT INTO `users` (first_name,last_name,email,phone,password,role,status,referral_code) VALUES
('Admin','VatanParvar','admin@vatanparvar.uz','998901234567','$2y$10$KIXxw9t6K8fZ8K9z8oUOheO6pK7vZP9NfVqJYnKqzU8m0ZqP6L1KW','admin','active','ADMIN001'),
('Developer','VatanParvar','dev@vatanparvar.uz','998901234568','$2y$10$KIXxw9t6K8fZ8K9z8oUOheO6pK7vZP9NfVqJYnKqzU8m0ZqP6L1KW','developer','active','DEV0001'),
('Test','User','user@vatanparvar.uz','998901234569','$2y$10$KIXxw9t6K8fZ8K9z8oUOheO6pK7vZP9NfVqJYnKqzU8m0ZqP6L1KW','user','active','USER001');

-- Tariflar
INSERT INTO `tariffs` (name_latin,name_cyrillic,description_latin,description_cyrillic,price,duration_days,features_latin,features_cyrillic,tests_per_day,is_popular,sort_order) VALUES
('Bepul','Бепул','Boshlang''ich foydalanuvchilar uchun','Бошланғич фойдаланувчилар учун',0,30,'Kunlik 3 ta test|Asosiy savollar|Statistika','Кунлик 3 та тест|Асосий саволлар|Статистика',3,0,1),
('Standart','Стандарт','Faol o''quvchilar uchun','Фаол ўқувчилар учун',49000,30,'Cheksiz testlar|Barcha biletlar|Batafsil statistika|Reyting','Чексиз тестлар|Барча билетлар|Батафсил статистика|Рейтинг',999,1,2),
('Premium','Премиум','Eng yaxshi tayyorlanish uchun','Энг яхши тайёрланиш учун',99000,30,'Cheksiz testlar|Barcha biletlar|Video darslar|Shaxsiy mentor|Sertifikat','Чексиз тестлар|Барча билетлар|Видео дарслар|Шахсий ментор|Сертификат',999,0,3);

-- Sozlamalar
INSERT INTO `settings` (setting_key,setting_value,setting_type,setting_group) VALUES
('site_name','VatanParvar Yaypan','text','general'),
('site_logo','/assets/images/logo.png','image','general'),
('site_banner','/assets/images/banner.jpg','image','general'),
('site_phone','+998 90 123 45 67','text','contact'),
('site_email','info@vatanparvar.uz','text','contact'),
('site_address','Yaypan shahri, Farg''ona viloyati','text','contact'),
('site_address_cyrillic','Яйпан шаҳри, Фарғона вилояти','text','contact'),
('working_hours','Du-Sha: 09:00-18:00','text','contact'),
('telegram_url','https://t.me/vatanparvar','text','social'),
('instagram_url','https://instagram.com/vatanparvar','text','social'),
('youtube_url','https://youtube.com/vatanparvar','text','social'),
('facebook_url','https://facebook.com/vatanparvar','text','social'),
('click_merchant_id','','text','payment'),
('click_service_id','','text','payment'),
('payme_merchant_id','','text','payment'),
('card_number','8600 1234 5678 9012','text','payment'),
('card_holder','VATANPARVAR YAYPAN','text','payment'),
('seo_title','VatanParvar Yaypan - Avtomaktab','text','seo'),
('seo_description','Yaypan shahridagi eng zamonaviy avtomaktab platformasi','text','seo'),
('seo_keywords','avtomaktab, yo''l harakati, test, yaypan','text','seo'),
('hero_stats_users','5000','text','homepage'),
('hero_stats_questions','3000','text','homepage'),
('hero_stats_success','98','text','homepage'),
('telegram_bot_token','','text','telegram'),
('telegram_admin_chat_id','','text','telegram');

-- Biletlar
INSERT INTO `tickets` (title_latin,title_cyrillic,ticket_number,questions_count,time_minutes) VALUES
('Bilet 1','Билет 1',1,20,25),
('Bilet 2','Билет 2',2,20,25),
('Bilet 3','Билет 3',3,20,25),
('Bilet 4','Билет 4',4,20,25),
('Bilet 5','Билет 5',5,20,25);

-- Namuna savollar (1-bilet)
INSERT INTO `questions` (ticket_id,question_latin,question_cyrillic,category,difficulty) VALUES
(1,'Yo''l harakati nima?','Йўл ҳаракати нима?','Asoslar','easy'),
(1,'Svetoforning sariq rangi nimani anglatadi?','Светофорнинг сариқ ранги нимани англатади?','Belgilar','easy'),
(1,'Eng yuqori tezlik shaharda qancha?','Энг юқори тезлик шаҳарда қанча?','Tezlik','medium');

-- Namuna javoblar
INSERT INTO `answers` (question_id,answer_latin,answer_cyrillic,is_correct) VALUES
(1,'Avtomobillarning yurishi','Автомобилларнинг юриши',0),
(1,'Yo''lda transport va piyodalarning harakati','Йўлда транспорт ва пиёдаларнинг ҳаракати',1),
(1,'Faqat piyodalarning yurishi','Фақат пиёдаларнинг юриши',0),
(1,'Faqat avtobus harakati','Фақат автобус ҳаракати',0),
(2,'To''xtang','Тўхтанг',0),
(2,'Diqqat, tayyorlaning','Диққат, тайёрланинг',1),
(2,'Yuring','Юринг',0),
(2,'Orqaga qayting','Орқага қайтинг',0),
(3,'40 km/soat','40 км/соат',0),
(3,'60 km/soat','60 км/соат',1),
(3,'80 km/soat','80 км/соат',0),
(3,'90 km/soat','90 км/соат',0);

-- Namuna bloglar
INSERT INTO `blog_posts` (title_latin,title_cyrillic,slug,excerpt_latin,excerpt_cyrillic,content_latin,content_cyrillic,category,status) VALUES
('Avtomaktabga qanday tayyorgarlik ko''rish kerak?','Автомактабга қандай тайёргарлик кўриш керак?','tayyorgarlik','Avtomaktabga kirishdan oldingi muhim tavsiyalar','Автомактабга киришдан олдинги муҳим тавсиялар','Avtomaktabga tayyorgarlik haqida to''liq qo''llanma...','Автомактабга тайёргарлик ҳақида тўлиқ қўлланма...','Tavsiyalar','published'),
('Yo''l belgilarini yodlash uchun 5 usul','Йўл белгиларини ёдлаш учун 5 усул','belgilar','Belgilarni tezroq yodlab olish uchun foydali maslahatlar','Белгиларни тезроқ ёдлаб олиш учун фойдали маслаҳатлар','Yo''l belgilari haqida...','Йўл белгилари ҳақида...','Maslahatlar','published'),
('Imtihondan muvaffaqiyatli o''tish sirlari','Имтиҳондан муваффақиятли ўтиш сирлари','imtihon','Birinchi marta imtihondan muvaffaqiyatli o''tish','Биринчи марта имтиҳондан муваффақиятли ўтиш','Imtihon mazmuni...','Имтиҳон мазмуни...','Tajriba','published');

-- Namuna sharhlar
INSERT INTO `reviews` (name,text,rating,status) VALUES
('Akmal Rahimov','Juda zo''r platforma! Birinchi marta imtihondan o''tdim. Rahmat!',5,'approved'),
('Dilnoza Karimova','Savollar to''liq va tushunarli. Tavsiya qilaman!',5,'approved'),
('Bekzod Umarov','Reyting tizimi juda qiziqarli. O''rgansam zavqlanib o''rganaman.',5,'approved'),
('Madina Yusupova','Mobil versiyasi ham juda qulay. Har joyda foydalanish mumkin.',4,'approved');



-- Click/Payme secret keys (admin tomonidan to'ldiriladi)
INSERT INTO `settings` (setting_key,setting_value,setting_type,setting_group) VALUES
('click_secret_key','','text','payment'),
('payme_secret_key','','text','payment')
ON DUPLICATE KEY UPDATE setting_value = setting_value;



-- =========================================================
-- PERFORMANCE INDEXES (FAZA 5 - performance optimization)
-- =========================================================
-- test_attempts so'rovlarni optimallashtirish
ALTER TABLE `test_attempts` ADD INDEX `idx_user_status` (`user_id`, `status`);
ALTER TABLE `test_attempts` ADD INDEX `idx_started_at` (`started_at`);
ALTER TABLE `test_attempts` ADD INDEX `idx_finished_at` (`finished_at`);

-- payments uchun
ALTER TABLE `payments` ADD INDEX `idx_user_status` (`user_id`, `status`);
ALTER TABLE `payments` ADD INDEX `idx_created` (`created_at`);

-- questions uchun
ALTER TABLE `questions` ADD INDEX `idx_ticket_status` (`ticket_id`, `status`);

-- blog_posts uchun
ALTER TABLE `blog_posts` ADD INDEX `idx_status_created` (`status`, `created_at`);
ALTER TABLE `blog_posts` ADD INDEX `idx_category` (`category`);

-- logs uchun
ALTER TABLE `logs` ADD INDEX `idx_action_created` (`action`, `created_at`);

-- users uchun
ALTER TABLE `users` ADD INDEX `idx_role_status` (`role`, `status`);
ALTER TABLE `users` ADD INDEX `idx_telegram` (`telegram_id`);
ALTER TABLE `users` ADD INDEX `idx_tariff` (`tariff_id`);



-- Cron uchun ogohlantirish vaqti ustuni
ALTER TABLE `users` ADD COLUMN `last_warned_at` DATETIME DEFAULT NULL AFTER `last_login`;
