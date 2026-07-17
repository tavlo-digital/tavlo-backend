# Customer and vendor Pusher queues

Customer state events are delivered through Pusher Channels while notification persistence and
session-activity logging run outside the HTTP response path on Redis queues. Supabase remains a
customer-only dual-publish fallback during rollout. Vendor owner, waiter, and kitchen events use
Pusher exclusively; their notification persistence and broadcast delivery have dedicated queues.

## Deploy

1. Run `php artisan migrate`.
2. Configure persistent workers for customer delivery and isolated vendor operations. Supervisor
   names and queue names below use lowercase alphanumeric characters only:

   | Supervisor name | Queue | Command |
   | --- | --- | --- |
   | `customerrealtime` | `realtime` | `php artisan queue:work redis --queue=realtime --sleep=1 --tries=3 --timeout=60` |
   | `customernotifications` | `notifications` | `php artisan queue:work redis --queue=notifications --sleep=1 --tries=3 --timeout=60` |
   | `customeractivity` | `activity` | `php artisan queue:work redis --queue=activity --sleep=1 --tries=3 --timeout=60` |
   | `customercommands` | `customercommands` | `php artisan queue:work redis --queue=customercommands --sleep=1 --tries=3 --timeout=90` |
   | `staffcommands` | `staffcommands` | `php artisan queue:work redis --queue=staffcommands --sleep=1 --tries=0 --timeout=90` |
   | `vendornotifications` | `vendornotifications` | `php artisan queue:work redis --queue=vendornotifications --sleep=1 --tries=3 --timeout=60` |
   | `vendorrealtime` | `vendorrealtime` | `php artisan queue:work redis --queue=vendorrealtime --sleep=1 --tries=3 --timeout=60` |

   ```bash
   php artisan queue:work redis --queue=realtime --sleep=1 --tries=3 --timeout=60
   php artisan queue:work redis --queue=notifications --sleep=1 --tries=3 --timeout=60
   php artisan queue:work redis --queue=activity --sleep=1 --tries=3 --timeout=60
   php artisan queue:work redis --queue=customercommands --sleep=1 --tries=3 --timeout=90
   php artisan queue:work redis --queue=staffcommands --sleep=1 --tries=0 --timeout=90
   php artisan queue:work redis --queue=vendornotifications --sleep=1 --tries=3 --timeout=60
   php artisan queue:work redis --queue=vendorrealtime --sleep=1 --tries=3 --timeout=60
   ```

   Run all workers under Supervisor, systemd, or the hosting provider's persistent worker manager.
   Restart them after every deployment with `php artisan queue:restart`.

   When upgrading an environment that used the previous hyphenated queue names, either let those
   queues drain before changing the environment values or temporarily run a Supervisor named
   `legacyqueues` for `customer-commands,staff-commands,vendor-notifications,vendor-realtime` until
   Redis reports no pending jobs on them. The Supervisor name remains lowercase alphanumeric even
   though its temporary queue arguments use the legacy names.
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
   VENDOR_REALTIME_ENABLED=true
   VENDOR_QUEUE_CONNECTION=redis
   VENDOR_NOTIFICATIONS_QUEUE=vendornotifications
   VENDOR_REALTIME_QUEUE=vendorrealtime
   CUSTOMER_COMMANDS_QUEUE=customercommands
   STAFF_ASYNC_COMMANDS_ENABLED=true
   STAFF_COMMANDS_CONNECTION=redis
   STAFF_COMMANDS_QUEUE=staffcommands
   STAFF_COMMAND_STATUS_TTL=3600
   STAFF_COMMAND_LOCK_SECONDS=120

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
4. Add the public values to each Pusher frontend build environment:

   ```dotenv
   NEXT_PUBLIC_PUSHER_ENABLED=true
   NEXT_PUBLIC_PUSHER_APP_KEY=<same-pusher-app-key>
   NEXT_PUBLIC_PUSHER_APP_CLUSTER=<same-pusher-cluster>
   ```

   Never copy `PUSHER_APP_SECRET` or `PUSHER_APP_ID` into the customer frontend.
5. Monitor all queues and Pusher delivery:

   ```bash
   php artisan queue:monitor realtime,notifications,activity,customercommands,staffcommands,vendornotifications,vendorrealtime --max=100
   ```

   Also monitor `failed_jobs`, worker logs, the Pusher dashboard, and end-to-end event latency.
   Retry recoverable failures with `php artisan queue:retry all` after fixing their cause.

Business mutations commit even when a worker or Pusher is temporarily unavailable. Redis retains
queued work while workers are stopped, and the unique notification delivery key prevents retry
duplicates. The initiating customer still applies the mutation response immediately; a tablemate
performs one history recovery request only after a genuine realtime reconnect.

Vendor operational work is enqueued only after the domain transaction commits. The
`vendornotifications` worker inserts recipient-scoped rows with an idempotent delivery key, then
the `vendorrealtime` worker broadcasts those persisted rows. Clients deduplicate retries with
`metadata.event_id` and recover role-visible resources after a genuine reconnect.

## Request lifecycle

When queue flags are enabled, the response is flushed before Tavlo pushes the small job payload to
Redis. Workers perform participant lookup, Pusher delivery, notification inserts, and activity-log
queries later. Disabled flags keep the synchronous compatibility path and therefore may take longer.

Staff mutations use a Redis-first command lifecycle when `STAFF_ASYNC_COMMANDS_ENABLED=true`.
During the request, a Redis Lua script atomically reserves the actor-scoped idempotency key, assigns
monotonic sequences to every affected resource, and stores the initial command status. Tavlo then
enqueues `ProcessStaffCommand` on `staffcommands` and returns `202 Accepted` with a command ID and
status URL. Reusing the same key and payload returns the existing command; reusing it for a different
payload returns `409 Conflict`. If Redis or enqueueing is unavailable, the request fails closed with
`503` instead of running the mutation synchronously.

The worker waits for earlier sequences on all affected resources, revalidates the staff actor and
role, and performs the domain mutation in a database transaction. It persists a terminal
`staff_commands` row before updating the Redis terminal status. It then schedules the initiating
waiter or kitchen actor's silent `staff_command_completed` or `staff_command_failed` notification on
`vendornotifications`; that queue persists the notification before `vendorrealtime` broadcasts it.
Clients may poll `GET /api/vendor/commands/{commandId}` and should also treat the terminal Pusher
event as a prompt to reconcile the command status. Redis status and sequencing keys expire after
`STAFF_COMMAND_STATUS_TTL`; the database row remains the status fallback. Normal sequence and
resource-lock releases do not consume a finite attempt budget; the command retry deadline is tied
to that Redis status TTL.

## Realtime policy rollout

The migration installs an authenticated customer policy in addition to the existing vendor, waiter, and kitchen branches. It intentionally leaves the legacy `Allow realtime select` public policy in place for the verification window.

After verifying realtime delivery while signed in as each actor type, remove the unsafe policy:

```bash
php artisan notifications:restrict-realtime --force
```

Do not run that command before customer, vendor, waiter, and kitchen subscriptions have all been verified against the migrated policy.

## Maintenance and rollback

`session-activities:prune` runs daily and deletes activity rows older than the configured retention.
To disable customer Pusher without changing APIs, set backend `CUSTOMER_REALTIME_ENABLED=false` and
frontend `NEXT_PUBLIC_PUSHER_ENABLED=false`; the temporary customer Supabase fallback remains
available. Vendor realtime has no Supabase fallback: setting `VENDOR_REALTIME_ENABLED=false` stops
new vendor Pusher broadcasts while preserving queued/persisted notifications and HTTP recovery.
Do not remove notification delivery keys while queued notification jobs remain.
