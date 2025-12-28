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
        Schema::create('offer_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('offer_id')->constrained()->restrictOnDelete();
            $table->foreignId('test_parameter_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('test_package_id')->nullable()->constrained()->restrictOnDelete();
            $table->decimal('price', 12, 2)->default(0);
            $table->integer('qty');
            $table->string('sample_tested_by')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('offer_details');
    }
};
