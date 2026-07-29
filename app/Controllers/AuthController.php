<?php
/**
 * DockerPanel — Auth Controller
 */

class AuthController {

    /**
     * Страница входа / обработка логина
     */
    public function login(): void {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $data = Validator::jsonBody();
            $username = $data['username'] ?? '';
            $password = $data['password'] ?? '';

            if (empty($username) || empty($password)) {
                Response::error('Заполните все поля');
            }

            // Rate limiting
            $key = 'login_' . Security::getClientIp();
            if (!Security::rateLimit($key, 5, 300)) {
                Response::error('Слишком много попыток. Подождите 5 минут.');
            }

            $db = Database::getInstance();
            $stmt = $db->prepare('SELECT * FROM users WHERE username = ? AND is_active = 1');
            $stmt->execute([$username]);
            $user = $stmt->fetch();

            if (!$user || !Security::verifyPassword($password, $user['password_hash'])) {
                Security::auditLog('login_failed', 'user', $username);
                Response::error('Неверный логин или пароль');
            }

            // Успешный вход
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_role'] = $user['role'];
            $_SESSION['username'] = $user['username'];

            // Обновить last_login
            $stmt = $db->prepare('UPDATE users SET last_login = datetime("now") WHERE id = ?');
            $stmt->execute([$user['id']]);

            Security::auditLog('login', 'user', (string)$user['id']);

            Response::success(null, 'Авторизация успешна');
        }

        Response::view('auth/login');
    }

    /**
     * Выход
     */
    public function logout(): void {
        Security::auditLog('logout', 'user', (string)($_SESSION['user_id'] ?? 0));
        session_destroy();
        header('Location: /auth/login');
        exit;
    }

    /**
     * Проверить текущую сессию
     */
    public function check(): void {
        if (AuthMiddleware::check()) {
            Response::success(AuthMiddleware::user());
        }
        Response::error('Not authenticated', 401);
    }
}
