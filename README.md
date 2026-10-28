# My MVC Project

Lightweight custom PHP MVC (PSR-4, PDO, zero runtime dependencies) with Vue 3 islands, jQuery and Tailwind CSS v4.
Runs the same in **Docker** and **XAMPP**.

## Option A: Docker

```bash
cp .env.example .env
docker compose up -d --build
docker compose exec app composer install
docker compose exec app php database/migrate.php
# optional phpMyAdmin:  docker compose --profile tools up -d
```

App: http://localhost:8080 · MySQL from host: 127.0.0.1:3307 · phpMyAdmin: http://localhost:8081
(Host ports 8080/3307 are used on purpose so they never clash with XAMPP's 80/3306.)

## Option B: XAMPP (PHP 8.4 + Apache + MySQL)

1. Put the folder in `htdocs/my-mvc-project`, enable `mod_rewrite` (default on).
2. `cp .env.example .env` and set `DB_HOST=127.0.0.1`, `DB_USERNAME=root`, `DB_PASSWORD=` (blank by default).
3. Create the database `mvc_app` in phpMyAdmin.
4. In the project folder: `composer install` then `php database/migrate.php`.
5. Open http://localhost/my-mvc-project/ (the root `.htaccess` forwards to `/public`).
   The router strips the subfolder automatically; no config needed.

## Frontend build

Compiled assets go to `public/build` and are committed, so XAMPP users need no Node.
To rebuild after editing `resources/`:

```bash
npm install
npm run build        # or: npm run dev  (watch mode)
```

## How it fits together

- `public/index.php` -> `Core\Application` (loads .env/config, container, session, router, middleware)
- Request flow: Router -> SecurityHeaders -> CsrfMiddleware -> Controller -> Service -> Repository (PDO) -> Model
- CSRF: `<meta name="csrf-token">` + jQuery sends `X-CSRF-TOKEN`; forms use `<?= csrf_field() ?>`
- Vue islands: add `<div data-vue="Counter" data-props='{"start":1}'>` in any view; drop components in `resources/js/components/`
- DB host: `DB_HOST` if set, else `db` inside Docker, else `127.0.0.1`

1st run this for db setup migration
"docker compose exec app php database/migrate.php"

2nd run this commad to use for tables data seeding
"docker compose exec app php runseeder.php"
