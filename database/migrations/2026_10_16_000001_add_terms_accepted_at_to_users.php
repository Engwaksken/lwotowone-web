<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasColumn('users', 'terms_accepted_at')) {
            Schema::table('users', fn (Blueprint $table) => $table->dateTime('terms_accepted_at')->nullable());
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'terms_accepted_at')) {
            Schema::table('users', fn (Blueprint $table) => $table->dropColumn('terms_accepted_at'));
        }
    }
};
