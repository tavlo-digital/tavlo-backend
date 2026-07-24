<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_otps', function (Blueprint $table) {
            $table->id();
            // Email the OTP was issued to. The customer row may not yet be
            // verified (registration) or may exist already (password reset).
            $table->string('email')->index();
            // Flow the code belongs to: "registration" | "password_reset".
            $table->string('purpose', 40);
            // Only the hash of the code is stored — never the plaintext OTP.
            $table->string('code_hash');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('expires_at');
            $table->timestamp('consumed_at')->nullable();
            $table->timestamp('last_sent_at')->nullable();
            $table->timestamps();

            $table->index(['email', 'purpose']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_otps');
    }
};
