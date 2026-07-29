<?php
/**
 * DockerPanel — Nginx Service
 * 
 * Управление конфигурациями Nginx для доменов контейнеров
 */

class NginxService {

    private string $sitesPath;

    public function __construct() {
        $db = Database::getInstance();
        $stmt = $db->prepare("SELECT value FROM settings WHERE key = 'nginx_sites_path'");
        $stmt->execute();
        $this->sitesPath = ($stmt->fetchColumn()) ?: '/etc/nginx/sites-enabled';
    }

    /**
     * Создать reverse proxy конфигурацию для контейнера
     */
    public function createProxyConfig(string $subdomain, string $baseDomain, string $containerIp, int $containerPort): string {
        $serverName = "{$subdomain}.{$baseDomain}";
        $configName = "dockerpanel_{$subdomain}";
        $configPath = "{$this->sitesPath}/{$configName}.conf";

        $config = <<<NGINX
# DockerPanel auto-generated config for {$serverName}
server {
    listen 80;
    server_name {$serverName};

    location / {
        proxy_pass http://{$containerIp}:{$containerPort};
        proxy_set_header Host \$host;
        proxy_set_header X-Real-IP \$remote_addr;
        proxy_set_header X-Forwarded-For \$proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto \$scheme;
        proxy_http_version 1.1;
        proxy_set_header Upgrade \$http_upgrade;
        proxy_set_header Connection "upgrade";
        proxy_read_timeout 86400;
        proxy_buffering off;
    }

    # Логи
    access_log /var/log/nginx/{$configName}_access.log;
    error_log /var/log/nginx/{$configName}_error.log;
}
NGINX;

        file_put_contents($configPath, $config);
        $this->reload();

        return $configPath;
    }

    /**
     * Удалить конфигурацию
     */
    public function removeConfig(string $configPath): bool {
        if (file_exists($configPath)) {
            unlink($configPath);
            $this->reload();
            return true;
        }
        return false;
    }

    /**
     * Добавить SSL (Let's Encrypt)
     */
    public function enableSSL(string $domain): string {
        $db = Database::getInstance();
        $stmt = $db->prepare("SELECT value FROM settings WHERE key = 'ssl_email'");
        $stmt->execute();
        $email = $stmt->fetchColumn() ?: '';

        $cmd = "certbot --nginx -d {$domain} --non-interactive --agree-tos";
        if (!empty($email)) {
            $cmd .= " --email {$email}";
        } else {
            $cmd .= " --register-unsafely-without-email";
        }

        return shell_exec("{$cmd} 2>&1") ?? '';
    }

    /**
     * Проверить конфигурацию Nginx
     */
    public function test(): array {
        $output = shell_exec("nginx -t 2>&1") ?? '';
        $valid = strpos($output, 'test is successful') !== false;
        return ['valid' => $valid, 'output' => $output];
    }

    /**
     * Перезагрузить Nginx
     */
    public function reload(): string {
        return shell_exec("systemctl reload nginx 2>&1") ?? '';
    }
}
