#!/bin/bash

# DockerPanel - Автоматический скрипт установки
# Поддерживаемые ОС: Ubuntu 22.04+, Debian 11+

set -e

echo -e "\033[1;36m========================================================\033[0m"
echo -e "\033[1;36m           Установка DockerPanel 시작 (Start)           \033[0m"
echo -e "\033[1;36m========================================================\033[0m"

# Проверка на root
if [ "$EUID" -ne 0 ]; then
  echo -e "\033[1;31mПожалуйста, запустите скрипт от имени root (sudo bash install.sh)\033[0m"
  exit 1
fi

# Отключаем policy-rc.d, если установка идёт внутри Docker-контейнера
if [ -f /.dockerenv ] || [ -f /run/.containerenv ]; then
    echo -e "\033[1;33m[!] Обнаружен контейнер: отключаем блокировку policy-rc.d...\033[0m"
    printf '#!/bin/sh\nexit 0\n' > /usr/sbin/policy-rc.d
    chmod +x /usr/sbin/policy-rc.d
fi

echo -e "\n\033[1;34m[1/6] Обновление списка пакетов...\033[0m"
DEBIAN_FRONTEND=noninteractive apt-get update -yq

echo -e "\n\033[1;34m[2/6] Установка базовых утилит и Docker...\033[0m"
DEBIAN_FRONTEND=noninteractive apt-get install -yq curl wget git unzip sudo
if ! command -v docker &> /dev/null; then
    echo "Docker не найден. Устанавливаем Docker..."
    curl -fsSL https://get.docker.com -o get-docker.sh
    sh get-docker.sh
    rm get-docker.sh
fi

echo -e "\n\033[1;34m[3/6] Установка Nginx, PHP 8.x и Python...\033[0m"
DEBIAN_FRONTEND=noninteractive apt-get install -yq nginx sqlite3 php-fpm php-cli php-sqlite3 php-curl php-mbstring python3 python3-pip python3-websockets python3-docker python3-ptyprocess cron

echo -e "\n\033[1;34m[4/6] Клонирование репозитория DockerPanel...\033[0m"
WEB_DIR="/var/www/dockerpanel"
if [ -d "$WEB_DIR" ]; then
    echo "Директория $WEB_DIR уже существует. Делаем бекап..."
    mv "$WEB_DIR" "${WEB_DIR}_backup_$(date +%s)"
fi
git clone https://github.com/GrinRonm/dokerpanel.git "$WEB_DIR"

echo -e "\n\033[1;34m[5/6] Настройка прав доступа и базы данных...\033[0m"
mkdir -p "$WEB_DIR/storage/logs"
chown -R www-data:www-data "$WEB_DIR"
chmod -R 775 "$WEB_DIR/storage"

# Разрешаем www-data выполнять docker, systemctl и du без пароля
cat <<EOF > /etc/sudoers.d/dockerpanel
www-data ALL=(ALL) NOPASSWD: /usr/bin/docker
www-data ALL=(ALL) NOPASSWD: /bin/systemctl
www-data ALL=(ALL) NOPASSWD: /usr/bin/du
EOF
chmod 440 /etc/sudoers.d/dockerpanel

# Создаем пустую БД если ее нет
sudo -u www-data php "$WEB_DIR/scripts/init_db.php" || true

echo -e "\n\033[1;34m[6/6] Настройка Nginx и сервисов...\033[0m"
cat <<'EOF' > /etc/nginx/sites-available/dockerpanel
server {
    listen 80;
    server_name _;
    root /var/www/dockerpanel;
    index index.php index.html;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location /ws/ {
        proxy_pass http://127.0.0.1:8765/;
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection "upgrade";
        proxy_set_header Host $host;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/var/run/php/php8.3-fpm.sock;
    }

    location /public/ {
        expires 30d;
        access_log off;
    }
}
EOF

# Проверяем версию PHP-FPM и обновляем конфиг Nginx
PHP_VER=$(ls /etc/php/ | head -n 1)
if [ ! -z "$PHP_VER" ]; then
    sed -i "s|unix:/var/run/php/php8.3-fpm.sock;|unix:/var/run/php/php${PHP_VER}-fpm.sock;|g" /etc/nginx/sites-available/dockerpanel
fi

ln -sf /etc/nginx/sites-available/dockerpanel /etc/nginx/sites-enabled/
rm -f /etc/nginx/sites-enabled/default
systemctl reload nginx

# Настройка WebSocket терминала
cp "$WEB_DIR/websocket/terminal.service" /etc/systemd/system/
systemctl daemon-reload
systemctl enable terminal.service
systemctl restart terminal.service

# Настройка Cron для бекапов
(crontab -l 2>/dev/null | grep -v "backup_cron.php"; echo "* * * * * sudo -u www-data php $WEB_DIR/scripts/backup_cron.php >> $WEB_DIR/storage/logs/cron.log 2>&1") | crontab -

echo -e "\n\033[1;32m========================================================\033[0m"
echo -e "\033[1;32m  Установка DockerPanel успешно завершена! 🎉\033[0m"
echo -e "\033[1;32m========================================================\033[0m"
IP=$(curl -s http://whatismyip.akamai.com/ || hostname -I | awk '{print $1}')
echo -e "\nПанель доступна по адресу: \033[1;36mhttp://${IP}\033[0m"
echo -e "Логин по умолчанию: \033[1;33madmin\033[0m"
echo -e "Пароль по умолчанию: \033[1;33madmin\033[0m"
echo -e "\033[1;31mНастоятельно рекомендуется сменить пароль после первого входа!\033[0m\n"
