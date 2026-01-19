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
        Schema::create('lhp_documents', function (Blueprint $table) {
            $table->id();

            $table->foreignId('offer_id')->constrained()->cascadeOnDelete();

            $table->string('job_number')->unique();
            $table->date('tanggal_dilaporkan');

            // status global (high-level)
            $table->enum('status', [
                'draft',            // dibuat admin input
                'in_analysis',      // analis mengisi
                'in_review',        // penyelia / admin / MT / prelim
                'validated',        // final approved
                'revised',          // ada revisi aktif
            ])->default('draft');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lhp_documents');
    }
};
