<?php
/**
 * DockerPanel — Settings Controller
 */

class SettingsController {
    public function index(): void {
        if (isAjax()) {
            $db = Database::getInstance();
            $settings = $db->query('SELECT * FROM settings ORDER BY key')->fetchAll();
            $result = [];
            foreach ($settings as $s) { $result[$s['key']] = $s['value']; }
            Response::success($result);
            return;
        }
        Response::view('settings');
    }

    public function update(): void {
        Security::requireRole('admin');
        $data = Validator::jsonBody();
        $db = Database::getInstance();
        
        foreach ($data as $key => $value) {
            if (!preg_match('/^[a-z_]+$/', $key)) continue;
            $stmt = $db->prepare('INSERT OR REPLACE INTO settings (key, value, updated_at) VALUES (?, ?, datetime("now"))');
            $stmt->execute([$key, $value]);
        }
        
        Security::auditLog('settings_update', 'settings', '', json_encode(array_keys($data)));
        Response::success(null, 'Настройки сохранены');
    }

    public function docker(): void {
        try {
            $docker = new DockerService();
            $info = $docker->systemInfo();
            $version = $docker->version();
            $disk = $docker->diskUsage();
            
            Response::success([
                'info' => [
                    'server_version' => $info['ServerVersion'] ?? '',
                    'os' => $info['OperatingSystem'] ?? '',
                    'arch' => $info['Architecture'] ?? '',
                    'kernel' => $info['KernelVersion'] ?? '',
                    'cpus' => $info['NCPU'] ?? 0,
                    'memory' => DockerService::formatBytes($info['MemTotal'] ?? 0),
                    'containers' => $info['Containers'] ?? 0,
                    'images' => $info['Images'] ?? 0,
                    'driver' => $info['Driver'] ?? '',
                    'docker_root' => $info['DockerRootDir'] ?? '',
                ],
                'version' => $version,
                'disk' => $disk,
            ]);
        } catch (\Exception $e) { Response::error($e->getMessage()); }
    }

    public function checkUpdate(): void {
        Security::requireRole('admin');
        require_once BASE_PATH . '/app/Services/UpdateService.php';
        $result = UpdateService::checkUpdates();
        if (isset($result['error'])) {
            Response::error($result['error']);
            return;
        }
        Response::success($result);
    }

    public function startUpdate(): void {
        Security::requireRole('admin');
        require_once BASE_PATH . '/app/Services/UpdateService.php';
        if (UpdateService::startUpdate()) {
            Response::success(null, 'Обновление запущено в фоновом режиме. Система будет перезагружена через минуту.');
        } else {
            Response::error('Ошибка запуска обновления.');
        }
    }
}
