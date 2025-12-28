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
        Schema::create('offer_reviews', function (Blueprint $table) {
            $table->id();

            $table->foreignId('offer_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('review_step_id')
                ->constrained('review_steps')
                ->cascadeOnDelete();

            $table->foreignId('reviewer_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->enum('decision', [
                'pending',
                'approved',
                'rejected',
            ])->default('pending');

            $table->text('note')->nullable();

            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->unique(['offer_id', 'review_step_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('offer_reviews');
    }
};
