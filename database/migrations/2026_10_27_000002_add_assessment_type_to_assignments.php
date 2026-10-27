<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasColumn('assignments', 'assessment_type')) {
            Schema::table('assignments', function (Blueprint $table) {
                $table->string('assessment_type', 20)->default('assignment')->after('course_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('assignments', 'assessment_type')) {
            Schema::table('assignments', fn (Blueprint $table) => $table->dropColumn('assessment_type'));
        }
    }
};
