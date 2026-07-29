<?php
/**
 * DockerPanel — Network Controller
 */

class NetworkController {
    private DockerService $docker;
    public function __construct() { $this->docker = new DockerService(); }

    public function index(): void {
        if (isAjax()) { $this->list(); return; }
        Response::view('networks');
    }

    public function list(): void {
        try {
            $networks = $this->docker->listNetworks();
            $result = [];
            foreach ($networks as $n) {
                $containers = [];
                foreach ($n['Containers'] ?? [] as $cId => $c) {
                    $containers[] = ['id' => $cId, 'name' => $c['Name'] ?? '', 'ip' => $c['IPv4Address'] ?? ''];
                }
                $result[] = [
                    'id' => $n['Id'] ?? '',
                    'short_id' => substr($n['Id'] ?? '', 0, 12),
                    'name' => $n['Name'] ?? '',
                    'driver' => $n['Driver'] ?? '',
                    'scope' => $n['Scope'] ?? '',
                    'internal' => $n['Internal'] ?? false,
                    'subnet' => $n['IPAM']['Config'][0]['Subnet'] ?? '-',
                    'gateway' => $n['IPAM']['Config'][0]['Gateway'] ?? '-',
                    'containers' => $containers,
                    'created' => $n['Created'] ?? '',
                ];
            }
            Response::success($result);
        } catch (\Exception $e) { Response::error($e->getMessage()); }
    }

    public function create(): void {
        $data = Validator::jsonBody();
        if (empty($data['name'])) Response::error('Укажите имя сети');
        try {
            $result = $this->docker->createNetwork($data['name'], $data['driver'] ?? 'bridge', $data);
            Security::auditLog('network_create', 'network', $data['name']);
            Response::success($result, 'Сеть создана');
        } catch (\Exception $e) { Response::error($e->getMessage()); }
    }

    public function remove(): void {
        $data = Validator::jsonBody();
        $id = $data['id'] ?? '';
        if (empty($id)) Response::error('ID не указан');
        try {
            $this->docker->removeNetwork($id);
            Security::auditLog('network_remove', 'network', $id);
            Response::success(null, 'Сеть удалена');
        } catch (\Exception $e) { Response::error($e->getMessage()); }
    }

    public function connect(): void {
        $data = Validator::jsonBody();
        try {
            $this->docker->connectToNetwork($data['network_id'], $data['container_id']);
            Response::success(null, 'Контейнер подключён к сети');
        } catch (\Exception $e) { Response::error($e->getMessage()); }
    }

    public function disconnect(): void {
        $data = Validator::jsonBody();
        try {
            $this->docker->disconnectFromNetwork($data['network_id'], $data['container_id']);
            Response::success(null, 'Контейнер отключён от сети');
        } catch (\Exception $e) { Response::error($e->getMessage()); }
    }
}
