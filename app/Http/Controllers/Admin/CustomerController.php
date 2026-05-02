<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\GdprRequest;
use Inertia\Inertia;

class CustomerController extends Controller
{
    public function index()
    {
        $customers = Customer::all();

        $stats = [
            'totalCustomers' => $customers->count(),
            'totalOrders' => $customers->sum('orders_count'),
            'flaggedAccounts' => $customers->where('risk_level', '!=', 'none')->count(),
            'gdprRequests' => GdprRequest::where('created_at', '>=', now()->subDays(30))->count(),
            'highActivity' => $customers->where('orders_count', '>=', 10)->count(),
        ];

        $customersData = $customers->map(function (Customer $c) {
            return [
                'id' => $c->customer_public_id,
                'accountType' => ucfirst($c->account_type),
                'email' => $c->email,
                'phone' => $c->phone,
                'risk' => $c->risk_level,
                'riskTooltip' => $c->risk_tooltip,
                'ordersCount' => $c->orders_count,
                'totalSpend' => '€' . number_format($c->total_spend, 2),
                'lastActive' => $c->last_active_at?->diffForHumans() ?? 'Never',
            ];
        });

        return Inertia::render('admin/customers/index', [
            'stats' => $stats,
            'customersData' => $customersData->values(),
        ]);
    }

    public function show(string $customer, string $tab = 'overview')
    {
        $c = Customer::where('customer_public_id', $customer)->firstOrFail();

        $customerData = [
            'id' => $c->customer_public_id,
            'name' => $c->name,
            'accountType' => ucfirst($c->account_type),
            'email' => $c->email,
            'phone' => $c->phone,
            'risk' => $c->risk_level,
            'riskTooltip' => $c->risk_tooltip,
            'ordersCount' => $c->orders_count,
            'totalSpend' => number_format($c->total_spend, 2),
            'loyaltyPoints' => $c->loyalty_points ?? 0,
            'accountCreated' => $c->created_at?->format('Y-m-d') ?? '-',
            'lastLogin' => $c->last_active_at?->format('Y-m-d H:i') ?? 'Never',
            'registrationSource' => ucfirst($c->registration_source ?? 'web'),
        ];

        $tabData = [];

        if ($tab === 'orders') {
            $tabData['orders'] = $c->orders()
                ->with('vendor:id,name')
                ->orderByDesc('created_at')
                ->get()
                ->map(fn ($o) => [
                    'id' => $o->order_public_id,
                    'vendor' => $o->vendor?->name ?? 'Unknown',
                    'date' => $o->created_at->format('Y-m-d H:i'),
                    'status' => ucfirst($o->status),
                    'amount' => '€' . number_format($o->amount, 2),
                ]);
        }

        if ($tab === 'refunds') {
            $tabData['refunds'] = $c->refunds()
                ->with('order:id,order_public_id')
                ->orderByDesc('created_at')
                ->get()
                ->map(fn ($r) => [
                    'id' => $r->refund_public_id,
                    'type' => strtoupper($r->type),
                    'status' => ucfirst($r->status),
                    'orderId' => $r->order?->order_public_id ?? '-',
                    'amount' => '€' . number_format($r->amount, 2),
                    'date' => $r->created_at->format('Y-m-d'),
                    'reason' => $r->reason,
                    'description' => $r->description,
                ]);
        }

        if ($tab === 'reviews') {
            $tabData['reviews'] = $c->reviews()
                ->with('vendor:id,name')
                ->orderByDesc('created_at')
                ->get()
                ->map(fn ($r) => [
                    'id' => $r->review_public_id,
                    'vendor' => $r->vendor?->name ?? 'Unknown',
                    'rating' => $r->rating,
                    'date' => $r->created_at->format('Y-m-d'),
                    'text' => $r->text,
                    'flagged' => $r->flagged,
                ]);
        }

        if ($tab === 'activity') {
            $tabData['activities'] = $c->activities()
                ->orderByDesc('created_at')
                ->limit(20)
                ->get()
                ->map(fn ($a) => [
                    'event' => $a->title,
                    'time' => $a->created_at->format('Y-m-d H:i'),
                    'detail' => $a->detail,
                    'ip' => $a->ip_address,
                ]);
        }

        if ($tab === 'gdpr') {
            $tabData['gdprRequests'] = $c->gdprRequests()
                ->with('handler:id,name')
                ->orderByDesc('created_at')
                ->get()
                ->map(fn ($g) => [
                    'id' => $g->id,
                    'type' => $g->type,
                    'status' => ucfirst($g->status),
                    'reason' => $g->reason,
                    'date' => $g->created_at->format('Y-m-d'),
                    'handledBy' => $g->handler?->name,
                    'completedAt' => $g->completed_at?->format('Y-m-d'),
                ]);
        }

        return Inertia::render('admin/customer/[customer]/show', [
            'customer' => $customerData,
            'activeTab' => $tab,
            'tabData' => $tabData,
        ]);
    }
}
