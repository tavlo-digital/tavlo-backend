<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SubscriptionPlan extends Model
{
    protected $fillable = [
        'name',
        'description',
        'monthly_price',
        'yearly_price',
        'currency',
        'max_users',
        'parent_plan_id',
        'is_popular',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'monthly_price' => 'decimal:2',
            'yearly_price' => 'decimal:2',
            'is_popular' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function parentPlan()
    {
        return $this->belongsTo(SubscriptionPlan::class, 'parent_plan_id');
    }

    public function childPlans()
    {
        return $this->hasMany(SubscriptionPlan::class, 'parent_plan_id');
    }

    public function features()
    {
        return $this->belongsToMany(Feature::class, 'plan_features', 'plan_id', 'feature_id')
            ->withPivot('is_inherited')
            ->withTimestamps();
    }

    public function subscriptions()
    {
        return $this->hasMany(Subscription::class, 'plan_id');
    }
}
