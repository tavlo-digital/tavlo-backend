# Customer Pusher, notification, and activity queues

Customer state events are delivered through Pusher Channels while notification persistence and
session-activity logging run outside the HTTP response path on Redis queues. Supabase remains a
dual-publish fallback during rollout.

## Deploy

1. Run `php artisan migrate`.
2. Configure one persistent low-latency worker for Pusher delivery and another for persistence:

   ```bash
   php artisan queue:work redis --queue=realtime --sleep=1 --tries=3 --timeout=60
   php artisan queue:work redis --queue=notifications,activity --sleep=1 --tries=3 --timeout=60
   ```

   Run both workers under Supervisor, systemd, or the hosting provider's persistent worker manager.
   Restart them after every deployment with `php artisan queue:restart`.
3. Create a Pusher Channels application and enable queues and Pusher in the deployed backend
   environment:

   ```dotenv
   QUEUE_CONNECTION=redis
   NOTIFICATIONS_QUEUE_ENABLED=true
   NOTIFICATIONS_QUEUE=notifications
   SESSION_ACTIVITY_QUEUE_ENABLED=true
   SESSION_ACTIVITY_QUEUE=activity
   SESSION_ACTIVITY_RETENTION_DAYS=30
   BROADCAST_CONNECTION=pusher
   CUSTOMER_REALTIME_ENABLED=true
   REALTIME_QUEUE=realtime

   PUSHER_APP_ID=<pusher-app-id>
   PUSHER_APP_KEY=<pusher-app-key>
   PUSHER_APP_SECRET=<pusher-app-secret>
   PUSHER_APP_CLUSTER=<pusher-cluster>
   PUSHER_HOST=
   PUSHER_PORT=443
   PUSHER_SCHEME=https
   PUSHER_CONNECT_TIMEOUT=1
   PUSHER_REQUEST_TIMEOUT=2
   ```
4. Add the public values to the customer frontend build environment:

   ```dotenv
   NEXT_PUBLIC_PUSHER_ENABLED=true
   NEXT_PUBLIC_PUSHER_APP_KEY=<same-pusher-app-key>
   NEXT_PUBLIC_PUSHER_APP_CLUSTER=<same-pusher-cluster>
   ```

   Never copy `PUSHER_APP_SECRET` or `PUSHER_APP_ID` into the customer frontend.
5. Monitor all queues and Pusher delivery:

   ```bash
   php artisan queue:monitor realtime,notifications,activity --max=100
   ```

   Also monitor `failed_jobs`, worker logs, the Pusher dashboard, and end-to-end event latency.
   Retry recoverable failures with `php artisan queue:retry all` after fixing their cause.

Business mutations commit even when a worker or Pusher is temporarily unavailable. Redis retains
queued work while workers are stopped, and the unique notification delivery key prevents retry
duplicates. The initiating customer still applies the mutation response immediately; a tablemate
performs one history recovery request only after a genuine realtime reconnect.

## Request lifecycle

When queue flags are enabled, the response is flushed before Tavlo pushes the small job payload to
Redis. Workers perform participant lookup, Pusher delivery, notification inserts, and activity-log
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
To disable Pusher without changing APIs, set backend `CUSTOMER_REALTIME_ENABLED=false` and frontend
`NEXT_PUBLIC_PUSHER_ENABLED=false`; Supabase remains available. Setting either persistence queue flag
to `false` restores its synchronous compatibility path. Do not remove notification delivery keys
while queued notification jobs remain.
