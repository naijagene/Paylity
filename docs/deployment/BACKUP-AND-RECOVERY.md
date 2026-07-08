# Backup Strategy

## What to back up

| Asset | Frequency | Retention |
|-------|-----------|-----------|
| Production database | Daily (minimum) | 30 days |
| `.env` and secrets | On change | Secure vault |
| Application code | Every release | Git tags |
| Uploaded assets (if any) | Daily | 30 days |

## Database backups

### Manual backup

```bash
mysqldump -u <user> -p <database> > paylity-$(date +%F).sql
```

Store dumps off-server (object storage or encrypted backup volume).

### Automated backup

Configure the hosting provider or a cron job:

```cron
0 2 * * * mysqldump ... | gzip > /backups/paylity-$(date +\%F).sql.gz
```

Verify restores monthly.

## Application state

- System settings and feature flags live in the database (included in DB backup).
- Queue `failed_jobs` table is included in DB backup.
- Receipt HTML cache and catalog cache are ephemeral; no separate backup required.

## Secrets

- Never commit `.env` files.
- Store `OPERATOR_ACCESS_KEY`, Paystack, and VTPass credentials in a team password manager or secrets vault.
- Rotate operator key after personnel changes.

## Pre-launch verification

- [ ] Backup job runs successfully
- [ ] Restore tested on staging
- [ ] Backup alerts configured for failures
