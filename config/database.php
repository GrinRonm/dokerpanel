<?php
/**
 * DockerPanel — SQLite Database Connection (Singleton)
 */

class Database {
    private static ?PDO $instance = null;

    /**
     * Получить PDO-подключение к SQLite
     */
    public static function getInstance(): PDO {
        if (self::$instance === null) {
            $dbPath = DB_PATH;
            $dbDir = dirname($dbPath);
            
            if (!is_dir($dbDir)) {
                mkdir($dbDir, 0755, true);
            }

            self::$instance = new PDO("sqlite:{$dbPath}", null, null, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);

            // Оптимизация SQLite
            self::$instance->exec('PRAGMA journal_mode=WAL');
            self::$instance->exec('PRAGMA synchronous=NORMAL');
            self::$instance->exec('PRAGMA foreign_keys=ON');
            self::$instance->exec('PRAGMA busy_timeout=5000');
        }

        return self::$instance;
    }

    /**
     * Инициализация БД — создание таблиц
     */
    public static function initialize(): void {
        $db = self::getInstance();
        $schema = file_get_contents(ROOT_PATH . '/database/schema.sql');
        $db->exec($schema);
    }

    /**
     * Заполнение начальными данными
     */
    public static function seed(): void {
        $db = self::getInstance();
        $seeds = file_get_contents(ROOT_PATH . '/database/seeds.sql');
        $db->exec($seeds);
    }

    // Предотвращение клонирования и десериализации
    private function __construct() {}
    private function __clone() {}
    public function __wakeup() {
        throw new \Exception("Cannot unserialize singleton");
    }
}
