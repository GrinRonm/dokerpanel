<?php
/**
 * DockerPanel — Compose Controller
 */

class ComposeController {
    private DockerComposeService $compose;
    public function __construct() { $this->compose = new DockerComposeService(); }

    public function index(): void {
        if (isAjax()) { $this->list(); return; }
        Response::view('compose');
    }

    public function list(): void {
        $projects = $this->compose->list(AuthMiddleware::isAdmin() ? 0 : AuthMiddleware::userId());
        Response::success($projects);
    }

    public function create(): void {
        $data = Validator::jsonBody();
        $validator = new Validator();
        $validator->required('name', $data['name'] ?? '', 'Имя проекта')
                  ->required('yaml', $data['yaml'] ?? '', 'YAML');
        if ($validator->hasErrors()) Response::error($validator->getFirstError());

        try {
            $result = $this->compose->create($data['name'], $data['yaml'], AuthMiddleware::userId());
            Security::auditLog('compose_create', 'compose', $data['name']);
            Response::success($result, 'Проект создан');
        } catch (\Exception $e) { Response::error($e->getMessage()); }
    }

    public function update(): void {
        $data = Validator::jsonBody();
        $id = (int)($data['id'] ?? 0);
        if (!$id) Response::error('ID не указан');
        try {
            $this->compose->update($id, $data['yaml'] ?? '');
            Response::success(null, 'Проект обновлён');
        } catch (\Exception $e) { Response::error($e->getMessage()); }
    }

    public function delete(): void {
        $data = Validator::jsonBody();
        $id = (int)($data['id'] ?? 0);
        if (!$id) Response::error('ID не указан');
        try {
            $this->compose->delete($id);
            Security::auditLog('compose_delete', 'compose', (string)$id);
            Response::success(null, 'Проект удалён');
        } catch (\Exception $e) { Response::error($e->getMessage()); }
    }

    public function up(): void {
        $data = Validator::jsonBody();
        $id = (int)($data['id'] ?? 0);
        if (!$id) Response::error('ID не указан');
        try {
            $output = $this->compose->up($id);
            Security::auditLog('compose_up', 'compose', (string)$id);
            Response::success(['output' => $output], 'Проект запущен');
        } catch (\Exception $e) { Response::error($e->getMessage()); }
    }

    public function down(): void {
        $data = Validator::jsonBody();
        $id = (int)($data['id'] ?? 0);
        if (!$id) Response::error('ID не указан');
        try {
            $output = $this->compose->down($id);
            Security::auditLog('compose_down', 'compose', (string)$id);
            Response::success(['output' => $output], 'Проект остановлен');
        } catch (\Exception $e) { Response::error($e->getMessage()); }
    }

    public function restart(): void {
        $data = Validator::jsonBody();
        $id = (int)($data['id'] ?? 0);
        if (!$id) Response::error('ID не указан');
        try {
            $output = $this->compose->restart($id);
            Response::success(['output' => $output], 'Проект перезапущен');
        } catch (\Exception $e) { Response::error($e->getMessage()); }
    }

    public function logs(): void {
        $id = (int)Validator::get('id', 0);
        if (!$id) Response::error('ID не указан');
        try {
            $output = $this->compose->logs($id);
            Response::success(['logs' => $output]);
        } catch (\Exception $e) { Response::error($e->getMessage()); }
    }

    public function validate(): void {
        $data = Validator::jsonBody();
        $yaml = $data['yaml'] ?? '';
        if (empty($yaml)) Response::error('YAML пустой');
        $result = $this->compose->validate($yaml);
        Response::success($result);
    }
}
