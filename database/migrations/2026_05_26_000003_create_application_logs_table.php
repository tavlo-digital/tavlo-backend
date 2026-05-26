<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('application_logs', function (Blueprint $table) {
            $table->id();
            $table->string('level', 20)->index();
            $table->text('message');
            $table->json('context')->nullable();
            $table->string('channel', 50)->nullable();
            $table->timestamp('logged_at')->useCurrent()->index();

            $table->index(['level', 'logged_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('application_logs');
    }
};
