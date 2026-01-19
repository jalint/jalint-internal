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
        Schema::create('lhp_document_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lhp_document_id')->constrained()->cascadeOnDelete();
            $table->string('identifikasi_laboratorium');
            $table->string('identifikasi_contoh_uji');
            $table->foreignId('sample_matric_id')->constrained()->restrictOnDelete();
            $table->date('tanggal_pengambilan')->nullable();
            $table->date('tanggal_penerimaan')->nullable();
            $table->time('waktu_pengambilan')->nullable();
            $table->time('waktu_penerimaan')->nullable();
            $table->date('waktu_analisis_end')->nullable();
            $table->date('waktu_analisis_end')->nullable();
            $table->string('koordinat_lintang')->nullable();
            $table->string('koordinat_bujur')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lhp_document_details');
    }
};
