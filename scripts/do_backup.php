<?php
/**
 * DockerPanel — Фоновое создание бэкапов
 */

define('ROOT_PATH', dirname(__DIR__));
require_once ROOT_PATH . '/config/config.php';
require_once ROOT_PATH . '/config/database.php';
require_once ROOT_PATH . '/app/Services/DockerService.php';
require_once ROOT_PATH . '/app/Services/BackupService.php';

$backupId = (int)($argv[1] ?? 0);
if ($backupId <= 0) {
    exit("Invalid backup ID\n");
}

try {
    $db = Database::getInstance();
    $stmt = $db->prepare('SELECT * FROM backups WHERE id = ?');
    $stmt->execute([$backupId]);
    $backup = $stmt->fetch();

    if (!$backup) {
        exit("Backup not found\n");
    }

    if ($backup['type'] === 'container') {
        // Узнаем docker_id из контейнера
        $stmtC = $db->prepare('SELECT docker_id FROM containers WHERE id = ?');
        $stmtC->execute([$backup['container_id']]);
        $container = $stmtC->fetch();
        if (!$container) throw new Exception("Container not found");

        $dockerId = $container['docker_id'];
        $imageName = "dockerpanel-backup:{$backup['name']}";
        $filePath = escapeshellarg($backup['file_path']);
        
        // Commit
        shell_exec("docker commit " . escapeshellarg($dockerId) . " " . escapeshellarg($imageName) . " 2>&1");
        // Save
        shell_exec("docker save -o {$filePath} " . escapeshellarg($imageName) . " 2>&1");
        // Remove image
        shell_exec("docker rmi " . escapeshellarg($imageName) . " 2>&1");
        
        $size = file_exists($backup['file_path']) ? filesize($backup['file_path']) : 0;
        $stmt = $db->prepare('UPDATE backups SET status = ?, file_size = ? WHERE id = ?');
        $stmt->execute(['completed', $size, $backupId]);
    } elseif ($backup['type'] === 'volume') {
        // Узнаём имя volume из name
        $volumeName = str_replace('volume_', '', $backup['name']);
        $filePath = escapeshellarg($backup['file_path']);
        $backupDir = escapeshellarg(dirname($backup['file_path']));
        $fileName = escapeshellarg(basename($backup['file_path']));
        
        shell_exec("docker run --rm -v " . escapeshellarg($volumeName) . ":/data -v {$backupDir}:/backup alpine tar -czf /backup/{$fileName} -C /data . 2>&1");
        
        $size = file_exists($backup['file_path']) ? filesize($backup['file_path']) : 0;
        $stmt = $db->prepare('UPDATE backups SET status = ?, file_size = ? WHERE id = ?');
        $stmt->execute(['completed', $size, $backupId]);
    }

} catch (Exception $e) {
    $db = Database::getInstance();
    $stmt = $db->prepare('UPDATE backups SET status = ?, notes = ? WHERE id = ?');
    $stmt->execute(['failed', $e->getMessage(), $backupId]);
    echo "Backup failed: " . $e->getMessage() . "\n";
}
