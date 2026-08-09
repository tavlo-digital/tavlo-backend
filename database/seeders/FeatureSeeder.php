<?php

namespace Database\Seeders;

use App\Models\Feature;
use Illuminate\Database\Seeder;

class FeatureSeeder extends Seeder
{
    public function run(): void
    {
        $features = [
            // Menu & Content
            ['name' => 'Basic Menu Management', 'description' => 'Create and manage menu items', 'category' => 'Menu & Content'],
            ['name' => 'Menu Categories', 'description' => 'Organize items by category', 'category' => 'Menu & Content', 'requires' => 'Basic Menu Management'],
            ['name' => 'Menu Item Images', 'description' => 'Upload photos for menu items', 'category' => 'Menu & Content'],
            ['name' => 'Unlimited Menu Items', 'description' => 'No limit on menu items', 'category' => 'Menu & Content', 'requires' => 'Basic Menu Management'],
            ['name' => 'Item Modifiers & Options', 'description' => 'Size, extras, customizations', 'category' => 'Menu & Content', 'requires' => 'Basic Menu Management'],

            // Ordering & Payments
            ['name' => 'QR Code Ordering', 'description' => 'Customer QR-based ordering', 'category' => 'Ordering & Payments'],
            ['name' => 'Table Management', 'description' => 'Assign orders to tables', 'category' => 'Ordering & Payments', 'requires' => 'QR Code Ordering'],
            ['name' => 'Card Payments', 'description' => 'Stripe card processing', 'category' => 'Ordering & Payments'],
            ['name' => 'Cash Payment Option', 'description' => 'Mark orders as cash paid', 'category' => 'Ordering & Payments'],
            ['name' => 'Split Bill', 'description' => 'Divide order among customers', 'category' => 'Ordering & Payments', 'requires' => 'Card Payments'],
            ['name' => 'Tipping', 'description' => 'Customer tips at checkout', 'category' => 'Ordering & Payments', 'requires' => 'Card Payments'],

            // Analytics
            ['name' => 'Basic Analytics', 'description' => 'Sales overview & trends', 'category' => 'Analytics'],
            ['name' => 'Advanced Analytics', 'description' => 'Detailed reports & insights', 'category' => 'Analytics', 'requires' => 'Basic Analytics'],
            ['name' => 'Export Reports', 'description' => 'Download CSV/Excel reports', 'category' => 'Analytics', 'requires' => 'Basic Analytics'],
            ['name' => 'Real-Time Dashboard', 'description' => 'Live order monitoring', 'category' => 'Analytics', 'requires' => 'Advanced Analytics'],

            // Customer Engagement
            ['name' => 'Multi-Language Support', 'description' => 'German, English, Arabic', 'category' => 'Customer Engagement'],
            ['name' => 'Loyalty Program', 'description' => 'Points & rewards system', 'category' => 'Customer Engagement'],
            ['name' => 'Customer Reviews', 'description' => 'Order feedback & ratings', 'category' => 'Customer Engagement'],
            ['name' => 'Email Marketing', 'description' => 'Send promotions to customers', 'category' => 'Customer Engagement'],

            // Support & Admin
            ['name' => 'Email Support', 'description' => 'Support via email (48h)', 'category' => 'Support & Admin'],
            ['name' => 'Priority Support', 'description' => 'Faster response (24h)', 'category' => 'Support & Admin'],
            ['name' => 'Dedicated Account Manager', 'description' => 'Personal account rep', 'category' => 'Support & Admin', 'requires' => 'Priority Support'],
            ['name' => 'Multi-User Access', 'description' => 'Multiple vendor admin accounts', 'category' => 'Support & Admin'],

            // Integrations
            ['name' => 'API Access', 'description' => 'REST API for integrations', 'category' => 'Integrations'],
            ['name' => 'Webhooks', 'description' => 'Real-time event notifications', 'category' => 'Integrations', 'requires' => 'API Access'],
            ['name' => 'White-Label Options', 'description' => 'Custom branding & domain', 'category' => 'Integrations'],
            ['name' => 'POS Integration', 'description' => 'Connect to existing POS', 'category' => 'Integrations', 'requires' => 'API Access'],
        ];

        // First pass: create all features without dependencies
        $featureModels = [];
        foreach ($features as $featureData) {
            $feature = Feature::updateOrCreate(
                ['name' => $featureData['name']],
                [
                    'description' => $featureData['description'],
                    'category' => $featureData['category'],
                ]
            );

            // Baseline English row so the admin translation screen is populated.
            $feature->localizedTranslations()->updateOrCreate(
                ['language' => 'en'],
                ['name' => $featureData['name'], 'description' => $featureData['description']]
            );

            $featureModels[$featureData['name']] = $feature;
        }

        // Second pass: set required_feature_id
        foreach ($features as $featureData) {
            if (isset($featureData['requires'])) {
                $feature = $featureModels[$featureData['name']];
                $requiredFeature = $featureModels[$featureData['requires']];
                $feature->update(['required_feature_id' => $requiredFeature->id]);
            }
        }
    }
}
