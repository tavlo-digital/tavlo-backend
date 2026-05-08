<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Laravel\Sanctum\HasApiTokens;

class TeamMember extends Authenticatable
{
    use HasApiTokens, Notifiable;

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
            'password'     => 'hashed',
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
            'kitchen' => ['orders.view', 'orders.manage', 'orders.kitchen'],
            'waiter'  => ['orders.view', 'orders.manage', 'tables.close'],
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
