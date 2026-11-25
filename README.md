# MVC_PHP — Custom PHP MVC Framework

A custom, dependency-light PHP MVC application framework with a hardened container runtime. It separates a **public frontend** from a **secure backend (admin)** context while keeping business logic and data access shared in one layer.

---

## Table of Contents

1. [Overview](#1-overview)
2. [Technology Stack](#2-technology-stack)
3. [Architecture](#3-architecture)
4. [Environments and Docker](#4-environments-and-docker)
5. [Getting Started](#5-getting-started)
6. [Configuration](#6-configuration)
7. [Operations Playbook](#7-operations-playbook)
8. [Security Hardening](#8-security-hardening)
9. [Quality and CI](#9-quality-and-ci)
10. [PHP Version and Upgrade Policy](#10-php-version-and-upgrade-policy)
11. [Release and Rollback](#11-release-and-rollback)
12. [Troubleshooting](#12-troubleshooting)

---

## 1. Overview

**Design goals**

- Strict separation of Frontend and Backend controllers and views, with shared Services, Repositories and Models.
- A framework core (`core/`) with no external runtime dependencies.
- Reproducible, containerised builds: one multi-stage Dockerfile produces a hardened production image.
- Security by default: anti-webshell PHP hardening, read-only application code, non-root runtime, CSRF protection and security headers.
- The same project folder also runs under XAMPP (root `.htaccess` redirects to `public/`).

**Reference domain:** an e-commerce storefront. The schema covers users, categories, products, orders and order details ([section 3.5](#35-database-migrations)).

---

## 2. Technology Stack

| Layer          | Technology                                                                      | Notes                                               |
| :------------- | :------------------------------------------------------------------------------ | :-------------------------------------------------- |
| Language       | PHP 8.6 (pinned `8.6.0`)                                                        | `declare(strict_types=1)` throughout                |
| Web server     | Apache 2.4 (official `php:8.6.0-apache` image)                                  | Non-root, listens on **8080** in the container      |
| Database       | MySQL 8.4 via PDO                                                               | Singleton factory in `core/Database.php`            |
| Dependencies   | Composer 2, PSR-4 autoloading                                                   | Composer is **not** shipped in the production image |
| Frontend build | Vite, PostCSS / Tailwind CSS, Vue single-file components as islands             | Output in `public/build/`                           |
| Containers     | Docker, Docker Compose **2.24+**                                                | The dev override uses the `!override` tag           |
| Quality        | PHP_CodeSniffer (`phpcs.xml`), SonarQube (`sonar-project.properties`)           |                                                     |
| CI             | GitHub Actions (`.github/workflows/ci.yml`)                                     |                                                     |
| Security       | `security_php2shell.ini`, Apache hardening, CSRF and security-header middleware | See [section 8](#8-security-hardening)              |

---

## 3. Architecture

### 3.1 Request lifecycle

```text
Browser -> Apache (public/ document root, .htaccess rewrite)
        -> public/index.php            (front controller / bootstrap)
        -> Core\Application            (micro DI container, config load)
        -> Core\Router + routes/web.php
        -> Middleware                  (SecurityHeaders, CsrfMiddleware)
        -> Controller                  (Frontend or Backend context)
        -> Service -> Repository       (business logic -> data access, PDO)
        -> View                        (escaped output via e())
        -> Core\Response
```

### 3.2 Layer responsibilities

| Layer           | Path                            | Responsibility                                                                                                                                                      |
| :-------------- | :------------------------------ | :------------------------------------------------------------------------------------------------------------------------------------------------------------------ |
| Core engine     | `core/`                         | Container, Router, Request/Response, Session, CSRF, View, Database, Migrator, Env, exception handling                                                               |
| Context modules | `app/Frontend/`, `app/Backend/` | Each context owns its `Controllers/`, `Models/` and `Views/` (`home/`, `layouts/`). Strict boundary between the public and admin contexts                           |
| Middleware      | `app/Middleware/`               | Cross-cutting request/response interceptors (`CsrfMiddleware`, `SecurityHeaders`)                                                                                   |
| Services        | `app/Services/`                 | Shared business logic (`UserService`)                                                                                                                               |
| Repositories    | `app/Repositories/`             | Data-access abstraction; interfaces in `Contracts/`                                                                                                                 |
| Shared models   | `app/Models/`                   | Models used by both contexts (`User`)                                                                                                                               |
| Helpers         | `app/Helpers/`                  | Static utilities: `ArrayHelper`, `StringHelper`, `ValidatorHelper`, `SqlHelper`, `ConstantHelper`, `DebugHelper`                                                    |
| Exceptions      | `app/Exceptions/`               | Typed application exceptions: `AppException` plus Authentication, Authorization, Conflict, Domain, NotFound, Runtime, SqlHelperConnection and Validation exceptions |
| Support         | `app/Support/`                  | `DatabaseSeeder`, `Validator`                                                                                                                                       |
| Database        | `database/`                     | `migrate.php` runner and numbered SQL migrations                                                                                                                    |
| Config          | `config/`                       | Immutable configuration manifests (`app.php`, `database.php`)                                                                                                       |

### 3.3 Project structure

```text
MVC_PHP/
├── .github/workflows/ci.yml        # CI pipeline
├── app/
│   ├── Backend/                    # Secure admin context
│   │   ├── Controllers/            # HomeController.php
│   │   ├── Models/
│   │   └── Views/                  # home/, layouts/
│   ├── Frontend/                   # Public context
│   │   ├── Controllers/            # HomeController.php
│   │   ├── Models/
│   │   └── Views/                  # home/, layouts/
│   ├── Exceptions/                 # AppException + typed subclasses
│   ├── Helpers/                    # Array, Constant, Debug, Sql, String, Validator helpers
│   ├── Middleware/                 # CsrfMiddleware, SecurityHeaders
│   ├── Models/                     # Shared models (User)
│   ├── Repositories/               # UserRepository + Contracts/ (interfaces)
│   ├── Services/                   # UserService
│   └── Support/                    # DatabaseSeeder, Validator
├── config/                         # app.php, database.php
├── core/                           # Framework engine (no external dependencies)
├── database/
│   ├── migrate.php                 # Migration runner
│   └── migrations/                 # 000_create_db ... 005_create_order_detail (SQL)
├── docker/
│   ├── apache/
│   │   ├── vhost.conf              # VirtualHost (DocumentRoot = public/)
│   │   ├── security.conf           # Apache security directives (see note in 4.3)
│   │   └── custom_dev.ini          # DEV ONLY: relaxed PHP settings (mounted by the override)
│   ├── php/
│   │   ├── Dockerfile              # Multi-stage build: base -> vendor -> production
│   │   ├── opcache.ini             # OPcache tuning
│   │   └── security_php2shell.ini  # Anti-webshell hardening (production stage only)
│   └── tools/
│       └── Dockerfile              # Image for the developer tooling container
├── public/                         # Only web-accessible directory
│   ├── .htaccess
│   ├── index.php                   # Front controller
│   └── build/                      # Compiled assets
├── resources/
│   ├── css/app.css
│   └── js/                         # app.js, bridge.js, components/Counter.vue
├── routes/web.php                  # Route registrar
├── storage/logs/                   # app.log, php-error.log - the only writable path
├── .dockerignore
├── .env.example
├── .gitignore
├── .htaccess                       # Root redirect to public/ (XAMPP compatibility)
├── apply-and-verify.sh             # Rebuild + hardening verification script
├── composer.json / composer.lock
├── docker-compose.yml              # Hardened, production-like base
├── docker-compose.override.yml     # Development overrides (auto-loaded)
├── docker-compose.tools.yml        # Developer tooling container (Composer, QA tools)
├── package.json
├── phpcs.xml
├── sonar-project.properties
├── runseeder.php                   # Seeder runner (CLI, development data)
└── vite.config.js
```

> Local artefacts such as `build.log`, `phpinfo.txt`, `phpcs-report.txt`, `project_structure*.txt`, `MVC_INFO.txt` and `github_readme.md` are not part of the application. Keep them out of the Docker image (`.dockerignore`), and do not commit `phpinfo.txt`: it dumps server configuration.

### 3.4 PSR-4 namespaces

| File path                                                | Namespace                                   |
| :------------------------------------------------------- | :------------------------------------------ |
| `app/Backend/Controllers/HomeController.php`             | `App\Backend\Controllers`                   |
| `app/Frontend/Controllers/HomeController.php`            | `App\Frontend\Controllers`                  |
| `app/Backend/Models/*`, `app/Frontend/Models/*`          | `App\Backend\Models`, `App\Frontend\Models` |
| `app/Exceptions/*`                                       | `App\Exceptions`                            |
| `app/Helpers/*`                                          | `App\Helpers`                               |
| `app/Middleware/CsrfMiddleware.php`                      | `App\Middleware`                            |
| `app/Models/User.php`                                    | `App\Models`                                |
| `app/Repositories/UserRepository.php`                    | `App\Repositories`                          |
| `app/Repositories/Contracts/UserRepositoryInterface.php` | `App\Repositories\Contracts`                |
| `app/Services/UserService.php`                           | `App\Services`                              |
| `app/Support/*`                                          | `App\Support`                               |
| `core/Application.php`                                   | `Core`                                      |

### 3.5 Database migrations

Run with `php database/migrate.php`. Migrations are plain numbered SQL files applied in order:

| File                              | Purpose              |
| :-------------------------------- | :------------------- |
| `000_create_db.sql`               | Creates the database |
| `001_create_user_master.sql`      | Users                |
| `002_create_categorie_master.sql` | Product categories   |
| `003_create_product_master.sql`   | Products             |
| `004_create_order_master.sql`     | Orders               |
| `005_create_order_detail.sql`     | Order lines          |

Seeding (`app/Support/DatabaseSeeder.php`, started through `runseeder.php`) is for development data only. Never run it against a production database.

---

## 4. Environments and Docker

### 4.1 Two modes

|                  | Hardened (production-like)                           | Development                                                      |
| :--------------- | :--------------------------------------------------- | :--------------------------------------------------------------- |
| Compose files    | `docker-compose.yml` only                            | `docker-compose.yml` + `docker-compose.override.yml` (automatic) |
| Application code | Baked into the image, **root-owned, read-only**      | Bind-mounted from the host (**writable**)                        |
| Writable path    | `storage/` (named volume) only                       | Whole project folder                                             |
| PHP settings     | `security_php2shell.ini` in effect                   | `custom_dev.ini` loads **last** and can relax settings           |
| MySQL port       | Not published                                        | `127.0.0.1:3309`                                                 |
| phpMyAdmin       | Not deployed                                         | `127.0.0.1:8084`                                                 |
| Command          | `docker compose -f docker-compose.yml up -d --build` | `docker compose up -d --build`                                   |

> The development mode deliberately weakens the hardening. Never use it to validate or ship a release; use the hardened mode and `apply-and-verify.sh`.

### 4.2 Compose files

| File                          | Purpose                                                                                                                           |
| :---------------------------- | :-------------------------------------------------------------------------------------------------------------------------------- |
| `docker-compose.yml`          | Hardened base: `app` (non-root, `cap_drop: ALL`, `no-new-privileges`, port `127.0.0.1:${APP_PORT}:8080`) and `db` (internal only) |
| `docker-compose.override.yml` | Dev only: bind mount, `custom_dev.ini`, MySQL host port, phpMyAdmin                                                               |
| `docker-compose.tools.yml`    | Separate `mvc_tools` project: Composer and QA tools. Its `vendor/` lives in a named volume and never touches the host or the app  |

### 4.3 Dockerfile stages (`docker/php/Dockerfile`)

| Stage        | Purpose                                                                                                                                                      |
| :----------- | :----------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `base`       | Official `php:<version>-apache` image, extensions, Apache hardening, OPcache and vhost config. **Does not** include the hardening ini                        |
| `vendor`     | Composer, git and unzip live here only. Runs `composer install --no-dev` and `dump-autoload --classmap-authoritative`                                        |
| `production` | Adds `security_php2shell.ini`, copies code from `vendor`, root-owned code, writable `storage/`, runs as `www-data` on port 8080, `HEALTHCHECK` on `/healthz` |

The hardening ini must stay in the `production` stage only: `open_basedir` and `disable_functions` would otherwise break Composer in the `vendor` stage.

Compose builds only `docker/php/Dockerfile`. A legacy `Dockerfile` in the project root is unused (it references files that no longer exist) and should be deleted. `docker/apache/security.conf` is not referenced by the active Dockerfile, which writes equivalent directives inline; keep a single source of truth (either copy this file in, or remove it).

### 4.4 Local endpoints

| URL / address            | Service                             | Purpose                                              |
| :----------------------- | :---------------------------------- | :--------------------------------------------------- |
| `http://localhost:8088/` | `app` (Apache, container port 8080) | Application. Port set by `APP_PORT` (default `8088`) |
| `http://localhost:8084/` | `phpmyadmin` (dev only)             | Database GUI                                         |
| `127.0.0.1:3309`         | `db` (dev only)                     | External DB clients (TablePlus, DBeaver)             |

---

## 5. Getting Started

**Prerequisites:** Docker Desktop with Compose 2.24 or newer, Git, Composer on the host (the dev container reads the host's `vendor/`), Git Bash or a similar shell.

```bash
# 1. Environment file (required: DB_ROOT_PASSWORD)
cp .env.example .env            # then edit the values

# 2. Dependencies on the host (development mode reads ./vendor)
composer install

# 3. Start the development stack
docker compose up -d --build

# 4. Create the schema
docker compose exec app php database/migrate.php
```

Open `http://localhost:8088/`.

---

## 6. Configuration

Settings come from environment variables. **The real environment (Docker) wins over the `.env` file (XAMPP).**

| Variable           | Default            | Description                                                                                                         |
| :----------------- | :----------------- | :------------------------------------------------------------------------------------------------------------------ |
| `DB_ROOT_PASSWORD` | none, **required** | MySQL root password. Compose refuses to start without it. MySQL only reads it when the data volume is first created |
| `DB_DATABASE`      | `mvc_app`          | Database name                                                                                                       |
| `DB_USERNAME`      | `root`             | Application DB user. Use a dedicated least-privilege user for any real deployment                                   |
| `APP_PORT`         | `8088`             | Host port mapped to Apache's 8080                                                                                   |
| `DB_FORWARD_PORT`  | `3309`             | Host port for MySQL (dev override only)                                                                             |

`DB_HOST` and `DB_PORT` are set by Compose (`db` and `3306`).

**Coding convention:** `Core\Env::get(string $key, ?string $default)` is strictly typed. Pass defaults as **strings**: `Env::get('DB_PORT', '3306')`, never `3306`.

Never commit `.env`. It is excluded from the Docker build context.

---

## 7. Operations Playbook

| Task                                   | Command                                                                                  |
| :------------------------------------- | :--------------------------------------------------------------------------------------- |
| Start (dev)                            | `docker compose up -d --build`                                                           |
| Stop                                   | `docker compose down`                                                                    |
| Follow logs                            | `docker compose logs -f app`                                                             |
| PHP error log                          | `docker compose exec app tail -n 50 /var/www/html/storage/logs/php-error.log`            |
| Shell in the app container             | `docker compose exec app bash`                                                           |
| Run migrations                         | `docker compose exec app php database/migrate.php`                                       |
| Seed development data                  | `docker compose exec app php runseeder.php` (never in production)                        |
| Refresh the autoloader                 | `composer dump-autoload -o` (on the host)                                                |
| Database shell                         | `docker compose exec db sh -c 'mysql -uroot -p"$MYSQL_ROOT_PASSWORD" "$MYSQL_DATABASE"'` |
| Run a tool in the tooling container    | `docker compose -f docker-compose.tools.yml run --rm tools <command>`                    |
| Verify the hardened build              | `bash apply-and-verify.sh` (add `QUICK=1` to reuse the build cache)                      |
| Return to dev after verifying          | `docker compose up -d --force-recreate`                                                  |
| Full reset (**deletes database data**) | `docker compose down --volumes && docker compose up -d --build`                          |

The production image has no Composer and its code is read-only, so `composer install` inside the app container is not available.

---

## 8. Security Hardening

### 8.1 Defence in depth

| Layer       | Controls                                                                                                                                                                                                                               |
| :---------- | :------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| PHP runtime | `docker/php/security_php2shell.ini` (details below)                                                                                                                                                                                    |
| Filesystem  | Code is root-owned and read-only; only `storage/` is writable by `www-data`; `/var/www/html` itself is root-owned so the runtime user cannot replace code                                                                              |
| Container   | Non-root user, port 8080, `cap_drop: ALL`, `no-new-privileges`, no Composer or git in the final image, no DB port published                                                                                                            |
| Apache      | `ServerTokens Prod`, `ServerSignature Off`, `TraceEnable Off`, `autoindex` disabled, DocumentRoot `public/` only                                                                                                                       |
| Application | `CsrfMiddleware`, `SecurityHeaders` middleware, output escaping with `e()`, typed exceptions (`app/Exceptions`). Rules: all SQL through PDO prepared statements (including `SqlHelper`); no debug output (`DebugHelper`) in production |
| Secrets     | `.env` excluded from images and git; passwords come from the environment                                                                                                                                                               |

### 8.2 What the PHP ini enforces

- **Information disclosure:** `expose_php`, `display_errors` and `display_startup_errors` off; errors go to `storage/logs/php-error.log`; stack-trace arguments hidden.
- **Remote inclusion:** `allow_url_fopen`, `allow_url_include` and `enable_dl` off.
- **Command execution blocked (`disable_functions`):** `exec`, `system`, `passthru`, `shell_exec` (also blocks the backtick operator), `proc_open`, `popen`, `pcntl_exec`, plus related `proc_*` and `posix_*` functions, `symlink`, `link`, `putenv`, `phpinfo`, `highlight_file`. `eval` is a language construct and cannot be disabled this way.
- **Filesystem:** `open_basedir = /var/www/html:/tmp:/usr/local/lib/php:/.dockerenv` (a defence-in-depth control, not a complete sandbox).
- **Sessions:** strict mode, cookies only, `HttpOnly`, `Secure`, `SameSite=Strict`, upload progress off. Secure cookies need HTTPS; most browsers treat `localhost` as secure. Use `Lax` instead of `Strict` if you add SSO or external-link logins.
- **Resource limits:** execution time, input time and variables, request and upload sizes.

Ini files are not backslash-continuable: keep `disable_functions` on a single line.

### 8.3 Verifying the hardening

```bash
bash apply-and-verify.sh
```

The script uses only `docker-compose.yml`, checks the static configuration (hardening ini in the `production` stage, no bind mounts, port 8080), rebuilds, waits for the health check and prints `[PASS]`/`[FAIL]` for: disabled functions, `open_basedir`, `allow_url_fopen`, `expose_php`, `display_errors`, non-root user, read-only code, writable `storage/logs`, no `X-Powered-By`, no Apache version leak, and host port reachability. On failure it prints diagnostics.

### 8.4 Release security checklist

- [ ] `bash apply-and-verify.sh` reports 0 failed
- [ ] `composer audit` is clean
- [ ] Image scan (for example `trivy image`) and Dockerfile lint (`hadolint docker/php/Dockerfile`) pass
- [ ] `docker compose -f docker-compose.yml config` shows no bind mounts and no published DB port
- [ ] No secrets or dev files in the image (`.env`, `*.log`, `phpinfo.txt`, backups)
- [ ] phpMyAdmin and the tooling container are not deployed
- [ ] DB user is least-privilege (not `root`)
- [ ] No debug helpers or verbose errors reachable in production; all SQL is parameterised
- [ ] CLI scripts (`runseeder.php`, `database/migrate.php`) are not web-reachable under any server setup, including XAMPP
- [ ] HTTPS and HSTS are enforced at the edge (load balancer or reverse proxy)

---

## 9. Quality and CI

- **Standards:** PSR-4, PSR-12 via `phpcs.xml`, `declare(strict_types=1)` in every file.
- **Static analysis and review:** SonarQube (`sonar-project.properties`); run tools in the tooling container (`docker-compose.tools.yml`).
- **CI (`.github/workflows/ci.yml`):** recommended gates are lint, `phpcs`, tests, `composer audit`, image build, image scan and `apply-and-verify.sh`.

---

## 10. PHP Version and Upgrade Policy

**Current:** PHP 8.6.x, pinned in `docker/php/Dockerfile` as `ARG PHP_VERSION` (`8.6.0`). PHP 8.6.0 was released on 19 Nov 2026.

Per [php.net/supported-versions](https://www.php.net/supported-versions.php), each branch gets two years of active support and two more of security-only fixes. By that policy PHP 8.6 should receive active support until 31 Dec 2028 and security fixes until 31 Dec 2030; confirm the exact dates on php.net. For reference, PHP 8.6 is supported until 31 Dec 2029 and PHP 8.2 reaches end of life on 31 Dec 2026.

| Change                                              | Cadence                                | Notes                                                                                                                     |
| :-------------------------------------------------- | :------------------------------------- | :------------------------------------------------------------------------------------------------------------------------ |
| Patch release (8.6.x)                               | Monthly, after a staging soak          | Normally no code changes                                                                                                  |
| Minor release (for example 8.6 to the next version) | Yearly, after the first patch releases | One minor at a time; read the official migration guide for the target (`https://www.php.net/migrationXY`) and `UPGRADING` |

**Upgrade checklist**

1. Read the migration guide for the target version; list backward-incompatible changes.
2. Make the code deprecation-clean on the **current** version (`error_reporting=E_ALL` in staging, PHPCompatibility, Rector dry-run).
3. Check dependencies and extensions:
   ```bash
   composer why-not php 8.6
   composer check-platform-reqs
   composer audit
   ```
4. Build a test image without touching the working setup:
   ```bash
   docker build -f docker/php/Dockerfile --build-arg PHP_VERSION=<target> --target production -t mvc_php:test .
   ```
5. Run the test suite, `apply-and-verify.sh`, a performance baseline and a log scan for new warnings.
6. Diff `php -i` between old and new to catch changed defaults.
7. Canary the new image, then roll out fully ([section 11](#11-release-and-rollback)).
8. Do not combine the PHP upgrade with schema changes or feature releases.

### PHP 8.6 notes

- **Image tag:** `php:8.6.0-apache`. Confirm it resolves before building: `docker manifest inspect php:8.6.0-apache`.
- **Thread safety:** the official 8.6 images are reported to be thread-safe (ZTS) builds. Check with `docker compose exec app php -i | grep -i "thread safety"`, and make sure every extension you add supports PHP 8.6 and ZTS.
- **Session defaults changed** in 8.6 (strict mode, HttpOnly, SameSite=Lax). `security_php2shell.ini` sets these explicitly (`SameSite=Strict`), so your values apply.
- **Re-verify the hardening** on the new version: `bash apply-and-verify.sh`, then read `storage/logs/php-error.log` for deprecations or ini warnings at startup.
- Review the official PHP 8.6 migration guide (`https://www.php.net/migration86`) for backward-incompatible changes before release.

---

## 11. Release and Rollback

1. Build the production image from the tagged commit and pin the base image by digest.
2. Deploy side by side (new container next to the old one), send 5 to 10% of traffic, and watch error rate and latency.
3. Roll forward when stable.
4. **Rollback = redeploy the previous image tag.** Keep the last known-good tag for several days.
5. Record the change, test evidence and approval, as regulated environments require.

---

## 12. Troubleshooting

| Symptom                                                                      | Cause                                              | Fix                                                                               |
| :--------------------------------------------------------------------------- | :------------------------------------------------- | :-------------------------------------------------------------------------------- |
| Build error about a missing `docker/php/security.ini`                        | The legacy root `Dockerfile` was built             | Delete it; compose builds `docker/php/Dockerfile`                                 |
| `install-php-extensions` fails after the PHP upgrade                         | An extension does not yet support PHP 8.6 (or ZTS) | Check the extension's release notes, pin a compatible version or wait for support |
| `composer: command not found` in the app container                           | Production image has no Composer                   | Run Composer on the host or in the tooling container                              |
| Build fails: `open_basedir restriction ... /usr/bin/composer`                | Hardening ini copied into `base` or `vendor` stage | Keep `COPY ... security_php2shell.ini` only in the `production` stage             |
| Container `unhealthy`, `/healthz` returns 500                                | PHP fatal error (see the PHP error log)            | `docker compose exec app tail -n 50 /var/www/html/storage/logs/php-error.log`     |
| `TypeError: Core\Env::get(): Argument #2 ($default) must be of type ?string` | Non-string default with `strict_types`             | `Env::get('DB_PORT', '3306')`                                                     |
| Warning about `/.dockerenv` and `open_basedir`                               | Path not allowed                                   | Keep `/.dockerenv` in `open_basedir` (already set)                                |
| App unreachable on the published port                                        | Mapping targets 80, Apache listens on 8080         | Map to `:8080` (`APP_PORT:8080`)                                                  |
| `allow_url_fopen` or `display_errors` are On                                 | Dev `custom_dev.ini` loads last                    | Expected in dev; use hardened mode to verify                                      |
| `Set DB_ROOT_PASSWORD in .env`                                               | Missing variable                                   | Add it to `.env`                                                                  |
| DB password change has no effect                                             | MySQL reads it only on first volume creation       | Reset the volume (deletes data): `docker compose down --volumes`                  |
| Writes fail in hardened mode                                                 | Code is read-only                                  | Write only under `storage/`                                                       |
| `Found orphan containers (mvc_phpmyadmin)`                                   | Hardened run after a dev run                       | Harmless; `docker compose up -d` restores dev mode                                |
| Git Bash mangles container paths                                             | MSYS path conversion                               | Prefix the command with `MSYS_NO_PATHCONV=1`                                      |
