<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('patients', function (Blueprint $table) {
            $table->id();
            // A string, never a number: leading zeros and the dash are part of the
            // ID, and the three CLSU formats (YY-NNNN, YY-NNNNNN, YYYY-NNNN) differ.
            $table->string('clsu_id', 50)->unique();
            $table->string('first_name', 100);
            $table->string('middle_name', 100)->nullable();
            $table->string('last_name', 100);
            $table->string('sex', 10)->nullable();
            $table->date('birth_date')->nullable();
            $table->string('patient_type', 20);
            $table->string('college', 150)->nullable();
            $table->string('blood_type', 5)->nullable();
            // The simulator stores the profile as one document. The API contract,
            // not this storage shape, is what Telemed depends on.
            $table->json('medical_profile');
            $table->timestamp('record_updated_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('patients');
    }
};
