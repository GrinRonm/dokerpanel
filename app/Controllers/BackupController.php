<?php
/**
 * DockerPanel — Backup Controller
 */

class BackupController {
    private BackupService $backup;
    public function __construct() { $this->backup = new BackupService(); }

    public function index(): void {
        if (isAjax()) { $this->list(); return; }
        Response::view('backups');
    }

    public function list(): void {
        Response::success($this->backup->list());
    }

    public function create(): void {
        $data = Validator::jsonBody();
        $type = $data['type'] ?? 'container';
        try {
            if ($type === 'volume') {
                $result = $this->backup->backupVolume($data['volume_name'] ?? '');
            } else {
                $result = $this->backup->backupContainer($data['container_id'] ?? '', $data['name'] ?? '');
            }
            Security::auditLog('backup_create', 'backup', (string)$result['id']);
            Response::success($result, 'Бэкап создан');
        } catch (\Exception $e) { Response::error($e->getMessage()); }
    }

    public function restore(): void {
        $data = Validator::jsonBody();
        $id = (int)($data['id'] ?? 0);
        if (!$id) Response::error('ID не указан');
        try {
            $output = $this->backup->restore($id);
            Security::auditLog('backup_restore', 'backup', (string)$id);
            Response::success(['output' => $output], 'Бэкап восстановлен');
        } catch (\Exception $e) { Response::error($e->getMessage()); }
    }

    public function delete(): void {
        $data = Validator::jsonBody();
        $id = (int)($data['id'] ?? 0);
        if (!$id) Response::error('ID не указан');
        try {
            $this->backup->delete($id);
            Security::auditLog('backup_delete', 'backup', (string)$id);
            Response::success(null, 'Бэкап удалён');
        } catch (\Exception $e) { Response::error($e->getMessage()); }
    }

    public function download(): void {
        $id = (int)Validator::get('id', 0);
        if (!$id) Response::error('ID не указан');
        $db = Database::getInstance();
        $stmt = $db->prepare('SELECT * FROM backups WHERE id = ?');
        $stmt->execute([$id]);
        $backup = $stmt->fetch();
        if (!$backup || !file_exists($backup['file_path'])) Response::error('Файл не найден');
        Response::download($backup['file_path']);
    }

    public function schedule(): void {
        $data = Validator::jsonBody();
        $db = Database::getInstance();
        if ($_SERVER['REQUEST_METHOD'] === 'GET' || empty($data)) {
            $schedules = $db->query('SELECT bs.*, c.name as container_name FROM backup_schedules bs LEFT JOIN containers c ON bs.container_id = c.id ORDER BY bs.created_at DESC')->fetchAll();
            Response::success($schedules);
            return;
        }
        $stmt = $db->prepare('INSERT INTO backup_schedules (container_id, schedule_cron, retention_days, is_active) VALUES (?, ?, ?, ?)');
        $stmt->execute([$data['container_id'] ?? null, $data['cron'] ?? '0 3 * * *', $data['retention'] ?? 7, 1]);
        Response::success(null, 'Расписание создано');
    }
}
