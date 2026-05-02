<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CartItem extends Model
{
    protected $fillable = [
        'table_scan_session_id',
        'menu_item_id',
        'quantity',
        'notes',
        'order_ids',
        'preparing_start_at',
        'ready_at',
    ];

    protected function casts(): array
    {
        return [
            'quantity'           => 'integer',
            'order_ids'          => 'array',
            'preparing_start_at' => 'datetime',
            'ready_at'           => 'datetime',
        ];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(TableScanSession::class, 'table_scan_session_id');
    }

    public function menuItem(): BelongsTo
    {
        return $this->belongsTo(MenuItem::class);
    }
}
