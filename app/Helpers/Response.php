<?php
/**
 * DockerPanel — Response Helper
 * 
 * Унифицированные JSON и HTML ответы
 */

class Response {

    /**
     * Отправить JSON-ответ
     */
    public static function json(array $data, int $code = 200): void {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        header('X-Content-Type-Options: nosniff');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }

    /**
     * Успешный ответ
     */
    public static function success(mixed $data = null, string $message = 'OK'): void {
        self::json([
            'success' => true,
            'message' => $message,
            'data' => $data,
        ]);
    }

    /**
     * Ошибка
     */
    public static function error(string $message = 'Error', int $code = 400, mixed $data = null): void {
        self::json([
            'success' => false,
            'message' => $message,
            'data' => $data,
        ], $code);
    }

    /**
     * Отрендерить view
     */
    public static function view(string $viewName, array $data = []): void {
        extract($data);
        $csrfToken = CsrfMiddleware::getToken();
        
        // Определяем контент view
        $viewFile = VIEW_PATH . '/' . str_replace('.', '/', $viewName) . '.php';
        if (!file_exists($viewFile)) {
            self::error("View not found: {$viewName}", 500);
        }

        // Рендерим view в буфер
        ob_start();
        require $viewFile;
        $content = ob_get_clean();

        // Если это не auth страница — оборачиваем в layout
        if (strpos($viewName, 'auth/') !== 0) {
            $pageContent = $content;
            require VIEW_PATH . '/layouts/main.php';
        } else {
            echo $content;
        }
    }

    /**
     * Отправить файл для скачивания
     */
    public static function download(string $filePath, string $fileName = ''): void {
        if (!file_exists($filePath)) {
            self::error('File not found', 404);
        }
        
        if (empty($fileName)) {
            $fileName = basename($filePath);
        }

        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $fileName . '"');
        header('Content-Length: ' . filesize($filePath));
        header('X-Content-Type-Options: nosniff');
        readfile($filePath);
        exit;
    }
}
