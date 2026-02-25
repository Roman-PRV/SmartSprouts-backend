# 🐳 Docker Guide

The project uses three Compose files to separate development and production environments:

| File | Purpose |
|---|---|
| `docker-compose.yml` | Base configuration shared across all environments |
| `docker-compose.override.yml` | Development overrides (auto-loaded) |
| `docker-compose.prod.yml` | Production overrides (explicitly loaded via `-f`) |

---

## Development

```bash
# Start the stack (automatically merges override.yml)
docker compose up -d

# Start with the ukrainian-tts service
docker compose --profile tts up -d

# Rebuild after changes in Dockerfile
docker compose build laravel
docker compose up -d
```

The development stack runs `laravel` using the **`dev` stage** (Xdebug enabled, bind-mount for source code).

---

## Production

```bash
# Start the production stack
docker compose -f docker-compose.yml -f docker-compose.prod.yml up -d

# Start with the ukrainian-tts service
docker compose -f docker-compose.yml -f docker-compose.prod.yml --profile tts up -d

# Rebuild the production image
docker compose -f docker-compose.yml -f docker-compose.prod.yml build laravel
```

The production stack is built using the **`prod` stage** (no Xdebug, no dev dependencies, optimized OPcache).  
Migrations run automatically on container startup (`APP_ENV=production` triggers `php artisan migrate --force` in `entrypoint.sh`).

---

## Ukrainian TTS (optional service)

The TTS service (~3–5 GB image with PyTorch + models) **does not start by default**.  
It is activated via the Docker Compose profile `tts`:

```bash
docker compose --profile tts up -d
```

In production, if an external TTS API is used, the `--profile tts` flag is not needed.

---

## Xdebug

Xdebug is installed **only in the `dev` stage** of the image.

Configuration: [`laravel/docker/xdebug.ini`](laravel/docker/xdebug.ini)  
Port: `9003` | IDE Key: `ANTIGRAVITY`

---

## Configuration Verification

```bash
# Validate dev configuration (no build)
docker compose config

# Validate prod configuration (no build)
docker compose -f docker-compose.yml -f docker-compose.prod.yml config

# Check which services start without the TTS profile
docker compose config --services

# Verify that Xdebug is absent in the prod image
docker compose -f docker-compose.yml -f docker-compose.prod.yml build laravel
docker run --rm smartsprouts-backend-laravel:prod php -m | grep -i xdebug
```

---

## Environment Variables

Copy `.env.example` to `.env` and fill in the values:

| Variable | Required | Description |
|---|---|---|
| `MYSQL_ROOT_PASSWORD` | ✅ | MySQL root password |
| `MYSQL_PASSWORD` | ✅ | Password for the MySQL app user |
| `REDIS_PASSWORD` | ✅ | Redis password |
| `APP_ENV` | — | Set via Compose (`local` / `production`) |
| `REDIS_PORT` | — | Redis port on host (default: `6379`, dev only) |
