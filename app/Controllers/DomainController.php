<?php
/**
 * DockerPanel — Domain Controller
 */

class DomainController {
    public function index(): void {
        if (isAjax()) { $this->list(); return; }
        Response::view('domains');
    }

    public function list(): void {
        $db = Database::getInstance();
        $domains = $db->query('SELECT d.*, c.name as container_name FROM domains d LEFT JOIN containers c ON d.container_id = c.id ORDER BY d.created_at DESC')->fetchAll();
        Response::success($domains);
    }

    public function create(): void {
        $data = Validator::jsonBody();
        $containerId = $data['container_id'] ?? '';
        $subdomain = $data['subdomain'] ?? '';
        $containerPort = (int)($data['container_port'] ?? 80);

        if (empty($containerId) || empty($subdomain)) Response::error('Заполните все поля');

        $db = Database::getInstance();
        $stmt = $db->prepare("SELECT value FROM settings WHERE key = 'base_domain'");
        $stmt->execute();
        $baseDomain = $stmt->fetchColumn();

        if (empty($baseDomain)) Response::error('Базовый домен не настроен. Перейдите в Настройки → Домены.');

        try {
            $docker = new DockerService();
            $containerIp = $docker->getContainerIp($containerId);
            if ($containerIp === '-') Response::error('Не удалось определить IP контейнера');

            $nginx = new NginxService();
            $configPath = $nginx->createProxyConfig($subdomain, $baseDomain, $containerIp, $containerPort);

            // Получить internal container_id из БД
            $stmt = $db->prepare('SELECT id FROM containers WHERE docker_id LIKE ?');
            $stmt->execute([$containerId . '%']);
            $cRow = $stmt->fetch();

            $stmt = $db->prepare('INSERT INTO domains (container_id, subdomain, base_domain, container_port, nginx_config_path) VALUES (?, ?, ?, ?, ?)');
            $stmt->execute([$cRow ? $cRow['id'] : 0, $subdomain, $baseDomain, $containerPort, $configPath]);

            Security::auditLog('domain_create', 'domain', "{$subdomain}.{$baseDomain}");
            Response::success(['domain' => "{$subdomain}.{$baseDomain}"], "Домен {$subdomain}.{$baseDomain} создан");
        } catch (\Exception $e) { Response::error($e->getMessage()); }
    }

    public function remove(): void {
        $data = Validator::jsonBody();
        $id = (int)($data['id'] ?? 0);
        if (!$id) Response::error('ID не указан');

        $db = Database::getInstance();
        $stmt = $db->prepare('SELECT * FROM domains WHERE id = ?');
        $stmt->execute([$id]);
        $domain = $stmt->fetch();
        if (!$domain) Response::error('Домен не найден');

        $nginx = new NginxService();
        $nginx->removeConfig($domain['nginx_config_path']);

        $stmt = $db->prepare('DELETE FROM domains WHERE id = ?');
        $stmt->execute([$id]);

        Security::auditLog('domain_remove', 'domain', $domain['subdomain']);
        Response::success(null, 'Домен удалён');
    }

    public function ssl(): void {
        $data = Validator::jsonBody();
        $id = (int)($data['id'] ?? 0);
        if (!$id) Response::error('ID не указан');

        $db = Database::getInstance();
        $stmt = $db->prepare('SELECT * FROM domains WHERE id = ?');
        $stmt->execute([$id]);
        $domain = $stmt->fetch();
        if (!$domain) Response::error('Домен не найден');

        try {
            $nginx = new NginxService();
            $fullDomain = "{$domain['subdomain']}.{$domain['base_domain']}";
            $output = $nginx->enableSSL($fullDomain);

            $stmt = $db->prepare('UPDATE domains SET ssl_enabled = 1 WHERE id = ?');
            $stmt->execute([$id]);

            Response::success(['output' => $output], "SSL для {$fullDomain} включён");
        } catch (\Exception $e) { Response::error($e->getMessage()); }
    }
}
