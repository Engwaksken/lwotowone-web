<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};
use Illuminate\Support\Str;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasColumn('events', 'registration_token')) {
            Schema::table('events', fn (Blueprint $table) => $table->uuid('registration_token')->nullable()->unique());
        }
        DB::table('events')->whereNull('registration_token')->orderBy('id')->chunkById(100, function ($events) {
            foreach ($events as $event) DB::table('events')->where('id', $event->id)->update(['registration_token' => (string)Str::uuid()]);
        });
        Schema::table('event_registrations', fn (Blueprint $table) => $table->unsignedBigInteger('user_id')->nullable()->change());
        foreach (['participant_name', 'participant_email', 'participant_phone'] as $column) {
            if (!Schema::hasColumn('event_registrations', $column)) Schema::table('event_registrations', fn (Blueprint $table) => $table->string($column)->nullable());
        }
        foreach (['attended_at', 'attendance_recorded_at'] as $column) {
            if (!Schema::hasColumn('event_registrations', $column)) Schema::table('event_registrations', fn (Blueprint $table) => $table->dateTime($column)->nullable());
        }
        if (!Schema::hasColumn('event_registrations', 'attendance_recorded_by')) Schema::table('event_registrations', fn (Blueprint $table) => $table->foreignId('attendance_recorded_by')->nullable()->constrained('users')->nullOnDelete());
        DB::table('event_registrations')->whereNotNull('user_id')->whereNull('participant_email')->orderBy('id')->chunkById(100, function ($registrations) {
            foreach ($registrations as $registration) {
                $user = DB::table('users')->find($registration->user_id);
                if ($user) DB::table('event_registrations')->where('id', $registration->id)->update(['participant_name' => $user->name, 'participant_email' => strtolower($user->email), 'participant_phone' => $user->phone]);
            }
        });
        if (!Schema::hasIndex('event_registrations', 'event_registrations_event_email_unique')) Schema::table('event_registrations', fn (Blueprint $table) => $table->unique(['event_id', 'participant_email'], 'event_registrations_event_email_unique'));

        if (!Schema::hasTable('surveys')) Schema::create('surveys', function (Blueprint $table) {
            $table->id(); $table->uuid('token')->unique(); $table->string('title'); $table->text('description')->nullable();
            $table->string('status')->default('draft')->index(); $table->boolean('anonymous')->default(true); $table->json('questions');
            $table->dateTime('opens_at')->nullable(); $table->dateTime('closes_at')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete(); $table->timestamps();
        });
        if (!Schema::hasTable('survey_responses')) Schema::create('survey_responses', function (Blueprint $table) {
            $table->id(); $table->foreignId('survey_id')->constrained('surveys')->restrictOnDelete();
            $table->uuid('submission_token'); $table->boolean('anonymous');
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('respondent_name')->nullable(); $table->string('respondent_email')->nullable();
            $table->json('questions_snapshot'); $table->json('answers'); $table->dateTime('submitted_at')->index(); $table->timestamps();
            $table->unique(['survey_id', 'submission_token']);
        });
    }

    public function down(): void
    {
        if (DB::table('event_registrations')->whereNull('user_id')->exists() || DB::table('survey_responses')->exists()) {
            throw new \RuntimeException('Export and remove guest event registrations and survey responses before rollback. No data has been removed.');
        }
        Schema::dropIfExists('survey_responses'); Schema::dropIfExists('surveys');
        Schema::table('event_registrations', fn (Blueprint $table) => $table->dropForeign(['attendance_recorded_by']));
        Schema::table('event_registrations', fn (Blueprint $table) => $table->dropUnique('event_registrations_event_email_unique'));
        Schema::table('event_registrations', fn (Blueprint $table) => $table->dropColumn(['participant_name', 'participant_email', 'participant_phone', 'attended_at', 'attendance_recorded_at', 'attendance_recorded_by']));
        Schema::table('event_registrations', fn (Blueprint $table) => $table->unsignedBigInteger('user_id')->nullable(false)->change());
        Schema::table('events', fn (Blueprint $table) => $table->dropUnique(['registration_token']));
        Schema::table('events', fn (Blueprint $table) => $table->dropColumn('registration_token'));
    }
};
