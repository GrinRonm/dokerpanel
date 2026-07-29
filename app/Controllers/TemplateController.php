<?php
/**
 * DockerPanel — Template Controller
 */

class TemplateController {
    public function index(): void {
        if (isAjax()) { $this->list(); return; }
        Response::view('templates');
    }

    public function list(): void {
        $db = Database::getInstance();
        $category = Validator::get('category', '');
        if (!empty($category)) {
            $stmt = $db->prepare('SELECT * FROM templates WHERE is_active = 1 AND category = ? ORDER BY sort_order, name');
            $stmt->execute([$category]);
        } else {
            $stmt = $db->query('SELECT * FROM templates WHERE is_active = 1 ORDER BY sort_order, name');
        }
        $templates = $stmt->fetchAll();
        
        // Декодируем config_json
        foreach ($templates as &$t) {
            $t['config'] = json_decode($t['config_json'], true) ?: [];
        }
        
        Response::success($templates);
    }

    /**
     * Развернуть контейнер из шаблона
     */
    public function deploy(): void {
        $data = Validator::jsonBody();
        $templateId = (int)($data['template_id'] ?? 0);
        $name = $data['name'] ?? '';
        
        if (!$templateId) Response::error('Шаблон не указан');
        if (empty($name)) Response::error('Укажите имя контейнера');

        $db = Database::getInstance();
        $stmt = $db->prepare('SELECT * FROM templates WHERE id = ?');
        $stmt->execute([$templateId]);
        $template = $stmt->fetch();

        if (!$template) Response::error('Шаблон не найден', 404);

        $config = json_decode($template['config_json'], true) ?: [];
        $tag = $data['tag'] ?? $template['default_tag'] ?? 'latest';

        // Объединить конфиг шаблона с пользовательскими настройками
        $createData = [
            'name' => $name,
            'image' => $template['image'],
            'tag' => $tag,
            'template_id' => $templateId,
            'cmd' => $data['cmd'] ?? $config['cmd'] ?? '',
            'env' => $data['env'] ?? $config['env'] ?? [],
            'ports' => $data['ports'] ?? $config['ports'] ?? [],
            'volumes' => $data['volumes'] ?? $config['volumes'] ?? [],
            'cpu' => $data['cpu'] ?? $config['cpu'] ?? DEFAULT_CPU_LIMIT,
            'ram' => $data['ram'] ?? $config['ram'] ?? DEFAULT_RAM_LIMIT,
            'restart' => $config['restart'] ?? 'unless-stopped',
            'network' => $data['network'] ?? '',
            'privileged' => $data['privileged'] ?? false,
        ];

        // Используем ContainerController для создания
        $_POST = [];
        // Передаём через JSON body
        $GLOBALS['_override_json_body'] = $createData;
        
        // Делаем через DockerService напрямую
        try {
            $docker = new DockerService();
            $fullImage = $template['image'] . ':' . $tag;
            $docker->pullImage($template['image'], $tag);

            $containerConfig = [
                'image' => $fullImage,
                'cmd' => $createData['cmd'],
                'env' => $createData['env'],
                'ports' => $createData['ports'],
                'volumes' => $createData['volumes'],
                'cpu' => $createData['cpu'],
                'ram' => $createData['ram'],
                'restart' => $createData['restart'],
                'network' => $createData['network'],
                'privileged' => $createData['privileged'],
            ];

            $result = $docker->createContainer($containerConfig, $name);
            $containerId = $result['Id'] ?? '';

            if (empty($containerId)) Response::error('Не удалось создать контейнер');

            $docker->startContainer($containerId);

            $stmt = $db->prepare('INSERT INTO containers (docker_id, name, user_id, template_id, image, config_json) VALUES (?, ?, ?, ?, ?, ?)');
            $stmt->execute([$containerId, $name, AuthMiddleware::userId(), $templateId, $fullImage, json_encode($containerConfig)]);

            Security::auditLog('template_deploy', 'container', $containerId, $template['name']);
            Response::success(['id' => $containerId, 'name' => $name], "Контейнер {$name} создан из шаблона {$template['name']}");
        } catch (\Exception $e) {
            Response::error('Ошибка развёртывания: ' . $e->getMessage());
        }
    }
}
