# Customer API

## Pickup and takeaway order sessions

Pickup and takeaway reuse the table-session, cart, order-history, sharing, and
payment routes. Every request in an off-premise flow must include one of these
headers:

```http
X-Order-Mode: pickup
```

```http
X-Order-Mode: takeaway
```

Without the header, the existing dine-in behavior is used. Pickup sessions are
resolved from `vendor_public_id` and may include `scheduled_for`. Takeaway
sessions are resolved from the vendor QR `token` and are always ASAP. Group
membership is scoped by vendor, order mode, and the four-digit session PIN.

### Resolve an ordering link

`GET /api/customer/table/status`

Authentication: none.

Query parameters:

- Pickup: `vendor_public_id`
- Takeaway: `token`

Example response:

```json
{
  "table": null,
  "vendor": {
    "id": "vendor-public-id",
    "name": "Tavlo Kitchen",
    "slug": "tavlo-kitchen"
  },
  "status": "available",
  "orderMode": "takeaway"
}
```

`vendor.slug` is always a non-empty, unique URL slug. Legacy vendor rows are
backfilled during deployment and new vendors receive a slug automatically.

For compatibility, older QR redirect clients may resolve a takeaway token with
`GET /api/customer/pickup/status?token={token}`. It returns the same vendor
identity plus `"type": "takeaway"`; new clients should continue using the
shared `/table/status` route above.

### Start a session

`POST /api/customer/table/scan`

Authentication: customer Sanctum token.

Pickup request body:

```json
{
  "vendor_public_id": "vendor-public-id",
  "scheduled_for": "2026-08-09T18:30:00.000Z"
}
```

Set `scheduled_for` to `null` for ASAP. Takeaway request body:

```json
{
  "token": "vendor-takeaway-qr-token"
}
```

Takeaway QR sessions are always stored as ASAP. The server ignores any
`scheduled_for` value sent by a stale client when `X-Order-Mode: takeaway`.

Example `201` response:

```json
{
  "pin": "4821",
  "session": {
    "id": "123",
    "status": "active",
    "type": "pickup",
    "pin": "4821",
    "scheduledFor": "09.08.2026 23:30"
  },
  "table": null,
  "vendor": {
    "id": "vendor-public-id",
    "name": "Tavlo Kitchen",
    "slug": "tavlo-kitchen"
  }
}
```

### Join a session with a PIN

`POST /api/customer/table/pin`

Authentication: customer Sanctum token.

Request body uses the same target as start-session plus the PIN:

```json
{
  "vendor_public_id": "vendor-public-id",
  "pin": "4821"
}
```

For takeaway, send `token` instead of `vendor_public_id`. The response has the
same shape as start-session and copies the group schedule.

### Session-dependent routes

The following existing authenticated routes use the same `X-Order-Mode` header
and active group session:

- `GET /api/customer/table/session/status`
- `POST /api/customer/table/close`
- `GET /api/customer/cart`
- `POST /api/customer/cart/items`
- `PATCH /api/customer/cart/items/{id}`
- `DELETE /api/customer/cart/items/{id}`
- `POST /api/customer/table/order/draft`
- `GET /api/customer/table/history`
- `POST /api/customer/payments/pay-for`
- `POST /api/customer/payments/release-pay-for`
- `POST /api/customer/payments/create-intent`
- `POST /api/customer/payments/request-cash`
- `GET /api/customer/payments/verify`

Confirming the cart creates a draft pickup/takeaway order. Group members see
each other's drafts and can share items or pay for another member. A successful
card payment, or vendor confirmation of a pending cash payment, marks the order
paid and confirms it in the same transaction. Dine-in draft and confirmation
behavior is unchanged.
