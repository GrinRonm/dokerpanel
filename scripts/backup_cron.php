<?php
/**
 * DockerPanel — Запуск резервного копирования по расписанию (Cron)
 * Этот скрипт должен вызываться каждую минуту (или реже) через cron
 */

define('ROOT_PATH', dirname(__DIR__));
require_once ROOT_PATH . '/config/config.php';
require_once ROOT_PATH . '/config/database.php';
require_once ROOT_PATH . '/app/Services/DockerService.php';
require_once ROOT_PATH . '/app/Services/BackupService.php';

try {
    $db = Database::getInstance();
    $backupService = new BackupService();

    // Получаем активные расписания, время которых подошло
    // Для простоты предполагаем, что `cron_expression` = 'daily', 'weekly', 'monthly'
    // и проверяем, нужно ли выполнить бэкап сейчас
    
    // В упрощенном виде: выполняем все активные бэкапы, если подошло время
    // (Требуется доработка логики расписания. Здесь мы создаем базовую реализацию)
    
    $stmt = $db->query("SELECT * FROM backup_schedules WHERE is_active = 1");
    $schedules = $stmt->fetchAll();

    foreach ($schedules as $schedule) {
        $lastRun = strtotime($schedule['last_run'] ?: '1970-01-01');
        $now = time();
        $shouldRun = false;

        switch ($schedule['cron_expression']) {
            case 'daily':
                $shouldRun = ($now - $lastRun) >= 86400;
                break;
            case 'weekly':
                $shouldRun = ($now - $lastRun) >= 604800;
                break;
            case 'monthly':
                $shouldRun = ($now - $lastRun) >= 2592000;
                break;
            default:
                // Custom cron (TODO: implement cron parser)
                break;
        }

        if ($shouldRun) {
            echo "Running backup for container: {$schedule['container_id']}...\n";
            $backupService->createBackup($schedule['container_id'], 'Автоматический бэкап по расписанию');
            
            // Обновляем время последнего запуска
            $update = $db->prepare("UPDATE backup_schedules SET last_run = datetime('now') WHERE id = ?");
            $update->execute([$schedule['id']]);
        }
    }
} catch (Exception $e) {
    echo "Ошибка выполнения крона: " . $e->getMessage() . "\n";
    exit(1);
}
