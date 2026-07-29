<?php
/**
 * DockerPanel — REST API Controller
 * 
 * Аутентификация через Bearer Token (api_token из таблицы users)
 */

class ApiController {

    public function containers(): void {
        $docker = new DockerService();
        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            $containers = $docker->listContainers(true);
            Response::json(['data' => $containers]);
        }
    }

    public function containerCreate(): void {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') Response::json(['error' => 'Method not allowed'], 405);
        $data = Validator::jsonBody();
        try {
            $docker = new DockerService();
            $config = [
                'image' => ($data['image'] ?? 'ubuntu') . ':' . ($data['tag'] ?? 'latest'),
                'cmd' => $data['cmd'] ?? '',
                'env' => $data['env'] ?? [],
                'ports' => $data['ports'] ?? [],
                'volumes' => $data['volumes'] ?? [],
                'cpu' => $data['cpu'] ?? '1',
                'ram' => $data['ram'] ?? '512m',
                'restart' => $data['restart'] ?? 'unless-stopped',
            ];
            if (!empty($data['image'])) $docker->pullImage($data['image'], $data['tag'] ?? 'latest');
            $result = $docker->createContainer($config, $data['name'] ?? '');
            $containerId = $result['Id'] ?? '';
            if ($containerId) $docker->startContainer($containerId);
            Response::json(['data' => $result, 'message' => 'Container created']);
        } catch (\Exception $e) { Response::json(['error' => $e->getMessage()], 500); }
    }

    public function containerAction(): void {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') Response::json(['error' => 'Method not allowed'], 405);
        $data = Validator::jsonBody();
        $id = $data['id'] ?? '';
        $action = $data['action'] ?? '';
        if (empty($id) || empty($action)) Response::json(['error' => 'id and action required'], 400);

        try {
            $docker = new DockerService();
            match($action) {
                'start' => $docker->startContainer($id),
                'stop' => $docker->stopContainer($id),
                'restart' => $docker->restartContainer($id),
                'remove' => $docker->removeContainer($id),
                default => Response::json(['error' => 'Unknown action'], 400),
            };
            Response::json(['message' => "Action {$action} completed"]);
        } catch (\Exception $e) { Response::json(['error' => $e->getMessage()], 500); }
    }

    public function images(): void {
        $docker = new DockerService();
        Response::json(['data' => $docker->listImages()]);
    }

    public function networks(): void {
        $docker = new DockerService();
        Response::json(['data' => $docker->listNetworks()]);
    }

    public function volumes(): void {
        $docker = new DockerService();
        Response::json(['data' => $docker->listVolumes()]);
    }

    public function system(): void {
        $docker = new DockerService();
        Response::json(['data' => [
            'info' => $docker->systemInfo(),
            'version' => $docker->version(),
        ]]);
    }
}
