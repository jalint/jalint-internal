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
        Schema::create('offers', function (Blueprint $table) {
            $table->id();

            $table->string('offer_number')->unique();
            $table->string('title');

            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_contact_id')->nullable()->constrained()->nullOnDelete();

            $table->date('offer_date');
            $table->date('expired_date')->nullable();

            $table->string('request_number')->nullable();
            $table->foreignId('template_id')->nullable()->constrained()->nullOnDelete();

            $table->text('additional_description')->nullable();
            $table->string('testing_activities');
            $table->decimal('discount_amount', 15, 2)->default(0);
            $table->decimal('vat_percent', 5, 2)->default(0);
            $table->decimal('withholding_tax_percent', 5, 2)->default(0);

            $table->enum('status', [
                'draft',
                'in_review',
                'approved',
                'rejected',
                'canceled',
            ])->default('draft');

            $table->decimal('subtotal_amount', 15, 2)->default(0);

            $table->decimal('ppn_amount', 15, 2)->default(0);
            $table->decimal('pph_amount', 15, 2)->default(0);
            $table->decimal('total_amount', 15, 2)->default(0);      // total invoice
            $table->decimal('payable_amount', 15, 2)->default(0);    // dibayar ke vendor

            $table->foreignId('created_by')->constrained('users');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('offers');
    }
};
