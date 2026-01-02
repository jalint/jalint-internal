<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('test_parameters', function (Blueprint $table) {
            $table->id();

            // Core identity
            $table->string('name')->unique();
            $table->string('code')->unique();
            $table->string('unit')->nullable(); // e.g. mg/L, %, °C
            $table->foreignId('sample_type_id')
             ->constrained('sample_types')->restrictOnDelete();

            // Pricing
            $table->decimal('price', 12, 2)->default(0);
            $table->foreignId('test_method_id')
             ->constrained('test_methods')->noActionOnDelete();

            // Standard value range
            $table->decimal('standard_min_value', 12, 4)->nullable();
            $table->decimal('standard_max_value', 12, 4)->nullable();
            $table->string('standard_unit')->nullable();

            // Metadata
            $table->enum('status', ['A', 'N/A', 'S'])
                  ->default('A');

            $table->text('standard_note')->nullable();
            $table->text('description')->nullable();

            $table->timestamps();

            // Optional indexes
            $table->index('name');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('test_parameters');
    }
};
