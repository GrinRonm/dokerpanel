<?php
/**
 * DockerPanel — Monitor Controller
 */

class MonitorController {
    public function index(): void { Response::view('dashboard'); }

    public function stats(): void {
        $id = Validator::get('id');
        if (empty($id)) Response::error('ID не указан');
        try {
            $monitor = new MonitorService();
            Response::success($monitor->getContainerStats($id));
        } catch (\Exception $e) { Response::error($e->getMessage()); }
    }

    public function system(): void {
        try {
            $monitor = new MonitorService();
            Response::success($monitor->getSystemStats());
        } catch (\Exception $e) { Response::error($e->getMessage()); }
    }
}
