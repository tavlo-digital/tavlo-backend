<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Feature extends Model
{
    protected $fillable = [
        'name',
        'description',
        'category',
        'required_feature_id',
    ];

    public function requiredFeature()
    {
        return $this->belongsTo(Feature::class, 'required_feature_id');
    }

    public function dependentFeatures()
    {
        return $this->hasMany(Feature::class, 'required_feature_id');
    }

    public function plans()
    {
        return $this->belongsToMany(SubscriptionPlan::class, 'plan_features', 'feature_id', 'plan_id')
            ->withPivot('is_inherited')
            ->withTimestamps();
    }

    public function localizedTranslations(): HasMany
    {
        return $this->hasMany(FeatureTranslation::class);
    }
}
