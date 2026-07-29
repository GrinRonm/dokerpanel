<?php
/**
 * DockerPanel — Main Configuration
 */

// Пути
if (!defined('ROOT_PATH')) {
    define('ROOT_PATH', dirname(__DIR__));
}
define('APP_PATH', ROOT_PATH . '/app');
define('VIEW_PATH', ROOT_PATH . '/views');
define('STORAGE_PATH', ROOT_PATH . '/storage');
define('DB_PATH', STORAGE_PATH . '/database/database.sqlite');
define('PUBLIC_PATH', ROOT_PATH . '/public');

// Безопасность
define('SECRET_KEY', '%%SECRET_KEY%%'); // Заменяется при установке
define('SESSION_LIFETIME', 86400); // 24 часа
define('CSRF_TOKEN_LIFETIME', 3600); // 1 час

// Docker
define('DOCKER_SOCKET', '/var/run/docker.sock');
define('DOCKER_API_VERSION', 'v1.44');
define('DOCKER_API_URL', 'http://localhost/' . DOCKER_API_VERSION);

// WebSocket терминал
define('WS_HOST', '0.0.0.0');
define('WS_PORT', 8765);

// Лимиты по умолчанию
define('DEFAULT_CPU_LIMIT', '1');
define('DEFAULT_RAM_LIMIT', '512m');
define('DEFAULT_DISK_LIMIT', '10g');

// Автозапуск
define('AUTO_START_CONTAINERS', true);

// Версия
define('APP_VERSION', '1.0.0');
define('APP_NAME', 'DockerPanel');
