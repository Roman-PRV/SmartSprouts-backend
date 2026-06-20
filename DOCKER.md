# 🐳 Docker Guide

The project uses two Compose files to separate development and production:

| File | Purpose |
|---|---|
| `docker-compose.yml` | Base, prod-ready configuration. Coolify reads **only** this file in production. |
| `docker-compose.override.yml` | Development overrides (auto-loaded by Docker Compose; dev-only) |

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

## Production (Coolify)

Production is deployed by **Coolify**, which reads **only `docker-compose.yml`**
(`docker-compose.override.yml` is dev-only and ignored). The `laravel` / `queue-worker`
images are pulled from GHCR — CI builds the **`prod` stage** (no Xdebug, no dev
dependencies, optimized OPcache), pushes it, then triggers a Coolify webhook
(see `.github/workflows/ci.yml`). Env vars and the domain are set in the Coolify UI,
not in a committed file.

Migrations run automatically on container startup: `APP_ENV=production` triggers
`php artisan migrate --force --isolated` in `entrypoint.sh`.

### Networking invariant (do not add custom networks)

The base compose declares **no custom networks on purpose**. Under Coolify every
service joins the network Coolify manages (the resource "Destination" network),
which the Traefik proxy also joins — so the proxy resolves each service to one
deterministic IP. Adding a custom network makes containers **multi-homed**, and
Traefik then routes to an IP it cannot reach → intermittent **504** that the
browser reports as a CORS error (`No 'Access-Control-Allow-Origin'`). Locally,
Compose's implicit `default` network gives the same single-network behaviour.

### Troubleshooting: backend unreachable after deploy

Symptom: the frontend hangs ~20s then fails with a CORS error, yet containers are healthy.

```bash
# 1. Containers up & healthy?
docker ps -a --filter "name=<resource-uuid>" --format "table {{.Names}}\t{{.Status}}"

# 2. App reachable directly (bypass Traefik)? Fast response = app fine, blame the proxy.
docker exec <nginx-container> wget -qO- http://127.0.0.1/api/games

# 3. Is nginx on a SINGLE network? More than one = the multi-homing bug.
docker ps --format "table {{.Names}}\t{{.Networks}}"

# 4. App logs survive redeploy on the laravel_storage volume:
docker exec <laravel-container> tail -50 storage/logs/laravel.log
```

If the browser shows "CORS" / timeout but the app log is empty and step 2 is instant,
the fault is proxy↔nginx routing — not the application or the CORS config.

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

# Validate prod configuration as Coolify sees it (base file only)
docker compose -f docker-compose.yml config

# Check which services start without the TTS profile
docker compose config --services

# Verify that Xdebug is absent in the prod image (build the prod stage directly)
docker build -f laravel/docker/Dockerfile --target prod -t smartsprouts-prod-check laravel
docker run --rm smartsprouts-prod-check php -m | grep -i xdebug   # expect no output
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
