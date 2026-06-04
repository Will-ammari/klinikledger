<?php

use App\Enums\PatientStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('patients', function (Blueprint $table) {
            $table->id();

            $table->foreignId('clinic_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->string('first_name');
            $table->string('last_name');

            $table->string('email')->nullable();
            $table->string('phone')->nullable();

            $table->date('date_of_birth')->nullable();

            $table->string('status')->default(PatientStatus::Active->value);

            $table->string('address')->nullable();
            $table->string('city')->nullable();

            $table->timestamp('anonymized_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['clinic_id', 'status']);
            $table->index(['clinic_id', 'last_name']);
            $table->unique(['clinic_id', 'email']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('patients');
    }
};
