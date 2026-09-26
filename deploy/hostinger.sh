#!/usr/bin/env bash
#
# Deploy awawa lên Hostinger (shared hosting) qua SSH.
#
# Cấu trúc thư mục khuyến nghị:
#   ~/domains/awawa.herspalab.com/laravel      <- git clone repo vào đây (app root)
#   ~/domains/awawa.herspalab.com/public_html  <- symlink -> laravel/public
#
# Chuẩn bị (chạy 1 lần trên SSH):
#   cd ~/domains/awawa.herspalab.com
#   rm -rf public_html
#   git clone https://github.com/HoangMinhKhanhDev/awawa-education.git laravel
#   ln -s "$PWD/laravel/public" public_html
#   cd laravel && cp ../env.production.example .env   # rồi điền giá trị
#   php artisan key:generate
#   ln -s "$PWD/storage/app/public" public/storage
#
# Cron (hPanel > Advanced > Cron Jobs), mỗi phút:
#   php /home/USER/domains/awawa.herspalab.com/laravel/artisan schedule:run
#
# Sau đó mỗi lần deploy chỉ cần chạy: bash deploy/hostinger.sh
#
set -euo pipefail

APP_DIR="${APP_DIR:-$HOME/domains/awawa.herspalab.com/laravel}"
PHP_BIN="${PHP_BIN:-php}"

cd "$APP_DIR"

echo "==> [1/6] Pull code mới nhất"
git pull --ff-only origin main

echo "==> [2/6] Cài Composer (local, nếu thiếu)"
if [ ! -f composer.phar ]; then
    "$PHP_BIN" -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
    "$PHP_BIN" composer-setup.php --install-dir=. --filename=composer.phar
    rm -f composer-setup.php
fi

echo "==> [3/6] Cài dependencies (no-dev)"
"$PHP_BIN" -d memory_limit=1024M composer.phar install --no-dev --prefer-dist --optimize-autoloader --no-interaction

echo "==> [4/6] Migrate database"
"$PHP_BIN" artisan migrate --force

echo "==> [5/6] Storage"
mkdir -p storage/app/public
PUBLIC_DISK_ROOT_VALUE="$(grep -E '^PUBLIC_DISK_ROOT=' .env 2>/dev/null | head -n1 | cut -d= -f2- | tr -d '"' || true)"
if [ -n "$PUBLIC_DISK_ROOT_VALUE" ]; then
    echo "    PUBLIC_DISK_ROOT=$PUBLIC_DISK_ROOT_VALUE -> bỏ qua symlink"
    mkdir -p "$PUBLIC_DISK_ROOT_VALUE"
else
    if [ ! -L public/storage ]; then
        ln -s "$APP_DIR/storage/app/public" public/storage || true
    fi
fi

echo "==> [6/6] Tối ưu cache"
"$PHP_BIN" artisan optimize

echo "==> Hoàn tất deploy."
