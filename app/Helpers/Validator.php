<?php
/**
 * DockerPanel — Input Validator
 */

class Validator {

    private array $errors = [];

    /**
     * Проверить обязательное поле
     */
    public function required(string $field, mixed $value, string $label = ''): self {
        $label = $label ?: $field;
        if (empty($value) && $value !== '0' && $value !== 0) {
            $this->errors[$field] = "{$label} обязательно для заполнения";
        }
        return $this;
    }

    /**
     * Проверить минимальную длину
     */
    public function minLength(string $field, mixed $value, int $min, string $label = ''): self {
        $label = $label ?: $field;
        if (strlen((string)$value) < $min) {
            $this->errors[$field] = "{$label} должно содержать минимум {$min} символов";
        }
        return $this;
    }

    /**
     * Проверить максимальную длину
     */
    public function maxLength(string $field, mixed $value, int $max, string $label = ''): self {
        $label = $label ?: $field;
        if (strlen((string)$value) > $max) {
            $this->errors[$field] = "{$label} не может превышать {$max} символов";
        }
        return $this;
    }

    /**
     * Проверить regex-паттерн
     */
    public function pattern(string $field, mixed $value, string $pattern, string $message = ''): self {
        if (!empty($value) && !preg_match($pattern, (string)$value)) {
            $this->errors[$field] = $message ?: "{$field} имеет неверный формат";
        }
        return $this;
    }

    /**
     * Проверить имя контейнера (буквы, цифры, дефисы, подчёркивания)
     */
    public function containerName(string $field, mixed $value): self {
        return $this->pattern($field, $value, '/^[a-zA-Z0-9][a-zA-Z0-9_.-]*$/',
            'Имя контейнера может содержать только буквы, цифры, дефисы, точки и подчёркивания');
    }

    /**
     * Проверить Docker image name
     */
    public function imageName(string $field, mixed $value): self {
        return $this->pattern($field, $value, '/^[a-zA-Z0-9][a-zA-Z0-9_.\/-]*$/',
            'Неверный формат имени образа');
    }

    /**
     * Проверить email
     */
    public function email(string $field, mixed $value): self {
        if (!empty($value) && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
            $this->errors[$field] = 'Неверный формат email';
        }
        return $this;
    }

    /**
     * Проверить числовое значение
     */
    public function numeric(string $field, mixed $value, string $label = ''): self {
        $label = $label ?: $field;
        if (!empty($value) && !is_numeric($value)) {
            $this->errors[$field] = "{$label} должно быть числом";
        }
        return $this;
    }

    /**
     * Проверить порт (1-65535)
     */
    public function port(string $field, mixed $value): self {
        if (!empty($value)) {
            $port = (int)$value;
            if ($port < 1 || $port > 65535) {
                $this->errors[$field] = "Порт должен быть от 1 до 65535";
            }
        }
        return $this;
    }

    /**
     * Есть ли ошибки
     */
    public function hasErrors(): bool {
        return !empty($this->errors);
    }

    /**
     * Получить ошибки
     */
    public function getErrors(): array {
        return $this->errors;
    }

    /**
     * Получить первую ошибку
     */
    public function getFirstError(): string {
        return reset($this->errors) ?: '';
    }

    /**
     * Сбросить ошибки
     */
    public function reset(): self {
        $this->errors = [];
        return $this;
    }

    /**
     * Безопасно получить POST-параметр
     */
    public static function post(string $key, mixed $default = ''): mixed {
        $value = $_POST[$key] ?? $default;
        if (is_string($value)) {
            return trim($value);
        }
        return $value;
    }

    /**
     * Безопасно получить GET-параметр
     */
    public static function get(string $key, mixed $default = ''): mixed {
        $value = $_GET[$key] ?? $default;
        if (is_string($value)) {
            return htmlspecialchars(trim($value), ENT_QUOTES, 'UTF-8');
        }
        return $value;
    }

    /**
     * Получить JSON body
     */
    public static function jsonBody(): array {
        $body = file_get_contents('php://input');
        $data = json_decode($body, true);
        return is_array($data) ? $data : [];
    }
}
