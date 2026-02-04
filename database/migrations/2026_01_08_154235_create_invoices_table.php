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
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();

            $table->foreignId('offer_id')
                ->constrained('offers')
                ->cascadeOnDelete();

            $table->foreignId('customer_id')
                ->constrained('customers')
                ->cascadeOnDelete();

            $table->string('invoice_number')->unique();
            $table->string('faktur_pajak_path')->nullable();

            $table->decimal('subtotal_amount', 15, 2);
            $table->decimal('discount_amount', 15, 2)->default(0);

            // SNAPSHOT PAJAK
            $table->decimal('vat_percent', 5, 2);
            $table->decimal('vat_amount', 15, 2);

            $table->decimal('pph_percent', 5, 2);
            $table->decimal('pph_amount', 15, 2);

            $table->decimal('total_amount', 15, 2);

            $table->enum('status', [
                'unpaid',     // belum ada pembayaran sah
                'partial',    // sudah ada DP / termin
                'paid',       // lunas
                'cancelled',
            ])->default('unpaid');

            $table->date('issued_at');
            $table->date('due_date');
            $table->timestamps();

            $table->index(['status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
