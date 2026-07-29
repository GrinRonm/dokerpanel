<?php
/**
 * DockerPanel — Dashboard Controller
 */

class DashboardController {

    /**
     * Главная страница Dashboard
     */
    public function index(): void {
        if (isAjax()) {
            $this->stats();
            return;
        }
        Response::view('dashboard');
    }

    /**
     * Статистика для Dashboard (AJAX)
     */
    public function stats(): void {
        try {
            $monitor = new MonitorService();
            
            $data = [
                'docker' => $monitor->getDockerSummary(),
                'system' => $monitor->getSystemStats(),
                'recent_actions' => $monitor->getRecentActions(10),
            ];

            Response::success($data);
        } catch (\Exception $e) {
            Response::error('Ошибка получения статистики: ' . $e->getMessage());
        }
    }
}
