<?php
/**
 * DockerPanel — User Controller
 */

class UserController {
    public function index(): void {
        Security::requireRole('admin');
        if (isAjax()) { $this->list(); return; }
        Response::view('users');
    }

    public function list(): void {
        Security::requireRole('admin');
        $db = Database::getInstance();
        $users = $db->query('SELECT id, username, email, role, is_active, api_token, last_login, created_at FROM users ORDER BY created_at DESC')->fetchAll();
        Response::success($users);
    }

    public function create(): void {
        Security::requireRole('admin');
        $data = Validator::jsonBody();
        $validator = new Validator();
        $validator->required('username', $data['username'] ?? '')
                  ->required('password', $data['password'] ?? '')
                  ->minLength('username', $data['username'] ?? '', 3)
                  ->minLength('password', $data['password'] ?? '', 6);
        if ($validator->hasErrors()) Response::error($validator->getFirstError());

        $db = Database::getInstance();
        // Проверка уникальности
        $stmt = $db->prepare('SELECT id FROM users WHERE username = ?');
        $stmt->execute([$data['username']]);
        if ($stmt->fetch()) Response::error('Пользователь уже существует');

        $apiToken = Security::generateToken(32);
        $stmt = $db->prepare('INSERT INTO users (username, password_hash, email, role, api_token) VALUES (?, ?, ?, ?, ?)');
        $stmt->execute([
            $data['username'],
            Security::hashPassword($data['password']),
            $data['email'] ?? '',
            $data['role'] ?? 'user',
            $apiToken,
        ]);

        Security::auditLog('user_create', 'user', (string)$db->lastInsertId(), $data['username']);
        Response::success(['api_token' => $apiToken], 'Пользователь создан');
    }

    public function update(): void {
        Security::requireRole('admin');
        $data = Validator::jsonBody();
        $id = (int)($data['id'] ?? 0);
        if (!$id) Response::error('ID не указан');

        $db = Database::getInstance();
        $sets = [];
        $params = [];

        if (isset($data['email'])) { $sets[] = 'email = ?'; $params[] = $data['email']; }
        if (isset($data['role'])) { $sets[] = 'role = ?'; $params[] = $data['role']; }
        if (isset($data['is_active'])) { $sets[] = 'is_active = ?'; $params[] = (int)$data['is_active']; }
        if (!empty($data['password'])) { $sets[] = 'password_hash = ?'; $params[] = Security::hashPassword($data['password']); }
        if (isset($data['regenerate_token'])) { $sets[] = 'api_token = ?'; $params[] = Security::generateToken(32); }

        if (empty($sets)) Response::error('Нет данных для обновления');

        $params[] = $id;
        $stmt = $db->prepare('UPDATE users SET ' . implode(', ', $sets) . ', updated_at = datetime("now") WHERE id = ?');
        $stmt->execute($params);

        Security::auditLog('user_update', 'user', (string)$id);
        Response::success(null, 'Пользователь обновлён');
    }

    public function delete(): void {
        Security::requireRole('admin');
        $data = Validator::jsonBody();
        $id = (int)($data['id'] ?? 0);
        if (!$id) Response::error('ID не указан');
        if ($id === AuthMiddleware::userId()) Response::error('Нельзя удалить себя');

        $db = Database::getInstance();
        $stmt = $db->prepare('DELETE FROM users WHERE id = ?');
        $stmt->execute([$id]);

        Security::auditLog('user_delete', 'user', (string)$id);
        Response::success(null, 'Пользователь удалён');
    }
}
