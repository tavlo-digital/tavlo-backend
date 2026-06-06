<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'user_role']);
            $table->dropIndex(['user_id', 'read']);

            $table->dropColumn(['user_id', 'user_role']);

            $table->unsignedBigInteger('customer_id')->nullable()->after('id');
            $table->unsignedBigInteger('vendor_id')->nullable()->after('customer_id');
            $table->unsignedBigInteger('waiter_id')->nullable()->after('vendor_id');
            $table->unsignedBigInteger('kitchen_id')->nullable()->after('waiter_id');
            $table->unsignedBigInteger('admin_id')->nullable()->after('kitchen_id');

            $table->index('customer_id');
            $table->index('vendor_id');
            $table->index('waiter_id');
            $table->index('kitchen_id');
            $table->index('admin_id');
        });
    }

    public function down(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->dropIndex(['customer_id']);
            $table->dropIndex(['vendor_id']);
            $table->dropIndex(['waiter_id']);
            $table->dropIndex(['kitchen_id']);
            $table->dropIndex(['admin_id']);

            $table->dropColumn(['customer_id', 'vendor_id', 'waiter_id', 'kitchen_id', 'admin_id']);

            $table->unsignedBigInteger('user_id')->after('id');
            $table->string('user_role')->after('read');

            $table->index(['user_id', 'user_role']);
            $table->index(['user_id', 'read']);
        });
    }
};
