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
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('nip');
            $table->string('email')->unique();
            $table->string('phone_number');
            $table->string('photo_path')->nullable();
            $table->text('address');
            $table->foreignId('position_id')
               ->constrained('positions')
               ->restrictOnDelete();
            $table->foreignId('user_id')->unique()->nullable()
               ->constrained('users')
               ->restrictOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employees');
    }
};
