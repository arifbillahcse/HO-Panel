<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('domains', function (Blueprint $table) {
            // The currency the domain was ordered in, so renewals bill in the
            // same currency rather than guessing a default.
            $table->string('currency', 3)->nullable()->after('tld');
        });
    }

    public function down(): void
    {
        Schema::table('domains', function (Blueprint $table) {
            $table->dropColumn('currency');
        });
    }
};
