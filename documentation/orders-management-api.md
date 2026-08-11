# Orders Management API

## List active orders

`GET /api/vendor/{vendorId}/orders`

Authentication: vendor/staff token authorized for the requested vendor.

Optional query parameters:

- `status`
- `orderType`: `dine-in`, `pickup`, or `takeaway`

The `sessions` collection contains active dine-in table groups. The `takeaway`
collection contains both pickup and takeaway orders; each entry exposes
`orderMode`, `scheduledFor`, `kitchenReleasedAt`, `pickupTime`, and
`pickupStatus`.

Each formatted order includes its `customer` owner and an optional `paidBy`
customer. When `paidBy` is present, waiter clients should attribute the order
and its items to that payer rather than to the owner. Both objects contain a
stable string `id` and display `name`.

Vendor and waiter actors receive every paid pickup/takeaway order immediately.
Kitchen actors also receive paid scheduled pickup orders with a null
`kitchenReleasedAt` so the kitchen Pickup tab can show the future schedule. Such
orders remain excluded from the active preparation queue and cannot be updated
by kitchen staff until released. The initial kitchen Pusher event is silent, so
it updates the schedule without raising a new-order alert.

ASAP orders are released when payment confirms them. Scheduled pickup orders
are released when the pickup time is 20 minutes away; the scheduler runs
`kitchen-orders:release-scheduled` every minute. The release notification uses
the existing `order_confirmed` operational event and includes a complete `order`
snapshot for Pusher reconciliation.

Unpaid card drafts are not returned. A draft with a pending cash request is
returned so staff can collect and confirm the payment, but kitchen clients must
continue to exclude draft orders from their preparation queue.

Example response:

```json
{
  "sessions": [],
  "takeaway": [
    {
      "id": "order-public-id",
      "status": "draft",
      "orderType": "pickup",
      "orderMode": "pickup",
      "scheduledFor": "09.08.2026 23:30",
      "kitchenReleasedAt": null,
      "pickupTime": "2026-08-09T18:30:00.000000Z",
      "pickupStatus": "pending",
      "paymentMethod": "cash",
      "paymentPending": true,
      "paymentReceived": false
    }
  ]
}
```

## Confirm cash payment

`PATCH /api/vendor/orders/{orderId}/confirm-cash`

Authentication: vendor/staff token authorized for the requested vendor.

Request body:

```json
{
  "paymentNote": "Cash received at counter"
}
```

For pickup and takeaway, confirming cash marks every order covered by the cash
payment as paid and changes any covered draft to `confirmed` in the same
transaction. The existing dine-in lifecycle is unchanged.

## Mark pickup/takeaway order picked up

`PATCH /api/vendor/orders/{orderId}/picked-up`

Authentication: vendor token or waiter staff token authorized for the order's
vendor. Waiter requests use the standard staff-command `Idempotency-Key` header.

The order must be pickup/takeaway, paid, and every linked cart item must already
be ready. A successful request marks the order and its linked items picked up,
notifies the customer group, and publishes `order_picked_up` to vendor, waiter,
and kitchen realtime channels.

Example response:

```json
{
  "id": "42",
  "orderType": "pickup",
  "status": "picked_up",
  "displayStatus": "picked-up",
  "pickupStatus": "picked-up",
  "pickedUpAt": "2026-08-09T18:20:00.000000Z",
  "items": [
    {
      "cartItemId": 99,
      "status": "picked_up",
      "pickedUpAt": "2026-08-09T18:20:00.000000Z"
    }
  ]
}
```
