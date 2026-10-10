<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // MySQL DDL is not transactional; completed additions can survive a retry.
        foreach (['course', 'program'] as $entity) {
            $memberships = $entity.'_instructors';
            $parent = $entity.'s';
            $parentKey = $entity.'_id';

            if (!Schema::hasTable($memberships)) {
                Schema::create($memberships, function (Blueprint $table) use ($parent, $parentKey) {
                    $table->id();
                    $table->foreignId($parentKey)->constrained($parent)->cascadeOnDelete();
                    $table->foreignId('user_id')->index()->constrained('users')->cascadeOnDelete();
                    $table->timestamps();
                    // Also indexes parent lookups and supports the lead's composite FK.
                    $table->unique([$parentKey, 'user_id']);
                });
            }

            $leads = $entity.'_instructor_leads';
            if (!Schema::hasTable($leads)) {
                Schema::create($leads, function (Blueprint $table) use ($parent, $parentKey, $memberships) {
                    $table->id();
                    $table->foreignId($parentKey)->unique()->constrained($parent)->cascadeOnDelete();
                    $table->foreignId('user_id')->index()->constrained('users')->cascadeOnDelete();
                    $table->timestamps();
                    $table->index([$parentKey, 'user_id']);
                    // No row means no lead. Removing a membership clears its lead.
                    $table->foreign([$parentKey, 'user_id'])
                        ->references([$parentKey, 'user_id'])
                        ->on($memberships)
                        ->cascadeOnDelete();
                });
            }
        }

        // Preserve all legacy values; neither role nor lead privilege is inferred.
        // NOT EXISTS makes a retry additive without suppressing FK/data errors.
        DB::table('course_instructors')->insertUsing(
            ['course_id', 'user_id', 'created_at', 'updated_at'],
            DB::table('courses')
                ->select('courses.id', 'courses.instructor_id', 'courses.created_at', 'courses.updated_at')
                ->whereNotNull('courses.instructor_id')
                ->whereNotExists(function ($query) {
                    $query->selectRaw('1')->from('course_instructors')
                        ->whereColumn('course_instructors.course_id', 'courses.id')
                        ->whereColumn('course_instructors.user_id', 'courses.instructor_id');
                })
        );

        // Change only nullability: retain the existing FK, index and all values.
        Schema::table('courses', function (Blueprint $table) {
            $table->unsignedBigInteger('instructor_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        // Never invent an instructor or delete a course to restore NOT NULL.
        // Check before any DDL, especially on non-transactional MySQL.
        if (DB::table('courses')->whereNull('instructor_id')->exists()) {
            throw new \RuntimeException(
                'Cannot roll back instructor memberships: courses.instructor_id contains NULL. '
                .'Explicitly assign legacy instructors before retrying; no data has been removed.'
            );
        }

        Schema::table('courses', function (Blueprint $table) {
            $table->unsignedBigInteger('instructor_id')->nullable(false)->change();
        });

        Schema::dropIfExists('program_instructor_leads');
        Schema::dropIfExists('course_instructor_leads');
        Schema::dropIfExists('program_instructors');
        Schema::dropIfExists('course_instructors');
    }
};
