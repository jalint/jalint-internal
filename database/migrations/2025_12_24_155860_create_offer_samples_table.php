<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('offer_samples', function (Blueprint $table) {
            $table->id();

            $table->foreignId('offer_id')
                ->constrained('offers')
                ->cascadeOnDelete();

            // Judul contoh uji (misal: "Contoh Uji 1 - Air Limbah")
            $table->string('title');

            $table->timestamps();

            // Optional tapi direkomendasikan
            $table->index(['offer_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('offer_samples');
    }
};
