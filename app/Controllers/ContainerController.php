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

            // 1. Сначала добавляем ожидающие контейнеры
            $db = Database::getInstance();
            $pending = $db->query('SELECT * FROM pending_containers ORDER BY created_at DESC')->fetchAll();
            foreach ($pending as $p) {
                $result[] = [
                    'id' => 'pending_' . $p['id'],
                    'short_id' => '...',
                    'name' => $p['name'],
                    'image' => $p['image'],
                    'state' => 'pending',
                    'status' => $p['status'],
                    'ports' => [],
                    'created' => $p['created_at'],
                    'size_rw' => '0 B',
                    'size_root' => '0 B',
                    'network' => [],
                    'is_pending' => true
                ];
            }

            // 2. Добавляем реальные контейнеры
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
                    'is_pending' => false
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

            // Сохранить в БД как ожидающий
            $db = Database::getInstance();
            $stmt = $db->prepare('INSERT INTO pending_containers (name, image, status, config_json, user_id, template_id) VALUES (?, ?, ?, ?, ?, ?)');
            $stmt->execute([
                $data['name'],
                $fullImage,
                'Скачивание образа...',
                json_encode($config),
                AuthMiddleware::userId(),
                $data['template_id'] ?? null
            ]);
            
            $pendingId = $db->lastInsertId();

            // Запускаем фоновый процесс создания
            $cmd = sprintf(
                "nohup php %s %d > /dev/null 2>&1 &",
                escapeshellarg(ROOT_PATH . '/scripts/do_create.php'),
                $pendingId
            );
            shell_exec($cmd);

            Security::auditLog('container_create_request', 'container', '', $data['name']);

            Response::success(['id' => 'pending_' . $pendingId, 'name' => $data['name']], 'Процесс создания запущен');
        } catch (\Exception $e) {
            Response::error('Ошибка создания: ' . $e->getMessage());
        }
    }

    /**
     * Обновить (редактировать) контейнер
     */
    public function update(): void {
        $data = Validator::jsonBody();
        $id = (int)($data['id'] ?? 0);
        
        if (!$id) Response::error('ID контейнера не указан');

        $db = Database::getInstance();
        $stmt = $db->prepare('SELECT * FROM containers WHERE id = ?');
        $stmt->execute([$id]);
        $container = $stmt->fetch();

        if (!$container) Response::error('Контейнер не найден');

        try {
            $image = $data['image'];
            $tag = $data['tag'] ?? 'latest';
            $fullImage = "{$image}:{$tag}";

            $this->docker->pullImage($image, $tag);

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

            // Удаляем старый контейнер
            try {
                $this->docker->removeContainer($container['docker_id'], true, false);
            } catch (\Exception $e) {
                // Игнорируем если он уже удален
            }

            // Создаём новый с тем же именем
            $result = $this->docker->createContainer($config, $container['name']);
            $newDockerId = $result['Id'] ?? '';

            if (empty($newDockerId)) {
                Response::error('Не удалось пересоздать контейнер');
            }

            $this->docker->startContainer($newDockerId);

            // Если это шаблон Ubuntu Systemd, устанавливаем базовые утилиты (синхронно, чтобы дождаться установки)
            if (strpos($fullImage, 'systemd-ubuntu') !== false) {
                $cmd = "docker exec {$newDockerId} bash -c 'sleep 2 && apt-get update && apt-get install -y curl wget sudo nano net-tools iproute2'";
                shell_exec($cmd);
            }

            // Обновляем в БД
            $stmt = $db->prepare('UPDATE containers SET docker_id = ?, image = ?, config_json = ? WHERE id = ?');
            $stmt->execute([$newDockerId, $fullImage, json_encode($config), $id]);

            Security::auditLog('container_update', 'container', $newDockerId, $container['name']);
            Response::success(['id' => $newDockerId, 'name' => $container['name']], 'Контейнер обновлён');
        } catch (\Exception $e) {
            Response::error('Ошибка обновления: ' . $e->getMessage());
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

                // Попробуем получить из БД
                $db = Database::getInstance();
                $stmt = $db->prepare('SELECT id, config_json, template_id FROM containers WHERE docker_id = ?');
                $stmt->execute([$id]);
                $dbContainer = $stmt->fetch();
                if ($dbContainer) {
                    $result['db_id'] = $dbContainer['id'];
                    $result['template_id'] = $dbContainer['template_id'];
                    $result['config'] = json_decode($dbContainer['config_json'], true);
                }

                
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
     * Получить логи контейнера
     */
    public function logs(): void {
        $id = $_GET['id'] ?? '';
        if (!$id) {
            Response::error('ID контейнера обязателен');
        }
        try {
            $logs = $this->docker->getLogs($id);
            Response::success(['logs' => $logs]);
        } catch (\Exception $e) {
            Response::error($e->getMessage());
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
