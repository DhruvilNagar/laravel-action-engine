# Production Deployment Checklist

Use this checklist when deploying Laravel Action Engine improvements to production.

## Pre-Deployment

### Code Quality
- [ ] All tests passing locally (`composer test`)
- [ ] PHPStan analysis clean (`composer analyse`)
- [ ] Code formatted with Pint (`composer format`)
- [ ] CI/CD pipeline passing on GitHub
- [ ] Code coverage at acceptable level (target: 80%+)

### Configuration Review
- [ ] Review `config/action-engine.php` settings
- [ ] Set appropriate batch sizes for your server capacity
- [ ] Configure queue settings (connection, name)
- [ ] Set undo expiry days appropriately
- [ ] Configure monitoring driver (Sentry, Datadog, etc.)
- [ ] Enable/disable safety features as needed
- [ ] Set rate limiting thresholds
- [ ] Configure export settings

### Database Preparation
- [ ] Run new migration: `php artisan migrate`
- [ ] Verify all indexes created successfully
- [ ] Check table sizes and disk space
- [ ] Backup database before adding indexes to large tables
- [ ] Test migration rollback in staging
- [ ] Review query performance after indexes

### Environment Variables
```bash
# Required
ACTION_ENGINE_QUEUE_CONNECTION=redis
ACTION_ENGINE_QUEUE_NAME=bulk-actions

# Optional but recommended
ACTION_ENGINE_BATCH_SIZE=500
ACTION_ENGINE_MONITORING_ENABLED=true
ACTION_ENGINE_MONITORING_DRIVER=log # or sentry, datadog, etc.
ACTION_ENGINE_AUDIT_ENABLED=true

# Safety features
ACTION_ENGINE_UNDO_EXPIRY_DAYS=7

# Monitoring integrations
SENTRY_ENABLED=true
SENTRY_LARAVEL_DSN=your-dsn-here
```

## Deployment Steps

### 1. Staging Deployment
- [ ] Deploy to staging environment
- [ ] Run migrations
- [ ] Test basic bulk action execution
- [ ] Verify progress tracking works
- [ ] Test undo functionality
- [ ] Check monitoring integration
- [ ] Review logs for errors
- [ ] Perform load testing
- [ ] Verify safety features (confirmation prompts)

### 2. Monitoring Setup
- [ ] Configure Telescope (if using)
- [ ] Set up Sentry/Bugsnag error tracking
- [ ] Configure Prometheus/Datadog metrics
- [ ] Set up alerting rules
- [ ] Test health check endpoint
- [ ] Create monitoring dashboard
- [ ] Set up log aggregation

### 3. Queue Configuration
- [ ] Verify queue workers are running
- [ ] Check supervisor configuration
- [ ] Test queue failure handling
- [ ] Monitor queue depth
- [ ] Set up queue monitoring alerts
- [ ] Test job retry mechanism

### 4. Performance Validation
- [ ] Run performance benchmarks
- [ ] Check memory usage under load
- [ ] Monitor database query times
- [ ] Verify cache is working
- [ ] Test with production-like data volumes
- [ ] Check disk space for exports/snapshots

### 5. Safety Testing
- [ ] Test confirmation prompts for destructive actions
- [ ] Verify soft delete before hard delete
- [ ] Test record locking
- [ ] Verify automatic rollback on failures
- [ ] Test dry run mode
- [ ] Check undo expiration

## Production Deployment

### Before Deployment
- [ ] Schedule maintenance window (if needed)
- [ ] Notify team and stakeholders
- [ ] Prepare rollback plan
- [ ] Take full database backup
- [ ] Document current state

### During Deployment
- [ ] Put application in maintenance mode (if needed)
- [ ] Deploy code changes
- [ ] Run migrations
- [ ] Clear caches: `php artisan optimize:clear`
- [ ] Restart queue workers
- [ ] Restart PHP-FPM/web server
- [ ] Take application out of maintenance mode

### Immediately After Deployment
- [ ] Verify application is accessible
- [ ] Check for errors in logs
- [ ] Test basic functionality
- [ ] Monitor queue processing
- [ ] Check monitoring dashboards
- [ ] Verify health check endpoint
- [ ] Test one small bulk action

## Post-Deployment Monitoring (First 24 Hours)

### Hour 1
- [ ] Monitor error rates in Sentry/Bugsnag
- [ ] Check queue depth and processing rate
- [ ] Review application logs
- [ ] Monitor database performance
- [ ] Check memory usage
- [ ] Verify monitoring is collecting data

### Hour 4
- [ ] Review any error patterns
- [ ] Check for stuck executions
- [ ] Monitor failed jobs
- [ ] Review user feedback (if any)
- [ ] Check disk space usage

### Hour 12
- [ ] Analyze performance trends
- [ ] Review all monitoring dashboards
- [ ] Check undo data accumulation
- [ ] Verify cleanup jobs are scheduled
- [ ] Review audit logs

### Hour 24
- [ ] Full system health check
- [ ] Performance comparison with baseline
- [ ] Review any incidents
- [ ] Document lessons learned
- [ ] Plan any immediate improvements

## Ongoing Maintenance

### Daily
- [ ] Check error rates
- [ ] Monitor queue depth
- [ ] Review failed jobs
- [ ] Check disk space

### Weekly
- [ ] Review performance metrics
- [ ] Check for stuck executions
- [ ] Review audit logs
- [ ] Monitor database growth
- [ ] Check failed jobs patterns

### Monthly
- [ ] Run cleanup: `php artisan action-engine:cleanup`
- [ ] Review and archive audit logs
- [ ] Optimize database tables
- [ ] Review and update configuration
- [ ] Check for package updates
- [ ] Review monitoring alerts effectiveness

## Troubleshooting

### If Something Goes Wrong

1. **Check the logs first:**
```bash
tail -f storage/logs/laravel.log
```

2. **Check health status:**
```bash
php artisan action-engine:health
```

3. **Review failed jobs:**
```bash
php artisan queue:failed
```

4. **Check queue workers:**
```bash
php artisan queue:monitor
```

5. **Emergency rollback:**
```bash
# Roll back code
git checkout previous-version
composer install --no-dev --optimize-autoloader

# Roll back migrations
php artisan migrate:rollback

# Clear caches
php artisan optimize:clear

# Restart services
sudo supervisorctl restart all
```

## Rollback Plan

### Quick Rollback (< 5 minutes)
If critical issues discovered immediately:
1. Restore previous code version
2. Roll back migrations
3. Restart services
4. Monitor for stability

### Full Rollback (5-15 minutes)
If persistent issues:
1. Put application in maintenance mode
2. Restore database from backup
3. Restore previous code version
4. Clear all caches
5. Restart all services
6. Verify functionality
7. Remove maintenance mode
8. Document issues for analysis

## Success Criteria

Deployment is successful if:
- [ ] All critical functionality works
- [ ] No increase in error rates
- [ ] Queue processing normally
- [ ] Response times within acceptable range
- [ ] Memory usage stable
- [ ] No data loss or corruption
- [ ] Monitoring collecting data
- [ ] Alerts configured and working

## Communication

### Stakeholders to Notify
- [ ] Development team
- [ ] Operations team
- [ ] Product team
- [ ] Support team
- [ ] End users (if needed)

### Information to Share
- Deployment time and duration
- New features available
- Known issues (if any)
- Expected impacts
- How to report problems

## Documentation Updates

- [ ] Update README with badges
- [ ] Update CHANGELOG
- [ ] Document any configuration changes
- [ ] Update deployment documentation
- [ ] Share performance benchmarks
- [ ] Document any issues encountered

---

## Notes

Use this space to document deployment-specific notes:

**Date:**
**Deployed By:**
**Version:**
**Environment:**

**Issues Encountered:**


**Resolutions Applied:**


**Performance Observations:**


**Next Steps:**
