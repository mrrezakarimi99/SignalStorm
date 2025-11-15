# 🌩️ SignalStorm - AI-Powered Crypto Trading Signal Platform

**Automated Trading Signals with ML Predictions | Telegram Alerts | SOLID Architecture**

[![Docker](https://img.shields.io/badge/Docker-Ready-blue)](https://www.docker.com/)
[![Laravel](https://img.shields.io/badge/Laravel-11-red)](https://laravel.com/)
[![Python](https://img.shields.io/badge/Python-3.11-green)](https://www.python.org/)
[![TensorFlow](https://img.shields.io/badge/TensorFlow-2.x-orange)](https://www.tensorflow.org/)

**Quick Start:** `./setup.sh` → Configure Telegram → Get automated trading signals! 🚀

---

## 📖 What Is This?

A production-ready, **fully automated** crypto trading signal platform that:

✅ **Fetches** live market data from Binance API
✅ **Trains** LSTM neural network models for price prediction
✅ **Generates** BUY/SELL/HOLD signals with confidence scores
✅ **Sends** beautiful Telegram notifications automatically
✅ **Stores** all predictions and model metrics
✅ **Schedules** everything - data fetching, training, predictions
✅ **Manages** model files with download API

### 🎯 Perfect For:
- Learning ML integration in real-world applications
- Understanding SOLID principles and clean architecture
- Building automated trading systems
- Practicing microservices with Docker
- Getting real crypto trading signals
---

## 🚀 Quick Start

### 1. Clone & Setup
```bash
git clone https://github.com/mrrezakarimi99/SignalStorm.git
cd SignalStorm
./setup.sh
```

**Setup automatically:**
- ✅ Starts PostgreSQL, Redis
- ✅ Builds Laravel + Python containers
- ✅ Installs all dependencies
- ✅ Runs database migrations
- ✅ Starts all 6 services

### 2. Configure Telegram Bot (Optional but Recommended)

**Create Telegram Bot:**
1. Open Telegram and search for `@BotFather`
2. Send `/newbot` and follow instructions
3. Copy your bot token (e.g., `123456789:ABCdefGHIjklMNOpqrsTUVwxyz`)
4. Start a chat with your bot and send any message
5. Get your chat ID: `https://api.telegram.org/bot<YOUR_BOT_TOKEN>/getUpdates`

**Update `.env`:**
```env
# Telegram Configuration
TELEGRAM_BOT_TOKEN=123456789:ABCdefGHIjklMNOpqrsTUVwxyz
TELEGRAM_CHAT_ID=your_chat_id

# Enable Telegram notifications
NOTIFIERS=telegram,console
```

**Restart services:**
```bash
docker compose restart laravel laravel-worker
```

### 3. Verify Installation
```bash
# Check all services are running
docker compose ps

# Check Laravel API
curl http://localhost:8000/health

# Check Python Trainer
curl http://localhost:8001/health

# Check database
docker compose exec laravel php artisan tinker --execute='echo "Candles: " . App\Models\Candle::count();'
```
---

## 💻 Complete Workflow

### Step 1: Fetch Historical Data
```bash
# Fetch data for specific symbol
docker compose exec laravel php artisan data:fetch BTCUSDT 1h --limit=1000

# Fetch for all configured symbols (recommended)
docker compose exec laravel php artisan data:fetch-all --limit=1000
```

### Step 2: Train ML Models
```bash
# Train model for specific symbol
docker compose exec laravel php artisan model:train BTCUSDT 1h --limit=1000 --epochs=100

# Train all models (recommended)
docker compose exec laravel php artisan model:train-all --limit=1000 --epochs=100
```

**Training takes ~15-30 seconds per model. Watch progress:**
```bash
docker compose logs -f python-trainer
```

### Step 3: Generate Trading Signals
```bash
# Generate prediction for specific symbol
docker compose exec laravel php artisan predict:generate BTCUSDT 1h

# Generate predictions for all trained models (recommended)
docker compose exec laravel php artisan predict:all
```

**You'll receive Telegram notifications like this:**

```
🟢 TRADING SIGNAL 🟢

🎯 Signal: BUY
💰 Symbol: BTCUSDT
⏰ Timeframe: 4h

📊 Price Analysis:
• Current: $67,543.21
• Predicted: $68,891.45
• Change: 📈 +2.00%

🎲 Confidence: 85% ██████████░░░░░░░░░

🤖 Model: lstm_BTCUSDT_4h_20251102_004756

💡 Suggestion:
✅ Strong Buy Signal - High probability of upward movement
• Consider entering a position
• Recommended stop-loss: $66,192.35 (-2%)

⚠️ This is an AI-generated signal. Always DYOR.
```

### Step 4: View Results
```bash
# Get latest predictions via API
curl http://localhost:8000/api/predictions/latest | jq .

# Get high-confidence trading signals only
curl "http://localhost:8000/api/predictions/signals?min_confidence=80" | jq .

# View specific symbol predictions
curl http://localhost:8000/api/predictions/BTCUSDT | jq .

# Check in database
docker compose exec laravel php artisan tinker --execute='
  App\Models\Prediction::whereIn("signal", ["BUY", "SELL"])
    ->where("confidence", ">=", 70)
    ->latest()
    ->take(5)
    ->get(["symbol", "signal", "confidence", "price_change_percent"]);
'
```

### Step 5: Download Trained Models
```bash
# List all models
curl http://localhost:8000/api/models | jq '.details[] | {model_version, symbol, interval, file_size_mb, metrics}'

# Get model info
curl http://localhost:8000/api/models/lstm_BTCUSDT_4h_20251102_004756/info | jq .

# Download model file
curl -O -J http://localhost:8000/api/models/lstm_BTCUSDT_4h_20251102_004756/download
```
---

## ⚡ Automation (Set It and Forget It!)

SignalStorm includes **Laravel Scheduler** for full automation. Enable it and get signals 24/7!

### What Runs Automatically:

| Task | Default Schedule | Configurable |
|------|-----------------|--------------|
| 📊 Fetch Data | Every hour | `FETCH_SCHEDULE` |
| 🤖 Train Models | Daily at 2 AM | `TRAINING_SCHEDULE` |
| 🎯 Generate Predictions | Every 4 hours | `PREDICTION_SCHEDULE` |
| 📱 Daily Summary | Daily at 8 AM | `DAILY_SUMMARY_TIME` |

### Enable Automation

**1. Update `.env`:**
```env
# Automation Settings
AUTO_FETCH_DATA=true
AUTO_TRAIN_MODEL=true
AUTO_PREDICT=true
DAILY_SUMMARY_ENABLED=true

# Schedules
FETCH_SCHEDULE=hourly                    # hourly, every30minutes, every15minutes
TRAINING_SCHEDULE=daily                  # daily, weekly, twiceDaily
TRAINING_SCHEDULE_TIME=02:00             # 24-hour format
PREDICTION_SCHEDULE=every4hours          # hourly, every4hours, every6hours
DAILY_SUMMARY_TIME=08:00                 # When to send daily summary

# Signal Thresholds
MIN_SIGNAL_CONFIDENCE=70                 # Only notify signals >= 70% confidence
INSTANT_NOTIFICATION_MIN_CONFIDENCE=80   # Instant alerts for >= 80% confidence
```

**2. The scheduler container is already running!**

Check scheduler logs:
```bash
docker compose logs -f laravel-scheduler
```

**3. Manual trigger (for testing):**
```bash
# Test daily summary
docker compose exec laravel php artisan signal:daily-summary

# Test prediction generation
docker compose exec laravel php artisan predict:all

# View scheduler tasks
docker compose exec laravel php artisan schedule:list
```

### Example Daily Summary Notification:

```
📊 Daily Trading Signals Summary
November 2, 2025

📈 Total Signals: 8
• Buy: 5
• Sell: 3

🔝 Top Signals:

1. 🟢 BTCUSDT (4h)
   Signal: BUY | Confidence: 95%
   Change: +3.45%

2. 🟢 ETHUSDT (1h)
   Signal: BUY | Confidence: 87%
   Change: +2.31%

3. 🔴 BNBUSDT (4h)
   Signal: SELL | Confidence: 82%
   Change: -1.89%
...
```

---

## 🏗️ Architecture

```
┌──────────────────────────────────────────────────────────────┐
│                     SIGNALSTORM PLATFORM                      │
└──────────────────────────────────────────────────────────────┘

┌─────────────────────┐         ┌──────────────────────────┐
│   Laravel API       │         │   Python ML Trainer      │
│   (Port 8000)       │◄───────►│   (Port 8001)            │
├─────────────────────┤  HTTP   ├──────────────────────────┤
│ • Data Providers    │         │ • LSTM Neural Networks   │
│ • Normalizer        │         │ • TensorFlow/Keras       │
│ • Queue Jobs        │         │ • Model Storage (HDF5)   │
│ • Trading Signals   │         │ • Prediction Engine      │
│ • Telegram Notifier │         └──────────────────────────┘
│ • API Routes        │
└─────────────────────┘
         │
         ├──────► PostgreSQL (5432) - Data storage
         ├──────► Redis (6379) - Queue & cache
         └──────► Telegram Bot API - Notifications

┌─────────────────────────────────────────────────────────────┐
│  Background Services                                         │
├─────────────────────────────────────────────────────────────┤
│  • Queue Worker   - Process jobs (training, predictions)    │
│  • Scheduler      - Run automated tasks                     │
│  • Migrator       - Database setup (runs once)              │
└─────────────────────────────────────────────────────────────┘
```

### Data Flow:

```
1. Binance API → Fetch Data → Store in PostgreSQL
                     ↓
2. Queue Job → Normalize Data → Python Trainer → Train LSTM Model
                     ↓
3. Saved Model → Prediction Request → ML Inference → Trading Signal
                     ↓
4. Signal Analysis → Telegram Notification → User receives alert!
```
---

## 📡 API Reference

### Health & Status

```bash
GET /health
```
Returns system health status

### Data Management

```bash
# Queue data fetch job
POST /api/data/fetch
Content-Type: application/json

{
  "symbol": "BTCUSDT",
  "interval": "1h",
  "limit": 100
}
```

```bash
# Get candles for symbol
GET /api/data/candles/{symbol}/{interval}
Example: /api/data/candles/BTCUSDT/1h
```

### Model Training

```bash
# Queue training job
POST /api/model/train
Content-Type: application/json

{
  "symbol": "BTCUSDT",
  "interval": "1h",
  "limit": 1000,
  "epochs": 100,
  "batch_size": 32
}
```

### Model Management

```bash
# List all trained models with metrics
GET /api/models

# Get specific model info
GET /api/models/{model_version}/info

# Download model file (.h5)
GET /api/models/{model_version}/download
```

**Example Response:**
```json
{
  "success": true,
  "count": 9,
  "details": [
    {
      "model_version": "lstm_BTCUSDT_4h_20251102_004756",
      "symbol": "BTCUSDT",
      "interval": "4h",
      "file_size_mb": 0.42,
      "metrics": {
        "train_loss": "0.00579369",
        "val_loss": "0.00489768"
      }
    }
  ]
}
```

### Predictions & Signals

```bash
# Generate new prediction
POST /api/predictions/generate
Content-Type: application/json

{
  "symbol": "BTCUSDT",
  "interval": "1h",
  "model_version": "lstm_BTCUSDT_1h_20251102_004743" // optional
}
```

```bash
# Get latest predictions
GET /api/predictions/latest?limit=10&symbol=BTCUSDT

# Get trading signals (BUY/SELL only, high confidence)
GET /api/predictions/signals?min_confidence=80

# Get predictions for specific symbol
GET /api/predictions/{symbol}
```

**Signal Response:**
```json
{
  "success": true,
  "count": 3,
  "data": [
    {
      "id": 1,
      "symbol": "BTCUSDT",
      "interval": "4h",
      "signal": "BUY",
      "confidence": 85.0,
      "current_price": "67543.21000000",
      "predicted_price": "68891.45000000",
      "price_change_percent": "2.0000",
      "prediction_time": "2025-11-02T12:00:00.000000Z",
      "target_time": "2025-11-02T16:00:00.000000Z",
      "model_version": "lstm_BTCUSDT_4h_20251102_004756"
    }
  ]
}
```

### Python Trainer Direct API

```bash
# Health check
GET http://localhost:8001/health

# Train model
POST http://localhost:8001/train
{
  "data": [...],
  "config": {"epochs": 100, "batch_size": 32}
}

# Generate prediction
POST http://localhost:8001/predict
{
  "features": [...],
  "model_version": "lstm_BTCUSDT_4h_20251102_004756"
}

# List models
GET http://localhost:8001/models

# Get model info
GET http://localhost:8001/models/{model_version}/info

# Download model
GET http://localhost:8001/models/{model_version}/download
```
---

## ⚙️ Configuration

All settings are in `.env` file:

### Core Services

```env
# Database
DB_CONNECTION=pgsql
DB_HOST=postgres
DB_PORT=5432
DB_DATABASE=signalstorm
DB_USERNAME=signalstorm
DB_PASSWORD=signalstormpassword

# Redis (Queue & Cache)
REDIS_HOST=redis
REDIS_PORT=6379

# Python Trainer
TRAINER_SERVICE_URL=http://python-trainer:8001
```

### Data Provider

```env
# Use real Binance data
DATA_PROVIDER=binance
BINANCE_API_KEY=your_api_key_here
BINANCE_API_SECRET=your_secret_here
BINANCE_BASE_URL=https://api.binance.com

# Or use mock data for testing
# DATA_PROVIDER=mock
```

### Telegram Notifications

```env
# Telegram Bot
TELEGRAM_BOT_TOKEN=123456789:ABCdefGHIjklMNOpqrsTUVwxyz
TELEGRAM_CHAT_ID=your_chat_id

# Enable Telegram
NOTIFIERS=telegram,console

# Notification Settings
INSTANT_SIGNAL_NOTIFICATIONS=true
INSTANT_NOTIFICATION_MIN_CONFIDENCE=80
DAILY_SUMMARY_ENABLED=true
DAILY_SUMMARY_TIME=08:00
```

### Trading Pairs

```env
# Enable/disable specific pairs
TRADING_BTCUSDT_ENABLED=true
TRADING_ETHUSDT_ENABLED=true
TRADING_BNBUSDT_ENABLED=false
```

### Automation

```env
# Auto-fetch data
AUTO_FETCH_DATA=true
FETCH_SCHEDULE=hourly
FETCH_LIMIT=100

# Auto-train models
AUTO_TRAIN_MODEL=true
TRAINING_SCHEDULE=daily
TRAINING_SCHEDULE_TIME=02:00
TRAINING_DATA_LIMIT=1000
TRAINING_EPOCHS=100
TRAINING_BATCH_SIZE=32

# Auto-generate predictions
AUTO_PREDICT=true
PREDICTION_SCHEDULE=every4hours
MIN_SIGNAL_CONFIDENCE=70
MIN_PRICE_CHANGE=0.5
```

### Apply Changes

After editing `.env`:
```bash
docker compose restart laravel laravel-worker laravel-scheduler
```
---

## 🔧 Commands Reference

### Makefile (Recommended)

```bash
make up              # Start all services
make down            # Stop all services
make restart         # Restart services
make logs            # View logs
make demo            # Run complete demo
make test            # Run tests
make clean           # Clean up
make help            # Show all commands
```

### Docker Compose

```bash
docker compose up -d                    # Start all services
docker compose down                     # Stop all services
docker compose ps                       # List services
docker compose logs -f                  # Follow logs
docker compose logs -f laravel          # Follow specific service
docker compose exec laravel bash        # Shell access
docker compose restart laravel          # Restart service
```

### Data Management

```bash
# Fetch data
docker compose exec laravel php artisan data:fetch BTCUSDT 1h --limit=1000
docker compose exec laravel php artisan data:fetch-all --limit=1000

# View candles
docker compose exec laravel php artisan tinker --execute='
  App\Models\Candle::forSymbol("BTCUSDT")
    ->forInterval("1h")
    ->orderBy("open_time", "desc")
    ->take(10)
    ->get(["open_time", "open", "high", "low", "close"]);
'
```

### Model Training

```bash
# Train single model
docker compose exec laravel php artisan model:train BTCUSDT 1h \
  --limit=1000 \
  --epochs=100 \
  --batch-size=32

# Train all models
docker compose exec laravel php artisan model:train-all \
  --limit=1000 \
  --epochs=100

# Watch training progress
docker compose logs -f python-trainer
docker compose logs -f laravel-worker
```

### Predictions & Signals

```bash
# Generate predictions
docker compose exec laravel php artisan predict:generate BTCUSDT 1h
docker compose exec laravel php artisan predict:all

# Send daily summary
docker compose exec laravel php artisan signal:daily-summary

# View predictions
docker compose exec laravel php artisan tinker --execute='
  App\Models\Prediction::whereIn("signal", ["BUY", "SELL"])
    ->where("confidence", ">=", 70)
    ->latest()
    ->take(10)
    ->get(["symbol", "signal", "confidence", "price_change_percent", "created_at"]);
'
```

### Automation

```bash
# List scheduled tasks
docker compose exec laravel php artisan schedule:list

# Test scheduler (run tasks now)
docker compose exec laravel php artisan schedule:run

# View scheduler logs
docker compose logs -f laravel-scheduler
```

### Database

```bash
# Fresh migration
docker compose exec laravel php artisan migrate:fresh

# Run migrations
docker compose exec laravel php artisan migrate

# Database stats
docker compose exec laravel php artisan tinker --execute='
  echo "Candles: " . App\Models\Candle::count() . "\n";
  echo "Models: " . App\Models\ModelMetric::distinct("model_version")->count() . "\n";
  echo "Predictions: " . App\Models\Prediction::count() . "\n";
'

# Interactive shell
docker compose exec laravel php artisan tinker
```

### Testing

```bash
# Run PHP tests
docker compose exec laravel php artisan test

# Run Python tests
docker compose exec python-trainer pytest

# Test Telegram notification
docker compose exec laravel php artisan tinker --execute='
  $notifier = app(App\Notifiers\TelegramNotifier::class);
  $notifier->send("Test message from SignalStorm! 🚀");
'
```

### Troubleshooting

#### Duplicate Predictions in Database

**Symptom**: Multiple predictions with same symbol+interval but different model versions

**Cause**: Multiple model versions exist for the same symbol+interval, and old versions were running predictions

**Solution**:
```bash
# 1. Clean up existing duplicates
docker compose exec laravel php artisan predict:cleanup-duplicates

# 2. Verify the fix - should now only use latest models
docker compose exec laravel php artisan predict:all

# 3. Check predictions (should see only one per symbol+interval)
docker compose exec laravel php artisan tinker --execute='
  \App\Models\Prediction::select("symbol", "interval", "model_version")
    ->where("created_at", ">=", now()->subHour())
    ->orderBy("symbol")
    ->orderBy("interval")
    ->get();
'
```

**Prevention**: The system now automatically:
- Uses only the latest model version per symbol+interval
- Deletes predictions created in the last 5 minutes before creating new ones
- Tracks sent notifications to prevent re-sending

#### Too Many Telegram Messages

**Symptom**: Getting 10+ Telegram messages every 15 minutes

**Solution**:
```bash
# 1. Setup batch notifications
docker compose exec laravel php artisan migrate

# 2. Mark old predictions as sent
docker compose exec laravel php artisan tinker --execute="\App\Models\Prediction::update(['notified_at' => now()]);"

# 3. Test batch sending
docker compose exec laravel php artisan signal:send-batch
```

Now you'll get only 2-3 clean messages every 15 minutes instead of spam!

#### Other Common Issues

```bash
# Clear caches
docker compose exec laravel php artisan cache:clear
docker compose exec laravel php artisan config:clear
docker compose exec laravel php artisan route:clear

# Check queue
docker compose exec laravel php artisan queue:failed
docker compose exec laravel php artisan queue:retry all

# View specific logs
docker compose logs --tail=100 laravel
docker compose logs --tail=100 python-trainer
docker compose logs --tail=100 laravel-worker

# Restart everything
docker compose down && docker compose up -d

# Full reset
make clean && make up
```
---

## ✨ Features

### 🤖 Machine Learning
- **LSTM Neural Networks** - Deep learning for time series prediction
- **Automatic Training** - Scheduled model retraining with fresh data
- **Model Versioning** - Track and compare different model versions
- **Metrics Tracking** - Loss, MAE, validation metrics stored in database
- **Model Downloads** - Export trained models (.h5 files) via API

### 📊 Trading Signals
- **BUY/SELL/HOLD Signals** - Clear actionable recommendations
- **Confidence Scoring** - 0-100% confidence for each prediction
- **Multi-Timeframe** - 1h, 4h, 1d intervals supported
- **Stop-Loss Suggestions** - Automatic risk management recommendations
- **Signal History** - All predictions stored for backtesting

### 📱 Telegram Integration
- **Instant Alerts** - Real-time notifications for strong signals
- **Beautiful Formatting** - Rich HTML messages with emojis
- **Daily Summaries** - Morning digest of top signals
- **Confidence Bars** - Visual confidence indicators
- **Trading Suggestions** - Position size and stop-loss recommendations

### 🔄 Full Automation
- **Scheduled Data Fetching** - Automatic market data updates
- **Scheduled Training** - Regular model retraining
- **Scheduled Predictions** - Continuous signal generation
- **Background Processing** - Non-blocking queue jobs
- **Laravel Scheduler** - Built-in cron-like task scheduling

### 📈 Data Management
- **Binance Integration** - Live market data from Binance API
- **PostgreSQL Storage** - Reliable data persistence
- **Data Normalization** - ML-ready data preprocessing
- **Historical Data** - Store and analyze thousands of candles
- **RESTful API** - Access all data via HTTP endpoints

### 🏗️ Architecture
- **SOLID Principles** - Clean, maintainable code
- **Strategy Pattern** - Easy to extend with new providers
- **Dependency Injection** - Testable and modular
- **Microservices** - Laravel orchestration + Python ML
- **Docker Ready** - One-command deployment
- **Queue Processing** - Redis-backed job queues

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
