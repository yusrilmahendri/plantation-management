# Deployment — Plantation ↔ Finance integration (Task 7)

Shared secret: Plantation `FINANCE_SERVICE_TOKEN` **must equal** Finance `PLANTATION_SERVICE_TOKEN`.
Optional HMAC: Plantation `FINANCE_HMAC_SECRET` **must equal** Finance `PLANTATION_HMAC_SECRET`.

Do **not** enable events until both apps are migrated and the queue worker is running.

## 1. Finance (`family-keuangan` / arusku.my.id)

1. Backup the database.
2. Deploy code.
3. `php artisan migrate --force`
4. `php artisan optimize:clear`
5. `php artisan config:cache`
6. `php artisan route:cache`
7. `php artisan view:cache`
8. `php artisan finance:entity-audit`
9. `php artisan finance:account-audit`

Required env:

```
PLANTATION_SERVICE_URL=
PLANTATION_SERVICE_TOKEN=
PLANTATION_SERVICE_TIMEOUT=15
PLANTATION_HMAC_SECRET=
```

Internal receiver: `POST /api/internal/plantation/events`

## 2. Plantation (`plantation-management`)

1. Backup the database.
2. Deploy code.
3. `php artisan migrate --force`
4. `php artisan optimize:clear`
5. `php artisan config:cache`
6. `php artisan route:cache`
7. `php artisan view:cache`
8. Keep `INTEGRATION_EVENTS_ENABLED=false`
9. Confirm Finance endpoint with a Bearer token (expect validation 422, not 401).
10. Start queue + scheduler (see below).
11. Set `INTEGRATION_EVENTS_ENABLED=true` and `php artisan config:cache`

Required env:

```
FINANCE_SERVICE_URL=https://arusku.my.id
FINANCE_SERVICE_TOKEN=
FINANCE_SERVICE_TIMEOUT=15
FINANCE_HMAC_SECRET=
INTEGRATION_EVENTS_ENABLED=false
INTEGRATION_QUEUE=integrations
INTEGRATION_MAX_ATTEMPTS=8
INTEGRATION_OUTBOX_RETENTION_DAYS=90
QUEUE_CONNECTION=database
```

## 3. Queue and cron

Preferred (Supervisor or equivalent):

```
php artisan queue:work database --queue=integrations --sleep=1 --tries=1
```

Scheduler (cron every minute):

```
* * * * * cd /path/to/plantation-management && php artisan schedule:run >> /dev/null 2>&1
```

Scheduled commands:

- `integration:dispatch-outbox` every minute
- `integration:prune-outbox` daily (SENT rows older than retention; FAILED is kept)

If Supervisor is unavailable, run both via cron:

```
* * * * * cd /path/to/plantation-management && php artisan integration:dispatch-outbox
* * * * * cd /path/to/plantation-management && php artisan queue:work database --queue=integrations --stop-when-empty --tries=1
```

There is **no automatic backfill** of historical purchases, payroll, or sales.
