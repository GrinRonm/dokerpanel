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

echo "[3/3] Запись версии..."
COMMIT_HASH=$(git rev-parse --short HEAD)
echo "<?php return ['version' => '${COMMIT_HASH}'];" > $INSTALL_DIR/config/version.php
chown www-data:www-data $INSTALL_DIR/config/version.php

echo "[4/4] Перезапуск сервисов..."
systemctl restart terminal.service || true
systemctl restart nginx || true
systemctl restart php8.3-fpm || true

echo "Обновление завершено! 🎉"
