<?php
/**
 * DockerPanel — Container Controller
 * 
 * Полное управление Docker-контейнерами
 */

class ContainerController {

    private DockerService $docker;

    public function __construct() {
        $this->docker = new DockerService();
    }

    /**
     * Страница списка контейнеров
     */
    public function index(): void {
        if (isAjax()) {
            $this->list();
            return;
        }
        Response::view('containers/list');
    }

    /**
     * Список контейнеров (JSON)
     */
    public function list(): void {
        try {
            $containers = $this->docker->listContainers(true);
            $result = [];

            foreach ($containers as $c) {
                $ports = [];
                foreach ($c['Ports'] ?? [] as $p) {
                    $port = '';
                    if (!empty($p['PublicPort'])) {
                        $port = $p['PublicPort'] . ':' . $p['PrivatePort'];
                    } else {
                        $port = (string)($p['PrivatePort'] ?? '');
                    }
                    if ($port) $ports[] = $port;
                }

                $name = ltrim($c['Names'][0] ?? '', '/');

                $result[] = [
                    'id' => $c['Id'] ?? '',
                    'short_id' => substr($c['Id'] ?? '', 0, 12),
                    'name' => $name,
                    'image' => $c['Image'] ?? '',
                    'state' => $c['State'] ?? 'unknown',
                    'status' => $c['Status'] ?? '',
                    'ports' => $ports,
                    'created' => date('Y-m-d H:i:s', $c['Created'] ?? 0),
                    'size_rw' => DockerService::formatBytes($c['SizeRw'] ?? 0),
                    'size_root' => DockerService::formatBytes($c['SizeRootFs'] ?? 0),
                    'network' => array_keys($c['NetworkSettings']['Networks'] ?? []),
                ];
            }

            Response::success($result);
        } catch (\Exception $e) {
            Response::error('Ошибка получения контейнеров: ' . $e->getMessage());
        }
    }

    /**
     * Создать контейнер
     */
    public function create(): void {
        if ($_SERVER['REQUEST_METHOD'] === 'GET' && !isAjax()) {
            Response::view('containers/create');
            return;
        }

        $data = Validator::jsonBody();
        
        $validator = new Validator();
        $validator->required('name', $data['name'] ?? '', 'Имя')
                  ->required('image', $data['image'] ?? '', 'Образ')
                  ->containerName('name', $data['name'] ?? '');

        if ($validator->hasErrors()) {
            Response::error($validator->getFirstError());
        }

        try {
            $image = $data['image'];
            $tag = $data['tag'] ?? 'latest';
            $fullImage = "{$image}:{$tag}";

            // Скачать образ если нет
            $this->docker->pullImage($image, $tag);

            // Конфигурация
            $config = [
                'image' => $fullImage,
                'cmd' => $data['cmd'] ?? '',
                'env' => $data['env'] ?? [],
                'ports' => $data['ports'] ?? [],
                'volumes' => $data['volumes'] ?? [],
                'tmpfs' => $data['tmpfs'] ?? [],
                'cgroupns' => $data['cgroupns'] ?? '',
                'cpu' => $data['cpu'] ?? DEFAULT_CPU_LIMIT,
                'ram' => $data['ram'] ?? DEFAULT_RAM_LIMIT,
                'restart' => $data['restart'] ?? 'unless-stopped',
                'network' => $data['network'] ?? '',
                'privileged' => $data['privileged'] ?? false,
            ];

            // Создание
            $result = $this->docker->createContainer($config, $data['name']);
            $containerId = $result['Id'] ?? '';

            if (empty($containerId)) {
                Response::error('Не удалось создать контейнер');
            }

            // Запустить
            $this->docker->startContainer($containerId);

            // Если это шаблон Ubuntu Systemd, устанавливаем базовые утилиты в фоне
            if (strpos($fullImage, 'systemd-ubuntu') !== false) {
                $cmd = "docker exec -d {$containerId} bash -c 'sleep 5 && apt-get update && apt-get install -y curl wget sudo nano net-tools iproute2'";
                shell_exec($cmd);
            }

            // Сохранить в БД
            $db = Database::getInstance();
            $stmt = $db->prepare('INSERT INTO containers (docker_id, name, user_id, template_id, image, config_json) VALUES (?, ?, ?, ?, ?, ?)');
            $stmt->execute([
                $containerId,
                $data['name'],
                AuthMiddleware::userId(),
                $data['template_id'] ?? null,
                $fullImage,
                json_encode($config),
            ]);

            Security::auditLog('container_create', 'container', $containerId, $data['name']);

            Response::success(['id' => $containerId, 'name' => $data['name']], 'Контейнер создан');
        } catch (\Exception $e) {
            Response::error('Ошибка создания: ' . $e->getMessage());
        }
    }

    /**
     * Детальная информация о контейнере
     */
    public function detail(): void {
        $id = Validator::get('id');
        if (empty($id)) Response::error('ID не указан');

        try {
            $info = $this->docker->inspectContainer($id);
            
            if (isAjax()) {
                // Форматированная информация
                $ports = [];
                $portBindings = $info['HostConfig']['PortBindings'] ?? [];
                foreach ($portBindings as $containerPort => $bindings) {
                    foreach ($bindings ?? [] as $b) {
                        $ports[] = ($b['HostPort'] ?? '') . ':' . str_replace('/tcp', '', $containerPort);
                    }
                }

                $networks = [];
                foreach ($info['NetworkSettings']['Networks'] ?? [] as $name => $net) {
                    $networks[] = [
                        'name' => $name,
                        'ip' => $net['IPAddress'] ?? '-',
                        'gateway' => $net['Gateway'] ?? '-',
                    ];
                }

                $result = [
                    'id' => $info['Id'],
                    'name' => ltrim($info['Name'] ?? '', '/'),
                    'image' => $info['Config']['Image'] ?? '',
                    'state' => $info['State']['Status'] ?? 'unknown',
                    'running' => $info['State']['Running'] ?? false,
                    'started_at' => $info['State']['StartedAt'] ?? '',
                    'created' => $info['Created'] ?? '',
                    'cmd' => implode(' ', $info['Config']['Cmd'] ?? []),
                    'env' => $info['Config']['Env'] ?? [],
                    'ports' => $ports,
                    'networks' => $networks,
                    'mounts' => $info['Mounts'] ?? [],
                    'restart_policy' => $info['HostConfig']['RestartPolicy']['Name'] ?? '',
                    'cpu_nano' => $info['HostConfig']['NanoCpus'] ?? 0,
                    'memory_limit' => $info['HostConfig']['Memory'] ?? 0,
                    'memory_limit_formatted' => DockerService::formatBytes($info['HostConfig']['Memory'] ?? 0),
                    'privileged' => $info['HostConfig']['Privileged'] ?? false,
                    'platform' => $info['Platform'] ?? '',
                ];
                
                Response::success($result);
            }

            Response::view('containers/detail', ['container' => $info]);
        } catch (\Exception $e) {
            Response::error('Контейнер не найден: ' . $e->getMessage(), 404);
        }
    }

    /**
     * Запустить контейнер
     */
    public function start(): void {
        $data = Validator::jsonBody();
        $id = $data['id'] ?? Validator::get('id');
        if (empty($id)) Response::error('ID не указан');

        try {
            $this->docker->startContainer($id);
            Security::auditLog('container_start', 'container', $id);
            Response::success(null, 'Контейнер запущен');
        } catch (\Exception $e) {
            Response::error('Ошибка запуска: ' . $e->getMessage());
        }
    }

    /**
     * Остановить контейнер
     */
    public function stop(): void {
        $data = Validator::jsonBody();
        $id = $data['id'] ?? Validator::get('id');
        if (empty($id)) Response::error('ID не указан');

        try {
            $this->docker->stopContainer($id);
            Security::auditLog('container_stop', 'container', $id);
            Response::success(null, 'Контейнер остановлен');
        } catch (\Exception $e) {
            Response::error('Ошибка остановки: ' . $e->getMessage());
        }
    }

    /**
     * Перезапустить
     */
    public function restart(): void {
        $data = Validator::jsonBody();
        $id = $data['id'] ?? Validator::get('id');
        if (empty($id)) Response::error('ID не указан');

        try {
            $this->docker->restartContainer($id);
            Security::auditLog('container_restart', 'container', $id);
            Response::success(null, 'Контейнер перезапущен');
        } catch (\Exception $e) {
            Response::error('Ошибка перезапуска: ' . $e->getMessage());
        }
    }

    /**
     * Удалить
     */
    public function remove(): void {
        $data = Validator::jsonBody();
        $id = $data['id'] ?? Validator::get('id');
        if (empty($id)) Response::error('ID не указан');

        try {
            $this->docker->removeContainer($id, true);
            
            // Удалить из БД
            $db = Database::getInstance();
            $stmt = $db->prepare('DELETE FROM containers WHERE docker_id LIKE ?');
            $stmt->execute([$id . '%']);

            Security::auditLog('container_remove', 'container', $id);
            Response::success(null, 'Контейнер удалён');
        } catch (\Exception $e) {
            Response::error('Ошибка удаления: ' . $e->getMessage());
        }
    }

    /**
     * Выполнить команду в контейнере
     */
    public function exec(): void {
        $data = Validator::jsonBody();
        $id = $data['id'] ?? '';
        $command = $data['command'] ?? '';

        if (empty($id) || empty($command)) {
            Response::error('Укажите ID контейнера и команду');
        }

        try {
            $output = $this->docker->execInContainer($id, $command);
            Response::success(['output' => $output]);
        } catch (\Exception $e) {
            Response::error('Ошибка выполнения: ' . $e->getMessage());
        }
    }

    /**
     * Статистика контейнера
     */
    public function stats(): void {
        $id = Validator::get('id');
        if (empty($id)) Response::error('ID не указан');

        try {
            $monitor = new MonitorService();
            $stats = $monitor->getContainerStats($id);
            Response::success($stats);
        } catch (\Exception $e) {
            Response::error('Ошибка: ' . $e->getMessage());
        }
    }

    /**
     * Процессы контейнера
     */
    public function top(): void {
        $id = Validator::get('id');
        if (empty($id)) Response::error('ID не указан');

        try {
            $top = $this->docker->containerTop($id);
            Response::success($top);
        } catch (\Exception $e) {
            Response::error('Ошибка: ' . $e->getMessage());
        }
    }

    /**
     * Переименовать
     */
    public function rename(): void {
        $data = Validator::jsonBody();
        $id = $data['id'] ?? '';
        $name = $data['name'] ?? '';

        if (empty($id) || empty($name)) Response::error('Укажите ID и новое имя');

        try {
            $this->docker->renameContainer($id, $name);
            Security::auditLog('container_rename', 'container', $id, $name);
            Response::success(null, 'Контейнер переименован');
        } catch (\Exception $e) {
            Response::error('Ошибка: ' . $e->getMessage());
        }
    }

    /**
     * Обновить ресурсы контейнера
     */
    public function update(): void {
        $data = Validator::jsonBody();
        $id = $data['id'] ?? '';
        if (empty($id)) Response::error('ID не указан');

        try {
            $this->docker->updateContainer($id, $data);
            Security::auditLog('container_update', 'container', $id, json_encode($data));
            Response::success(null, 'Контейнер обновлён');
        } catch (\Exception $e) {
            Response::error('Ошибка: ' . $e->getMessage());
        }
    }

    /**
     * Импорт существующих контейнеров
     */
    public function import(): void {
        try {
            $containers = $this->docker->listContainers(true);
            $db = Database::getInstance();
            $imported = 0;

            foreach ($containers as $c) {
                $dockerId = $c['Id'] ?? '';
                $name = ltrim($c['Names'][0] ?? '', '/');

                // Проверить, не импортирован ли уже
                $stmt = $db->prepare('SELECT id FROM containers WHERE docker_id = ?');
                $stmt->execute([$dockerId]);
                if ($stmt->fetch()) continue;

                $stmt = $db->prepare('INSERT INTO containers (docker_id, name, user_id, image, config_json) VALUES (?, ?, ?, ?, ?)');
                $stmt->execute([
                    $dockerId,
                    $name,
                    AuthMiddleware::userId(),
                    $c['Image'] ?? '',
                    '{}',
                ]);
                $imported++;
            }

            Security::auditLog('containers_import', 'system', '', "Imported {$imported} containers");
            Response::success(['imported' => $imported], "Импортировано контейнеров: {$imported}");
        } catch (\Exception $e) {
            Response::error('Ошибка импорта: ' . $e->getMessage());
        }
    }

    /**
     * Страница терминала
     */
    public function terminal(): void {
        $id = Validator::get('id');
        if (empty($id)) {
            header('Location: /containers');
            exit;
        }
        Response::view('containers/terminal', ['containerId' => $id]);
    }
}
