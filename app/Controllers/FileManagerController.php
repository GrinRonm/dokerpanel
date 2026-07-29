<?php
/**
 * DockerPanel — File Manager Controller
 */

class FileManagerController {
    private DockerService $docker;
    public function __construct() { $this->docker = new DockerService(); }

    public function index(): void {
        Response::view('containers/files', ['containerId' => Validator::get('id')]);
    }

    public function list(): void {
        $id = Validator::get('id');
        $path = Validator::get('path', '/');
        if (empty($id)) Response::error('ID контейнера не указан');
        try {
            $files = $this->docker->listFiles($id, $path);
            Response::success(['files' => $files, 'path' => $path]);
        } catch (\Exception $e) { Response::error($e->getMessage()); }
    }

    public function read(): void {
        $id = Validator::get('id');
        $path = Validator::get('path');
        if (empty($id) || empty($path)) Response::error('Параметры не указаны');
        try {
            $content = $this->docker->readFile($id, $path);
            // Определяем тип файла для подсветки синтаксиса
            $ext = pathinfo($path, PATHINFO_EXTENSION);
            $modeMap = [
                'php' => 'php', 'py' => 'python', 'js' => 'javascript', 'ts' => 'javascript',
                'html' => 'htmlmixed', 'htm' => 'htmlmixed', 'css' => 'css', 'scss' => 'css',
                'json' => 'javascript', 'yml' => 'yaml', 'yaml' => 'yaml', 'xml' => 'xml',
                'sql' => 'sql', 'md' => 'markdown', 'sh' => 'shell', 'bash' => 'shell',
                'dockerfile' => 'dockerfile', 'go' => 'go', 'rs' => 'rust', 'java' => 'clike',
                'c' => 'clike', 'cpp' => 'clike', 'h' => 'clike', 'rb' => 'ruby',
                'conf' => 'nginx', 'ini' => 'properties', 'toml' => 'toml',
            ];
            $mode = $modeMap[strtolower($ext)] ?? 'text';
            Response::success(['content' => $content, 'path' => $path, 'mode' => $mode]);
        } catch (\Exception $e) { Response::error($e->getMessage()); }
    }

    public function write(): void {
        $data = Validator::jsonBody();
        $id = $data['id'] ?? '';
        $path = $data['path'] ?? '';
        $content = $data['content'] ?? '';
        if (empty($id) || empty($path)) Response::error('Параметры не указаны');
        try {
            $this->docker->writeFile($id, $path, $content);
            Security::auditLog('file_write', 'container', $id, $path);
            Response::success(null, 'Файл сохранён');
        } catch (\Exception $e) { Response::error($e->getMessage()); }
    }

    public function delete(): void {
        $data = Validator::jsonBody();
        $id = $data['id'] ?? '';
        $path = $data['path'] ?? '';
        if (empty($id) || empty($path)) Response::error('Параметры не указаны');
        try {
            $this->docker->deleteFile($id, $path);
            Response::success(null, 'Удалено');
        } catch (\Exception $e) { Response::error($e->getMessage()); }
    }

    public function mkdir(): void {
        $data = Validator::jsonBody();
        try {
            $this->docker->createDirectory($data['id'], $data['path']);
            Response::success(null, 'Директория создана');
        } catch (\Exception $e) { Response::error($e->getMessage()); }
    }

    public function rename(): void {
        $data = Validator::jsonBody();
        try {
            $this->docker->moveFile($data['id'], $data['old_path'], $data['new_path']);
            Response::success(null, 'Переименовано');
        } catch (\Exception $e) { Response::error($e->getMessage()); }
    }

    public function copy(): void {
        $data = Validator::jsonBody();
        try {
            $this->docker->copyFile($data['id'], $data['src'], $data['dst']);
            Response::success(null, 'Скопировано');
        } catch (\Exception $e) { Response::error($e->getMessage()); }
    }

    public function move(): void {
        $data = Validator::jsonBody();
        try {
            $this->docker->moveFile($data['id'], $data['src'], $data['dst']);
            Response::success(null, 'Перемещено');
        } catch (\Exception $e) { Response::error($e->getMessage()); }
    }

    public function upload(): void {
        $id = $_POST['id'] ?? '';
        $path = $_POST['path'] ?? '/';
        if (empty($id)) Response::error('ID не указан');
        if (empty($_FILES['file'])) Response::error('Файл не загружен');

        $tmpPath = $_FILES['file']['tmp_name'];
        $fileName = basename($_FILES['file']['name']);
        $containerPath = rtrim($path, '/') . '/' . $fileName;

        try {
            $this->docker->uploadFile($id, $tmpPath, $containerPath);
            Response::success(null, "Файл {$fileName} загружен");
        } catch (\Exception $e) { Response::error($e->getMessage()); }
    }

    public function download(): void {
        $id = Validator::get('id');
        $path = Validator::get('path');
        if (empty($id) || empty($path)) Response::error('Параметры не указаны');

        $tmpFile = tempnam(sys_get_temp_dir(), 'dockerpanel_');
        try {
            $this->docker->downloadFile($id, $path, $tmpFile);
            Response::download($tmpFile, basename($path));
        } finally {
            @unlink($tmpFile);
        }
    }

    public function archive(): void {
        $data = Validator::jsonBody();
        try {
            $archivePath = '/tmp/' . basename($data['path']) . '.tar.gz';
            $this->docker->archiveFiles($data['id'], $data['path'], $archivePath);
            Response::success(['archive_path' => $archivePath], 'Архив создан');
        } catch (\Exception $e) { Response::error($e->getMessage()); }
    }

    public function extract(): void {
        $data = Validator::jsonBody();
        try {
            $this->docker->extractArchive($data['id'], $data['archive_path'], $data['target_path'] ?? '/');
            Response::success(null, 'Архив распакован');
        } catch (\Exception $e) { Response::error($e->getMessage()); }
    }
}
