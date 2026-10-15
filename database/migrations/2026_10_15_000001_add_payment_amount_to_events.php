<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasColumn('events', 'payment_amount')) {
            Schema::table('events', fn (Blueprint $table) => $table->decimal('payment_amount', 12, 2)->nullable());
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('events', 'payment_amount')) {
            Schema::table('events', fn (Blueprint $table) => $table->dropColumn('payment_amount'));
        }
    }
};
