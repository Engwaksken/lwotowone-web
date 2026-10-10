<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

return new class extends Migration {
    public function up(): void
    {
        Schema::table('certificate_templates', fn (Blueprint $table) => $table->unsignedBigInteger('course_id')->nullable()->change());
        if (!Schema::hasColumn('certificate_templates', 'event_id')) {
            Schema::table('certificate_templates', fn (Blueprint $table) => $table->foreignId('event_id')->nullable()->unique()->constrained('events')->restrictOnDelete());
        }
        if (!Schema::hasColumn('certificate_templates', 'name')) {
            Schema::table('certificate_templates', fn (Blueprint $table) => $table->string('name')->nullable());
        }
        DB::table('certificate_templates')->whereNull('name')->orderBy('id')->chunkById(100, function ($templates) {
            foreach ($templates as $template) {
                $title = $template->course_id
                    ? DB::table('courses')->where('id', $template->course_id)->value('title')
                    : DB::table('events')->where('id', $template->event_id)->value('title');
                DB::table('certificate_templates')->where('id', $template->id)->update(['name' => mb_substr(($title ?? 'Certificate').' certificate', 0, 255)]);
            }
        });
    }

    public function down(): void
    {
        if (DB::table('certificate_templates')->whereNull('course_id')->exists()) {
            throw new \RuntimeException('Cannot roll back while event certificate templates exist. Export or remove those templates before retrying; no data has been removed.');
        }
        Schema::table('certificate_templates', fn (Blueprint $table) => $table->dropForeign(['event_id']));
        Schema::table('certificate_templates', fn (Blueprint $table) => $table->dropUnique(['event_id']));
        Schema::table('certificate_templates', fn (Blueprint $table) => $table->dropColumn('event_id'));
        Schema::table('certificate_templates', fn (Blueprint $table) => $table->dropColumn('name'));
        Schema::table('certificate_templates', fn (Blueprint $table) => $table->unsignedBigInteger('course_id')->nullable(false)->change());
    }
};
