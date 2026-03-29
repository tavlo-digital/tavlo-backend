<?php

namespace App\Http\Controllers\Api\Vendor;

use App\Http\Controllers\Controller;
use App\Models\TeamMember;
use App\Models\Vendor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class TeamController extends Controller
{
    /**
     * GET /api/vendor/{vendorId}/team
     */
    public function index(Request $request, string $vendorId): JsonResponse
    {
        $vendor = $this->resolveVendor($vendorId);
        $this->authorizeVendor($request, $vendor);

        $members = $vendor->teamMembers()
            ->orderBy('name')
            ->get()
            ->map(fn (TeamMember $m) => $this->formatMember($m));

        return response()->json($members);
    }

    /**
     * POST /api/vendor/{vendorId}/team/invite
     */
    public function invite(Request $request, string $vendorId): JsonResponse
    {
        $vendor = $this->resolveVendor($vendorId);
        $this->authorizeVendor($request, $vendor);

        $data = $request->validate([
            'name'        => ['required', 'string', 'max:255'],
            'email'       => ['required', 'email', 'max:255'],
            'role'        => ['required', 'in:waiter,kitchen,manager'],
            'permissions' => ['nullable', 'array'],
        ]);

        // Prevent duplicate pending invites for same email within this vendor
        $existing = $vendor->teamMembers()->where('email', $data['email'])->first();
        if ($existing) {
            return response()->json(['message' => 'A team member with this email already exists'], 422);
        }

        $permissions = $data['permissions'] ?? TeamMember::defaultPermissions($data['role']);

        $member = $vendor->teamMembers()->create([
            'name'             => $data['name'],
            'email'            => $data['email'],
            'role'             => $data['role'],
            'permissions'      => $permissions,
            'status'           => 'invited',
            'invitation_token' => TeamMember::generateInvitationToken(),
            'invited_at'       => now(),
        ]);

        // TODO: dispatch InviteTeamMemberMail when mail is configured

        return response()->json($this->formatMember($member), 201);
    }

    /**
     * PATCH /api/vendor/{vendorId}/team/{memberId}
     */
    public function update(Request $request, string $vendorId, string $memberId): JsonResponse
    {
        $vendor = $this->resolveVendor($vendorId);
        $this->authorizeVendor($request, $vendor);

        $member = $vendor->teamMembers()->findOrFail($memberId);

        $data = $request->validate([
            'name'        => ['sometimes', 'string', 'max:255'],
            'role'        => ['sometimes', 'in:waiter,kitchen,manager'],
            'permissions' => ['sometimes', 'array'],
            'status'      => ['sometimes', 'in:invited,active,suspended'],
        ]);

        $member->update($data);

        return response()->json($this->formatMember($member->fresh()));
    }

    /**
     * DELETE /api/vendor/{vendorId}/team/{memberId}
     */
    public function destroy(Request $request, string $vendorId, string $memberId): JsonResponse
    {
        $vendor = $this->resolveVendor($vendorId);
        $this->authorizeVendor($request, $vendor);

        $vendor->teamMembers()->findOrFail($memberId)->delete();

        return response()->json(['message' => 'Team member removed']);
    }

    // ----------------------------------------------------------------

    private function formatMember(TeamMember $member): array
    {
        return [
            'id'          => (string) $member->id,
            'name'        => $member->name,
            'email'       => $member->email,
            'role'        => $member->role,
            'permissions' => $member->permissions ?? [],
            'status'      => $member->status,
            'invitedAt'   => $member->invited_at?->toISOString(),
            'joinedAt'    => $member->joined_at?->toISOString(),
        ];
    }

    private function resolveVendor(string $vendorId): Vendor
    {
        return Vendor::where('vendor_public_id', $vendorId)
            ->orWhere('id', $vendorId)
            ->firstOrFail();
    }

    private function authorizeVendor(Request $request, Vendor $vendor): void
    {
        $user = $request->user();
        if ($user && $user->getTable() === 'vendors' && $user->id !== $vendor->id) {
            abort(403, 'Unauthorized');
        }
    }
}
