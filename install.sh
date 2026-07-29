#!/bin/bash
# DockerPanel 1-Click Installer
set -e

REPO_URL="https://github.com/ВАШ_ПОЛЬЗОВАТЕЛЬ/dockerpanel.git"
INSTALL_DIR="/var/www/dockerpanel"

echo "========================================"
echo "    DockerPanel 1-Click Installer"
echo "========================================"

if [ "$EUID" -ne 0 ]; then
  echo "Пожалуйста, запустите скрипт от имени root (sudo)"
  exit 1
fi

echo "[1/7] Установка системных зависимостей..."
apt-get update
apt-get install -y git nginx sqlite3 curl python3-pip python3-venv

echo "[2/7] Установка PHP 8..."
apt-get install -y php-fpm php-sqlite3 php-curl php-mbstring php-xml

echo "[3/7] Установка Docker (если не установлен)..."
if ! command -v docker &> /dev/null; then
    curl -fsSL https://get.docker.com -o get-docker.sh
    sh get-docker.sh
    rm get-docker.sh
fi

echo "[4/7] Клонирование репозитория..."
if [ -d "$INSTALL_DIR" ]; then
    echo "Директория $INSTALL_DIR уже существует. Обновление..."
    cd $INSTALL_DIR
    git pull
else
    git clone $REPO_URL $INSTALL_DIR
fi

echo "[5/7] Настройка базы данных и прав..."
cd $INSTALL_DIR
mkdir -p storage/database storage/logs storage/backups
sqlite3 storage/database/database.sqlite < database/schema.sql
chown -R www-data:www-data $INSTALL_DIR
chmod -R 775 $INSTALL_DIR/storage

echo "[6/7] Настройка Nginx..."
cat > /etc/nginx/sites-available/dockerpanel << 'EOF'
server {
    listen 80;
    server_name _;
    root /var/www/dockerpanel/public;
    index index.php index.html;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/var/run/php/php-fpm.sock;
    }
}
EOF
ln -sf /etc/nginx/sites-available/dockerpanel /etc/nginx/sites-enabled/
rm -f /etc/nginx/sites-enabled/default
systemctl restart nginx

echo "[7/7] Настройка WebSocket терминала и Cron..."
apt-get install -y python3-websockets python3-docker python3-ptyprocess || pip3 install websockets docker ptyprocess --break-system-packages
cp websocket/terminal.service /etc/systemd/system/
systemctl daemon-reload
systemctl enable terminal.service
systemctl restart terminal.service

echo "* * * * * www-data php $INSTALL_DIR/scripts/backup_cron.php >> $INSTALL_DIR/storage/logs/cron.log 2>&1" > /etc/cron.d/dockerpanel_backup
systemctl restart cron

echo "========================================"
echo "Установка успешно завершена! 🎉"
echo "URL панели: http://$(curl -s ifconfig.me)"
echo "Логин по умолчанию: admin"
echo "Пароль по умолчанию: admin"
echo "========================================"
