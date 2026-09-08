<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MenuItemView extends Model
{
    const UPDATED_AT = null;

    protected $fillable = [
        'vendor_id',
        'menu_item_id',
        'table_scan_session_id',
    ];

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function menuItem(): BelongsTo
    {
        return $this->belongsTo(MenuItem::class);
    }

    public function tableScanSession(): BelongsTo
    {
        return $this->belongsTo(TableScanSession::class);
    }
}
