<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admin_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action_type'); // restricted_data_access, personal_data_export, anonymization, account_deletion, refund_approval, etc.
            $table->string('target_type'); // customer, order, refund, etc.
            $table->unsignedBigInteger('target_id');
            $table->text('reason')->nullable();
            $table->json('metadata')->nullable();
            $table->string('ip_address')->nullable();
            $table->timestamps();

            $table->index(['target_type', 'target_id']);
            $table->index('admin_id');
            $table->index('action_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
