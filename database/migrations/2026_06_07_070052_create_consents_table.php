<?php

use App\Enums\ConsentStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('consents', function (Blueprint $table) {
            $table->id();

            $table->foreignId('clinic_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('patient_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('granted_by_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->foreignId('withdrawn_by_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->string('type');
            $table->string('status')->default(ConsentStatus::Granted->value);

            $table->timestamp('granted_at')->nullable();
            $table->timestamp('withdrawn_at')->nullable();

            $table->text('notes')->nullable();

            $table->timestamps();

            $table->index(['clinic_id', 'patient_id']);
            $table->index(['clinic_id', 'type']);
            $table->index(['clinic_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('consents');
    }
};
