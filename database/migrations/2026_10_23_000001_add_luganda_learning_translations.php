<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->string('preferred_language', 2)->default('en'));
        Schema::table('lessons', function (Blueprint $table): void {
            $table->string('content_language', 2)->default('en');
            $table->string('title')->nullable()->change();
            $table->text('body')->nullable()->change();
        });

        foreach ([
            'programs' => ['title_lg', 'summary_lg', 'body_lg'],
            'courses' => ['title_lg', 'summary_lg'],
            'course_modules' => ['title_lg'],
            'lessons' => ['title_lg', 'body_lg', 'content_body_lg'],
            'assignments' => ['title_lg', 'instructions_lg'],
            'resources' => ['title_lg', 'description_lg', 'content_body_lg'],
        ] as $tableName => $columns) {
            Schema::table($tableName, function (Blueprint $table) use ($columns): void {
                foreach ($columns as $column) $table->text($column)->nullable();
            });
        }
    }

    public function down(): void
    {
        DB::table('lessons')->whereNull('title')->update(['title' => '']);
        DB::table('lessons')->whereNull('body')->update(['body' => '']);
        foreach ([
            'programs' => ['title_lg', 'summary_lg', 'body_lg'],
            'courses' => ['title_lg', 'summary_lg'],
            'course_modules' => ['title_lg'],
            'lessons' => ['title_lg', 'body_lg', 'content_body_lg'],
            'assignments' => ['title_lg', 'instructions_lg'],
            'resources' => ['title_lg', 'description_lg', 'content_body_lg'],
        ] as $tableName => $columns) {
            Schema::table($tableName, fn (Blueprint $table) => $table->dropColumn($columns));
        }

        Schema::table('lessons', function (Blueprint $table): void {
            $table->string('title')->nullable(false)->change();
            $table->text('body')->nullable(false)->change();
            $table->dropColumn('content_language');
        });
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('preferred_language'));
    }
};
