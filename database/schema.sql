-- ============================================
-- DockerPanel — Database Schema (SQLite)
-- ============================================

-- Пользователи
CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    username TEXT NOT NULL UNIQUE,
    password_hash TEXT NOT NULL,
    email TEXT DEFAULT '',
    role TEXT NOT NULL DEFAULT 'user' CHECK(role IN ('admin', 'user', 'viewer')),
    api_token TEXT DEFAULT NULL,
    is_active INTEGER NOT NULL DEFAULT 1,
    last_login TEXT DEFAULT NULL,
    created_at TEXT NOT NULL DEFAULT (datetime('now')),
    updated_at TEXT NOT NULL DEFAULT (datetime('now'))
);

-- Метаданные контейнеров (связь с Docker)
CREATE TABLE IF NOT EXISTS containers (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    docker_id TEXT NOT NULL UNIQUE,
    name TEXT NOT NULL,
    user_id INTEGER NOT NULL,
    template_id INTEGER DEFAULT NULL,
    image TEXT NOT NULL,
    config_json TEXT DEFAULT '{}',
    notes TEXT DEFAULT '',
    created_at TEXT NOT NULL DEFAULT (datetime('now')),
    updated_at TEXT NOT NULL DEFAULT (datetime('now')),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (template_id) REFERENCES templates(id) ON DELETE SET NULL
);

-- Шаблоны контейнеров
CREATE TABLE IF NOT EXISTS templates (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    slug TEXT NOT NULL UNIQUE,
    category TEXT NOT NULL DEFAULT 'other',
    description TEXT DEFAULT '',
    image TEXT NOT NULL,
    default_tag TEXT NOT NULL DEFAULT 'latest',
    icon TEXT DEFAULT 'cube',
    config_json TEXT NOT NULL DEFAULT '{}',
    is_active INTEGER NOT NULL DEFAULT 1,
    sort_order INTEGER NOT NULL DEFAULT 0,
    created_at TEXT NOT NULL DEFAULT (datetime('now'))
);

-- Привязанные домены
CREATE TABLE IF NOT EXISTS domains (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    container_id INTEGER NOT NULL,
    subdomain TEXT NOT NULL,
    base_domain TEXT NOT NULL,
    container_port INTEGER NOT NULL DEFAULT 80,
    ssl_enabled INTEGER NOT NULL DEFAULT 0,
    nginx_config_path TEXT DEFAULT '',
    is_active INTEGER NOT NULL DEFAULT 1,
    created_at TEXT NOT NULL DEFAULT (datetime('now')),
    FOREIGN KEY (container_id) REFERENCES containers(id) ON DELETE CASCADE
);

-- Лог действий
CREATE TABLE IF NOT EXISTS audit_log (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER DEFAULT NULL,
    action TEXT NOT NULL,
    target_type TEXT DEFAULT '',
    target_id TEXT DEFAULT '',
    details TEXT DEFAULT '',
    ip_address TEXT DEFAULT '',
    created_at TEXT NOT NULL DEFAULT (datetime('now')),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

-- Системные настройки
CREATE TABLE IF NOT EXISTS settings (
    key TEXT PRIMARY KEY,
    value TEXT NOT NULL DEFAULT '',
    description TEXT DEFAULT '',
    updated_at TEXT NOT NULL DEFAULT (datetime('now'))
);

-- Резервные копии
CREATE TABLE IF NOT EXISTS backups (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    container_id INTEGER DEFAULT NULL,
    type TEXT NOT NULL DEFAULT 'container' CHECK(type IN ('container', 'volume', 'compose', 'full')),
    name TEXT NOT NULL,
    file_path TEXT NOT NULL,
    file_size INTEGER DEFAULT 0,
    status TEXT NOT NULL DEFAULT 'pending' CHECK(status IN ('pending', 'running', 'completed', 'failed')),
    notes TEXT DEFAULT '',
    created_at TEXT NOT NULL DEFAULT (datetime('now')),
    FOREIGN KEY (container_id) REFERENCES containers(id) ON DELETE SET NULL
);

-- Docker Compose проекты
CREATE TABLE IF NOT EXISTS compose_projects (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL UNIQUE,
    user_id INTEGER NOT NULL,
    description TEXT DEFAULT '',
    yaml_content TEXT NOT NULL DEFAULT '',
    project_path TEXT DEFAULT '',
    status TEXT NOT NULL DEFAULT 'stopped' CHECK(status IN ('running', 'stopped', 'partial', 'error')),
    created_at TEXT NOT NULL DEFAULT (datetime('now')),
    updated_at TEXT NOT NULL DEFAULT (datetime('now')),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Расписание бэкапов
CREATE TABLE IF NOT EXISTS backup_schedules (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    container_id INTEGER DEFAULT NULL,
    schedule_cron TEXT NOT NULL DEFAULT '0 3 * * *',
    retention_days INTEGER NOT NULL DEFAULT 7,
    is_active INTEGER NOT NULL DEFAULT 1,
    last_run TEXT DEFAULT NULL,
    next_run TEXT DEFAULT NULL,
    created_at TEXT NOT NULL DEFAULT (datetime('now')),
    FOREIGN KEY (container_id) REFERENCES containers(id) ON DELETE CASCADE
);

-- Индексы
CREATE INDEX IF NOT EXISTS idx_containers_user ON containers(user_id);
CREATE INDEX IF NOT EXISTS idx_containers_docker ON containers(docker_id);
CREATE INDEX IF NOT EXISTS idx_audit_user ON audit_log(user_id);
CREATE INDEX IF NOT EXISTS idx_audit_created ON audit_log(created_at);
CREATE INDEX IF NOT EXISTS idx_domains_container ON domains(container_id);
CREATE INDEX IF NOT EXISTS idx_backups_container ON backups(container_id);
CREATE INDEX IF NOT EXISTS idx_templates_category ON templates(category);
CREATE INDEX IF NOT EXISTS idx_compose_user ON compose_projects(user_id);
