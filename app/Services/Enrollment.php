<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class Enrollment
{
    /** A course is finished once all its lessons and assessments are done, or a certificate was issued for it. */
    public static function finished(int $learnerId, int $courseId): bool
    {
        if (DB::table('course_certificates')->where('user_id', $learnerId)->where('course_id', $courseId)->exists()) return true;
        $learner = \App\Models\User::find($learnerId);
        return $learner !== null && LearningAccess::completionReady($learner, $courseId);
    }

    /**
     * The course a learner has to finish before they can choose another one themselves, or null when they are free to choose.
     * Learners who paid for access before enrolments were recorded keep their selected course until staff change it.
     */
    public static function blockingCourse(\App\Models\User $learner): ?object
    {
        $enrolments = DB::table('enrolments')->join('courses', 'courses.id', '=', 'enrolments.course_id')
            ->where('enrolments.user_id', $learner->id)->orderBy('enrolments.id')->get(['courses.id', 'courses.title']);
        if ($enrolments->isEmpty()) {
            return $learner->learning_access_paid && $learner->selected_course_id
                ? DB::table('courses')->where('id', $learner->selected_course_id)->first(['id', 'title'])
                : null;
        }
        return $enrolments->first(fn ($course) => !self::finished((int) $learner->id, (int) $course->id));
    }

    public function confirm(int $learnerId, int $cohortId, int $actorId, ?int $courseId=null): bool
    {
        return DB::transaction(function () use ($learnerId, $cohortId, $actorId, $courseId) {
            $learner = DB::table('users')->where('id', $learnerId)->where('role', 'participant')->lockForUpdate()->first();
            $courseId=$courseId??$learner?->selected_course_id;
            if (!$learner || !$learner->profile_complete || !$courseId) {
                throw ValidationException::withMessages(['enrollment'=>'The learner must have a completed profile and selected course.']);
            }
            $existing=DB::table('enrolments')->where('user_id',$learnerId)->where('course_id',$courseId)->first();
            if($existing?->payment_confirmed_at)return false;
            if (!DB::table('courses')->where('id', $courseId)->where('status', 'published')->exists()) {
                throw ValidationException::withMessages(['enrollment'=>'The learner selected course is no longer available.']);
            }
            $cohort = DB::table('mel_cohorts')->where('id', $cohortId)->where('active', true)->lockForUpdate()->first();
            if (!$cohort || !str_contains($cohort->learner_number_format, '{sequence}')) {
                throw ValidationException::withMessages(['enrollment'=>'Select an active cohort with a valid number format.']);
            }
            $this->ensureCohortFree($learnerId, $cohortId, 'enrollment', 'The learner is already taking %s in this cohort. Change that course instead, or choose another cohort.', $courseId);
            $sequence = (int) $cohort->next_sequence;
            $number=$learner->learner_no;
            if(!$number){
            do {
                $number = strtr($cohort->learner_number_format, ['{prefix}'=>$cohort->learner_number_prefix, '{cohort}'=>Str::upper(Str::slug($cohort->name, '-')), '{year}'=>now()->format('Y'), '{sequence}'=>str_pad((string)$sequence, (int)$cohort->sequence_padding, '0', STR_PAD_LEFT)]);
                if (!DB::table('users')->where('learner_no', $number)->exists()) break;
                $sequence++;
            } while (true);
            DB::table('mel_cohorts')->where('id', $cohortId)->update(['next_sequence'=>$sequence+1, 'updated_at'=>now()]);
            }
            DB::table('users')->where('id', $learnerId)->update(['cohort_id'=>$cohortId, 'learner_no'=>$number, 'enrollment_category'=>$cohort->enrollment_category, 'enrollment_date'=>today()->toDateString(), 'learner_status'=>'Active', 'learning_access_paid'=>true, 'updated_at'=>now()]);
            DB::table('enrolments')->updateOrInsert(['user_id'=>$learnerId, 'course_id'=>$courseId], ['cohort_id'=>$cohortId,'payment_confirmed_at'=>now(),'created_at'=>$existing?->created_at??now(), 'updated_at'=>now()]);
            DB::table('audit_logs')->insert(['user_id'=>$actorId, 'action'=>'confirm-payment', 'module'=>'learners', 'record_id'=>$learnerId, 'created_at'=>now(), 'updated_at'=>now()]);
            return true;
        });
    }

    /**
     * Enrolls a learner in the course they selected (at sign-up or on their profile), under their own cohort
     * when it is open, otherwise the newest open cohort. This runs when the profile is first complete and again
     * whenever a learner who has finished their course picks the next one. Returns [course title, null] on success,
     * or [null, reason] when it could not be done, so the learner can enroll from the course catalog instead.
     */
    public function enrollInSelectedCourse(\App\Models\User $learner): array
    {
        if ($learner->role !== 'participant' || !$learner->selected_course_id) return [null, null];
        if (DB::table('enrolments')->where('user_id', $learner->id)->where('course_id', $learner->selected_course_id)->exists()) return [null, null];
        $cohortId = ($learner->cohort_id ? DB::table('mel_cohorts')->where('id', $learner->cohort_id)->where('active', true)->value('id') : null)
            ?? DB::table('mel_cohorts')->where('active', true)->orderByDesc('id')->value('id');
        if (!$cohortId) return [null, 'There is no open cohort yet, so you could not be enrolled in your course. You can enroll from the course catalog once one opens.'];
        try {
            $this->selfEnroll((int) $learner->id, (int) $learner->selected_course_id, (int) $cohortId);
        } catch (ValidationException $e) {
            return [null, collect($e->errors())->flatten()->first()];
        }
        return [DB::table('courses')->where('id', $learner->selected_course_id)->value('title'), null];
    }

    /**
     * A learner enrolls themselves in a course under an active cohort.
     * A learner takes one unfinished course per cohort; only admins and instructors can change it before it is finished.
     * Once it is finished the learner can enroll in another course, in the same cohort or another.
     * Learners who have already paid for programme access start straight away; others get the usual trial.
     */
    public function selfEnroll(int $learnerId, int $courseId, int $cohortId): void
    {
        DB::transaction(function () use ($learnerId, $courseId, $cohortId) {
            $learner = DB::table('users')->where('id', $learnerId)->where('role', 'participant')->lockForUpdate()->first();
            if (!$learner || !$learner->profile_complete) {
                throw ValidationException::withMessages(['course_id'=>'Complete your learner profile before enrolling in a course.']);
            }
            $course = DB::table('courses')->where('id', $courseId)->where('status', 'published')->first();
            if (!$course) {
                throw ValidationException::withMessages(['course_id'=>'This course is not available.']);
            }
            if (!DB::table('mel_cohorts')->where('id', $cohortId)->where('active', true)->exists()) {
                throw ValidationException::withMessages(['cohort_id'=>'Choose an open cohort.']);
            }
            if (DB::table('enrolments')->where('user_id', $learnerId)->where('course_id', $courseId)->exists()) {
                throw ValidationException::withMessages(['course_id'=>'You are already enrolled in this course.']);
            }
            $this->ensureCohortFree($learnerId, $cohortId, 'cohort_id', 'You are still taking %s in this cohort. Finish it before enrolling in another course, or ask an administrator or instructor to change it.');
            if ($course->prerequisite_course_id && !LearningAccess::completionReady(\App\Models\User::find($learnerId), (int) $course->prerequisite_course_id)) {
                $title = DB::table('courses')->where('id', $course->prerequisite_course_id)->value('title');
                throw ValidationException::withMessages(['course_id'=>"Finish {$title} before enrolling in this course."]);
            }
            // The new course becomes the learner's current one when they have no other course still in progress.
            $current = self::blockingCourse(\App\Models\User::find($learnerId)) === null;
            $trial = $learner->learning_access_paid ? [] : ['trial_started_at'=>now(), 'trial_expires_at'=>now()->addHours(12)];
            DB::table('enrolments')->insert(['user_id'=>$learnerId, 'course_id'=>$courseId, 'cohort_id'=>$cohortId, 'created_at'=>now(), 'updated_at'=>now()] + $trial);
            if ($current) {
                DB::table('users')->where('id', $learnerId)->update(['selected_course_id'=>$courseId, 'learner_status'=>'Active', 'updated_at'=>now()]);
            }
            // Requests sent before self-enrollment existed are settled by enrolling.
            DB::table('enrollment_requests')->where('user_id', $learnerId)->where('course_id', $courseId)->where('status', 'pending')->update(['status'=>'approved', 'decided_at'=>now(), 'updated_at'=>now()]);
            DB::table('audit_logs')->insert(['user_id'=>$learnerId, 'action'=>'self-enroll', 'module'=>'learners', 'record_id'=>$learnerId, 'created_at'=>now(), 'updated_at'=>now()]);
        });
    }

    /** Throws when the learner already has an unfinished course (other than $exceptCourseId) in the cohort. */
    private function ensureCohortFree(int $learnerId, int $cohortId, string $field, string $message, ?int $exceptCourseId=null): void
    {
        $taken = DB::table('enrolments')->join('courses', 'courses.id', '=', 'enrolments.course_id')
            ->where('enrolments.user_id', $learnerId)->where('enrolments.cohort_id', $cohortId)
            ->when($exceptCourseId, fn($q)=>$q->where('enrolments.course_id', '!=', $exceptCourseId))
            ->get(['courses.id', 'courses.title'])
            ->first(fn ($course) => !self::finished($learnerId, (int) $course->id));
        if ($taken !== null) {
            throw ValidationException::withMessages([$field=>sprintf($message, $taken->title)]);
        }
    }

    /** Approving enrolls the learner in the requested course under the chosen cohort. */
    public function approve(int $requestId, int $cohortId, int $actorId): object
    {
        return DB::transaction(function () use ($requestId, $cohortId, $actorId) {
            $request = $this->pendingRequest($requestId);
            $this->confirm((int) $request->user_id, $cohortId, $actorId, (int) $request->course_id);
            DB::table('enrollment_requests')->where('id', $requestId)->update(['status'=>'approved', 'decided_by'=>$actorId, 'decided_at'=>now(), 'updated_at'=>now()]);
            DB::table('audit_logs')->insert(['user_id'=>$actorId, 'action'=>'approve-enrollment-request', 'module'=>'learners', 'record_id'=>$request->user_id, 'created_at'=>now(), 'updated_at'=>now()]);
            return $request;
        });
    }

    public function reject(int $requestId, int $actorId, ?string $note=null): object
    {
        return DB::transaction(function () use ($requestId, $actorId, $note) {
            $request = $this->pendingRequest($requestId);
            DB::table('enrollment_requests')->where('id', $requestId)->update(['status'=>'rejected', 'decision_note'=>$note, 'decided_by'=>$actorId, 'decided_at'=>now(), 'updated_at'=>now()]);
            DB::table('audit_logs')->insert(['user_id'=>$actorId, 'action'=>'reject-enrollment-request', 'module'=>'learners', 'record_id'=>$request->user_id, 'created_at'=>now(), 'updated_at'=>now()]);
            return $request;
        });
    }

    /** Moves a learner's enrolment (and its payment/trial status) from one course to another. */
    public function changeCourse(int $learnerId, int $fromCourseId, int $toCourseId, int $actorId): void
    {
        DB::transaction(function () use ($learnerId, $fromCourseId, $toCourseId, $actorId) {
            $enrolment = DB::table('enrolments')->where('user_id', $learnerId)->where('course_id', $fromCourseId)->lockForUpdate()->first();
            abort_unless($enrolment && DB::table('users')->where('id', $learnerId)->where('role', 'participant')->exists(), 404);
            if ($fromCourseId === $toCourseId) {
                throw ValidationException::withMessages(['course_id'=>'Choose a different course.']);
            }
            if (!DB::table('courses')->where('id', $toCourseId)->where('status', 'published')->exists()) {
                throw ValidationException::withMessages(['course_id'=>'Choose a published course.']);
            }
            if (DB::table('enrolments')->where('user_id', $learnerId)->where('course_id', $toCourseId)->exists()) {
                throw ValidationException::withMessages(['course_id'=>'The learner is already enrolled in that course.']);
            }
            if (DB::table('course_certificates')->where('user_id', $learnerId)->where('course_id', $fromCourseId)->exists()) {
                throw ValidationException::withMessages(['course_id'=>'The learner already has a certificate for this course, so it cannot be changed.']);
            }
            DB::table('enrolments')->where('id', $enrolment->id)->update(['course_id'=>$toCourseId, 'updated_at'=>now()]);
            DB::table('users')->where('id', $learnerId)->update(['selected_course_id'=>$toCourseId, 'updated_at'=>now()]);
            DB::table('enrollment_requests')->where('user_id', $learnerId)->where('course_id', $toCourseId)->where('status', 'pending')->update(['status'=>'approved', 'decided_by'=>$actorId, 'decided_at'=>now(), 'updated_at'=>now()]);
            DB::table('audit_logs')->insert(['user_id'=>$actorId, 'action'=>'change-course', 'module'=>'learners', 'record_id'=>$learnerId, 'created_at'=>now(), 'updated_at'=>now()]);
        });
    }

    private function pendingRequest(int $requestId): object
    {
        $request = DB::table('enrollment_requests')->where('id', $requestId)->lockForUpdate()->first();
        abort_unless($request, 404);
        if ($request->status !== 'pending') {
            throw ValidationException::withMessages(['enrollment'=>'This request has already been '.$request->status.'.']);
        }
        return $request;
    }

    public function import(string $path, ?int $defaultCohort, int $actorId): array
    {
        $handle = fopen($path, 'rb');
        if (!$handle) throw ValidationException::withMessages(['csv'=>'The CSV could not be read.']);
        $rows = []; $errors = []; $seen = []; $line = 1;
        try {
            $header = fgetcsv($handle, 0, ',', '"', '');
            if (!$header) throw ValidationException::withMessages(['csv'=>'The CSV is empty.']);
            $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', $header[0]);
            $header = array_map(fn($value)=>strtolower(trim($value)), $header);
            if ($header !== ['email', 'cohort_id']) throw ValidationException::withMessages(['csv'=>'Use the template headers: email,cohort_id.']);
            while (($values = fgetcsv($handle, 0, ',', '"', '')) !== false) {
                $line++;
                if (count($values)===1 && trim((string)$values[0])==='') continue;
                if ($line > 1001) throw ValidationException::withMessages(['csv'=>'Import at most 1,000 learners at a time.']);
                if (count($values)!==2) {$errors[]="Row $line: expected two columns.";continue;}
                $email = strtolower(trim($values[0])); $cohort = trim($values[1]);
                if (!filter_var($email, FILTER_VALIDATE_EMAIL) || isset($seen[$email])) {$errors[]="Row $line: invalid or duplicate email.";continue;}
                $seen[$email] = true;
                if ($cohort!=='' && (!ctype_digit($cohort) || (int)$cohort<1)) {$errors[]="Row $line: cohort_id must be a positive number.";continue;}
                $cohortId = $cohort!=='' ? (int)$cohort : $defaultCohort;
                $learner = DB::table('users')->whereRaw('LOWER(email) = ?', [$email])->where('role','participant')->first();
                if (!$learner || !$learner->profile_complete || !$learner->selected_course_id) {$errors[]="Row $line: learner not found or profile/course selection incomplete.";continue;}
                if (!$cohortId || !DB::table('mel_cohorts')->where('id',$cohortId)->where('active',true)->exists()) {$errors[]="Row $line: select an active cohort or enter its ID.";continue;}
                if (!$learner->learning_access_paid && !DB::table('courses')->where('id',$learner->selected_course_id)->where('status','published')->exists()) {$errors[]="Row $line: selected course is not published.";continue;}
                $rows[] = ['learner'=>(int)$learner->id,'cohort'=>$cohortId];
            }
        } finally {fclose($handle);}
        if ($errors) throw ValidationException::withMessages(['csv'=>array_slice($errors,0,25)]);
        if (!$rows) throw ValidationException::withMessages(['csv'=>'Add at least one learner to the CSV.']);
        return DB::transaction(function () use ($rows,$actorId) {
            $enrolled=0;
            foreach ($rows as $row) if ($this->confirm($row['learner'],$row['cohort'],$actorId)) $enrolled++;
            return ['enrolled'=>$enrolled,'skipped'=>count($rows)-$enrolled];
        });
    }
}
