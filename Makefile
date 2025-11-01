# SignalStorm Makefile
# Convenience commands for common operations

.PHONY: help setup up down restart logs clean test

help: ## Show this help message
	@echo 'Usage: make [target]'
	@echo ''
	@echo 'Available targets:'
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*?## "}; {printf "  %-20s %s\n", $$1, $$2}'

setup: ## Initial setup - run this first
	@echo "🚀 Setting up SignalStorm..."
	@chmod +x setup.sh
	@./setup.sh

up: ## Start all services
	@echo "▶️  Starting services..."
	@docker compose up -d

down: ## Stop all services
	@echo "⏹️  Stopping services..."
	@docker compose down

restart: ## Restart all services
	@echo "🔄 Restarting services..."
	@docker compose restart

logs: ## Show logs (use CTRL+C to exit)
	@docker compose logs -f

logs-laravel: ## Show Laravel logs
	@docker compose logs -f laravel

logs-python: ## Show Python trainer logs
	@docker compose logs -f python-trainer

logs-worker: ## Show queue worker logs
	@docker compose logs -f laravel-worker

ps: ## Show running services
	@docker compose ps

shell-laravel: ## Open Laravel shell
	@docker compose exec laravel bash

shell-python: ## Open Python shell
	@docker compose exec python-trainer bash

tinker: ## Open Laravel Tinker
	@docker compose exec laravel php artisan tinker

test: ## Run all tests
	@echo "🧪 Running Laravel tests..."
	@docker compose exec laravel php artisan test
	@echo ""
	@echo "🧪 Running Python tests..."
	@docker compose exec python-trainer pytest -v

test-laravel: ## Run Laravel tests
	@docker compose exec laravel php artisan test

test-python: ## Run Python tests
	@docker compose exec python-trainer pytest -v

migrate: ## Run database migrations
	@docker compose exec laravel php artisan migrate

migrate-fresh: ## Fresh database migration (WARNING: deletes all data)
	@docker compose exec laravel php artisan migrate:fresh

fetch-data: ## Fetch sample data (BTCUSDT 1h 100 candles)
	@echo "📊 Fetching sample data..."
	@docker compose exec laravel php artisan data:fetch BTCUSDT 1h 100

train-model: ## Train sample model (BTCUSDT 1h)
	@echo "🤖 Training model..."
	@docker compose exec laravel php artisan model:train BTCUSDT 1h --limit=100 --epochs=5

queue-work: ## Run queue worker in foreground
	@docker compose exec laravel php artisan queue:work --verbose

clean: ## Clean up containers and volumes
	@echo "🧹 Cleaning up..."
	@docker compose down -v
	@docker system prune -f

rebuild: ## Rebuild all containers
	@echo "🔨 Rebuilding containers..."
	@docker compose down
	@docker compose build --no-cache
	@docker compose up -d

health: ## Check service health
	@echo "🏥 Checking service health..."
	@echo "Laravel:"
	@curl -s http://localhost:8000 || echo "  ❌ Not responding"
	@echo ""
	@echo "Python Trainer:"
	@curl -s http://localhost:8001/health || echo "  ❌ Not responding"
	@echo ""
	@echo "Services:"
	@docker compose ps

install-laravel: ## Install Laravel dependencies
	@docker compose exec laravel composer install

install-python: ## Install Python dependencies
	@docker compose exec python-trainer pip install -r requirements.txt

cache-clear: ## Clear Laravel cache
	@docker compose exec laravel php artisan cache:clear
	@docker compose exec laravel php artisan config:clear
	@docker compose exec laravel php artisan route:clear

demo: ## Run complete demo workflow
	@echo "🎬 Running demo workflow..."
	@echo ""
	@echo "1️⃣  Fetching data..."
	@docker compose exec laravel php artisan data:fetch BTCUSDT 1h 100
	@sleep 3
	@echo ""
	@echo "2️⃣  Training model..."
	@docker compose exec laravel php artisan model:train BTCUSDT 1h --limit=100 --epochs=5
	@echo ""
	@echo "3️⃣  Check logs for results:"
	@echo "    make logs-worker"

# Default target
.DEFAULT_GOAL := help

