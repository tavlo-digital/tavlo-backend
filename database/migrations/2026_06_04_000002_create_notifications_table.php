<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('event');
            $table->text('message');
            $table->boolean('read')->default(false);
            $table->string('user_role');
            $table->timestamps();

            $table->index(['user_id', 'user_role']);
            $table->index(['user_id', 'read']);
            $table->index('event');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
