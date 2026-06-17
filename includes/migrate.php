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
    $lockFile = dirname(__DIR__) . '/.migrated_v2.3';
    if (is_file($lockFile)) return;
    if (!is_file(dirname(__DIR__) . '/.installed')) return; // hali install qilinmagan

    try {
        require_once __DIR__ . '/database.php';
        $pdo = Database::getInstance()->pdo;
        if (!$pdo) return;

        $r = run_migrations($pdo);
        @file_put_contents($lockFile, json_encode([
            'migrated_at' => date('c'),
            'version'     => '2.3.0',
            'results'     => $r,
        ]));

        // Settings cache'ni tozalash
        @unlink(dirname(__DIR__) . '/cache/data/settings.cache');
    } catch (Throwable $e) {
        // Silent fail
    }
}
