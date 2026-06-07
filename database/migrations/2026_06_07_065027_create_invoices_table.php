<?php

use App\Enums\InvoiceStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();

            $table->foreignId('clinic_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('patient_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('appointment_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();

            $table->string('status')->default(InvoiceStatus::Draft->value);

            $table->decimal('subtotal', 10, 2)->default(0);
            $table->decimal('tax', 10, 2)->default(0);
            $table->decimal('total', 10, 2)->default(0);

            $table->date('due_date')->nullable();
            $table->timestamp('issued_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();

            $table->timestamps();

            $table->index(['clinic_id', 'status']);
            $table->index(['clinic_id', 'patient_id']);
            $table->index(['clinic_id', 'appointment_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
