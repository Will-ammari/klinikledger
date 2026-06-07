<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('patient_exports', function (Blueprint $table) {
            $table->id();

            $table->foreignId('clinic_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('patient_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('requested_by_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->json('payload');
            $table->timestamp('generated_at');

            $table->timestamps();

            $table->index(['clinic_id', 'patient_id']);
            $table->index(['clinic_id', 'requested_by_user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('patient_exports');
    }
};
