<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class TeamMember extends Model
{
    protected $table = 'team_members';

    protected $hidden = ['password', 'invitation_token'];

    protected $fillable = [
        'vendor_id',
        'name',
        'email',
        'password',
        'role',
        'permissions',
        'status',
        'invitation_token',
        'invited_at',
        'joined_at',
    ];

    protected function casts(): array
    {
        return [
            'permissions'  => 'array',
            'invited_at'   => 'datetime',
            'joined_at'    => 'datetime',
        ];
    }

    public function vendor(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    /**
     * Default permissions per role.
     */
    public static function defaultPermissions(string $role): array
    {
        return match ($role) {
            'manager' => [
                'view_orders', 'manage_orders', 'view_menu', 'manage_menu',
                'view_inventory', 'manage_inventory', 'view_reviews', 'manage_reviews',
                'view_reservations', 'manage_reservations', 'view_analytics',
            ],
            'kitchen' => ['view_orders', 'manage_orders', 'view_menu', 'view_inventory'],
            'waiter'  => ['view_orders', 'manage_orders', 'view_reservations', 'manage_reservations'],
            default   => [],
        };
    }

    /**
     * Generate a fresh secure invitation token.
     */
    public static function generateInvitationToken(): string
    {
        return Str::random(64);
    }
}
