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
        Schema::create('lhp_reviews', function (Blueprint $table) {
            $table->id();

            $table->foreignId('lhp_document_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lhp_step_id')->nullable()->constrained()->noActionOnDelete();
            $table->string('role');
            // analis, penyelia, admin_input, manager_teknis, admin_prelim

            $table->enum('decision', [
                'pending',
                'approved',
                'revised',
            ])->default('pending');

            $table->text('note')->nullable();
            $table->foreignId('reviewed_by')->nullable();
            $table->timestamp('reviewed_at')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lhp_reviews');
    }
};
