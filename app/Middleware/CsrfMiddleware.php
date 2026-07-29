<?php
/**
 * DockerPanel — CSRF Middleware
 */

class CsrfMiddleware {

    /**
     * Получить или создать CSRF-токен
     */
    public static function getToken(): string {
        if (empty($_SESSION['csrf_token']) || empty($_SESSION['csrf_time']) || 
            (time() - $_SESSION['csrf_time'] > CSRF_TOKEN_LIFETIME)) {
            $_SESSION['csrf_token'] = Security::generateToken(32);
            $_SESSION['csrf_time'] = time();
        }
        return $_SESSION['csrf_token'];
    }

    /**
     * Проверить CSRF-токен
     */
    public static function verify(): void {
        $token = $_POST['_csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        
        if (empty($token) || empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
            Response::error('Ошибка безопасности (CSRF). Обновите страницу.', 403);
        }
    }
}
