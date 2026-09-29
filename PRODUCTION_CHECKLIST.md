# Production Checklist — CKPN System

## Environment Configuration

- [ ] `.env` production values set:
  - `APP_ENV=production`
  - `APP_DEBUG=false`
  - `DB_HOST`, `DB_USERNAME`, `DB_PASSWORD` (encrypted, not committed)
  - `MAIL_DRIVER=smtp` with production mail service
  - `QUEUE_CONNECTION=redis` or `database` (not `sync`)
  - `CACHE_DRIVER=redis` (for SnapshotCacheService TTL)
  - `LOG_CHANNEL=stack` with Sentry integration (optional)

## Database & Backups

- [ ] MySQL 8.x running with:
  - Character set: `utf8mb4`
  - Default collation: `utf8mb4_unicode_ci`
  - Backups scheduled daily via cron or managed service
  - Backup retention: ≥30 days
  - Test restore procedure quarterly

- [ ] Run migrations:
  ```bash
  php artisan migrate --force
  ```

- [ ] Verify indexes applied:
  ```bash
  php artisan migrate --path=database/migrations/2026_09_29_000000_add_snapshot_indexes.php --force
  ```

## Queue & Job Processing

- [ ] Redis running and accessible from app server:
  - `REDIS_HOST`, `REDIS_PORT` configured
  - Test connection: `redis-cli ping`

- [ ] Queue workers started (systemd/supervisor):
  ```bash
  php artisan queue:work --queue=ckpn-calculation --tries=3 --timeout=1800
  ```
  - Ensure persistent (auto-restart on failure)
  - Monitor via `queue:monitor` or custom dashboard

- [ ] Test job dispatch: `php artisan tinker` → `ReportExportJob::dispatch(...)`

## Error Logging & Monitoring

- [ ] Sentry (or similar) configured for error tracking:
  - `SENTRY_LARAVEL_DSN` set in `.env`
  - Test: `php artisan tinker` → `throw new Exception('test')`

- [ ] Application logs configured:
  - `LOG_CHANNEL=stack` writes to `storage/logs/laravel.log`
  - Log rotation: daily, retain 14 days
  - Monitor disk space for logs

- [ ] Email notifications for critical errors:
  - Configure `MAIL_FROM_ADDRESS` (alerts sent from this)
  - Set alert recipients in config

## Security

- [ ] HTTPS enforced:
  - SSL certificate valid (Let's Encrypt recommended)
  - HSTS header enabled: `Strict-Transport-Security: max-age=31536000`

- [ ] CORS configured for allowed origins:
  - `APP_URL` matches production domain

- [ ] Database credentials encrypted:
  - `.env` not readable by web server
  - DB user has minimal required privileges (no DROP/CREATE)

- [ ] File uploads restricted:
  - Upload directory outside web root if possible
  - Virus scanning on uploaded files (optional)

- [ ] Rate limiting active:
  - API endpoints rate-limited (via middleware)
  - Login attempts limited to 5/minute per IP

## Performance

- [ ] Assets minified & cached:
  - Run `npm run build` (Vite production build)
  - CloudFront or CDN serving static assets (optional)

- [ ] Cache warming (optional):
  - Pre-cache common parameter lookups via `php artisan cache:clear && artisan ckpn:warm-cache` (custom)

- [ ] Query optimization verified:
  - Composite indexes applied (Phase 26 migration)
  - No N+1 queries in critical paths (verified via debugbar/telescopeDev env or logs)

## Monitoring & Alerts

- [ ] Health check endpoint created:
  ```bash
  GET /health → { "status": "ok", "database": "ok", "redis": "ok" }
  ```

- [ ] Monitoring dashboard setup (New Relic, Datadog, or custom):
  - CPU, memory, disk space alerts
  - Queue depth monitoring (alert if > 1000 pending)
  - Database connection pool exhaustion alert

- [ ] Daily report generation via cron:
  - `0 1 * * * php /var/www/html/artisan reports:generate-daily` (1 AM daily)
  - Logs to `storage/logs/reports.log`

## User Access & Permissions

- [ ] Filament Shield roles configured:
  - `super_admin`: full access
  - `risk_analyst`: read/write calculations, view results
  - `approver`: approve completed calculations
  - `viewer`: read-only access to results

- [ ] Admin user created with strong password:
  - `php artisan tinker` → `User::create([...])`
  - Force password reset on first login (optional)

- [ ] 2FA enabled for admin users (optional but recommended)

## Backup & Disaster Recovery

- [ ] Database backup strategy:
  - Daily full backup + hourly incremental
  - Offsite backup storage (S3, Azure Blob, etc.)
  - Test restore monthly

- [ ] Application code version control:
  - All code in Git repo with tags for releases
  - CI/CD pipeline for automated deployments

- [ ] Disaster recovery plan documented:
  - RTO (Recovery Time Objective): max 2 hours
  - RPO (Recovery Point Objective): max 1 hour
  - Failover procedures tested

## Testing Before Go-Live

- [ ] Full regression test suite passes:
  ```bash
  php artisan test --parallel
  ```

- [ ] Load testing completed (optional):
  - Simulate 100 concurrent users
  - Verify queue handles batch jobs without backlog

- [ ] UAT sign-off from business stakeholders

## Go-Live Checklist

- [ ] Production DNS records updated
- [ ] SSL certificate deployed and tested
- [ ] Load balancer health checks configured
- [ ] Monitoring dashboards live and alerting
- [ ] Incident response team on-call
- [ ] Runbook documentation accessible
- [ ] Rollback plan ready (revert to previous version if needed)

## Post-Go-Live (First 24 Hours)

- [ ] Monitor all error logs & alerts continuously
- [ ] Verify data integrity (random spot-checks of calculations)
- [ ] Test all user workflows (upload, calculate, export, approve)
- [ ] Document any issues & patches applied
- [ ] Team debrief at end of day 1

---

**Last Updated**: 2026-09-29
**Status**: Ready for production
