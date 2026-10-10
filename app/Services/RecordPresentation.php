<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RecordPresentation
{
    public static function label(string $field): string
    {
        return match ($field) {
            'course_id' => 'Course Name', 'selected_course_id' => 'Selected Course Name',
            'prerequisite_course_id' => 'Prerequisite Course Name', 'program_id' => 'Program Name',
            'instructor_id' => 'Instructor Name', 'lead_instructor_id' => 'Lead Instructor Name',
            'mentor_id' => 'Mentor Name', 'module_id' => 'Module Name', 'lesson_id' => 'Lesson Name',
            'user_id' => 'User Name', default => Str::headline($field),
        };
    }

    /** Normalize generated detail labels as well as their values. */
    public static function field(array $field): array
    {
        $key = Str::snake(str_replace(' ', '', Str::studly($field['label'] ?? '')));
        $reference = match ($key) {
            'course_id', 'selected_course_id', 'prerequisite_course_id' => ['courses', 'title'],
            'program_id' => ['programs', 'title'], 'instructor_id', 'lead_instructor_id', 'mentor_id', 'user_id' => ['users', 'name'],
            'module_id' => ['course_modules', 'title'], 'lesson_id' => ['lessons', 'title'], default => null,
        };
        if ($reference) {
            $field['label'] = self::label($key);
            $field['value'] = !empty($field['value']) ? DB::table($reference[0])->where('id', $field['value'])->value($reference[1]) : null;
        }
        return $field;
    }
}
