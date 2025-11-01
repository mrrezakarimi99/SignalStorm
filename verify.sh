#!/bin/bash

# SignalStorm Verification Script
# Checks if everything is ready to run

echo "🔍 SignalStorm Setup Verification"
echo "=================================="
echo ""

# Colors
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
NC='\033[0m'

ERRORS=0

# Function to check file exists
check_file() {
    if [ -f "$1" ]; then
        echo -e "${GREEN}✓${NC} $1"
        return 0
    else
        echo -e "${RED}✗${NC} $1 - MISSING"
        ERRORS=$((ERRORS + 1))
        return 1
    fi
}

# Function to check directory exists
check_dir() {
    if [ -d "$1" ]; then
        echo -e "${GREEN}✓${NC} $1/"
        return 0
    else
        echo -e "${RED}✗${NC} $1/ - MISSING"
        ERRORS=$((ERRORS + 1))
        return 1
    fi
}

echo "📁 Checking Directory Structure..."
check_dir "laravel"
check_dir "laravel/app"
check_dir "laravel/bootstrap"
check_dir "laravel/config"
check_dir "laravel/routes"
check_dir "laravel/public"
check_dir "trainer"
check_dir "trainer/src"
check_dir "docker"
echo ""

echo "🐘 Checking Laravel Core Files..."
check_file "laravel/artisan"
check_file "laravel/public/index.php"
check_file "laravel/bootstrap/app.php"
check_file "laravel/composer.json"
echo ""

echo "🛣️  Checking Laravel Routes..."
check_file "laravel/routes/web.php"
check_file "laravel/routes/api.php"
check_file "laravel/routes/console.php"
echo ""

echo "⚙️  Checking Laravel Config..."
check_file "laravel/config/app.php"
check_file "laravel/config/database.php"
check_file "laravel/config/queue.php"
check_file "laravel/config/services.php"
echo ""

echo "📦 Checking Laravel Application..."
check_file "laravel/app/Console/Kernel.php"
check_file "laravel/app/Providers/SignalStormServiceProvider.php"
check_dir "laravel/app/Contracts"
check_dir "laravel/app/Models"
check_dir "laravel/app/Jobs"
echo ""

echo "🐍 Checking Python Trainer..."
check_file "trainer/requirements.txt"
check_file "trainer/src/api/main.py"
check_file "trainer/src/trainers/lstm_trainer.py"
check_dir "trainer/src/contracts"
check_dir "trainer/tests"
echo ""

echo "🐳 Checking Docker Files..."
check_file "docker-compose.yml"
check_file "docker/laravel.Dockerfile"
check_file "docker/python.Dockerfile"
echo ""

echo "📄 Checking Documentation..."
check_file "README.md"
check_file "QUICKSTART.md"
check_file "ARCHITECTURE.md"
check_file "FIXES_APPLIED.md"
echo ""

echo "🔧 Checking Scripts..."
check_file "setup.sh"
check_file "Makefile"
check_file ".env.example"
echo ""

# Check if artisan is executable
if [ -x "laravel/artisan" ]; then
    echo -e "${GREEN}✓${NC} laravel/artisan is executable"
else
    echo -e "${YELLOW}⚠${NC} laravel/artisan is not executable (will be fixed in setup)"
fi

# Check if setup.sh is executable
if [ -x "setup.sh" ]; then
    echo -e "${GREEN}✓${NC} setup.sh is executable"
else
    echo -e "${YELLOW}⚠${NC} setup.sh is not executable (run: chmod +x setup.sh)"
fi

echo ""
echo "🐳 Checking Docker..."
if command -v docker &> /dev/null; then
    echo -e "${GREEN}✓${NC} Docker is installed"

    if docker compose version &> /dev/null 2>&1; then
        echo -e "${GREEN}✓${NC} Docker Compose is available"
        COMPOSE_VERSION=$(docker compose version --short 2>&1)
        echo "  Version: $COMPOSE_VERSION"
    else
        echo -e "${RED}✗${NC} Docker Compose is not available"
        echo "  Install Docker Compose plugin"
        ERRORS=$((ERRORS + 1))
    fi
else
    echo -e "${RED}✗${NC} Docker is not installed"
    ERRORS=$((ERRORS + 1))
fi

echo ""
echo "=================================="

if [ $ERRORS -eq 0 ]; then
    echo -e "${GREEN}✅ All checks passed! You're ready to run:${NC}"
    echo ""
    echo "  ./setup.sh"
    echo ""
    echo "or"
    echo ""
    echo "  make setup"
    echo ""
    exit 0
else
    echo -e "${RED}❌ Found $ERRORS error(s)${NC}"
    echo ""
    echo "Please check the missing files/directories above."
    exit 1
fi

