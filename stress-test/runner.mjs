#!/usr/bin/env node
// ─── Stress Test Runner ───────────────────────────────────────────────────
// Orchestrates customer, waiter, and kitchen actors for each tier.
// Usage:
//   node runner.mjs --tier 1         # run tier 1 only
//   node runner.mjs --tier all       # run all tiers sequentially
//   node runner.mjs --tier 1 --dry   # dry run (log actions without HTTP)

import { execSync } from 'child_process';
import { readFileSync, writeFileSync, mkdirSync } from 'fs';
import { runCustomer } from './actors/customer.mjs';
import { runKitchen } from './actors/kitchen.mjs';
import { runWaiter } from './actors/waiter.mjs';
import config from './config.mjs';
import { getMetrics, clearMetrics, summarizeMetrics, formatTable, log, sleep, randomInt } from './helpers.mjs';

const APP_PATH = '/home/1613226.cloudwaysapps.com/ynfjqwuxxs/public_html';

function cleanActiveSessions() {
  try {
    const cmd = `cd ${APP_PATH} && php artisan tinker --execute="use App\\\\Models\\\\TableScanSession; \\$c = TableScanSession::where('status','active')->update(['status'=>'closed']); echo \\"Closed \\$c sessions\\";"`;
    const out = execSync(cmd, { encoding: 'utf-8', timeout: 15000 }).trim();
    log('runner', `  Session cleanup: ${out}`);
  } catch (err) {
    log('runner', `  Session cleanup failed: ${err.message}`);
  }
}

// ─── Parse CLI args ───────────────────────────────────────────────────────

const args = process.argv.slice(2);
const tierArg = args.find((a, i) => args[i - 1] === '--tier') || '1';
const isDry = args.includes('--dry');
const quickMode = args.includes('--quick'); // 2-min tiers for validation

if (isDry) log('runner', 'DRY RUN — no HTTP calls will be made');
if (quickMode) {
  config.tierDurationMs = 2 * 60 * 1000;  // 2 minutes
  config.cooldownMs = 30 * 1000;
  log('runner', 'QUICK MODE — 2-minute tiers');
}

// ─── Load test data ───────────────────────────────────────────────────────

let testData;
try {
  testData = JSON.parse(readFileSync('results/test-data.json', 'utf-8'));
} catch (err) {
  console.error('Run "node setup.mjs" first to generate test data.');
  process.exit(1);
}

// ─── Tier execution ───────────────────────────────────────────────────────

async function runTier(tierConfig, tierIndex) {
  cleanActiveSessions();
  const startTime = Date.now();
  clearMetrics();

  log('runner', '');
  log('runner', '═══════════════════════════════════════════════════════════');
  log('runner', `  ${tierConfig.name}: ${tierConfig.actors} actors — ${tierConfig.desc}`);
  log('runner', `  Duration: ${config.tierDurationMs / 1000}s`);
  log('runner', '═══════════════════════════════════════════════════════════');

  // Determine which vendors to use
  const vendorKeys = tierConfig.restaurant === 'both'
    ? ['restaurantA', 'restaurantB']
    : [tierConfig.restaurant];

  const promises = [];

  for (const vendorKey of vendorKeys) {
    const vendor = testData.vendors[vendorKey];
    if (!vendor) {
      log('runner', `⚠ Vendor ${vendorKey} not found in test data, skipping`);
      continue;
    }

    if (vendor.menu.length === 0) {
      log('runner', `⚠ Vendor ${vendorKey} has no menu items, skipping`);
      continue;
    }

    const tablesForThisVendor = tierConfig.restaurant === 'both'
      ? Math.ceil(tierConfig.tables / 2)
      : tierConfig.tables;

    const availableTables = vendor.tables.slice(0, tablesForThisVendor);
    if (availableTables.length === 0) {
      log('runner', `⚠ No tables available for ${vendorKey}`);
      continue;
    }

    log('runner', `  ${vendorKey}: ${availableTables.length} tables, ${vendor.menu.length} menu items`);

    // Start waiter actors
    for (let w = 0; w < vendor.waiters.length; w++) {
      const waiterCred = vendor.waiters[w];
      promises.push(
        runWaiter({
          actorId: `waiter-${vendorKey}-${w}`,
          waiterIndex: `${vendorKey}-${w}`,
          vendorPublicId: vendor.vendorId,
          email: waiterCred.email,
          password: waiterCred.password,
          startTime,
          tierDuration: config.tierDurationMs,
        })
      );
    }

    // Start kitchen actor(s)
    for (let k = 0; k < vendor.kitchen.length; k++) {
      const kitchenCred = vendor.kitchen[k];
      promises.push(
        runKitchen({
          actorId: `kitchen-${vendorKey}-${k}`,
          kitchenIndex: `${vendorKey}-${k}`,
          vendorPublicId: vendor.vendorId,
          email: kitchenCred.email,
          password: kitchenCred.password,
          startTime,
          tierDuration: config.tierDurationMs,
        })
      );
    }

    // Start customer actors per table (staggered)
    for (let t = 0; t < availableTables.length; t++) {
      const table = availableTables[t];
      const customersAtTable = randomInt(config.minCustomersPerTable, config.maxCustomersPerTable);

      // Shared state for customers at this table
      const tableState = {
        pin: null,
        orderIds: [],
        sessions: [],
        paidOrders: new Set(),
      };

      // Stagger table starts
      const tableDelay = t * config.tableStaggerMs + randomInt(0, config.tableStaggerMs / 2);

      for (let c = 0; c < customersAtTable; c++) {
        const customerDelay = tableDelay + (c * randomInt(1000, 3000));

        promises.push(
          (async () => {
            await sleep(customerDelay);
            return runCustomer({
              actorId: `customer-${vendorKey}-T${t}-C${c}`,
              vendorPublicId: vendor.vendorId,
              table,
              menu: vendor.menu,
              tableIndex: t,
              customerIndex: c,
              totalCustomersAtTable: customersAtTable,
              tableState,
              startTime,
              tierDuration: config.tierDurationMs,
            });
          })()
        );
      }

      log('runner', `  Table ${table.number}: ${customersAtTable} customers (delay: ${tableDelay}ms)`);
    }
  }

  // Run all actors concurrently
  log('runner', `  Starting ${promises.length} actors...`);
  const results = await Promise.allSettled(promises);

  // Collect results
  const elapsed = Date.now() - startTime;
  const actorResults = results.map(r => r.status === 'fulfilled' ? r.value : { error: r.reason?.message });

  const customers = actorResults.filter(r => r.role === 'customer');
  const waiters = actorResults.filter(r => r.role === 'waiter');
  const kitchens = actorResults.filter(r => r.role === 'kitchen');

  const ordersPlaced = customers.filter(c => c.orderCompleted).length;
  const paymentsDone = customers.filter(c => c.paymentCompleted).length;
  const totalErrors = actorResults.reduce((sum, r) => sum + (r.errors?.length || 0), 0);

  // Summary
  log('runner', '');
  log('runner', `─── ${tierConfig.name} Results ───`);
  log('runner', `  Duration: ${(elapsed / 1000).toFixed(0)}s`);
  log('runner', `  Actors: ${actorResults.length} total (${customers.length} customers, ${waiters.length} waiters, ${kitchens.length} kitchen)`);
  log('runner', `  Orders placed: ${ordersPlaced}/${customers.length}`);
  log('runner', `  Payments completed: ${paymentsDone}/${customers.length}`);
  log('runner', `  Total errors: ${totalErrors}`);

  if (waiters.length > 0) {
    const wConfirmed = waiters.reduce((s, w) => s + (w.ordersConfirmed || 0), 0);
    const wServed = waiters.reduce((s, w) => s + (w.ordersServed || 0), 0);
    const wCash = waiters.reduce((s, w) => s + (w.cashConfirmed || 0), 0);
    log('runner', `  Waiter actions: ${wConfirmed} confirmed, ${wServed} served, ${wCash} cash`);
  }

  if (kitchens.length > 0) {
    const kItems = kitchens.reduce((s, k) => s + (k.itemsPrepared || 0), 0);
    const kOrders = kitchens.reduce((s, k) => s + (k.ordersCompleted || 0), 0);
    log('runner', `  Kitchen actions: ${kItems} items prepared, ${kOrders} orders completed`);
  }

  // Metrics summary
  const metrics = getMetrics();
  const metricsSummary = summarizeMetrics(metrics);

  log('runner', '');
  log('runner', '─── Endpoint Performance ───');
  console.log(formatTable(metricsSummary));

  // Calculate throughput
  const durationSec = elapsed / 1000;
  const totalRequests = metrics.length;
  log('runner', '');
  log('runner', `  Total requests: ${totalRequests}`);
  log('runner', `  Throughput: ${(totalRequests / durationSec).toFixed(1)} req/s`);
  log('runner', `  Success rate: ${((metrics.filter(m => m.success).length / totalRequests) * 100).toFixed(1)}%`);

  // Save results
  const tierResult = {
    tier: tierConfig.name,
    config: tierConfig,
    duration: elapsed,
    actors: {
      total: actorResults.length,
      customers: customers.length,
      waiters: waiters.length,
      kitchen: kitchens.length,
    },
    outcomes: {
      ordersPlaced,
      paymentsCompleted: paymentsDone,
      totalErrors,
    },
    metrics: metricsSummary,
    rawMetrics: metrics,
    actorResults,
    timestamp: new Date().toISOString(),
  };

  mkdirSync('results', { recursive: true });
  const filename = `results/tier-${tierIndex + 1}-${Date.now()}.json`;
  writeFileSync(filename, JSON.stringify(tierResult, null, 2));
  log('runner', `  Results saved to ${filename}`);

  return tierResult;
}

// ─── Main ─────────────────────────────────────────────────────────────────

async function main() {
  log('runner', '╔══════════════════════════════════════════════╗');
  log('runner', '║  Tavlo Stress Test Runner                    ║');
  log('runner', '╚══════════════════════════════════════════════╝');
  log('runner', `API: ${config.apiBaseUrl}`);

  const tiersToRun = tierArg === 'all'
    ? config.tiers.map((t, i) => i)
    : [parseInt(tierArg, 10) - 1];

  const allResults = [];

  for (const tierIdx of tiersToRun) {
    if (tierIdx < 0 || tierIdx >= config.tiers.length) {
      log('runner', `Invalid tier index: ${tierIdx + 1}`);
      continue;
    }

    const result = await runTier(config.tiers[tierIdx], tierIdx);
    allResults.push(result);

    // Cooldown between tiers
    if (tiersToRun.indexOf(tierIdx) < tiersToRun.length - 1) {
      log('runner', '');
      log('runner', `Cooling down for ${config.cooldownMs / 1000}s...`);
      await sleep(config.cooldownMs);
    }
  }

  // Save combined results
  if (allResults.length > 1) {
    const combined = {
      tiers: allResults.map(r => ({
        name: r.tier,
        actors: r.actors.total,
        duration: r.duration,
        ordersPlaced: r.outcomes.ordersPlaced,
        paymentsCompleted: r.outcomes.paymentsCompleted,
        errors: r.outcomes.totalErrors,
        avgResponseTime: Object.values(r.metrics).reduce((s, m) => s + m.avg, 0) / Object.values(r.metrics).length,
        p95ResponseTime: Math.max(...Object.values(r.metrics).map(m => m.p95)),
        throughput: (r.rawMetrics.length / (r.duration / 1000)).toFixed(1),
      })),
      generatedAt: new Date().toISOString(),
    };

    writeFileSync('results/combined-results.json', JSON.stringify(combined, null, 2));
    log('runner', '');
    log('runner', '═══ Combined Results ═══');
    console.log('');
    console.log('Tier         │ Actors │ Orders │ Payments │ Errors │ Avg(ms) │ p95(ms) │ req/s');
    console.log('─────────────┼────────┼────────┼──────────┼────────┼─────────┼─────────┼──────');
    for (const t of combined.tiers) {
      console.log(
        `${t.name.padEnd(13)}│ ${String(t.actors).padStart(6)} │ ${String(t.ordersPlaced).padStart(6)} │ ${String(t.paymentsCompleted).padStart(8)} │ ${String(t.errors).padStart(6)} │ ${String(Math.round(t.avgResponseTime)).padStart(7)} │ ${String(t.p95ResponseTime).padStart(7)} │ ${t.throughput.padStart(5)}`
      );
    }
  }

  log('runner', '');
  log('runner', 'Stress test complete. Run "node analyze.mjs" for detailed analysis.');
}

main().catch(err => {
  console.error('Runner failed:', err);
  process.exit(1);
});
