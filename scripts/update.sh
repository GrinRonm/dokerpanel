#!/bin/bash
# DockerPanel Updater
set -e

INSTALL_DIR="/var/www/dockerpanel"

echo "========================================"
echo "    DockerPanel Updater"
echo "========================================"

cd $INSTALL_DIR

echo "[1/3] Загрузка обновлений..."
git reset --hard
git pull origin main

echo "[2/3] Обновление прав..."
chown -R www-data:www-data $INSTALL_DIR
chmod -R 775 $INSTALL_DIR/storage

echo "[3/3] Перезапуск сервисов..."
systemctl restart terminal.service || true
systemctl restart nginx || true
systemctl restart php8.3-fpm || true

echo "Обновление завершено! 🎉"
