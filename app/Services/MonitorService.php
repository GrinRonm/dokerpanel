<?php
/**
 * DockerPanel — Monitor Service
 * 
 * Мониторинг системных ресурсов и контейнеров
 */

class MonitorService {

    private DockerService $docker;

    public function __construct() {
        $this->docker = new DockerService();
    }

    /**
     * Статистика контейнера (форматированная)
     */
    public function getContainerStats(string $containerId): array {
        $stats = $this->docker->containerStats($containerId);

        return [
            'cpu_percent' => DockerService::calculateCpuPercent($stats),
            'memory' => DockerService::calculateMemory($stats),
            'network' => DockerService::calculateNetwork($stats),
            'pids' => $stats['pids_stats']['current'] ?? 0,
            'read_at' => $stats['read'] ?? date('c'),
        ];
    }

    /**
     * Системные ресурсы хоста
     */
    public function getSystemStats(): array {
        return [
            'cpu' => $this->getHostCpuUsage(),
            'memory' => $this->getHostMemory(),
            'disk' => $this->getHostDisk(),
            'uptime' => $this->getUptime(),
            'load' => $this->getLoadAverage(),
        ];
    }

    /**
     * Сводка по Docker
     */
    public function getDockerSummary(): array {
        $containers = $this->docker->listContainers(true);
        $images = $this->docker->listImages();
        $volumes = $this->docker->listVolumes();
        $networks = $this->docker->listNetworks();

        $running = 0;
        $stopped = 0;
        foreach ($containers as $c) {
            if (($c['State'] ?? '') === 'running') {
                $running++;
            } else {
                $stopped++;
            }
        }

        return [
            'containers_total' => count($containers),
            'containers_running' => $running,
            'containers_stopped' => $stopped,
            'images_count' => count($images),
            'volumes_count' => count($volumes),
            'networks_count' => count($networks),
        ];
    }

    /**
     * CPU хоста
     */
    private function getHostCpuUsage(): array {
        $load = sys_getloadavg();
        $cpuCount = (int)(shell_exec("nproc 2>/dev/null") ?? 1);
        $usage = $cpuCount > 0 ? round(($load[0] / $cpuCount) * 100, 1) : 0;

        return [
            'usage_percent' => min($usage, 100),
            'cores' => $cpuCount,
            'load_1' => $load[0] ?? 0,
            'load_5' => $load[1] ?? 0,
            'load_15' => $load[2] ?? 0,
        ];
    }

    /**
     * Память хоста
     */
    private function getHostMemory(): array {
        $meminfo = file_get_contents('/proc/meminfo');
        preg_match('/MemTotal:\s+(\d+)/', $meminfo, $total);
        preg_match('/MemAvailable:\s+(\d+)/', $meminfo, $available);
        preg_match('/SwapTotal:\s+(\d+)/', $meminfo, $swapTotal);
        preg_match('/SwapFree:\s+(\d+)/', $meminfo, $swapFree);

        $totalKb = (int)($total[1] ?? 0);
        $availableKb = (int)($available[1] ?? 0);
        $usedKb = $totalKb - $availableKb;

        return [
            'total' => $totalKb * 1024,
            'used' => $usedKb * 1024,
            'available' => $availableKb * 1024,
            'percent' => $totalKb > 0 ? round(($usedKb / $totalKb) * 100, 1) : 0,
            'total_formatted' => DockerService::formatBytes($totalKb * 1024),
            'used_formatted' => DockerService::formatBytes($usedKb * 1024),
            'swap_total' => ($swapTotal[1] ?? 0) * 1024,
            'swap_used' => (($swapTotal[1] ?? 0) - ($swapFree[1] ?? 0)) * 1024,
        ];
    }

    /**
     * Диск хоста
     */
    private function getHostDisk(): array {
        $total = disk_total_space('/');
        $free = disk_free_space('/');
        $used = $total - $free;

        return [
            'total' => $total,
            'used' => $used,
            'free' => $free,
            'percent' => $total > 0 ? round(($used / $total) * 100, 1) : 0,
            'total_formatted' => DockerService::formatBytes($total),
            'used_formatted' => DockerService::formatBytes($used),
            'free_formatted' => DockerService::formatBytes($free),
        ];
    }

    /**
     * Uptime сервера
     */
    private function getUptime(): string {
        $uptime = (float)(shell_exec("cat /proc/uptime 2>/dev/null | awk '{print $1}'") ?? 0);
        $days = floor($uptime / 86400);
        $hours = floor(($uptime % 86400) / 3600);
        $mins = floor(($uptime % 3600) / 60);
        
        $parts = [];
        if ($days > 0) $parts[] = "{$days}д";
        if ($hours > 0) $parts[] = "{$hours}ч";
        $parts[] = "{$mins}м";
        
        return implode(' ', $parts);
    }

    /**
     * Load average
     */
    private function getLoadAverage(): array {
        $load = sys_getloadavg();
        return [
            '1min' => round($load[0] ?? 0, 2),
            '5min' => round($load[1] ?? 0, 2),
            '15min' => round($load[2] ?? 0, 2),
        ];
    }

    /**
     * Последние действия
     */
    public function getRecentActions(int $limit = 20): array {
        $db = Database::getInstance();
        $stmt = $db->prepare('SELECT al.*, u.username FROM audit_log al LEFT JOIN users u ON al.user_id = u.id ORDER BY al.created_at DESC LIMIT ?');
        $stmt->execute([$limit]);
        return $stmt->fetchAll();
    }
}
