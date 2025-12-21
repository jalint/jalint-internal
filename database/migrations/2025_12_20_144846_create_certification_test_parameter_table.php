<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('certification_test_parameter', function (Blueprint $table) {
            $table->id();

            $table->foreignId('certification_id')
                ->constrained('certifications')
                ->cascadeOnDelete();

            $table->foreignId('test_parameter_id')
                ->constrained('test_parameters')
                ->cascadeOnDelete();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('certification_test_parameter');
    }
};
