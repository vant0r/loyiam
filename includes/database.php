<?php
/**
 * Database — PDO orqali MySQL ulanishi
 */
require_once __DIR__ . '/config.php';

class Database {
    private static $instance = null;
    public  $pdo;

    private function __construct() {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];
            $this->pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            // Demo rejimi: ulanish bo'lmasa ham sahifa ishlasin
            $this->pdo = null;
            if (!defined('DB_FAIL_SILENT')) {
                define('DB_FAIL_SILENT', true);
            }
            // Faqat developer paneli uchun ko'rsatamiz
            if (defined('SHOW_DB_ERROR') && SHOW_DB_ERROR) {
                die("DB ulanish xatosi: " . $e->getMessage());
            }
        }
    }

    public static function getInstance(): Database {
        if (self::$instance === null) {
            self::$instance = new Database();
        }
        return self::$instance;
    }

    /** SELECT ko'p qator */
    public function fetchAll(string $sql, array $params = []): array {
        if (!$this->pdo) return [];
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll() ?: [];
        } catch (Exception $e) { return []; }
    }

    /** SELECT bitta qator */
    public function fetch(string $sql, array $params = []) {
        if (!$this->pdo) return null;
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            $row = $stmt->fetch();
            return $row ?: null;
        } catch (Exception $e) { return null; }
    }

    /** INSERT/UPDATE/DELETE */
    public function execute(string $sql, array $params = []): bool {
        if (!$this->pdo) return false;
        try {
            $stmt = $this->pdo->prepare($sql);
            return $stmt->execute($params);
        } catch (Exception $e) { return false; }
    }

    public function lastInsertId() {
        return $this->pdo ? $this->pdo->lastInsertId() : 0;
    }
}

/** Tezkor yordamchi */
function db(): Database {
    return Database::getInstance();
}
