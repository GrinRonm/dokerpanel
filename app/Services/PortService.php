<?php
/**
 * DockerPanel — Port Service
 * 
 * Поиск свободных портов на хосте
 */

class PortService {

    /** Минимальный порт для автоназначения */
    private int $minPort = 10000;
    /** Максимальный порт */
    private int $maxPort = 60000;
    /** Зарезервированные порты */
    private array $reserved = [22, 80, 443, 3306, 5432, 8765];

    /**
     * Найти свободный порт
     */
    public function findFreePort(int $startFrom = 0): int {
        $start = $startFrom > 0 ? $startFrom : $this->minPort;
        $usedPorts = $this->getUsedPorts();

        for ($port = $start; $port <= $this->maxPort; $port++) {
            if (!in_array($port, $usedPorts) && !in_array($port, $this->reserved)) {
                // Дополнительная проверка через socket
                if ($this->isPortFree($port)) {
                    return $port;
                }
            }
        }

        throw new \RuntimeException("Нет свободных портов в диапазоне {$start}-{$this->maxPort}");
    }

    /**
     * Проверить, свободен ли порт
     */
    public function isPortFree(int $port): bool {
        $connection = @fsockopen('127.0.0.1', $port, $errno, $errstr, 1);
        if ($connection) {
            fclose($connection);
            return false; // Порт занят
        }
        return true; // Порт свободен
    }

    /**
     * Получить все занятые порты (Docker + система)
     */
    public function getUsedPorts(): array {
        $ports = [];

        // Порты из Docker-контейнеров
        try {
            $docker = new DockerService();
            $containers = $docker->listContainers(true);
            foreach ($containers as $container) {
                if (!empty($container['Ports'])) {
                    foreach ($container['Ports'] as $port) {
                        if (!empty($port['PublicPort'])) {
                            $ports[] = (int)$port['PublicPort'];
                        }
                    }
                }
            }
        } catch (\Exception $e) {}

        // Порты из системы через ss
        $output = shell_exec("ss -tlnp 2>/dev/null | awk '{print \$4}' | grep -oP '\\d+\$'") ?? '';
        foreach (explode("\n", trim($output)) as $p) {
            if (is_numeric($p)) {
                $ports[] = (int)$p;
            }
        }

        return array_unique($ports);
    }

    /**
     * Проверить и предложить альтернативный порт, если указанный занят
     */
    public function suggestPort(int $desired): array {
        if ($this->isPortFree($desired)) {
            return ['port' => $desired, 'available' => true, 'suggested' => false];
        }
        $alternative = $this->findFreePort($desired + 1);
        return ['port' => $alternative, 'available' => false, 'suggested' => true, 'original' => $desired];
    }
}
