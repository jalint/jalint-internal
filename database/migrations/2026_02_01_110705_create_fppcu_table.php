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
        Schema::create('fppcu', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lhp_document_id')->constrained()->cascadeOnDelete();
            $table->string('nama_bahan_produk');
            // Menggunakan integer agar bisa dihitung
            $table->integer('jumlah_wadah_contoh_uji');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fppcu');
    }
};
