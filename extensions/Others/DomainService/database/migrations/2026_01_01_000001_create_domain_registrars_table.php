<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('domain_registrars', function (Blueprint $table) {
            $table->id();
            // Driver key, e.g. "cosmotown" — resolves to a RegistrarDriver class.
            $table->string('driver');
            $table->string('name');
            // Encrypted at rest via the model's 'encrypted' cast.
            $table->text('credentials')->nullable();
            $table->boolean('sandbox')->default(false);
            $table->boolean('enabled')->default(true);
            $table->timestamps();

            $table->index('enabled');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('domain_registrars');
    }
};
