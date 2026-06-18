<?php
/**
 * Avtomatik migratsiya — DB sxemasini yangi versiyaga ko'taradi
 *
 * Foydalanish:
 *   require_once __DIR__ . '/migrate.php';
 *   run_migrations(db()->pdo);
 *
 * Yoki avtomatik (bir marta) — config.php oxirida.
 */

function run_migrations(?PDO $pdo): array {
    if (!$pdo) return ['ok' => 0, 'skipped' => 0, 'errors' => ['DB ulanmagan']];

    $migrations = [
        // v2.3 — bilet rasmi
        ['add_ticket_image',
         "ALTER TABLE `tickets` ADD COLUMN `image` VARCHAR(255) DEFAULT NULL AFTER `time_minutes`"],

        // v2.3 — bilet uchun standart questions_count default
        ['set_default_questions',
         "ALTER TABLE `tickets` MODIFY `questions_count` INT NOT NULL DEFAULT 20"],

        // v2.4 — notifications jadval
        ['create_notifications', "
            CREATE TABLE IF NOT EXISTS `notifications` (
              `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
              `user_id` INT UNSIGNED NOT NULL,
              `type` VARCHAR(50) NOT NULL DEFAULT 'info',
              `title` VARCHAR(255) NOT NULL,
              `message` TEXT,
              `link` VARCHAR(255) DEFAULT NULL,
              `icon` VARCHAR(20) DEFAULT NULL,
              `is_read` TINYINT(1) DEFAULT 0,
              `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
              KEY `idx_user_read` (`user_id`, `is_read`),
              KEY `idx_created` (`created_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        ],

        // v3.0 — secure remember-me tokenlar (Auth::set_remember_cookie uchun)
        ['create_remember_tokens', "
            CREATE TABLE IF NOT EXISTS `remember_tokens` (
              `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
              `user_id` INT UNSIGNED NOT NULL,
              `token_hash` CHAR(64) NOT NULL,
              `expires_at` DATETIME NOT NULL,
              `user_agent` VARCHAR(255) DEFAULT NULL,
              `ip_address` VARCHAR(45) DEFAULT NULL,
              `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
              KEY `idx_user` (`user_id`),
              UNIQUE KEY `uniq_hash` (`token_hash`),
              KEY `idx_expires` (`expires_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        ],

        // v3.0 — to'lovlarda public_token (signed invoice)
        ['add_payment_token',
         "ALTER TABLE `payments` ADD COLUMN `public_token` VARCHAR(64) DEFAULT NULL AFTER `transaction_id`, ADD UNIQUE KEY `uniq_public_token` (`public_token`)"],
    ];

    $results = ['ok' => 0, 'skipped' => 0, 'errors' => []];

    foreach ($migrations as [$name, $sql]) {
        try {
            $pdo->exec($sql);
            $results['ok']++;
        } catch (PDOException $e) {
            $msg = $e->getMessage();
            if (stripos($msg, 'Duplicate column') !== false ||
                stripos($msg, 'already exists') !== false ||
                stripos($msg, 'check that column/key exists') !== false) {
                $results['skipped']++;
            } else {
                $results['errors'][] = "$name: $msg";
            }
        }
    }

    // Default sozlamalar
    $defaults = [
        ['default_question_image', '/assets/images/default-question.svg', 'image', 'general'],
        ['default_ticket_image',   '/assets/images/default-ticket.svg',   'image', 'general'],
        ['default_questions_per_ticket', '20', 'number', 'general'],
    ];

    try {
        $stmt = $pdo->prepare(
            "INSERT IGNORE INTO settings (setting_key, setting_value, setting_type, setting_group)
             VALUES (?, ?, ?, ?)"
        );
        foreach ($defaults as $d) {
            $stmt->execute($d);
        }
    } catch (Throwable $e) {
        $results['errors'][] = "settings: " . $e->getMessage();
    }

    return $results;
}

/**
 * Avtomatik bir marta ishga tushirish (config.php dan chaqiriladi)
 */
function maybe_auto_migrate(): void {
    $lockFile = dirname(__DIR__) . '/.migrated_v3.0';
    if (is_file($lockFile)) return;
    if (!is_file(dirname(__DIR__) . '/.installed')) return;

    try {
        require_once __DIR__ . '/database.php';
        $pdo = Database::getInstance()->pdo;
        if (!$pdo) return;

        $r = run_migrations($pdo);
        @file_put_contents($lockFile, json_encode([
            'migrated_at' => date('c'),
            'version'     => '2.4.0',
            'results'     => $r,
        ]));

        @unlink(dirname(__DIR__) . '/cache/data/settings.cache');
    } catch (Throwable $e) {}
}
