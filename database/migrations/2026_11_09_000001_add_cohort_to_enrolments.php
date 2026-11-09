<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Each enrolment belongs to a cohort, and a learner may take only one course per cohort.
 * Existing learners keep their earliest course in their current cohort; any other legacy
 * enrolments stay without a cohort so the unique rule holds.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('enrolments', function (Blueprint $t) {
            $t->foreignId('cohort_id')->nullable()->after('course_id')->constrained('mel_cohorts')->restrictOnDelete();
            $t->unique(['user_id', 'cohort_id']);
        });

        $first = DB::table('enrolments')->selectRaw('MIN(id) as id')->groupBy('user_id')->pluck('id');
        DB::table('users')->whereNotNull('cohort_id')->get(['id', 'cohort_id'])->each(function ($user) use ($first) {
            DB::table('enrolments')->where('user_id', $user->id)->whereIn('id', $first)->update(['cohort_id' => $user->cohort_id]);
        });
    }

    public function down(): void
    {
        Schema::table('enrolments', function (Blueprint $t) {
            $t->dropUnique(['user_id', 'cohort_id']);
            $t->dropConstrainedForeignId('cohort_id');
        });
    }
};
