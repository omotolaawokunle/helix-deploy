# HelixDeploy Frontend

Vue 3 TypeScript SPA for [HelixDeploy](../../README.md). Fully decoupled from the API — communicates over REST, SSE (log streaming), and Laravel Reverb (realtime push events).

---

## Prerequisites

- Node.js 22
- Running HelixDeploy API (see [`apps/api/README.md`](../api/README.md))

---

## Setup

```bash
cp .env.example .env
npm install
```

Configure `.env`:

| Variable              | Example                      | Purpose               |
| --------------------- | ---------------------------- | --------------------- |
| `VITE_API_URL`        | `http://localhost:8000`      | API base URL          |
| `VITE_REVERB_HOST`    | `localhost`                  | Reverb WebSocket host |
| `VITE_REVERB_PORT`    | `8080`                       | Reverb WebSocket port |
| `VITE_REVERB_SCHEME`  | `http`                       | `http` or `https`     |
| `VITE_REVERB_APP_KEY` | (match API `REVERB_APP_KEY`) | Reverb app key        |

Ensure the API `.env` has `SANCTUM_STATEFUL_DOMAINS` set to include your frontend origin (e.g. `localhost:5173`).

---

## Development

```bash
npm run dev
```

The dev server runs at [http://localhost:5173](http://localhost:5173) by default.

Alternatively, start everything from the API directory:

```bash
cd ../api && composer dev
```

---

## Project structure

Feature-driven vertical slices mirror the API domain modules:

```
src/
  features/
    auth/              Login, register, email verification
    servers/           Server list, detail, provisioning
    sites/             Site settings, env vars, nginx, DNS/SSL
    deployments/       Deployment detail, log viewer
    build-runners/     Build runner management
    projects/          Projects and environments
    pipelines/         Pipeline builder and detail
    teams/             Team management
    organizations/     Org settings, invitations
    provisioning/      Provisioning templates
    integrations/      Cloudflare, DigitalOcean settings
    monitoring/        Operations dashboard
    audit/             Audit log viewer
  components/
    ui/                Shadcn/Vue primitives
    layout/            App shell, sidebar, org switcher
    common/            EnvironmentBadge, ConfirmDestructiveDialog, etc.
  composables/         Shared logic (theme, route prefetch, org channel)
  stores/              Pinia stores (feature-level)
  router/              Vue Router config
  types/               TypeScript interfaces for API entities
  lib/                 Axios instance, utilities
```

---

## Conventions

- **TypeScript strict mode** — no `any`, explicit return types on all functions
- **HTTP** — Axios with `withCredentials: true` and `withXSRFToken: true`
- **State** — Pinia stores per feature, not one global store
- **UI** — Shadcn/Vue + Tailwind CSS; production environments always show `EnvironmentBadge` and confirmation guards
- **Realtime** — Reverb for discrete state changes (server status, deployment events, runner slots); SSE for log streams (deployments, commands)
- **Destructive actions** — always use `ConfirmDestructiveDialog`

Design context: [`.impeccable.md`](../../.impeccable.md)

---

## Build

```bash
npm run build
```

Production build outputs to `dist/`. The Docker image serves this via Nginx with API and Reverb proxying configured in `Dockerfile`.

---

## Testing

```bash
npm run test          # single run
npm run test:watch    # watch mode
```

Tests use Vitest and `@vue/test-utils`.

---

## Theming

Light, dark, and system themes are supported. The user's preference is persisted in `localStorage` (`helix-deploy-theme`) and applied via the account menu.

Brand accent is teal/cyan on a neutral OKLCH base. See `src/style.css` for design tokens.
