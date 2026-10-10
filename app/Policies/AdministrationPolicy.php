<?php

namespace App\Policies;

use App\Models\InstructorRecord;
use App\Models\User;

/** Named abilities only; not a blanket policy for generic Record CRUD. */
final class AdministrationPolicy
{
    /** Preserves the existing MEL administrator/manager role gate. */
    public function accessMel(User $user): bool
    {
        return $user->manager();
    }

    /** Editor, save, background, and preview all remain administrator-only. */
    public function manageCertificateTemplate(User $user, InstructorRecord $course): bool
    {
        return $user->role === 'admin'
            && $course->getTable() === 'courses'
            && $course->exists;
    }

    /** Course/program assignment and lead changes share the same admin-only gate. */
    public function modifyInstructorAssignments(User $user, InstructorRecord $record): bool
    {
        return $user->role === 'admin'
            && in_array($record->getTable(), ['courses', 'programs'], true)
            && $record->exists;
    }
}
