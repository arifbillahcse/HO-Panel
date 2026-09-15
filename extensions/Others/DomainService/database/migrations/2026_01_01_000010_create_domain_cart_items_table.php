<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('domain_cart_items', function (Blueprint $table) {
            $table->id();
            // Deleting the cart (checkout, or the normal cart-expiry cleanup)
            // must take its domain lines with it, same as product cart items.
            $table->foreignId('cart_id')->constrained()->cascadeOnDelete();

            $table->string('name');
            $table->string('tld');
            $table->enum('action', ['register', 'transfer']);
            $table->unsignedTinyInteger('years')->default(1);

            // Transfer only. Encrypted: worthless once the transfer starts,
            // but sits here until checkout actually submits it.
            $table->text('auth_code')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('domain_cart_items');
    }
};
