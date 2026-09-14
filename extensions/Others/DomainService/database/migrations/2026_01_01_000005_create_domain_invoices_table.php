<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Links a Paymenter invoice to the domain action it pays for, so the
        // Invoice\Paid listener knows what to do without overloading anything
        // on the invoice itself.
        Schema::create('domain_invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained('invoices')->cascadeOnDelete();
            $table->foreignId('domain_id')->constrained('domains')->cascadeOnDelete();
            // register | renew | transfer
            $table->string('action');
            $table->unsignedTinyInteger('years')->default(1);
            $table->boolean('processed')->default(false);
            $table->timestamps();

            $table->index(['invoice_id', 'processed']);
            $table->index('domain_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('domain_invoices');
    }
};
