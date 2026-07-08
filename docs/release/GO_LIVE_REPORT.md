# PAYLITY RC1 Go-Live Report

**Release:** RC1 Soft Launch  
**Date:** 2026-07-08  
**Scope:** PAY-024 Production Hardening & Go-Live Readiness

## Executive summary

PAYLITY has been hardened for RC1 soft launch across infrastructure, security, performance, monitoring, operations, deployment documentation, backup/recovery, and validation. No new customer-facing business features were introduced.

## Workstream status

| Workstream | Status | Notes |
|------------|--------|-------|
| Infrastructure hardening | Complete | `PaylityEnvironmentValidator`, enhanced `/health`, queue metrics |
| Security hardening | Complete | Security headers, session cookie defaults, preflight secrets validation, rate limits |
| Performance | Complete | Transaction indexes, catalog/receipt caching, API gzip compression |
| Monitoring | Complete | Incident mode, customer/ops banners, checkout block, queue metrics |
| Operations | Complete | Reconciliation, failed, settlement, retry reports with CSV export |
| Deployment docs | Complete | Checklist, rollback, environment, queue, server guides |
| Backup & recovery | Complete | Backup strategy, DR plan, restore procedures |
| Go-live validation | Complete | `GoLiveSmokeTest`, smoke test documentation |

## Key deliverables

### API (`apps/api`)

- `PaylityEnvironmentValidator` — centralized startup/preflight validation
- `HealthCheckService` — database, cache, queue, mail, Paystack, VTPass checks; HTTP 503 when degraded
- `GET /api/v1/platform/status` — public checkout availability
- `incident_mode` system setting — blocks checkout with `INCIDENT_MODE` error code
- `SecurityHeadersMiddleware` — CSP, HSTS, X-Frame-Options, Referrer-Policy, Permissions-Policy, X-Content-Type-Options
- `CompressApiResponseMiddleware` — gzip for large JSON responses
- Ops reports: daily reconciliation, failed transactions, settlement summary, retry summary
- Queue metrics in ops monitoring
- Performance indexes on `transactions`
- Catalog and receipt HTML caching

### Customer frontend (`apps/web`)

- `IncidentModeBanner` on all pages via root providers
- Proactive checkout block when incident or maintenance mode is active

### Ops console (`apps/ops`)

- Executive dashboard incident/maintenance warning banner
- Incident mode toggle on platform page
- Expanded reports with CSV exports
- Queue health card from live monitoring data

## Test results

Run before deploy:

```bash
cd apps/api && php artisan test
cd apps/web && npm run lint && npm run test && npm run build
cd apps/ops && npm run lint && npm run test && npm run build
```

Automated smoke suite: `GoLiveSmokeTest`, `ProductionHardeningTest`, `PreLaunchHardeningTest`.

## Pre-launch checklist

- [ ] `php artisan paylity:preflight` — 0 FAIL
- [ ] `incident_mode` and `maintenance_mode` disabled
- [ ] Paystack/VTPass credentials verified for launch environment
- [ ] Backup job verified
- [ ] Operator access key distributed securely
- [ ] Smoke tests pass on target environment

## Documentation index

- [Production Deployment Checklist](../deployment/PRODUCTION-DEPLOYMENT-CHECKLIST.md)
- [Production Rollback](../deployment/PRODUCTION-ROLLBACK.md)
- [Production Environment](../deployment/PRODUCTION-ENVIRONMENT.md)
- [Queue Operations](../deployment/QUEUE-OPERATIONS.md)
- [Server Operations](../deployment/SERVER-OPERATIONS.md)
- [Backup & Recovery](../deployment/BACKUP-AND-RECOVERY.md)
- [Disaster Recovery](../deployment/DISASTER-RECOVERY.md)
- [Go-Live Smoke Tests](../deployment/GO-LIVE-SMOKE-TESTS.md)

## Sign-off

| Role | Status | Date |
|------|--------|------|
| Engineering | Ready for RC1 soft launch | 2026-07-08 |
| Operations | Pending environment verification | |
| Product | Pending soft launch approval | |

## Suggested commit

```
chore(release): harden platform for RC1 soft launch
```
