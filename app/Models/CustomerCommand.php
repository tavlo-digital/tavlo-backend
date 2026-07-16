<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CustomerCommand extends Model
{
    protected $fillable = [
        'command_id',
        'customer_id',
        'table_scan_session_id',
        'sequence',
        'operation',
        'status',
        'payload',
        'response',
        'error',
        'processed_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'response' => 'array',
            'sequence' => 'integer',
            'processed_at' => 'datetime',
        ];
    }
}
