<?php
/**
 * DockerPanel — Docker Compose Service
 */

class DockerComposeService {

    private string $basePath;

    public function __construct() {
        $this->basePath = STORAGE_PATH . '/compose';
        if (!is_dir($this->basePath)) {
            mkdir($this->basePath, 0755, true);
        }
    }

    /**
     * Создать Compose-проект
     */
    public function create(string $name, string $yaml, int $userId): array {
        $db = Database::getInstance();
        $projectPath = $this->basePath . '/' . $name;

        if (!is_dir($projectPath)) {
            mkdir($projectPath, 0755, true);
        }

        file_put_contents($projectPath . '/docker-compose.yml', $yaml);

        $stmt = $db->prepare('INSERT INTO compose_projects (name, user_id, yaml_content, project_path, status) VALUES (?, ?, ?, ?, ?)');
        $stmt->execute([$name, $userId, $yaml, $projectPath, 'stopped']);

        return ['id' => $db->lastInsertId(), 'name' => $name, 'path' => $projectPath];
    }

    /**
     * Обновить YAML
     */
    public function update(int $id, string $yaml): bool {
        $db = Database::getInstance();
        $stmt = $db->prepare('SELECT * FROM compose_projects WHERE id = ?');
        $stmt->execute([$id]);
        $project = $stmt->fetch();

        if (!$project) return false;

        file_put_contents($project['project_path'] . '/docker-compose.yml', $yaml);

        $stmt = $db->prepare('UPDATE compose_projects SET yaml_content = ?, updated_at = datetime("now") WHERE id = ?');
        $stmt->execute([$yaml, $id]);
        return true;
    }

    /**
     * Запустить (docker compose up -d)
     */
    public function up(int $id): string {
        $project = $this->getProject($id);
        if (!$project) return 'Project not found';

        $output = shell_exec("cd " . escapeshellarg($project['project_path']) . " && docker compose up -d 2>&1") ?? '';
        
        $db = Database::getInstance();
        $stmt = $db->prepare('UPDATE compose_projects SET status = ?, updated_at = datetime("now") WHERE id = ?');
        $stmt->execute(['running', $id]);
        
        return $output;
    }

    /**
     * Остановить (docker compose down)
     */
    public function down(int $id): string {
        $project = $this->getProject($id);
        if (!$project) return 'Project not found';

        $output = shell_exec("cd " . escapeshellarg($project['project_path']) . " && docker compose down 2>&1") ?? '';
        
        $db = Database::getInstance();
        $stmt = $db->prepare('UPDATE compose_projects SET status = ?, updated_at = datetime("now") WHERE id = ?');
        $stmt->execute(['stopped', $id]);
        
        return $output;
    }

    /**
     * Перезапустить
     */
    public function restart(int $id): string {
        $project = $this->getProject($id);
        if (!$project) return 'Project not found';

        return shell_exec("cd " . escapeshellarg($project['project_path']) . " && docker compose restart 2>&1") ?? '';
    }

    /**
     * Логи
     */
    public function logs(int $id, int $tail = 200): string {
        $project = $this->getProject($id);
        if (!$project) return 'Project not found';

        return shell_exec("cd " . escapeshellarg($project['project_path']) . " && docker compose logs --tail={$tail} 2>&1") ?? '';
    }

    /**
     * Валидация YAML
     */
    public function validate(string $yaml): array {
        $tmpFile = tempnam(sys_get_temp_dir(), 'compose_');
        file_put_contents($tmpFile, $yaml);
        
        $output = shell_exec("docker compose -f {$tmpFile} config 2>&1") ?? '';
        $exitCode = 0;
        exec("docker compose -f {$tmpFile} config 2>&1", $_, $exitCode);
        unlink($tmpFile);

        return [
            'valid' => $exitCode === 0,
            'output' => $output,
        ];
    }

    /**
     * Удалить проект
     */
    public function delete(int $id, bool $removeContainers = true): bool {
        $project = $this->getProject($id);
        if (!$project) return false;

        if ($removeContainers && $project['status'] === 'running') {
            $this->down($id);
        }

        // Удалить файлы
        if (is_dir($project['project_path'])) {
            shell_exec("rm -rf " . escapeshellarg($project['project_path']));
        }

        $db = Database::getInstance();
        $stmt = $db->prepare('DELETE FROM compose_projects WHERE id = ?');
        $stmt->execute([$id]);
        return true;
    }

    /**
     * Список проектов
     */
    public function list(int $userId = 0): array {
        $db = Database::getInstance();
        if ($userId > 0) {
            $stmt = $db->prepare('SELECT * FROM compose_projects WHERE user_id = ? ORDER BY created_at DESC');
            $stmt->execute([$userId]);
        } else {
            $stmt = $db->query('SELECT * FROM compose_projects ORDER BY created_at DESC');
        }
        return $stmt->fetchAll();
    }

    /**
     * Получить проект по ID
     */
    public function getProject(int $id): ?array {
        $db = Database::getInstance();
        $stmt = $db->prepare('SELECT * FROM compose_projects WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }
}
