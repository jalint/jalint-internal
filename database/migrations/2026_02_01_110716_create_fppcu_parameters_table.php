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
        Schema::create('fppcu_parameters', function (Blueprint $table) {
            $table->id();
            // Menambahkan index pada foreignId secara eksplisit untuk performa
            $table->foreignId('fppcu_id')->constrained('fppcu')->cascadeOnDelete();
            $table->string('jenis_wadah');
            $table->string('volume_contoh_uji');
            $table->string('pengawetan');
            $table->text('keterangan')->nullable(); // Menggunakan text jika isinya berpotensi panjang
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fppcu_parameters');
    }
};
