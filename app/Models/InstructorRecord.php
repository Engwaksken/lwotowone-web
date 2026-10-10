<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use InvalidArgumentException;

/** Course/program specialization; all other modules remain generic Records. */
class InstructorRecord extends Record
{
    protected $fillable = [];
    protected $guarded = ['*'];

    public function setTable($table)
    {
        $this->fillable = match ($table) {
            'courses' => ['title', 'program_id', 'instructor_id', 'prerequisite_course_id', 'summary', 'level', 'duration_hours', 'status', 'created_at', 'updated_at'],
            'programs' => ['title', 'slug', 'category', 'summary', 'body', 'status', 'created_at', 'updated_at'],
            default => throw new InvalidArgumentException('InstructorRecord supports only courses and programs.'),
        };

        return parent::setTable($table);
    }

    protected function casts(): array
    {
        return ['created_at' => 'datetime', 'updated_at' => 'datetime', 'program_id' => 'integer', 'instructor_id' => 'integer', 'prerequisite_course_id' => 'integer', 'duration_hours' => 'integer'];
    }

    public function instructorParentKey(): string
    {
        return $this->getTable() === 'courses' ? 'course_id' : 'program_id';
    }

    /** Raw persisted memberships (including inactive historical assignments). */
    public function instructors(): BelongsToMany
    {
        $entity = $this->getTable() === 'courses' ? 'course' : 'program';

        return $this->belongsToMany(User::class, $entity.'_instructors', $entity.'_id', 'user_id')->withTimestamps();
    }

    /** Zero or one persisted lead; not an authorization relationship. */
    public function instructorLeads(): BelongsToMany
    {
        $entity = $this->getTable() === 'courses' ? 'course' : 'program';

        return $this->belongsToMany(User::class, $entity.'_instructor_leads', $entity.'_id', 'user_id')->withTimestamps();
    }

    public function legacyInstructor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'instructor_id');
    }

    /** Active instructor membership only; program queries never include courses. */
    public function scopeAssignedToInstructor(Builder $query, int $userId, bool $allowLegacy = false): Builder
    {
        return $query->where(function (Builder $memberships) use ($userId, $allowLegacy) {
            $memberships->whereHas('instructors', fn (Builder $users) => $users
                ->where('users.id', $userId)->where('role', 'instructor')->where('status', 'active'));

            // Opt-in for pre-membership fixtures only. Never enable for authoritative empty assignments.
            if ($allowLegacy && $this->getTable() === 'courses') {
                $memberships->orWhere(function (Builder $legacy) use ($userId) {
                    $legacy->whereDoesntHave('instructors')->whereHas('legacyInstructor', fn (Builder $users) => $users
                        ->where('users.id', $userId)->where('role', 'instructor')->where('status', 'active'));
                });
            }
        });
    }

    public function hasAssignedInstructor(int $userId, bool $allowLegacy = false): bool
    {
        return $this->exists && $this->newQuery()->whereKey($this->getKey())
            ->assignedToInstructor($userId, $allowLegacy)->exists();
    }

    /** Fresh persisted IDs; legacy fallback is explicit, never cached or inferred from lead. */
    public function assignedInstructorIds(bool $allowLegacy = false): array
    {
        if (!$this->exists) {
            return [];
        }
        $ids = $this->instructors()->orderBy('users.id')->pluck('users.id')->map(fn ($id) => (int) $id)->all();
        if ($ids === [] && $allowLegacy && $this->getTable() === 'courses') {
            $legacy = $this->newQuery()->whereKey($this->getKey())->value('instructor_id');
            return $legacy === null ? [] : [(int) $legacy];
        }
        return $ids;
    }

    /** Safe projection only: no User serialization, emails, profile data, or pivots. */
    public function resolvedInstructorData(bool $allowLegacy = false): array
    {
        $instructors = User::query()->whereIn('id', $this->assignedInstructorIds($allowLegacy))
            ->orderBy('id')->get(['id', 'name'])->map(fn (User $user) => ['id' => (int) $user->id, 'name' => $user->name])->all();
        $leadId = $this->exists ? $this->instructorLeads()->value('users.id') : null;
        $lead = null;
        foreach ($instructors as $instructor) {
            if ($instructor['id'] === (int) $leadId) {
                $lead = $instructor;
            }
        }
        return ['instructors' => $instructors, 'lead' => $lead];
    }
}
