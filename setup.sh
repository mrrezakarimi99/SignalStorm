#!/bin/bash

echo "🚀 SignalStorm Setup..."

# Check Docker
if ! docker compose version &> /dev/null; then
    echo "❌ Docker Compose not found"
    exit 1
fi

# Create .env
if [ ! -f .env ]; then
    cp .env.example .env
    echo "✓ Created .env"
fi

# Create directories
mkdir -p laravel/storage/{app,framework/{cache,sessions,views},logs}
mkdir -p laravel/bootstrap/cache
mkdir -p trainer/models
echo "✓ Created directories"

# Start infrastructure
echo "Starting PostgreSQL, Redis..."
docker compose up -d postgres redis
sleep 10
echo "✓ Infrastructure started"

# Build and start Laravel
echo "Building Laravel..."
docker compose build laravel
docker compose up -d laravel
sleep 5
echo "✓ Laravel started"

# Install dependencies
echo "Installing Composer dependencies..."
docker compose exec -T laravel composer install --no-interaction || echo "⚠️  Composer install warning (might be OK)"

# Generate key
echo "Generating app key..."
docker compose exec -T laravel php artisan key:generate || echo "⚠️  Key generation warning"

# Migrate database
echo "Running migrations..."
docker compose exec -T laravel php artisan migrate --force || echo "⚠️  Migration warning"

# Start Python
echo "Building Python trainer..."
docker compose build python-trainer
docker compose up -d python-trainer
echo "✓ Python trainer started"

# Start worker
echo "Starting queue worker..."
docker compose up -d laravel-worker

echo ""
echo "✅ Setup Complete!"
echo ""
echo "Services:"
echo "  Laravel:  http://localhost:8000"
echo "  Python:   http://localhost:8001"
echo "  MinIO:    http://localhost:9001 (admin/password123)"
echo ""
echo "Quick commands:"
echo "  docker compose ps                    # Check status"
echo "  docker compose logs -f               # View logs"
echo "  docker compose exec laravel php artisan data:fetch BTCUSDT 1h 100"
echo "  docker compose exec laravel php artisan model:train BTCUSDT 1h"
echo ""
echo "Happy trading! 🎉"


