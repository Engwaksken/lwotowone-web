<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        foreach (['lessons', 'resources'] as $table) {
            foreach (['content_format', 'content_source', 'content_url', 'content_body'] as $column) {
                if (!Schema::hasColumn($table, $column)) {
                    Schema::table($table, function (Blueprint $schema) use ($column) {
                        if ($column === 'content_body') $schema->text($column)->nullable();
                        else $schema->string($column, $column === 'content_url' ? 1000 : 20)->nullable();
                    });
                }
            }
        }
        if (!Schema::hasColumn('lessons', 'file_path')) {
            Schema::table('lessons', fn (Blueprint $table) => $table->string('file_path')->nullable());
        }
    }

    public function down(): void
    {
        foreach (['lessons', 'resources'] as $table) {
            Schema::table($table, fn (Blueprint $schema) => $schema->dropColumn(['content_format', 'content_source', 'content_url', 'content_body']));
        }
        Schema::table('lessons', fn (Blueprint $table) => $table->dropColumn('file_path'));
    }
};
