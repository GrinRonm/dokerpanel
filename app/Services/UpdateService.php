<?php
class UpdateService {
    public static function checkUpdates(): array {
        $repo = 'GrinRonm/dokerpanel';
        $currentVersion = self::getCurrentVersion();
        
        $ch = curl_init("https://api.github.com/repos/{$repo}/commits/main");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_USERAGENT, 'DockerPanel-Updater');
        $response = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($status !== 200 || !$response) {
            return ['error' => 'Не удалось получить информацию об обновлениях.'];
        }

        $data = json_decode($response, true);
        $latestVersion = substr($data['sha'] ?? 'v1.0.0', 0, 7);

        return [
            'current_version' => $currentVersion,
            'latest_version' => $latestVersion,
            'has_update' => ($currentVersion !== $latestVersion)
        ];
    }

    public static function getCurrentVersion(): string {
        $versionFile = ROOT_PATH . '/config/version.php';
        if (file_exists($versionFile)) {
            $config = require $versionFile;
            return $config['version'] ?? 'v1.0.0';
        }
        return 'v1.0.0';
    }

    public static function startUpdate(): bool {
        $updateScript = ROOT_PATH . '/scripts/update.sh';
        if (!file_exists($updateScript)) {
            return false;
        }

        // Запуск скрипта обновления в фоне
        exec("bash {$updateScript} > /dev/null 2>&1 &");
        return true;
    }
}
