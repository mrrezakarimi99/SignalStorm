# 🌩️ SignalStorm - Crypto Trading Alert Platform
**Laravel (orchestration) + Python (ML) | SOLID Principles | Docker Ready**
Quick start: `./setup.sh` → Wait 3 min → Done! ✅
---
## 📖 What Is This?
A production-ready crypto trading platform that:
- 📊 Fetches market data (Binance, Mock, etc.)
- 🤖 Trains ML models (LSTM neural networks)
- 📱 Sends alerts (Telegram, Console, etc.)
- 💾 Stores everything (PostgreSQL)
- ⚡ Processes jobs (Redis queues)
**Why use it?** Learn SOLID principles, microservices, ML integration, Docker orchestration - all in one real-world project.
---
## 🚀 Quick Start
```bash
cd /home/reza/Projects/Crypto/SignalStorm
./setup.sh
```
**That's it!** Setup automatically:
1. Starts PostgreSQL, Redis, MinIO
2. Builds Laravel + Python containers
3. Installs all dependencies
4. Runs database migrations
5. Starts all 6 services
**Verify:**
```bash
curl http://localhost:8000         # Laravel API
curl http://localhost:8001/health  # Python Trainer
docker compose ps                  # All services
```
---
## 💻 Usage Examples
### Fetch Data
```bash
docker compose exec laravel php artisan data:fetch BTCUSDT 1h 100
```
### Train Model
```bash
docker compose exec laravel php artisan model:train BTCUSDT 1h --limit=1000 --epochs=50
```
### Get Predictions
```bash
curl http://localhost:8000/api/predictions/latest
```
### Explore Data
```bash
docker compose exec laravel php artisan tinker
>>> App\Models\Candle::count()
>>> App\Models\Prediction::latest()->first()
```
---
## 🏗️ Architecture
```
Laravel (Port 8000)                Python (Port 8001)
├── Data Providers    ────HTTP────▶ LSTM Trainer
├── Normalizer                      Model Storage
├── Notifiers
├── Queue Jobs
└── PostgreSQL + Redis
```
**Services:**
- Laravel API (8000) - Orchestration, jobs, notifications
- Python Trainer (8001) - ML training & predictions
- PostgreSQL (5432) - Database
- Redis (6379) - Queue & cache
- MinIO (9000/9001) - Model storage
- Queue Worker - Background processing
---
## 📡 API Reference
### Laravel (http://localhost:8000)
**Health:**
- `GET /` - Status
- `GET /health` - Health check
**Data:**
- `POST /api/data/fetch` - Fetch data
  ```json
  {"symbol":"BTCUSDT","interval":"1h","limit":100}
  ```
- `GET /api/data/candles/{symbol}/{interval}` - Get candles
**Training:**
- `POST /api/model/train` - Train model
  ```json
  {"symbol":"BTCUSDT","interval":"1h","limit":1000,"epochs":50}
  ```
**Predictions:**
- `GET /api/predictions/latest` - Latest predictions
- `GET /api/predictions/{symbol}` - Symbol predictions
### Python (http://localhost:8001)
- `GET /health` - Health check
- `POST /train` - Train model
- `POST /predict` - Get predictions
- `GET /models` - List models
---
## ⚙️ Configuration
Edit `.env`:
**Use Real Binance Data:**
```env
DATA_PROVIDER=binance
BINANCE_API_KEY=your_key
BINANCE_API_SECRET=your_secret
```
**Enable Telegram:**
```env
NOTIFIERS=telegram,console
TELEGRAM_BOT_TOKEN=your_token
TELEGRAM_CHAT_ID=your_chat_id
```
Restart: `docker compose restart laravel laravel-worker`
---
## 🔧 Common Commands
**Makefile (recommended):**
```bash
make up           # Start all services
make down         # Stop all services
make logs         # View logs
make demo         # Run complete demo
make test         # Run all tests
make help         # Show all commands
```
**Docker Compose:**
```bash
docker compose up -d                    # Start
docker compose down                     # Stop
docker compose logs -f                  # Logs
docker compose exec laravel bash        # Shell
```
**Laravel CLI:**
```bash
docker compose exec laravel php artisan data:fetch BTCUSDT 1h 100
docker compose exec laravel php artisan model:train BTCUSDT 1h
docker compose exec laravel php artisan tinker
docker compose exec laravel php artisan test
```
---
## 🎯 SOLID Principles (How It Works)
### Single Responsibility
Each class has one job:
- `BinanceProvider` → only fetches from Binance
- `TelegramNotifier` → only sends Telegram messages
- `DataNormalizer` → only transforms data
### Open/Closed
Add new features without modifying existing code:
- Want Coinbase? Create `CoinbaseProvider` implementing `IDataProvider`
- Want Slack? Create `SlackNotifier` implementing `INotifier`
- No changes to core code!
### Liskov Substitution
All implementations are interchangeable:
- `BinanceProvider` ↔ `MockProvider` work identically
- `TelegramNotifier` ↔ `ConsoleNotifier` work identically
### Interface Segregation
Focused interfaces:
- `IDataProvider` - data fetching only
- `INotifier` - notifications only
- `ITrainer` - ML training only
### Dependency Inversion
Depend on abstractions:
```php
// ✅ Good
public function __construct(IDataProvider $provider) {}
// ❌ Bad
public function __construct(BinanceProvider $provider) {}
```
---
## 🔌 Extending (Strategy Pattern)
### Add New Data Provider
**1. Create class:**
```php
// laravel/app/Providers/DataProviders/CoinbaseProvider.php
class CoinbaseProvider implements IDataProvider {
    public function fetchHistorical(...) { /* your code */ }
    public function subscribeRealtime(...) { /* your code */ }
    public function getName() { return 'coinbase'; }
}
```
**2. Register:**
```php
// laravel/app/Providers/SignalStormServiceProvider.php
return match(config('services.data_provider')) {
    'binance' => new BinanceProvider(),
    'coinbase' => new CoinbaseProvider(),  // Add this
    'mock' => new MockProvider(),
};
```
**3. Use:**
```env
DATA_PROVIDER=coinbase
```
**Done/home/reza/Projects/Crypto/SignalStorm && rm -f ARCHITECTURE.md FIXES_APPLIED.md INDEX.md PROJECT_SUMMARY.md QUICKSTART.md START_HERE.md QUICK_REFERENCE.txt* No existing code modified.
### Add New Notifier
Same process with `INotifier` interface.
### Add New ML Model
Same process with `ITrainer` interface in Python.
---
## 🧪 Testing
```bash
# All tests
make test
# Laravel only
docker compose exec laravel php artisan test
# Python only
docker compose exec python-trainer pytest -v
```
---
## 🆘 Troubleshooting
**Services won't start:**
```bash
docker compose logs        # Check errors
docker compose down -v     # Clean slate
./setup.sh                 # Re-run setup
```
**Laravel errors:**
```bash
docker compose exec laravel php artisan cache:clear
docker compose exec laravel chmod -R 777 storage bootstrap/cache
```
**Database issues:**
```bash
docker compose exec laravel php artisan migrate:fresh
```
**View logs:**
```bash
docker compose logs -f laravel         # Laravel
docker compose logs -f python-trainer  # Python
docker compose logs -f laravel-worker  # Queue worker
```
---
## 📁 Project Structure
```
SignalStorm/
├── laravel/              # Laravel app
│   ├── app/
│   │   ├── Contracts/    # 5 interfaces (SOLID - DIP)
│   │   ├── Providers/    # 2 data providers (Strategy)
│   │   ├── Notifiers/    # 2 notifiers (Strategy)
│   │   ├── Services/     # 3 services
│   │   ├── Models/       # 3 models
│   │   ├── Jobs/         # 2 jobs
│   │   └── Console/      # 2 commands
│   ├── routes/           # API routes
│   ├── config/           # Configuration
│   ├── database/         # 3 migrations
│   └── tests/            # PHPUnit tests
│
├── trainer/              # Python ML service
│   ├── src/
│   │   ├── contracts/    # 2 interfaces
│   │   ├── trainers/     # LSTM trainer
│   │   ├── storage/      # Model storage
│   │   └── api/          # FastAPI
│   └── tests/            # Pytest tests
│
├── docker/               # Dockerfiles
├── docker-compose.yml    # Services
├── setup.sh              # Auto-setup
├── verify.sh             # Verify setup
├── Makefile              # Commands
└── README.md             # This file
```
---
## 🎓 What You'll Learn
- ✅ SOLID Principles (real-world implementation)
- ✅ Strategy Pattern (pluggable components)
- ✅ Dependency Injection (composition root)
- ✅ Microservices (Laravel ↔ Python)
- ✅ Docker (multi-container orchestration)
- ✅ Queue Processing (background jobs)
- ✅ ML Integration (LSTM neural networks)
- ✅ API Design (RESTful endpoints)
- ✅ Testing (unit + integration)
---
## 📊 Tech Stack
- **Backend:** Laravel 10 (PHP 8.2)
- **ML:** Python 3.11 (FastAPI + TensorFlow)
- **Database:** PostgreSQL 15
- **Cache/Queue:** Redis 7
- **Storage:** MinIO
- **Container:** Docker Compose V2
---
## ✨ What Makes This Special
✅ **Production Ready** - Migrations, tests, error handling  
✅ **SOLID Throughout** - Every class follows principles  
✅ **Extensible** - Add features without breaking code  
✅ **Well Documented** - Clear code with type hints  
✅ **One Command Setup** - `./setup.sh` and done  
✅ **Real World** - Actual trading platform use case  
---
## 📚 Quick Reference
| Task | Command |
|------|---------|
| Start | `./setup.sh` or `make up` |
| Stop | `make down` |
| Logs | `make logs` |
| Test | `make test` |
| Demo | `make demo` |
| Shell | `docker compose exec laravel bash` |
| Tinker | `docker compose exec laravel php artisan tinker` |
**Services:**
- Laravel: http://localhost:8000
- Python: http://localhost:8001
- MinIO: http://localhost:9001 (admin/password123)
---
## 🎉 Ready to Start
```bash
cd /home/reza/Projects/Crypto/SignalStorm
./setup.sh
```
**That's it!** Start building amazing trading features.
---
**Built with ❤️ following SOLID principles and clean architecture**
