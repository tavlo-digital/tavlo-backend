import axios from 'axios';
import { randomUUID } from 'crypto';
import config from './config.mjs';

// ─── HTTP Client with metrics ─────────────────────────────────────────────

const metrics = [];

export function getMetrics() { return metrics; }
export function clearMetrics() { metrics.length = 0; }

export async function api(method, path, data = null, token = null, retries = 2, extraHeaders = {}) {
  const url = `${config.apiBaseUrl}${path}`;
  const headers = { 'Accept': 'application/json', 'Content-Type': 'application/json', ...extraHeaders };
  if (token) headers['Authorization'] = `Bearer ${token}`;

  const start = Date.now();
  let lastError;

  for (let attempt = 0; attempt <= retries; attempt++) {
    try {
      const res = await axios({ method, url, data, headers, timeout: 30000, validateStatus: () => true });
      const duration = Date.now() - start;

      metrics.push({
        method, path, status: res.status, duration, timestamp: Date.now(),
        success: res.status >= 200 && res.status < 400,
      });

      if (res.status >= 400) {
        const msg = res.data?.message || res.data?.error || JSON.stringify(res.data);
        const err = new Error(`${method} ${path} → ${res.status}: ${msg}`);
        err.status = res.status;
        err.response = res.data;
        throw err;
      }

      return res.data;
    } catch (err) {
      lastError = err;
      if (err.status && err.status < 500) throw err; // don't retry 4xx
      if (attempt < retries) await sleep(1000 * (attempt + 1));
    }
  }
  throw lastError;
}

// ─── Utility functions ────────────────────────────────────────────────────

export function sleep(ms) {
  return new Promise(resolve => setTimeout(resolve, ms));
}

export function randomInt(min, max) {
  return Math.floor(Math.random() * (max - min + 1)) + min;
}

export function randomDelay() {
  return randomInt(config.minActionDelayMs, config.maxActionDelayMs);
}

export function randomFrom(arr) {
  return arr[Math.floor(Math.random() * arr.length)];
}

export function randomNote() {
  return Math.random() < 0.4 ? randomFrom(config.notePool) : null;
}

export function pickPaymentMethod(isLastCustomer = false) {
  if (isLastCustomer) {
    // Last customer can't rely on "get covered" — must pay
    const r = Math.random();
    if (r < 0.5) return 'card';
    if (r < 0.85) return 'cash';
    return 'payForOthers';
  }
  const r = Math.random();
  const d = config.paymentDistribution;
  if (r < d.card) return 'card';
  if (r < d.card + d.cash) return 'cash';
  if (r < d.card + d.cash + d.payForOthers) return 'payForOthers';
  return 'getCovered';
}

export { randomUUID };

export function log(actor, msg) {
  const ts = new Date().toISOString().slice(11, 23);
  console.log(`[${ts}] [${actor}] ${msg}`);
}

// ─── Metric summarization ─────────────────────────────────────────────────

export function summarizeMetrics(metricsList) {
  const byEndpoint = {};

  for (const m of metricsList) {
    const key = `${m.method} ${m.path}`;
    if (!byEndpoint[key]) byEndpoint[key] = { durations: [], errors: 0, total: 0 };
    byEndpoint[key].total++;
    byEndpoint[key].durations.push(m.duration);
    if (!m.success) byEndpoint[key].errors++;
  }

  const summary = {};
  for (const [endpoint, data] of Object.entries(byEndpoint)) {
    const sorted = data.durations.sort((a, b) => a - b);
    summary[endpoint] = {
      count: data.total,
      errors: data.errors,
      errorRate: ((data.errors / data.total) * 100).toFixed(1) + '%',
      p50: percentile(sorted, 50),
      p95: percentile(sorted, 95),
      p99: percentile(sorted, 99),
      max: sorted[sorted.length - 1],
      avg: Math.round(sorted.reduce((a, b) => a + b, 0) / sorted.length),
    };
  }

  return summary;
}

function percentile(sorted, p) {
  const idx = Math.ceil((p / 100) * sorted.length) - 1;
  return sorted[Math.max(0, idx)];
}

export function formatTable(summary) {
  const rows = Object.entries(summary)
    .sort((a, b) => b[1].count - a[1].count)
    .map(([endpoint, s]) => [
      endpoint.padEnd(50),
      String(s.count).padStart(6),
      String(s.errors).padStart(6),
      s.errorRate.padStart(8),
      (s.avg + 'ms').padStart(8),
      (s.p50 + 'ms').padStart(8),
      (s.p95 + 'ms').padStart(8),
      (s.p99 + 'ms').padStart(8),
      (s.max + 'ms').padStart(8),
    ]);

  const header = [
    'Endpoint'.padEnd(50),
    'Count'.padStart(6),
    'Errs'.padStart(6),
    'Err%'.padStart(8),
    'Avg'.padStart(8),
    'p50'.padStart(8),
    'p95'.padStart(8),
    'p99'.padStart(8),
    'Max'.padStart(8),
  ];

  const sep = header.map(h => '─'.repeat(h.length));
  return [header.join(' │ '), sep.join('─┼─'), ...rows.map(r => r.join(' │ '))].join('\n');
}
