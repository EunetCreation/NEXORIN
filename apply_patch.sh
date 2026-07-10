#!/usr/bin/env bash
set -euo pipefail
TARGET="${1:-}"
if [ -z "$TARGET" ]; then
  echo "Usage: ./apply_patch.sh /path/to/NEXORIN-APP"
  exit 2
fi
if [ ! -f "$TARGET/artisan" ]; then
  echo "Target does not look like a Laravel project root: $TARGET"
  exit 2
fi
rsync -av ./ "$TARGET" --exclude README_INSTALL_PATCH.md --exclude apply_patch.sh
cd "$TARGET"
composer dump-autoload
php artisan config:clear
php artisan view:clear
php artisan route:clear
printf '\nPatch copied. Now run:\n  npm install\n  npm run pdf:install\n\n'
