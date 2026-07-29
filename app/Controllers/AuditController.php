<?php
/**
 * DockerPanel — Audit Controller
 */

class AuditController {
    public function index(): void {
        if (isAjax()) {
            $this->list();
            return;
        }
        Response::view('audit/index');
    }

    public function list(): void {
        $db = Database::getInstance();
        $logs = $db->query("
            SELECT a.*, u.username 
            FROM audit_log a 
            LEFT JOIN users u ON a.user_id = u.id 
            ORDER BY a.created_at DESC 
            LIMIT 100
        ")->fetchAll();

        Response::success($logs);
    }
}
