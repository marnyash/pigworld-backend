#!/bin/bash
# Automated backend deployment script for cPanel.
# Usage: BACKEND_DIR=/home/account/apps/pigworld-api ./deploy-backend.sh

set -e

BACKEND_DIR="${BACKEND_DIR:-$HOME/apps/pigworld-api}"
API_DOMAIN="${API_DOMAIN:-api.pigworldsmart.com}"
CRM_DOMAIN="${CRM_DOMAIN:-crm.pigworldsmart.com}"

echo "========================================="
echo "Pig World Smart - Backend Deployment"
echo "========================================="
echo ""

# Check prerequisites
echo "[1/10] Checking prerequisites..."
php -v | head -n1
composer --version | head -n1
echo "✓ PHP and Composer available"
echo ""

# Navigate to backend
echo "[2/10] Navigating to backend directory..."
cd "$BACKEND_DIR"
echo "✓ Working directory: $(pwd)"
echo ""

# Check if .env exists
if [ ! -f ".env" ]; then
    echo "[3/10] Creating .env file..."
    echo "ERROR: .env does not exist. Please create it manually with production credentials."
    echo "Use the production template in DEPLOYMENT.md and add credentials only on the server."
    exit 1
else
    echo "[3/10] Using existing .env file"
    echo "✓ .env found"
fi
echo ""

# Install dependencies
echo "[4/10] Installing Composer dependencies..."
composer install --no-dev --optimize-autoloader
echo "✓ Dependencies installed"
echo ""

# Generate APP_KEY if not set
echo "[5/10] Checking APP_KEY..."
if grep -q "APP_KEY=$" .env; then
    echo "⚠ APP_KEY not set. Generating..."
    NEW_KEY=$(php -r 'echo base64_encode(random_bytes(32));')
    sed -i "s/APP_KEY=/APP_KEY=base64:$NEW_KEY/" .env
    echo "✓ APP_KEY generated: base64:$NEW_KEY"
else
    echo "✓ APP_KEY already set"
fi
echo ""

# Run migrations
echo "[6/10] Running database migrations..."
php artisan migrate --force
echo "✓ Migrations completed"
echo ""

# Set permissions
echo "[7/10] Setting permissions..."
chmod -R 775 storage bootstrap/cache
echo "✓ Permissions set for storage and cache"
echo ""

# Cache configuration
echo "[8/10] Caching configuration..."
php artisan config:cache
php artisan route:cache
php artisan view:cache
echo "✓ Configuration cached"
echo ""

# Test API health
echo "[9/10] Testing API health..."
HEALTH_CHECK=$(curl -s https://$API_DOMAIN/up || echo "FAILED")
if echo "$HEALTH_CHECK" | grep -q "ok"; then
    echo "✓ API health check passed"
else
    echo "⚠ API health check failed. Verify domain and HTTPS configuration."
    echo "Response: $HEALTH_CHECK"
fi
echo ""

# Summary
echo "[10/10] Deployment complete!"
echo ""
echo "========================================="
echo "Next Steps:"
echo "========================================="
echo "1. Build CRM: VITE_API_BASE_URL=https://$API_DOMAIN/api/v1 npm run build"
echo "2. Upload dist/ to https://$CRM_DOMAIN/"
echo "3. Test API: curl https://$API_DOMAIN/up"
echo "4. Open browser: https://$CRM_DOMAIN/"
echo ""
echo "For detailed instructions, see DEPLOYMENT.md"
echo "========================================="
