# HelixDeploy

Open source, self-hosted, multi-tenant deployment and infrastructure orchestration platform.

- **API** — Laravel 12 control and execution plane (`apps/api`)
- **Frontend** — Vue 3 TypeScript SPA (`apps/frontend`)

## Quick Start (Docker)

1. Clone the repository:

   ```bash
   git clone https://github.com/your-org/helixdeploy
   cd helixdeploy/infrastructure
   ```

2. Copy the environment file and set secrets:

   ```bash
   cp .env.example .env
   ```

3. Generate `APP_KEY` (run once, paste into `.env`):

   ```bash
   docker compose run --rm api php artisan key:generate --show
   ```

4. Fill in `.env`:

   - `DB_PASSWORD`
   - `REDIS_PASSWORD`
   - `REVERB_APP_KEY` and `REVERB_APP_SECRET` (random strings; keep `REVERB_APP_KEY` in sync with the frontend build arg if you rebuild after changing it)

5. Start the stack:

   ```bash
   docker compose up -d --build
   ```

6. Run migrations and seed the database:

   ```bash
   docker compose exec api php artisan migrate --seed
   ```

7. Open [http://localhost](http://localhost) and register your first account.

All `docker compose` commands below assume your current directory is `infrastructure/`.

## APP_KEY rotation

Organization master keys are encrypted with `APP_KEY`. Rotating the application key requires re-encrypting stored credentials:

1. Generate a new key and update `APP_KEY` in `.env` (keep the previous key value handy).
2. Rekey credentials using the old key:

   ```bash
   docker compose exec api php artisan credentials:rekey --old-key="{OLD_APP_KEY}"
   ```

3. Restart API and worker containers:

   ```bash
   docker compose restart api worker-deployments worker-provisioning worker-default
   ```

## Architecture notes

- **Control plane / execution plane** — HTTP controllers dispatch jobs and return immediately; SSH, deployments, and provisioning run on dedicated queue workers (`deployments`, `provisioning`, `commands`, `monitoring`, `default`).
- **Secrets** — Each organization has a libsodium master key; per-secret envelope encryption uses a fresh nonce on every write. Secrets are never returned in API responses or logs.
- **SSH** — Host fingerprints are recorded on first connection (TOFU) and verified on every subsequent connection. A mismatch aborts the session and alerts the organization; fingerprints are never silently updated.
- **Audit logs** — Append-only immutable records with `before_state` / `after_state` snapshots. No update or delete paths exist at any layer.

### Docker services

| Service | Role |
| --- | --- |
| `api` | Laravel HTTP API (`artisan serve` on port 8000) |
| `worker-deployments` | `deployments` queue |
| `worker-provisioning` | `provisioning` queue |
| `worker-default` | `commands`, `monitoring`, and `default` queues |
| `scheduler` | `schedule:work` for cron-style tasks |
| `reverb` | Laravel Reverb WebSocket server (port 8080) |
| `frontend` | Nginx serving the SPA; proxies `/api/`, `/sanctum/`, SSE streams, and `/app/` to Reverb |
| `postgres` | PostgreSQL 16 |
| `redis` | Redis 7 (AOF persistence, password protected) |

Workers use `queue:work` rather than multiple Horizon masters, because only one Horizon instance should run per Redis namespace. To use Horizon locally for debugging, run `docker compose exec api php artisan horizon` in a separate terminal (stop the split workers first to avoid duplicate job processing).

## Local development (without Docker)

See `apps/api/README.md` and `apps/frontend/README.md` for API and frontend development setup.
