<?php
/**
 * DockerPanel — Image Controller
 */

class ImageController {
    private DockerService $docker;
    
    public function __construct() { $this->docker = new DockerService(); }

    public function index(): void {
        if (isAjax()) { $this->list(); return; }
        Response::view('images');
    }

    public function list(): void {
        try {
            $images = $this->docker->listImages();
            $result = [];
            foreach ($images as $img) {
                $tags = $img['RepoTags'] ?? ['<none>:<none>'];
                $result[] = [
                    'id' => $img['Id'] ?? '',
                    'short_id' => substr(str_replace('sha256:', '', $img['Id'] ?? ''), 0, 12),
                    'tags' => $tags,
                    'name' => $tags[0] ?? '<none>',
                    'size' => $img['Size'] ?? 0,
                    'size_formatted' => DockerService::formatBytes($img['Size'] ?? 0),
                    'created' => date('Y-m-d H:i:s', $img['Created'] ?? 0),
                    'containers' => $img['Containers'] ?? 0,
                ];
            }
            Response::success($result);
        } catch (\Exception $e) { Response::error($e->getMessage()); }
    }

    public function pull(): void {
        $data = Validator::jsonBody();
        $name = $data['name'] ?? '';
        $tag = $data['tag'] ?? 'latest';
        if (empty($name)) Response::error('Укажите имя образа');
        
        try {
            $output = $this->docker->pullImage($name, $tag);
            Security::auditLog('image_pull', 'image', $name, $tag);
            Response::success(['output' => $output], "Образ {$name}:{$tag} скачан");
        } catch (\Exception $e) { Response::error($e->getMessage()); }
    }

    public function remove(): void {
        $data = Validator::jsonBody();
        $id = $data['id'] ?? '';
        if (empty($id)) Response::error('ID не указан');
        
        try {
            $this->docker->removeImage($id, true);
            Security::auditLog('image_remove', 'image', $id);
            Response::success(null, 'Образ удалён');
        } catch (\Exception $e) { Response::error($e->getMessage()); }
    }

    public function search(): void {
        $term = Validator::get('q', '');
        if (empty($term)) Response::error('Укажите поисковый запрос');
        
        try {
            $results = $this->docker->searchImages($term);
            Response::success($results);
        } catch (\Exception $e) { Response::error($e->getMessage()); }
    }

    public function history(): void {
        $id = Validator::get('id');
        if (empty($id)) Response::error('ID не указан');
        
        try {
            $history = $this->docker->imageHistory($id);
            Response::success($history);
        } catch (\Exception $e) { Response::error($e->getMessage()); }
    }
}
