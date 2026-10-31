<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class Enrollment
{
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
            DB::table('enrolments')->updateOrInsert(['user_id'=>$learnerId, 'course_id'=>$courseId], ['payment_confirmed_at'=>now(),'created_at'=>$existing?->created_at??now(), 'updated_at'=>now()]);
            DB::table('audit_logs')->insert(['user_id'=>$actorId, 'action'=>'confirm-payment', 'module'=>'learners', 'record_id'=>$learnerId, 'created_at'=>now(), 'updated_at'=>now()]);
            return true;
        });
    }

    /** A learner asks to join a course. A rejected request can be sent again. */
    public function request(int $learnerId, int $courseId, ?string $message=null): void
    {
        DB::transaction(function () use ($learnerId, $courseId, $message) {
            $learner = DB::table('users')->where('id', $learnerId)->where('role', 'participant')->lockForUpdate()->first();
            if (!$learner || !$learner->profile_complete) {
                throw ValidationException::withMessages(['course_id'=>'Complete your learner profile before requesting a course.']);
            }
            if (!DB::table('courses')->where('id', $courseId)->where('status', 'published')->exists()) {
                throw ValidationException::withMessages(['course_id'=>'This course is not available.']);
            }
            if (DB::table('enrolments')->where('user_id', $learnerId)->where('course_id', $courseId)->exists()) {
                throw ValidationException::withMessages(['course_id'=>'You are already enrolled in this course.']);
            }
            $existing = DB::table('enrollment_requests')->where('user_id', $learnerId)->where('course_id', $courseId)->first();
            if ($existing?->status === 'pending') {
                throw ValidationException::withMessages(['course_id'=>'Your request for this course is already waiting for review.']);
            }
            DB::table('enrollment_requests')->updateOrInsert(['user_id'=>$learnerId, 'course_id'=>$courseId], ['status'=>'pending', 'message'=>$message, 'decision_note'=>null, 'decided_by'=>null, 'decided_at'=>null, 'created_at'=>$existing?->created_at ?? now(), 'updated_at'=>now()]);
        });
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
