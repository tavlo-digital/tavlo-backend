<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_session_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('table_scan_session_id')->constrained('table_scan_sessions')->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->string('endpoint');
            $table->string('method', 10);
            $table->timestamps();

            $table->index(['table_scan_session_id', 'created_at'], 'csa_session_created_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_session_activities');
    }
};
