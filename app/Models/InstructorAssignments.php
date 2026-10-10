<?php

namespace App\Models;

use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

/** Explicit synchronization boundary, kept separate from the thin Record model. */
final class InstructorAssignments
{
    /**
     * Replace the complete membership set and lead atomically. Null clears the lead.
     * IDs must be positive integers; all must currently be active instructors.
     * Callers own authorization. No program-to-course or lead privileges are implied.
     * Returns the refreshed input record; clears cached relationships.
     */
    public static function sync(InstructorRecord $record, array $instructorIds, ?int $leadId = null): InstructorRecord
    {
        if (!$record->exists || $record->isDirty()) {
            throw new InvalidArgumentException('Sync requires a persisted, clean course/program record.');
        }
        foreach ($instructorIds as $id) {
            if (!is_int($id) || $id < 1) {
                throw ValidationException::withMessages(['instructor_ids' => 'Instructor IDs must be positive integers.']);
            }
        }
        $ids = array_values(array_unique($instructorIds));
        sort($ids, SORT_NUMERIC);
        if ($leadId !== null && !in_array($leadId, $ids, true)) {
            throw ValidationException::withMessages(['lead_instructor_id' => 'The lead must be an assigned instructor.']);
        }

        $record->getConnection()->transaction(function () use ($record, $ids, $leadId) {
            $parent = $record->newQuery()->whereKey($record->getKey())->lockForUpdate()->firstOrFail();
            $users = (new User)->setConnection($record->getConnectionName())->newQuery()
                ->whereIn('id', $ids)->orderBy('id')->lockForUpdate()->get(['id', 'role', 'status']);
            if ($users->count() !== count($ids) || $users->contains(fn (User $user) => $user->role !== 'instructor' || $user->status !== 'active')) {
                throw ValidationException::withMessages(['instructor_ids' => 'Every assigned user must be an active instructor.']);
            }

            // Delete first so membership removal cannot leave a dangling composite lead FK.
            $parent->instructorLeads()->detach();
            $parent->instructors()->sync($ids);
            if ($leadId !== null) {
                $parent->instructorLeads()->attach($leadId);
            }
            if ($parent->getTable() === 'courses') {
                $legacy = $parent->instructor_id;
                $parent->instructor_id = in_array($legacy, $ids, true) ? $legacy : ($ids[0] ?? null);
                $parent->save();
            }
        });

        return $record->unsetRelations()->refresh();
    }
}
