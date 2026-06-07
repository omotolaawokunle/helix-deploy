# HelixDeploy API

Laravel 12 REST API — the control and execution plane for [HelixDeploy](../../README.md).

HTTP controllers handle auth, validation, authorisation, and job dispatch. SSH, deployments, provisioning, builds, and metrics collection run on queue workers — never inside HTTP requests.

---

## Prerequisites

- PHP 8.3+ with extensions: `pdo_pgsql`, `redis`, `sodium`, `mbstring`, `openssl`, `curl`
- Composer 2
- PostgreSQL 16
- Redis 7

---

## Setup

```bash
cp .env.example .env
composer install
php artisan key:generate
php artisan migrate --seed
```

Configure `.env` for your local database, Redis, and Reverb credentials. Set `SPA_URL` to your frontend origin (e.g. `http://localhost:5173`) and include that host in `SANCTUM_STATEFUL_DOMAINS`.

---

## Development

Start the API, queue worker, log tail, Reverb, and frontend Vite dev server together:

```bash
composer dev
```

This runs:

| Process  | Command                                                                              |
| -------- | ------------------------------------------------------------------------------------ |
| API      | `php artisan serve`                                                                  |
| Queue    | `queue:listen` on `deployments`, `provisioning`, `commands`, `monitoring`, `default` |
| Logs     | `php artisan pail`                                                                   |
| Reverb   | `php artisan reverb:start`                                                           |
| Frontend | `npm run dev` in `../frontend`                                                       |

For build-runner development, start an additional worker:

```bash
php artisan queue:work redis --queue=builds --sleep=3 --tries=1 --timeout=1800
```

Or run Horizon (includes all queues):

```bash
php artisan horizon
```

The scheduler handles server pings, metrics collection, certificate renewal, and watchdog jobs:

```bash
php artisan schedule:work
```

---

## Module structure

Domain logic lives in `app/Modules/{Domain}/`. Each module owns its Controllers, Actions, DTOs, Services, Jobs, Policies, Models, Migrations, and Events.

```
app/Modules/
  Auth/              Authentication, API tokens
  Organizations/     Orgs, members, invitations
  Teams/             Team membership and project scoping
  Projects/          Projects and environments
  Servers/           Server registration, groups, cloud providers
  Sites/             Sites, env vars, nginx, DNS, SSL
  Deployments/       Deploy, rollback, log streaming
  BuildRunners/      Build runner pool and slot management
  Pipelines/         Pipeline stages and approval gates
  Provisioning/      Server provisioning and templates
  Credentials/       Encrypted secret storage
  CronJobs/          Scheduled task management
  Daemons/           Supervisor process management
  Commands/          Remote command execution
  Monitoring/        Server metrics collection
  Audit/             Immutable audit log
  Integrations/      Cloudflare, DigitalOcean DNS
```

Shared packages in `packages/`:

```
packages/
  SSH/           phpseclib abstraction (SSHManager, FakeSSHConnection)
  Encryption/    libsodium envelope encryption
  Execution/     Deployment and build step framework
  Artifacts/     Tarball creation, SCP transfer, checksum verification
  Provisioning/  Provisioning script library
  Realtime/      SSE and Reverb broadcasting helpers
```

---

## Queues

| Queue          | Purpose                                            |
| -------------- | -------------------------------------------------- |
| `builds`       | Build runner compile and artifact transfer         |
| `deployments`  | Deploy and rollback execution                      |
| `provisioning` | Server provisioning, DNS records, SSL certificates |
| `commands`     | Remote command execution                           |
| `monitoring`   | Server metrics and build runner health checks      |
| `default`      | Miscellaneous jobs                                 |

Horizon configuration: `config/horizon.php`.

---

## Artisan commands

```bash
# Re-encrypt org master keys after APP_KEY rotation
php artisan credentials:rekey --old-key="{OLD_APP_KEY}"

# Build runner operations
php artisan runners:check-slots --org={org_id}
php artisan runners:check-slots --fix
php artisan runners:ping {runner_id}
```

---

## Testing

```bash
php artisan test
```

All SSH tests use `FakeSSHConnection` — never real SSH.

```bash
./vendor/bin/pint          # Code style
./vendor/bin/phpstan analyse  # Static analysis
```

---

## API conventions

- Base path: `/api/v1`
- Auth: Sanctum cookie (SPA) or Bearer token (API clients)
- All tenant resources scoped by `organization_id` from auth context
- Sensitive fields never present in API responses
- Long-running operations dispatched as jobs on named queues
