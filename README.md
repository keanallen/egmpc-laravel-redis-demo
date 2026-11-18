# Attendance Lab

Standalone Laravel 12 / Blade demo for teaching MySQL reports, Redis page caching, stale data and targeted invalidation. The default path uses MySQL; **all report cache reads, writes and invalidation calls ship commented out**. Redis can be stopped. No JavaScript build is required.

## Run with Docker Compose

From `eg-demo`, create `.env` from `.env.example` if it does not already exist, then build the PHP image:

```sh
cp .env.example .env
docker compose build php
docker compose run --rm --no-deps php php artisan key:generate --show
```

Copy the generated `base64:...` value into `APP_KEY` in your local `.env`. Using `--show` lets you edit the host file without requiring the container user to own it.

```sh
docker compose up -d nginx
docker compose exec php php artisan migrate --seed
```

Open http://localhost:1234. Compose starts MySQL and waits for it to be healthy before starting PHP. It supplies the container database credentials (`mysql:3306`, database `mydb`) and Redis address (`redis:6379`); host ports `3333` and `6380` are for clients outside Docker. Redis is not required or started by the command above. For the caching lesson, start it separately with `docker compose up -d redis`, then uncomment the controller blocks as described below.

Composer dependencies, Laravel storage, and bootstrap cache use named volumes. PHP's source bind mount is writable so Docker can create the nested volume mountpoints, including `vendor` on a fresh checkout. Nginx keeps its read-only source mount. Run Artisan as the configured `www-data` user (the default). The dependency volume is initialized from the built image on its first creation.

Run the tests in an isolated SQLite database, overriding the container's normal database settings explicitly:

```sh
docker compose exec -e DB_CONNECTION=sqlite -e DB_DATABASE=:memory: php php artisan test --cache-directory=/tmp/phpunit-cache
```

For Nginx configuration validation after startup, run `docker compose exec nginx nginx -t`. Stop services with `docker compose down`; named volumes persist.

## Setup

Requires PHP 8.2+, Composer, MySQL 8+, and Laravel's usual PHP extensions (including PDO MySQL, mbstring, XML and DOM). Use PHP's PDO SQLite extension for the default test suite. Predis is installed by Composer for the optional Redis lesson; no PHP Redis extension is needed.

From this directory:

```sh
composer install
cp .env.example .env
php artisan key:generate
```

Create a **new, dedicated database** with a MySQL administrator account:

```sql
CREATE DATABASE attendance_demo CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'attendance_demo'@'localhost' IDENTIFIED BY 'demo_password';
GRANT ALL PRIVILEGES ON attendance_demo.* TO 'attendance_demo'@'localhost';
```

Match `.env` to your local MySQL credentials and host, then:

```sh
php artisan migrate --seed
php artisan serve
```

Open http://127.0.0.1:8000. The initial selection is Branch 1 / September 2025. `/employees` opens the employee list. This is a local training app with synthetic data and no authentication.

The seed creates exactly six branches, 500 employees each and 20 weekdays (September 1–26, 2025): **3,000 employees / 60,000 records**. Each employee starts with 16 present, 3 late and 1 absent days, rotated deterministically across dates. For example, B01-0001 starts late on September 1. `php artisan db:seed` can be rerun without duplicates; it resets seeded statuses. Run it before the lesson, while caching is disabled. It does not clear report caches.

Verify counts in MySQL:

```sql
SELECT COUNT(*) FROM branches;            -- 6
SELECT COUNT(*) FROM employees;           -- 3000
SELECT COUNT(*) FROM attendance_records;  -- 60000
SELECT branch_id, COUNT(*) FROM employees GROUP BY branch_id; -- 500 each
SELECT COUNT(DISTINCT attendance_date) FROM attendance_records; -- 20
```

## Redis setup (separate live lesson)

Install/start a local Redis instance, or use a dedicated container:

```sh
docker run --rm --name attendance-demo-redis -p 127.0.0.1:6379:6379 redis:7-alpine
```

The supplied environment uses `REDIS_CLIENT=predis`, host `127.0.0.1`, port `6379`, Redis database `1`. Adjust these if needed, then run `php artisan config:clear`. Leave `CACHE_STORE=file` and `SESSION_DRIVER=file`: the lesson explicitly calls `Cache::store('redis')`.

Two controller edits enable the lesson. Do not change the report query:

1. **ENABLE CACHE** — `app/Http/Controllers/DashboardController.php`: comment out exactly the three executable lines between `MYSQL ONLY START` and `MYSQL ONLY END` (`$data`, `$source`, `$reportQueriesRan`). Remove the **entire** `/* ENABLE CACHE START` and `ENABLE CACHE END */` boundary lines, leaving all code between them active. Keep both `hrtime(true)` calls in place.
2. **INVALIDATE CACHE** — `app/Http/Controllers/EmployeeController.php`: after the stale-data demonstration, remove the **entire** `/* INVALIDATE CACHE START` and `INVALIDATE CACHE END */` boundary lines, leaving the enclosed three statements active after the save.

Cache entries contain only arrays of rows and the total employee count. Keys include branch, month, page and page size; TTL is 300 seconds. Each branch/month has one composite tag shared by every page and page size. A successful attendance save clears that tag using the employee's real branch and the record's real month. It preserves other branches and months. Pagination links are built from the current request, outside the cache.

The query builder's grouped `paginate()` executes a count query and a limited page query. The on-page query indicator describes whether this report-loading path ran; it excludes branch lookup, filter validation and rendering. Data load time surrounds only report loading, before Blade renders. A late day counts only as late; missing records produce zeros. There are no artificial delays.

## Live walkthrough

1. Open Branch 1, September 2025, page 1 (`/?branch_id=1&month=2025-09&page=1`). Refresh. Both requests show **MySQL** and **Attendance report queries ran: Yes**.
2. Uncomment **ENABLE CACHE** as directed. Load page 1 to fill the cache; refresh and observe **Redis**, with report queries **No**.
3. Open page 2 for the first time: it loads from MySQL. Return to page 1: it hits Redis. Each page has its own key because each contains different employees.
4. Keep **INVALIDATE CACHE** commented. From page 1, open B01-0001 and change September 1 from Late to Present. Save, then reopen the **same branch/month/page** before five minutes elapse. Redis still shows the old counts (16 present / 3 late / 1 absent).
5. Uncomment **INVALIDATE CACHE**, then change that record from Present to Absent. Reopen that same report page: MySQL recalculates (16 present / 2 late / 2 absent), and the next refresh uses Redis. Every other cached page for Branch 1 / September 2025 also misses next time. Cached pages for other branches and months stay available.

Compare timings for the same branch, month and page. MySQL may already be fast; skipping the two report queries is the reliable observation. If restarting a lesson within five minutes, clear only the target report tag via Tinker if installed, or wait for expiry; an old cache can otherwise make the first fill look like a hit.

To return to delivery mode, restore both block comments and reactivate the three MySQL-only lines. Redis may then be stopped again.

## Verification

```sh
php artisan test
```

The feature suite uses an in-memory SQLite test database. It checks exact seed counts and reruns, weekdays, aggregation and date boundaries, missing records, two-query loading, both paginated lists and filter persistence, valid updates and changed totals, invalid statuses, mismatched employee URLs, and unique constraints. The Redis lesson test skips during the default suite.

Also run against **a separate empty MySQL test database**, created with the same SQL pattern as above (never the classroom database):

```sh
DB_CONNECTION=mysql DB_DATABASE=attendance_demo_test php artisan test
```

With Redis running, verify the **actual commented controller blocks** in both lesson stages:

```sh
python3 scripts/verify_redis_lesson.py
# Optional: PHP_BINARY=/path/to/php python3 scripts/verify_redis_lesson.py
# Optional MySQL test run:
DB_CONNECTION=mysql DB_DATABASE=attendance_demo_test python3 scripts/verify_redis_lesson.py
```

The script temporarily enables caching, tests the stale-data stage, then enables invalidation and tests page-wide invalidation plus isolation of other branches/months. Tests use a unique cache prefix. Original controller bytes are restored in `finally`, including after test failures. Stop the dev server while running this source-editing verification. Run it with the delivery comments intact.

### Verification in the delivery environment

Performed: source/config review, interface static detector (no findings), JSON/XML parsing, seed distribution and report SQL semantics checked independently using Python/SQLite, and verification of the Redis script's transformations/restoration with a test process.

**Not run:** Composer install, PHP lint, Laravel feature tests, MySQL migrations/seeding, rendered Blade/browser checks, and live Redis hits/invalidation. PHP and Composer are absent, Docker socket access is denied, Windows interop fails, and shell downloads cannot resolve DNS. Redis is installed, but it cannot exercise Laravel without PHP. These are verification limits, not reported passing tests. No live report/cache calls were enabled in the delivered source.

References: [Laravel cache tags](https://laravel.com/docs/12.x/cache#cache-tags), [Laravel pagination](https://laravel.com/docs/12.x/pagination).
