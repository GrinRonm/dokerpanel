<?php
/**
 * DockerPanel — Скрипт фонового создания контейнера
 */

define('ROOT_PATH', dirname(__DIR__));
set_time_limit(0);
require ROOT_PATH . '/config/config.php';
require ROOT_PATH . '/config/database.php';
require ROOT_PATH . '/app/Services/PortService.php';
require ROOT_PATH . '/app/Services/DockerService.php';

// Функция логгирования ошибки и выхода
function fail($db, $id, $message) {
    $stmt = $db->prepare("UPDATE pending_containers SET status = ? WHERE id = ?");
    $stmt->execute(['Ошибка: ' . $message, $id]);
    error_log("do_create.php [ID: $id] ERROR: $message");
    exit(1);
}

// Функция обновления статуса
function updateStatus($db, $id, $status) {
    $stmt = $db->prepare("UPDATE pending_containers SET status = ? WHERE id = ?");
    $stmt->execute([$status, $id]);
}

if ($argc < 2) {
    die("Usage: php do_create.php <pending_id>\n");
}

$pendingId = (int)$argv[1];
$db = Database::getInstance();
$docker = new DockerService();

// Получаем информацию о задаче
$stmt = $db->prepare("SELECT * FROM pending_containers WHERE id = ?");
$stmt->execute([$pendingId]);
$pending = $stmt->fetch();

if (!$pending) {
    die("Pending container not found\n");
}

try {
    $config = json_decode($pending['config_json'], true);
    $fullImage = $config['image'];
    list($image, $tag) = explode(':', $fullImage);

    // 1. Скачивание образа
    updateStatus($db, $pendingId, 'Скачивание образа...');
    $docker->pullImage($image, $tag);

    // 2. Создание контейнера
    updateStatus($db, $pendingId, 'Создание контейнера...');
    $result = $docker->createContainer($config, $pending['name']);
    $containerId = $result['Id'] ?? '';

    if (empty($containerId)) {
        fail($db, $pendingId, 'Не удалось создать контейнер (пустой ID)');
    }

    // 3. Запуск контейнера
    updateStatus($db, $pendingId, 'Запуск контейнера...');
    $docker->startContainer($containerId);

    // 4. Пост-установка (для системных шаблонов)
    if (strpos($fullImage, 'systemd-ubuntu') !== false) {
        updateStatus($db, $pendingId, 'Настройка системных утилит (может занять до 1 мин)...');
        $cmd = "docker exec {$containerId} bash -c 'sleep 2 && apt-get update && DEBIAN_FRONTEND=noninteractive apt-get install -yq curl wget sudo nano net-tools iproute2' > /dev/null 2>&1";
        shell_exec($cmd);
    }

    // 5. Сохраняем в основную БД
    updateStatus($db, $pendingId, 'Завершение...');
    $stmt = $db->prepare('INSERT INTO containers (docker_id, name, user_id, template_id, image, config_json) VALUES (?, ?, ?, ?, ?, ?)');
    $stmt->execute([
        $containerId,
        $pending['name'],
        $pending['user_id'],
        $pending['template_id'],
        $fullImage,
        $pending['config_json'],
    ]);

    // 6. Удаляем временную задачу
    $stmt = $db->prepare('DELETE FROM pending_containers WHERE id = ?');
    $stmt->execute([$pendingId]);

} catch (\Exception $e) {
    fail($db, $pendingId, $e->getMessage());
}
