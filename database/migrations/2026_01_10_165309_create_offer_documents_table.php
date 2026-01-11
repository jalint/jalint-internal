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
        Schema::create('offer_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('offer_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->string('type');
            // contoh: subkon_letter

            $table->string('uploaded_by_role');
            // admin_kuptdk | customer

            $table->string('file_path');

            $table->timestamps();

            $table->index(['offer_id', 'type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('offer_documents');
    }
};
