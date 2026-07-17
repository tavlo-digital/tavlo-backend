<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StaffCommand extends Model
{
    protected $fillable = [
        'command_id',
        'idempotency_key',
        'team_member_id',
        'vendor_id',
        'actor_role',
        'operation',
        'status',
        'payload',
        'resource_sequences',
        'http_status',
        'response',
        'error',
        'processed_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'resource_sequences' => 'array',
            'response' => 'array',
            'http_status' => 'integer',
            'processed_at' => 'datetime',
        ];
    }
}
