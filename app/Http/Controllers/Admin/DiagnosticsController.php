<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ApplicationLog;
use App\Models\OrderPayment;
use App\Models\StripeWebhookLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Inertia\Inertia;

class DiagnosticsController extends Controller
{
    public function index(Request $request)
    {
        $tab = $request->get('tab', 'stripe');

        return Inertia::render('admin/diagnostics/index', [
            'currentTab' => $tab,
            'stripe' => fn () => $this->stripeData(),
            'appLogs' => fn () => $this->appLogsData($request->get('level')),
        ]);
    }

    public function webhookLogs(Request $request)
    {
        $logs = StripeWebhookLog::query()
            ->orderByDesc('created_at')
            ->paginate(25);

        return Inertia::render('admin/diagnostics/webhook-logs', [
            'logs' => $logs,
        ]);
    }

    public function applicationLogs(Request $request)
    {
        $level = $request->get('level');

        $logs = ApplicationLog::query()
            ->when($level, fn ($q) => $q->where('level', $level))
            ->orderByDesc('logged_at')
            ->paginate(30);

        $levelCounts = ApplicationLog::query()
            ->selectRaw('level, count(*) as count')
            ->groupBy('level')
            ->pluck('count', 'level')
            ->toArray();

        return Inertia::render('admin/diagnostics/application-logs', [
            'logs' => $logs,
            'levelCounts' => $levelCounts,
            'currentLevel' => $level,
        ]);
    }

    public function reconcile()
    {
        Artisan::call('payments:reconcile-stale');
        $output = Artisan::output();

        return back()->with('flash', [
            'type' => 'success',
            'message' => trim($output),
        ]);
    }

    private function stripeData(): array
    {
        $webhookSecretSet = trim((string) config('services.stripe.webhook_secret')) !== '';
        $stripeSecretSet = trim((string) config('services.stripe.secret')) !== '';

        $last24h = StripeWebhookLog::where('created_at', '>=', now()->subHours(24));
        $webhookStats = [
            'total' => (clone $last24h)->count(),
            'processed' => (clone $last24h)->where('outcome', 'processed')->count(),
            'signature_invalid' => (clone $last24h)->where('outcome', 'signature_invalid')->count(),
            'payment_not_found' => (clone $last24h)->where('outcome', 'payment_not_found')->count(),
            'ignored' => (clone $last24h)->where('outcome', 'ignored_event_type')->count(),
        ];

        $stalePayments = OrderPayment::query()
            ->where('status', '!=', 'succeeded')
            ->where('created_at', '<', now()->subMinutes(10))
            ->whereHas('order', fn ($q) => $q
                ->where('payment_received', false)
                ->where('payment_pending', true)
            )
            ->count();

        $recentLogs = StripeWebhookLog::query()
            ->orderByDesc('created_at')
            ->limit(20)
            ->get();

        $checks = [
            [
                'name' => 'STRIPE_SECRET',
                'status' => $stripeSecretSet ? 'ok' : 'error',
                'detail' => $stripeSecretSet ? 'Configured' : 'Missing — payments will fail',
            ],
            [
                'name' => 'STRIPE_WEBHOOK_SECRET',
                'status' => $webhookSecretSet ? 'ok' : 'error',
                'detail' => $webhookSecretSet ? 'Configured' : 'Missing — webhooks will be rejected with 400',
            ],
            [
                'name' => 'Webhook delivery (24h)',
                'status' => $webhookStats['signature_invalid'] > 0 ? 'error' : ($webhookStats['total'] === 0 ? 'warning' : 'ok'),
                'detail' => $webhookStats['signature_invalid'] > 0
                    ? "{$webhookStats['signature_invalid']} signature failures"
                    : ($webhookStats['total'] === 0 ? 'No events received in 24h' : "{$webhookStats['processed']} processed successfully"),
            ],
            [
                'name' => 'Stale payments',
                'status' => $stalePayments > 0 ? 'warning' : 'ok',
                'detail' => $stalePayments > 0
                    ? "{$stalePayments} payment(s) pending > 10 min"
                    : 'No stale payments',
            ],
        ];

        return [
            'checks' => $checks,
            'webhookStats' => $webhookStats,
            'stalePayments' => $stalePayments,
            'recentLogs' => $recentLogs,
        ];
    }

    private function appLogsData(?string $level): array
    {
        $recentLogs = ApplicationLog::query()
            ->when($level, fn ($q) => $q->where('level', $level))
            ->orderByDesc('logged_at')
            ->limit(20)
            ->get();

        $levelCounts = ApplicationLog::query()
            ->selectRaw('level, count(*) as count')
            ->groupBy('level')
            ->pluck('count', 'level')
            ->toArray();

        $totalErrors24h = ApplicationLog::query()
            ->whereIn('level', ['ERROR', 'CRITICAL', 'EMERGENCY', 'ALERT'])
            ->where('logged_at', '>=', now()->subHours(24))
            ->count();

        return [
            'recentLogs' => $recentLogs,
            'levelCounts' => $levelCounts,
            'currentLevel' => $level,
            'totalErrors24h' => $totalErrors24h,
        ];
    }
}
