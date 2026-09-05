// ─── Kitchen Actor ────────────────────────────────────────────────────────
// Polls for waiter-confirmed orders and processes items:
//   mark preparing → wait (cook time) → mark ready → mark order ready

import config from '../config.mjs';
import { api, sleep, randomInt, log, randomUUID } from '../helpers.mjs';

function extractOrdersFromSessions(sessions, statusFilter = null) {
  const orders = [];
  for (const session of sessions) {
    for (const order of (session.orders || [])) {
      if (!statusFilter || order.status === statusFilter) {
        orders.push({
          id: order.id,
          orderId: order.id,
          orderPublicId: order.orderPublicId || order.order_public_id,
          status: order.status,
          items: order.items || [],
        });
      }
    }
  }
  return orders;
}

export async function runKitchen(opts) {
  const {
    actorId,
    kitchenIndex,
    vendorPublicId,
    email,
    password,
    startTime,
    tierDuration,
  } = opts;

  const name = `Kitchen-${kitchenIndex}`;
  function expired() { return Date.now() - startTime > tierDuration; }

  const result = {
    actor: name,
    role: 'kitchen',
    itemsPrepared: 0,
    ordersCompleted: 0,
    errors: [],
    actions: [],
    startedAt: Date.now(),
    finishedAt: null,
  };

  function record(action, success = true, detail = '') {
    result.actions.push({ action, success, detail, at: Date.now() - startTime });
  }

  const processedOrders = new Set();

  // Login
  let token;
  try {
    log(name, `Logging in as ${email}...`);
    const loginRes = await api('POST', '/api/vendor/login', { email, password });
    token = loginRes.token;
    record('login');
    log(name, 'Login OK');
  } catch (err) {
    log(name, `Login FAILED: ${err.message}`);
    result.errors.push({ message: err.message });
    result.finishedAt = Date.now();
    return result;
  }

  // Polling loop
  log(name, 'Starting kitchen polling loop...');

  while (!expired()) {
    const pollInterval = randomInt(...config.kitchenPollIntervalMs);
    await sleep(pollInterval);
    if (expired()) break;

    try {
      // Fetch all orders
      let allOrders = [];
      try {
        const res = await api('GET', `/api/vendor/${vendorPublicId}/orders`, null, token);
        const sessions = res.sessions || [];
        allOrders = extractOrdersFromSessions(sessions);
      } catch (err) {
        if (err.status !== 404) record('poll_orders', false, err.message);
        continue;
      }

      // Process waiter_confirmed and in_progress orders
      const cookableOrders = allOrders.filter(o =>
        ['waiter_confirmed', 'in_progress'].includes(o.status) && !processedOrders.has(o.id)
      );

      for (const order of cookableOrders.slice(0, 2)) {
        if (expired()) break;

        const orderId = order.id;
        log(name, `Processing order ${orderId} (${order.items.length} items)...`);

        // Process each item
        for (const item of order.items) {
          if (expired()) break;

          const itemId = item.cartItemId || item.cart_item_id || item.id;
          const itemStatus = item.status || item.itemStatus || item.item_status;

          // Skip already-processed items
          if (['ready', 'served', 'picked_up'].includes(itemStatus)) continue;

          // Mark as preparing
          if (['new', 'received', undefined].includes(itemStatus)) {
            try {
              await api('PATCH', `/api/vendor/orders/${orderId}/items/${itemId}`, {
                status: 'preparing',
              }, token, 2, { 'Idempotency-Key': randomUUID() });
              record('item_preparing', true, `Order ${orderId}, item ${itemId}`);
            } catch (err) {
              record('item_preparing', false, `${err.message}`);
              continue;
            }
          }

          // Simulate cooking time
          const cookTime = randomInt(...config.kitchenCookTimeMs);
          await sleep(cookTime);
          if (expired()) break;

          // Mark as ready
          try {
            await api('PATCH', `/api/vendor/orders/${orderId}/items/${itemId}`, {
              status: 'ready',
            }, token, 2, { 'Idempotency-Key': randomUUID() });
            result.itemsPrepared++;
            record('item_ready', true, `Order ${orderId}, item ${itemId}`);
          } catch (err) {
            record('item_ready', false, `${err.message}`);
          }
        }

        // Mark entire order as ready
        if (!expired()) {
          try {
            await api('PATCH', `/api/vendor/orders/${orderId}/ready`, {}, token, 2, { 'Idempotency-Key': randomUUID() });
            result.ordersCompleted++;
            processedOrders.add(orderId);
            record('order_ready', true, `Order ${orderId}`);
            log(name, `Order ${orderId} READY`);
          } catch (err) {
            record('order_ready', false, `Order ${orderId}: ${err.message}`);
          }
        }
      }
    } catch (err) {
      result.errors.push({ message: err.message, at: Date.now() - startTime });
      log(name, `Poll error: ${err.message}`);
    }
  }

  log(name, `Done. Items prepared: ${result.itemsPrepared}, Orders completed: ${result.ordersCompleted}`);
  result.finishedAt = Date.now();
  return result;
}
