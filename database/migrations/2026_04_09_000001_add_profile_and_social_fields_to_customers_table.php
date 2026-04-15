<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->string('first_name')->after('name')->nullable();
            $table->string('last_name')->after('first_name')->nullable();
            $table->string('gender')->nullable()->after('last_name');
            $table->date('date_of_birth')->nullable()->after('gender');
            $table->text('address')->nullable()->after('date_of_birth');
            $table->string('profile_picture')->nullable()->after('address');
            $table->string('social_provider')->nullable()->after('password'); // google, apple
            $table->string('social_provider_id')->nullable()->after('social_provider');
            $table->boolean('phone_verified')->default(false)->after('email_verified_at');
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn([
                'first_name',
                'last_name',
                'gender',
                'date_of_birth',
                'address',
                'profile_picture',
                'social_provider',
                'social_provider_id',
                'phone_verified',
            ]);
        });
    }
};
