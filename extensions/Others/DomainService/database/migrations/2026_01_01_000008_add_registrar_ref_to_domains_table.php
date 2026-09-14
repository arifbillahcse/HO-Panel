<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('domains', function (Blueprint $table) {
            // A registrar's own handle for the domain. Cosmotown keys by name
            // and ignores this; ResellerClub stores its order id here. Generic
            // so any driver can use it without a schema change.
            $table->string('registrar_ref')->nullable()->after('tld');
        });
    }

    public function down(): void
    {
        Schema::table('domains', function (Blueprint $table) {
            $table->dropColumn('registrar_ref');
        });
    }
};
