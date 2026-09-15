<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('domains', function (Blueprint $table) {
            // Nullable: the admin "Order for Customer" / "Import Domain"
            // flows create a domain with no cart order behind it at all.
            $table->foreignId('order_id')->nullable()->after('user_id')->constrained('orders')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('domains', function (Blueprint $table) {
            $table->dropConstrainedForeignId('order_id');
        });
    }
};
