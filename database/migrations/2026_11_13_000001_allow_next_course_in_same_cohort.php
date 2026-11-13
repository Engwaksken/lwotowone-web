<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A learner who finishes a course can enroll in the next one in the same cohort, so a cohort
 * may now hold several of a learner's enrolments. The app still allows only one unfinished
 * course per cohort (App\Services\Enrollment). A plain index replaces the unique one so
 * foreign keys keep an index on MySQL.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('enrolments', function (Blueprint $t) {
            $t->index(['user_id', 'cohort_id'], 'enrolments_user_cohort_index');
        });
        Schema::table('enrolments', function (Blueprint $t) {
            $t->dropUnique(['user_id', 'cohort_id']);
        });
    }

    public function down(): void
    {
        Schema::table('enrolments', function (Blueprint $t) {
            $t->unique(['user_id', 'cohort_id']);
        });
        Schema::table('enrolments', function (Blueprint $t) {
            $t->dropIndex('enrolments_user_cohort_index');
        });
    }
};
