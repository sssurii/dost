# INF-01: Dockerized Laravel 12.x Setup

**Phase:** 1 — Infrastructure  
**Complexity:** 2 | **Estimate:** 4h  
**Depends on:** Nothing (first ticket)  
**Blocks:** ALL other tickets

---

## 1. Objective

Provision a fully containerised, reproducible dev environment using Docker Compose that mirrors a production-grade setup, with static networking, a Postgres 16 database, and a Laravel Reverb WebSocket server.

> **Architecture context:** This Docker setup is the **development / browser-testing environment**. It is NOT the production Android build.
> - Laravel 12 is confirmed (`nativephp/mobile` v3 supports `illuminate/contracts ^10|^11|^12`)
> - The Android build uses **SQLite on-device** (configured in MOB-01); this Docker environment uses PostgreSQL for richer dev tooling
> - **Queue:** Docker dev uses `database` driver (Valkey optional for production); Android build uses `database` driver (SQLite-backed)

---

## 2. Prerequisites

| Tool | Minimum Version |
|------|----------------|
| Docker Engine | 26+ |
| Docker Compose (plugin) | v2.24+ |
| PHP (host, for artisan init only) | 8.3+ |
| Composer | 2.7+ |

---

## 3. Architecture Overview

```
┌─────────────────────────────────────────────────────────┐
│  Docker Network: app-network  (15.15.0.0/16)            │
│  Gateway: 15.15.0.1                                     │
│                                                         │
│  ┌──────────────┐   ┌──────────────┐  ┌─────────────┐  │
│  │ laravel.test │   │   postgres   │  │   reverb    │  │
│  │  (PHP 8.3)   │──▶│  (PG 16)     │  │  (ws:8080)  │  │
│  │  :80, :443   │   │  :5432       │  │  :8080      │  │
│  │  IP:15.15.0.2│   │  IP:15.15.0.3│  │ IP:15.15.0.4│  │
│  └──────────────┘   └──────────────┘  └─────────────┘  │
└─────────────────────────────────────────────────────────┘
```

---

## 4. Step-by-Step Implementation

### Step 1 — Bootstrap Laravel 12.x

```bash
# Install fresh Laravel 12
composer create-project laravel/laravel:^12.0 dost

cd dost

# Verify version
php artisan --version
# Expected: Laravel Framework 12.x.x
```

> **Note:** `nativephp/mobile` v3 officially supports `illuminate/contracts ^10|^11|^12`. Laravel 12 is the confirmed version for this project.

### Step 2 — Create `docker-compose.yml`

Create at project root:

```yaml
# docker-compose.yml
name: dost

services:

  # ─── Application (PHP-FPM + Nginx or Caddy) ─────────────────
  laravel.test:
    build:
      context: ./docker/8.3
      dockerfile: Dockerfile
      args:
        WWWGROUP: "${WWWGROUP:-1000}"
    image: dost-app
    container_name: dost_app
    restart: unless-stopped
    ports:
      - "${APP_PORT:-80}:80"
      - "${VITE_PORT:-5173}:${VITE_PORT:-5173}"
    environment:
      WWWUSER: "${WWWUSER:-1000}"
      LARAVEL_SAIL: 1
      XDEBUG_MODE: "${SAIL_XDEBUG_MODE:-off}"
      XDEBUG_CONFIG: "${SAIL_XDEBUG_CONFIG:-client_host=host-gateway}"
      IGNITION_LOCAL_SITES_PATH: "${PWD}"
    volumes:
      - ".:/var/www/html"
    networks:
      app-network:
        ipv4_address: 15.15.0.2
    depends_on:
      postgres:
        condition: service_healthy
      reverb:
        condition: service_started

  # ─── PostgreSQL 16 ──────────────────────────────────────────
  postgres:
    image: postgres:16-alpine
    container_name: dost_postgres
    restart: unless-stopped
    ports:
      - "${DB_PORT:-5432}:5432"
    environment:
      POSTGRES_DB: "${DB_DATABASE:-dost}"
      POSTGRES_USER: "${DB_USERNAME:-dost_user}"
      POSTGRES_PASSWORD: "${DB_PASSWORD:-secret}"
    volumes:
      - dost-postgres:/var/lib/postgresql/data
      - ./docker/pgsql/create-testing-database.sql:/docker-entrypoint-initdb.d/10-create-testing-database.sql
    networks:
      app-network:
        ipv4_address: 15.15.0.3
    healthcheck:
      test: ["CMD", "pg_isready", "-q", "-d", "${DB_DATABASE:-dost}", "-U", "${DB_USERNAME:-dost_user}"]
      retries: 3
      timeout: 5s

  # ─── Laravel Reverb ─────────────────────────────────────────
  reverb:
    image: dost-app  # reuse same app image
    container_name: dost_reverb
    restart: unless-stopped
    command: php artisan reverb:start --host=0.0.0.0 --port=8080 --debug
    ports:
      - "${REVERB_PORT:-8080}:8080"
    environment:
      APP_KEY: "${APP_KEY}"
      REVERB_APP_ID: "${REVERB_APP_ID:-dost}"
      REVERB_APP_KEY: "${REVERB_APP_KEY:-dost-key}"
      REVERB_APP_SECRET: "${REVERB_APP_SECRET:-dost-secret}"
    volumes:
      - ".:/var/www/html"
    networks:
      app-network:
        ipv4_address: 15.15.0.4
    depends_on:
      - laravel.test

  # ─── Valkey (open-source Redis replacement) ─────────────────
  # Used for queue workers when running server-side (Option B from Q16).
  # Not required for device-local NativePHP builds (Option A uses SQLite queue).
  # Valkey = Linux Foundation Redis fork, 100% API-compatible, BSD-3-Clause licensed.
  valkey:
    image: valkey/valkey:8-alpine
    container_name: dost_valkey
    restart: unless-stopped
    ports:
      - "${REDIS_PORT:-6379}:6379"
    volumes:
      - dost-valkey:/data
    networks:
      app-network:
        ipv4_address: 15.15.0.5
    healthcheck:
      test: ["CMD", "valkey-cli", "ping"]
      retries: 3
      timeout: 5s

# ─── Named Volumes ────────────────────────────────────────────
volumes:
  dost-postgres:
    driver: local
  dost-valkey:
    driver: local

# ─── Networks ─────────────────────────────────────────────────
networks:
  app-network:
    driver: bridge
    ipam:
      driver: default
      config:
        - subnet: 15.15.0.0/16
          gateway: 15.15.0.1
```

### Step 3 — Create the Application Dockerfile

```
docker/
└── 8.3/
    ├── Dockerfile
    ├── php.ini
    ├── supervisord.conf
    └── start-container
```

**`docker/8.3/Dockerfile`:**

```dockerfile
FROM ubuntu:24.04

LABEL maintainer="Dost Team"

ARG WWWGROUP=1000
ARG NODE_VERSION=22
ARG POSTGRES_VERSION=16
ARG PHP_VERSION=8.3

WORKDIR /var/www/html

ENV DEBIAN_FRONTEND=noninteractive
ENV TZ=UTC

RUN ln -snf /usr/share/zoneinfo/$TZ /etc/localtime && echo $TZ > /etc/timezone

RUN apt-get update && apt-get install -y \
    curl gnupg gosu ca-certificates zip unzip git \
    supervisor sqlite3 libcap2-bin libpng-dev python3 dnsutils librsvg2-bin \
    && apt-get -y autoremove && apt-get clean

# PHP 8.3
RUN curl -sS 'https://keyserver.ubuntu.com/pks/lookup?op=get&search=0x14aa40ec0831756756d7f66c4f4ea0aae5267a6c' | \
    gpg --dearmor | tee /etc/apt/keyrings/ppa_ondrej_php.gpg > /dev/null

RUN echo "deb [signed-by=/etc/apt/keyrings/ppa_ondrej_php.gpg] https://ppa.launchpadcontent.net/ondrej/php/ubuntu noble main" \
    > /etc/apt/sources.list.d/ppa_ondrej_php.list

RUN apt-get update && apt-get install -y \
    php${PHP_VERSION}-cli php${PHP_VERSION}-fpm \
    php${PHP_VERSION}-pgsql php${PHP_VERSION}-sqlite3 php${PHP_VERSION}-gd \
    php${PHP_VERSION}-curl php${PHP_VERSION}-imap php${PHP_VERSION}-mbstring \
    php${PHP_VERSION}-xml php${PHP_VERSION}-zip php${PHP_VERSION}-bcmath \
    php${PHP_VERSION}-soap php${PHP_VERSION}-intl php${PHP_VERSION}-readline \
    php${PHP_VERSION}-ldap php${PHP_VERSION}-msgpack php${PHP_VERSION}-igbinary \
    php${PHP_VERSION}-redis php${PHP_VERSION}-swoole \
    php${PHP_VERSION}-pcov php${PHP_VERSION}-imagick php${PHP_VERSION}-xdebug

# Install Composer
RUN curl -sLS https://getcomposer.org/installer | php -- --install-dir=/usr/bin/ --filename=composer

# Node
RUN curl -fsSL https://deb.nodesource.com/setup_${NODE_VERSION}.x | bash - \
    && apt-get install -y nodejs

# Nginx
RUN apt-get install -y nginx \
    && setcap "cap_net_bind_service=+ep" /usr/sbin/nginx

# User setup
RUN groupadd --force -g $WWWGROUP sail \
    && useradd -ms /bin/bash --no-user-group -g $WWWGROUP -u 1337 sail

COPY supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY php.ini /etc/php/${PHP_VERSION}/cli/conf.d/99-sail.ini
COPY start-container /usr/local/bin/start-container
RUN chmod +x /usr/local/bin/start-container

EXPOSE 80 8080

ENTRYPOINT ["start-container"]
```

**`docker/8.3/supervisord.conf`:**

```ini
[supervisord]
nodaemon=true
user=root
logfile=/var/log/supervisor/supervisord.log
pidfile=/var/run/supervisord.pid

[program:php]
command=/usr/sbin/php-fpm8.3 -F
autostart=true
autorestart=true

[program:nginx]
command=/usr/sbin/nginx -g "daemon off;"
autostart=true
autorestart=true
```

**`docker/8.3/start-container`:**

```bash
#!/usr/bin/env bash

if [ "$1" != "" ]; then
    exec gosu sail "$@"
fi

# Wait for DB
until pg_isready -h postgres -p 5432 -U "${DB_USERNAME:-dost_user}" 2>/dev/null; do
  >&2 echo "Postgres not ready — sleeping..."
  sleep 2
done

exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf
```

### Step 4 — PostgreSQL Init Script

**`docker/pgsql/create-testing-database.sql`:**

```sql
SELECT 'CREATE DATABASE dost_testing'
WHERE NOT EXISTS (SELECT FROM pg_database WHERE datname = 'dost_testing')\gexec

GRANT ALL PRIVILEGES ON DATABASE dost_testing TO dost_user;
```

### Step 5 — Environment Configuration

Copy `.env.example` to `.env` and update:

```dotenv
APP_NAME="Dost"
APP_ENV=local
APP_KEY=   # will be generated
APP_DEBUG=true
APP_URL=http://localhost

DB_CONNECTION=pgsql
DB_HOST=15.15.0.3        # static IP of postgres container
DB_PORT=5432
DB_DATABASE=dost
DB_USERNAME=dost_user
DB_PASSWORD=secret

BROADCAST_CONNECTION=reverb

REVERB_APP_ID=dost
REVERB_APP_KEY=dost-key
REVERB_APP_SECRET=dost-secret
REVERB_HOST=15.15.0.4    # static IP of reverb container
REVERB_PORT=8080
REVERB_SCHEME=http

VITE_REVERB_APP_KEY="${REVERB_APP_KEY}"
VITE_REVERB_HOST="${REVERB_HOST}"
VITE_REVERB_PORT="${REVERB_PORT}"
VITE_REVERB_SCHEME="${REVERB_SCHEME}"
```

### Step 6 — Install Laravel Reverb

```bash
# Inside container: docker compose exec laravel.test bash
composer require laravel/reverb
php artisan reverb:install
```

### Step 7 — Build & Boot

```bash
# First time setup
docker compose build --no-cache
docker compose up -d

# Generate key
docker compose exec laravel.test php artisan key:generate

# Run migrations
docker compose exec laravel.test php artisan migrate

# Verify gateway connectivity
docker compose exec laravel.test ping -c 2 15.15.0.1
```

### Step 8 — Storage Setup

```bash
docker compose exec laravel.test php artisan storage:link

# Create recordings folder structure
docker compose exec laravel.test bash -c "mkdir -p storage/app/public/recordings"
docker compose exec laravel.test bash -c "echo '*\n!.gitignore' > storage/app/public/recordings/.gitignore"
```

---

## 5. Directory Structure After Ticket

```
dost/
├── app/
├── docker/
│   ├── 8.3/
│   │   ├── Dockerfile
│   │   ├── php.ini
│   │   ├── supervisord.conf
│   │   └── start-container
│   └── pgsql/
│       └── create-testing-database.sql
├── docker-compose.yml
├── .env
├── .env.example
└── ...
```

---

## 6. Verification Checklist

- [ ] `docker compose ps` — all 3 containers show `running`
- [ ] `curl http://localhost` — returns Laravel welcome page (HTTP 200)
- [ ] `docker compose exec laravel.test php artisan migrate` — runs without error
- [ ] `docker compose exec laravel.test ping -c 2 15.15.0.1` — 0% packet loss
- [ ] `docker compose exec laravel.test php artisan reverb:start --test` — connects OK
- [ ] `docker compose exec laravel.test php artisan tinker` → `DB::connection()->getPDO()` — no exception
- [ ] Reverb WebSocket reachable on ws://localhost:8080

---

## 7. Acceptance Criteria

1. `docker compose up -d` starts all services without manual intervention.
2. Laravel app connects to Postgres via static IP `15.15.0.3`.
3. Reverb accessible from the app container at `15.15.0.4:8080`.
4. Storage symlink exists; `storage/app/public/recordings` directory present.
5. All containers communicate within `15.15.0.0/16` subnet.
6. `php artisan migrate` runs clean on fresh boot.

---

## 8. Risks & Mitigations

| Risk | Mitigation |
|------|-----------|
| IP collision with host network | Confirm `15.15.0.0/16` is not used on dev machine; change subnet if needed |
| PHP extension missing for Postgres | Ensure `php8.3-pgsql` is in Dockerfile |
| Reverb container starts before app is ready | Use `depends_on` with `service_started`; app handles reconnects |
| `nativephp/mobile` v3.x compatibility | By design — Laravel 12 is confirmed; `nativephp/mobile` v3 fully supports it |
| PostgreSQL not usable in Android build | By design — Android build uses SQLite (configured in MOB-01) |
| Valkey image unfamiliar | Drop-in Redis replacement; all Laravel Redis commands work unchanged |

