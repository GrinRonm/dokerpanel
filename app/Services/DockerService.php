<?php
/**
 * DockerPanel — Docker Service
 * 
 * Обёртка над Docker Engine API через Unix Socket.
 * Все операции с Docker проходят через этот сервис.
 */

class DockerService {

    private string $socketPath;
    private string $apiVersion;

    public function __construct() {
        $this->socketPath = DOCKER_SOCKET;
        $this->apiVersion = DOCKER_API_VERSION;
    }

    // ==========================================
    // HTTP-запросы к Docker Engine API
    // ==========================================

    /**
     * Выполнить GET-запрос к Docker API
     */
    private function get(string $endpoint, array $query = []): array {
        return $this->request('GET', $endpoint, $query);
    }

    /**
     * Выполнить POST-запрос к Docker API
     */
    private function post(string $endpoint, array $body = [], array $query = []): array {
        return $this->request('POST', $endpoint, $query, $body);
    }

    /**
     * Выполнить DELETE-запрос к Docker API
     */
    private function delete(string $endpoint, array $query = []): array {
        return $this->request('DELETE', $endpoint, $query);
    }

    /**
     * HTTP-запрос через Unix Socket
     */
    private function request(string $method, string $endpoint, array $query = [], ?array $body = null): array {
        $url = "http://localhost/{$this->apiVersion}{$endpoint}";
        if (!empty($query)) {
            $url .= '?' . http_build_query($query);
        }

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_UNIX_SOCKET_PATH => $this->socketPath,
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 120,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        ]);

        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            throw new \RuntimeException("Docker API error: {$error}");
        }

        $data = json_decode($response, true);
        
        // Docker API иногда возвращает пустой ответ (204 No Content)
        if ($data === null && $httpCode >= 200 && $httpCode < 300) {
            return ['_http_code' => $httpCode];
        }

        if ($httpCode >= 400) {
            $message = $data['message'] ?? $response ?? 'Unknown error';
            throw new \RuntimeException("Docker API [{$httpCode}]: {$message}");
        }

        if (!is_array($data)) {
            return ['_raw' => $response, '_http_code' => $httpCode];
        }

        return $data;
    }

    /**
     * Выполнить Docker CLI команду
     */
    private function cli(string $command, bool $returnOutput = true): string|bool {
        $fullCmd = "docker {$command} 2>&1";
        if ($returnOutput) {
            return shell_exec($fullCmd) ?? '';
        }
        exec($fullCmd, $output, $code);
        return $code === 0;
    }

    // ==========================================
    // Контейнеры
    // ==========================================

    /**
     * Список всех контейнеров
     */
    public function listContainers(bool $all = true): array {
        $query = ['all' => $all ? 'true' : 'false', 'size' => 'true'];
        return $this->get('/containers/json', $query);
    }

    /**
     * Информация о контейнере
     */
    public function inspectContainer(string $id): array {
        return $this->get("/containers/{$id}/json");
    }

    /**
     * Создать контейнер
     */
    public function createContainer(array $config, string $name = ''): array {
        $query = [];
        if (!empty($name)) {
            $query['name'] = $name;
        }

        // Формируем конфигурацию для Docker API
        $body = [
            'Image' => $config['image'] ?? 'ubuntu:latest',
            'Tty' => true,
            'OpenStdin' => true,
            'StdinOnce' => false,
        ];

        // Команда запуска
        if (!empty($config['cmd'])) {
            $body['Cmd'] = is_array($config['cmd']) ? $config['cmd'] : explode(' ', $config['cmd']);
        }

        // Переменные окружения
        if (!empty($config['env']) && is_array($config['env'])) {
            $body['Env'] = [];
            foreach ($config['env'] as $env) {
                if (!empty($env['name'])) {
                    $body['Env'][] = $env['name'] . '=' . ($env['value'] ?? '');
                }
            }
        }

        // Labels
        $body['Labels'] = [
            'managed_by' => 'dockerpanel',
            'created_by' => (string)($_SESSION['user_id'] ?? 0),
        ];

        // Host Config
        $hostConfig = [
            'RestartPolicy' => ['Name' => $config['restart'] ?? 'unless-stopped'],
        ];

        // Порты
        if (!empty($config['ports']) && is_array($config['ports'])) {
            $body['ExposedPorts'] = [];
            $hostConfig['PortBindings'] = [];
            
            foreach ($config['ports'] as $port) {
                $containerPort = $port['container'] ?? '';
                $hostPort = $port['host'] ?? '';
                
                if (empty($containerPort)) continue;
                
                $key = "{$containerPort}/tcp";
                $body['ExposedPorts'][$key] = (object)[];
                
                if (empty($hostPort)) {
                    // Автоматически назначить свободный порт
                    $portService = new PortService();
                    $hostPort = (string)$portService->findFreePort();
                }
                
                $hostConfig['PortBindings'][$key] = [
                    ['HostPort' => (string)$hostPort]
                ];
            }
        }

        // Ресурсы: CPU
        if (!empty($config['cpu'])) {
            $cpuFloat = (float)$config['cpu'];
            $hostConfig['NanoCpus'] = (int)($cpuFloat * 1e9);
        }

        // Ресурсы: RAM
        if (!empty($config['ram'])) {
            $hostConfig['Memory'] = $this->parseMemoryString($config['ram']);
        }

        // Volumes
        if (!empty($config['volumes']) && is_array($config['volumes'])) {
            $hostConfig['Binds'] = [];
            $body['Volumes'] = [];
            
            foreach ($config['volumes'] as $vol) {
                $containerPath = $vol['container'] ?? '';
                $hostPath = $vol['host'] ?? '';
                
                if (empty($containerPath)) continue;
                
                if (!empty($hostPath)) {
                    $hostConfig['Binds'][] = "{$hostPath}:{$containerPath}";
                } else {
                    if (is_array($body['Volumes'])) $body['Volumes'] = (object)[];
                    $body['Volumes']->{$containerPath} = (object)[];
                }
            }
            if (is_array($body['Volumes']) && empty($body['Volumes'])) {
                $body['Volumes'] = (object)[];
            }
        }

        // Сеть
        if (!empty($config['network'])) {
            $hostConfig['NetworkMode'] = $config['network'];
        }

        // Привилегированный режим
        if (!empty($config['privileged'])) {
            $hostConfig['Privileged'] = true;
        }

        // Tmpfs (для systemd)
        if (!empty($config['tmpfs']) && is_array($config['tmpfs'])) {
            $hostConfig['Tmpfs'] = [];
            foreach ($config['tmpfs'] as $path) {
                $hostConfig['Tmpfs'][$path] = "";
            }
        }

        // CgroupnsMode (для systemd)
        if (!empty($config['cgroupns'])) {
            $hostConfig['CgroupnsMode'] = $config['cgroupns'];
        }

        $body['HostConfig'] = $hostConfig;

        return $this->post('/containers/create', $body, $query);
    }

    /**
     * Запустить контейнер
     */
    public function startContainer(string $id): array {
        return $this->post("/containers/{$id}/start");
    }

    /**
     * Остановить контейнер
     */
    public function stopContainer(string $id, int $timeout = 10): array {
        return $this->post("/containers/{$id}/stop", [], ['t' => $timeout]);
    }

    /**
     * Перезапустить контейнер
     */
    public function restartContainer(string $id): array {
        return $this->post("/containers/{$id}/restart");
    }

    /**
     * Удалить контейнер
     */
    public function removeContainer(string $id, bool $force = true, bool $removeVolumes = false): array {
        return $this->delete("/containers/{$id}", [
            'force' => $force ? 'true' : 'false',
            'v' => $removeVolumes ? 'true' : 'false',
        ]);
    }

    /**
     * Статистика контейнера (CPU, RAM, сеть, диск)
     */
    public function containerStats(string $id): array {
        return $this->get("/containers/{$id}/stats", ['stream' => 'false']);
    }

    /**
     * Логи контейнера
     */
    public function containerLogs(string $id, int $tail = 500, bool $timestamps = true): string {
        $cmd = "logs --tail {$tail}" . ($timestamps ? ' --timestamps' : '') . " {$id}";
        return $this->cli($cmd);
    }

    /**
     * Выполнить команду в контейнере
     */
    public function execInContainer(string $id, string $command): string {
        $escapedCmd = Security::shellEscape($command);
        return $this->cli("exec {$id} sh -c {$escapedCmd}");
    }

    /**
     * Список процессов в контейнере (top)
     */
    public function containerTop(string $id): array {
        return $this->get("/containers/{$id}/top");
    }

    /**
     * Переименовать контейнер
     */
    public function renameContainer(string $id, string $newName): array {
        return $this->post("/containers/{$id}/rename", [], ['name' => $newName]);
    }

    /**
     * Обновить ресурсы контейнера
     */
    public function updateContainer(string $id, array $config): array {
        $body = [];
        if (!empty($config['cpu'])) {
            $body['NanoCpus'] = (int)((float)$config['cpu'] * 1e9);
        }
        if (!empty($config['ram'])) {
            $body['Memory'] = $this->parseMemoryString($config['ram']);
        }
        return $this->post("/containers/{$id}/update", $body);
    }

    // ==========================================
    // Образы
    // ==========================================

    /**
     * Список образов
     */
    public function listImages(): array {
        return $this->get('/images/json');
    }

    /**
     * Скачать образ
     */
    public function pullImage(string $name, string $tag = 'latest'): string {
        $fullName = "{$name}:{$tag}";
        return $this->cli("pull {$fullName}");
    }

    /**
     * Удалить образ
     */
    public function removeImage(string $id, bool $force = false): array {
        return $this->delete("/images/{$id}", ['force' => $force ? 'true' : 'false']);
    }

    /**
     * Поиск образов на Docker Hub
     */
    public function searchImages(string $term): array {
        return $this->get('/images/search', ['term' => $term, 'limit' => 25]);
    }

    /**
     * История образа
     */
    public function imageHistory(string $id): array {
        return $this->get("/images/{$id}/history");
    }

    /**
     * Информация об образе
     */
    public function inspectImage(string $id): array {
        return $this->get("/images/{$id}/json");
    }

    // ==========================================
    // Сети
    // ==========================================

    /**
     * Список сетей
     */
    public function listNetworks(): array {
        return $this->get('/networks');
    }

    /**
     * Создать сеть
     */
    public function createNetwork(string $name, string $driver = 'bridge', array $options = []): array {
        $body = [
            'Name' => $name,
            'Driver' => $driver,
            'IPAM' => ['Driver' => 'default'],
        ];
        if (!empty($options['subnet'])) {
            $body['IPAM']['Config'] = [['Subnet' => $options['subnet']]];
        }
        return $this->post('/networks/create', $body);
    }

    /**
     * Удалить сеть
     */
    public function removeNetwork(string $id): array {
        return $this->delete("/networks/{$id}");
    }

    /**
     * Подключить контейнер к сети
     */
    public function connectToNetwork(string $networkId, string $containerId): array {
        return $this->post("/networks/{$networkId}/connect", ['Container' => $containerId]);
    }

    /**
     * Отключить контейнер от сети
     */
    public function disconnectFromNetwork(string $networkId, string $containerId): array {
        return $this->post("/networks/{$networkId}/disconnect", ['Container' => $containerId]);
    }

    /**
     * Информация о сети
     */
    public function inspectNetwork(string $id): array {
        return $this->get("/networks/{$id}");
    }

    // ==========================================
    // Volumes
    // ==========================================

    /**
     * Список volumes
     */
    public function listVolumes(): array {
        $result = $this->get('/volumes');
        return $result['Volumes'] ?? [];
    }

    /**
     * Создать volume
     */
    public function createVolume(string $name, string $driver = 'local'): array {
        return $this->post('/volumes/create', [
            'Name' => $name,
            'Driver' => $driver,
        ]);
    }

    /**
     * Удалить volume
     */
    public function removeVolume(string $name): array {
        return $this->delete("/volumes/{$name}");
    }

    /**
     * Очистить неиспользуемые volumes
     */
    public function pruneVolumes(): array {
        return $this->post('/volumes/prune');
    }

    /**
     * Информация о volume
     */
    public function inspectVolume(string $name): array {
        return $this->get("/volumes/{$name}");
    }

    // ==========================================
    // Система
    // ==========================================

    /**
     * Системная информация Docker
     */
    public function systemInfo(): array {
        return $this->get('/info');
    }

    /**
     * Версия Docker
     */
    public function version(): array {
        return $this->get('/version');
    }

    /**
     * Использование диска
     */
    public function diskUsage(): array {
        return $this->get('/system/df');
    }

    /**
     * Ping Docker
     */
    public function ping(): bool {
        try {
            $this->get('/_ping');
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    // ==========================================
    // Файловые операции в контейнере
    // ==========================================

    /**
     * Список файлов в директории контейнера
     */
    public function listFiles(string $containerId, string $path = '/'): array {
        $escapedPath = Security::shellEscape($path);
        $output = $this->cli("exec {$containerId} ls -la --time-style=long-iso {$escapedPath}");
        return $this->parseLsOutput($output, $path);
    }

    /**
     * Читать содержимое файла
     */
    public function readFile(string $containerId, string $path): string {
        $escapedPath = Security::shellEscape($path);
        return $this->cli("exec {$containerId} cat {$escapedPath}");
    }

    /**
     * Записать содержимое файла
     */
    public function writeFile(string $containerId, string $path, string $content): bool {
        // Используем base64 для безопасной передачи содержимого
        $base64 = base64_encode($content);
        $escapedPath = Security::shellEscape($path);
        $result = $this->cli("exec {$containerId} sh -c 'echo {$base64} | base64 -d > {$path}'");
        return true;
    }

    /**
     * Удалить файл/директорию в контейнере
     */
    public function deleteFile(string $containerId, string $path): bool {
        $escapedPath = Security::shellEscape($path);
        $this->cli("exec {$containerId} rm -rf {$escapedPath}");
        return true;
    }

    /**
     * Создать директорию
     */
    public function createDirectory(string $containerId, string $path): bool {
        $escapedPath = Security::shellEscape($path);
        $this->cli("exec {$containerId} mkdir -p {$escapedPath}");
        return true;
    }

    /**
     * Копировать файл внутри контейнера
     */
    public function copyFile(string $containerId, string $src, string $dst): bool {
        $this->cli("exec {$containerId} cp -r " . Security::shellEscape($src) . " " . Security::shellEscape($dst));
        return true;
    }

    /**
     * Переместить файл
     */
    public function moveFile(string $containerId, string $src, string $dst): bool {
        $this->cli("exec {$containerId} mv " . Security::shellEscape($src) . " " . Security::shellEscape($dst));
        return true;
    }

    /**
     * Загрузить файл в контейнер
     */
    public function uploadFile(string $containerId, string $localPath, string $containerPath): bool {
        $this->cli("cp {$localPath} {$containerId}:{$containerPath}");
        return true;
    }

    /**
     * Скачать файл из контейнера
     */
    public function downloadFile(string $containerId, string $containerPath, string $localPath): bool {
        $this->cli("cp {$containerId}:{$containerPath} {$localPath}");
        return true;
    }

    /**
     * Создать архив
     */
    public function archiveFiles(string $containerId, string $path, string $archivePath): bool {
        $this->cli("exec {$containerId} tar -czf " . Security::shellEscape($archivePath) . " -C " . 
                   Security::shellEscape(dirname($path)) . " " . Security::shellEscape(basename($path)));
        return true;
    }

    /**
     * Распаковать архив
     */
    public function extractArchive(string $containerId, string $archivePath, string $targetPath): bool {
        $this->cli("exec {$containerId} tar -xzf " . Security::shellEscape($archivePath) . " -C " . Security::shellEscape($targetPath));
        return true;
    }

    // ==========================================
    // Утилиты
    // ==========================================

    /**
     * Парсинг строки памяти (512m, 1g) в байты
     */
    private function parseMemoryString(string $memory): int {
        $memory = strtolower(trim($memory));
        $value = (float)$memory;
        
        if (str_ends_with($memory, 'g') || str_ends_with($memory, 'gb')) {
            return (int)($value * 1024 * 1024 * 1024);
        }
        if (str_ends_with($memory, 'm') || str_ends_with($memory, 'mb')) {
            return (int)($value * 1024 * 1024);
        }
        if (str_ends_with($memory, 'k') || str_ends_with($memory, 'kb')) {
            return (int)($value * 1024);
        }
        return (int)$value;
    }

    /**
     * Форматирование байт в человекочитаемый формат
     */
    public static function formatBytes(int $bytes, int $precision = 2): string {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $factor = floor((strlen((string)$bytes) - 1) / 3);
        $factor = min($factor, count($units) - 1);
        return round($bytes / pow(1024, $factor), $precision) . ' ' . $units[$factor];
    }

    /**
     * Рассчитать CPU% из docker stats
     */
    public static function calculateCpuPercent(array $stats): float {
        $cpuDelta = ($stats['cpu_stats']['cpu_usage']['total_usage'] ?? 0) -
                    ($stats['precpu_stats']['cpu_usage']['total_usage'] ?? 0);
        $systemDelta = ($stats['cpu_stats']['system_cpu_usage'] ?? 0) -
                       ($stats['precpu_stats']['system_cpu_usage'] ?? 0);
        $cpuCount = $stats['cpu_stats']['online_cpus'] ?? 1;

        if ($systemDelta > 0 && $cpuDelta > 0) {
            return round(($cpuDelta / $systemDelta) * $cpuCount * 100.0, 2);
        }
        return 0.0;
    }

    /**
     * Рассчитать RAM из docker stats
     */
    public static function calculateMemory(array $stats): array {
        $used = $stats['memory_stats']['usage'] ?? 0;
        $limit = $stats['memory_stats']['limit'] ?? 0;
        $cache = $stats['memory_stats']['stats']['cache'] ?? 0;
        $actualUsed = $used - $cache;
        
        return [
            'used' => $actualUsed,
            'limit' => $limit,
            'percent' => $limit > 0 ? round(($actualUsed / $limit) * 100, 2) : 0,
            'used_formatted' => self::formatBytes($actualUsed),
            'limit_formatted' => self::formatBytes($limit),
        ];
    }

    /**
     * Рассчитать сетевой трафик из docker stats
     */
    public static function calculateNetwork(array $stats): array {
        $rx = 0;
        $tx = 0;
        $networks = $stats['networks'] ?? [];
        foreach ($networks as $iface) {
            $rx += $iface['rx_bytes'] ?? 0;
            $tx += $iface['tx_bytes'] ?? 0;
        }
        return [
            'rx' => $rx,
            'tx' => $tx,
            'rx_formatted' => self::formatBytes($rx),
            'tx_formatted' => self::formatBytes($tx),
        ];
    }

    /**
     * Парсинг вывода ls -la
     */
    private function parseLsOutput(string $output, string $basePath): array {
        $lines = explode("\n", trim($output));
        $files = [];
        
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line) || strpos($line, 'total') === 0) continue;
            
            // Парсим ls -la output
            if (preg_match('/^([drwx\-lsStT]{10})\s+(\d+)\s+(\S+)\s+(\S+)\s+(\d+)\s+(\d{4}-\d{2}-\d{2})\s+(\d{2}:\d{2})\s+(.+)$/', $line, $m)) {
                $name = $m[8];
                if ($name === '.' || $name === '..') continue;
                
                $files[] = [
                    'name' => $name,
                    'path' => rtrim($basePath, '/') . '/' . $name,
                    'permissions' => $m[1],
                    'owner' => $m[3],
                    'group' => $m[4],
                    'size' => (int)$m[5],
                    'size_formatted' => self::formatBytes((int)$m[5]),
                    'modified' => $m[6] . ' ' . $m[7],
                    'is_dir' => $m[1][0] === 'd',
                    'is_link' => $m[1][0] === 'l',
                ];
            }
        }
        
        // Сортировка: папки сначала, затем файлы
        usort($files, function($a, $b) {
            if ($a['is_dir'] !== $b['is_dir']) return $b['is_dir'] - $a['is_dir'];
            return strcasecmp($a['name'], $b['name']);
        });
        
        return $files;
    }

    /**
     * Получить IP-адрес контейнера
     */
    public function getContainerIp(string $id): string {
        try {
            $info = $this->inspectContainer($id);
            $networks = $info['NetworkSettings']['Networks'] ?? [];
            foreach ($networks as $net) {
                if (!empty($net['IPAddress'])) {
                    return $net['IPAddress'];
                }
            }
        } catch (\Exception $e) {}
        return '-';
    }
}
