# Notification and activity queues

Customer notification fan-out and session-activity logging can run outside the HTTP response path on Laravel's database queue.

## Deploy

1. Run `php artisan migrate`.
2. Start persistent workers before enabling either feature flag:

   ```bash
   php artisan queue:work database --queue=notifications,activity --tries=3 --timeout=60
   ```

   Run the worker under Supervisor, systemd, or the hosting provider's persistent worker manager. Restart workers after every deployment with `php artisan queue:restart`.
3. Enable the queues in production:

   ```dotenv
   QUEUE_CONNECTION=database
   NOTIFICATIONS_QUEUE_ENABLED=true
   NOTIFICATIONS_QUEUE=notifications
   SESSION_ACTIVITY_QUEUE_ENABLED=true
   SESSION_ACTIVITY_QUEUE=activity
   SESSION_ACTIVITY_RETENTION_DAYS=30
   ```
4. Monitor `jobs`, `failed_jobs`, queue-worker logs, and notification delivery latency. Retry recoverable failures with `php artisan queue:retry all` after fixing their cause.

Business mutations commit even when a worker is temporarily unavailable. Queued jobs remain in `jobs`; when the worker resumes, the unique notification delivery key prevents retry duplicates.

## Realtime policy rollout

The migration installs an authenticated customer policy in addition to the existing vendor, waiter, and kitchen branches. It intentionally leaves the legacy `Allow realtime select` public policy in place for the verification window.

After verifying realtime delivery while signed in as each actor type, remove the unsafe policy:

```bash
php artisan notifications:restrict-realtime --force
```

Do not run that command before customer, vendor, waiter, and kitchen subscriptions have all been verified against the migrated policy.

## Maintenance and rollback

`session-activities:prune` runs daily and deletes activity rows older than the configured retention. To stop asynchronous delivery without losing business operations, set both queue feature flags to `false`; notification and activity writes then use the synchronous compatibility path. Do not roll back the delivery-key migration while queued notification jobs remain.
