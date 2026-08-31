<?php

namespace App\Http\Middleware;

use App\Models\TeamMember;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureStaffCanAccessVendorRoute
{
    /**
     * Staff tokens may only access their role-specific operational routes.
     * Owner/vendor tokens pass through unchanged.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof TeamMember) {
            return $next($request);
        }

        if ($user->status !== 'active') {
            abort(403, 'This staff account is not active.');
        }

        $routeName = (string) $request->route()?->getName();

        $allowedForEveryone = [
            'vendor.me',
            'vendor.logout',
            'vendor.profile.password',
            'vendor.broadcasting.auth',
            'vendor.notifications.index',
            'vendor.notifications.read',
            'vendor.notifications.readAll',
            'vendor.commands.show',
            'vendor.orders.index',
            'vendor.orders.show',
            'vendor.orders.itemStatus',
        ];

        $allowedByRole = [
            'kitchen' => [
                'vendor.orders.ready',
                'vendor.orders.itemStatusBatch',
            ],
            'waiter' => [
                'vendor.orders.confirm',
                'vendor.orders.confirmCash',
                'vendor.orders.served',
                'vendor.orders.pickedUp',
                'vendor.orders.serveReadyItems',
                'vendor.orders.itemStatusBatch',
                'vendor.tables.index',
                'vendor.tables.closeSession',
                // The same capability for a group with no table to close from.
                'vendor.scanSessions.close',
                'vendor.tables.dismissCall',
                'vendor.tables.transfer',
                'vendor.tables.staffOrder',
                'vendor.tables.createSession',
                'vendor.menu.items.index',
            ],
        ];

        $allowed = in_array($routeName, $allowedForEveryone, true)
            || in_array($routeName, $allowedByRole[$user->role] ?? [], true);

        if (! $allowed) {
            abort(403, 'This staff role cannot access that route.');
        }

        $vendorId = $request->route('vendorId');
        if ($vendorId !== null && ! $this->matchesVendor($user, (string) $vendorId)) {
            abort(403, 'This staff account belongs to a different vendor.');
        }

        return $next($request);
    }

    private function matchesVendor(TeamMember $member, string $vendorId): bool
    {
        $vendor = $member->vendor;

        return $vendor
            && ((string) $vendor->id === $vendorId || (string) $vendor->vendor_public_id === $vendorId);
    }
}
