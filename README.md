# MVC_PHP — Custom PHP MVC Application

Operational documentation for a custom PHP MVC application that runs in two independent local environments: Docker Desktop (Docker Compose) and XAMPP. Docker and XAMPP instructions are parallel so the same task can be found in both. Values that the repository does not confirm are written as placeholders and listed in [section 17](#17-verification-and-assumptions).

## Evidence legend

| Tag | Meaning |
| :--- | :--- |
| `[CONFIRMED]` | Observed in a repository file, a command output from this project, or a file supplied for this README |
| `[UNVERIFIED]` | Not inspected. A verification command is given next to it |
| `[GAP]` | A control or capability that is absent or not implemented. See the gap register in section 17 |
| `<PLACEHOLDER>` | A value the repository does not confirm. Replace it after running the stated verification |

Shell labels. Every command block names its shell. *Git Bash / POSIX* blocks use single quotes and `$VAR`, and are not valid unchanged in PowerShell or Command Prompt. The Docker commands in this README are written for Git Bash / POSIX because that is the shell used on this project; PowerShell or `cmd` variants are shown only where the syntax differs.

## Table of contents

1. [Project overview](#1-project-overview)
2. [Architecture](#2-architecture)
3. [Compatibility matrix](#3-compatibility-matrix)
4. [Environment isolation](#4-environment-isolation)
5. [Port management](#5-port-management)
6. [Docker Desktop setup](#6-docker-desktop-setup)
7. [XAMPP setup](#7-xampp-setup)
8. [Configuration reference](#8-configuration-reference)
9. [Database and assets](#9-database-and-assets)
10. [Daily development workflow](#10-daily-development-workflow)
11. [Validation](#11-validation)
12. [Security](#12-security)
13. [Quality and CI](#13-quality-and-ci)
14. [Upgrades and lifecycle](#14-upgrades-and-lifecycle)
15. [Troubleshooting](#15-troubleshooting)
16. [Release and rollback](#16-release-and-rollback)
17. [Verification and assumptions](#17-verification-and-assumptions)

---

## 1. Project overview

- A custom PHP MVC application with a public frontend context and a backend (admin) context. Business logic and data access are shared. `[CONFIRMED: app/ tree]`
- Reference domain: e-commerce. The migrations create users, categories, products, orders and order details. `[CONFIRMED: database/migrations/]`
- Framework core in `core/` (container, router, request/response, session, CSRF, view, database, migrator, env loader). `[CONFIRMED: core/ tree]`
- Frontend build pipeline: Vite with Vue single-file components (`resources/js/components/Counter.vue`) and CSS (`resources/css/app.css`), output in `public/build/`. Scripts, versions and ports are `[UNVERIFIED]` (see section 9.3).
- Containerised runtime: one multi-stage Dockerfile (`docker/php/Dockerfile`) and Compose files. Compose files use the current [Compose Specification](https://docs.docker.com/reference/compose-file/) and declare no `version:` key. `[CONFIRMED: docker-compose.yml, docker-compose.override.yml, docker-compose.tools.yml]`
- The same repository can also run under XAMPP. XAMPP does not use Docker in any way.

---

## 2. Architecture

### 2.1 Request flow

```text
Browser -> Apache (document root: public/)
        -> public/index.php            front controller
        -> Core\Application            container + configuration load
        -> Core\Router (routes/web.php)
        -> middleware                  CsrfMiddleware, SecurityHeaders
        -> controller                  app/Frontend/Controllers or app/Backend/Controllers
        -> service -> repository       app/Services -> app/Repositories (PDO via core/Database.php)
        -> view                        app/<Context>/Views (output escaped with e())
        -> Core\Response
```

`[CONFIRMED]` component names and folders. `[UNVERIFIED]` the exact order and which middleware is registered on which route. Verify in `public/index.php`, `core/Application.php`, `core/Router.php` and `routes/web.php`.

### 2.2 Layers and boundaries

| Layer | Path | Rule |
| :--- | :--- | :--- |
| Core engine | `core/` | No application logic; no dependency on `app/` |
| Contexts | `app/Frontend/`, `app/Backend/` | Each owns `Controllers/`, `Models/`, `Views/` (`home/`, `layouts/`). The public and admin contexts do not share views |
| Middleware | `app/Middleware/` | Cross-cutting request/response concerns |
| Services | `app/Services/` | Business logic shared by both contexts |
| Repositories | `app/Repositories/` (+ `Contracts/`) | The only layer that talks to the database |
| Shared models | `app/Models/` | Models used by both contexts |
| Helpers / Support / Exceptions | `app/Helpers/`, `app/Support/`, `app/Exceptions/` | Static utilities, seeding/validation support, typed exceptions |
| Config | `config/` | `app.php`, `database.php` |
| Database | `database/migrate.php`, `database/migrations/` | Numbered SQL migrations |
| Public web root | `public/` | The only directory Apache serves |
| Writable state | `storage/logs/` | The only runtime-writable path in the hardened image |

### 2.3 Adding a feature

1. Add the route in `routes/web.php` and attach the needed middleware.
2. Add a controller in the correct context. Controllers stay thin.
3. Put business rules in a service; put SQL only in a repository (define the interface in `Repositories/Contracts/`).
4. Add or reuse a model; add a numbered migration in `database/migrations/` for schema changes.
5. Render through the context's view; escape every dynamic value with `e()`.
6. Never query the database from a view, and never let a frontend controller call a backend class.

### 2.4 Verified directory tree

Source: the `tree` output supplied for this project plus a directory listing of the built image. Hidden files are listed from the listing.

```text
MVC_PHP/
├── .github/                        # contains workflows (contents UNVERIFIED)
├── .vscode/
├── .dockerignore
├── .env.example
├── .gitattributes
├── .gitignore
├── .phpcs.cache
├── app/
│   ├── Backend/{Controllers,Models,Views}/       # Views: home/, layouts/
│   ├── Frontend/{Controllers,Models,Views}/
│   ├── Exceptions/                 # AppException + typed subclasses
│   ├── Helpers/                    # Array, Constant, Debug, Sql, String, Validator helpers
│   ├── Middleware/                 # CsrfMiddleware, SecurityHeaders
│   ├── Models/                     # User
│   ├── Repositories/               # UserRepository, Contracts/UserRepositoryInterface
│   ├── Services/                   # UserService
│   └── Support/                    # DatabaseSeeder, Validator
├── config/                         # app.php, database.php
├── core/                           # Application, Config, Container, Controller, Csrf, Database, Env,
│                                   # ExceptionHandler, HttpException, MiddlewareInterface, Migrator,
│                                   # Request, Response, Router, Session, View, helpers.php
├── database/
│   ├── migrate.php
│   └── migrations/                 # 000_create_db ... 005_create_order_detail (.sql)
├── docker/
│   ├── apache/                     # vhost.conf, security.conf, custom_dev.ini (dev only)
│   ├── php/                        # Dockerfile, opcache.ini, security_php2shell.ini
│   └── tools/Dockerfile            # tooling container image
├── public/                         # index.php, build/ (+ .htaccess: UNVERIFIED)
├── resources/                      # css/app.css, js/{app.js,bridge.js,components/Counter.vue}
├── routes/web.php
├── storage/logs/                   # app.log, php-error.log
├── apply-and-verify.sh
├── composer.json, composer.lock
├── docker-compose.yml              # hardened base
├── docker-compose.override.yml     # development (auto-loaded)
├── docker-compose.tools.yml        # tooling project "mvc_tools"
├── package.json, vite.config.js    # no package-lock.json in the supplied tree [GAP]
├── phpcs.xml, sonar-project.properties
└── runseeder.php
```

Not part of the application (local artefacts present in the tree: `build.log`, `phpinfo.txt`, `phpcs-report.txt`, `project_structure*.txt`, `MVC_INFO.txt`, `github_readme.md`, a legacy root `Dockerfile`). Keep them out of git and out of the image. `phpinfo.txt` dumps server configuration and must not be committed. The root `Dockerfile` is unused by Compose and references `docker/php/security.ini`, which does not exist.

Root `.htaccess`: older documentation claimed one exists as a redirect to `public/`. It does not appear in the directory listing of the built image `[CONFIRMED absent in that listing]`. Do not rely on a root redirect: both environments must use `public/` as the document root. Whether `public/.htaccess` exists is `[UNVERIFIED]` (`ls -la public`).

### 2.5 PSR-4 mapping

`[UNVERIFIED]` against `composer.json`. Verify with: `sed -n '/"autoload"/,/}/p' composer.json`.

| Directory | Namespace |
| :--- | :--- |
| `app/Backend/Controllers`, `app/Frontend/Controllers` | `App\Backend\Controllers`, `App\Frontend\Controllers` |
| `app/Backend/Models`, `app/Frontend/Models` | `App\Backend\Models`, `App\Frontend\Models` |
| `app/Exceptions`, `app/Helpers`, `app/Middleware`, `app/Models`, `app/Support` | `App\Exceptions`, `App\Helpers`, `App\Middleware`, `App\Models`, `App\Support` |
| `app/Repositories`, `app/Repositories/Contracts` | `App\Repositories`, `App\Repositories\Contracts` |
| `app/Services` | `App\Services` |
| `core` | `Core` |

---

## 3. Compatibility matrix

Every row names its source. Replace placeholders only after running the verification command.

| Component | Value | Source | Pinned? | Needed for | Verify locally |
| :--- | :--- | :--- | :--- | :--- | :--- |
| PHP (Docker) | `<PHP_VERSION>` from `ARG PHP_VERSION` | `docker/php/Dockerfile` | Exact version | Dev and production | `grep -n "^ARG PHP_VERSION" docker/php/Dockerfile`; `docker compose exec app php -v` |
| PHP (XAMPP) | `<XAMPP_PHP_VERSION>` (bundled with XAMPP) | XAMPP install | Not pinned | Dev only | `<XAMPP_DIR>\php\php.exe -v` |
| PHP constraint | `<COMPOSER_PHP_CONSTRAINT>` | `composer.json` `require.php` | Range | Both | `grep -n '"php"' composer.json` |
| Apache | Whatever `php:<PHP_VERSION>-apache` ships (2.4.x on Debian) | Base image; observed in container logs | Via image | Docker | `docker compose exec app apache2 -v` |
| Apache (XAMPP) | `<XAMPP_APACHE_VERSION>` | XAMPP install | Not pinned | XAMPP | `<XAMPP_DIR>\apache\bin\httpd.exe -v` |
| Composer (Docker build) | `composer:2` image | `docker/php/Dockerfile` vendor stage | Major only | Build | `docker run --rm composer:2 --version` |
| Composer (tooling / host) | `<COMPOSER_VERSION>` | `docker/tools/Dockerfile` `[UNVERIFIED]` / host | Not pinned | Dev | `composer --version` |
| Node.js / npm | `<NODE_VERSION>` / `<NPM_VERSION>` | `package.json` `engines`, `.nvmrc` `[UNVERIFIED]` | Unknown | Frontend build | `node -v`; `npm -v`; `grep -n '"engines"' -A3 package.json` |
| Vite / Vue / CSS toolchain | `<VITE_VERSION>` | `package.json` | Range | Frontend build | `npm ls vite vue` |
| Database (Docker) | MySQL `8.4` | `docker-compose.yml` (`mysql:8.4`) | Minor tag, not digest | Dev | `docker compose exec db mysql --version` |
| Database (XAMPP) | MariaDB/MySQL `<MARIADB_VERSION>` (bundled) | XAMPP install | Not pinned | XAMPP | `<XAMPP_DIR>\mysql\bin\mysql.exe --version` |
| PHP extensions | `pdo_mysql`, `zip` installed by `install-php-extensions`; `sodium`, OPcache present in the image | `docker/php/Dockerfile`; image `conf.d` listing | Via image | Docker | `docker compose exec app php -m` |
| Required extensions (all) | `<REQUIRED_EXTENSIONS>` | `composer.json` + `composer check-platform-reqs` | Range | Both | `composer check-platform-reqs` |
| Docker Desktop | `<DOCKER_DESKTOP_VERSION>` | Developer machine | Not pinned | Docker | `docker version` |
| Compose | v2 plugin, 2.24 or newer (the override uses the `!override` tag) | `docker-compose.override.yml` | Minimum | Docker | `docker compose version` |
| phpMyAdmin | `phpmyadmin:5.2` | `docker-compose.override.yml` | Minor tag, tag unverified | Dev only | `docker manifest inspect phpmyadmin:5.2` |

Engine difference: Docker uses MySQL 8.4; XAMPP ships MariaDB (or MySQL, depending on the XAMPP build). The migrations must run on both. Compatibility is `[UNVERIFIED]`; run the migrations in each environment and compare the resulting tables.

---

## 4. Environment isolation

### 4.1 Supported isolation model: two working copies

The application loads its settings from real environment variables first and from a `.env` file second `[CONFIRMED: core/Env.php::get]`. In the Docker development stack the whole project folder is bind-mounted into the container, so anything in the folder is visible to the container. The only way to keep the two environments from contaminating each other without changing code is to give each its own folder:

```text
<DOCKER_WORKDIR>   e.g. C:\projects\mvc_php_docker   <- Docker Desktop only
<XAMPP_WORKDIR>    e.g. C:\projects\mvc_php_xampp    <- XAMPP only
```

```bash
# Git Bash / POSIX - run once from the parent folder
git clone <REPO_URL> mvc_php_docker
git clone <REPO_URL> mvc_php_xampp
```

| State | Docker working copy | XAMPP working copy |
| :--- | :--- | :--- |
| `.env` | Own file (Compose variables) | Own file (application variables) |
| `vendor/` | Own (see 6.6) | Own (host Composer, XAMPP PHP) |
| `node_modules/` | Own (see 6.6) | Own (Windows npm) |
| `public/build/` | Own | Own |
| `storage/` (logs, cache, uploads) | Own | Own |
| Database engine and data | Docker volume `<COMPOSE_PROJECT>_dbdata` (MySQL 8.4) | `<XAMPP_DIR>\mysql\data` |
| Containers, network, volumes | Docker project (section 6.2) | None |
| Package download caches | Docker: `composer-cache` volume (tooling project) | Host Composer/npm caches (downloads only; safe to share) |

Different folder names give different Compose project names, so the two Docker-side resources never collide with each other, and nothing in the XAMPP copy is read by a container.

### 4.2 Single working copy (fallback, weaker isolation)

If you keep one folder, these items are shared and can contaminate each other: `.env`, `vendor/`, `node_modules/`, `public/build/`, `storage/`. `[GAP G-1]`

- Use `.env.docker` for Compose only: always run `docker compose --env-file .env.docker ...`. This keeps Compose from reading the XAMPP `.env`.
- Keep the XAMPP values in `.env` (the filename the application loader reads; verify the path in `core/Env.php`).
- The Docker development container still sees the XAMPP `.env` through the bind mount. Because real environment variables win, only keys that Compose does not set (anything other than `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`) can leak from it.
- `vendor/` and `node_modules/` created by one runtime may not work in the other (for example Linux binaries from a container inside a Windows `node_modules`). Delete and reinstall them when you switch (section 15).
- `apply-and-verify.sh` reads `.env` only `[GAP G-3]`.

### 4.3 Environment files

| File | Used by | Location | Contents | Committed |
| :--- | :--- | :--- | :--- | :--- |
| `.env.example` | Template | Repository | Placeholder keys. Contents `[UNVERIFIED]` | Yes |
| `.env` (Docker copy) | Compose variable interpolation; also read by the app through the bind mount in dev | `<DOCKER_WORKDIR>` | `DB_ROOT_PASSWORD` (required), optional `APP_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_FORWARD_PORT` | No |
| `.env` (XAMPP copy) | Application loader | `<XAMPP_WORKDIR>` | `DB_HOST=127.0.0.1`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`, application keys | No |
| `.env.docker`, `.env.xampp` | Fallback names for single-copy mode only | Project root | As above | No |

Confirm they are ignored: `git check-ignore -v .env .env.docker .env.xampp` (each must print a rule). `.gitignore` contents are `[UNVERIFIED]`.

### 4.4 Precedence (highest wins)

| # | Source | Applies to |
| :--- | :--- | :--- |
| 1 | Real process environment inside the app container or the PHP process (`getenv`). In Docker these are the `environment:` entries of the `app` service | Application at runtime `[CONFIRMED: core/Env.php]`. An empty string counts as unset |
| 2 | `.env` file read by `core/Env.php` | Application fallback only |
| 3 | Defaults in `config/*.php` (for example `DB_DATABASE` falls back to `mvc_app`, `DB_PORT` to `3306`, `DB_HOST` to `db` inside Docker or `127.0.0.1` outside) | Application `[CONFIRMED: config/database.php]` |

For Compose values: variables exported in the shell running `docker compose` override the env file (`--env-file`, or `.env` when none is given), which overrides `${VAR:-default}` defaults in the Compose file. Print the resolved result with the redaction command in section 6.5 before starting anything.

### 4.5 Switching environments safely

- Two working copies: nothing to switch. Stop whichever environment holds a port you need (section 5.4).
- Single working copy:
  1. Stop the running environment (`docker compose down`, or stop Apache and MySQL in the XAMPP Control Panel).
  2. Select that environment's env file (`--env-file .env.docker` for Docker; `.env` for XAMPP).
  3. Confirm the active database host and name with the isolation tests below.
  4. Never copy Docker database files into the XAMPP `data` folder, and never point two engines at one data directory. Use SQL dumps to move data.

### 4.6 Isolation tests (no secrets printed)

Docker (Git Bash / POSIX)

```bash
docker compose exec app sh -c 'echo "in-docker=$(test -f /.dockerenv && echo yes || echo no) DB_HOST=$DB_HOST DB_PORT=$DB_PORT DB_DATABASE=$DB_DATABASE"; php -v | head -n 1'
```

Expected: `in-docker=yes`, `DB_HOST=db`, `DB_PORT=3306`.

XAMPP (Command Prompt, from `<XAMPP_WORKDIR>`)

```bat
findstr /B /C:"DB_HOST" /C:"DB_PORT" /C:"DB_DATABASE" .env
where php
php -v
```

Expected: `DB_HOST=127.0.0.1`, the XAMPP port, and a `php` path inside `<XAMPP_DIR>`. Nothing printed contains a password.

Both: the two web URLs answer on different ports (section 5), and `docker volume ls --filter label=com.docker.compose.project=<COMPOSE_PROJECT>` lists only Docker resources while `<XAMPP_DIR>\mysql\data` contains only XAMPP databases.

---

## 5. Port management

### 5.1 Port register

| Port | Side | Environment | Value | Defined in | Reachable from | Status |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| Apache listener | Container | Docker | `8080` | `docker/php/Dockerfile` (production stage rewrites `Listen` and the VirtualHost) and its `HEALTHCHECK` | Inside the container network only | `[CONFIRMED]` (published mapping `127.0.0.1:8080->8080` seen in `docker compose ps`) |
| App host port | Host | Docker | `127.0.0.1:${APP_PORT:-8088}` mapped to container `8080` | `docker-compose.yml` | The host machine only (loopback) | Default `8088` `[CONFIRMED]`. Your `.env` may override it; a past `docker compose ps` showed host `8080`, so use `<APP_PORT>` |
| MySQL listener | Container | Docker | `3306` | `mysql:8.4` image | Not published in the hardened base | `[CONFIRMED]` |
| MySQL host port | Host | Docker (dev override only) | `127.0.0.1:${DB_FORWARD_PORT:-3309}` mapped to `3306` | `docker-compose.override.yml` | Host loopback | Default `3309` `[CONFIRMED]` |
| phpMyAdmin listener / host | Container / Host | Docker (dev override only) | `80` / `127.0.0.1:8084` | `docker-compose.override.yml` | Host loopback | Host port is hard-coded `[GAP G-6]` |
| Vite dev server | Host or container | Either | `<VITE_PORT>` | `vite.config.js` | `[UNVERIFIED]` | Read `vite.config.js` `server.port` |
| XAMPP Apache | Host | XAMPP | `<XAMPP_HTTP_PORT>` (XAMPP default is `80`) | `<XAMPP_DIR>\apache\conf\httpd.conf` (`Listen`) | Host | XAMPP default, not a project fact |
| XAMPP MySQL / MariaDB | Host | XAMPP | `<XAMPP_MYSQL_PORT>` (XAMPP default is `3306`) | `<XAMPP_DIR>\mysql\bin\my.ini` (`port=`) | Host | XAMPP default, not a project fact |

The original Compose file chose `8088` and `3309` so Docker would not fight XAMPP on `80` and `3306`. `[CONFIRMED: comments in the original docker-compose.yml]`

### 5.2 Which value controls what

- The internal listener (Apache `8080`) is set in the Dockerfile and in `vhost.conf` as rewritten by the Dockerfile. Compose does not change it.
- The host mapping is set only in the `ports:` entry of the Compose file. Changing `APP_PORT` changes the browser URL, not the container listener.
- If you change the internal listener, you must also change the Compose `ports:` target and the `HEALTHCHECK` in the Dockerfile, then rebuild.
- XAMPP has no mapping: the port in `httpd.conf` is the port you browse to.

### 5.3 Changing a port

| What | Docker | XAMPP |
| :--- | :--- | :--- |
| Application | `APP_PORT` in `.env`, then `docker compose up -d` | `Listen` in `httpd.conf` and the `<VirtualHost *:<XAMPP_HTTP_PORT>>` line, then restart Apache |
| Database | `DB_FORWARD_PORT` in `.env` (dev). The container side stays `3306` | `port=` in `my.ini`, restart MySQL, then set `DB_PORT` in the XAMPP `.env` |
| Database admin UI | Edit the host port in `docker-compose.override.yml` (not parameterised) | Part of XAMPP Apache (`/phpmyadmin`), no separate port |
| Frontend dev server | `<VITE_PORT>` in `vite.config.js` | Same file |

After any port change, review: the browser URL, database client settings (host and port), the health-check address, `APP_URL` or any trusted-host or CORS setting in `config/app.php` `[UNVERIFIED]` (`grep -n "url\|host\|cors" -i config/app.php`).

### 5.4 Finding and resolving a port conflict

Before startup:

PowerShell
```powershell
Get-NetTCPConnection -LocalPort 8088 -State Listen | Select-Object LocalPort, OwningProcess
Get-Process -Id <PID>
```

Command Prompt / Git Bash (Windows `netstat`)
```bat
netstat -ano | findstr :8088
tasklist /FI "PID eq <PID>"
```

Linux / POSIX: `ss -ltnp | grep ':8088'` macOS: `lsof -nP -iTCP:8088 -sTCP:LISTEN`

Inspect Docker's own bindings: `docker ps --format "table {{.Names}}\t{{.Ports}}"`.

After a failed start, run `docker compose ps -a` and read the error text. `bind: Only one usage of each socket address` means the host port is taken.

Safest fix, in order: (1) change only the host-side mapping (section 5.3); (2) stop the process only after identifying it (`Stop-Process -Id <PID>` or `taskkill /PID <PID>`); never stop a process you did not identify. XAMPP services are stopped from the XAMPP Control Panel.

### 5.5 Running Docker and XAMPP together or one at a time

| Scenario | Rule |
| :--- | :--- |
| Simultaneous | Docker keeps its defaults (`8088`, `3309`, `8084`); XAMPP keeps `<XAMPP_HTTP_PORT>` and `<XAMPP_MYSQL_PORT>`. They do not overlap as long as XAMPP is not moved onto `8088`, `3309` or `8084`. Use the isolation model in 4.1 |
| Sequential | Stop one environment (`docker compose down`, or stop Apache and MySQL in the Control Panel) before the other uses the same host port |

---

## 6. Docker Desktop setup

All commands in this section run on the host, from `<DOCKER_WORKDIR>`, in Git Bash / POSIX unless labelled otherwise. Commands that run inside a container are written as `docker compose exec ...`.

### 6.1 Compose files and how they combine

| File | Role | Loaded |
| :--- | :--- | :--- |
| `docker-compose.yml` | Hardened, production-like base: services `app` and `db`; volumes `dbdata`, `appstorage` | Always |
| `docker-compose.override.yml` | Development: bind mount, `custom_dev.ini`, MySQL host port, phpMyAdmin | Automatically, but only when you do not pass `-f` |
| `docker-compose.tools.yml` | Separate project `mvc_tools` (service `tools`): Composer and quality tools | Never automatically. Always pass `-f docker-compose.tools.yml` |

Compose processes files in the order supplied; later files override or extend earlier ones. With no `-f`, Compose reads `docker-compose.yml` and then `docker-compose.override.yml`. With `-f docker-compose.yml`, only that file is read. Lists such as `ports:` are merged; the override replaces the base `app.volumes` entirely because it uses the `!override` tag. The `COMPOSE_FILE` environment variable changes the default file list, so make sure it is unset: Git Bash `unset COMPOSE_FILE`, PowerShell `Remove-Item Env:COMPOSE_FILE`.

Always render the merged configuration before starting anything (redacts every `*PASSWORD:` value):

```bash
# Development stack (base + override)
docker compose config | sed -E 's/(PASSWORD: ).*/\1<redacted>/'

# Hardened stack (base only)
docker compose -f docker-compose.yml config | sed -E 's/(PASSWORD: ).*/\1<redacted>/'
```

```powershell
# PowerShell equivalent
docker compose config | ForEach-Object { $_ -replace '(PASSWORD: ).*', '$1<redacted>' }
```

Useful summaries: `docker compose config --services`, `docker compose config --volumes`, `docker compose config --images`.

In the rendered output, confirm: the effective project name (`name:` at the top), the services, each `ports:` entry (published and target), each volume's `type:` (`bind` means host files are mounted), and the `environment:` keys.

### 6.2 Project, container, network and volume names

The base Compose file has no top-level `name:`, so the project name comes from the folder name unless you pass `-p <name>` or set `COMPOSE_PROJECT_NAME` `[GAP G-11]`.

| Resource | Name | Status |
| :--- | :--- | :--- |
| Project | `mvc_php` (folder `MVC_PHP`) | `[CONFIRMED]` from container names |
| Containers | `mvc_php-app-1`, `mvc_php-db-1` | `[CONFIRMED]` |
| phpMyAdmin container | `mvc_phpmyadmin` (fixed `container_name`, not prefixed; a second Docker working copy would clash) | `[CONFIRMED]` |
| Image | `mvc_php-app` | `[CONFIRMED]` |
| Volumes | `mvc_php_appstorage` `[CONFIRMED]`, `mvc_php_dbdata` (same pattern) | verify with `docker volume ls` |
| Network | `mvc_php_default` (Compose default naming) | `[UNVERIFIED]`: `docker network ls` |

List only this project's resources:

```bash
docker compose ls
docker volume ls --filter label=com.docker.compose.project=mvc_php
docker network ls --filter label=com.docker.compose.project=mvc_php
docker compose ps -a
```

Stale or orphaned resources show up as a project name you do not recognise, or as a warning `Found orphan containers (mvc_phpmyadmin)` after a hardened run. The orphan is the dev-only phpMyAdmin container and is harmless; `docker compose down --remove-orphans` removes it.

### 6.3 Dockerfile stages (`docker/php/Dockerfile`)

`[CONFIRMED]` from the build logs. Re-read the file before relying on any detail; it is the authority.

| Stage | Purpose |
| :--- | :--- |
| `base` | `php:<PHP_VERSION>-apache` (value of `ARG PHP_VERSION`). Installs `pdo_mysql` and `zip` with `install-php-extensions`, enables `rewrite`, `headers`, `remoteip`, disables `autoindex`, installs `php.ini-production` as `php.ini`, writes Apache `ServerTokens Prod`, `ServerSignature Off`, `TraceEnable Off`, copies `opcache.ini` and `vhost.conf`. Does not contain the hardening ini |
| `vendor` | Adds Composer (`composer:2`), git and unzip. `composer install --no-dev --no-scripts --no-autoloader`, copies the project, then `composer dump-autoload --no-dev --classmap-authoritative --no-scripts` and `composer check-platform-reqs --no-dev` |
| `production` | Copies `security_php2shell.ini`, then the code from `vendor`. Code is root-owned; `storage/` is owned by `www-data`; Apache is switched to port `8080`; runs as `www-data`; defines a `HEALTHCHECK` |

The hardening ini must stay in `production` only: `open_basedir` and `disable_functions` break Composer in `vendor`. There is no separate `development` stage; development reuses the `production` image and relaxes it with the override file.

### 6.4 Development versus hardened

| Aspect | Development (`docker compose ...`) | Hardened (`docker compose -f docker-compose.yml ...`) |
| :--- | :--- | :--- |
| Application code | Host folder bind-mounted at `/var/www/html`; writable; `vendor/` comes from the host | Copied into the image; root-owned; read-only to the runtime user |
| Writable paths | Whole project folder | `storage/` (named volume `appstorage`) only |
| PHP settings | Hardening ini, then `docker/apache/custom_dev.ini` loads last and overrides. It sets `allow_url_fopen = On` (line 16) `[CONFIRMED]`; other keys `[UNVERIFIED]` (`cat docker/apache/custom_dev.ini`) | Hardening ini only |
| Verbose errors | Observed `display_errors` ON in dev | OFF; errors go to `storage/logs/php-error.log` |
| Database port | Published on `127.0.0.1:<DB_FORWARD_PORT>` | Not published |
| phpMyAdmin | Present, `127.0.0.1:8084` | Absent |
| Composer, git in the running container | Absent (production image) | Absent |
| Shell access | `docker compose exec app sh` | Same |
| Non-root, `cap_drop: ALL`, `no-new-privileges` | Defined in the base file; the override does not change them (verify in the rendered output) | Defined in the base file |

> The development stack is not production-safe. Evaluate and release only with `-f docker-compose.yml`.

Preventing accidental deployment of development services: always run releases with `-f docker-compose.yml`; check that `docker compose -f docker-compose.yml config --services` prints only `app` and `db`; do not deploy `docker-compose.override.yml` or `docker-compose.tools.yml`. `apply-and-verify.sh` also fails if the base file contains a bind mount or mounts `custom_dev.ini`.

### 6.5 First run (safe order)

Working directory: `<DOCKER_WORKDIR>`. Nothing below creates persistent state until step 6.

```bash
# 1. Inspect the repository
git status --short
ls docker/php/Dockerfile docker-compose.yml docker-compose.override.yml .env.example

# 2. Create the Docker environment file (Git Bash / POSIX)
cp .env.example .env
```

PowerShell: `Copy-Item .env.example .env`   Command Prompt: `copy .env.example .env`

Edit `.env` and replace every placeholder. `DB_ROOT_PASSWORD` is required; Compose refuses to start without it. If you reuse an existing database volume, keep the password it was created with, because MySQL only reads it when the data volume is first created.

```bash
# 3. Check required secrets WITHOUT printing them
grep -qE '^DB_ROOT_PASSWORD=.+' .env && echo "DB_ROOT_PASSWORD is set" || echo "DB_ROOT_PASSWORD is MISSING"

# 4. Confirm the host ports are free (section 5.4), then render the config (redacted)
docker compose config | sed -E 's/(PASSWORD: ).*/\1<redacted>/'

# 5. Dependencies for the development bind mount (section 6.6): ./vendor must exist

# 6. Start only the database and wait until it is healthy
docker compose up -d --wait db

# 7. Build and start the application
docker compose up -d --build app
docker compose ps

# 8. Create the schema (section 6.7), then validate (section 11)
docker compose exec app php database/migrate.php
```

Single-working-copy fallback: add `--env-file .env.docker` after `docker compose` in every command and use `.env.docker` instead of `.env`.

For a release-readiness run, use the hardened stack and the project script:

```bash
docker compose -f docker-compose.yml config | sed -E 's/(PASSWORD: ).*/\1<redacted>/'
bash apply-and-verify.sh          # full rebuild with --no-cache
QUICK=1 bash apply-and-verify.sh  # reuse the build cache
```

The script needs a POSIX shell (Git Bash, WSL, Linux, macOS); it does not run in PowerShell or Command Prompt. It uses only `docker-compose.yml`.

### 6.6 Composer and npm in Docker

- Where Composer runs. `docker-compose.tools.yml` defines the `tools` service (image from `docker/tools/Dockerfile`, project `mvc_tools`). It mounts the project at `/app`, keeps `vendor/` in the named volume `tools-vendor`, and the Composer cache in `composer-cache`. `[CONFIRMED]`
  ```bash
  docker compose -f docker-compose.tools.yml build tools
  docker compose -f docker-compose.tools.yml run --rm tools composer --version
  ```
  The tools container's `vendor/` is not the host `./vendor`, so it never replaces the host copy, and it is not visible to the development `app` container either.
- Where the dev `app` container gets `vendor/`. From the host folder through the bind mount `[GAP G-2]`. The development stack was observed healthy while reading the host folder, so a host `vendor/` was in use. Populate it with host Composer in `<DOCKER_WORKDIR>`:
  ```bash
  composer install --no-interaction     # host; uses composer.lock
  ```
  This is host-generated, so use it only in the Docker working copy and let `composer.lock` decide versions. The runtime platform check in `vendor/composer/platform_check.php` runs against the container's PHP at request time. If host Composer fails a platform check, fix the host runtime; do not use `--ignore-platform-req` unless you have verified the container PHP satisfies the constraint.
- Composer inside the `app` container: not available (Composer is only in the `vendor` build stage). `[CONFIRMED]`
- Where npm runs: `[UNVERIFIED]`. Check whether the tools image has Node: `docker compose -f docker-compose.tools.yml run --rm tools node -v`. If it does, `docker compose -f docker-compose.tools.yml run --rm tools npm ci` writes `node_modules/` into the host folder through the bind mount (Linux binaries). Do that only in the Docker working copy. If the tools image has no Node, use host npm. `package-lock.json` is not in the supplied tree, so `npm ci` has no lock to use `[GAP G-4]`; see section 9.3.
- Two working copies and the tools project: `docker-compose.tools.yml` fixes `name: mvc_tools`, so two copies would share `tools-vendor`. Run it from one copy only, or pass `-p mvc_tools_<suffix>`.
- File ownership. Docker Desktop on Windows presents bind-mounted files with permissive ownership, so container-created files are normally editable. On Linux hosts, container-created files are root-owned; recover with `sudo chown -R "$(id -u):$(id -g)" vendor node_modules` (Linux only).
- Dependencies created by the wrong runtime (for example a Windows `node_modules` used by a Linux container): delete only that directory inside the affected working copy and reinstall with the correct runtime. Confirm it is generated and ignored first: `git check-ignore -v vendor node_modules`.

### 6.7 Database, migrations and seeding

| Item | Value |
| :--- | :--- |
| Engine | MySQL `8.4` (`mysql:8.4`) `[CONFIRMED]` |
| Host from the `app` container | `db` (Compose service name) `[CONFIRMED]` |
| Host from the Docker host | `127.0.0.1` on `<DB_FORWARD_PORT>` (dev override only) |
| Internal port | `3306` |
| Database name | `${DB_DATABASE:-mvc_app}` |
| Application user | `${DB_USERNAME:-root}` `[GAP G-8]`: the default is the MySQL root user |
| Initialization | `MYSQL_DATABASE` creates the database on first start of an empty volume. `000_create_db.sql` may also create it `[UNVERIFIED]` (`cat database/migrations/000_create_db.sql`) |

Run order is the numeric file order: `000_create_db`, `001_create_user_master`, `002_create_categorie_master`, `003_create_product_master`, `004_create_order_master`, `005_create_order_detail`.

```bash
docker compose exec app php database/migrate.php     # runner exists [CONFIRMED]; behaviour [UNVERIFIED]
docker compose exec app php runseeder.php            # development data only; usage [UNVERIFIED]
```

Whether the runner records applied migrations, whether it is safe to run twice, and whether any migration can be reversed are `[UNVERIFIED]`. Read `database/migrate.php` and `core/Migrator.php` before repeating it, and treat it as not repeatable and not reversible until you have. Never run the seeder against a database that holds data you need. Table check: `docker compose exec db sh -c 'mysql -uroot -p"$MYSQL_ROOT_PASSWORD" "$MYSQL_DATABASE" -e "SHOW TABLES"'`.

### 6.8 Frontend assets

Scripts come from `package.json`; list them with `npm run` (no arguments). Build command: `<NPM_BUILD_SCRIPT>`; dev-server command: `<NPM_DEV_SCRIPT>`. Output goes to `public/build/` `[CONFIRMED path]`. Rebuild after any change under `resources/`. A stale build shows as old CSS or JS in the browser; compare `ls -lt public/build | head` with your last `resources/` edit, then rebuild. If `public/build/` is generated and ignored (`git check-ignore public/build`), delete its contents in this working copy and rebuild; if it is committed, a rebuild changes tracked files.

### 6.9 Daily commands, stop and reset

```bash
docker compose up -d                  # start the development stack
docker compose ps                     # status and health
docker compose logs -f app            # follow application logs
docker compose exec app sh            # shell in the app container
docker compose exec app php -v        # PHP CLI
docker compose down                   # stop; keeps volumes and data
docker compose down --remove-orphans  # also remove orphaned services
```

Project-scoped reset. Destroys the Docker database.

```bash
docker compose down --volumes
```

This deletes `mvc_php_dbdata` and `mvc_php_appstorage` for this Compose project only. It does not touch XAMPP databases, other Docker projects, or host files. Rebuild the image of this project only with `docker compose build --no-cache app`. Do not use `docker system prune`, `docker volume prune` or `docker rm $(docker ps -aq)`: they affect unrelated projects.

### 6.10 Windows path behavior (Git Bash)

Git Bash rewrites arguments that look like POSIX paths. A bare `docker compose exec app tail /var/www/html/...` can arrive as a Windows path.

- Pattern observed working on this project: put container paths inside `sh -c '...'`, for example `docker compose exec app sh -c 'tail -n 30 /var/www/html/storage/logs/php-error.log'`. `[CONFIRMED]`
- Standard Git for Windows setting: prefix the command with `MSYS_NO_PATHCONV=1`. `[UNVERIFIED on this repository]`
- PowerShell and Command Prompt do not rewrite paths, but do not accept the single-quote and `$VAR` syntax used above.

---

## 7. XAMPP setup

XAMPP runs on the host. It does not use Docker, Compose service names, containers or Docker volumes. Commands are labelled Command Prompt (`cmd`) or PowerShell; run them from `<XAMPP_WORKDIR>` unless stated. `<XAMPP_DIR>` is your XAMPP install folder (the installer default is `C:\xampp`; verify yours).

### 7.1 Prerequisites

| Need | Verify (Command Prompt) |
| :--- | :--- |
| XAMPP with Apache and MySQL/MariaDB | `"<XAMPP_DIR>\php\php.exe" -v` |
| PHP satisfying `composer.json` (`<COMPOSER_PHP_CONSTRAINT>`) | `grep -n '"php"' composer.json` (Git Bash) |
| Composer | `composer --version` |
| Node.js and npm (`<NODE_VERSION>`) | `node -v` and `npm -v` |
| Git | `git --version` |

### 7.2 Project placement

Clone the repository into `<XAMPP_WORKDIR>` (for example `C:\projects\mvc_php_xampp`), outside `<XAMPP_DIR>\htdocs`.

> Do not serve the repository root. No root `.htaccess` was found, so exposing the root would make `.env`, `composer.json`, `database/`, `storage/` and `runseeder.php` downloadable. Apache must serve `public/` only.

### 7.3 PHP executable and extensions

```bat
"<XAMPP_DIR>\php\php.exe" -v
"<XAMPP_DIR>\php\php.exe" -m
"<XAMPP_DIR>\php\php.exe" --ini
where php
```

If `where php` does not list `<XAMPP_DIR>\php` first, set it for the current session:

```bat
set PATH=<XAMPP_DIR>\php;%PATH%
```
```powershell
$env:Path = "<XAMPP_DIR>\php;" + $env:Path
```

`pdo_mysql` must be enabled: in the file shown by `--ini` (normally `<XAMPP_DIR>\php\php.ini`) the line `extension=pdo_mysql` must not start with `;`. Restart Apache after editing. Check everything the project needs with `composer check-platform-reqs`.

### 7.4 Composer

Install Composer from getcomposer.org and, when the installer asks for a PHP executable, choose `<XAMPP_DIR>\php\php.exe`. `composer --version` prints the PHP version it uses; `composer diagnose` checks the setup.

```bat
composer validate
composer install
```

`composer install` uses `composer.lock` when it exists `[CONFIRMED: composer.lock present]`. In XAMPP, `vendor/` is created in `<XAMPP_WORKDIR>\vendor` and includes dev packages (the Docker image uses `--no-dev`).

### 7.5 Node.js and npm

Install a Node.js version matching `<NODE_VERSION>`, then `node -v` and `npm -v`. Dependencies and build: section 9.3. `node_modules/` is created in `<XAMPP_WORKDIR>\node_modules` with Windows binaries.

### 7.6 Apache: document root and virtual host

Apache must point at `<XAMPP_WORKDIR>\public`. Edit `<XAMPP_DIR>\apache\conf\extra\httpd-vhosts.conf` (use forward slashes):

```apache
<VirtualHost *:<XAMPP_HTTP_PORT>>
    ServerName mvc-xampp.localhost
    DocumentRoot "C:/projects/mvc_php_xampp/public"
    <Directory "C:/projects/mvc_php_xampp/public">
        Options -Indexes
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

In `<XAMPP_DIR>\apache\conf\httpd.conf` confirm: `Listen <XAMPP_HTTP_PORT>`, `LoadModule rewrite_module modules/mod_rewrite.so` is not commented out, and `Include conf/extra/httpd-vhosts.conf` is not commented out. If the browser does not resolve `mvc-xampp.localhost`, add `127.0.0.1 mvc-xampp.localhost` to `C:\Windows\System32\drivers\etc\hosts` (open the editor as Administrator).

Test the configuration before restarting:

```bat
"<XAMPP_DIR>\apache\bin\httpd.exe" -t
```

`AllowOverride All` only matters if `public/.htaccess` exists. Whether it exists and which rewrite rules it holds are `[UNVERIFIED]` (`dir public /a`). Without a rule that sends requests to `index.php`, routes other than `/` will return 404 `[GAP G-15]`; adding rules is a repository change and is not done by this README. XAMPP does not receive `docker/php/security_php2shell.ini`, so it is not hardened `[GAP G-12]`.

### 7.7 Environment file

```bat
copy .env.example .env
```

Set (the keys the loader reads, from `config/database.php`):

| Key | XAMPP value |
| :--- | :--- |
| `DB_HOST` | `127.0.0.1` (never `db`; that name exists only inside the Docker network) |
| `DB_PORT` | `<XAMPP_MYSQL_PORT>` |
| `DB_DATABASE` | `<XAMPP_DB_NAME>` |
| `DB_USERNAME` | `<XAMPP_DB_USER>` |
| `DB_PASSWORD` | `<XAMPP_DB_PASSWORD>` |

`config/app.php` keys (environment name, debug flag, URL) are `[UNVERIFIED]`: `type config\app.php`. Pass defaults as strings if you edit config code (`Env::get('DB_PORT', '3306')`), because `Env::get` is strictly typed.

### 7.8 Database

Read `database\migrations\000_create_db.sql` first. If it creates the database, do not create it by hand. XAMPP's MySQL/MariaDB `root` account ships without a password; set one, and give the application its own user:

```bat
"<XAMPP_DIR>\mysql\bin\mysql.exe" -h 127.0.0.1 -P <XAMPP_MYSQL_PORT> -u root -p
```
```sql
CREATE DATABASE IF NOT EXISTS `<XAMPP_DB_NAME>` CHARACTER SET utf8mb4;
CREATE USER '<XAMPP_DB_USER>'@'localhost' IDENTIFIED BY '<XAMPP_DB_PASSWORD>';
GRANT ALL PRIVILEGES ON `<XAMPP_DB_NAME>`.* TO '<XAMPP_DB_USER>'@'localhost';
```

Use a database name and credentials different from the Docker ones. If `000_create_db.sql` hard-codes a database name, that name wins `[UNVERIFIED]`.

### 7.9 Migrations and seeding

```bat
php database\migrate.php
php runseeder.php
```

The first command assumes `php` is the XAMPP PHP (`where php`). The same repeatability warning as section 6.7 applies; the seeder is development-only unless the repository proves otherwise.

### 7.10 Start, validate, stop

Start Apache and MySQL from the XAMPP Control Panel (Apache must start without a port error).

```bat
curl.exe -I http://mvc-xampp.localhost:<XAMPP_HTTP_PORT>/
```

In PowerShell use `curl.exe` (not `curl`, which is an alias for `Invoke-WebRequest`). Logs: `<XAMPP_DIR>\apache\logs\error.log` and `<XAMPP_WORKDIR>\storage\logs\`. Stop both services from the Control Panel.

### 7.11 XAMPP reset (affects XAMPP only)

```bat
"<XAMPP_DIR>\mysql\bin\mysql.exe" -h 127.0.0.1 -P <XAMPP_MYSQL_PORT> -u root -p -e "DROP DATABASE `<XAMPP_DB_NAME>`"
```

Destroys the XAMPP database `<XAMPP_DB_NAME>`. Then re-run the migrations. To clear generated files, delete `vendor`, `node_modules` and `storage\logs\*` inside `<XAMPP_WORKDIR>` only. Never touch Docker volumes or copy Docker database files into `<XAMPP_DIR>\mysql\data`.

---

## 8. Configuration reference

Application variables are read by `core/Env.php` through `config/*.php`. Infrastructure variables belong to Docker only and must never be set in the XAMPP `.env`.

| Variable | Kind | Read by | Default | Docker value | XAMPP value |
| :--- | :--- | :--- | :--- | :--- | :--- |
| `DB_HOST` | Application | `config/database.php` | `db` inside Docker (`/.dockerenv` present), otherwise `127.0.0.1` | Set by Compose: `db` | `127.0.0.1` |
| `DB_PORT` | Application | `config/database.php` (cast to int) | `3306` | Set by Compose: `3306` | `<XAMPP_MYSQL_PORT>` |
| `DB_DATABASE` | Application + Docker | `config/database.php`; Compose (`MYSQL_DATABASE`) | `mvc_app` | `.env` `DB_DATABASE` | `<XAMPP_DB_NAME>` |
| `DB_USERNAME` | Application + Docker | `config/database.php`; Compose | `root` | `.env` `DB_USERNAME` | `<XAMPP_DB_USER>` |
| `DB_PASSWORD` | Application | `config/database.php` | empty | Set by Compose from `DB_ROOT_PASSWORD` | `<XAMPP_DB_PASSWORD>` |
| `DB_ROOT_PASSWORD` | Infrastructure | Compose (MySQL root password and app `DB_PASSWORD`) | none, required | `.env` | Not used |
| `APP_PORT` | Infrastructure | Compose | `8088` | `.env` | Not used |
| `DB_FORWARD_PORT` | Infrastructure | Compose override | `3309` | `.env` | Not used |
| `<APP_ENV>`, `<APP_DEBUG>`, `<APP_URL>` | Application | `config/app.php` | `[UNVERIFIED]` | `[UNVERIFIED]` | `[UNVERIFIED]` |

`[CONFIRMED]` rows come from `config/database.php`, `core/Env.php` and `docker-compose.yml`. Replace the `config/app.php` row after reading that file.

Precedence is in section 4.4. The Docker application receives `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME` and `DB_PASSWORD` only through the Compose `environment:` block, so a value in the application `.env` cannot override them.

### 8.1 Secret safety

- Check a value exists without printing it: `grep -qE '^DB_ROOT_PASSWORD=.+' .env && echo set || echo MISSING`.
- Never print a resolved configuration without redaction (section 6.1). Never paste a password into a Dockerfile, a Compose file, a log or a command line you share.
- Detect tracked secrets: `git ls-files | grep -E '(^|/)\.env($|\.)'` should list only `.env.example`; check history with `git log --all --oneline -- .env`.
- After any exposure, rotate: change the password in `.env`, update the database account (`ALTER USER ... IDENTIFIED BY '<NEW_PASSWORD>'`, because MySQL ignores `MYSQL_ROOT_PASSWORD` after the volume exists), restart the application, and remove the secret from history.
- `.dockerignore` must exclude `.env` and `.env.*` so secrets never enter an image. Contents are `[UNVERIFIED]`: `grep -n "env" .dockerignore`.

---

## 9. Database and assets

### 9.1 Engines and access

| | Docker | XAMPP |
| :--- | :--- | :--- |
| Engine | MySQL 8.4 | `<MARIADB_VERSION>` (bundled) |
| Host used by the app | `db` | `127.0.0.1` |
| Port used by the app | `3306` (internal) | `<XAMPP_MYSQL_PORT>` |
| Client access from the host | `127.0.0.1:<DB_FORWARD_PORT>` (dev override only) | `<XAMPP_DIR>\mysql\bin\mysql.exe`, XAMPP phpMyAdmin |
| Data storage | Docker volume `mvc_php_dbdata` | `<XAMPP_DIR>\mysql\data` |

### 9.2 Migrations

| Order | File | Creates |
| :--- | :--- | :--- |
| 0 | `000_create_db.sql` | Database (verify the name inside the file) |
| 1 | `001_create_user_master.sql` | Users |
| 2 | `002_create_categorie_master.sql` | Categories |
| 3 | `003_create_product_master.sql` | Products |
| 4 | `004_create_order_master.sql` | Orders |
| 5 | `005_create_order_detail.sql` | Order details |

Runner: `database/migrate.php` (with `core/Migrator.php`). Whether it tracks applied files, can be repeated, or can reverse changes is `[UNVERIFIED]`; treat every run as potentially non-repeatable and take a dump first (section 16). Seeder: `runseeder.php` with `app/Support/DatabaseSeeder.php`, development only.

### 9.3 Frontend assets

| Step | Docker (Docker working copy) | XAMPP (XAMPP working copy) |
| :--- | :--- | :--- |
| List scripts | `docker compose -f docker-compose.tools.yml run --rm tools npm run` (only if the tools image has Node) or host `npm run` | `npm run` |
| Install | `npm ci` when `package-lock.json` exists; otherwise see below | Same |
| Build | `npm run <NPM_BUILD_SCRIPT>` | `npm run <NPM_BUILD_SCRIPT>` |
| Dev server | `npm run <NPM_DEV_SCRIPT>` on `<VITE_PORT>` | Same |
| Output | `public/build/` | `public/build/` |

`package.json` and `vite.config.js` exist; the script names, Node version, Vite port and lock file are `[UNVERIFIED]`. The supplied tree has no `package-lock.json`, so `npm ci` fails and `npm install` resolves versions freshly `[GAP G-4]`. Creating and committing a lock file is a repository change; this README does not do it. Dependency audit: `npm audit` (needs a lock file).

---

## 10. Daily development workflow

| Task | Docker (Git Bash, `<DOCKER_WORKDIR>`) | XAMPP (Command Prompt, `<XAMPP_WORKDIR>`) |
| :--- | :--- | :--- |
| Select environment | Work in the Docker copy. Single copy: add `--env-file .env.docker` | Work in the XAMPP copy; `.env` holds XAMPP values |
| Start | `docker compose up -d` | Start Apache and MySQL in the Control Panel |
| Status | `docker compose ps` | Control Panel shows green modules |
| Open the app | `http://localhost:<APP_PORT>/` | `http://mvc-xampp.localhost:<XAMPP_HTTP_PORT>/` |
| Logs | `docker compose logs -f app` | `<XAMPP_DIR>\apache\logs\error.log`, `storage\logs\` |
| Composer dependency change | Edit `composer.json`, update the lock deliberately with the tooling container (`... run --rm tools composer update <package>`), commit `composer.lock`, then refresh the host `vendor/` (6.6) | `composer update <package>` then `composer install` |
| Database change | Add `database/migrations/NNN_*.sql`; `docker compose exec app php database/migrate.php` | `php database\migrate.php` |
| Frontend change | Edit `resources/`; rebuild (9.3) | Same |
| Autoload refresh | `composer dump-autoload -o` on the host (tooling container does not write the host `vendor/`) | `composer dump-autoload -o` |
| Stop | `docker compose down` | Stop both modules in the Control Panel |
| Cleanup (this environment only) | `docker compose down --volumes` (destroys the Docker DB) | `DROP DATABASE` for the XAMPP DB only (7.11) |

---

## 11. Validation

Status: confirmed = the command and its target exist in the repository; repository-dependent = works only if the named file or setting exists; unavailable = no such check exists in the project.

| Check | Docker (Git Bash, from `<DOCKER_WORKDIR>`) | XAMPP (Command Prompt, from `<XAMPP_WORKDIR>`) | Status |
| :--- | :--- | :--- | :--- |
| Merged Compose config valid | `docker compose config -q && echo valid` (dev); add `-f docker-compose.yml` for hardened | n/a | confirmed |
| PHP version | `docker compose exec app php -v` | `php -v` (confirm `where php`) | confirmed |
| PHP extensions | `docker compose exec app php -m` | `php -m` | confirmed |
| Composer version | `docker compose -f docker-compose.tools.yml run --rm tools composer --version` | `composer --version` | confirmed |
| Platform requirements | `docker compose -f docker-compose.tools.yml run --rm tools composer check-platform-reqs` (the image build also runs it with `--no-dev`) | `composer check-platform-reqs` | confirmed |
| Lock-file consistency | `... run --rm tools composer validate`; npm: `npm ls --depth=0` | `composer validate`; `npm ls --depth=0` | confirmed (npm lock: repository-dependent) |
| PSR-4 autoloading | `composer dump-autoload -o` (host). Strict check: `composer dump-autoload --strict-psr` (verify the flag with `composer dump-autoload --help`) | Same | repository-dependent |
| Node and npm | `docker compose -f docker-compose.tools.yml run --rm tools node -v` | `node -v`; `npm -v` | repository-dependent |
| Database connectivity | `docker compose exec app php -r '$d=new PDO("mysql:host=".getenv("DB_HOST").";port=".getenv("DB_PORT").";dbname=".getenv("DB_DATABASE"),getenv("DB_USERNAME"),getenv("DB_PASSWORD")); echo "DB OK\n";'` | `"<XAMPP_DIR>\mysql\bin\mysql.exe" -h 127.0.0.1 -P <XAMPP_MYSQL_PORT> -u <XAMPP_DB_USER> -p <XAMPP_DB_NAME> -e "SELECT 1"` (prompts for the password) | confirmed |
| Database readiness (container) | `docker compose exec db sh -c 'mysqladmin ping -h localhost -p"$MYSQL_ROOT_PASSWORD" --silent && echo DB_READY'` | n/a | confirmed |
| Migration state | `... exec db sh -c 'mysql -uroot -p"$MYSQL_ROOT_PASSWORD" "$MYSQL_DATABASE" -e "SHOW TABLES"'` | `mysql.exe ... -e "SHOW TABLES"` | no status command is confirmed (unavailable); inspect tables |
| HTTP status | `curl -sI http://localhost:<APP_PORT>/` | `curl.exe -I http://mvc-xampp.localhost:<XAMPP_HTTP_PORT>/` | confirmed |
| Health endpoint | `docker compose exec app curl -sI http://localhost:8080/healthz` | Only if the route exists | repository-dependent: `grep -n "healthz" routes/web.php` |
| Asset availability | `ls public/build` | `dir public\build` | confirmed |
| Application and PHP logs | `docker compose logs --tail 50 app`; `docker compose exec app sh -c 'tail -n 30 /var/www/html/storage/logs/php-error.log'` | `type storage\logs\php-error.log` if present; Apache `error.log` | confirmed |
| Container health | `docker compose ps`; `docker inspect --format '{{.State.Health.Status}}' mvc_php-app-1` | n/a | confirmed |
| Non-root execution | `docker compose exec app id -u` (expect not `0`; `33` observed) | n/a | confirmed |
| Read-only code path (hardened only) | `docker compose -f docker-compose.yml exec app sh -c 'touch /var/www/html/.w 2>/dev/null && echo WRITABLE \|\| echo READONLY'` (expect `READONLY`) | n/a | confirmed; fails by design in dev |
| Exposed ports | `docker compose ps --format 'table {{.Name}}\t{{.Ports}}'` | `netstat -ano \| findstr LISTENING` | confirmed |
| Capabilities and privilege flags | `docker inspect --format '{{.HostConfig.CapDrop}} {{.HostConfig.SecurityOpt}}' mvc_php-app-1` | n/a | confirmed |
| Public-file exposure | `curl -s -o /dev/null -w '%{http_code}\n' http://localhost:<APP_PORT>/.env` (also `/composer.json`, `/storage/logs/app.log`); expect `404` | Same with `curl.exe` against the XAMPP URL | confirmed |
| Environment separation | Section 4.6 | Section 4.6 | confirmed |
| Hardened-build checks (bundle) | `bash apply-and-verify.sh` | n/a | confirmed |

`apply-and-verify.sh` checks: `.env` has `DB_ROOT_PASSWORD`; the hardening ini is copied once in the `production` stage of `docker/php/Dockerfile`; the ini has no line continuation and sets `allow_url_fopen = Off`; the base Compose file has no bind mount and no `custom_dev.ini`; the app port targets `8080`; then it rebuilds (add `QUICK=1` to reuse the cache), waits for health, and tests disabled functions, `open_basedir`, `allow_url_fopen`, `expose_php`, `display_errors`, non-root user, read-only code, writable `storage/logs`, absent `X-Powered-By`, no Apache version in `Server`, and host-port reachability. A hardened run that finishes with 0 failed has not yet been recorded in the logs supplied for this README.

---

## 12. Security

Status values: Defined (present in a configuration file, effect not yet run), Observed (seen in a run on this project), Absent, Unverified.

### 12.1 Control register

| Control | Status | Source | Verify |
| :--- | :--- | :--- | :--- |
| Only `public/` is served | Observed | `vhost.conf` `DocumentRoot /var/www/html/public` (Docker); XAMPP vhost in 7.6 | Public-file exposure check (section 11) |
| Application runs as non-root | Observed (uid `33`, dev stack) | `docker/php/Dockerfile` `USER www-data` | `docker compose exec app id -u` |
| Linux capabilities dropped | Defined | `cap_drop: [ALL]` in `docker-compose.yml` | `docker inspect` command in section 11 |
| Privilege escalation blocked | Defined | `no-new-privileges:true` in `docker-compose.yml` | same |
| Application code read-only | Defined for the hardened image; Absent in dev (bind mount) | Dockerfile `chmod`, `chown root` | Read-only check (section 11) |
| Container root filesystem read-only | Absent (`read_only` not set) `[GAP G-7]` | `docker-compose.yml` | n/a |
| Writable paths | `storage/` only (hardened); whole folder (dev) | Dockerfile; `appstorage` volume | `docker compose exec app sh -c 'ls -ld /var/www/html /var/www/html/storage'` |
| Database not published | Defined (base). Dev publishes on loopback only | Compose files | `docker compose ps` |
| Database least privilege | Absent: the application uses `root` by default `[GAP G-8]` | `config/database.php`, Compose | n/a |
| Dangerous functions disabled | Observed (`system`, `shell_exec`/backticks, earlier run) | `security_php2shell.ini` `disable_functions` | Section 11 / script |
| `open_basedir` | Observed (blocks `/etc/passwd`); includes `/.dockerenv` so the Docker check in `config/database.php` works | `security_php2shell.ini` | Script |
| `allow_url_fopen`/`allow_url_include` off | Defined (hardened). Overridden to On in dev by `custom_dev.ini` | ini files | Script (hardened only) |
| Errors hidden; logged to `storage/logs/php-error.log` | Defined (hardened); dev shows errors | ini files | `php -i` in the container |
| Session cookie settings (strict mode, HttpOnly, Secure, SameSite=Strict) | Defined (Docker hardened image only) | `security_php2shell.ini` | `php -i \| grep session.cookie` |
| Security headers | Observed: `X-Content-Type-Options: nosniff`, `Referrer-Policy: strict-origin-when-cross-origin` (full set `[UNVERIFIED]`) | `app/Middleware/SecurityHeaders.php`, Apache | `curl -sI` |
| No `X-Powered-By` / version in `Server` | Observed (`Server: Apache`) | `expose_php=Off`, `ServerTokens Prod` | Script |
| CSRF protection | Defined (files exist); route wiring Unverified | `core/Csrf.php`, `app/Middleware/CsrfMiddleware.php` | `grep -rn "CsrfMiddleware" routes core app` |
| Prepared statements | Unverified | `core/Database.php`, `app/Helpers/SqlHelper.php` | `grep -rnE "(->query\(\|->exec\()" app core`, then review for interpolated input |
| Output escaping | Unverified (helper `e()` documented) | `core/helpers.php`, views | `grep -rn "<?=" app/*/Views` and look for unescaped values |
| Authentication / authorization boundary | Unverified (exception classes exist) | `app/Exceptions/Authentication*`, `Authorization*` | Read `app/Backend` routes and middleware |
| File uploads | Unverified (no upload code identified) | n/a | `grep -rn "move_uploaded_file\|\$_FILES" app core` |
| `.env`, logs, backups, CLI scripts not reachable | Defined by the `public/` document root; `.dockerignore`/`.gitignore` contents Unverified | `.dockerignore`, `.gitignore` | `cat .dockerignore .gitignore` |
| Debug helpers inactive in production | Unverified | `app/Helpers/DebugHelper.php` | `grep -rn "DebugHelper" app core routes` |
| Dependency auditing | Absent from automation; command exists | `composer audit`, `npm audit` | Run both |
| Image scanning, Dockerfile lint | Absent (no configuration in the repository) | n/a | `hadolint docker/php/Dockerfile`, `trivy image <IMAGE_NAME>` are external tools, not configured by the project |
| Static analysis | `phpcs.xml` and `sonar-project.properties` exist; PHPStan/Psalm configuration absent | Repository tree | Section 13 |
| Base image pinned by digest | Absent `[GAP G-10]` | `docker/php/Dockerfile` | `grep -n "^FROM" docker/php/Dockerfile` |

### 12.2 Development versus production settings

| Setting | Development | Production (hardened) |
| :--- | :--- | :--- |
| Compose files | Base + override | Base only |
| Code mount | Writable bind mount | Image only |
| PHP hardening ini | Overridden by `custom_dev.ini` | In effect |
| Verbose errors | On | Off |
| DB and admin tools | Loopback ports, phpMyAdmin | None |
| XAMPP | No hardening ini `[GAP G-12]` (its `php.ini` applies; `open_basedir` paths use `;` on Windows) | Not a deployment target |

Local containers are not a security boundary for the development machine: the bind mount gives the container write access to the project folder, and the tooling container runs as root.

---

## 13. Quality and CI

| Check | In the repository? | Command | Evidence |
| :--- | :--- | :--- | :--- |
| PHP coding standard | Config present | `vendor/bin/phpcs` if installed `[UNVERIFIED]` | `phpcs.xml`, `.phpcs.cache`, `phpcs-report.txt` |
| SonarQube analysis | Config present | Scanner command is external `[UNVERIFIED]` | `sonar-project.properties` |
| Composer scripts | `[UNVERIFIED]` | `composer run-script --list` | `composer.json` `scripts` |
| npm scripts | `[UNVERIFIED]` | `npm run` | `package.json` `scripts` |
| Automated tests | None observed: no `tests/` directory or PHPUnit configuration `[GAP G-5]` | none | To add: `phpunit.xml` and `tests/` |
| Static analysis (PHPStan, Psalm) | Absent | none | To add: `phpstan.neon` or `psalm.xml` |
| Dependency audit | Tool command exists, not automated | `composer audit`; `npm audit` | n/a |
| Image scan / Dockerfile lint | Absent | none | To add: CI steps and tool configuration |
| Pre-commit hooks | None observed `[UNVERIFIED]` | none | `ls .git/hooks`, `.pre-commit-config.yaml` not in the tree |
| CI workflow | `.github/workflows/ci.yml` exists; contents `[UNVERIFIED]` | n/a | `cat .github/workflows/ci.yml` |

Authoritative environment for release validation: the hardened Docker build (`docker-compose.yml` + `apply-and-verify.sh`). If `ci.yml` uses a different PHP version or runtime than `ARG PHP_VERSION`, record the difference here and trust the Docker image result.

Coding conventions: `declare(strict_types=1)` in every PHP file; PSR-4 (section 2.5); every `Env::get` default is a string (`Env::get('DB_PORT', '3306')`); SQL only in repositories, with prepared statements; escape all view output with `e()`.

---

## 14. Upgrades and lifecycle

### 14.1 PHP support policy

PHP release branches receive two years of active support, then two years of critical-security support, then reach end of life. Active-support releases get bug fixes and security fixes; security-only releases get critical security fixes only. Do not treat all supported versions as equally maintained.

Dates retrieved from [php.net/supported-versions](https://www.php.net/supported-versions.php) in November 2026. Re-verify before relying on them:

| Branch | Active support ends | Security support ends |
| :--- | :--- | :--- |
| 8.2 | already ended | 31 Dec 2026 |
| 8.3 | 31 Dec 2025 (ended) | 31 Dec 2027 |
| 8.4 | 31 Dec 2026 | 31 Dec 2028 |
| 8.5 | 31 Dec 2027 | 31 Dec 2029 |
| 8.6 (released 19 Nov 2026) | not retrieved: check php.net | not retrieved: check php.net |

The project's runtime is `<PHP_VERSION>` (`ARG PHP_VERSION` in `docker/php/Dockerfile`). Last values seen during setup: `8.5.12`, with a move to `8.6.0` under evaluation. PHP 8.6 is a project target only after all of these pass: the image tag resolves (`docker manifest inspect php:<PHP_VERSION>-apache`), `install-php-extensions` succeeds, `composer why-not php 8.6` and `composer check-platform-reqs` pass, the hardened stack passes `apply-and-verify.sh`, `php-error.log` shows no new deprecations, and the migration guide has been reviewed ([PHP 8.5 to 8.6 guide](https://www.php.net/manual/en/migration86.php)). A third-party image README reports that the official 8.6 images are thread-safe (ZTS) builds; verify with `docker compose exec app php -i | grep -i "thread safety"` and re-check every extension.

### 14.2 Controlled upgrade process

One change type per change set. Never mix a PHP, Composer, npm and database image upgrade in one commit.

1. Record the last known-good state: `git tag known-good-<DATE>`; `docker tag mvc_php-app:latest mvc_php-app:known-good`; take a database dump (section 16).
2. Read the official migration guide and note backward-incompatible changes and deprecations.
3. Make the code deprecation-clean on the current version (`error_reporting=E_ALL` in a staging run; read `storage/logs/php-error.log`).
4. Check dependencies: `composer why-not php <TARGET>`, `composer check-platform-reqs`, `composer audit`; `npm outdated` / `npm audit` for the frontend.
5. Change one value: edit `ARG PHP_VERSION` (or a package constraint, or the `mysql:` tag), and nothing else.
6. Rebuild without cache: `docker compose -f docker-compose.yml build --no-cache app`; record the new image ID: `docker image inspect mvc_php-app:latest --format '{{.Id}}'`.
7. Verify: `bash apply-and-verify.sh`, the validation table (section 11), startup logs, and `php -v` inside the container to confirm the runtime identity. If the project has tests, run them; there is no test suite today, so the validation evidence is the script output, logs and manual route checks `[GAP G-5]`.
8. Roll out gradually (section 16). Roll back if any check fails.
9. After a base-image security update: rebuild, repeat step 7, and review the end-of-life status of the branch.

For XAMPP, upgrade through the XAMPP installer or a new XAMPP version, then repeat `composer check-platform-reqs` and the XAMPP validation checks.

---

## 15. Troubleshooting

Classify first, then act. No row recommends deleting all containers, images or volumes.

| Symptom | Likely cause | Diagnostic | Safest corrective action |
| :--- | :--- | :--- | :--- |
| `composer install` fails with a lock/dependency conflict | `composer.lock` and `composer.json` out of sync | `composer validate`; `composer why-not <package> <version>` | Update the specific package deliberately and commit the lock. Do not delete the lock |
| Composer: "requires php ^X" | Wrong runtime for the work copy | `composer check-platform-reqs`; `php -v`; `where php` | Run Composer with the intended PHP (XAMPP's `php.exe` or the tooling container). Avoid `--ignore-platform-reqs` |
| Missing PHP extension | Not enabled or not installed | `php -m`; `php --ini` | XAMPP: uncomment `extension=...` in `php.ini` and restart Apache. Docker: add it to `install-php-extensions` in the Dockerfile (a repository change) and rebuild |
| Composer or npm cannot reach the registry | Network, proxy or registry outage | `composer diagnose`; `npm config get registry`; retry | Fix proxy settings and retry. Do not disable TLS verification |
| `npm ci` says a lock file is required | No `package-lock.json` `[GAP G-4]` | `ls package-lock.json` | Use `npm install` once in one working copy and commit the lock (repository change) |
| npm fails or native module errors in `node_modules` | Mismatched Node version, or `node_modules` created by the other runtime | `node -v`; `npm ls`; check which environment created the folder | Delete `node_modules` in that working copy only and reinstall with the correct runtime |
| Docker build fails at `composer install` with `open_basedir` | Hardening ini copied before the `vendor` stage | `grep -n "security_php2shell\|^FROM" docker/php/Dockerfile` | Keep the ini copy only in the `production` stage |
| Docker build: `COPY failed` / file not found | File excluded by `.dockerignore` or missing | `cat .dockerignore`; `ls <file>` | Remove the exclusion or restore the file. The Dockerfile copies `docker/php/*.ini`, so do not exclude `docker/` |
| Container `unhealthy` | HEALTHCHECK on `/healthz` failing: PHP fatal, database, or missing route | `docker compose ps`; `docker compose logs --tail 50 app`; `docker compose exec app sh -c 'tail -n 30 /var/www/html/storage/logs/php-error.log'`; `grep -n healthz routes/web.php` | Fix the cause shown in the log. Do not remove the healthcheck |
| HTTP 500 | PHP fatal error (for example `TypeError: Core\Env::get(): Argument #2 ($default) must be of type ?string`) | `php-error.log` as above | Fix the code; defaults passed to `Env::get` must be strings |
| App unreachable on the published port | Host mapping points at the wrong container port | `docker compose config \| grep -B1 -A3 ports` | Map the host port to container port `8080` (`<APP_PORT>:8080`) |
| Port already in use | Another process owns the host port | Section 5.4 | Change the host-side mapping, or stop the identified process |
| XAMPP: only `/` works, other routes 404 | No rewrite to the front controller: module off, `AllowOverride None`, or no `public/.htaccess` `[GAP G-15]` | `"<XAMPP_DIR>\apache\bin\httpd.exe" -t`; `dir public /a` | Enable `mod_rewrite`, set `AllowOverride All`, and confirm the rewrite rules exist in the repository |
| Directory listing, or source/`.env` visible in the browser | Document root is not `public/` | Check `DocumentRoot` in the vhost | Point it at `public/` and restart Apache. Treat any exposed secret as compromised and rotate it |
| Permission denied writing files | Hardened code is read-only; only `storage/` is writable | `docker compose exec app sh -c 'ls -ld /var/www/html/storage'` | Write only under `storage/`. XAMPP: check Windows folder permissions |
| "Set DB_ROOT_PASSWORD in .env" | Missing required variable | `grep -qE '^DB_ROOT_PASSWORD=.+' .env && echo set \|\| echo MISSING` | Add it to `.env` |
| Database "Access denied" | Password differs from the one the data volume was created with | Connectivity check (section 11) | Align `.env`, or change the account with `ALTER USER`. Resetting the volume destroys data: dump first |
| `Unknown host db` (XAMPP) or connection refused to `127.0.0.1` (container) | Wrong hostname for the environment | Isolation test (4.6) | Docker uses `db`; XAMPP uses `127.0.0.1` and its own port |
| Migration fails partway | SQL error, partial schema | `SHOW TABLES`; read `database/migrate.php` | Restore from the pre-migration dump, fix the SQL, rerun. Do not re-run blindly |
| Stale volume (old schema or password) | Volume outlives the Compose file changes | `docker volume ls --filter label=com.docker.compose.project=mvc_php` | Dump the data, then `docker compose down --volumes` (Docker project only) |
| Orphan containers or an old stack | Different Compose file set used earlier | `docker compose ls`; `docker compose ps -a` | `docker compose down --remove-orphans` |
| Compose uses unexpected files | `COMPOSE_FILE` is set, or `-f` omitted | `echo $COMPOSE_FILE`; `docker compose config` | `unset COMPOSE_FILE` and pass `-f` explicitly |
| `service "x" is not defined` / wrong service name | Command used a name from another file set | `docker compose config --services` | Use `app`, `db` (base), `phpmyadmin` (dev), or `tools` (tooling project) |
| Container path rewritten (Git Bash) | MSYS path conversion | Section 6.10 | Wrap in `sh -c '...'` or prefix `MSYS_NO_PATHCONV=1` |
| Container-created files not editable (Linux host) | Root ownership | `ls -l vendor \| head` | `sudo chown -R "$(id -u):$(id -g)" vendor node_modules` |
| `the input device is not a TTY` (Git Bash) | mintty has no TTY | n/a | Add `-T` for non-interactive commands; interactive use may need `winpty` `[UNVERIFIED]` |
| Read-only check fails in the hardened run | Override or bind mount in use | `docker compose -f docker-compose.yml config \| grep "type: bind"` | Run with `-f docker-compose.yml` only |

---

## 16. Release and rollback

1. Work from a clean, tagged commit with `composer.lock` committed (and `package-lock.json` once it exists).
2. Render and inspect the hardened configuration: `docker compose -f docker-compose.yml config` (redacted as in 6.1). It must list only `app` and `db`, no bind mounts, and no published database port.
3. Back up the database before any migration. Git Bash / POSIX:
   ```bash
   docker compose exec -T db sh -c 'mysqldump -uroot -p"$MYSQL_ROOT_PASSWORD" "$MYSQL_DATABASE"' > backup_<DATE>.sql
   ```
   PowerShell's `>` writes UTF-16 and corrupts dumps; use Git Bash or `mysqldump --result-file`. The dump contains data: store it securely and never commit it.
4. Build the target stage and record its identity:
   ```bash
   docker build -f docker/php/Dockerfile --target production -t <IMAGE_NAME>:<RELEASE_TAG> .
   docker image inspect <IMAGE_NAME>:<RELEASE_TAG> --format '{{.Id}}'
   ```
   If the image is pushed to a registry, record the registry digest instead of relying on the mutable tag. Base-image digest pinning is not in place today `[GAP G-10]`.
5. Verify: `bash apply-and-verify.sh`, the validation table, review of startup logs.
6. Deploy gradually: run the new version beside the old one, send a small share of traffic, watch errors and latency, then move the rest.
7. Roll back by redeploying the last known-good build: check out the `known-good-<DATE>` git tag (this restores the Dockerfile and lock files), rebuild, and restore the database dump if a migration ran. The Compose file builds from source and declares no `image:`, so a retained tag such as `mvc_php-app:known-good` is a reference copy, not an automatic rollback target.
8. Never deploy `docker-compose.override.yml`, `docker-compose.tools.yml`, phpMyAdmin, `custom_dev.ini`, or any local artefact listed in section 2.4.
9. Record the change, the verification output and the approval where your environment requires an audit trail.

---

## 17. Verification and assumptions

### 17.1 Confirmed facts

- Compose files exist: `docker-compose.yml` (services `app`, `db`), `docker-compose.override.yml` (`app` volumes replaced, `db` host port, `phpmyadmin`), `docker-compose.tools.yml` (`name: mvc_tools`, service `tools`). None declares `version:`.
- The Compose project is `mvc_php`; containers `mvc_php-app-1`, `mvc_php-db-1`, `mvc_phpmyadmin`; volume `mvc_php_appstorage` was created.
- `docker/php/Dockerfile` has stages `base`, `vendor`, `production`; Apache listens on `8080`; `www-data` (uid `33`) runs the application.
- `core/Env.php`: real environment first, then `.env`, then the supplied default; `Env::get` takes a `?string` default.
- `config/database.php` host default: `db` inside Docker, `127.0.0.1` otherwise; port default `3306`; database default `mvc_app`; username default `root`.
- Migration files `000`–`005` and `database/migrate.php` exist; the seeder runner is `runseeder.php`.
- `security_php2shell.ini` takes effect in the production stage. In earlier runs `system()` and shell execution were disabled, `open_basedir` blocked `/etc/passwd`, and `expose_php` was off.
- A root `.htaccess` was not present in the image directory listing.

### 17.2 Placeholders to replace

| Placeholder | Where to verify |
| :--- | :--- |
| `<PHP_VERSION>` | `grep -n "^ARG PHP_VERSION" docker/php/Dockerfile` |
| `<COMPOSER_PHP_CONSTRAINT>`, `<REQUIRED_EXTENSIONS>` | `composer.json`; `composer check-platform-reqs` |
| `<NODE_VERSION>`, `<NPM_VERSION>`, `<VITE_VERSION>` | `package.json` (`engines`, dependencies); `.nvmrc` |
| `<NPM_BUILD_SCRIPT>`, `<NPM_DEV_SCRIPT>`, `<VITE_PORT>` | `npm run`; `vite.config.js` |
| `<APP_PORT>`, `<DB_FORWARD_PORT>` | `.env`; rendered Compose output |
| `<APP_ENV>`, `<APP_DEBUG>`, `<APP_URL>` | `config/app.php` |
| `<XAMPP_DIR>`, `<XAMPP_PHP_VERSION>`, `<XAMPP_APACHE_VERSION>`, `<MARIADB_VERSION>`, `<XAMPP_HTTP_PORT>`, `<XAMPP_MYSQL_PORT>` | XAMPP install; `httpd.conf`; `my.ini`; `php.exe -v` |
| `<XAMPP_DB_NAME>`, `<XAMPP_DB_USER>`, `<XAMPP_DB_PASSWORD>` | Chosen by you; must differ from the Docker values |
| `<DOCKER_WORKDIR>`, `<XAMPP_WORKDIR>`, `<REPO_URL>` | Your machine; your remote |
| `<COMPOSER_VERSION>`, `<DOCKER_DESKTOP_VERSION>` | `composer --version`; `docker version` |
| `<IMAGE_NAME>`, `<RELEASE_TAG>`, `<DATE>`, `<PID>` | Your release process |

### 17.3 Commands that could not be verified

Marked `[UNVERIFIED]` above: the `database/migrate.php` and `runseeder.php` behaviour and arguments; `npm` scripts and whether the tools image contains Node; `composer dump-autoload --strict-psr`; `MSYS_NO_PATHCONV=1` and `winpty` on this repository; the `phpmyadmin:5.2` tag; every XAMPP command (no XAMPP output was supplied); the `/healthz` route.

### 17.4 Evidence to collect (run from the repository root, paste the output)

```bash
grep -n "^ARG PHP_VERSION\|^FROM" docker/php/Dockerfile
grep -n '"php"' composer.json; sed -n '/"autoload"/,/}/p;/"scripts"/,/}/p' composer.json
sed -n '/"scripts"/,/}/p;/"engines"/,/}/p' package.json; ls package-lock.json .nvmrc
cat vite.config.js | head -40
ls -la . public
grep -n "healthz" routes/web.php
cat .dockerignore .gitignore .env.example
cat docker/apache/custom_dev.ini docker/tools/Dockerfile
cat .github/workflows/ci.yml
sed -n '1,60p' database/migrate.php
sed -n '1,40p' config/app.php
cat database/migrations/000_create_db.sql
```

### 17.5 Gap register

| ID | Gap | Where to resolve |
| :--- | :--- | :--- |
| G-1 | In a single working copy, `.env`, `vendor/`, `node_modules/`, `public/build/`, `storage/` are shared between Docker dev and XAMPP | Use two working copies (4.1) |
| G-2 | No verified container path that populates the host `./vendor` used by the dev stack; the tooling project keeps `vendor/` in a named volume | `docker-compose.tools.yml`, `docker-compose.override.yml` |
| G-3 | `apply-and-verify.sh` reads `.env` only and does not accept `.env.docker` | `apply-and-verify.sh` |
| G-4 | No `package-lock.json` in the supplied tree | Repository root |
| G-5 | No automated tests, static analysis, image scan, Dockerfile lint or pre-commit hooks observed | New config files (section 13) |
| G-6 | phpMyAdmin host port is hard-coded; tag unverified | `docker-compose.override.yml` |
| G-7 | Container root filesystem is not read-only | `docker-compose.yml` |
| G-8 | Application uses the MySQL `root` account by default | `.env`, MySQL user setup |
| G-9 | Legacy root `Dockerfile` (references a missing `docker/php/security.ini`) and a `docker/apache/security.conf` that the active Dockerfile does not use | Remove or wire in |
| G-10 | Base image not pinned by digest | `docker/php/Dockerfile` |
| G-11 | Compose project name derived from the folder name | Add `name:` or use `-p` |
| G-12 | XAMPP does not receive the PHP hardening ini | `<XAMPP_DIR>\php\php.ini` |
| G-13 | Migration idempotency, tracking and reversibility unverified | `database/migrate.php`, `core/Migrator.php` |
| G-14 | `/healthz` route not confirmed in `routes/web.php` | `routes/web.php` |
| G-15 | `public/.htaccess` and rewrite rules unverified | `public/` |

### 17.6 Corrections made to earlier documentation

- The older README showed a `php:8.4-apache` Dockerfile, a `:80` port mapping, a bind-mounted production Compose file and `composer install` inside the app container. None of these matches the active files; they were replaced.
- It claimed a root `.htaccess` redirect; none was found. It listed `app/Views/` and `DashboardController`; the views are inside each context and the controller is `HomeController`.
- Two port lines were malformed (`"8084` and `:80"` split across lines) and were not reproduced.
- Its comment mentioned PHP 8.6 while its Dockerfile showed 8.4; the version is now defined only by `ARG PHP_VERSION`.