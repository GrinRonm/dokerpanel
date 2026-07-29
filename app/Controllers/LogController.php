<?php
/**
 * DockerPanel — Log Controller
 */

class LogController {
    private DockerService $docker;
    public function __construct() { $this->docker = new DockerService(); }

    public function index(): void { Response::view('containers/detail'); }

    public function container(): void {
        $id = Validator::get('id');
        $tail = (int)Validator::get('tail', '500');
        if (empty($id)) Response::error('ID не указан');
        try {
            $logs = $this->docker->containerLogs($id, $tail);
            Response::success(['logs' => $logs]);
        } catch (\Exception $e) { Response::error($e->getMessage()); }
    }

    public function download(): void {
        $id = Validator::get('id');
        if (empty($id)) Response::error('ID не указан');
        try {
            $logs = $this->docker->containerLogs($id, 10000);
            $tmpFile = tempnam(sys_get_temp_dir(), 'logs_');
            file_put_contents($tmpFile, $logs);
            Response::download($tmpFile, "container_{$id}_logs.txt");
        } catch (\Exception $e) { Response::error($e->getMessage()); }
    }

    public function clear(): void {
        $data = Validator::jsonBody();
        $id = $data['id'] ?? '';
        if (empty($id)) Response::error('ID не указан');
        // Docker не поддерживает очистку логов через API — очищаем файл
        try {
            $logPath = shell_exec("docker inspect --format='{{.LogPath}}' {$id} 2>/dev/null");
            if (!empty($logPath)) {
                shell_exec("truncate -s 0 " . trim($logPath) . " 2>/dev/null");
            }
            Response::success(null, 'Логи очищены');
        } catch (\Exception $e) { Response::error($e->getMessage()); }
    }
}
