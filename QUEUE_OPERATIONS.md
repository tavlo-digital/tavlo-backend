# Customer Reverb, notification, and activity queues

Customer state events can be delivered through Laravel Reverb while notification persistence and
session-activity logging run outside the HTTP response path on Redis queues. Supabase remains a
dual-publish fallback until the Reverb rollout has been verified.

## Deploy

1. Run `php artisan migrate`.
2. Configure one persistent low-latency worker for Reverb delivery and another for persistence:

   ```bash
   php artisan queue:work redis --queue=realtime --sleep=1 --tries=3 --timeout=60
   php artisan queue:work redis --queue=notifications,activity --sleep=1 --tries=3 --timeout=60
   ```

   Run both workers under Supervisor, systemd, or the hosting provider's persistent worker manager.
   Restart them after every deployment with `php artisan queue:restart`.
3. Run Reverb itself as a separate persistent Supervisor process:

   ```bash
   php artisan reverb:start --host=0.0.0.0 --port=8080
   ```

   Proxy the public WSS hostname/port 443 to `127.0.0.1:8080`. The deploy workflow calls
   `php artisan reverb:restart`; it intentionally does not start a temporary deployment process.
4. Enable queues and Reverb in the deployed backend environment:

   ```dotenv
   QUEUE_CONNECTION=redis
   NOTIFICATIONS_QUEUE_ENABLED=true
   NOTIFICATIONS_QUEUE=notifications
   SESSION_ACTIVITY_QUEUE_ENABLED=true
   SESSION_ACTIVITY_QUEUE=activity
   SESSION_ACTIVITY_RETENTION_DAYS=30
   BROADCAST_CONNECTION=reverb
   CUSTOMER_REVERB_ENABLED=true
   REALTIME_QUEUE=realtime

   REVERB_APP_ID=tavlo-staging
   REVERB_APP_KEY=<public-random-key>
   REVERB_APP_SECRET=<private-random-secret>
   REVERB_HOST=<public-wss-hostname>
   REVERB_PORT=443
   REVERB_SCHEME=https
   REVERB_SERVER_HOST=0.0.0.0
   REVERB_SERVER_PORT=8080
   REVERB_ALLOWED_ORIGINS=https://<customer-hostname>
   ```
5. Add the public values to the customer frontend build environment:

   ```dotenv
   NEXT_PUBLIC_REVERB_ENABLED=true
   NEXT_PUBLIC_REVERB_APP_KEY=<same-public-random-key>
   NEXT_PUBLIC_REVERB_HOST=<public-wss-hostname>
   NEXT_PUBLIC_REVERB_PORT=443
   NEXT_PUBLIC_REVERB_SCHEME=https
   ```

   Never copy `REVERB_APP_SECRET` into the customer frontend.
6. Monitor all queues and the Reverb process:

   ```bash
   php artisan queue:monitor realtime,notifications,activity --max=100
   ```

   Also monitor `failed_jobs`, worker logs, Reverb connection logs, and end-to-end event latency.
   Retry recoverable failures with `php artisan queue:retry all` after fixing their cause.

Business mutations commit even when a worker or Reverb is temporarily unavailable. Redis retains
queued work while workers are stopped, and the unique notification delivery key prevents retry
duplicates. The initiating customer still applies the mutation response immediately; a tablemate
performs one history recovery request only after a genuine realtime reconnect.

## Request lifecycle

When queue flags are enabled, the response is flushed before Tavlo pushes the small job payload to
Redis. Workers perform participant lookup, Reverb delivery, notification inserts, and activity-log
queries later. Disabled flags keep the synchronous compatibility path and therefore may take longer.

## Realtime policy rollout

The migration installs an authenticated customer policy in addition to the existing vendor, waiter, and kitchen branches. It intentionally leaves the legacy `Allow realtime select` public policy in place for the verification window.

After verifying realtime delivery while signed in as each actor type, remove the unsafe policy:

```bash
php artisan notifications:restrict-realtime --force
```

Do not run that command before customer, vendor, waiter, and kitchen subscriptions have all been verified against the migrated policy.

## Maintenance and rollback

`session-activities:prune` runs daily and deletes activity rows older than the configured retention.
To disable Reverb without changing APIs, set backend `CUSTOMER_REVERB_ENABLED=false` and frontend
`NEXT_PUBLIC_REVERB_ENABLED=false`; Supabase remains available. Setting either persistence queue flag
to `false` restores its synchronous compatibility path. Do not remove notification delivery keys
while queued notification jobs remain.
