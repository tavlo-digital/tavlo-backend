<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staff_commands', function (Blueprint $table) {
            $table->id();
            $table->uuid('command_id')->unique();
            $table->uuid('idempotency_key');
            // Audit identity must survive actor/vendor removal so a command that
            // loses authorization while queued can still record a terminal row.
            $table->unsignedBigInteger('team_member_id');
            $table->unsignedBigInteger('vendor_id');
            $table->string('actor_role', 32);
            $table->string('operation', 64);
            $table->string('status', 24)->default('processing');
            $table->json('payload');
            $table->json('resource_sequences');
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->json('response')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->unique(['team_member_id', 'idempotency_key']);
            $table->index(['team_member_id', 'status']);
            $table->index(['vendor_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_commands');
    }
};
