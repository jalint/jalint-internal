<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('parameter_test_package', function (Blueprint $table) {
            $table->id();

            $table->foreignId('test_package_id')
                ->constrained('test_packages')
                ->restrictOnDelete();

            $table->foreignId('test_parameter_id')
                ->constrained('test_parameters')
                ->restrictOnDelete();

            // Kalau mau aman dari duplikasi
            $table->unique(['test_package_id', 'test_parameter_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('parameter_test_package');
    }
};
