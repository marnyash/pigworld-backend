#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
APP_DIR="$ROOT_DIR/app"

cd "$APP_DIR"

if [[ ! -f ".env" ]]; then
  echo "Missing .env file in $APP_DIR"
  echo "Copy .env.example to .env before running this script."
  exit 1
fi

if [[ ! -f "database/database.sqlite" ]]; then
  touch database/database.sqlite
fi

DB_CONNECTION=sqlite DB_DATABASE=database/database.sqlite php artisan migrate --force --seed
DB_CONNECTION=sqlite DB_DATABASE=database/database.sqlite php artisan serve --host 0.0.0.0 --port 8000
