<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fiscal_receipts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('vendor_id')->constrained()->cascadeOnDelete();

            $table->string('provider')->default('fiskaly');
            $table->string('country', 2);
            $table->string('invoice_number')->nullable();

            // Our id for the receipt at fiskaly. Both SIGN AT and SIGN DE take a
            // client-chosen id on PUT, which is what makes a retry idempotent
            // rather than a second signature.
            $table->uuid('external_id')->unique();

            // pending | signed | failed
            $table->string('state')->default('pending');

            // Exactly what was sent, and exactly what came back. The signed
            // document must stay reproducible even if menu prices change.
            $table->json('payload');
            $table->json('response')->nullable();

            // Fields a receipt has to display.
            $table->text('qr_code_data')->nullable();
            $table->text('signature')->nullable();
            $table->string('signature_counter')->nullable();
            $table->string('receipt_number')->nullable();
            $table->string('register_serial_number')->nullable();
            $table->timestamp('signed_at')->nullable();

            $table->decimal('total_gross', 10, 2);
            $table->string('currency', 3)->default('EUR');

            $table->unsignedInteger('attempts')->default(0);
            $table->text('last_error')->nullable();

            $table->timestamps();

            $table->index(['vendor_id', 'state']);
            $table->index('state');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fiscal_receipts');
    }
};
