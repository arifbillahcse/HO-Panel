<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('domain_tlds', function (Blueprint $table) {
            $table->id();
            // Stored without the leading dot, lower-case: "com", "net", "co.uk".
            $table->string('tld')->unique();
            $table->foreignId('registrar_id')->constrained('domain_registrars')->cascadeOnDelete();
            $table->unsignedTinyInteger('min_years')->default(1);
            $table->unsignedTinyInteger('max_years')->default(10);
            $table->unsignedSmallInteger('grace_days')->default(0);
            $table->unsignedSmallInteger('redemption_days')->default(0);
            $table->boolean('enabled')->default(true);
            $table->timestamps();

            $table->index(['enabled', 'registrar_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('domain_tlds');
    }
};
