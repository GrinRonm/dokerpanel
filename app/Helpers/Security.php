<?php
/**
 * DockerPanel — Security Helper
 */

class Security {

    /**
     * Очистка строки от XSS
     */
    public static function sanitize(string $input): string {
        return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
    }

    /**
     * Очистка для shell-команд
     */
    public static function shellEscape(string $input): string {
        return escapeshellarg($input);
    }

    /**
     * Проверка на опасные команды (для выполнения на хосте)
     */
    public static function isDangerousCommand(string $cmd): bool {
        $dangerous = [
            'rm -rf /',
            'mkfs',
            'dd if=',
            ':(){',
            'chmod -R 777 /',
            'shutdown',
            'reboot',
            'init 0',
            'init 6',
            'halt',
            'poweroff',
        ];
        $cmdLower = strtolower(trim($cmd));
        foreach ($dangerous as $d) {
            if (strpos($cmdLower, strtolower($d)) !== false) {
                return true;
            }
        }
        return false;
    }

    /**
     * Генерация случайного токена
     */
    public static function generateToken(int $length = 64): string {
        return bin2hex(random_bytes($length / 2));
    }

    /**
     * Хеширование пароля
     */
    public static function hashPassword(string $password): string {
        return password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
    }

    /**
     * Проверка пароля
     */
    public static function verifyPassword(string $password, string $hash): bool {
        return password_verify($password, $hash);
    }

    /**
     * Получить IP-адрес клиента
     */
    public static function getClientIp(): string {
        $headers = ['HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR'];
        foreach ($headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ip = explode(',', $_SERVER[$header])[0];
                return trim($ip);
            }
        }
        return '0.0.0.0';
    }

    /**
     * Записать в лог действий
     */
    public static function auditLog(string $action, string $targetType = '', string $targetId = '', string $details = ''): void {
        try {
            $db = Database::getInstance();
            $stmt = $db->prepare('INSERT INTO audit_log (user_id, action, target_type, target_id, details, ip_address) VALUES (?, ?, ?, ?, ?, ?)');
            $stmt->execute([
                $_SESSION['user_id'] ?? null,
                $action,
                $targetType,
                $targetId,
                $details,
                self::getClientIp(),
            ]);
        } catch (\Exception $e) {
            error_log("Audit log error: " . $e->getMessage());
        }
    }

    /**
     * Проверить роль пользователя
     */
    public static function requireRole(string $role): void {
        $userRole = $_SESSION['user_role'] ?? 'viewer';
        $roles = ['viewer' => 0, 'user' => 1, 'admin' => 2];
        $required = $roles[$role] ?? 0;
        $current = $roles[$userRole] ?? 0;
        
        if ($current < $required) {
            Response::error('Недостаточно прав', 403);
        }
    }

    /**
     * Проверить rate limit
     */
    public static function rateLimit(string $key, int $maxAttempts = 10, int $windowSeconds = 60): bool {
        $cacheFile = STORAGE_PATH . '/cache/rate_' . md5($key) . '.json';
        $cacheDir = dirname($cacheFile);
        if (!is_dir($cacheDir)) {
            mkdir($cacheDir, 0755, true);
        }

        $data = ['attempts' => [], 'blocked_until' => 0];
        if (file_exists($cacheFile)) {
            $data = json_decode(file_get_contents($cacheFile), true) ?: $data;
        }

        $now = time();

        // Проверка блокировки
        if ($data['blocked_until'] > $now) {
            return false;
        }

        // Очистка старых попыток
        $data['attempts'] = array_filter($data['attempts'], fn($t) => $t > $now - $windowSeconds);

        // Проверка лимита
        if (count($data['attempts']) >= $maxAttempts) {
            $data['blocked_until'] = $now + $windowSeconds;
            file_put_contents($cacheFile, json_encode($data));
            return false;
        }

        // Добавление попытки
        $data['attempts'][] = $now;
        file_put_contents($cacheFile, json_encode($data));
        return true;
    }
}
