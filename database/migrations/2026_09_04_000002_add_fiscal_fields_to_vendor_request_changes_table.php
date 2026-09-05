<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vendor_request_changes', function (Blueprint $table) {
            // Cash register registration is part of a vendor's legal and tax
            // identity, so it travels through the same approval as the rest of
            // it rather than on a track of its own.
            $table->string('fon_participant_id')->nullable();
            $table->string('fon_user_id')->nullable();
            // Encrypted, and cleared once it has been handed to the device.
            $table->text('fon_user_pin')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('vendor_request_changes', function (Blueprint $table) {
            $table->dropColumn(['fon_participant_id', 'fon_user_id', 'fon_user_pin']);
        });
    }
};
