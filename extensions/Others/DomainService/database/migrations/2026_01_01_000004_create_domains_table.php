<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('domains', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('registrar_id')->constrained('domain_registrars')->restrictOnDelete();

            // Full name ("example.com") plus its parts, so lookups by name and
            // reports by TLD are both indexed rather than computed.
            $table->string('name');
            $table->string('sld');
            $table->string('tld');

            // pending | active | expired | transfer_pending | transfer_failed | cancelled
            $table->string('status')->default('pending');

            $table->timestamp('registered_at')->nullable();
            $table->timestamp('expires_at')->nullable();

            $table->json('nameservers')->nullable();
            $table->boolean('locked')->default(false);
            $table->boolean('privacy')->default(false);
            $table->boolean('autorenew')->default(true);

            $table->timestamps();

            // Scale: every hot query path is indexed.
            $table->unique('name');
            $table->index(['user_id', 'status']);
            $table->index(['status', 'expires_at']); // renewal sweep
            $table->index('registrar_id');
            $table->index('tld');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('domains');
    }
};
