<?php
/**
 * DockerPanel — Entry Point / Router
 * 
 * Все запросы проходят через этот файл.
 * Nginx перенаправляет сюда через try_files.
 */

// Загрузка конфигурации
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';

// Автозагрузка классов
spl_autoload_register(function ($class) {
    $paths = [
        APP_PATH . '/Controllers/' . $class . '.php',
        APP_PATH . '/Models/' . $class . '.php',
        APP_PATH . '/Services/' . $class . '.php',
        APP_PATH . '/Middleware/' . $class . '.php',
        APP_PATH . '/Helpers/' . $class . '.php',
    ];
    foreach ($paths as $path) {
        if (file_exists($path)) {
            require_once $path;
            return;
        }
    }
});

// Старт сессии
session_start();
session_regenerate_id(false);

// Определение маршрута
$requestUri = $_SERVER['REQUEST_URI'] ?? '/';
$basePath = '/';
$path = parse_url($requestUri, PHP_URL_PATH);
if (strpos($path, $basePath) === 0) {
    $path = substr($path, strlen($basePath));
}
$path = trim($path, '/');

// Статические файлы — отдаём напрямую через Nginx
if (preg_match('/\.(css|js|png|jpg|jpeg|gif|svg|ico|woff2?|ttf|eot|map)$/', $path)) {
    return false;
}

// Загрузка маршрутов
$routes = require __DIR__ . '/config/routes.php';

// CSRF-защита для POST-запросов
if ($_SERVER['REQUEST_METHOD'] === 'POST' && strpos($path, 'api/') !== 0) {
    CsrfMiddleware::verify();
}

// Проверка авторизации (кроме auth/* маршрутов)
if (strpos($path, 'auth/') !== 0 && strpos($path, 'api/') !== 0) {
    if (!AuthMiddleware::check()) {
        if (isAjax()) {
            Response::json(['error' => 'Unauthorized'], 401);
        }
        header('Location: /auth/login');
        exit;
    }
}

// API аутентификация через токен
if (strpos($path, 'api/') === 0) {
    $token = $_SERVER['HTTP_AUTHORIZATION'] ?? $_GET['token'] ?? '';
    $token = str_replace('Bearer ', '', $token);
    if (empty($token)) {
        Response::json(['error' => 'API token required'], 401);
    }
    $db = Database::getInstance();
    $stmt = $db->prepare('SELECT * FROM users WHERE api_token = ? AND is_active = 1');
    $stmt->execute([$token]);
    $apiUser = $stmt->fetch();
    if (!$apiUser) {
        Response::json(['error' => 'Invalid API token'], 401);
    }
    $_SESSION['user_id'] = $apiUser['id'];
    $_SESSION['user_role'] = $apiUser['role'];
}

// Поиск маршрута
if (isset($routes[$path])) {
    [$controllerName, $method] = $routes[$path];
    $controller = new $controllerName();
    
    if (!method_exists($controller, $method)) {
        Response::error('Method not found', 404);
    }
    
    $controller->$method();
} else {
    // 404
    if (isAjax()) {
        Response::json(['error' => 'Route not found'], 404);
    }
    http_response_code(404);
    echo '<!DOCTYPE html><html><head><title>404</title></head><body style="background:#0f0f23;color:#fff;display:flex;align-items:center;justify-content:center;height:100vh;font-family:Inter,sans-serif"><div style="text-align:center"><h1 style="font-size:72px;margin:0;background:linear-gradient(135deg,#00d4ff,#7c3aed);-webkit-background-clip:text;-webkit-text-fill-color:transparent">404</h1><p>Страница не найдена</p><a href="/" style="color:#00d4ff">← На главную</a></div></body></html>';
}

/**
 * Проверка AJAX-запроса
 */
function isAjax(): bool {
    return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
           strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest' ||
           !empty($_SERVER['HTTP_ACCEPT']) && 
           strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false;
}
