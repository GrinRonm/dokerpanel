<?php
/**
 * DockerPanel — Auth Middleware
 */

class AuthMiddleware {

    /**
     * Проверить авторизацию
     */
    public static function check(): bool {
        return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
    }

    /**
     * Получить текущего пользователя
     */
    public static function user(): ?array {
        if (!self::check()) return null;
        
        $db = Database::getInstance();
        $stmt = $db->prepare('SELECT id, username, email, role, created_at FROM users WHERE id = ? AND is_active = 1');
        $stmt->execute([$_SESSION['user_id']]);
        return $stmt->fetch() ?: null;
    }

    /**
     * ID текущего пользователя
     */
    public static function userId(): ?int {
        return $_SESSION['user_id'] ?? null;
    }

    /**
     * Роль текущего пользователя
     */
    public static function role(): string {
        return $_SESSION['user_role'] ?? 'viewer';
    }

    /**
     * Проверка — администратор ли
     */
    public static function isAdmin(): bool {
        return self::role() === 'admin';
    }
}
