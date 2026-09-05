<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fiscal_devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_id')->unique()->constrained()->cascadeOnDelete();

            $table->string('provider')->default('fiskaly');
            $table->string('country', 2);
            $table->string('environment')->default('sandbox');

            // AT: signature creation unit (SCU). DE: technical security system (TSS).
            $table->uuid('signature_unit_id')->nullable();
            // AT: cash register. DE: client under the TSS.
            $table->uuid('register_id')->nullable();
            $table->string('serial_number')->nullable();

            // pending | registered | initialized | failed | disabled
            $table->string('state')->default('pending');
            $table->text('last_error')->nullable();

            // DE admin PIN and AT FinanzOnline credentials. Encrypted at rest —
            // these are the vendor's tax-office secrets, not ours.
            $table->text('credentials')->nullable();

            $table->timestamp('registered_at')->nullable();
            $table->timestamp('initialized_at')->nullable();
            $table->timestamps();

            $table->index(['state', 'country']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fiscal_devices');
    }
};
