# My MVC Project

Lightweight custom PHP 8.5+ MVC framework (PSR-4, PDO, zero runtime dependencies) with Vue 3 islands, jQuery, and Tailwind CSS v4.
Runs identically in **Docker** and **XAMPP**.

---

## New Laptop / Fresh Clone Setup (Git & Line Endings)

Before cloning on a new Windows/Mac/Linux machine, configure Git to preserve Unix (`LF`) line endings so `PHP_CodeSniffer` never flags `CRLF` (`\r\n`) errors:

```bash
git config --global core.autocrlf input
git clone <YOUR_REPO_URL> MVC_PHP
cd MVC_PHP
```

_(If any file on Windows ever gets saved with `\r\n`, convert it back with `dos2unix app/\*\*/_.php core/_.php` or run PHPCBF.)_

---

## Option A: Docker (Recommended)

### 1. Check for Port Conflicts (If Running Other Docker Projects)

If another project (such as `mykart`) is already running on your machine, check active ports first:

```bash
docker ps --format "table {{.Names}}\t{{.Ports}}"
```

If ports conflict, either stop the other project (`docker stop <container_name>`) or update host ports in `docker-compose.yml` / `.env`.

### 2. Start Containers, Install Dependencies & Seed Database

```bash
cp .env.example .env
docker compose up -d --build
docker compose exec -e COMPOSER_PROCESS_TIMEOUT=0 app composer install

# 1st: Run database creation & schema migrations
docker compose exec app php database/migrate.php

# 2nd: Run table data seeding
docker compose exec app php runseeder.php

# Optional phpMyAdmin:
docker compose --profile tools up -d
```

- **App:** http://localhost:8088
- **MySQL (from host):** `127.0.0.1:3309`
- **phpMyAdmin:** http://localhost:8084
  _(Host ports `8088`/`3309`/`8084` are used on purpose so they never clash with XAMPP's `80`/`3306`.)_

---

## Option B: XAMPP (PHP 8.6 + Apache + MySQL)

1. Place the project folder in `htdocs/my-mvc-project` and ensure `mod_rewrite` is enabled (default on).
2. Run `cp .env.example .env` and set `DB_HOST=127.0.0.1`, `DB_USERNAME=root`, and `DB_PASSWORD=` (blank by default).
3. Create the database `mvc_app` in phpMyAdmin (or let `migrate.php` create it automatically).
4. In the project folder, run:
   ```bash
   composer install
   php database/migrate.php
   php runseeder.php
   ```
5. Open http://localhost/my-mvc-project/ (the root `.htaccess` forwards to `/public` and the router strips the subfolder automatically).

---

## Frontend Build

Compiled assets live in `public/build` and are committed so XAMPP users need no Node.js installation.
To rebuild after editing files in `resources/`:

```bash
npm install
npm run build        # or: npm run dev (watch mode)
```

---

## Code Quality, PHP 8.5+ Standards & Security Gate (PHPCS / PHPCBF)

This codebase is hardened to **PHP 8.5+**, **PSR-12 / PER Coding Style 2.0+**, **Slevomat Coding Standard**, and **SonarQube** clean-code rules (`declare(strict_types=1);`, typed constants, constructor property promotion, early exits, allowlisted SQL identifiers, and zero `eval`/`extract`/`md5`/`var_dump`/`@` suppression).

### Run Quality Audit & Auto-Fix via Docker Tools Container

```bash
# Auto-fix formatting, fully-qualified global functions, and LF line endings
docker compose -f docker-compose.tools.yml run --rm tools vendor/bin/phpcbf

# Run full PHPCS audit (writes phpcs-report.txt)
docker compose -f docker-compose.tools.yml run --rm tools vendor/bin/phpcs --no-cache --colors --report=full --report-file=phpcs-report.txt
```

> **Engineering & AI Accountability Note:** Automated linters (`PHPCS`, `PHPCBF`, `SonarQube`) and AI coding tools accelerate refactoring and security checks, **but must be applied carefully without disrupting existing app functionality. Ultimately, the developer—not AI—holds full responsibility for architecture, business logic, and production security.**

---

## Architecture & Request Lifecycle

- **Entry Point:** `public/index.php` -> `Core\Application` (loads `.env` and `config/`, binds DI container, starts hardened session, registers `Core\ExceptionHandler`, and dispatches router).
- **Request Pipeline:** `Router` -> `SecurityHeaders` -> `CsrfMiddleware` -> `Controller` -> `Service` -> `Repository (PDO)` -> `Model/DTO`.
- **Directory Layout:**
  - `core/` — Framework engine (`Application`, `Router`, `Request`, `Response`, `View`, `ExceptionHandler`, `HttpException`, `Database`).
  - `app/Controllers/` — Thin HTTP controllers (`Request` -> `Service` -> `Response`).
  - `app/Services/` — Core business logic and `SqlHelper::transactional()` boundaries.
  - `app/Repositories/` — Interface-bound PDO data access (`UserRepositoryInterface` -> `UserRepository`).
  - `app/Exceptions/` — Typed HTTP & domain exceptions (`AppException`, `ValidationException`, `AuthenticationException`, `AuthorizationException`, `NotFoundException`, `ConflictException`, `DomainException`, `RuntimeException`, `SqlHelperConnectionException`).
  - `app/Helpers/` — Pure, stateless utilities (`ConstantHelper`, `ValidatorHelper`, `SqlHelper`, `DebugHelper`).
- **CSRF Protection:** `<meta name="csrf-token">` + jQuery sends `X-CSRF-TOKEN`; HTML forms use `<?= csrf_field() ?>`.
- **Vue 3 Islands:** Add `<div data-vue="Counter" data-props='{"start":1}'>` in any view and drop components into `resources/js/components/`.
- **Database Host Resolution:** Uses `DB_HOST` if set in `.env`, else `db` inside Docker, else `127.0.0.1`.
