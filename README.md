# Ultima Vox CMS

Lightweight CMS built around a simple HostCMS-inspired content model: nodes, layouts and infosystems, without XML/XSLT and EAV queries.

## Requirements

- PHP 8.4+
- PostgreSQL 16+
- Nginx
- Composer 2
- PHP extensions: `pdo`, `pdo_pgsql`, `json`

## Install

```bash
git clone https://github.com/ultima-vox/cms.git
cd cms
composer install --no-dev --classmap-authoritative
cp .env.example .env
```

Configure `.env`, then run:

```bash
php bin/console migrate
php bin/console health
```

Create the first administrator without putting the password into shell history:

```bash
export CMS_ADMIN_PASSWORD='replace-with-a-strong-password'
php bin/console user:create admin@example.com 'Administrator'
unset CMS_ADMIN_PASSWORD
```

Point Nginx document root to `public/`. A reference configuration is available at `deploy/nginx.conf.example`.

The PHP-FPM user must be able to write to `storage/cache` and `storage/logs`.

## Core model

- `nodes` — hierarchical site structure and pages;
- `layouts` — Twig page layouts;
- `infosystems` — reusable content stores;
- `infosystem_items` — items with indexed `JSONB` custom properties;
- `users`, `roles`, `permissions` — administration access control;
- `audit_log` — security and change audit storage.

## Commands

```bash
php bin/console migrate
php bin/console health
php bin/console user:create <email> <display-name>
```

## Runtime

- `/health` — health endpoint;
- `/admin/login` — administration login;
- `/admin` — protected administration dashboard;
- all remaining URLs are resolved through the `nodes` table.

## Security baseline

The core enables Twig auto-escaping, CSRF tokens for state-changing admin requests, strict cookie sessions, login throttling, RBAC checks and standard browser security headers. Production deployments should terminate TLS and keep `APP_DEBUG=false`.
