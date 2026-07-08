# Disaster Recovery

## Recovery objectives (RC1 soft launch)

| Metric | Target |
|--------|--------|
| RPO (data loss) | ≤ 24 hours (daily backups) |
| RTO (restoration) | ≤ 4 hours |

Tighten these targets before full public launch.

## Scenarios

### API host failure

1. Provision replacement host.
2. Deploy last known-good release from Git tag.
3. Restore `.env` from secrets vault.
4. Restore database from latest backup.
5. Run `php artisan migrate --force` if needed.
6. Start queue worker and verify health endpoint.

### Database corruption or loss

1. Enable incident mode immediately.
2. Stop write traffic (maintenance mode if needed).
3. Restore database from latest clean backup.
4. Replay or reconcile transactions from Paystack dashboard.
5. Verify ops reconciliation report before reopening checkout.

### Provider outage (Paystack / VTPass)

1. Enable incident mode if checkout or fulfillment is broadly affected.
2. Disable affected feature flag if partial.
3. Monitor failed transaction and retry reports.
4. Resume after provider confirmation.

### Frontend outage

Customer and ops frontends are independently deployable. Roll back the affected frontend build while API remains available.

## Restore procedure

1. **Assess** — identify scope (API, DB, provider, frontend).
2. **Contain** — incident mode + maintenance mode as needed.
3. **Restore** — database and application from backup/tag.
4. **Validate** — run go-live smoke tests (see `GO-LIVE-SMOKE-TESTS.md`).
5. **Communicate** — clear incident banner, notify stakeholders.
6. **Review** — post-incident notes in operations runbook.

## Contacts and runbooks

- Operations runbook: `docs/launch/OPERATIONS-RUNBOOK.md`
- Rollback guide: `PRODUCTION-ROLLBACK.md`
- Backup strategy: `BACKUP-AND-RECOVERY.md`
