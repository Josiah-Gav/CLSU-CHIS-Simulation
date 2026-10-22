<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('encounters', function (Blueprint $table) {
            $table->id();
            // Assigned right after insert from the row id (ENC-YYYY-NNNNNN), hence nullable.
            $table->string('chis_encounter_id', 20)->nullable()->unique();
            // The idempotency key. The unique index is what makes two concurrent
            // identical submissions resolve to one record.
            $table->unsignedBigInteger('telemed_encounter_id')->unique();
            $table->foreignId('patient_id')->constrained('patients');
            $table->string('encounter_type', 20);
            $table->text('diagnosis');
            $table->timestamp('completed_at');
            $table->json('payload');
            $table->char('payload_hash', 64);
            $table->timestamp('received_at');
            $table->timestamps();

            $table->index('received_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('encounters');
    }
};
