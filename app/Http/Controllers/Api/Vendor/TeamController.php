<?php

namespace App\Http\Controllers\Api\Vendor;

use App\Http\Controllers\Controller;
use App\Mail\TeamInvitationMail;
use App\Models\TeamMember;
use App\Models\Vendor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;

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
            ->orderBy('role')
            ->orderBy('email')
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
            'email'       => ['required', 'email', 'max:255'],
            'role'        => ['required', Rule::in(['waiter', 'kitchen'])],
        ]);

        $email = strtolower($data['email']);

        if (Vendor::whereRaw('lower(email) = ?', [$email])->exists()) {
            return response()->json(['message' => 'This email already belongs to a vendor account.'], 422);
        }

        if (TeamMember::whereRaw('lower(email) = ?', [$email])->exists()) {
            return response()->json(['message' => 'A team member with this email already exists.'], 422);
        }

        $permissions = TeamMember::defaultPermissions($data['role']);

        $member = $vendor->teamMembers()->create([
            'name'             => $this->nameFromEmail($email),
            'email'            => $email,
            'role'             => $data['role'],
            'permissions'      => $permissions,
            'status'           => 'invited',
            'invitation_token' => TeamMember::generateInvitationToken(),
            'invited_at'       => now(),
        ]);

        Mail::to($member->email)->send(new TeamInvitationMail($member->load('vendor')));

        return response()->json($this->formatMember($member), 201);
    }

    /**
     * GET /api/vendor/team/invitations/{token}
     */
    public function invitation(string $token): JsonResponse
    {
        $member = TeamMember::with('vendor')
            ->where('invitation_token', $token)
            ->where('status', 'invited')
            ->firstOrFail();

        return response()->json($this->formatInvitation($member));
    }

    /**
     * POST /api/vendor/team/invitations/{token}/accept
     */
    public function acceptInvitation(Request $request, string $token): JsonResponse
    {
        $member = TeamMember::with('vendor')
            ->where('invitation_token', $token)
            ->where('status', 'invited')
            ->firstOrFail();

        $data = $request->validate([
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $member->update([
            'password'         => $data['password'],
            'status'           => 'active',
            'invitation_token' => null,
            'joined_at'        => now(),
        ]);

        return response()->json([
            'message' => 'Invitation accepted. You can now sign in.',
            'member'  => $this->formatInvitation($member->fresh('vendor')),
        ]);
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
            'role'        => ['sometimes', Rule::in(['waiter', 'kitchen'])],
            'permissions' => ['sometimes', 'array'],
            'status'      => ['sometimes', Rule::in(['invited', 'active', 'suspended'])],
        ]);

        if (isset($data['role']) && ! array_key_exists('permissions', $data)) {
            $data['permissions'] = TeamMember::defaultPermissions($data['role']);
        }

        $member->update($data);

        return response()->json($this->formatMember($member->fresh()));
    }

    /**
     * POST /api/vendor/{vendorId}/team/{memberId}/resend
     */
    public function resendInvite(Request $request, string $vendorId, string $memberId): JsonResponse
    {
        $vendor = $this->resolveVendor($vendorId);
        $this->authorizeVendor($request, $vendor);

        $member = $vendor->teamMembers()
            ->where('status', 'invited')
            ->findOrFail($memberId);

        $member->update([
            'invitation_token' => TeamMember::generateInvitationToken(),
            'invited_at'       => now(),
        ]);

        Mail::to($member->email)->send(new TeamInvitationMail($member->fresh('vendor')));

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

    private function formatInvitation(TeamMember $member): array
    {
        return [
            'email'      => $member->email,
            'role'       => $member->role,
            'status'     => $member->status,
            'vendorId'   => $member->vendor ? (string) $member->vendor->id : null,
            'vendorName' => $member->vendor?->restaurant_name ?: $member->vendor?->name,
        ];
    }

    private function nameFromEmail(string $email): string
    {
        $name = str_replace(['.', '_', '-'], ' ', explode('@', $email)[0]);

        return ucwords($name);
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
