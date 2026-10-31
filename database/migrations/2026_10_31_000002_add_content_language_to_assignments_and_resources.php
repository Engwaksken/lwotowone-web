<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Each learning language has its own assignments and resources, like lessons already do.
 * English content lives in the base columns and Luganda content in the *_lg columns.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('assignments', function (Blueprint $table): void {
            $table->string('content_language', 2)->default('en')->index();
            $table->string('title')->nullable()->change();
            $table->text('instructions')->nullable()->change();
        });
        Schema::table('resources', function (Blueprint $table): void {
            $table->string('content_language', 2)->default('en')->index();
            $table->string('title')->nullable()->change();
            $table->text('description')->nullable()->change();
        });

        // A resource attached to a lesson belongs to that lesson's language.
        DB::table('resources')->whereNotNull('lesson_id')->orderBy('id')->each(function ($resource): void {
            $language = DB::table('lessons')->where('id', $resource->lesson_id)->value('content_language');
            if ($language && $language !== 'en') DB::table('resources')->where('id', $resource->id)->update(['content_language' => $language]);
        });

        $this->splitAssignments();
        $this->splitResources();
    }

    private static function filled($value): bool
    {
        return is_string($value) && trim($value) !== '';
    }

    /** Complete Luganda translations stored on English assignments become their own Luganda assignments. */
    private function splitAssignments(): void
    {
        DB::table('assignments')->where('content_language', 'en')->orderBy('id')->get()->each(function ($row): void {
            if (!self::filled($row->title_lg) || !self::filled($row->instructions_lg)) return;
            $copy = (array) $row;
            unset($copy['id']);
            $copy = array_merge($copy, ['content_language' => 'lg', 'title' => null, 'instructions' => null, 'created_at' => now(), 'updated_at' => now()]);
            $newId = DB::table('assignments')->insertGetId($copy);
            DB::table('assignments')->where('id', $row->id)->update(['title_lg' => null, 'instructions_lg' => null]);
            DB::table('learning_attachments')->where('entity_type', 'assignment')->where('entity_id', $row->id)->get()
                ->each(fn ($file) => DB::table('learning_attachments')->insert(['entity_type' => 'assignment', 'entity_id' => $newId, 'file_path' => $file->file_path, 'original_name' => $file->original_name, 'created_at' => now(), 'updated_at' => now()]));
        });
    }

    /** Complete Luganda translations stored on English resources become their own Luganda resources. */
    private function splitResources(): void
    {
        DB::table('resources')->where('content_language', 'en')->orderBy('id')->get()->each(function ($row): void {
            $writtenText = ($row->content_source ?? null) === 'write';
            if (!self::filled($row->title_lg) || !self::filled($row->description_lg) || ($writtenText && !self::filled($row->content_body_lg))) return;
            $copy = (array) $row;
            unset($copy['id']);
            // Editing a resource deletes its replaced file, so the copy gets its own file.
            if ($row->file_path && Storage::disk('local')->exists($row->file_path)) {
                $path = dirname($row->file_path).'/'.Str::random(40).'.'.pathinfo($row->file_path, PATHINFO_EXTENSION);
                Storage::disk('local')->copy($row->file_path, $path);
                $copy['file_path'] = $path;
            }
            $copy = array_merge($copy, ['content_language' => 'lg', 'title' => null, 'description' => null, 'content_body' => null, 'lesson_id' => null, 'created_at' => now(), 'updated_at' => now()]);
            DB::table('resources')->insert($copy);
            DB::table('resources')->where('id', $row->id)->update(['title_lg' => null, 'description_lg' => null, 'content_body_lg' => null]);
        });
    }

    public function down(): void
    {
        DB::table('assignments')->whereNull('title')->update(['title' => DB::raw("COALESCE(title_lg, '')")]);
        DB::table('assignments')->whereNull('instructions')->update(['instructions' => DB::raw("COALESCE(instructions_lg, '')")]);
        DB::table('resources')->whereNull('title')->update(['title' => DB::raw("COALESCE(title_lg, '')")]);
        DB::table('resources')->whereNull('description')->update(['description' => DB::raw("COALESCE(description_lg, '')")]);
        Schema::table('assignments', function (Blueprint $table): void {
            $table->dropIndex(['content_language']);
            $table->dropColumn('content_language');
            $table->string('title')->nullable(false)->change();
            $table->text('instructions')->nullable(false)->change();
        });
        Schema::table('resources', function (Blueprint $table): void {
            $table->dropIndex(['content_language']);
            $table->dropColumn('content_language');
            $table->string('title')->nullable(false)->change();
            $table->text('description')->nullable(false)->change();
        });
    }
};
