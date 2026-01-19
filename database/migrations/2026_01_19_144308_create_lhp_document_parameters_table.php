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
        Schema::create('lhp_document_parameters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('offer_sample_parameter_id')->constrained()->restrictOnDelete();
            $table->text('result')->nullable();
            $table->text('description_results')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lhp_document_parameters');
    }
};
