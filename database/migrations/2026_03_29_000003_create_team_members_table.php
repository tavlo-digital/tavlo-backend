<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('team_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password')->nullable(); // null until invitation accepted
            $table->string('role')->default('waiter'); // waiter, kitchen, manager
            $table->json('permissions')->nullable(); // granular permission overrides
            $table->string('status')->default('invited'); // invited, active, suspended
            $table->string('invitation_token')->nullable()->unique();
            $table->timestamp('invited_at')->nullable();
            $table->timestamp('joined_at')->nullable();
            $table->rememberToken();
            $table->timestamps();

            $table->index('vendor_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('team_members');
    }
};
