// ─── Customer Actor ───────────────────────────────────────────────────────
// Simulates one guest customer's full lifecycle:
//   guest login → scan table → browse menu → add items → confirm order → pay

import { api, sleep, randomInt, randomFrom, randomNote, randomDelay, pickPaymentMethod, log } from '../helpers.mjs';
import config from '../config.mjs';

export async function runCustomer(opts) {
  const {
    actorId,
    vendorPublicId,
    table,          // { id, number, qr_token }
    menu,           // [{ id, name, price, paid_addons, free_addons, modifier_groups }]
    tableIndex,
    customerIndex,
    totalCustomersAtTable,
    tableState,     // shared mutable object: { pin, orderIds, sessions, paidOrders }
    startTime,
    tierDuration,
  } = opts;

  const name = `Customer-T${tableIndex}-C${customerIndex}`;
  const isFirst = customerIndex === 0;
  const isLast = customerIndex === totalCustomersAtTable - 1;

  function expired() { return Date.now() - startTime > tierDuration; }

  let token = null;
  let sessionData = null;
  let myOrderId = null;
  let myOrderPublicId = null;

  const result = {
    actor: name,
    role: 'customer',
    table: tableIndex,
    actions: [],
    errors: [],
    orderCompleted: false,
    paymentCompleted: false,
    startedAt: Date.now(),
    finishedAt: null,
  };

  function record(action, success = true, detail = '') {
    result.actions.push({ action, success, detail, at: Date.now() - startTime });
  }

  try {
    // 1. Create guest account
    log(name, 'Creating guest account...');
    const guestRes = await api('POST', '/api/customer/guest');
    token = guestRes.token;
    record('guest_login');
    log(name, 'Guest account created');

    await sleep(randomInt(500, 2000));
    if (expired()) return result;

    // 2. Browse menu
    log(name, 'Browsing menu...');
    await api('GET', `/api/customer/restaurants/${vendorPublicId}/menu`);
    record('browse_menu');
    await sleep(randomInt(1000, 3000));
    if (expired()) return result;

    // 3. Scan table / join session
    if (isFirst) {
      log(name, `Scanning table ${table.number} (first customer)...`);
      try {
        const scanRes = await api('POST', '/api/customer/table/scan', {
          token: table.qr_token,
        }, token);
        tableState.pin = scanRes.pin || scanRes.session?.pin;
        sessionData = scanRes;
        record('table_scan');
        log(name, `Table scanned, PIN: ${tableState.pin}`);
      } catch (err) {
        // If session already exists, try joining with PIN
        if (err.status === 422 || err.status === 409) {
          log(name, 'Session exists, waiting for PIN...');
          await sleep(2000);
          if (tableState.pin) {
            const pinRes = await api('POST', '/api/customer/table/pin', {
              token: table.qr_token,
              pin: tableState.pin,
            }, token);
            sessionData = pinRes;
            record('table_pin_join');
          } else {
            throw err;
          }
        } else {
          throw err;
        }
      }
    } else {
      // Wait for first customer to scan and get PIN
      log(name, 'Waiting for table PIN...');
      let waitAttempts = 0;
      while (!tableState.pin && waitAttempts < 30) {
        await sleep(1000);
        waitAttempts++;
      }
      if (!tableState.pin) {
        record('table_pin_wait', false, 'Timed out waiting for PIN');
        log(name, 'Timed out waiting for PIN');
        return result;
      }

      log(name, `Joining table with PIN ${tableState.pin}...`);
      try {
        // Scan first — will get 409 "already has active session" which is expected
        let scanRes;
        try {
          scanRes = await api('POST', '/api/customer/table/scan', { token: table.qr_token }, token);
          // If scan succeeds, we're in (first customer may have finished before us)
          sessionData = scanRes;
          record('table_scan_join');
          log(name, 'Joined via scan');
        } catch (scanErr) {
          // 409 = session exists, need PIN — this is the normal path for non-first customers
          if (scanErr.status === 409 || scanErr.status === 422) {
            const pinRes = await api('POST', '/api/customer/table/pin', {
              token: table.qr_token,
              pin: tableState.pin,
            }, token);
            sessionData = pinRes;
            record('table_pin_join');
            log(name, 'Joined via PIN');
          } else {
            throw scanErr;
          }
        }
      } catch (err) {
        record('table_pin_join', false, err.message);
        log(name, `Failed to join table: ${err.message}`);
        return result;
      }
    }

    await sleep(randomInt(1000, 4000));
    if (expired()) return result;

    // 4. Add items to cart
    const numItems = randomInt(config.minItemsPerCustomer, config.maxItemsPerCustomer);
    const selectedItems = [];

    for (let i = 0; i < numItems; i++) {
      if (expired()) break;

      const menuItem = randomFrom(menu);
      const note = randomNote();

      const payload = {
        menu_item_id: menuItem.id,
        quantity: randomInt(1, 2),
        notes: note,
      };

      // Add random paid addons (if available)
      if (menuItem.paid_addons?.length > 0 && Math.random() < 0.3) {
        const addon = randomFrom(menuItem.paid_addons);
        payload.paid_addons = [addon];
      }

      // Add random free addons
      if (menuItem.free_addons?.length > 0 && Math.random() < 0.3) {
        payload.free_addons = [randomFrom(menuItem.free_addons)];
      }

      // Add modifiers if available
      if (menuItem.modifier_groups?.length > 0) {
        payload.selected_modifiers = [];
        for (const group of menuItem.modifier_groups) {
          if (!group.options || group.options.length === 0) continue;
          const numSelections = randomInt(
            group.min_selection || (group.is_required ? 1 : 0),
            Math.min(group.max_selection || group.options.length, group.options.length)
          );
          if (numSelections > 0) {
            const shuffled = [...group.options].sort(() => Math.random() - 0.5);
            payload.selected_modifiers.push({
              modifier_group_id: group.id,
              option_ids: shuffled.slice(0, numSelections).map(o => o.id),
            });
          }
        }
      }

      try {
        log(name, `Adding ${menuItem.name} to cart...`);
        const cartRes = await api('POST', '/api/customer/cart/items', payload, token);
        selectedItems.push({ menuItem, cartItemId: cartRes.item?.id || cartRes.id });
        record('cart_add', true, menuItem.name);
      } catch (err) {
        record('cart_add', false, `${menuItem.name}: ${err.message}`);
        log(name, `Cart add failed: ${err.message}`);
      }

      await sleep(randomInt(2000, 8000));
    }

    if (selectedItems.length === 0) {
      log(name, 'No items added, skipping order');
      result.finishedAt = Date.now();
      return result;
    }

    if (expired()) { result.finishedAt = Date.now(); return result; }

    // 5. Create draft order
    log(name, 'Creating draft order...');
    try {
      const draftRes = await api('POST', '/api/customer/table/order/draft', {}, token);
      // Response: { people: [{ is_me: true, orders: [{ id, order_public_id, ... }] }] }
      const myPerson = draftRes.people?.find(p => p.is_me);
      const myOrder = myPerson?.orders?.[myPerson.orders.length - 1];
      myOrderId = myOrder?.id || draftRes.order?.id || draftRes.id;
      myOrderPublicId = myOrder?.order_public_id || draftRes.order?.order_public_id || draftRes.order_public_id;
      tableState.orderIds.push({ customerId: customerIndex, orderId: myOrderId, orderPublicId: myOrderPublicId });
      record('order_draft');
      log(name, `Draft order created: ${myOrderPublicId} (id=${myOrderId})`);
    } catch (err) {
      record('order_draft', false, err.message);
      log(name, `Draft order failed: ${err.message}`);
      result.finishedAt = Date.now();
      return result;
    }

    await sleep(randomInt(1000, 3000));
    if (expired()) { result.finishedAt = Date.now(); return result; }

    // 6. Randomly share items with tablemates
    if (tableState.orderIds.length > 1 && Math.random() < 0.3) {
      const otherOrder = tableState.orderIds.find(o => o.customerId !== customerIndex);
      if (otherOrder) {
        try {
          log(name, `Sharing items with tablemate...`);
          await api('PUT', `/api/customer/table/order/update/${myOrderId}`, {
            shared_order_ids: [otherOrder.orderId],
          }, token);
          record('item_share');
        } catch (err) {
          record('item_share', false, err.message);
        }
      }
    }

    await sleep(randomInt(1000, 2000));
    if (expired()) { result.finishedAt = Date.now(); return result; }

    // 7. Confirm order
    log(name, 'Confirming order...');
    try {
      await api('POST', '/api/customer/table/order/confirmed', {}, token);
      result.orderCompleted = true;
      record('order_confirm');
      log(name, 'Order confirmed!');
    } catch (err) {
      record('order_confirm', false, err.message);
      log(name, `Order confirm failed: ${err.message}`);
      result.finishedAt = Date.now();
      return result;
    }

    // 8. Wait for kitchen processing (poll order status)
    log(name, 'Waiting for order to be prepared...');
    let orderReady = false;
    let pollAttempts = 0;
    const maxPollAttempts = 60; // ~5 minutes max wait

    while (!orderReady && pollAttempts < maxPollAttempts && !expired()) {
      await sleep(5000);
      pollAttempts++;
      try {
        const statusRes = await api('GET', `/api/customer/orders/${myOrderPublicId}`, null, token);
        const status = statusRes.order?.status || statusRes.status;
        if (['served', 'in_progress', 'waiter_confirmed', 'picked_up'].includes(status)) {
          orderReady = true;
          record('order_ready_poll', true, `Status: ${status} after ${pollAttempts} polls`);
          log(name, `Order status: ${status}`);
        }
      } catch (err) {
        // Ignore polling errors
      }
    }

    if (!orderReady) {
      log(name, 'Order not ready in time, proceeding to payment anyway');
    }

    if (expired()) { result.finishedAt = Date.now(); return result; }

    // 9. Payment
    const paymentMethod = pickPaymentMethod(isLast);
    log(name, `Payment method: ${paymentMethod}`);

    try {
      switch (paymentMethod) {
        case 'cash':
          await api('POST', '/api/customer/payments/request-cash', {}, token);
          result.paymentCompleted = true;
          record('payment_cash');
          log(name, 'Cash payment requested');
          break;

        case 'card':
          log(name, 'Creating Stripe payment intent...');
          const intentRes = await api('POST', '/api/customer/payments/create-intent', {}, token);
          record('payment_create_intent');

          // Add random tip (10-20%)
          if (Math.random() < 0.5 && intentRes.client_secret) {
            const tipAmount = randomInt(100, 500); // cents
            try {
              await api('POST', '/api/customer/payments/update-intent', {
                tip: tipAmount,
              }, token);
              record('payment_add_tip');
            } catch { /* tip is optional */ }
          }

          // In a real test with Stripe test mode, the payment intent
          // would be confirmed client-side. We simulate by verifying.
          await sleep(2000);
          try {
            await api('GET', '/api/customer/payments/verify', null, token);
            record('payment_verify');
          } catch { /* webhook will handle it */ }

          result.paymentCompleted = true;
          log(name, 'Card payment initiated');
          break;

        case 'payForOthers': {
          const otherOrder = tableState.orderIds.find(o => o.customerId !== customerIndex);
          if (otherOrder && !tableState.paidOrders.has(otherOrder.orderId)) {
            try {
              await api('POST', '/api/customer/payments/pay-for', {
                order_id: otherOrder.orderId,
              }, token);
              tableState.paidOrders.add(otherOrder.orderId);
              record('payment_pay_for_other');
              log(name, `Paying for tablemate's order ${otherOrder.orderId}`);
            } catch (err) {
              record('payment_pay_for_other', false, err.message);
              // Fall back to cash
              await api('POST', '/api/customer/payments/request-cash', {}, token);
              record('payment_cash_fallback');
            }
          } else {
            // No eligible order to pay for, fall back to cash
            await api('POST', '/api/customer/payments/request-cash', {}, token);
            record('payment_cash_fallback');
          }
          result.paymentCompleted = true;
          break;
        }

        case 'getCovered':
          // Do nothing — someone else will pay for this customer
          record('payment_get_covered');
          log(name, 'Waiting for someone else to pay');
          result.paymentCompleted = true; // considered "done" from this actor's perspective
          break;
      }
    } catch (err) {
      record('payment', false, `${paymentMethod}: ${err.message}`);
      log(name, `Payment failed: ${err.message}`);
    }

    // 10. Close table (last customer only)
    if (isLast) {
      await sleep(randomInt(3000, 8000));
      if (!expired()) {
        try {
          log(name, 'Closing table session...');
          await api('POST', '/api/customer/table/close', { table_id: table.id }, token);
          record('table_close');
          log(name, 'Table closed');
        } catch (err) {
          record('table_close', false, err.message);
          log(name, `Table close failed: ${err.message}`);
        }
      }
    }
  } catch (err) {
    result.errors.push({ message: err.message, stack: err.stack?.split('\n')[1]?.trim() });
    log(name, `FATAL: ${err.message}`);
  }

  result.finishedAt = Date.now();
  return result;
}
