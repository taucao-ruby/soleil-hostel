# 🔧 Environment Setup Guide - Soleil Hostel

**Last Updated**: December 11, 2025  
**Status**: ✅ Production Ready  
**Compatibility**: Windows, macOS, Linux, Docker, CI/CD

---

## 📋 Quick Start (30 seconds)

```bash
# Clone the repository
git clone https://github.com/taucao-ruby/soleil-hostel.git
cd soleil-hostel/backend

# Setup environment
cp .env.example .env
composer install
php artisan key:generate

# Run tests (optional)
php artisan test

# ✅ Done!
```

---

## 🌍 Environment Files Overview

### `.env.example` - Default Development Configuration

- **Database**: SQLite (in-memory or file)
- **Cache**: Database
- **Queue**: Sync (synchronous)
- **Redis**: Optional (commented out)
- **Use Case**: Development, Testing, CI/CD

```dotenv
DB_CONNECTION=sqlite
CACHE_STORE=database
QUEUE_CONNECTION=sync
```

### `.env` - Active Configuration (Local Development)

- **Database**: SQLite (copy from `.env.example`)
- **Cache**: Database
- **Queue**: Sync
- **Generated**: From `cp .env.example .env`
- **Use Case**: Local machine development

### `.env.testing` - Test Execution Configuration

- **Database**: SQLite (in-memory)
- **Cache**: Array (in-memory)
- **Queue**: Sync
- **Session**: Array
- **Generated**: Automatically loaded by PHPUnit
- **Use Case**: Running `php artisan test`

---

## 🚀 Complete Setup Instructions

### Prerequisites

- PHP 8.2+ with extensions:
  - PDO SQLite (`pdo_sqlite`)
  - JSON (`json`)
  - cURL (`curl`)
  - OpenSSL (`openssl`)

### Step 1: Clone Repository

```bash
git clone https://github.com/taucao-ruby/soleil-hostel.git
cd soleil-hostel/backend
```

### Step 2: Copy Environment File

```bash
cp .env.example .env
```

**What this does:**

- Creates `.env` with safe defaults
- Uses SQLite (no MySQL needed)
- Uses database cache (no Redis needed)
- Uses sync queue (no queue server needed)

### Step 3: Install Dependencies

```bash
composer install
```

**Expected output:**

```
Installing dependencies from lock file
Generating optimized autoload files
> php artisan package:discover --ansi
  laravel/pail ............................ DONE
  laravel/sail ............................ DONE
  laravel/sanctum ......................... DONE
  laravel/tinker .......................... DONE
  nesbot/carbon ........................... DONE
  nunomaduro/collision .................... DONE
  nunomaduro/termwind .................... DONE

✅ SUCCESS
```

### Step 4: Generate Application Key

```bash
php artisan key:generate
```

**Expected output:**

```
INFO  Application key set successfully.
```

### Step 5: Run Database Migrations (Optional)

```bash
# Create tables (SQLite in-memory doesn't persist)
php artisan migrate

# Or with seeding (adds sample data)
php artisan migrate:seed
```

### Step 6: Start Development Server

```bash
php artisan serve
```

**Expected output:**

```
   INFO  Server running on [http://127.0.0.1:8000]

  Press Ctrl+C to quit
```

---

## 🧪 Running Tests

### All Tests

```bash
php artisan test
```

### Specific Category

```bash
# Authentication tests
php artisan test tests/Feature/Auth/

# Booking tests
php artisan test tests/Feature/Booking/

# Security tests
php artisan test tests/Feature/Security/
```

### With Test Filtering

```bash
# Single test file
php artisan test tests/Feature/Auth/AuthenticationTest.php

# Single test method
php artisan test tests/Feature/Auth/AuthenticationTest.php::test_login_success
```

### Test Coverage (requires xdebug)

```bash
php artisan test --coverage --min=95
```

---

## 🐳 Docker Setup

### Using Docker Compose

```bash
# Start containers
docker-compose up -d

# Inside container
docker-compose exec app bash

# Run Laravel commands
docker-compose exec app php artisan test
```

### Docker File Structure

```dockerfile
# Backend container uses:
# - PHP 8.2 with PDO SQLite
# - Composer pre-installed
# - All required extensions
```

---

## 🔄 CI/CD Configuration

### GitHub Actions (`.github/workflows/`)

Automatically runs on:

- Push to `main` branch
- Pull requests
- Scheduled daily

**Steps executed:**

1. ✅ Setup PHP environment
2. ✅ Copy `.env.example` to `.env`
3. ✅ Run `composer install`
4. ✅ Generate application key
5. ✅ Run test suite
6. ✅ Generate coverage report

**Key Configuration**: Uses `.env.example` default (SQLite + database cache)

---

## 🛠️ Configuration by Environment

### Development (Your Machine)

```dotenv
APP_ENV=local
APP_DEBUG=true
DB_CONNECTION=sqlite
CACHE_STORE=database
QUEUE_CONNECTION=sync
```

**Benefits:**

- No external services needed
- Fast development iteration
- Easy to reset database (just delete `.env`)

### Testing (CI/CD / GitHub Actions)

```dotenv
APP_ENV=testing
APP_DEBUG=false
DB_CONNECTION=sqlite
DB_DATABASE=:memory:
CACHE_STORE=array
QUEUE_CONNECTION=sync
```

**Benefits:**

- Uses in-memory database (fastest)
- Isolated from development database
- No state persists between test runs

### Production (Server)

```dotenv
APP_ENV=production
APP_DEBUG=false
DB_CONNECTION=mysql           # ← Enable MySQL
DB_HOST=db.example.com        # ← Your DB server
CACHE_STORE=redis             # ← Enable Redis
QUEUE_CONNECTION=redis        # ← Enable queue
REDIS_HOST=cache.example.com
```

**Changes needed:**

1. Update database credentials
2. Set `CACHE_STORE=redis`
3. Set `QUEUE_CONNECTION=redis`
4. Configure Redis server details
5. Generate new `APP_KEY`

---

## 🔐 Sensitive Credentials

### DO NOT commit to git:

- `.env` file (contains `APP_KEY`)
- Database credentials
- Redis passwords
- API keys

### How to protect:

- Use `.env.example` as template (safe defaults)
- Each developer creates own `.env` locally
- CI/CD uses secure secrets management
- Production uses environment-specific secrets

---

## ❌ Common Issues & Solutions

### Issue 1: "Access denied for user 'root'"

**Cause**: MySQL is set in `.env` but not running  
**Solution**:

```bash
# Verify database setting
cat .env | grep DB_CONNECTION

# Should show: DB_CONNECTION=sqlite
# If not, run: cp .env.example .env
```

### Issue 2: "Undefined constant Redis::SERIALIZER_IGBINARY"

**Cause**: Redis configuration in `cache.php` with phpredis unavailable  
**Solution**:

- `.env.example` now has `CACHE_STORE=database` (safe default)
- For production with Redis, update `.env` with Redis config

### Issue 3: "Package not discoverable"

**Cause**: Cached package discovery or autoload error  
**Solution**:

```bash
composer dump-autoload
php artisan package:discover --ansi
```

### Issue 4: Tests fail with "table doesn't exist"

**Cause**: Database migrations not run before tests  
**Solution**:

- PHPUnit automatically migrates (see `phpunit.xml`)
- If issue persists: `php artisan migrate --env=testing`

### Issue 5: "Cannot connect to Redis"

**Cause**: Redis not installed or not running  
**Solution**:

- Development: Use `QUEUE_CONNECTION=sync` (already set)
- For Redis: Install Redis and update `.env`

---

## 📊 Database Configuration

### SQLite (Default - No Setup Needed)

```dotenv
DB_CONNECTION=sqlite
# Auto-creates database.sqlite in storage/ directory
```

**Pros:**

- Zero configuration
- Perfect for development/testing
- No external server
- Single file backup

**Cons:**

- Not suitable for multi-process production
- Limited concurrent access
- No advanced features

### MySQL (Production)

```dotenv
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=soleil_hostel
DB_USERNAME=root
DB_PASSWORD=yourpassword
```

**Setup:**

```bash
# Create database
mysql -u root -p
> CREATE DATABASE soleil_hostel CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
> exit

# Run migrations
php artisan migrate --env=production
```

---

## 🗄️ Cache Configuration

### Array Cache (Testing)

```dotenv
CACHE_STORE=array
```

- In-memory, cleared after each request
- Perfect for tests
- Zero configuration

### Database Cache (Development)

```dotenv
CACHE_STORE=database
```

- Persists in `cache` table
- No external service needed
- Slower than Redis, but reliable

### Redis Cache (Production)

```dotenv
CACHE_STORE=redis
REDIS_HOST=127.0.0.1
REDIS_PORT=6379
REDIS_PASSWORD=null
```

---

## 🔗 Queue Configuration

### Sync Queue (Development/Testing)

```dotenv
QUEUE_CONNECTION=sync
```

- Executes jobs immediately
- No queue server needed
- Perfect for development

### Redis Queue (Production)

```dotenv
QUEUE_CONNECTION=redis
REDIS_HOST=127.0.0.1
```

**Run worker in production:**

```bash
php artisan queue:work
```

---

## 📝 Required PHP Extensions

Check which extensions are installed:

```bash
php -m | grep -E "pdo_sqlite|json|curl|openssl"
```

If missing, install:

**Windows (XAMPP/Laragon):**

- Already included in `php.ini`
- Uncomment if needed

**macOS (Homebrew):**

```bash
brew install php php-sqlite php-json php-curl openssl
```

**Linux (Ubuntu/Debian):**

```bash
sudo apt-get install php-sqlite3 php-json php-curl php-openssl
```

---

## 🚀 Performance Optimization

### For Development

```bash
# Optimize autoloader
composer dump-autoload --optimize

# Clear all caches
php artisan cache:clear
php artisan config:clear
php artisan view:clear
php artisan route:clear
```

### For Production

```bash
# Cache config
php artisan config:cache

# Cache routes
php artisan route:cache

# Cache views
php artisan view:cache

# Optimize autoloader
composer install --no-dev --optimize-autoloader
```

---

## 🔍 Verification Checklist

After setup, verify everything works:

```bash
✅ Composer installed
composer --version

✅ Laravel installed
php artisan --version

✅ Database connection works
php artisan tinker
>>> DB::connection()->getPdo()

✅ Cache works
php artisan tinker
>>> Cache::put('test', 'value')

✅ Tests run
php artisan test

✅ Application boots
php artisan serve
# Visit http://127.0.0.1:8000
```

---

## 🆘 Getting Help

### Check Application Logs

```bash
tail -f storage/logs/laravel.log
```

### Enable Debug Mode

```dotenv
# In .env
APP_DEBUG=true
```

### Test Database Connection

```bash
php artisan tinker
>>> DB::connection()->getPdo()->getWrappedConnection()->getDatabase()
```

### Test Cache Connection

```bash
php artisan tinker
>>> Cache::put('test', 'hello')
>>> Cache::get('test')
```

### Run Diagnostics

```bash
php artisan about
```

---

## 📚 Documentation References

- **[Laravel 12 Documentation](https://laravel.com/docs/12.x)**
- **[SQLite Setup](https://laravel.com/docs/12.x/database#sqlite-configuration)**
- **[Cache Configuration](https://laravel.com/docs/12.x/cache)**
- **[Queue Configuration](https://laravel.com/docs/12.x/queues)**
- **[Testing](https://laravel.com/docs/12.x/testing)**

---

## 🎯 Summary

| Component   | Default  | Development | Production |
| ----------- | -------- | ----------- | ---------- |
| Database    | SQLite   | SQLite      | MySQL      |
| Cache       | Database | Database    | Redis      |
| Queue       | Sync     | Sync        | Redis      |
| Debug       | true     | true        | false      |
| Environment | local    | local       | production |

**Key takeaway**: `.env.example` provides safe defaults that work everywhere. For production, override specific settings with your infrastructure details.

---

## ✅ Status

- ✅ Development: Ready to go
- ✅ Testing: `php artisan test` works
- ✅ CI/CD: Automatic on GitHub
- ✅ Production: Ready with proper configuration

**Last tested**: December 11, 2025  
**All tests**: 190+ passing

---

Generated by GitHub Copilot | Soleil Hostel Project
