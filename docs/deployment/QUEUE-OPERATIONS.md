# Queue Operations

## Default configuration

PAYLITY RC1 uses Laravel queues for asynchronous work when `QUEUE_CONNECTION` is not `sync`.

Recommended production setting:

```env
QUEUE_CONNECTION=database
```

## Worker process

Run a persistent worker on the API host:

```bash
php artisan queue:work --sleep=3 --tries=3 --max-time=3600
```

Use a process supervisor (systemd, Supervisor, or hosting panel) to keep the worker alive across restarts.

## Monitoring

- `GET /api/v1/health` — queue connection status, pending and failed job counts
- Ops monitoring (`GET /api/v1/ops/monitoring`) — `queue.pending_jobs`, `queue.failed_jobs`, `queue.status`
- Executive dashboard — queue health card

### Status meanings

| Status | Meaning |
|--------|---------|
| `ok` | Queue connection healthy, no failed jobs |
| `degraded` | Failed jobs present or connection issue |
| `warning` | `sync` driver on deployed environment |

## Failed jobs

Inspect failed jobs:

```bash
php artisan queue:failed
```

Retry a failed job:

```bash
php artisan queue:retry <id>
```

Retry all failed jobs after a fix:

```bash
php artisan queue:retry all
```

## Deploy procedure

1. Pause or drain workers during deploy (optional for low-traffic soft launch).
2. Deploy code and run migrations.
3. `php artisan queue:restart`
4. Confirm worker process restarted.
5. Verify ops monitoring queue metrics.

## RC1 note

Mail is commonly `sync` or `log` during soft launch. Move to a queued mail transport before high-volume production email.
