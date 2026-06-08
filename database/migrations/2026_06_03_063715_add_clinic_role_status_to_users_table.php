<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('clinic_id')
                ->nullable()
                ->after('id')
                ->constrained()
                ->cascadeOnDelete();

            $table->string('role')
                ->default('owner_clinic')
                ->after('password');

            $table->string('status')
                ->default('active')
                ->after('role');

            $table->index(['clinic_id', 'role']);
            $table->index(['clinic_id', 'email']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['clinic_id']);
            $table->dropIndex(['clinic_id', 'role']);
            $table->dropIndex(['clinic_id', 'email']);
            $table->dropColumn(['clinic_id', 'role', 'status']);
        });
    }
};
