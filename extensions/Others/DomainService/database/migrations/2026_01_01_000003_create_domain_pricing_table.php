<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('domain_pricing', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tld_id')->constrained('domain_tlds')->cascadeOnDelete();
            // Matches Paymenter's currency codes (BDT, USD, …).
            $table->string('currency', 3);
            $table->decimal('register_price', 17, 2)->default(0);
            $table->decimal('renew_price', 17, 2)->default(0);
            $table->decimal('transfer_price', 17, 2)->default(0);
            $table->timestamps();

            // One price row per TLD per currency.
            $table->unique(['tld_id', 'currency']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('domain_pricing');
    }
};
