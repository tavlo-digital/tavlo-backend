#!/usr/bin/env node
// ─── Post-Test Analysis ──────────────────────────────────────────────────
// Reads all tier result files and produces a comparative report.
// Usage: node analyze.mjs

import { readdirSync, readFileSync, writeFileSync } from 'fs';
import { summarizeMetrics, formatTable } from './helpers.mjs';

const resultFiles = readdirSync('results')
  .filter(f => f.startsWith('tier-') && f.endsWith('.json'))
  .sort();

if (resultFiles.length === 0) {
  console.error('No tier result files found. Run the stress test first.');
  process.exit(1);
}

const tiers = resultFiles.map(f => JSON.parse(readFileSync(`results/${f}`, 'utf-8')));

// ─── Comparison Table ─────────────────────────────────────────────────────

console.log('');
console.log('╔══════════════════════════════════════════════════════════════════════════════════╗');
console.log('║                    TAVLO STRESS TEST — ANALYSIS REPORT                          ║');
console.log('╚══════════════════════════════════════════════════════════════════════════════════╝');
console.log('');

// Overview table
console.log('═══ Tier Comparison ═══');
console.log('');
console.log('Tier         │ Actors │ Orders │ Paid │ Errors │ Duration │ Req/s  │ Avg(ms) │ p95(ms) │ p99(ms)');
console.log('─────────────┼────────┼────────┼──────┼────────┼──────────┼────────┼─────────┼─────────┼────────');

for (const t of tiers) {
  const metrics = t.rawMetrics || [];
  const duSec = (t.duration / 1000).toFixed(0);
  const reqPerSec = metrics.length > 0 ? (metrics.length / (t.duration / 1000)).toFixed(1) : '0';

  const allDurations = metrics.map(m => m.duration).sort((a, b) => a - b);
  const avgMs = allDurations.length > 0
    ? Math.round(allDurations.reduce((a, b) => a + b, 0) / allDurations.length)
    : 0;
  const p95Idx = Math.ceil(allDurations.length * 0.95) - 1;
  const p99Idx = Math.ceil(allDurations.length * 0.99) - 1;
  const p95 = allDurations[Math.max(0, p95Idx)] || 0;
  const p99 = allDurations[Math.max(0, p99Idx)] || 0;

  console.log(
    `${(t.tier || '').padEnd(13)}│ ${String(t.actors?.total || 0).padStart(6)} │ ${String(t.outcomes?.ordersPlaced || 0).padStart(6)} │ ${String(t.outcomes?.paymentsCompleted || 0).padStart(4)} │ ${String(t.outcomes?.totalErrors || 0).padStart(6)} │ ${(duSec + 's').padStart(8)} │ ${reqPerSec.padStart(6)} │ ${String(avgMs).padStart(7)} │ ${String(p95).padStart(7)} │ ${String(p99).padStart(7)}`
  );
}

// ─── Error Analysis ───────────────────────────────────────────────────────

console.log('');
console.log('═══ Error Breakdown ═══');
console.log('');

for (const t of tiers) {
  const errorsByEndpoint = {};
  const metrics = t.rawMetrics || [];

  for (const m of metrics) {
    if (!m.success) {
      const key = `${m.method} ${m.path}`;
      errorsByEndpoint[key] = (errorsByEndpoint[key] || 0) + 1;
    }
  }

  const errorEntries = Object.entries(errorsByEndpoint).sort((a, b) => b[1] - a[1]);
  if (errorEntries.length > 0) {
    console.log(`  ${t.tier}:`);
    for (const [endpoint, count] of errorEntries.slice(0, 10)) {
      console.log(`    ${endpoint}: ${count} errors`);
    }
  } else {
    console.log(`  ${t.tier}: No errors!`);
  }
}

// ─── Hot Path Analysis ────────────────────────────────────────────────────

console.log('');
console.log('═══ Slowest Endpoints (by p95) ═══');
console.log('');

for (const t of tiers) {
  const summary = t.metrics || summarizeMetrics(t.rawMetrics || []);
  const sorted = Object.entries(summary)
    .sort((a, b) => b[1].p95 - a[1].p95)
    .slice(0, 5);

  console.log(`  ${t.tier}:`);
  for (const [endpoint, s] of sorted) {
    console.log(`    ${endpoint}: p95=${s.p95}ms, p99=${s.p99}ms, avg=${s.avg}ms (${s.count} calls)`);
  }
  console.log('');
}

// ─── Actor Performance ────────────────────────────────────────────────────

console.log('═══ Actor Summary ═══');
console.log('');

for (const t of tiers) {
  const actors = t.actorResults || [];
  const customers = actors.filter(a => a.role === 'customer');
  const waiters = actors.filter(a => a.role === 'waiter');
  const kitchens = actors.filter(a => a.role === 'kitchen');

  console.log(`  ${t.tier}:`);

  // Customer success rates
  const orderSuccess = customers.filter(c => c.orderCompleted).length;
  const paySuccess = customers.filter(c => c.paymentCompleted).length;
  const customerTimes = customers
    .filter(c => c.finishedAt && c.startedAt)
    .map(c => c.finishedAt - c.startedAt);
  const avgCustomerTime = customerTimes.length > 0
    ? Math.round(customerTimes.reduce((a, b) => a + b, 0) / customerTimes.length / 1000)
    : 0;

  console.log(`    Customers (${customers.length}): ${orderSuccess} orders placed, ${paySuccess} paid`);
  console.log(`    Avg customer lifecycle: ${avgCustomerTime}s`);

  // Waiter stats
  const totalConfirmed = waiters.reduce((s, w) => s + (w.ordersConfirmed || 0), 0);
  const totalServed = waiters.reduce((s, w) => s + (w.ordersServed || 0), 0);
  console.log(`    Waiters (${waiters.length}): ${totalConfirmed} confirmed, ${totalServed} served`);

  // Kitchen stats
  const totalItems = kitchens.reduce((s, k) => s + (k.itemsPrepared || 0), 0);
  const totalOrders = kitchens.reduce((s, k) => s + (k.ordersCompleted || 0), 0);
  console.log(`    Kitchen (${kitchens.length}): ${totalItems} items prepared, ${totalOrders} orders completed`);

  console.log('');
}

// ─── Scaling Analysis ─────────────────────────────────────────────────────

if (tiers.length > 1) {
  console.log('═══ Scaling Analysis ═══');
  console.log('');

  for (let i = 1; i < tiers.length; i++) {
    const prev = tiers[i - 1];
    const curr = tiers[i];

    const prevMetrics = prev.rawMetrics || [];
    const currMetrics = curr.rawMetrics || [];

    const prevAvg = prevMetrics.length > 0
      ? prevMetrics.reduce((s, m) => s + m.duration, 0) / prevMetrics.length
      : 0;
    const currAvg = currMetrics.length > 0
      ? currMetrics.reduce((s, m) => s + m.duration, 0) / currMetrics.length
      : 0;

    const actorIncrease = ((curr.actors?.total || 0) / (prev.actors?.total || 1) - 1) * 100;
    const latencyIncrease = prevAvg > 0 ? ((currAvg / prevAvg - 1) * 100) : 0;

    const prevErrorRate = prevMetrics.length > 0
      ? (prevMetrics.filter(m => !m.success).length / prevMetrics.length) * 100
      : 0;
    const currErrorRate = currMetrics.length > 0
      ? (currMetrics.filter(m => !m.success).length / currMetrics.length) * 100
      : 0;

    console.log(`  ${prev.tier} → ${curr.tier}:`);
    console.log(`    Actors: +${actorIncrease.toFixed(0)}%`);
    console.log(`    Avg latency: ${Math.round(prevAvg)}ms → ${Math.round(currAvg)}ms (${latencyIncrease >= 0 ? '+' : ''}${latencyIncrease.toFixed(0)}%)`);
    console.log(`    Error rate: ${prevErrorRate.toFixed(1)}% → ${currErrorRate.toFixed(1)}%`);

    if (latencyIncrease > 100) {
      console.log(`    ⚠ DEGRADATION: Latency more than doubled`);
    }
    if (currErrorRate > 5) {
      console.log(`    ⚠ HIGH ERROR RATE: ${currErrorRate.toFixed(1)}% errors`);
    }
    console.log('');
  }
}

// ─── Bottleneck Identification ────────────────────────────────────────────

console.log('═══ Potential Bottlenecks ═══');
console.log('');

const lastTier = tiers[tiers.length - 1];
const lastMetrics = lastTier?.rawMetrics || [];

// Find endpoints where p95 > 2s
const lastSummary = lastTier?.metrics || summarizeMetrics(lastMetrics);
const slowEndpoints = Object.entries(lastSummary).filter(([, s]) => s.p95 > 2000);
if (slowEndpoints.length > 0) {
  console.log('  Slow endpoints (p95 > 2s):');
  for (const [endpoint, s] of slowEndpoints) {
    console.log(`    ${endpoint}: p95=${s.p95}ms`);
  }
} else {
  console.log('  No endpoints with p95 > 2s');
}

// Find endpoints with high error rates
const errorEndpoints = Object.entries(lastSummary)
  .filter(([, s]) => parseFloat(s.errorRate) > 5);
if (errorEndpoints.length > 0) {
  console.log('');
  console.log('  High error rate endpoints (> 5%):');
  for (const [endpoint, s] of errorEndpoints) {
    console.log(`    ${endpoint}: ${s.errorRate} (${s.errors}/${s.count})`);
  }
}

console.log('');
console.log('═══ Recommendations ═══');
console.log('');

const avgAll = lastMetrics.length > 0
  ? lastMetrics.reduce((s, m) => s + m.duration, 0) / lastMetrics.length
  : 0;

if (avgAll > 500) {
  console.log('  → Average response time > 500ms — consider increasing PHP-FPM workers');
}
if (slowEndpoints.find(([e]) => e.includes('cart'))) {
  console.log('  → Cart endpoints are slow — likely DB lock contention on concurrent cart adds');
}
if (slowEndpoints.find(([e]) => e.includes('order/confirmed'))) {
  console.log('  → Order confirmation is slow — heavy transaction with notifications');
}
if (slowEndpoints.find(([e]) => e.includes('payment'))) {
  console.log('  → Payment endpoints slow — Stripe API latency is a factor');
}
if (errorEndpoints.length > 0) {
  console.log('  → High error rates detected — check server logs for specific failures');
}
if (avgAll < 200 && errorEndpoints.length === 0) {
  console.log('  → System performing well at this load level. Consider increasing to next tier.');
}

// Save report
const report = {
  generatedAt: new Date().toISOString(),
  tierCount: tiers.length,
  tiers: tiers.map(t => ({
    name: t.tier,
    actors: t.actors?.total,
    ordersPlaced: t.outcomes?.ordersPlaced,
    paymentsCompleted: t.outcomes?.paymentsCompleted,
    errors: t.outcomes?.totalErrors,
    duration: t.duration,
    requestCount: (t.rawMetrics || []).length,
  })),
  bottlenecks: {
    slowEndpoints: slowEndpoints.map(([e, s]) => ({ endpoint: e, p95: s.p95 })),
    errorEndpoints: errorEndpoints.map(([e, s]) => ({ endpoint: e, errorRate: s.errorRate })),
  },
};

writeFileSync('results/analysis-report.json', JSON.stringify(report, null, 2));
console.log('');
console.log('Report saved to results/analysis-report.json');
