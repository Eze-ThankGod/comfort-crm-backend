#!/bin/bash
set -e

echo "=========================================="
echo "  Comfort CRM — cPanel Production Build"
echo "=========================================="

# Clean up any previous build artifacts
rm -rf build/ laravel-deploy.zip

echo ""
echo "[1/4] Installing production dependencies..."
composer install --optimize-autoloader --no-dev --quiet

echo "[2/4] Clearing local caches..."
php artisan config:clear
php artisan route:clear
php artisan view:clear

echo "[3/4] Bundling files..."
mkdir -p build

rsync -a \
    --exclude='.git' \
    --exclude='.github' \
    --exclude='node_modules' \
    --exclude='tests' \
    --exclude='build' \
    --exclude='laravel-deploy.zip' \
    --exclude='.env' \
    --exclude='.env.example' \
    --exclude='storage/logs/*.log' \
    --exclude='storage/framework/cache/*' \
    --exclude='storage/framework/sessions/*' \
    --exclude='storage/framework/views/*' \
    --exclude='*.sh' \
    --exclude='phpunit.xml' \
    --exclude='vite.config.js' \
    --exclude='package.json' \
    --exclude='package-lock.json' \
    . build/

echo "[4/4] Creating zip archive..."
zip -r laravel-deploy.zip build/ --quiet

# Clean up temp build folder
rm -rf build/

echo ""
echo "=========================================="
echo "  Build complete: laravel-deploy.zip"
echo "=========================================="
echo ""
echo "Next steps:"
echo "  1. Upload laravel-deploy.zip to cPanel File Manager → public_html"
echo "  2. Extract the zip and move contents from build/ to public_html/"
echo "  3. Run: composer install --optimize-autoloader --no-dev"
echo "  4. Create and configure your .env file"
echo "  5. Run: php artisan migrate --force"
echo "  6. Run: php artisan db:seed --force"
echo "  7. Run: php artisan storage:link"
echo "  8. Run: php artisan config:cache && php artisan route:cache && php artisan view:cache"
echo "  9. Run: chmod -R 755 storage bootstrap/cache"
echo " 10. Add cron job (see README.md)"
echo ""
