-- ============================================
-- DockerPanel — Seeds (Initial Data)
-- ============================================

-- Настройки по умолчанию
INSERT OR IGNORE INTO settings (key, value, description) VALUES
('site_name', 'DockerPanel', 'Название панели'),
('site_url', '', 'URL панели'),
('base_domain', '', 'Базовый домен для контейнеров'),
('default_network', 'bridge', 'Сеть Docker по умолчанию'),
('max_containers_per_user', '50', 'Максимум контейнеров на пользователя'),
('default_cpu_limit', '1', 'CPU лимит по умолчанию'),
('default_ram_limit', '512m', 'RAM лимит по умолчанию'),
('default_disk_limit', '10g', 'Лимит диска по умолчанию'),
('enable_registration', '0', 'Разрешить регистрацию'),
('enable_domains', '0', 'Включить автоматические домены'),
('ssl_email', '', 'Email для Let''s Encrypt'),
('backup_path', '/var/www/dockerpanel/storage/backups', 'Путь к бэкапам'),
('terminal_ws_port', '8765', 'Порт WebSocket терминала'),
('docker_socket', '/var/run/docker.sock', 'Docker socket'),
('nginx_sites_path', '/etc/nginx/sites-enabled', 'Путь к конфигам Nginx'),
('theme', 'dark', 'Тема интерфейса'),
('language', 'ru', 'Язык интерфейса');

-- ============================================
-- Шаблоны контейнеров
-- ============================================

-- Операционные системы
INSERT OR IGNORE INTO templates (name, slug, category, description, image, default_tag, icon, config_json, sort_order) VALUES
('Ubuntu', 'ubuntu', 'os', 'Ubuntu Linux — популярный дистрибутив для серверов и разработки', 'ubuntu', '22.04', 'ubuntu', '{"ports":[],"env":[],"volumes":[],"cmd":"/bin/bash","cpu":"1","ram":"512m","restart":"unless-stopped"}', 1),
('Ubuntu (Systemd VPS)', 'ubuntu-systemd', 'os', 'Ubuntu с рабочим systemd (PID 1). Позволяет использовать systemctl и запускать службы (подходит для установки сложных скриптов).', 'jrei/systemd-ubuntu', '22.04', 'ubuntu', '{"ports":[],"env":[],"volumes":[{"host":"/sys/fs/cgroup","container":"/sys/fs/cgroup:rw"}],"tmpfs":["/tmp","/run","/run/lock"],"cgroupns":"host","cmd":"","cpu":"1","ram":"512m","restart":"unless-stopped","privileged":true}', 2),
('Debian', 'debian', 'os', 'Debian — стабильный дистрибутив Linux', 'debian', 'bookworm', 'debian', '{"ports":[],"env":[],"volumes":[],"cmd":"/bin/bash","cpu":"1","ram":"512m","restart":"unless-stopped"}', 3),
('Alpine', 'alpine', 'os', 'Alpine Linux — минималистичный дистрибутив (5 MB)', 'alpine', 'latest', 'alpine', '{"ports":[],"env":[],"volumes":[],"cmd":"/bin/sh","cpu":"0.5","ram":"128m","restart":"unless-stopped"}', 4),
('CentOS', 'centos', 'os', 'CentOS Stream — корпоративный Linux', 'centos', 'stream9', 'centos', '{"ports":[],"env":[],"volumes":[],"cmd":"/bin/bash","cpu":"1","ram":"512m","restart":"unless-stopped"}', 5),
('Rocky Linux', 'rocky', 'os', 'Rocky Linux — стабильная замена CentOS', 'rockylinux', '9', 'linux', '{"ports":[],"env":[],"volumes":[],"cmd":"/bin/bash","cpu":"1","ram":"512m","restart":"unless-stopped"}', 6);

-- Веб-серверы
INSERT OR IGNORE INTO templates (name, slug, category, description, image, default_tag, icon, config_json, sort_order) VALUES
('Nginx', 'nginx', 'webserver', 'Nginx — высокопроизводительный веб-сервер и reverse proxy', 'nginx', 'latest', 'nginx', '{"ports":[{"host":"","container":"80"},{"host":"","container":"443"}],"env":[],"volumes":[{"host":"","container":"/usr/share/nginx/html"},{"host":"","container":"/etc/nginx/conf.d"}],"cpu":"0.5","ram":"256m","restart":"unless-stopped"}', 10),
('Apache', 'apache', 'webserver', 'Apache HTTP Server — самый популярный веб-сервер', 'httpd', 'latest', 'apache', '{"ports":[{"host":"","container":"80"}],"env":[],"volumes":[{"host":"","container":"/usr/local/apache2/htdocs"}],"cpu":"0.5","ram":"256m","restart":"unless-stopped"}', 11),
('Caddy', 'caddy', 'webserver', 'Caddy — современный веб-сервер с автоматическим HTTPS', 'caddy', 'latest', 'caddy', '{"ports":[{"host":"","container":"80"},{"host":"","container":"443"}],"env":[],"volumes":[{"host":"","container":"/srv"},{"host":"","container":"/data"},{"host":"","container":"/config"}],"cpu":"0.5","ram":"256m","restart":"unless-stopped"}', 12);

-- Языки программирования
INSERT OR IGNORE INTO templates (name, slug, category, description, image, default_tag, icon, config_json, sort_order) VALUES
('PHP', 'php', 'language', 'PHP с Apache — серверный язык программирования', 'php', '8.3-apache', 'php', '{"ports":[{"host":"","container":"80"}],"env":[],"volumes":[{"host":"","container":"/var/www/html"}],"cpu":"1","ram":"512m","restart":"unless-stopped"}', 20),
('Node.js', 'nodejs', 'language', 'Node.js — серверная платформа JavaScript', 'node', '20-alpine', 'nodejs', '{"ports":[{"host":"","container":"3000"}],"env":[],"volumes":[{"host":"","container":"/app"}],"cmd":"node","cpu":"1","ram":"512m","restart":"unless-stopped"}', 21),
('Python', 'python', 'language', 'Python — универсальный язык программирования', 'python', '3.12-slim', 'python', '{"ports":[],"env":[],"volumes":[{"host":"","container":"/app"}],"cmd":"python3","cpu":"1","ram":"512m","restart":"unless-stopped"}', 22),
('Java', 'java', 'language', 'OpenJDK — платформа Java', 'openjdk', '21-slim', 'java', '{"ports":[{"host":"","container":"8080"}],"env":[],"volumes":[{"host":"","container":"/app"}],"cpu":"2","ram":"1g","restart":"unless-stopped"}', 23),
('Go', 'golang', 'language', 'Go — компилируемый язык от Google', 'golang', '1.22-alpine', 'go', '{"ports":[],"env":[],"volumes":[{"host":"","container":"/app"}],"cpu":"1","ram":"512m","restart":"unless-stopped"}', 24),
('Ruby', 'ruby', 'language', 'Ruby — элегантный язык программирования', 'ruby', '3.3-slim', 'ruby', '{"ports":[{"host":"","container":"3000"}],"env":[],"volumes":[{"host":"","container":"/app"}],"cpu":"1","ram":"512m","restart":"unless-stopped"}', 25),
('Rust', 'rust', 'language', 'Rust — язык системного программирования', 'rust', 'latest', 'rust', '{"ports":[],"env":[],"volumes":[{"host":"","container":"/app"}],"cpu":"2","ram":"1g","restart":"unless-stopped"}', 26);

-- Базы данных
INSERT OR IGNORE INTO templates (name, slug, category, description, image, default_tag, icon, config_json, sort_order) VALUES
('MariaDB', 'mariadb', 'database', 'MariaDB — форк MySQL, реляционная БД', 'mariadb', 'latest', 'mariadb', '{"ports":[{"host":"","container":"3306"}],"env":[{"name":"MYSQL_ROOT_PASSWORD","value":"changeme"},{"name":"MYSQL_DATABASE","value":"mydb"}],"volumes":[{"host":"","container":"/var/lib/mysql"}],"cpu":"1","ram":"512m","restart":"unless-stopped"}', 30),
('PostgreSQL', 'postgresql', 'database', 'PostgreSQL — мощная реляционная БД', 'postgres', '16-alpine', 'postgresql', '{"ports":[{"host":"","container":"5432"}],"env":[{"name":"POSTGRES_PASSWORD","value":"changeme"},{"name":"POSTGRES_DB","value":"mydb"}],"volumes":[{"host":"","container":"/var/lib/postgresql/data"}],"cpu":"1","ram":"512m","restart":"unless-stopped"}', 31),
('MongoDB', 'mongodb', 'database', 'MongoDB — документоориентированная NoSQL БД', 'mongo', 'latest', 'mongodb', '{"ports":[{"host":"","container":"27017"}],"env":[{"name":"MONGO_INITDB_ROOT_USERNAME","value":"admin"},{"name":"MONGO_INITDB_ROOT_PASSWORD","value":"changeme"}],"volumes":[{"host":"","container":"/data/db"}],"cpu":"1","ram":"1g","restart":"unless-stopped"}', 32),
('Redis', 'redis', 'database', 'Redis — быстрое хранилище ключ-значение', 'redis', 'alpine', 'redis', '{"ports":[{"host":"","container":"6379"}],"env":[],"volumes":[{"host":"","container":"/data"}],"cpu":"0.5","ram":"256m","restart":"unless-stopped"}', 33),
('MySQL', 'mysql', 'database', 'MySQL — популярная реляционная БД', 'mysql', '8.0', 'mysql', '{"ports":[{"host":"","container":"3306"}],"env":[{"name":"MYSQL_ROOT_PASSWORD","value":"changeme"},{"name":"MYSQL_DATABASE","value":"mydb"}],"volumes":[{"host":"","container":"/var/lib/mysql"}],"cpu":"1","ram":"512m","restart":"unless-stopped"}', 34);

-- Мониторинг
INSERT OR IGNORE INTO templates (name, slug, category, description, image, default_tag, icon, config_json, sort_order) VALUES
('Grafana', 'grafana', 'monitoring', 'Grafana — визуализация метрик и дашборды', 'grafana/grafana', 'latest', 'grafana', '{"ports":[{"host":"","container":"3000"}],"env":[{"name":"GF_SECURITY_ADMIN_PASSWORD","value":"admin"}],"volumes":[{"host":"","container":"/var/lib/grafana"}],"cpu":"0.5","ram":"512m","restart":"unless-stopped"}', 40),
('Prometheus', 'prometheus', 'monitoring', 'Prometheus — система мониторинга и алертинга', 'prom/prometheus', 'latest', 'prometheus', '{"ports":[{"host":"","container":"9090"}],"env":[],"volumes":[{"host":"","container":"/prometheus"}],"cpu":"0.5","ram":"512m","restart":"unless-stopped"}', 41),
('InfluxDB', 'influxdb', 'monitoring', 'InfluxDB — time series база данных', 'influxdb', 'latest', 'influxdb', '{"ports":[{"host":"","container":"8086"}],"env":[{"name":"DOCKER_INFLUXDB_INIT_MODE","value":"setup"},{"name":"DOCKER_INFLUXDB_INIT_USERNAME","value":"admin"},{"name":"DOCKER_INFLUXDB_INIT_PASSWORD","value":"changeme123"},{"name":"DOCKER_INFLUXDB_INIT_ORG","value":"myorg"},{"name":"DOCKER_INFLUXDB_INIT_BUCKET","value":"mybucket"}],"volumes":[{"host":"","container":"/var/lib/influxdb2"}],"cpu":"1","ram":"512m","restart":"unless-stopped"}', 42),
('Zabbix', 'zabbix', 'monitoring', 'Zabbix — система мониторинга инфраструктуры', 'zabbix/zabbix-appliance', 'latest', 'zabbix', '{"ports":[{"host":"","container":"80"},{"host":"","container":"10051"}],"env":[],"volumes":[],"cpu":"2","ram":"1g","restart":"unless-stopped"}', 43);

-- Приложения
INSERT OR IGNORE INTO templates (name, slug, category, description, image, default_tag, icon, config_json, sort_order) VALUES
('Nextcloud', 'nextcloud', 'apps', 'Nextcloud — облачное хранилище файлов', 'nextcloud', 'latest', 'nextcloud', '{"ports":[{"host":"","container":"80"}],"env":[{"name":"NEXTCLOUD_ADMIN_USER","value":"admin"},{"name":"NEXTCLOUD_ADMIN_PASSWORD","value":"changeme"}],"volumes":[{"host":"","container":"/var/www/html"}],"cpu":"2","ram":"1g","restart":"unless-stopped"}', 50),
('Gitea', 'gitea', 'apps', 'Gitea — легковесный Git-сервер', 'gitea/gitea', 'latest', 'gitea', '{"ports":[{"host":"","container":"3000"},{"host":"","container":"22"}],"env":[],"volumes":[{"host":"","container":"/data"}],"cpu":"1","ram":"512m","restart":"unless-stopped"}', 51),
('GitLab', 'gitlab', 'apps', 'GitLab CE — полнофункциональная платформа DevOps', 'gitlab/gitlab-ce', 'latest', 'gitlab', '{"ports":[{"host":"","container":"80"},{"host":"","container":"443"},{"host":"","container":"22"}],"env":[{"name":"GITLAB_OMNIBUS_CONFIG","value":"external_url ''http://gitlab.local''"}],"volumes":[{"host":"","container":"/etc/gitlab"},{"host":"","container":"/var/log/gitlab"},{"host":"","container":"/var/opt/gitlab"}],"cpu":"4","ram":"4g","restart":"unless-stopped"}', 52),
('Jenkins', 'jenkins', 'apps', 'Jenkins — сервер CI/CD', 'jenkins/jenkins', 'lts', 'jenkins', '{"ports":[{"host":"","container":"8080"},{"host":"","container":"50000"}],"env":[],"volumes":[{"host":"","container":"/var/jenkins_home"}],"cpu":"2","ram":"2g","restart":"unless-stopped"}', 53),
('n8n', 'n8n', 'apps', 'n8n — платформа автоматизации workflow', 'n8nio/n8n', 'latest', 'n8n', '{"ports":[{"host":"","container":"5678"}],"env":[{"name":"N8N_BASIC_AUTH_ACTIVE","value":"true"},{"name":"N8N_BASIC_AUTH_USER","value":"admin"},{"name":"N8N_BASIC_AUTH_PASSWORD","value":"changeme"}],"volumes":[{"host":"","container":"/home/node/.n8n"}],"cpu":"1","ram":"512m","restart":"unless-stopped"}', 54),
('Portainer', 'portainer', 'apps', 'Portainer — управление Docker через GUI', 'portainer/portainer-ce', 'latest', 'portainer', '{"ports":[{"host":"","container":"9443"},{"host":"","container":"9000"}],"env":[],"volumes":[{"host":"/var/run/docker.sock","container":"/var/run/docker.sock"},{"host":"","container":"/data"}],"cpu":"0.5","ram":"256m","restart":"unless-stopped"}', 55),
('MinIO', 'minio', 'apps', 'MinIO — S3-совместимое объектное хранилище', 'minio/minio', 'latest', 'minio', '{"ports":[{"host":"","container":"9000"},{"host":"","container":"9001"}],"env":[{"name":"MINIO_ROOT_USER","value":"minioadmin"},{"name":"MINIO_ROOT_PASSWORD","value":"changeme123"}],"volumes":[{"host":"","container":"/data"}],"cmd":"server /data --console-address :9001","cpu":"1","ram":"512m","restart":"unless-stopped"}', 56);

-- AI
INSERT OR IGNORE INTO templates (name, slug, category, description, image, default_tag, icon, config_json, sort_order) VALUES
('Ollama', 'ollama', 'ai', 'Ollama — локальный запуск LLM моделей', 'ollama/ollama', 'latest', 'ollama', '{"ports":[{"host":"","container":"11434"}],"env":[],"volumes":[{"host":"","container":"/root/.ollama"}],"cpu":"4","ram":"8g","restart":"unless-stopped"}', 60),
('Open WebUI', 'openwebui', 'ai', 'Open WebUI — веб-интерфейс для LLM', 'ghcr.io/open-webui/open-webui', 'main', 'openwebui', '{"ports":[{"host":"","container":"8080"}],"env":[{"name":"OLLAMA_BASE_URL","value":"http://ollama:11434"}],"volumes":[{"host":"","container":"/app/backend/data"}],"cpu":"1","ram":"1g","restart":"unless-stopped"}', 61);

-- Коммуникации
INSERT OR IGNORE INTO templates (name, slug, category, description, image, default_tag, icon, config_json, sort_order) VALUES
('Matrix Synapse', 'synapse', 'communication', 'Matrix Synapse — сервер децентрализованного мессенджера', 'matrixdotorg/synapse', 'latest', 'matrix', '{"ports":[{"host":"","container":"8008"},{"host":"","container":"8448"}],"env":[{"name":"SYNAPSE_SERVER_NAME","value":"matrix.local"},{"name":"SYNAPSE_REPORT_STATS","value":"no"}],"volumes":[{"host":"","container":"/data"}],"cpu":"1","ram":"1g","restart":"unless-stopped"}', 70),
('Mastodon', 'mastodon', 'communication', 'Mastodon — децентрализованная социальная сеть', 'tootsuite/mastodon', 'latest', 'mastodon', '{"ports":[{"host":"","container":"3000"},{"host":"","container":"4000"}],"env":[],"volumes":[],"cpu":"2","ram":"2g","restart":"unless-stopped"}', 71);

-- Медиа
INSERT OR IGNORE INTO templates (name, slug, category, description, image, default_tag, icon, config_json, sort_order) VALUES
('Immich', 'immich', 'media', 'Immich — самохостинг Google Photos', 'ghcr.io/immich-app/immich-server', 'release', 'immich', '{"ports":[{"host":"","container":"3001"}],"env":[{"name":"DB_PASSWORD","value":"changeme"}],"volumes":[{"host":"","container":"/usr/src/app/upload"}],"cpu":"2","ram":"2g","restart":"unless-stopped"}', 80);
