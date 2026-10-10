<?php

namespace App\Policies;

use App\Models\InstructorRecord;
use App\Models\User;

/** Explicit course abilities; no program membership, lead, or legacy privileges. */
final class CoursePolicy
{
    public function view(User $user, InstructorRecord $course): bool
    {
        return $this->isCourse($course)
            && ($user->manager() || $this->isAssignedInstructor($user, $course));
    }

    public function manage(User $user, InstructorRecord $course): bool
    {
        return $this->view($user, $course);
    }

    /** Staff access only; participant learning-access rules remain separate. */
    public function readResource(User $user, InstructorRecord $course): bool
    {
        return $this->view($user, $course);
    }

    /** The caller resolves the submission's assignment to its persisted course. */
    public function reviewSubmissions(User $user, InstructorRecord $course): bool
    {
        return $this->view($user, $course);
    }

    /** Intentionally no administrator/manager bypass. */
    public function recommendCertificate(User $user, InstructorRecord $course): bool
    {
        return $this->isCourse($course) && $this->isAssignedInstructor($user, $course);
    }

    /** Covers both membership replacement and lead changes, not ordinary edits. */
    public function modifyAssignments(User $user, InstructorRecord $course): bool
    {
        return $this->isCourse($course) && $user->role === 'admin';
    }

    private function isCourse(InstructorRecord $course): bool
    {
        return $course->getTable() === 'courses' && $course->exists;
    }

    private function isAssignedInstructor(User $user, InstructorRecord $course): bool
    {
        return $user->role === 'instructor'
            && $user->status === 'active'
            && $course->hasAssignedInstructor((int) $user->getKey(), false);
    }
}
