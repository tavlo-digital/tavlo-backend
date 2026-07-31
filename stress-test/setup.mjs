#!/usr/bin/env node
// ─── Setup: Discover server state & verify test data ──────────────────────
// Run this ONCE before the stress test. It:
//   1. Verifies API is reachable
//   2. Logs in as each vendor to verify credentials
//   3. Fetches menus, tables, and verifies staff logins
//   4. Writes results/test-data.json for the runner

import { api, log } from './helpers.mjs';
import config from './config.mjs';
import { writeFileSync, mkdirSync } from 'fs';

const testData = { vendors: {}, generatedAt: new Date().toISOString() };

async function discoverVendor(key, vendorConfig) {
  log('setup', `Discovering ${key} (${vendorConfig.publicId})...`);
  const vendorId = vendorConfig.publicId;

  // Vendor login
  let vendorToken;
  try {
    const res = await api('POST', '/api/vendor/login', {
      email: vendorConfig.email,
      password: vendorConfig.password,
    });
    vendorToken = res.token;
    log('setup', `  ✓ Vendor login OK`);
  } catch (err) {
    log('setup', `  ✗ Vendor login FAILED: ${err.message}`);
    return null;
  }

  // Fetch menu via customer API (public)
  let menu = [];
  try {
    const menuRes = await api('GET', `/api/customer/restaurants/${vendorId}/menu`);
    // Response can be flat array of items or nested { categories: [...] }
    const items = Array.isArray(menuRes) ? menuRes
      : menuRes.categories ? menuRes.categories.flatMap(c => c.items || [])
      : menuRes.data ? (Array.isArray(menuRes.data) ? menuRes.data : [])
      : [];

    for (const item of items) {
      menu.push({
        id: item.id,
        name: item.name,
        price: item.price,
        paid_addons: item.paid_addons || [],
        free_addons: item.free_addons || [],
        removable_items: item.removable_items || [],
        modifier_groups: item.modifier_groups || [],
      });
    }
    log('setup', `  ✓ Menu: ${menu.length} items`);
  } catch (err) {
    log('setup', `  ⚠ Menu fetch failed: ${err.message}`);
  }

  // Fetch tables
  let tables = [];
  try {
    const tablesRes = await api('GET', `/api/vendor/${vendorId}/tables`, null, vendorToken);
    const raw = Array.isArray(tablesRes) ? tablesRes : tablesRes.tables || tablesRes.data || [];
    tables = raw
      .filter(t => t.is_active !== false && t.isActive !== false)
      .map(t => ({ id: t.id, number: t.number, name: t.name, qr_token: t.qr_token || t.qrToken }));
    log('setup', `  ✓ Tables: ${tables.length} active`);
  } catch (err) {
    log('setup', `  ⚠ Tables fetch failed: ${err.message}`);
  }

  // Staff credentials from config
  const staffConfig = config.staff[key];
  if (!staffConfig) {
    log('setup', `  ⚠ No staff config for ${key}`);
    return { key, vendorId, vendorToken, menu, tables, staff: [], waiters: [], kitchen: [] };
  }

  const staffCredentials = [];

  // Verify waiter logins
  for (const email of staffConfig.waiters) {
    try {
      const res = await api('POST', '/api/vendor/login', {
        email,
        password: config.staff.staffPassword,
      });
      staffCredentials.push({ email, role: 'waiter', password: config.staff.staffPassword, token: res.token });
      log('setup', `  ✓ Waiter ${email} login OK`);
    } catch (err) {
      log('setup', `  ✗ Waiter ${email} login FAILED: ${err.message}`);
    }
  }

  // Verify kitchen logins
  for (const email of staffConfig.kitchen) {
    try {
      const res = await api('POST', '/api/vendor/login', {
        email,
        password: config.staff.staffPassword,
      });
      staffCredentials.push({ email, role: 'kitchen', password: config.staff.staffPassword, token: res.token });
      log('setup', `  ✓ Kitchen ${email} login OK`);
    } catch (err) {
      log('setup', `  ✗ Kitchen ${email} login FAILED: ${err.message}`);
    }
  }

  return {
    key,
    vendorId,
    vendorToken,
    email: vendorConfig.email,
    menu,
    tables,
    staff: staffCredentials,
    waiters: staffCredentials.filter(s => s.role === 'waiter'),
    kitchen: staffCredentials.filter(s => s.role === 'kitchen'),
  };
}

async function main() {
  log('setup', '═══════════════════════════════════════════════');
  log('setup', '  Tavlo Stress Test — Setup & Discovery');
  log('setup', `  API: ${config.apiBaseUrl}`);
  log('setup', '═══════════════════════════════════════════════');

  // Health check
  try {
    await api('GET', '/api/customer/ping');
    log('setup', '✓ API is reachable');
  } catch (err) {
    log('setup', `✗ API unreachable: ${err.message}`);
    process.exit(1);
  }

  // Discover each vendor
  for (const [key, vendorConfig] of Object.entries(config.vendors)) {
    const data = await discoverVendor(key, vendorConfig);
    if (data) {
      testData.vendors[key] = data;

      if (data.menu.length === 0) {
        log('setup', `  ⚠ ${key} has NO MENU ITEMS — stress test will skip this vendor`);
      }
    }
  }

  // Summary
  log('setup', '');
  log('setup', '═══ Discovery Summary ═══');
  let ready = true;
  for (const [key, v] of Object.entries(testData.vendors)) {
    log('setup', `  ${key} (${v.vendorId}):`);
    log('setup', `    Menu items:  ${v.menu.length}`);
    log('setup', `    Tables:      ${v.tables.length}`);
    log('setup', `    Waiters:     ${v.waiters.length}`);
    log('setup', `    Kitchen:     ${v.kitchen.length}`);

    if (v.menu.length === 0 || v.tables.length === 0 || v.waiters.length === 0 || v.kitchen.length === 0) {
      ready = false;
    }
  }

  // Write test data
  mkdirSync('results', { recursive: true });
  writeFileSync('results/test-data.json', JSON.stringify(testData, null, 2));
  log('setup', '');
  log('setup', `✓ Test data written to results/test-data.json`);

  if (ready) {
    log('setup', '✓ All systems GO — run: node runner.mjs --tier 1');
  } else {
    log('setup', '⚠ Some vendors are missing data — check the summary above');
  }
}

main().catch(err => {
  console.error('Setup failed:', err);
  process.exit(1);
});
