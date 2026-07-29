<?php
/**
 * DockerPanel — Backup Service
 */

class BackupService {

    private string $backupPath;
    private DockerService $docker;

    public function __construct() {
        $this->backupPath = STORAGE_PATH . '/backups';
        $this->docker = new DockerService();
        if (!is_dir($this->backupPath)) {
            mkdir($this->backupPath, 0755, true);
        }
    }

    /**
     * Бэкап контейнера (commit + save)
     */
    public function backupContainer(string $containerId, string $name = ''): array {
        $db = Database::getInstance();
        $timestamp = date('Y-m-d_H-i-s');
        $backupName = $name ?: "container_{$containerId}_{$timestamp}";
        $fileName = "{$backupName}.tar";
        $filePath = "{$this->backupPath}/{$fileName}";

        // Записываем статус
        $stmt = $db->prepare('INSERT INTO backups (container_id, type, name, file_path, status) VALUES ((SELECT id FROM containers WHERE docker_id LIKE ?), ?, ?, ?, ?)');
        $cId = null;
        $stmtC = $db->prepare('SELECT id FROM containers WHERE docker_id LIKE ?');
        $stmtC->execute([$containerId . '%']);
        $row = $stmtC->fetch();
        $cId = $row ? $row['id'] : null;

        $stmt = $db->prepare('INSERT INTO backups (container_id, type, name, file_path, status) VALUES (?, ?, ?, ?, ?)');
        $stmt->execute([$cId, 'container', $backupName, $filePath, 'running']);
        $backupId = $db->lastInsertId();

        try {
            // Commit контейнера в образ
            $imageName = "dockerpanel-backup:{$backupName}";
            $output = shell_exec("docker commit {$containerId} {$imageName} 2>&1");

            // Сохранить образ в tar
            shell_exec("docker save -o {$filePath} {$imageName} 2>&1");

            // Удалить временный образ
            shell_exec("docker rmi {$imageName} 2>&1");

            $size = file_exists($filePath) ? filesize($filePath) : 0;
            $stmt = $db->prepare('UPDATE backups SET status = ?, file_size = ? WHERE id = ?');
            $stmt->execute(['completed', $size, $backupId]);

            return ['id' => $backupId, 'name' => $backupName, 'path' => $filePath, 'size' => $size];
        } catch (\Exception $e) {
            $stmt = $db->prepare('UPDATE backups SET status = ? WHERE id = ?');
            $stmt->execute(['failed', $backupId]);
            throw $e;
        }
    }

    /**
     * Бэкап volume
     */
    public function backupVolume(string $volumeName): array {
        $timestamp = date('Y-m-d_H-i-s');
        $fileName = "volume_{$volumeName}_{$timestamp}.tar.gz";
        $filePath = "{$this->backupPath}/{$fileName}";

        shell_exec("docker run --rm -v {$volumeName}:/data -v {$this->backupPath}:/backup alpine tar -czf /backup/{$fileName} -C /data . 2>&1");

        $size = file_exists($filePath) ? filesize($filePath) : 0;

        $db = Database::getInstance();
        $stmt = $db->prepare('INSERT INTO backups (type, name, file_path, file_size, status) VALUES (?, ?, ?, ?, ?)');
        $stmt->execute(['volume', "volume_{$volumeName}", $filePath, $size, 'completed']);

        return ['id' => $db->lastInsertId(), 'name' => $fileName, 'path' => $filePath, 'size' => $size];
    }

    /**
     * Восстановить контейнер из бэкапа
     */
    public function restore(int $backupId): string {
        $db = Database::getInstance();
        $stmt = $db->prepare('SELECT * FROM backups WHERE id = ?');
        $stmt->execute([$backupId]);
        $backup = $stmt->fetch();

        if (!$backup || !file_exists($backup['file_path'])) {
            throw new \RuntimeException('Backup not found');
        }

        $output = shell_exec("docker load -i " . escapeshellarg($backup['file_path']) . " 2>&1");
        return $output ?? '';
    }

    /**
     * Удалить бэкап
     */
    public function delete(int $backupId): bool {
        $db = Database::getInstance();
        $stmt = $db->prepare('SELECT * FROM backups WHERE id = ?');
        $stmt->execute([$backupId]);
        $backup = $stmt->fetch();

        if ($backup && file_exists($backup['file_path'])) {
            unlink($backup['file_path']);
        }

        $stmt = $db->prepare('DELETE FROM backups WHERE id = ?');
        $stmt->execute([$backupId]);
        return true;
    }

    /**
     * Список бэкапов
     */
    public function list(): array {
        $db = Database::getInstance();
        return $db->query('SELECT b.*, c.name as container_name FROM backups b LEFT JOIN containers c ON b.container_id = c.id ORDER BY b.created_at DESC')->fetchAll();
    }
}
