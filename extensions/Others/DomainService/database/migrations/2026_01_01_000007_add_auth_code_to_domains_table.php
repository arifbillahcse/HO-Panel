<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('domains', function (Blueprint $table) {
            // The inbound transfer auth code, held only until the transfer is
            // submitted to the registrar, then cleared. Encrypted at rest via
            // the model cast.
            $table->text('auth_code')->nullable()->after('currency');
        });
    }

    public function down(): void
    {
        Schema::table('domains', function (Blueprint $table) {
            $table->dropColumn('auth_code');
        });
    }
};
