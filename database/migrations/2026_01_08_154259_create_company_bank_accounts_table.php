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
        Schema::create('company_bank_accounts', function (Blueprint $table) {
            $table->id();

            $table->string('bank_name');
            $table->string('account_number');
            $table->string('account_name');

            $table->string('branch')->nullable();
            $table->string('currency', 3)->default('IDR');

            $table->boolean('is_active')->default(true);

            $table->text('note')->nullable();

            $table->timestamps();

            $table->index(['bank_name', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('company_bank_accounts');
    }
};
