<?php
/**
 * DockerPanel — Volume Controller
 */

class VolumeController {
    private DockerService $docker;
    public function __construct() { $this->docker = new DockerService(); }

    public function index(): void {
        if (isAjax()) { $this->list(); return; }
        Response::view('volumes');
    }

    public function list(): void {
        try {
            $volumes = $this->docker->listVolumes();
            $result = [];
            foreach ($volumes as $v) {
                // Получить размер
                $mountpoint = $v['Mountpoint'] ?? '';
                $size = 0;
                if (!empty($mountpoint) && is_dir($mountpoint)) {
                    $sizeStr = shell_exec("du -sb " . escapeshellarg($mountpoint) . " 2>/dev/null | awk '{print $1}'") ?? '0';
                    $size = (int)trim($sizeStr);
                }
                $result[] = [
                    'name' => $v['Name'] ?? '',
                    'driver' => $v['Driver'] ?? '',
                    'mountpoint' => $mountpoint,
                    'scope' => $v['Scope'] ?? '',
                    'created' => $v['CreatedAt'] ?? '',
                    'size' => $size,
                    'size_formatted' => DockerService::formatBytes($size),
                    'labels' => $v['Labels'] ?? [],
                ];
            }
            Response::success($result);
        } catch (\Exception $e) { Response::error($e->getMessage()); }
    }

    public function create(): void {
        $data = Validator::jsonBody();
        if (empty($data['name'])) Response::error('Укажите имя volume');
        try {
            $result = $this->docker->createVolume($data['name'], $data['driver'] ?? 'local');
            Security::auditLog('volume_create', 'volume', $data['name']);
            Response::success($result, 'Volume создан');
        } catch (\Exception $e) { Response::error($e->getMessage()); }
    }

    public function remove(): void {
        $data = Validator::jsonBody();
        $name = $data['name'] ?? '';
        if (empty($name)) Response::error('Имя не указано');
        try {
            $this->docker->removeVolume($name);
            Security::auditLog('volume_remove', 'volume', $name);
            Response::success(null, 'Volume удалён');
        } catch (\Exception $e) { Response::error($e->getMessage()); }
    }

    public function prune(): void {
        Security::requireRole('admin');
        try {
            $result = $this->docker->pruneVolumes();
            Security::auditLog('volumes_prune', 'volume', '', json_encode($result));
            Response::success($result, 'Неиспользуемые volumes очищены');
        } catch (\Exception $e) { Response::error($e->getMessage()); }
    }
}
