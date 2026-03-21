<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FeatureUsage extends Model
{
    protected $table = 'feature_usage';

    protected $fillable = [
        'vendor_id',
        'feature_id',
        'usage_value',
        'period_start',
        'period_end',
    ];

    protected function casts(): array
    {
        return [
            'period_start' => 'date',
            'period_end' => 'date',
        ];
    }

    public function vendor()
    {
        return $this->belongsTo(Vendor::class);
    }

    public function feature()
    {
        return $this->belongsTo(Feature::class);
    }
}
