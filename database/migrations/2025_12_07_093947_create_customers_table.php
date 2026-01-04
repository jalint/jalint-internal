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
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->foreignId('customer_type_id')
                  ->constrained('customer_types')
                  ->noActionOnDelete();
            $table->boolean('status')->default(true);
            $table->string('email')->unique();
            $table->string('npwp')->unique();
            $table->string('website');
            $table->text('address');
            $table->string('city');
            $table->string('postal_code');
            $table->string('province');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
