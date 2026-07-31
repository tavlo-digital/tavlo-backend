// ─── Waiter Actor ─────────────────────────────────────────────────────────
// Polls for new confirmed orders and processes them:
//   waiter-confirm → watch for ready items → serve → confirm cash

import { api, sleep, randomInt, log, randomUUID } from '../helpers.mjs';
import config from '../config.mjs';

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
          paymentMethod: order.paymentMethod || order.payment_method,
          paymentPending: order.paymentPending ?? order.payment_pending,
          paymentReceived: order.paymentReceived ?? order.payment_received,
          sessionId: session.sessionId,
        });
      }
    }
  }
  return orders;
}

export async function runWaiter(opts) {
  const {
    actorId,
    waiterIndex,
    vendorPublicId,
    email,
    password,
    startTime,
    tierDuration,
  } = opts;

  const name = `Waiter-${waiterIndex}`;
  function expired() { return Date.now() - startTime > tierDuration; }

  const result = {
    actor: name,
    role: 'waiter',
    ordersConfirmed: 0,
    ordersServed: 0,
    cashConfirmed: 0,
    errors: [],
    actions: [],
    startedAt: Date.now(),
    finishedAt: null,
  };

  function record(action, success = true, detail = '') {
    result.actions.push({ action, success, detail, at: Date.now() - startTime });
  }

  const processedConfirm = new Set();
  const processedServe = new Set();

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
  log(name, 'Starting polling loop...');

  while (!expired()) {
    const pollInterval = randomInt(...config.waiterPollIntervalMs);
    await sleep(pollInterval);
    if (expired()) break;

    try {
      // Fetch all orders (grouped by session)
      let allOrders = [];
      try {
        const res = await api('GET', `/api/vendor/${vendorPublicId}/orders`, null, token);
        const sessions = res.sessions || [];
        allOrders = extractOrdersFromSessions(sessions);
      } catch (err) {
        if (err.status !== 404) record('poll_orders', false, err.message);
        continue;
      }

      // Waiter-confirm orders with status "confirmed"
      const confirmedOrders = allOrders.filter(o => o.status === 'confirmed' && !processedConfirm.has(o.id));
      for (const order of confirmedOrders.slice(0, 3)) {
        if (expired()) break;
        try {
          await api('PATCH', `/api/vendor/orders/${order.id}/confirm`, {}, token, 2, { 'Idempotency-Key': randomUUID() });
          result.ordersConfirmed++;
          processedConfirm.add(order.id);
          record('order_waiter_confirm', true, `Order ${order.id}`);
          log(name, `Confirmed order ${order.id}`);
        } catch (err) {
          record('order_waiter_confirm', false, `Order ${order.id}: ${err.message}`);
        }
        await sleep(randomInt(300, 800));
      }

      // Serve ready items for in_progress / waiter_confirmed orders
      const servableOrders = allOrders.filter(o =>
        ['in_progress', 'waiter_confirmed'].includes(o.status) && !processedServe.has(o.id)
      );
      for (const order of servableOrders.slice(0, 3)) {
        if (expired()) break;

        // Try serving ready items
        try {
          await api('PATCH', `/api/vendor/orders/${order.id}/items/serve-ready`, {}, token, 2, { 'Idempotency-Key': randomUUID() });
          record('serve_ready_items', true, `Order ${order.id}`);
        } catch {
          // No ready items
        }

        // Try marking as served
        try {
          await api('PATCH', `/api/vendor/orders/${order.id}/served`, {}, token, 2, { 'Idempotency-Key': randomUUID() });
          result.ordersServed++;
          processedServe.add(order.id);
          record('order_served', true, `Order ${order.id}`);
          log(name, `Served order ${order.id}`);
        } catch {
          // Not all items ready yet
        }

        await sleep(randomInt(200, 500));
      }

      // Confirm cash payments (payment_pending + payment_method=cash)
      const cashOrders = allOrders.filter(o =>
        (o.paymentPending || o.paymentMethod === 'cash') && !o.paymentReceived
      );
      for (const order of cashOrders.slice(0, 3)) {
        if (expired()) break;
        try {
          await api('PATCH', `/api/vendor/orders/${order.id}/confirm-cash`, {}, token, 2, { 'Idempotency-Key': randomUUID() });
          result.cashConfirmed++;
          record('confirm_cash', true, `Order ${order.id}`);
          log(name, `Cash confirmed for order ${order.id}`);
        } catch (err) {
          record('confirm_cash', false, `Order ${order.id}: ${err.message}`);
        }
      }

    } catch (err) {
      result.errors.push({ message: err.message, at: Date.now() - startTime });
      log(name, `Poll error: ${err.message}`);
    }
  }

  log(name, `Done. Confirmed: ${result.ordersConfirmed}, Served: ${result.ordersServed}, Cash: ${result.cashConfirmed}`);
  result.finishedAt = Date.now();
  return result;
}
