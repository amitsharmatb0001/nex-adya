#!/bin/bash

# Nexadya Phase 1 Setup Script
# This script sets up Rate Limiting, Caching, and Performance Optimizations

set -e

echo "================================================"
echo "Nexadya Phase 1 Setup"
echo "Security & Performance Optimizations"
echo "================================================"
echo ""

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Check if Redis is running
echo "Checking Redis connection..."
if redis-cli ping > /dev/null 2>&1; then
    echo -e "${GREEN}✓ Redis is running${NC}"
else
    echo -e "${RED}✗ Redis is not running${NC}"
    echo ""
    echo "Please install and start Redis:"
    echo "  macOS:   brew install redis && brew services start redis"
    echo "  Ubuntu:  sudo apt install redis-server && sudo systemctl start redis"
    echo "  Windows: Download from https://github.com/microsoftarchive/redis/releases"
    echo ""
    read -p "Continue anyway? (y/n) " -n 1 -r
    echo
    if [[ ! $REPLY =~ ^[Yy]$ ]]; then
        exit 1
    fi
fi

# Check .env configuration
echo ""
echo "Checking .env configuration..."

if ! grep -q "CACHE_STORE=redis" .env 2>/dev/null; then
    echo -e "${YELLOW}⚠ CACHE_STORE not set to redis in .env${NC}"
    read -p "Update .env to use Redis? (y/n) " -n 1 -r
    echo
    if [[ $REPLY =~ ^[Yy]$ ]]; then
        if grep -q "CACHE_STORE=" .env; then
            sed -i.bak 's/CACHE_STORE=.*/CACHE_STORE=redis/' .env
        else
            echo "CACHE_STORE=redis" >> .env
        fi
        echo -e "${GREEN}✓ Updated CACHE_STORE to redis${NC}"
    fi
fi

# Check Redis configuration in .env
if ! grep -q "REDIS_HOST=" .env 2>/dev/null; then
    echo "REDIS_HOST=127.0.0.1" >> .env
    echo "REDIS_PASSWORD=null" >> .env
    echo "REDIS_PORT=6379" >> .env
    echo -e "${GREEN}✓ Added Redis configuration to .env${NC}"
fi

# Run migrations
echo ""
echo "Running migrations..."
php artisan migrate --force

if [ $? -eq 0 ]; then
    echo -e "${GREEN}✓ Migrations completed successfully${NC}"
else
    echo -e "${RED}✗ Migration failed${NC}"
    exit 1
fi

# Clear and optimize
echo ""
echo "Clearing and optimizing..."
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear

echo -e "${GREEN}✓ Cache cleared${NC}"

# Cache configuration
php artisan config:cache
php artisan route:cache

echo -e "${GREEN}✓ Configuration cached${NC}"

# Test Redis connection
echo ""
echo "Testing Redis connection..."
php artisan tinker --execute="
    Cache::put('setup_test', 'working', 60);
    \$result = Cache::get('setup_test');
    if (\$result === 'working') {
        echo '✓ Redis connection successful\n';
    } else {
        echo '✗ Redis connection failed\n';
        exit(1);
    }
"

# Display cache stats
echo ""
echo "Current cache statistics:"
php artisan cache:stats

# Summary
echo ""
echo "================================================"
echo -e "${GREEN}Phase 1 Setup Complete!${NC}"
echo "================================================"
echo ""
echo "What's been configured:"
echo "  ✓ Redis caching enabled"
echo "  ✓ Performance indexes created"
echo "  ✓ Model observers registered"
echo "  ✓ Rate limiting configured"
echo "  ✓ File validation rules added"
echo ""
echo "Next steps:"
echo "  1. Review routes/api_with_rate_limiting_example.php"
echo "  2. Update your actual routes with rate limiting"
echo "  3. Replace old controllers with cached versions"
echo "  4. Test the API endpoints"
echo "  5. Monitor cache hit rate: php artisan cache:stats"
echo ""
echo "Documentation:"
echo "  - Phase 1 Guide: PHASE_1_IMPLEMENTATION_GUIDE.md"
echo "  - Rate Limiting Config: config/rate-limiting.php"
echo "  - Cache Service: app/Services/CacheService.php"
echo ""
echo "Useful commands:"
echo "  php artisan cache:stats          # View cache statistics"
echo "  php artisan cache:clear-app      # Clear application caches"
echo "  php artisan cache:stats --refresh # Live cache monitoring"
echo ""
echo "================================================"
