<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('domain_pricing', function (Blueprint $table) {
            // Added to the renew price when a domain is renewed during its
            // redemption period (past grace, still recoverable at the
            // registry — almost every registry charges a steep premium here).
            $table->decimal('redemption_fee', 17, 2)->default(0)->after('transfer_price');
        });
    }

    public function down(): void
    {
        Schema::table('domain_pricing', function (Blueprint $table) {
            $table->dropColumn('redemption_fee');
        });
    }
};
